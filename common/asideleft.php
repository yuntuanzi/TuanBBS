<style>
.nav-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.5rem;
    border-radius: 0.5rem;
    font-size: 0.875rem;
    font-weight: 500;
    transition: all 0.2s ease;
    text-decoration: none;
    background-color: white;
    color: #334155;
    border: 1px solid #e2e8f0;
}

.nav-btn:hover:not(.nav-btn-active) {
    background-color: #f8fafc;
}

.nav-btn-active {
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    border-bottom-width: 2px;
}

.nav-icon {
    width: 26px;
    height: 26px;
    margin-right: 4px;
    vertical-align: middle;
}

.nav-text {
    vertical-align: middle;
}

.unread-badge {
    margin-left: 4px;
    padding: 0.125rem 0.375rem;
    border-radius: 9999px;
    color: white;
    font-size: 0.75rem;
    font-weight: 500;
}

/* 移动端顶栏样式 */
.mobile-header {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    height: 60px;
    background: white;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    z-index: 50;
    padding: 0 16px;
    align-items: center;
    justify-content: space-between;
}

.mobile-header-logo {
    display: flex;
    align-items: center;
    gap: 8px;
}

.mobile-header-logo img {
    width: 36px;
    height: 36px;
    border-radius: 8px;
}

.mobile-header-title {
    font-weight: bold;
    font-size: 1.1rem;
    background: linear-gradient(to right, #4f46e5, #2563eb);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
}

.mobile-header-actions {
    display: flex;
    gap: 12px;
}

.mobile-menu-btn, .mobile-settings-btn {
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: #f1f5f9;
    color: #334155;
}

.mobile-menu-btn.active {
    background: #e0e7ff;
    color: #4f46e5;
}

.mobile-menu-panel {
    position: fixed;
    top: 60px;
    left: 0;
    right: 0;
    background: white;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    z-index: 40;
    padding: 16px;
    transform: translateY(-100%);
    opacity: 0;
    transition: all 0.3s ease;
    max-height: calc(100vh - 60px);
    overflow-y: auto;
}

.mobile-menu-panel.open {
    transform: translateY(0);
    opacity: 1;
}

.mobile-menu-section {
    margin-bottom: 16px;
}

.mobile-menu-section-title {
    font-size: 0.75rem;
    text-transform: uppercase;
    color: #64748b;
    margin-bottom: 8px;
    padding-left: 8px;
}

.mobile-menu-item {
    display: flex;
    align-items: center;
    padding: 12px 16px;
    border-radius: 8px;
    color: #334155;
    text-decoration: none;
}

.mobile-menu-item.active {
    background: #e0e7ff;
    color: #4f46e5;
    font-weight: 500;
}

.mobile-menu-item img {
    width: 24px;
    height: 24px;
    margin-right: 12px;
}

/* 移动端底栏样式 */
.mobile-footer {
    display: none;
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    height: 60px;
    background: white;
    box-shadow: 0 -2px 4px rgba(0, 0, 0, 0.1);
    z-index: 50;
    padding: 0 16px;
}

.mobile-footer-nav {
    display: flex;
    height: 100%;
    align-items: center;
    justify-content: space-around;
}

.mobile-footer-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    color: #64748b;
    font-size: 0.75rem;
    gap: 4px;
    position: relative;
}

.mobile-footer-item.active {
    color: #4f46e5;
}

.mobile-footer-item img {
    width: 24px;
    height: 24px;
}

.mobile-footer-badge {
    position: absolute;
    top: 0;
    right: -8px;
    background: #ef4444;
    color: white;
    border-radius: 50%;
    width: 16px;
    height: 16px;
    font-size: 0.625rem;
    display: flex;
    align-items: center;
    justify-content: center;
}

@media (max-width: 1023px) {
    #sidebar-container {
        display: none;
    }
    
    .mobile-header {
        display: flex;
    }
    
    .mobile-footer {
        display: block;
    }
    
    #content-area {
        margin-left: 0 !important;
        padding-top: 80px;
        padding-bottom: 80px;
    }
}

</style>

<!-- 电脑端侧边栏 -->
<div class="fixed inset-y-0 left-0 z-40 transition-all duration-300 ease-in-out" id="sidebar-container">
    <aside id="sidebar" class="absolute inset-y-0 left-0 w-64 lg:w-72 bg-gradient-to-b from-slate-50 to-white shadow-lg transform transition-transform duration-300 ease-in-out border-r border-slate-200/30 overflow-y-auto z-50">
        <div class="p-5 h-full flex flex-col">
            <!-- 站点标题区域 -->
            <div class="flex items-center gap-3 mb-3 pb-4 border-b border-slate-200/30">
                <a href="index.php" class="flex items-center gap-3">
                    <img src="<?= htmlspecialchars($site_info['site_logo'] ?? 'static/images/default-avatar.png') ?>" alt="<?= htmlspecialchars($site_info['site_title']) ?>Logo" class="w-12 h-12 rounded-lg object-cover shadow-sm border border-slate-100">
                    <div>
                        <h1 class="text-lg font-bold bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent"><?= htmlspecialchars($site_info['site_title']) ?></h1>
                        <p class="text-xs text-slate-500"><?= htmlspecialchars($site_info['site_subtitle']) ?></p>
                    </div>
                </a>
            </div>
            
            <?php 
            $current_user = get_logged_in_user();
            // 默认未读消息和通知数量
            $unread_messages = 0;
            $unread_notifications = 0;
            
            if ($current_user) {
                global $pdo;
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM private_messages WHERE receiver_id = ? AND is_read = 0");
                $stmt->execute([$current_user['user_id']]);
                $unread_messages = $stmt->fetchColumn();
                
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
                $stmt->execute([$current_user['user_id']]);
                $unread_notifications = $stmt->fetchColumn();
            }
            ?>
            
            <?php if ($current_user): ?>
            <!-- 用户卡片区域 -->
            <div class="mb-2 pb-4 border-b border-slate-200/30">
                <div class="flex items-center gap-3 p-3 bg-gradient-to-r from-indigo-50 to-blue-50 rounded-xl shadow-sm">
                    <div class="relative">
                        <img src="<?= htmlspecialchars($current_user['avatar_url'] ?? 'https://pic.星.fun/uploads/2025/07/29/22/6888e1c952773.jpg') ?>" alt="用户头像" class="w-12 h-12 rounded-full border-2 border-white shadow-md">
                        <span class="absolute -bottom-1 -right-1 w-4 h-4 rounded-full bg-emerald-500 border-2 border-white"></span>
                    </div>
                    <div class="flex-grow">
                        <p class="font-medium text-slate-800"><?= htmlspecialchars($current_user['username']) ?></p>
                        <p class="text-xs text-slate-500">
                            <?= $current_user['role'] === 'admin' ? '管理员' : ($current_user['role'] === 'moderator' ? '版主' : '正式会员') ?>
                        </p>
                    </div>
                </div>
                <div class="flex gap-2 mt-3">
                    <a href="profile.php?id=<?= $current_user['user_id'] ?>" class="nav-btn flex-1 
                        <?= basename($_SERVER['PHP_SELF']) == 'profile.php' 
                            ? 'nav-btn-active bg-indigo-100 text-indigo-700 border-indigo-200' 
                            : '' ?>">
                        <img src="static/icon/forum/工人.svg" alt="主页图标" class="nav-icon">
                        <span class="nav-text">主页</span>
                    </a>
                    <a href="settings.php" class="nav-btn flex-1 
                        <?= basename($_SERVER['PHP_SELF']) == 'settings.php' 
                            ? 'nav-btn-active bg-indigo-50 text-indigo-700 border-indigo-100' 
                            : '' ?>">
                        <img src="static/icon/forum/更多服务.svg" alt="设置图标" class="nav-icon">
                        <span class="nav-text">设置</span>
                    </a>
                </div>
                <div class="flex gap-2 mt-3">
                    <a href="messages.php" class="nav-btn flex-1 relative
                        <?= basename($_SERVER['PHP_SELF']) == 'messages.php' 
                            ? 'nav-btn-active bg-blue-50 text-blue-700 border-blue-100' 
                            : '' ?>" title="私信">
                        <img src="static/icon/forum/村委信箱.svg" alt="私信图标" class="nav-icon">
                        <span class="nav-text">私信</span>
                        <?php if ($unread_messages > 0): ?>
                        <span class="unread-badge bg-rose-500"><?= $unread_messages ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="notifications.php" class="nav-btn flex-1 relative
                        <?= basename($_SERVER['PHP_SELF']) == 'notifications.php' 
                            ? 'nav-btn-active bg-purple-50 text-purple-700 border-purple-100' 
                            : '' ?>" title="通知">
                        <img src="static/icon/forum/定位.svg" alt="通知图标" class="nav-icon">
                        <span class="nav-text">通知</span>
                        <?php if ($unread_notifications > 0): ?>
                        <span class="unread-badge bg-amber-500"><?= $unread_notifications ?></span>
                        <?php endif; ?>
                    </a>
                </div>
                <div class="mt-3">
                    <a href="logout.php" class="w-full flex items-center justify-center py-2 rounded-lg text-sm font-medium transition-all duration-200 bg-white text-rose-600 hover:bg-rose-50 border border-rose-200">
                        退出登录
                    </a>
                </div>
                <!-- 发布新帖按钮 -->
                <div class="mt-2">
                    <a href="create_topic.php" target="_blank" class="w-full flex items-center justify-center py-2 rounded-lg text-sm font-medium transition-all duration-200 bg-blue-500 text-white hover:bg-blue-800">
                        发布新帖
                    </a>
                </div>
            </div>
            <?php else: ?>
            <div class="mb-6 pb-4 border-b border-slate-200/30">
                <div class="flex gap-2">
                    <a href="login.php" class="flex-1 text-center py-2.5 rounded-lg text-sm font-medium transition-all duration-200
                        <?= basename($_SERVER['PHP_SELF']) == 'login.php' 
                            ? 'bg-blue-100 text-blue-700 shadow-sm border border-blue-200' 
                            : 'bg-white text-slate-700 hover:bg-slate-50 border border-slate-200' ?>">
                        <i class="fa fa-sign-in mr-1"></i>登录
                    </a>
                    <a href="register.php" class="flex-1 text-center py-2.5 rounded-lg text-sm font-medium transition-all duration-200 bg-gradient-to-r from-indigo-600 to-blue-600 text-white hover:from-indigo-700 hover:to-blue-700 shadow-sm hover:shadow-md">
                        <i class="fa fa-user-plus mr-1"></i>注册
                    </a>
                </div>
            </div>
            <?php endif; ?>
            <nav class="flex-grow">
                <ul class="space-y-1">
                    <li>
                        <a href="index.php" class="sidebar-item flex items-center px-3 py-2.5 rounded-lg text-slate-700 hover:bg-slate-100 transition-colors duration-200 
                            <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'bg-indigo-50 text-indigo-700 font-medium border border-indigo-100' : 'border border-transparent' ?>">
                            <img src="static/icon/forum/房屋.svg" alt="主页图标" width="32" height="32">
                            <span class="ml-2">首页</span>
                        </a>
                    </li>
                    <li class="pt-4 pb-1">
                        <p class="px-3 text-xs font-medium text-slate-500 uppercase tracking-wider">内容分类</p>
                    </li>
                    <?php foreach ($sections as $section): ?>
                    <li>
                        <a href="section.php?id=<?= $section['section_id'] ?>" class="sidebar-item flex items-center px-3 py-2.5 rounded-lg text-slate-700 hover:bg-slate-100 transition-colors duration-200
                            <?= (basename($_SERVER['PHP_SELF']) == 'section.php' && isset($_GET['id']) && $_GET['id'] == $section['section_id']) ? 'bg-indigo-50 text-indigo-700 font-medium border border-indigo-100' : 'border border-transparent' ?>">
                            <img src="<?= htmlspecialchars($section['icon'] ?? 'static/icon/forum/更多服务.svg') ?>" alt="<?= htmlspecialchars($section['name']) ?>板块图标" width="32" height="32">
                            <span class="ml-2"><?= htmlspecialchars($section['name']) ?></span>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </nav>
            <div class="pt-4 mt-4 border-t border-slate-200/30">
                <p class="text-xs text-center text-slate-500">
                    © 2025 <?= htmlspecialchars($site_info['site_title']) ?>
                </p>
            </div>
        </div>
    </aside>
    <div id="sidebar-backdrop" class="fixed inset-0 bg-black/30 backdrop-blur-sm z-40 hidden lg:hidden transition-opacity duration-300"></div>
</div>

<!-- 移动端顶栏 -->
<div class="mobile-header">
    <div class="mobile-header-logo">
        <img src="<?= htmlspecialchars($site_info['site_logo'] ?? 'static/images/default-avatar.png') ?>" alt="<?= htmlspecialchars($site_info['site_title']) ?>Logo">
        <span class="mobile-header-title"><?= htmlspecialchars($site_info['site_title']) ?></span>
    </div>
    <div class="mobile-header-actions">
        <button class="mobile-menu-btn" id="mobile-menu-btn">
            <i class="fa fa-bars"></i>
        </button>
        <a href="settings.php" class="mobile-settings-btn">
            <i class="fa fa-cog"></i>
        </a>
    </div>
</div>

<!-- 移动端菜单面板 -->
<div class="mobile-menu-panel" id="mobile-menu-panel">
    <div class="mobile-menu-section">
        <a href="index.php" class="mobile-menu-item <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>">
            <img src="static/icon/forum/房屋.svg" alt="首页">
            <span>首页</span>
        </a>
    </div>
    
    <div class="mobile-menu-section">
        <div class="mobile-menu-section-title">内容分类</div>
        <?php foreach ($sections as $section): ?>
        <a href="section.php?id=<?= $section['section_id'] ?>" class="mobile-menu-item <?= (basename($_SERVER['PHP_SELF']) == 'section.php' && isset($_GET['id']) && $_GET['id'] == $section['section_id']) ? 'active' : '' ?>">
            <img src="<?= htmlspecialchars($section['icon'] ?? 'static/icon/forum/更多服务.svg') ?>" alt="<?= htmlspecialchars($section['name']) ?>">
            <span><?= htmlspecialchars($section['name']) ?></span>
        </a>
        <?php endforeach; ?>
    </div>
    
    <?php if ($current_user): ?>
    <div class="mobile-menu-section">
        <div class="mobile-menu-section-title">个人中心</div>
        <a href="profile.php?id=<?= $current_user['user_id'] ?>" class="mobile-menu-item <?= basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : '' ?>">
            <img src="static/icon/forum/工人.svg" alt="个人主页">
            <span>个人主页</span>
        </a>
        <a href="messages.php" class="mobile-menu-item <?= basename($_SERVER['PHP_SELF']) == 'messages.php' ? 'active' : '' ?>">
            <img src="static/icon/forum/村委信箱.svg" alt="私信">
            <span>私信<?php if ($unread_messages > 0): ?><span class="mobile-footer-badge"><?= $unread_messages ?></span><?php endif; ?></span>
        </a>
        <a href="notifications.php" class="mobile-menu-item <?= basename($_SERVER['PHP_SELF']) == 'notifications.php' ? 'active' : '' ?>">
            <img src="static/icon/forum/定位.svg" alt="通知">
            <span>通知<?php if ($unread_notifications > 0): ?><span class="mobile-footer-badge"><?= $unread_notifications ?></span><?php endif; ?></span>
        </a>
        <a href="settings.php" class="mobile-menu-item <?= basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : '' ?>">
            <img src="static/icon/forum/更多服务.svg" alt="设置">
            <span>设置</span>
        </a>
    </div>
    <?php else: ?>
    <div class="mobile-menu-section">
        <div class="mobile-menu-section-title">账户</div>
        <a href="login.php" class="mobile-menu-item <?= basename($_SERVER['PHP_SELF']) == 'login.php' ? 'active' : '' ?>">
            <img src="static/icon/forum/工人.svg" alt="登录">
            <span>登录</span>
        </a>
        <a href="register.php" class="mobile-menu-item <?= basename($_SERVER['PHP_SELF']) == 'register.php' ? 'active' : '' ?>">
            <img src="static/icon/forum/更多服务.svg" alt="注册">
            <span>注册</span>
        </a>
    </div>
    <?php endif; ?>
</div>

<!-- 移动端底栏 -->
<div class="mobile-footer">
    <div class="mobile-footer-nav">
        <a href="index.php" class="mobile-footer-item <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>">
            <img src="static/icon/forum/房屋.svg" alt="首页">
            <span>首页</span>
        </a>
        <a href="messages.php" class="mobile-footer-item <?= basename($_SERVER['PHP_SELF']) == 'messages.php' ? 'active' : '' ?>">
            <img src="static/icon/forum/村委信箱.svg" alt="私信">
            <span>私信</span>
            <?php if ($unread_messages > 0): ?>
            <span class="mobile-footer-badge"><?= $unread_messages ?></span>
            <?php endif; ?>
        </a>
        <a href="create_topic.php" class="mobile-footer-item <?= basename($_SERVER['PHP_SELF']) == 'create_topic.php' ? 'active' : '' ?>">
            <img src="static/icon/forum/定位.svg" alt="发布">
            <span>发布</span>
        </a>
        <a href="notifications.php" class="mobile-footer-item <?= basename($_SERVER['PHP_SELF']) == 'notifications.php' ? 'active' : '' ?>">
            <img src="static/icon/forum/更多服务.svg" alt="通知">
            <span>通知</span>
            <?php if ($unread_notifications > 0): ?>
            <span class="mobile-footer-badge"><?= $unread_notifications ?></span>
            <?php endif; ?>
        </a>
        <?php if ($current_user): ?>
        <a href="profile.php?id=<?= $current_user['user_id'] ?>" class="mobile-footer-item <?= basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : '' ?>">
            <img src="static/icon/forum/工人.svg" alt="我的">
            <span>我的</span>
        </a>
        <?php else: ?>
        <a href="login.php" class="mobile-footer-item <?= basename($_SERVER['PHP_SELF']) == 'login.php' ? 'active' : '' ?>">
            <img src="static/icon/forum/工人.svg" alt="登录">
            <span>登录</span>
        </a>
        <?php endif; ?>
    </div>
</div>

<script>
// 移动端菜单切换
document.getElementById('mobile-menu-btn').addEventListener('click', function() {
    this.classList.toggle('active');
    document.getElementById('mobile-menu-panel').classList.toggle('open');
});

// 点击菜单项关闭菜单
document.querySelectorAll('.mobile-menu-item').forEach(item => {
    item.addEventListener('click', function() {
        document.getElementById('mobile-menu-btn').classList.remove('active');
        document.getElementById('mobile-menu-panel').classList.remove('open');
    });
});
</script>