<?php
require_once 'db.php';
$pdo = getPDO();
$posts = $pdo->query('SELECT id, title, created_at FROM posts ORDER BY created_at DESC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>博客文章</title>
<link rel="stylesheet" href="https://cdn.tailwindcss.com">
</head>
<body class="max-w-3xl mx-auto p-4">
<h1 class="text-3xl mb-4">博客文章</h1>
<ul>
<?php foreach ($posts as $post): ?>
<li class="mb-2">
<a class="text-blue-600" href="post.php?id=<?php echo $post['id']; ?>">
<?php echo htmlspecialchars($post['title']); ?></a>
<span class="text-gray-500 text-sm ml-2"><?php echo $post['created_at']; ?></span>
</li>
<?php endforeach; ?>
</ul>
</body>
</html>
