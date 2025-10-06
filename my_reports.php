<?php
require_once 'common/functions.php';

// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}


/**
 * 获取回复信息
 * @param int $reply_id 回复ID
 */
function get_reply($reply_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT r.*, u.username, u.avatar_url 
        FROM replies r
        JOIN users u ON r.user_id = u.user_id
        WHERE r.reply_id = :reply_id
    ");
    $stmt->bindParam(':reply_id', $reply_id, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * 获取用户信息
 * @param int $user_id 用户ID
 */
function get_user_info($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT user_id, username, avatar_url, role, status 
        FROM users 
        WHERE user_id = :user_id
    ");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

$current_user = get_logged_in_user();

// 获取举报状态的文本描述
function get_report_status_text($status) {
    $status_map = [
        'pending' => ['待处理', 'bg-yellow-100 text-yellow-800'],
        'processed' => ['已处理', 'bg-green-100 text-green-800'],
        'dismissed' => ['已驳回', 'bg-gray-100 text-gray-800']
    ];
    return $status_map[$status] ?? ['未知', 'bg-gray-100 text-gray-800'];
}

// 获取目标类型的文本描述
function get_target_type_text($type) {
    $type_map = [
        'topic' => '主题',
        'reply' => '回复',
        'user' => '用户'
    ];
    return $type_map[$type] ?? '未知';
}

// 获取用户的举报记录
function get_user_reports($user_id, $page = 1, $per_page = 15) {
    global $pdo;
    
    $page = max(1, intval($page));
    $offset = ($page - 1) * $per_page;
    
    // 获取举报记录
    $stmt = $pdo->prepare("
        SELECT * FROM reports 
        WHERE user_id = :user_id 
        ORDER BY created_at DESC 
        LIMIT :offset, :per_page
    ");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindParam(':per_page', $per_page, PDO::PARAM_INT);
    $stmt->execute();
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 获取总记录数
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total FROM reports 
        WHERE user_id = :user_id
    ");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $total = $stmt->fetchColumn();
    
    // 获取每条举报的目标信息
    foreach ($reports as &$report) {
        $report['target_type_text'] = get_target_type_text($report['target_type']);
        list($report['status_text'], $report['status_class']) = get_report_status_text($report['status']);
        
        // 获取目标的标题和链接
        switch ($report['target_type']) {
            case 'topic':
                $topic = get_topic($report['target_id']);
                $report['target_title'] = $topic ? $topic['title'] : '已删除的主题';
                $report['target_url'] = $topic ? "topic.php?id={$report['target_id']}" : '#';
                break;
                
            case 'reply':
                $reply = get_reply($report['target_id']);
                if ($reply) {
                    $report['target_title'] = mb_substr(strip_tags($reply['content']), 0, 50) . '...';
                    $report['target_url'] = "topic.php?id={$reply['topic_id']}#reply-{$report['target_id']}";
                } else {
                    $report['target_title'] = '已删除的回复';
                    $report['target_url'] = '#';
                }
                break;
                
            case 'user':
                $user = get_user_info($report['target_id']);
                $report['target_title'] = $user ? $user['username'] : '已删除的用户';
                $report['target_url'] = $user ? "profile.php?id={$report['target_id']}" : '#';
                break;
        }
    }
    
    return [
        'reports' => $reports,
        'total' => $total,
        'page' => $page,
        'per_page' => $per_page,
        'total_pages' => ceil($total / $per_page)
    ];
}

// 获取当前页的举报记录
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$reports_data = get_user_reports($current_user['user_id'], $page);

include('common/header.php');
?>
<title>我的举报记录 - <?= htmlspecialchars($site_info['site_title']) ?></title>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen flex flex-col overflow-x-hidden m-0 p-0">
    <div id="error-container" class="fixed top-4 left-1/2 transform -translate-x-1/2 z-50 max-w-2xl w-full bg-red-50 border border-red-200 text-red-700 p-4 rounded-lg shadow-lg hidden">
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <i class="fa fa-exclamation-circle text-xl"></i>
            </div>
            <div class="ml-3 flex-1">
                <h3 class="text-sm font-medium">操作失败</h3>
                <div class="mt-2 text-sm" id="error-details"></div>
                <div class="mt-4">
                    <button onclick="document.getElementById('error-container').classList.add('hidden')" class="inline-flex items-center px-3 py-1.5 border border-red-300 rounded-md text-sm font-medium text-red-700 bg-white hover:bg-red-50">
                        关闭
                    </button>
                </div>
            </div>
        </div>
    </div>

    <main class="flex-grow flex flex-col md:flex-row transition-all duration-300 pt-6" id="main-container">
        <?php include('common/asideleft.php'); ?>

        <div class="flex-grow transition-all duration-300 p-4 md:ml-64 lg:ml-72" id="content-area">
            <div class="max-w-4xl mx-auto">
                <div class="bg-white rounded-xl shadow-card p-5 mb-6">
                    <div class="flex items-center justify-between mb-6">
                        <h1 class="text-xl font-bold">我的举报记录</h1>
                        <a href="index.php" class="text-sm text-slate-500 hover:text-primary transition-colors">
                            <i class="fa fa-home mr-1"></i>返回首页
                        </a>
                    </div>
                    
                    <?php if (empty($reports_data['reports'])): ?>
                        <div class="text-center py-12">
                            <div class="w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fa fa-flag-o text-slate-400 text-2xl"></i>
                            </div>
                            <h3 class="text-lg font-medium text-slate-700 mb-2">暂无举报记录</h3>
                            <p class="text-slate-500 mb-6">您还没有提交过任何举报</p>
                            <a href="index.php" class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary-dark transition-colors">
                                <i class="fa fa-arrow-left mr-2"></i>返回首页
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200">
                                <thead>
                                    <tr>
                                        <th class="px-4 py-3 bg-slate-50 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">举报对象</th>
                                        <th class="px-4 py-3 bg-slate-50 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">举报原因</th>
                                        <th class="px-4 py-3 bg-slate-50 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">状态</th>
                                        <th class="px-4 py-3 bg-slate-50 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">举报时间</th>
                                        <th class="px-4 py-3 bg-slate-50 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">操作</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-slate-200">
                                    <?php foreach ($reports_data['reports'] as $report): ?>
                                    <tr>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-slate-900">
                                                <a href="<?= $report['target_url'] ?>" class="hover:text-primary transition-colors" <?= $report['target_url'] == '#' ? 'onclick="return false;"' : '' ?>>
                                                    <?= htmlspecialchars($report['target_title']) ?>
                                                </a>
                                            </div>
                                            <div class="text-xs text-slate-500">
                                                <?= $report['target_type_text'] ?>
                                            </div>
                                        </td>
                                        <td class="px-4 py-4">
                                            <div class="text-sm text-slate-900"><?= htmlspecialchars($report['reason']) ?></div>
                                            <?php if (!empty($report['description'])): ?>
                                            <div class="text-xs text-slate-500 mt-1 line-clamp-2">
                                                <?= htmlspecialchars($report['description']) ?>
                                            </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <span class="px-2 py-1 text-xs font-medium rounded-full <?= $report['status_class'] ?>">
                                                <?= $report['status_text'] ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-slate-500">
                                            <?= time_ago($report['created_at']) ?>
                                            <div class="text-xs mt-1">
                                                <?= date('Y-m-d H:i', strtotime($report['created_at'])) ?>
                                            </div>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm font-medium">
                                            <button class="view-report-details text-primary hover:text-primary-dark transition-colors" 
                                                    data-report-id="<?= $report['report_id'] ?>"
                                                    data-target-type="<?= $report['target_type_text'] ?>"
                                                    data-target-title="<?= htmlspecialchars($report['target_title']) ?>"
                                                    data-reason="<?= htmlspecialchars($report['reason']) ?>"
                                                    data-description="<?= htmlspecialchars($report['description']) ?>"
                                                    data-status="<?= $report['status_text'] ?>"
                                                    data-status-class="<?= $report['status_class'] ?>"
                                                    data-created-at="<?= date('Y-m-d H:i', strtotime($report['created_at'])) ?>">
                                                详情
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if ($reports_data['total_pages'] > 1): ?>
                        <div class="flex items-center justify-between px-4 py-3 sm:px-6 mt-4">
                            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                                <div>
                                    <p class="text-sm text-slate-700">
                                        显示第 <span class="font-medium"><?= (($reports_data['page'] - 1) * $reports_data['per_page']) + 1 ?></span> 到 
                                        <span class="font-medium"><?= min($reports_data['page'] * $reports_data['per_page'], $reports_data['total']) ?></span> 条，
                                        共 <span class="font-medium"><?= $reports_data['total'] ?></span> 条记录
                                    </p>
                                </div>
                                <div>
                                    <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                        <a href="my_reports.php?page=<?= max(1, $reports_data['page'] - 1) ?>" 
                                           class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-slate-300 bg-white text-sm font-medium text-slate-500 hover:bg-slate-50">
                                            <span class="sr-only">上一页</span>
                                            <i class="fa fa-chevron-left h-5 w-5"></i>
                                        </a>
                                        
                                        <?php for ($i = 1; $i <= $reports_data['total_pages']; $i++): ?>
                                            <?php if ($i == $reports_data['page']): ?>
                                            <a href="my_reports.php?page=<?= $i ?>" 
                                               aria-current="page"
                                               class="z-10 bg-primary text-white relative inline-flex items-center px-4 py-2 border border-primary text-sm font-medium">
                                                <?= $i ?>
                                            </a>
                                            <?php else: ?>
                                            <a href="my_reports.php?page=<?= $i ?>" 
                                               class="bg-white border-slate-300 text-slate-500 hover:bg-slate-50 relative inline-flex items-center px-4 py-2 border text-sm font-medium">
                                                <?= $i ?>
                                            </a>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                        
                                        <a href="my_reports.php?page=<?= min($reports_data['total_pages'], $reports_data['page'] + 1) ?>" 
                                           class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-slate-300 bg-white text-sm font-medium text-slate-500 hover:bg-slate-50">
                                            <span class="sr-only">下一页</span>
                                            <i class="fa fa-chevron-right h-5 w-5"></i>
                                        </a>
                                    </nav>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
    
    <div id="report-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-xl max-w-lg w-full mx-4 transform transition-all">
            <div class="px-6 py-4 border-b border-slate-200 flex justify-between items-center">
                <h3 class="text-lg font-medium text-slate-900">举报详情</h3>
                <button id="close-modal" class="text-slate-400 hover:text-slate-500">
                    <i class="fa fa-times"></i>
                </button>
            </div>
            <div class="px-6 py-4">
                <div class="mb-4">
                    <p class="text-sm font-medium text-slate-500 mb-1">举报对象</p>
                    <p id="modal-target-type" class="text-xs text-slate-500 mb-1"></p>
                    <p id="modal-target-title" class="text-sm text-slate-900 font-medium"></p>
                </div>
                <div class="mb-4">
                    <p class="text-sm font-medium text-slate-500 mb-1">举报原因</p>
                    <p id="modal-reason" class="text-sm text-slate-900"></p>
                </div>
                <div class="mb-4">
                    <p class="text-sm font-medium text-slate-500 mb-1">详细描述</p>
                    <p id="modal-description" class="text-sm text-slate-700 whitespace-pre-line"></p>
                </div>
                <div class="mb-4">
                    <p class="text-sm font-medium text-slate-500 mb-1">举报状态</p>
                    <p id="modal-status" class="text-sm"></p>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500 mb-1">举报时间</p>
                    <p id="modal-created-at" class="text-sm text-slate-700"></p>
                </div>
            </div>
            <div class="px-6 py-4 bg-slate-50 rounded-b-lg flex justify-end">
                <button id="close-modal-btn" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary-dark transition-colors">
                    关闭
                </button>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('report-modal');
        const closeModal = document.getElementById('close-modal');
        const closeModalBtn = document.getElementById('close-modal-btn');
        
        document.querySelectorAll('.view-report-details').forEach(button => {
            button.addEventListener('click', function() {
                const reportId = this.getAttribute('data-report-id');
                const targetType = this.getAttribute('data-target-type');
                const targetTitle = this.getAttribute('data-target-title');
                const reason = this.getAttribute('data-reason');
                const description = this.getAttribute('data-description');
                const status = this.getAttribute('data-status');
                const statusClass = this.getAttribute('data-status-class');
                const createdAt = this.getAttribute('data-created-at');
                
                document.getElementById('modal-target-type').textContent = targetType;
                document.getElementById('modal-target-title').textContent = targetTitle;
                document.getElementById('modal-reason').textContent = reason;
                document.getElementById('modal-description').textContent = description || '无';
                document.getElementById('modal-created-at').textContent = createdAt;
                
                const statusElement = document.getElementById('modal-status');
                statusElement.innerHTML = `<span class="px-2 py-1 text-xs font-medium rounded-full ${statusClass}">${status}</span>`;
                
                modal.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            });
        });
        
        function hideModal() {
            modal.classList.add('hidden');
            document.body.style.overflow = '';
        }
        
        closeModal.addEventListener('click', hideModal);
        closeModalBtn.addEventListener('click', hideModal);
        
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                hideModal();
            }
        });
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
                hideModal();
            }
        });
    });
    </script>

    <?php include('common/footer.php'); ?>
