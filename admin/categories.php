<?php
require_once '../db.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$pdo = getPDO();
$categories = $pdo->query('SELECT id, name FROM categories')->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>Categories</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
</head>
<body class="p-6">
<h1 class="text-2xl mb-4">Categories</h1>
<ul>
<?php foreach ($categories as $c): ?>
<li><?php echo htmlspecialchars($c['name']); ?></li>
<?php endforeach; ?>
</ul>
<a href="dashboard.php" class="text-blue-600">Back</a>
</body>
</html>
