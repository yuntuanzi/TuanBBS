<?php
require_once 'common/functions.php';

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

function get_user_by_email($email) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("查询用户失败: " . $e->getMessage());
        return null;
    }
}

function reset_user_password($email, $new_password) {
    global $pdo;
    
    try {
        $user = get_user_by_email($email);
        if (!$user) {
            return ['success' => false, 'message' => '该邮箱未注册'];
        }
        
        $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
        $stmt->execute([$password_hash, $email]);
        
        return ['success' => true, 'message' => '密码重置成功'];
    } catch (PDOException $e) {
        error_log("重置密码失败: " . $e->getMessage());
        return ['success' => false, 'message' => '密码重置失败', 'error_details' => $e->getMessage()];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $response = ['success' => false, 'message' => '', 'error_details' => '', 'field' => ''];
    
    try {
        if (!class_exists('Redis')) {
            throw new Exception('Redis扩展未安装');
        }
        
        $redis = new Redis();
        $redis->connect(REDIS_HOST, REDIS_PORT);
        if (REDIS_PASS) $redis->auth(REDIS_PASS);
        
        if ($_POST['action'] === 'send_code') {
            $email_prefix = trim($_POST['email_prefix'] ?? '');
            $email = $email_prefix . '@qq.com';
            
            if (empty($email_prefix)) {
                $response['message'] = '请输入QQ邮箱前缀（仅需输入@qq.com前的部分）';
                $response['field'] = 'email_prefix';
            } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $email_prefix)) {
                $response['message'] = '邮箱前缀只能包含字母、数字和下划线';
                $response['field'] = 'email_prefix';
            } else {
                $user = get_user_by_email($email);
                if (!$user) {
                    $response['message'] = '该邮箱未注册';
                    $response['field'] = 'email_prefix';
                } else {
                    $cooldownKey = REDIS_PREFIX . 'mail_cooldown:forgot_password:' . $email;
                    if ($redis->exists($cooldownKey)) {
                        $ttl = $redis->ttl($cooldownKey);
                        $response['message'] = "请勿频繁发送验证码，请在{$ttl}秒后再试";
                        $response['field'] = 'email_prefix';
                    } else {
                        $mailService = MailService::getInstance();
                        $result = $mailService->sendVerificationCode($email, 'forgot_password');
                        
                        if ($result['success']) {
                            $redis->setex($cooldownKey, 60, '1');
                            
                            $response['success'] = true;
                            $response['message'] = '验证码已发送至您的QQ邮箱，请查收';
                        } else {
                            $response['message'] = $result['message'] ?? '验证码发送失败';
                        }
                    }
                }
            }
        } elseif ($_POST['action'] === 'reset_password') {
            $email_prefix = trim($_POST['email_prefix'] ?? '');
            $email = $email_prefix . '@qq.com';
            $verification_code = trim($_POST['verification_code'] ?? '');
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            if (empty($email_prefix)) {
                $response['message'] = '请输入QQ邮箱前缀';
                $response['field'] = 'email_prefix';
            } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $email_prefix)) {
                $response['message'] = '邮箱前缀只能包含字母、数字和下划线';
                $response['field'] = 'email_prefix';
            } elseif (empty($verification_code)) {
                $response['message'] = '请输入验证码';
                $response['field'] = 'verification_code';
            } elseif (strlen($verification_code) !== 6 || !is_numeric($verification_code)) {
                $response['message'] = '验证码为6位数字';
                $response['field'] = 'verification_code';
            } elseif (strlen($new_password) < 6 || strlen($new_password) > 20) {
                $response['message'] = '密码长度必须在6-20位之间';
                $response['field'] = 'new_password';
            } elseif ($new_password !== $confirm_password) {
                $response['message'] = '两次输入的密码不一致';
                $response['field'] = 'confirm_password';
            } else {
                $key = REDIS_PREFIX . 'verify_code:forgot_password:' . $email;
                $storedCode = $redis->get($key);
                
                if (!$storedCode) {
                    $response['message'] = '验证码已过期，请重新获取';
                    $response['field'] = 'verification_code';
                } else {
                    $codeData = json_decode($storedCode, true);
                    if ($codeData['code'] !== $verification_code) {
                        $response['message'] = '验证码不正确，请重新输入';
                        $response['field'] = 'verification_code';
                    } else {
                        $redis->del($key);
                        
                        $result = reset_user_password($email, $new_password);
                        
                        if ($result['success']) {
                            $response['success'] = true;
                            $response['message'] = '密码重置成功，请使用新密码登录';
                            
                            $user = get_user_by_email($email);
                            if ($user) {
                                create_notification(
                                    $user['id'],
                                    null,
                                    'system',
                                    'user',
                                    $user['id'],
                                    '您的账户密码已成功重置。如非本人操作，请立即联系客服。'
                                );
                            }
                        } else {
                            $response['message'] = $result['message'];
                            $response['error_details'] = $result['error_details'] ?? '';
                        }
                    }
                }
            }
        }
    } catch (Exception $e) {
        $response['message'] = '服务器处理失败';
        $response['error_details'] = '错误信息: ' . $e->getMessage() . ' 在文件 ' . $e->getFile() . ' 第 ' . $e->getLine() . ' 行';
        error_log('密码找回错误: ' . $response['error_details']);
    }
    
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response);
    exit;
}

include('common/header.php');
?>
    <title>找回密码 - <?= htmlspecialchars($site_info['site_title']) ?></title>
    <meta name="keywords" content="<?= htmlspecialchars($site_info['site_title']) ?>, 找回<?= htmlspecialchars($site_info['site_title']) ?>密码, 忘记<?= htmlspecialchars($site_info['site_title']) ?>密码, <?= htmlspecialchars($site_info['site_title']) ?>账户安全">
    <meta name="description" content="忘记密码？别担心，可以通过邮箱重置您在<?= htmlspecialchars($site_info['site_title']) ?>的账户密码。">
    <script>
document.addEventListener('DOMContentLoaded', function() {
    const sendCodeBtn = document.getElementById('send-code-btn');
    const resetForm = document.getElementById('reset-form');
    const errorContainer = document.getElementById('error-container');
    const errorDetails = document.getElementById('error-details');
    
    let lastCodeSentTime = 0;
    
    sendCodeBtn.addEventListener('click', function() {
        const emailPrefix = document.getElementById('email-prefix').value.trim();
        const now = Date.now();
        
        if (now - lastCodeSentTime < 60000) {
            showError('请不要频繁发送验证码，60秒后再试', 'error', '', 'email_prefix');
            return;
        }
        
        if (!emailPrefix) {
            showError('请输入QQ邮箱前缀（仅需输入@qq.com前的部分）', 'error', '', 'email_prefix');
            return;
        }
        
        if (!/^[a-zA-Z0-9_]+$/.test(emailPrefix)) {
            showError('邮箱前缀只能包含字母、数字和下划线', 'error', '', 'email_prefix');
            return;
        }
        
        sendCodeBtn.disabled = true;
        const originalText = sendCodeBtn.textContent;
        sendCodeBtn.textContent = '发送中...';
        
        const protocol = window.location.protocol;
        const host = window.location.host;
        const apiUrl = `${protocol}//${host}/api/mail.php`;
        
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 10000);
        
        fetch(apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            credentials: 'include',
            body: JSON.stringify({
                type: 'forgot_password',
                email: emailPrefix + '@qq.com'
            }),
            signal: controller.signal
        })
        .then(response => {
            clearTimeout(timeoutId);
            
            if (!response.ok) {
                return response.text().then(text => {
                    try {
                        const errorData = JSON.parse(text);
                        throw new Error(errorData.message || `服务器错误: ${response.status}`);
                    } catch (e) {
                        throw new Error(`服务器错误: ${text.substring(0, 200)}`);
                    }
                });
            }
            
            return response.json();
        })
        .then(data => {
            if (data.success) {
                lastCodeSentTime = Date.now();
                startCountdown(60);
                showError('验证码已发送至您的QQ邮箱，请查收', 'success', '', 'verification_code');
            } else {
                showError(data.message || '发送验证码失败', 'error', data.error_details, 'email_prefix');
                sendCodeBtn.disabled = false;
                sendCodeBtn.textContent = originalText;
            }
        })
        .catch(error => {
            console.error('验证码发送错误:', error);
            let errorMsg = '请求失败: ';
            
            if (error.name === 'AbortError') {
                errorMsg += '请求超时，请稍后再试';
            } else if (error.message.includes('Failed to fetch')) {
                errorMsg += '网络连接异常，请检查网络';
            } else {
                errorMsg += error.message;
            }
            
            showError(errorMsg, 'error', '', 'email_prefix');
            sendCodeBtn.disabled = false;
            sendCodeBtn.textContent = originalText;
        });
    });
    
    resetForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        hideError();
        
        const emailPrefix = document.getElementById('email-prefix').value.trim();
        const verificationCode = document.getElementById('verification-code').value.trim();
        const newPassword = document.getElementById('new-password').value;
        const confirmPassword = document.getElementById('confirm-password').value;
        
        if (!emailPrefix) {
            showError('请输入QQ邮箱前缀（仅需输入@qq.com前的部分）', 'error', '', 'email_prefix');
            return;
        }
        if (!/^[a-zA-Z0-9_]+$/.test(emailPrefix)) {
            showError('邮箱前缀只能包含字母、数字和下划线', 'error', '', 'email_prefix');
            return;
        }
        if (!verificationCode) {
            showError('请输入验证码', 'error', '', 'verification_code');
            return;
        }
        if (verificationCode.length !== 6 || !/^\d{6}$/.test(verificationCode)) {
            showError('验证码为6位数字', 'error', '', 'verification_code');
            return;
        }
        if (newPassword.length < 6 || newPassword.length > 20) {
            showError('密码长度必须在6-20位之间', 'error', '', 'new_password');
            return;
        }
        if (newPassword !== confirmPassword) {
            showError('两次输入的密码不一致', 'error', '', 'confirm_password');
            return;
        }
        
        const formData = new FormData(resetForm);
        formData.append('action', 'reset_password');
        
        const submitBtn = resetForm.querySelector('button[type="submit"]');
        const originalBtnText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i><span>重置中...</span>';
        
        fetch('forgot_password.php', {
            method: 'POST',
            credentials: 'include',
            body: formData
        })
        .then(response => {
            const responseClone = response.clone();
            
            if (!response.ok) {
                return responseClone.text().then(text => {
                    throw new Error(`服务器错误 (${response.status}): ${text.substring(0, 200)}`);
                });
            }
            
            return responseClone.json().catch(error => {
                return responseClone.text().then(text => {
                    throw new Error(`解析响应失败: ${text.substring(0, 200)}`);
                });
            });
        })
        .then(data => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
            
            if (data.success) {
                showError(data.message, 'success');
                setTimeout(() => {
                    window.location.href = 'login.php';
                }, 1500);
            } else {
                showError(data.message, 'error', data.error_details, data.field);
            }
        })
        .catch(error => {
            console.error('密码重置请求失败:', error);
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
            showError('密码重置失败', 'error', error.message);
        });
    });
    
    function startCountdown(seconds) {
        let remaining = seconds;
        const interval = setInterval(() => {
            sendCodeBtn.textContent = `${remaining}秒后重新获取`;
            remaining--;
            
            if (remaining < 0) {
                clearInterval(interval);
                sendCodeBtn.disabled = false;
                sendCodeBtn.textContent = '获取验证码';
            }
        }, 1000);
    }
    
    function showError(message, type = 'error', details = '', field = '') {
        errorContainer.querySelector('#error-message').textContent = message;
        errorContainer.classList.remove('hidden', 'bg-red-50', 'text-red-600', 'bg-green-50', 'text-green-600');
        
        if (details) {
            errorDetails.textContent = details;
            errorDetails.classList.remove('hidden');
        } else {
            errorDetails.textContent = '';
            errorDetails.classList.add('hidden');
        }
        
        if (type === 'error') {
            errorContainer.classList.add('bg-red-50', 'text-red-600');
            errorContainer.classList.remove('bg-green-50', 'text-green-600');
        } else {
            errorContainer.classList.add('bg-green-50', 'text-green-600');
            errorContainer.classList.remove('bg-red-50', 'text-red-600');
        }
        
        errorContainer.classList.remove('hidden');
        
        if (field) {
            const element = document.getElementById(field);
            if (element) {
                document.querySelectorAll('input').forEach(el => {
                    el.classList.remove('border-red-500', 'focus:border-red-500');
                });
                
                element.classList.add('border-red-500', 'focus:border-red-500');
                
                element.scrollIntoView({ behavior: 'smooth', block: 'center' });
                
                element.classList.add('animate-pulse');
                setTimeout(() => {
                    element.classList.remove('animate-pulse');
                }, 1000);
            }
        }
    }
    
    function hideError() {
        errorContainer.classList.add('hidden');
        errorDetails.classList.add('hidden');
        
        document.querySelectorAll('input').forEach(el => {
            el.classList.remove('border-red-500', 'focus:border-red-500', 'animate-pulse');
        });
    }
    
    document.querySelectorAll('input').forEach(input => {
        input.addEventListener('input', hideError);
    });
});
    </script>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md bg-white rounded-2xl shadow-card p-8">
        <div class="text-center mb-8">
            <img src="<?= htmlspecialchars($site_info['site_logo'] ?? 'static/images/default-avatar.png') ?>" alt="<?= htmlspecialchars($site_info['site_title']) ?>Logo" class="w-16 h-16 rounded-lg mx-auto mb-4">
            <h1 class="text-2xl font-bold text-primary">找回密码</h1>
            <p class="text-slate-500">通过邮箱验证重置您的密码</p>
        </div>
        
        <div id="error-container" class="mb-4 p-3 rounded-lg text-sm hidden">
            <i class="fa fa-exclamation-circle mr-1"></i> <span id="error-message"></span>
            <div id="error-details" class="mt-2 text-xs hidden"></div>
        </div>
        
        <form id="reset-form" class="space-y-4">
            <div>
                <label for="email-prefix" class="block text-sm font-medium text-slate-700 mb-1">QQ邮箱</label>
                <div class="flex">
                    <input type="text" id="email-prefix" name="email_prefix" class="flex-1 px-4 py-3 rounded-l-lg border border-slate-300 focus:border-primary focus:ring-2 focus:ring-primary/50 transition-all" placeholder="请输入QQ邮箱前缀" required>
                    <span class="inline-flex items-center px-3 rounded-r-lg border border-l-0 border-slate-300 bg-slate-50 text-slate-500">@qq.com</span>
                </div>
                <p class="mt-1 text-xs text-slate-500">只需输入QQ邮箱前缀，如:123456或yourname</p>
            </div>
            
            <div>
                <label for="verification-code" class="block text-sm font-medium text-slate-700 mb-1">验证码</label>
                <div class="flex gap-2">
                    <input type="text" id="verification-code" name="verification_code" class="flex-1 px-4 py-3 rounded-lg border border-slate-300 focus:border-primary focus:ring-2 focus:ring-primary/50 transition-all" placeholder="请输入6位验证码" required>
                    <button type="button" id="send-code-btn" class="whitespace-nowrap px-4 py-3 rounded-lg border border-primary text-primary bg-white hover:bg-primary/10 hover:border-primary/80 focus:outline-none focus:ring-2 focus:ring-primary/50 focus:ring-offset-2 transition-all duration-200 ease-in-out">
                        获取验证码
                    </button>
                </div>
            </div>
            
            <div>
                <label for="new-password" class="block text-sm font-medium text-slate-700 mb-1">新密码</label>
                <input type="password" id="new-password" name="new_password" class="w-full px-4 py-3 rounded-lg border border-slate-300 focus:border-primary focus:ring-2 focus:ring-primary/50 transition-all" placeholder="请输入6-20位新密码" required>
            </div>
            
            <div>
                <label for="confirm-password" class="block text-sm font-medium text-slate-700 mb-1">确认新密码</label>
                <input type="password" id="confirm-password" name="confirm_password" class="w-full px-4 py-3 rounded-lg border border-slate-300 focus:border-primary focus:ring-2 focus:ring-primary/50 transition-all" placeholder="请再次输入新密码" required>
            </div>
            
            <button type="submit" class="btn-primary w-full flex items-center justify-center gap-2">
                <i class="fa fa-key"></i>
                <span>重置密码</span>
            </button>
        </form>
        
        <div class="mt-6 text-center text-sm text-slate-500">
            <p>想起密码了? <a href="login.php" class="text-primary font-medium hover:text-dark">立即登录</a></p>
        </div>
    </div>
</body>
</html>