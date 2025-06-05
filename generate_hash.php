<?php
$password = 'Nanci_fanghua520'; // 在这里设置一个强密码
$hash = password_hash($password, PASSWORD_DEFAULT);
echo "原始密码: " . htmlspecialchars($password) . "<br>";
echo "密码哈希: " . htmlspecialchars($hash);
?>
