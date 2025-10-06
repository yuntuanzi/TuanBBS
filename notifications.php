<?php
require_once 'common/functions.php';

$user = get_logged_in_user();
if (!$user) {
    header('Location: login.php?redirect=notifications.php');
    exit;
}

$user_id = $user['user_id'];

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function get_user_notifications($user_id, $type = '', $page = 1, $per_page = 20) {
    global $pdo;
    
    $user_id = intval($user_id);
    $page = max(1, intval($page));
    $offset = ($page - 1) * $per_page;
    $per_page = intval($per_page);
    
    $conditions = ['n.user_id = :user_id'];
    $params = [':user_id' => $user_id];
    
    if (!empty($type)) {
        $conditions[] = 'n.type = :type';
        $params[':type'] = $type;
    }
    
    $whereClause = implode(' AND ', $conditions);
    
    $stmt = $pdo->prepare("
        SELECT n.*, 
               u.username as sender_username, 
               u.avatar_url as sender_avatar,
               t1.title as topic_title,
               t1.topic_id as direct_topic_id,
               r.content as reply_content,
               t2.title as reply_topic_title,
               t2.topic_id as reply_topic_id
        FROM notifications n
        LEFT JOIN users u ON n.sender_id = u.user_id
        LEFT JOIN topics t1 ON (n.target_type = 'topic' AND n.target_id = t1.topic_id)
        LEFT JOIN replies r ON (n.target_type = 'reply' AND n.target_id = r.reply_id)
        LEFT JOIN topics t2 ON (n.target_type = 'reply' AND r.topic_id = t2.topic_id)
        WHERE $whereClause
        ORDER BY n.created_at DESC
        LIMIT :offset, :per_page
    ");
    
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindParam(':per_page', $per_page, PDO::PARAM_INT);
    
    $stmt->execute();
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM notifications n
        WHERE $whereClause
    ");
    
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    
    $countStmt->execute();
    $total = $countStmt->fetchColumn();
    
    return [
        'notifications' => $notifications,
        'total' => $total,
        'page' => $page,
        'per_page' => $per_page,
        'total_pages' => ceil($total / $per_page)
    ];
}

function mark_notifications_as_read($user_id, $notification_id = null) {
    global $pdo;
    
    $user_id = intval($user_id);
    if ($user_id <= 0) {
        return ['success' => false, 'message' => '无效的用户ID'];
    }
    
    $conditions = ['user_id = :user_id', 'is_read = 0'];
    $params = [':user_id' => $user_id];
    
    if ($notification_id !== null) {
        $notification_id = intval($notification_id);
        if ($notification_id <= 0) {
            return ['success' => false, 'message' => '无效的通知ID'];
        }
        $conditions[] = 'notification_id = :notification_id';
        $params[':notification_id'] = $notification_id;
    }
    
    $whereClause = implode(' AND ', $conditions);
    
    try {
        $stmt = $pdo->prepare("
            UPDATE notifications
            SET is_read = 1
            WHERE $whereClause
        ");
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            return ['success' => true, 'message' => '操作成功'];
        } else {
            return ['success' => false, 'message' => '没有可标记的未读通知'];
        }
    } catch (PDOException $e) {
        $errorMsg = '数据库操作失败: ' . $e->getMessage();
        error_log($errorMsg);
        return ['success' => false, 'message' => $errorMsg];
    }
}

$error_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = '请求验证失败：CSRF令牌不匹配，请刷新页面重试';
        if ($is_ajax) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => $error_message]);
            exit;
        }
    } else {
        if (isset($_POST['action'])) {
            if ($_POST['action'] === 'mark_read' && isset($_POST['notification_id'])) {
                $result = mark_notifications_as_read($user_id, $_POST['notification_id']);
                echo json_encode($result);
                exit;
            } elseif ($_POST['action'] === 'mark_all_read') {
                $result = mark_notifications_as_read($user_id);
                if ($result['success']) {
                    $_SESSION['success_message'] = '所有通知已标记为已读';
                    header('Location: notifications.php');
                    exit;
                } else {
                    $error_message = $result['message'];
                }
            } else {
                $error_message = '无效的操作类型';
            }
        } else {
            $error_message = '缺少操作参数';
        }
    }
    
    if (!empty($error_message) && !$is_ajax) {
        $_SESSION['error_message'] = $error_message;
        header('Location: notifications.php');
        exit;
    }
}

include('common/header.php');

$current_page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$current_type = isset($_GET['type']) && in_array($_GET['type'], ['reply', 'like', 'mention', 'system', 'follow']) ? $_GET['type'] : '';

$notifications_data = get_user_notifications($user_id, $current_type, $current_page);
$notifications = $notifications_data['notifications'];
?>
    <title>消息通知 - <?php echo htmlspecialchars($site_info['site_title']); ?></title>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen flex flex-col overflow-x-hidden m-0 p-0">
    <div id="error-container" class="fixed top-4 left-1/2 transform -translate-x-1/2 z-50 max-w-2xl w-full bg-red-50 border border-red-200 text-red-700 p-4 rounded-lg shadow-lg hidden">
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <i class="fa fa-exclamation-circle text-xl"></i>
            </div>
            <div class="ml-3 flex-1">
                <h3 class="text-sm font-medium">操作失败</h3>
                <div class="mt-2 text-sm" id="error-details">
                </div>
                <div class="mt-4">
                    <button onclick="document.getElementById('error-container').classList.add('hidden')" class="inline-flex items-center px-3 py-1.5 border border-red-300 rounded-md text-sm font-medium text-red-700 bg-white hover:bg-red-50">
                        关闭
                    </button>
                </div>
            </div>
        </div>
    </div>

    <main class="flex-grow flex flex-col md:flex-row transition-all duration-300 pt-6" id="main-container">
        <?php include('common/asideleft.php'); ?>
        
        <div class="flex-grow transition-all duration-300 p-4 md:ml-64 lg:ml-72" id="content-area">
            <div class="max-w-5xl mx-auto">
                <div class="flex items-center justify-between mb-6">
                    <h1 class="text-2xl font-bold">消息通知</h1>
                    <form method="post" onsubmit="return confirm('确定要标记所有通知为已读吗？');">
                        <input type="hidden" name="action" value="mark_all_read">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <button type="submit" class="text-sm text-primary hover:text-dark transition-colors">
                            全部标记为已读
                        </button>
                    </form>
                </div>
                
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="bg-success/10 text-success border border-success/20 rounded-lg p-3 mb-6 flex items-center gap-2">
                        <i class="fa fa-check-circle"></i>
                        <span><?php echo htmlspecialchars($_SESSION['success_message']); ?></span>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error_message'])): ?>
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            showError('<?php echo addslashes($_SESSION['error_message']); ?>');
                        });
                    </script>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>
                
                <div class="bg-white rounded-xl shadow-card overflow-hidden mb-6">
                    <div class="flex border-b border-slate-100">
                        <a href="notifications.php" class="flex-1 py-3 text-center font-medium <?php echo $current_type === '' ? 'text-primary border-b-2 border-primary' : 'text-slate-500 hover:text-primary transition-colors'; ?>">
                            全部
                        </a>
                        <a href="notifications.php?type=reply" class="flex-1 py-3 text-center font-medium <?php echo $current_type === 'reply' ? 'text-primary border-b-2 border-primary' : 'text-slate-500 hover:text-primary transition-colors'; ?>">
                            回复
                        </a>
                        <a href="notifications.php?type=like" class="flex-1 py-3 text-center font-medium <?php echo $current_type === 'like' ? 'text-primary border-b-2 border-primary' : 'text-slate-500 hover:text-primary transition-colors'; ?>">
                            点赞
                        </a>
                        <a href="notifications.php?type=follow" class="flex-1 py-3 text-center font-medium <?php echo $current_type === 'follow' ? 'text-primary border-b-2 border-primary' : 'text-slate-500 hover:text-primary transition-colors'; ?>">
                            关注
                        </a>
                        <a href="notifications.php?type=system" class="flex-1 py-3 text-center font-medium <?php echo $current_type === 'system' ? 'text-primary border-b-2 border-primary' : 'text-slate-500 hover:text-primary transition-colors'; ?>">
                            系统
                        </a>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-card overflow-hidden">
                    <?php if (empty($notifications)): ?>
                        <div class="p-8 text-center">
                            <div class="w-16 h-16 mx-auto bg-slate-100 rounded-full flex items-center justify-center mb-4">
                                <i class="fa fa-bell-o text-2xl text-slate-400"></i>
                            </div>
                            <h3 class="text-lg font-medium text-slate-700 mb-2">暂无通知</h3>
                            <p class="text-slate-500">当有新的消息通知时，会显示在这里</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($notifications as $notification): ?>
                            <div class="p-4 border-b border-slate-100 hover:bg-slate-50 transition-colors <?php echo !$notification['is_read'] ? 'bg-primary/5' : ''; ?>">
                                <div class="flex items-start gap-3">
                                    <div class="flex-shrink-0">
                                        <?php if ($notification['type'] === 'system'): ?>
                                            <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center text-primary">
                                                <i class="fa fa-cog"></i>
                                            </div>
                                        <?php else: ?>
                                            <img src="<?php echo $notification['sender_avatar'] ? htmlspecialchars($notification['sender_avatar']) : 'static/images/default-avatar.png'; ?>" 
                                                 alt="<?php echo htmlspecialchars($notification['sender_username'] ?? '用户'); ?>的头像" 
                                                 class="w-10 h-10 rounded-full object-cover">
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="flex-grow">
                                        <div class="flex items-center justify-between mb-1">
                                            <p class="font-medium">
                                                <?php if ($notification['type'] === 'system'): ?>
                                                    系统通知
                                                <?php else: ?>
                                                    <?php echo htmlspecialchars($notification['sender_username'] ?? '未知用户'); ?>
                                                <?php endif; ?>
                                            </p>
                                            <div class="flex items-center gap-2">
                                                <span class="text-xs text-slate-500">
                                                    <?php echo time_ago($notification['created_at']); ?>
                                                </span>
                                                <?php if (!$notification['is_read']): ?>
                                                    <button class="mark-as-read-btn text-xs text-primary hover:text-dark" 
                                                            data-id="<?php echo $notification['notification_id']; ?>">
                                                        标为已读
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <p class="text-slate-700 mb-2">
                                            <?php 
                                            if ($notification['type'] === 'system' && !empty($notification['message'])) {
                                                echo nl2br(htmlspecialchars($notification['message']));
                                            } else {
                                                $topic_id = $notification['direct_topic_id'] ?? $notification['reply_topic_id'] ?? 0;
                                                $topic_title = $notification['topic_title'] ?? $notification['reply_topic_title'] ?? '未知话题';
                                                
                                                $target_url = '';
                                                if ($topic_id) {
                                                    $target_url = "topic.php?id={$topic_id}";
                                                    if ($notification['target_type'] === 'reply') {
                                                        $target_url .= "#reply-{$notification['target_id']}";
                                                    }
                                                }
                                                
                                                switch ($notification['type']) {
                                                    case 'reply':
                                                        if ($target_url) {
                                                            echo "回复了你的" . ($notification['target_type'] === 'topic' ? '话题' : '评论，话题:') . " <a href=\"{$target_url}\" class=\"text-primary hover:text-dark\">" . htmlspecialchars($topic_title) . "</a>";
                                                        } else {
                                                            echo "回复了你的" . ($notification['target_type'] === 'topic' ? '话题' : '评论，话题:');
                                                        }
                                                        break;
                                                    case 'like':
                                                        if ($target_url) {
                                                            echo "点赞了你的" . ($notification['target_type'] === 'topic' ? '话题' : '评论，话题:') . " <a href=\"{$target_url}\" class=\"text-primary hover:text-dark\">" . htmlspecialchars($topic_title) . "</a>";
                                                        } else {
                                                            echo "点赞了你的" . ($notification['target_type'] === 'topic' ? '话题' : '评论，话题:');
                                                        }
                                                        break;
                                                    case 'mention':
                                                        if ($target_url) {
                                                            echo "在 <a href=\"{$target_url}\" class=\"text-primary hover:text-dark\">" . htmlspecialchars($topic_title) . "</a> 中提到了你";
                                                        } else {
                                                            echo "在某个话题中提到了你";
                                                        }
                                                        break;
                                                    case 'follow':
                                                        $target_url = "profile.php?id={$notification['target_id']}";
                                                        echo "关注了你，<a href=\"{$target_url}\" class=\"text-primary hover:text-dark\">查看他的主页</a>";
                                                        break;
                                                    default:
                                                        if (!empty($notification['message'])) {
                                                            echo nl2br(htmlspecialchars($notification['message']));
                                                        }
                                                }
                                            }
                                            ?>
                                        </p>
                                        
                                        <?php if (!empty($notification['reply_content']) && ($notification['type'] === 'reply' || $notification['type'] === 'mention')): ?>
                                            <?php 
                                            $topic_id = $notification['direct_topic_id'] ?? $notification['reply_topic_id'] ?? 0;
                                            ?>
                                            <p class="text-sm bg-slate-50 rounded-lg p-3">
                                                <?php echo nl2br(htmlspecialchars($notification['reply_content'])); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if (!$notification['is_read']): ?>
                                        <div class="w-2 h-2 rounded-full bg-primary mt-2 flex-shrink-0"></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <?php if ($notifications_data['total_pages'] > 1): ?>
                    <div class="mt-6 flex justify-center">
                        <nav class="flex items-center gap-1">
                            <?php if ($current_page > 1): ?>
                                <a href="notifications.php?<?php echo $current_type ? "type={$current_type}&" : ''; ?>page=<?php echo $current_page - 1; ?>" 
                                   class="w-9 h-9 flex items-center justify-center rounded-lg border border-slate-200 hover:border-primary hover:text-primary transition-colors">
                                    <i class="fa fa-angle-left"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $notifications_data['total_pages']; $i++): ?>
                                <?php if ($i == $current_page): ?>
                                    <span class="w-9 h-9 flex items-center justify-center rounded-lg bg-primary text-white">
                                        <?php echo $i; ?>
                                    </span>
                                <?php else: ?>
                                    <a href="notifications.php?<?php echo $current_type ? "type={$current_type}&" : ''; ?>page=<?php echo $i; ?>" 
                                       class="w-9 h-9 flex items-center justify-center rounded-lg border border-slate-200 hover:border-primary hover:text-primary transition-colors">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endif; ?>
                            <?php endfor; ?>
                            
                            <?php if ($current_page < $notifications_data['total_pages']): ?>
                                <a href="notifications.php?<?php echo $current_type ? "type={$current_type}&" : ''; ?>page=<?php echo $current_page + 1; ?>" 
                                   class="w-9 h-9 flex items-center justify-center rounded-lg border border-slate-200 hover:border-primary hover:text-primary transition-colors">
                                    <i class="fa fa-angle-right"></i>
                                </a>
                            <?php endif; ?>
                        </nav>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php include('common/asideright.php'); ?>
    </main>
    
    <script>
        function showError(message) {
            const errorContainer = document.getElementById('error-container');
            const errorDetails = document.getElementById('error-details');
            
            errorDetails.textContent = message;
            
            errorContainer.classList.remove('hidden');
            
            setTimeout(() => {
                errorContainer.classList.add('hidden');
            }, 8000);
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const markButtons = document.querySelectorAll('.mark-as-read-btn');
            const csrfToken = '<?php echo $_SESSION['csrf_token']; ?>';
            
            markButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const notificationId = this.getAttribute('data-id');
                    const buttonElement = this;
                    const unreadDot = this.closest('.flex.items-start').querySelector('.bg-primary.w-2.h-2');
                    const notificationRow = this.closest('.border-b');
                    
                    const formData = new FormData();
                    formData.append('action', 'mark_read');
                    formData.append('notification_id', notificationId);
                    formData.append('csrf_token', csrfToken);
                    
                    fetch('notifications.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP错误，状态码: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            if (buttonElement) buttonElement.remove();
                            if (unreadDot) unreadDot.remove();
                            if (notificationRow) notificationRow.classList.remove('bg-primary/5');
                            
                            const unreadCountEl = document.querySelector('.fa-bell-o + span');
                            if (unreadCountEl) {
                                let currentCount = parseInt(unreadCountEl.textContent);
                                if (currentCount > 0) {
                                    unreadCountEl.textContent = currentCount - 1;
                                    if (currentCount - 1 === 0) {
                                        unreadCountEl.remove();
                                    }
                                }
                            }
                        } else {
                            showError(data.message || '标记已读失败，请重试');
                        }
                    })
                    .catch(error => {
                        console.error('标记已读失败:', error);
                        showError(`操作失败: ${error.message}，请检查网络连接后重试`);
                    });
                });
            });
        });
    </script>
<?php include('common/footer.php'); ?>