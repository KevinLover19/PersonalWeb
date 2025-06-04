<?php
require_once '../db.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    if ($title && $content) {
        $pdo = getPDO();
        $stmt = $pdo->prepare('INSERT INTO posts (title, content, created_at, updated_at) VALUES (?, ?, NOW(), NOW())');
        $stmt->execute([$title, $content]);
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Title and content are required.';
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>New Post</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
</head>
<body class="p-6">
<h1 class="text-2xl mb-4">New Post</h1>
<?php if (!empty($error)): ?>
<p class="text-red-500 mb-2"><?php echo htmlspecialchars($error); ?></p>
<?php endif; ?>
<form method="post">
<input type="text" name="title" placeholder="Title" class="border w-full p-2 mb-3" required>
<textarea name="content" placeholder="Content" class="border w-full p-2 mb-3" rows="10" required></textarea>
<button type="submit" class="bg-blue-500 text-white px-4 py-2">Publish</button>
</form>
</body>
</html>
