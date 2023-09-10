# sgkbot
AN interesting project
Sgk 机器人分为前端和后端两部分
后端启动方法：
1.ssh 连接上服务器
ip：134.195.91.222
用户名：root
密码：NbvyKhpdKz3P
2.进入后端文件所在目录
命令：cd webman/
3.启动后端
命令：php start.php start -d
重启命令：php start.php restart
关闭命令：php start.php stop

前端启动方法：
1. 登录服务器宝塔面板：
http://13.19.91.22:8888/login
第一层用户名 123 密码 111
第二层用户名 sgk 密码 sgktestserver
一般只要 nginx 服务运行正常，前端文件正常，无需维护

修改说明：
1. 登录宝塔面板：详见其它维护文档
2. 打开文件界面，并进入/www/wwwroot/justchat.baby 目录，详见下图
3. 
4. 编辑 aKeXNSwRtdCicXFM.php 这个文件（双击文件编辑）
5.
6.  6. 找到/start、/manual 等命令，修改对应位置下面 return 后面双引号之中的文字，即可修
改命令回复，同理，也可添加其它命令，修改完成以后点击左上角保存文件，无需重启
sgk 机器人，立即生效。
