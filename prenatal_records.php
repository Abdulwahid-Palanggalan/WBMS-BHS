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

// Build query - UPDATED FOR YOUR ACTUAL DATABASE STRUCTURE
$query = "
    SELECT pr.*, 
           m.first_name as mother_first_name, 
           m.last_name as mother_last_name,
           m.phone as mother_phone,
           pd.edc, pd.lmp,
           ROUND(DATEDIFF(pr.visit_date, pd.lmp) / 7, 1) as gestational_weeks,
           (SELECT weight FROM prenatal_records WHERE mother_id = m.id AND visit_date < pr.visit_date ORDER BY visit_date DESC LIMIT 1) as previous_weight,
           pr.weight - (SELECT weight FROM prenatal_records WHERE mother_id = m.id AND visit_date < pr.visit_date ORDER BY visit_date DESC LIMIT 1) as weight_change
    FROM prenatal_records pr
    JOIN mothers m ON pr.mother_id = m.id
    LEFT JOIN pregnancy_details pd ON m.id = pd.mother_id
";

$countQuery = "
    SELECT COUNT(*)
    FROM prenatal_records pr
    JOIN mothers m ON pr.mother_id = m.id
    LEFT JOIN pregnancy_details pd ON m.id = pd.mother_id
";

if (!empty($search)) {
    $where = " WHERE m.first_name LIKE :search OR m.last_name LIKE :search OR m.phone LIKE :search";
    $query .= $where;
    $countQuery .= $where;
}

$query .= " ORDER BY pr.visit_date DESC LIMIT :limit OFFSET :offset";

// Get total count
$stmt = $pdo->prepare($countQuery);
if (!empty($search)) {
    $stmt->bindValue(':search', '%' . $search . '%');
}
$stmt->execute();
$totalRecords = $stmt->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

// Get prenatal records
$stmt = $pdo->prepare($query);
if (!empty($search)) {
    $stmt->bindValue(':search', '%' . $search . '%');
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$prenatalRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prenatal Records - Kibenes eBirth</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .prenatal-details-modal .modal-lg {
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
        .pregnancy-info {
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
        .weight-increase { 
            color: #28a745; 
        }
        .weight-decrease { 
            color: #dc3545; 
        }
        .weight-stable { 
            color: #6c757d; 
        }
        .medication-yes {
            background-color: #d4edda;
            color: #155724;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.75em;
        }
        .medication-no {
            background-color: #f8d7da;
            color: #721c24;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.75em;
        }
        .test-badge {
            background-color: #e9ecef;
            color: #495057;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75em;
            margin-right: 5px;
            margin-bottom: 5px;
            display: inline-block;
        }
        .test-normal {
            background-color: #d4edda;
            color: #155724;
        }
        .test-abnormal {
            background-color: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <?php include_once $rootPath . '/includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include_once $rootPath . '/includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Prenatal Care Records</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="forms/prenatal_form.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>New Prenatal Visit
                        </a>
                    </div>
                </div>

                <!-- Search and Stats -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <form method="GET" class="d-flex">
                            <input type="text" name="search" class="form-control me-2" placeholder="Search mothers..." value="<?php echo htmlspecialchars($search ?? ''); ?>">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i>
                            </button>
                        </form>
                    </div>
                    <div class="col-md-6 text-end">
                        <div class="text-muted">
                            Total: <strong><?php echo $totalRecords; ?></strong> prenatal records found
                        </div>
                    </div>
                </div>

                <!-- Prenatal Records Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Visit Date</th>
                                        <th>Mother</th>
                                        <th>Contact</th>
                                        <th>Gestational Age</th>
                                        <th>Visit #</th>
                                        <th>Vital Signs</th>
                                        <th>Weight Tracking</th>
                                        <th>Medications</th>
                                        <th>Lab Tests</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($prenatalRecords)): ?>
                                        <?php foreach ($prenatalRecords as $record): ?>
                                        <tr>
                                            <td><?= date('M j, Y', strtotime($record['visit_date'])) ?></td>
                                            <td>
                                                <div class="fw-semibold"><?= htmlspecialchars(($record['mother_first_name'] ?? '') . ' ' . ($record['mother_last_name'] ?? '')) ?></div>
                                                <?php if (!empty($record['edc'])): ?>
                                                    <small class="text-muted">EDC: <?= date('M j, Y', strtotime($record['edc'])) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div><i class="fas fa-phone text-success me-1"></i><?= htmlspecialchars($record['mother_phone'] ?? '') ?></div>
                                            </td>
                                            <td>
                                                <strong><?= $record['gestational_weeks'] ?? 0 ?> weeks</strong>
                                                <?php if (!empty($record['gestational_age'])): ?>
                                                    <br><small class="text-muted"><?= $record['gestational_age'] ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary"><?= $record['visit_number'] ?? '' ?></span>
                                            </td>
                                            <td>
                                                <small>
                                                    BP: <?= $record['blood_pressure'] ?? 'N/A' ?><br>
                                                    Temp: <?= $record['temperature'] ?? 'N/A' ?>°C
                                                </small>
                                            </td>
                                            <td>
                                                <strong><?= $record['weight'] ?? '' ?> kg</strong>
                                                <?php if ($record['previous_weight'] ?? false): ?>
                                                    <?php
                                                    $weightChange = $record['weight_change'] ?? 0;
                                                    $changeClass = $weightChange > 0 ? 'weight-increase' : ($weightChange < 0 ? 'weight-decrease' : 'weight-stable');
                                                    $changeIcon = $weightChange > 0 ? 'fa-arrow-up' : ($weightChange < 0 ? 'fa-arrow-down' : 'fa-minus');
                                                    ?>
                                                    <br>
                                                    <small class="<?= $changeClass ?>">
                                                        <i class="fas <?= $changeIcon ?>"></i>
                                                        <?= abs($weightChange) ?> kg
                                                    </small>
                                                <?php else: ?>
                                                    <br><small class="text-muted">First record</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="d-flex gap-1">
                                                    <?php if ($record['iron_supplement'] ?? false): ?>
                                                        <span class="medication-yes" title="Iron">Fe</span>
                                                    <?php endif; ?>
                                                    <?php if ($record['folic_acid'] ?? false): ?>
                                                        <span class="medication-yes" title="Folic Acid">FA</span>
                                                    <?php endif; ?>
                                                    <?php if ($record['calcium'] ?? false): ?>
                                                        <span class="medication-yes" title="Calcium">Ca</span>
                                                    <?php endif; ?>
                                                    <?php if (!($record['iron_supplement'] ?? false) && !($record['folic_acid'] ?? false) && !($record['calcium'] ?? false)): ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-wrap gap-1">
                                                    <?php if (!empty($record['hb_level'])): ?>
                                                        <span class="test-badge <?= ($record['hb_level'] < 11) ? 'test-abnormal' : 'test-normal' ?>" title="Hemoglobin">
                                                            Hb: <?= $record['hb_level'] ?>
                                                        </span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($record['blood_group'])): ?>
                                                        <span class="test-badge" title="Blood Group">
                                                            BG: <?= $record['blood_group'] ?>
                                                        </span>
                                                    <?php endif; ?>
                                                    <?php if (empty($record['hb_level']) && empty($record['blood_group'])): ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <a href="forms/prenatal_form.php?edit=<?= $record['id'] ?>" class="btn btn-sm btn-outline-warning">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <button class="btn btn-sm btn-outline-info view-details" 
                                                            data-record-id="<?= $record['id'] ?>"
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#prenatalDetailsModal">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-danger" 
                                                            onclick="confirmDelete('prenatal_record', <?= $record['id'] ?>, 'Prenatal Visit #<?= $record['visit_number'] ?? '' ?> for <?= htmlspecialchars(($record['mother_first_name'] ?? '') . ' ' . ($record['mother_last_name'] ?? '')) ?>')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="10" class="text-center py-4 text-muted">
                                                <i class="fas fa-heartbeat fa-2x mb-3 d-block"></i>
                                                <?php echo !empty($search) ? 'No prenatal records found matching your search.' : 'No prenatal records found'; ?>
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
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>">Previous</a>
                                    </li>
                                <?php endif; ?>

                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($page < $totalPages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>">Next</a>
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

    <!-- Prenatal Details Modal -->
    <div class="modal fade prenatal-details-modal" id="prenatalDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Prenatal Visit Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="prenatalDetailsContent">
                    <!-- Content will be loaded via AJAX -->
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading prenatal visit details...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="editPrenatalBtn">Edit Visit</button>
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

        // View prenatal details
        document.addEventListener('DOMContentLoaded', function() {
            const viewButtons = document.querySelectorAll('.view-details');
            const modal = document.getElementById('prenatalDetailsModal');
            const content = document.getElementById('prenatalDetailsContent');
            const editBtn = document.getElementById('editPrenatalBtn');
            let currentRecordId = null;

            viewButtons.forEach(button => {
                button.addEventListener('click', function() {
                    currentRecordId = this.getAttribute('data-record-id');
                    loadPrenatalDetails(currentRecordId);
                });
            });

            function loadPrenatalDetails(recordId) {
                fetch(`get_prenatal_details.php?id=${recordId}`)
                    .then(response => response.text())
                    .then(data => {
                        content.innerHTML = data;
                    })
                    .catch(error => {
                        content.innerHTML = `
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Failed to load prenatal visit details. Please try again.
                            </div>
                        `;
                    });
            }

            // Edit button handler
            editBtn.addEventListener('click', function() {
                if (currentRecordId) {
                    window.location.href = `forms/prenatal_form.php?edit=${currentRecordId}`;
                }
            });

            // Reload details when modal is shown
            modal.addEventListener('show.bs.modal', function() {
                if (currentRecordId) {
                    loadPrenatalDetails(currentRecordId);
                }
            });
        });
    </script>
</body>
</html>