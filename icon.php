<?php
$iconRoot = 'static/icon/';  // 图标存放的目录，如果你比较闲的话可以修改

if (!is_dir($iconRoot)) {
    die("图标目录不存在: " . $iconRoot);
}

$categories = [];
$dirIterator = new DirectoryIterator($iconRoot);

foreach ($dirIterator as $fileinfo) {
    if ($fileinfo->isDir() && !$fileinfo->isDot()) {
        $categoryName = $fileinfo->getFilename();
        $categoryPath = $fileinfo->getPathname();
        
        $svgFiles = [];
        $svgDirIterator = new DirectoryIterator($categoryPath);
        
        foreach ($svgDirIterator as $svgFileInfo) {
            if (!$svgFileInfo->isDot() && $svgFileInfo->isFile() && 
                strtolower($svgFileInfo->getExtension()) === 'svg') {
                $relativeUrl = $iconRoot . $categoryName . '/' . $svgFileInfo->getFilename();
                $iconUrl = htmlspecialchars(str_replace(DIRECTORY_SEPARATOR, '/', $relativeUrl));
                
                $svgFiles[] = [
                    'name' => $svgFileInfo->getFilename(),
                    'path' => $svgFileInfo->getPathname(),
                    'url' => $iconUrl
                ];
            }
        }
        
        if (!empty($svgFiles)) {
            $categories[] = [
                'name' => $categoryName,
                'path' => $categoryPath,
                'icons' => $svgFiles
            ];
        }
    }
}

if (empty($categories)) {
    $noIconsFound = true;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>图标选择</title>
    <script src="static/js/tailwindcss.js"></script>
    <link href="static/css/font-awesome.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#3B82F6',
                        secondary: '#6B7280',
                        accent: '#10B981',
                        light: '#F3F4F6',
                        dark: '#1F2937'
                    },
                    fontFamily: {
                        inter: ['Inter', 'system-ui', 'sans-serif'],
                    },
                }
            }
        }
    </script>
    
    <style type="text/tailwindcss">
        @layer utilities {
            .content-auto {
                content-visibility: auto;
            }
            .icon-hover {
                @apply transition-all duration-300 hover:shadow-lg hover:bg-primary/5;
            }
            .category-active {
                @apply bg-primary text-white;
            }
            .copy-toast {
                @apply fixed top-4 right-4 bg-accent text-white px-4 py-2 rounded-lg shadow-md z-50 transform transition-all duration-300 translate-x-full opacity-0;
            }
            .copy-toast.show {
                @apply translate-x-0 opacity-100;
            }
            .icon-selected {
                @apply ring-2 ring-primary bg-primary/5;
            }
        }
    </style>
</head>
<body class="font-inter bg-gray-50 text-dark">
    <header class="bg-white shadow-sm sticky top-0 z-40">
        <div class="container mx-auto px-4 py-4 flex justify-between items-center">
            <h1 class="text-2xl font-bold text-primary flex items-center">
                图标选择
            </h1>
        </div>
    </header>

    <div id="copy-toast" class="copy-toast">
        图标url复制成功
    </div>

    <main class="container mx-auto px-4 py-8">
        <?php if (isset($noIconsFound) && $noIconsFound): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-6 py-4 rounded-lg mb-8">
                <div class="flex items-center">
                    <i class="fa fa-exclamation-circle text-xl mr-3"></i>
                    <div>
                        <h3 class="font-medium">未找到图标</h3>
                        <p class="text-sm mt-1">请在 <?php echo $iconRoot; ?> 目录下放置SVG图标文件或包含SVG图标的子文件夹。</p>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="mb-8 overflow-x-auto pb-2">
                <div class="flex space-x-2 min-w-max">
                    <button class="category-btn category-active px-4 py-2 rounded-full text-sm font-medium transition-colors" 
                            data-category="all">
                        全部图标
                    </button>
                    <?php foreach ($categories as $category): ?>
                        <button class="category-btn px-4 py-2 rounded-full text-sm font-medium bg-light hover:bg-gray-200 transition-colors" 
                                data-category="<?php echo htmlspecialchars($category['name']); ?>">
                            <?php echo htmlspecialchars($category['name']); ?>
                            <span class="ml-1 bg-primary/10 text-primary px-2 py-0.5 rounded-full text-xs">
                                <?php echo count($category['icons']); ?>
                            </span>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-8 gap-4">
                <?php foreach ($categories as $category): ?>
                    <?php foreach ($category['icons'] as $icon): ?>
                        <div class="icon-item" 
                             data-category="<?php echo htmlspecialchars($category['name']); ?>" 
                             data-name="<?php echo htmlspecialchars($icon['name']); ?>"
                             data-url="<?php echo $icon['url']; ?>">
                            <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100 icon-hover cursor-pointer flex flex-col items-center justify-center h-full">
                                <div class="icon-preview w-16 h-16 flex items-center justify-center mb-2">
                                    <?php
                                    $svgContent = file_get_contents($icon['path']);
                                    $svgContent = preg_replace('/width="[^"]+"/', 'width="64"', $svgContent);
                                    $svgContent = preg_replace('/height="[^"]+"/', 'height="64"', $svgContent);
                                    echo $svgContent;
                                    ?>
                                </div>
                                <p class="text-xs text-center text-gray-600 truncate w-full"><?php echo htmlspecialchars($icon['name']); ?></p>
                                <p class="text-xs text-center text-gray-400 mt-1"><?php echo htmlspecialchars($category['name']); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const categoryBtns = document.querySelectorAll('.category-btn');
            const iconItems = document.querySelectorAll('.icon-item');
            
            categoryBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    categoryBtns.forEach(b => b.classList.remove('category-active'));
                    this.classList.add('category-active');
                    
                    const category = this.getAttribute('data-category');
                    
                    iconItems.forEach(item => {
                        if (category === 'all' || item.getAttribute('data-category') === category) {
                            item.style.display = 'block';
                        } else {
                            item.style.display = 'none';
                        }
                    });
                });
            });
            
            const copyToast = document.getElementById('copy-toast');
            
            function showCopyToast() {
                copyToast.classList.add('show');
                
                setTimeout(() => {
                    copyToast.classList.remove('show');
                }, 2000);
            }
            
            iconItems.forEach(item => {
                item.addEventListener('click', function() {
                    const iconUrl = this.getAttribute('data-url');
                    
                    navigator.clipboard.writeText(iconUrl)
                        .then(() => {
                            showCopyToast();
                            
                            this.querySelector('div').classList.add('icon-selected');
                            
                            setTimeout(() => {
                                this.querySelector('div').classList.remove('icon-selected');
                            }, 1000);
                        })
                        .catch(err => {
                            console.error('无法复制内容: ', err);
                            alert('复制失败，请手动复制图标URL');
                        });
                });
            });
        });
    </script>
</body>
</html>
    