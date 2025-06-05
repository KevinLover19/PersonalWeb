<?php
require_once '../db.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$pdo = getPDO();
$admins = $pdo->query('SELECT username, password_hash FROM admins')->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>Admin Passwords</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
</head>
<body class="p-6">
<h1 class="text-2xl mb-4">Admin Password Hashes</h1>
<table class="table-auto border">
<tr><th class="px-4 py-2 border">Username</th><th class="px-4 py-2 border">Password Hash</th></tr>
<?php foreach ($admins as $a): ?>
<tr>
<td class="border px-4 py-2"><?php echo htmlspecialchars($a['username']); ?></td>
<td class="border px-4 py-2 font-mono break-all"><?php echo htmlspecialchars($a['password_hash']); ?></td>
</tr>
<?php endforeach; ?>
</table>
<a href="dashboard.php" class="text-blue-600 mt-4 inline-block">Back to Dashboard</a>
</body>
</html>
