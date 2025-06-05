<?php
// /www/wwwroot/maxcaulfield.cn/login.php
require_once __DIR__ . '/db.php'; // Includes config.php (for session_start) and getPDO()

$pdo = getPDO();
$errors = [];

// If user is already logged in, redirect them to blog.php
if (isset($_SESSION['user_id'])) {
    header('Location: blog.php'); 
    exit;
}

// Check if redirected due to a ban and display session message
// This message is set by header.php if a logged-in user is found to be banned
if (isset($_GET['banned']) && $_GET['banned'] == '1' && isset($_SESSION['ban_message'])) {
    $errors[] = $_SESSION['ban_message']; 
    unset($_SESSION['ban_message']); // Clear message after displaying
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_identifier = trim($_POST['login_identifier'] ?? ''); 
    $password = $_POST['password'] ?? '';

    if (empty($login_identifier) || empty($password)) {
        $errors[] = '用户名/邮箱和密码为必填项。';
    } else {
        try {
            $is_email = filter_var($login_identifier, FILTER_VALIDATE_EMAIL);
            
            if ($is_email) {
                $stmt = $pdo->prepare("SELECT id, username, password_hash, is_banned, ban_expires_at, ban_reason FROM users WHERE email = ?");
            } else {
                $stmt = $pdo->prepare("SELECT id, username, password_hash, is_banned, ban_expires_at, ban_reason FROM users WHERE username = ?");
            }
            
            $stmt->execute([$login_identifier]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $is_currently_banned = $user['is_banned'] == 1;
                if ($is_currently_banned) {
                    // Check if the ban has expired
                    if ($user['ban_expires_at'] !== null && strtotime($user['ban_expires_at']) < time()) {
                        // Ban has expired, auto-unban
                        $unban_stmt = $pdo->prepare("UPDATE users SET is_banned = 0, ban_expires_at = NULL, ban_reason = NULL WHERE id = ?");
                        $unban_stmt->execute([$user['id']]);
                        $is_currently_banned = false; // User is no longer considered banned for this login attempt
                    }
                }

                if ($is_currently_banned) {
                    // Assemble detailed ban message for a direct login attempt while banned
                    $ban_error_message = '您因违规行为被禁止登录。';
                    if (!empty($user['ban_reason']) && $user['ban_reason'] !== 'N/A') {
                        $ban_error_message .= ' 原因: ' . htmlspecialchars($user['ban_reason']);
                    }
                    if ($user['ban_expires_at'] !== null) {
                        $ban_error_message .= ' 封禁至: ' . date('Y-m-d H:i', strtotime($user['ban_expires_at']));
                    } else {
                        $ban_error_message .= ' 此封禁为永久性的。';
                    }
                    // Add this specific message to errors only if not already set by session (to avoid duplicate messages)
                    if (!in_array($ban_error_message, $errors)) { 
                        $errors[] = $ban_error_message;
                    }
                } elseif (password_verify($password, $user['password_hash'])) {
                    // Password is correct, set up session
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];

                    $update_stmt = $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?");
                    $update_stmt->execute([$user['id']]);

                    if (isset($_GET['redirect']) && !empty($_GET['redirect']) && strpos($_GET['redirect'], 'login.php') === false && strpos($_GET['redirect'], 'register.php') === false) {
                        header('Location: ' . $_GET['redirect']);
                    } else {
                        header('Location: blog.php'); 
                    }
                    exit;
                } else if (!$is_currently_banned) { 
                     $errors[] = '无效的用户名/邮箱或密码。';
                }
            } else { 
                $errors[] = '无效的用户名/邮箱或密码。';
            }
        } catch (PDOException $e) {
            $errors[] = '登录失败，数据库发生错误。请稍后再试。';
            error_log("User login DB error: " . $e->getMessage());
        }
    }
}

$baseUrl = ''; 
// Since translations are removed from header, we set the title directly here.
$page_actual_title = "用户登录 - 麦青春的博客"; 
$active_nav_icon = ''; 

require_once __DIR__ . '/includes/header.php'; 
?>

<div class="container mx-auto px-6 py-12 max-w-md">
    <div class="bg-[var(--bg-secondary)] glass-effect p-8 rounded-xl shadow-2xl">
        <h1 class="text-3xl font-bold text-gradient mb-8 text-center">用户登录</h1>

        <?php if (!empty($errors)): ?>
            <div class="mb-6 bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md" role="alert">
                <p class="font-bold">错误!</p>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form action="login.php<?php echo isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : ''; ?>" method="POST" class="space-y-6">
            <div>
                <label for="login_identifier" class="block text-sm font-medium text-[var(--text-primary)] mb-1">用户名或邮箱</label>
                <input type="text" name="login_identifier" id="login_identifier" value="<?php echo htmlspecialchars($_POST['login_identifier'] ?? ''); ?>" required
                       class="w-full p-3 rounded-lg bg-[var(--bg-primary)] border border-[var(--border-color)] focus:ring-2 focus:ring-[var(--text-accent)] focus:border-[var(--text-accent)] transition-colors">
            </div>
            <div>
                <div class="flex justify-between items-center mb-1">
                    <label for="password" class="block text-sm font-medium text-[var(--text-primary)]">密码</label>
                    <a href="forgot_password.php" class="text-xs text-[var(--text-accent)] hover:underline">忘记密码?</a>
                </div>
                <input type="password" name="password" id="password" required
                       class="w-full p-3 rounded-lg bg-[var(--bg-primary)] border border-[var(--border-color)] focus:ring-2 focus:ring-[var(--text-accent)] focus:border-[var(--text-accent)] transition-colors">
            </div>
            <div>
                <button type="submit"
                        class="w-full px-6 py-3 bg-[var(--text-accent)] text-white font-semibold rounded-lg hover:opacity-90 transition-opacity focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-[var(--bg-secondary)] focus:ring-[var(--text-accent)]">
                    登录
                </button>
            </div>
        </form>
        <p class="mt-6 text-center text-sm text-[var(--text-secondary)]">
            还没有账户?
            <a href="register.php" class="font-medium text-[var(--text-accent)] hover:underline">点此注册</a>
        </p>
         <p class="mt-2 text-center text-sm text-[var(--text-secondary)]">
            <a href="admin/login.php" class="font-medium text-[var(--text-accent)] hover:underline">管理员登录 &rarr;</a>
        </p>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
