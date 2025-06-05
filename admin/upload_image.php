<?php
// /www/wwwroot/maxcaulfield.cn/admin/upload_image.php

// IMPORTANT: Ensure this script is protected if it's not already handled by a session check
// For example, you might want to include db.php and check $_SESSION['admin_id']
/*
require_once __DIR__ . '/../db.php'; // Includes config.php and getPDO()
if (!isset($_SESSION['admin_id'])) {
    http_response_code(403); // Forbidden
    echo json_encode(['error' => ['message' => '用户未授权。']]);
    exit;
}
*/

header('Content-Type: application/json');

// --- Configuration ---
// Define the ABSOLUTE path to the uploads directory.
// Example: /www/wwwroot/maxcaulfield.cn/uploads/images/
// __DIR__ is the directory of the current script (admin).
// So, __DIR__ . '/../uploads/images/' would point to /www/wwwroot/maxcaulfield.cn/uploads/images/
$upload_dir_path = __DIR__ . '/../uploads/images/';

// Define the BASE URL for accessing the uploaded images.
// This MUST correspond to how the $upload_dir_path is served by your web server.
// Example: https://maxcaulfield.cn/uploads/images/
// You might need to adjust this based on your server configuration.
// A common way to determine this dynamically if the script is in a known structure:
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$domain_name = $_SERVER['HTTP_HOST'];
// Assuming 'admin' is one level down from the web root where 'uploads' also resides.
$base_url_path = str_replace('/admin', '', dirname($_SERVER['SCRIPT_NAME'])); // Get path relative to domain
$upload_url_base = $protocol . $domain_name . $base_url_path . '/uploads/images/';


$allowed_mime_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$max_file_size = 5 * 1024 * 1024; // 5 MB

// --- Helper Function for Error Response ---
function send_error_response($message, $http_code = 400) {
    http_response_code($http_code);
    echo json_encode(['error' => ['message' => $message]]);
    exit;
}

// --- Create Upload Directory if it doesn't exist ---
if (!file_exists($upload_dir_path)) {
    if (!mkdir($upload_dir_path, 0775, true)) { // 0775 for permissions, true for recursive
        send_error_response('无法创建上传目录。请检查服务器权限。', 500);
    }
}
if (!is_writable($upload_dir_path)) {
    send_error_response('上传目录不可写。请检查服务器权限。', 500);
}

// --- File Upload Handling ---
if (!isset($_FILES['file']['error']) || is_array($_FILES['file']['error'])) {
    send_error_response('无效的上传参数。');
}

switch ($_FILES['file']['error']) {
    case UPLOAD_ERR_OK:
        break;
    case UPLOAD_ERR_NO_FILE:
        send_error_response('未选择任何文件进行上传。');
        break;
    case UPLOAD_ERR_INI_SIZE:
    case UPLOAD_ERR_FORM_SIZE:
        send_error_response('上传的文件大小超出了限制。');
        break;
    default:
        send_error_response('未知的上传错误。');
}

// Check file size
if ($_FILES['file']['size'] > $max_file_size) {
    send_error_response('文件过大。最大允许 ' . ($max_file_size / 1024 / 1024) . ' MB。');
}

// Check MIME type
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime_type = $finfo->file($_FILES['file']['tmp_name']);
if (!in_array($mime_type, $allowed_mime_types)) {
    send_error_response('无效的文件类型。只允许上传图片 (JPEG, PNG, GIF, WEBP)。');
}

// Validate file extension
$file_extension = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
if (!in_array($file_extension, $allowed_extensions)) {
    send_error_response('无效的文件扩展名。只允许 ' . implode(', ', $allowed_extensions) . '。');
}

// Generate a unique filename to prevent overwriting and for security
// Using timestamp and random string.
$original_filename_without_ext = pathinfo($_FILES['file']['name'], PATHINFO_FILENAME);
// Sanitize the original filename part (optional, but good practice)
$sanitized_original_filename = preg_replace("/[^a-zA-Z0-9_\-\.]/", "_", $original_filename_without_ext);
$unique_filename = time() . '_' . substr(md5(uniqid(rand(), true)), 0, 8) . '_' . $sanitized_original_filename . '.' . $file_extension;
$destination_path = $upload_dir_path . $unique_filename;

// Move the uploaded file
if (!move_uploaded_file($_FILES['file']['tmp_name'], $destination_path)) {
    send_error_response('移动上传文件失败。请检查服务器配置。', 500);
}

// --- Success Response ---
// TinyMCE expects a JSON response with a "location" property containing the URL of the uploaded image.
$image_url = $upload_url_base . $unique_filename;
echo json_encode(['location' => $image_url]);
exit;

?>
