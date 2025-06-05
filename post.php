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

if (isset($_GET['like'])) {
    $cid = intval($_GET['like']);
    $pdo->prepare('UPDATE comments SET likes = likes + 1 WHERE id=?')->execute([$cid]);
    header('Location: post.php?id=' . $id);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? 'Anonymous');
    $content = trim($_POST['content'] ?? '');
    $parent = isset($_POST['parent_id']) ? intval($_POST['parent_id']) : null;
    if ($content !== '') {
        $cstmt = $pdo->prepare('INSERT INTO comments (post_id, parent_id, name, content, likes, created_at) VALUES (?, ?, ?, ?, 0, NOW())');
        $cstmt->execute([$id, $parent, $name, $content]);
        header('Location: post.php?id=' . $id);
        exit;
    }
}

$cstmt = $pdo->prepare('SELECT id, parent_id, name, content, likes, created_at FROM comments WHERE post_id=? ORDER BY created_at ASC');
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
<div class="mb-6 prose border-b pb-4">
<?php echo $post['content']; ?>
</div>
<h2 class="text-xl mb-2">Comments</h2>
<ul class="mb-6">
<?php foreach ($comments as $c): ?>
<li class="mb-2 border-b pb-2" style="margin-left: <?php echo $c['parent_id'] ? '2rem' : '0'; ?>;">
<strong><?php echo htmlspecialchars($c['name']); ?></strong>
<span class="text-gray-500 text-sm ml-2"><?php echo $c['created_at']; ?></span>
<p><?php echo nl2br(htmlspecialchars($c['content'])); ?></p>
<a href="?id=<?php echo $id; ?>&like=<?php echo $c['id']; ?>" class="text-sm text-blue-600">Like (<?php echo $c['likes']; ?>)</a>
<button onclick="document.getElementById('reply-<?php echo $c['id']; ?>').classList.toggle('hidden')" class="ml-2 text-sm text-green-600">Reply</button>
<form id="reply-<?php echo $c['id']; ?>" method="post" class="hidden mt-2">
<input type="hidden" name="parent_id" value="<?php echo $c['id']; ?>">
<input type="text" name="name" placeholder="Your name" class="border w-full p-2 mb-2">
<textarea name="content" class="border w-full p-2 mb-2" rows="2" required></textarea>
<button class="bg-blue-500 text-white px-2 py-1" type="submit">Send</button>
</form>
</li>
<?php endforeach; ?>
</ul>
<form method="post" class="space-y-2">
<input type="hidden" name="parent_id" value="0">
<input type="text" name="name" placeholder="Your name" class="border w-full p-2">
<textarea name="content" placeholder="Your comment" class="border w-full p-2" rows="4" required></textarea>
<button type="submit" class="bg-blue-500 text-white px-4 py-2">Submit</button>
</form>
</body>
</html>
