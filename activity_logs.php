<?php
// Use correct path to includes
$rootPath = __DIR__;

require_once $rootPath . '/includes/auth.php';
require_once $rootPath . '/includes/functions.php';

if (!isAuthorized(['admin'])) {
    header("Location: login.php");
    exit();
}

// Set the base URL
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$path = dirname($_SERVER['SCRIPT_NAME']);
$baseUrl = $protocol . "://" . $host . $path;
$baseUrl = rtrim($baseUrl, '/');

global $pdo;

// Simple query to get all activities
$search = $_GET['search'] ?? '';
$roleFilter = $_GET['role'] ?? '';

// Build simple query
$query = "
    SELECT 
        a.id,
        a.activity,
        a.timestamp,
        a.user_id,
        u.username,
        u.role,
        u.first_name,
        u.last_name
    FROM system_activities a 
    LEFT JOIN users u ON a.user_id = u.id 
    WHERE 1=1
";

$params = [];

// Simple search filter
if (!empty($search)) {
    $query .= " AND (a.activity LIKE :search OR u.username LIKE :search OR u.first_name LIKE :search OR u.last_name LIKE :search)";
    $params[':search'] = "%$search%";
}

// Simple role filter
if (!empty($roleFilter)) {
    $query .= " AND u.role = :role";
    $params[':role'] = $roleFilter;
}

$query .= " ORDER BY a.timestamp DESC";

// Get total count
$countQuery = "SELECT COUNT(*) FROM ($query) as count_query";
$countStmt = $pdo->prepare($countQuery);
foreach ($params as $key => $value) {
    $countStmt->bindValue($key, $value);
}
$countStmt->execute();
$totalActivities = $countStmt->fetchColumn();

// Compact pagination - mas konting records per page
$perPage = 15; // Reduced from 50 to 15 for more compact view
$totalPages = ceil($totalActivities / $perPage);
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($currentPage - 1) * $perPage;

$query .= " LIMIT $perPage OFFSET $offset";

// Execute main query
$stmt = $pdo->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique roles for filter dropdown
$roles = $pdo->query("SELECT DISTINCT role FROM users WHERE role IS NOT NULL ORDER BY role")->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Activity Logs - Kibenes eBirth</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>/css/style.css">
    <style>
        :root {
            --primary: #1a73e8;
            --primary-light: #e8f0fe;
            --secondary: #34a853;
            --accent: #fbbc05;
            --light: #f8f9fa;
            --dark: #202124;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', system-ui, sans-serif;
            font-size: 0.9rem;
        }
        
        .dashboard-header {
            background: white;
            border-radius: 8px;
            padding: 1rem 1.25rem;
            margin-bottom: 1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .content-section {
            background: white;
            border-radius: 8px;
            margin-bottom: 1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .section-header {
            background: #2c3e50;
            color: white;
            padding: 0.75rem 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .section-header h5 {
            margin: 0;
            font-weight: 600;
            font-size: 0.95rem;
        }
        
        .section-body {
            padding: 1rem;
        }
        
        .simple-filter {
            background: #f8f9fa;
            border-radius: 6px;
            padding: 0.75rem;
            margin-bottom: 1rem;
            border: 1px solid #dee2e6;
        }
        
        .activity-item {
            display: flex;
            align-items: start;
            padding: 0.5rem 0.75rem;
            border-bottom: 1px solid #f0f0f0;
            transition: background-color 0.2s ease;
            font-size: 0.85rem;
        }
        
        .activity-item:hover {
            background-color: #f8f9fa;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 0.75rem;
            flex-shrink: 0;
            font-size: 0.8rem;
        }
        
        .activity-icon.admin { background: #e8f5e8; color: #34a853; }
        .activity-icon.midwife { background: #e3f2fd; color: #1a73e8; }
        .activity-icon.user { background: #fff3cd; color: #fbbc05; }
        .activity-icon.system { background: #f5f5f5; color: #6c757d; }
        
        .activity-content {
            flex: 1;
            min-width: 0; /* Prevent overflow */
        }
        
        .activity-text {
            margin: 0;
            color: var(--dark);
            line-height: 1.3;
            word-wrap: break-word;
        }
        
        .activity-meta {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-top: 0.25rem;
            flex-wrap: wrap;
        }
        
        .activity-time {
            font-size: 0.75rem;
            color: #6c757d;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .activity-user {
            font-size: 0.75rem;
            color: #6c757d;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .badge-role {
            font-size: 0.65rem;
            padding: 0.2rem 0.4rem;
        }
        
        .badge-admin { background: #34a853; }
        .badge-midwife { background: #1a73e8; }
        .badge-user { background: #fbbc05; }
        
        .pagination-container {
            display: flex;
            justify-content: center;
            margin-top: 1rem;
        }
        
        .pagination {
            font-size: 0.85rem;
            margin-bottom: 0;
        }
        
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            opacity: 0.5;
        }
        
        .stats-card {
            background: white;
            border-radius: 6px;
            padding: 1rem;
            text-align: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border-left: 3px solid var(--primary);
        }
        
        .stats-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.25rem;
        }
        
        .stats-label {
            color: #6c757d;
            font-weight: 500;
            font-size: 0.8rem;
        }
        
        .form-control, .form-select {
            font-size: 0.85rem;
            padding: 0.4rem 0.75rem;
        }
        
        .btn {
            font-size: 0.85rem;
            padding: 0.4rem 0.75rem;
        }
        
        @media (max-width: 768px) {
            .activity-meta {
                flex-direction: column;
                align-items: start;
                gap: 0.25rem;
            }
            
            .activity-item {
                padding: 0.5rem;
            }
            
            .stats-card {
                margin-bottom: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <?php include_once $rootPath . '/includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include_once $rootPath . '/includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <!-- Dashboard Header -->
                <div class="dashboard-header">
                    <div class="row align-items-center">
                        <div class="col">
                            <h1 class="h4 mb-1">System Activity Logs</h1>
                            <p class="text-muted mb-0">Complete history of all system activities</p>
                        </div>
                    </div>
                </div>

                <!-- Simple Stats -->
                <div class="row mb-3">
                    <div class="col-md-4">
                        <div class="stats-card">
                            <div class="stats-number"><?php echo $totalActivities; ?></div>
                            <div class="stats-label">Total Activities</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card">
                            <div class="stats-number"><?php echo $perPage; ?></div>
                            <div class="stats-label">Per Page</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card">
                            <div class="stats-number"><?php echo $currentPage; ?> of <?php echo $totalPages; ?></div>
                            <div class="stats-label">Current Page</div>
                        </div>
                    </div>
                </div>

                <!-- Simple Filters -->
                <div class="content-section">
                    <div class="section-header">
                        <h5><i class="fas fa-search me-1"></i>Quick Search</h5>
                    </div>
                    <div class="section-body">
                        <form method="GET" class="simple-filter">
                            <div class="row g-2 align-items-end">
                                <div class="col-md-7">
                                    <label class="form-label small">Search Activities</label>
                                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search activities, usernames..." value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small">Filter by Role</label>
                                    <select name="role" class="form-select form-select-sm">
                                        <option value="">All Roles</option>
                                        <?php foreach ($roles as $role): ?>
                                            <option value="<?php echo $role; ?>" <?php echo $roleFilter === $role ? 'selected' : ''; ?>>
                                                <?php echo ucfirst($role); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-sm btn-primary">
                                            <i class="fas fa-search me-1"></i>Search
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <?php if (!empty($search) || !empty($roleFilter)): ?>
                            <div class="row mt-2">
                                <div class="col-12">
                                    <a href="<?php echo $baseUrl; ?>/activity_logs.php" class="btn btn-sm btn-outline-secondary">
                                        <i class="fas fa-refresh me-1"></i>Clear Filters
                                    </a>
                                    <small class="text-muted ms-2">
                                        Showing <?php echo count($activities); ?> of <?php echo $totalActivities; ?> activities
                                    </small>
                                </div>
                            </div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <!-- Activities List -->
                <div class="content-section">
                    <div class="section-header">
                        <h5><i class="fas fa-history me-1"></i>All Activities</h5>
                        <span class="badge bg-light text-dark small"><?php echo $totalActivities; ?> total</span>
                    </div>
                    <div class="section-body p-0">
                        <?php if (!empty($activities)): ?>
                            <div class="activities-list">
                                <?php foreach ($activities as $activity): 
                                    $roleClass = $activity['role'] ?? 'system';
                                    $iconClass = $activity['role'] ?? 'system';
                                    $badgeClass = 'badge-' . $roleClass;
                                ?>
                                <div class="activity-item">
                                    <div class="activity-icon <?php echo $iconClass; ?>">
                                        <i class="fas <?php 
                                            echo $activity['role'] == 'admin' ? 'fa-user-shield' : 
                                                 ($activity['role'] == 'midwife' ? 'fa-user-nurse' : 
                                                 ($activity['role'] == 'user' ? 'fa-user' : 'fa-cog')); 
                                        ?>"></i>
                                    </div>
                                    <div class="activity-content">
                                        <p class="activity-text"><?php echo htmlspecialchars($activity['activity']); ?></p>
                                        <div class="activity-meta">
                                            <div class="activity-time">
                                                <i class="fas fa-clock"></i>
                                                <?php echo date('M j, Y g:i A', strtotime($activity['timestamp'])); ?>
                                            </div>
                                            <?php if ($activity['username']): ?>
                                            <div class="activity-user">
                                                <i class="fas fa-user"></i>
                                                <?php echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']); ?>
                                                <span class="badge <?php echo $badgeClass; ?> badge-role ms-1">
                                                    <?php echo ucfirst($activity['role']); ?>
                                                </span>
                                            </div>
                                            <?php else: ?>
                                            <div class="activity-user">
                                                <i class="fas fa-cog"></i>
                                                System
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Simple Pagination -->
                            <?php if ($totalPages > 1): ?>
                            <div class="pagination-container">
                                <nav>
                                    <ul class="pagination pagination-sm">
                                        <?php if ($currentPage > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $currentPage - 1])); ?>">
                                                    <i class="fas fa-chevron-left"></i>
                                                </a>
                                            </li>
                                        <?php endif; ?>

                                        <?php 
                                        // Show limited page numbers
                                        $startPage = max(1, $currentPage - 2);
                                        $endPage = min($totalPages, $currentPage + 2);
                                        
                                        for ($i = $startPage; $i <= $endPage; $i++): ?>
                                            <li class="page-item <?php echo $i == $currentPage ? 'active' : ''; ?>">
                                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>

                                        <?php if ($currentPage < $totalPages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $currentPage + 1])); ?>">
                                                    <i class="fas fa-chevron-right"></i>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            </div>
                            <?php endif; ?>

                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-search"></i>
                                <h6>No Activities Found</h6>
                                <p class="text-muted small">No system activities match your search criteria.</p>
                                <a href="<?php echo $baseUrl; ?>/activity_logs.php" class="btn btn-sm btn-primary">
                                    <i class="fas fa-refresh me-1"></i>View All Activities
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh page every 3 minutes to show new activities
        setTimeout(function() {
            window.location.reload();
        }, 180000);
    </script>
</body>
</html>