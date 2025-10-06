<?php
require_once __DIR__ . '/../common/functions.php';
require_once __DIR__ . '/notification.php';
header('Content-Type: application/json');

// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '请先登录']);
    exit;
}

$action = $_GET['action'] ?? '';
$user_id = $_SESSION['user_id'];

try {
    switch ($action) {
        case 'follow':
            $following_id = intval($_POST['following_id'] ?? 0);
            
            if ($user_id == $following_id) {
                throw new Exception('不能关注自己');
            }
            
            // 检查用户是否存在
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE user_id = ?");
            $stmt->execute([$following_id]);
            if (!$stmt->fetch()) {
                throw new Exception('用户不存在');
            }
            
            // 检查是否已关注
            $stmt = $pdo->prepare("SELECT follow_id FROM follows WHERE follower_id = ? AND following_id = ?");
            $stmt->execute([$user_id, $following_id]);
            
            if ($stmt->fetch()) {
                // 已关注，执行取消关注
                $stmt = $pdo->prepare("DELETE FROM follows WHERE follower_id = ? AND following_id = ?");
                $stmt->execute([$user_id, $following_id]);
                
                echo json_encode([
                    'success' => true, 
                    'is_following' => false, 
                    'message' => '已取消关注'
                ]);
            } else {
                // 未关注，执行关注
                $stmt = $pdo->prepare("INSERT INTO follows (follower_id, following_id, created_at) VALUES (?, ?, NOW())");
                $stmt->execute([$user_id, $following_id]);
                
                // 创建关注通知
                $success = create_notification(
                    $following_id,
                    $user_id,
                    'follow',
                    'user',
                    $user_id,
                    '用户 ' . htmlspecialchars($_SESSION['username']) . ' 关注了你'
                );
                
                if (!$success) {
                    error_log("Failed to create follow notification for user $following_id");
                }
                
                echo json_encode([
                    'success' => true, 
                    'is_following' => true, 
                    'message' => '关注成功'
                ]);
            }
            break;
            
        case 'check_follow':
            $following_id = intval($_GET['following_id'] ?? 0);
            
            $stmt = $pdo->prepare("SELECT follow_id FROM follows WHERE follower_id = ? AND following_id = ?");
            $stmt->execute([$user_id, $following_id]);
            
            echo json_encode([
                'success' => true, 
                'is_following' => (bool)$stmt->fetch()
            ]);
            break;
            
        default:
            throw new Exception('无效的操作');
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
?>