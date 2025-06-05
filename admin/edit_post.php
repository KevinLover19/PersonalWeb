<?php
require_once '../db.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$pdo = getPDO();
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$stmt = $pdo->prepare('SELECT * FROM posts WHERE id=?');
$stmt->execute([$id]);
$post = $stmt->fetch();
if (!$post) {
    echo 'Post not found';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    if ($title && $content) {
        $stmt = $pdo->prepare('UPDATE posts SET title=?, content=?, updated_at=NOW() WHERE id=?');
        $stmt->execute([$title, $content, $id]);
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
<title>Edit Post</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
</head>
<body class="p-6">
<h1 class="text-2xl mb-4">Edit Post</h1>
<?php if (!empty($error)): ?>
<p class="text-red-500 mb-2"><?php echo htmlspecialchars($error); ?></p>
<?php endif; ?>
<form method="post">
<input type="text" name="title" value="<?php echo htmlspecialchars($post['title']); ?>" class="border w-full p-2 mb-3" required>
<textarea name="content" class="border w-full p-2 mb-3" rows="10" required><?php echo htmlspecialchars($post['content']); ?></textarea>
<button type="submit" class="bg-blue-500 text-white px-4 py-2">Save</button>
</form>
</body>
</html>
