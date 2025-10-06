<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// 数据库连接
try {
    $dsn = "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=".DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("数据库连接失败: " . $e->getMessage());
}

/**
 * 获取站点信息
 */
function get_site_info() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM site_info LIMIT 1");
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * 获取活跃用户
 * @param int $limit 数量限制
 */
function get_active_users($limit = 20) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM active_users LIMIT :limit");
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * 获取板块列表
 */
function get_sections() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM sections WHERE is_active = 1 ORDER BY display_order");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * 获取最新话题
 * @param int $limit 数量限制
 */
function get_latest_topics($limit = 10) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT t.*, u.username, u.avatar_url, s.name as section_name 
        FROM topics t
        JOIN users u ON t.user_id = u.user_id
        JOIN sections s ON t.section_id = s.section_id
        ORDER BY t.created_at DESC LIMIT :limit
    ");
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * 时间格式化显示
 * @param string $datetime 数据库时间字符串
 */
function time_ago($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    
    if ($diff < 60) {
        return '刚刚';
    } elseif ($diff < 3600) {
        return floor($diff / 60) . '分钟前';
    } elseif ($diff < 86400) {
        return floor($diff / 3600) . '小时前';
    } elseif ($diff < 2592000) {
        return floor($diff / 86400) . '天前';
    } else {
        return date('Y-m-d', $time);
    }
}

/**
 * 获取话题总数
 */
function get_topic_count() {
    global $pdo;
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM topics");
    return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
}

/**
 * 获取回复总数
 */
function get_reply_count() {
    global $pdo;
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM replies");
    return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
}

/**
 * 获取用户总数
 */
function get_user_count() {
    global $pdo;
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
}

/**
 * 获取热门标签
 * @param int $limit 数量限制
 */
function get_popular_tags($limit = 5) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT * FROM popular_tags 
        ORDER BY usage_count DESC 
        LIMIT :limit
    ");
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * 获取热门话题
 * @param int $limit 数量限制
 */
function get_hot_topics($limit = 5) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT 
            t.topic_id,
            t.title,
            t.content,
            t.view_count,
            t.reply_count,
            t.like_count,
            t.created_at,
            u.user_id,
            u.username,
            u.avatar_url
        FROM 
            topics t
        JOIN 
            users u ON t.user_id = u.user_id
        WHERE 
            t.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY 
            (t.view_count * 0.3 + t.reply_count * 0.4 + t.like_count * 0.3) DESC
        LIMIT :limit
    ");
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
/**
 * 获取精选话题
 * @param int $limit 数量限制
 */
function get_featured_topics($limit = 10) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT t.*, u.username, u.avatar_url, s.name as section_name 
        FROM topics t
        JOIN users u ON t.user_id = u.user_id
        JOIN sections s ON t.section_id = s.section_id
        WHERE t.is_essence = 1
        ORDER BY t.created_at DESC 
        LIMIT :limit
    ");
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * 获取随机标签
 * @param int $limit 数量限制
 */
function get_random_tags($limit = 10) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT 
            tag_id, 
            name 
        FROM 
            tags 
        ORDER BY 
            RAND()
        LIMIT :limit
    ");
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * 获取单个话题详情
 * @param int $topic_id 话题ID
 */
function get_topic($topic_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT t.*, u.username, u.avatar_url, u.bio, s.name as section_name 
        FROM topics t
        JOIN users u ON t.user_id = u.user_id
        JOIN sections s ON t.section_id = s.section_id
        WHERE t.topic_id = :topic_id
    ");
    $stmt->bindParam(':topic_id', $topic_id, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * 获取话题的标签
 * @param int $topic_id 话题ID
 */
function get_topic_tags($topic_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT t.tag_id, t.name 
        FROM topic_tags tt
        JOIN tags t ON tt.tag_id = t.tag_id
        WHERE tt.topic_id = :topic_id
    ");
    $stmt->bindParam(':topic_id', $topic_id, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


/**
 * 获取话题的回复总数
 * @param int $topic_id 话题ID
 */
function get_topic_reply_count($topic_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM replies WHERE topic_id = :topic_id");
    $stmt->bindParam(':topic_id', $topic_id, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
}

/**
 * 增加话题查看次数
 * @param int $topic_id 话题ID
 */
function increment_topic_views($topic_id) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE topics SET view_count = view_count + 1 WHERE topic_id = :topic_id");
    $stmt->bindParam(':topic_id', $topic_id, PDO::PARAM_INT);
    $stmt->execute();
}

/**
 * 检查用户是否点赞了话题或回复
 * @param int $user_id 用户ID
 * @param string $target_type 目标类型(topic/reply)
 * @param int $target_id 目标ID
 */
function has_user_liked($user_id, $target_type, $target_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM likes 
        WHERE user_id = :user_id AND target_type = :target_type AND target_id = :target_id
    ");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':target_type', $target_type);
    $stmt->bindParam(':target_id', $target_id, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
}

/**
 * 用户登录
 * @param string $username 用户名或邮箱
 * @param string $password 密码
 */
function user_login($username, $password) {
    global $pdo;
    
    // 查询用户
    $stmt = $pdo->prepare("
        SELECT user_id, username, email, password_hash, role, status, avatar_url 
        FROM users 
        WHERE username = :username OR email = :username
    ");
    $stmt->bindParam(':username', $username);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        return ['success' => false, 'message' => '用户名或邮箱不存在'];
    }
    
    // 检查账号状态
    if ($user['status'] !== 'active') {
        $statusText = $user['status'] === 'banned' ? '封禁' : '注销';
        return [
            'success' => false,
            'message' => "账号已被{$statusText}，如需帮助请联系管理员<br><b>（主页页脚有联系方式）</b>"
        ];
    }
    
    // 验证密码
    if (!password_verify($password, $user['password_hash'])) {
        return ['success' => false, 'message' => '密码不正确'];
    }
    
    // 更新最后登录时间
    $stmt = $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE user_id = :user_id");
    $stmt->bindParam(':user_id', $user['user_id']);
    $stmt->execute();
    
    // 设置会话
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['avatar_url'] = $user['avatar_url'];
    
    return ['success' => true];
}

/**
 * 用户注册
 * @param string $username 用户名
 * @param string $email 邮箱
 * @param string $password 密码
 */
function user_register($username, $email, $password) {
    global $pdo;
    $username = trim($username);
    if (mb_strlen($username, 'UTF-8') < 2 || mb_strlen($username, 'UTF-8') > 16) {
        return ['success' => false, 'message' => '用户名长度必须在2-16位之间'];
    }
    if (!preg_match('/^[\x{4e00}-\x{9fa5}a-zA-Z0-9_\-]+$/u', $username)) {
        return ['success' => false, 'message' => '用户名只能包含中文、字母、数字、下划线和减号'];
    }
    
    // 验证邮箱格式和QQ邮箱
    $email = trim($email);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => '邮箱格式不正确'];
    }
    if (!preg_match('/@qq\.com$/i', $email)) {
        return ['success' => false, 'message' => '目前仅支持QQ邮箱注册'];
    }
    
    // 验证密码强度
    if (strlen($password) < 6 || strlen($password) > 20) {
        return ['success' => false, 'message' => '密码长度必须在6-20位之间'];
    }
    if (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        return ['success' => false, 'message' => '密码必须包含大小写字母和数字'];
    }
    
    // 检查用户名是否已存在
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = :username");
    $stmt->bindParam(':username', $username);
    $stmt->execute();
    if ($stmt->fetchColumn() > 0) {
        return ['success' => false, 'message' => '用户名已存在'];
    }
    
    // 检查邮箱是否已存在
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    if ($stmt->fetchColumn() > 0) {
        return ['success' => false, 'message' => '邮箱已被注册'];
    }
    
    // 创建用户
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("
        INSERT INTO users (username, email, password_hash, created_at, updated_at)
        VALUES (:username, :email, :password_hash, NOW(), NOW())
    ");
    $stmt->bindParam(':username', $username);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':password_hash', $password_hash);
    
    try {
        $stmt->execute();
        $user_id = $pdo->lastInsertId();
        
        // 设置会话
        $_SESSION['user_id'] = $user_id;
        $_SESSION['username'] = $username;
        $_SESSION['role'] = 'user';
        $_SESSION['avatar_url'] = null;
        
        return ['success' => true, 'user_id' => $user_id]; // 确保返回 user_id
    } catch (PDOException $e) {
        error_log("注册失败: " . $e->getMessage());
        return ['success' => false, 'message' => '注册失败，请稍后再试'];
    }
}

/**
     * 获取当前登录用户信息
     * 优先从数据库获取最新信息，确保头像等数据是最新的
     */
    function get_logged_in_user() {
        if (!isset($_SESSION['user_id'])) {
            return null;
        }
        
        global $pdo;
        
        // 从数据库获取最新的用户信息
        $stmt = $pdo->prepare("
            SELECT user_id, username, role, avatar_url 
            FROM users 
            WHERE user_id = :user_id
        ");
        $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            // 更新会话中的数据，保持同步
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['avatar_url'] = $user['avatar_url'];
            
            return $user;
        }
        
        // 如果数据库中没有找到用户，可能是用户被删除，此时清除会话
        user_logout();
        return null;
    }
    

/**
 * 用户登出
 */
function user_logout() {
    // 清除会话数据
    session_unset();
    session_destroy();
    
    // 重新生成会话ID
    session_regenerate_id(true);
}

/**
 * 获取话题的回复
 * 
 * @param int $topic_id 话题ID
 * @param int $page 页码
 * @param string $sort 排序方式：newest（最新）、oldest（最早）、popular（热门）
 * @param int $per_page 每页回复数
 * @return array 回复列表
 */
function get_topic_replies($topic_id, $page = 1, $sort = 'newest', $per_page = 10) {
    global $pdo;
    
    $topic_id = intval($topic_id);
    $page = max(1, intval($page));
    $offset = ($page - 1) * $per_page;
    $per_page = intval($per_page); // 确保每页数量是整数
    
    // 确定排序方式
    switch ($sort) {
        case 'oldest':
            $order = 'created_at ASC';
            break;
        case 'popular':
            $order = 'like_count DESC, created_at DESC';
            break;
        case 'newest':
        default:
            $order = 'created_at DESC';
            break;
    }
    
    // 修改SQL语句，使用命名参数
    $stmt = $pdo->prepare("
        SELECT r.*, u.username, u.avatar_url
        FROM replies r
        JOIN users u ON r.user_id = u.user_id
        WHERE r.topic_id = :topic_id
        ORDER BY $order
        LIMIT :offset, :per_page
    ");
    
    // 绑定参数并指定数据类型为整数
    $stmt->bindParam(':topic_id', $topic_id, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindParam(':per_page', $per_page, PDO::PARAM_INT);
    
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
    


// 获取常用数据
$site_info = get_site_info();
$sections = get_sections();
?>