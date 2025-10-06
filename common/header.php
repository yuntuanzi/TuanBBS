<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="static/js/tailwindcss.js"></script>
    <link href="static/css/font-awesome.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#3B82F6',
                        secondary: '#93C5FD',
                        accent: '#F59E0B',
                        dark: '#1E40AF',
                        success: '#10B981',
                        info: '#06B6D4',
                        warning: '#F59E0B',
                    },
                    fontFamily: {
                        sans: ['Inter', 'system-ui', 'sans-serif'],
                    },
                    boxShadow: {
                        'card': '0 4px 12px rgba(59, 130, 246, 0.1)',
                        'card-hover': '0 8px 24px rgba(59, 130, 246, 0.15)',
                        'stat': '0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03)',
                    }
                },
            }
        }
    </script>
    <style type="text/tailwindcss">
        @layer utilities {
            .content-auto {
                content-visibility: auto;
            }
            .sidebar-item {
                @apply flex items-center gap-3 px-4 py-3 rounded-lg transition-all duration-300 hover:bg-primary/10 hover:text-primary;
            }
            .sidebar-item.active {
                @apply bg-primary/20 text-primary font-medium;
            }
            .post-card {
                @apply bg-white rounded-xl shadow-card hover:shadow-card-hover transition-all duration-300 overflow-hidden cursor-pointer;
            }
            .btn-primary {
                @apply bg-primary hover:bg-dark text-white font-medium py-2 px-6 rounded-lg transition-all duration-300 transform hover:-translate-y-0.5;
            }
            .stat-card {
                @apply rounded-xl p-4 text-white transition-all duration-300 hover:shadow-lg hover:-translate-y-1 overflow-hidden relative;
            }
            .stat-card::before {
                content: '';
                position: absolute;
                top: 0;
                right: 0;
                width: 60px;
                height: 60px;
                opacity: 0.1;
                transform: rotate(45deg) translate(20px, -30px);
                transition: transform 0.3s ease;
            }
            .stat-card:hover::before {
                transform: rotate(45deg) translate(10px, -40px);
            }
            .sidebar-toggle-btn {
                @apply w-8 h-8 rounded-full flex items-center justify-center text-slate-500 hover:bg-primary/10 hover:text-primary transition-all duration-300 lg:hidden;
            }
            .sidebar-toggle-btn-desktop {
                @apply absolute -right-4 top-1/2 w-8 h-8 rounded-full bg-primary flex items-center justify-center text-white shadow-md transform -translate-y-1/2 cursor-pointer hover:bg-dark transition-all duration-300 hidden lg:flex;
            }
            .sidebar-toggle-btn-collapsed {
                @apply fixed left-0 w-8 h-8 rounded-full bg-primary flex items-center justify-center text-white shadow-md cursor-pointer hover:bg-dark transition-all duration-300 z-40;
            }
        }
    </style>