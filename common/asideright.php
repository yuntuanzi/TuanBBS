<aside class="hidden lg:block w-[28rem] shrink-0 pl-4 pr-8 py-6 mr-8 transition-all duration-300" id="right-sidebar">
    <div class="bg-white rounded-2xl p-5 mb-5 border-l-4 border-accent">
        <h3 class="text-lg font-bold mb-3 flex items-center">
            <img src="static/icon/forum/公告.svg" alt="公告图标" width="26" height="26">
            &nbsp;
            站点公告
        </h3>
        <div class="text-slate-700 text-sm leading-relaxed">
            <?= htmlspecialchars($site_info['site_announcement'] ?? '请在后台添加公告') ?>
        </div>
        <div class="mt-3 pt-3 border-t border-slate-100 text-xs text-slate-500">
            <i class="fa fa-clock-o mr-1"></i>
            最后更新: <?= time_ago($site_info['updated_at']) ?>
        </div>
    </div>

    <div class="rounded-2xl mb-5 overflow-hidden transform transition-all duration-500">
        <h3 class="text-lg font-bold mb-4 pb-2 border-b border-slate-100 flex items-center group">
            <i class="fa fa-bar-chart text-primary mr-2 transition-transform duration-300 group-hover:rotate-6"></i>
            站点统计
        </h3>
        <div class="grid grid-cols-3 gap-4">
            <div class="stat-card bg-gradient-to-br from-primary to-blue-600 rounded-xl p-4 transition-all duration-500 hover:-translate-y-1 relative overflow-hidden group">
                <div class="absolute -right-6 -top-6 w-16 h-16 bg-white/10 rounded-full transition-transform duration-700 group-hover:scale-150"></div>
                
                <div class="relative z-10">
                    <div class="text-white/80 text-xs mb-1 flex items-center">
                        <i class="fa fa-th-large mr-1"></i> 话题数
                    </div>
                    <p class="text-2xl font-bold text-white mb-2 counter"><?= number_format(get_topic_count()) ?></p>
                    <div class="w-full bg-white/20 rounded-full h-1.5 overflow-hidden">
                        <div class="bg-white h-full rounded-full transition-all duration-1000" style="width: 0%" data-width="75%"></div>
                    </div>
                </div>
            </div>
            
            <div class="stat-card bg-gradient-to-br from-success to-emerald-600 rounded-xl p-4 transition-all duration-500 hover:-translate-y-1 relative overflow-hidden group">
                <div class="absolute -right-6 -top-6 w-16 h-16 bg-white/10 rounded-full transition-transform duration-700 group-hover:scale-150"></div>
                
                <div class="relative z-10">
                    <div class="text-white/80 text-xs mb-1 flex items-center">
                        <i class="fa fa-file-text-o mr-1"></i> 评论数
                    </div>
                    <p class="text-2xl font-bold text-white mb-2 counter"><?= number_format(get_reply_count()) ?></p>
                    <div class="w-full bg-white/20 rounded-full h-1.5 overflow-hidden">
                        <div class="bg-white h-full rounded-full transition-all duration-1000" style="width: 0%" data-width="90%"></div>
                    </div>
                </div>
            </div>
            
            <div class="stat-card bg-gradient-to-br from-info to-cyan-500 rounded-xl p-4 transition-all duration-500 hover:-translate-y-1 relative overflow-hidden group">
                <div class="absolute -right-6 -top-6 w-16 h-16 bg-white/10 rounded-full transition-transform duration-700 group-hover:scale-150"></div>
                
                <div class="relative z-10">
                    <div class="text-white/80 text-xs mb-1 flex items-center">
                        <i class="fa fa-users mr-1"></i> 注册用户
                    </div>
                    <p class="text-2xl font-bold text-white mb-2 counter"><?= number_format(get_user_count()) ?></p>
                    <div class="w-full bg-white/20 rounded-full h-1.5 overflow-hidden">
                        <div class="bg-white h-full rounded-full transition-all duration-1000" style="width: 0%" data-width="65%"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="mt-4 text-xs text-slate-500 text-right italic">
            <i class="fa fa-refresh mr-1"></i> 数据实时更新中
        </div>
    </div>
    
    <div class="bg-white rounded-2xl p-5 mb-5">
        <h3 class="text-lg font-bold mb-3 pb-2 border-b border-slate-100">热门话题榜</h3>
        <ul class="space-y-3">
            <?php foreach (get_hot_topics(10) as $index => $topic): ?>
            <li class="flex items-start gap-3">
                <span class="text-sm font-medium text-slate-500 w-5"><?= $index + 1 ?>.</span>
                <a href="topic.php?id=<?= $topic['topic_id'] ?>" class="flex-1 group">
                    <h4 class="text-sm font-medium group-hover:text-primary transition-colors line-clamp-2">
                        <?= htmlspecialchars($topic['title']) ?>
                    </h4>
                    <div class="flex items-center text-xs text-slate-500 mt-1">
                        <span><?= htmlspecialchars($topic['username']) ?></span>
                        <span class="mx-1">·</span>
                        <span><?= time_ago($topic['created_at']) ?></span>
                        <span class="ml-auto flex items-center gap-1">
                            <i class="fa fa-eye"></i>
                            <span><?= $topic['view_count'] ?></span>
                        </span>
                    </div>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    
    <div class="bg-white rounded-2xl p-5">
        <h3 class="text-lg font-bold mb-3 pb-2 border-b border-slate-100">随机标签</h3>
        <div class="flex flex-wrap gap-2">
            <?php foreach (get_random_tags(10) as $tag): ?>
            <a href="tag.php?id=<?= $tag['tag_id'] ?>" class="px-3 py-1 bg-primary/10 text-primary rounded-full text-sm hover:bg-primary hover:text-white transition-colors">
                #<?= htmlspecialchars($tag['name']) ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</aside>