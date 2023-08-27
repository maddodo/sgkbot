<?php
try {
    $body = json_decode(file_get_contents("php://input"), true);
    //file_put_contents('errlog.log', file_get_contents("php://input")."\n", FILE_APPEND | LOCK_EX);
    class Sql
    {
        public $link;

        public function __construct()
        {
            $DbConfig = [
                'host' => 'localhost',
                'port' => 3306,
                'user' => 'dosbot',
                'pwd' => 'xxx',
                'name' => 'dosbot',
                'long' => false,
            ];
            if (empty($DbConfig['port'])) $DbConfig['port'] = 3306;
            $this->link = mysqli_connect($DbConfig['host'], $DbConfig['user'], $DbConfig['pwd'], $DbConfig['name'], $DbConfig['port']);
            for ($x = 0; !$this->link; $x++) {
                if ($x > 3) exit(mysqli_connect_error());
                $this->link = mysqli_connect($DbConfig['host'], $DbConfig['user'], $DbConfig['pwd'], $DbConfig['name'], $DbConfig['port']);
            }
        }

        //链接数据库
        public function conn()
        {
            $this->query('set names utf8');
        }

        public function fetch($q)
        {
            return mysqli_fetch_assoc($q);
        }

        public function count($q)
        {
            $result = mysqli_query($this->link, $q);
            $count = mysqli_fetch_array($result);
            return $count[0];
        }

        public function query($q)
        {
            return mysqli_query($this->link, $q);
        }

        public function escape($str)
        {
            return mysqli_real_escape_string($this->link, $str);
        }

        public function affected()
        {
            return mysqli_affected_rows($this->link);
        }


        //查询数据
        public function get_row($sql)
        {
            $res = $this->query($sql);
            if (!$res) return false;
            return mysqli_fetch_assoc($res);
        }


        //查询全部数据
        public function getAll($sql)
        {
            $res = $this->query($sql);
            while ($row =  mysqli_fetch_assoc($res)) {
                $data[] = $row;
            }
            return $data;
        }

        //报错信息
        public function error()
        {
            return mysqli_error($this->link);
        }

        //关闭数据库链接
        public function close()
        {
            return mysqli_close($this->link);
        }
    }

    class PhoneLocation
    {
        const DATA_FILE = __DIR__ . '/phone.dat';
        protected static $spList = [1 => '移动', 2 => '联通', 3 => '电信', 4 => '电信虚拟运营商', 5 => '联通虚拟运营商', 6 => '移动虚拟运营商'];
        private $_fileHandle = null;
        private $_fileSize = 0;

        public function __construct()
        {
            $this->_fileHandle = fopen(self::DATA_FILE, 'r');
            $this->_fileSize = filesize(self::DATA_FILE);
        }

        /**
         * 查找单个手机号码归属地信息
         * @param  int $phone
         * @return array
         * @author shitoudev <shitoudev@gmail.com>
         */
        public function find($phone)
        {
            $item = [];
            if (strlen($phone) != 11) {
                return $item;
            }
            $telPrefix = substr($phone, 0, 7);

            fseek($this->_fileHandle, 4);
            $offset = fread($this->_fileHandle, 4);
            $indexBegin = implode('', unpack('L', $offset));
            $total = ($this->_fileSize - $indexBegin) / 9;

            $position = $leftPos = 0;
            $rightPos = $total;

            while ($leftPos < $rightPos - 1) {
                $position = $leftPos + (($rightPos - $leftPos) >> 1);
                fseek($this->_fileHandle, ($position * 9) + $indexBegin);
                $idx = implode('', unpack('L', fread($this->_fileHandle, 4)));
                // echo 'position = '.$position.' idx = '.$idx;
                if ($idx < $telPrefix) {
                    $leftPos = $position;
                } elseif ($idx > $telPrefix) {
                    $rightPos = $position;
                } else {
                    // 找到数据
                    fseek($this->_fileHandle, ($position * 9 + 4) + $indexBegin);
                    $itemIdx = unpack('Lidx_pos/ctype', fread($this->_fileHandle, 5));
                    $itemPos = $itemIdx['idx_pos'];
                    $type = $itemIdx['type'];
                    fseek($this->_fileHandle, $itemPos);
                    $itemStr = '';
                    while (($tmp = fread($this->_fileHandle, 1)) != chr(0)) {
                        $itemStr .= $tmp;
                    }
                    $item = $this->phoneInfo($itemStr, $type);
                    break;
                }
            }
            return $item;
        }

        /**
         * 解析归属地信息
         * @param  string $itemStr
         * @param  int $type
         * @return array
         * @author shitoudev <shitoudev@gmail.com>
         */
        private function phoneInfo($itemStr, $type)
        {
            $typeStr = self::$spList[$type];
            $itemArr = explode('|', $itemStr);
            $data = ['province' => $itemArr[0], 'city' => $itemArr[1], 'postcode' => $itemArr[2], 'tel_prefix' => $itemArr[3], 'sp' => $typeStr];
            return $data;
        }

        public function __destruct()
        {
            fclose($this->_fileHandle);
        }
    }


    /**
     * TG机器人 熊猫社工库
     */
    class bot
    {
        public $url;
        public $body;
        /**
         * 
         */
        public function __construct($body)
        {
            $token = '5412220534:AAHIvX3PLQx1jcksFy6pRRpSFx7l9SFgb84';
            $this->url = 'https://api.telegram.org/bot' . $token . '/';

            $this->body = $body;
        }

        private function post($post)
        {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            $output = curl_exec($ch);
            curl_close($ch);
            return $output;
        }

        public function sendmsg($msg)
        {
            $data = [
                "method" => "sendMessage",
                "chat_id" => $this->body['message']['chat']['id'],
                "text" => $msg,
                "reply_to_message_id" => $this->body['message']['message_id'],
                "allow_sending_without_reply" => 'true'
            ];
            return json_decode($this->post($data),true);
        }

        public function delmsg($msg)
        {
            $data = [
                "method" => "deleteMessage",
                "chat_id" => $this->body['message']['chat']['id'],
                "message_id" => $msg
            ];
            $re = $this->post($data);
            return true;
        }
    }

    function startwith($str, $pattern)
    {
        if (preg_match('/' . $pattern . '(.*)/', $str, $arr) > 0) return $arr;
        //if(stripos($str,$pattern) === 0) return true;//去掉i即大小写敏感
        else return false;
    }

    function txtmatch($txt)
    {
        if ($txt == "/start") {
            return "关注频道 @ChinaSGK 即可免费查询 防止封号 获取最新更新";
        }
        if ($txt == "/manual") {
            return "关注频道 @ChinaSGK 即可免费查询 防止封号 获取最新更新";
        }
        if ($txt == "/donate") {
            return "关注频道 @ChinaSGK 即可免费查询 防止封号 获取最新更新";
        }
        if ($txt == "/contact") {
            return "关注频道 @ChinaSGK 即可免费查询 防止封号 获取最新更新";
        }
        if (is_numeric($txt)) {
            if (strlen($txt) == 11){
            $mobnum = file_get_contents('http://134.195.91.222/num?num=' . $txt);
            $json1 = json_decode($mobnum, true);
            $data1 = $json1[0];
            $username = $data1["username"] ?? "查询结果为空 关注频道更新 永不迷路: @ChinaSGK";
            $mobile = $data1["mobile"] ?? "查询结果为空 关注频道更新 永不迷路: @ChinaSGK";
            $phone = file_get_contents('http://134.195.91.222/phone?phone=' . $txt);
            $json2 = json_decode($phone, true);
            $data2 = $json2[0];
            $mail = $data2["邮箱"] ?? "查询结果为空 关注频道更新 永不迷路: @ChinaSGK";
            $xm = $data2["姓名"] ?? "查询结果为空 关注频道更新 永不迷路: @ChinaSGK";
            $sfz = $data2["身份证"] ?? "查询结果为空 关注频道更新 永不迷路: @ChinaSGK";
            $zh1 = $data2["账号1"] ?? "查询结果为空 关注频道更新 永不迷路: @ChinaSGK";
            $zh2 = $data2["账号2"] ?? "查询结果为空 关注频道更新 永不迷路: @ChinaSGK";
            $sj = $data2["手机号"] ?? "查询结果为空 关注频道更新 永不迷路: @ChinaSGK";
            $dz = $data2["地址"] ?? "查询结果为空 关注频道更新 永不迷路: @ChinaSGK";
            $wbmob = file_get_contents('http://134.195.91.222/weibop?p=' . $txt);
            $json3 = json_decode($wbmob, true);
            $data3 = $json3[0];
            $wbuid = $data3["uid"] ?? "查询结果为空 关注频道更新 永不迷路: @ChinaSGK";
            $wbpho = $data2["phonw"] ?? "查询结果为空 关注频道更新 永不迷路: @ChinaSGK";
            return "邮箱：${mail}\n姓名：${xm}\n身份证：${sfz}\n账号1：${zh1}\n账号2：${zh2}\n手机号：${sj}\n地址：${dz}\nQQ号：${username}\n手机号：${mobile}\n微博uid：${wbuid}\n手机号：${wbpho}";
            }
            if (strlen($txt) == 7 || strlen($txt) == 8 || strlen($txt) == 9 || strlen($txt) == 10){
            $qqnum = file_get_contents('http://134.195.91.222/saomiaoqusi?qq=' . $txt);
            $json1 = json_decode($qqnum, true);
            $data1 = $json1[0];
            $username = $data1["username"] ?? "查询结果为空 关注频道更新 永不迷路: @ChinaSGK询";
            $mobile1 = $data1["mobile"] ?? "查询结果为空 关注频道更新 永不迷路: @ChinaSGK";
            $bili = file_get_contents('http://134.195.91.222/bili?uid=' . $txt);
            $json2 = json_decode($bili, true);
            $data2 = $json2[0];
            $uid = $data2["uid"] ?? "查询结果为空 关注频道更新 永不迷路: @ChinaSGK";
            $mobile2 = $data2["phone"] ?? "查询结果为空 关注频道更新 永不迷路: @ChinaSGK";
            $uin = file_get_contents('http://134.195.91.222/lol?uin=' . $txt);
            $json3 = json_decode($uin, true);
            $data3 = $json3[0];
            $username = $data3["uin"] ?? "查询结果为空 关注频道更新 永不迷路: @ChinaSGK";
            $name = $data3["name"] ?? "查询结果为空 关注频道更新 永不迷路: @ChinaSGK";
            $area = $data3["area"] ?? "查询结果为空 关注频道更新 永不迷路: @ChinaSGK";
            $wbnum = file_get_contents('http://134.195.91.222/wb?uid=' . $txt);
            $json4 = json_decode($wbnum, true);
            $data4 = $json4[0];
            $wbuid = $data4["uid"] ?? "查询结果为空 关注频道更新 永不迷路: @ChinaSGK";
            $wbpho = $data4["phone"] ?? "查询结果为空 关注频道更新 永不迷路: @ChinaSGK";
            return "bilibili uid：${uid}\n手机号：${mobile2}\nQQ号：${username}\n手机号：${mobile1}\nLOL uin：${username}\n昵称：${name}\n地区：${area}\n微博uid：${wbuid}\n手机号：${wbpho}";
            }
            return "很抱歉，您要查找的数据暂未收录。\n请检查输入的数字是否为以下支持类型：\n手机号、bilibili uid、LOL uin、QQ号、微博号";
        }
        if (preg_match("/[\x7f-\xff]/", $txt)) {
            $name = file_get_contents('http://134.195.91.222/name?name=' . $txt);
            $json = json_decode($name, true);
            $data = $json[0];
            $mail = $data["邮箱"] ?? "查询结果为空 关注频道更新 永不迷路: @ChinaSGK";
            $xm = $data["姓名"] ?? "查询结果为空 关注频道更新 永不迷路: @ChinaSGK";
            $sfz = $data["身份证"] ?? "查询结果为空 关注频道更新 永不迷路: @ChinaSGK";
            $zh1 = $data["账号1"] ?? "查询结果为空 关注频道更新 永不迷路: @ChinaSGK";
            $zh2 = $data["账号2"] ?? "查询结果为空 关注频道更新 永不迷路: @ChinaSGK";
            $sj = $data["手机号"] ?? "查询结果为空 关注频道更新 永不迷路: @ChinaSGK";
            $dz = $data["地址"] ?? "查询结果为空 关注频道更新 永不迷路: @ChinaSGK";
            return "邮箱：${mail}\n姓名：${xm}\n身份证：${sfz}\n账号1：${zh1}\n账号2：${zh2}\n手机号：${sj}\n地址：${dz}";
            }else return "检测到您的输入为：$txt\n请不要输入无意义的查询";
            
        if (empty($txt)) return false;

        preg_match('/^(菜单|查B绑|查Q绑|查绑Q|查微博|反查微博|查LOL|反查LOL|姓名猎魔|电话猎魔)(?:\s*)(.*)/ix', $txt, $arr);

        if ($arr[1] == '菜单') return "目前支持：\n【QQ绑定手机】【微博绑定手机】\n【LOL绑定】【姓名猎魔】\n【电话猎魔】【bilibili绑定】\n\n**指令列表**\n查Q绑  查绑Q\n查微博 反查微博\n查LOL 查B绑\n姓名猎魔 电话猎魔";

        if (strcasecmp($arr[1],'查B绑') == 0) {
            $bili = file_get_contents('http://134.195.91.222/bili?uid=' . $arr[2]);
            $json = json_decode($bili, true);
            $data = $json[0];
            $uid = $data["uid"] ?? "";
            $mobile = $data["phone"] ?? '110';
            return "bilibili uid：${uid}\n手机号：${mobile}";
        }
        if (strcasecmp($arr[1],'查Q绑') == 0) {
            $qqnum = file_get_contents('http://134.195.91.222/saomiaoqusi?qq=' . $arr[2]);
            $json = json_decode($qqnum, true);
            $data = $json[0];
            $username = $data["username"] ?? "";
            $mobile = $data["mobile"] ?? '110';
            return "QQ号：${username}\n手机号：${mobile}";
        }
        if (strcasecmp($arr[1],'查绑Q') == 0) {
            $mobnum = file_get_contents('http://134.195.91.222/num?num=' . $arr[2]);
            $json = json_decode($mobnum, true);
            $data = $json[0];
            $username = $data["username"] ?? "";
            $mobile = $data["mobile"] ?? '110';
            return "QQ号：${username}\n手机号：${mobile}";
        }
        if ($arr[1] == '查微博') {
            $mobnum = file_get_contents('http://134.195.91.222/wb?uid=' . $arr[2]);
            $json = json_decode($mobnum, true);
            $data = $json[0];
            $username = $data["uid"] ?? "";
            $mobile = $data["phone"] ?? '110';
            return "微博号：${username}\n手机号：${mobile}";
        }
        if ($arr[1] == '反查微博') {
            $mobnum = file_get_contents('http://134.195.91.222/wbwb?num=' . $arr[2]);
            $json = json_decode($mobnum, true);
            $data = $json[0];
            $username = $data["uid"] ?? "";
            $mobile = $data["phone"] ?? '110';
            return "微博号：${username}\n手机号：${mobile}";
        }
        if (strcasecmp($arr[1],'查LOL') == 0) {
            $uin = file_get_contents('http://134.195.91.222/lol?uin=' . $arr[2]);
            $json = json_decode($uin, true);
            $data = $json[0];
            $username = $data["uin"] ?? "";
            $name = $data["name"] ?? '110';
            $area = $data["area"] ?? "";
            return "uin：${username}\n昵称：${name}\n地区：${area}";
        }
        if ($arr[1] == '姓名猎魔') {
            $name = file_get_contents('http://134.195.91.222/name?name=' . $arr[2]);
            $json = json_decode($name, true);
            $data = $json[0];
            $mail = $data["邮箱"] ?? "";
            $xm = $data["姓名"] ?? "";
            $sfz = $data["身份证"] ?? "";
            $zh1 = $data["账号1"] ?? "";
            $zh2 = $data["账号2"] ?? "";
            $sj = $data["手机号"] ?? "";
            $dz = $data["地址"] ?? "";
            return "邮箱：${mail}\n姓名：${xm}\n身份证：${sfz}\n账号1：${zh1}\n账号2：${zh2}\n手机号：${sj}\n地址：${dz}";
        }
        if ($arr[1] == '电话猎魔') {
            $phone = file_get_contents('http://134.195.91.222/phone?phone=' . $arr[2]);
            $json = json_decode($phone, true);
            $data = $json[0];
            $mail = $data["邮箱"] ?? "";
            $xm = $data["姓名"] ?? "";
            $sfz = $data["身份证"] ?? "";
            $zh1 = $data["账号1"] ?? "";
            $zh2 = $data["账号2"] ?? "";
            $sj = $data["手机号"] ?? "";
            $dz = $data["地址"] ?? "";
            return "邮箱：${mail}\n姓名：${xm}\n身份证：${sfz}\n账号1：${zh1}\n账号2：${zh2}\n手机号：${sj}\n地址：${dz}";
        }
    }



    #####正题#####
    if (!empty($body['message']['text'])) { //文本消息
        $send = txtmatch($body['message']['text']);
        
        if ($send) {
            $bot = new bot($body);
            $re = $bot->sendmsg($send);
            
            /*
            fastcgi_finish_request();
            set_time_limit(650);
            sleep(30);
            $senddel = $bot->delmsg($bot->body['message']['message_id']);
            sleep(600);
            $redel = $bot->delmsg($re['result']['message_id']);*/
        }
    } elseif (!empty($body['message']['sticker'])) { //贴图
    //throw new Exception(json_encode($body));


    } elseif (!empty($body['message']['photo'])) { //图片
    //throw new Exception(json_encode($body));


    } else {
    //throw new Exception(json_encode($body));
        
    }
} catch (Exception $e) {
    file_put_contents('errlog.log', $e->getMessage()."\n", FILE_APPEND | LOCK_EX);
}
