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
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700&family=Noto+Sans+SC:wght@400;700&display=swap" rel="stylesheet">
<style>
    body { font-family: 'Inter', 'Noto Sans SC', 'Noto Emoji', sans-serif; }
</style>
</head>
<body class="max-w-3xl mx-auto p-4">
<h1 class="text-3xl mb-4">博客文章</h1>
<ul>
<?php foreach ($posts as $post): ?>
<li class="mb-2">
<a class="text-blue-600" href="post.php?id=<?php echo $post['id']; ?>">
<?php echo htmlspecialchars($post['title']); ?></a>
</li>
<?php endforeach; ?>
</ul>
</body>
</html>
