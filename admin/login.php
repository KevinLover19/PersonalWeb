<?php
// /www/wwwroot/maxcaulfield.cn/admin/login.php
// Corrected path to db.php, which should be in the root directory
require_once __DIR__ . '/../db.php'; 

// Ensure session is started (db.php or config.php should handle this)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If admin is already logged in, redirect to dashboard
if (isset($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$pdo = getPDO(); // Get PDO instance from db.php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = '用户名和密码不能为空。';
    } else {
        try {
            $stmt = $pdo->prepare(
                'SELECT id, password_hash FROM admins WHERE username = ? LIMIT 1'
            );
            $stmt->execute([$username]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($admin && password_verify($password, $admin['password_hash'])) {
                // Login successful: write to Session
                $_SESSION['admin_id']   = $admin['id'];
                $_SESSION['admin_name'] = $username; // Storing username in session

                header('Location: dashboard.php');
                exit;
            } else {
                $error = '无效的用户名或密码。';
            }
        } catch (PDOException $e) {
            $error = '数据库错误，请稍后再试。';
            error_log("Admin login DB error: " . $e->getMessage());
        }
    }
}

// Since translation functionality was removed from the main site header,
// we'll use hardcoded Chinese text here as well.
$page_title = "管理员登录 - 麦青春的博客";

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: 'Inter', sans-serif; 
            background-color: #f0f2f5; /* A slightly different background for admin */
        }
        .login-card {
            background-color: white;
            padding: 2rem; /* p-8 */
            border-radius: 0.75rem; /* rounded-xl */
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); /* shadow-lg */
            width: 100%;
            max-width: 24rem; /* max-w-sm */
        }
        .form-input {
            width: 100%;
            padding: 0.75rem 1rem; /* p-3 */
            border: 1px solid #d1d5db; /* border-gray-300 */
            border-radius: 0.375rem; /* rounded-md */
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }
        .form-input:focus {
            outline: none;
            border-color: #3b82f6; /* focus:border-blue-500 */
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3); /* focus:ring focus:ring-blue-200 focus:ring-opacity-50 */
        }
        .submit-button {
            width: 100%;
            padding: 0.75rem 1rem; /* py-3 */
            background-color: #2563eb; /* bg-blue-600 */
            color: white;
            font-weight: 600; /* font-semibold */
            border-radius: 0.375rem; /* rounded-md */
            transition: background-color 0.15s ease-in-out;
        }
        .submit-button:hover {
            background-color: #1d4ed8; /* hover:bg-blue-700 */
        }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen">
    <div class="login-card">
        <h2 class="text-2xl font-bold text-center text-gray-800 mb-8">管理员登录</h2>

        <?php if ($error): ?>
            <div class="mb-4 p-4 bg-red-100 border-l-4 border-red-500 text-red-700 rounded-md" role="alert">
                <p><?php echo htmlspecialchars($error); ?></p>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" class="space-y-6">
            <div>
                <label for="username" class="block text-sm font-medium text-gray-700 mb-1">用户名</label>
                <input type="text" name="username" id="username" class="form-input" 
                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
            </div>
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">密码</label>
                <input type="password" name="password" id="password" class="form-input" required>
            </div>
            <div>
                <button type="submit" class="submit-button">
                    登录
                </button>
            </div>
        </form>
        <p class="mt-6 text-center text-sm text-gray-600">
            <a href="../login.php" class="font-medium text-blue-600 hover:text-blue-500 hover:underline">返回用户登录 &rarr;</a>
        </p>
    </div>
</body>
</html>
