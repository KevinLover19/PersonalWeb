<?php
require_once 'db.php';
$pdo = getPDO();
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$stmt = $pdo->prepare('SELECT * FROM posts WHERE id=?');
$stmt->execute([$id]);
$post = $stmt->fetch();
if (!$post) {
    http_response_code(404);
    echo 'Post not found';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? 'Anonymous');
    $content = trim($_POST['content'] ?? '');
    if ($content !== '') {
        $cstmt = $pdo->prepare('INSERT INTO comments (post_id, name, content, created_at) VALUES (?, ?, ?, NOW())');
        $cstmt->execute([$id, $name, $content]);
        header('Location: post.php?id=' . $id);
        exit;
    }
}

$cstmt = $pdo->prepare('SELECT name, content, created_at FROM comments WHERE post_id=? ORDER BY created_at ASC');
$cstmt->execute([$id]);
$comments = $cstmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title><?php echo htmlspecialchars($post['title']); ?></title>
<link rel="stylesheet" href="https://cdn.tailwindcss.com">
</head>
<body class="max-w-3xl mx-auto p-4">
<h1 class="text-3xl mb-4"><?php echo htmlspecialchars($post['title']); ?></h1>
<div class="mb-6 whitespace-pre-wrap border-b pb-4">
<?php echo nl2br(htmlspecialchars($post['content'])); ?>
</div>
<h2 class="text-xl mb-2">Comments</h2>
<ul class="mb-6">
<?php foreach ($comments as $c): ?>
<li class="mb-2 border-b pb-2">
<strong><?php echo htmlspecialchars($c['name']); ?></strong>
<span class="text-gray-500 text-sm ml-2"><?php echo $c['created_at']; ?></span>
<p><?php echo nl2br(htmlspecialchars($c['content'])); ?></p>
</li>
<?php endforeach; ?>
</ul>
<form method="post" class="space-y-2">
<input type="text" name="name" placeholder="Your name" class="border w-full p-2">
<textarea name="content" placeholder="Your comment" class="border w-full p-2" rows="4" required></textarea>
<button type="submit" class="bg-blue-500 text-white px-4 py-2">Submit</button>
</form>
</body>
</html>
