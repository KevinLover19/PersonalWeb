<?php
// /www/wwwroot/maxcaulfield.cn/category_v2.php
require_once __DIR__ . '/db.php';
$active_nav_icon = 'blog'; // Or 'categories' if you have a separate nav item

$pdo = getPDO();
$category_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($category_id <= 0) {
    header('Location: blog_v2.php'); // Redirect to main blog if no valid ID
    exit;
}

// Fetch category details
$stmt_category = $pdo->prepare("SELECT id, name, description FROM categories WHERE id = ?");
$stmt_category->execute([$category_id]);
$category = $stmt_category->fetch(PDO::FETCH_ASSOC);

if (!$category) {
    http_response_code(404);
    $page_actual_title = "分类未找到 - " . ($_SESSION['site_settings']['site_name'] ?? 'My Blog');
    require_once __DIR__ . '/includes/header.php';
    echo '<div class="container mx-auto px-6 py-12 text-center glass-effect mt-8">';
    echo '<h1 class="text-3xl font-bold text-gradient mb-4">分类未找到</h1>';
    echo '<p class="text-[var(--text-secondary)] mb-6">抱歉，您要查找的分类不存在。</p>';
    echo '<a href="blog_v2.php" class="font-semibold text-[var(--text-accent)] hover:underline">返回博客列表 &rarr;</a>';
    echo '</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$page_actual_title = "分类: " . htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8') . " - " . ($_SESSION['site_settings']['site_name'] ?? 'My Blog');

// Determine Base URL for the site (consistent with blog_v2.php)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$domain_name = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base_site_url = rtrim($protocol . $domain_name . (dirname($_SERVER['SCRIPT_NAME']) === '/' ? '' : dirname($_SERVER['SCRIPT_NAME'])), '/');

// Functions from blog_v2.php (or move to a shared helper file)
if (!function_exists('make_absolute_url')) {
    function make_absolute_url($url, $base_site_url_func) { // Renamed param to avoid conflict
        if (empty($url) || preg_match('~^(?:f|ht)tps?://~i', $url)) return $url;
        if (strpos($url, '//') === 0) return "http:" . $url;
        if (substr($base_site_url_func, -1) === '/' && $url[0] === '/') $url = substr($url, 1);
        elseif (substr($base_site_url_func, -1) !== '/' && $url[0] !== '/') $base_site_url_func .= '/';
        return rtrim($base_site_url_func, '/') . '/' . ltrim($url, '/');
    }
}
if (!function_exists('get_first_image_url_from_content')) {
    function get_first_image_url_from_content($html_content, $base_site_url_func) { // Renamed param
        if (empty($html_content)) return null;
        $doc = new DOMDocument();
        @$doc->loadHTML('<?xml encoding="utf-8" ?>' . $html_content);
        $image_tags = $doc->getElementsByTagName('img');
        if ($image_tags->length > 0) {
            $src = $image_tags->item(0)->getAttribute('src');
            if (!empty($src) && strpos($src, 'data:image/gif;base64') === false) {
                return make_absolute_url($src, $base_site_url_func);
            }
        }
        return null;
    }
}

// Fetch posts in this category
$stmt_posts = $pdo->prepare(
    'SELECT p.id, p.title, p.content, p.cover_image_url, ' .
    'SUBSTRING(REPLACE(REPLACE(p.content, CHAR(13), \'\'), CHAR(10), \'\'), 1, 250) AS excerpt_raw, ' .
    'p.created_at, p.tags ' . // Category name is already known
    'FROM posts p ' .
    'WHERE p.category_id = ? AND p.status = "published" ' .
    'ORDER BY p.created_at DESC'
);
$stmt_posts->execute([$category_id]);
$posts_from_db = $stmt_posts->fetchAll();

$posts_for_display = [];
if ($posts_from_db) {
    foreach ($posts_from_db as $post) {
        $temp_post = $post;
        $current_cover_image = $post['cover_image_url'] ? make_absolute_url($post['cover_image_url'], $base_site_url) : null;
        if (empty($current_cover_image)) {
            $current_cover_image = get_first_image_url_from_content($post['content'], $base_site_url);
        }
        if (empty($current_cover_image) || strpos($current_cover_image, 'data:image/gif;base64') !== false) {
            $placeholder_bg = substr(md5($post['title']), 0, 6);
            $placeholder_text_color = substr(md5((string)$post['id']), 0, 6);
            $placeholder_text = urlencode(mb_substr($post['title'], 0, 15, 'UTF-8'));
            $current_cover_image = "https://placehold.co/800x400/{$placeholder_bg}/{$placeholder_text_color}?text={$placeholder_text}&font=inter";
        }
        $temp_post['display_cover_image_url'] = $current_cover_image;
        $plain_excerpt = strip_tags($post['excerpt_raw']);
        $temp_post['excerpt'] = mb_substr($plain_excerpt, 0, 120, 'UTF-8');
        if (mb_strlen($plain_excerpt, 'UTF-8') > 120) $temp_post['excerpt'] .= '...';
        
        $temp_post['title_safe'] = htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8');
        $temp_post['excerpt_safe'] = htmlspecialchars($temp_post['excerpt'], ENT_QUOTES, 'UTF-8');
        $posts_for_display[] = $temp_post;
    }
}

$baseUrl = ''; // As in blog_v2.php
require_once __DIR__ . '/includes/header.php';
?>

<div class="container mx-auto px-4 sm:px-6 lg:px-8 relative z-10 mt-8 mb-8">
    <header class="mb-8 text-center border-b border-[var(--border-color)] pb-6">
        <h1 class="text-3xl md:text-4xl font-bold text-gradient" data-translate-key="category_page_title_prefix">分类</h1>
        <p class="text-2xl md:text-3xl text-[var(--text-primary)] mt-1"><?php echo htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8'); ?></p>
        <?php if (!empty($category['description'])): ?>
            <p class="mt-3 text-md text-[var(--text-secondary)] max-w-2xl mx-auto"><?php echo htmlspecialchars($category['description'], ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
    </header>

    <main class="space-y-8 md:space-y-12">
        <?php if (empty($posts_for_display)): ?>
            <div class="glass-effect p-8 text-center">
                <p class="text-xl text-[var(--text-secondary)]" data-translate-key="no_posts_in_category">此分类下暂无文章。</p>
                <a href="<?php echo $baseUrl; ?>blog_v2.php" class="mt-4 inline-block font-semibold text-[var(--text-accent)] hover:underline" data-translate-key="back_to_blog_list">&larr; 返回博客列表</a>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 md:gap-8">
                <?php foreach ($posts_for_display as $index => $post): ?>
                <article class="blog-post-card rounded-xl overflow-hidden fade-in glass-effect flex flex-col group" style="animation-delay: <?php echo $index * 0.05; ?>s;">
                    <a href="<?php echo $baseUrl; ?>post_v2.php?id=<?php echo $post['id']; ?>" class="block hover:opacity-90 transition-opacity">
                        <div class="h-56 bg-gray-700 flex items-center justify-center p-2 card-header-block relative overflow-hidden">
                            <img src="<?php echo htmlspecialchars($post['display_cover_image_url'], ENT_QUOTES, 'UTF-8'); ?>"
                                 alt="<?php echo $post['title_safe']; ?> 的特色图片"
                                 class="absolute inset-0 w-full h-full object-cover transition-transform duration-300 ease-in-out group-hover:scale-105"
                                 onerror="this.onerror=null; this.src='https://placehold.co/800x400/cccccc/969696?text=Image+Not+Found&font=inter'; this.alt='图片加载失败';">
                            <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/30 to-transparent"></div>
                            <h3 class="relative text-xl font-bold text-white text-center line-clamp-2 p-3 self-end w-full">
                                <?php echo $post['title_safe']; ?>
                            </h3>
                        </div>
                    </a>
                    <div class="p-5 flex flex-col flex-grow">
                        <p class="text-xs text-[var(--text-secondary)] mb-2">
                            <time datetime="<?php echo date('Y-m-d', strtotime($post['created_at'])); ?>"><?php echo date('Y年m月d日', strtotime($post['created_at'])); ?></time>
                        </p>
                        <h2 class="text-lg font-bold mb-3">
                            <a href="<?php echo $baseUrl; ?>post_v2.php?id=<?php echo $post['id']; ?>" class="hover:text-[var(--text-accent)] transition-colors">
                                <?php echo $post['title_safe']; ?>
                            </a>
                        </h2>
                        <p class="text-sm text-[var(--text-secondary)] mb-4 line-clamp-3 flex-grow">
                            <?php echo $post['excerpt_safe']; ?>
                        </p>
                        <a href="<?php echo $baseUrl; ?>post_v2.php?id=<?php echo $post['id']; ?>" class="mt-auto text-sm font-semibold text-[var(--text-accent)] hover:underline self-start" data-translate-key="read_more">阅读全文 &rarr;</a>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</div>
<script>
// Staggered fade-in animation script (can be moved to a global JS file)
document.addEventListener('DOMContentLoaded', function() {
    function applyStaggeredFadeIn() {
        const items = document.querySelectorAll('.fade-in'); // Simpler selector if only for these cards
        items.forEach((item, index) => {
            item.style.opacity = '0';
            let delay = parseFloat(item.style.animationDelay);
            if (isNaN(delay) || delay === 0) { 
                 delay = index * 0.05; 
                 item.style.animationDelay = `${delay}s`;
            }
            item.style.animationName = 'fadeInAnimation'; 
            item.style.animationFillMode = 'forwards'; 
            item.style.animationDuration = '0.5s';
            item.style.animationTimingFunction = 'ease-in-out';
        });
    }
    
    let fadeInAnimationDefined = false;
    try {
        if (document.styleSheets && document.styleSheets.length > 0 && document.styleSheets[0].cssRules) {
             fadeInAnimationDefined = Array.from(document.styleSheets[0].cssRules).some(rule => rule.type === CSSRule.KEYFRAMES_RULE && rule.name === 'fadeInAnimation');
        }
    } catch (e) { console.warn("Could not access cssRules for fadeInAnimation check.", e); }

    if (!fadeInAnimationDefined) {
        const styleSheet = document.createElement("style");
        styleSheet.type = "text/css";
        styleSheet.innerText = `
            @keyframes fadeInAnimation {
                from { opacity: 0; transform: translateY(20px); }
                to { opacity: 1; transform: translateY(0); }
            }
            .fade-in { opacity: 0; }
        `;
        document.head.appendChild(styleSheet);
    }
    applyStaggeredFadeIn();
});
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
