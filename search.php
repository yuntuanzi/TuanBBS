<?php
require_once 'common/functions.php';
include('common/header.php');

$keyword = isset($_GET['q']) ? trim($_GET['q']) : '';

$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$valid_filters = ['all', 'topic', 'reply', 'user', 'tag'];
if (!in_array($filter, $valid_filters)) {
    $filter = 'all';
}

$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$valid_sorts = ['newest', 'hottest', 'relevant'];
if (!in_array($sort, $valid_sorts)) {
    $sort = 'newest';
}

$time_range = isset($_GET['time']) ? $_GET['time'] : 'all';
$valid_time_ranges = ['all', 'day', 'week', 'month', 'year'];
if (!in_array($time_range, $valid_time_ranges)) {
    $time_range = 'all';
}

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

$results = [];
$total_results = 0;

if (!empty($keyword)) {
    global $pdo;
    
    switch ($filter) {
        case 'topic':
            $time_where = '';
            if ($time_range == 'day') {
                $time_where = "AND t.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)";
            } elseif ($time_range == 'week') {
                $time_where = "AND t.created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
            } elseif ($time_range == 'month') {
                $time_where = "AND t.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
            } elseif ($time_range == 'year') {
                $time_where = "AND t.created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
            }
            
            $order_by = "t.created_at DESC";
            if ($sort == 'hottest') {
                $order_by = "(t.view_count * 0.3 + t.reply_count * 0.4 + t.like_count * 0.3) DESC";
            } elseif ($sort == 'relevant') {
                $order_by = "CASE WHEN t.title LIKE :keyword_exact THEN 0 WHEN t.title LIKE :keyword THEN 1 WHEN t.content LIKE :keyword THEN 2 ELSE 3 END";
            }
            
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count
                FROM topics t
                WHERE (t.title LIKE :keyword OR t.content LIKE :keyword)
                $time_where
            ");
            $stmt->bindValue(':keyword', "%{$keyword}%");
            $stmt->execute();
            $total_results = $stmt->fetchColumn();
            
            if ($total_results > 0) {
                $stmt = $pdo->prepare("
                    SELECT t.*, u.username, u.avatar_url, s.name as section_name
                    FROM topics t
                    JOIN users u ON t.user_id = u.user_id
                    JOIN sections s ON t.section_id = s.section_id
                    WHERE (t.title LIKE :keyword OR t.content LIKE :keyword)
                    $time_where
                    ORDER BY $order_by
                    LIMIT :offset, :per_page
                ");
                $stmt->bindValue(':keyword', "%{$keyword}%");
                if ($sort == 'relevant') {
                    $stmt->bindValue(':keyword_exact', "{$keyword}");
                }
                $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
                $stmt->bindParam(':per_page', $per_page, PDO::PARAM_INT);
                $stmt->execute();
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $results = array_map(function($item) use ($keyword) {
                    $item['title'] = highlight_keyword($item['title'], $keyword);
                    $item['content'] = highlight_keyword(truncate_text($item['content'], 200), $keyword);
                    $item['type'] = 'topic';
                    return $item;
                }, $results);
            }
            break;
            
        case 'reply':
            $time_where = '';
            if ($time_range == 'day') {
                $time_where = "AND r.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)";
            } elseif ($time_range == 'week') {
                $time_where = "AND r.created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
            } elseif ($time_range == 'month') {
                $time_where = "AND r.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
            } elseif ($time_range == 'year') {
                $time_where = "AND r.created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
            }
            
            $order_by = "r.created_at DESC";
            if ($sort == 'hottest') {
                $order_by = "r.like_count DESC, r.created_at DESC";
            } elseif ($sort == 'relevant') {
                $order_by = "CASE WHEN r.content LIKE :keyword_exact THEN 0 ELSE 1 END";
            }
            
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count
                FROM replies r
                WHERE r.content LIKE :keyword
                $time_where
            ");
            $stmt->bindValue(':keyword', "%{$keyword}%");
            $stmt->execute();
            $total_results = $stmt->fetchColumn();
            
            if ($total_results > 0) {
                $stmt = $pdo->prepare("
                    SELECT r.*, u.username, u.avatar_url, t.title as topic_title, t.topic_id
                    FROM replies r
                    JOIN users u ON r.user_id = u.user_id
                    JOIN topics t ON r.topic_id = t.topic_id
                    WHERE r.content LIKE :keyword
                    $time_where
                    ORDER BY $order_by
                    LIMIT :offset, :per_page
                ");
                $stmt->bindValue(':keyword', "%{$keyword}%");
                if ($sort == 'relevant') {
                    $stmt->bindValue(':keyword_exact', "{$keyword}");
                }
                $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
                $stmt->bindParam(':per_page', $per_page, PDO::PARAM_INT);
                $stmt->execute();
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $results = array_map(function($item) use ($keyword) {
                    $item['content'] = highlight_keyword(truncate_text($item['content'], 200), $keyword);
                    $item['topic_title'] = highlight_keyword($item['topic_title'], $keyword);
                    $item['type'] = 'reply';
                    return $item;
                }, $results);
            }
            break;
            
        case 'user':
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count
                FROM users u
                WHERE u.username LIKE :keyword OR u.email LIKE :keyword
            ");
            $stmt->bindValue(':keyword', "%{$keyword}%");
            $stmt->execute();
            $total_results = $stmt->fetchColumn();
            
            if ($total_results > 0) {
                $order_by = "u.username ASC";
                if ($sort == 'newest') {
                    $order_by = "u.created_at DESC";
                }
                
                $stmt = $pdo->prepare("
                    SELECT u.*, 
                           (SELECT COUNT(*) FROM topics WHERE user_id = u.user_id) as topic_count,
                           (SELECT COUNT(*) FROM replies WHERE user_id = u.user_id) as reply_count
                    FROM users u
                    WHERE u.username LIKE :keyword OR u.email LIKE :keyword
                    ORDER BY $order_by
                    LIMIT :offset, :per_page
                ");
                $stmt->bindValue(':keyword', "%{$keyword}%");
                $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
                $stmt->bindParam(':per_page', $per_page, PDO::PARAM_INT);
                $stmt->execute();
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $results = array_map(function($item) use ($keyword) {
                    $item['username'] = highlight_keyword($item['username'], $keyword);
                    $item['type'] = 'user';
                    return $item;
                }, $results);
            }
            break;
            
        case 'tag':
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count
                FROM tags t
                WHERE t.name LIKE :keyword OR t.description LIKE :keyword
            ");
            $stmt->bindValue(':keyword', "%{$keyword}%");
            $stmt->execute();
            $total_results = $stmt->fetchColumn();
            
            if ($total_results > 0) {
                $order_by = "t.usage_count DESC";
                if ($sort == 'newest') {
                    $order_by = "t.created_at DESC";
                } elseif ($sort == 'relevant') {
                    $order_by = "CASE WHEN t.name LIKE :keyword_exact THEN 0 WHEN t.name LIKE :keyword THEN 1 ELSE 2 END";
                }
                
                $stmt = $pdo->prepare("
                    SELECT t.*
                    FROM tags t
                    WHERE t.name LIKE :keyword OR t.description LIKE :keyword
                    ORDER BY $order_by
                    LIMIT :offset, :per_page
                ");
                $stmt->bindValue(':keyword', "%{$keyword}%");
                if ($sort == 'relevant') {
                    $stmt->bindValue(':keyword_exact', "{$keyword}");
                }
                $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
                $stmt->bindParam(':per_page', $per_page, PDO::PARAM_INT);
                $stmt->execute();
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $results = array_map(function($item) use ($keyword) {
                    $item['name'] = highlight_keyword($item['name'], $keyword);
                    if (!empty($item['description'])) {
                        $item['description'] = highlight_keyword(truncate_text($item['description'], 200), $keyword);
                    }
                    $item['type'] = 'tag';
                    return $item;
                }, $results);
            }
            break;
            
        case 'all':
        default:
            $time_where = '';
            if ($time_range == 'day') {
                $time_where = "DATE_SUB(NOW(), INTERVAL 1 DAY)";
            } elseif ($time_range == 'week') {
                $time_where = "DATE_SUB(NOW(), INTERVAL 1 WEEK)";
            } elseif ($time_range == 'month') {
                $time_where = "DATE_SUB(NOW(), INTERVAL 1 MONTH)";
            } elseif ($time_range == 'year') {
                $time_where = "DATE_SUB(NOW(), INTERVAL 1 YEAR)";
            }
            
            $topic_conditions = "(t.title LIKE :keyword OR t.content LIKE :keyword)";
            if (!empty($time_where)) {
                $topic_conditions .= " AND t.created_at >= {$time_where}";
            }
            
            $reply_conditions = "r.content LIKE :keyword";
            if (!empty($time_where)) {
                $reply_conditions .= " AND r.created_at >= {$time_where}";
            }
            
            $user_conditions = "u.username LIKE :keyword OR u.email LIKE :keyword";
            
            $tag_conditions = "t.name LIKE :keyword OR t.description LIKE :keyword";
            
            $stmt = $pdo->prepare("
                SELECT 
                    (SELECT COUNT(*) FROM topics t WHERE {$topic_conditions}) +
                    (SELECT COUNT(*) FROM replies r WHERE {$reply_conditions}) +
                    (SELECT COUNT(*) FROM users u WHERE {$user_conditions}) +
                    (SELECT COUNT(*) FROM tags t WHERE {$tag_conditions}) 
                    as count
            ");
            $stmt->bindValue(':keyword', "%{$keyword}%");
            $stmt->execute();
            $total_results = $stmt->fetchColumn();
            
            if ($total_results > 0) {
                $topics = [];
                $stmt = $pdo->prepare("
                    SELECT t.*, u.username, u.avatar_url, s.name as section_name, 'topic' as type, t.created_at as sort_date
                    FROM topics t
                    JOIN users u ON t.user_id = u.user_id
                    JOIN sections s ON t.section_id = s.section_id
                    WHERE {$topic_conditions}
                    LIMIT :offset, :per_page
                ");
                $stmt->bindValue(':keyword', "%{$keyword}%");
                $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
                $stmt->bindParam(':per_page', $per_page, PDO::PARAM_INT);
                $stmt->execute();
                $topics = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $replies = [];
                $stmt = $pdo->prepare("
                    SELECT r.*, u.username, u.avatar_url, t.title as topic_title, t.topic_id, 'reply' as type, r.created_at as sort_date
                    FROM replies r
                    JOIN users u ON r.user_id = u.user_id
                    JOIN topics t ON r.topic_id = t.topic_id
                    WHERE {$reply_conditions}
                    LIMIT :offset, :per_page
                ");
                $stmt->bindValue(':keyword', "%{$keyword}%");
                $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
                $stmt->bindParam(':per_page', $per_page, PDO::PARAM_INT);
                $stmt->execute();
                $replies = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $users = [];
                $stmt = $pdo->prepare("
                    SELECT u.*, 
                           (SELECT COUNT(*) FROM topics WHERE user_id = u.user_id) as topic_count,
                           (SELECT COUNT(*) FROM replies WHERE user_id = u.user_id) as reply_count,
                           'user' as type, u.created_at as sort_date
                    FROM users u
                    WHERE {$user_conditions}
                    LIMIT :offset, :per_page
                ");
                $stmt->bindValue(':keyword', "%{$keyword}%");
                $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
                $stmt->bindParam(':per_page', $per_page, PDO::PARAM_INT);
                $stmt->execute();
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $tags = [];
                $stmt = $pdo->prepare("
                    SELECT t.*, 'tag' as type, t.created_at as sort_date
                    FROM tags t
                    WHERE {$tag_conditions}
                    LIMIT :offset, :per_page
                ");
                $stmt->bindValue(':keyword', "%{$keyword}%");
                $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
                $stmt->bindParam(':per_page', $per_page, PDO::PARAM_INT);
                $stmt->execute();
                $tags = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $results = array_merge($topics, $replies, $users, $tags);
                
                if ($sort == 'newest') {
                    usort($results, function($a, $b) {
                        return strtotime($b['sort_date']) - strtotime($a['sort_date']);
                    });
                } elseif ($sort == 'hottest') {
                    usort($results, function($a, $b) {
                        $scoreA = 0;
                        $scoreB = 0;
                        
                        if ($a['type'] == 'topic') {
                            $scoreA = ($a['view_count'] * 0.3) + ($a['reply_count'] * 0.4) + ($a['like_count'] * 0.3);
                        } elseif ($a['type'] == 'reply') {
                            $scoreA = $a['like_count'] * 2;
                        } elseif ($a['type'] == 'user') {
                            $scoreA = ($a['topic_count'] * 2) + $a['reply_count'];
                        } elseif ($a['type'] == 'tag') {
                            $scoreA = $a['usage_count'];
                        }
                        
                        if ($b['type'] == 'topic') {
                            $scoreB = ($b['view_count'] * 0.3) + ($b['reply_count'] * 0.4) + ($b['like_count'] * 0.3);
                        } elseif ($b['type'] == 'reply') {
                            $scoreB = $b['like_count'] * 2;
                        } elseif ($b['type'] == 'user') {
                            $scoreB = ($b['topic_count'] * 2) + $b['reply_count'];
                        } elseif ($b['type'] == 'tag') {
                            $scoreB = $b['usage_count'];
                        }
                        
                        return $scoreB - $scoreA;
                    });
                }
                
                $results = array_map(function($item) use ($keyword) {
                    switch ($item['type']) {
                        case 'topic':
                            $item['title'] = highlight_keyword($item['title'], $keyword);
                            $item['content'] = highlight_keyword(truncate_text($item['content'], 200), $keyword);
                            break;
                        case 'reply':
                            $item['content'] = highlight_keyword(truncate_text($item['content'], 200), $keyword);
                            $item['topic_title'] = highlight_keyword($item['topic_title'], $keyword);
                            break;
                        case 'user':
                            $item['username'] = highlight_keyword($item['username'], $keyword);
                            break;
                        case 'tag':
                            $item['name'] = highlight_keyword($item['name'], $keyword);
                            if (!empty($item['description'])) {
                                $item['description'] = highlight_keyword(truncate_text($item['description'], 200), $keyword);
                            }
                            break;
                    }
                    return $item;
                }, $results);
            }
            break;
    }
}

function highlight_keyword($text, $keyword) {
    if (empty($keyword) || empty($text)) {
        return $text;
    }
    return preg_replace('/(' . preg_quote($keyword, '/') . ')/i', '<span class="bg-yellow-200">\1</span>', $text);
}

function truncate_text($text, $length = 200) {
    $text = strip_tags($text);
    if (mb_strlen($text) <= $length) {
        return $text;
    }
    return mb_substr($text, 0, $length) . '...';
}
?>
    <title>搜索 - <?= htmlspecialchars($site_info['site_title']) ?></title>
    <meta name="keywords" content="<?= htmlspecialchars($site_info['site_title']) ?>, <?= htmlspecialchars($site_info['site_title']) ?>搜索, 搜索文章, 搜索帖子, 搜索用户, 搜索话题, 搜索博文, 搜索标签">
    <meta name="description" content="善用搜索，事半功倍~">
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen flex flex-col overflow-x-hidden m-0 p-0">
    <main class="flex-grow flex flex-col md:flex-row transition-all duration-300 pt-6" id="main-container">
        <?php include('common/asideleft.php'); ?>
        
        <div class="flex-grow transition-all duration-300 p-4 md:ml-64 lg:ml-72" id="content-area">
            <div class="max-w-5xl mx-auto">
                <div class="bg-white rounded-xl shadow-card p-5 mb-6">
                    <form method="get" action="search.php" class="relative">
                        <div class="flex items-center">
                            <i class="fa fa-search text-slate-400 ml-4 z-10 absolute"></i>
                            <input type="text" name="q" class="w-full px-4 py-3 pl-12 rounded-lg border border-slate-300 focus:border-primary focus:ring-2 focus:ring-primary/50 transition-all" placeholder="搜索帖子、用户、标签..." value="<?= htmlspecialchars($keyword) ?>">
                            <button type="submit" class="ml-2 px-4 py-3 bg-primary text-white rounded-lg text-sm whitespace-nowrap">
                                搜索
                            </button>
                        </div>
                        
                        <div class="flex flex-wrap items-center gap-4 mt-4">
                            <div class="flex items-center gap-2">
                                <span class="text-sm text-slate-700">筛选:</span>
                                <select name="filter" class="text-sm border border-slate-300 rounded-lg px-3 py-1.5 focus:border-primary focus:ring-primary/50" onchange="this.form.submit()">
                                    <option value="all" <?= $filter == 'all' ? 'selected' : '' ?>>全部内容</option>
                                    <option value="topic" <?= $filter == 'topic' ? 'selected' : '' ?>>话题</option>
                                    <option value="reply" <?= $filter == 'reply' ? 'selected' : '' ?>>回复</option>
                                    <option value="user" <?= $filter == 'user' ? 'selected' : '' ?>>用户</option>
                                    <option value="tag" <?= $filter == 'tag' ? 'selected' : '' ?>>标签</option>
                                </select>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-sm text-slate-700">排序:</span>
                                <select name="sort" class="text-sm border border-slate-300 rounded-lg px-3 py-1.5 focus:border-primary focus:ring-primary/50" onchange="this.form.submit()">
                                    <option value="newest" <?= $sort == 'newest' ? 'selected' : '' ?>>最新</option>
                                    <option value="hottest" <?= $sort == 'hottest' ? 'selected' : '' ?>>最热</option>
                                    <option value="relevant" <?= $sort == 'relevant' ? 'selected' : '' ?>>最相关</option>
                                </select>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-sm text-slate-700">时间:</span>
                                <select name="time" class="text-sm border border-slate-300 rounded-lg px-3 py-1.5 focus:border-primary focus:ring-primary/50" onchange="this.form.submit()">
                                    <option value="all" <?= $time_range == 'all' ? 'selected' : '' ?>>全部时间</option>
                                    <option value="day" <?= $time_range == 'day' ? 'selected' : '' ?>>最近一天</option>
                                    <option value="week" <?= $time_range == 'week' ? 'selected' : '' ?>>最近一周</option>
                                    <option value="month" <?= $time_range == 'month' ? 'selected' : '' ?>>最近一月</option>
                                    <option value="year" <?= $time_range == 'year' ? 'selected' : '' ?>>最近一年</option>
                                </select>
                            </div>
                        </div>
                    </form>
                </div>
                
                <div class="mb-6">
                    <?php if (!empty($keyword)): ?>
                        <h2 class="text-lg font-bold mb-4">关于"<?= htmlspecialchars($keyword) ?>"的搜索结果 (<?= $total_results ?>条)</h2>
                        
                        <?php if (!empty($results)): ?>
                            <?php foreach ($results as $result): ?>
                                <?php if ($result['type'] == 'topic'): ?>
                                    <div class="bg-white rounded-xl shadow-card p-5 mb-4">
                                        <div class="flex items-start gap-3">
                                            <img src="<?= htmlspecialchars($result['avatar_url'] ?? 'static/images/default-avatar.png') ?>" alt="<?= htmlspecialchars($result['username']) ?>的头像" class="w-10 h-10 rounded-full mt-1">
                                            <div class="flex-grow">
                                                <h3 class="font-bold text-lg mb-1 hover:text-primary transition-colors">
                                                    <a href="topic.php?id=<?= $result['topic_id'] ?>"><?= $result['title'] ?></a>
                                                </h3>
                                                <p class="text-slate-600 mb-2 line-clamp-2"><?= $result['content'] ?></p>
                                                <div class="flex flex-wrap items-center text-sm text-slate-500 gap-4">
                                                    <span><a href="profile.php?id=<?= $result['user_id'] ?>" class="hover:text-primary transition-colors"><?= htmlspecialchars($result['username']) ?></a></span>
                                                    <span><i class="fa fa-clock-o mr-1"></i> <?= time_ago($result['created_at']) ?></span>
                                                    <span><i class="fa fa-comment mr-1"></i> <?= $result['reply_count'] ?></span>
                                                    <span><i class="fa fa-heart mr-1"></i> <?= $result['like_count'] ?></span>
                                                    <span><a href="section.php?id=<?= $result['section_id'] ?>" class="text-slate-600 hover:text-primary transition-colors"><?= htmlspecialchars($result['section_name']) ?></a></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php elseif ($result['type'] == 'reply'): ?>
                                    <div class="bg-white rounded-xl shadow-card p-5 mb-4">
                                        <div class="flex items-start gap-3">
                                            <img src="<?= htmlspecialchars($result['avatar_url'] ?? 'static/images/default-avatar.png') ?>" alt="<?= htmlspecialchars($result['username']) ?>的头像" class="w-10 h-10 rounded-full mt-1">
                                            <div class="flex-grow">
                                                <div class="mb-2 text-sm text-slate-500">
                                                    回复了话题: <a href="topic.php?id=<?= $result['topic_id'] ?>#reply-<?= $result['reply_id'] ?>" class="text-primary hover:underline"><?= $result['topic_title'] ?></a>
                                                </div>
                                                <p class="text-slate-600 mb-2 line-clamp-2"><?= $result['content'] ?></p>
                                                <div class="flex items-center text-sm text-slate-500 gap-4">
                                                    <span><a href="profile.php?id=<?= $result['user_id'] ?>" class="hover:text-primary transition-colors"><?= htmlspecialchars($result['username']) ?></a></span>
                                                    <span><i class="fa fa-clock-o mr-1"></i> <?= time_ago($result['created_at']) ?></span>
                                                    <span><i class="fa fa-heart mr-1"></i> <?= $result['like_count'] ?></span>
                                                    <span><a href="topic.php?id=<?= $result['topic_id'] ?>#reply-<?= $result['reply_id'] ?>" class="text-primary hover:underline">查看上下文</a></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php elseif ($result['type'] == 'user'): ?>
                                    <div class="bg-white rounded-xl shadow-card p-5 mb-4">
                                        <div class="flex items-start gap-3">
                                            <img src="<?= htmlspecialchars($result['avatar_url'] ?? 'static/images/default-avatar.png') ?>" alt="<?= htmlspecialchars($result['username']) ?>的头像" class="w-12 h-12 rounded-full mt-1">
                                            <div class="flex-grow">
                                                <h3 class="font-bold text-lg mb-1 hover:text-primary transition-colors">
                                                    <a href="profile.php?id=<?= $result['user_id'] ?>"><?= $result['username'] ?></a>
                                                </h3>
                                                <p class="text-slate-600 mb-2">
                                                    <?= !empty($result['bio']) ? truncate_text($result['bio'], 150) : '该用户尚未填写个人简介' ?>
                                                </p>
                                                <div class="flex items-center text-sm text-slate-500 gap-4">
                                                    <span><i class="fa fa-user-plus mr-1"></i> 注册于 <?= date('Y-m-d', strtotime($result['created_at'])) ?></span>
                                                    <span><i class="fa fa-file-text-o mr-1"></i> 发布了 <?= $result['topic_count'] ?> 个话题</span>
                                                    <span><i class="fa fa-comment-o mr-1"></i> 发表了 <?= $result['reply_count'] ?> 条回复</span>
                                                </div>
                                            </div>
                                            <div>
                                                <button class="px-3 py-1.5 text-sm bg-primary/10 text-primary rounded-lg hover:bg-primary/20 transition-colors">
                                                    关注
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php elseif ($result['type'] == 'tag'): ?>
                                    <div class="bg-white rounded-xl shadow-card p-5 mb-4">
                                        <div class="flex items-start gap-3">
                                            <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center text-primary mt-1">
                                                <i class="fa fa-tag"></i>
                                            </div>
                                            <div class="flex-grow">
                                                <h3 class="font-bold text-lg mb-1 hover:text-primary transition-colors">
                                                    <a href="tag.php?id=<?= $result['tag_id'] ?>"><?= $result['name'] ?></a>
                                                </h3>
                                                <p class="text-slate-600 mb-2">
                                                    <?= !empty($result['description']) ? $result['description'] : '该标签暂无描述' ?>
                                                </p>
                                                <div class="flex items-center text-sm text-slate-500 gap-4">
                                                    <span><i class="fa fa-files-o mr-1"></i> 被使用了 <?= $result['usage_count'] ?> 次</span>
                                                    <span><i class="fa fa-clock-o mr-1"></i> 创建于 <?= date('Y-m-d', strtotime($result['created_at'])) ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            
                            <?php if ($total_results > $per_page): ?>
                                <div class="flex justify-center mt-6">
                                    <nav class="inline-flex rounded-md shadow">
                                        <?php if ($page > 1): ?>
                                            <a href="search.php?q=<?= urlencode($keyword) ?>&filter=<?= $filter ?>&sort=<?= $sort ?>&time=<?= $time_range ?>&page=<?= $page - 1 ?>" 
                                               class="px-3 py-2 rounded-l-md border border-slate-300 bg-white text-sm font-medium text-slate-500 hover:bg-slate-50">
                                                上一页
                                            </a>
                                        <?php else: ?>
                                            <span class="px-3 py-2 rounded-l-md border border-slate-300 bg-slate-100 text-sm font-medium text-slate-400 cursor-not-allowed">
                                                上一页
                                            </span>
                                        <?php endif; ?>
                                        
                                        <?php 
                                        $total_pages = ceil($total_results / $per_page);
                                        $start_page = max(1, $page - 2);
                                        $end_page = min($total_pages, $page + 2);
                                        
                                        if ($start_page > 1) {
                                            echo '<a href="search.php?q=' . urlencode($keyword) . '&filter=' . $filter . '&sort=' . $sort . '&time=' . $time_range . '&page=1" class="px-3 py-2 border-t border-b border-slate-300 bg-white text-sm font-medium text-slate-500 hover:bg-slate-50">1</a>';
                                            if ($start_page > 2) {
                                                echo '<span class="px-3 py-2 border-t border-b border-slate-300 bg-white text-sm font-medium text-slate-300">...</span>';
                                            }
                                        }
                                        
                                        for ($i = $start_page; $i <= $end_page; $i++) {
                                            if ($i == $page) {
                                                echo '<a href="search.php?q=' . urlencode($keyword) . '&filter=' . $filter . '&sort=' . $sort . '&time=' . $time_range . '&page=' . $i . '" class="px-3 py-2 border-t border-b border-slate-300 bg-white text-sm font-medium text-primary hover:bg-slate-50">' . $i . '</a>';
                                            } else {
                                                echo '<a href="search.php?q=' . urlencode($keyword) . '&filter=' . $filter . '&sort=' . $sort . '&time=' . $time_range . '&page=' . $i . '" class="px-3 py-2 border-t border-b border-slate-300 bg-white text-sm font-medium text-slate-500 hover:bg-slate-50">' . $i . '</a>';
                                            }
                                        }
                                        
                                        if ($end_page < $total_pages) {
                                            if ($end_page < $total_pages - 1) {
                                                echo '<span class="px-3 py-2 border-t border-b border-slate-300 bg-white text-sm font-medium text-slate-300">...</span>';
                                            }
                                            echo '<a href="search.php?q=' . urlencode($keyword) . '&filter=' . $filter . '&sort=' . $sort . '&time=' . $time_range . '&page=' . $total_pages . '" class="px-3 py-2 border-t border-b border-slate-300 bg-white text-sm font-medium text-slate-500 hover:bg-slate-50">' . $total_pages . '</a>';
                                        }
                                        ?>
                                        
                                        <?php if ($page < $total_pages): ?>
                                            <a href="search.php?q=<?= urlencode($keyword) ?>&filter=<?= $filter ?>&sort=<?= $sort ?>&time=<?= $time_range ?>&page=<?= $page + 1 ?>" 
                                               class="px-3 py-2 rounded-r-md border border-slate-300 bg-white text-sm font-medium text-slate-500 hover:bg-slate-50">
                                                下一页
                                            </a>
                                        <?php else: ?>
                                            <span class="px-3 py-2 rounded-r-md border border-slate-300 bg-slate-100 text-sm font-medium text-slate-400 cursor-not-allowed">
                                                下一页
                                            </span>
                                        <?php endif; ?>
                                    </nav>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="bg-white rounded-xl shadow-card p-8 text-center">
                                <i class="fa fa-search text-5xl text-slate-300 mb-4"></i>
                                <h3 class="text-xl font-medium text-slate-700 mb-2">未找到相关结果</h3>
                                <p class="text-slate-500 mb-6">尝试使用不同的筛选条件</p>
                                <a href="search.php?q=<?= urlencode($keyword) ?>" class="btn-primary">清除筛选条件</a>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="bg-white rounded-xl shadow-card p-8 text-center">
                            <i class="fa fa-search text-5xl text-slate-300 mb-4"></i>
                            <h3 class="text-xl font-medium text-slate-700 mb-2">请输入搜索关键词</h3>
                            <p class="text-slate-500">搜索话题、回复、用户或标签</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php include('common/asideright.php'); ?>
    </main>
<?php include('common/footer.php'); ?>