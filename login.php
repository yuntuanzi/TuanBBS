<?php
require_once 'common/functions.php';

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    $result = user_login($username, $password);
    
    if ($result['success']) {
        $redirect = $_GET['redirect'] ?? 'index.php';
        header("Location: $redirect");
        exit;
    } else {
        $error = $result['message'];
    }
}

include('common/header.php');
?>
    <title>登录 - <?= htmlspecialchars($site_info['site_title']) ?></title>
<meta name="keywords" content="<?= htmlspecialchars($site_info['site_title']) ?>, <?= htmlspecialchars($site_info['site_title']) ?>登录">
<meta name="description" content="登录<?= htmlspecialchars($site_info['site_subtitle']) ?>，开启不同的探险~">
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md bg-white rounded-2xl shadow-card p-8">
        <div class="text-center mb-8">
            <img src="<?= htmlspecialchars($site_info['site_logo'] ?? 'static/images/default-avatar.png') ?>" alt="<?= htmlspecialchars($site_info['site_title']) ?>Logo" class="w-16 h-16 rounded-lg mx-auto mb-4">
            <h1 class="text-2xl font-bold text-primary">欢迎回来</h1>
            <p class="text-slate-500">登录你的<?= htmlspecialchars($site_info['site_title']) ?>账号</p>
        </div>
        
        <?php if ($error): ?>
        <div class="mb-4 p-3 bg-red-50 text-red-600 rounded-lg text-sm">
            <i class="fa fa-exclamation-circle mr-1"></i> <?= ($error) ?>
        </div>
        <?php endif; ?>
        
        <form method="POST" class="space-y-4">
            <div>
                <label for="username" class="block text-sm font-medium text-slate-700 mb-1">用户名或邮箱</label>
                <input type="text" id="username" name="username" class="w-full px-4 py-3 rounded-lg border border-slate-300 focus:border-primary focus:ring-2 focus:ring-primary/50 transition-all" placeholder="请输入用户名或邮箱" required>
            </div>
            
            <div>
                <label for="password" class="block text-sm font-medium text-slate-700 mb-1">密码</label>
                <input type="password" id="password" name="password" class="w-full px-4 py-3 rounded-lg border border-slate-300 focus:border-primary focus:ring-2 focus:ring-primary/50 transition-all" placeholder="请输入密码" required>
            </div>
            
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <input type="checkbox" id="remember" name="remember" class="h-4 w-4 text-primary focus:ring-primary/50 border-slate-300 rounded">
                    <label for="remember" class="ml-2 block text-sm text-slate-700">记住我</label>
                </div>
                <a href="forgot_password.php" class="text-sm text-primary hover:text-dark">忘记密码?</a>
            </div>
            
            <button type="submit" class="btn-primary w-full flex items-center justify-center gap-2">
                <i class="fa fa-sign-in"></i>
                <span>登录</span>
            </button>
        </form>
        
        <div class="mt-6 text-center text-sm text-slate-500">
            <p>还没有账号? <a href="register.php" class="text-primary font-medium hover:text-dark">立即注册</a></p>
        </div>
        
        <div class="mt-6 pt-6 border-t border-slate-100">
            <div class="flex justify-center gap-4">
                <a href="#" class="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center text-slate-600 hover:bg-primary/10 hover:text-primary transition-colors">
                    <i class="fa fa-qq"></i>
                </a>
                <a href="#" class="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center text-slate-600 hover:bg-primary/10 hover:text-primary transition-colors">
                    <i class="fa fa-weixin"></i>
                </a>
                <a href="#" class="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center text-slate-600 hover:bg-primary/10 hover:text-primary transition-colors">
                    <i class="fa fa-weibo"></i>
                </a>
            </div>
        </div>
    </div>
</body>
</html>