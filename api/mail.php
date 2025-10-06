<?php
require_once __DIR__ . '/../common/functions.php';
require_once __DIR__ . '/../common/MailService.php';

// 确保在输出任何内容前设置头信息
header('Content-Type: application/json; charset=utf-8');

// CORS设置 - 更严格的来源验证
$allowedOrigins = [
    'http://' . $_SERVER['HTTP_HOST'],
    'https://' . $_SERVER['HTTP_HOST']
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '';
$isAllowed = false;

foreach ($allowedOrigins as $allowed) {
    if (strpos($origin, $allowed) === 0) {
        $isAllowed = true;
        break;
    }
}

if (!$isAllowed) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => '请求被拒绝：不允许的来源']);
    exit;
}

// 添加CORS头
header("Access-Control-Allow-Origin: " . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Credentials: true");

// 处理预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// 获取并验证请求数据
$rawInput = file_get_contents('php://input');
if ($rawInput === false) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => '无法读取请求数据'
    ]);
    exit;
}

$input = json_decode($rawInput, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => '无效的JSON格式',
        'error_details' => json_last_error_msg()
    ]);
    exit;
}

// 验证必填字段
if (empty($input['type']) || empty($input['email'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => '缺少必要参数：类型和邮箱为必填项'
    ]);
    exit;
}

// 验证邮件类型
$allowedTypes = ['register', 'change_email', 'forgot_password'];
if (!in_array($input['type'], $allowedTypes)) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => '无效的邮件类型',
        'error_details' => '允许的类型：' . implode(', ', $allowedTypes)
    ]);
    exit;
}

// 验证邮箱格式
if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => '邮箱格式不正确',
        'error_details' => '请输入有效的邮箱地址'
    ]);
    exit;
}

// 检查Redis是否可用
if (!class_exists('Redis')) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => '服务器配置错误',
        'error_details' => 'Redis扩展未安装'
    ]);
    exit;
}

// 检查是否频繁发送
try {
    $redis = new Redis();
    $redis->connect(REDIS_HOST, REDIS_PORT);
    if (REDIS_PASS) {
        $redis->auth(REDIS_PASS);
    }
    
    $key = REDIS_PREFIX . 'mail_cooldown:' . $input['type'] . ':' . $input['email'];
    if ($redis->exists($key)) {
        $ttl = $redis->ttl($key);
        http_response_code(429);
        echo json_encode([
            'success' => false, 
            'message' => '请勿频繁发送验证码',
            'error_details' => "请在{$ttl}秒后再试"
        ]);
        exit;
    }
    
    // 设置冷却时间(60秒)
    $redis->setex($key, 60, '1');
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => '服务器缓存错误',
        'error_details' => $e->getMessage()
    ]);
    exit;
}

// 发送验证码
try {
    $mailService = MailService::getInstance();
    $result = $mailService->sendVerificationCode($input['email'], $input['type']);
    
    if ($result['success']) {
        echo json_encode([
            'success' => true, 
            'message' => '验证码已发送至您的邮箱，请查收',
            'expire' => 300 // 5分钟有效期
        ]);
    } else {
        http_response_code(500);
        echo json_encode($result);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => '发送验证码失败',
        'error_details' => $e->getMessage()
    ]);
}
    