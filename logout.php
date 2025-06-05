<?php
// /www/wwwroot/maxcaulfield.cn/logout.php
require_once __DIR__ . '/config.php'; // To ensure session is started

// Unset all user-specific session variables
unset($_SESSION['user_id']);
unset($_SESSION['username']);

// Or, to destroy the entire session (if admin and user sessions are completely separate and this won't affect admin)
// session_destroy(); // Be cautious with this if you have other session data you need to preserve.

// Redirect to blog.php
header('Location: blog.php'); // Changed from index.php to blog.php
exit;
?>
