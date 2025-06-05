<?php
// /www/wwwroot/maxcaulfield.cn/admin/new_post.php
// (Canvas filename: new_post_v5.php)

// Enable detailed error reporting for debugging (REMOVE OR COMMENT OUT IN PRODUCTION)
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../db.php'; 

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$pdo = getPDO();
if (!$pdo) {
    // Log this error for the admin, and show a user-friendly message.
    error_log("FATAL ERROR: Database connection could not be established in new_post.php.");
    die("数据库连接失败。请检查配置或联系管理员。");
}
$message = '';
$message_type = ''; 
$admin_id = $_SESSION['admin_id'];

$cover_upload_dir_relative = '/uploads/post_covers/'; 
$cover_upload_path_absolute = __DIR__ . '/..' . $cover_upload_dir_relative; 

// Check and create upload directory
if (!file_exists($cover_upload_path_absolute)) {
    if (!mkdir($cover_upload_path_absolute, 0775, true)) { // Attempt to create recursively
        $message .= (empty($message) ? '' : '<br>') . "严重警告：无法自动创建封面图片上传目录: " . htmlspecialchars($cover_upload_path_absolute) . "。请手动在网站根目录下创建 'uploads/post_covers/' 并设置正确的写入权限。";
        if(empty($message_type)) $message_type = 'error';
    }
} elseif (!is_writable($cover_upload_path_absolute)) {
     $message .= (empty($message) ? '' : '<br>') . "警告：封面图片上传目录不可写: " . htmlspecialchars($cover_upload_path_absolute) . "。请检查服务器权限。";
     if(empty($message_type)) $message_type = 'error';
}

// Initialize form variables for repopulation on error or for sticky form
$form_title = $_POST['title'] ?? '';
$form_content_for_textarea = $_POST['content'] ?? ''; 
$form_category_id = $_POST['category_id'] ?? null;
$form_status = $_POST['status'] ?? 'draft';
$form_tags_input = $_POST['tags'] ?? '';
$form_cover_image_url_text = $_POST['cover_image_url_text'] ?? '';

$categories_for_form = [];
try {
    $stmt_categories_fetch_new = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC");
     if ($stmt_categories_fetch_new) {
        $categories_for_form = $stmt_categories_fetch_new->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // This case might indicate a problem with the PDO connection itself or a more fundamental query issue
        throw new PDOException("分类查询未能返回结果对象。 PDO Error: " . implode(":", $pdo->errorInfo()));
    }
} catch (PDOException $e) {
    $message .= (empty($message) ? '' : '<br>') . "获取分类失败: " . $e->getMessage();
    if(empty($message_type)) $message_type = 'error';
    error_log("Error fetching categories for new_post.php: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Re-assign from POST for processing, $form_ variables are for re-display
    $title = trim($_POST['title'] ?? '');
    $content_from_editor = $_POST['content'] ?? ''; 
    $category_id = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
    $status = trim($_POST['status'] ?? 'draft');
    $tags_input = trim($_POST['tags'] ?? '');
    $cover_image_url_text_input = trim($_POST['cover_image_url_text'] ?? '');
    $final_cover_image_url = ''; // This will hold the path to be saved

    // Basic validation
    if (empty($title)) {
        $message = '文章标题不能为空。';
        $message_type = 'error';
    } elseif (empty($content_from_editor)) {
        $message = '文章内容不能为空。';
        $message_type = 'error';
    } 
    // Only proceed if basic validation passes
    if (empty($message_type)) {
        // Handle Cover Image File Upload
        if (isset($_FILES['cover_image_file']) && $_FILES['cover_image_file']['error'] == UPLOAD_ERR_OK) {
             if (!is_writable($cover_upload_path_absolute)) { // Double-check writability at point of use
                 $message = '封面上传目录不可写，无法保存图片。请检查路径: ' . htmlspecialchars($cover_upload_path_absolute);
                 $message_type = 'error';
            } else {
                $allowed_mime_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $file_tmp_name = $_FILES['cover_image_file']['tmp_name'];
                $file_mime_type = mime_content_type($file_tmp_name); // Get MIME type of the uploaded file
                $file_size = $_FILES['cover_image_file']['size'];
                $max_size = 5 * 1024 * 1024; // 5MB

                if (!in_array($file_mime_type, $allowed_mime_types)) {
                    $message = '无效的封面图片类型。检测到的类型: ' . htmlspecialchars($file_mime_type) . '。请上传 JPEG, PNG, GIF, 或 WEBP 格式。';
                    $message_type = 'error';
                } elseif ($file_size > $max_size) {
                    $message = '封面图片文件过大 (' . round($file_size / 1024) . 'KB)，最大允许 ' . ($max_size / 1024 / 1024) . 'MB。';
                    $message_type = 'error';
                } else {
                    $original_basename = basename($_FILES['cover_image_file']['name']);
                    $safe_original_filename = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $original_basename);
                    $unique_filename = time() . '_' . substr(md5(uniqid(rand(), true)),0,6) . '_' . $safe_original_filename;
                    $destination_path = $cover_upload_path_absolute . $unique_filename;

                    if (move_uploaded_file($file_tmp_name, $destination_path)) {
                        $final_cover_image_url = $cover_upload_dir_relative . $unique_filename; // Relative path for DB
                    } else {
                        $php_err_msg = error_get_last()['message'] ?? '未知错误';
                        $message = '移动上传的封面图片失败。请检查服务器权限和路径。PHP 错误: ' . htmlspecialchars($php_err_msg);
                        $message_type = 'error';
                        error_log("Failed to move uploaded cover image for new post: " . $original_basename . " to " . $destination_path . ". PHP Error: " . $php_err_msg);
                    }
                }
            }
        } elseif (!empty($cover_image_url_text_input)) { // If no file uploaded, check text URL input
            if (filter_var($cover_image_url_text_input, FILTER_VALIDATE_URL)) {
                $final_cover_image_url = $cover_image_url_text_input;
            } else {
                $message = '提供的封面图片URL格式不正确。';
                $message_type = 'error';
            }
        }

        // If no errors so far (from validation or file upload), proceed to database insert
        if (empty($message_type)) {
            try {
                $sql_insert_post = "INSERT INTO posts (admin_id, title, content, category_id, tags, status, cover_image_url, created_at, updated_at) 
                        VALUES (:admin_id, :title, :content, :category_id, :tags, :status, :cover_image_url, NOW(), NOW())";
                $stmt_insert_post = $pdo->prepare($sql_insert_post);
                
                if (!$stmt_insert_post) {
                    // Log detailed error and throw exception
                    $pdo_error_info = $pdo->errorInfo();
                    error_log("Prepare failed for new post insert: " . ($pdo_error_info[2] ?? 'Unknown PDO error'));
                    throw new PDOException("数据库预处理语句失败 (创建文章)。");
                }
                
                // Bind parameters
                $stmt_insert_post->bindParam(':admin_id', $admin_id, PDO::PARAM_INT);
                $stmt_insert_post->bindParam(':title', $title, PDO::PARAM_STR);
                $stmt_insert_post->bindParam(':content', $content_from_editor, PDO::PARAM_STR);
                $stmt_insert_post->bindParam(':category_id', $category_id, $category_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
                $stmt_insert_post->bindParam(':tags', $tags_input, PDO::PARAM_STR);
                $stmt_insert_post->bindParam(':status', $status, PDO::PARAM_STR);
                $stmt_insert_post->bindParam(':cover_image_url', $final_cover_image_url, !empty($final_cover_image_url) ? PDO::PARAM_STR : PDO::PARAM_NULL);

                if ($stmt_insert_post->execute()) {
                    $new_post_id = $pdo->lastInsertId();
                    $_SESSION['success_message'] = "新文章已成功创建！ 文章 ID: " . $new_post_id . " <a href='edit_post.php?id=".$new_post_id."' class='font-semibold underline hover:text-green-800'>立即编辑</a>";
                    // Redirect to the SAME new_post.php page (server name) to show the success message
                    // The form fields will be cleared by the logic below the POST block if success_message is set.
                    header('Location: new_post.php'); 
                    exit;

                } else {
                    // Log detailed error and set user message
                    $stmt_error_info = $stmt_insert_post->errorInfo();
                    error_log("Error creating post (execute failed): " . ($stmt_error_info[2] ?? 'Unknown statement execution error'));
                    $message = "创建文章失败，请重试 (数据库执行错误)。";
                    $message_type = 'error';
                }
            } catch (PDOException $e) {
                // Log detailed error and set user message
                error_log("PDOException during new post insertion: " . $e->getMessage());
                $message = "数据库错误 (创建文章时): " . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
}

// If redirected from successful save, show session message
if(isset($_SESSION['success_message'])){
    $message = $_SESSION['success_message']; // This can contain HTML for the link
    $message_type = 'success';
    unset($_SESSION['success_message']);
    // Clear form fields for the next new post entry
    $form_title = ''; 
    $form_content_for_textarea = ''; 
    $form_category_id = null; 
    $form_status = 'draft'; 
    $form_tags_input = ''; 
    $form_cover_image_url_text = '';
    // Add JS to clear TinyMCE and file input if possible
    echo '<script>
        if(typeof tinymce !== "undefined" && tinymce.get("content")) { tinymce.get("content").setContent(""); tinymce.triggerSave(); }
        const coverFileElem = document.getElementById("cover_image_file"); if(coverFileElem) coverFileElem.value = "";
        const coverPreviewElem = document.getElementById("cover_image_preview_element"); if(coverPreviewElem) { coverPreviewElem.style.display="none"; coverPreviewElem.src="#"; }
    </script>';
}


$page_title_display = "撰写新文章"; 
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title_display); ?> - 后台管理</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.tiny.cloud/1/jo70mwj1yjdvh1nbfy9h3y2yf9e848lhcjppdpqevz0ri3le/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f4f7f6; color: #333; }
        .admin-header { background-color: #2d3748; color: white; padding: 1rem 1.5rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .admin-nav a { color: #cbd5e1; margin-right: 1rem; text-decoration: none; transition: color 0.2s ease; }
        .admin-nav a.active, .admin-nav a:hover { color: white; font-weight: bold; } 
        .form-label { display: block; margin-bottom: 0.5rem; font-weight: 500; color: #4a5568; }
        .form-input, .form-select, .form-textarea, .form-file-input {
            width: 100%; padding: 0.75rem; border: 1px solid #cbd5e0;
            border-radius: 0.375rem; box-shadow: inset 0 1px 2px rgba(0,0,0,0.05);
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }
        .form-file-input { padding: 0.45rem 0.75rem; }
        .form-input:focus, .form-select:focus, .form-textarea:focus, .form-file-input:focus {
            border-color: #3b82f6; outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.25);
        }
        .tox-tinymce { border-radius: 0.375rem !important; border: 1px solid #cbd5e0 !important; }
        .image-preview-container { margin-top: 0.5rem; min-height:100px; }
        .image-preview { max-width: 200px; max-height: 150px; border: 1px solid #ddd; padding: 2px; object-fit: cover; border-radius: 0.25rem; display:none;}
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            tinymce.init({
                selector: 'textarea#content',
                plugins: 'preview importcss searchreplace autolink autosave save directionality code visualblocks visualchars fullscreen image link media template codesample table charmap pagebreak nonbreaking anchor insertdatetime advlist lists wordcount help charmap quickbars emoticons accordion',
                menubar: 'file edit view insert format tools table help',
                toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | link image media table mergetags | align lineheight | tinycomments | checklist numlist bullist indent outdent | emoticons charmap | removeformat',
                height: 500, image_advtab: true, importcss_append: true,
                images_upload_url: 'upload_image.php', 
                images_upload_base_path: '<?php echo rtrim(dirname($_SERVER['PHP_SELF']), '/') . '/../uploads/images/'; ?>',
                images_file_types: 'jpeg,jpg,jpe,jfi,jif,jfif,png,gif,bmp,webp',
                images_upload_handler: function (blobInfo, success, failure, progress) {
                    var xhr, formData; xhr = new XMLHttpRequest(); xhr.withCredentials = false; xhr.open('POST', 'upload_image.php'); 
                    xhr.upload.onprogress = function (e) { progress(e.loaded / e.total * 100); };
                    xhr.onload = function() {
                        var json; if (xhr.status === 403) { failure('HTTP Error: ' + xhr.status + '. Check upload_image.php permissions.', { remove: true }); return; }
                        if (xhr.status < 200 || xhr.status >= 300) { failure('HTTP Error: ' + xhr.status); return; }
                        try { json = JSON.parse(xhr.responseText); } catch (e) { failure('Invalid JSON: ' + xhr.responseText); return; }
                        if (!json || typeof json.location != 'string') { failure('Invalid JSON response from upload_image.php: ' + xhr.responseText); return; }
                        success(json.location); 
                    };
                    xhr.onerror = function () { failure('Image upload failed. Network error or server unreachable. Code: ' + xhr.status); };
                    formData = new FormData(); formData.append('file', blobInfo.blob(), blobInfo.filename()); xhr.send(formData);
                },
                content_style: 'body { font-family:Inter,Arial,sans-serif; font-size:16px }',
                autosave_ask_before_unload: true, autosave_interval: '30s',
                autosave_prefix: 'tinymce-autosave-new-post-{path}{query}-{id}-',
                autosave_restore_when_empty: true, autosave_retention: '2m',
                setup: function (editor) { // For new_post.php, ensure editor gets repopulated content on server-side error
                    editor.on('init', function () {
                        const initialContentForEditor = <?php echo json_encode($form_content_for_textarea); ?>;
                        // Only set content if editor is currently empty and PHP provided repopulation data
                        // This avoids overwriting content restored by TinyMCE's own autosave if PHP content is empty (e.g. after successful save & redirect)
                        if (initialContentForEditor && editor.getContent({format: 'text'}).trim() === '') {
                            editor.setContent(initialContentForEditor);
                        }
                    });
                }
            });
            
            const coverImageFileInput = document.getElementById('cover_image_file');
            const coverImageUrlTextInput = document.getElementById('cover_image_url_text');
            const coverImagePreview = document.getElementById('cover_image_preview_element');

            function displayPreview(inputElementOrUrl) {
                let urlToPreview = '';
                if (typeof inputElementOrUrl === 'string') { 
                    urlToPreview = inputElementOrUrl.trim();
                } else if (inputElementOrUrl && inputElementOrUrl.files && inputElementOrUrl.files[0]) { 
                    urlToPreview = URL.createObjectURL(inputElementOrUrl.files[0]);
                    if(coverImageUrlTextInput) coverImageUrlTextInput.value = ''; 
                }

                if (urlToPreview && coverImagePreview) {
                    coverImagePreview.src = urlToPreview;
                    coverImagePreview.style.display = 'block';
                } else if (coverImagePreview) {
                    coverImagePreview.style.display = 'none';
                    coverImagePreview.src = '#';
                }
            }

            if(coverImageFileInput) {
                coverImageFileInput.addEventListener('change', function() { displayPreview(this); });
            }
            if(coverImageUrlTextInput) {
                coverImageUrlTextInput.addEventListener('input', function() {
                     if (this.value.trim() !== '') { 
                        if(coverImageFileInput) coverImageFileInput.value = ''; 
                        displayPreview(this.value);
                    } else { 
                        displayPreview(null);
                    }
                });
            }
            
            const initialTextUrl = <?php echo json_encode($form_cover_image_url_text); ?>;
            if(initialTextUrl && coverImageUrlTextInput && coverImageUrlTextInput.value.trim() !== ''){ // Check against current value
                displayPreview(initialTextUrl);
            }
        });
    </script>
</head>
<body class="min-h-screen bg-gray-100">
    <header class="admin-header">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-xl font-semibold">后台管理</h1>
            <nav class="admin-nav">
                <a href="dashboard.php">仪表盘</a>
                <a href="new_post.php" class="active">撰写新文章</a> <!-- Corrected Link -->
                <a href="posts_list.php">文章列表</a> 
                <a href="categories.php">分类管理</a> <!-- Corrected Link -->
                <a href="users.php">用户管理</a>
                <a href="logout.php">登出</a>
            </nav>
        </div>
    </header>

    <main class="container mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="bg-white p-6 md:p-8 rounded-lg shadow-xl">
            <h2 class="text-2xl font-bold text-gray-800 mb-6"><?php echo htmlspecialchars($page_title_display); ?></h2>

            <?php if (!empty($message)): ?>
            <div class="mb-6 p-4 rounded-md <?php echo $message_type === 'error' ? 'bg-red-100 border-l-4 border-red-500 text-red-700' : 'bg-green-100 border-l-4 border-green-500 text-green-700'; ?>" role="alert">
                <p><?php echo $message; ?></p>
            </div>
            <?php endif; ?>

            <form method="POST" action="new_post.php" enctype="multipart/form-data" class="space-y-6"> <!-- Corrected Action -->
                <div>
                    <label for="title" class="form-label">标题 <span class="text-red-500">*</span></label>
                    <input type="text" id="title" name="title" class="form-input" placeholder="请输入文章标题" value="<?php echo htmlspecialchars($form_title); ?>" required>
                </div>
                
                <div>
                    <label for="cover_image_file" class="form-label">上传封面图片 (可选)</label>
                    <input type="file" id="cover_image_file" name="cover_image_file" class="form-file-input" accept="image/jpeg,image/png,image/gif,image/webp">
                    <p class="text-xs text-gray-500 mt-1">如果选择文件，它将被用作封面。最大 5MB。</p>
                    
                    <label for="cover_image_url_text" class="form-label mt-3">或输入封面图片 URL (可选)</label>
                    <input type="url" id="cover_image_url_text" name="cover_image_url_text" class="form-input" placeholder="例如: https://example.com/image.jpg" value="<?php echo htmlspecialchars($form_cover_image_url_text); ?>">
                     <p class="text-xs text-gray-500 mt-1">如果上面上传了文件，此URL将被忽略。</p>

                    <div class="image-preview-container mt-2">
                        <img id="cover_image_preview_element" src="#" alt="封面图片预览" class="image-preview">
                    </div>
                </div>

                <div>
                    <label for="content" class="form-label">内容 <span class="text-red-500">*</span></label>
                    <textarea id="content" name="content" class="form-textarea" rows="15"><?php echo htmlspecialchars($form_content_for_textarea); ?></textarea>
                </div>
                 <div>
                    <label for="tags" class="form-label">标签 (用英文逗号分隔)</label>
                    <input type="text" id="tags" name="tags" class="form-input" placeholder="例如: 技术,生活,随笔" value="<?php echo htmlspecialchars($form_tags_input); ?>">
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="category_id" class="form-label">分类</label>
                        <select id="category_id" name="category_id" class="form-select">
                            <option value="">选择分类 (可选)</option>
                            <?php 
                            // This check can be simplified or removed if $categories_for_form handles empty case well
                            // $has_categories_check_query_new_form = $pdo->query("SELECT 1 FROM categories LIMIT 1");
                            // $has_categories_check_new_form = $has_categories_check_query_new_form ? $has_categories_check_query_new_form->fetch() : false;
                            if (empty($categories_for_form)): 
                            ?>
                                <option value="" disabled>暂无分类 (请先前往“分类管理”创建)</option>
                            <?php endif; ?>
                            <?php foreach ($categories_for_form as $category_item): ?>
                            <option value="<?php echo $category_item['id']; ?>" <?php echo ($form_category_id == $category_item['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category_item['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="status" class="form-label">状态</label>
                        <select id="status" name="status" class="form-select">
                            <option value="draft" <?php echo ($form_status === 'draft') ? 'selected' : ''; ?>>草稿</option>
                            <option value="published" <?php echo ($form_status === 'published') ? 'selected' : ''; ?>>已发布</option>
                        </select>
                    </div>
                </div>
                <div>
                    <button type="submit" class="w-full md:w-auto inline-flex items-center justify-center px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg shadow-md transition-colors duration-150">
                        <i class="fas fa-save mr-2"></i>保存文章
                    </button>
                </div>
            </form>
        </div>
    </main>
    <footer class="text-center text-sm text-gray-500 py-6 mt-10 border-t border-gray-200">
         &copy; <?php echo date('Y'); ?> MaxCaulfield Admin Panel.
    </footer>
</body>
</html>
