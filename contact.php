<?php
require_once 'common/functions.php';
$siteInfo = get_site_info();
?>

<?php include 'common/header.php'; ?>
<title>联系我们 - <?= htmlspecialchars($site_info['site_title']) ?></title>
<meta name="keywords" content="<?= htmlspecialchars($site_info['site_title']) ?>, 联系我们, <?= htmlspecialchars($site_info['site_title']) ?>客服, 反馈<?= htmlspecialchars($site_info['site_title']) ?>, 建议<?= htmlspecialchars($site_info['site_title']) ?>">
<meta name="description" content="若您在使用<?= htmlspecialchars($site_info['site_title']) ?>时遇到任何问题，都可以与我们取得联系~">
</head>
<body class="bg-gray-50 text-gray-800 min-h-screen flex flex-col">
    <?php include 'common/asideleft.php'; ?>

    <main id="content-area" class="flex-1 transition-all duration-300">
        <div class="container mx-auto px-4 py-12">
            <div class="mb-10 text-center">
                <h1 class="text-[clamp(1.8rem,4vw,2.5rem)] font-bold text-gray-800 mb-3">联系我们</h1>
                <p class="text-gray-600 max-w-2xl mx-auto">有任何问题、建议或反馈？请通过以下方式与我们联系，我们会尽快回复您</p>
            </div>

            <div class="max-w-5xl mx-auto">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
                    <div class="bg-white rounded-xl shadow-card p-6 text-center hover:shadow-card-hover transition-all duration-300">
                        <div class="w-14 h-14 bg-blue-100 rounded-full flex items-center justify-center mb-4 mx-auto">
                            <i class="fa fa-envelope text-2xl text-primary"></i>
                        </div>
                        <h3 class="text-lg font-semibold mb-2">电子邮件</h3>
                        <p class="text-gray-600 mb-3">发送邮件给我们的客服团队</p>
                        <a href="mailto:<?php echo htmlspecialchars($siteInfo['webmaster_email'] ?? 'contact@example.com'); ?>" class="text-primary hover:underline inline-flex items-center">
                            <?php echo htmlspecialchars($siteInfo['webmaster_email'] ?? 'contact@example.com'); ?>
                        </a>
                    </div>

                    <div class="bg-white rounded-xl shadow-card p-6 text-center hover:shadow-card-hover transition-all duration-300">
                        <div class="w-14 h-14 bg-green-100 rounded-full flex items-center justify-center mb-4 mx-auto">
                            <i class="fa fa-qq text-2xl text-success"></i>
                        </div>
                        <h3 class="text-lg font-semibold mb-2">QQ 联系</h3>
                        <p class="text-gray-600 mb-3">添加我们的QQ获取帮助</p>
                        <a class="text-success hover:underline inline-flex items-center">
                            <?php echo htmlspecialchars($siteInfo['webmaster_qq'] ?? '未填写'); ?>
                        </a>
                    </div>

                    <div class="bg-white rounded-xl shadow-card p-6 text-center hover:shadow-card-hover transition-all duration-300">
                        <div class="w-14 h-14 bg-purple-100 rounded-full flex items-center justify-center mb-4 mx-auto">
                            <i class="fa fa-clock-o text-2xl text-purple-600"></i>
                        </div>
                        <h3 class="text-lg font-semibold mb-2">通过站内信</h3>
                        <p class="text-gray-600 mb-3">通过站内私信联系管理员获得帮助</p>
                        <a href="messages.php?conversation=1" class="text-primary hover:underline inline-flex items-center">
                            点我跳转
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include 'common/footer.php'; ?>