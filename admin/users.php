<?php
// /www/wwwroot/maxcaulfield.cn/admin/users.php
require_once __DIR__ . '/../db.php'; // Includes config.php and getPDO()

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$pdo = getPDO();
$users = [];
$message = '';
$message_type = ''; // 'success' or 'error'

// --- Action Handling ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $user_id_to_action = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

    if ($user_id_to_action > 0) {
        // Prevent admin from banning/deleting themselves (optional, based on admin ID 1 or session admin_id)
        // Example: Assuming admin user ID '1' should not be targetable by these actions from UI
        // Or if you store admin's own user ID from 'users' table in session:
        // $current_admin_user_id_in_users_table = $_SESSION['admin_user_id_from_users_table'] ?? 0;
        // if ($user_id_to_action == $current_admin_user_id_in_users_table) {
        //     $message = "操作不允许：不能对自己执行此操作。";
        //     $message_type = 'error';
        // } else {
            try {
                if ($action === 'delete_user') {
                    // You might want to also delete related data (e.g., user's posts, comments) 
                    // or handle it via foreign key constraints (ON DELETE CASCADE or SET NULL).
                    // For simplicity, just deleting the user record here.
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                    if ($stmt->execute([$user_id_to_action])) {
                        $message = "用户 ID: " . $user_id_to_action . " 已成功删除。";
                        $message_type = 'success';
                    } else {
                        $message = "删除用户失败。";
                        $message_type = 'error';
                    }
                } elseif ($action === 'temp_ban') {
                    $duration_hours = isset($_POST['duration_hours']) ? intval($_POST['duration_hours']) : 0;
                    $reason = trim($_POST['reason'] ?? ''); // Default to empty string if not set
                    if (empty($reason)) $reason = 'N/A'; // Set to N/A if empty after trim

                    if ($duration_hours > 0) {
                        $ban_expires_at = date('Y-m-d H:i:s', strtotime("+$duration_hours hours"));
                        $stmt = $pdo->prepare("UPDATE users SET is_banned = 1, ban_expires_at = ?, ban_reason = ? WHERE id = ?");
                        if ($stmt->execute([$ban_expires_at, $reason, $user_id_to_action])) {
                            $message = "用户 ID: " . $user_id_to_action . " 已被临时封禁 " . $duration_hours . " 小时。";
                            $message_type = 'success';
                        } else {
                            $message = "临时封禁用户失败。";
                            $message_type = 'error';
                        }
                    } else {
                        $message = "临时封禁时长必须大于0小时。";
                        $message_type = 'error';
                    }
                } elseif ($action === 'perm_ban') {
                    $reason = trim($_POST['reason'] ?? '');
                    if (empty($reason)) $reason = 'N/A';

                    $stmt = $pdo->prepare("UPDATE users SET is_banned = 1, ban_expires_at = NULL, ban_reason = ? WHERE id = ?");
                    if ($stmt->execute([$reason, $user_id_to_action])) {
                        $message = "用户 ID: " . $user_id_to_action . "已被永久封禁。";
                        $message_type = 'success';
                    } else {
                        $message = "永久封禁用户失败。";
                        $message_type = 'error';
                    }
                } elseif ($action === 'unban_user') {
                    $stmt = $pdo->prepare("UPDATE users SET is_banned = 0, ban_expires_at = NULL, ban_reason = NULL WHERE id = ?");
                    if ($stmt->execute([$user_id_to_action])) {
                        $message = "用户 ID: " . $user_id_to_action . " 已被解封。";
                        $message_type = 'success';
                    } else {
                        $message = "解封用户失败。";
                        $message_type = 'error';
                    }
                }
            } catch (PDOException $e) {
                $message = "数据库操作失败: " . $e->getMessage();
                $message_type = 'error';
                error_log("Admin user action error for user ID $user_id_to_action, action $action: " . $e->getMessage());
            }
        // } // End of self-action prevention
    } else if ($action !== '') { // If action is set but user_id is not valid
        $message = "无效的用户ID。";
        $message_type = 'error';
    }
}
// --- End Action Handling ---


// Fetch users and check ban status
try {
    $stmt = $pdo->query("SELECT id, username, email, created_at, last_login_at, is_banned, ban_expires_at, ban_reason FROM users ORDER BY created_at DESC");
    $fetched_users = $stmt->fetchAll();
    $users = []; // Initialize $users array
    if ($fetched_users) {
        foreach ($fetched_users as $user) {
            // Auto-unban if ban has expired
            if ($user['is_banned'] == 1 && $user['ban_expires_at'] !== null && strtotime($user['ban_expires_at']) < time()) {
                $unban_stmt = $pdo->prepare("UPDATE users SET is_banned = 0, ban_expires_at = NULL, ban_reason = NULL WHERE id = ?");
                if ($unban_stmt->execute([$user['id']])) {
                    $user['is_banned'] = 0;
                    $user['ban_expires_at'] = null;
                    $user['ban_reason'] = null;
                    // Optionally, add a log or a system message that a user was auto-unbanned
                }
            }
            $users[] = $user;
        }
    }
} catch (PDOException $e) {
    if(empty($message)) { // Only set if no action message is already present
        $message = "获取用户列表失败: " . $e->getMessage();
        $message_type = 'error';
    }
    error_log("Admin view users DB error: " . $e->getMessage());
}

$t_admin = [
    'users_list_title' => '注册用户列表',
    'id' => 'ID',
    'username' => '用户名',
    'email' => '邮箱',
    'status' => '状态',
    'ban_expires' => '封禁到期',
    'reason' => '原因',
    'actions' => '操作',
    'banned' => '已封禁',
    'active' => '正常',
    'temp_ban' => '临时封禁',
    'perm_ban' => '永久封禁',
    'unban' => '解封',
    'delete' => '删除',
    'registered_at' => '注册时间',
    'last_login' => '最后登录',
    'no_users_found' => '目前没有注册用户。',
    'back_to_dashboard' => '返回仪表盘',
    'confirm_delete_user' => '确定要永久删除此用户吗？此操作无法撤销。',
    'ban_duration_hours' => '封禁时长 (小时)',
    'ban_reason_placeholder' => '请输入封禁原因 (可选)',
    'submit_ban' => '确认封禁',
    'cancel' => '取消',
];

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($t_admin['users_list_title']); ?> - 后台管理</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f4f7f6; color: #333; }
        .header-bar { background-color: #2d3748; color: white; }
        .table th { background-color: #f8f9fa; }
        .table th, .table td { border-bottom-width: 1px; border-color: #e5e7eb; padding: 0.5rem 0.75rem; text-align: left; font-size: 0.875rem; }
        .table tr:hover td { background-color: #f9fafb; }
        .action-btn { padding: 0.25rem 0.5rem; margin: 0.125rem; font-size: 0.75rem; border-radius: 0.25rem; cursor:pointer; transition: background-color 0.2s ease; }
        .modal { display: none; position: fixed; z-index: 100; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); align-items: center; justify-content: center;}
        .modal-content { background-color: #fefefe; /* margin: 10% auto; */ padding: 20px; border: 1px solid #888; width: 90%; max-width: 500px; border-radius: 0.5rem; box-shadow: 0 4px 8px rgba(0,0,0,0.2); }
        .close-btn { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; line-height: 1; }
        .close-btn:hover, .close-btn:focus { color: black; text-decoration: none; }
        .status-banned { color: #dc2626; font-weight: bold; }
        .status-active { color: #16a34a; }
    </style>
</head>
<body class="min-h-screen">

    <header class="header-bar shadow-lg">
        <div class="container mx-auto px-6 py-3 flex justify-between items-center">
            <h1 class="text-xl font-semibold tracking-tight">后台管理 - <?php echo htmlspecialchars($t_admin['users_list_title']); ?></h1>
            <nav>
                <a href="dashboard.php" class="text-gray-300 hover:text-white px-3 py-2 rounded-md text-sm font-medium hover:bg-gray-700 transition-colors">
                    <i class="fas fa-tachometer-alt mr-1"></i><?php echo htmlspecialchars($t_admin['back_to_dashboard']); ?>
                </a>
                <a href="logout.php" class="text-gray-300 hover:text-white px-3 py-2 rounded-md text-sm font-medium hover:bg-gray-700 transition-colors ml-2">
                    <i class="fas fa-sign-out-alt mr-1"></i>登出
                </a>
            </nav>
        </div>
    </header>

    <main class="container mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-800"><?php echo htmlspecialchars($t_admin['users_list_title']); ?></h2>
        </div>

        <?php if (!empty($message)): ?>
        <div class="mb-6 p-3 rounded-md text-sm <?php echo $message_type === 'error' ? 'bg-red-100 border-l-4 border-red-500 text-red-700' : 'bg-green-100 border-l-4 border-green-500 text-green-700'; ?>" role="alert">
            <p><?php echo htmlspecialchars($message); ?></p>
        </div>
        <?php endif; ?>

        <div class="bg-white shadow-xl rounded-lg overflow-x-auto">
            <table class="min-w-full leading-normal table">
                <thead>
                    <tr>
                        <th><?php echo htmlspecialchars($t_admin['id']); ?></th>
                        <th><?php echo htmlspecialchars($t_admin['username']); ?></th>
                        <th><?php echo htmlspecialchars($t_admin['email']); ?></th>
                        <th><?php echo htmlspecialchars($t_admin['status']); ?></th>
                        <th><?php echo htmlspecialchars($t_admin['ban_expires']); ?></th>
                        <th class="max-w-xs"><?php echo htmlspecialchars($t_admin['reason']); ?></th>
                        <th><?php echo htmlspecialchars($t_admin['registered_at']); ?></th>
                        <th><?php echo htmlspecialchars($t_admin['last_login']); ?></th>
                        <th><?php echo htmlspecialchars($t_admin['actions']); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="9" class="py-8 text-center text-gray-500"><?php echo htmlspecialchars($t_admin['no_users_found']); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <tr class="hover:bg-gray-50 <?php echo $user['is_banned'] ? 'bg-red-50 hover:bg-red-100' : ''; ?>">
                                <td><?php echo $user['id']; ?></td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <?php if ($user['is_banned']): ?>
                                        <span class="status-banned"><?php echo htmlspecialchars($t_admin['banned']); ?></span>
                                    <?php else: ?>
                                        <span class="status-active"><?php echo htmlspecialchars($t_admin['active']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $user['ban_expires_at'] ? date('Y-m-d H:i', strtotime($user['ban_expires_at'])) : ($user['is_banned'] ? '永久' : 'N/A'); ?></td>
                                <td class="max-w-xs truncate" title="<?php echo htmlspecialchars($user['ban_reason'] ?? ''); ?>"><?php echo htmlspecialchars($user['ban_reason'] ?? 'N/A'); ?></td>
                                <td><?php echo date('Y-m-d H:i', strtotime($user['created_at'])); ?></td>
                                <td><?php echo $user['last_login_at'] ? date('Y-m-d H:i', strtotime($user['last_login_at'])) : 'N/A'; ?></td>
                                <td class="whitespace-nowrap">
                                    <?php if ($user['is_banned']): ?>
                                        <button onclick="openModal('unbanModal', <?php echo $user['id']; ?>, '<?php echo htmlspecialchars(addslashes($user['username'])); ?>')" class="action-btn bg-green-500 hover:bg-green-600 text-white" title="<?php echo htmlspecialchars($t_admin['unban']); ?>"><i class="fas fa-check-circle"></i></button>
                                    <?php else: ?>
                                        <button onclick="openModal('tempBanModal', <?php echo $user['id']; ?>, '<?php echo htmlspecialchars(addslashes($user['username'])); ?>')" class="action-btn bg-yellow-500 hover:bg-yellow-600 text-white" title="<?php echo htmlspecialchars($t_admin['temp_ban']); ?>"><i class="fas fa-clock"></i></button>
                                        <button onclick="openModal('permBanModal', <?php echo $user['id']; ?>, '<?php echo htmlspecialchars(addslashes($user['username'])); ?>')" class="action-btn bg-red-600 hover:bg-red-700 text-white" title="<?php echo htmlspecialchars($t_admin['perm_ban']); ?>"><i class="fas fa-gavel"></i></button>
                                    <?php endif; ?>
                                    <button onclick="openModal('deleteUserModal', <?php echo $user['id']; ?>, '<?php echo htmlspecialchars(addslashes($user['username'])); ?>')" class="action-btn bg-gray-700 hover:bg-gray-800 text-white" title="<?php echo htmlspecialchars($t_admin['delete']); ?>"><i class="fas fa-trash-alt"></i></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <!-- Modals -->
    <div id="tempBanModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeModal('tempBanModal')">&times;</span>
            <h3 class="text-lg font-semibold mb-3">临时封禁用户: <span id="tempBanUserName" class="font-normal"></span></h3>
            <form method="POST" action="users.php">
                <input type="hidden" name="action" value="temp_ban">
                <input type="hidden" name="user_id" id="tempBanUserId">
                <div class="mb-3">
                    <label for="duration_hours" class="block text-sm font-medium text-gray-700"><?php echo htmlspecialchars($t_admin['ban_duration_hours']); ?>:</label>
                    <input type="number" name="duration_hours" id="duration_hours" min="1" value="24" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>
                <div class="mb-4">
                    <label for="temp_ban_reason" class="block text-sm font-medium text-gray-700"><?php echo htmlspecialchars($t_admin['reason']); ?>:</label>
                    <textarea name="reason" id="temp_ban_reason" rows="3" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" placeholder="<?php echo htmlspecialchars($t_admin['ban_reason_placeholder']); ?>"></textarea>
                </div>
                <div class="flex justify-end space-x-3 mt-4">
                    <button type="button" onclick="closeModal('tempBanModal')" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-medium py-2 px-4 rounded-md"><?php echo htmlspecialchars($t_admin['cancel']); ?></button>
                    <button type="submit" class="bg-yellow-500 hover:bg-yellow-600 text-white font-medium py-2 px-4 rounded-md"><?php echo htmlspecialchars($t_admin['submit_ban']); ?></button>
                </div>
            </form>
        </div>
    </div>

    <div id="permBanModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeModal('permBanModal')">&times;</span>
            <h3 class="text-lg font-semibold mb-3">永久封禁用户: <span id="permBanUserName" class="font-normal"></span></h3>
            <form method="POST" action="users.php">
                <input type="hidden" name="action" value="perm_ban">
                <input type="hidden" name="user_id" id="permBanUserId">
                <div class="mb-4">
                    <label for="perm_ban_reason" class="block text-sm font-medium text-gray-700"><?php echo htmlspecialchars($t_admin['reason']); ?>:</label>
                    <textarea name="reason" id="perm_ban_reason" rows="3" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" placeholder="<?php echo htmlspecialchars($t_admin['ban_reason_placeholder']); ?>"></textarea>
                </div>
                <div class="flex justify-end space-x-3 mt-4">
                     <button type="button" onclick="closeModal('permBanModal')" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-medium py-2 px-4 rounded-md"><?php echo htmlspecialchars($t_admin['cancel']); ?></button>
                    <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-medium py-2 px-4 rounded-md"><?php echo htmlspecialchars($t_admin['submit_ban']); ?></button>
                </div>
            </form>
        </div>
    </div>
    
    <div id="unbanModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeModal('unbanModal')">&times;</span>
            <h3 class="text-lg font-semibold mb-3">确认解封用户?</h3>
             <p class="mb-4 text-sm text-gray-600">您确定要解封用户 <strong id="unbanUserNameDisplay" class="font-normal"></strong> (ID: <span id="unbanUserIdDisplay" class="font-normal"></span>) 吗?</p>
            <form method="POST" action="users.php">
                <input type="hidden" name="action" value="unban_user">
                <input type="hidden" name="user_id" id="unbanUserId">
                <div class="flex justify-end space-x-3 mt-4">
                    <button type="button" onclick="closeModal('unbanModal')" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-medium py-2 px-4 rounded-md"><?php echo htmlspecialchars($t_admin['cancel']); ?></button>
                    <button type="submit" class="bg-green-500 hover:bg-green-600 text-white font-medium py-2 px-4 rounded-md"><?php echo htmlspecialchars($t_admin['unban']); ?></button>
                </div>
            </form>
        </div>
    </div>

    <div id="deleteUserModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeModal('deleteUserModal')">&times;</span>
            <h3 class="text-lg font-semibold mb-3 text-red-600">确认删除用户?</h3>
            <p class="mb-4 text-sm text-gray-600">您确定要永久删除用户 <strong id="deleteUserName" class="font-normal"></strong> (ID: <span id="deleteUserIdDisplay" class="font-normal"></span>) 吗？此操作无法撤销。</p>
            <form method="POST" action="users.php">
                <input type="hidden" name="action" value="delete_user">
                <input type="hidden" name="user_id" id="deleteUserId">
                 <div class="flex justify-end space-x-3 mt-4">
                    <button type="button" onclick="closeModal('deleteUserModal')" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-medium py-2 px-4 rounded-md"><?php echo htmlspecialchars($t_admin['cancel']); ?></button>
                    <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-medium py-2 px-4 rounded-md"><?php echo htmlspecialchars($t_admin['delete']); ?></button>
                </div>
            </form>
        </div>
    </div>


    <footer class="text-center text-sm text-gray-500 py-6 mt-10 border-t border-gray-200">
        &copy; <?php echo date('Y'); ?> MaxCaulfield Admin Panel.
    </footer>

    <script>
        function openModal(modalId, userId, userName = '') {
            const modal = document.getElementById(modalId);
            if (!modal) return;
            modal.style.display = 'flex'; // Use flex for centering defined in CSS
            
            if (modalId === 'tempBanModal') {
                modal.querySelector('#tempBanUserId').value = userId;
                modal.querySelector('#tempBanUserName').textContent = userName;
            } else if (modalId === 'permBanModal') {
                modal.querySelector('#permBanUserId').value = userId;
                modal.querySelector('#permBanUserName').textContent = userName;
            } else if (modalId === 'deleteUserModal') {
                modal.querySelector('#deleteUserId').value = userId;
                modal.querySelector('#deleteUserIdDisplay').textContent = userId;
                modal.querySelector('#deleteUserName').textContent = userName;
            } else if (modalId === 'unbanModal') {
                modal.querySelector('#unbanUserId').value = userId;
                modal.querySelector('#unbanUserIdDisplay').textContent = userId;
                // Assuming you might want to show username for unban confirmation too
                const unbanUserNameDisplay = modal.querySelector('#unbanUserNameDisplay');
                if (unbanUserNameDisplay) unbanUserNameDisplay.textContent = userName;
            }
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.style.display = 'none';
            }
        }

        // Close modal if user clicks outside of its content area
        window.addEventListener('click', function(event) {
            const modals = document.getElementsByClassName('modal');
            for (let i = 0; i < modals.length; i++) {
                if (event.target == modals[i]) { // Check if the click is on the modal backdrop itself
                    modals[i].style.display = "none";
                }
            }
        });
    </script>

</body>
</html>
