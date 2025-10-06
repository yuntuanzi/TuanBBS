<?php
/**
 * 创建通知（支持自定义消息）
 * @param int $receiver_id 接收用户ID
 * @param int|null $sender_id 发送用户ID（系统通知可为null）
 * @param string $type 通知类型
 * @param string $target_type 目标类型
 * @param int $target_id 目标ID
 * @param string|null $message 自定义消息内容（可选）
 * @return bool 是否创建成功
 */
function create_notification($receiver_id, $sender_id, $type, $target_type, $target_id, $message = null) {
    global $pdo;
    
    // 参数验证
    $allowed_types = ['reply', 'like', 'mention', 'system', 'follow'];
    $allowed_target_types = ['topic', 'reply', 'user'];
    
    if (!in_array($type, $allowed_types) || 
        !in_array($target_type, $allowed_target_types) ||
        $receiver_id <= 0 || $target_id <= 0) {
        return false;
    }
    
    // 防止自己通知自己（系统通知除外）
    if ($sender_id !== null && $receiver_id == $sender_id) {
        return false;
    }
    
    // 如果是关注通知，检查24小时内是否已存在相同通知
    if ($type === 'follow') {
        $stmt = $pdo->prepare("
            SELECT notification_id 
            FROM notifications 
            WHERE user_id = ? 
              AND sender_id = ? 
              AND type = 'follow' 
              AND created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)
            LIMIT 1
        ");
        $stmt->execute([$receiver_id, $sender_id]);
        if ($stmt->fetch()) {
            return false; // 24小时内已存在关注通知，不再创建
        }
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO notifications 
            (user_id, sender_id, type, target_type, target_id, message, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $result = $stmt->execute([
            $receiver_id,
            $sender_id,
            $type,
            $target_type,
            $target_id,
            $message
        ]);
        
        return $result;
        
    } catch (PDOException $e) {
        error_log("创建通知失败: " . $e->getMessage());
        return false;
    }
}

/**
 * 解析内容中的@提及用户，创建提及通知
 * @param string $content 回复/话题内容
 * @param int $sender_id 发送者ID
 * @param int $topic_id 关联话题ID
 * @param int $reply_id 关联回复ID（可为0）
 * @return void
 */
function handle_mentions($content, $sender_id, $topic_id, $reply_id = 0) {
    global $pdo;
    
    // 匹配@用户名（用户名规则：字母、数字、下划线）
    if (preg_match_all('/@([a-zA-Z0-9_]+)/', $content, $matches)) {
        $usernames = array_unique($matches[1]);
        
        // 查询匹配的用户ID
        $placeholders = rtrim(str_repeat('?,', count($usernames)), ',');
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username IN ($placeholders)");
        $stmt->execute($usernames);
        $mentioned_users = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // 为每个被提及用户创建通知
        $target_type = $reply_id > 0 ? 'reply' : 'topic';
        $target_id = $reply_id > 0 ? $reply_id : $topic_id;
        foreach ($mentioned_users as $user_id) {
            create_notification($user_id, $sender_id, 'mention', $target_type, $target_id);
        }
    }
}

/**
 * 获取用户未读通知数量
 * @param int $user_id 用户ID
 * @return int 未读数量
 */
function get_unread_notification_count($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    return (int)$stmt->fetchColumn();
}

/**
 * 获取用户的通知列表
 * @param int $user_id 用户ID
 * @param int $page 页码
 * @param int $limit 每页数量
 * @return array 通知列表（包含发送者信息）
 */
function get_user_notifications($user_id, $page = 1, $limit = 20) {
    global $pdo;
    $offset = ($page - 1) * $limit;
    
    $stmt = $pdo->prepare("
        SELECT n.*, u.username, u.avatar_url 
        FROM notifications n
        LEFT JOIN users u ON n.sender_id = u.user_id
        WHERE n.user_id = ?
        ORDER BY n.created_at DESC
        LIMIT ?, ?
    ");
    $stmt->execute([$user_id, $offset, $limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}