<?php
// /www/wwwroot/maxcaulfield.cn/reset_password.php
require_once __DIR__ . '/db.php';

$pdo = getPDO();
$errors = [];
$success_message = '';

// Check if user is authorized to reset password (via session flag from forgot_password.php)
if (!isset($_SESSION['reset_token_valid_for_user'])) {
    // Optional: Add an error message or simply redirect
    // $_SESSION['error_message'] = "无效的密码重置请求或链接已过期。";
    header('Location: forgot_password.php');
    exit;
}
$user_id_to_reset = $_SESSION['reset_token_valid_for_user'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($new_password) || empty($confirm_password)) {
        $errors[] = '新密码和确认密码均为必填项。';
    } elseif (strlen($new_password) < 6) {
        $errors[] = '新密码至少需要6位字符。';
    } elseif ($new_password !== $confirm_password) {
        $errors[] = '两次输入的新密码不一致。';
    }

    if (empty($errors)) {
        $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        try {
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, security_answer_hash = NULL, security_question_id = NULL WHERE id = ?");
            // Optionally, nullify security question/answer after successful password reset for added security, forcing user to set new ones.
            // Or just update password: $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");

            if ($stmt->execute([$new_password_hash, $user_id_to_reset])) {
                unset($_SESSION['reset_token_valid_for_user']); // Invalidate the reset token
                // unset($_SESSION['reset_user_id']); // Already unset if using token logic properly
                $success_message = '密码已成功重置！您现在可以使用新密码登录。';
            } else {
                $errors[] = '密码重置失败，请重试。';
            }
        } catch (PDOException $e) {
            $errors[] = '重置密码时发生数据库错误。';
            error_log("Password reset DB error for user ID $user_id_to_reset: " . $e->getMessage());
        }
    }
}

$page_actual_title = "重置密码 - 麦青春的博客";
require_once __DIR__ . '/includes/header.php';
?>

<div class="container mx-auto px-6 py-12 max-w-md">
    <div class="bg-[var(--bg-secondary)] glass-effect p-8 rounded-xl shadow-2xl">
        <h1 class="text-3xl font-bold text-gradient mb-6 text-center">重置密码</h1>

        <?php if (!empty($errors)): ?>
            <div class="mb-4 bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md" role="alert">
                <p class="font-bold">错误!</p>
                <ul><?php foreach ($errors as $error): ?><li><?php echo htmlspecialchars($error); ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <div class="mb-4 bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-md" role="alert">
                <p><?php echo htmlspecialchars($success_message); ?></p>
                <p class="mt-2"><a href="login.php" class="font-semibold hover:underline">点击这里登录 &rarr;</a></p>
            </div>
        <?php else: ?>
            <form action="reset_password.php" method="POST" class="space-y-6">
                <div>
                    <label for="new_password" class="block text-sm font-medium text-[var(--text-primary)] mb-1">新密码</label>
                    <input type="password" name="new_password" id="new_password" required
                           class="w-full p-3 rounded-lg bg-[var(--bg-primary)] border border-[var(--border-color)] focus:ring-2 focus:ring-[var(--text-accent)] focus:border-[var(--text-accent)] transition-colors"
                           placeholder="至少6位字符">
                </div>
                <div>
                    <label for="confirm_password" class="block text-sm font-medium text-[var(--text-primary)] mb-1">确认新密码</label>
                    <input type="password" name="confirm_password" id="confirm_password" required
                           class="w-full p-3 rounded-lg bg-[var(--bg-primary)] border border-[var(--border-color)] focus:ring-2 focus:ring-[var(--text-accent)] focus:border-[var(--text-accent)] transition-colors">
                </div>
                <div>
                    <button type="submit"
                            class="w-full px-6 py-3 bg-[var(--text-accent)] text-white font-semibold rounded-lg hover:opacity-90 transition-opacity">
                        确认重置密码
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
