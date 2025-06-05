<?php
// /www/wwwroot/maxcaulfield.cn/blog_v2.php
require_once __DIR__ . '/db.php';
$page_title_key = 'blog_page_title'; // For translation in header if used
$page_actual_title = null; // Will be set by header.php or here
$active_nav_icon = 'blog'; // For highlighting active nav item

$pdo = getPDO();

/**
 * Tries to construct an absolute URL.
 * @param string $url The URL to process.
 * @param string $base_site_url The base URL of the site (e.g., https://maxcaulfield.cn)
 * @return string The processed URL.
 */
function make_absolute_url($url, $base_site_url) {
    if (empty($url) || preg_match('~^(?:f|ht)tps?://~i', $url)) {
        return $url; // Already absolute or empty
    }
    if (strpos($url, '//') === 0) {
        return "http:" . $url; // Protocol-relative
    }
    // Remove leading slash if base_site_url already has one and url starts with one.
    if (substr($base_site_url, -1) === '/' && $url[0] === '/') {
        $url = substr($url, 1);
    } elseif (substr($base_site_url, -1) !== '/' && $url[0] !== '/') {
        $base_site_url .= '/';
    }
    return rtrim($base_site_url, '/') . '/' . ltrim($url, '/');
}


/**
 * Extracts the URL of the first image from HTML content.
 *
 * @param string|null $html_content The HTML content to parse.
 * @param string $base_site_url The base URL of the site for resolving relative paths.
 * @return string|null The URL of the first image, or null if not found.
 */
function get_first_image_url_from_content($html_content, $base_site_url) {
    if (empty($html_content)) {
        return null;
    }
    $doc = new DOMDocument();
    // Suppress errors for potentially malformed HTML & load with UTF-8 hint
    @$doc->loadHTML('<?xml encoding="utf-8" ?>' . $html_content);
    $image_tags = $doc->getElementsByTagName('img');
    if ($image_tags->length > 0) {
        $first_image = $image_tags->item(0);
        $src = $first_image->getAttribute('src');
        if (!empty($src) && strpos($src, 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7') === false) {
            return make_absolute_url($src, $base_site_url); // Make it absolute
        }
    }
    return null;
}

// Determine Base URL for the site (you might need to adjust this logic)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$domain_name = $_SERVER['HTTP_HOST'] ?? 'localhost'; // Fallback for CLI or misconfigured server
$base_site_url = rtrim($protocol . $domain_name . (dirname($_SERVER['SCRIPT_NAME']) === '/' ? '' : dirname($_SERVER['SCRIPT_NAME'])), '/');


// Fetch all published posts
$stmt_posts = $pdo->query(
    'SELECT p.id, p.title, p.content, p.cover_image_url, ' .
    'SUBSTRING(REPLACE(REPLACE(p.content, CHAR(13), \'\'), CHAR(10), \'\'), 1, 250) AS excerpt_raw, ' .
    'p.created_at, c.name as category_name, c.id as category_id, p.tags ' .
    'FROM posts p ' .
    'LEFT JOIN categories c ON p.category_id = c.id ' .
    'WHERE p.status = "published" ' .
    'ORDER BY p.created_at DESC'
);
$posts_from_db = $stmt_posts ? $stmt_posts->fetchAll() : [];

// Prepare posts for display
$posts_for_display = [];
if ($posts_from_db) {
    foreach ($posts_from_db as $post) {
        $temp_post = $post;
        
        // Determine cover image: 1st from DB field, 2nd from content, 3rd placeholder
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

        // Create a cleaner excerpt
        $plain_excerpt = strip_tags($post['excerpt_raw']);
        $temp_post['excerpt'] = mb_substr($plain_excerpt, 0, 120, 'UTF-8'); 
        if (mb_strlen($plain_excerpt, 'UTF-8') > 120) {
            $temp_post['excerpt'] .= '...';
        }
        
        // Handle Kaomoji/text display for title and excerpt (htmlspecialchars is generally good for security)
        // If Kaomojis are pure text, htmlspecialchars is fine. If they rely on specific HTML entities that
        // are being double-escaped, that's a more complex issue related to data source or previous processing.
        // For now, standard escaping is maintained.
        $temp_post['title_safe'] = htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8');
        $temp_post['excerpt_safe'] = htmlspecialchars($temp_post['excerpt'], ENT_QUOTES, 'UTF-8');

        $posts_for_display[] = $temp_post;
    }
}

// Get all active categories and their post counts
$stmt_categories = $pdo->query(
    'SELECT c.id, c.name, COUNT(p.id) as post_count FROM categories c ' .
    'JOIN posts p ON c.id = p.category_id WHERE p.status = "published" ' .
    'GROUP BY c.id, c.name HAVING post_count > 0 ORDER BY c.name ASC'
);
$active_categories = $stmt_categories ? $stmt_categories->fetchAll() : [];

// Extract and count all tags from published posts
$all_tags_flat = [];
if ($posts_from_db) {
    foreach ($posts_from_db as $post) {
        if (!empty($post['tags'])) {
            $post_tags = explode(',', $post['tags']);
            foreach ($post_tags as $tag) {
                $trimmed_tag = trim($tag);
                if (!empty($trimmed_tag)) {
                    $all_tags_flat[] = $trimmed_tag;
                }
            }
        }
    }
}
$tag_counts = array_count_values($all_tags_flat); // Counts occurrences of each tag
arsort($tag_counts); // Sort tags by count, descending
$unique_tags_sorted_by_count = array_keys($tag_counts);


$baseUrl = ''; // Base URL for links, assuming includes/header.php might set it or it's relative to root.
// For category links, we'll use category_v2.php
require_once __DIR__ . '/includes/header.php'; // Header might set $baseUrl and $page_actual_title
if (is_null($page_actual_title)) { // Fallback title
    $page_actual_title = "博客文章 - " . ($_SESSION['site_settings']['site_name'] ?? 'My Blog');
}
?>
<style>
    .tag-cloud .tag-link {
        display: inline-block;
        padding: 0.4rem 0.9rem; /* Adjusted padding */
        margin: 0.25rem;
        border-radius: 9999px; /* Pill shape */
        font-size: 0.85rem; /* Slightly smaller font */
        font-weight: 500;
        text-decoration: none;
        transition: all 0.3s ease;
        border: 1px solid transparent; /* Base border */
    }
    /* Light theme specific tag styles */
    body.light-theme .tag-cloud .tag-link {
        background-color: #e2e8f0; /* light-gray-200 */
        color: #4a5568; /* light-gray-700 */
        border-color: #cbd5e0; /* light-gray-400 */
    }
    body.light-theme .tag-cloud .tag-link:hover {
        background-color: #4299e1; /* light-blue-500 */
        color: white;
        border-color: #2b6cb0; /* light-blue-700 */
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    /* Dark theme specific tag styles */
    body.dark-theme .tag-cloud .tag-link {
        background-color: #2d3748; /* dark-gray-700 */
        color: #e2e8f0; /* dark-gray-200 */
        border-color: #4a5568; /* dark-gray-600 */
    }
    body.dark-theme .tag-cloud .tag-link:hover {
        background-color: var(--text-accent, #63b3ed); /* Use accent color from theme */
        color: #1a202c; /* dark-gray-900, or a lighter color if accent is dark */
        border-color: var(--glow-color, #90cdf4);
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.25);
    }
     /* Ensure Kaomoji font is appropriate if default is not good */
    body {
        /* Consider a font that has good Unicode coverage if Kaomoji display is an issue */
        /* font-family: 'Noto Sans SC', 'Inter', sans-serif; */ /* Example adding Noto Sans SC */
    }
</style>

<div class="container mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-1 md:grid-cols-3 gap-8 md:gap-12 relative z-10 mt-8 mb-8">
    <main class="md:col-span-2 space-y-8 md:space-y-12 stagger-fade">
        <?php if (empty($posts_for_display)): ?>
            <div class="glass-effect p-8 text-center">
                <p class="text-xl text-[var(--text-secondary)]" data-translate-key="no_posts_found">暂无文章，敬请期待！</p>
            </div>
        <?php else: ?>
            <?php foreach ($posts_for_display as $index => $post): ?>
            <article class="blog-post-card rounded-xl overflow-hidden fade-in glass-effect flex flex-col group" style="animation-delay: <?php echo $index * 0.05; ?>s;">
                <a href="<?php echo $baseUrl; ?>post_v2.php?id=<?php echo $post['id']; ?>" class="block hover:opacity-90 transition-opacity">
                    <div class="h-56 sm:h-64 md:h-72 bg-gray-700 flex items-center justify-center p-2 card-header-block relative overflow-hidden">
                        <img src="<?php echo htmlspecialchars($post['display_cover_image_url']); ?>"
                             alt="<?php echo $post['title_safe']; ?> 的特色图片"
                             class="absolute inset-0 w-full h-full object-cover transition-transform duration-300 ease-in-out group-hover:scale-105"
                             onerror="this.onerror=null; this.src='https://placehold.co/800x400/cccccc/969696?text=Image+Not+Found&font=inter'; this.alt='图片加载失败';">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/30 to-transparent"></div>
                        <h3 class="relative text-xl sm:text-2xl font-bold text-white text-center line-clamp-2 p-3 self-end w-full">
                            <?php echo $post['title_safe']; // Display safe title ?>
                        </h3>
                    </div>
                </a>
                <div class="p-5 md:p-6 flex flex-col flex-grow">
                    <p class="text-xs sm:text-sm text-[var(--text-secondary)] mb-2">
                        <span data-translate-key="category">分类</span>:
                        <?php if (!empty($post['category_name'])): ?>
                            <a href="<?php echo $baseUrl; ?>category_v2.php?id=<?php echo $post['category_id']; ?>" class="hover:text-[var(--text-accent)]">
                                <?php echo htmlspecialchars($post['category_name']); ?>
                            </a>
                        <?php else: ?>
                            <span data-translate-key="uncategorized">未分类</span>
                        <?php endif; ?>
                         | <time datetime="<?php echo date('Y-m-d', strtotime($post['created_at'])); ?>"><?php echo date('Y年m月d日', strtotime($post['created_at'])); ?></time>
                    </p>
                    <h2 class="text-lg md:text-xl font-bold mb-3">
                        <a href="<?php echo $baseUrl; ?>post_v2.php?id=<?php echo $post['id']; ?>" class="hover:text-[var(--text-accent)] transition-colors">
                            <?php echo $post['title_safe']; // Display safe title ?>
                        </a>
                    </h2>
                    <p class="text-sm text-[var(--text-secondary)] mb-4 line-clamp-3 flex-grow">
                        <?php echo $post['excerpt_safe']; // Display safe excerpt ?>
                    </p>
                    <a href="<?php echo $baseUrl; ?>post_v2.php?id=<?php echo $post['id']; ?>" class="mt-auto text-sm font-semibold text-[var(--text-accent)] hover:underline self-start" data-translate-key="read_more">阅读全文 &rarr;</a>
                </div>
            </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>

    <aside class="md:col-span-1 space-y-8 md:space-y-10 stagger-fade">
        <div class="sidebar-module glass-effect p-6 fade-in" style="animation-delay: 0.1s;">
            <h3 class="text-xl font-semibold mb-4 border-b border-[var(--border-color)] pb-2 text-gradient" data-translate-key="about_me">关于博主</h3>
            <img src="<?php echo $baseUrl; ?>images/avatar.jpg" alt="博主头像" class="w-24 h-24 rounded-full mx-auto mb-4 border-2 border-[var(--text-accent)]" onerror="this.onerror=null; this.src='https://placehold.co/96x96/0b0f19/a7c7e7?text=Avatar&font=inter'; this.alt='博主头像加载失败';">
            <p class="text-sm text-center text-[var(--text-secondary)]" data-translate-key="about_me_text_short">
                你好！我是麦青春，热爱探索与创造。欢迎来到我的博客！
            </p>
             <a href="<?php echo $baseUrl; ?>about.html" class="block text-center mt-3 text-sm text-[var(--text-accent)] hover:underline" data-translate-key="learn_more_about_me">了解更多关于我 &rarr;</a>
        </div>
        <div class="sidebar-module glass-effect p-6 fade-in" style="animation-delay: 0.15s;">
            <h3 class="text-xl font-semibold mb-4 border-b border-[var(--border-color)] pb-2 text-gradient" data-translate-key="categories">文章分类</h3>
            <ul class="space-y-2">
                <?php if (!empty($active_categories)): ?>
                    <?php foreach ($active_categories as $category): ?>
                    <li><a href="<?php echo $baseUrl; ?>category_v2.php?id=<?php echo $category['id']; ?>" class="flex justify-between items-center text-[var(--text-secondary)] hover:text-[var(--text-accent)] transition-colors text-sm">
                            <span><?php echo htmlspecialchars($category['name']); ?></span>
                            <span class="text-xs bg-[var(--bg-glass)] px-2 py-0.5 rounded-full border border-[var(--border-color)]"><?php echo $category['post_count']; ?></span>
                        </a></li>
                    <?php endforeach; ?>
                <?php else: ?>
                     <li class="text-sm text-[var(--text-secondary)]" data-translate-key="no_categories_yet">暂无分类</li>
                <?php endif; ?>
            </ul>
        </div>
        <div class="sidebar-module glass-effect p-6 fade-in" style="animation-delay: 0.2s;">
             <h3 class="text-xl font-semibold mb-4 border-b border-[var(--border-color)] pb-2 text-gradient" data-translate-key="tags">标签云</h3>
             <div class="tag-cloud flex flex-wrap gap-1 justify-center">
                <?php if (!empty($unique_tags_sorted_by_count)): ?>
                    <?php foreach($unique_tags_sorted_by_count as $tag_name): ?>
                     <a href="#" class="tag-link"><?php echo htmlspecialchars($tag_name); ?> <span class="text-xs opacity-75">(<?php echo $tag_counts[$tag_name]; ?>)</span></a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-sm text-[var(--text-secondary)]" data-translate-key="no_tags_yet">暂无标签</p>
                <?php endif; ?>
             </div>
        </div>
        <div class="sidebar-module glass-effect p-6 fade-in" style="animation-delay: 0.25s;">
            <h3 class="text-xl font-semibold mb-4 border-b border-[var(--border-color)] pb-2 text-gradient" data-translate-key="recent_posts">近期文章</h3>
            <ul class="space-y-3">
                <?php
                $recent_posts_display = array_slice($posts_for_display, 0, 5); // Uses the already processed posts
                if (!empty($recent_posts_display)):
                    foreach($recent_posts_display as $post_item):
                ?>
                <li><a href="<?php echo $baseUrl; ?>post_v2.php?id=<?php echo $post_item['id']; ?>" class="text-sm text-[var(--text-secondary)] hover:text-[var(--text-accent)] transition-colors line-clamp-1" title="<?php echo $post_item['title_safe']; ?>">
                    <?php echo $post_item['title_safe']; ?>
                </a></li>
                <?php
                    endforeach;
                else:
                ?>
                     <li class="text-sm text-[var(--text-secondary)]" data-translate-key="no_recent_posts">暂无近期文章。</li>
                <?php endif; ?>
            </ul>
        </div>
    </aside>
</div>
<script>
// Staggered fade-in animation script (remains the same as original, assuming it's functional)
document.addEventListener('DOMContentLoaded', function() {
    function applyStaggeredFadeIn() {
        const containers = document.querySelectorAll('.stagger-fade');
        containers.forEach(container => {
            const items = Array.from(container.children).filter(child => child.classList.contains('fade-in'));
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
        });
    }
    
    let fadeInAnimationDefined = false;
    try {
        if (document.styleSheets && document.styleSheets.length > 0 && document.styleSheets[0].cssRules) {
             fadeInAnimationDefined = Array.from(document.styleSheets[0].cssRules).some(rule => rule.type === CSSRule.KEYFRAMES_RULE && rule.name === 'fadeInAnimation');
        }
    } catch (e) {
        console.warn("Could not access cssRules, assuming fadeInAnimation might not be defined globally.", e);
    }

    if (!fadeInAnimationDefined) {
        const styleSheet = document.createElement("style");
        styleSheet.type = "text/css";
        styleSheet.innerText = `
            @keyframes fadeInAnimation {
                from { opacity: 0; transform: translateY(20px); }
                to { opacity: 1; transform: translateY(0); }
            }
            .fade-in { opacity: 0; } /* Initial state before animation */
        `;
        document.head.appendChild(styleSheet);
    }
    applyStaggeredFadeIn();
});
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
