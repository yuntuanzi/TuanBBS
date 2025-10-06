<?php
require_once '../common/functions.php';
require_once 'notification.php';

header('Content-Type: application/json');

// 检查用户是否登录
$user_id = $_SESSION['user_id'] ?? 0;
if (!$user_id && $_GET['action'] !== 'get_replies') {
    echo json_encode(['success' => false, 'message' => '请先登录']);
    exit;
}

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'like_reply':
            $reply_id = $_GET['reply_id'] ?? 0;
            
            if ($reply_id <= 0) {
                throw new Exception('无效的回复ID');
            }
            
            // 检查回复是否存在并获取回复作者
            $stmt = $pdo->prepare("SELECT reply_id, user_id, topic_id FROM replies WHERE reply_id = ?");
            $stmt->execute([$reply_id]);
            $reply = $stmt->fetch();
            
            if (!$reply) {
                throw new Exception('回复不存在');
            }
            
            $reply_author_id = $reply['user_id'];
            $topic_id = $reply['topic_id'];
            
            if (has_user_liked($user_id, 'reply', $reply_id)) {
                // 取消点赞
                $stmt = $pdo->prepare("DELETE FROM likes WHERE user_id = ? AND target_type = 'reply' AND target_id = ?");
                $stmt->execute([$user_id, $reply_id]);
                $stmt = $pdo->prepare("UPDATE replies SET like_count = like_count - 1 WHERE reply_id = ?");
                $stmt->execute([$reply_id]);
            } else {
                // 点赞
                $stmt = $pdo->prepare("INSERT INTO likes (user_id, target_type, target_id) VALUES (?, 'reply', ?)");
                $stmt->execute([$user_id, $reply_id]);
                $stmt = $pdo->prepare("UPDATE replies SET like_count = like_count + 1 WHERE reply_id = ?");
                $stmt->execute([$reply_id]);
                
                // 创建点赞通知
                create_notification(
                    $reply_author_id,
                    $user_id,
                    'like',
                    'reply',
                    $reply_id,
                    $topic_id
                );
            }
            
            // 获取更新后的点赞数
            $stmt = $pdo->prepare("SELECT like_count FROM replies WHERE reply_id = ?");
            $stmt->execute([$reply_id]);
            $like_count = $stmt->fetchColumn();
            
            echo json_encode(['success' => true, 'like_count' => $like_count]);
            break;
            
        default:
            // 默认处理回复提交
            $topic_id = $_POST['topic_id'] ?? 0;
            $content = trim($_POST['content'] ?? '');
            $reply_to = $_POST['reply_to'] ?? 0;
            
            // 验证输入
            if ($topic_id <= 0) {
                throw new Exception('无效的话题ID');
            }
            
            if (empty($content)) {
                throw new Exception('回复内容不能为空');
            }
            
            // 安全处理内容
            $original_content = $content; // 保存原始内容用于处理@提及
            $content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
            
            if (mb_strlen($content) > 10000) {
                throw new Exception('回复内容过长');
            }
            
            // 检查话题是否存在并获取话题作者和标题
            $stmt = $pdo->prepare("SELECT topic_id, user_id, title FROM topics WHERE topic_id = ?");
            $stmt->execute([$topic_id]);
            $topic = $stmt->fetch();
            
            if (!$topic) {
                throw new Exception('话题不存在');
            }
            
            $topic_author_id = $topic['user_id'];
            $topic_title = $topic['title'];
            
            // 插入回复
            $stmt = $pdo->prepare("
                INSERT INTO replies (content, user_id, topic_id, reply_to, created_at, updated_at)
                VALUES (?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([$content, $user_id, $topic_id, $reply_to]);
            
            $reply_id = $pdo->lastInsertId();
            
            // 更新话题的回复数和最后回复时间
            $stmt = $pdo->prepare("
                UPDATE topics 
                SET reply_count = reply_count + 1, last_reply_at = NOW() 
                WHERE topic_id = ?
            ");
            $stmt->execute([$topic_id]);
            
            // 获取当前用户信息
            $stmt = $pdo->prepare("SELECT username FROM users WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $current_user = $stmt->fetch();
            $current_username = $current_user['username'];
            
            // 生成通知消息（包含回复内容，超过50字符截断）
            $truncated_content = mb_strlen($original_content) > 50 
                ? mb_substr($original_content, 0, 50) . '...' 
                : $original_content;
            
            // 创建回复通知
            if ($reply_to > 0) {
                // 回复了别人的回复，通知被回复者
                $stmt = $pdo->prepare("SELECT user_id, content FROM replies WHERE reply_id = ?");
                $stmt->execute([$reply_to]);
                $parent_reply = $stmt->fetch();
                
                if ($parent_reply) {
                    $notification_message = $truncated_content;
                    
                    create_notification(
                        $parent_reply['user_id'],
                        $user_id,
                        'reply',
                        'reply',
                        $reply_id,
                        $notification_message
                    );
                }
            } else {
                // 直接回复话题，通知话题作者
                $notification_message = $truncated_content;
                
                create_notification(
                    $topic_author_id,
                    $user_id,
                    'reply',
                    'topic',
                    $topic_id,
                    $notification_message
                );
            }
            
            // 处理@提及
            handle_mentions($original_content, $user_id, 'reply', $reply_id, $topic_id);
            
            echo json_encode(['success' => true, 'reply_id' => $reply_id]);
    }
} catch (Exception $e) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit;
}
?>
