<?php
    require_once 'common/functions.php';
    require_once 'api/deletetopic.php';
    $topic_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($topic_id <= 0) {
        header('Location: index.php');
        exit;
    }

    $topic = get_topic($topic_id);
    if (!$topic) {
        header('Location: index.php');
        exit;
    }

    increment_topic_views($topic_id);

    $tags = get_topic_tags($topic_id);

    $sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
    $valid_sorts = ['newest', 'oldest', 'popular'];
    if (!in_array($sort, $valid_sorts)) {
        $sort = 'newest';
    }

    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $replies = get_topic_replies($topic_id, $page, $sort);
    $reply_count = get_topic_reply_count($topic_id);

    $hot_topics = get_hot_topics(5);

    $is_logged_in = isset($_SESSION['user_id']);
    $user_id = $is_logged_in ? $_SESSION['user_id'] : 0;
    $current_user = $is_logged_in ? get_logged_in_user() : null;

    $has_liked = $is_logged_in ? has_user_liked($user_id, 'topic', $topic_id) : false;

    include('common/header.php');
    ?>
    <style>
#success-toast {
    transition: all 0.3s ease;
    opacity: 0;
    transform: translate(-50%, -20px);
}

#success-toast.show {
    opacity: 1;
    transform: translate(-50%, 0);
}

.markdown-content h1, .markdown-content h2, .markdown-content h3,
.markdown-content h4, .markdown-content h5, .markdown-content h6 {
    margin-top: 1.5rem;
    margin-bottom: 1rem;
    font-weight: 600;
}

.markdown-content p {
    margin-bottom: 1rem;
}

.markdown-content ul, .markdown-content ol {
    margin-bottom: 1rem;
    padding-left: 2rem;
}

.markdown-content ul {
    list-style-type: disc;
}

.markdown-content ol {
    list-style-type: decimal;
}

.markdown-content pre {
    background-color: #f7f7f7;
    padding: 1rem;
    border-radius: 0.5rem;
    overflow-x: auto;
    margin-bottom: 1rem;
}

.markdown-content code {
    background-color: #f7f7f7;
    padding: 0.2rem 0.4rem;
    border-radius: 0.25rem;
    font-family: monospace;
}

.markdown-content blockquote {
    border-left: 4px solid #e2e8f0;
    padding-left: 1rem;
    margin-left: 0;
    margin-bottom: 1rem;
    color: #64748b;
}

.markdown-content img {
    max-width: 100%;
    height: auto;
    border-radius: 0.5rem;
    margin: 1rem 0;
}

.markdown-content a {
    color: #3b82f6;
    text-decoration: underline;
}

.markdown-content table {
    border-collapse: collapse;
    width: 100%;
    margin-bottom: 1rem;
}

.markdown-content th, .markdown-content td {
    border: 1px solid #e2e8f0;
    padding: 0.5rem 1rem;
}

.markdown-content th {
    background-color: #f1f5f9;
}
    </style>
        <title><?= htmlspecialchars($topic['title']) ?> - <?= htmlspecialchars($site_info['site_title']) ?></title>
    <meta name="keywords" content="<?= htmlspecialchars($site_info['site_title']) ?>, <?= htmlspecialchars($topic['title']) ?>, 文章, 博客, 论坛">
    <meta name="description" content="快来浏览<?= htmlspecialchars($topic['title']) ?>，好东西统统都在<?= htmlspecialchars($site_info['site_title']) ?>，在这里拥抱更广阔的星空~">
        <link rel="stylesheet" href="https://scdn.星.fun/npm/easymde@2.18.0/dist/easymde.min.css">
        <link rel="stylesheet" href="https://scdn.星.fun/npm/font-awesome@4.7.0/css/font-awesome.min.css">
    </head>
    <body class="bg-slate-50 text-slate-800 min-h-screen flex flex-col overflow-x-hidden m-0 p-0">
        <div id="error-container" class="fixed top-4 left-1/2 transform -translate-x-1/2 z-50 max-w-2xl w-full bg-red-50 border border-red-200 text-red-700 p-4 rounded-lg shadow-lg hidden">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <i class="fa fa-exclamation-circle text-xl"></i>
                </div>
                <div class="ml-3 flex-1">
                    <h3 class="text-sm font-medium">操作失败</h3>
                    <div class="mt-2 text-sm" id="error-details">
                    </div>
                    <div class="mt-4">
                        <button onclick="document.getElementById('error-container').classList.add('hidden')" class="inline-flex items-center px-3 py-1.5 border border-red-300 rounded-md text-sm font-medium text-red-700 bg-white hover:bg-red-50">
                            关闭
                        </button>
                    </div>
                </div>
            </div>
        </div>

<div id="success-toast" class="fixed top-4 left-1/2 transform -translate-x-1/2 z-50 hidden">
    <div class="bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg flex items-center gap-2">
        <i class="fa fa-check-circle text-xl"></i>
        <span id="success-message" class="text-base font-medium"></span>
    </div>
</div>

        <main class="flex-grow flex flex-col md:flex-row transition-all duration-300 pt-6" id="main-container">
            <?php include('common/asideleft.php'); ?>

            <div class="flex-grow transition-all duration-300 p-4 md:ml-64 lg:ml-72" id="content-area">
                <div class="max-w-5xl mx-auto">
                    <div class="bg-white rounded-xl shadow-card p-5 mb-6">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <span class="inline-block px-3 py-1 bg-primary/10 text-primary text-sm rounded-full mb-2"><?= htmlspecialchars($topic['section_name']) ?></span>
                                <h1 class="text-2xl font-bold"><?= htmlspecialchars($topic['title']) ?></h1>
                            </div>
                            <div class="flex gap-2">
                                <button class="sidebar-toggle-btn">
                                    <i class="fa fa-bookmark-o"></i>
                                </button>
                                <button class="sidebar-toggle-btn">
                                    <i class="fa fa-share-alt"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="flex items-center justify-between border-b border-slate-100 pb-4 mb-4">
                            <div class="flex items-center gap-3">
                                <a href="profile.php?id=<?= $topic['user_id'] ?>">
                                    <img src="<?= htmlspecialchars($topic['avatar_url'] ?? 'static/images/default-avatar.png') ?>" alt="作者头像" class="w-10 h-10 rounded-full">
                                </a>
                                <div>
                                    <a href="profile.php?id=<?= $topic['user_id'] ?>" class="font-medium hover:text-primary transition-colors">
                                        <?= htmlspecialchars($topic['username']) ?>
                                    </a>
                                    <p class="text-sm text-slate-500">
                                        发布于 <?= time_ago($topic['created_at']) ?>
                                        <?php if ($topic['updated_at'] != $topic['created_at']): ?>
                                        · 最后编辑于 <?= time_ago($topic['updated_at']) ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
<div class="flex items-center gap-2">
    <?php if ($is_logged_in && $current_user['user_id'] != $topic['user_id']): ?>
        <button id="follow-btn" class="px-3 py-1.5 text-sm bg-primary/10 text-primary rounded-lg hover:bg-primary/20 transition-colors" 
                data-user-id="<?= $topic['user_id'] ?>">
            <i class="fa fa-user-plus mr-1"></i>
            <span>关注</span>
        </button>
    <?php elseif ($is_logged_in && $current_user['user_id'] == $topic['user_id']): ?>
        <a href="profile.php?id=<?= $current_user['user_id'] ?>" class="px-3 py-1.5 text-sm bg-slate-100 text-slate-500 rounded-lg hover:bg-slate-200 transition-colors">
            <i class="fa fa-user mr-1"></i>
            <span>个人主页</span>
        </a>
    <?php else: ?>
        <button class="px-3 py-1.5 text-sm bg-primary/10 text-primary rounded-lg hover:bg-primary/20 transition-colors" 
                onclick="window.location.href='login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>'">
            <i class="fa fa-user-plus mr-1"></i>
            <span>关注</span>
        </button>
    <?php endif; ?>
</div>
                        </div>
                        
                        <div class="markdown-content prose max-w-none mb-6" id="topic-content">
                            <textarea id="topic-markdown" class="hidden"><?= htmlspecialchars($topic['content']) ?></textarea>
                        </div>
                        
                        <?php if (!empty($tags)): ?>
                        <div class="flex flex-wrap gap-2 mb-4">
                            <?php foreach ($tags as $tag): ?>
                            <a href="tag.php?id=<?= $tag['tag_id'] ?>" class="px-2 py-1 bg-slate-100 text-slate-600 text-sm rounded hover:bg-primary/10 hover:text-primary transition-colors">
                                <?= htmlspecialchars($tag['name']) ?>
                            </a>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="flex items-center justify-between border-t border-slate-100 pt-4">
                            <div class="flex items-center gap-4">
                                <button class="flex items-center gap-1 <?= $has_liked ? 'text-primary' : 'text-slate-500' ?> hover:text-primary transition-colors" 
                                        id="like-btn" data-topic-id="<?= $topic_id ?>">
                                    <i class="fa <?= $has_liked ? 'fa-thumbs-up' : 'fa-thumbs-o-up' ?>"></i>
                                    <span id="like-count"><?= $topic['like_count'] ?></span>
                                </button>
                                <button class="flex items-center gap-1 text-slate-500 hover:text-primary transition-colors">
                                    <i class="fa fa-comment-o"></i>
                                    <span><?= $reply_count ?></span>
                                </button>
                                <button class="flex items-center gap-1 text-slate-500 hover:text-primary transition-colors">
                                    <i class="fa fa-eye"></i>
                                    <span><?= $topic['view_count'] ?></span>
                                </button>
                            </div>
                            <div class="flex items-center gap-2">
                                <a href="#reply-form" class="px-3 py-1.5 text-sm bg-primary/10 text-primary rounded-lg hover:bg-primary/20 transition-colors">
                                    <i class="fa fa-reply mr-1"></i>回复
                                </a>
                                <div class="relative inline-block">
                                    <button id="topic-actions-btn" class="px-3 py-1.5 text-sm bg-white border border-slate-200 rounded-lg hover:border-primary hover:text-primary transition-colors focus:outline-none focus:ring-2 focus:ring-primary/20">
                                        <i class="fa fa-ellipsis-h"></i>
                                    </button>
                                    
                                    <div id="topic-actions-menu" class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-slate-100 z-50 hidden opacity-0 transform translate-y-2 transition-all duration-200">
                                        <ul class="py-1">
                                            <li>
                                                <a href="#" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-100 flex items-center gap-2" id="action-like">
                                                    <i class="fa <?= $has_liked ? 'fa-thumbs-up text-primary' : 'fa-thumbs-o-up text-slate-500' ?>"></i>
                                                    <span><?= $has_liked ? '取消点赞' : '点赞文章' ?></span>
                                                </a>
                                            </li>
                                            
                                            <li>
                                                <a href="#" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-100 flex items-center gap-2" id="action-copy-link">
                                                    <i class="fa fa-link text-slate-500"></i>
                                                    <span>复制链接</span>
                                                </a>
                                            </li>
                                            
                                            <li>
                                                <a href="report.php?id=<?= $topic_id ?>&type=topic" 
                                                   class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50 flex items-center gap-2" id="action-report">
                                                    <i class="fa fa-flag-o"></i>
                                                    <span>举报文章</span>
                                                </a>
                                            </li>
                                            
                                            <?php if ($is_logged_in && $current_user['user_id'] == $topic['user_id']): ?>
                                            <li class="border-t border-slate-100 my-1"></li>
                                            <li>
                                                <a href="edit_topic.php?id=<?= $topic_id ?>" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-100 flex items-center gap-2">
                                                    <i class="fa fa-pencil text-slate-500"></i>
                                                    <span>编辑文章</span>
                                                </a>
                                            </li>
                                            <?php endif; ?>
                                            
                                            <?php if ($is_logged_in && can_delete_topic($current_user['user_id'], $topic_id)): ?>
                                            <li>
                                                <a href="#" class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50 flex items-center gap-2" id="action-delete">
                                                    <i class="fa fa-trash-o"></i>
                                                    <span>删除文章</span>
                                                </a>
                                            </li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-card p-5 mb-6">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-lg font-bold">全部回复 (<?= $reply_count ?>)</h2>
                            <div class="flex items-center gap-2">
                                <button class="sort-btn px-3 py-1.5 text-sm rounded-lg <?= $sort === 'newest' ? 'bg-primary text-white' : 'bg-white border border-slate-200 hover:border-primary hover:text-primary transition-colors' ?>" data-sort="newest">最新</button>
                                <button class="sort-btn px-3 py-1.5 text-sm rounded-lg <?= $sort === 'oldest' ? 'bg-primary text-white' : 'bg-white border border-slate-200 hover:border-primary hover:text-primary transition-colors' ?>" data-sort="oldest">最早</button>
                                <button class="sort-btn px-3 py-1.5 text-sm rounded-lg <?= $sort === 'popular' ? 'bg-primary text-white' : 'bg-white border border-slate-200 hover:border-primary hover:text-primary transition-colors' ?>" data-sort="popular">热门</button>
                            </div>
                        </div>
                        
                        <div id="replies-container">
                            <?php if (!empty($replies)): ?>
                                <?php foreach ($replies as $reply): ?>
                                <div class="border-b border-slate-100 pb-4 mb-4" id="reply-<?= $reply['reply_id'] ?>">
                                    <div class="flex items-start gap-3 mb-3">
                                        <a href="profile.php?id=<?= $reply['user_id'] ?>">
                                            <img src="<?= htmlspecialchars($reply['avatar_url'] ?? 'static/images/default-avatar.png') ?>" alt="用户头像" class="w-8 h-8 rounded-full mt-1">
                                        </a>
                                        <div class="flex-grow">
                                            <div class="flex items-center justify-between mb-1">
                                                <a href="profile.php?id=<?= $reply['user_id'] ?>" class="font-medium hover:text-primary transition-colors">
                                                    <?= htmlspecialchars($reply['username']) ?>
                                                </a>
                                                <span class="text-xs text-slate-500"><?= time_ago($reply['created_at']) ?></span>
                                            </div>
                                            <div class="markdown-content text-slate-700">
                                                <textarea class="reply-markdown hidden"><?= htmlspecialchars($reply['content']) ?></textarea>
                                            </div>
                                            
                                            <div class="flex items-center gap-4 mt-3">
                                                <button class="flex items-center gap-1 text-slate-500 hover:text-primary transition-colors text-sm reply-like-btn" 
                                                        data-reply-id="<?= $reply['reply_id'] ?>">
                                                    <i class="fa fa-thumbs-o-up"></i>
                                                    <span class="like-count"><?= $reply['like_count'] ?></span>
                                                </button>
                                                <button class="flex items-center gap-1 text-slate-500 hover:text-primary transition-colors text-sm reply-reply-btn"
                                                        data-reply-id="<?= $reply['reply_id'] ?>"
                                                        data-username="<?= htmlspecialchars($reply['username']) ?>">
                                                    <i class="fa fa-reply"></i>
                                                    <span>回复</span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center py-8 text-slate-500">
                                    <i class="fa fa-comment-o text-4xl mb-3"></i>
                                    <p>还没有回复，快来抢沙发吧！</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($reply_count > count($replies)): ?>
                        <div class="text-center">
                            <button id="load-more-replies" class="btn-primary inline-flex items-center gap-2" data-page="<?= $page ?>" data-topic-id="<?= $topic_id ?>" data-sort="<?= $sort ?>">
                                <span>加载更多回复</span>
                                <i class="fa fa-chevron-down"></i>
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-card p-5" id="reply-form">
                        <h3 class="text-lg font-bold mb-4">发表回复</h3>
                        <?php if ($is_logged_in): ?>
                        <form id="new-reply-form">
                            <input type="hidden" name="topic_id" value="<?= $topic_id ?>">
                            <input type="hidden" name="reply_to" id="reply-to" value="0">
                            <div class="mb-4">
                                <textarea name="content" id="reply-content" class="w-full px-4 py-3 rounded-lg border border-slate-300 focus:border-primary focus:ring-2 focus:ring-primary/50 transition-all min-h-[120px]" placeholder="写下你的回复（支持Markdown格式）..."></textarea>
                                <div id="reply-error" class="text-red-500 text-sm mt-1 hidden"></div>
                            </div>
                            <div class="flex items-center justify-between">
                                <button type="submit" class="btn-primary px-6">
                                    <i class="fa fa-paper-plane mr-1"></i> 发表
                                </button>
                            </div>
                        </form>
                        <?php else: ?>
                        <div class="text-center py-8">
                            <p class="text-slate-500 mb-4">请登录后发表回复</p>
                            <div class="flex gap-4 justify-center">
                                <a href="login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="btn-primary">登录</a>
                                <a href="register.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="px-6 py-2 border border-primary text-primary rounded-lg font-medium hover:bg-primary/5 transition-colors">注册</a>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php include('common/asideright.php'); ?>
        </main>
        
        <script src="https://scdn.星.fun/npm/marked/marked.min.js"></script>
        <script src="https://scdn.星.fun/npm/easymde@2.18.0/dist/easymde.min.js"></script>
        <script>
        let easyMDE;
        if (document.getElementById('reply-content') && <?= $is_logged_in ? 'true' : 'false' ?>) {
            easyMDE = new EasyMDE({
                element: document.getElementById('reply-content'),
                spellChecker: false,
                toolbar: [
                    'bold', 'italic', 'strikethrough', '|',
                    'heading-1', 'heading-2', 'heading-3', '|',
                    'code', 'quote', 'unordered-list', 'ordered-list', '|',
                    'link', 'image', '|',
                    'preview', 'side-by-side', 'fullscreen', '|',
                    {
                        name: 'guide',
                        action: function customFunction(editor){
                            alert('Markdown语法指南：\n\n**粗体** - 粗体文本\n*斜体* - 斜体文本\n# 标题\n- 列表项\n[链接文本](链接地址)\n![图片描述](图片地址)');
                        },
                        className: 'fa fa-question-circle',
                        title: 'Markdown语法指南',
                    }
                ],
                status: false,
                placeholder: "写下你的回复（支持Markdown格式）..."
            });
        }
        
function renderMarkdown() {
    const topicMarkdownElement = document.getElementById('topic-markdown');
    if (topicMarkdownElement) {
        const topicMarkdown = topicMarkdownElement.value;
        const topicContentElement = document.getElementById('topic-content');
        if (topicContentElement) {
            topicContentElement.innerHTML = marked.parse(topicMarkdown);
        }
    }
    
    document.querySelectorAll('.reply-markdown').forEach(textarea => {
        if (textarea && textarea.value) {
            const markdown = textarea.value;
            const container = textarea.parentElement;
            if (container) {
                container.innerHTML = marked.parse(markdown);
            }
        }
    });
}
        
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(() => {
        try {
            renderMarkdown();
        } catch (e) {
            console.error('初始化渲染Markdown时出错:', e);
        }
    }, 100);
});
        
        function showError(message, details = '') {
            const errorContainer = document.getElementById('error-container');
            const errorDetails = document.getElementById('error-details');
            
            if (details) {
                errorDetails.innerHTML = `<p>错误详情:</p><pre class="bg-red-100 p-2 rounded text-xs mt-1 overflow-x-auto">${escapeHtml(details)}</pre>`;
            } else {
                errorDetails.textContent = message;
            }
            
            errorContainer.classList.remove('hidden');
            
            setTimeout(() => {
                errorContainer.classList.add('hidden');
            }, 10000);
        }
        
function showSuccess(message) {
    const toast = document.getElementById('success-toast');
    const messageElement = document.getElementById('success-message');
    
    messageElement.textContent = message;
    toast.classList.remove('hidden');
    setTimeout(() => toast.classList.add('show'), 10);
    
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.classList.add('hidden'), 300);
    }, 3000);
}
        
        function escapeHtml(unsafe) {
            return unsafe
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
        
        document.getElementById('topic-actions-btn').addEventListener('click', function(e) {
            e.stopPropagation();
            const menu = document.getElementById('topic-actions-menu');
            const isHidden = menu.classList.contains('hidden');
            
            if (isHidden) {
                menu.classList.remove('hidden', 'opacity-0', 'translate-y-2');
            } else {
                menu.classList.add('opacity-0', 'translate-y-2');
                setTimeout(() => {
                    menu.classList.add('hidden');
                }, 200);
            }
        });

        document.addEventListener('click', function() {
            const menu = document.getElementById('topic-actions-menu');
            if (!menu.classList.contains('hidden')) {
                menu.classList.add('opacity-0', 'translate-y-2');
                setTimeout(() => {
                    menu.classList.add('hidden');
                }, 200);
            }
        });

        document.getElementById('topic-actions-menu').addEventListener('click', function(e) {
            e.stopPropagation();
        });

        document.getElementById('like-btn').addEventListener('click', function() {
            const topicId = this.getAttribute('data-topic-id');
            const likeIcon = this.querySelector('i');
            const likeCount = document.getElementById('like-count');
            
            fetch('/api/topic.php?action=like_topic&topic_id=' + topicId, {
                credentials: 'same-origin'
            })
            .then(response => {
                if (!response.ok) {
                    return response.text().then(text => {
                        throw new Error(`HTTP错误: ${response.status} - ${text}`);
                    });
                }
                return response.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        throw new Error(`JSON解析错误: ${e.message}\n服务器返回内容: ${text}`);
                    }
                });
            })
            .then(data => {
                if (data.success) {
                    likeCount.textContent = data.like_count;
                    if (likeIcon.classList.contains('fa-thumbs-o-up')) {
                        likeIcon.classList.remove('fa-thumbs-o-up');
                        likeIcon.classList.add('fa-thumbs-up');
                        this.classList.add('text-primary');
                        showSuccess('点赞成功');
                    } else {
                        likeIcon.classList.remove('fa-thumbs-up');
                        likeIcon.classList.add('fa-thumbs-o-up');
                        this.classList.remove('text-primary');
                        showSuccess('取消点赞成功');
                    }
                    
                    const actionLike = document.getElementById('action-like');
                    if (actionLike) {
                        const actionLikeIcon = actionLike.querySelector('i');
                        const actionLikeText = actionLike.querySelector('span');
                        
                        if (likeIcon.classList.contains('fa-thumbs-up')) {
                            actionLikeIcon.classList.remove('fa-thumbs-o-up', 'text-slate-500');
                            actionLikeIcon.classList.add('fa-thumbs-up', 'text-primary');
                            actionLikeText.textContent = '取消点赞';
                        } else {
                            actionLikeIcon.classList.remove('fa-thumbs-up', 'text-primary');
                            actionLikeIcon.classList.add('fa-thumbs-o-up', 'text-slate-500');
                            actionLikeText.textContent = '点赞文章';
                        }
                    }
                } else {
                    showError(data.message);
                }
            })
            .catch(error => {
                console.error('点赞错误:', error);
                showError('点赞操作失败', error.message);
            });
        });

        document.getElementById('action-like')?.addEventListener('click', function(e) {
            e.preventDefault();
            document.getElementById('like-btn').click();
            
            const menu = document.getElementById('topic-actions-menu');
            menu.classList.add('opacity-0', 'translate-y-2');
            setTimeout(() => {
                menu.classList.add('hidden');
            }, 200);
        });

        document.getElementById('action-copy-link')?.addEventListener('click', function(e) {
            e.preventDefault();
            const currentUrl = window.location.href;
            
            navigator.clipboard.writeText(currentUrl)
                .then(() => {
                    showSuccess('链接已复制到剪贴板');
                })
                .catch(err => {
                    const tempInput = document.createElement('input');
                    document.body.appendChild(tempInput);
                    tempInput.value = currentUrl;
                    tempInput.select();
                    document.execCommand('copy');
                    document.body.removeChild(tempInput);
                    showSuccess('链接已复制到剪贴板');
                });
            
            const menu = document.getElementById('topic-actions-menu');
            menu.classList.add('opacity-0', 'translate-y-2');
            setTimeout(() => {
                menu.classList.add('hidden');
            }, 200);
        });

        document.getElementById('action-delete')?.addEventListener('click', function(e) {
            e.preventDefault();
            
            if (!confirm('确定要删除这篇文章吗？此操作不可恢复！')) {
                return;
            }
            
            const topicId = <?= $topic_id ?>;
            const currentUserRole = '<?= $current_user['role'] ?? 'user' ?>';
            let reason = '';
            
            if (currentUserRole === 'admin' || currentUserRole === 'moderator') {
                reason = prompt('请输入删除原因（管理员操作必须填写原因）：');
                if (reason === null) return;
                if (!reason.trim()) {
                    showError('必须填写删除原因');
                    return;
                }
            }
            
            fetch('/api/topic.php?action=delete_topic', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `topic_id=${topicId}&reason=${encodeURIComponent(reason)}`,
                credentials: 'same-origin'
            })
            .then(response => {
                if (!response.ok) {
                    return response.text().then(text => {
                        throw new Error(`HTTP错误: ${response.status} - ${text}`);
                    });
                }
                return response.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        throw new Error(`JSON解析错误: ${e.message}\n服务器返回内容: ${text}`);
                    }
                });
            })
            .then(data => {
                if (data.success) {
                    showSuccess('文章已删除');
                    setTimeout(() => {
                        window.location.href = 'index.php';
                    }, 1500);
                } else {
                    showError(data.message);
                }
            })
            .catch(error => {
                console.error('删除错误:', error);
                showError('删除操作失败', error.message);
            });
            
            const menu = document.getElementById('topic-actions-menu');
            menu.classList.add('opacity-0', 'translate-y-2');
            setTimeout(() => {
                menu.classList.add('hidden');
            }, 200);
        });

        document.querySelectorAll('.reply-like-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const replyId = this.getAttribute('data-reply-id');
                const likeIcon = this.querySelector('i');
                const likeCount = this.querySelector('.like-count');
                
                fetch('/api/reply.php?action=like_reply&reply_id=' + replyId, {
                    credentials: 'same-origin'
                })
            .then(response => {
                if (!response.ok) {
                    return response.text().then(text => {
                        throw new Error(`HTTP错误: ${response.status} - ${text}`);
                    });
                }
                return response.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        throw new Error(`JSON解析错误: ${e.message}\n服务器返回内容: ${text}`);
                    }
                });
            })
            .then(data => {
                if (data.success) {
                    likeCount.textContent = data.like_count;
                    if (likeIcon.classList.contains('fa-thumbs-o-up')) {
                        likeIcon.classList.remove('fa-thumbs-o-up');
                        likeIcon.classList.add('fa-thumbs-up');
                        this.classList.add('text-primary');
                        showSuccess('点赞成功');
                    } else {
                        likeIcon.classList.remove('fa-thumbs-up');
                        likeIcon.classList.add('fa-thumbs-o-up');
                        this.classList.remove('text-primary');
                        showSuccess('取消点赞成功');
                    }
                } else {
                    showError(data.message);
                }
            })
            .catch(error => {
                console.error('回复点赞错误:', error);
                showError('回复点赞操作失败', error.message);
            });
        });
    });
    
    document.getElementById('replies-container').addEventListener('click', function(e) {
        const replyBtn = e.target.closest('.reply-reply-btn');
        if (replyBtn) {
            const replyId = replyBtn.getAttribute('data-reply-id');
            const username = replyBtn.getAttribute('data-username');
            const replyTo = document.getElementById('reply-to');
            
            replyTo.value = replyId;
            
            if (easyMDE) {
                const currentValue = easyMDE.value();
                easyMDE.value(`@${username} ` + currentValue);
                easyMDE.codemirror.focus();
            } else {
                window.location.href = 'login.php?redirect=' + encodeURIComponent(window.location.href);
            }
            
            document.getElementById('reply-form').scrollIntoView({ behavior: 'smooth' });
        }
    });
    
document.getElementById('new-reply-form')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    let content;
    if (easyMDE && typeof easyMDE.value === 'function') {
        content = easyMDE.value().trim();
    } else {
        const textarea = document.getElementById('reply-content');
        content = textarea ? textarea.value.trim() : '';
    }
    
    const errorElement = document.getElementById('reply-error');
    
    if (!content) {
        errorElement.textContent = '回复内容不能为空';
        errorElement.classList.remove('hidden');
        return;
    }
    
    if (content.length > 10000) {
        errorElement.textContent = '回复内容过长，最多10000个字符';
        errorElement.classList.remove('hidden');
        return;
    }
    
    errorElement.classList.add('hidden');
    
    const formData = new FormData();
    formData.append('topic_id', <?= $topic_id ?>);
    formData.append('reply_to', document.getElementById('reply-to').value);
    formData.append('content', content);
    
    fetch('/api/reply.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => {
        if (!response.ok) {
            return response.text().then(text => {
                throw new Error(`HTTP错误: ${response.status} - ${text}`);
            });
        }
        return response.text().then(text => {
            try {
                return JSON.parse(text);
            } catch (e) {
                throw new Error(`JSON解析错误: ${e.message}\n服务器返回内容: ${text}`);
            }
        });
    })
    .then(data => {
        if (data.success) {
            if (easyMDE && typeof easyMDE.value === 'function') {
                easyMDE.value('');
            } else {
                const textarea = document.getElementById('reply-content');
                if (textarea) textarea.value = '';
            }
            document.getElementById('reply-to').value = '0';
            showSuccess('回复发表成功');
            
            const currentUser = <?= json_encode($current_user) ?>;
            
            const repliesContainer = document.getElementById('replies-container');
            const newReply = document.createElement('div');
            newReply.className = 'border-b border-slate-100 pb-4 mb-4';
            newReply.innerHTML = `
                <div class="flex items-start gap-3 mb-3">
                    <a href="profile.php?id=${currentUser.user_id}">
                        <img src="${currentUser.avatar_url ? currentUser.avatar_url : 'static/images/default-avatar.png'}" alt="用户头像" class="w-8 h-8 rounded-full mt-1">
                    </a>
                    <div class="flex-grow">
                        <div class="flex items-center justify-between mb-1">
                            <a href="profile.php?id=${currentUser.user_id}" class="font-medium hover:text-primary transition-colors">
                                ${currentUser.username}
                            </a>
                            <span class="text-xs text-slate-500">刚刚</span>
                        </div>
                        <div class="markdown-content text-slate-700">
                            <textarea class="reply-markdown hidden">${escapeHtml(content)}</textarea>
                        </div>
                        
                        <div class="flex items-center gap-4 mt-3">
                            <button class="flex items-center gap-1 text-slate-500 hover:text-primary transition-colors text-sm reply-like-btn" 
                                    data-reply-id="${data.reply_id}">
                                <i class="fa fa-thumbs-o-up"></i>
                                <span class="like-count">0</span>
                            </button>
                            <button class="flex items-center gap-1 text-slate-500 hover:text-primary transition-colors text-sm reply-reply-btn"
                                    data-reply-id="${data.reply_id}"
                                    data-username="${currentUser.username}">
                                <i class="fa fa-reply"></i>
                                <span>回复</span>
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
        
        setTimeout(() => {
            try {
                renderMarkdown();
            } catch (e) {
                console.error('渲染新回复Markdown时出错:', e);
            }
        }, 100);
            
            const currentSort = document.querySelector('.sort-btn.bg-primary')?.getAttribute('data-sort') || 'newest';
            if (currentSort === 'newest') {
                repliesContainer.insertBefore(newReply, repliesContainer.firstChild);
            }
            
            renderMarkdown();
            
            const replyCountElement = document.querySelector('.fa-comment-o')?.nextElementSibling;
            if (replyCountElement) {
                replyCountElement.textContent = parseInt(replyCountElement.textContent) + 1;
            }
            
        } else {
            showError(data.message);
        }
    })
    .catch(error => {
        console.error('提交回复错误:', error);
        showError('提交回复失败', error.message);
    });
});
    
    document.querySelectorAll('.sort-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const sortType = this.getAttribute('data-sort');
            const topicId = <?= $topic_id ?>;
            
            document.querySelectorAll('.sort-btn').forEach(b => {
                b.classList.remove('bg-primary', 'text-white');
                b.classList.add('bg-white', 'border', 'border-slate-200', 'hover:border-primary', 'hover:text-primary', 'transition-colors');
            });
            this.classList.remove('bg-white', 'border', 'border-slate-200', 'hover:border-primary', 'hover:text-primary', 'transition-colors');
            this.classList.add('bg-primary', 'text-white');
            
            const repliesContainer = document.getElementById('replies-container');
            repliesContainer.innerHTML = '<div class="text-center py-8"><i class="fa fa-spinner fa-spin text-xl text-primary"></i><p class="mt-2 text-slate-500">加载中...</p></div>';
            
            const loadMoreBtn = document.getElementById('load-more-replies');
            if (loadMoreBtn) {
                loadMoreBtn.classList.add('hidden');
            }
            
            fetch(`/api/topic.php?action=get_replies&topic_id=${topicId}&page=1&sort=${sortType}`, {
                credentials: 'same-origin'
            })
            .then(response => {
                if (!response.ok) {
                    return response.text().then(text => {
                        throw new Error(`HTTP错误: ${response.status} - ${text}`);
                    });
                }
                return response.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        throw new Error(`JSON解析错误: ${e.message}\n服务器返回内容: ${text}`);
                    }
                });
            })
            .then(data => {
                if (data.success) {
                    repliesContainer.innerHTML = '';
                    
                    if (data.data.length > 0) {
                        data.data.forEach(reply => {
                            const replyElement = document.createElement('div');
                            replyElement.className = 'border-b border-slate-100 pb-4 mb-4';
                            replyElement.id = `reply-${reply.reply_id}`;
                            replyElement.innerHTML = `
                                <div class="flex items-start gap-3 mb-3">
                                    <a href="profile.php?id=${reply.user_id}">
                                        <img src="${reply.avatar_url ? reply.avatar_url : 'static/images/default-avatar.png'}" alt="用户头像" class="w-8 h-8 rounded-full mt-1">
                                    </a>
                                    <div class="flex-grow">
                                        <div class="flex items-center justify-between mb-1">
                                            <a href="profile.php?id=${reply.user_id}" class="font-medium hover:text-primary transition-colors">
                                                ${reply.username}
                                            </a>
                                            <span class="text-xs text-slate-500"><?= time_ago($reply['created_at']) ?></span>
                                        </div>
                                        <div class="markdown-content text-slate-700">
                                            <textarea class="reply-markdown hidden">${escapeHtml(reply.content)}</textarea>
                                        </div>
                                        
                                        <div class="flex items-center gap-4 mt-3">
                                            <button class="flex items-center gap-1 text-slate-500 hover:text-primary transition-colors text-sm reply-like-btn" 
                                                    data-reply-id="${reply.reply_id}">
                                                <i class="fa fa-thumbs-o-up"></i>
                                                <span class="like-count">${reply.like_count}</span>
                                            </button>
                                            <button class="flex items-center gap-1 text-slate-500 hover:text-primary transition-colors text-sm reply-reply-btn"
                                                    data-reply-id="${reply.reply_id}"
                                                    data-username="${reply.username}">
                                                <i class="fa fa-reply"></i>
                                                <span>回复</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            `;
                            
                            repliesContainer.appendChild(replyElement);
                        });
                        
                        renderMarkdown();
                        
                        if (loadMoreBtn) {
                            loadMoreBtn.setAttribute('data-page', 1);
                            loadMoreBtn.setAttribute('data-sort', sortType);
                            loadMoreBtn.classList.remove('hidden');
                        } else if (<?= $reply_count ?> > data.data.length) {
                            const newLoadMoreBtn = document.createElement('div');
                            newLoadMoreBtn.className = 'text-center';
                            newLoadMoreBtn.innerHTML = `
                                <button id="load-more-replies" class="btn-primary inline-flex items-center gap-2" data-page="1" data-topic-id="${topicId}" data-sort="${sortType}">
                                    <span>加载更多回复</span>
                                    <i class="fa fa-chevron-down"></i>
                                </button>
                            `;
                            repliesContainer.parentNode.appendChild(newLoadMoreBtn);
                            
                            newLoadMoreBtn.querySelector('#load-more-replies').addEventListener('click', loadMoreRepliesHandler);
                        }
                    } else {
                        repliesContainer.innerHTML = `
                            <div class="text-center py-8 text-slate-500">
                                <i class="fa fa-comment-o text-4xl mb-3"></i>
                                <p>还没有回复，快来抢沙发吧！</p>
                            </div>
                        `;
                    }
                } else {
                    showError(data.message);
                    repliesContainer.innerHTML = `
                        <div class="text-center py-8 text-slate-500">
                            <i class="fa fa-exclamation-circle text-4xl mb-3 text-red-500"></i>
                            <p>加载回复失败，请重试</p>
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('加载回复错误:', error);
                showError('加载回复失败', error.message);
                repliesContainer.innerHTML = `
                    <div class="text-center py-8 text-slate-500">
                        <i class="fa fa-exclamation-circle text-4xl mb-3 text-red-500"></i>
                        <p>加载回复失败，请重试</p>
                    </div>
                `;
            });
        });
    });
    
    function loadMoreRepliesHandler() {
        const btn = this;
        const topicId = btn.getAttribute('data-topic-id');
        const sortType = btn.getAttribute('data-sort');
        let page = parseInt(btn.getAttribute('data-page')) + 1;
        
        btn.innerHTML = '<span>加载中...</span><i class="fa fa-spinner fa-spin"></i>';
        btn.disabled = true;
        
        fetch(`/api/topic.php?action=get_replies&topic_id=${topicId}&page=${page}&sort=${sortType}`, {
            credentials: 'same-origin'
        })
        .then(response => {
            if (!response.ok) {
                return response.text().then(text => {
                    throw new Error(`HTTP错误: ${response.status} - ${text}`);
                });
            }
            return response.text().then(text => {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    throw new Error(`JSON解析错误: ${e.message}\n服务器返回内容: ${text}`);
                }
            });
        })
        .then(data => {
            if (data.success && data.data.length > 0) {
                const repliesContainer = document.getElementById('replies-container');
                
                data.data.forEach(reply => {
                    const replyElement = document.createElement('div');
                    replyElement.className = 'border-b border-slate-100 pb-4 mb-4';
                    replyElement.id = `reply-${reply.reply_id}`;
                    replyElement.innerHTML = `
                        <div class="flex items-start gap-3 mb-3">
                            <a href="profile.php?id=${reply.user_id}">
                                <img src="${reply.avatar_url ? reply.avatar_url : 'static/images/default-avatar.png'}" alt="用户头像" class="w-8 h-8 rounded-full mt-1">
                            </a>
                            <div class="flex-grow">
                                <div class="flex items-center justify-between mb-1">
                                    <a href="profile.php?id=${reply.user_id}" class="font-medium hover:text-primary transition-colors">
                                        ${reply.username}
                                    </a>
                                    <span class="text-xs text-slate-500"><?= time_ago($reply['created_at']) ?></span>
                                </div>
                                <div class="markdown-content text-slate-700">
                                    <textarea class="reply-markdown hidden">${escapeHtml(reply.content)}</textarea>
                                </div>
                                
                                <div class="flex items-center gap-4 mt-3">
                                    <button class="flex items-center gap-1 text-slate-500 hover:text-primary transition-colors text-sm reply-like-btn" 
                                            data-reply-id="${reply.reply_id}">
                                        <i class="fa fa-thumbs-o-up"></i>
                                        <span class="like-count">${reply.like_count}</span>
                                    </button>
                                    <button class="flex items-center gap-1 text-slate-500 hover:text-primary transition-colors text-sm reply-reply-btn"
                                            data-reply-id="${reply.reply_id}"
                                            data-username="${reply.username}">
                                        <i class="fa fa-reply"></i>
                                        <span>回复</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    repliesContainer.appendChild(replyElement);
                });
                
                renderMarkdown();
                
                btn.setAttribute('data-page', page);
                btn.innerHTML = '<span>加载更多回复</span><i class="fa fa-chevron-down"></i>';
                btn.disabled = false;
            } else {
                btn.innerHTML = '<span>没有更多回复</span>';
                btn.classList.remove('btn-primary');
                btn.classList.add('bg-slate-100', 'text-slate-500', 'cursor-not-allowed');
            }
        })
        .catch(error => {
            console.error('加载回复错误:', error);
            showError('加载回复失败', error.message);
            btn.innerHTML = '<span>加载更多回复</span><i class="fa fa-chevron-down"></i>';
            btn.disabled = false;
        });
    }
    
    document.getElementById('load-more-replies')?.addEventListener('click', loadMoreRepliesHandler);
    
    document.getElementById('follow-btn')?.addEventListener('click', function() {
        const userId = this.getAttribute('data-user-id');
        const followIcon = this.querySelector('i');
        const followText = this.querySelector('span');
        
        fetch('/api/follow.php?action=follow', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `following_id=${userId}`,
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.is_following) {
                    followIcon.classList.remove('fa-user-plus');
                    followIcon.classList.add('fa-check');
                    followText.textContent = '已关注';
                    showSuccess('关注成功');
                } else {
                    followIcon.classList.remove('fa-check');
                    followIcon.classList.add('fa-user-plus');
                    followText.textContent = '关注';
                    showSuccess('已取消关注');
                }
            } else {
                showError(data.message);
            }
        })
        .catch(error => {
            console.error('关注操作失败:', error);
            showError('操作失败，请重试');
        });
    });
    
    <?php if ($is_logged_in && $current_user['user_id'] != $topic['user_id']): ?>
    document.addEventListener('DOMContentLoaded', function() {
        const followBtn = document.getElementById('follow-btn');
        if (followBtn) {
            const userId = followBtn.getAttribute('data-user-id');
            
            fetch(`/api/follow.php?action=check_follow&following_id=${userId}`, {
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.is_following) {
                    const followIcon = followBtn.querySelector('i');
                    const followText = followBtn.querySelector('span');
                    
                    followIcon.classList.remove('fa-user-plus');
                    followIcon.classList.add('fa-check');
                    followText.textContent = '已关注';
                }
            })
            .catch(error => {
                console.error('检查关注状态失败:', error);
            });
        }
    });
    <?php endif; ?>
        </script>
        
    <?php include('common/footer.php'); ?>