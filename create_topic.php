<?php
require_once 'common/functions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['verify_start_time'])) {
    $_SESSION['verify_start_time'] = 0;
}
if (!isset($_SESSION['verify_token'])) {
    $_SESSION['verify_token'] = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_verification'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'CSRF验证失败'
        ]);
        exit;
    }
    
    $_SESSION['verify_start_time'] = time();
    $_SESSION['verify_token'] = bin2hex(random_bytes(16));
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'token' => $_SESSION['verify_token']
    ]);
    exit;
}

$user = get_logged_in_user();
if (!$user) {
    header("Location: login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

function get_user_today_topics($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM topics 
        WHERE user_id = :user_id 
        AND created_at >= CURDATE()
    ");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
}

$today_posts = get_user_today_topics($user['user_id']);
$daily_limit = 5;
$post_limit_reached = $today_posts >= $daily_limit;

$errors = [];
$success = false;
$title = $content = $tags = '';
$section_id = 1;
$is_draft = $receive_notifications = 0;

$sections = get_sections();

$draft_key = 'draft_' . $user['user_id'];
$has_draft = false;
$draft_data = [];

if (isset($_COOKIE[$draft_key])) {
    $draft_data = json_decode($_COOKIE[$draft_key], true);
    if ($draft_data && is_array($draft_data)) {
        $has_draft = true;
        $title = $draft_data['title'] ?? '';
        $content = $draft_data['content'] ?? '';
        $tags = $draft_data['tags'] ?? '';
        $section_id = $draft_data['section_id'] ?? 1;
    }
}

if (isset($_FILES['image_upload']) && $_FILES['image_upload']['error'] === UPLOAD_ERR_OK) {
    try {
        $upload_dir = 'uploads/articles/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $file_info = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($file_info, $_FILES['image_upload']['tmp_name']);
        finfo_close($file_info);
        
        if (!in_array($mime_type, $allowed_types)) {
            throw new Exception('只允许上传JPEG、PNG、GIF或WEBP格式的图片');
        }
        
        $max_file_size = 5 * 1024 * 1024;
        if ($_FILES['image_upload']['size'] > $max_file_size) {
            throw new Exception('文件大小不能超过5MB');
        }
        
        $ext = pathinfo($_FILES['image_upload']['name'], PATHINFO_EXTENSION);
        $filename = uniqid('img_') . '.' . $ext;
        $filepath = $upload_dir . $filename;
        
        if (move_uploaded_file($_FILES['image_upload']['tmp_name'], $filepath)) {
            $image_url = '/' . $filepath;
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'url' => $image_url,
                'markdown' => "![图片描述]($image_url)"
            ]);
            exit;
        } else {
            throw new Exception('文件上传失败，可能是权限问题');
        }
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage(),
            'error_code' => 1001
        ]);
        exit;
    }
}

if (!isset($_SESSION['behavior_verify_token'])) {
    $_SESSION['behavior_verify_token'] = bin2hex(random_bytes(32));
}
$behavior_verify_token = $_SESSION['behavior_verify_token'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($post_limit_reached) {
        $errors[] = '您今天发布的帖子数量已达上限（5篇），请明天再来吧';
    } else {
        if (!isset($_POST['behavior_verify_token']) || 
            !isset($_POST['verify_complete']) || 
            $_POST['verify_complete'] != 'true' ||
            $_POST['behavior_verify_token'] != $behavior_verify_token) {
            $errors[] = '请完成安全验证后再发布';
        } else {
            if (!isset($_POST['verify_token']) || 
                empty($_POST['verify_token']) ||
                $_POST['verify_token'] != $_SESSION['verify_token'] ||
                $_SESSION['verify_start_time'] == 0 ||
                (time() - $_SESSION['verify_start_time']) < 3) {
                $errors[] = '验证时间不足，请长按3秒完成验证';
            } else {
                unset($_SESSION['verify_token']);
                unset($_SESSION['verify_start_time']);
                unset($_SESSION['behavior_verify_token']);
                
                $section_id = intval($_POST['section'] ?? 1);
                $title = trim($_POST['title'] ?? '');
                $content = trim($_POST['content'] ?? '');
                $tags = trim($_POST['tags'] ?? '');
                $is_draft = isset($_POST['is_draft']) ? 1 : 0;
                $receive_notifications = isset($_POST['receive_notifications']) ? 1 : 0;

                $valid_section = false;
                foreach ($sections as $section) {
                    if ($section['section_id'] == $section_id) {
                        $valid_section = true;
                        break;
                    }
                }
                if (!$valid_section) {
                    $errors[] = '请选择有效的板块';
                }

                if (empty($title)) {
                    $errors[] = '标题不能为空';
                } elseif (mb_strlen($title) < 5 || mb_strlen($title) > 50) {
                    $errors[] = '标题长度必须在5-50个字符之间';
                }

                if (empty($content)) {
                    $errors[] = '内容不能为空';
                } elseif (mb_strlen($content) < 10) {
                    $errors[] = '内容长度不能少于10个字符';
                }

                $tag_array = [];
                if (!empty($tags)) {
                    $tag_array = array_map('trim', explode(',', $tags));
                    $tag_array = array_filter($tag_array);
                    
                    if (count($tag_array) > 5) {
                        $errors[] = '最多只能添加5个标签';
                    }
                    
                    foreach ($tag_array as $tag) {
                        if (mb_strlen($tag) > 20) {
                            $errors[] = '每个标签长度不能超过20个字符';
                            break;
                        }
                    }
                }

                if (empty($errors)) {
                    try {
                        global $pdo;
                        
                        $pdo->beginTransaction();
                        
                        $stmt = $pdo->prepare("
                            INSERT INTO topics 
                            (title, content, user_id, section_id, is_sticky, is_essence, is_closed, created_at, updated_at) 
                            VALUES 
                            (:title, :content, :user_id, :section_id, 0, 0, 0, NOW(), NOW())
                        ");
                        $stmt->bindParam(':title', $title);
                        $stmt->bindParam(':content', $content);
                        $stmt->bindParam(':user_id', $user['user_id'], PDO::PARAM_INT);
                        $stmt->bindParam(':section_id', $section_id, PDO::PARAM_INT);
                        $stmt->execute();
                        
                        $topic_id = $pdo->lastInsertId();
                        
                        if (!empty($tag_array)) {
                            foreach ($tag_array as $tag_name) {
                                $stmt = $pdo->prepare("SELECT tag_id FROM tags WHERE name = :name");
                                $stmt->bindParam(':name', $tag_name);
                                $stmt->execute();
                                $tag = $stmt->fetch(PDO::FETCH_ASSOC);
                                
                                if ($tag) {
                                    $tag_id = $tag['tag_id'];
                                    
                                    $stmt = $pdo->prepare("UPDATE tags SET usage_count = usage_count + 1 WHERE tag_id = :tag_id");
                                    $stmt->bindParam(':tag_id', $tag_id, PDO::PARAM_INT);
                                    $stmt->execute();
                                } else {
                                    $stmt = $pdo->prepare("INSERT INTO tags (name, usage_count, created_at) VALUES (:name, 1, NOW())");
                                    $stmt->bindParam(':name', $tag_name);
                                    $stmt->execute();
                                    $tag_id = $pdo->lastInsertId();
                                }
                                
                                $stmt = $pdo->prepare("INSERT INTO topic_tags (topic_id, tag_id) VALUES (:topic_id, :tag_id)");
                                $stmt->bindParam(':topic_id', $topic_id, PDO::PARAM_INT);
                                $stmt->bindParam(':tag_id', $tag_id, PDO::PARAM_INT);
                                $stmt->execute();
                            }
                        }
                        
                        $pdo->commit();
                        
                        setcookie($draft_key, '', time() - 3600, '/');
                        unset($_SESSION['draft_history_' . $user['user_id']]);
                        
                        header("Location: topic.php?id=$topic_id");
                        exit;
                        
                    } catch (PDOException $e) {
                        $pdo->rollBack();
                        error_log("发布话题失败: " . $e->getMessage());
                        $errors[] = '发布话题时出错，请稍后再试';
                    }
                }
            }
        }
    }
}

include('common/header.php');
?>

    <title>发布新帖 - <?= htmlspecialchars($site_info['site_title']) ?></title>
    <link rel="stylesheet" href="https://scdn.星.fun/npm/easymde/dist/easymde.min.css">
    <style>
        .editor-toolbar button:hover {
            background-color: #f1f5f9 !important;
        }
        .CodeMirror {
            min-height: 300px;
            border-radius: 0 0 8px 8px;
        }
        @media (max-width: 640px) {
            .CodeMirror {
                min-height: 200px;
            }
        }
        .editor-statusbar {
            display: none;
        }
        .save-status-container {
            margin-top: 0.5rem;
            margin-bottom: 1.5rem;
        }
        .save-status {
            font-size: 0.875rem;
            color: #64748b;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.5rem;
            padding: 0.3rem 0;
        }
        .save-status .spinner {
            width: 1rem;
            height: 1rem;
            border: 2px solid rgba(99, 102, 241, 0.3);
            border-radius: 50%;
            border-top-color: #6366f1;
            animation: spin 1s ease-in-out infinite;
            display: none;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .save-status.saving .spinner {
            display: inline-block;
        }
        .history-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        .history-modal.show {
            opacity: 1;
            visibility: visible;
        }
        .history-modal.show ~ #main-container {
            overflow: hidden;
            height: 100vh;
        }
        .history-content {
            background-color: white;
            border-radius: 8px;
            width: 90%;
            max-width: 800px;
            max-height: 80vh;
            overflow: hidden;
            transform: translateY(20px);
            transition: transform 0.3s ease;
        }
        .history-modal.show .history-content {
            transform: translateY(0);
        }
        .history-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .history-title {
            font-size: 1.25rem;
            font-weight: 600;
        }
        .history-actions {
            display: flex;
            gap: 1rem;
        }
        .clear-history-btn {
            background-color: #ef4444;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.875rem;
            transition: background-color 0.2s ease;
        }
        .clear-history-btn:hover {
            background-color: #dc2626;
        }
        .close-history {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #64748b;
        }
        .history-list {
            padding: 1rem 1.5rem;
            overflow-y: auto;
            max-height: calc(80vh - 120px);
        }
        .history-item {
            padding: 1rem;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .history-item:hover {
            border-color: #6366f1;
            background-color: #f8fafc;
        }
        .history-item-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }
        .history-time {
            font-size: 0.875rem;
            color: #64748b;
        }
        .history-item-actions {
            display: flex;
            gap: 0.5rem;
        }
        .history-btn {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            border: none;
            cursor: pointer;
        }
        .history-preview {
            background-color: #f1f5f9;
            color: #334155;
        }
        .history-restore {
            background-color: #6366f1;
            color: white;
        }
        .preview-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1001;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        .preview-modal.show {
            opacity: 1;
            visibility: visible;
        }
        .preview-content {
            background-color: white;
            border-radius: 8px;
            width: 90%;
            max-width: 800px;
            max-height: 80vh;
            overflow: hidden;
            transform: translateY(20px);
            transition: transform 0.3s ease;
        }
        .preview-modal.show .preview-content {
            transform: translateY(0);
        }
        .preview-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .preview-title {
            font-size: 1.25rem;
            font-weight: 600;
        }
        .close-preview {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #64748b;
        }
        .preview-body {
            padding: 1rem 1.5rem;
            overflow-y: auto;
            max-height: calc(80vh - 80px);
        }
        .preview-body h1 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }
        .preview-body .preview-content-text {
            line-height: 1.6;
        }
        .upload-progress-container {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            z-index: 2000;
            width: 90%;
            max-width: 500px;
            display: none;
        }
        .upload-progress-container.show {
            display: block;
        }
        .upload-progress-header {
            margin-bottom: 1rem;
            text-align: center;
        }
        .upload-progress-header h3 {
            margin: 0;
            color: #334155;
        }
        .progress-bar-container {
            width: 100%;
            height: 8px;
            background-color: #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 1rem;
        }
        .progress-bar {
            height: 100%;
            background-color: #6366f1;
            width: 0%;
            transition: width 0.3s ease;
        }
        .upload-status {
            text-align: center;
            color: #64748b;
            font-size: 0.875rem;
        }
        .upload-error {
            color: #ef4444;
            text-align: center;
            margin-top: 1rem;
            font-size: 0.875rem;
            display: none;
        }
        .upload-error.show {
            display: block;
        }
        .error-details {
            font-size: 0.75rem;
            margin-top: 0.5rem;
            padding: 0.5rem;
            background-color: #fee2e2;
            border-radius: 4px;
            display: none;
        }
        .error-details.show {
            display: block;
        }
        .retry-upload {
            margin-top: 1rem;
            text-align: center;
        }
        .retry-btn {
            background-color: #6366f1;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.875rem;
            transition: background-color 0.2s ease;
        }
        .retry-btn:hover {
            background-color: #4f46e5;
        }
        .btn-primary {
            background-color: #6366f1;
            color: white;
            border: none;
            padding: 0.6rem 1.2rem;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        .btn-primary:hover {
            background-color: #4f46e5;
            transform: translateY(-1px);
        }
        .btn-primary:active {
            transform: translateY(0);
        }
        .btn-primary:disabled {
            background-color: #a5b4fc;
            cursor: not-allowed;
        }
        
        .save-info {
            flex: 1;
            min-width: 200px;
        }
        .save-actions {
            display: flex;
            gap: 0.5rem;
        }
        #save-now-btn, #show-history-btn {
            white-space: nowrap;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8125rem;
            background: none;
            border: none;
        }
        
        .verify-container {
            margin: 1.5rem 0;
            text-align: center;
        }
        .verify-button {
            background-color: #6366f1;
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
            overflow: hidden;
        }
        .verify-button:disabled {
            background-color: #94a3b8;
            cursor: not-allowed;
        }
        .verify-button .verify-progress {
            position: absolute;
            top: 0;
            left: 0;
            height: 100%;
            background-color: rgba(255, 255, 255, 0.2);
            width: 0%;
            transition: width 0.1s linear;
        }
        .verify-button .verify-text {
            position: relative;
            z-index: 1;
        }
        .verify-message {
            margin-top: 0.5rem;
            font-size: 0.875rem;
            color: #64748b;
        }
        
        .limit-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 3000;
        }
        .limit-content {
            background-color: white;
            border-radius: 8px;
            padding: 2rem;
            max-width: 90%;
            width: 500px;
            text-align: center;
        }
        .limit-icon {
            font-size: 3rem;
            color: #ef4444;
            margin-bottom: 1rem;
        }
        .limit-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #1e293b;
        }
        .limit-message {
            color: #64748b;
            margin-bottom: 2rem;
            line-height: 1.6;
        }
        .limit-ok {
            background-color: #6366f1;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }
        .limit-ok:hover {
            background-color: #4f46e5;
        }
        
        @media (max-width: 480px) {
            #save-now-btn, #show-history-btn {
                padding: 0.2rem 0.4rem;
                font-size: 0.75rem;
            }
            
            .save-status {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .save-actions {
                margin-top: 0.5rem;
            }
            
            .grid-cols-2.sm\:grid-cols-3.md\:grid-cols-4 {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 0.5rem;
            }
            
            .flex.justify-end.gap-3 {
                flex-direction: column;
                gap: 0.75rem;
            }
            
            .btn-primary, button[type="button"][onclick="window.history.back()"] {
                width: 100%;
                padding: 0.75rem;
            }
            
            .verify-button {
                width: 100%;
                padding: 0.75rem 1rem;
            }
            
            .bg-white.rounded-xl.shadow-card.p-5 {
                padding: 1rem;
            }
            
            h1.text-xl.font-bold.mb-6 {
                margin-bottom: 1rem;
                font-size: 1.5rem;
            }
            
            .mb-5 {
                margin-bottom: 1rem;
            }
            
            .mb-6 {
                margin-bottom: 1.5rem;
            }
            
            input#title, input#tags {
                padding: 0.75rem;
                font-size: 1rem;
            }
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen flex flex-col overflow-x-hidden m-0 p-0">
    <?php if ($post_limit_reached): ?>
    <div class="limit-modal" id="limit-modal">
        <div class="limit-content">
            <h2 class="limit-title">发布限制</h2>
            <p class="limit-message">
                您今天已经发布了<?= $today_posts ?>篇帖子，达到了每日发布上限（5篇）。<br>
                请明天再来发布新内容吧！
            </p>
            <button class="limit-ok" onclick="window.location.href='index.php'">确定</button>
        </div>
    </div>
    <?php endif; ?>

    <main class="flex-grow flex flex-col md:flex-row transition-all duration-300 pt-6" id="main-container">
        <?php include('common/asideleft.php'); ?>
        <div class="flex-grow transition-all duration-300 p-4 md:ml-64 lg:ml-72" id="content-area">
            <div class="max-w-5xl mx-auto">
                <div class="bg-white rounded-xl shadow-card p-5 mb-6">
                    <h1 class="text-xl font-bold mb-6">发布新帖</h1>
                    
                    <?php if ($has_draft && !isset($_POST['delete_draft'])): ?>
                        <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fa fa-info-circle text-blue-500"></i>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-blue-800">您有一份草稿待发布</h3>
                                    <div class="mt-2 text-sm text-blue-700">
                                        <p>上次编辑时间: <?= date('Y-m-d H:i:s', $draft_data['timestamp'] ?? time()) ?></p>
                                        <div class="mt-2 flex gap-2">
                                            <button type="button" onclick="continueEditing()" class="px-3 py-1 bg-blue-500 text-white rounded text-sm hover:bg-blue-600 transition-colors">
                                                继续编辑
                                            </button>
                                            <button type="button" onclick="deleteDraft()" class="px-3 py-1 bg-white border border-slate-300 text-slate-700 rounded text-sm hover:bg-slate-50 transition-colors">
                                                删除草稿
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($errors)): ?>
                        <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fa fa-exclamation-circle text-red-500"></i>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-red-800">提交失败，请修正以下错误：</h3>
                                    <div class="mt-2 text-sm text-red-700">
                                        <ul class="list-disc pl-5 space-y-1">
                                            <?php foreach ($errors as $error): ?>
                                                <li><?php echo htmlspecialchars($error); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" id="topic-form" enctype="multipart/form-data">
                        <input type="hidden" name="verify_token" id="verify_token" value="">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                        <input type="hidden" name="behavior_verify_token" value="<?= $behavior_verify_token ?>">
                        <input type="hidden" name="verify_complete" id="verify_complete" value="false">
                        <input type="file" id="image-upload" name="image_upload" accept="image/*" style="display: none;">
                        
                        <div class="mb-5">
                            <label class="block text-sm font-medium text-slate-700 mb-2">选择板块</label>
                            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2">
                                <?php foreach ($sections as $section): ?>
                                    <label class="flex items-center gap-2 p-3 border border-slate-200 rounded-lg cursor-pointer hover:border-primary hover:bg-primary/5 transition-colors has-[:checked]:border-primary has-[:checked]:bg-primary/10">
                                        <input type="radio" name="section" value="<?php echo $section['section_id']; ?>" class="hidden" <?php echo $section_id == $section['section_id'] ? 'checked' : ''; ?>>
                                        <img src="<?= htmlspecialchars($section['icon'] ?? 'static/icon/forum/更多服务.svg') ?>" alt="<?= htmlspecialchars($section['name']) ?>板块图标" width="32" height="32">
                                        <span><?php echo htmlspecialchars($section['name']); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="mb-5">
                            <label for="title" class="block text-sm font-medium text-slate-700 mb-2">标题</label>
                            <input type="text" id="title" name="title" class="w-full px-4 py-3 rounded-lg border border-slate-300 focus:border-primary focus:ring-2 focus:ring-primary/50 transition-all" placeholder="请输入标题（5-50字）" value="<?php echo htmlspecialchars($title); ?>" <?php echo $post_limit_reached ? 'disabled' : ''; ?>>
                            
                            <div class="save-status-container">
                                <div class="save-status" id="save-status">
                                    <span class="spinner"></span>
                                    <div class="save-info">
                                        <span id="save-message">自动保存将在编辑时开始</span>
                                        <p class="text-xs mt-1">1.仅保留最新3份,每30秒自动保存本地 2.清空浏览器缓存将丢失历史记录</p>
                                    </div>
                                    <div class="save-actions">
                                        <button type="button" id="save-now-btn" class="text-primary text-sm hover:underline" <?php echo $post_limit_reached ? 'disabled' : ''; ?>>立即保存</button>
                                        <button type="button" id="show-history-btn" class="text-primary text-sm hover:underline" <?php echo $post_limit_reached ? 'disabled' : ''; ?>>查看历史</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-5">
                            <label class="block text-sm font-medium text-slate-700 mb-2">内容</label>
                            <textarea id="editor" name="content" style="display: none;" <?php echo $post_limit_reached ? 'disabled' : ''; ?>><?php echo htmlspecialchars($content); ?></textarea>
                        </div>
                        
                        <div class="mb-5">
                            <label for="tags" class="block text-sm font-medium text-slate-700 mb-2">标签（最多5个，用英文逗号分隔）</label>
                            <input type="text" id="tags" name="tags" class="w-full px-4 py-3 rounded-lg border border-slate-300 focus:border-primary focus:ring-2 focus:ring-primary/50 transition-all" placeholder="例如: 摄影,绘画,日记" value="<?php echo htmlspecialchars($tags); ?>" <?php echo $post_limit_reached ? 'disabled' : ''; ?>>
                            <div class="flex flex-wrap gap-2 mt-2">
                                <?php if (!empty($tag_array)): ?>
                                    <?php foreach ($tag_array as $tag): ?>
                                        <span class="px-2 py-1 bg-primary/10 text-primary rounded-full text-xs"><?php echo htmlspecialchars($tag); ?></span>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- 行为验证按钮 -->
                        <div class="verify-container" <?php echo $post_limit_reached ? 'style="display: none;"' : ''; ?>>
                            <button type="button" id="verify-button" class="verify-button">
                                <div class="verify-progress"></div>
                                <span class="verify-text">长按3秒完成安全验证</span>
                            </button>
                            <p class="verify-message" id="verify-message">请完成安全验证后再发布</p>
                        </div>
                        
                        <div class="flex justify-end gap-3">
                            <button type="button" onclick="window.history.back()" class="px-6 py-2 bg-white border border-slate-300 text-slate-700 rounded-lg hover:border-primary hover:text-primary transition-colors">
                                取消
                            </button>
                            <button type="submit" class="btn-primary px-6" id="submit-button" disabled>
                                <i class="fa fa-paper-plane mr-1"></i> 发布
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php include('common/asideright.php'); ?>
    </main>
    
    <div class="history-modal" id="history-modal">
        <div class="history-content">
            <div class="history-header">
                <h2 class="history-title">保存历史</h2>
                <div class="history-actions">
                    <button class="clear-history-btn" id="clear-history-btn">清空历史</button>
                    <button class="close-history" id="close-history">&times;</button>
                </div>
            </div>
            <div class="history-list" id="history-list">
                <div class="text-center text-slate-500 py-6" id="no-history-message">
                    暂无保存历史
                </div>
            </div>
        </div>
    </div>
    
    <div class="preview-modal" id="preview-modal">
        <div class="preview-content">
            <div class="preview-header">
                <h2 class="preview-title">历史版本预览</h2>
                <button class="close-preview" id="close-preview">&times;</button>
            </div>
            <div class="preview-body" id="preview-body">
            </div>
        </div>
    </div>
    
    <div class="upload-overlay" id="upload-overlay"></div>
    <div class="upload-progress-container" id="upload-progress-container">
        <div class="upload-progress-header">
            <h3>上传图片</h3>
        </div>
        <div class="progress-bar-container">
            <div class="progress-bar" id="progress-bar"></div>
        </div>
        <div class="upload-status" id="upload-status">准备上传...</div>
        <div class="upload-error" id="upload-error">
            <span id="error-message"></span>
            <div class="error-details" id="error-details"></div>
        </div>
        <div class="retry-upload" id="retry-upload">
            <button class="retry-btn" id="retry-btn">重试</button>
        </div>
    </div>

    <script src="https://scdn.星.fun/npm/easymde/dist/easymde.min.js"></script>
    <script src="https://scdn.星.fun/npm/marked/marked.min.js"></script>
    <script>
        if (!window.csrfToken) {
            window.csrfToken = "<?= $_SESSION['csrf_token'] ?? '' ?>";
        }

        const easyMDE = new EasyMDE({
            element: document.getElementById('editor'),
            autoDownloadFontAwesome: false,
            spellChecker: false,
            placeholder: '写下你的内容...支持Markdown语法',
            toolbar: [
                'bold', 'italic', 'heading', '|',
                'quote', 'unordered-list', 'ordered-list', '|',
                'link', {
                    name: 'image',
                    action: function(editor) {
                        document.getElementById('image-upload').click();
                    },
                    className: 'fa fa-image',
                    title: '插入图片',
                }, '|',
                'preview', 'side-by-side', 'fullscreen', '|',
                'guide'
            ],
            status: false
        });

        const imageUpload = document.getElementById('image-upload');
        const uploadOverlay = document.getElementById('upload-overlay');
        const uploadProgressContainer = document.getElementById('upload-progress-container');
        const progressBar = document.getElementById('progress-bar');
        const uploadStatus = document.getElementById('upload-status');
        const uploadError = document.getElementById('upload-error');
        const errorMessage = document.getElementById('error-message');
        const errorDetails = document.getElementById('error-details');
        const retryUpload = document.getElementById('retry-upload');
        const retryBtn = document.getElementById('retry-btn');
        let currentFile = null;

        imageUpload.addEventListener('change', function(e) {
            if (e.target.files.length > 0) {
                currentFile = e.target.files[0];
                uploadImage(currentFile);
            }
        });

        function uploadImage(file) {
            uploadOverlay.classList.add('show');
            uploadProgressContainer.classList.add('show');
            progressBar.style.width = '0%';
            uploadStatus.textContent = '正在准备上传...';
            
            const formData = new FormData();
            formData.append('image_upload', file);
            formData.append('csrf_token', window.csrfToken);
            
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'create_topic.php', true);
            
            xhr.upload.addEventListener('progress', function(e) {
                if (e.lengthComputable) {
                    const percent = (e.loaded / e.total) * 100;
                    progressBar.style.width = percent + '%';
                    
                    if (percent < 10) {
                        uploadStatus.textContent = `开始上传... ${Math.round(percent)}%`;
                    } else if (percent < 99) {
                        uploadStatus.textContent = `上传中... ${Math.round(percent)}%`;
                    } else {
                        uploadStatus.textContent = '正在处理图片...';
                    }
                }
            });
            
            xhr.onload = function() {
                try {
                    progressBar.style.width = '100%';
                    uploadStatus.textContent = '正在处理图片...';
                    
                    let response;
                    try {
                        response = JSON.parse(xhr.responseText);
                    } catch (e) {
                        throw new Error('服务器返回格式错误');
                    }
                    
                    if (response.success && response.url) {
                        setTimeout(() => {
                            const markdown = `![图片描述](${response.url})`;
                            easyMDE.codemirror.replaceSelection(markdown);
                            
                            const cursorPos = easyMDE.codemirror.getCursor();
                            easyMDE.codemirror.setCursor({
                                line: cursorPos.line,
                                ch: cursorPos.ch - (response.url.length + 2)
                            });
                            
                            uploadOverlay.classList.remove('show');
                            uploadProgressContainer.classList.remove('show');
                            imageUpload.value = '';
                            currentFile = null;
                        }, 300);
                    } else {
                        throw new Error(response.message || '上传失败');
                    }
                } catch (error) {
                    showUploadError('上传失败', error.message);
                    console.error('图片上传错误:', error);
                }
            };
            
            xhr.onerror = function() {
                showUploadError('网络错误', '上传过程中发生网络错误');
            };
            
            xhr.ontimeout = function() {
                showUploadError('上传超时', '上传时间过长，请重试');
            };
            
            xhr.timeout = 30000;
            xhr.send(formData);
        }

        function showUploadError(message, details) {
            uploadStatus.textContent = '上传失败';
            errorMessage.textContent = message;
            
            if (details) {
                errorDetails.textContent = details;
                errorDetails.classList.add('show');
            }
            
            uploadError.classList.add('show');
            retryUpload.style.display = 'block';
        }

        retryBtn.addEventListener('click', function() {
            if (currentFile) {
                uploadError.classList.remove('show');
                errorDetails.classList.remove('show');
                retryUpload.style.display = 'none';
                uploadImage(currentFile);
            }
        });

        let lastSaveTime = 0;
        let lastSavedContentHash = '';
        const draftKey = 'draft_<?= $user['user_id'] ?>';
        const historyKey = 'draft_history_<?= $user['user_id'] ?>';
        const form = document.getElementById('topic-form');
        const titleInput = document.getElementById('title');
        const tagsInput = document.getElementById('tags');
        const saveStatus = document.getElementById('save-status');
        const saveMessage = document.getElementById('save-message');
        const saveNowBtn = document.getElementById('save-now-btn');
        const showHistoryBtn = document.getElementById('show-history-btn');
        const historyModal = document.getElementById('history-modal');
        const historyList = document.getElementById('history-list');
        const closeHistoryBtn = document.getElementById('close-history');
        const noHistoryMessage = document.getElementById('no-history-message');
        const previewModal = document.getElementById('preview-modal');
        const previewBody = document.getElementById('preview-body');
        const closePreviewBtn = document.getElementById('close-preview');
        const clearHistoryBtn = document.getElementById('clear-history-btn');
        
        const MAX_HISTORY_COUNT = 3;
        
        function getContentHash() {
            const content = JSON.stringify({
                title: titleInput.value,
                content: easyMDE.value(),
                tags: tagsInput.value,
                section_id: document.querySelector('input[name="section"]:checked').value
            });
            let hash = 0;
            for (let i = 0; i < content.length; i++) {
                const char = content.charCodeAt(i);
                hash = ((hash << 5) - hash) + char;
                hash = hash & hash;
            }
            return hash.toString();
        }
        
        function saveDraft(isManualSave = false) {
            const currentHash = getContentHash();
            
            if (currentHash === lastSavedContentHash && !isManualSave) {
                return;
            }
            
            saveStatus.classList.add('saving');
            saveMessage.textContent = isManualSave ? '正在保存...' : '自动保存中...';
            
            const currentDraft = {
                title: titleInput.value,
                content: easyMDE.value(),
                tags: tagsInput.value,
                section_id: document.querySelector('input[name="section"]:checked').value,
                timestamp: Math.floor(Date.now() / 1000)
            };
            
            localStorage.setItem(draftKey, JSON.stringify(currentDraft));
            lastSavedContentHash = currentHash;
            saveToHistory(currentDraft);
            
            lastSaveTime = Date.now();
            saveStatus.classList.remove('saving');
            saveMessage.textContent = `已保存于 ${formatDateTime(currentDraft.timestamp)}`;
            
            document.cookie = `${draftKey}=1; path=/; max-age=${30 * 24 * 60 * 60}`;
        }
        
        function saveToHistory(draft) {
            let history = JSON.parse(localStorage.getItem(historyKey) || '[]');
            history.unshift({
                id: Date.now(),
                ...draft
            });
            
            if (history.length > MAX_HISTORY_COUNT) {
                history = history.slice(0, MAX_HISTORY_COUNT);
            }
            
            localStorage.setItem(historyKey, JSON.stringify(history));
            renderHistoryList();
        }
        
        function renderHistoryList() {
            const history = JSON.parse(localStorage.getItem(historyKey) || '[]');
            historyList.innerHTML = '';
            
            if (history.length === 0) {
                noHistoryMessage.style.display = 'block';
                clearHistoryBtn.disabled = true;
                return;
            }
            
            noHistoryMessage.style.display = 'none';
            clearHistoryBtn.disabled = false;
            
            history.forEach(item => {
                const historyItem = document.createElement('div');
                historyItem.className = 'history-item';
                historyItem.dataset.id = item.id;
                
                const displayTitle = item.title.length > 30 
                    ? item.title.substring(0, 30) + '...' 
                    : (item.title || '无标题');
                
                historyItem.innerHTML = `
                    <div class="history-item-header">
                        <div class="history-title">${displayTitle}</div>
                        <div class="history-item-actions">
                            <button class="history-btn history-preview" data-id="${item.id}">预览</button>
                            <button class="history-btn history-restore" data-id="${item.id}">恢复</button>
                        </div>
                    </div>
                    <div class="history-time">${formatDateTime(item.timestamp)}</div>
                `;
                
                historyList.appendChild(historyItem);
            });
            
            document.querySelectorAll('.history-preview').forEach(btn => {
                btn.addEventListener('click', e => {
                    e.stopPropagation();
                    previewHistory(parseInt(e.currentTarget.dataset.id));
                });
            });
            
            document.querySelectorAll('.history-restore').forEach(btn => {
                btn.addEventListener('click', e => {
                    e.stopPropagation();
                    restoreHistory(parseInt(e.currentTarget.dataset.id));
                });
            });
        }
        
        function previewHistory(id) {
            const history = JSON.parse(localStorage.getItem(historyKey) || '[]');
            const item = history.find(h => h.id === id);
            
            if (item) {
                previewBody.innerHTML = `
                    <h1>${item.title || '无标题'}</h1>
                    <div class="preview-content-text">${marked.parse(item.content || '')}</div>
                    <div class="text-sm text-slate-500 mt-4">保存于 ${formatDateTime(item.timestamp)}</div>
                `;
                previewModal.classList.add('show');
            }
        }
        
        function restoreHistory(id) {
            const history = JSON.parse(localStorage.getItem(historyKey) || '[]');
            const item = history.find(h => h.id === id);
            
            if (item && confirm('确定要恢复此版本吗？当前内容将被覆盖。')) {
                titleInput.value = item.title || '';
                easyMDE.value(item.content || '');
                tagsInput.value = item.tags || '';
                document.querySelector(`input[name="section"][value="${item.section_id || 1}"]`).checked = true;
                saveDraft(true);
                historyModal.classList.remove('show');
            }
        }
        
        function clearHistory() {
            if (confirm('确定要清空所有保存历史吗？此操作不可恢复。')) {
                localStorage.removeItem(historyKey);
                renderHistoryList();
            }
        }
        
        function deleteDraft() {
            if (confirm('确定要删除草稿吗？此操作不可恢复。')) {
                localStorage.removeItem(draftKey);
                localStorage.removeItem(historyKey);
                document.cookie = `${draftKey}=; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT`;
                window.location.reload();
            }
        }
        
        function continueEditing() {
            const draft = localStorage.getItem(draftKey);
            if (draft) {
                const data = JSON.parse(draft);
                titleInput.value = data.title || '';
                easyMDE.value(data.content || '');
                tagsInput.value = data.tags || '';
                document.querySelector(`input[name="section"][value="${data.section_id || 1}"]`).checked = true;
                lastSavedContentHash = getContentHash();
            }
        }
        
        setInterval(() => {
            if (titleInput.value || easyMDE.value() || tagsInput.value) {
                saveDraft();
            }
        }, 20000);
        
        saveNowBtn.addEventListener('click', () => saveDraft(true));
        
        showHistoryBtn.addEventListener('click', () => {
            renderHistoryList();
            historyModal.classList.add('show');
            document.body.style.overflow = 'hidden';
        });
        
        closeHistoryBtn.addEventListener('click', () => {
            historyModal.classList.remove('show');
            document.body.style.overflow = '';
        });
        
        clearHistoryBtn.addEventListener('click', clearHistory);
        
        closePreviewBtn.addEventListener('click', () => previewModal.classList.remove('show'));
        
        historyModal.addEventListener('click', (e) => {
            if (e.target === historyModal) {
                historyModal.classList.remove('show');
                document.body.style.overflow = '';
            }
        });
        
        previewModal.addEventListener('click', (e) => {
            if (e.target === previewModal) previewModal.classList.remove('show');
        });
        
        form.addEventListener('submit', () => {
            localStorage.removeItem(draftKey);
            localStorage.removeItem(historyKey);
        });
        
        document.addEventListener('DOMContentLoaded', () => {
            continueEditing();
            renderHistoryList();
            
            const draft = localStorage.getItem(draftKey);
            if (draft) {
                const data = JSON.parse(draft);
                saveMessage.textContent = `已保存于 ${formatDateTime(data.timestamp)}`;
            }
            
            // 初始化行为验证按钮
            initVerifyButton();
        });

        function formatDateTime(timestamp) {
            const date = new Date(timestamp * 1000);
            return `${date.getFullYear()}-${(date.getMonth() + 1).toString().padStart(2, '0')}-${date.getDate().toString().padStart(2, '0')} ${date.getHours().toString().padStart(2, '0')}:${date.getMinutes().toString().padStart(2, '0')}`;
        }
        
        
        
        
function initVerifyButton() {
    const verifyButton = document.getElementById('verify-button');
    const verifyProgress = verifyButton.querySelector('.verify-progress');
    const verifyMessage = document.getElementById('verify-message');
    const submitButton = document.getElementById('submit-button');
    const verifyCompleteInput = document.getElementById('verify_complete');
    const verifyTokenInput = document.getElementById('verify_token');
    
    let pressTimer = null;
    let progress = 0;
    let progressInterval = null;
    const requiredTime = 3000; // 3秒
    const intervalTime = 50;
    let verificationInProgress = false;
    let serverToken = null;
    
    // 向服务器请求开始验证
    function requestStartVerification() {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'create_topic.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            resolve(response.token);
                        } else {
                            reject('服务器拒绝验证请求');
                        }
                    } catch (e) {
                        reject('服务器响应格式错误');
                    }
                } else {
                    reject('服务器错误: ' + xhr.status);
                }
            };
            xhr.onerror = function() {
                reject('网络错误');
            };
            xhr.send('start_verification=1&csrf_token=' + encodeURIComponent(window.csrfToken));
        });
    }
    
    function startVerification() {
        if (verifyCompleteInput.value === 'true') return;
        
        // 重置状态
        progress = 0;
        verifyProgress.style.width = '0%';
        verifyMessage.textContent = '正在初始化验证...';
        verificationInProgress = true;
        
        // 向服务器请求开始验证
        requestStartVerification()
            .then(token => {
                serverToken = token;
                verifyTokenInput.value = token;
                
                // 初始化成功后开始计时
                verifyMessage.textContent = '正在验证...请继续长按';
                progressInterval = setInterval(() => {
                    if (!verificationInProgress) return;
                    
                    progress += intervalTime;
                    const percent = Math.min(100, (progress / requiredTime) * 100);
                    verifyProgress.style.width = `${percent}%`;
                    
                    if (progress >= requiredTime) {
                        completeVerification();
                    }
                }, intervalTime);
            })
            .catch(error => {
                verifyMessage.textContent = '验证初始化失败: ' + error;
                verificationInProgress = false;
            });
    }
    
    function endVerification() {
        if (progress < requiredTime && progressInterval && verificationInProgress) {
            clearInterval(progressInterval);
            verifyProgress.style.width = '0%';
            verifyMessage.textContent = '验证未完成，请长按3秒';
            verificationInProgress = false;
            serverToken = null;
        }
    }
    
    function completeVerification() {
        if (!verificationInProgress || !serverToken) return;
        
        clearInterval(progressInterval);
        verifyProgress.style.width = '100%';
        verifyButton.disabled = true;
        verifyButton.querySelector('.verify-text').textContent = '验证成功';
        verifyMessage.textContent = '安全验证已完成，可以发布了';
        
        // 更新表单状态
        verifyCompleteInput.value = 'true';
        submitButton.disabled = false;
        verificationInProgress = false;
    }
    
    // 绑定事件
    verifyButton.addEventListener('mousedown', startVerification);
    verifyButton.addEventListener('touchstart', (e) => {
        e.preventDefault();
        startVerification();
    });
    
    verifyButton.addEventListener('mouseup', endVerification);
    verifyButton.addEventListener('mouseleave', endVerification);
    verifyButton.addEventListener('touchend', (e) => {
        e.preventDefault();
        endVerification();
    });
    
    // 防止通过控制台直接调用
    window.completeVerification = function() {
        verifyMessage.textContent = '验证失败：检测到异常操作';
        return false;
    };
}


    </script>
<?php include('common/footer.php'); ?>
