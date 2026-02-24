<?php
// family_planning.php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

if (!isAuthorized(['admin', 'midwife'])) {
    header("Location: login.php");
    exit();
}

$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Query
$query = "
    SELECT fpr.*, m.first_name, m.last_name, m.phone, fpm.method_name 
    FROM family_planning_records fpr
    JOIN mothers m ON fpr.mother_id = m.id
    JOIN family_planning_methods fpm ON fpr.method_id = fpm.id
";

$whereConditions = [];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(m.first_name LIKE :search OR m.last_name LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if (!empty($whereConditions)) {
    $query .= " WHERE " . implode(" AND ", $whereConditions);
}

$query .= " ORDER BY fpr.registration_date DESC LIMIT :limit OFFSET :offset";

// Count
$countQuery = "
    SELECT COUNT(*) 
    FROM family_planning_records fpr
    JOIN mothers m ON fpr.mother_id = m.id
";
if (!empty($whereConditions)) {
    $countQuery .= " WHERE " . implode(" AND ", $whereConditions);
}
$stmt = $pdo->prepare($countQuery);
if (!empty($search)) {
    $stmt->bindValue(':search', '%' . $search . '%');
}
$stmt->execute();
$totalRecords = $stmt->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

// Execute Main
$stmt = $pdo->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Family Planning - Kibenes eBirth</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include_once 'includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include_once 'includes/sidebar.php'; ?>
            
            <main class="main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Family Planning Services</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="forms/family_planning_form.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>New Client
                        </a>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-body">
                         <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Mother Name</th>
                                        <th>Method</th>
                                        <th>Date Registered</th>
                                        <th>Next Service</th>
                                        <th>Remarks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($records)): ?>
                                        <tr><td colspan="5" class="text-center text-muted">No family planning records found.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($records as $r): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($r['first_name'] . ' ' . $r['last_name']); ?></td>
                                            <td><span class="badge bg-info"><?php echo htmlspecialchars($r['method_name']); ?></span></td>
                                            <td><?php echo date('M j, Y', strtotime($r['registration_date'])); ?></td>
                                            <td>
                                                <?php echo $r['next_service_date'] ? date('M j, Y', strtotime($r['next_service_date'])) : '--'; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($r['remarks'] ?? ''); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                         </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
