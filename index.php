<?php
if (!file_exists('config/db.php')) {
    header('Location: install.php');
    exit;
}

require_once 'common/functions.php';

$filter = isset($_GET['filter']) ? $_GET['filter'] : 'latest';

switch ($filter) {
    case 'hot':
        $topics = get_hot_topics(10);
        break;
    case 'featured':
        $topics = get_featured_topics(10);
        break;
    case 'latest':
    default:
        $topics = get_latest_topics(10);
        break;
}

$hot_topics = get_hot_topics(5);

include('common/header.php');
?>
    <title><?= htmlspecialchars($site_info['site_title']) ?> - <?= htmlspecialchars($site_info['site_subtitle']) ?></title>
    <meta name="keywords" content="<?= htmlspecialchars($site_info['site_title']) ?>, <?= htmlspecialchars($site_info['site_title']) ?>首页, 文章, 博客, 论坛, 个人网站">
    <meta name="description" content="<?= htmlspecialchars($site_info['site_subtitle']) ?>，欢迎来到<?= htmlspecialchars($site_info['site_title']) ?>，在这里拥抱更广阔的星空~">
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen flex flex-col overflow-x-hidden m-0 p-0">
    
    <main class="flex-grow flex flex-col md:flex-row transition-all duration-300 pt-6" id="main-container">
        <?php include('common/asideleft.php'); ?>
        
        <div class="flex-grow transition-all duration-300 p-4 md:ml-64 lg:ml-72" id="content-area">
            <div class="max-w-5xl mx-auto">
                <div class="mb-6">
                    <div class="rounded-2xl overflow-hidden relative h-32 md:h-40">
                        <img src="<?= htmlspecialchars($site_info['home_banner_url'] ?? 'static/images/default-bg.png') ?>" alt="<?= htmlspecialchars($site_info['site_title']) ?>横幅" class="w-full h-full object-cover">
                        <div class="absolute inset-0 bg-gradient-to-r from-primary/80 to-transparent flex items-center">
                            <div class="p-4 md:p-6 text-white">
                                <h2 class="text-xl md:text-2xl font-bold mb-1"><?= htmlspecialchars($site_info['home_title']) ?></h2>
                                <p class="text-sm md:text-base max-w-md"><?= htmlspecialchars($site_info['home_subtitle']) ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                    <div class="mb-4">
                        <form action="search.php" method="get" class="relative">
                            <input 
                                type="text" 
                                name="q" 
                                placeholder="搜索话题、内容或用户..." 
                                class="w-full py-2 px-4 pr-10 rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary transition-all"
                                value="<?= isset($_GET['q']) ? htmlspecialchars($_GET['q']) : '' ?>"
                            >
                            <button type="submit" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-400 hover:text-primary transition-colors">
                                <i class="fa fa-search"></i>
                            </button>
                        </form>
                    </div>

                <div class="space-y-4">
                    <div class="flex items-center gap-2 mb-4">
                        <a href="?filter=latest" class="px-3 py-1.5 text-sm rounded-lg <?= $filter === 'latest' ? 'bg-primary text-white' : 'bg-white border border-slate-200 hover:border-primary hover:text-primary' ?> transition-colors">最新</a>
                        <a href="?filter=hot" class="px-3 py-1.5 text-sm rounded-lg <?= $filter === 'hot' ? 'bg-primary text-white' : 'bg-white border border-slate-200 hover:border-primary hover:text-primary' ?> transition-colors">热门</a>
                        <a href="?filter=featured" class="px-3 py-1.5 text-sm rounded-lg <?= $filter === 'featured' ? 'bg-primary text-white' : 'bg-white border border-slate-200 hover:border-primary hover:text-primary' ?> transition-colors">精选</a>
                    </div>
                    
                    <?php foreach ($topics as $topic): ?>
                    <article class="post-card">
                            <div class="p-5 flex flex-col">
                                <a href="topic.php?id=<?= $topic['topic_id'] ?>" class="block">
                                <h4 class="font-bold text-lg mb-3 hover:text-primary transition-colors">
                                    <?= htmlspecialchars($topic['title']) ?>
                                </h4>
                                <p class="text-slate-600 mb-4 line-clamp-3"><?= htmlspecialchars($topic['content']) ?></p>
                                </a>
                                <div class="flex items-center justify-between mt-auto">
                                    <div class="flex items-center text-sm text-slate-500">
                                        <img src="<?= htmlspecialchars($topic['avatar_url'] ?? 'static/images/default-avatar.png') ?>" alt="作者头像" class="w-6 h-6 rounded-full mr-2">
                                        <span class="mr-3"><?= htmlspecialchars($topic['username']) ?></span>
                                        <span><i class="fa fa-clock-o mr-1"></i> <?= time_ago($topic['created_at']) ?></span>
                                    </div>
                                    <div class="flex items-center text-sm text-slate-500 gap-3">
                                        <span><i class="fa fa-eye mr-1"></i> <?= $topic['view_count'] ?? 0 ?></span>
                                        <span><i class="fa fa-comment mr-1"></i> <?= $topic['reply_count'] ?? 0 ?></span>
                                        <span><i class="fa fa-heart mr-1"></i> <?= $topic['like_count'] ?? 0 ?></span>
                                    </div>
                                </div>
                            </div>
                    </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <?php include('common/asideright.php'); ?>
    </main>

    <div id="loading-indicator" class="text-center mt-6 hidden">
        <i class="fa fa-spinner fa-spin text-primary"></i>
        <span class="ml-2 text-slate-600">加载中...</span>
    </div>
    <div id="end-message" class="text-center mt-6 text-slate-500 hidden">
        已经到底啦~
    </div>
    <div id="error-message" class="text-center mt-6 text-red-500 hidden">
        加载失败，稍后尝试
    </div>
    <div id="loading-indicator" class="text-center mt-6 hidden">
        <i class="fa fa-spinner fa-spin text-primary"></i>
        <span class="ml-2 text-slate-600">加载中...</span>
    </div>
    <div id="end-message" class="text-center mt-6 mb-12 text-slate-500 hidden">
        到底啦，没有更多内容了
    </div>
    <div id="error-message" class="text-center mt-6 mb-12 text-red-500 hidden">
        加载失败，点击此处重试
    </div>

    <script>
        let isLoading = false;
        let hasMore = true;
        let currentPage = 1;
        const currentFilter = '<?= $filter ?>';
        let scrollTimeout;
        
        function loadMoreContent() {
            if (isLoading || !hasMore) return;
            
            isLoading = true;
            document.getElementById('loading-indicator').classList.remove('hidden');
            document.getElementById('end-message').classList.add('hidden');
            document.getElementById('error-message').classList.add('hidden');
            
            const page = currentPage + 1;
            
            fetch(`api/load_topics.php?page=${page}&filter=${currentFilter}`)
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
                    
                    const topics = data.data || [];
                    
                    if (topics.length > 0) {
                        let container = document.querySelector('.space-y-4');
                        const lastArticle = container.querySelector('article:last-child');
                        
                        if (lastArticle) {
                            container = lastArticle.parentNode;
                        }
                        
                        topics.forEach(topic => {
                            const article = document.createElement('article');
                            article.className = 'post-card';
                            article.innerHTML = `
                                <a href="topic.php?id=${topic.topic_id}" class="block">
                                    <div class="p-5 flex flex-col">
                                        <h4 class="font-bold text-lg mb-3 hover:text-primary transition-colors">
                                            ${escapeHtml(topic.title)}
                                        </h4>
                                        <p class="text-slate-600 mb-4 line-clamp-3">${escapeHtml(topic.content)}</p>
                                        <div class="flex items-center justify-between mt-auto">
                                            <div class="flex items-center text-sm text-slate-500">
                                                <img src="${escapeHtml(topic.avatar_url || 'static/images/default-avatar.png')}" alt="作者头像" class="w-6 h-6 rounded-full mr-2">
                                                <span class="mr-3">${escapeHtml(topic.username)}</span>
                                                <span><i class="fa fa-clock-o mr-1"></i> ${timeAgo(topic.created_at)}</span>
                                            </div>
                                            <div class="flex items-center text-sm text-slate-500 gap-3">
                                                <span><i class="fa fa-eye mr-1"></i> ${topic.view_count || 0}</span>
                                                <span><i class="fa fa-comment mr-1"></i> ${topic.reply_count || 0}</span>
                                                <span><i class="fa fa-heart mr-1"></i> ${topic.like_count || 0}</span>
                                            </div>
                                        </div>
                                    </div>
                                </a>
                            `;
                            container.appendChild(article);
                        });
                        
                        currentPage = page;
                        
                        hasMore = !!data.meta?.next_page;
                        
                        if (!hasMore) {
                            setTimeout(() => {
                                document.getElementById('loading-indicator').classList.add('hidden');
                                document.getElementById('end-message').classList.remove('hidden');
                            }, 500);
                        } else {
                            document.getElementById('loading-indicator').classList.add('hidden');
                        }
                    } else {
                        hasMore = false;
                        document.getElementById('loading-indicator').classList.add('hidden');
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
        
        window.addEventListener('scroll', function() {
            clearTimeout(scrollTimeout);
            scrollTimeout = setTimeout(() => {
                if ((window.innerHeight + window.scrollY) >= (document.body.offsetHeight - 100)) {
                    loadMoreContent();
                }
            }, 50);
        });
        document.getElementById('error-message').addEventListener('click', function() {
            loadMoreContent();
        });

        function escapeHtml(unsafe) {
            if (!unsafe) return '';
            return unsafe.toString()
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        function timeAgo(datetime) {
            const time = new Date(datetime).getTime();
            const diff = (Date.now() - time) / 1000;
            
            if (diff < 60) return '刚刚';
            if (diff < 3600) return Math.floor(diff / 60) + '分钟前';
            if (diff < 86400) return Math.floor(diff / 3600) + '小时前';
            if (diff < 2592000) return Math.floor(diff / 86400) + '天前';
            
            return new Date(time).toISOString().split('T')[0];
        }
    </script>
    
    

<?php include('common/footer.php'); ?>