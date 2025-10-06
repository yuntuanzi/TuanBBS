<?php
require_once '../common/functions.php';

// 设置响应头为JSON格式
header('Content-Type: application/json');

// 获取请求参数
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'latest';
$limit = 10;
$offset = ($page - 1) * $limit;

try {
    // 基础SQL查询
    $sql = "
        SELECT 
            t.topic_id,
            t.title,
            t.content,
            t.view_count,
            t.reply_count,
            t.like_count,
            t.created_at,
            t.is_essence,
            u.user_id,
            u.username,
            u.avatar_url,
            s.section_id,
            s.name as section_name
        FROM 
            topics t
        JOIN 
            users u ON t.user_id = u.user_id
        JOIN 
            sections s ON t.section_id = s.section_id
    ";

    // 根据筛选类型添加条件
    switch ($filter) {
        case 'hot':
            // 热门帖子：根据浏览量、回复数和点赞数的加权算法排序
            $sql .= " WHERE t.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            $sql .= " ORDER BY (t.view_count * 0.3 + t.reply_count * 0.4 + t.like_count * 0.3) DESC";
            break;
            
        case 'featured':
            // 精选帖子：只显示精华帖
            $sql .= " WHERE t.is_essence = 1";
            $sql .= " ORDER BY t.created_at DESC";
            break;
            
        case 'latest':
        default:
            // 最新帖子：按创建时间排序
            $sql .= " ORDER BY t.created_at DESC";
            break;
    }

    // 添加分页限制
    $sql .= " LIMIT :limit OFFSET :offset";

    // 准备并执行查询
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    // 获取结果
    $topics = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 对内容进行安全处理
    foreach ($topics as &$topic) {
        $topic['title'] = htmlspecialchars($topic['title'], ENT_QUOTES, 'UTF-8');
        $topic['content'] = htmlspecialchars($topic['content'], ENT_QUOTES, 'UTF-8');
        $topic['username'] = htmlspecialchars($topic['username'], ENT_QUOTES, 'UTF-8');
        $topic['section_name'] = htmlspecialchars($topic['section_name'], ENT_QUOTES, 'UTF-8');
        
        // 确保统计字段有默认值
        $topic['view_count'] = $topic['view_count'] ?? 0;
        $topic['reply_count'] = $topic['reply_count'] ?? 0;
        $topic['like_count'] = $topic['like_count'] ?? 0;
        
        // 处理头像URL
        $topic['avatar_url'] = $topic['avatar_url'] ?? '';
    }

    // 返回JSON格式数据 - 修复：前端需要从data属性获取话题列表
    echo json_encode([
        'success' => true,
        'data' => $topics,
        'meta' => [
            'current_page' => $page,
            'next_page' => count($topics) === $limit ? $page + 1 : null,
            'filter' => $filter
        ]
    ]);

} catch (PDOException $e) {
    // 错误处理
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => '数据库查询失败: ' . $e->getMessage(),
        'error' => [
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
}
