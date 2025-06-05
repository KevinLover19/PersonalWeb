<?php
// /www/wwwroot/maxcaulfield.cn/forgot_password.php
require_once __DIR__ . '/db.php';

$pdo = getPDO();
$errors = [];
$step = 1; // Step 1: Enter username/email. Step 2: Answer security question.
$user_id_for_reset = null;
$security_question_text = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['step1_submit'])) {
        $identifier = trim($_POST['identifier'] ?? '');
        if (empty($identifier)) {
            $errors[] = '请输入您的用户名或邮箱地址。';
        } else {
            try {
                $is_email = filter_var($identifier, FILTER_VALIDATE_EMAIL);
                if ($is_email) {
                    $stmt = $pdo->prepare("SELECT u.id, sq.question_text FROM users u JOIN security_questions sq ON u.security_question_id = sq.id WHERE u.email = ?");
                } else {
                    $stmt = $pdo->prepare("SELECT u.id, sq.question_text FROM users u JOIN security_questions sq ON u.security_question_id = sq.id WHERE u.username = ?");
                }
                $stmt->execute([$identifier]);
                $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user_data && !empty($user_data['question_text'])) {
                    $_SESSION['reset_user_id'] = $user_data['id'];
                    $_SESSION['reset_security_question'] = $user_data['question_text'];
                    $step = 2;
                    $user_id_for_reset = $user_data['id']; // Not strictly needed here, but for consistency
                    $security_question_text = $user_data['question_text'];
                } else {
                    $errors[] = '未找到用户，或用户未设置安全问题。';
                }
            } catch (PDOException $e) {
                $errors[] = '数据库查询错误。';
                error_log("Forgot password step 1 DB error: " . $e->getMessage());
            }
        }
    } elseif (isset($_POST['step2_submit'])) {
        if (!isset($_SESSION['reset_user_id']) || !isset($_SESSION['reset_security_question'])) {
            $errors[] = '会话已过期或无效，请重新开始。';
            $step = 1; // Force back to step 1
        } else {
            $user_id_for_reset = $_SESSION['reset_user_id'];
            $security_question_text = $_SESSION['reset_security_question']; // For display if error
            $security_answer_attempt = trim($_POST['security_answer'] ?? '');
            $step = 2; // Stay on step 2 for display

            if (empty($security_answer_attempt)) {
                $errors[] = '请输入安全问题的答案。';
            } else {
                try {
                    $stmt = $pdo->prepare("SELECT security_answer_hash FROM users WHERE id = ?");
                    $stmt->execute([$user_id_for_reset]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($user && password_verify(strtolower(trim($security_answer_attempt)), $user['security_answer_hash'])) {
                        // Answer is correct, generate a reset token and redirect to reset_password.php
                        // For simplicity here, we'll just set a session flag. A token is more secure.
                        $_SESSION['reset_token_valid_for_user'] = $user_id_for_reset;
                        unset($_SESSION['reset_security_question']); // Clean up
                        header('Location: reset_password.php');
                        exit;
                    } else {
                        $errors[] = '安全问题答案不正确。';
                    }
                } catch (PDOException $e) {
                    $errors[] = '验证答案时发生数据库错误。';
                    error_log("Forgot password step 2 DB error: " . $e->getMessage());
                }
            }
        }
    }
}

// If returning to step 2 due to an error, repopulate question
if ($step == 2 && isset($_SESSION['reset_security_question']) && empty($security_question_text)) {
    $security_question_text = $_SESSION['reset_security_question'];
}


$page_actual_title = "忘记密码 - 麦青春的博客";
require_once __DIR__ . '/includes/header.php';
?>

<div class="container mx-auto px-6 py-12 max-w-md">
    <div class="bg-[var(--bg-secondary)] glass-effect p-8 rounded-xl shadow-2xl">
        <h1 class="text-3xl font-bold text-gradient mb-6 text-center">忘记密码</h1>

        <?php if (!empty($errors)): ?>
            <div class="mb-4 bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md" role="alert">
                <p class="font-bold">错误!</p>
                <ul><?php foreach ($errors as $error): ?><li><?php echo htmlspecialchars($error); ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>

        <?php if ($step === 1): ?>
            <form action="forgot_password.php" method="POST" class="space-y-6">
                <p class="text-sm text-[var(--text-secondary)]">请输入您的用户名或注册邮箱以开始密码重置流程。</p>
                <div>
                    <label for="identifier" class="block text-sm font-medium text-[var(--text-primary)] mb-1">用户名或邮箱</label>
                    <input type="text" name="identifier" id="identifier" required
                           class="w-full p-3 rounded-lg bg-[var(--bg-primary)] border border-[var(--border-color)] focus:ring-2 focus:ring-[var(--text-accent)] focus:border-[var(--text-accent)] transition-colors">
                </div>
                <div>
                    <button type="submit" name="step1_submit"
                            class="w-full px-6 py-3 bg-[var(--text-accent)] text-white font-semibold rounded-lg hover:opacity-90 transition-opacity">
                        下一步
                    </button>
                </div>
            </form>
        <?php elseif ($step === 2 && !empty($security_question_text)): ?>
            <form action="forgot_password.php" method="POST" class="space-y-6">
                <input type="hidden" name="user_id_for_reset_display" value="<?php echo htmlspecialchars($_SESSION['reset_user_id'] ?? ''); ?>">
                <p class="text-sm text-[var(--text-secondary)]">请回答以下安全问题以验证您的身份：</p>
                <div>
                    <p class="block text-sm font-medium text-[var(--text-primary)] mb-1">安全问题:</p>
                    <p class="p-3 rounded-lg bg-[var(--bg-primary)] border border-[var(--border-color)]"><?php echo htmlspecialchars($security_question_text); ?></p>
                </div>
                <div>
                    <label for="security_answer" class="block text-sm font-medium text-[var(--text-primary)] mb-1">您的答案</label>
                    <input type="text" name="security_answer" id="security_answer" required autofocus
                           class="w-full p-3 rounded-lg bg-[var(--bg-primary)] border border-[var(--border-color)] focus:ring-2 focus:ring-[var(--text-accent)] focus:border-[var(--text-accent)] transition-colors">
                </div>
                <div>
                    <button type="submit" name="step2_submit"
                            class="w-full px-6 py-3 bg-[var(--text-accent)] text-white font-semibold rounded-lg hover:opacity-90 transition-opacity">
                        验证答案
                    </button>
                </div>
            </form>
        <?php endif; ?>
        <p class="mt-6 text-center text-sm text-[var(--text-secondary)]">
            记起密码了? <a href="login.php" class="font-medium text-[var(--text-accent)] hover:underline">返回登录</a>
        </p>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
