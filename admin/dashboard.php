<?php
require_once '../db.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$pdo = getPDO();
$posts = $pdo->query('SELECT id, title, created_at FROM posts ORDER BY created_at DESC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>Dashboard</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
</head>
<body class="p-6">
<h1 class="text-2xl mb-4">Dashboard</h1>
<a href="new_post.php" class="text-blue-600">Create New Post</a> |
<a href="view_passwords.php" class="text-blue-600">View Passwords</a> |
<a href="logout.php" class="text-red-600">Logout</a>
<ul class="mt-4">
<?php foreach ($posts as $post): ?>
<li class="mb-2">
<?php echo htmlspecialchars($post['title']); ?> -
<a class="text-blue-600" href="../post.php?id=<?php echo $post['id']; ?>">View</a>
|
<a class="text-green-600" href="edit_post.php?id=<?php echo $post['id']; ?>">Edit</a>
</li>
<?php endforeach; ?>
</ul>
</body>
</html>
