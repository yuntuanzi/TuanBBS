<footer class="bg-white border-t border-gray-100 py-6">
    <div class="container mx-auto px-4">
        <div class="flex flex-wrap justify-center gap-x-8 gap-y-3 mb-5">
            <a href="notifications.php" class="text-gray-600 hover:text-primary text-sm transition-colors">
                <i class="fa fa-bullhorn text-primary mr-1"></i>我的通知
            </a>
            <a href="my_reports.php" class="text-gray-600 hover:text-primary text-sm transition-colors">
                <i class="fa fa-shield text-primary mr-1"></i>举报记录
            </a>
            <a href="help.php" class="text-gray-600 hover:text-primary text-sm transition-colors">
                <i class="fa fa-question-circle text-primary mr-1"></i>帮助中心
            </a>
            <a href="contact.php" class="text-gray-600 hover:text-primary text-sm transition-colors">
                <i class="fa fa-envelope text-primary mr-1"></i>联系我们
            </a>
        </div>
    </div>
</footer>

<script>
    const sidebarContainer = document.getElementById('sidebar-container');
    const sidebar = document.getElementById('sidebar');
    const sidebarBackdrop = document.getElementById('sidebar-backdrop');
    const contentArea = document.getElementById('content-area');
    
    const toggleBtn = document.createElement('button');
    toggleBtn.id = 'sidebar-toggle';
    toggleBtn.className = 'fixed z-50 flex items-center justify-center w-10 h-10 rounded-full bg-white/80 shadow-md backdrop-blur-sm transition-all duration-300 ease-in-out hover:shadow-lg hover:bg-white';
    toggleBtn.innerHTML = '<i class="fa fa-bars text-slate-700"></i>';
    
    // 只在电脑端添加侧边栏切换按钮
    if (window.innerWidth >= 1024) {
        document.body.appendChild(toggleBtn);
    }
    
    let sidebarOpen = window.innerWidth >= 1024;
    
    function setToggleButtonPosition() {
        if (window.innerWidth < 1024) {
            return; // 移动端不显示按钮
        }
        
        if (sidebarOpen) {
            toggleBtn.style.left = `${sidebar.offsetWidth - 10}px`;
            toggleBtn.style.top = '20px';
            toggleBtn.innerHTML = '<i class="fa fa-chevron-left text-slate-700"></i>';
        } else {
            toggleBtn.style.left = '10px';
            toggleBtn.style.top = '20px';
            toggleBtn.innerHTML = '<i class="fa fa-chevron-right text-slate-700"></i>';
        }
    }
    
    function updateSidebarState() {
        if (sidebarOpen) {
            sidebar.style.transform = 'translateX(0)';
            sidebarBackdrop.classList.remove('hidden');
            sidebarBackdrop.classList.add('flex');
            
            if (window.innerWidth >= 1024) {
                contentArea.style.marginLeft = `${sidebar.offsetWidth}px`;
            }
        } else {
            sidebar.style.transform = 'translateX(-100%)';
            sidebarBackdrop.classList.add('hidden');
            sidebarBackdrop.classList.remove('flex');
            
            if (window.innerWidth >= 1024) {
                contentArea.style.marginLeft = '0';
            }
        }
        
        setToggleButtonPosition();
    }
    
    function toggleSidebar() {
        sidebarOpen = !sidebarOpen;
        updateSidebarState();
    }
    
    window.addEventListener('load', function() {
        if (window.innerWidth >= 1024) {
            contentArea.style.marginLeft = `${sidebar.offsetWidth}px`;
            contentArea.style.transition = 'margin-left 0.3s ease-in-out';
        }
        
        updateSidebarState();
        
        sidebarBackdrop.addEventListener('click', toggleSidebar);
        
        if (window.innerWidth >= 1024) {
            toggleBtn.addEventListener('click', toggleSidebar);
        }
    });
    
    window.addEventListener('resize', function() {
        if (window.innerWidth >= 1024 && !sidebarOpen) {
            sidebarOpen = true;
            updateSidebarState();
            // 只在电脑端添加按钮
            if (!document.body.contains(toggleBtn)) {
                document.body.appendChild(toggleBtn);
            }
        } else if (window.innerWidth < 1024 && sidebarOpen) {
            sidebarOpen = false;
            updateSidebarState();
            // 移动端移除按钮
            if (document.body.contains(toggleBtn)) {
                document.body.removeChild(toggleBtn);
            }
        } else {
            updateSidebarState();
        }
    });
    
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            document.querySelector(this.getAttribute('href'))?.scrollIntoView({
                behavior: 'smooth'
            });
        });
    });
</script>

<style>
    #sidebar {
        background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
    }
    
    #sidebar-toggle {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        z-index: 50;
        backdrop-filter: blur(10px);
    }
    
    #sidebar-toggle:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.12);
    }
    
    /* 在移动端隐藏侧边栏切换按钮 */
    @media (max-width: 1023px) {
        #sidebar-toggle {
            display: none !important;
        }
    }
    
    #sidebar-backdrop {
        backdrop-filter: blur(4px);
    }
    
    .sidebar-item.active {
        background-color: rgba(99, 102, 241, 0.1);
        color: #6366f1;
        font-weight: 500;
    }
    
    #sidebar::-webkit-scrollbar {
        width: 5px;
    }
    
    #sidebar::-webkit-scrollbar-track {
        background: #f1f5f9;
        border-radius: 10px;
    }
    
    #sidebar::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 10px;
    }
    
    #sidebar::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }
    
    .notification-badge {
        position: absolute;
        top: -4px;
        right: -4px;
        width: 16px;
        height: 16px;
        border-radius: 9999px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 10px;
        font-weight: 600;
    }
    
    .backdrop-blur {
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
    }
</style>
</body>
</html>