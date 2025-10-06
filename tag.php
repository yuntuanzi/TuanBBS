<?php
require_once 'common/functions.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$tag_id = intval($_GET['id']);

global $pdo;
$stmt = $pdo->prepare("SELECT * FROM tags WHERE tag_id = :tag_id");
$stmt->bindParam(':tag_id', $tag_id, PDO::PARAM_INT);
$stmt->execute();
$tag = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tag) {
    header('Location: index.php');
    exit;
}

$page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

$stmt = $pdo->prepare("
    SELECT t.*, u.username, u.avatar_url, s.name as section_name 
    FROM topics t
    JOIN users u ON t.user_id = u.user_id
    JOIN sections s ON t.section_id = s.section_id
    JOIN topic_tags tt ON t.topic_id = tt.topic_id
    WHERE tt.tag_id = :tag_id
    ORDER BY t.created_at DESC
    LIMIT :offset, :per_page
");
$stmt->bindParam(':tag_id', $tag_id, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->bindParam(':per_page', $per_page, PDO::PARAM_INT);
$stmt->execute();
$topics = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT COUNT(*) as count 
    FROM topic_tags tt
    WHERE tt.tag_id = :tag_id
");
$stmt->bindParam(':tag_id', $tag_id, PDO::PARAM_INT);
$stmt->execute();
$total_topics = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
$total_pages = ceil($total_topics / $per_page);

$stmt = $pdo->prepare("
    SELECT t.tag_id, t.name, COUNT(tt.topic_id) as shared_topics
    FROM tags t
    JOIN topic_tags tt ON t.tag_id = tt.tag_id
    WHERE tt.topic_id IN (
        SELECT topic_id FROM topic_tags WHERE tag_id = :tag_id
    ) AND t.tag_id != :tag_id
    GROUP BY t.tag_id, t.name
    ORDER BY shared_topics DESC
    LIMIT 10
");
$stmt->bindParam(':tag_id', $tag_id, PDO::PARAM_INT);
$stmt->execute();
$related_tags = $stmt->fetchAll(PDO::FETCH_ASSOC);

include('common/header.php');
?>
    <title><?= htmlspecialchars($tag['name']) ?> - <?= htmlspecialchars($site_info['site_title']) ?></title>
    <meta name="keywords" content="<?= htmlspecialchars($site_info['site_title']) ?>, <?= htmlspecialchars($tag['name']) ?>, 文章标签, 博客标签, 论坛标签">
    <meta name="description" content="<?= htmlspecialchars($tag['name']) ?>标签，欢迎来到<?= htmlspecialchars($site_info['site_title']) ?>，在这里拥抱更广阔的星空~">
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen flex flex-col overflow-x-hidden m-0 p-0">
    
    <main class="flex-grow flex flex-col md:flex-row transition-all duration-300 pt-6" id="main-container">
        <?php include('common/asideleft.php'); ?>
        
        <div class="flex-grow transition-all duration-300 p-4 md:ml-64 lg:ml-72" id="content-area">
            <div class="max-w-5xl mx-auto">
                <div class="bg-white rounded-xl p-6 mb-6 shadow-sm">
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div>
                            <h1 class="text-2xl font-bold mb-2">#<?= htmlspecialchars($tag['name']) ?></h1>
                            <p class="text-slate-600 mb-2">
                                <?= htmlspecialchars($tag['description'] ?? '该标签暂无描述') ?>
                            </p>
                            <div class="text-sm text-slate-500">
                                <span><i class="fa fa-bookmark-o mr-1"></i> 被使用 <?= $tag['usage_count'] ?> 次</span>
                                <span class="mx-2">•</span>
                                <span><i class="fa fa-calendar-o mr-1"></i> 创建于 <?= date('Y-m-d', strtotime($tag['created_at'])) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="space-y-4">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-semibold">相关话题 (<?= $total_topics ?>)</h2>
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
                                    
                                    <div class="flex flex-wrap gap-2 mb-4">
                                        <?php 
                                        $topic_tags = get_topic_tags($topic['topic_id']);
                                        foreach ($topic_tags as $t) {
                                            echo '<a href="tag.php?id=' . $t['tag_id'] . '" class="px-2 py-1 bg-slate-100 text-slate-700 text-xs rounded-full hover:bg-primary hover:text-white transition-colors">' . htmlspecialchars($t['name']) . '</a>';
                                        }
                                        ?>
                                    </div>
                                    
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
                        
                        <?php if ($total_pages > 1): ?>
                        <div class="flex justify-center mt-8">
                            <nav class="inline-flex rounded-md shadow-sm" aria-label="分页">
                                <?php if ($page > 1): ?>
                                <a href="tag.php?id=<?= $tag_id ?>&page=<?= $page - 1 ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-slate-300 bg-white text-sm font-medium text-slate-500 hover:bg-slate-50">
                                    <i class="fa fa-chevron-left"></i>
                                </a>
                                <?php endif; ?>
                                
                                <?php 
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $start_page + 4);
                                
                                if ($start_page > 1) {
                                    echo '<a href="tag.php?id=' . $tag_id . '&page=1" class="relative inline-flex items-center px-4 py-2 border border-slate-300 bg-white text-sm font-medium text-slate-700 hover:bg-slate-50">1</a>';
                                    if ($start_page > 2) {
                                        echo '<span class="relative inline-flex items-center px-4 py-2 border border-slate-300 bg-white text-sm font-medium text-slate-700">...</span>';
                                    }
                                }
                                
                                for ($i = $start_page; $i <= $end_page; $i++) {
                                    if ($i == $page) {
                                        echo '<a href="tag.php?id=' . $tag_id . '&page=' . $i . '" aria-current="page" class="z-10 bg-primary text-white relative inline-flex items-center px-4 py-2 border border-primary text-sm font-medium">' . $i . '</a>';
                                    } else {
                                        echo '<a href="tag.php?id=' . $tag_id . '&page=' . $i . '" class="relative inline-flex items-center px-4 py-2 border border-slate-300 bg-white text-sm font-medium text-slate-700 hover:bg-slate-50">' . $i . '</a>';
                                    }
                                }
                                
                                if ($end_page < $total_pages) {
                                    if ($end_page < $total_pages - 1) {
                                        echo '<span class="relative inline-flex items-center px-4 py-2 border border-slate-300 bg-white text-sm font-medium text-slate-700">...</span>';
                                    }
                                    echo '<a href="tag.php?id=' . $tag_id . '&page=' . $total_pages . '" class="relative inline-flex items-center px-4 py-2 border border-slate-300 bg-white text-sm font-medium text-slate-700 hover:bg-slate-50">' . $total_pages . '</a>';
                                }
                                ?>
                                
                                <?php if ($page < $total_pages): ?>
                                <a href="tag.php?id=<?= $tag_id ?>&page=<?= $page + 1 ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-slate-300 bg-white text-sm font-medium text-slate-500 hover:bg-slate-50">
                                    <i class="fa fa-chevron-right"></i>
                                </a>
                                <?php endif; ?>
                            </nav>
                        </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="bg-white rounded-xl p-8 text-center">
                            <div class="text-slate-400 mb-4">
                                <i class="fa fa-folder-open-o text-5xl"></i>
                            </div>
                            <h3 class="text-lg font-medium mb-2">暂无相关话题</h3>
                            <p class="text-slate-500 mb-4">该标签下还没有任何话题，成为第一个发布相关话题的人吧！</p>
                            <?php if (get_logged_in_user()): ?>
                                <a href="create_topic.php" class="btn-primary inline-flex items-center gap-2">
                                    <i class="fa fa-pencil"></i>
                                    <span>发布话题</span>
                                </a>
                            <?php else: ?>
                                <a href="login.php" class="btn-primary inline-flex items-center gap-2">
                                    <i class="fa fa-sign-in"></i>
                                    <span>登录后发布话题</span>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
            <?php include('common/asideright.php'); ?>
        </main>
<?php include('common/footer.php'); ?>