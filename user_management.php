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
        
        // Check if username or email already exists (excluding current user)
        $checkSql = "SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([$username, $email, $userId]);
        
        if ($checkStmt->fetch()) {
            $error = "Username or email already exists!";
        } else {
            $sql = "UPDATE users SET first_name = ?, last_name = ?, username = ?, email = ?, role = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            
            if ($stmt->execute([$firstName, $lastName, $username, $email, $role, $userId])) {
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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Kibenes eBirth</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .status-badge {
            font-size: 0.75rem;
            padding: 0.35em 0.65em;
        }
        .nav-tabs .nav-link.active {
            font-weight: 600;
        }
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #007bff;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1.1rem;
        }
        .action-buttons .btn {
            margin: 0 2px;
        }
        .table th {
            border-top: none;
            font-weight: 700;
            color: #ffffff;
            background-color: #2c3e50;
            border-bottom: 2px solid #34495e;
            padding: 12px 8px;
        }
        .table-hover tbody tr:hover {
            background-color: rgba(52, 152, 219, 0.1);
        }
        .action-buttons .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }
    </style>
</head>
<body>
    <?php include_once 'includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include_once 'includes/sidebar.php'; ?>
            
            <main class="main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <div>
                        <h1 class="h2">User Management</h1>
                        <p class="text-muted mb-0">Manage user accounts and permissions</p>
                    </div>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                        <i class="fas fa-user-plus"></i> Add User
                    </button>
                </div>
                
                <!-- Alerts -->
                <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <!-- Status Tabs -->
                <div class="card mb-4">
                    <div class="card-header">
                        <ul class="nav nav-tabs card-header-tabs" id="userTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button" role="tab">
                                    Pending <span class="badge bg-warning ms-1"><?php echo $pendingCount; ?></span>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="active-tab" data-bs-toggle="tab" data-bs-target="#active" type="button" role="tab">
                                    Active <span class="badge bg-success ms-1"><?php echo $activeCount; ?></span>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="suspended-tab" data-bs-toggle="tab" data-bs-target="#suspended" type="button" role="tab">
                                    Suspended <span class="badge bg-danger ms-1"><?php echo $suspendedCount; ?></span>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="rejected-tab" data-bs-toggle="tab" data-bs-target="#rejected" type="button" role="tab">
                                    Rejected <span class="badge bg-secondary ms-1"><?php echo $rejectedCount; ?></span>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="all-tab" data-bs-toggle="tab" data-bs-target="#all" type="button" role="tab">
                                    All Users <span class="badge bg-primary ms-1"><?php echo $totalCount; ?></span>
                                </button>
                            </li>
                        </ul>
                    </div>
                    
                    <div class="card-body">
                        <div class="tab-content" id="userTabsContent">
                            <!-- Pending Users Tab -->
                            <div class="tab-pane fade show active" id="pending" role="tabpanel">
                                <?php
                                $pendingUsers = array_filter($users, function($user) {
                                    return $user['status'] === 'pending';
                                });
                                ?>
                                <?php if (!empty($pendingUsers)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>User</th>
                                                    <th>Username</th>
                                                    <th>Email</th>
                                                    <th>Role</th>
                                                    <th>Registered</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($pendingUsers as $user): ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="user-avatar me-3">
                                                                <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                                                            </div>
                                                            <div>
                                                                <div class="fw-semibold"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                                                                <small class="text-muted">Waiting approval</small>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                    <td>
                                                        <span class="badge bg-info"><?php echo ucfirst($user['role']); ?></span>
                                                    </td>
                                                    <td>
                                                        <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                                                    </td>
                                                    <td>
                                                        <div class="action-buttons">
                                                            <a href="?action=approve&id=<?php echo $user['id']; ?>" class="btn btn-sm btn-success" title="Approve">
                                                                <i class="fas fa-check"></i> Approve
                                                            </a>
                                                            <a href="?action=reject&id=<?php echo $user['id']; ?>" class="btn btn-sm btn-danger" title="Reject" onclick="return confirm('Are you sure you want to reject this user?')">
                                                                <i class="fas fa-times"></i> Reject
                                                            </a>
                                                            <a href="?edit_id=<?php echo $user['id']; ?>" class="btn btn-sm btn-info" title="Edit">
                                                                <i class="fas fa-edit"></i> Edit
                                                            </a>
                                                            <a href="?action=delete&id=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this user?')">
                                                                <i class="fas fa-trash"></i> Delete
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4 text-muted">
                                        <i class="fas fa-users fa-3x mb-3"></i>
                                        <h5>No Pending Users</h5>
                                        <p>There are no users waiting for approval.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Active Users Tab -->
                            <div class="tab-pane fade" id="active" role="tabpanel">
                                <?php
                                $activeUsers = array_filter($users, function($user) {
                                    return $user['status'] === 'active';
                                });
                                ?>
                                <?php if (!empty($activeUsers)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>User</th>
                                                    <th>Username</th>
                                                    <th>Email</th>
                                                    <th>Role</th>
                                                    <th>Last Active</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($activeUsers as $user): ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="user-avatar me-3">
                                                                <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                                                            </div>
                                                            <div>
                                                                <div class="fw-semibold"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                                                                <small class="text-success">Approved</small>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                    <td>
                                                        <span class="badge bg-info"><?php echo ucfirst($user['role']); ?></span>
                                                    </td>
                                                    <td>
                                                        <?php 
                                                        if ($user['last_activity']) {
                                                            echo date('M j, Y g:i A', strtotime($user['last_activity']));
                                                        } else {
                                                            echo 'Never';
                                                        }
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <div class="action-buttons">
                                                            <a href="?action=suspend&id=<?php echo $user['id']; ?>" class="btn btn-sm btn-warning" title="Suspend" onclick="return confirm('Are you sure you want to suspend this user?')">
                                                                <i class="fas fa-pause"></i> Suspend
                                                            </a>
                                                            <a href="?edit_id=<?php echo $user['id']; ?>" class="btn btn-sm btn-info" title="Edit">
                                                                <i class="fas fa-edit"></i> Edit
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4 text-muted">
                                        <i class="fas fa-users fa-3x mb-3"></i>
                                        <h5>No Active Users</h5>
                                        <p>There are no active users in the system.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Suspended Users Tab -->
                            <div class="tab-pane fade" id="suspended" role="tabpanel">
                                <?php
                                $suspendedUsers = array_filter($users, function($user) {
                                    return $user['status'] === 'suspended';
                                });
                                ?>
                                <?php if (!empty($suspendedUsers)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>User</th>
                                                    <th>Username</th>
                                                    <th>Email</th>
                                                    <th>Role</th>
                                                    <th>Suspended Since</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($suspendedUsers as $user): ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="user-avatar me-3">
                                                                <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                                                            </div>
                                                            <div>
                                                                <div class="fw-semibold"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                                                                <small class="text-danger">Suspended</small>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                    <td>
                                                        <span class="badge bg-info"><?php echo ucfirst($user['role']); ?></span>
                                                    </td>
                                                    <td>
                                                        <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                                                    </td>
                                                    <td>
                                                        <div class="action-buttons">
                                                            <a href="?action=activate&id=<?php echo $user['id']; ?>" class="btn btn-sm btn-success" title="Activate">
                                                                <i class="fas fa-play"></i> Activate
                                                            </a>
                                                            <a href="?edit_id=<?php echo $user['id']; ?>" class="btn btn-sm btn-info" title="Edit">
                                                                <i class="fas fa-edit"></i> Edit
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4 text-muted">
                                        <i class="fas fa-users fa-3x mb-3"></i>
                                        <h5>No Suspended Users</h5>
                                        <p>There are no suspended users.</p>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Rejected Users Tab -->
                            <div class="tab-pane fade" id="rejected" role="tabpanel">
                                <?php
                                $rejectedUsers = array_filter($users, function($user) {
                                    return $user['status'] === 'rejected';
                                });
                                ?>
                                <?php if (!empty($rejectedUsers)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>User</th>
                                                    <th>Username</th>
                                                    <th>Email</th>
                                                    <th>Role</th>
                                                    <th>Rejected Date</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($rejectedUsers as $user): ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="user-avatar me-3">
                                                                <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                                                            </div>
                                                            <div>
                                                                <div class="fw-semibold"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                                                                <small class="text-secondary">Rejected</small>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                    <td>
                                                        <span class="badge bg-info"><?php echo ucfirst($user['role']); ?></span>
                                                    </td>
                                                    <td>
                                                        <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                                                    </td>
                                                    <td>
                                                        <div class="action-buttons">
                                                            <a href="?action=approve&id=<?php echo $user['id']; ?>" class="btn btn-sm btn-success" title="Approve">
                                                                <i class="fas fa-check"></i> Approve
                                                            </a>
                                                            <a href="?edit_id=<?php echo $user['id']; ?>" class="btn btn-sm btn-info" title="Edit">
                                                                <i class="fas fa-edit"></i> Edit
                                                            </a>
                                                            <a href="?action=delete&id=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this user?')">
                                                                <i class="fas fa-trash"></i> Delete
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4 text-muted">
                                        <i class="fas fa-users fa-3x mb-3"></i>
                                        <h5>No Rejected Users</h5>
                                        <p>There are no rejected users.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- All Users Tab -->
                            <div class="tab-pane fade" id="all" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>User</th>
                                                <th>Username</th>
                                                <th>Email</th>
                                                <th>Role</th>
                                                <th>Status</th>
                                                <th>Registered</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($users as $user): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="user-avatar me-3">
                                                            <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                                                        </div>
                                                        <div>
                                                            <div class="fw-semibold"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                <td>
                                                    <span class="badge bg-info"><?php echo ucfirst($user['role']); ?></span>
                                                </td>
                                                <td>
                                                    <?php if ($user['status'] === 'active'): ?>
                                                    <span class="badge bg-success">Active</span>
                                                    <?php elseif ($user['status'] === 'pending'): ?>
                                                    <span class="badge bg-warning">Pending</span>
                                                    <?php elseif ($user['status'] === 'suspended'): ?>
                                                    <span class="badge bg-danger">Suspended</span>
                                                    <?php elseif ($user['status'] === 'rejected'): ?>
                                                    <span class="badge bg-secondary">Rejected</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                                                </td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <?php if ($user['status'] === 'pending'): ?>
                                                        <a href="?action=approve&id=<?php echo $user['id']; ?>" class="btn btn-sm btn-success" title="Approve">
                                                            <i class="fas fa-check"></i>
                                                        </a>
                                                        <a href="?action=reject&id=<?php echo $user['id']; ?>" class="btn btn-sm btn-danger" title="Reject" onclick="return confirm('Are you sure you want to reject this user?')">
                                                            <i class="fas fa-times"></i>
                                                        </a>
                                                        <?php elseif ($user['status'] === 'active'): ?>
                                                        <a href="?action=suspend&id=<?php echo $user['id']; ?>" class="btn btn-sm btn-warning" title="Suspend" onclick="return confirm('Are you sure you want to suspend this user?')">
                                                            <i class="fas fa-pause"></i>
                                                        </a>
                                                        <?php elseif ($user['status'] === 'suspended'): ?>
                                                        <a href="?action=activate&id=<?php echo $user['id']; ?>" class="btn btn-sm btn-success" title="Activate">
                                                            <i class="fas fa-play"></i>
                                                        </a>
                                                        <?php endif; ?>
                                                        
                                                        <a href="?edit_id=<?php echo $user['id']; ?>" class="btn btn-sm btn-info" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        
                                                        <?php if (in_array($user['status'], ['pending', 'rejected'])): ?>
                                                        <a href="?action=delete&id=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this user?')">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                        <?php endif; ?>
                                                    </div>
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
    </div>
    
    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST">
                        <input type="hidden" name="add_user" value="1">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="first_name" class="form-label">First Name *</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="last_name" class="form-label">Last Name *</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" required>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="username" class="form-label">Username *</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email *</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="role" class="form-label">Role *</label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="midwife">Midwife</option>
                                <option value="bhw">Barangay Health Worker</option>
                                <option value="bns">Barangay Nutrition Scholar</option>
                                <option value="mother">Mother</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password *</label>
                            <input type="password" class="form-control" id="password" name="password" required minlength="6">
                            <div class="form-text">Password must be at least 6 characters long.</div>
                        </div>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            New users will be created with "Pending" status and will require admin approval before they can access the system.
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Add User</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <?php if ($editUser): ?>
    <div class="modal fade show" id="editUserModal" tabindex="-1" style="display: block; padding-right: 17px;">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit User</h5>
                    <a href="?" class="btn-close"></a>
                </div>
                <div class="modal-body">
                    <form method="POST">
                        <input type="hidden" name="edit_user" value="1">
                        <input type="hidden" name="user_id" value="<?php echo $editUser['id']; ?>">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_first_name" class="form-label">First Name *</label>
                                    <input type="text" class="form-control" id="edit_first_name" name="first_name" value="<?php echo htmlspecialchars($editUser['first_name']); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_last_name" class="form-label">Last Name *</label>
                                    <input type="text" class="form-control" id="edit_last_name" name="last_name" value="<?php echo htmlspecialchars($editUser['last_name']); ?>" required>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_username" class="form-label">Username *</label>
                            <input type="text" class="form-control" id="edit_username" name="username" value="<?php echo htmlspecialchars($editUser['username']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_email" class="form-label">Email *</label>
                            <input type="email" class="form-control" id="edit_email" name="email" value="<?php echo htmlspecialchars($editUser['email']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_role" class="form-label">Role *</label>
                            <select class="form-select" id="edit_role" name="role" required>
                                <option value="midwife" <?php echo $editUser['role'] === 'midwife' ? 'selected' : ''; ?>>Midwife</option>
                                <option value="bhw" <?php echo $editUser['role'] === 'bhw' ? 'selected' : ''; ?>>Barangay Health Worker</option>
                                <option value="bns" <?php echo $editUser['role'] === 'bns' ? 'selected' : ''; ?>>Barangay Nutrition Scholar</option>
                                <option value="mother" <?php echo $editUser['role'] === 'mother' ? 'selected' : ''; ?>>Mother</option>
                            </select>
                        </div>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Changing user information will not affect their current status.
                        </div>
                        <div class="d-flex gap-2">
                            <a href="?" class="btn btn-secondary w-50">Cancel</a>
                            <button type="submit" class="btn btn-primary w-50">Update User</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <div class="modal-backdrop fade show"></div>
    <?php endif; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-focus on first input when modal opens
        document.getElementById('addUserModal')?.addEventListener('shown.bs.modal', function () {
            document.getElementById('first_name').focus();
        });

        // Auto-focus on first input when edit modal is shown
        <?php if ($editUser): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('edit_first_name').focus();
        });
        <?php endif; ?>
        
        // Show password strength (optional enhancement)
        document.getElementById('password')?.addEventListener('input', function() {
            const password = this.value;
            const strength = document.getElementById('password-strength');
            
            if (!strength) {
                const strengthDiv = document.createElement('div');
                strengthDiv.id = 'password-strength';
                strengthDiv.className = 'form-text';
                this.parentNode.appendChild(strengthDiv);
            }
            
            let strengthText = '';
            let strengthClass = '';
            
            if (password.length === 0) {
                strengthText = '';
            } else if (password.length < 6) {
                strengthText = 'Weak';
                strengthClass = 'text-danger';
            } else if (password.length < 8) {
                strengthText = 'Medium';
                strengthClass = 'text-warning';
            } else {
                strengthText = 'Strong';
                strengthClass = 'text-success';
            }
            
            document.getElementById('password-strength').innerHTML = strengthText;
            document.getElementById('password-strength').className = 'form-text ' + strengthClass;
        });
    </script>
</body>
</html>