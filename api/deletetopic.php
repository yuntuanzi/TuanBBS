<?php
// 引入消息通知功能
require_once __DIR__ . '/notification.php';

/**
 * 检查用户是否有删除话题的权限
 * @param int $user_id 用户ID
 * @param int $topic_id 话题ID
 * @return bool
 */
function can_delete_topic($user_id, $topic_id) {
    global $pdo;
    
    // 获取话题信息
    $stmt = $pdo->prepare("SELECT user_id FROM topics WHERE topic_id = ?");
    $stmt->execute([$topic_id]);
    $topic = $stmt->fetch();
    
    if (!$topic) {
        return false;
    }
    
    // 如果是作者本人
    if ($topic['user_id'] == $user_id) {
        return true;
    }
    
    // 检查是否是管理员或版主
    $stmt = $pdo->prepare("SELECT role FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    return ($user && in_array($user['role'], ['admin', 'moderator']));
}

/**
 * 删除话题
 * @param int $topic_id 话题ID
 * @param int $user_id 操作用户ID
 * @param string $reason 删除原因
 */
function delete_topic($topic_id, $user_id, $reason = '') {
    global $pdo;
    
    // 开始事务
    $pdo->beginTransaction();
    
    try {
        // 1. 获取话题完整信息
        $stmt = $pdo->prepare("
            SELECT t.*, u.username as author_name, u.user_id as author_id, 
                   GROUP_CONCAT(tt.tag_id) as tag_ids 
            FROM topics t
            JOIN users u ON t.user_id = u.user_id
            LEFT JOIN topic_tags tt ON t.topic_id = tt.topic_id
            WHERE t.topic_id = ?
            GROUP BY t.topic_id
        ");
        $stmt->execute([$topic_id]);
        $topic = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$topic) {
            throw new Exception('话题不存在');
        }
        
        // 2. 获取操作用户信息
        $stmt = $pdo->prepare("SELECT username, role FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $operator = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$operator) {
            throw new Exception('操作用户不存在');
        }
        
        // 3. 获取话题的标签信息
        $tags = [];
        if (!empty($topic['tag_ids'])) {
            $stmt = $pdo->prepare("SELECT * FROM tags WHERE tag_id IN (?)");
            $stmt->execute([$topic['tag_ids']]);
            $tags = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        // 4. 记录删除操作到修改记录表
        $stmt = $pdo->prepare("
            INSERT INTO topic_modifications (
                topic_id, user_id, action, old_title, old_content, 
                old_section_id, old_tags, reason
            ) VALUES (?, ?, 'delete', ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $topic_id,
            $user_id,
            $topic['title'],
            $topic['content'],
            $topic['section_id'],
            json_encode($tags),
            $reason
        ]);
        
        // 5. 如果是管理员/版主删除(非作者本人)，发送通知给作者
        if ($operator['role'] != 'user' && $topic['author_id'] != $user_id) {
            $message = "您的话题《{$topic['title']}》已被{$operator['username']}删除。";
            $message .= $reason ? "\n删除原因: {$reason}" : "";
            
            create_notification(
                $topic['author_id'],
                $user_id,
                'system',
                'topic',
                $topic_id,
                $message
            );
        }
        
        // 6. 删除话题的标签关联
        $stmt = $pdo->prepare("DELETE FROM topic_tags WHERE topic_id = ?");
        $stmt->execute([$topic_id]);
        
        // 7. 删除话题的点赞记录
        $stmt = $pdo->prepare("DELETE FROM likes WHERE target_type = 'topic' AND target_id = ?");
        $stmt->execute([$topic_id]);
        
        // 8. 删除话题的收藏记录
        $stmt = $pdo->prepare("DELETE FROM favorites WHERE topic_id = ?");
        $stmt->execute([$topic_id]);
        
        // 9. 删除话题的回复
        $stmt = $pdo->prepare("DELETE FROM replies WHERE topic_id = ?");
        $stmt->execute([$topic_id]);
        
        // 10. 删除话题本身
        $stmt = $pdo->prepare("DELETE FROM topics WHERE topic_id = ?");
        $stmt->execute([$topic_id]);
        
        // 提交事务
        $pdo->commit();
        
        return true;
    } catch (Exception $e) {
        // 回滚事务
        $pdo->rollBack();
        throw $e;
    }
}