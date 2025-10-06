<?php
require_once 'common/functions.php';
require_once 'common/MailService.php';

$user = get_logged_in_user();
if (!$user) {
    header('Location: login.php');
    exit;
}

$stmt = $pdo->prepare("SELECT username, email, avatar_url, bio, status, created_at, last_login_at FROM users WHERE user_id = ?");
$stmt->execute([$user['user_id']]);
$user_info = $stmt->fetch(PDO::FETCH_ASSOC);

$errors = [];
$success = false;
$email_verification_step = 1;
$show_reset_password_form = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    $response = ['success' => false, 'message' => '', 'data' => []];
    
    try {
        if (isset($_POST['update_profile'])) {
            $username = trim($_POST['username']);
            $bio = trim($_POST['bio']);
            
            if (empty($username)) {
                throw new Exception('用户名不能为空');
            }
            elseif (mb_strlen($username, 'UTF-8') < 2 || mb_strlen($username, 'UTF-8') > 16) {
                throw new Exception('用户名长度必须在2-16位之间');
            }
            
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND user_id != ?");
            $stmt->execute([$username, $user['user_id']]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('用户名已被使用');
            }
            
            $stmt = $pdo->prepare("UPDATE users SET username = ?, bio = ?, updated_at = NOW() WHERE user_id = ?");
            $stmt->execute([$username, $bio, $user['user_id']]);
            
            $_SESSION['username'] = $username;
            
            $stmt = $pdo->prepare("SELECT username, email, avatar_url, bio, status, created_at, last_login_at FROM users WHERE user_id = ?");
            $stmt->execute([$user['user_id']]);
            $user_info = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $response['success'] = true;
            $response['message'] = '个人信息更新成功';
            $response['data']['username'] = $username;
            $response['data']['bio'] = $bio;
        }
        
        if (isset($_POST['request_email_change'])) {
            $current_password = $_POST['current_password'];
            $new_email = trim($_POST['new_email']);
            
            $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE user_id = ?");
            $stmt->execute([$user['user_id']]);
            $password_hash = $stmt->fetchColumn();
            
            if (!password_verify($current_password, $password_hash)) {
                throw new Exception('当前密码不正确');
            }
            
            if (empty($new_email)) {
                throw new Exception('新邮箱不能为空');
            } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('邮箱格式不正确');
            } elseif ($new_email == $user_info['email']) {
                throw new Exception('新邮箱不能与当前邮箱相同');
            }
            
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND user_id != ?");
            $stmt->execute([$new_email, $user['user_id']]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('邮箱已被注册');
            }
            
            $_SESSION['new_email'] = $new_email;
            
            $mailService = MailService::getInstance();
            $result = $mailService->sendVerificationCode($new_email, 'change_email');
            
            if (!$result['success']) {
                throw new Exception($result['message']);
            }
            
            $response['success'] = true;
            $response['message'] = '验证码已发送至新邮箱，请查收';
            $response['data']['step'] = 2;
        }
        
        if (isset($_POST['verify_email_code'])) {
            $code = trim($_POST['verification_code']);
            $new_email = $_SESSION['new_email'] ?? '';
            
            if (empty($code)) {
                throw new Exception('验证码不能为空');
            } elseif (empty($new_email)) {
                throw new Exception('验证会话已过期，请重新开始');
            }
            
            $redis = new Redis();
            $redis->connect(REDIS_HOST, REDIS_PORT);
            if (REDIS_PASS) $redis->auth(REDIS_PASS);
            
            $key = REDIS_PREFIX . 'verify_code:change_email:' . $new_email;
            $stored_data = $redis->get($key);
            
            if (!$stored_data) {
                throw new Exception('验证码已过期，请重新获取');
            }
            
            $stored_data = json_decode($stored_data, true);
            
            if ($stored_data['code'] != $code) {
                throw new Exception('验证码不正确');
            }
            
            $stmt = $pdo->prepare("UPDATE users SET email = ?, updated_at = NOW() WHERE user_id = ?");
            $stmt->execute([$new_email, $user['user_id']]);
            
            unset($_SESSION['new_email']);
            
            $stmt = $pdo->prepare("SELECT username, email, avatar_url, bio, status, created_at, last_login_at FROM users WHERE user_id = ?");
            $stmt->execute([$user['user_id']]);
            $user_info = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $response['success'] = true;
            $response['message'] = '邮箱修改成功';
            $response['data']['email'] = $new_email;
            $response['data']['step'] = 1;
        }
        
        if (isset($_POST['change_password'])) {
            $current_password = $_POST['current_password'];
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];
            
            $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE user_id = ?");
            $stmt->execute([$user['user_id']]);
            $password_hash = $stmt->fetchColumn();
            
            if (!password_verify($current_password, $password_hash)) {
                throw new Exception('当前密码不正确');
            }
            
            if (empty($new_password)) {
                throw new Exception('新密码不能为空');
            } elseif (strlen($new_password) < 6 || strlen($new_password) > 20) {
                throw new Exception('密码长度必须在6-20位之间');
            } elseif ($new_password !== $confirm_password) {
                throw new Exception('两次输入的密码不一致');
            }
            
            $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE user_id = ?");
            $stmt->execute([$new_password_hash, $user['user_id']]);
            
            $response['success'] = true;
            $response['message'] = '密码修改成功';
        }
        
        if (isset($_POST['request_password_reset'])) {
            $email = $user_info['email'];
            
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('您的账号未绑定有效邮箱，无法通过邮箱重置密码');
            }
            
            $mailService = MailService::getInstance();
            $result = $mailService->sendVerificationCode($email, 'forgot_password');
            
            if (!$result['success']) {
                throw new Exception($result['message']);
            }
            
            $_SESSION['reset_email'] = $email;
            
            $response['success'] = true;
            $response['message'] = '验证码已发送至您的邮箱，请查收';
            $response['data']['show_reset_form'] = true;
        }
        
        if (isset($_POST['confirm_password_reset'])) {
            $code = trim($_POST['reset_code']);
            $new_password = $_POST['new_reset_password'];
            $confirm_password = $_POST['confirm_reset_password'];
            $email = $_SESSION['reset_email'] ?? '';
            
            if (empty($email)) {
                throw new Exception('重置会话已过期，请重新开始');
            }
            
            if (empty($code)) {
                throw new Exception('验证码不能为空');
            }
            
            $redis = new Redis();
            $redis->connect(REDIS_HOST, REDIS_PORT);
            if (REDIS_PASS) $redis->auth(REDIS_PASS);
            
            $key = REDIS_PREFIX . 'verify_code:forgot_password:' . $email;
            $stored_data = $redis->get($key);
            
            if (!$stored_data) {
                throw new Exception('验证码已过期，请重新获取');
            }
            
            $stored_data = json_decode($stored_data, true);
            
            if ($stored_data['code'] != $code) {
                throw new Exception('验证码不正确');
            }
            
            if (empty($new_password)) {
                throw new Exception('新密码不能为空');
            } elseif (strlen($new_password) < 6 || strlen($new_password) > 20) {
                throw new Exception('密码长度必须在6-20位之间');
            } elseif ($new_password !== $confirm_password) {
                throw new Exception('两次输入的密码不一致');
            }
            
            $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE email = ?");
            $stmt->execute([$new_password_hash, $email]);
            
            unset($_SESSION['reset_email']);
            
            $response['success'] = true;
            $response['message'] = '密码重置成功，请使用新密码登录';
            $response['data']['show_reset_form'] = false;
        }
        
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
            $avatar = $_FILES['avatar'];
            
            if ($avatar['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('文件上传失败');
            }
            
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 2 * 1024 * 1024;
            
            if (!in_array($avatar['type'], $allowed_types)) {
                throw new Exception('只允许上传JPEG、PNG或GIF图片');
            } elseif ($avatar['size'] > $max_size) {
                throw new Exception('图片大小不能超过2MB');
            }
            
            $ext = pathinfo($avatar['name'], PATHINFO_EXTENSION);
            $filename = 'avatar_' . $user['user_id'] . '_' . time() . '.' . $ext;
            $upload_path = 'uploads/avatars/' . $filename;
            
            if (!is_dir('uploads/avatars')) {
                mkdir('uploads/avatars', 0755, true);
            }
            
            if (!move_uploaded_file($avatar['tmp_name'], $upload_path)) {
                throw new Exception('头像上传失败');
            }
            
            if ($user['avatar_url'] && !str_contains($user['avatar_url'], 'default-avatar.png')) {
                @unlink($user['avatar_url']);
            }
            
            $stmt = $pdo->prepare("UPDATE users SET avatar_url = ?, updated_at = NOW() WHERE user_id = ?");
            $stmt->execute([$upload_path, $user['user_id']]);
            
            $_SESSION['avatar_url'] = $upload_path;
            
            $stmt = $pdo->prepare("SELECT username, email, avatar_url, bio, status, created_at, last_login_at FROM users WHERE user_id = ?");
            $stmt->execute([$user['user_id']]);
            $user_info = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $response['success'] = true;
            $response['message'] = '头像上传成功';
            $response['data']['avatar_url'] = $upload_path;
        }
        
        if (isset($_POST['deactivate_account'])) {
            $confirm1 = isset($_POST['confirm_deactivate1']) && $_POST['confirm_deactivate1'] === '1';
            $confirm2 = isset($_POST['confirm_deactivate2']) && $_POST['confirm_deactivate2'] === '1';
            $confirm3 = isset($_POST['confirm_deactivate3']) && $_POST['confirm_deactivate3'] === '1';
            $confirm4 = isset($_POST['confirm_deactivate4']) && $_POST['confirm_deactivate4'] === '1';
            
            if (!$confirm1 || !$confirm2 || !$confirm3 || !$confirm4) {
                throw new Exception('请确认所有注销条款');
            }
            
            $stmt = $pdo->prepare("UPDATE users SET status = 'suspended', updated_at = NOW() WHERE user_id = ?");
            $stmt->execute([$user['user_id']]);
            
            user_logout();
            
            $response['success'] = true;
            $response['message'] = '账号已成功注销';
            $response['data']['redirect'] = 'index.php';
        }
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

$created_at = new DateTime($user_info['created_at']);
$formatted_created_at = $created_at->format('Y年m月d日 H:i:s');

$formatted_last_login = '从未登录';
if (!empty($user_info['last_login_at']) && $user_info['last_login_at'] != '0000-00-00 00:00:00') {
    $last_login = new DateTime($user_info['last_login_at']);
    $formatted_last_login = $last_login->format('Y年m月d日 H:i:s');
}

$is_email_bound = !empty($user_info['email']) && filter_var($user_info['email'], FILTER_VALIDATE_EMAIL);

$is_password_set = true;

$account_status = $user_info['status'] === 'active' ? '正常' : 
                 ($user_info['status'] === 'banned' ? '封禁' : '停用');

include('common/header.php');
?>

<title>个人设置 - <?= htmlspecialchars($site_info['site_title']) ?></title>
<style>
    :root {
        --primary: #3b82f6;
        --primary-dark: #2563eb;
        --success: #10b981;
        --danger: #ef4444;
        --warning: #f59e0b;
        --gray-50: #f9fafb;
        --gray-100: #f3f4f6;
        --gray-200: #e5e7eb;
        --gray-300: #d1d5db;
        --gray-400: #9ca3af;
        --gray-500: #6b7280;
        --gray-600: #4b5563;
        --gray-700: #374151;
        --gray-800: #1f2937;
        --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.05);
        --shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        --transition: all 0.2s ease-in-out;
    }
    
    .avatar-upload-container {
        position: relative;
        width: 140px;
        height: 140px;
        margin: 0 auto;
        transition: var(--transition);
    }
    
    .avatar-upload-container:hover {
        transform: translateY(-3px);
    }
    
    .avatar-preview {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid white;
        box-shadow: var(--shadow);
    }
    
    .avatar-upload-btn {
        position: absolute;
        bottom: 0;
        right: 0;
        background: var(--primary);
        color: white;
        width: 36px;
        height: 36px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: var(--shadow-sm);
        transition: var(--transition);
        border: 2px solid white;
    }
    
    .avatar-upload-btn:hover {
        background: var(--primary-dark);
        transform: scale(1.05);
    }
    
    .avatar-upload-btn input {
        display: none;
    }
    
    .progress-container {
        width: 100%;
        margin-top: 8px;
        display: none;
    }
    
    .progress-bar {
        height: 4px;
        background: var(--gray-200);
        border-radius: 2px;
        overflow: hidden;
    }
    
    .progress-fill {
        height: 100%;
        background: var(--primary);
        width: 0%;
        transition: width 0.3s ease;
    }
    
    .account-status {
        display: inline-flex;
        align-items: center;
        padding: 0.2rem 0.5rem;
        border-radius: 999px;
        font-size: 0.8125rem;
        font-weight: 500;
    }
    
    .account-status::before {
        content: '';
        width: 5px;
        height: 5px;
        border-radius: 50%;
        margin-right: 4px;
    }
    
    .status-active {
        background-color: rgba(16, 185, 129, 0.1);
        color: var(--success);
    }
    
    .status-active::before {
        background-color: var(--success);
    }
    
    .status-banned {
        background-color: rgba(239, 68, 68, 0.1);
        color: var(--danger);
    }
    
    .status-banned::before {
        background-color: var(--danger);
    }
    
    .status-suspended {
        background-color: rgba(245, 158, 11, 0.1);
        color: var(--warning);
    }
    
    .status-suspended::before {
        background-color: var(--warning);
    }
    
    .settings-card {
        background: white;
        border-radius: 8px;
        box-shadow: var(--shadow);
        padding: 1rem;
        margin-bottom: 1rem;
    }
    
    .settings-card h3 {
        font-size: 1.05rem;
        font-weight: 600;
        color: var(--gray-800);
        margin-bottom: 0.8rem;
        padding-bottom: 0.5rem;
        border-bottom: 1px solid var(--gray-200);
        display: flex;
        align-items: center;
    }
    
    .settings-card h3 i {
        margin-right: 0.5rem;
        color: var(--primary);
        font-size: 1.1rem;
    }
    
    .form-group {
        margin-bottom: 0.9rem;
    }
    
    .form-label {
        display: block;
        font-size: 0.8125rem;
        font-weight: 500;
        color: var(--gray-700);
        margin-bottom: 0.35rem;
    }
    
    .form-input {
        width: 100%;
        padding: 0.6rem 0.8rem;
        border: 1px solid var(--gray-300);
        border-radius: 6px;
        font-size: 0.9375rem;
        transition: var(--transition);
    }
    
    .form-input:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
    }
    
    .form-input.error {
        border-color: var(--danger);
    }
    
    .form-input.error:focus {
        box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.1);
    }
    
    .form-textarea {
        min-height: 80px;
        resize: vertical;
    }
    
    .form-hint {
        font-size: 0.75rem;
        color: var(--gray-500);
        margin-top: 0.25rem;
    }
    
    .form-error {
        font-size: 0.75rem;
        color: var(--danger);
        margin-top: 0.25rem;
        display: flex;
        align-items: center;
        display: none;
    }
    
    .form-error.show {
        display: flex;
    }
    
    .form-error i {
        margin-right: 3px;
        font-size: 0.8125rem;
    }
    
    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.5rem 1rem;
        font-weight: 500;
        font-size: 0.8125rem;
        border-radius: 6px;
        cursor: pointer;
        transition: var(--transition);
        border: none;
    }
    
    .btn i {
        margin-right: 5px;
        font-size: 0.875rem;
    }
    
    .btn-primary {
        background-color: var(--primary);
        color: white;
    }
    
    .btn-primary:hover {
        background-color: var(--primary-dark);
    }
    
    .btn-danger {
        background-color: var(--danger);
        color: white;
    }
    
    .btn-danger:hover {
        background-color: #dc2626;
    }
    
    .btn-secondary {
        background-color: var(--gray-200);
        color: var(--gray-700);
    }
    
    .btn-secondary:hover {
        background-color: var(--gray-300);
    }
    
    .alert {
        padding: 0.75rem 1rem;
        border-radius: 6px;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        font-size: 0.8125rem;
        display: none;
    }
    
    .alert.show {
        display: flex;
    }
    
    .alert i {
        margin-right: 0.5rem;
        font-size: 1.1rem;
    }
    
    .alert-success {
        background-color: rgba(16, 185, 129, 0.1);
        color: var(--success);
        border: 1px solid rgba(16, 185, 129, 0.2);
    }
    
    .alert-danger {
        background-color: rgba(239, 68, 68, 0.1);
        color: var(--danger);
        border: 1px solid rgba(239, 68, 68, 0.2);
    }
    
    .account-info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 0.75rem;
    }
    
    .account-info-item {
        background-color: var(--gray-50);
        padding: 0.75rem;
        border-radius: 6px;
    }
    
    .account-info-label {
        font-size: 0.75rem;
        color: var(--gray-500);
        margin-bottom: 0.2rem;
    }
    
    .account-info-value {
        font-weight: 500;
        color: var(--gray-800);
        font-size: 0.9375rem;
    }
    
    .checkbox-group {
        margin-bottom: 0.75rem;
    }
    
    .checkbox-item {
        display: flex;
        align-items: flex-start;
        margin-bottom: 0.5rem;
    }
    
    .checkbox-item input {
        margin-top: 2px;
        width: 16px;
        height: 16px;
        border-radius: 3px;
    }
    
    .checkbox-item label {
        margin-left: 0.4rem;
        font-size: 0.8125rem;
        color: var(--gray-700);
    }
    
    .verification-code {
        letter-spacing: 4px;
        text-align: center;
        font-size: 1.2rem;
    }
    
    .security-section {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 1rem;
        margin-bottom: 1rem;
    }
    
    .security-item {
        background: white;
        border-radius: 8px;
        box-shadow: var(--shadow);
        padding: 1.2rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        transition: var(--transition);
    }
    
    .security-item:hover {
        transform: translateY(-3px);
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    
    .security-content {
        display: flex;
        align-items: center;
    }
    
    .security-icon {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background-color: rgba(59, 130, 246, 0.1);
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 1rem;
        flex-shrink: 0;
    }
    
    .security-icon i {
        font-size: 1.5rem;
        color: var(--primary);
    }
    
    .security-info h4 {
        font-size: 0.9375rem;
        font-weight: 600;
        color: var(--gray-800);
        margin-bottom: 0.2rem;
    }
    
    .security-status {
        font-size: 0.8125rem;
        color: var(--gray-500);
    }
    
    .status-bound {
        color: var(--success);
    }
    
    .status-unbound {
        color: var(--warning);
    }
    
    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: rgba(0, 0, 0, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1000;
        visibility: hidden;
        opacity: 0;
        transition: visibility 0s linear 0.25s, opacity 0.25s;
    }
    
    .modal-overlay.active {
        visibility: visible;
        opacity: 1;
        transition-delay: 0s;
    }
    
    .modal {
        background-color: white;
        border-radius: 8px;
        width: 90%;
        max-width: 500px;
        max-height: 90vh;
        overflow-y: auto;
        transform: scale(0.95);
        transition: transform 0.25s;
    }
    
    .modal-overlay.active .modal {
        transform: scale(1);
    }
    
    .modal-header {
        padding: 1rem 1.25rem;
        border-bottom: 1px solid var(--gray-200);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .modal-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--gray-800);
    }
    
    .modal-close {
        background: none;
        border: none;
        font-size: 1.25rem;
        color: var(--gray-500);
        cursor: pointer;
        transition: color 0.2s;
    }
    
    .modal-close:hover {
        color: var(--danger);
    }
    
    .modal-body {
        padding: 1.25rem;
    }
    
    .modal-footer {
        padding: 1rem 1.25rem;
        border-top: 1px solid var(--gray-200);
        display: flex;
        justify-content: flex-end;
        gap: 0.5rem;
    }
    
    .tabs {
        display: flex;
        border-bottom: 1px solid var(--gray-200);
        margin-bottom: 1rem;
    }
    
    .tab {
        padding: 0.75rem 1rem;
        cursor: pointer;
        transition: all 0.2s;
        border-bottom: 2px solid transparent;
        font-size: 0.875rem;
        font-weight: 500;
    }
    
    .tab.active {
        border-bottom-color: var(--primary);
        color: var(--primary);
    }
    
    .tab-content {
        display: none;
    }
    
    .tab-content.active {
        display: block;
    }
    
    .loading {
        display: inline-block;
        width: 20px;
        height: 20px;
        border: 3px solid rgba(255,255,255,.3);
        border-radius: 50%;
        border-top-color: white;
        animation: spin 1s ease-in-out infinite;
    }
    
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
    
    @media (max-width: 768px) {
        .account-info-grid {
            grid-template-columns: 1fr;
        }
        
        .security-section {
            grid-template-columns: 1fr;
        }
    }
</style>
</head>
<body class="bg-gray-50 text-gray-800 min-h-screen flex flex-col overflow-x-hidden m-0 p-0">
    
    <main class="flex-grow flex flex-col md:flex-row transition-all duration-300 pt-4" id="main-container">
        <?php include('common/asideleft.php'); ?>
        
        <div class="flex-grow transition-all duration-300 p-3 md:ml-64 lg:ml-72" id="content-area">
            <div class="max-w-4xl mx-auto">
                <div class="mb-4">
                    <h1 class="text-[clamp(1.3rem,2.5vw,1.8rem)] font-bold text-gray-800 mb-1">个人设置</h1>
                    <p class="text-gray-500 text-sm">管理您的账户信息、安全设置和偏好</p>
                </div>
                
                <div class="alert alert-success" id="global-success-alert">
                    <i class="fa fa-check-circle"></i>
                    <span id="global-success-message"></span>
                </div>
                
                <div class="alert alert-danger" id="global-error-alert">
                    <i class="fa fa-exclamation-circle"></i>
                    <span id="global-error-message"></span>
                </div>
                
                <form id="profile-form" class="settings-card">
                    <h3><i class="fa fa-id-card"></i>个人信息</h3>
                    
                    <div class="flex flex-col md:flex-row gap-5">
                        <div class="w-full md:w-1/3">
                            <div class="flex flex-col items-center">
                                <div class="avatar-upload-container">
                                    <img id="avatar-preview" src="<?= htmlspecialchars($user_info['avatar_url'] ?? 'static/images/default-avatar.png') ?>" 
                                         alt="用户头像" class="avatar-preview">
                                    <label class="avatar-upload-btn">
                                        <i class="fa fa-camera"></i>
                                        <input type="file" name="avatar" id="avatar" accept="image/*">
                                    </label>
                                </div>
                                <div class="progress-container" id="progress-container">
                                    <div class="progress-bar">
                                        <div class="progress-fill" id="progress-fill"></div>
                                    </div>
                                    <p class="text-xs text-center mt-1" id="progress-text">上传中: 0%</p>
                                </div>
                                <p class="form-error" id="avatar-error"><i class="fa fa-exclamation-circle"></i><span></span></p>
                                <p class="form-hint mt-1 text-center">支持JPG、PNG、GIF，最大2MB</p>
                            </div>
                        </div>
                        
                        <div class="w-full md:w-2/3">
                            <div class="form-group">
                                <label for="username" class="form-label">用户名</label>
                                <input type="text" id="username" name="username" 
                                       value="<?= htmlspecialchars($user_info['username']) ?>" 
                                       class="form-input">
                                <p class="form-error" id="username-error"><i class="fa fa-exclamation-circle"></i><span></span></p>
                                <p class="form-hint">2-16个字符，可包含字母、数字和下划线</p>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">当前邮箱</label>
                                <p class="form-input" style="background-color: var(--gray-50); cursor: default;" id="current-email">
                                    <?= htmlspecialchars($user_info['email']) ?>
                                </p>
                            </div>
                            
                            <div class="form-group">
                                <label for="bio" class="form-label">个人简介</label>
                                <textarea id="bio" name="bio" rows="3" 
                                          class="form-input form-textarea"><?= htmlspecialchars($user_info['bio'] ?? '') ?></textarea>
                                <p class="form-hint">简单介绍一下自己吧</p>
                            </div>
                            
                            <div class="text-right pt-1">
                                <button type="submit" name="update_profile" class="btn btn-primary" id="update-profile-btn">
                                    <i class="fa fa-save"></i>保存更改
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
                
                <div class="settings-card">
                    <h3><i class="fa fa-shield"></i>账户安全</h3>
                    <div class="security-section">
                        <div class="security-item">
                            <div class="security-content">
                                <div class="security-icon">
                                    <i class="fa fa-envelope"></i>
                                </div>
                                <div class="security-info">
                                    <h4>绑定邮箱</h4>
                                    <p class="security-status <?= $is_email_bound ? 'status-bound' : 'status-unbound' ?>">
                                        <?= $is_email_bound ? '已绑定: ' . htmlspecialchars($user_info['email']) : '未绑定' ?>
                                    </p>
                                </div>
                            </div>
                            <button class="btn btn-primary" onclick="openModal('email-modal')">
                                <i class="fa fa-pencil"></i>修改
                            </button>
                        </div>
                        
                        <div class="security-item">
                            <div class="security-content">
                                <div class="security-icon">
                                    <i class="fa fa-lock"></i>
                                </div>
                                <div class="security-info">
                                    <h4>密码管理</h4>
                                    <p class="security-status <?= $is_password_set ? 'status-bound' : 'status-unbound' ?>">
                                        <?= $is_password_set ? '已设置密码' : '未设置密码' ?>
                                    </p>
                                </div>
                            </div>
                            <button class="btn btn-primary" onclick="openModal('password-modal')">
                                <i class="fa fa-pencil"></i>修改
                            </button>
                        </div>
                        
                        <div class="security-item">
                            <div class="security-content">
                                <div class="security-icon">
                                    <i class="fa fa-trash"></i>
                                </div>
                                <div class="security-info">
                                    <h4>账号注销</h4>
                                    <p class="security-status">
                                        当前状态: <?= $account_status ?>
                                    </p>
                                </div>
                            </div>
                            <button class="btn btn-danger" onclick="openModal('deactivate-modal')">
                                <i class="fa fa-ban"></i>注销
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="settings-card">
                    <h3><i class="fa fa-user-circle"></i>账户信息</h3>
                    <div class="account-info-grid">
                        <div class="account-info-item">
                            <p class="account-info-label">用户ID</p>
                            <p class="account-info-value"><?= htmlspecialchars($user['user_id']) ?></p>
                        </div>
                        <div class="account-info-item">
                            <p class="account-info-label">账户状态</p>
                            <p class="account-status status-<?= htmlspecialchars($user_info['status']) ?>">
                                <?= 
                                    $user_info['status'] === 'active' ? '正常' : 
                                    ($user_info['status'] === 'banned' ? '封禁' : '停用')
                                ?>
                            </p>
                        </div>
                        <div class="account-info-item">
                            <p class="account-info-label">注册时间</p>
                            <p class="account-info-value"><?= htmlspecialchars($formatted_created_at) ?></p>
                        </div>
                        <div class="account-info-item">
                            <p class="account-info-label">最后登录</p>
                            <p class="account-info-value" id="last-login-time"><?= htmlspecialchars($formatted_last_login) ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php include('common/asideright.php'); ?>
    </main>
    
    <div class="modal-overlay" id="email-modal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">绑定邮箱修改</h3>
                <button class="modal-close" onclick="closeModal('email-modal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="change-email-form">
                    <div class="alert alert-success" id="email-success-alert">
                        <i class="fa fa-check-circle"></i>
                        <span id="email-success-message"></span>
                    </div>
                    
                    <div class="alert alert-danger" id="email-error-alert">
                        <i class="fa fa-exclamation-circle"></i>
                        <span id="email-error-message"></span>
                    </div>
                    
                    <div id="email-step-1">
                        <div class="form-group">
                            <label for="current_password_email" class="form-label">当前密码</label>
                            <input type="password" id="current_password_email" name="current_password" 
                                   class="form-input">
                            <p class="form-error" id="current_password_email-error"><i class="fa fa-exclamation-circle"></i><span></span></p>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_email" class="form-label">新邮箱</label>
                            <input type="email" id="new_email" name="new_email" 
                                   class="form-input">
                            <p class="form-error" id="new_email-error"><i class="fa fa-exclamation-circle"></i><span></span></p>
                            <p class="form-hint">我们将发送验证码到该邮箱进行验证</p>
                        </div>
                    </div>
                    
                    <div id="email-step-2" style="display: none;">
                        <div class="alert alert-success">
                            <i class="fa fa-info-circle"></i>
                            <span id="email-verification-message">验证码已发送至 ，请在5分钟内完成验证</span>
                        </div>
                        
                        <div class="form-group">
                            <label for="verification_code" class="form-label">邮箱验证码</label>
                            <input type="text" id="verification_code" name="verification_code" 
                                   class="form-input verification-code"
                                   placeholder="输入6位验证码">
                            <p class="form-error" id="verification_code-error"><i class="fa fa-exclamation-circle"></i><span></span></p>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <div id="email-step-1-buttons">
                            <button type="button" name="request_email_change" class="btn btn-primary" id="send-verification-btn">
                                <i class="fa fa-paper-plane"></i>发送验证码
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="closeModal('email-modal')">
                                <i class="fa fa-times"></i>关闭
                            </button>
                        </div>
                        
                        <div id="email-step-2-buttons" style="display: none;">
                            <button type="button" name="verify_email_code" class="btn btn-primary" id="verify-code-btn">
                                <i class="fa fa-check"></i>验证并修改邮箱
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="closeModal('email-modal')">
                                <i class="fa fa-times"></i>关闭
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="modal-overlay" id="password-modal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">密码修改</h3>
                <button class="modal-close" onclick="closeModal('password-modal')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="alert alert-success" id="password-success-alert">
                    <i class="fa fa-check-circle"></i>
                    <span id="password-success-message"></span>
                </div>
                
                <div class="alert alert-danger" id="password-error-alert">
                    <i class="fa fa-exclamation-circle"></i>
                    <span id="password-error-message"></span>
                </div>
                
                <div class="tabs">
                    <div class="tab active" onclick="switchTab('password-by-old', this)">通过原密码重置</div>
                    <div class="tab" onclick="switchTab('password-by-email', this)">通过邮箱重置</div>
                </div>
                
                <div class="tab-content active" id="password-by-old">
                    <div id="change-password-form">
                        <div class="form-group">
                            <label for="current_password_old" class="form-label">当前密码</label>
                            <input type="password" id="current_password_old" name="current_password" 
                                   class="form-input">
                            <p class="form-error" id="current_password_old-error"><i class="fa fa-exclamation-circle"></i><span></span></p>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="form-group">
                                <label for="new_password" class="form-label">新密码</label>
                                <input type="password" id="new_password" name="new_password" 
                                       class="form-input">
                                <p class="form-error" id="new_password-error"><i class="fa fa-exclamation-circle"></i><span></span></p>
                                <p class="form-hint">6-20个字符，建议包含字母和数字</p>
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password" class="form-label">确认新密码</label>
                                <input type="password" id="confirm_password" name="confirm_password" 
                                       class="form-input">
                                <p class="form-error" id="confirm_password-error"><i class="fa fa-exclamation-circle"></i><span></span></p>
                                <p class="form-hint">再次输入新密码</p>
                            </div>
                        </div>
                        
                        <div class="modal-footer">
                            <button type="button" name="change_password" class="btn btn-primary" id="submit-password-change">
                                <i class="fa fa-key"></i>修改密码
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="closeModal('password-modal')">
                                <i class="fa fa-times"></i>关闭
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="tab-content" id="password-by-email">
                    <div id="reset-password-form">
                        <div id="request-reset-step">
                            <div class="form-group">
                                <label class="form-label">当前绑定邮箱</label>
                                <p class="form-input" style="background-color: var(--gray-50); cursor: default;">
                                    <?= htmlspecialchars($user_info['email']) ?>
                                </p>
                                <p class="form-hint">我们将发送验证码到您当前绑定的邮箱用于重置密码</p>
                            </div>
                            
                            <div class="modal-footer">
                                <button type="button" name="request_password_reset" class="btn btn-primary" id="send-reset-code-btn">
                                    <i class="fa fa-paper-plane"></i>发送重置验证码
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="closeModal('password-modal')">
                                    <i class="fa fa-times"></i>关闭
                                </button>
                            </div>
                        </div>
                        
                        <div id="confirm-reset-step" style="display: none;">
                            <div class="alert alert-success">
                                <i class="fa fa-info-circle"></i>
                                <span id="reset-verification-message">验证码已发送至 <?= htmlspecialchars($user_info['email']) ?>，请在5分钟内完成验证</span>
                            </div>
                            
                            <div class="form-group">
                                <label for="reset_code" class="form-label">重置验证码</label>
                                <input type="text" id="reset_code" name="reset_code" 
                                       class="form-input verification-code"
                                       placeholder="输入6位验证码">
                                <p class="form-error" id="reset_code-error"><i class="fa fa-exclamation-circle"></i><span></span></p>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="form-group">
                                    <label for="new_reset_password" class="form-label">新密码</label>
                                    <input type="password" id="new_reset_password" name="new_reset_password" 
                                           class="form-input">
                                    <p class="form-error" id="new_reset_password-error"><i class="fa fa-exclamation-circle"></i><span></span></p>
                                    <p class="form-hint">6-20个字符，建议包含字母和数字</p>
                                </div>
                                
                                <div class="form-group">
                                    <label for="confirm_reset_password" class="form-label">确认新密码</label>
                                    <input type="password" id="confirm_reset_password" name="confirm_reset_password" 
                                           class="form-input">
                                    <p class="form-error" id="confirm_reset_password-error"><i class="fa fa-exclamation-circle"></i><span></span></p>
                                    <p class="form-hint">再次输入新密码</p>
                                </div>
                            </div>
                            
                            <div class="modal-footer">
                                <button type="button" name="confirm_password_reset" class="btn btn-primary" id="confirm-reset-btn">
                                    <i class="fa fa-check"></i>确认重置密码
                                </button>
                                <button type="button" id="cancel-reset" class="btn btn-secondary">
                                    <i class="fa fa-times"></i>取消
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="closeModal('password-modal')">
                                    <i class="fa fa-times"></i>关闭
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="modal-overlay" id="deactivate-modal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">账号注销</h3>
                <button class="modal-close" onclick="closeModal('deactivate-modal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="deactivate-form">
                    <div class="alert alert-danger mb-4">
                        <i class="fa fa-exclamation-triangle"></i>
                        <div>
                            <strong>警告：账号注销是不可逆操作！</strong>
                            <ul class="mt-1 space-y-0.5 text-xs">
                                <li>1. 注销后您将无法登录此账号</li>
                                <li>2. 您的邮箱和用户名不会被释放</li>
                                <li>3. 我们会留存您的数据365天以备查验</li>
                                <li>4. 账户注销后不会豁免既定处罚</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="alert alert-success" id="deactivate-success-alert">
                        <i class="fa fa-check-circle"></i>
                        <span id="deactivate-success-message"></span>
                    </div>
                    
                    <div class="alert alert-danger" id="deactivate-error-alert">
                        <i class="fa fa-exclamation-circle"></i>
                        <span id="deactivate-error-message"></span>
                    </div>
                    
                    <div class="checkbox-group">
                        <div class="checkbox-item">
                            <input type="checkbox" id="confirm_deactivate1" name="confirm_deactivate1" value="1" 
                                   class="h-4 w-4 text-danger focus:ring-danger border-gray-300 rounded">
                            <label for="confirm_deactivate1">我现在很清醒，清楚自己在做什么</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" id="confirm_deactivate2" name="confirm_deactivate2" value="1" 
                                   class="h-4 w-4 text-danger focus:ring-danger border-gray-300 rounded">
                            <label for="confirm_deactivate2">我已经阅读并同意上述注意事项</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" id="confirm_deactivate3" name="confirm_deactivate3" value="1" 
                                   class="h-4 w-4 text-danger focus:ring-danger border-gray-300 rounded">
                            <label for="confirm_deactivate3">我清楚地知道注销后带来的所有后果</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" id="confirm_deactivate4" name="confirm_deactivate4" value="1" 
                                   class="h-4 w-4 text-danger focus:ring-danger border-gray-300 rounded">
                            <label for="confirm_deactivate4">我确认要注销我的账号，且不会后悔</label>
                        </div>
                    </div>
                    
                    <p class="form-error" id="deactivate-error"><i class="fa fa-exclamation-circle"></i><span></span></p>
                    
                    <div class="modal-footer">
                        <button type="button" name="deactivate_account" class="btn btn-danger" id="confirm-deactivate-btn">
                            <i class="fa fa-warning"></i>确认注销
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="closeModal('deactivate-modal')">
                            <i class="fa fa-times"></i>取消
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function showAlert(alertId, message) {
            const alert = document.getElementById(alertId);
            const messageElement = document.getElementById(alertId.replace('alert', 'message'));
            
            messageElement.textContent = message;
            alert.classList.add('show');
            
            setTimeout(() => {
                alert.classList.remove('show');
            }, 3000);
        }
        
        function hideAllErrors(formId) {
            const form = document.getElementById(formId);
            if (!form) return;
            
            const errorElements = form.querySelectorAll('.form-error');
            errorElements.forEach(el => {
                el.classList.remove('show');
                el.querySelector('span').textContent = '';
            });
            
            const inputElements = form.querySelectorAll('.form-input');
            inputElements.forEach(el => {
                el.classList.remove('error');
            });
        }
        
        function showFieldError(fieldId, message) {
            const errorElement = document.getElementById(`${fieldId}-error`);
            const inputElement = document.getElementById(fieldId);
            
            if (errorElement) {
                errorElement.classList.add('show');
                errorElement.querySelector('span').textContent = message;
            }
            
            if (inputElement) {
                inputElement.classList.add('error');
            }
        }
        
        function showLoading(buttonId) {
            const button = document.getElementById(buttonId);
            if (!button) return;
            
            button.disabled = true;
            const originalContent = button.innerHTML;
            button.innerHTML = '<div class="loading"></div>处理中...';
            
            return originalContent;
        }
        
        function hideLoading(buttonId, originalContent) {
            const button = document.getElementById(buttonId);
            if (!button) return;
            
            button.disabled = false;
            button.innerHTML = originalContent;
        }
        
        document.getElementById('avatar').addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('avatar-preview').src = e.target.result;
                }
                reader.readAsDataURL(this.files[0]);
                
                const progressContainer = document.getElementById('progress-container');
                const progressFill = document.getElementById('progress-fill');
                const progressText = document.getElementById('progress-text');
                progressContainer.style.display = 'block';
                progressText.style.color = '';
                
                const formData = new FormData();
                formData.append('avatar', this.files[0]);
                formData.append('ajax', '1');
                
                const xhr = new XMLHttpRequest();
                xhr.open('POST', window.location.href, true);
                
                xhr.upload.onprogress = function(e) {
                    if (e.lengthComputable) {
                        const percentComplete = Math.round((e.loaded / e.total) * 100);
                        progressFill.style.width = percentComplete + '%';
                        progressText.textContent = '上传中: ' + percentComplete + '%';
                    }
                };
                
                xhr.onload = function() {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        
                        if (xhr.status === 200 && response.success) {
                            progressText.textContent = '上传完成';
                            progressText.style.color = 'var(--success)';
                            showAlert('global-success-alert', response.message);
                            
                            document.getElementById('avatar-preview').src = response.data.avatar_url;
                            
                            document.getElementById('avatar').value = '';
                        } else {
                            throw new Error(response.message || '上传失败');
                        }
                    } catch (error) {
                        progressText.textContent = error.message;
                        progressText.style.color = 'var(--danger)';
                        showFieldError('avatar', error.message);
                    }
                    
                    setTimeout(() => {
                        progressContainer.style.display = 'none';
                        progressFill.style.width = '0%';
                    }, 1500);
                };
                
                xhr.onerror = function() {
                    progressText.textContent = '网络错误，请重试';
                    progressText.style.color = 'var(--danger)';
                    showFieldError('avatar', '网络错误，请重试');
                    
                    setTimeout(() => {
                        progressContainer.style.display = 'none';
                        progressFill.style.width = '0%';
                    }, 1500);
                };
                
                xhr.send(formData);
            }
        });
        
        document.getElementById('profile-form').addEventListener('submit', function(e) {
            e.preventDefault();
            hideAllErrors('profile-form');
            
            const username = document.getElementById('username').value.trim();
            const bio = document.getElementById('bio').value.trim();
            
            let isValid = true;
            
            if (!username) {
                showFieldError('username', '用户名不能为空');
                isValid = false;
            } else if (username.length < 2 || username.length > 16) {
                showFieldError('username', '用户名长度必须在2-16位之间');
                isValid = false;
            }
            
            if (!isValid) return;
            
            const formData = new FormData();
            formData.append('username', username);
            formData.append('bio', bio);
            formData.append('update_profile', '1');
            formData.append('ajax', '1');
            
            const originalContent = showLoading('update-profile-btn');
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                hideLoading('update-profile-btn', originalContent);
                
                if (data.success) {
                    showAlert('global-success-alert', data.message);
                } else {
                    showFieldError('username', data.message);
                }
            })
            .catch(error => {
                hideLoading('update-profile-btn', originalContent);
                showAlert('global-error-alert', '网络错误，请重试');
            });
        });
        
        document.getElementById('send-verification-btn').addEventListener('click', function() {
            hideAllErrors('change-email-form');
            
            const currentPassword = document.getElementById('current_password_email').value;
            const newEmail = document.getElementById('new_email').value.trim();
            
            let isValid = true;
            
            if (!currentPassword) {
                showFieldError('current_password_email', '当前密码不能为空');
                isValid = false;
            }
            
            if (!newEmail) {
                showFieldError('new_email', '新邮箱不能为空');
                isValid = false;
            } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(newEmail)) {
                showFieldError('new_email', '邮箱格式不正确');
                isValid = false;
            }
            
            if (!isValid) return;
            
            const formData = new FormData();
            formData.append('current_password', currentPassword);
            formData.append('new_email', newEmail);
            formData.append('request_email_change', '1');
            formData.append('ajax', '1');
            
            const originalContent = showLoading('send-verification-btn');
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                hideLoading('send-verification-btn', originalContent);
                
                if (data.success) {
                    document.getElementById('email-step-1').style.display = 'none';
                    document.getElementById('email-step-2').style.display = 'block';
                    document.getElementById('email-step-1-buttons').style.display = 'none';
                    document.getElementById('email-step-2-buttons').style.display = 'flex';
                    
                    document.getElementById('email-verification-message').textContent = 
                        `验证码已发送至 ${newEmail}，请在5分钟内完成验证`;
                    
                    showAlert('email-success-alert', data.message);
                } else {
                    if (data.message.includes('密码')) {
                        showFieldError('current_password_email', data.message);
                    } else {
                        showFieldError('new_email', data.message);
                    }
                }
            })
            .catch(error => {
                hideLoading('send-verification-btn', originalContent);
                showAlert('email-error-alert', '网络错误，请重试');
            });
        });
        
        document.getElementById('verify-code-btn').addEventListener('click', function() {
            hideAllErrors('change-email-form');
            
            const verificationCode = document.getElementById('verification_code').value.trim();
            
            let isValid = true;
            
            if (!verificationCode) {
                showFieldError('verification_code', '验证码不能为空');
                isValid = false;
            }
            
            if (!isValid) return;
            
            const formData = new FormData();
            formData.append('verification_code', verificationCode);
            formData.append('verify_email_code', '1');
            formData.append('ajax', '1');
            
            const originalContent = showLoading('verify-code-btn');
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                hideLoading('verify-code-btn', originalContent);
                
                if (data.success) {
                    document.getElementById('current-email').textContent = data.data.email;
                    
                    closeModal('email-modal');
                    
                    showAlert('global-success-alert', data.message);
                    
                    resetEmailForm();
                } else {
                    showFieldError('verification_code', data.message);
                }
            })
            .catch(error => {
                hideLoading('verify-code-btn', originalContent);
                showAlert('email-error-alert', '网络错误，请重试');
            });
        });
        
        function resetEmailForm() {
            document.getElementById('current_password_email').value = '';
            document.getElementById('new_email').value = '';
            document.getElementById('verification_code').value = '';
            
            document.getElementById('email-step-1').style.display = 'block';
            document.getElementById('email-step-2').style.display = 'none';
            document.getElementById('email-step-1-buttons').style.display = 'flex';
            document.getElementById('email-step-2-buttons').style.display = 'none';
            
            hideAllErrors('change-email-form');
        }
        
        document.getElementById('submit-password-change').addEventListener('click', function() {
            hideAllErrors('change-password-form');
            
            const currentPassword = document.getElementById('current_password_old').value;
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            let isValid = true;
            
            if (!currentPassword) {
                showFieldError('current_password_old', '当前密码不能为空');
                isValid = false;
            }
            
            if (!newPassword) {
                showFieldError('new_password', '新密码不能为空');
                isValid = false;
            } else if (newPassword.length < 6 || newPassword.length > 20) {
                showFieldError('new_password', '密码长度必须在6-20位之间');
                isValid = false;
            }
            
            if (newPassword !== confirmPassword) {
                showFieldError('confirm_password', '两次输入的密码不一致');
                isValid = false;
            }
            
            if (!isValid) return;
            
            const formData = new FormData();
            formData.append('current_password', currentPassword);
            formData.append('new_password', newPassword);
            formData.append('confirm_password', confirmPassword);
            formData.append('change_password', '1');
            formData.append('ajax', '1');
            
            const originalContent = showLoading('submit-password-change');
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('网络响应不正常');
                }
                return response.json();
            })
            .then(data => {
                hideLoading('submit-password-change', originalContent);
                
                if (data.success) {
                    closeModal('password-modal');
                    
                    showAlert('global-success-alert', data.message);
                    
                    resetPasswordByOldForm();
                } else {
                    if (data.message.includes('当前密码')) {
                        showFieldError('current_password_old', data.message);
                    } else if (data.message.includes('密码长度')) {
                        showFieldError('new_password', data.message);
                    } else if (data.message.includes('不一致')) {
                        showFieldError('confirm_password', data.message);
                    } else {
                        showAlert('password-error-alert', data.message);
                    }
                }
            })
            .catch(error => {
                hideLoading('submit-password-change', originalContent);
                showAlert('password-error-alert', '网络错误，请重试: ' + error.message);
            });
        });
        
        document.getElementById('send-reset-code-btn').addEventListener('click', function() {
            hideAllErrors('reset-password-form');
            
            const originalContent = showLoading('send-reset-code-btn');
            
            const formData = new FormData();
            formData.append('request_password_reset', '1');
            formData.append('ajax', '1');
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('网络响应不正常');
                }
                return response.json();
            })
            .then(data => {
                hideLoading('send-reset-code-btn', originalContent);
                
                if (data.success) {
                    document.getElementById('request-reset-step').style.display = 'none';
                    document.getElementById('confirm-reset-step').style.display = 'block';
                    
                    showAlert('password-success-alert', data.message);
                } else {
                    showAlert('password-error-alert', data.message);
                }
            })
            .catch(error => {
                hideLoading('send-reset-code-btn', originalContent);
                showAlert('password-error-alert', '网络错误，请重试: ' + error.message);
            });
        });
        
        document.getElementById('confirm-reset-btn').addEventListener('click', function() {
            hideAllErrors('reset-password-form');
            
            const resetCode = document.getElementById('reset_code').value.trim();
            const newResetPassword = document.getElementById('new_reset_password').value;
            const confirmResetPassword = document.getElementById('confirm_reset_password').value;
            
            let isValid = true;
            
            if (!resetCode) {
                showFieldError('reset_code', '验证码不能为空');
                isValid = false;
            }
            
            if (!newResetPassword) {
                showFieldError('new_reset_password', '新密码不能为空');
                isValid = false;
            } else if (newResetPassword.length < 6 || newResetPassword.length > 20) {
                showFieldError('new_reset_password', '密码长度必须在6-20位之间');
                isValid = false;
            }
            
            if (newResetPassword !== confirmResetPassword) {
                showFieldError('confirm_reset_password', '两次输入的密码不一致');
                isValid = false;
            }
            
            if (!isValid) return;
            
            const formData = new FormData();
            formData.append('reset_code', resetCode);
            formData.append('new_reset_password', newResetPassword);
            formData.append('confirm_reset_password', confirmResetPassword);
            formData.append('confirm_password_reset', '1');
            formData.append('ajax', '1');
            
            const originalContent = showLoading('confirm-reset-btn');
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('网络响应不正常');
                }
                return response.json();
            })
            .then(data => {
                hideLoading('confirm-reset-btn', originalContent);
                
                if (data.success) {
                    closeModal('password-modal');
                    
                    showAlert('global-success-alert', data.message);
                    
                    resetPasswordForm();
                    switchTab('password-by-old', document.querySelectorAll('.tab')[0]);
                } else {
                    if (data.message.includes('验证码')) {
                        showFieldError('reset_code', data.message);
                    } else if (data.message.includes('密码长度')) {
                        showFieldError('new_reset_password', data.message);
                    } else if (data.message.includes('不一致')) {
                        showFieldError('confirm_reset_password', data.message);
                    } else {
                        showAlert('password-error-alert', data.message);
                    }
                }
            })
            .catch(error => {
                hideLoading('confirm-reset-btn', originalContent);
                showAlert('password-error-alert', '网络错误，请重试: ' + error.message);
            });
        });
        
        function resetPasswordForm() {
            document.getElementById('reset_code').value = '';
            document.getElementById('new_reset_password').value = '';
            document.getElementById('confirm_reset_password').value = '';
            
            document.getElementById('request-reset-step').style.display = 'block';
            document.getElementById('confirm-reset-step').style.display = 'none';
            
            hideAllErrors('reset-password-form');
        }
        
        function resetPasswordByOldForm() {
            document.getElementById('current_password_old').value = '';
            document.getElementById('new_password').value = '';
            document.getElementById('confirm_password').value = '';
            
            hideAllErrors('change-password-form');
        }
        
        document.getElementById('cancel-reset').addEventListener('click', function() {
            resetPasswordForm();
        });
        
        document.getElementById('confirm-deactivate-btn').addEventListener('click', function() {
            hideAllErrors('deactivate-form');
            
            const confirm1 = document.getElementById('confirm_deactivate1').checked;
            const confirm2 = document.getElementById('confirm_deactivate2').checked;
            const confirm3 = document.getElementById('confirm_deactivate3').checked;
            const confirm4 = document.getElementById('confirm_deactivate4').checked;
            
            if (!confirm('确定要注销账号吗？此操作不可逆！')) {
                return;
            }
            
            if (!confirm1 || !confirm2 || !confirm3 || !confirm4) {
                showFieldError('deactivate', '请确认所有注销条款');
                return;
            }
            
            const formData = new FormData();
            formData.append('confirm_deactivate1', confirm1 ? '1' : '0');
            formData.append('confirm_deactivate2', confirm2 ? '1' : '0');
            formData.append('confirm_deactivate3', confirm3 ? '1' : '0');
            formData.append('confirm_deactivate4', confirm4 ? '1' : '0');
            formData.append('deactivate_account', '1');
            formData.append('ajax', '1');
            
            const originalContent = showLoading('confirm-deactivate-btn');
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                hideLoading('confirm-deactivate-btn', originalContent);
                
                if (data.success) {
                    showAlert('deactivate-success-alert', data.message);
                    
                    setTimeout(() => {
                        window.location.href = data.data.redirect || 'index.php';
                    }, 1500);
                } else {
                    showFieldError('deactivate', data.message);
                }
            })
            .catch(error => {
                hideLoading('confirm-deactivate-btn', originalContent);
                showAlert('deactivate-error-alert', '网络错误，请重试');
            });
        });
        
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
            document.body.style.overflow = '';
            
            if (modalId === 'email-modal') {
                resetEmailForm();
            } else if (modalId === 'password-modal') {
                resetPasswordForm();
                resetPasswordByOldForm();
            }
        }
        
        window.onclick = function(event) {
            if (event.target.classList.contains('modal-overlay')) {
                event.target.classList.remove('active');
                document.body.style.overflow = '';
                
                if (event.target.id === 'email-modal') {
                    resetEmailForm();
                } else if (event.target.id === 'password-modal') {
                    resetPasswordForm();
                    resetPasswordByOldForm();
                }
            }
        }
        
        function switchTab(tabId, tabElement) {
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            tabElement.classList.add('active');
            
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            document.getElementById(tabId).classList.add('active');
            
            if (tabId === 'password-by-email') {
                resetPasswordForm();
            } else {
                resetPasswordByOldForm();
            }
        }
    </script>

<?php include('common/footer.php'); ?>