<?php
require_once 'common/functions.php';

// 确保CSRF令牌存在
if (empty($_SESSION['csrf_token'])) {
    // 生成随机令牌
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 检查用户是否登录
$user = get_logged_in_user();
if (!$user) {
    header("Location: login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// 验证话题ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit;
}
$topic_id = intval($_GET['id']);

// 获取话题信息
$topic = get_topic($topic_id);
if (!$topic) {
    header("Location: index.php");
    exit;
}

// 检查权限：只有话题作者、管理员或版主可以编辑
$is_author = ($topic['user_id'] == $user['user_id']);
$is_admin = ($user['role'] == 'admin');
$is_moderator = ($user['role'] == 'moderator');

if (!$is_author && !$is_admin && !$is_moderator) {
    // 记录未授权访问尝试
    error_log("未授权用户尝试编辑话题: User ID=" . $user['user_id'] . ", Topic ID=" . $topic_id);
    header("Location: topic.php?id=$topic_id");
    exit;
}

// 获取话题当前标签
$current_tags = get_topic_tags($topic_id);
$current_tags_str = implode(', ', array_column($current_tags, 'name'));

// 获取板块列表
$sections = get_sections();

$errors = [];
$success = false;
$title = $topic['title'];
$content = $topic['content'];
$tags = $current_tags_str;
$section_id = $topic['section_id'];
$reason = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 验证CSRF令牌 - 现在确保$_SESSION['csrf_token']已定义
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = '表单验证失败，请刷新页面重试';
        // 记录CSRF验证失败
        error_log("CSRF验证失败: User ID=" . $user['user_id'] . ", Topic ID=" . $topic_id);
        
        // 生成新的令牌，防止重试攻击
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } else {
        // 验证通过后生成新的令牌，防止重复提交
        $old_csrf_token = $_SESSION['csrf_token'];
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        
        $section_id = intval($_POST['section'] ?? $topic['section_id']);
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $tags = trim($_POST['tags'] ?? '');
        $reason = trim($_POST['reason'] ?? '');

        // 验证板块
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

        // 验证标题
        if (empty($title)) {
            $errors[] = '标题不能为空';
        } elseif (mb_strlen($title) < 5 || mb_strlen($title) > 50) {
            $errors[] = '标题长度必须在5-50个字符之间';
        }

        // 验证内容
        if (empty($content)) {
            $errors[] = '内容不能为空';
        } elseif (mb_strlen($content) < 10) {
            $errors[] = '内容长度不能少于10个字符';
        }

        // 验证标签
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

        // 管理员/版主编辑需要填写理由
        if (($is_admin || $is_moderator) && !$is_author && empty($reason)) {
            $errors[] = '请填写修改理由';
        }

        if (empty($errors)) {
            try {
                global $pdo;
                $pdo->beginTransaction();

                // 检查是否有实际修改
                $has_changes = false;
                $changes = [];

                // 标题变化
                if ($title !== $topic['title']) {
                    $has_changes = true;
                    $changes['title'] = [
                        'old' => $topic['title'],
                        'new' => $title
                    ];
                }

                // 内容变化
                if ($content !== $topic['content']) {
                    $has_changes = true;
                    $changes['content'] = [
                        'old' => $topic['content'],
                        'new' => $content
                    ];
                }

                // 板块变化
                if ($section_id != $topic['section_id']) {
                    $has_changes = true;
                    $changes['section'] = [
                        'old' => $topic['section_id'],
                        'new' => $section_id
                    ];
                }

                // 标签变化
                $new_tags = array_map('strtolower', $tag_array);
                $old_tags = array_map('strtolower', array_column($current_tags, 'name'));
                
                if (implode(',', $new_tags) !== implode(',', $old_tags)) {
                    $has_changes = true;
                    $changes['tags'] = [
                        'old' => $current_tags,
                        'new' => $tag_array
                    ];
                }

                // 如果没有实际修改，直接返回
                if (!$has_changes) {
                    $pdo->rollBack();
                    header("Location: topic.php?id=$topic_id");
                    exit;
                }

                // 更新话题信息
                $stmt = $pdo->prepare("
                    UPDATE topics 
                    SET title = :title, 
                        content = :content, 
                        section_id = :section_id,
                        updated_at = NOW()
                    WHERE topic_id = :topic_id
                ");
                $stmt->bindParam(':title', $title);
                $stmt->bindParam(':content', $content);
                $stmt->bindParam(':section_id', $section_id, PDO::PARAM_INT);
                $stmt->bindParam(':topic_id', $topic_id, PDO::PARAM_INT);
                $stmt->execute();

                // 处理标签变化
                if (isset($changes['tags'])) {
                    // 删除旧标签关联
                    $stmt = $pdo->prepare("DELETE FROM topic_tags WHERE topic_id = :topic_id");
                    $stmt->bindParam(':topic_id', $topic_id, PDO::PARAM_INT);
                    $stmt->execute();

                    // 更新标签使用次数
                    foreach ($current_tags as $old_tag) {
                        $stmt = $pdo->prepare("
                            UPDATE tags 
                            SET usage_count = GREATEST(0, usage_count - 1) 
                            WHERE tag_id = :tag_id
                        ");
                        $stmt->bindParam(':tag_id', $old_tag['tag_id'], PDO::PARAM_INT);
                        $stmt->execute();
                    }

                    // 添加新标签
                    if (!empty($tag_array)) {
                        foreach ($tag_array as $tag_name) {
                            $stmt = $pdo->prepare("SELECT tag_id FROM tags WHERE LOWER(name) = LOWER(:name)");
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
                }

                // 记录修改历史
                $stmt = $pdo->prepare("
                    INSERT INTO topic_modifications 
                    (topic_id, user_id, action, old_title, new_title, old_content, new_content, 
                     old_section_id, new_section_id, old_tags, new_tags, reason, created_at) 
                    VALUES 
                    (:topic_id, :user_id, 'edit', :old_title, :new_title, :old_content, :new_content,
                     :old_section_id, :new_section_id, :old_tags, :new_tags, :reason, NOW())
                ");
                
                $old_tags_json = json_encode(array_column($current_tags, 'name'));
                $new_tags_json = json_encode($tag_array);
                
                $stmt->bindParam(':topic_id', $topic_id, PDO::PARAM_INT);
                $stmt->bindParam(':user_id', $user['user_id'], PDO::PARAM_INT);
                $stmt->bindParam(':old_title', $topic['title']);
                $stmt->bindParam(':new_title', $title);
                $stmt->bindParam(':old_content', $topic['content']);
                $stmt->bindParam(':new_content', $content);
                $stmt->bindParam(':old_section_id', $topic['section_id'], PDO::PARAM_INT);
                $stmt->bindParam(':new_section_id', $section_id, PDO::PARAM_INT);
                $stmt->bindParam(':old_tags', $old_tags_json);
                $stmt->bindParam(':new_tags', $new_tags_json);
                $stmt->bindParam(':reason', $reason);
                $stmt->execute();

                $pdo->commit();

                // 跳转到话题页面，使用303状态码防止表单重提交
                header("Location: topic.php?id=$topic_id&updated=1", true, 303);
                exit;
                
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("编辑话题失败: " . $e->getMessage());
                $errors[] = '编辑话题时出错，请稍后再试';
            }
        }
    }
}

include('common/header.php');
?>
    <title>编辑话题 - <?= htmlspecialchars($site_info['site_title']) ?></title>
    <link rel="stylesheet" href="https://scdn.星.fun/npm/easymde/dist/easymde.min.css">
    <style>
        .editor-toolbar button:hover {
            background-color: #f1f5f9 !important;
        }
        .CodeMirror {
            min-height: 300px;
            border-radius: 0 0 8px 8px;
        }
        .editor-statusbar {
            display: none;
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
        .edit-info {
            background-color: #f8fafc;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            color: #64748b;
        }
        .reason-container {
            background-color: #fff3cd;
            border-left: 4px solid #fbbf24;
            padding: 1rem;
            border-radius: 0 8px 8px 0;
            margin-bottom: 1.5rem;
        }
        .reason-container h3 {
            margin-top: 0;
            margin-bottom: 0.5rem;
            color: #92400e;
            font-size: 1rem;
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen flex flex-col overflow-x-hidden m-0 p-0">
    <main class="flex-grow flex flex-col md:flex-row transition-all duration-300 pt-6" id="main-container">
        <?php include('common/asideleft.php'); ?>
        <div class="flex-grow transition-all duration-300 p-4 md:ml-64 lg:ml-72" id="content-area">
            <div class="max-w-5xl mx-auto">
                <div class="bg-white rounded-xl shadow-card p-5 mb-6">
                    <h1 class="text-xl font-bold mb-6">编辑话题</h1>
                    
                    <div class="edit-info">
                        <p>
                            话题创建于: <?= date('Y-m-d H:i', strtotime($topic['created_at'])) ?><br>
                            最后编辑于: <?= $topic['updated_at'] ? date('Y-m-d H:i', strtotime($topic['updated_at'])) : '未编辑过' ?>
                        </p>
                    </div>
                    
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
                    
                    <?php if (($is_admin || $is_moderator) && !$is_author): ?>
                        <div class="reason-container">
                            <h3>修改理由（管理员/版主编辑需要填写）</h3>
                            <p class="text-sm text-amber-800 mb-2">请说明修改此话题的原因，以便用户理解和记录</p>
                            <textarea name="reason" id="reason" rows="2" class="w-full px-4 py-2 rounded-lg border border-slate-300 focus:border-primary focus:ring-2 focus:ring-primary/50 transition-all" placeholder="请输入修改理由"><?php echo htmlspecialchars($reason); ?></textarea>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" id="topic-form" enctype="multipart/form-data">
                        <!-- 现在确保csrf_token总是被设置 -->
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                        <input type="file" id="image-upload" name="image_upload" accept="image/*" style="display: none;">
                        
                        <div class="mb-5">
                            <label class="block text-sm font-medium text-slate-700 mb-2">选择板块</label>
                            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2">
                                <?php foreach ($sections as $section): ?>
                                    <label class="flex items-center gap-2 p-3 border border-slate-200 rounded-lg cursor-pointer hover:border-primary hover:bg-primary/5 transition-colors has-[:checked]:border-primary has-[:checked]:bg-primary/10">
                                        <input type="radio" name="section" value="<?php echo $section['section_id']; ?>" class="hidden" <?php echo $section_id == $section['section_id'] ? 'checked' : ''; ?>>
                                        <i class="fa <?php echo htmlspecialchars($section['icon'] ?? 'fa-comments'); ?> text-primary"></i>
                                        <span><?php echo htmlspecialchars($section['name']); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="mb-5">
                            <label for="title" class="block text-sm font-medium text-slate-700 mb-2">标题</label>
                            <input type="text" id="title" name="title" class="w-full px-4 py-3 rounded-lg border border-slate-300 focus:border-primary focus:ring-2 focus:ring-primary/50 transition-all" placeholder="请输入标题（5-50字）" value="<?php echo htmlspecialchars($title); ?>">
                        </div>
                        
                        <div class="mb-5">
                            <label class="block text-sm font-medium text-slate-700 mb-2">内容</label>
                            <textarea id="editor" name="content" style="display: none;"><?php echo htmlspecialchars($content); ?></textarea>
                        </div>
                        
                        <div class="mb-5">
                            <label for="tags" class="block text-sm font-medium text-slate-700 mb-2">标签（最多5个，用英文逗号分隔）</label>
                            <input type="text" id="tags" name="tags" class="w-full px-4 py-3 rounded-lg border border-slate-300 focus:border-primary focus:ring-2 focus:ring-primary/50 transition-all" placeholder="例如: 摄影,绘画,日记" value="<?php echo htmlspecialchars($tags); ?>">
                            
                            <?php 
                            $tag_array = array_map('trim', explode(',', $tags));
                            $tag_array = array_filter($tag_array);
                            ?>
                            
                            <div class="flex flex-wrap gap-2 mt-2">
                                <?php if (!empty($tag_array)): ?>
                                    <?php foreach ($tag_array as $tag): ?>
                                        <span class="px-2 py-1 bg-primary/10 text-primary rounded-full text-xs"><?php echo htmlspecialchars($tag); ?></span>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="flex justify-end gap-3">
                            <button type="button" onclick="window.history.back()" class="px-6 py-2 bg-white border border-slate-300 text-slate-700 rounded-lg hover:border-primary hover:text-primary transition-colors">
                                取消
                            </button>
                            <button type="submit" class="btn-primary px-6">
                                <i class="fa fa-save mr-1"></i> 保存修改
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php include('common/asideright.php'); ?>
    </main>

    <script src="https://scdn.星.fun/npm/easymde/dist/easymde.min.js"></script>
    <script>
        // 确保CSRF令牌在JavaScript中可用
        const csrfToken = "<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>";
        if (!window.csrfToken) {
            window.csrfToken = csrfToken;
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

        // 图片上传功能与create_topic.php保持一致
        const imageUpload = document.getElementById('image-upload');
        
        imageUpload.addEventListener('change', function(e) {
            if (e.target.files.length > 0) {
                uploadImage(e.target.files[0]);
            }
        });

        function uploadImage(file) {
            // 创建上传进度指示器
            const uploadOverlay = document.createElement('div');
            uploadOverlay.className = 'fixed inset-0 bg-black/30 z-50 flex items-center justify-center';
            uploadOverlay.innerHTML = `
                <div class="bg-white p-6 rounded-lg max-w-md w-full">
                    <h3 class="text-lg font-medium mb-4">上传图片</h3>
                    <div class="w-full h-2 bg-gray-200 rounded-full mb-4">
                        <div id="upload-progress" class="h-full bg-primary rounded-full" style="width: 0%"></div>
                    </div>
                    <p id="upload-status" class="text-sm text-gray-600">准备上传...</p>
                </div>
            `;
            document.body.appendChild(uploadOverlay);
            
            const progressBar = uploadOverlay.querySelector('#upload-progress');
            const uploadStatus = uploadOverlay.querySelector('#upload-status');

            const formData = new FormData();
            formData.append('image_upload', file);
            formData.append('csrf_token', window.csrfToken);
            
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'create_topic.php', true);
            
            xhr.upload.addEventListener('progress', function(e) {
                if (e.lengthComputable) {
                    const percent = (e.loaded / e.total) * 100;
                    progressBar.style.width = percent + '%';
                    uploadStatus.textContent = `上传中... ${Math.round(percent)}%`;
                }
            });
            
            xhr.onload = function() {
                try {
                    let response = JSON.parse(xhr.responseText);
                    
                    if (response.success && response.url) {
                        progressBar.style.width = '100%';
                        uploadStatus.textContent = '上传成功，正在插入图片...';
                        
                        setTimeout(() => {
                            const markdown = `![图片描述](${response.url})`;
                            easyMDE.codemirror.replaceSelection(markdown);
                            document.body.removeChild(uploadOverlay);
                            imageUpload.value = '';
                        }, 500);
                    } else {
                        throw new Error(response.message || '上传失败');
                    }
                } catch (error) {
                    uploadStatus.textContent = `上传失败: ${error.message}`;
                    progressBar.className = 'h-full bg-red-500 rounded-full';
                    
                    setTimeout(() => {
                        document.body.removeChild(uploadOverlay);
                    }, 2000);
                }
            };
            
            xhr.onerror = function() {
                uploadStatus.textContent = '网络错误，上传失败';
                progressBar.className = 'h-full bg-red-500 rounded-full';
                
                setTimeout(() => {
                    document.body.removeChild(uploadOverlay);
                }, 2000);
            };
            
            xhr.send(formData);
        }
    </script>
<?php include('common/footer.php'); ?>
