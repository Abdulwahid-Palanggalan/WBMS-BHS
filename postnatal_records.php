<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session FIRST before any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$rootPath = __DIR__;
require_once $rootPath . '/includes/auth.php';
require_once $rootPath . '/includes/functions.php';

// Check authorization
if (!isAuthorized(['admin', 'midwife'])) {
    header("Location: login.php");
    exit();
}

// Handle search and pagination
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Build query - CORRECTED based on your database structure
$query = "
    SELECT 
        pr.*,
        br.first_name as baby_first_name, 
        br.middle_name as baby_middle_name,
        br.last_name as baby_last_name,
        br.birth_date, 
        br.birth_weight, 
        br.gender,
        m.first_name as mother_first_name,
        m.middle_name as mother_middle_name,
        m.last_name as mother_last_name,
        m.phone as mother_phone,
        m.address as mother_address,
        u_recorder.first_name as recorded_first_name,
        u_recorder.last_name as recorded_last_name,
        DATEDIFF(pr.visit_date, br.birth_date) as days_after_birth,
        (pr.baby_weight - br.birth_weight) as weight_gain
    FROM postnatal_records pr
    JOIN birth_records br ON pr.baby_id = br.id
    JOIN mothers m ON br.mother_id = m.id
    LEFT JOIN users u_recorder ON pr.recorded_by = u_recorder.id
";

$countQuery = "
    SELECT COUNT(*) 
    FROM postnatal_records pr
    JOIN birth_records br ON pr.baby_id = br.id
    JOIN mothers m ON br.mother_id = m.id
";

$whereConditions = [];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(m.first_name LIKE :search OR m.last_name LIKE :search OR m.phone LIKE :search 
               OR br.first_name LIKE :search OR br.last_name LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if (!empty($whereConditions)) {
    $whereClause = " WHERE " . implode(" AND ", $whereConditions);
    $query .= $whereClause;
    $countQuery .= $whereClause;
}

$query .= " ORDER BY pr.visit_date DESC LIMIT :limit OFFSET :offset";

// Get total count
$stmt = $pdo->prepare($countQuery);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$totalRecords = $stmt->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

// Get postnatal records
$stmt = $pdo->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$postnatalRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Postnatal Records - Kibenes eBirth</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .postnatal-details-modal .modal-lg {
            max-width: 900px;
        }
        .detail-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            border-left: 4px solid #007bff;
        }
        .detail-section h6 {
            color: #2c3e50;
            margin-bottom: 15px;
            font-weight: 600;
        }
        .detail-item {
            margin-bottom: 8px;
            padding-bottom: 8px;
            border-bottom: 1px solid #e9ecef;
        }
        .detail-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        .detail-label {
            font-weight: 600;
            color: #495057;
            min-width: 180px;
        }
        .detail-value {
            color: #6c757d;
        }
        .empty-data {
            color: #6c757d;
            font-style: italic;
        }
        .mother-info {
            background: linear-gradient(135deg, #e8f5e8 0%, #f0f8ff 100%);
            border-left: 4px solid #28a745;
        }
        .baby-info {
            background: linear-gradient(135deg, #fff3cd 0%, #f8f9fa 100%);
            border-left: 4px solid #ffc107;
        }
        .visit-info {
            background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%);
            border-left: 4px solid #9c27b0;
        }
        .table-hover tbody tr:hover {
            background-color: rgba(0, 123, 255, 0.05);
            cursor: pointer;
        }
        .table th {
            background-color: #2c3e50;
            color: white;
            font-weight: 600;
            vertical-align: middle;
        }
        .weight-positive {
            color: #28a745;
            font-weight: 600;
        }
        .weight-negative {
            color: #dc3545;
            font-weight: 600;
        }
        .weight-neutral {
            color: #6c757d;
        }
        .status-badge {
            font-size: 0.75em;
            padding: 0.35em 0.65em;
        }
        .action-buttons {
            min-width: 120px;
        }
        .vital-badge {
            font-size: 0.7em;
            padding: 0.3em 0.6em;
        }
        .health-status-normal { background-color: #28a745; color: white; }
        .health-status-warning { background-color: #ffc107; color: black; }
        .health-status-danger { background-color: #dc3545; color: white; }
    </style>
</head>
<body>
    <?php include_once $rootPath . '/includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include_once $rootPath . '/includes/sidebar.php'; ?>
            
            <main class="main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Postnatal Care Records</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="forms/postnatal_form.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>New Postnatal Visit
                        </a>
                    </div>
                </div>

                <!-- Search and Stats -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <form method="GET" class="d-flex">
                            <input type="text" name="search" class="form-control me-2" placeholder="Search by mother or baby name..." value="<?php echo htmlspecialchars($search ?? ''); ?>">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i>
                            </button>
                        </form>
                    </div>
                    <div class="col-md-6 text-end">
                        <div class="text-muted">
                            Total: <strong><?php echo $totalRecords; ?></strong> postnatal records found
                            <?php if (!empty($search)): ?>
                                <span class="badge bg-info">Search Active</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Postnatal Records Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th width="12%">Visit Information</th>
                                        <th width="15%">Mother Details</th>
                                        <th width="15%">Baby Details</th>
                                        <th width="10%">Days After Birth</th>
                                        <th width="12%">Vital Signs</th>
                                        <th width="12%">Weight Tracking</th>
                                        <th width="10%">Feeding Method</th>
                                        <th width="14%" class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($postnatalRecords)): ?>
                                        <?php foreach ($postnatalRecords as $record): ?>
                                        <tr>
                                            <!-- Visit Information Column -->
                                            <td>
                                                <div class="fw-semibold text-primary">
                                                    <?= date('M j, Y', strtotime($record['visit_date'])) ?>
                                                </div>
                                                <div class="mt-1">
                                                    <span class="badge bg-primary status-badge">
                                                        Visit #<?= $record['visit_number'] ?? '1' ?>
                                                    </span>
                                                </div>
                                                <?php if (!empty($record['next_visit_date'])): ?>
                                                    <div class="small text-muted mt-1">
                                                        <i class="fas fa-calendar-check me-1"></i>
                                                        Next: <?= date('M j', strtotime($record['next_visit_date'])) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            
                                            <!-- Mother Details Column -->
                                            <td>
                                                <div class="fw-semibold">
                                                    <?= htmlspecialchars(($record['mother_first_name'] ?? '') . ' ' . ($record['mother_last_name'] ?? '')) ?>
                                                </div>
                                                <div class="small text-muted">
                                                    <i class="fas fa-phone text-success me-1"></i>
                                                    <?= !empty($record['mother_phone']) ? htmlspecialchars($record['mother_phone']) : 'No phone' ?>
                                                </div>
                                                <?php if (!empty($record['weight'])): ?>
                                                    <div class="small mt-1">
                                                        <strong>Weight:</strong> <?= $record['weight'] ?> kg
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            
                                            <!-- Baby Details Column -->
                                            <td>
                                                <div class="fw-semibold">
                                                    <?= htmlspecialchars(($record['baby_first_name'] ?? '') . ' ' . ($record['baby_last_name'] ?? '')) ?>
                                                </div>
                                                <div class="small text-muted">
                                                    <span class="badge bg-<?= strtolower($record['gender'] ?? '') == 'male' ? 'primary' : 'danger' ?> vital-badge">
                                                        <?= ucfirst($record['gender'] ?? '') ?>
                                                    </span>
                                                    <?php if (!empty($record['birth_date'])): ?>
                                                        <span class="ms-1">
                                                            Born: <?= date('M j', strtotime($record['birth_date'])) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if (!empty($record['birth_weight'])): ?>
                                                    <div class="small mt-1">
                                                        <strong>Birth Wt:</strong> <?= $record['birth_weight'] ?> kg
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            
                                            <!-- Days After Birth Column -->
                                            <td>
                                                <?php 
                                                $days = $record['days_after_birth'] ?? 0;
                                                if ($days <= 7) {
                                                    $badgeClass = 'bg-danger';
                                                } elseif ($days <= 30) {
                                                    $badgeClass = 'bg-warning';
                                                } else {
                                                    $badgeClass = 'bg-success';
                                                }
                                                ?>
                                                <span class="badge <?= $badgeClass ?> status-badge">
                                                    <?= $days ?> days
                                                </span>
                                                <?php if ($days > 0): ?>
                                                    <div class="small text-muted mt-1">
                                                        <?= floor($days / 7) ?> week(s)
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            
                                            <!-- Vital Signs Column -->
                                            <td>
                                                <div class="small">
                                                    <?php if (!empty($record['blood_pressure'])): ?>
                                                        <div class="mb-1">
                                                            <strong>BP:</strong> 
                                                            <span class="text-primary"><?= $record['blood_pressure'] ?></span>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($record['temperature'])): ?>
                                                        <div>
                                                            <strong>Temp:</strong> 
                                                            <span class="text-success"><?= $record['temperature'] ?>°C</span>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if (empty($record['blood_pressure']) && empty($record['temperature'])): ?>
                                                    <span class="text-muted small">No vitals</span>
                                                <?php endif; ?>
                                            </td>
                                            
                                            <!-- Weight Tracking Column -->
                                            <td>
                                                <div class="small">
                                                    <div class="mb-1">
                                                        <strong>Current:</strong> 
                                                        <span class="fw-semibold"><?= $record['baby_weight'] ?? 'N/A' ?> kg</span>
                                                    </div>
                                                    <?php if (isset($record['weight_gain']) && $record['baby_weight']): ?>
                                                        <div class="mb-1">
                                                            <strong>Total Gain:</strong>
                                                            <span class="<?= ($record['weight_gain'] > 0) ? 'weight-positive' : (($record['weight_gain'] < 0) ? 'weight-negative' : 'weight-neutral') ?>">
                                                                <?= number_format($record['weight_gain'], 2) ?> kg
                                                            </span>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            
                                            <!-- Feeding Method Column -->
                                            <td>
                                                <?php if (!empty($record['feeding_method'])): ?>
                                                    <?php
                                                    $feedingMethods = [
                                                        'exclusive-breastfeeding' => ['label' => 'Breastfeeding', 'class' => 'bg-success'],
                                                        'mixed-feeding' => ['label' => 'Mixed', 'class' => 'bg-warning'],
                                                        'formula' => ['label' => 'Formula', 'class' => 'bg-info']
                                                    ];
                                                    $method = $feedingMethods[$record['feeding_method']] ?? ['label' => ucfirst(str_replace('-', ' ', $record['feeding_method'])), 'class' => 'bg-secondary'];
                                                    ?>
                                                    <span class="badge <?= $method['class'] ?> status-badge">
                                                        <?= $method['label'] ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted small">Not specified</span>
                                                <?php endif; ?>
                                            </td>
                                            
                                            <!-- Actions Column -->
                                            <td class="text-center action-buttons">
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <button class="btn btn-outline-info view-details" 
                                                            data-record-id="<?= $record['id'] ?>"
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#postnatalDetailsModal"
                                                            title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <a href="forms/postnatal_form.php?edit=<?= $record['id'] ?>" 
                                                       class="btn btn-outline-warning" title="Edit Visit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <!-- DELETE BUTTON - Available for both admin and midwife -->
                                                    <button class="btn btn-outline-danger" 
                                                            onclick="confirmDelete('postnatal_record', <?= $record['id'] ?>, 'Postnatal Visit #<?= $record['visit_number'] ?? '1' ?> for <?= htmlspecialchars(($record['baby_first_name'] ?? '') . ' ' . ($record['baby_last_name'] ?? '')) ?>')"
                                                            title="Delete Record">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="text-center py-4 text-muted">
                                                <i class="fas fa-baby-carriage fa-2x mb-3 d-block"></i>
                                                <?php echo !empty($search) ? 'No postnatal records found matching your search.' : 'No postnatal records found'; ?>
                                                <?php if (!empty($search)): ?>
                                                    <br><a href="?" class="btn btn-sm btn-outline-primary mt-2">Clear Search</a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                        <nav>
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>">
                                            <i class="fas fa-chevron-left"></i> Previous
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php 
                                // Show limited pagination links
                                $startPage = max(1, $page - 2);
                                $endPage = min($totalPages, $page + 2);
                                
                                for ($i = $startPage; $i <= $endPage; $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($page < $totalPages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>">
                                            Next <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Postnatal Details Modal -->
    <div class="modal fade postnatal-details-modal" id="postnatalDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Postnatal Visit Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="postnatalDetailsContent">
                    <!-- Content will be loaded via AJAX -->
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading postnatal visit details...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="editPostnatalBtn">
                        <i class="fas fa-edit me-1"></i>Edit Visit
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function confirmDelete(type, id, name) {
            Swal.fire({
                title: 'Are you sure?',
                html: `You are about to delete <strong>${name}</strong>. This action cannot be undone.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `delete.php?type=${type}&id=${id}`;
                }
            });
        }

        // View postnatal details
        document.addEventListener('DOMContentLoaded', function() {
            const viewButtons = document.querySelectorAll('.view-details');
            const modal = document.getElementById('postnatalDetailsModal');
            const content = document.getElementById('postnatalDetailsContent');
            const editBtn = document.getElementById('editPostnatalBtn');
            let currentRecordId = null;

            viewButtons.forEach(button => {
                button.addEventListener('click', function() {
                    currentRecordId = this.getAttribute('data-record-id');
                    loadPostnatalDetails(currentRecordId);
                });
            });

            function loadPostnatalDetails(recordId) {
                fetch(`get_postnatal_details.php?id=${recordId}`)
                    .then(response => response.text())
                    .then(data => {
                        content.innerHTML = data;
                    })
                    .catch(error => {
                        content.innerHTML = `
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Failed to load postnatal visit details. Please try again.
                            </div>
                        `;
                    });
            }

            // Edit button handler
            editBtn.addEventListener('click', function() {
                if (currentRecordId) {
                    window.location.href = `forms/postnatal_form.php?edit=${currentRecordId}`;
                }
            });

            // Reload details when modal is shown
            modal.addEventListener('show.bs.modal', function() {
                if (currentRecordId) {
                    loadPostnatalDetails(currentRecordId);
                }
            });
        });
    </script>
</body>
</html>