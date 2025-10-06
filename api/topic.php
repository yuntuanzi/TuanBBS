<?php
require_once '../common/functions.php';
require_once 'notification.php';
require_once '../api/deletetopic.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$topic_id = $_GET['topic_id'] ?? 0;

try {
    switch ($action) {
        case 'get_replies':
            $page = $_GET['page'] ?? 1;
            $sort = $_GET['sort'] ?? 'newest';
            
            // 验证排序方式
            $valid_sorts = ['newest', 'oldest', 'popular'];
            if (!in_array($sort, $valid_sorts)) {
                $sort = 'newest';
            }
            
            $replies = get_topic_replies($topic_id, $page, $sort);
            echo json_encode(['success' => true, 'data' => $replies]);
            break;
            
        case 'like_topic':
            // 需要用户登录验证
            $user_id = $_SESSION['user_id'] ?? 0;
            if (!$user_id) {
                throw new Exception('请先登录');
            }
            
            // 获取话题作者
            $stmt = $pdo->prepare("SELECT user_id FROM topics WHERE topic_id = ?");
            $stmt->execute([$topic_id]);
            $topic = $stmt->fetch();
            
            if (!$topic) {
                throw new Exception('话题不存在');
            }
            
            $topic_author_id = $topic['user_id'];
            
            if (has_user_liked($user_id, 'topic', $topic_id)) {
                // 取消点赞
                $stmt = $pdo->prepare("DELETE FROM likes WHERE user_id = ? AND target_type = 'topic' AND target_id = ?");
                $stmt->execute([$user_id, $topic_id]);
                $stmt = $pdo->prepare("UPDATE topics SET like_count = like_count - 1 WHERE topic_id = ?");
                $stmt->execute([$topic_id]);
            } else {
                // 点赞
                $stmt = $pdo->prepare("INSERT INTO likes (user_id, target_type, target_id) VALUES (?, 'topic', ?)");
                $stmt->execute([$user_id, $topic_id]);
                $stmt = $pdo->prepare("UPDATE topics SET like_count = like_count + 1 WHERE topic_id = ?");
                $stmt->execute([$topic_id]);
                
                // 创建点赞通知
                create_notification(
                    $topic_author_id,
                    $user_id,
                    'like',
                    'topic',
                    $topic_id
                );
            }
            
            // 获取更新后的点赞数
            $stmt = $pdo->prepare("SELECT like_count FROM topics WHERE topic_id = ?");
            $stmt->execute([$topic_id]);
            $like_count = $stmt->fetchColumn();
            
            echo json_encode(['success' => true, 'like_count' => $like_count]);
            break;

        case 'delete_topic':
            // 需要用户登录验证
            $user_id = $_SESSION['user_id'] ?? 0;
            if (!$user_id) {
                throw new Exception('请先登录');
            }
            
            $topic_id = $_POST['topic_id'] ?? 0;
            $reason = $_POST['reason'] ?? '';
            
            // 检查权限
            if (!can_delete_topic($user_id, $topic_id)) {
                throw new Exception('无权删除此话题');
            }
            
            // 如果是管理员或版主删除，必须提供原因
            $stmt = $pdo->prepare("SELECT role FROM users WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            if (($user['role'] == 'admin' || $user['role'] == 'moderator') && empty($reason)) {
                throw new Exception('管理员删除必须提供原因');
            }
            
            // 执行删除
            delete_topic($topic_id, $user_id, $reason);
            
            echo json_encode(['success' => true, 'message' => '话题已删除']);
            break;
        default:
            throw new Exception('无效的操作');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
