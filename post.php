<?php
// /www/wwwroot/maxcaulfield.cn/post.php
// (Canvas filename: post_v6.php)

// Enable detailed error reporting for debugging (REMOVE OR COMMENT OUT IN PRODUCTION)
error_reporting(E_ALL);
ini_set('display_errors', 1); 

// Corrected path for db.php, assuming it's in the same directory as post.php
// (i.e., /www/wwwroot/maxcaulfield.cn/db.php)
require_once __DIR__ . '/db.php'; 

$page_actual_title = "文章详情"; 
$active_nav_icon = 'blog'; 
$baseUrl = ''; // This should ideally be defined in one place, e.g., config or db.php
$path_to_root = ''; 

$pdo = getPDO(); // getPDO() function is expected from db.php
if (!$pdo) {
    error_log("FATAL ERROR: Database connection could not be established in post.php (getPDO returned null).");
    // Output a user-friendly error if HTML hasn't started. Otherwise, this might be too late.
    // Consider having db.php or an early bootstrap script handle this.
    die("数据库连接失败。请检查配置或联系管理员。错误代码: POST_DB_INIT_FAIL");
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$reply_to_comment_id = isset($_GET['replyto']) ? intval($_GET['replyto']) : null;

// Determine Base URL for the site
$protocol_post = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$domain_name_post = $_SERVER['HTTP_HOST'] ?? 'localhost'; // Fallback for CLI or misconfigured server
$script_dir_post = dirname($_SERVER['SCRIPT_NAME']);
// Ensure base_site_url_post does not include the script name if it's a file like index.php at root
$base_path_for_url = ($script_dir_post === '/' || $script_dir_post === '\\') ? '' : $script_dir_post;
$base_site_url_post = rtrim($protocol_post . $domain_name_post . $base_path_for_url, '/');


if ($id <= 0) {
    header('Location: blog.php'); // Use original server filename
    exit;
}

$post = null; 
try {
    $stmt_post_fetch = $pdo->prepare(
        'SELECT p.*, c.name as category_name, c.id as category_id_for_link ' .
        'FROM posts p LEFT JOIN categories c ON p.category_id = c.id ' .
        'WHERE p.id = ? AND p.status = "published"'
    );
    if(!$stmt_post_fetch) {
        $pdo_error = $pdo->errorInfo();
        error_log("Prepare failed for fetching post ID {$id} in post.php. PDO Error: " . ($pdo_error[2] ?? "Unknown error"));
        throw new PDOException("数据库预处理语句失败 (获取文章)。");
    }
    if(!$stmt_post_fetch->execute([$id])) {
        $stmt_error = $stmt_post_fetch->errorInfo();
        error_log("Execute failed for fetching post ID {$id} in post.php. Statement Error: " . ($stmt_error[2] ?? "Unknown error"));
        throw new PDOException("数据库执行失败 (获取文章)。");
    }
    $post = $stmt_post_fetch->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("PDOException in post.php while fetching post data for ID $id: " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
    $page_actual_title = "错误 - " . ($_SESSION['site_settings']['site_name'] ?? 'My Blog');
    // Avoid including header/footer if they also rely on db and might re-trigger error
    echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><title>'.$page_actual_title.'</title><style>body{font-family:sans-serif;padding:20px;text-align:center;}</style></head><body>';
    echo '<h1 style="color:red;">加载文章时发生数据库错误</h1><p>请稍后重试或联系管理员。详情已记录。</p>';
    echo '<p><a href="blog.php">返回博客</a></p>'; // Use original server filename
    echo '</body></html>';
    exit;
}


if (!$post) {
    http_response_code(404);
    $page_actual_title = "文章未找到 - " . ($_SESSION['site_settings']['site_name'] ?? 'My Blog'); 
    // Assuming header.php might not be available if db error occurred before
    echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><title>'.$page_actual_title.'</title><style>body{font-family:sans-serif;padding:20px;text-align:center;}</style></head><body>';
    echo '<h1>文章未找到</h1><p>抱歉，您要查找的文章不存在、尚未发布或已被删除。</p>';
    echo '<p><a href="blog.php">返回博客列表 &rarr;</a></p>'; // Use original server filename
    echo '</body></html>';
    exit;
}

// If we reach here, $post is valid. Now include the header.
require_once __DIR__ . '/includes/header.php'; // Assuming header.php is in an 'includes' subdirectory

$page_actual_title = htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') . " - " . ($_SESSION['site_settings']['site_name'] ?? 'My Blog'); // Update title now that header is loaded
$display_content = $post['content']; 
// Make image URLs in content absolute
$display_content = preg_replace_callback(
    '/<img([^>]+)src=["\']((?!https?:\/\/|\/\/|data:)[^\'"]+)["\']([^>]*)>/i',
    function ($matches) use ($base_site_url_post) {
        // If $matches[2] starts with a slash, it's absolute from webroot.
        // If not, it's relative to the current page's path, which is harder to resolve robustly here
        // without knowing the exact structure of where images might be.
        // Assuming images from TinyMCE are usually webroot-relative or full URLs.
        $img_path = urldecode($matches[2]);
        if (strpos($img_path, '/') === 0) { // Starts with a slash (webroot relative)
            $abs_src = $base_site_url_post . $img_path;
        } else { // Does not start with a slash, could be relative to script or a base path
            // This might need adjustment if images are stored in complex relative paths
            // For now, assume it might be relative to a common 'uploads' or 'images' dir at root
            // A safer bet is that TinyMCE is configured for root-relative or absolute paths
            $abs_src = $base_site_url_post . '/' . ltrim($img_path, '/'); 
        }
        return "<img{$matches[1]}src=\"" . htmlspecialchars($abs_src, ENT_QUOTES, 'UTF-8') . "\"{$matches[3]}>";
    },
    $display_content
);

$comment_error_message = '';
$comment_success_message = '';
$is_user_logged_in = isset($_SESSION['user_id']);
$current_user_id = $_SESSION['user_id'] ?? null;
$current_username = $_SESSION['username'] ?? '访客';

// --- Handle Like/Unlike Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'like_comment' && isset($_POST['comment_id_to_like'])) {
    if ($is_user_logged_in) {
        $comment_id_to_like = intval($_POST['comment_id_to_like']);
        try {
            $pdo->beginTransaction();
            $like_check_stmt = $pdo->prepare("SELECT id FROM comment_likes WHERE user_id = ? AND comment_id = ?");
            if(!$like_check_stmt) { $err=implode(":",$pdo->errorInfo()); error_log("Prepare L1 failed: $err"); throw new PDOException("Prepare L1 failed: $err");}
            $like_check_stmt->execute([$current_user_id, $comment_id_to_like]);

            if ($like_check_stmt->fetch()) {
                $delete_like_stmt = $pdo->prepare("DELETE FROM comment_likes WHERE user_id = ? AND comment_id = ?");
                if(!$delete_like_stmt) { $err=implode(":",$pdo->errorInfo()); error_log("Prepare L2 failed: $err"); throw new PDOException("Prepare L2 failed: $err");}
                $delete_like_stmt->execute([$current_user_id, $comment_id_to_like]);
                $update_likes_stmt = $pdo->prepare("UPDATE comments SET likes = GREATEST(0, likes - 1) WHERE id = ?");
            } else {
                $insert_like_stmt = $pdo->prepare("INSERT INTO comment_likes (user_id, comment_id) VALUES (?, ?)");
                if(!$insert_like_stmt) { $err=implode(":",$pdo->errorInfo()); error_log("Prepare L3 failed: $err"); throw new PDOException("Prepare L3 failed: $err");}
                $insert_like_stmt->execute([$current_user_id, $comment_id_to_like]);
                $update_likes_stmt = $pdo->prepare("UPDATE comments SET likes = likes + 1 WHERE id = ?");
            }
            if(!$update_likes_stmt) { $err=implode(":",$pdo->errorInfo()); error_log("Prepare L4 failed: $err"); throw new PDOException("Prepare L4 failed: $err");}
            $update_likes_stmt->execute([$comment_id_to_like]);
            $pdo->commit();
            header('Location: post.php?id=' . $id . '#comment-' . $comment_id_to_like); 
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error_detail = $e->getMessage();
             if (strpos(strtolower($error_detail), 'no such table') !== false || strpos(strtolower($error_detail), 'unknown table') !== false) {
                if(strpos($error_detail, 'comment_likes') !== false) $comment_error_message = "处理点赞失败：'comment_likes' 表不存在。";
                else if(strpos($error_detail, 'comments') !== false) $comment_error_message = "处理点赞失败：'comments' 表不存在。";
                else $comment_error_message = "处理点赞失败：相关数据表不存在。";
            } else {
                $comment_error_message = "处理点赞时发生数据库错误。详情: " . htmlspecialchars($error_detail);
            }
            error_log("Error liking comment (post_id: $id, comment_id: $comment_id_to_like): " . $error_detail);
        }
    }
}

// --- Handle Comment Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment_content']) && !isset($_POST['action'])) {
    $is_anonymous_submission = isset($_POST['is_anonymous']) && $_POST['is_anonymous'] === 'on';
    $comment_content = trim($_POST['comment_content'] ?? '');
    $parent_id_submission = isset($_POST['parent_comment_id']) && !empty($_POST['parent_comment_id']) ? intval($_POST['parent_comment_id']) : null;
    $name_to_submit = $current_username; 
    $email_to_submit = ''; 

    if ($is_user_logged_in) {
        if ($is_anonymous_submission) $name_to_submit = '匿名用户';
    } else { 
        if ($is_anonymous_submission) {
            $name_to_submit = '匿名用户';
        } else {
            $name_to_submit = trim($_POST['name'] ?? '');
            $email_to_submit = trim($_POST['email'] ?? ''); 
            if (empty($name_to_submit)) $comment_error_message = '非匿名发表时，昵称为必填项。';
            elseif (!empty($email_to_submit) && !filter_var($email_to_submit, FILTER_VALIDATE_EMAIL)) $comment_error_message = '请输入有效的邮箱地址。';
        }
    }
    if (empty($comment_content)) $comment_error_message = '评论内容不能为空。';

    if (empty($comment_error_message)) {
        try {
            $user_id_for_comment = ($is_user_logged_in && !$is_anonymous_submission) ? $current_user_id : null;
            $cstmt_insert = $pdo->prepare('INSERT INTO comments (post_id, user_id, parent_comment_id, name, email, content, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
            if (!$cstmt_insert) {
                 $pdo_err = $pdo->errorInfo();
                 error_log("Prepare failed for comment insert. PDO Error: " . ($pdo_err[2] ?? "Unknown error"));
                 throw new PDOException("数据库预处理语句失败 (提交评论)。");
            }
            
            if ($cstmt_insert->execute([$id, $user_id_for_comment, $parent_id_submission, $name_to_submit, $email_to_submit, $comment_content])) {
                $_SESSION['comment_success'] = '评论已成功提交！';
                $new_comment_id = $pdo->lastInsertId();
                header('Location: post.php?id=' . $id . '#comment-' . $new_comment_id); 
                exit;
            } else {
                 $stmt_error_info_comment = $cstmt_insert->errorInfo();
                 error_log("Comment insertion failed (post_id: $id): " . ($stmt_error_info_comment[2] ?? "Unknown statement error"));
                 $comment_error_message = '评论提交失败，请重试。';
            }
        } catch (PDOException $e) {
            $error_detail = $e->getMessage();
            if (strpos(strtolower($error_detail), 'no such table') !== false || strpos(strtolower($error_detail), 'unknown table') !== false && strpos($error_detail, 'comments') !== false) {
                $comment_error_message = "评论提交失败：'comments' 表不存在。请联系管理员。";
            } else {
                $comment_error_message = '评论提交时发生数据库错误。详情: ' . htmlspecialchars($error_detail);
            }
            error_log("Error inserting comment (post_id: $id): " . $error_detail);
        }
    }
}


if (isset($_SESSION['comment_success'])) {
    $comment_success_message = $_SESSION['comment_success'];
    unset($_SESSION['comment_success']);
}

// --- Fetch Comments ---
$raw_comments = [];
try {
    // Test 'comments' table
    $test_comments_stmt = $pdo->query("SELECT 1 FROM comments LIMIT 1");
    if ($test_comments_stmt === false) {
        // Query failed, table might not exist or other SQL error
        $pdo_error_test_comments = $pdo->errorInfo();
        error_log("Test query on 'comments' table failed. PDO Error: " . ($pdo_error_test_comments[2] ?? "Unknown error"));
        throw new PDOException("无法访问评论数据表 ('comments')。");
    }
     // Test 'users' table for join
    $test_users_stmt = $pdo->query("SELECT 1 FROM users LIMIT 1");
    if ($test_users_stmt === false) {
        $pdo_error_test_users = $pdo->errorInfo();
        error_log("Test query on 'users' table failed. PDO Error: " . ($pdo_error_test_users[2] ?? "Unknown error"));
        // This is less critical for basic comment display if user data is optional, but good to know
    }


    $sql_comments = "SELECT c.*, u.username as author_username 
                     FROM comments c 
                     LEFT JOIN users u ON c.user_id = u.id AND c.name != '匿名用户' AND c.user_id IS NOT NULL
                     WHERE c.post_id = ? 
                     ORDER BY c.created_at ASC";
    $cstmt_fetch_comments = $pdo->prepare($sql_comments);
    if (!$cstmt_fetch_comments) {
        $pdo_err_fetch = $pdo->errorInfo();
        error_log("Prepare failed for fetching comments in post.php. PDO Error: " . ($pdo_err_fetch[2] ?? "Unknown error"));
        throw new PDOException("数据库预处理语句失败 (加载评论)。");
    }
    if (!$cstmt_fetch_comments->execute([$id])) {
        $stmt_error_comments = $cstmt_fetch_comments->errorInfo();
        error_log("Execute failed for fetching comments in post.php. Statement Error: " . ($stmt_error_comments[2] ?? "Unknown error"));
        throw new PDOException("数据库执行失败 (加载评论)。");
    }
    $raw_comments = $cstmt_fetch_comments->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_detail = $e->getMessage();
    error_log("Critical error fetching comments (post_id: $id): " . $error_detail . "\nTrace: " . $e->getTraceAsString());
    if (strpos(strtolower($error_detail), 'no such table') !== false || strpos(strtolower($error_detail), 'unknown table') !== false) {
        if (strpos($error_detail, 'comments') !== false) $comment_error_message = "错误：'comments' 表不存在。无法加载评论。";
        elseif (strpos($error_detail, 'users') !== false) $comment_error_message = "错误：'users' 表不存在。部分评论信息可能不完整。";
        else $comment_error_message = "错误：评论功能所需的数据表之一不存在。";
    } elseif (strpos(strtolower($error_detail), 'syntax error') !== false || strpos(strtolower($error_detail), 'unknown column') !== false){
        $comment_error_message = "评论加载失败：数据库查询语法错误或列名不匹配。";
    } else {
        $comment_error_message = "评论加载失败，数据库查询时发生严重错误。";
    }
}

$comments_by_id = [];
$toplevel_comments = [];

if (!empty($raw_comments)) {
    $comment_likes_table_exists = true; // Assume exists unless proven otherwise
    try {
        $test_likes_stmt = $pdo->query("SELECT 1 FROM comment_likes LIMIT 1");
         if ($test_likes_stmt === false) {
            $pdo_error_test_likes = $pdo->errorInfo();
            error_log("Test query on 'comment_likes' table failed. PDO Error: " . ($pdo_error_test_likes[2] ?? "Unknown error"));
            throw new PDOException("无法访问点赞数据表 ('comment_likes')。");
        }
    } catch (PDOException $e) {
        $comment_likes_table_exists = false;
        if(empty($comment_error_message)) $comment_error_message = "点赞功能暂时不可用 ('comment_likes' 表可能不存在或无法访问)。";
        error_log("Error accessing comment_likes table during setup: " . $e->getMessage());
    }

    foreach ($raw_comments as $comment) {
        $comment['user_has_liked'] = false;
        if ($is_user_logged_in && $current_user_id && $comment_likes_table_exists) {
            try {
                $like_check_stmt_loop = $pdo->prepare("SELECT id FROM comment_likes WHERE user_id = ? AND comment_id = ?");
                if(!$like_check_stmt_loop) { $err_l = $pdo->errorInfo(); error_log("Prepare LC loop failed: ".($err_l[2]??"")); throw new PDOException("Prepare LC loop failed"); }
                $like_check_stmt_loop->execute([$current_user_id, $comment['id']]);
                if ($like_check_stmt_loop->fetch()) {
                    $comment['user_has_liked'] = true;
                }
            } catch (PDOException $e) {
                error_log("Error checking like status for comment ID {$comment['id']}: " . $e->getMessage());
            }
        }
        $comment['display_name'] = ($comment['user_id'] && !empty($comment['author_username'])) ? $comment['author_username'] : $comment['name'];
        $comments_by_id[$comment['id']] = $comment;
        $comments_by_id[$comment['id']]['replies'] = [];
    }

    foreach ($comments_by_id as $comment_id_loop => &$comment_data_loop) {
        if ($comment_data_loop['parent_comment_id'] && isset($comments_by_id[$comment_data_loop['parent_comment_id']])) {
            $comments_by_id[$comment_data_loop['parent_comment_id']]['replies'][] = &$comment_data_loop;
        } else {
            $toplevel_comments[] = &$comment_data_loop;
        }
    }
    unset($comment_data_loop); 
}

// Function to Display Comments Recursively
function display_comments_recursive($comments_array, $current_post_id_func, $is_logged_in_func, $depth = 0) {
    // (Function content remains the same as post_v5.php)
    $marginLeft = $depth * 20;
    foreach ($comments_array as $comment_item) {
        echo '<div id="comment-' . $comment_item['id'] . '" class="comment-item p-3 md:p-4 glass-effect rounded-lg ' . ($depth > 0 ? 'mt-3' : 'mb-4 md:mb-6') . '" style="margin-left: ' . $marginLeft . 'px;">';
        echo '  <div class="flex justify-between items-center mb-1">';
        echo '      <p class="font-semibold text-[var(--text-primary)] text-sm md:text-base text-left">' . htmlspecialchars($comment_item['display_name'], ENT_QUOTES, 'UTF-8') . '</p>';
        echo '      <div class="flex items-center space-x-2">';
        if ($is_logged_in_func) { 
            echo '      <form method="POST" action="post.php?id=' . $current_post_id_func . '#comment-' . $comment_item['id'] . '" class="inline-flex items-center">';
            echo '          <input type="hidden" name="action" value="like_comment">';
            echo '          <input type="hidden" name="comment_id_to_like" value="' . $comment_item['id'] . '">';
            echo '          <button type="submit" class="text-xs md:text-sm text-[var(--text-accent)] hover:text-[var(--glow-color)] focus:outline-none ' . ($comment_item['user_has_liked'] ? 'font-bold text-[var(--glow-color)]' : '') . '" title="' . ($comment_item['user_has_liked'] ? '取消赞' : '赞') . '">';
            echo '              <i class="fas fa-thumbs-up"></i> ' . ($comment_item['likes'] ?? 0);
            echo '          </button>';
            echo '      </form>';
        } else { 
            echo '      <span class="text-xs md:text-sm text-[var(--text-secondary)]"><i class="fas fa-thumbs-up"></i> ' . ($comment_item['likes'] ?? 0) . '</span>';
        }
        echo '          <button onclick="prepareReply(' . $comment_item['id'] . ', \'' . htmlspecialchars(addslashes($comment_item['display_name']), ENT_QUOTES, 'UTF-8') . '\')" class="text-xs md:text-sm text-[var(--text-accent)] hover:underline">回复</button>';
        echo '      </div>';
        echo '  </div>';
        echo '  <p class="text-xs text-[var(--text-secondary)] mb-2 text-left">';
        echo '      <time datetime="' . date('Y-m-d H:i:s', strtotime($comment_item['created_at'])) . '">' . date('Y年m月d日 H:i', strtotime($comment_item['created_at'])) . '</time>';
        echo '  </p>';
        echo '  <div class="text-[var(--text-secondary)] text-sm md:text-base whitespace-pre-wrap text-left prose prose-sm dark:prose-invert max-w-none">' . nl2br(htmlspecialchars($comment_item['content'], ENT_QUOTES, 'UTF-8')) . '</div>';

        if (!empty($comment_item['replies'])) {
            echo '<div class="replies-container mt-3 border-l-2 border-[var(--border-color)] pl-3">'; 
            display_comments_recursive($comment_item['replies'], $current_post_id_func, $is_logged_in_func, $depth + 1);
            echo '</div>';
        }
        echo '</div>';
    }
}

?>
<style>
    /* Styles from post_v5.php - unchanged for brevity */
    .post-content-area { padding-left: 1rem; padding-right: 1rem; }
    @media (min-width: 768px) { .post-content-area { padding-left: 2rem; padding-right: 2rem; } }
    @media (min-width: 1024px) { .post-content-area { padding-left: 3rem; padding-right: 3rem; } }
    .post-body img { max-width: 100%; height: auto; border-radius: 0.5rem; margin-top: 1.5em; margin-bottom: 1.5em; display: block; margin-left: auto; margin-right: auto; border: 1px solid var(--border-color); transition: transform 0.3s ease-in-out, box-shadow 0.3s ease-in-out; }
    .post-body img:hover { transform: scale(1.02); box-shadow: 0 8px 16px rgba(0,0,0,0.1), 0 3px 6px rgba(0,0,0,0.08); }
    .comment-form-grid { display: grid; grid-template-columns: 1fr; gap: 1rem; }
    @media (min-width: 768px) { .comment-form-grid { grid-template-columns: 1fr 2fr; gap: 1.5rem; align-items: start; }}
    .email-field-wrapper { overflow: hidden; max-height: 0; opacity: 0; transition: max-height 0.4s ease-out, opacity 0.3s 0.1s ease-out, margin-top 0.4s ease-out; margin-top: 0;}
    .email-field-wrapper.active { max-height: 100px; opacity: 1; margin-top: 1rem; }
    .comment-form-input, #comment-content { width: 100%; padding: 0.6rem 0.8rem; border-radius: 0.375rem; background-color: var(--bg-input, var(--bg-primary)); border: 1px solid var(--border-color); color: var(--text-primary); font-size: 0.9rem;}
    .comment-form-input:focus, #comment-content:focus { outline: none; border-color: var(--text-accent); box-shadow: 0 0 0 2px rgba(var(--text-accent-rgb-val, 99, 179, 237), 0.3); }
    body.light-theme .comment-form-input:focus, body.light-theme #comment-content:focus { box-shadow: 0 0 0 2px rgba(var(--text-accent-rgb-light, 59, 130, 246), 0.3); }
    body.dark-theme .comment-form-input:focus, body.dark-theme #comment-content:focus { box-shadow: 0 0 0 2px rgba(var(--text-accent-rgb-dark, 96, 165, 250), 0.3); }
    .comment-item .prose { font-size: 0.9rem; }
</style>

<div class="container mx-auto px-4 sm:px-6 lg:px-8 relative z-10 mt-6 md:mt-8 mb-12 md:mb-16">
     <div class="mb-4 md:mb-6">
        <a href="blog.php" class="text-sm text-[var(--text-accent)] hover:underline"> 
            &larr; <span data-translate-key="back_to_blog_list">返回博客列表</span>
        </a>
    </div>
     <article class="post-content-area glass-effect rounded-xl">
        <header class="mb-6 md:mb-10 pt-6 md:pt-8 text-center"> 
            <h1 class="text-2xl sm:text-3xl md:text-4xl lg:text-5xl font-bold mb-3 md:mb-4 text-gradient px-2"> 
                <?php echo htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8'); ?>
            </h1>
            <p class="text-xs sm:text-sm text-[var(--text-secondary)]">
                <span data-translate-key="category">分类</span>:
                <?php if (!empty($post['category_name'])): ?>
                    <a href="category.php?id=<?php echo $post['category_id_for_link']; ?>" class="hover:text-[var(--text-accent)]"> 
                        <?php echo htmlspecialchars($post['category_name'], ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                <?php else: ?>
                    <span data-translate-key="uncategorized">未分类</span>
                <?php endif; ?>
                <span class="mx-1 sm:mx-2">|</span>
                <span data-translate-key="published_on">发布于</span>: <time datetime="<?php echo date('Y-m-d', strtotime($post['created_at'])); ?>"><?php echo date('Y年m月d日', strtotime($post['created_at'])); ?></time>
            </p>
        </header>

        <div class="post-body pb-6 md:pb-8 prose dark:prose-invert max-w-none prose-sm sm:prose-base lg:prose-lg 
                    prose-headings:text-[var(--text-primary)] prose-p:text-[var(--text-secondary)] prose-strong:text-[var(--text-primary)]
                    prose-a:text-[var(--text-accent)] hover:prose-a:text-[var(--glow-color)]
                    prose-blockquote:border-[var(--text-accent)] prose-blockquote:text-[var(--text-secondary)]
                    prose-code:bg-gray-200 dark:prose-code:bg-gray-700 prose-code:p-1 prose-code:rounded prose-code:text-sm
                    prose-img:rounded-lg prose-img:shadow-md"> 
            <?php echo $display_content; ?>
        </div>
        <hr class="my-6 md:my-10 border-[var(--border-color)]">

        <section id="comments-section" class="mt-6 md:mt-10 pb-6 md:pb-8 px-2 md:px-0">
            <h2 class="text-xl md:text-2xl font-semibold mb-5 md:mb-6 text-gradient" data-translate-key="comments_section_title">评论区</h2>
            
            <?php if (!empty($comment_error_message)): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-3 md:p-4 mb-4 rounded-md text-sm" role="alert">
                    <p class="font-bold">错误!</p>
                    <p><?php echo htmlspecialchars($comment_error_message, ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            <?php endif; ?>
            <?php if ($comment_success_message): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-3 md:p-4 mb-6 rounded-md text-sm" role="alert">
                    <p><?php echo htmlspecialchars($comment_success_message, ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            <?php endif; ?>

            <form id="comment-form" method="post" action="post.php?id=<?php echo $id; ?>#comment-form" class="mb-8 p-4 md:p-6 glass-effect rounded-lg"> 
                <input type="hidden" name="parent_comment_id" id="parent_comment_id" value="<?php echo $reply_to_comment_id ? htmlspecialchars($reply_to_comment_id, ENT_QUOTES, 'UTF-8') : ''; ?>">
                <div id="reply-to-info" class="mb-3 text-sm text-[var(--text-secondary)]" style="display: <?php echo $reply_to_comment_id ? 'block' : 'none'; ?>;">
                    <span data-translate-key="replying_to">回复</span> <strong id="reply-to-username" class="text-[var(--text-accent)]"></strong>:
                    <a href="post.php?id=<?php echo $id; ?>#comment-form" onclick="cancelReply(event)" class="ml-2 text-xs hover:underline" data-translate-key="cancel_reply">(取消回复)</a> 
                </div>

                <div class="comment-form-grid">
                    <div class="comment-form-meta">
                        <?php if ($is_user_logged_in): ?>
                            <p class="text-sm text-[var(--text-primary)] mb-1 text-left">
                                <span data-translate-key="posting_as">您将以</span> <strong class="text-[var(--text-accent)]"><?php echo htmlspecialchars($current_username, ENT_QUOTES, 'UTF-8'); ?></strong> <span data-translate-key="posting_as_suffix">的身份发表评论。</span>
                            </p>
                            <div class="comment-form-anonymous mt-1 flex items-center">
                                <input type="checkbox" name="is_anonymous" id="is_anonymous" <?php echo (isset($_POST['is_anonymous']) && $_POST['is_anonymous'] === 'on') ? 'checked' : ''; ?> class="h-4 w-4 rounded border-gray-300 text-[var(--text-accent)] focus:ring-[var(--text-accent)] focus:ring-offset-0 focus:ring-offset-transparent focus:ring-2 align-middle">
                                <label for="is_anonymous" class="ml-2 text-sm text-[var(--text-secondary)] align-middle" data-translate-key="post_anonymously">作为匿名用户发表</label>
                            </div>
                        <?php else: ?>
                            <div>
                                <label for="comment-name" class="block text-sm font-medium text-[var(--text-primary)] mb-1 text-left" data-translate-key="nickname">昵称 <span id="nickname-required-indicator" class="text-red-500 <?php echo (isset($_POST['is_anonymous']) && $_POST['is_anonymous'] === 'on') ? 'hidden' : ''; ?>">*</span></label>
                                <input type="text" name="name" id="comment-name" value="<?php echo htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" class="comment-form-input">
                            </div>
                            <div class="comment-form-anonymous mt-1 flex items-center">
                                <input type="checkbox" name="is_anonymous" id="is_anonymous" <?php echo (isset($_POST['is_anonymous']) && $_POST['is_anonymous'] === 'on') ? 'checked' : ''; ?> class="h-4 w-4 rounded border-gray-300 text-[var(--text-accent)] focus:ring-[var(--text-accent)] focus:ring-offset-0 focus:ring-offset-transparent focus:ring-2 align-middle">
                                <label for="is_anonymous" class="ml-2 text-sm text-[var(--text-secondary)] align-middle" data-translate-key="post_anonymously">匿名发表</label>
                            </div>
                            <div class="email-field-wrapper <?php echo (isset($_POST['is_anonymous']) && $_POST['is_anonymous'] === 'on') ? '' : 'active'; ?>">
                                <label for="comment-email" class="block text-sm font-medium text-[var(--text-primary)] mb-1 text-left" data-translate-key="email">邮箱 <span class="text-xs text-[var(--text-secondary)]" data-translate-key="email_optional_private">(可选, 不会公开)</span></label>
                                <input type="email" name="email" id="comment-email" value="<?php echo htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" class="comment-form-input">
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="comment-form-content-wrapper">
                        <div>
                            <label for="comment-content" class="block text-sm font-medium text-[var(--text-primary)] mb-1 text-left" data-translate-key="comment_content_label">评论内容 <span class="text-red-500">*</span></label>
                            <textarea name="comment_content" id="comment-content" rows="5" required class="comment-form-input"><?php echo htmlspecialchars($_POST['comment_content'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </div>
                        <div class="comment-form-submit-area">
                            <button type="submit" class="px-5 py-2 bg-[var(--text-accent)] text-white font-semibold rounded-lg hover:opacity-90 transition-opacity text-sm" data-translate-key="submit_comment">提交评论</button>
                        </div>
                    </div>
                </div>
            </form>

            <div class="space-y-4 md:space-y-6">
                <?php if (empty($toplevel_comments) && empty($comment_error_message)): ?>
                    <p class="text-[var(--text-secondary)] text-center" data-translate-key="no_comments_yet">暂无评论，快来抢沙发吧！</p>
                <?php elseif (!empty($toplevel_comments)): ?>
                    <?php display_comments_recursive($toplevel_comments, $id, $is_user_logged_in); ?>
                <?php endif; ?>
            </div>
        </section>
     </article>
</div>
<script>
// Script from post_v5.php (JavaScript logic remains largely the same)
document.addEventListener('DOMContentLoaded', () => {
    const isUserLoggedIn = <?php echo json_encode($is_user_logged_in); ?>;
    const anonymousCheckbox = document.getElementById('is_anonymous');
    const nameInput = document.getElementById('comment-name'); 
    const emailWrapper = document.querySelector('.email-field-wrapper'); 
    const emailInput = document.getElementById('comment-email'); 
    const nicknameRequiredIndicator = document.getElementById('nickname-required-indicator');
    
    const mainCommentForm = document.getElementById('comment-form');
    const parentCommentIdInput = document.getElementById('parent_comment_id');
    const replyToInfoDiv = document.getElementById('reply-to-info');
    const replyToUsernameSpan = document.getElementById('reply-to-username');
    const commentContentArea = document.getElementById('comment-content');

    function updateCommentNameFieldRequirement() {
        if (!isUserLoggedIn && nameInput) { 
            if (anonymousCheckbox.checked) {
                nameInput.removeAttribute('required');
                if(nicknameRequiredIndicator) nicknameRequiredIndicator.classList.add('hidden');
            } else {
                nameInput.setAttribute('required', 'required');
                if(nicknameRequiredIndicator) nicknameRequiredIndicator.classList.remove('hidden');
            }
        } else if (nicknameRequiredIndicator) { 
             if(nicknameRequiredIndicator) nicknameRequiredIndicator.classList.add('hidden');
        }
    }

    function toggleFieldsForAnonymous() {
        if (!isUserLoggedIn && nameInput && emailWrapper && emailInput) { 
            if (anonymousCheckbox.checked) {
                nameInput.disabled = true; nameInput.value = '匿名用户'; 
                emailWrapper.classList.remove('active'); emailInput.value = '';
            } else {
                nameInput.disabled = false; if (nameInput.value === '匿名用户') nameInput.value = '';
                emailWrapper.classList.add('active');
            }
        }
        updateCommentNameFieldRequirement();
    }

    if (anonymousCheckbox) {
        anonymousCheckbox.addEventListener('change', toggleFieldsForAnonymous);
        toggleFieldsForAnonymous(); 
    }

    window.prepareReply = function(commentId, usernameToReply) {
        if (parentCommentIdInput && replyToInfoDiv && replyToUsernameSpan && mainCommentForm && commentContentArea) {
            parentCommentIdInput.value = commentId;
            replyToUsernameSpan.textContent = usernameToReply;
            replyToInfoDiv.style.display = 'block';
            mainCommentForm.scrollIntoView({ behavior: 'smooth', block: 'center' });
            commentContentArea.focus();
            commentContentArea.value = "@" + usernameToReply + " ";
        }
    }
    
    window.cancelReply = function(event) {
        event.preventDefault(); 
        if (parentCommentIdInput && replyToInfoDiv && commentContentArea) {
            parentCommentIdInput.value = '';
            replyToInfoDiv.style.display = 'none';
            const oldReplyUsername = replyToUsernameSpan.textContent;
            replyToUsernameSpan.textContent = '';
            if (commentContentArea.value.trim() === "@" + oldReplyUsername) commentContentArea.value = '';
            window.location.href = 'post.php?id=<?php echo $id; ?>#comment-form'; // Corrected JS Link
        }
    }

    const urlParams = new URLSearchParams(window.location.search);
    const replyToIdFromUrl = urlParams.get('replyto');
    if (replyToIdFromUrl) {
        const commentToReplyElement = document.getElementById('comment-' + replyToIdFromUrl);
        if (commentToReplyElement) {
            const usernameElement = commentToReplyElement.querySelector('p.font-semibold'); 
            const username = usernameElement ? usernameElement.textContent.trim() : 'User ' + replyToIdFromUrl;
            prepareReply(replyToIdFromUrl, username);
        }
    }

    const imagesInPost = document.querySelectorAll('.post-body img');
    if (imagesInPost.length > 0) {
        const lightboxOverlay = document.createElement('div'); 
        lightboxOverlay.id = 'lightbox-overlay';
        lightboxOverlay.className = 'fixed inset-0 bg-black bg-opacity-90 hidden items-center justify-center z-[1000] transition-opacity duration-300 ease-in-out cursor-zoom-out opacity-0';
        const lightboxImg = document.createElement('img');
        lightboxImg.id = 'lightbox-img';
        lightboxImg.className = 'max-w-[90vw] max-h-[90vh] object-contain rounded-lg shadow-xl cursor-default';
        const lightboxClose = document.createElement('span');
        lightboxClose.id = 'lightbox-close';
        lightboxClose.innerHTML = '&times;';
        lightboxClose.className = 'absolute top-4 right-6 text-white text-5xl font-light cursor-pointer hover:text-gray-300 transition-colors leading-none p-1';
        
        lightboxOverlay.appendChild(lightboxImg);
        lightboxOverlay.appendChild(lightboxClose);
        document.body.appendChild(lightboxOverlay);

        imagesInPost.forEach(img => {
            if (img.closest('a')) return;
            if (img.naturalWidth > 50 && img.naturalHeight > 50 && !img.classList.contains('no-lightbox')) { 
                 img.style.cursor = 'zoom-in';
                 img.addEventListener('click', (e) => { 
                    e.stopPropagation(); lightboxImg.src = img.src;
                    lightboxOverlay.classList.remove('hidden'); lightboxOverlay.classList.add('flex'); 
                    setTimeout(() => lightboxOverlay.style.opacity = '1', 10); 
                });
            }
        });
        const closeLightboxFunc = () => {
            lightboxOverlay.style.opacity = '0';
            setTimeout(() => {
                lightboxOverlay.classList.add('hidden'); lightboxOverlay.classList.remove('flex');
                lightboxImg.src = ""; 
            }, 300);
        }
        lightboxOverlay.addEventListener('click', (e) => { if (e.target === lightboxOverlay) closeLightboxFunc(); });
        lightboxClose.addEventListener('click', closeLightboxFunc);
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !lightboxOverlay.classList.contains('hidden')) closeLightboxFunc(); });
    }
});
</script>
<?php require_once __DIR__ . '/includes/footer.php'; // Assuming footer.php is in an 'includes' subdirectory ?>
