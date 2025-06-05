<?php
// /www/wwwroot/maxcaulfield.cn/config.php
// 数据库和其他配置

// ** 数据库连接配置 **
define('DB_HOST', 'localhost');
define('DB_NAME', 'maxcaulfield_cn');
define('DB_USER', 'maxcaulfield_cn');
define('DB_PASS', 'd5iKNkpKd2eGxT8p'); // 您之前确认的密码
define('DB_CHARSET', 'utf8mb4');       // 建议添加字符集定义

// ** Session 管理 **
// 确保 session 在脚本早期启动
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// ** 错误报告 (开发时建议取消注释以下两行以显示错误) **
// error_reporting(E_ALL);
// ini_set('display_errors', 1);
?>