<?php
require_once __DIR__ . '/../common/functions.php';

header('Content-Type: application/json');

// 检查用户是否登录
$user = get_logged_in_user();
if (!$user) {
    echo json_encode([]);
    exit;
}

$query = isset($_GET['q']) ? trim($_GET['q']) : '';

if (strlen($query) < 2) {
    echo json_encode([]);
    exit;
}

try {
    $pdo = get_pdo();
    $stmt = $pdo->prepare("
        SELECT user_id, username, role 
        FROM users 
        WHERE username LIKE :query AND user_id != :current_user_id
        ORDER BY 
            CASE 
                WHEN role = 'admin' THEN 1
                WHEN role = 'moderator' THEN 2
                ELSE 3
            END,
            username
        LIMIT 10
    ");
    $stmt->bindValue(':query', "%$query%");
    $stmt->bindValue(':current_user_id', $user['user_id'], PDO::PARAM_INT);
    $stmt->execute();
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($results);
} catch (PDOException $e) {
    error_log('Database error: ' . $e->getMessage());
    echo json_encode([]);
}