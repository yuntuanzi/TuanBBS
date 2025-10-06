<?php
require_once 'common/functions.php';

$section_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($section_id <= 0) {
    header("Location: index.php");
    exit;
}

$current_section = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM sections WHERE section_id = :id");
    $stmt->bindParam(':id', $section_id, PDO::PARAM_INT);
    $stmt->execute();
    $current_section = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();
    
} catch (PDOException $e) {
    die("获取板块信息失败: " . $e->getMessage());
}

if (!$current_section) {
    header("Location: index.php");
    exit;
}

$filter = isset($_GET['filter']) ? $_GET['filter'] : 'latest';

switch ($filter) {
    case 'hot':
        $stmt = $pdo->prepare("
            SELECT t.*, u.username, u.avatar_url, s.name as section_name 
            FROM topics t
            JOIN users u ON t.user_id = u.user_id
            JOIN sections s ON t.section_id = s.section_id
            WHERE t.section_id = :section_id AND t.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY (t.view_count * 0.3 + t.reply_count * 0.4 + t.like_count * 0.3) DESC
            LIMIT 10
        ");
        break;
    case 'featured':
        $stmt = $pdo->prepare("
            SELECT t.*, u.username, u.avatar_url, s.name as section_name 
            FROM topics t
            JOIN users u ON t.user_id = u.user_id
            JOIN sections s ON t.section_id = s.section_id
            WHERE t.section_id = :section_id AND t.is_essence = 1
            ORDER BY t.created_at DESC
            LIMIT 10
        ");
        break;
    case 'latest':
    default:
        $stmt = $pdo->prepare("
            SELECT t.*, u.username, u.avatar_url, s.name as section_name 
            FROM topics t
            JOIN users u ON t.user_id = u.user_id
            JOIN sections s ON t.section_id = s.section_id
            WHERE t.section_id = :section_id
            ORDER BY t.created_at DESC
            LIMIT 10
        ");
        break;
}

$stmt->bindParam(':section_id', $section_id, PDO::PARAM_INT);
$stmt->execute();
$topics = $stmt->fetchAll(PDO::FETCH_ASSOC);

$hot_topics = get_hot_topics(5);

$protected_section_data = $current_section;
$protected_site_info = $site_info;
$protected_sections = $sections;

include('common/header.php');
?>
    <title><?= htmlspecialchars($protected_section_data['name']) ?> - <?= htmlspecialchars($protected_site_info['site_title']) ?></title>
    <meta name="keywords" content="<?= htmlspecialchars($site_info['site_title']) ?>, <?= htmlspecialchars($protected_section_data['name']) ?>, 板块, 分类">
    <meta name="description" content="<?= htmlspecialchars($protected_section_data['name']) ?>分类，快来<?= htmlspecialchars($site_info['site_subtitle']) ?>这里，看看有什么好东西吧~">
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen flex flex-col overflow-x-hidden m-0 p-0">
    
    <main class="flex-grow flex flex-col md:flex-row transition-all duration-300 pt-6" id="main-container">
        <?php 
        $site_info = $protected_site_info;
        $sections = $protected_sections;
        include('common/asideleft.php'); 
        $current_section = $protected_section_data;
        ?>
        
                <div class="flex-grow transition-all duration-300 p-4 md:ml-64 lg:ml-72" id="content-area">
                    <div class="max-w-5xl mx-auto">
                    <?php if (!empty($current_section['banner_url'])): ?>
                    <div class="rounded-2xl overflow-hidden mb-6 relative">
                        <img src="<?= htmlspecialchars($current_section['banner_url']) ?>" 
                             alt="<?= htmlspecialchars($current_section['name']) ?> 背景图" 
                             class="w-full h-80 object-cover">
                        
                        <div class="absolute inset-0 bg-gradient-to-t from-black/30 to-transparent flex items-end p-6">
                            <div class="flex items-center gap-4 text-white">
                                <?php if ($current_section['icon']): ?>
                                <div class="w-12 h-12 rounded-lg bg-white/20 flex items-center justify-center text-white text-xl">
                                    <img src="<?= htmlspecialchars($current_section['icon'] ?? 'static/icon/forum/更多服务.svg') ?>" alt="板块图标" width="45" height="45">
                                </div>
                                <?php endif; ?>
                                <div>
                                    <h2 class="text-2xl font-bold"><?= htmlspecialchars($current_section['name']) ?></h2>
                                    <?php if (!empty($current_section['description'])): ?>
                                    <p class="text-white/90 mt-1"><?= htmlspecialchars($current_section['description']) ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="rounded-2xl bg-white shadow-md mb-6 p-6">
                        <div class="flex items-center gap-4">
                            <?php if ($current_section['icon']): ?>
                            <div class="w-12 h-12 rounded-lg flex items-center justify-center text-primary text-xl">
                                <img src="<?= htmlspecialchars($current_section['icon'] ?? 'static/icon/forum/更多服务.svg') ?>" alt="板块图标" width="45" height="45">
                            </div>
                            <?php endif; ?>
                            <div>
                                <h2 class="text-2xl font-bold"><?= htmlspecialchars($current_section['name']) ?></h2>
                                <p class="text-slate-600 mt-1"><?= htmlspecialchars($current_section['description'] ?? '暂无板块描述') ?></p>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

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
                        <a href="?id=<?= $section_id ?>&filter=latest" class="px-3 py-1.5 text-sm rounded-lg <?= $filter === 'latest' ? 'bg-primary text-white' : 'bg-white border border-slate-200 hover:border-primary hover:text-primary' ?> transition-colors">最新</a>
                        <a href="?id=<?= $section_id ?>&filter=hot" class="px-3 py-1.5 text-sm rounded-lg <?= $filter === 'hot' ? 'bg-primary text-white' : 'bg-white border border-slate-200 hover:border-primary hover:text-primary' ?> transition-colors">热门</a>
                        <a href="?id=<?= $section_id ?>&filter=featured" class="px-3 py-1.5 text-sm rounded-lg <?= $filter === 'featured' ? 'bg-primary text-white' : 'bg-white border border-slate-200 hover:border-primary hover:text-primary' ?> transition-colors">精选</a>
                    </div>
                    
                    <?php if (count($topics) > 0): ?>
                        <?php foreach ($topics as $topic): ?>
                        <article class="post-card">
                            <a href="topic.php?id=<?= $topic['topic_id'] ?>" class="block">
                                <div class="p-5 flex flex-col">
                                    <h4 class="font-bold text-lg mb-3 hover:text-primary transition-colors">
                                        <?= htmlspecialchars($topic['title']) ?>
                                    </h4>
                                    <p class="text-slate-600 mb-4 line-clamp-3"><?= htmlspecialchars($topic['content']) ?></p>
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
                            </a>
                        </article>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="bg-white rounded-xl shadow-md p-8 text-center">
                            <div class="text-slate-400 mb-4">
                                <i class="fa fa-file-text-o text-4xl"></i>
                            </div>
                            <h3 class="text-lg font-medium text-slate-700 mb-2">该板块还没有帖子</h3>
                            <p class="text-slate-500 mb-4">快来发布第一个帖子吧！</p>
                            <a href="create_topic.php" class="btn-primary inline-block">
                                发布新帖
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- 加载状态指示器 -->
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
            </div>
        </div>
        
        <?php include('common/asideright.php'); ?>
    </main>

    <script>
        // 初始化变量
        let isLoading = false;
        let hasMore = true;
        let currentPage = 1;
        const currentFilter = '<?= $filter ?>';
        const sectionId = <?= $section_id ?>;
        let scrollTimeout;
        
        // 加载更多内容
        function loadMoreContent() {
            if (isLoading || !hasMore) return;
            
            isLoading = true;
            document.getElementById('loading-indicator').classList.remove('hidden');
            document.getElementById('end-message').classList.add('hidden');
            document.getElementById('error-message').classList.add('hidden');
            
            const page = currentPage + 1;
            
            fetch(`api/load_topics.php?page=${page}&filter=${currentFilter}&section=${sectionId}`)
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
                        const container = document.querySelector('.space-y-4');
                        
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
                        
                        // 根据API返回判断是否还有更多内容
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
        
        // 滚动加载触发
        window.addEventListener('scroll', function() {
            clearTimeout(scrollTimeout);
            scrollTimeout = setTimeout(() => {
                // 当滚动到距离底部100px时加载更多
                if ((window.innerHeight + window.scrollY) >= (document.body.offsetHeight - 100)) {
                    loadMoreContent();
                }
            }, 50);
        });
        
        // 错误重试
        document.getElementById('error-message').addEventListener('click', function() {
            loadMoreContent();
        });

        // HTML转义函数
        function escapeHtml(unsafe) {
            if (!unsafe) return '';
            return unsafe.toString()
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        // 时间格式化函数
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
