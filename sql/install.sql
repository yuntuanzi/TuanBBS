-- 用户信息表
CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT COMMENT '用户ID，主键',
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '用户名，唯一',
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '电子邮箱，唯一',
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '密码哈希值',
  `avatar_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '头像URL',
  `cover_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '个人主页背景图URL',
  `bio` text COLLATE utf8mb4_unicode_ci COMMENT '个人简介',
  `role` enum('admin','moderator','user') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'user' COMMENT '用户角色：管理员、版主、普通用户',
  `status` enum('active','banned','suspended') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active' COMMENT '账号状态：正常、封禁、停用',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  `last_login_at` timestamp NULL DEFAULT NULL COMMENT '最后登录时间',
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户信息表';


-- 论坛板块表
CREATE TABLE `sections` (
  `section_id` int(11) NOT NULL AUTO_INCREMENT COMMENT '板块ID，主键',
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '板块名称',
  `description` text COLLATE utf8mb4_unicode_ci COMMENT '板块描述',
  `banner_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '板块横幅图片URL',
  `icon` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '板块图标类名',
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否激活',
  `display_order` int(11) NOT NULL DEFAULT '0' COMMENT '显示顺序',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`section_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='论坛板块表';


-- 主题帖子表
CREATE TABLE `topics` (
  `topic_id` int(11) NOT NULL AUTO_INCREMENT COMMENT '主题ID，主键',
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '主题标题',
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '主题内容',
  `user_id` int(11) NOT NULL COMMENT '发帖用户ID',
  `section_id` int(11) NOT NULL COMMENT '所属板块ID',
  `view_count` int(11) NOT NULL DEFAULT '0' COMMENT '查看次数',
  `reply_count` int(11) NOT NULL DEFAULT '0' COMMENT '回复数量',
  `like_count` int(11) NOT NULL DEFAULT '0' COMMENT '点赞数量',
  `is_sticky` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否置顶',
  `is_essence` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否精华',
  `is_closed` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否关闭',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  `last_reply_at` timestamp NULL DEFAULT NULL COMMENT '最后回复时间',
  PRIMARY KEY (`topic_id`),
  KEY `user_id` (`user_id`),
  KEY `section_id` (`section_id`),
  KEY `created_at` (`created_at`),
  KEY `last_reply_at` (`last_reply_at`),
  CONSTRAINT `topics_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `topics_ibfk_2` FOREIGN KEY (`section_id`) REFERENCES `sections` (`section_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='主题帖子表';


-- 主题回复表
CREATE TABLE `replies` (
  `reply_id` int(11) NOT NULL AUTO_INCREMENT COMMENT '回复ID，主键',
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '回复内容',
  `user_id` int(11) NOT NULL COMMENT '回复用户ID',
  `topic_id` int(11) NOT NULL COMMENT '所属主题ID',
  `reply_to` int(11) DEFAULT '0' COMMENT '回复目标ID，0表示直接回复主题',
  `like_count` int(11) NOT NULL DEFAULT '0' COMMENT '点赞数量',
  `is_first_reply` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否首楼回复',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`reply_id`),
  KEY `user_id` (`user_id`),
  KEY `topic_id` (`topic_id`),
  KEY `created_at` (`created_at`),
  KEY `reply_to` (`reply_to`),
  CONSTRAINT `replies_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `replies_ibfk_2` FOREIGN KEY (`topic_id`) REFERENCES `topics` (`topic_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='主题回复表';


-- 标签表
CREATE TABLE `tags` (
  `tag_id` int(11) NOT NULL AUTO_INCREMENT COMMENT '标签ID，主键',
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '标签名称，唯一',
  `description` text COLLATE utf8mb4_unicode_ci COMMENT '标签描述',
  `usage_count` int(11) NOT NULL DEFAULT '0' COMMENT '使用次数',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`tag_id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='标签表';


-- 主题标签关联表
CREATE TABLE `topic_tags` (
  `topic_id` int(11) NOT NULL COMMENT '主题ID',
  `tag_id` int(11) NOT NULL COMMENT '标签ID',
  PRIMARY KEY (`topic_id`,`tag_id`),
  KEY `tag_id` (`tag_id`),
  CONSTRAINT `topic_tags_ibfk_1` FOREIGN KEY (`topic_id`) REFERENCES `topics` (`topic_id`) ON DELETE CASCADE,
  CONSTRAINT `topic_tags_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`tag_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='主题标签关联表';


-- 用户收藏表
CREATE TABLE `favorites` (
  `favorite_id` int(11) NOT NULL AUTO_INCREMENT COMMENT '收藏ID，主键',
  `user_id` int(11) NOT NULL COMMENT '用户ID',
  `topic_id` int(11) NOT NULL COMMENT '主题ID',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '收藏时间',
  PRIMARY KEY (`favorite_id`),
  UNIQUE KEY `user_topic` (`user_id`,`topic_id`),
  KEY `topic_id` (`topic_id`),
  CONSTRAINT `favorites_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `favorites_ibfk_2` FOREIGN KEY (`topic_id`) REFERENCES `topics` (`topic_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户收藏表';


-- 用户关注关系表
CREATE TABLE `follows` (
  `follow_id` int(11) NOT NULL AUTO_INCREMENT COMMENT '关注ID，主键',
  `follower_id` int(11) NOT NULL COMMENT '关注者ID',
  `following_id` int(11) NOT NULL COMMENT '被关注者ID',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '关注时间',
  PRIMARY KEY (`follow_id`),
  UNIQUE KEY `follower_following` (`follower_id`,`following_id`),
  KEY `following_id` (`following_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户关注关系表';


-- 点赞记录表
CREATE TABLE `likes` (
  `like_id` int(11) NOT NULL AUTO_INCREMENT COMMENT '点赞ID，主键',
  `user_id` int(11) NOT NULL COMMENT '点赞用户ID',
  `target_type` enum('topic','reply') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '点赞目标类型：主题或回复',
  `target_id` int(11) NOT NULL COMMENT '点赞目标ID',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '点赞时间',
  PRIMARY KEY (`like_id`),
  UNIQUE KEY `user_target` (`user_id`,`target_type`,`target_id`),
  KEY `target` (`target_type`,`target_id`),
  CONSTRAINT `likes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='点赞记录表';


-- 用户通知表
CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL AUTO_INCREMENT COMMENT '通知ID，主键',
  `user_id` int(11) NOT NULL COMMENT '接收用户ID',
  `sender_id` int(11) DEFAULT NULL COMMENT '发送用户ID',
  `type` enum('reply','like','mention','system','follow') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '通知类型：回复、点赞、提及、系统、关注',
  `target_type` enum('topic','reply','user') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `target_id` int(11) NOT NULL COMMENT '目标ID',
  `message` text COLLATE utf8mb4_unicode_ci COMMENT '自定义消息内容',
  `is_read` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否已读',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`notification_id`),
  KEY `user_id` (`user_id`),
  KEY `is_read` (`is_read`),
  KEY `sender_id` (`sender_id`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户通知表';


-- 用户私信表
CREATE TABLE `private_messages` (
  `message_id` int(11) NOT NULL AUTO_INCREMENT COMMENT '私信ID，主键',
  `sender_id` int(11) NOT NULL COMMENT '发送者ID',
  `receiver_id` int(11) NOT NULL COMMENT '接收者ID',
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '私信内容',
  `is_read` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否已读',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '发送时间',
  PRIMARY KEY (`message_id`),
  KEY `sender_id` (`sender_id`),
  KEY `receiver_id` (`receiver_id`),
  CONSTRAINT `private_messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `private_messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户私信表';


-- 资源文件表
CREATE TABLE `resources` (
  `resource_id` int(11) NOT NULL AUTO_INCREMENT COMMENT '资源ID，主键',
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '资源标题',
  `description` text COLLATE utf8mb4_unicode_ci COMMENT '资源描述',
  `file_url` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '文件URL',
  `file_size` int(11) DEFAULT NULL COMMENT '文件大小(字节)',
  `file_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '文件类型',
  `user_id` int(11) NOT NULL COMMENT '上传用户ID',
  `download_count` int(11) NOT NULL DEFAULT '0' COMMENT '下载次数',
  `is_approved` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否审核通过',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '上传时间',
  PRIMARY KEY (`resource_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `resources_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='资源文件表';


-- 站点基本信息配置表
CREATE TABLE `site_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '记录ID，主键',
  `site_title` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '站点标题',
  `site_subtitle` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '站点副标题',
  `home_title` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '首页标题',
  `home_subtitle` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '首页副标题',
  `home_banner_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '首页横幅图片URL',
  `site_logo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '站点logo URL',
  `webmaster_email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '站长邮箱',
  `webmaster_qq` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '站长QQ',
  `site_announcement` text COLLATE utf8mb4_unicode_ci COMMENT '站点公告',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='站点基本信息配置表';


-- 站点每日统计表
CREATE TABLE `site_stats` (
  `stat_id` int(11) NOT NULL AUTO_INCREMENT COMMENT '统计ID，主键',
  `stat_date` date NOT NULL COMMENT '统计日期',
  `user_count` int(11) NOT NULL DEFAULT '0' COMMENT '用户总数',
  `topic_count` int(11) NOT NULL DEFAULT '0' COMMENT '主题总数',
  `reply_count` int(11) NOT NULL DEFAULT '0' COMMENT '回复总数',
  `visit_count` int(11) NOT NULL DEFAULT '0' COMMENT '访问次数',
  PRIMARY KEY (`stat_id`),
  UNIQUE KEY `stat_date` (`stat_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='站点每日统计表';


-- 主题修改记录表
CREATE TABLE `topic_modifications` (
  `modification_id` int(11) NOT NULL AUTO_INCREMENT COMMENT '修改记录ID',
  `topic_id` int(11) DEFAULT NULL COMMENT '主题ID',
  `user_id` int(11) NOT NULL COMMENT '操作用户ID',
  `action` enum('edit','delete') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '操作类型：编辑或删除',
  `old_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '原标题',
  `new_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '新标题',
  `old_content` text COLLATE utf8mb4_unicode_ci COMMENT '原内容',
  `new_content` text COLLATE utf8mb4_unicode_ci COMMENT '新内容',
  `old_section_id` int(11) DEFAULT NULL COMMENT '原板块ID',
  `new_section_id` int(11) DEFAULT NULL COMMENT '新板块ID',
  `old_tags` text COLLATE utf8mb4_unicode_ci COMMENT '原标签(JSON格式)',
  `new_tags` text COLLATE utf8mb4_unicode_ci COMMENT '新标签(JSON格式)',
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '操作原因',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '操作时间',
  PRIMARY KEY (`modification_id`),
  KEY `topic_id` (`topic_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `topic_modifications_ibfk_2` FOREIGN KEY (`topic_id`) REFERENCES `topics` (`topic_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='主题修改记录表';


-- 举报记录表
CREATE TABLE `reports` (
  `report_id` int(11) NOT NULL AUTO_INCREMENT COMMENT '举报ID，主键',
  `user_id` int(11) NOT NULL COMMENT '举报人ID',
  `target_type` enum('topic','reply','user') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '举报目标类型：主题、回复、用户',
  `target_id` int(11) NOT NULL COMMENT '举报目标ID',
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '举报原因',
  `description` text COLLATE utf8mb4_unicode_ci COMMENT '举报详细描述',
  `status` enum('pending','processed','dismissed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT '举报状态：待处理、已处理、已驳回',
  `processed_by` int(11) DEFAULT NULL COMMENT '处理人ID',
  `processed_at` timestamp NULL DEFAULT NULL COMMENT '处理时间',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '举报时间',
  PRIMARY KEY (`report_id`),
  KEY `user_id` (`user_id`),
  KEY `target` (`target_type`,`target_id`),
  KEY `status` (`status`),
  CONSTRAINT `reports_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `reports_ibfk_2` FOREIGN KEY (`processed_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='举报记录表';


-- 活跃用户视图
CREATE ALGORITHM=UNDEFINED DEFINER=`xihui`@`localhost` SQL SECURITY DEFINER VIEW `active_users`  AS 
SELECT 
  `u`.`user_id` AS `user_id`, 
  `u`.`username` AS `username`, 
  `u`.`avatar_url` AS `avatar_url`, 
  count(distinct `t`.`topic_id`) AS `topic_count`, 
  count(distinct `r`.`reply_id`) AS `reply_count`, 
  max(greatest(ifnull(`t`.`created_at`,'1970-01-01'),ifnull(`r`.`created_at`,'1970-01-01'))) AS `last_activity` 
FROM ((`users` `u` left join `topics` `t` on((`u`.`user_id` = `t`.`user_id`))) left join `replies` `r` on((`u`.`user_id` = `r`.`user_id`))) 
GROUP BY `u`.`user_id`, `u`.`username`, `u`.`avatar_url` 
ORDER BY ((count(distinct `t`.`topic_id`) * 2) + count(distinct `r`.`reply_id`)) DESC 
LIMIT 0, 20 ;


-- 热门主题视图
CREATE ALGORITHM=UNDEFINED DEFINER=`xihui`@`localhost` SQL SECURITY DEFINER VIEW `hot_topics`  AS 
SELECT 
  `t`.`topic_id` AS `topic_id`, 
  `t`.`title` AS `title`, 
  `t`.`view_count` AS `view_count`, 
  `t`.`reply_count` AS `reply_count`, 
  `t`.`like_count` AS `like_count`, 
  `t`.`created_at` AS `created_at`, 
  `t`.`last_reply_at` AS `last_reply_at`, 
  `u`.`user_id` AS `user_id`, 
  `u`.`username` AS `username`, 
  `u`.`avatar_url` AS `avatar_url`, 
  `s`.`section_id` AS `section_id`, 
  `s`.`name` AS `section_name` 
FROM ((`topics` `t` join `users` `u` on((`t`.`user_id` = `u`.`user_id`))) join `sections` `s` on((`t`.`section_id` = `s`.`section_id`))) 
WHERE (`t`.`created_at` >= (now() - interval 7 day)) 
ORDER BY (((`t`.`view_count` * 0.3) + (`t`.`reply_count` * 0.4)) + (`t`.`like_count` * 0.3)) DESC 
LIMIT 0, 20 ;


-- 热门标签视图
CREATE ALGORITHM=UNDEFINED DEFINER=`xihui`@`localhost` SQL SECURITY DEFINER VIEW `popular_tags`  AS 
SELECT 
  `t`.`tag_id` AS `tag_id`, 
  `t`.`name` AS `name`, 
  `t`.`description` AS `description`, 
  count(`tt`.`topic_id`) AS `usage_count`, 
  `t`.`created_at` AS `created_at` 
FROM (`tags` `t` left join `topic_tags` `tt` on((`t`.`tag_id` = `tt`.`tag_id`))) 
GROUP BY `t`.`tag_id` 
ORDER BY `usage_count` DESC ;