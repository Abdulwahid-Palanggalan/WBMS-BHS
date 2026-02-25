<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

if (!isAuthorized(['admin'])) {
    header("Location: login.php");
    exit();
}

redirectIfNotLoggedIn();

global $pdo;

$message = '';
$error = '';

// Handle user actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $userId = $_GET['id'];
    $action = $_GET['action'];
    
    if ($action === 'approve') {
        $sql = "UPDATE users SET status = 'active', approved_at = NOW() WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute([$userId])) {
            $message = "User approved successfully!";
            logActivity($_SESSION['user_id'], "Approved user ID: $userId");
        } else {
            $error = "Failed to approve user.";
        }
    } elseif ($action === 'reject') {
        $sql = "UPDATE users SET status = 'rejected' WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute([$userId])) {
            $message = "User rejected successfully!";
            logActivity($_SESSION['user_id'], "Rejected user ID: $userId");
        } else {
            $error = "Failed to reject user.";
        }
    } elseif ($action === 'suspend') {
        $sql = "UPDATE users SET status = 'suspended' WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute([$userId])) {
            $message = "User suspended successfully!";
            logActivity($_SESSION['user_id'], "Suspended user ID: $userId");
        } else {
            $error = "Failed to suspend user.";
        }
    } elseif ($action === 'activate') {
        $sql = "UPDATE users SET status = 'active' WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute([$userId])) {
            $message = "User activated successfully!";
            logActivity($_SESSION['user_id'], "Activated user ID: $userId");
        } else {
            $error = "Failed to activate user.";
        }
    } elseif ($action === 'delete') {
        // Only allow deletion of pending or rejected users
        $sql = "DELETE FROM users WHERE id = ? AND status IN ('pending', 'rejected')";
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute([$userId])) {
            if ($stmt->rowCount() > 0) {
                $message = "User deleted successfully!";
                logActivity($_SESSION['user_id'], "Deleted user ID: $userId");
            } else {
                $error = "Cannot delete user. Only pending or rejected users can be deleted.";
            }
        } else {
            $error = "Failed to delete user.";
        }
    }
}

// Handle add user form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_user'])) {
        $firstName = $_POST['first_name'];
        $lastName = $_POST['last_name'];
        $username = $_POST['username'];
        $email = $_POST['email'];
        $role = $_POST['role'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        
        // Check if username or email already exists
        $checkSql = "SELECT id FROM users WHERE username = ? OR email = ?";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([$username, $email]);
        
        if ($checkStmt->fetch()) {
            $error = "Username or email already exists!";
        } else {
            $sql = "INSERT INTO users (first_name, last_name, username, email, role, password, assigned_sitios, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, NULL, 'pending', NOW())";
            $stmt = $pdo->prepare($sql);
            
            if ($stmt->execute([$firstName, $lastName, $username, $email, $role, $password])) {
                $message = "User created successfully! Waiting for admin approval.";
                logActivity($_SESSION['user_id'], "Created new user: $username");
            } else {
                $error = "Failed to create user.";
            }
        }
    }
    
    // Handle edit user form submission
    if (isset($_POST['edit_user'])) {
        $userId = $_POST['user_id'];
        $firstName = $_POST['first_name'];
        $lastName = $_POST['last_name'];
        $username = $_POST['username'];
        $email = $_POST['email'];
        $role = $_POST['role'];
        $status = $_POST['status'];
        
        // Check if username or email already exists (excluding current user)
        $checkSql = "SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([$username, $email, $userId]);
        
        if ($checkStmt->fetch()) {
            $error = "Username or email already exists!";
        } else {
            $params = [$firstName, $lastName, $username, $email, $role, $status];
            $passwordSet = "";
            if (!empty($_POST['password'])) {
                $passwordSet = ", password = ?";
                $params[] = password_hash($_POST['password'], PASSWORD_DEFAULT);
            }
            $params[] = $userId;
            
            $sql = "UPDATE users SET first_name = ?, last_name = ?, username = ?, email = ?, role = ?, status = ? $passwordSet WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            
            if ($stmt->execute($params)) {
                $message = "User updated successfully!";
                logActivity($_SESSION['user_id'], "Updated user ID: $userId");
            } else {
                $error = "Failed to update user.";
            }
        }
    }
}

// Get user data for editing
$editUser = null;
if (isset($_GET['edit_id'])) {
    $editId = $_GET['edit_id'];
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$editId]);
    $editUser = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get all users with their status and activity
$users = $pdo->query("
    SELECT u.*, 
           COUNT(sa.id) as activity_count,
           (SELECT MAX(timestamp) FROM system_activities WHERE user_id = u.id) as last_activity
    FROM users u 
    LEFT JOIN system_activities sa ON u.id = sa.user_id 
    GROUP BY u.id 
    ORDER BY 
        CASE 
            WHEN u.status = 'pending' THEN 1
            WHEN u.status = 'active' THEN 2
            WHEN u.status = 'suspended' THEN 3
            WHEN u.status = 'rejected' THEN 4
        END,
        u.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Count users by status for tabs
$pendingCount = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'pending'")->fetchColumn();
$activeCount = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn();
$suspendedCount = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'suspended'")->fetchColumn();
$rejectedCount = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'rejected'")->fetchColumn();
$totalCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Help Desk System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php include_once __DIR__ . '/includes/tailwind_config.php'; ?>
    <style type="text/tailwindcss">
        @layer components {
            .stat-card-clinical {
                @apply bg-white p-6 rounded-3xl border border-slate-100 shadow-sm hover:shadow-md transition-all duration-300;
            }
            .table-modern th {
                @apply px-6 py-4 text-left text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em] border-b border-slate-50;
            }
            .table-modern td {
                @apply px-6 py-4 text-sm text-slate-600 border-b border-slate-50;
            }
            .card-premium {
                @apply bg-white rounded-[2rem] border border-slate-100 shadow-sm shadow-slate-200/50 p-8;
            }
            .form-input-premium {
                @apply w-full px-4 py-3 rounded-2xl border border-slate-200 focus:border-health-500 focus:ring-4 focus:ring-health-500/10 outline-none transition-all duration-200 bg-white;
            }
            .form-label-premium {
                @apply block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2 ml-1;
            }
            .user-avatar-premium {
                @apply w-10 h-10 rounded-2xl flex items-center justify-center text-sm font-bold shadow-lg;
            }
            .tab-btn {
                @apply px-6 py-3 rounded-2xl text-xs font-bold uppercase tracking-widest transition-all flex items-center gap-2;
            }
            .tab-btn-active {
                @apply bg-health-600 text-white shadow-lg shadow-health-100;
            }
            .tab-btn-inactive {
                @apply bg-white text-slate-400 hover:bg-slate-50 border border-slate-100;
            }
        }
    </style>
</head>
<body class="bg-slate-50 min-h-full">
    <?php include_once __DIR__ . '/includes/header.php'; ?>
    
    <div class="flex flex-col lg:flex-row min-h-[calc(100vh-4rem)]">
        <?php include_once __DIR__ . '/includes/sidebar.php'; ?>
        
        <main class="flex-1 p-4 lg:p-8 space-y-8">
            <!-- CLINICAL HEADER -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 bg-white p-8 rounded-[2rem] border border-slate-100 shadow-sm">
                <div>
                    <h1 class="text-3xl font-black text-slate-900 tracking-tight flex items-center gap-3">
                        <i class="fas fa-users-gear text-health-600"></i>
                        User Management
                    </h1>
                    <p class="text-slate-500 font-medium mt-1">Manage system access, roles, and administrative permissions.</p>
                </div>
                <button onclick="openModal('addUserModal')" class="bg-health-600 hover:bg-health-700 text-white font-bold px-6 py-3 rounded-2xl transition-all shadow-lg shadow-health-100 flex items-center gap-2 active:scale-95">
                    <i class="fas fa-user-plus"></i>
                    <span>Add New User</span>
                </button>
            </div>

            <!-- PREMIUM ALERTS -->
            <?php if ($message): ?>
            <div class="bg-emerald-50 border border-emerald-100 rounded-2xl p-4 flex items-center gap-3 text-emerald-800 animate-in fade-in slide-in-from-top duration-500">
                <div class="w-8 h-8 bg-emerald-100 rounded-lg flex items-center justify-center text-sm">
                    <i class="fas fa-check-circle"></i>
                </div>
                <p class="font-bold text-sm"><?= $message; ?></p>
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="bg-rose-50 border border-rose-100 rounded-2xl p-4 flex items-center gap-3 text-rose-800 animate-in fade-in slide-in-from-top duration-500">
                <div class="w-8 h-8 bg-rose-100 rounded-lg flex items-center justify-center text-sm">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <p class="font-bold text-sm"><?= $error; ?></p>
            </div>
            <?php endif; ?>

            <!-- STATS GRID -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="stat-card-clinical border-l-4 border-health-600">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em] mb-1">Total Registry</p>
                            <h3 class="text-3xl font-black text-slate-900"><?= $totalCount; ?></h3>
                        </div>
                        <div class="w-10 h-10 bg-health-50 text-health-600 rounded-xl flex items-center justify-center shadow-soft">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card-clinical border-l-4 border-amber-500 <?= $pendingCount > 0 ? 'ring-2 ring-amber-500/20' : '' ?>">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em] mb-1">Pending Approval</p>
                            <h3 class="text-3xl font-black text-slate-900"><?= $pendingCount; ?></h3>
                        </div>
                        <div class="w-10 h-10 <?= $pendingCount > 0 ? 'bg-amber-500 text-white animate-pulse' : 'bg-amber-50 text-amber-500' ?> rounded-xl flex items-center justify-center shadow-soft">
                            <i class="fas fa-user-clock"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card-clinical border-l-4 border-emerald-500">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em] mb-1">Active Accounts</p>
                            <h3 class="text-3xl font-black text-slate-900"><?= $activeCount; ?></h3>
                        </div>
                        <div class="w-10 h-10 bg-emerald-50 text-emerald-500 rounded-xl flex items-center justify-center shadow-soft">
                            <i class="fas fa-user-check"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card-clinical border-l-4 border-slate-400">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em] mb-1">Suspended</p>
                            <h3 class="text-3xl font-black text-slate-900"><?= $suspendedCount; ?></h3>
                        </div>
                        <div class="w-10 h-10 bg-slate-100 text-slate-500 rounded-xl flex items-center justify-center shadow-soft">
                            <i class="fas fa-user-slash"></i>
                        </div>
                    </div>
                </div>
            </div>
                
            <!-- USER REGISTRY -->
            <div class="space-y-6">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 px-2">
                    <h3 class="text-sm font-black text-slate-400 uppercase tracking-[0.3em]">User Registry Center</h3>
                    
                    <div class="flex flex-wrap gap-2" id="userTabs" role="tablist">
                        <button onclick="switchTab('pending')" class="tab-btn tab-btn-inactive" id="pending-tab">
                            <i class="fas fa-clock"></i>
                            Pending <span class="bg-slate-100 px-2 py-0.5 rounded-lg ml-1"><?= $pendingCount; ?></span>
                        </button>
                        <button onclick="switchTab('active')" class="tab-btn tab-btn-inactive" id="active-tab">
                            <i class="fas fa-check-circle"></i>
                            Active <span class="bg-slate-100 px-2 py-0.5 rounded-lg ml-1"><?= $activeCount; ?></span>
                        </button>
                        <button onclick="switchTab('suspended')" class="tab-btn tab-btn-inactive" id="suspended-tab">
                            <i class="fas fa-pause-circle"></i>
                            Suspended <span class="bg-slate-100 px-2 py-0.5 rounded-lg ml-1"><?= $suspendedCount; ?></span>
                        </button>
                        <button onclick="switchTab('rejected')" class="tab-btn tab-btn-inactive" id="rejected-tab">
                            <i class="fas fa-times-circle"></i>
                            Rejected <span class="bg-slate-100 px-2 py-0.5 rounded-lg ml-1"><?= $rejectedCount; ?></span>
                        </button>
                        <button onclick="switchTab('all')" class="tab-btn tab-btn-active" id="all-tab">
                            <i class="fas fa-users-viewfinder"></i>
                            All <span class="bg-white/20 px-2 py-0.5 rounded-lg ml-1"><?= $totalCount; ?></span>
                        </button>
                    </div>
                </div>

                <div class="card-premium">
                    <div id="userTabsContent">
                        <!-- Pending Users Tab -->
                        <div class="tab-content-item hidden" id="pending-content">
                            <?php $pendingUsers = array_filter($users, fn($u) => $u['status'] === 'pending'); ?>
                            <?php if (!empty($pendingUsers)): ?>
                                <div class="overflow-x-auto">
                                    <table class="w-full table-modern">
                                        <thead>
                                            <tr>
                                                <th>User Profile</th>
                                                <th>Access Details</th>
                                                <th>Clinical Role</th>
                                                <th>Registration</th>
                                                <th class="text-right">Care Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($pendingUsers as $user): ?>
                                            <tr class="hover:bg-slate-50/50 transition-colors group">
                                                <td class="py-5">
                                                    <div class="flex items-center gap-4">
                                                        <div class="user-avatar-premium bg-amber-500 text-white shadow-amber-100">
                                                            <?= strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                                                        </div>
                                                        <div class="flex flex-col">
                                                            <span class="font-bold text-slate-800 tracking-tight group-hover:text-health-700 transition-colors"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></span>
                                                            <span class="text-[10px] font-medium text-slate-400 italic">Waiting approval</span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="flex flex-col">
                                                        <span class="text-xs font-bold text-slate-600">@<?= htmlspecialchars($user['username']); ?></span>
                                                        <span class="text-[10px] text-slate-400"><?= htmlspecialchars($user['email']); ?></span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="bg-sky-50 text-sky-600 text-[10px] font-bold px-3 py-1 rounded-full border border-sky-100 uppercase tracking-tighter italic"><?= ucfirst($user['role']); ?></span>
                                                </td>
                                                <td><span class="text-xs font-medium text-slate-500"><?= date('M j, Y', strtotime($user['created_at'])); ?></span></td>
                                                <td class="text-right">
                                                    <div class="flex justify-end gap-2">
                                                        <button onclick="confirmAction('approve', <?= $user['id']; ?>)" class="bg-emerald-500 hover:bg-emerald-600 text-white p-2.5 rounded-xl transition-all shadow-lg shadow-emerald-100 active:scale-90" title="Approve">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                        <button onclick="confirmAction('reject', <?= $user['id']; ?>)" class="bg-rose-500 hover:bg-rose-600 text-white p-2.5 rounded-xl transition-all shadow-lg shadow-rose-100 active:scale-90" title="Reject">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                        <button onclick="openEditModal(<?= htmlspecialchars(json_encode($user)); ?>)" class="bg-sky-500 hover:bg-sky-600 text-white p-2.5 rounded-xl transition-all shadow-lg shadow-sky-100 active:scale-90" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-10 opacity-50">
                                    <i class="fas fa-user-clock text-4xl mb-4 text-slate-300"></i>
                                    <p class="text-sm font-bold text-slate-400 uppercase tracking-widest">No pending approvals found</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Active Users Tab -->
                        <div class="tab-content-item hidden" id="active-content">
                            <?php $activeUsers = array_filter($users, fn($u) => $u['status'] === 'active'); ?>
                            <?php if (!empty($activeUsers)): ?>
                                <div class="overflow-x-auto">
                                    <table class="w-full table-modern">
                                        <thead>
                                            <tr>
                                                <th>User Profile</th>
                                                <th>Access Details</th>
                                                <th>Clinical Role</th>
                                                <th>Last Activity</th>
                                                <th class="text-right">Care Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($activeUsers as $user): ?>
                                            <tr class="hover:bg-slate-50/50 transition-colors group">
                                                <td class="py-5">
                                                    <div class="flex items-center gap-4">
                                                        <div class="user-avatar-premium bg-health-600 text-white shadow-health-100">
                                                            <?= strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                                                        </div>
                                                        <div class="flex flex-col">
                                                            <span class="font-bold text-slate-800 tracking-tight group-hover:text-health-700 transition-colors"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></span>
                                                            <span class="text-[10px] font-bold text-emerald-500 uppercase tracking-widest">Active System Access</span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="flex flex-col">
                                                        <span class="text-xs font-bold text-slate-600">@<?= htmlspecialchars($user['username']); ?></span>
                                                        <span class="text-[10px] text-slate-400"><?= htmlspecialchars($user['email']); ?></span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="bg-sky-50 text-sky-600 text-[10px] font-bold px-3 py-1 rounded-full border border-sky-100 uppercase tracking-tighter italic"><?= ucfirst($user['role']); ?></span>
                                                </td>
                                                <td>
                                                    <span class="text-xs font-medium text-slate-500">
                                                        <?= $user['last_activity'] ? date('M j, Y g:i A', strtotime($user['last_activity'])) : 'No recent login'; ?>
                                                    </span>
                                                </td>
                                                <td class="text-right">
                                                    <div class="flex justify-end gap-2">
                                                        <button onclick="confirmAction('suspend', <?= $user['id']; ?>)" class="bg-amber-500 hover:bg-amber-600 text-white p-2.5 rounded-xl transition-all shadow-lg shadow-amber-100 active:scale-90" title="Suspend">
                                                            <i class="fas fa-pause"></i>
                                                        </button>
                                                        <button onclick="openEditModal(<?= htmlspecialchars(json_encode($user)); ?>)" class="bg-sky-500 hover:bg-sky-600 text-white p-2.5 rounded-xl transition-all shadow-lg shadow-sky-100 active:scale-90" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-10 opacity-50">
                                    <i class="fas fa-users-slash text-4xl mb-4 text-slate-300"></i>
                                    <p class="text-sm font-bold text-slate-400 uppercase tracking-widest">No active accounts matching criteria</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Suspended Users Tab -->
                        <div class="tab-content-item hidden" id="suspended-content">
                            <?php $suspendedUsers = array_filter($users, fn($u) => $u['status'] === 'suspended'); ?>
                            <?php if (!empty($suspendedUsers)): ?>
                                <div class="overflow-x-auto">
                                    <table class="w-full table-modern">
                                        <thead>
                                            <tr>
                                                <th>User Profile</th>
                                                <th>Access Details</th>
                                                <th>Clinical Role</th>
                                                <th>Inactivity</th>
                                                <th class="text-right">Care Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($suspendedUsers as $user): ?>
                                            <tr class="hover:bg-slate-50/50 transition-colors group">
                                                <td class="py-5">
                                                    <div class="flex items-center gap-4">
                                                        <div class="user-avatar-premium bg-slate-400 text-white shadow-slate-100">
                                                            <?= strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                                                        </div>
                                                        <div class="flex flex-col">
                                                            <span class="font-bold text-slate-800 tracking-tight transition-colors"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></span>
                                                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Access Terminated</span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="flex flex-col">
                                                        <span class="text-xs font-bold text-slate-600">@<?= htmlspecialchars($user['username']); ?></span>
                                                        <span class="text-[10px] text-slate-400"><?= htmlspecialchars($user['email']); ?></span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="bg-sky-50 text-sky-600 text-[10px] font-bold px-3 py-1 rounded-full border border-sky-100 uppercase tracking-tighter italic"><?= ucfirst($user['role']); ?></span>
                                                </td>
                                                <td><span class="text-xs font-medium text-slate-500">Suspended: <?= date('M j, Y', strtotime($user['created_at'])); ?></span></td>
                                                <td class="text-right">
                                                    <div class="flex justify-end gap-2">
                                                        <button onclick="confirmAction('activate', <?= $user['id']; ?>)" class="bg-emerald-500 hover:bg-emerald-600 text-white p-2.5 rounded-xl transition-all shadow-lg shadow-emerald-100 active:scale-90" title="Reactivate">
                                                            <i class="fas fa-play"></i>
                                                        </button>
                                                        <button onclick="openEditModal(<?= htmlspecialchars(json_encode($user)); ?>)" class="bg-sky-500 hover:bg-sky-600 text-white p-2.5 rounded-xl transition-all shadow-lg shadow-sky-100 active:scale-90" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-10 opacity-50">
                                    <i class="fas fa-user-slash text-4xl mb-4 text-slate-300"></i>
                                    <p class="text-sm font-bold text-slate-400 uppercase tracking-widest">No suspended accounts found</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Rejected Users Tab -->
                        <div class="tab-content-item hidden" id="rejected-content">
                            <?php $rejectedUsers = array_filter($users, fn($u) => $u['status'] === 'rejected'); ?>
                            <?php if (!empty($rejectedUsers)): ?>
                                <div class="overflow-x-auto">
                                    <table class="w-full table-modern">
                                        <thead>
                                            <tr>
                                                <th>User Profile</th>
                                                <th>Access Details</th>
                                                <th>Clinical Role</th>
                                                <th class="text-right">Care Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($rejectedUsers as $user): ?>
                                            <tr class="hover:bg-slate-50/50 transition-colors group">
                                                <td class="py-5">
                                                    <div class="flex items-center gap-4 text-slate-400">
                                                        <div class="user-avatar-premium bg-slate-200 text-slate-400 shadow-none">
                                                            <?= strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                                                        </div>
                                                        <div class="flex flex-col">
                                                            <span class="font-bold tracking-tight italic"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></span>
                                                            <span class="text-[10px] font-bold uppercase tracking-widest">Application Rejected</span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="flex flex-col opacity-50">
                                                        <span class="text-xs font-bold text-slate-600">@<?= htmlspecialchars($user['username']); ?></span>
                                                        <span class="text-[10px] text-slate-400"><?= htmlspecialchars($user['email']); ?></span>
                                                    </div>
                                                </td>
                                                <td><span class="opacity-50"><?= ucfirst($user['role']); ?></span></td>
                                                <td class="text-right">
                                                    <div class="flex justify-end gap-2">
                                                        <button onclick="confirmAction('approve', <?= $user['id']; ?>)" class="bg-emerald-500 hover:bg-emerald-600 text-white p-2.5 rounded-xl transition-all shadow-lg shadow-emerald-100 active:scale-90" title="Rethink / Approve">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                        <button onclick="confirmAction('delete', <?= $user['id']; ?>)" class="bg-rose-500 hover:bg-rose-600 text-white p-2.5 rounded-xl transition-all shadow-lg shadow-rose-100 active:scale-90" title="Delete Forever">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-10 opacity-50">
                                    <i class="fas fa-user-xmark text-4xl mb-4 text-slate-300"></i>
                                    <p class="text-sm font-bold text-slate-400 uppercase tracking-widest">No rejected applications found</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- All Users Tab -->
                        <div class="tab-content-item" id="all-content">
                            <div class="overflow-x-auto">
                                <table class="w-full table-modern">
                                    <thead>
                                        <tr>
                                            <th>User Profile</th>
                                            <th>Clinical Role</th>
                                            <th>Status</th>
                                            <th>Registration</th>
                                            <th class="text-right">Care Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($users as $user): ?>
                                        <tr class="hover:bg-slate-50/50 transition-colors group">
                                            <td class="py-5">
                                                <div class="flex items-center gap-4">
                                                    <?php 
                                                        $avatarColor = match($user['status']) {
                                                            'active' => 'bg-health-600',
                                                            'pending' => 'bg-amber-500',
                                                            'suspended' => 'bg-slate-400',
                                                            'rejected' => 'bg-slate-200',
                                                            default => 'bg-slate-200'
                                                        };
                                                    ?>
                                                    <div class="user-avatar-premium <?= $avatarColor; ?> text-white font-bold">
                                                        <?= strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                                                    </div>
                                                    <div class="flex flex-col">
                                                        <span class="font-bold text-slate-800 tracking-tight group-hover:text-health-700 transition-colors"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></span>
                                                        <span class="text-[10px] font-medium text-slate-400">@<?= htmlspecialchars($user['username']); ?></span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="bg-sky-50 text-sky-600 text-[10px] font-bold px-3 py-1 rounded-full border border-sky-100 uppercase tracking-tighter italic"><?= ucfirst($user['role']); ?></span>
                                            </td>
                                            <td>
                                                <?php if ($user['status'] === 'active'): ?>
                                                <span class="text-emerald-500 text-[10px] font-black uppercase tracking-widest flex items-center gap-1.5"><div class="w-1.5 h-1.5 rounded-full bg-emerald-500"></div> Active</span>
                                                <?php elseif ($user['status'] === 'pending'): ?>
                                                <span class="text-amber-500 text-[10px] font-black uppercase tracking-widest flex items-center gap-1.5"><div class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></div> Pending</span>
                                                <?php elseif ($user['status'] === 'suspended'): ?>
                                                <span class="text-slate-400 text-[10px] font-black uppercase tracking-widest flex items-center gap-1.5">Suspended</span>
                                                <?php elseif ($user['status'] === 'rejected'): ?>
                                                <span class="text-slate-300 text-[10px] font-black uppercase tracking-widest flex items-center gap-1.5">Rejected</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><span class="text-xs font-medium text-slate-500"><?= date('M j, Y', strtotime($user['created_at'])); ?></span></td>
                                            <td class="text-right">
                                                <button onclick="openEditModal(<?= htmlspecialchars(json_encode($user)); ?>)" class="bg-slate-50 hover:bg-slate-100 text-slate-400 hover:text-health-600 p-2.5 rounded-xl transition-all active:scale-90" title="Advanced Edit">
                                                    <i class="fas fa-ellipsis-v"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            </main>
    </div>
    
    <!-- ADD USER MODAL -->
    <div id="addUserModal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-[2.5rem] shadow-2xl w-full max-w-2xl overflow-hidden animate-in zoom-in duration-300">
            <div class="flex items-center justify-between p-8 border-b border-slate-50 bg-slate-50/50">
                <h3 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-3">
                    <i class="fas fa-user-plus text-health-600"></i>
                    Register New User
                </h3>
                <button onclick="closeModal('addUserModal')" class="w-10 h-10 rounded-2xl bg-white border border-slate-100 flex items-center justify-center text-slate-400 hover:text-health-600 transition-all">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form action="" method="POST" class="p-8 space-y-6 max-h-[70vh] overflow-y-auto custom-scrollbar">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="form-label-premium">First Name</label>
                        <input type="text" name="first_name" required class="form-input-premium" placeholder="Enter first name">
                    </div>
                    <div>
                        <label class="form-label-premium">Last Name</label>
                        <input type="text" name="last_name" required class="form-input-premium" placeholder="Enter last name">
                    </div>
                    <div>
                        <label class="form-label-premium">Username</label>
                        <input type="text" name="username" required class="form-input-premium" placeholder="e.g. jdoe">
                    </div>
                    <div>
                        <label class="form-label-premium">Email Address</label>
                        <input type="email" name="email" required class="form-input-premium" placeholder="email@example.com">
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="form-label-premium">System Role</label>
                        <select name="role" required class="form-input-premium">
                            <option value="admin">Administrator</option>
                            <option value="midwife">Midwife</option>
                            <option value="bns">BNS (Nutrition Scholar)</option>
                            <option value="bhw">BHW (Health Worker)</option>
                            <option value="mother">Mother</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label-premium">Password</label>
                        <input type="password" name="password" id="add-password" required class="form-input-premium" placeholder="••••••••">
                        <div id="add-password-strength" class="text-[10px] font-bold mt-2 uppercase tracking-widest"></div>
                    </div>
                </div>
                <div class="flex justify-end gap-3 pt-6">
                    <button type="button" onclick="closeModal('addUserModal')" class="px-6 py-3 rounded-2xl text-slate-400 font-bold hover:bg-slate-50 transition-all">Cancel</button>
                    <button type="submit" name="add_user" class="bg-health-600 hover:bg-health-700 text-white font-bold px-8 py-3 rounded-2xl transition-all shadow-lg shadow-health-100 active:scale-95">Complete Registration</button>
                </div>
            </form>
        </div>
    </div>

    <!-- EDIT USER MODAL -->
    <div id="editUserModal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-[2.5rem] shadow-2xl w-full max-w-2xl overflow-hidden animate-in zoom-in duration-300">
            <div class="flex items-center justify-between p-8 border-b border-slate-50 bg-slate-50/50">
                <h3 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-3">
                    <i class="fas fa-user-pen text-sky-600"></i>
                    Update User Identity
                </h3>
                <button onclick="closeModal('editUserModal')" class="w-10 h-10 rounded-2xl bg-white border border-slate-100 flex items-center justify-center text-slate-400 hover:text-sky-600 transition-all">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form action="" method="POST" class="p-8 space-y-6 max-h-[70vh] overflow-y-auto custom-scrollbar">
                <input type="hidden" name="user_id" id="edit_user_id">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="form-label-premium">First Name</label>
                        <input type="text" name="first_name" id="edit_first_name" required class="form-input-premium">
                    </div>
                    <div>
                        <label class="form-label-premium">Last Name</label>
                        <input type="text" name="last_name" id="edit_last_name" required class="form-input-premium">
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="form-label-premium">Username</label>
                        <input type="text" name="username" id="edit_username" required class="form-input-premium">
                    </div>
                    <div>
                        <label class="form-label-premium">Email Address</label>
                        <input type="email" name="email" id="edit_email" required class="form-input-premium">
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="form-label-premium">System Role</label>
                        <select name="role" id="edit_role" required class="form-input-premium">
                            <option value="admin">Administrator</option>
                            <option value="midwife">Midwife</option>
                            <option value="bns">BNS (Nutrition Scholar)</option>
                            <option value="bhw">BHW (Health Worker)</option>
                            <option value="mother">Mother</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label-premium">Account Status</label>
                        <select name="status" id="edit_status" required class="form-input-premium">
                            <option value="pending">Pending Approval</option>
                            <option value="active">Active System Access</option>
                            <option value="suspended">Suspended / Deactivated</option>
                            <option value="rejected">Rejected Application</option>
                        </select>
                    </div>
                </div>
                <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100">
                    <label class="form-label-premium">New Password <span class="text-[10px] normal-case">(leave blank to keep current)</span></label>
                    <input type="password" name="password" id="edit-password" class="form-input-premium" placeholder="••••••••">
                    <div id="edit-password-strength" class="text-[10px] font-bold mt-2 uppercase tracking-widest"></div>
                </div>
                <div class="flex justify-end gap-3 pt-6">
                    <button type="button" onclick="closeModal('editUserModal')" class="px-6 py-3 rounded-2xl text-slate-400 font-bold hover:bg-slate-50 transition-all">Discard Changes</button>
                    <button type="submit" name="edit_user" class="bg-sky-600 hover:bg-sky-700 text-white font-bold px-8 py-3 rounded-2xl transition-all shadow-lg shadow-sky-100 active:scale-95">Save System Identity</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(id) {
            document.getElementById(id).classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeModal(id) {
            document.getElementById(id).classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        function switchTab(tabId) {
            // Update Tab Buttons
            const buttons = document.querySelectorAll('.tab-btn');
            buttons.forEach(btn => {
                btn.classList.remove('tab-btn-active');
                btn.classList.add('tab-btn-inactive');
                btn.querySelector('span').classList.remove('bg-white/20');
                btn.querySelector('span').classList.add('bg-slate-100');
            });

            const activeBtn = document.getElementById(tabId + '-tab');
            activeBtn.classList.remove('tab-btn-inactive');
            activeBtn.classList.add('tab-btn-active');
            activeBtn.querySelector('span').classList.remove('bg-slate-100');
            activeBtn.querySelector('span').classList.add('bg-white/20');

            // Update Tab Content
            const contentItems = document.querySelectorAll('.tab-content-item');
            contentItems.forEach(item => item.classList.add('hidden'));
            document.getElementById(tabId + '-content').classList.remove('hidden');
        }

        function openEditModal(user) {
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_first_name').value = user.first_name;
            document.getElementById('edit_last_name').value = user.last_name;
            document.getElementById('edit_username').value = user.username;
            document.getElementById('edit_email').value = user.email;
            document.getElementById('edit_role').value = user.role;
            document.getElementById('edit_status').value = user.status;
            openModal('editUserModal');
        }

        function confirmAction(action, id) {
            const configs = {
                approve: { title: 'Approve User?', text: 'Give system access to this user?', icon: 'question', color: '#10b981' },
                reject: { title: 'Reject Application?', text: 'Deny system access to this user?', icon: 'warning', color: '#f43f5e' },
                suspend: { title: 'Suspend Account?', text: 'Temporarily disable system access?', icon: 'warning', color: '#f59e0b' },
                activate: { title: 'Reactivate Account?', text: 'Restore system access for this user?', icon: 'question', color: '#10b981' },
                delete: { title: 'Delete Forever?', text: 'This action cannot be undone!', icon: 'error', color: '#f43f5e' }
            };

            const config = configs[action];
            Swal.fire({
                title: config.title,
                text: config.text,
                icon: config.icon,
                showCancelButton: true,
                confirmButtonColor: config.color,
                cancelButtonColor: '#94a3b8',
                confirmButtonText: 'Yes, proceed',
                borderRadius: '1.5rem',
                customClass: { popup: 'rounded-[2rem]' }
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `?action=${action}&id=${id}`;
                }
            });
        }

        function updatePasswordStrength(input, output) {
            const password = input.value;
            let strengthText = '';
            let strengthClass = '';
            
            if (password.length > 0) {
                if (password.length < 6) {
                    strengthText = 'Weak Connection';
                    strengthClass = 'text-rose-500';
                } else if (password.length < 10) {
                    strengthText = 'Average Security';
                    strengthClass = 'text-amber-500';
                } else {
                    strengthText = 'Premium Security';
                    strengthClass = 'text-emerald-500';
                }
            }
            
            output.innerHTML = strengthText;
            output.className = 'text-[10px] font-black mt-2 uppercase tracking-widest ' + strengthClass;
        }

        document.getElementById('add-password')?.addEventListener('input', function() {
            updatePasswordStrength(this, document.getElementById('add-password-strength'));
        });
        document.getElementById('edit-password')?.addEventListener('input', function() {
            updatePasswordStrength(this, document.getElementById('edit-password-strength'));
        });

        // Open specific user for edit if requested by URL
        <?php if (isset($_GET['edit_id'])): ?>
            <?php
            $editUser = array_filter($users, fn($u) => $u['id'] == $_GET['edit_id']);
            if (!empty($editUser)):
                $editUser = reset($editUser);
            ?>
                openEditModal(<?= json_encode($editUser); ?>);
            <?php endif; ?>
        <?php endif; ?>
    </script>
</body>
</html>