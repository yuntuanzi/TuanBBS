<?php
require_once 'common/functions.php';

$user = get_logged_in_user();
if (!$user) {
    header("Location: login.php");
    exit;
}

$current_conversation_id = isset($_GET['conversation']) ? intval($_GET['conversation']) : 0;

function get_user_conversations($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT 
            pm.message_id,
            CASE 
                WHEN pm.sender_id = :user_id THEN pm.receiver_id
                ELSE pm.sender_id
            END as other_user_id,
            u.username as other_username,
            u.avatar_url as other_avatar,
            pm.content as last_message,
            pm.is_read,
            pm.created_at,
            COUNT(CASE WHEN pm2.is_read = 0 AND pm2.receiver_id = :user_id THEN 1 END) as unread_count
        FROM 
            private_messages pm
        JOIN 
            users u ON (CASE WHEN pm.sender_id = :user_id THEN pm.receiver_id ELSE pm.sender_id END) = u.user_id
        LEFT JOIN 
            private_messages pm2 ON (
                (pm2.sender_id = pm.sender_id AND pm2.receiver_id = pm.receiver_id) OR 
                (pm2.sender_id = pm.receiver_id AND pm2.receiver_id = pm.sender_id)
            )
        WHERE 
            pm.message_id IN (
                SELECT MAX(message_id) 
                FROM private_messages 
                WHERE sender_id = :user_id OR receiver_id = :user_id 
                GROUP BY LEAST(sender_id, receiver_id), GREATEST(sender_id, receiver_id)
            )
        GROUP BY 
            pm.message_id, other_user_id, other_username, other_avatar, last_message, pm.is_read, pm.created_at
        ORDER BY 
            pm.created_at DESC
    ");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
function can_send_message($sender_id, $receiver_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT role FROM users WHERE user_id = :sender_id");
    $stmt->bindParam(':sender_id', $sender_id, PDO::PARAM_INT);
    $stmt->execute();
    $sender_role = $stmt->fetchColumn();
    
    if (in_array($sender_role, ['admin', 'moderator'])) {
        return true;
    }
    
    $stmt = $pdo->prepare("SELECT role FROM users WHERE user_id = :receiver_id");
    $stmt->bindParam(':receiver_id', $receiver_id, PDO::PARAM_INT);
    $stmt->execute();
    $receiver_role = $stmt->fetchColumn();
    
    if (in_array($receiver_role, ['admin', 'moderator'])) {
        return true;
    }
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM follows 
        WHERE (follower_id = :receiver_id AND following_id = :sender_id)
        OR (follower_id = :sender_id AND following_id = :receiver_id)
    ");
    $stmt->bindParam(':sender_id', $sender_id, PDO::PARAM_INT);
    $stmt->bindParam(':receiver_id', $receiver_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $mutual_follow = $stmt->fetchColumn() > 1;
    
    if ($mutual_follow) {
        return true;
    }
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM private_messages 
        WHERE sender_id = :receiver_id AND receiver_id = :sender_id
    ");
    $stmt->bindParam(':sender_id', $sender_id, PDO::PARAM_INT);
    $stmt->bindParam(':receiver_id', $receiver_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $has_reply = $stmt->fetchColumn() > 0;
    
    if ($has_reply) {
        return true;
    }
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM private_messages 
        WHERE sender_id = :sender_id AND receiver_id = :receiver_id 
        AND created_at >= NOW() - INTERVAL 24 HOUR
    ");
    $stmt->bindParam(':sender_id', $sender_id, PDO::PARAM_INT);
    $stmt->bindParam(':receiver_id', $receiver_id, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchColumn() == 0;
}
function get_conversation_messages($user_id, $other_user_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        UPDATE private_messages 
        SET is_read = 1 
        WHERE sender_id = :other_user_id AND receiver_id = :user_id AND is_read = 0
    ");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':other_user_id', $other_user_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $stmt = $pdo->prepare("
        SELECT 
            pm.*, 
            u.username as sender_username,
            u.avatar_url as sender_avatar,
            u.role as sender_role
        FROM 
            private_messages pm
        JOIN 
            users u ON pm.sender_id = u.user_id
        WHERE 
            (pm.sender_id = :user_id AND pm.receiver_id = :other_user_id) OR 
            (pm.sender_id = :other_user_id AND pm.receiver_id = :user_id)
        ORDER BY 
            pm.created_at ASC
    ");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':other_user_id', $other_user_id, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $receiver_id = intval($_POST['receiver_id']);
    $content = trim($_POST['content']);
    
    if (!empty($content)) {
        if (can_send_message($user['user_id'], $receiver_id)) {
            $stmt = $pdo->prepare("
                INSERT INTO private_messages (sender_id, receiver_id, content, created_at)
                VALUES (:sender_id, :receiver_id, :content, NOW())
            ");
            $stmt->bindParam(':sender_id', $user['user_id'], PDO::PARAM_INT);
            $stmt->bindParam(':receiver_id', $receiver_id, PDO::PARAM_INT);
            $stmt->bindParam(':content', $content);
            $stmt->execute();
            
            header("Location: messages.php?conversation=" . $receiver_id);
            exit;
        } else {
            $_SESSION['error_message'] = "对方未关注或回复你，24小时内只能发送一条消息";
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['search_users'])) {
    $search_query = trim($_GET['q'] ?? '');
    $results = [];
    
    if (!empty($search_query)) {
        $stmt = $pdo->prepare("
            SELECT user_id, username, avatar_url, role 
            FROM users 
            WHERE username LIKE :query AND user_id != :current_user_id
            ORDER BY username ASC
            LIMIT 10
        ");
        $param = '%' . $search_query . '%';
        $stmt->bindParam(':query', $param);
        $stmt->bindParam(':current_user_id', $user['user_id'], PDO::PARAM_INT);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    header('Content-Type: application/json');
    echo json_encode($results);
    exit;
}

$conversations = get_user_conversations($user['user_id']);

$current_messages = [];
$other_user = null;
$can_send_message = false;
if ($current_conversation_id > 0) {
    $current_messages = get_conversation_messages($user['user_id'], $current_conversation_id);
    $can_send_message = can_send_message($user['user_id'], $current_conversation_id);
    
    $stmt = $pdo->prepare("SELECT user_id, username, avatar_url, role FROM users WHERE user_id = :user_id");
    $stmt->bindParam(':user_id', $current_conversation_id, PDO::PARAM_INT);
    $stmt->execute();
    $other_user = $stmt->fetch(PDO::FETCH_ASSOC);
}

include('common/header.php');
?>
    <title>私信 - <?= htmlspecialchars($site_info['site_title']) ?></title>
    <style>
        .search-results-container {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-top: 5px;
            display: none;
        }
        .user-option {
            display: flex;
            align-items: center;
            padding: 8px 12px;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .user-option:hover {
            background-color: #f0f7ff;
        }
        .user-avatar {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            margin-right: 10px;
            object-fit: cover;
        }
        .role-badge {
            font-size: 12px;
            margin-left: 8px;
            padding: 2px 6px;
            border-radius: 12px;
        }
        .role-admin { background-color: #dc3545; color: white; }
        .role-moderator { background-color: #0d6efd; color: white; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen flex flex-col overflow-x-hidden m-0 p-0">
    <main class="flex-grow flex flex-col md:flex-row transition-all duration-300 pt-6" id="main-container">
        <?php include('common/asideleft.php'); ?>
        
        <div class="flex-grow transition-all duration-300 p-4 md:ml-64 lg:ml-72" id="content-area">
            <div class="max-w-6xl mx-auto bg-white rounded-xl shadow-card overflow-hidden flex flex-col md:flex-row h-[calc(100vh-120px)]">
                <div class="w-full md:w-1/3 border-r border-slate-200 overflow-y-auto">
                    <div class="p-4 border-b border-slate-200">
                        <h2 class="text-xl font-bold flex items-center justify-between">
                            <span>私信列表</span>
                            <button class="btn-primary px-4 py-2 text-sm flex items-center gap-2 no-animation" onclick="document.getElementById('new_message_modal').showModal()">
                                <i class="fa fa-pencil"></i>
                                <span>新私信</span>
                            </button>
                        </h2>
                    </div>
                    
                    <?php if (empty($conversations)): ?>
                        <div class="p-4 text-center text-slate-500">
                            <p>暂无私信</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($conversations as $conversation): ?>
                            <a href="messages.php?conversation=<?= $conversation['other_user_id'] ?>" class="flex items-center gap-3 p-4 border-b border-slate-100 hover:bg-slate-50 transition-colors <?= $current_conversation_id == $conversation['other_user_id'] ? 'bg-blue-50' : '' ?>">
                                <img src="<?= $conversation['other_avatar'] ?: 'static/images/default-avatar.png' ?>" alt="用户头像" class="w-10 h-10 rounded-full object-cover">
                                <div class="flex-grow">
                                    <div class="flex items-center justify-between mb-1">
                                        <p class="font-medium"><?= htmlspecialchars($conversation['other_username']) ?></p>
                                        <span class="text-xs text-slate-500"><?= time_ago($conversation['created_at']) ?></span>
                                    </div>
                                    <p class="text-sm text-slate-600 line-clamp-1"><?= htmlspecialchars($conversation['last_message']) ?></p>
                                </div>
                                <?php if ($conversation['unread_count'] > 0): ?>
                                    <div class="w-3 h-3 rounded-full bg-primary"></div>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <div class="w-full md:w-2/3 flex flex-col">
                    <?php if ($current_conversation_id > 0 && $other_user): ?>
                        <div class="p-4 border-b border-slate-200 flex items-center gap-3">
                            <a href="profile.php?id=<?= $other_user['user_id'] ?>">
                                <img src="<?= $other_user['avatar_url'] ?: 'static/images/default-avatar.png' ?>" alt="用户头像" class="w-10 h-10 rounded-full object-cover">
                            </a>
                            <div>
                                <a href="profile.php?id=<?= $other_user['user_id'] ?>" class="font-medium hover:underline"><?= htmlspecialchars($other_user['username']) ?></a>
                                <p class="text-xs text-slate-500"><?= count($current_messages) ?> 条消息</p>
                            </div>
                            <?php if (in_array($other_user['role'], ['admin', 'moderator'])): ?>
                                <span class="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded-full"><?= $other_user['role'] == 'admin' ? '管理员' : '版主' ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="flex-grow p-4 overflow-y-auto" id="message-container">
                            <?php foreach ($current_messages as $message): ?>
                                <div class="mb-4 flex <?= $message['sender_id'] == $user['user_id'] ? 'justify-end' : 'justify-start' ?>">
                                    <div class="max-w-xs md:max-w-md">
                                        <div class="rounded-lg p-3 <?= $message['sender_id'] == $user['user_id'] ? 'bg-primary text-white' : 'bg-slate-100' ?>">
                                            <p><?= htmlspecialchars($message['content']) ?></p>
                                        </div>
                                        <div class="flex items-center mt-1 text-xs <?= $message['sender_id'] == $user['user_id'] ? 'justify-end' : 'justify-start' ?>">
                                            <span class="<?= $message['sender_id'] == $user['user_id'] ? 'text-slate-500' : 'text-slate-500' ?>">
                                                <?= date('H:i', strtotime($message['created_at'])) ?>
                                            </span>
                                            <?php if ($message['sender_id'] == $user['user_id']): ?>
                                                <span class="ml-2 <?= $message['is_read'] ? 'text-blue-500' : 'text-slate-400' ?>">
                                                    <?= $message['is_read'] ? '已读' : '未读' ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="p-4 border-t border-slate-200">
                            <?php if (isset($_SESSION['error_message'])): ?>
                                <div class="mb-2 text-sm text-red-500"><?= $_SESSION['error_message'] ?></div>
                                <?php unset($_SESSION['error_message']); ?>
                            <?php endif; ?>
                            
                            <?php if ($can_send_message || in_array($user['role'], ['admin', 'moderator'])): ?>
                                <form method="post" class="flex gap-2">
                                    <input type="hidden" name="receiver_id" value="<?= $other_user['user_id'] ?>">
                                    <input type="text" name="content" placeholder="输入消息..." class="flex-grow p-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" required>
                                    <button type="submit" name="send_message" class="btn-primary px-4 py-2">
                                        <i class="fa fa-paper-plane"></i>
                                    </button>
                                </form>
                            <?php else: ?>
                                <div class="text-center text-slate-500 py-4">
                                    <p>对方未关注或回复你，24小时内只能发送一条消息</p>
                                    <?php 
                                    $stmt = $pdo->prepare("
                                        SELECT created_at FROM private_messages 
                                        WHERE sender_id = :sender_id AND receiver_id = :receiver_id 
                                        ORDER BY created_at DESC LIMIT 1
                                    ");
                                    $stmt->bindParam(':sender_id', $user['user_id'], PDO::PARAM_INT);
                                    $stmt->bindParam(':receiver_id', $current_conversation_id, PDO::PARAM_INT);
                                    $stmt->execute();
                                    $last_message_time = $stmt->fetchColumn();
                                    
                                    if ($last_message_time) {
                                        $next_message_time = date('Y-m-d H:i:s', strtotime($last_message_time) + 86400);
                                        echo '<p class="text-sm mt-1">下次可发送时间: ' . date('m-d H:i', strtotime($next_message_time)) . '</p>';
                                    }
                                    ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="flex-grow flex flex-col items-center justify-center text-slate-500">
                            <i class="fa fa-comments text-5xl mb-4"></i>
                            <p class="text-lg mb-2">选择或开始新的对话</p>
                            <button class="btn-primary px-4 py-2 mt-4" onclick="document.getElementById('new_message_modal').showModal()">
                                <i class="fa fa-pencil mr-2"></i>新私信
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <dialog id="new_message_modal" class="rounded-xl p-0 w-full max-w-md bg-white shadow-xl">
            <div class="p-4 border-b border-slate-200">
                <h3 class="text-lg font-bold">发送新私信</h3>
            </div>
            <form method="post" action="messages.php" class="p-4">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-slate-700 mb-1">收件人</label>
                    <div class="relative">
                        <input type="text" id="receiver_search" class="w-full p-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" placeholder="搜索用户名..." autocomplete="off">
                        <input type="hidden" id="receiver_id" name="receiver_id" required>
                        <div id="user_search_results" class="search-results-container absolute z-10 w-full bg-white shadow-md rounded-b-md"></div>
                    </div>
                </div>
                <div id="selected_user_container" class="hidden mb-4 flex items-center">
                    <img id="selected_avatar" src="" alt="用户头像" class="w-8 h-8 rounded-full object-cover mr-2">
                    <div>
                        <p id="selected_username" class="font-medium"></p>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-slate-700 mb-1">消息内容</label>
                    <textarea name="content" rows="4" class="w-full p-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" placeholder="输入消息内容..." required></textarea>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" onclick="document.getElementById('new_message_modal').close()" class="px-4 py-2 border border-slate-300 rounded-lg hover:bg-slate-50">取消</button>
                    <button type="submit" name="send_message" class="btn-primary px-4 py-2">发送</button>
                </div>
            </form>
        </dialog>
    </main>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.getElementById('message-container');
            if (container) {
                container.scrollTop = container.scrollHeight;
            }
            
            document.getElementById('new_message_modal').addEventListener('click', function(e) {
                if (e.target === this) {
                    this.close();
                    resetSearchForm();
                }
            });

            setupUserSearch();
        });

        function setupUserSearch() {
            const searchInput = document.getElementById('receiver_search');
            const resultsContainer = document.getElementById('user_search_results');
            const selectedContainer = document.getElementById('selected_user_container');
            const selectedAvatar = document.getElementById('selected_avatar');
            const selectedUsername = document.getElementById('selected_username');
            const receiverIdInput = document.getElementById('receiver_id');
            
            let searchTimeout = null;
            
            searchInput.addEventListener('input', function() {
                const query = this.value.trim();
                
                if (query.length === 0) {
                    resultsContainer.innerHTML = '';
                    resultsContainer.style.display = 'none';
                    return;
                }
                
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    searchUsers(query);
                }, 300);
            });
            
            document.addEventListener('click', function(e) {
                if (!resultsContainer.contains(e.target) && e.target !== searchInput) {
                    resultsContainer.style.display = 'none';
                }
            });
            
            function searchUsers(query) {
                fetch('messages.php?search_users=1&q=' + encodeURIComponent(query))
                    .then(response => response.json())
                    .then(users => {
                        if (users.length === 0) {
                            resultsContainer.innerHTML = '<div class="p-3 text-slate-500">未找到匹配用户</div>';
                        } else {
                            resultsContainer.innerHTML = '';
                            users.forEach(user => {
                                const option = document.createElement('div');
                                option.className = 'user-option';
                                option.innerHTML = `
                                    <img src="${user.avatar_url || 'static/images/default-avatar.png'}" class="user-avatar">
                                    <div class="flex-grow">
                                        <div class="flex items-center">
                                            <span>${user.username}</span>
                                            ${user.role === 'admin' ? 
                                                '<span class="role-badge role-admin">管理员</span>' : 
                                                (user.role === 'moderator' ? 
                                                    '<span class="role-badge role-moderator">版主</span>' : 
                                                    '')}
                                        </div>
                                    </div>
                                `;
                                
                                option.addEventListener('click', function() {
                                    selectUser(user);
                                });
                                
                                resultsContainer.appendChild(option);
                            });
                        }
                        resultsContainer.style.display = 'block';
                    })
                    .catch(error => {
                        console.error('搜索错误:', error);
                    });
            }
            
            function selectUser(user) {
                searchInput.value = '';
                searchInput.style.display = 'none';
                receiverIdInput.value = user.user_id;
                selectedAvatar.src = user.avatar_url || 'static/images/default-avatar.png';
                selectedUsername.textContent = user.username + 
                    (user.role === 'admin' ? ' (管理员)' : (user.role === 'moderator' ? ' (版主)' : ''));
                selectedContainer.style.display = 'flex';
                resultsContainer.style.display = 'none';
            }
            
            function resetSearchForm() {
                searchInput.value = '';
                searchInput.style.display = 'block';
                receiverIdInput.value = '';
                selectedContainer.style.display = 'none';
                resultsContainer.style.display = 'none';
            }
        }

    </script>
<?php include('common/footer.php'); ?>