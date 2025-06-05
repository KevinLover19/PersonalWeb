<?php
// /www/wwwroot/maxcaulfield.cn/register.php
require_once __DIR__ . '/db.php'; 

$pdo = getPDO();
$errors = [];
$success_message = '';

// Fetch security questions
$security_questions = [];
try {
    $stmt_sq = $pdo->query("SELECT id, question_text FROM security_questions ORDER BY id");
    $security_questions = $stmt_sq->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = '无法加载安全问题，请稍后再试。';
    error_log("Error fetching security questions: " . $e->getMessage());
}


// --- CAPTCHA Logic ---
function generate_captcha_question() {
    $num1 = rand(1, 9);
    $num2 = rand(1, 9);
    $_SESSION['captcha_answer'] = $num1 + $num2;
    $_SESSION['captcha_question'] = "计算: $num1 + $num2 = ?";
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['captcha_question'])) {
    generate_captcha_question();
}
$captcha_question = $_SESSION['captcha_question'] ?? 'CAPTCHA 未生成。';
// --- End CAPTCHA Logic ---

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $captcha_user_answer = trim($_POST['captcha'] ?? '');
    $security_question_id = filter_input(INPUT_POST, 'security_question_id', FILTER_VALIDATE_INT);
    $security_answer = trim($_POST['security_answer'] ?? '');

    // CAPTCHA validation
    if (empty($captcha_user_answer) || !isset($_SESSION['captcha_answer']) || intval($captcha_user_answer) !== $_SESSION['captcha_answer']) {
        $errors[] = '人机验证答案不正确，请重试。';
    }
    unset($_SESSION['captcha_answer']); 
    unset($_SESSION['captcha_question']);
    generate_captcha_question(); 
    $captcha_question = $_SESSION['captcha_question'];

    // Other validations
    if (empty($username)) { 
        $errors[] = '用户名为必填项。'; 
    } elseif (strlen($username) < 3 || strlen($username) > 50) {
        $errors[] = '用户名长度必须在3到50个字符之间。';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors[] = '用户名只能包含字母、数字和下划线。';
    }

    if (empty($email)) { 
        $errors[] = '邮箱为必填项。'; 
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = '邮箱格式不正确。';
    }
    
    if (empty($password)) { 
        $errors[] = '密码为必填项。'; 
    } elseif (strlen($password) < 6) { 
        $errors[] = '密码至少需要6位字符。'; 
    }
    if ($password !== $password_confirm) { 
        $errors[] = '两次输入的密码不一致。'; 
    }

    if (empty($security_question_id) || !is_numeric($security_question_id)) {
        $errors[] = '请选择一个安全问题。';
    }
    if (empty($security_answer)) {
        $errors[] = '安全问题答案为必填项。';
    } elseif (strlen($security_answer) < 3) { 
        $errors[] = '安全问题答案至少需要3个字符。';
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) { $errors[] = '用户名已被占用，请选择其他用户名。'; }

            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) { $errors[] = '该邮箱地址已被注册。'; }
        } catch (PDOException $e) {
            $errors[] = '数据库验证出错，请稍后再试。';
            error_log("Registration validation DB error: " . $e->getMessage());
        }
    }

    if (empty($errors)) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $security_answer_hash = password_hash(strtolower(trim($security_answer)), PASSWORD_DEFAULT); 

        try {
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, security_question_id, security_answer_hash, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            if ($stmt->execute([$username, $email, $password_hash, $security_question_id, $security_answer_hash])) {
                $success_message = '注册成功！您现在可以登录了。';
                $_POST = []; 
            } else {
                $errors[] = '注册失败，请重试。';
            }
        } catch (PDOException $e) {
            $errors[] = '注册过程中发生错误，请稍后再试。';
            error_log("Registration insert DB error: " . $e->getMessage());
        }
    }
}

$baseUrl = ''; 
$page_actual_title = "用户注册 - 麦青春的博客"; 

require_once __DIR__ . '/includes/header.php'; 
?>

<div class="container mx-auto px-6 py-12 max-w-md">
    <div class="bg-[var(--bg-secondary)] glass-effect p-8 rounded-xl shadow-2xl">
        <h1 class="text-3xl font-bold text-gradient mb-6 text-center">用户注册</h1>

        <?php if (!empty($errors)): ?>
            <div class="mb-4 bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md" role="alert">
                <p class="font-bold">错误!</p>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <div class="mb-4 bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-md" role="alert">
                <p><?php echo htmlspecialchars($success_message); ?></p>
                <p class="mt-2"><a href="login.php" class="font-semibold hover:underline">点击这里登录 &rarr;</a></p>
            </div>
        <?php else: ?>
            <form action="register.php" method="POST" class="space-y-6">
                <div>
                    <label for="username" class="block text-sm font-medium text-[var(--text-primary)] mb-1">用户名</label>
                    <input type="text" name="username" id="username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required
                           class="w-full p-3 rounded-lg bg-[var(--bg-primary)] border border-[var(--border-color)] focus:ring-2 focus:ring-[var(--text-accent)] focus:border-[var(--text-accent)] transition-colors"
                           placeholder="字母、数字、下划线">
                </div>
                <div>
                    <label for="email" class="block text-sm font-medium text-[var(--text-primary)] mb-1">邮箱</label>
                    <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required
                           class="w-full p-3 rounded-lg bg-[var(--bg-primary)] border border-[var(--border-color)] focus:ring-2 focus:ring-[var(--text-accent)] focus:border-[var(--text-accent)] transition-colors"
                           placeholder="your@example.com">
                </div>
                <div>
                    <label for="password" class="block text-sm font-medium text-[var(--text-primary)] mb-1">密码</label>
                    <input type="password" name="password" id="password" required
                           class="w-full p-3 rounded-lg bg-[var(--bg-primary)] border border-[var(--border-color)] focus:ring-2 focus:ring-[var(--text-accent)] focus:border-[var(--text-accent)] transition-colors"
                           placeholder="至少6位字符">
                </div>
                <div>
                    <label for="password_confirm" class="block text-sm font-medium text-[var(--text-primary)] mb-1">确认密码</label>
                    <input type="password" name="password_confirm" id="password_confirm" required
                           class="w-full p-3 rounded-lg bg-[var(--bg-primary)] border border-[var(--border-color)] focus:ring-2 focus:ring-[var(--text-accent)] focus:border-[var(--text-accent)] transition-colors">
                </div>

                <?php if (!empty($security_questions)): ?>
                <div>
                    <label for="security_question_id" class="block text-sm font-medium text-[var(--text-primary)] mb-1">安全问题</label>
                    <select name="security_question_id" id="security_question_id" required
                            class="w-full p-3 rounded-lg bg-[var(--bg-primary)] border border-[var(--border-color)] focus:ring-2 focus:ring-[var(--text-accent)] focus:border-[var(--text-accent)] transition-colors">
                        <option value="">-- 请选择一个安全问题 --</option>
                        <?php foreach ($security_questions as $sq): ?>
                            <option value="<?php echo $sq['id']; ?>" <?php echo (isset($_POST['security_question_id']) && $_POST['security_question_id'] == $sq['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($sq['question_text']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="security_answer" class="block text-sm font-medium text-[var(--text-primary)] mb-1">安全问题答案</label>
                    <input type="text" name="security_answer" id="security_answer" value="<?php echo htmlspecialchars($_POST['security_answer'] ?? ''); ?>" required
                           class="w-full p-3 rounded-lg bg-[var(--bg-primary)] border border-[var(--border-color)] focus:ring-2 focus:ring-[var(--text-accent)] focus:border-[var(--text-accent)] transition-colors"
                           placeholder="答案会被加密保存">
                </div>
                <?php else: ?>
                    <p class="text-sm text-red-500">安全问题加载失败，暂时无法注册。</p>
                <?php endif; ?>

                <div>
                    <label for="captcha" class="block text-sm font-medium text-[var(--text-primary)] mb-1">
                        人机验证: <?php echo htmlspecialchars($captcha_question); ?>
                    </label>
                    <input type="text" name="captcha" id="captcha" required autocomplete="off"
                           class="w-full p-3 rounded-lg bg-[var(--bg-primary)] border border-[var(--border-color)] focus:ring-2 focus:ring-[var(--text-accent)] focus:border-[var(--text-accent)] transition-colors"
                           placeholder="请输入计算结果">
                </div>
                <div>
                    <button type="submit"
                            class="w-full px-6 py-3 bg-[var(--text-accent)] text-white font-semibold rounded-lg hover:opacity-90 transition-opacity focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-[var(--bg-secondary)] focus:ring-[var(--text-accent)]">
                        注册
                    </button>
                </div>
            </form>
        <?php endif; ?>
        <p class="mt-6 text-center text-sm text-[var(--text-secondary)]">
            已有账户?
            <a href="login.php" class="font-medium text-[var(--text-accent)] hover:underline">点此登录</a>
        </p>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
