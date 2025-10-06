<?php
$steps = [
    1 => '环境检测',
    2 => '数据库信息',
    3 => '站点信息',
    4 => '管理员信息',
    5 => '发件邮箱信息',
    6 => '安装完成'
];

if (file_exists('installed.lock')) {
    die('系统已安装，如果需要重新安装，请删除 installed.lock 文件');
}

// 初始化会话
session_start();

// 处理步骤提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentStep = isset($_POST['step']) ? (int)$_POST['step'] : 1;
    
    // 保存当前步骤数据到会话
    $_SESSION['install_data'][$currentStep] = $_POST;
    
    // 处理特殊步骤
    if ($currentStep == 1) {
        // 环境检测通过，进入下一步
        header("Location: install.php?step=2");
        exit;
    } elseif ($currentStep == 5) {
        // 处理邮箱信息（可能跳过）
        if (isset($_POST['skip_email'])) {
            $_SESSION['install_data'][5]['skipped'] = true;
        }
        // 执行安装
        $installResult = performInstallation($_SESSION['install_data']);
        if ($installResult === true) {
            header("Location: install.php?step=6");
            exit;
        } else {
            $error = $installResult;
        }
    } elseif ($currentStep == 4) {
        // 从管理员信息到邮箱信息
        header("Location: install.php?step=5");
        exit;
    } elseif ($currentStep == 3) {
        // 从站点信息到管理员信息
        header("Location: install.php?step=4");
        exit;
    } elseif ($currentStep == 2) {
        // 验证数据库连接
        $dbHost = $_POST['db_host'];
        $dbName = $_POST['db_name'];
        $dbUser = $_POST['db_user'];
        $dbPass = $_POST['db_pass'];
        
        try {
            $pdo = new PDO("mysql:host=$dbHost;charset=utf8mb4", $dbUser, $dbPass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // 检查数据库是否存在，不存在则创建
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            
            // 连接到指定数据库
            $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);
            
            header("Location: install.php?step=3");
            exit;
        } catch (PDOException $e) {
            $error = "数据库连接失败: " . $e->getMessage();
        }
    }
}

// 当前步骤
$currentStep = isset($_GET['step']) ? (int)$_GET['step'] : 1;
if ($currentStep < 1 || $currentStep > 6) {
    $currentStep = 1;
}

function checkEnvironment() {
    $results = [
        'php_version' => [
            'name' => 'PHP 版本 (8.1+)',
            'current' => phpversion(),
            'passed' => version_compare(phpversion(), '8.1', '>=')
        ],
        'mysql_extension' => [
            'name' => 'MySQL 扩展',
            'current' => extension_loaded('pdo_mysql') ? '已安装' : '未安装',
            'passed' => extension_loaded('pdo_mysql')
        ],
        'config_writable' => [
            'name' => 'config 目录可写',
            'current' => is_writable('config') ? '可写' : '不可写',
            'passed' => is_writable('config')
        ]
    ];
    
    // 检查是否所有检测都通过
    $allPassed = true;
    foreach ($results as $result) {
        if (!$result['passed']) {
            $allPassed = false;
            break;
        }
    }
    
    return [
        'results' => $results,
        'all_passed' => $allPassed
    ];
}

// 执行安装函数
function performInstallation($data) {
    // 提取安装数据
    $dbData = $data[2];
    $siteData = $data[3];
    $adminData = $data[4];
    $emailData = isset($data[5]) ? $data[5] : [];
    
    // 1. 创建数据库配置文件
    $dbConfigContent = "<?php\n";
    $dbConfigContent .= "define('DB_HOST', '{$dbData['db_host']}');\n";
    $dbConfigContent .= "define('DB_NAME', '{$dbData['db_name']}');\n";
    $dbConfigContent .= "define('DB_USER', '{$dbData['db_user']}');\n";
    $dbConfigContent .= "define('DB_PASS', '{$dbData['db_pass']}');\n";
    $dbConfigContent .= "define('DB_CHARSET', 'utf8mb4');\n\n";
    
    // Redis 配置
    $dbConfigContent .= "define('REDIS_HOST', '{$dbData['redis_host']}');\n";
    $dbConfigContent .= "define('REDIS_PORT', {$dbData['redis_port']});\n";
    $dbConfigContent .= "define('REDIS_PASS', '{$dbData['redis_pass']}');\n";
    $dbConfigContent .= "define('REDIS_PREFIX', '{$dbData['redis_prefix']}');\n";
    $dbConfigContent .= "define('VERIFY_CODE_EXPIRE', 300);\n\n";
    
    // 邮件配置
    $dbConfigContent .= "// 邮件配置\n";
    if (isset($emailData['skipped']) && $emailData['skipped']) {
        $dbConfigContent .= "define('MAIL_ENABLED', 0);\n";
    } else {
        $dbConfigContent .= "define('MAIL_ENABLED', 1);\n";
        $dbConfigContent .= "define('MAIL_HOST', '{$emailData['mail_host']}');\n";
        $dbConfigContent .= "define('MAIL_PORT', {$emailData['mail_port']});\n";
        $dbConfigContent .= "define('MAIL_USERNAME', '{$emailData['mail_username']}');\n";
        $dbConfigContent .= "define('MAIL_PASSWORD', '{$emailData['mail_password']}');\n";
        $dbConfigContent .= "define('MAIL_FROM_NAME', '{$emailData['mail_from_name']}');\n";
        $dbConfigContent .= "define('MAIL_DEBUG', 0); // 0=关闭, 1=客户端消息, 2=客户端和服务端消息\n";
    }
    
    if (!file_put_contents('config/db.php', $dbConfigContent)) {
        return "无法写入数据库配置文件，请检查 config 目录权限";
    }
    
    // 2. 连接数据库并导入 SQL
    try {
        $pdo = new PDO(
            "mysql:host={$dbData['db_host']};dbname={$dbData['db_name']};charset=utf8mb4",
            $dbData['db_user'],
            $dbData['db_pass'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );
        
        // 读取 SQL 文件
        $sqlContent = file_get_contents('sql/install.sql');
        if ($sqlContent === false) {
            return "无法读取 SQL 安装文件，请检查 sql/install.sql 是否存在";
        }
        
        // 执行 SQL
        $pdo->exec($sqlContent);
        
        // 3. 插入管理员用户
        $passwordHash = password_hash($adminData['admin_password'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            INSERT INTO users 
            (username, email, password_hash, role, status) 
            VALUES (:username, :email, :password, 'admin', 'active')
        ");
        $stmt->execute([
            ':username' => $adminData['admin_username'],
            ':email' => $adminData['admin_email'],
            ':password' => $passwordHash
        ]);
        
        // 4. 插入站点信息
        $stmt = $pdo->prepare("
            INSERT INTO site_info 
            (site_title, site_subtitle, home_title, home_subtitle, webmaster_email) 
            VALUES (:title, :subtitle, :home_title, :home_subtitle, :webmaster_email)
        ");
        $stmt->execute([
            ':title' => $siteData['site_title'],
            ':subtitle' => $siteData['site_subtitle'],
            ':home_title' => $siteData['home_title'],
            ':home_subtitle' => $siteData['home_subtitle'],
            ':webmaster_email' => $adminData['admin_email']
        ]);
        
        // 5. 创建安装锁文件
        file_put_contents('installed.lock', date('Y-m-d H:i:s') . " 系统安装完成");
        
        return true;
    } catch (PDOException $e) {
        return "数据库操作失败: " . $e->getMessage();
    } catch (Exception $e) {
        return "安装过程出错: " . $e->getMessage();
    }
}

$envCheck = $currentStep == 1 ? checkEnvironment() : null;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统安装 - 第 <?php echo $currentStep; ?> 步</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f5f7fa;
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .header {
            background: #3498db;
            color: white;
            padding: 20px;
            text-align: center;
        }
        
        .header h1 {
            margin-bottom: 10px;
            font-size: 24px;
        }
        
        .steps {
            display: flex;
            border-bottom: 1px solid #eee;
            background: #f9f9f9;
        }
        
        .step {
            flex: 1;
            padding: 15px;
            text-align: center;
            position: relative;
            font-size: 14px;
        }
        
        .step::after {
            content: '';
            position: absolute;
            top: 50%;
            right: -10px;
            width: 20px;
            height: 20px;
            background: #f9f9f9;
            border: 1px solid #eee;
            border-left: none;
            border-bottom: none;
            transform: translateY(-50%) rotate(45deg);
            z-index: 1;
        }
        
        .step:last-child::after {
            display: none;
        }
        
        .step.active {
            background: #e3f2fd;
            color: #3498db;
            font-weight: bold;
        }
        
        .step.completed {
            background: #e8f5e9;
            color: #4caf50;
        }
        
        .content {
            padding: 30px;
        }
        
        .content h2 {
            margin-bottom: 20px;
            color: #2c3e50;
            font-size: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #444;
        }
        
        input, textarea, select {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        input:focus, textarea:focus, select:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .btn:hover {
            background: #2980b9;
        }
        
        .btn-skip {
            background: #95a5a6;
            margin-left: 10px;
        }
        
        .btn-skip:hover {
            background: #7f8c8d;
        }
        
        .actions {
            margin-top: 30px;
            text-align: right;
        }
        
        .env-check {
            margin-bottom: 20px;
        }
        
        .env-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        
        .env-item:last-child {
            border-bottom: none;
        }
        
        .status {
            font-weight: bold;
        }
        
        .status-passed {
            color: #27ae60;
        }
        
        .status-failed {
            color: #e74c3c;
        }
        
        .error {
            padding: 15px;
            background: #fdecea;
            border-left: 4px solid #e74c3c;
            color: #c0392b;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .success {
            text-align: center;
            padding: 40px 20px;
        }
        
        .success h2 {
            color: #27ae60;
            margin-bottom: 20px;
        }
        
        .success p {
            margin-bottom: 30px;
            font-size: 16px;
        }
        
        .links {
            margin-top: 30px;
        }
        
        .link {
            display: inline-block;
            margin: 0 10px;
            color: #3498db;
            text-decoration: none;
        }
        
        .link:hover {
            text-decoration: underline;
        }
        
        .help-text {
            font-size: 14px;
            color: #7f8c8d;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>网站系统安装向导</h1>
            <p>请按照步骤完成系统安装</p>
        </div>
        
        <div class="steps">
            <?php foreach ($steps as $stepNum => $stepName): ?>
                <div class="step <?php 
                    if ($stepNum == $currentStep) echo 'active';
                    elseif ($stepNum < $currentStep) echo 'completed';
                ?>">
                    步骤 <?php echo $stepNum; ?>: <?php echo $stepName; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="content">
            <?php if (isset($error)): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($currentStep == 1): ?>
                <h2>环境检测</h2>
                <p>请确保您的服务器满足以下环境要求：</p>
                
                <div class="env-check">
                    <?php foreach ($envCheck['results'] as $check): ?>
                        <div class="env-item">
                            <span><?php echo $check['name']; ?></span>
                            <span class="status <?php echo $check['passed'] ? 'status-passed' : 'status-failed'; ?>">
                                <?php echo $check['current'] . ' ' . ($check['passed'] ? '✓' : '✗'); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="actions">
                    <form method="post">
                        <input type="hidden" name="step" value="1">
                        <button type="submit" class="btn" <?php echo !$envCheck['all_passed'] ? 'disabled' : ''; ?>>
                            继续安装
                        </button>
                    </form>
                </div>
            <?php elseif ($currentStep == 2): ?>
                <h2>数据库信息</h2>
                <p>请填写数据库连接信息：</p>
                
                <form method="post">
                    <input type="hidden" name="step" value="2">
                    
                    <div class="form-group">
                        <label for="db_host">数据库主机</label>
                        <input type="text" id="db_host" name="db_host" value="localhost" required>
                        <div class="help-text">通常为 localhost 或 127.0.0.1</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="db_name">数据库名称</label>
                        <input type="text" id="db_name" name="db_name" value="" required>
                        <div class="help-text">系统将使用或创建此数据库</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="db_user">数据库用户名</label>
                        <input type="text" id="db_user" name="db_user" value="" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="db_pass">数据库密码</label>
                        <input type="password" id="db_pass" name="db_pass" required>
                    </div>
                    
                    <h3 style="margin: 20px 0 10px;">Redis 配置（可选）</h3>
                    
                    <div class="form-group">
                        <label for="redis_host">Redis 主机</label>
                        <input type="text" id="redis_host" name="redis_host" value="127.0.0.1">
                    </div>
                    
                    <div class="form-group">
                        <label for="redis_port">Redis 端口</label>
                        <input type="number" id="redis_port" name="redis_port" value="6379" min="1" max="65535">
                    </div>
                    
                    <div class="form-group">
                        <label for="redis_pass">Redis 密码</label>
                        <input type="password" id="redis_pass" name="redis_pass">
                        <div class="help-text">如无密码请留空</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="redis_prefix">Redis 键前缀</label>
                        <input type="text" id="redis_prefix" name="redis_prefix" value="xihui_">
                    </div>
                    
                    <div class="actions">
                        <button type="submit" class="btn">下一步</button>
                    </div>
                </form>
            <?php elseif ($currentStep == 3): ?>
                <h2>站点信息</h2>
                <p>请填写您的网站基本信息：</p>
                
                <form method="post">
                    <input type="hidden" name="step" value="3">
                    
                    <div class="form-group">
                        <label for="site_title">站点标题</label>
                        <input type="text" id="site_title" name="site_title" value="喜灰论坛" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="site_subtitle">站点副标题</label>
                        <input type="text" id="site_subtitle" name="site_subtitle" value="一个有趣的社区">
                    </div>
                    
                    <div class="form-group">
                        <label for="home_title">首页标题</label>
                        <input type="text" id="home_title" name="home_title" value="欢迎来到喜灰论坛">
                    </div>
                    
                    <div class="form-group">
                        <label for="home_subtitle">首页副标题</label>
                        <input type="text" id="home_subtitle" name="home_subtitle" value="分享你的想法和故事">
                    </div>
                    
                    <div class="actions">
                        <button type="submit" class="btn">下一步</button>
                    </div>
                </form>
            <?php elseif ($currentStep == 4): ?>
                <h2>管理员信息</h2>
                <p>请创建系统管理员账号：</p>
                
                <form method="post">
                    <input type="hidden" name="step" value="4">
                    
                    <div class="form-group">
                        <label for="admin_username">管理员用户名</label>
                        <input type="text" id="admin_username" name="admin_username" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="admin_email">管理员邮箱</label>
                        <input type="email" id="admin_email" name="admin_email" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="admin_password">管理员密码</label>
                        <input type="password" id="admin_password" name="admin_password" required>
                        <div class="help-text">密码长度至少6位</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="admin_password_confirm">确认密码</label>
                        <input type="password" id="admin_password_confirm" name="admin_password_confirm" required>
                    </div>
                    
                    <div class="actions">
                        <button type="submit" class="btn">下一步</button>
                    </div>
                </form>
            <?php elseif ($currentStep == 5): ?>
                <h2>发件邮箱信息</h2>
                <p>请填写邮件发送配置（用于发送验证码、通知等）：</p>
                
                <form method="post">
                    <input type="hidden" name="step" value="5">
                    
                    <div class="form-group">
                        <label for="mail_host">SMTP 服务器</label>
                        <input type="text" id="mail_host" name="mail_host" value="smtp.qiye.aliyun.com">
                        <div class="help-text">例如：smtp.qq.com、smtp.gmail.com</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="mail_port">SMTP 端口</label>
                        <input type="number" id="mail_port" name="mail_port" value="465" min="1" max="65535">
                        <div class="help-text">通常为 465 (SSL) 或 587 (TLS)</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="mail_username">邮箱账号</label>
                        <input type="email" id="mail_username" name="mail_username">
                    </div>
                    
                    <div class="form-group">
                        <label for="mail_password">邮箱密码/授权码</label>
                        <input type="password" id="mail_password" name="mail_password">
                        <div class="help-text">通常是邮箱授权码，而非登录密码</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="mail_from_name">发件人名称</label>
                        <input type="text" id="mail_from_name" name="mail_from_name" value="喜灰论坛">
                    </div>
                    
                    <div class="actions">
                        <button type="submit" class="btn">完成安装</button>
                        <button type="submit" class="btn btn-skip" name="skip_email" value="1">跳过</button>
                    </div>
                </form>
            <?php elseif ($currentStep == 6): ?>
                <div class="success">
                    <h2>安装成功！</h2>
                    <p>您的网站系统已成功安装完成。</p>
                    
                    <p>管理员账号：<?php echo $_SESSION['install_data'][4]['admin_username']; ?></p>
                    <p>管理员邮箱：<?php echo $_SESSION['install_data'][4]['admin_email']; ?></p>
                    
                    <div class="links">
                        <a href="/" class="link">访问前台</a>
                        <a href="/admin" class="link">访问后台</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        if (document.getElementById('admin_password_confirm')) {
            const password = document.getElementById('admin_password');
            const confirmPassword = document.getElementById('admin_password_confirm');
            const form = confirmPassword.closest('form');
            
            form.addEventListener('submit', function(e) {
                if (password.value !== confirmPassword.value) {
                    e.preventDefault();
                    alert('两次输入的密码不一致');
                    return false;
                }
                
                if (password.value.length < 6) {
                    e.preventDefault();
                    alert('密码长度至少为6位');
                    return false;
                }
            });
        }
    </script>
</body>
</html>
