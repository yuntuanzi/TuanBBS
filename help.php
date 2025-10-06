<?php
require_once 'common/functions.php';
?>

<?php include 'common/header.php'; ?>
<title>帮助中心 - <?= htmlspecialchars($site_info['site_title']) ?></title>
<meta name="keywords" content="<?= htmlspecialchars($site_info['site_title']) ?>, <?= htmlspecialchars($site_info['site_title']) ?>帮助中心, 求助<?= htmlspecialchars($site_info['site_title']) ?>, 反馈<?= htmlspecialchars($site_info['site_title']) ?>, <?= htmlspecialchars($site_info['site_title']) ?>问题">
<meta name="description" content="这里包含了使用<?= htmlspecialchars($site_info['site_title']) ?>时可能遇到的常见问题，希望对你有帮助~">
</head>
<body class="bg-gray-50 text-gray-800 min-h-screen flex flex-col">

    <?php include 'common/asideleft.php'; ?>

    <main id="content-area" class="flex-1 transition-all duration-300">
        <div class="container mx-auto px-4 py-12">
            <div class="mb-10 text-center">
                <h1 class="text-[clamp(1.8rem,4vw,2.5rem)] font-bold text-gray-800 mb-3">帮助中心</h1>
                <p class="text-gray-600 max-w-2xl mx-auto">找到您需要的答案，解决您在使用本站时遇到的问题</p>
                
                <div class="mt-6 md:hidden relative max-w-md mx-auto">
                    <input type="text" placeholder="搜索帮助文档..." class="w-full pl-10 pr-4 py-2 rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all">
                    <i class="fa fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                </div>
            </div>

            <div id="card-container" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-12 max-w-5xl mx-auto">
                <div class="help-card bg-white rounded-xl shadow-card hover:shadow-card-hover transition-all duration-300 p-6 group cursor-pointer" data-target="account">
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mb-4 group-hover:bg-primary group-hover:text-white transition-colors">
                        <i class="fa fa-user text-xl text-primary group-hover:text-white"></i>
                    </div>
                    <h3 class="text-xl font-semibold mb-2">账号相关</h3>
                    <p class="text-gray-600">注册、登录、密码找回及账号安全等问题</p>
                </div>
                
                <div class="help-card bg-white rounded-xl shadow-card hover:shadow-card-hover transition-all duration-300 p-6 group cursor-pointer" data-target="posts">
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mb-4 group-hover:bg-success group-hover:text-white transition-colors">
                        <i class="fa fa-file-text text-xl text-success group-hover:text-white"></i>
                    </div>
                    <h3 class="text-xl font-semibold mb-2">发帖与回帖</h3>
                    <p class="text-gray-600">如何发布内容、回复他人及管理自己的帖子</p>
                </div>
                
                <div class="help-card bg-white rounded-xl shadow-card hover:shadow-card-hover transition-all duration-300 p-6 group cursor-pointer" data-target="features">
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mb-4 group-hover:bg-purple-600 group-hover:text-white transition-colors">
                        <i class="fa fa-cogs text-xl text-purple-600 group-hover:text-white"></i>
                    </div>
                    <h3 class="text-xl font-semibold mb-2">功能使用</h3>
                    <p class="text-gray-600">本站各项功能的详细使用说明和技巧</p>
                </div>
                
                <div class="help-card bg-white rounded-xl shadow-card hover:shadow-card-hover transition-all duration-300 p-6 group cursor-pointer" data-target="rules">
                    <div class="w-12 h-12 bg-amber-100 rounded-lg flex items-center justify-center mb-4 group-hover:bg-warning group-hover:text-white transition-colors">
                        <i class="fa fa-gavel text-xl text-warning group-hover:text-white"></i>
                    </div>
                    <h3 class="text-xl font-semibold mb-2">社区规则</h3>
                    <p class="text-gray-600">了解社区规范、发帖准则及违规处理办法</p>
                </div>
                
                <div class="help-card bg-white rounded-xl shadow-card hover:shadow-card-hover transition-all duration-300 p-6 group cursor-pointer" data-target="privacy">
                    <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center mb-4 group-hover:bg-red-500 group-hover:text-white transition-colors">
                        <i class="fa fa-shield text-xl text-red-500 group-hover:text-white"></i>
                    </div>
                    <h3 class="text-xl font-semibold mb-2">隐私与安全</h3>
                    <p class="text-gray-600">如何保护个人信息及账号安全设置</p>
                </div>
                
                <div class="help-card bg-white rounded-xl shadow-card hover:shadow-card-hover transition-all duration-300 p-6 group cursor-pointer" data-target="contact">
                    <div class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center mb-4 group-hover:bg-indigo-600 group-hover:text-white transition-colors">
                        <i class="fa fa-envelope text-xl text-indigo-600 group-hover:text-white"></i>
                    </div>
                    <h3 class="text-xl font-semibold mb-2">联系我们</h3>
                    <p class="text-gray-600">获取进一步帮助或反馈问题的方式</p>
                </div>
            </div>

            <div id="help-content-container" class="max-w-3xl mx-auto opacity-0 transition-opacity duration-300 hidden">
                <button id="close-help" class="mb-6 inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg font-medium hover:bg-primary/90 transition-colors shadow-sm">
                    <i class="fa fa-arrow-left mr-2"></i> 返回帮助分类
                </button>
                
                <section id="account" class="help-content mb-12 scroll-mt-24 hidden">
                    <h2 class="text-2xl font-bold mb-6 pb-2 border-b border-gray-200 flex items-center">
                        <i class="fa fa-user text-primary mr-3"></i>账号相关
                    </h2>
                    
                    <div class="space-y-6">
                        <div class="bg-white rounded-xl shadow-sm p-5 hover:shadow-md transition-shadow">
                            <h3 class="text-lg font-semibold mb-2 flex items-start">
                                <span class="bg-primary/10 text-primary rounded-full w-6 h-6 flex items-center justify-center mr-2 mt-0.5 flex-shrink-0">1</span>
                                如何注册本站账号？
                            </h3>
                            <div class="pl-8">
                                <p class="text-gray-700">点击网站左上角的"注册"按钮，填写用户名、QQ邮箱和密码，完成验证后即可注册。注册成功后，您将收到一封验证邮件，请点击邮件中的链接激活您的账号。</p>
                                <p class="text-gray-700 mt-2">如果您未收到验证邮件，请检查垃圾邮件文件夹。</p>
                            </div>
                        </div>
                        
                        <div class="bg-white rounded-xl shadow-sm p-5 hover:shadow-md transition-shadow">
                            <h3 class="text-lg font-semibold mb-2 flex items-start">
                                <span class="bg-primary/10 text-primary rounded-full w-6 h-6 flex items-center justify-center mr-2 mt-0.5 flex-shrink-0">2</span>
                                忘记密码了怎么办？
                            </h3>
                            <div class="pl-8">
                                <p class="text-gray-700">在登录页面点击"忘记密码"链接，输入您注册时使用的电子邮箱，系统将发送验证码到您的邮箱。邮箱验证成功后，您可以设置新的密码。</p>
                                <p class="text-gray-700 mt-2">如遇问题，请<a href="contact.php" class="text-primary hover:underline">联系客服</a>。</p>
                            </div>
                        </div>
                        
                        <div class="bg-white rounded-xl shadow-sm p-5 hover:shadow-md transition-shadow">
                            <h3 class="text-lg font-semibold mb-2 flex items-start">
                                <span class="bg-primary/10 text-primary rounded-full w-6 h-6 flex items-center justify-center mr-2 mt-0.5 flex-shrink-0">3</span>
                                可以修改用户名吗？
                            </h3>
                            <div class="pl-8">
                                <p class="text-gray-700">您可以在"个人主页" -> 点击"编辑资料"进行修改。</p>
                                <p class="text-gray-700 mt-2">用户名修改后，您的历史发帖和回复将显示新用户名，但用户ID保持不变。</p>
                            </div>
                        </div>
                        
                        <div class="bg-white rounded-xl shadow-sm p-5 hover:shadow-md transition-shadow">
                            <h3 class="text-lg font-semibold mb-2 flex items-start">
                                <span class="bg-primary/10 text-primary rounded-full w-6 h-6 flex items-center justify-center mr-2 mt-0.5 flex-shrink-0">4</span>
                                如何注销我的账号？
                            </h3>
                            <div class="pl-8">
                                <p class="text-gray-700">账号注销需要谨慎操作，注销后所有数据将无法恢复。您可以在"个人设置" -> "账号安全"中找到注销选项，按照提示完成注销流程。注销申请提交后，将立即停用账号。请注意！邮箱和用户名不会被释放。</p>
                            </div>
                        </div>
                    </div>
                </section>
                
                <section id="posts" class="help-content mb-12 scroll-mt-24 hidden">
                    <h2 class="text-2xl font-bold mb-6 pb-2 border-b border-gray-200 flex items-center">
                        <i class="fa fa-file-text text-success mr-3"></i>发帖与回帖
                    </h2>
                    
                    <div class="space-y-6">
                        <div class="bg-white rounded-xl shadow-sm p-5 hover:shadow-md transition-shadow">
                            <h3 class="text-lg font-semibold mb-2 flex items-start">
                                <span class="bg-success/10 text-success rounded-full w-6 h-6 flex items-center justify-center mr-2 mt-0.5 flex-shrink-0">1</span>
                                如何发布新主题？
                            </h3>
                            <div class="pl-8">
                                <p class="text-gray-700">您可以点击页面左侧边栏的"发布新帖子"按钮，填写标题和内容。发布前请检查内容是否符合版块主题和社区规则。</p>
                                <p class="text-gray-700 mt-2">如遇发布失败，请检查内容是否包含敏感词或超出长度限制。</p>
                            </div>
                        </div>
                        
                        <div class="bg-white rounded-xl shadow-sm p-5 hover:shadow-md transition-shadow">
                            <h3 class="text-lg font-semibold mb-2 flex items-start">
                                <span class="bg-success/10 text-success rounded-full w-6 h-6 flex items-center justify-center mr-2 mt-0.5 flex-shrink-0">2</span>
                                如何编辑或删除自己的帖子？
                            </h3>
                            <div class="pl-8">
                                <p class="text-gray-700">在您发布的帖子下方，有"编辑"和"删除"按钮。</p>
                                <p class="text-gray-700">在您的个人主页也可以找到您发布的帖子，有“删除”按钮</p>
                                <p class="text-gray-700 mt-2">重要提示：删除带有大量回复的主题可能影响社区讨论连续性，建议编辑说明而非删除。如需紧急删除，请<a href="contact.php" class="text-primary hover:underline">联系管理员</a>。</p>
                            </div>
                        </div>
                        
                        <div class="bg-white rounded-xl shadow-sm p-5 hover:shadow-md transition-shadow">
                            <h3 class="text-lg font-semibold mb-2 flex items-start">
                                <span class="bg-success/10 text-success rounded-full w-6 h-6 flex items-center justify-center mr-2 mt-0.5 flex-shrink-0">3</span>
                                如何添加图片到帖子中？
                            </h3>
                            <div class="pl-8">
                                <p class="text-gray-700">在编辑器工具栏中点击图片图标，您可以选择上传图片。本站对单张图片大小限制为5MB，支持JPG、PNG、GIF格式。建议上传前压缩图片以提高加载速度。</p>
                                <p class="text-gray-700 mt-2">图片上传后会自动进行安全检测，违规图片将被系统自动屏蔽。</p>
                            </div>
                        </div>
                        
                        <div class="bg-white rounded-xl shadow-sm p-5 hover:shadow-md transition-shadow">
                            <h3 class="text-lg font-semibold mb-2 flex items-start">
                                <span class="bg-success/10 text-success rounded-full w-6 h-6 flex items-center justify-center mr-2 mt-0.5 flex-shrink-0">4</span>
                                如何引用他人回复？
                            </h3>
                            <div class="pl-8">
                                <p class="text-gray-700">在他人回复的下方，点击"回复"按钮。</p>
                            </div>
                        </div>
                    </div>
                </section>
                
                <section id="features" class="help-content mb-12 scroll-mt-24 hidden">
                    <h2 class="text-2xl font-bold mb-6 pb-2 border-b border-gray-200 flex items-center">
                        <i class="fa fa-cogs text-purple-600 mr-3"></i>功能使用
                    </h2>
                    
                    <div class="space-y-6">
                        <div class="bg-white rounded-xl shadow-sm p-5 hover:shadow-md transition-shadow">
                            <h3 class="text-lg font-semibold mb-2 flex items-start">
                                <span class="bg-purple-100 text-purple-600 rounded-full w-6 h-6 flex items-center justify-center mr-2 mt-0.5 flex-shrink-0">1</span>
                                如何使用私信功能？
                            </h3>
                            <div class="pl-8">
                                <p class="text-gray-700">点击左侧边栏的“私信”按钮，或在他人主页点击“私信”按钮。您可以向任何已注册用户发送私信，但请注意不要发送垃圾信息或骚扰内容。</p>
                            </div>
                        </div>
                        
                        <div class="bg-white rounded-xl shadow-sm p-5 hover:shadow-md transition-shadow">
                            <h3 class="text-lg font-semibold mb-2 flex items-start">
                                <span class="bg-purple-100 text-purple-600 rounded-full w-6 h-6 flex items-center justify-center mr-2 mt-0.5 flex-shrink-0">2</span>
                                如何关注其他用户？
                            </h3>
                            <div class="pl-8">
                                <p class="text-gray-700">在用户的个人主页点击"关注"按钮，即可关注该用户。</p>
                            </div>
                        </div>
                        
                        <div class="bg-white rounded-xl shadow-sm p-5 hover:shadow-md transition-shadow">
                            <h3 class="text-lg font-semibold mb-2 flex items-start">
                                <span class="bg-purple-100 text-purple-600 rounded-full w-6 h-6 flex items-center justify-center mr-2 mt-0.5 flex-shrink-0">3</span>
                                如何使用本站搜索功能？
                            </h3>
                            <div class="pl-8">
                                <p class="text-gray-700">使用页面中的搜索框，输入关键词即可搜索本站内容。您可以使用高级搜索选项，按版块、类型、时间范围等条件筛选结果。搜索支持关键词高亮显示和相关度排序。</p>
                            </div>
                        </div>
                        
                        <div class="bg-white rounded-xl shadow-sm p-5 hover:shadow-md transition-shadow">
                            <h3 class="text-lg font-semibold mb-2 flex items-start">
                                <span class="bg-purple-100 text-purple-600 rounded-full w-6 h-6 flex items-center justify-center mr-2 mt-0.5 flex-shrink-0">4</span>
                                如何设置个人资料和头像？
                            </h3>
                            <div class="pl-8">
                                <p class="text-gray-700">在"个人主页"页面可以编辑个人简介、用户名等信息。在"头像设置"区域可以上传或更换您的头像图片。</p>
                                <p class="text-gray-700 mt-2">头像图片支持JPG、PNG格式，建议尺寸为200x200像素，文件大小不超过2MB。</p>
                            </div>
                        </div>
                        
                        <div class="bg-white rounded-xl shadow-sm p-5 hover:shadow-md transition-shadow">
                            <h3 class="text-lg font-semibold mb-2 flex items-start">
                                <span class="bg-purple-100 text-purple-600 rounded-full w-6 h-6 flex items-center justify-center mr-2 mt-0.5 flex-shrink-0">5</span>
                                如何使用点赞功能？
                            </h3>
                            <div class="pl-8">
                                <p class="text-gray-700">在帖子详情页左上角或右下角菜单，点击"点赞"按钮可以将该帖子添加到您的点赞列表。您可以在"个人中心" -> "我的点赞"中查看所有点赞的帖子。</p>
                            </div>
                        </div>
                    </div>
                </section>
                
                <section id="rules" class="help-content mb-12 scroll-mt-24 hidden">
                    <h2 class="text-2xl font-bold mb-6 pb-2 border-b border-gray-200 flex items-center">
                        <i class="fa fa-gavel text-warning mr-3"></i>社区规则
                    </h2>
                    
                    <div class="space-y-6">
                        <div class="bg-white rounded-xl shadow-sm p-5 hover:shadow-md transition-shadow">
                            <h3 class="text-lg font-semibold mb-2 flex items-start">
                                <span class="bg-warning/10 text-warning rounded-full w-6 h-6 flex items-center justify-center mr-2 mt-0.5 flex-shrink-0">1</span>
                                哪些内容是禁止发布的？
                            </h3>
                            <div class="pl-8">
                                <p class="text-gray-700 mb-2">本站禁止发布以下内容：</p>
                                <ul class="list-disc pl-5 text-gray-700 space-y-1">
                                    <li>违反国家法律法规的内容</li>
                                    <li>涉及色情、暴力、恐怖的内容</li>
                                    <li>人身攻击、诽谤、侮辱他人的内容</li>
                                    <li>垃圾广告、传销信息</li>
                                    <li>与版块主题无关的内容</li>
                                    <li>重复发布的内容</li>
                                </ul>
                            </div>
                        </div>
                        
                        <div class="bg-white rounded-xl shadow-sm p-5 hover:shadow-md transition-shadow">
                            <h3 class="text-lg font-semibold mb-2 flex items-start">
                                <span class="bg-warning/10 text-warning rounded-full w-6 h-6 flex items-center justify-center mr-2 mt-0.5 flex-shrink-0">2</span>
                                违规内容会受到什么处理？
                            </h3>
                            <div class="pl-8">
                                <p class="text-gray-700">根据违规情节轻重，管理员可能会采取以下措施：</p>
                                <ul class="list-disc pl-5 text-gray-700 space-y-1 mt-2">
                                    <li>删除违规内容</li>
                                    <li>警告用户</li>
                                    <li>永久封禁账号</li>
                                </ul>
                                <p class="text-gray-700 mt-2">如对处理结果有异议，可通过<a href="contact.php" class="text-primary hover:underline">申诉渠道</a>提交申诉。</p>
                            </div>
                        </div>
                        
                        <div class="bg-white rounded-xl shadow-sm p-5 hover:shadow-md transition-shadow">
                            <h3 class="text-lg font-semibold mb-2 flex items-start">
                                <span class="bg-warning/10 text-warning rounded-full w-6 h-6 flex items-center justify-center mr-2 mt-0.5 flex-shrink-0">3</span>
                                如何举报违规内容？
                            </h3>
                            <div class="pl-8">
                                <p class="text-gray-700">在每个帖子或回复的下方，有"举报"按钮。点击后选择举报原因并提交，管理员会尽快处理您的举报。</p>
                                <p class="text-gray-700 mt-2">恶意举报也会受到处罚，请确保您的举报基于事实。</p>
                            </div>
                        </div>
                    </div>
                </section>
                
                <section id="privacy" class="help-content mb-12 scroll-mt-24 hidden">
                    <h2 class="text-2xl font-bold mb-6 pb-2 border-b border-gray-200 flex items-center">
                        <i class="fa fa-shield text-red-500 mr-3"></i>隐私与安全
                    </h2>
                    
                    <div class="space-y-6">
                        <div class="bg-white rounded-xl shadow-sm p-5 hover:shadow-md transition-shadow">
                            <h3 class="text-lg font-semibold mb-2 flex items-start">
                                <span class="bg-red-100 text-red-500 rounded-full w-6 h-6 flex items-center justify-center mr-2 mt-0.5 flex-shrink-0">1</span>
                                如何保护我的账号安全？
                            </h3>
                            <div class="pl-8">
                                <p class="text-gray-700 mb-2">保护账号安全的建议：</p>
                                <ul class="list-disc pl-5 text-gray-700 space-y-1">
                                    <li>使用复杂密码，包含字母、数字和特殊字符</li>
                                    <li>定期更换密码，不要在多个平台使用相同密码</li>
                                    <li>不要向他人泄露账号信息</li>
                                    <li>在公共网络环境下注意保护账号安全</li>
                                </ul>
                            </div>
                        </div>
                        
                        <div class="bg-white rounded-xl shadow-sm p-5 hover:shadow-md transition-shadow">
                            <h3 class="text-lg font-semibold mb-2 flex items-start">
                                <span class="bg-red-100 text-red-500 rounded-full w-6 h-6 flex items-center justify-center mr-2 mt-0.5 flex-shrink-0">2</span>
                                本站如何使用我的个人信息？
                            </h3>
                            <div class="pl-8">
                                <p class="text-gray-700">我们严格按照隐私政策使用您的个人信息，主要用于：</p>
                                <ul class="list-disc pl-5 text-gray-700 space-y-1 mt-2">
                                    <li>提供和维护本站服务</li>
                                    <li>改进和优化用户体验</li>
                                    <li>确保服务安全和防止欺诈</li>
                                    <li>向您发送重要通知</li>
                                </ul>
                                <p class="text-gray-700 mt-2">我们不会在未经您许可的情况下向第三方分享您的个人信息。
                            </div>
                        </div>
                        
                        <div class="bg-white rounded-xl shadow-sm p-5 hover:shadow-md transition-shadow">
                            <h3 class="text-lg font-semibold mb-2 flex items-start">
                                <span class="bg-red-100 text-red-500 rounded-full w-6 h-6 flex items-center justify-center mr-2 mt-0.5 flex-shrink-0">4</span>
                                发现账号异常怎么办？
                            </h3>
                            <div class="pl-8">
                                <p class="text-gray-700">如发现账号有异常登录或操作，请立即：</p>
                                <ol class="list-decimal pl-5 text-gray-700 space-y-1 mt-2">
                                    <li>修改账号密码，使用高强度新密码</li>
                                </ol>
                                <p class="text-gray-700 mt-2">如账号已被盗用，请立即通过<a href="contact.php" class="text-primary hover:underline">紧急通道</a>联系我们冻结账号。</p>
                            </div>
                        </div>
                    </div>
                </section>
                
                <section id="contact" class="help-content mb-12 scroll-mt-24 hidden bg-gradient-to-r from-blue-50 to-indigo-50 rounded-2xl p-6">
                    <h2 class="text-2xl font-bold mb-4 flex items-center">
                        <i class="fa fa-envelope text-indigo-600 mr-3"></i>仍需帮助？
                    </h2>
                    <p class="text-gray-700 mb-6">如果您没有找到所需的答案，可以通过以下方式联系我们：</p>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <a href="contact.php" class="bg-white rounded-lg p-4 shadow-sm hover:shadow-md transition-shadow flex items-center">
                            <div class="w-10 h-10 bg-indigo-100 rounded-full flex items-center justify-center mr-4">
                                <i class="fa fa-comments text-indigo-600"></i>
                            </div>
                            <div>
                                <h3 class="font-medium">联系客服</h3>
                                <p class="text-sm text-gray-600">发送消息给客服团队</p>
                            </div>
                        </a>
                        
                        <a href="contact.php" class="bg-white rounded-lg p-4 shadow-sm hover:shadow-md transition-shadow flex items-center">
                            <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center mr-4">
                                <i class="fa fa-lightbulb-o text-blue-600"></i>
                            </div>
                            <div>
                                <h3 class="font-medium">提交反馈</h3>
                                <p class="text-sm text-gray-600">提出建议或报告问题</p>
                            </div>
                        </a>
                        
                        <a href="contact.php" class="bg-white rounded-lg p-4 shadow-sm hover:shadow-md transition-shadow flex items-center">
                            <div class="w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center mr-4">
                                <i class="fa fa-life-ring text-purple-600"></i>
                            </div>
                            <div>
                                <h3 class="font-medium">在线支持</h3>
                                <p class="text-sm text-gray-600">工作时间：周一至周五 9:00-18:00</p>
                            </div>
                        </a>
                    </div>
                    
                    <div class="mt-6 bg-white rounded-lg p-5 shadow-sm">
                        <h3 class="font-medium mb-3">常见问题快速解答</h3>
                        <div class="space-y-3">
                            <div>
                                <p class="font-medium text-gray-800">客服响应时间？</p>
                                <p class="text-gray-600 mt-1">工作时间内一般会在24小时内回复，非工作时间可能延迟至下一个工作日。</p>
                            </div>
                            <div>
                                <p class="font-medium text-gray-800">账号申诉处理周期？</p>
                                <p class="text-gray-600 mt-1">账号相关申诉通常在3-5个工作日内处理完毕，处理结果会通过站内信通知。</p>
                            </div>
                            <div>
                                <p class="font-medium text-gray-800">如何反馈BUG？</p>
                                <p class="text-gray-600 mt-1">请在"提交反馈"页面详细描述问题现象、复现步骤及截图，这将帮助我们更快解决问题。</p>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const helpCards = document.querySelectorAll('.help-card');
            const helpContents = document.querySelectorAll('.help-content');
            const helpContainer = document.getElementById('help-content-container');
            const closeButton = document.getElementById('close-help');
            const cardContainer = document.getElementById('card-container');
            
            const urlParams = new URLSearchParams(window.location.search);
            const helpTopic = urlParams.get('topic');
            
            if (helpTopic && document.getElementById(helpTopic)) {
                showHelpContent(helpTopic);
            }
            
            helpCards.forEach(card => {
                card.addEventListener('click', function() {
                    const targetId = this.getAttribute('data-target');
                    showHelpContent(targetId);
                    
                    const newParams = new URLSearchParams(window.location.search);
                    newParams.set('topic', targetId);
                    window.history.pushState({}, '', `${window.location.pathname}?${newParams.toString()}`);
                });
            });
            
            closeButton.addEventListener('click', function() {
                helpContainer.classList.add('opacity-0');
                
                setTimeout(() => {
                    helpContainer.classList.add('hidden');
                    cardContainer.classList.remove('hidden');
                    
                    cardContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    
                    window.history.pushState({}, '', window.location.pathname);
                }, 300);
            });
            
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const targetId = this.getAttribute('href').substring(1);
                    
                    if (targetId && document.getElementById(targetId)) {
                        showHelpContent(targetId);
                        
                        const newParams = new URLSearchParams(window.location.search);
                        newParams.set('topic', targetId);
                        window.history.pushState({}, '', `${window.location.pathname}?${newParams.toString()}`);
                        
                        setTimeout(() => {
                            document.getElementById(targetId).scrollIntoView({ behavior: 'smooth', block: 'start' });
                        }, 100);
                    }
                });
            });
            
            function showHelpContent(targetId) {
                helpContents.forEach(content => {
                    content.classList.add('hidden');
                });
                
                const targetContent = document.getElementById(targetId);
                if (targetContent) {
                    targetContent.classList.remove('hidden');
                    
                    cardContainer.classList.add('hidden');
                    helpContainer.classList.remove('hidden');
                    
                    setTimeout(() => {
                        helpContainer.classList.remove('opacity-0');
                    }, 50);
                    
                    setTimeout(() => {
                        helpContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }, 100);
                }
            }
        });
    </script>
    <?php include 'common/footer.php'; ?>