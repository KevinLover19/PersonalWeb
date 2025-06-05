<?php
// /www/wwwroot/maxcaulfield.cn/admin/dashboard.php
require_once __DIR__ . '/../db.php'; // 引入根目录的 db.php

// 检查管理员是否已登录
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$pdo = getPDO();
$message       = '';
$message_type  = ''; // 'success' or 'error'

// ---------- 删除文章逻辑（保持不变） ----------
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $post_id_to_delete = intval($_GET['id']);
    if ($post_id_to_delete > 0) {
        $pdo->beginTransaction();
        try {
            $stmt_delete_comments = $pdo->prepare('DELETE FROM comments WHERE post_id = ?');
            $stmt_delete_comments->execute([$post_id_to_delete]);

            $stmt_delete_post = $pdo->prepare('DELETE FROM posts WHERE id = ?');
            $stmt_delete_post->execute([$post_id_to_delete]);

            if ($stmt_delete_post->rowCount() > 0) {
                $pdo->commit();
                $message      = "文章 ID: {$post_id_to_delete} 已成功删除。";
                $message_type = 'success';
            } else {
                $pdo->rollBack();
                $message      = "未找到文章 ID: {$post_id_to_delete}，或删除失败。";
                $message_type = 'error';
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $message      = "删除文章时发生数据库错误: " . $e->getMessage();
            $message_type = 'error';
            error_log("Error deleting post ID {$post_id_to_delete}: " . $e->getMessage());
        }
    } else {
        $message      = "无效的文章 ID。";
        $message_type = 'error';
    }
}
// ---------- 文章列表查询 ----------
$sql = 'SELECT p.id, p.title, p.created_at, p.status, c.name AS category_name
        FROM posts p
        LEFT JOIN categories c ON p.category_id = c.id
        ORDER BY p.created_at DESC';
try {
    $posts = $pdo->query($sql)->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching posts with categories/status: {$e->getMessage()}");
    try {
        // 回退：没有分类字段
        $fallback = $pdo->query('SELECT id, title, created_at, status FROM posts ORDER BY created_at DESC')->fetchAll();
        $posts    = [];
        foreach ($fallback as $row) {
            $row['category_name'] = null;
            $posts[]              = $row;
        }
        if (empty($message)) {
            $message      = "加载文章列表时遇到问题（与分类字段有关），部分信息可能缺失。错误：{$e->getMessage()}";
            $message_type = 'error';
        }
    } catch (PDOException $e2) {
        error_log("Fallback query failed: {$e2->getMessage()}");
        $posts = [];
        if (empty($message)) {
            $message      = "加载文章列表失败，请检查数据库。错误：{$e2->getMessage()}";
            $message_type = 'error';
        }
    }
}

// ---------- 多语言 ----------
$translations = [
    'zh-CN' => [
        'dashboard_title'       => '博客后台管理',
        'manage_posts'          => '文章管理',
        'manage_users'          => '用户管理',
        'manage_categories'     => '分类管理',           // 新增
        'view_users_button'     => '查看注册用户',
        'view_categories_button'=> '查看所有分类',       // 新增
        'create_new_post'       => '撰写新文章',
        'logout'                => '登出',
        'posts_list'            => '文章列表',
        'id'                    => 'ID',
        'title'                 => '标题',
        'category'              => '分类',
        'status'                => '状态',
        'date'                  => '发布日期',
        'actions'               => '操作',
        'view'                  => '查看',
        'edit'                  => '编辑',
        'delete'                => '删除',
        'no_posts_found'        => '还没有文章，快去写一篇吧！',
        'delete_confirm'        => '确定要删除这篇文章吗？此操作无法撤销，且相关评论也将被删除。',
        'status_published'      => '已发布',
        'status_draft'          => '草稿',
        'uncategorized'         => '未分类',
        'status_unknown'        => '未知',
    ],
];
$lang = $_SESSION['admin_lang'] ?? 'zh-CN';
$t    = $translations[$lang] ?? $translations['zh-CN'];

$path_to_root = '../'; // 供前台链接使用
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo htmlspecialchars($t['dashboard_title']); ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
    body { font-family:'Inter',sans-serif;background-color:#f4f7f6;color:#333 }
    .header-bar{background-color:#2d3748;color:#fff}
    .action-btn{display:inline-flex;align-items:center;justify-content:center;padding:0.375rem 0.75rem;border-radius:0.375rem;font-size:0.875rem;font-weight:500;transition:all .15s;margin-right:0.25rem;box-shadow:0 1px 2px rgba(0,0,0,.05)}
    .action-btn i{margin-right:0.3rem}
    .action-btn-view{background-color:#3b82f6;color:#fff}.action-btn-view:hover{background-color:#2563eb}
    .action-btn-edit{background-color:#f59e0b;color:#fff}.action-btn-edit:hover{background-color:#d97706}
    .action-btn-delete{background-color:#ef4444;color:#fff}.action-btn-delete:hover{background-color:#dc2626}
    .status-badge{padding:0.2rem 0.6rem;border-radius:9999px;font-size:0.75rem;font-weight:500}
    .status-published{background-color:#d1fae5;color:#065f46}
    .status-draft{background-color:#e5e7eb;color:#374151}
    .status-unknown{background-color:#fee2e2;color:#991b1b}
    .table th{background-color:#f8f9fa}
    .table th,.table td{border-bottom-width:1px;border-color:#e5e7eb;padding:0.75rem 1rem;text-align:left}
    .table tr:hover td{background-color:#f9fafb}
    .dashboard-card{background:#fff;border-radius:0.75rem;box-shadow:0 10px 15px -3px rgba(0,0,0,.1),0 4px 6px -2px rgba(0,0,0,.05);padding:1.5rem;transition:transform .2s,box-shadow .2s}
    .dashboard-card:hover{transform:translateY(-5px);box-shadow:0 20px 25px -5px rgba(0,0,0,.1),0 10px 10px -5px rgba(0,0,0,.04)}
</style>
</head>
<body class="min-h-screen">

<header class="header-bar shadow-lg">
    <div class="container mx-auto px-6 py-3 flex justify-between items-center">
        <h1 class="text-xl font-semibold tracking-tight"><?php echo htmlspecialchars($t['dashboard_title']); ?></h1>
        <nav class="space-x-4">
            <a href="categories.php" class="text-gray-300 hover:text-white text-sm"><i class="fas fa-list mr-1"></i><?php echo htmlspecialchars($t['manage_categories']); ?></a>
            <a href="logout.php" class="text-gray-300 hover:text-white text-sm"><i class="fas fa-sign-out-alt mr-1"></i><?php echo htmlspecialchars($t['logout']); ?></a>
        </nav>
    </div>
</header>

<main class="container mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <?php if ($message): ?>
        <div class="mb-6 p-4 rounded-md <?php echo $message_type==='error'?'bg-red-100 border-l-4 border-red-500 text-red-700':'bg-green-100 border-l-4 border-green-500 text-green-700'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <!-- Dashboard Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
        <a href="new_post.php" class="dashboard-card block hover:no-underline">
            <div class="flex items-center text-green-600 mb-3">
                <i class="fas fa-plus-circle fa-2x mr-3"></i>
                <h3 class="text-xl font-semibold text-gray-700"><?php echo htmlspecialchars($t['create_new_post']); ?></h3>
            </div>
            <p class="text-gray-600 text-sm">点击这里开始撰写一篇新的博客文章。</p>
        </a>

        <a href="users.php" class="dashboard-card block hover:no-underline">
            <div class="flex items-center text-blue-600 mb-3">
                <i class="fas fa-users fa-2x mr-3"></i>
                <h3 class="text-xl font-semibold text-gray-700"><?php echo htmlspecialchars($t['manage_users']); ?></h3>
            </div>
            <p class="text-gray-600 text-sm"><?php echo htmlspecialchars($t['view_users_button']); ?>并管理他们的账户。</p>
        </a>

        <a href="categories.php" class="dashboard-card block hover:no-underline">
            <div class="flex items-center text-purple-600 mb-3">
                <i class="fas fa-list-alt fa-2x mr-3"></i>
                <h3 class="text-xl font-semibold text-gray-700"><?php echo htmlspecialchars($t['manage_categories']); ?></h3>
            </div>
            <p class="text-gray-600 text-sm"><?php echo htmlspecialchars($t['view_categories_button']); ?>。</p>
        </a>
    </div>

    <div class="flex justify-between items-center mb-8">
        <h2 class="text-3xl font-bold text-gray-800"><?php echo htmlspecialchars($t['posts_list']); ?></h2>
    </div>

    <div class="bg-white shadow-2xl rounded-xl overflow-x-auto">
        <table class="min-w-full leading-normal table">
            <thead>
                <tr>
                    <th><?php echo htmlspecialchars($t['id']); ?></th>
                    <th><?php echo htmlspecialchars($t['title']); ?></th>
                    <th><?php echo htmlspecialchars($t['category']); ?></th>
                    <th><?php echo htmlspecialchars($t['status']); ?></th>
                    <th><?php echo htmlspecialchars($t['date']); ?></th>
                    <th><?php echo htmlspecialchars($t['actions']); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$posts): ?>
                    <tr><td colspan="6" class="px-6 py-10 text-center text-gray-500"><?php echo htmlspecialchars($t['no_posts_found']); ?></td></tr>
                <?php else: ?>
                    <?php foreach ($posts as $post): ?>
                        <?php
                            $status_raw   = strtolower($post['status'] ?? 'unknown');
                            $status_class = $status_raw === 'published' ? 'status-published' :
                                            ($status_raw === 'draft' ? 'status-draft' : 'status-unknown');
                            $status_text  = $status_raw === 'published' ? $t['status_published'] :
                                            ($status_raw === 'draft' ? $t['status_draft'] : $t['status_unknown']);
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4"><?php echo $post['id']; ?></td>
                            <td class="px-6 py-4">
                                <a href="<?php echo $path_to_root; ?>post.php?id=<?php echo $post['id']; ?>" target="_blank" class="text-indigo-600 hover:text-indigo-900 font-semibold">
                                    <?php echo htmlspecialchars($post['title']); ?>
                                </a>
                            </td>
                            <td class="px-6 py-4"><?php echo htmlspecialchars($post['category_name'] ?? $t['uncategorized']); ?></td>
                            <td class="px-6 py-4"><span class="status-badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
                            <td class="px-6 py-4"><?php echo date('Y-m-d H:i', strtotime($post['created_at'])); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <a href="<?php echo $path_to_root; ?>post.php?id=<?php echo $post['id']; ?>" target="_blank" class="action-btn action-btn-view" title="<?php echo htmlspecialchars($t['view']); ?>">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="edit_post.php?id=<?php echo $post['id']; ?>" class="action-btn action-btn-edit" title="<?php echo htmlspecialchars($t['edit']); ?>">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="dashboard.php?action=delete&id=<?php echo $post['id']; ?>" onclick="return confirm('<?php echo htmlspecialchars($t['delete_confirm']); ?>');" class="action-btn action-btn-delete" title="<?php echo htmlspecialchars($t['delete']); ?>">
                                    <i class="fas fa-trash-alt"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<footer class="text-center text-sm text-gray-500 py-6 mt-10 border-t border-gray-200">
    &copy; <?php echo date('Y'); ?> MaxCaulfield Admin Panel.
</footer>

</body>
</html>
