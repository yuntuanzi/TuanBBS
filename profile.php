<?php
require_once 'common/functions.php';

/**
 * 获取用户信息
 * @param int $user_id 用户ID
 */
function get_user_info($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT 
            u.*,
            (SELECT COUNT(*) FROM topics WHERE user_id = u.user_id) as topic_count,
            (SELECT COUNT(*) FROM replies WHERE user_id = u.user_id) as reply_count,
            (SELECT COUNT(*) FROM likes WHERE user_id = u.user_id) as like_count,
            (SELECT COUNT(*) FROM follows WHERE follower_id = u.user_id) as following_count,
            (SELECT COUNT(*) FROM follows WHERE following_id = u.user_id) as follower_count
        FROM users u
        WHERE u.user_id = :user_id
    ");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * 更新用户信息
 * @param int $user_id 用户ID
 * @param array $data 更新数据
 */
function update_user_info($user_id, $data) {
    global $pdo;
    
    $allowed_fields = ['username', 'bio', 'avatar_url', 'cover_url'];
    $updates = [];
    $params = [':user_id' => $user_id];
    
    foreach ($data as $key => $value) {
        if (in_array($key, $allowed_fields)) {
            $updates[] = "$key = :$key";
            $params[":$key"] = $value;
        }
    }
    
    if (empty($updates)) {
        return false;
    }
    
    $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE user_id = :user_id";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($params);
}

/**
 * 获取用户话题（支持分页）
 * @param int $user_id 用户ID
 * @param int $page 页码
 * @param int $limit 数量限制
 */
function get_user_topics($user_id, $page = 1, $limit = 10) {
    global $pdo;
    $offset = ($page - 1) * $limit;
    
    // 获取总数
    $countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM topics WHERE user_id = :user_id");
    $countStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $countStmt->execute();
    $total = $countStmt->fetchColumn();
    
    // 获取数据
    $stmt = $pdo->prepare("
        SELECT t.*, s.name as section_name 
        FROM topics t
        JOIN sections s ON t.section_id = s.section_id
        WHERE t.user_id = :user_id
        ORDER BY t.created_at DESC 
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $topics = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 计算是否有下一页
    $hasMore = ($page * $limit) < $total;
    
    return [
        'data' => $topics,
        'meta' => [
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'has_more' => $hasMore
        ]
    ];
}

/**
 * 获取用户回复（支持分页）
 * @param int $user_id 用户ID
 * @param int $page 页码
 * @param int $limit 数量限制
 */
function get_user_replies($user_id, $page = 1, $limit = 10) {
    global $pdo;
    $offset = ($page - 1) * $limit;
    
    // 获取总数
    $countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM replies WHERE user_id = :user_id");
    $countStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $countStmt->execute();
    $total = $countStmt->fetchColumn();
    
    // 获取数据
    $stmt = $pdo->prepare("
        SELECT r.*, t.title as topic_title, t.topic_id
        FROM replies r
        JOIN topics t ON r.topic_id = t.topic_id
        WHERE r.user_id = :user_id
        ORDER BY r.created_at DESC 
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $replies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 计算是否有下一页
    $hasMore = ($page * $limit) < $total;
    
    return [
        'data' => $replies,
        'meta' => [
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'has_more' => $hasMore
        ]
    ];
}

/**
 * 获取用户点赞的话题（支持分页）
 * @param int $user_id 用户ID
 * @param int $page 页码
 * @param int $limit 数量限制
 */
function get_user_liked_topics($user_id, $page = 1, $limit = 10) {
    global $pdo;
    $offset = ($page - 1) * $limit;
    
    // 获取总数
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM likes l
        JOIN topics t ON l.target_id = t.topic_id
        WHERE l.user_id = :user_id AND l.target_type = 'topic'
    ");
    $countStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $countStmt->execute();
    $total = $countStmt->fetchColumn();
    
    // 获取数据
    $stmt = $pdo->prepare("
        SELECT t.*, s.name as section_name 
        FROM likes l
        JOIN topics t ON l.target_id = t.topic_id
        JOIN sections s ON t.section_id = s.section_id
        WHERE l.user_id = :user_id AND l.target_type = 'topic'
        ORDER BY l.created_at DESC 
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $topics = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 计算是否有下一页
    $hasMore = ($page * $limit) < $total;
    
    return [
        'data' => $topics,
        'meta' => [
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'has_more' => $hasMore
        ]
    ];
}

/**
 * 检查是否关注了某用户
 * @param int $follower_id 关注者ID
 * @param int $following_id 被关注者ID
 */
function is_following($follower_id, $following_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM follows 
        WHERE follower_id = :follower_id AND following_id = :following_id
    ");
    $stmt->bindParam(':follower_id', $follower_id, PDO::PARAM_INT);
    $stmt->bindParam(':following_id', $following_id, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
}

/**
 * 删除话题
 * @param int $topic_id 话题ID
 * @param int $user_id 用户ID（用于验证权限）
 */
function delete_topic($topic_id, $user_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        DELETE FROM topics 
        WHERE topic_id = :topic_id AND user_id = :user_id
    ");
    $stmt->bindParam(':topic_id', $topic_id, PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    return $stmt->execute();
}

/**
 * 删除回复
 * @param int $reply_id 回复ID
 * @param int $user_id 用户ID（用于验证权限）
 */
function delete_reply($reply_id, $user_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        DELETE FROM replies 
        WHERE reply_id = :reply_id AND user_id = :user_id
    ");
    $stmt->bindParam(':reply_id', $reply_id, PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    return $stmt->execute();
}

/**
 * 处理文件上传
 * @param string $field_name 表单字段名
 * @param string $target_dir 目标目录
 * @param array $allowed_types 允许的文件类型
 * @param int $max_size 最大文件大小(字节)
 * @return array 包含成功状态和消息/文件路径的数组
 */
function handle_upload($field_name, $target_dir = 'uploads/', $allowed_types = ['image/jpeg', 'image/png', 'image/gif'], $max_size = 2097152) {
    // 确保目标目录存在
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0755, true);
    }
    
    // 检查是否有文件上传
    if (!isset($_FILES[$field_name]) || $_FILES[$field_name]['error'] == UPLOAD_ERR_NO_FILE) {
        return ['success' => false, 'message' => '没有文件被上传'];
    }
    
    $file = $_FILES[$field_name];
    
    // 检查上传错误
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => '文件大小超过服务器限制',
            UPLOAD_ERR_FORM_SIZE => '文件大小超过表单限制',
            UPLOAD_ERR_PARTIAL => '文件只有部分被上传',
            UPLOAD_ERR_NO_FILE => '没有文件被上传',
            UPLOAD_ERR_NO_TMP_DIR => '缺少临时文件夹',
            UPLOAD_ERR_CANT_WRITE => '写入磁盘失败',
            UPLOAD_ERR_EXTENSION => '文件上传被PHP扩展阻止'
        ];
        return ['success' => false, 'message' => $errors[$file['error']] ?? '未知上传错误'];
    }
    
    // 检查文件类型
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, $allowed_types)) {
        return ['success' => false, 'message' => '不允许的文件类型'];
    }
    
    // 检查文件大小
    if ($file['size'] > $max_size) {
        return ['success' => false, 'message' => '文件大小超过限制 (最大2MB)'];
    }
    
    // 生成唯一文件名
    $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $file_name = uniqid() . '.' . $file_ext;
    $target_path = $target_dir . $file_name;
    
    // 移动文件
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        return ['success' => true, 'path' => $target_path];
    } else {
        return ['success' => false, 'message' => '文件移动失败'];
    }
}

// 获取要查看的用户ID
$profile_user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($profile_user_id <= 0 && isset($_SESSION['user_id'])) {
    $profile_user_id = $_SESSION['user_id'];
}

if ($profile_user_id <= 0) {
    header("Location: login.php");
    exit;
}

// 获取用户信息
$user_info = get_user_info($profile_user_id);
if (!$user_info) {
    header("Location: 404.php");
    exit;
}

// 检查是否是当前用户自己的主页
$is_own_profile = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $profile_user_id;

// 获取当前标签页
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'topics';

// 处理编辑资料表单提交
if ($is_own_profile && isset($_POST['edit_profile'])) {
    $update_data = [];
    
    // 处理头像上传
    if (!empty($_FILES['avatar_file']['name'])) {
        $upload = handle_upload('avatar_file', 'uploads/avatars/');
        if ($upload['success']) {
            $update_data['avatar_url'] = $upload['path'];
        } else {
            $_SESSION['error'] = '头像上传失败: ' . $upload['message'];
        }
    } elseif (!empty($_POST['avatar_url']) && $_POST['avatar_url'] != $user_info['avatar_url']) {
        $update_data['avatar_url'] = trim($_POST['avatar_url']);
    }
    
    // 处理背景图上传
    if (!empty($_FILES['cover_file']['name'])) {
        $upload = handle_upload('cover_file', 'uploads/covers/');
        if ($upload['success']) {
            $update_data['cover_url'] = $upload['path'];
        } else {
            $_SESSION['error'] = '背景图上传失败: ' . $upload['message'];
        }
    } elseif (!empty($_POST['cover_url']) && $_POST['cover_url'] != $user_info['cover_url']) {
        $update_data['cover_url'] = trim($_POST['cover_url']);
    }
    
    // 处理其他字段
    if (!empty($_POST['username']) && $_POST['username'] != $user_info['username']) {
        $update_data['username'] = trim($_POST['username']);
    }
    
    if (isset($_POST['bio'])) {
        $update_data['bio'] = trim($_POST['bio']);
    }
    
    if (!empty($update_data)) {
        if (update_user_info($profile_user_id, $update_data)) {
            // 更新成功后刷新页面
            header("Location: profile.php?id=$profile_user_id");
            exit;
        } else {
            $_SESSION['error'] = '更新失败，请重试';
        }
    }
}

// 处理删除操作
if (isset($_POST['delete'])) {
    if (!$is_own_profile) {
        header("Location: profile.php?id=$profile_user_id");
        exit;
    }
    
    if (isset($_POST['topic_id'])) {
        delete_topic($_POST['topic_id'], $profile_user_id);
    } elseif (isset($_POST['reply_id'])) {
        delete_reply($_POST['reply_id'], $profile_user_id);
    }
    
    // 刷新页面
    header("Location: profile.php?id=$profile_user_id&tab=$tab");
    exit;
}

// 处理API请求 - 加载更多内容
if (isset($_GET['action']) && $_GET['action'] == 'load_more') {
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $result = ['success' => false, 'data' => [], 'meta' => []];
    
    try {
        switch ($tab) {
            case 'replies':
                $data = get_user_replies($profile_user_id, $page);
                break;
            case 'likes':
                $data = get_user_liked_topics($profile_user_id, $page);
                break;
            case 'topics':
            default:
                $data = get_user_topics($profile_user_id, $page);
                break;
        }
        
        $result = [
            'success' => true,
            'data' => $data['data'],
            'meta' => [
                'page' => $data['meta']['page'],
                'has_more' => $data['meta']['has_more']
            ]
        ];
    } catch (Exception $e) {
        $result['message'] = $e->getMessage();
    }
    
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
}

// 获取第一页内容
$page = 1;
switch ($tab) {
    case 'replies':
        $data = get_user_replies($profile_user_id, $page);
        $items = $data['data'];
        break;
    case 'likes':
        $data = get_user_liked_topics($profile_user_id, $page);
        $items = $data['data'];
        break;
    case 'topics':
    default:
        $tab = 'topics';
        $data = get_user_topics($profile_user_id, $page);
        $items = $data['data'];
        break;
}

// 检查当前登录用户是否关注了该用户
$is_following = false;
if (isset($_SESSION['user_id']) && !$is_own_profile) {
    $is_following = is_following($_SESSION['user_id'], $profile_user_id);
}

// 根据是否是自己的主页决定显示"我的"还是"Ta的"
$prefix = $is_own_profile ? '我的' : 'Ta的';

include('common/header.php');
?>
    <title><?php echo htmlspecialchars($user_info['username']); ?>的个人主页 - <?php echo htmlspecialchars($site_info['site_title']); ?></title>
    <script>
// 关注/取消关注函数
function toggleFollow(userId) {
    if (!<?php echo isset($_SESSION['user_id']) ? 'true' : 'false'; ?>) {
        window.location.href = 'login.php';
        return;
    }
    
    const btn = document.getElementById('follow-btn');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> 处理中...';
    
    const formData = new FormData();
    formData.append('following_id', userId);
    
    fetch('api/follow.php?action=follow', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // 更新按钮状态
            if (data.is_following) {
                btn.dataset.action = 'unfollow';
                btn.className = 'px-4 py-2 bg-slate-200 text-slate-700 rounded-lg hover:bg-slate-300 transition-all transform hover:scale-105 shadow-sm';
                btn.innerHTML = '已关注';
                showToast(data.message || '关注成功', 'success');
            } else {
                btn.dataset.action = 'follow';
                btn.className = 'px-4 py-2 bg-primary text-white rounded-lg hover:bg-dark transition-all transform hover:scale-105 shadow-sm';
                btn.innerHTML = '关注';
                showToast(data.message || '已取消关注', 'success');
            }
            
            // 更新粉丝数显示
            const followerCount = document.getElementById('follower-count');
            if (followerCount) {
                const currentCount = parseInt(followerCount.textContent);
                followerCount.textContent = data.is_following ? currentCount + 1 : currentCount - 1;
            }
        } else {
            showToast(data.message || '操作失败', 'error');
            btn.innerHTML = originalText;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('请求失败，请重试', 'error');
        btn.innerHTML = originalText;
    })
    .finally(() => {
        btn.disabled = false;
    });
}

// 多字节字符串截取函数（JavaScript版）
function mbSubstr(str, start, length) {
    // 处理边界情况
    if (!str) return '';
    if (length <= 0) return '';
    
    // 将字符串转换为数组，处理Unicode字符
    const chars = Array.from(str);
    // 计算结束索引
    const end = start + length;
    // 截取并返回结果
    return chars.slice(start, end).join('');
}

// 自动上传文件并显示进度条
function setupAutoUpload(fieldId, progressBarId, previewId, fileType) {
    const fileInput = document.getElementById(fieldId);
    const progressBar = document.getElementById(progressBarId);
    const preview = document.getElementById(previewId);
    
    if (!fileInput || !progressBar || !preview) return;
    
    fileInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;
        
        // 显示预览
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
        }
        reader.readAsDataURL(file);
        
        // 准备表单数据
        const formData = new FormData();
        formData.append(fileType === 'avatar' ? 'avatar_file' : 'cover_file', file);
        formData.append('edit_profile', '1');
        
        // 显示进度条
        progressBar.style.display = 'block';
        progressBar.querySelector('.progress-bar').style.width = '0%';
        progressBar.querySelector('.progress-text').textContent = '0%';
        
        // 发送文件
        const xhr = new XMLHttpRequest();
        xhr.open('POST', window.location.href, true);
        
        // 更新进度条
        xhr.upload.addEventListener('progress', function(e) {
            if (e.lengthComputable) {
                const percent = Math.round((e.loaded / e.total) * 100);
                progressBar.querySelector('.progress-bar').style.width = percent + '%';
                progressBar.querySelector('.progress-text').textContent = percent + '%';
            }
        });
        
        // 上传完成处理
        xhr.addEventListener('load', function() {
            if (xhr.status >= 200 && xhr.status < 300) {
                // 上传成功，刷新页面
                window.location.reload();
            } else {
                showToast('上传失败，请重试', 'error');
                progressBar.style.display = 'none';
            }
        });
        
        // 上传错误处理
        xhr.addEventListener('error', function() {
            showToast('上传失败，请重试', 'error');
            progressBar.style.display = 'none';
        });
        
        // 发送请求
        xhr.send(formData);
    });
}

// 自动加载更多内容功能
document.addEventListener('DOMContentLoaded', function() {
    // 初始化头像和封面图的自动上传
    setupAutoUpload('avatar_file', 'avatar_progress', 'avatar_preview', 'avatar');
    setupAutoUpload('avatar_file_edit', 'avatar_progress_edit', 'avatar_preview_edit', 'avatar');
    setupAutoUpload('cover_file', 'cover_progress', 'cover_preview', 'cover');
    setupAutoUpload('cover_file_edit', 'cover_progress_edit', 'cover_preview_edit', 'cover');
    
    // 自动加载配置
    let isLoading = false;
    let hasMore = <?php echo $data['meta']['has_more'] ? 'true' : 'false'; ?>;
    let currentPage = 1;
    const currentTab = '<?php echo $tab; ?>';
    const userId = <?php echo $profile_user_id; ?>;
    let scrollTimeout;
    
    // 加载更多内容
    function loadMoreContent() {
        if (isLoading || !hasMore) return;
        
        isLoading = true;
        // 显示加载指示器
        document.getElementById('loading-indicator').classList.remove('hidden');
        document.getElementById('end-message').classList.add('hidden');
        document.getElementById('error-message').classList.add('hidden');
        
        const nextPage = currentPage + 1;
        
        fetch(`profile.php?id=${userId}&tab=${currentTab}&action=load_more&page=${nextPage}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('网络响应不正常');
                }
                return response.json();
            })
            .then(data => {
                if (!data.success) {
                    throw new Error(data.message || '加载数据失败');
                }
                
                const items = data.data || [];
                const container = document.querySelector('.space-y-4');
                
                if (items.length > 0 && container) {
                    items.forEach(item => {
                        let html = '';
                        
                        // 根据当前标签页生成不同的HTML
                        if (currentTab === 'topics' || currentTab === 'likes') {
                            // 话题卡片
                            html = `
                            <article class="post-card">
                                <div class="p-5 flex flex-col">
                                    <h4 class="font-bold text-lg mb-3 hover:text-primary transition-colors">
                                        <a href="topic.php?id=${item.topic_id}">${escapeHtml(item.title)}</a>
                                    </h4>
                                    <p class="text-slate-600 mb-4 line-clamp-3">${escapeHtml(mbSubstr(stripTags(item.content), 0, 100))}...</p>
                                    <div class="flex flex-wrap items-center justify-between mt-auto">
                                        <div class="flex items-center text-sm text-slate-500">
                                            <span class="mr-3">${escapeHtml(item.section_name)}</span>
                                            <span><i class="fa fa-clock-o mr-1"></i> ${timeAgo(item.created_at)}</span>
                                        </div>
                                        <div class="flex items-center text-sm text-slate-500 gap-3">
                                            <span><i class="fa fa-eye mr-1"></i> ${item.view_count}</span>
                                            <span><i class="fa fa-comment mr-1"></i> ${item.reply_count}</span>
                                            <span><i class="fa fa-heart mr-1"></i> ${item.like_count}</span>
                                        </div>
                                    </div>
                                    <?php if ($is_own_profile && $tab == 'topics'): ?>
                                    <form method="post" class="mt-3 text-right">
                                        <input type="hidden" name="topic_id" value="${item.topic_id}">
                                        <button type="submit" name="delete" value="1" class="text-sm text-red-500 hover:text-red-700" onclick="return confirm('确定要删除这个话题吗？');">
                                            <i class="fa fa-trash"></i> 删除
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </article>`;
                        } else if (currentTab === 'replies') {
                            // 回复卡片
                            html = `
                            <article class="post-card">
                                <div class="p-5 flex flex-col">
                                    <h4 class="font-bold text-lg mb-3 hover:text-primary transition-colors">
                                        <a href="topic.php?id=${item.topic_id}#reply-${item.reply_id}">回复: ${escapeHtml(item.topic_title)}</a>
                                    </h4>
                                    <p class="text-slate-600 mb-4 line-clamp-3">${escapeHtml(mbSubstr(stripTags(item.content), 0, 100))}...</p>
                                    <div class="flex flex-wrap items-center justify-between mt-auto">
                                        <div class="flex items-center text-sm text-slate-500">
                                            <span><i class="fa fa-clock-o mr-1"></i> ${timeAgo(item.created_at)}</span>
                                        </div>
                                        <div class="flex items-center text-sm text-slate-500 gap-3">
                                            <span><i class="fa fa-heart mr-1"></i> ${item.like_count}</span>
                                        </div>
                                    </div>
                                    <?php if ($is_own_profile): ?>
                                    <form method="post" class="mt-3 text-right">
                                        <input type="hidden" name="reply_id" value="${item.reply_id}">
                                        <button type="submit" name="delete" value="1" class="text-sm text-red-500 hover:text-red-700" onclick="return confirm('确定要删除这条回复吗？');">
                                            <i class="fa fa-trash"></i> 删除
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </article>`;
                        }
                        
                        // 添加到容器
                        const tempDiv = document.createElement('div');
                        tempDiv.innerHTML = html;
                        container.appendChild(tempDiv.firstElementChild);
                    });
                    
                    // 更新状态
                    currentPage = nextPage;
                    hasMore = data.meta.has_more;
                } else {
                    hasMore = false;
                }
                
                // 更新加载状态
                document.getElementById('loading-indicator').classList.add('hidden');
                
                // 如果没有更多内容，显示提示
                if (!hasMore) {
                    document.getElementById('end-message').classList.remove('hidden');
                }
                
                isLoading = false;
            })
            .catch(error => {
                console.error('加载失败:', error);
                isLoading = false;
                document.getElementById('loading-indicator').classList.add('hidden');
                document.getElementById('error-message').classList.remove('hidden');
            });
    }
    
    // 监听滚动事件，添加防抖动
    window.addEventListener('scroll', function() {
        clearTimeout(scrollTimeout);
        scrollTimeout = setTimeout(() => {
            // 当滚动到距离底部100px时加载更多
            if ((window.innerHeight + window.scrollY) >= (document.body.offsetHeight - 100)) {
                loadMoreContent();
            }
        }, 50);
    });
    
    // 错误消息点击重试
    document.getElementById('error-message').addEventListener('click', loadMoreContent);
});

// 工具函数 - HTML转义
function escapeHtml(unsafe) {
    if (!unsafe) return '';
    return unsafe.toString()
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

// 工具函数 - 时间格式化
function timeAgo(datetime) {
    const time = new Date(datetime).getTime();
    const diff = (Date.now() - time) / 1000;
    
    if (diff < 60) return '刚刚';
    if (diff < 3600) return Math.floor(diff / 60) + '分钟前';
    if (diff < 86400) return Math.floor(diff / 3600) + '小时前';
    if (diff < 2592000) return Math.floor(diff / 86400) + '天前';
    
    return new Date(time).toISOString().split('T')[0];
}

// 工具函数 - 去除HTML标签
function stripTags(html) {
    if (!html) return '';
    const temp = document.createElement('div');
    temp.innerHTML = html;
    return temp.textContent || temp.innerText || '';
}

// 显示Toast通知
function showToast(message, type = 'success') {
    const toast = document.getElementById('toast-notification');
    const toastMessage = document.getElementById('toast-message');
    const toastIcon = toast.querySelector('i');
    
    // 设置消息内容
    toastMessage.textContent = message;
    
    // 根据类型设置样式
    switch(type) {
        case 'success':
            toast.className = 'fixed top-4 left-1/2 transform -translate-x-1/2 z-50 flex';
            toast.firstElementChild.className = 'bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg flex items-center gap-2 animate-fade-in';
            toastIcon.className = 'fa fa-check-circle text-xl';
            break;
        case 'error':
            toast.className = 'fixed top-4 left-1/2 transform -translate-x-1/2 z-50 flex';
            toast.firstElementChild.className = 'bg-red-500 text-white px-4 py-2 rounded-lg shadow-lg flex items-center gap-2 animate-fade-in';
            toastIcon.className = 'fa fa-exclamation-circle text-xl';
            break;
        case 'warning':
            toast.className = 'fixed top-4 left-1/2 transform -translate-x-1/2 z-50 flex';
            toast.firstElementChild.className = 'bg-yellow-500 text-white px-4 py-2 rounded-lg shadow-lg flex items-center gap-2 animate-fade-in';
            toastIcon.className = 'fa fa-exclamation-triangle text-xl';
            break;
        case 'info':
            toast.className = 'fixed top-4 left-1/2 transform -translate-x-1/2 z-50 flex';
            toast.firstElementChild.className = 'bg-blue-500 text-white px-4 py-2 rounded-lg shadow-lg flex items-center gap-2 animate-fade-in';
            toastIcon.className = 'fa fa-info-circle text-xl';
            break;
    }
    
    // 显示Toast
    toast.classList.remove('hidden');
    
    // 3秒后自动隐藏
    setTimeout(() => {
        toast.classList.add('hidden');
    }, 3000);
}
    </script>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen flex flex-col overflow-x-hidden m-0 p-0">
    <!-- 主要内容区 -->
    <main class="flex-grow flex flex-col md:flex-row transition-all duration-300 pt-6" id="main-container">
        <!-- 中间内容区 -->
        <div class="flex-grow transition-all duration-300 p-4 md:ml-64 lg:ml-72" id="content-area">
        <!-- 侧边栏 -->
        <?php include('common/asideleft.php'); ?>
            <div class="max-w-5xl mx-auto">
                <!-- 个人资料卡片 -->
                <div class="bg-white rounded-xl shadow-card overflow-hidden mb-6">
                    <!-- 封面图区域 - 增加渐变效果 -->
                    <div class="relative">
                        <div class="h-48 w-full" style="<?php echo $user_info['cover_url'] ? 'background-image: url(' . htmlspecialchars($user_info['cover_url']) . '); background-size: cover; background-position: center;' : 'background: linear-gradient(to right, #3b82f6, #60a5fa);'; ?>"></div>
                        <!-- 渐变遮罩层 -->
                        <div class="absolute inset-0 bg-gradient-to-t from-white via-white/10 to-transparent"></div>
                        <?php if ($is_own_profile): ?>
                        <button class="absolute top-3 right-3 w-10 h-10 rounded-full bg-white/30 backdrop-blur-sm flex items-center justify-center text-white hover:bg-white/50 transition-all shadow-md hover:scale-105" onclick="document.getElementById('cover-modal').classList.remove('hidden')">
                            <i class="fa fa-camera"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                    
                    <!-- 个人信息 - 提高不透明度 -->
                    <div class="p-5 relative bg-white/20 backdrop-blur-sm">
                        <div class="absolute -top-12 left-5">
                            <img src="<?php echo htmlspecialchars($user_info['avatar_url'] ?: 'static/images/default-avatar.png'); ?>" alt="用户头像" class="w-24 h-24 rounded-full border-4 border-white shadow-md">
                            <?php if ($is_own_profile): ?>
                            <button class="absolute bottom-0 right-0 w-8 h-8 rounded-full bg-primary flex items-center justify-center text-white text-xs hover:bg-dark transition-all shadow-md hover:scale-110" onclick="document.getElementById('avatar-modal').classList.remove('hidden')">
                                <i class="fa fa-camera"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                        
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mt-8 gap-4">
                            <div>
                                <h1 class="text-2xl font-bold"><?php echo htmlspecialchars($user_info['username']); ?></h1>
                                <p class="text-slate-500 text-sm">@<?php echo htmlspecialchars($user_info['username']); ?></p>
                            </div>
                            <?php if ($is_own_profile): ?>
                            <button class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-dark transition-all transform hover:scale-105 shadow-sm" onclick="document.getElementById('edit-modal').classList.remove('hidden')">
                                编辑资料
                            </button>
                            <?php else: ?>
                            <button id="follow-btn" 
                                data-action="<?php echo $is_following ? 'unfollow' : 'follow'; ?>" 
                                onclick="toggleFollow(<?php echo $profile_user_id; ?>)" 
                                class="<?php echo $is_following ? 'px-4 py-2 bg-slate-200 text-slate-700 rounded-lg hover:bg-slate-300 transition-all transform hover:scale-105 shadow-sm' : 'px-4 py-2 bg-primary text-white rounded-lg hover:bg-dark transition-all transform hover:scale-105 shadow-sm'; ?>">
                                <?php echo $is_following ? '已关注' : '关注'; ?>
                            </button>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($user_info['bio']): ?>
                        <p class="my-3 text-slate-700"><?php echo htmlspecialchars($user_info['bio']); ?></p>
                        <?php endif; ?>
                        
                        <div class="flex flex-wrap items-center gap-4 text-sm text-slate-500 mb-4">
                            <?php if ($user_info['last_login_at']): ?>
                            <span><i class="fa fa-clock-o mr-1"></i> 最后活跃: <?php echo time_ago($user_info['last_login_at']); ?></span>
                            <?php endif; ?>
                            <span><i class="fa fa-calendar mr-1"></i> 加入于 <?php echo date('Y年m月', strtotime($user_info['created_at'])); ?></span>
                        </div>
                        
                        <div class="flex flex-wrap items-center gap-6 border-t border-slate-100 pt-4">
                            <div class="text-center">
                                <p class="font-bold"><?php echo $user_info['topic_count']; ?></p>
                                <p class="text-sm text-slate-500">话题</p>
                            </div>
                            <div class="text-center">
                                <p class="font-bold"><?php echo $user_info['reply_count']; ?></p>
                                <p class="text-sm text-slate-500">回复</p>
                            </div>
                            <div class="text-center">
                                <p class="font-bold"><?php echo $user_info['like_count']; ?></p>
                                <p class="text-sm text-slate-500">获赞</p>
                            </div>
                            <div class="text-center">
                                <p class="font-bold" id="follower-count"><?php echo $user_info['follower_count']; ?></p>
                                <p class="text-sm text-slate-500">粉丝</p>
                            </div>
                            <div class="text-center">
                                <p class="font-bold"><?php echo $user_info['following_count']; ?></p>
                                <p class="text-sm text-slate-500">关注</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- 导航标签 -->
                <div class="bg-white rounded-xl shadow-card overflow-hidden mb-6">
                    <div class="flex border-b border-slate-100">
                        <a href="?id=<?php echo $profile_user_id; ?>&tab=topics" class="flex-1 py-3 text-center font-medium <?php echo $tab == 'topics' ? 'text-primary border-b-2 border-primary' : 'text-slate-500 hover:text-primary'; ?> transition-colors"><?php echo $prefix; ?>话题</a>
                        <a href="?id=<?php echo $profile_user_id; ?>&tab=replies" class="flex-1 py-3 text-center font-medium <?php echo $tab == 'replies' ? 'text-primary border-b-2 border-primary' : 'text-slate-500 hover:text-primary'; ?> transition-colors"><?php echo $prefix; ?>回复</a>
                        <a href="?id=<?php echo $profile_user_id; ?>&tab=likes" class="flex-1 py-3 text-center font-medium <?php echo $tab == 'likes' ? 'text-primary border-b-2 border-primary' : 'text-slate-500 hover:text-primary'; ?> transition-colors"><?php echo $prefix; ?>点赞</a>
                    </div>
                </div>
                
                <!-- 内容列表 -->
                <div class="space-y-4">
                    <?php if (empty($items)): ?>
                    <div class="bg-white rounded-xl shadow-card p-8 text-center">
                        <i class="fa fa-inbox text-4xl text-slate-300 mb-3"></i>
                        <p class="text-slate-500">暂无内容</p>
                    </div>
                    <?php else: ?>
                        <?php foreach ($items as $item): ?>
                            <?php if ($tab == 'topics' || $tab == 'likes'): ?>
                            <!-- 话题卡片 -->
                            <article class="post-card">
                                <div class="p-5 flex flex-col">
                                    <h4 class="font-bold text-lg mb-3 hover:text-primary transition-colors">
                                        <a href="topic.php?id=<?php echo $item['topic_id']; ?>"><?php echo htmlspecialchars($item['title']); ?></a>
                                    </h4>
                                    <p class="text-slate-600 mb-4 line-clamp-3"><?php echo mb_substr(strip_tags($item['content']), 0, 100, 'UTF-8'); ?>...</p>
                                    <div class="flex flex-wrap items-center justify-between mt-auto">
                                        <div class="flex items-center text-sm text-slate-500">
                                            <span class="mr-3"><?php echo htmlspecialchars($item['section_name']); ?></span>
                                            <span><i class="fa fa-clock-o mr-1"></i> <?php echo time_ago($item['created_at']); ?></span>
                                        </div>
                                        <div class="flex items-center text-sm text-slate-500 gap-3">
                                            <span><i class="fa fa-eye mr-1"></i> <?php echo $item['view_count']; ?></span>
                                            <span><i class="fa fa-comment mr-1"></i> <?php echo $item['reply_count']; ?></span>
                                            <span><i class="fa fa-heart mr-1"></i> <?php echo $item['like_count']; ?></span>
                                        </div>
                                    </div>
                                    <?php if ($is_own_profile && $tab == 'topics'): ?>
                                    <form method="post" class="mt-3 text-right">
                                        <input type="hidden" name="topic_id" value="<?php echo $item['topic_id']; ?>">
                                        <button type="submit" name="delete" value="1" class="text-sm text-red-500 hover:text-red-700" onclick="return confirm('确定要删除这个话题吗？');">
                                            <i class="fa fa-trash"></i> 删除
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </article>
                            <?php elseif ($tab == 'replies'): ?>
                            <!-- 回复卡片 -->
                            <article class="post-card">
                                <div class="p-5 flex flex-col">
                                    <h4 class="font-bold text-lg mb-3 hover:text-primary transition-colors">
                                        <a href="topic.php?id=<?php echo $item['topic_id']; ?>#reply-<?php echo $item['reply_id']; ?>">回复: <?php echo htmlspecialchars($item['topic_title']); ?></a>
                                    </h4>
                                    <p class="text-slate-600 mb-4 line-clamp-3"><?php echo mb_substr(strip_tags($item['content']), 0, 100, 'UTF-8'); ?>...</p>
                                    <div class="flex flex-wrap items-center justify-between mt-auto">
                                        <div class="flex items-center text-sm text-slate-500">
                                            <span><i class="fa fa-clock-o mr-1"></i> <?php echo time_ago($item['created_at']); ?></span>
                                        </div>
                                        <div class="flex items-center text-sm text-slate-500 gap-3">
                                            <span><i class="fa fa-heart mr-1"></i> <?php echo $item['like_count']; ?></span>
                                        </div>
                                    </div>
                                    <?php if ($is_own_profile): ?>
                                    <form method="post" class="mt-3 text-right">
                                        <input type="hidden" name="reply_id" value="<?php echo $item['reply_id']; ?>">
                                        <button type="submit" name="delete" value="1" class="text-sm text-red-500 hover:text-red-700" onclick="return confirm('确定要删除这条回复吗？');">
                                            <i class="fa fa-trash"></i> 删除
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </article>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- 加载状态指示器 -->
                <div id="loading-indicator" class="text-center mt-6 hidden">
                    <i class="fa fa-spinner fa-spin text-primary"></i>
                    <span class="ml-2 text-slate-600">加载中...</span>
                </div>
                <div id="end-message" class="text-center mt-6 mb-12 text-slate-500 hidden">
                    <i class="fa fa-info-circle mr-2"></i>到底啦，没有更多内容了
                </div>
                <div id="error-message" class="text-center mt-6 mb-12 text-red-500 hidden">
                    <i class="fa fa-exclamation-circle mr-2"></i>加载失败，点击此处重试
                </div>
            </div>
        </div>
        <!-- 右侧边栏 -->
        <?php include('common/asideright.php'); ?>
    </main>

<div id="edit-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-xl p-6 w-full max-w-md">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-bold">编辑个人资料</h3>
            <button class="text-slate-500 hover:text-slate-700" onclick="document.getElementById('edit-modal').classList.add('hidden')">
                <i class="fa fa-times"></i>
            </button>
        </div>
        <form method="post" enctype="multipart/form-data">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">用户名</label>
                    <input type="text" name="username" value="<?php echo htmlspecialchars($user_info['username']); ?>" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">个人简介</label>
                    <textarea name="bio" rows="3" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"><?php echo htmlspecialchars($user_info['bio'] ?? ''); ?></textarea>
                </div>
            </div>
            <div class="mt-6 flex justify-end space-x-3">
                <button type="button" class="px-4 py-2 bg-slate-200 text-slate-700 rounded-lg hover:bg-slate-300 transition-colors" onclick="document.getElementById('edit-modal').classList.add('hidden')">
                    取消
                </button>
                <button type="submit" name="edit_profile" value="1" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-dark transition-colors">
                    保存修改
                </button>
            </div>
        </form>
    </div>
</div>

<!-- 修改头像模态框 -->
<div id="avatar-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-xl p-6 w-full max-w-md">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-bold">修改头像</h3>
            <button class="text-slate-500 hover:text-slate-700" onclick="document.getElementById('avatar-modal').classList.add('hidden')">
                <i class="fa fa-times"></i>
            </button>
        </div>
        <form method="post" enctype="multipart/form-data">
            <div class="space-y-4">
                <div class="flex justify-center mb-3">
                    <img id="avatar_preview" src="<?php echo htmlspecialchars($user_info['avatar_url'] ?: 'static/images/default-avatar.png'); ?>" alt="当前头像" class="w-32 h-32 rounded-full border-4 border-white shadow-md">
                </div>
                <!-- 进度条 -->
                <div id="avatar_progress" class="hidden mb-3">
                    <div class="w-full bg-gray-200 rounded-full h-2.5">
                        <div class="progress-bar bg-primary h-2.5 rounded-full transition-all duration-300" style="width: 0%"></div>
                    </div>
                    <div class="flex justify-between text-xs text-slate-500 mt-1">
                        <span class="progress-text">0%</span>
                        <span>上传中...</span>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">上传新头像</label>
                    <!-- 美化上传按钮 -->
                    <label class="w-full flex items-center justify-center px-3 py-3 border-2 border-dashed border-slate-300 rounded-lg cursor-pointer bg-slate-50 hover:bg-slate-100 transition-colors">
                        <div class="flex flex-col items-center justify-center pt-5 pb-6">
                            <i class="fa fa-cloud-upload text-2xl text-slate-400 mb-2"></i>
                            <p class="mb-1 text-sm text-slate-600"><span class="font-semibold">点击上传</span> 或拖放文件</p>
                            <p class="text-xs text-slate-500">支持 PNG, JPG 或 GIF</p>
                        </div>
                        <input type="file" id="avatar_file" name="avatar_file" accept="image/jpeg,image/png,image/gif" class="hidden" />
                    </label>
                    <p class="text-xs text-slate-500 mt-1">或输入URL:</p>
                    <input type="text" name="avatar_url" value="<?php echo htmlspecialchars($user_info['avatar_url'] ?? ''); ?>" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                </div>
            </div>
            <div class="mt-6 flex justify-end space-x-3">
                <button type="button" class="px-4 py-2 bg-slate-200 text-slate-700 rounded-lg hover:bg-slate-300 transition-colors" onclick="document.getElementById('avatar-modal').classList.add('hidden')">
                    取消
                </button>
                <button type="submit" name="edit_profile" value="1" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-dark transition-colors">
                    保存修改
                </button>
            </div>
        </form>
    </div>
</div>

<!-- 修改背景图模态框 -->
<div id="cover-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-xl p-6 w-full max-w-md">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-bold">修改背景图</h3>
            <button class="text-slate-500 hover:text-slate-700" onclick="document.getElementById('cover-modal').classList.add('hidden')">
                <i class="fa fa-times"></i>
            </button>
        </div>
        <form method="post" enctype="multipart/form-data">
            <div class="space-y-4">
                <div class="h-24 bg-gradient-to-r from-primary to-blue-600 rounded-lg overflow-hidden shadow-sm mb-3" id="cover_preview" style="<?php echo $user_info['cover_url'] ? 'background-image: url(' . htmlspecialchars($user_info['cover_url']) . '); background-size: cover; background-position: center;' : ''; ?>">
                </div>
                <!-- 进度条 -->
                <div id="cover_progress" class="hidden mb-3">
                    <div class="w-full bg-gray-200 rounded-full h-2.5">
                        <div class="progress-bar bg-primary h-2.5 rounded-full transition-all duration-300" style="width: 0%"></div>
                    </div>
                    <div class="flex justify-between text-xs text-slate-500 mt-1">
                        <span class="progress-text">0%</span>
                        <span>上传中...</span>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">上传新背景图</label>
                    <!-- 美化上传按钮 -->
                    <label class="w-full flex items-center justify-center px-3 py-3 border-2 border-dashed border-slate-300 rounded-lg cursor-pointer bg-slate-50 hover:bg-slate-100 transition-colors">
                        <div class="flex flex-col items-center justify-center pt-5 pb-6">
                            <i class="fa fa-cloud-upload text-2xl text-slate-400 mb-2"></i>
                            <p class="mb-1 text-sm text-slate-600"><span class="font-semibold">点击上传</span> 或拖放文件</p>
                            <p class="text-xs text-slate-500">支持 PNG, JPG 或 GIF</p>
                        </div>
                        <input type="file" id="cover_file" name="cover_file" accept="image/jpeg,image/png,image/gif" class="hidden" />
                    </label>
                    <p class="text-xs text-slate-500 mt-1">或输入URL:</p>
                    <input type="text" name="cover_url" value="<?php echo htmlspecialchars($user_info['cover_url'] ?? ''); ?>" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                </div>
            </div>
            <div class="mt-6 flex justify-end space-x-3">
                <button type="button" class="px-4 py-2 bg-slate-200 text-slate-700 rounded-lg hover:bg-slate-300 transition-colors" onclick="document.getElementById('cover-modal').classList.add('hidden')">
                    取消
                </button>
                <button type="submit" name="edit_profile" value="1" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-dark transition-colors">
                    保存修改
                </button>
            </div>
        </form>
    </div>
</div>
<style>
    .animate-fade-in {
        animation: fadeIn 0.3s ease-in-out;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .progress-bar {
        transition: width 0.3s ease;
    }
</style>

<div id="toast-notification" class="fixed top-4 left-1/2 transform -translate-x-1/2 z-50 hidden">
    <div class="bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg flex items-center gap-2 animate-fade-in">
        <i class="fa fa-check-circle text-xl"></i>
        <span id="toast-message" class="text-base font-medium"></span>
    </div>
</div>

<?php include('common/footer.php'); ?>
