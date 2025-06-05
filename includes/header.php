<?php
// /www/wwwroot/maxcaulfield.cn/includes/header.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

global $pdo; 

$path_to_root = '';
if (strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false) {
    $path_to_root = '../';
}

// --- Active Ban Check for Logged-in Users (remains the same) ---
if (isset($_SESSION['user_id'])) {
    if (!isset($pdo) || !$pdo instanceof PDO) {
        if (function_exists('getPDO')) {
            $pdo = getPDO(); 
        } else {
            error_log("CRITICAL: getPDO() function not found in header.php. Ban check cannot proceed.");
        }
    }

    if (isset($pdo) && $pdo instanceof PDO) { 
        try {
            $stmt_check_ban = $pdo->prepare("SELECT id, is_banned, ban_expires_at, ban_reason FROM users WHERE id = ?");
            $stmt_check_ban->execute([$_SESSION['user_id']]);
            $current_user_status = $stmt_check_ban->fetch(PDO::FETCH_ASSOC);

            if ($current_user_status) {
                $is_currently_banned_in_db = $current_user_status['is_banned'] == 1;
                
                if ($is_currently_banned_in_db) {
                    $ban_is_still_active = true; 
                    if ($current_user_status['ban_expires_at'] !== null && strtotime($current_user_status['ban_expires_at']) < time()) {
                        $unban_stmt = $pdo->prepare("UPDATE users SET is_banned = 0, ban_expires_at = NULL, ban_reason = NULL WHERE id = ?");
                        if ($unban_stmt->execute([$_SESSION['user_id']])) {
                            $ban_is_still_active = false; 
                        } else {
                            error_log("Failed to auto-unban user ID: " . $_SESSION['user_id'] . " during header check.");
                        }
                    }

                    if ($ban_is_still_active) {
                        $_SESSION['ban_message'] = '您因违规行为被禁止登录。'; 
                        if (!empty($current_user_status['ban_reason']) && $current_user_status['ban_reason'] !== 'N/A') {
                             $_SESSION['ban_message'] .= ' 原因: ' . htmlspecialchars($current_user_status['ban_reason']);
                        }
                        if ($current_user_status['ban_expires_at'] !== null) {
                            $_SESSION['ban_message'] .= ' 封禁至: ' . date('Y-m-d H:i', strtotime($current_user_status['ban_expires_at']));
                        } else {
                            $_SESSION['ban_message'] .= ' 此封禁为永久性的。';
                        }
                        
                        unset($_SESSION['user_id']);
                        unset($_SESSION['username']);
                        
                        $login_url = $path_to_root . 'login.php?banned=1';
                        if (!headers_sent()) {
                            header('Location: ' . $login_url);
                            exit;
                        } else {
                            error_log("header.php: Headers already sent. Cannot redirect banned user ID: " . ($current_user_status['id'] ?? 'unknown'));
                            echo "<script>window.location.href='" . addslashes($login_url) . "';</script><noscript>You are banned and have been logged out. Please <a href=\"" . htmlspecialchars($login_url) . "\">click here</a>.</noscript>";
                            exit;
                        }
                    }
                }
            } else { 
                unset($_SESSION['user_id']);
                unset($_SESSION['username']);
            }
        } catch (PDOException $e) {
            error_log("CRITICAL: Database error checking user ban status in header.php: " . $e->getMessage());
        }
    }
}
// --- End Active Ban Check ---

// Site default title (Simplified Chinese)
$site_default_title = "麦青春的麦田";
$site_main_title_suffix = " - 麦青春的博客";

$title_for_page_final = $site_default_title; 
if (isset($page_actual_title) && !empty($page_actual_title)) {
    $title_for_page_final = $page_actual_title; 
} elseif (isset($page_title_display) && !empty($page_title_display)) { 
    $title_for_page_final = $page_title_display . $site_main_title_suffix;
}

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title_for_page_final); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/tsparticles@2.12.0/tsparticles.bundle.min.js"></script>
    <link rel="stylesheet" href="<?php echo $path_to_root; ?>css/style.css">
    <style>
        :root {
            --bg-primary-dark: #0b0f19; --bg-secondary-dark: rgba(17, 24, 39, 0.8); --bg-glass-dark: rgba(31, 41, 55, 0.6); --text-primary-dark: #e5e7eb; --text-secondary-dark: #9ca3af; --text-accent-dark: #a7c7e7; --border-color-dark: rgba(55, 65, 81, 0.5); --glow-color-dark: rgba(173, 216, 230, 0.7); --text-accent-rgb: 167, 199, 231;
            --bg-primary-light: #f9fafb; --bg-secondary-light: rgba(255, 255, 255, 0.85); --bg-glass-light: rgba(243, 244, 246, 0.7); --text-primary-light: #1f2937; --text-secondary-light: #4b5563; --text-accent-light: #3b82f6; --border-color-light: rgba(209, 213, 219, 0.6); --glow-color-light: rgba(59, 130, 246, 0.6); --text-accent-rgb-light: 59, 130, 246;
            --bg-primary: var(--bg-primary-dark); --bg-secondary: var(--bg-secondary-dark); --bg-glass: var(--bg-glass-dark); --text-primary: var(--text-primary-dark); --text-secondary: var(--text-secondary-dark); --text-accent: var(--text-accent-dark); --border-color: var(--border-color-dark); --glow-color: var(--glow-color-dark);
            scroll-behavior: smooth;
        }
        body.light-theme {
            --bg-primary: var(--bg-primary-light); --bg-secondary: var(--bg-secondary-light); --bg-glass: var(--bg-glass-light); --text-primary: var(--text-primary-light); --text-secondary: var(--text-secondary-light); --text-accent: var(--text-accent-light); --border-color: var(--border-color-light); --glow-color: var(--glow-color-light);
        }
        body { background-color: var(--bg-primary); color: var(--text-primary); font-family: 'Inter', sans-serif; transition: background-color 0.3s ease, color 0.3s ease; overflow-x: hidden; position: relative; }
        body::before { content: ''; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: linear-gradient(-45deg, #2563eb, #7c3aed, #16a34a, #0891b2, #4f46e5, #c026d3, #2563eb); background-size: 400% 400%; animation: gradient-flow 25s linear infinite; z-index: -2; opacity: 0.7; transition: opacity 0.3s ease; }
        body.light-theme::before { opacity: 0.4; }
        @keyframes gradient-flow { 0% { background-position: 0% 50%; } 100% { background-position: 100% 50%; } }
        #tsparticles-global { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -1; }
        .glass-effect { background-color: var(--bg-glass); backdrop-filter: blur(12px); border: 1px solid var(--border-color); border-radius: 0.75rem; transition: background-color 0.3s ease, border-color 0.3s ease; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .main-header-nav { position: sticky; top: 0; z-index: 50; transition: background-color 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease; }
        .main-header-nav.scrolled { background-color: var(--bg-glass); backdrop-filter: blur(16px); border-bottom: 1px solid var(--border-color); box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .main-header-nav:not(.scrolled) { background-color: transparent; border-bottom: 1px solid transparent; }
        .text-gradient { background-image: linear-gradient(to right, var(--text-accent), var(--glow-color)); -webkit-background-clip: text; background-clip: text; color: transparent; }
        body.light-theme .text-gradient { background-image: linear-gradient(to right, #3b82f6, #60a5fa); }
        ::-webkit-scrollbar { width: 8px; } ::-webkit-scrollbar-track { background: var(--bg-primary); }
        ::-webkit-scrollbar-thumb { background-color: var(--text-accent); border-radius: 10px; border: 2px solid var(--bg-primary); }
        ::-webkit-scrollbar-thumb:hover { background-color: var(--glow-color); }
        .user-auth-link-item { display: inline-flex; align-items: center; padding: 0.5rem 0.75rem; margin-left: 0.25rem; border-radius: 0.375rem; font-weight: 500; color: var(--text-secondary); transition: background-color 0.2s ease, color 0.2s ease; text-decoration: none; }
        .user-auth-link-item:hover { background-color: var(--bg-glass); color: var(--text-primary); text-decoration: none; }
        .user-auth-link-item i { margin-right: 0.4rem; }
        .user-display-name-item { display: inline-flex; align-items: center; padding: 0.5rem 0.75rem; margin-left: 0.25rem; color: var(--text-primary); font-weight: 500; }
        .user-display-name-item i { margin-right: 0.4rem; color: var(--text-accent); }
    </style>
</head>
<body class="dark-theme">
    <div id="tsparticles-global"></div>

    <header id="main-header-nav" class="main-header-nav py-4 sticky top-0 z-50 mb-8 md:mb-12">
        <div class="container mx-auto px-6 flex justify-between items-center">
            <a href="<?php echo $path_to_root; ?>index.html" class="text-2xl md:text-3xl font-bold hover:opacity-80 transition-opacity"> <!-- Changed to index.html -->
                <span class="text-gradient">麦青春的麦田</span>
            </a>
            <div class="flex items-center space-x-1 sm:space-x-2 md:space-x-3">
                <a href="<?php echo $path_to_root; ?>blog.php" aria-label="返回博客列表" title="返回博客列表" class="p-2 rounded-full hover:bg-[var(--bg-glass)] transition-colors text-[var(--text-secondary)] hover:text-[var(--text-primary)]">
                    <i class="fas fa-home text-lg"></i>
                </a>
                <button id="theme-toggle" aria-label="切换主题" class="p-2 rounded-full hover:bg-[var(--bg-glass)] transition-colors text-[var(--text-secondary)] hover:text-[var(--text-primary)]">
                    <i class="fas fa-sun text-lg"></i> <i class="fas fa-moon text-lg hidden"></i>
                </button>
                
                <!-- User Authentication Links Start Here -->
                <div class="flex items-center">
                    <?php if (isset($_SESSION['user_id']) && isset($_SESSION['username'])): ?>
                        <span class="user-display-name-item text-sm">
                            <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($_SESSION['username']); ?>
                        </span>
                        <a href="<?php echo $path_to_root; ?>logout.php" title="登出" class="user-auth-link-item">
                            <i class="fas fa-sign-out-alt text-lg"></i>
                            <span class="hidden sm:inline">登出</span>
                        </a>
                    <?php else: ?>
                        <a href="<?php echo $path_to_root; ?>login.php" title="登录" class="user-auth-link-item">
                            <i class="fas fa-sign-in-alt text-lg"></i>
                            <span class="hidden sm:inline">登录</span>
                        </a>
                        <a href="<?php echo $path_to_root; ?>register.php" title="注册" class="user-auth-link-item">
                            <i class="fas fa-user-plus text-lg"></i>
                            <span class="hidden sm:inline">注册</span>
                        </a>
                    <?php endif; ?>
                </div>
                <!-- User Authentication Links End Here -->
            </div>
        </div>
    </header>
    <script>
        // Theme Toggle and Header Scroll JS (remains the same)
        const themeToggle = document.getElementById('theme-toggle');
        const headerNav = document.getElementById('main-header-nav'); 
        let currentTheme = localStorage.getItem('theme') || 'dark'; 

        function applyCurrentTheme() { 
            const sunIcon = themeToggle ? themeToggle.querySelector('.fa-sun') : null;
            const moonIcon = themeToggle ? themeToggle.querySelector('.fa-moon') : null;
            if (currentTheme === 'light') {
                document.body.classList.add('light-theme'); document.body.classList.remove('dark-theme');
                if (sunIcon) sunIcon.classList.add('hidden'); if (moonIcon) moonIcon.classList.remove('hidden');
            } else {
                document.body.classList.add('dark-theme'); document.body.classList.remove('light-theme');
                if (sunIcon) sunIcon.classList.remove('hidden'); if (moonIcon) moonIcon.classList.add('hidden');
            }
        }
        
        document.addEventListener('DOMContentLoaded', () => { 
            if (themeToggle) { 
                applyCurrentTheme(); 
                themeToggle.addEventListener('click', () => { 
                    currentTheme = document.body.classList.contains('light-theme') ? 'dark' : 'light'; 
                    localStorage.setItem('theme', currentTheme); 
                    applyCurrentTheme(); 
                }); 
            }
            
            if (headerNav) { 
                window.addEventListener('scroll', () => { 
                    headerNav.classList.toggle('scrolled', window.scrollY > 30); 
                }); 
            }
            
            if (typeof tsParticles !== 'undefined' && document.getElementById('tsparticles-global')) { 
                tsParticles.load("tsparticles-global", { 
                     fpsLimit: 60, particles: { number: { value: 70, density: { enable: false } }, color: { value: ["#FFFFFF", "#ADD8E6", "#F0F8FF", "#a7c7e7", "#60a5fa"] }, shape: { type: ["circle", "star"] }, opacity: { value: {min: 0.1, max: 0.5}, animation: { enable: true, speed: 0.9, minimumValue: 0.1, sync: false } }, size: { value: { min: 0.5, max: 2.0 }, animation: { enable: false } }, links: { color: "random", distance: 140, enable: true, opacity: 0.1, width: 1, warp: false }, collisions: { enable: false }, move: { direction: "none", enable: true, outModes: { default: "out" }, random: true, speed: 0.6, straight: false, attract: { enable: false } } }, interactivity: { events: { onHover: { enable: true, mode: "repulse" }, onClick: { enable: true, mode: "push" }, resize: true, }, modes: { repulse: { distance: 80, duration: 0.4, speed: 0.8 }, push: { quantity: 2 }, }, }, detectRetina: true, background: { opacity: 0 } 
                }).catch(error => { console.error("Error loading global tsParticles:", error); }); 
            }
        });
    </script>
    <!-- Specific page content will be inserted by PHP -->
