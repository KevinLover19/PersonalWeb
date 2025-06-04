<?php
require_once '../db.php';

if (isset($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT id, password_hash FROM admins WHERE username=?');
    $stmt->execute([$username]);
    $admin = $stmt->fetch();
    if ($admin && password_verify($password, $admin['password_hash'])) {
        $_SESSION['admin_id'] = $admin['id'];
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Invalid credentials';
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>Admin Login</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
</head>
<body class="bg-gray-100 flex items-center justify-center h-screen">
<div class="bg-white p-6 rounded shadow-md w-80">
<h2 class="text-xl mb-4">Admin Login</h2>
<?php if ($error): ?>
<p class="text-red-500 mb-2"><?php echo htmlspecialchars($error); ?></p>
<?php endif; ?>
<form method="post">
<input type="text" name="username" placeholder="Username" class="border w-full p-2 mb-3" required>
<input type="password" name="password" placeholder="Password" class="border w-full p-2 mb-3" required>
<button class="bg-blue-500 text-white px-4 py-2" type="submit">Login</button>
</form>
</div>
</body>
</html>
