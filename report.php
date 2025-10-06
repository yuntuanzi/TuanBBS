<?php
require_once 'common/functions.php';

// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

/**
 * 获取回复信息
 * @param int $reply_id 回复ID
 */
function get_reply($reply_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT r.*, u.username, u.avatar_url 
        FROM replies r
        JOIN users u ON r.user_id = u.user_id
        WHERE r.reply_id = :reply_id
    ");
    $stmt->bindParam(':reply_id', $reply_id, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * 获取用户信息
 * @param int $user_id 用户ID
 */
function get_user_info($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT user_id, username, avatar_url, role, status 
        FROM users 
        WHERE user_id = :user_id
    ");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}


$current_user = get_logged_in_user();

// 获取举报类型和目标ID
$target_type = isset($_GET['type']) ? $_GET['type'] : '';
$target_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// 验证举报类型
$valid_types = ['topic', 'reply', 'user'];
if (!in_array($target_type, $valid_types) || $target_id <= 0) {
    header('Location: index.php');
    exit;
}

// 防止举报自己
if ($target_type === 'user' && $target_id == $current_user['user_id']) {
    die("<script>alert('不能举报自己');history.back();</script>");
}

// 获取举报目标信息
$target_info = [];
switch ($target_type) {
    case 'topic':
        $target_info = get_topic($target_id);
        if (!$target_info) {
            header('Location: index.php');
            exit;
        }
        // 防止举报自己的主题
        if ($target_info['user_id'] == $current_user['user_id']) {
            die("<script>alert('不能举报自己的主题');history.back();</script>");
        }
        $target_title = $target_info['title'];
        $target_url = "topic.php?id=$target_id";
        break;
    
    case 'reply':
        $target_info = get_reply($target_id);
        if (!$target_info) {
            header('Location: index.php');
            exit;
        }
        // 防止举报自己的回复
        if ($target_info['user_id'] == $current_user['user_id']) {
            die("<script>alert('不能举报自己的回复');history.back();</script>");
        }
        $target_title = mb_substr(strip_tags($target_info['content']), 0, 50) . '...';
        $target_url = "topic.php?id={$target_info['topic_id']}#reply-$target_id";
        break;
    
    case 'user':
        $target_info = get_user_info($target_id);
        if (!$target_info) {
            header('Location: index.php');
            exit;
        }
        $target_title = $target_info['username'];
        $target_url = "profile.php?id=$target_id";
        break;
}

// 检查24小时内是否已经举报过相同内容
global $pdo;
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM reports 
    WHERE user_id = :user_id 
    AND target_type = :target_type 
    AND target_id = :target_id 
    AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
");
$stmt->bindParam(':user_id', $current_user['user_id']);
$stmt->bindParam(':target_type', $target_type);
$stmt->bindParam(':target_id', $target_id);
$stmt->execute();
$recent_reports = $stmt->fetchColumn();

if ($recent_reports > 0) {
    die("<script>alert('24小时内已举报过此内容，请勿重复举报');history.back();</script>");
}

// 检查今日举报次数是否超过限制
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM reports 
    WHERE user_id = :user_id 
    AND DATE(created_at) = CURDATE()
");
$stmt->bindParam(':user_id', $current_user['user_id']);
$stmt->execute();
$today_reports = $stmt->fetchColumn();

if ($today_reports >= 20) {
    die("<script>alert('今日举报次数已达上限（20次），请明天再试');history.back();</script>");
}

// 处理举报提交
$report_submitted = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    
    // 验证举报内容
    if (empty($reason)) {
        $error = '请选择举报原因';
    } elseif (strlen($description) > 500) {
        $error = '举报描述不能超过500字';
    } else {
        // 插入举报记录
        $stmt = $pdo->prepare("
            INSERT INTO reports (
                user_id, 
                target_type, 
                target_id, 
                reason, 
                description, 
                status, 
                created_at
            ) VALUES (
                :user_id, 
                :target_type, 
                :target_id, 
                :reason, 
                :description, 
                'pending', 
                NOW()
            )
        ");
        
        $stmt->bindParam(':user_id', $current_user['user_id']);
        $stmt->bindParam(':target_type', $target_type);
        $stmt->bindParam(':target_id', $target_id);
        $stmt->bindParam(':reason', $reason);
        $stmt->bindParam(':description', $description);
        
        try {
            $stmt->execute();
            $report_submitted = true;
            $today_reports++;
        } catch (PDOException $e) {
            $error = '举报提交失败，请稍后再试';
            error_log("举报提交错误: " . $e->getMessage());
        }
    }
}

// 获取举报原因选项
$report_reasons = [
    '违规内容' => '包含违法、色情、暴力等违规内容',
    '垃圾广告' => '发布垃圾广告、推广信息',
    '人身攻击' => '包含人身攻击、辱骂等不当言论',
    '侵犯隐私' => '泄露他人隐私信息',
    '抄袭内容' => '未经授权转载或抄袭他人内容',
    '其他原因' => '其他不符合社区规范的行为'
];

include('common/header.php');
?>
<title>举报<?= $target_type === 'user' ? '用户' : ($target_type === 'topic' ? '主题' : '回复') ?> - <?= htmlspecialchars($site_info['site_title']) ?></title>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen flex flex-col overflow-x-hidden m-0 p-0">
    <div id="error-container" class="fixed top-4 left-1/2 transform -translate-x-1/2 z-50 max-w-2xl w-full bg-red-50 border border-red-200 text-red-700 p-4 rounded-lg shadow-lg hidden">
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <i class="fa fa-exclamation-circle text-xl"></i>
            </div>
            <div class="ml-3 flex-1">
                <h3 class="text-sm font-medium">操作失败</h3>
                <div class="mt-2 text-sm" id="error-details"></div>
                <div class="mt-4">
                    <button onclick="document.getElementById('error-container').classList.add('hidden')" class="inline-flex items-center px-3 py-1.5 border border-red-300 rounded-md text-sm font-medium text-red-700 bg-white hover:bg-red-50">
                        关闭
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div id="success-toast" class="fixed top-4 left-1/2 transform -translate-x-1/2 z-50 hidden">
        <div class="bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg flex items-center gap-2">
            <i class="fa fa-check-circle text-xl"></i>
            <span id="success-message" class="text-base font-medium"></span>
        </div>
    </div>

    <main class="flex-grow flex flex-col md:flex-row transition-all duration-300 pt-6" id="main-container">
        <?php include('common/asideleft.php'); ?>

        <div class="flex-grow transition-all duration-300 p-4 md:ml-64 lg:ml-72" id="content-area">
            <div class="max-w-3xl mx-auto">
                <div class="bg-white rounded-xl shadow-card p-5 mb-6">
                    <?php if ($report_submitted): ?>
                        <div class="text-center py-8">
                            <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fa fa-check text-green-600 text-2xl"></i>
                            </div>
                            <h2 class="text-2xl font-bold text-slate-800 mb-2">举报成功</h2>
                            <p class="text-slate-600 mb-6">感谢您的举报，管理员将会尽快处理</p>
                            
                            <div class="flex justify-center gap-4">
                                <a href="my_reports.php" class="px-6 py-2.5 bg-white border border-slate-200 rounded-lg text-slate-700 hover:bg-slate-50 transition-colors">
                                    我的举报记录
                                </a>
                                <a href="index.php" class="px-6 py-2.5 bg-primary text-white rounded-lg hover:bg-primary-dark transition-colors">
                                    回到主页
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="flex items-center justify-between mb-4">
                            <h1 class="text-xl font-bold">举报<?= $target_type === 'user' ? '用户' : ($target_type === 'topic' ? '主题' : '回复') ?></h1>
                            <a href="javascript:history.back()" class="text-sm text-slate-500 hover:text-primary transition-colors">
                                <i class="fa fa-arrow-left mr-1"></i>返回
                            </a>
                        </div>
                        
                        <div class="mb-4 p-3 bg-blue-50 text-blue-700 rounded-lg border border-blue-200 text-sm">
                            <i class="fa fa-info-circle mr-1"></i>
                            今日已举报 <?= $today_reports ?> 次（最多20次/天），24小时内不可重复举报同一内容
                        </div>
                        
                        <?php if (isset($error)): ?>
                        <div class="mb-4 p-4 bg-red-50 text-red-700 rounded-lg border border-red-200">
                            <i class="fa fa-exclamation-circle mr-2"></i>
                            <?= htmlspecialchars($error) ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="mb-6 p-4 bg-slate-50 rounded-lg border border-slate-200">
                            <h3 class="text-sm font-medium text-slate-500 mb-2">举报对象</h3>
                            <div class="flex items-start gap-3">
                                <?php if ($target_type === 'user'): ?>
                                <img src="<?= htmlspecialchars($target_info['avatar_url'] ?? 'static/images/default-avatar.png') ?>" alt="用户头像" class="w-10 h-10 rounded-full">
                                <div>
                                    <p class="font-medium"><?= htmlspecialchars($target_info['username']) ?></p>
                                    <p class="text-sm text-slate-500">用户ID: <?= $target_id ?></p>
                                </div>
                                <?php elseif ($target_type === 'topic'): ?>
                                <div class="w-10 h-10 rounded-lg bg-primary/10 flex items-center justify-center">
                                    <i class="fa fa-file-text-o text-primary"></i>
                                </div>
                                <div>
                                    <p class="font-medium"><?= htmlspecialchars($target_title) ?></p>
                                    <p class="text-sm text-slate-500">主题ID: <?= $target_id ?></p>
                                </div>
                                <?php else: ?>
                                <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center">
                                    <i class="fa fa-comment-o text-blue-500"></i>
                                </div>
                                <div>
                                    <p class="font-medium">回复内容</p>
                                    <p class="text-sm text-slate-500"><?= htmlspecialchars($target_title) ?></p>
                                    <p class="text-sm text-slate-500">回复ID: <?= $target_id ?></p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <form method="post" id="report-form">
                            <div class="mb-6">
                                <label class="block text-sm font-medium text-slate-700 mb-2">举报原因</label>
                                <div class="space-y-2">
                                    <?php foreach ($report_reasons as $value => $label): ?>
                                    <div class="flex items-center">
                                        <input type="radio" id="reason-<?= md5($value) ?>" name="reason" value="<?= htmlspecialchars($value) ?>" class="h-4 w-4 text-primary focus:ring-primary border-slate-300" required>
                                        <label for="reason-<?= md5($value) ?>" class="ml-2 block text-sm text-slate-700">
                                            <?= htmlspecialchars($value) ?>
                                            <span class="text-xs text-slate-500 block"><?= htmlspecialchars($label) ?></span>
                                        </label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <div class="mb-6">
                                <label for="description" class="block text-sm font-medium text-slate-700 mb-2">详细描述（可选）</label>
                                <textarea id="description" name="description" rows="4" class="w-full px-3 py-2 border border-slate-300 rounded-lg shadow-sm focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary" placeholder="请详细描述举报原因，帮助我们更好地处理问题..."></textarea>
                                <p class="mt-1 text-xs text-slate-500">最多500字</p>
                            </div>
                            
                            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200">
                                <button type="button" onclick="history.back()" class="px-4 py-2 border border-slate-300 rounded-lg text-sm font-medium text-slate-700 bg-white hover:bg-slate-50 transition-colors">
                                    取消
                                </button>
                                <button type="submit" class="px-4 py-2 rounded-lg text-sm font-medium text-white bg-red-600 hover:bg-red-700 transition-colors" <?= $today_reports >= 20 ? 'disabled title="今日举报次数已达上限"' : '' ?>>
                                    <i class="fa fa-flag mr-1"></i> 提交举报
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script>
    document.getElementById('report-form')?.addEventListener('submit', function(e) {
        const reason = document.querySelector('input[name="reason"]:checked');
        if (!reason) {
            e.preventDefault();
            showError('请选择举报原因');
            return;
        }
        
        const description = document.getElementById('description').value;
        if (description.length > 500) {
            e.preventDefault();
            showError('举报描述不能超过500字');
            return;
        }
    });
    
    function showError(message) {
        const errorContainer = document.getElementById('error-container');
        const errorDetails = document.getElementById('error-details');
        
        errorDetails.textContent = message;
        errorContainer.classList.remove('hidden');
        
        setTimeout(() => {
            errorContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 100);
    }
    </script>

    <?php include('common/footer.php'); ?>