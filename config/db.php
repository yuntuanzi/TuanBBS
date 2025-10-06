<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'xihui');
define('DB_USER', 'xihui');
define('DB_PASS', 'MKbieAd1PdNKzwW7');
define('DB_CHARSET', 'utf8mb4');

define('REDIS_HOST', '127.0.0.1');
define('REDIS_PORT', 6379);
define('REDIS_PASS', '');
define('REDIS_PREFIX', 'xihui_');
define('VERIFY_CODE_EXPIRE', 300);

// 邮件配置
define('MAIL_HOST', 'smtp.qiye.aliyun.com');
define('MAIL_PORT', 465);
define('MAIL_USERNAME', 'yun@yuncheng.fun'); // 发件QQ邮箱
define('MAIL_PASSWORD', 'aHPUJUWgcMBO0ZzK'); // 邮箱授权码，不是登录密码
define('MAIL_FROM_NAME', '喜灰论坛'); // 发件人昵称
define('MAIL_DEBUG', 0); // 0=关闭, 1=客户端消息, 2=客户端和服务端消息