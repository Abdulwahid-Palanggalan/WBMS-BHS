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

// Debug session data
error_log("Session role: " . ($_SESSION['role'] ?? 'NOT SET'));
error_log("Session user_id: " . ($_SESSION['user_id'] ?? 'NOT SET'));

// Check authorization
if (!isAuthorized(['admin', 'midwife'])) {
    error_log("User not authorized. Role: " . ($_SESSION['role'] ?? 'NOT SET'));
    header("Location: login.php");
    exit();
}

// Handle search and pagination
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Build query
$query = "SELECT m.*, u.first_name, u.last_name, u.email, u.phone,
                 pd.lmp, pd.edc, pd.gravida, pd.para
          FROM mothers m 
          JOIN users u ON m.user_id = u.id 
          LEFT JOIN pregnancy_details pd ON m.id = pd.mother_id";

$countQuery = "SELECT COUNT(*) FROM mothers m JOIN users u ON m.user_id = u.id";

if (!empty($search)) {
    $where = " WHERE u.first_name LIKE :search OR u.last_name LIKE :search OR u.email LIKE :search OR u.phone LIKE :search";
    $query .= $where;
    $countQuery .= $where;
}

$query .= " ORDER BY m.created_at DESC LIMIT :limit OFFSET :offset";

// Get total count
$stmt = $pdo->prepare($countQuery);
if (!empty($search)) {
    $stmt->bindValue(':search', '%' . $search . '%');
}
$stmt->execute();
$totalMothers = $stmt->fetchColumn();
$totalPages = ceil($totalMothers / $limit);

// Get mothers
$stmt = $pdo->prepare($query);
if (!empty($search)) {
    $stmt->bindValue(':search', '%' . $search . '%');
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$mothers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Mothers - Kibenes eBirth</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .mother-details-modal .modal-lg {
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
.pregnancy-info {
    background: linear-gradient(135deg, #e8f5e8 0%, #f0f8ff 100%);
    border-left: 4px solid #28a745;
}
.medical-info {
    background: linear-gradient(135deg, #fff3cd 0%, #f8f9fa 100%);
    border-left: 4px solid #ffc107;
}
.table-hover tbody tr:hover {
    background-color: rgba(0, 123, 255, 0.05);
    cursor: pointer;
}
.table th {
    background-color: #2c3e50;
    color: white;
    font-weight: 600;
    border-bottom: 2px solid #dee2e6;
    vertical-align: middle;
}
.status-badge {
    font-size: 0.75em;
    padding: 0.35em 0.65em;
}
.action-buttons {
    min-width: 140px;
}
    </style>
</head>
<body>
    <?php include_once $rootPath . '/includes/header.php'; ?>
    
    <div class="flex flex-col lg:flex-row min-h-[calc(100vh-4rem)]">
        <?php include_once $rootPath . '/includes/sidebar.php'; ?>
        
        <main class="flex-1 p-4 lg:p-8 space-y-8 no-print">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">All Registered Mothers</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="forms/mother_registration.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Register New Mother
                        </a>
                    </div>
                </div>

                <!-- Search and Stats -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <form method="GET" class="d-flex">
                            <input type="text" name="search" class="form-control me-2" placeholder="Search by name, email, or phone..." value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i>
                            </button>
                        </form>
                    </div>
                    <div class="col-md-6 text-end">
                        <div class="text-muted">
                            Total: <strong><?php echo $totalMothers; ?></strong> mothers found
                            <?php if (!empty($search)): ?>
                                <span class="badge bg-info">Search Active</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Mothers Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th width="18%">Mother Information</th>
                                        <th width="8%">Age</th>
                                        <th width="15%">Contact Details</th>
                                        <th width="15%">Address</th>
                                        <th width="18%">Pregnancy Details</th>
                                        <th width="8%">Status</th>
                                        <th width="18%" class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($mothers)): ?>
                                        <?php foreach ($mothers as $mother): ?>
                                        <tr>
                                            <!-- Mother Information Column -->
                                            <td>
                                                <div class="fw-semibold text-primary">
                                                    <?php echo htmlspecialchars($mother['first_name'] . ' ' . $mother['last_name']); ?>
                                                </div>
                                                <div class="small text-muted mt-1">
                                                    <i class="fas fa-heart text-danger me-1"></i>
                                                    <?php echo !empty($mother['civil_status']) ? htmlspecialchars($mother['civil_status']) : 'Civil Status: N/A'; ?>
                                                </div>
                                                <?php if (!empty($mother['date_of_birth']) && $mother['date_of_birth'] != '0000-00-00'): ?>
                                                    <div class="small text-muted">
                                                        <i class="fas fa-birthday-cake me-1"></i>
                                                        <?php echo date('M j, Y', strtotime($mother['date_of_birth'])); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            
                                            <!-- Age Column -->
                                            <td>
                                                <?php 
                                                if (!empty($mother['date_of_birth']) && $mother['date_of_birth'] != '0000-00-00') {
                                                    $age = date_diff(date_create($mother['date_of_birth']), date_create('today'))->y;
                                                    echo '<span class="fw-bold">' . $age . '</span> years';
                                                } else {
                                                    echo '<span class="text-muted">N/A</span>';
                                                }
                                                ?>
                                            </td>
                                            
                                            <!-- Contact Details Column -->
                                            <td>
                                                <?php if (!empty($mother['phone'])): ?>
                                                    <div class="d-flex align-items-center mb-1">
                                                        <i class="fas fa-phone text-success me-2 fa-sm"></i>
                                                        <span><?php echo htmlspecialchars($mother['phone']); ?></span>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if (!empty($mother['email'])): ?>
                                                    <div class="d-flex align-items-center">
                                                        <i class="fas fa-envelope text-primary me-2 fa-sm"></i>
                                                        <span class="small"><?php echo htmlspecialchars($mother['email']); ?></span>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if (empty($mother['phone']) && empty($mother['email'])): ?>
                                                    <span class="text-muted small">No contact info</span>
                                                <?php endif; ?>
                                            </td>
                                            
                                            <!-- Address Column -->
                                            <td>
                                                <?php if (!empty($mother['address'])): ?>
                                                    <span class="small" title="<?php echo htmlspecialchars($mother['address']); ?>">
                                                        <?php 
                                                        $address = htmlspecialchars($mother['address']);
                                                        echo strlen($address) > 30 ? substr($address, 0, 30) . '...' : $address;
                                                        ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted small">No address</span>
                                                <?php endif; ?>
                                            </td>
                                            
                                            <!-- Pregnancy Details Column -->
                                            <td>
                                                <?php if (!empty($mother['lmp']) && $mother['lmp'] != '0000-00-00'): ?>
                                                    <div class="mb-1">
                                                        <strong class="small">EDC:</strong>
                                                        <span class="small text-success fw-semibold">
                                                            <?php echo date('M j, Y', strtotime($mother['edc'])); ?>
                                                        </span>
                                                    </div>
                                                    <div class="small text-muted">
                                                        <strong>G:</strong><?php echo $mother['gravida']; ?> 
                                                        <strong class="ms-2">P:</strong><?php echo $mother['para']; ?>
                                                        <?php if (!empty($mother['lmp'])): ?>
                                                            <br><strong>LMP:</strong> <?php echo date('M j, Y', strtotime($mother['lmp'])); ?>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted small">No pregnancy data</span>
                                                <?php endif; ?>
                                            </td>
                                            
                                            <!-- Status Column -->
                                            <td>
                                                <?php
                                                // Determine status based on EDC
                                                if (!empty($mother['edc']) && $mother['edc'] != '0000-00-00') {
                                                    $edc = new DateTime($mother['edc']);
                                                    $today = new DateTime();
                                                    $days_diff = $today->diff($edc)->days;
                                                    
                                                    if ($edc < $today) {
                                                        echo '<span class="badge bg-warning status-badge">Delivered</span>';
                                                    } else if ($days_diff <= 30) {
                                                        echo '<span class="badge bg-danger status-badge">Due Soon</span>';
                                                    } else {
                                                        echo '<span class="badge bg-success status-badge">Pregnant</span>';
                                                    }
                                                } else {
                                                    echo '<span class="badge bg-secondary status-badge">Not Pregnant</span>';
                                                }
                                                ?>
                                            </td>
                                            
                                            <!-- Actions Column -->
                                            <td class="text-center action-buttons">
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <button class="btn btn-outline-info view-details" 
                                                            data-mother-id="<?php echo $mother['id']; ?>"
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#motherDetailsModal"
                                                            title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <a href="forms/mother_registration.php?edit=<?php echo $mother['id']; ?>" 
                                                       class="btn btn-outline-warning" title="Edit Mother">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    
                                                    <button class="btn btn-outline-danger" 
                                                            onclick="confirmDelete('mother', <?php echo $mother['id']; ?>, '<?php echo htmlspecialchars($mother['first_name'] . ' ' . $mother['last_name']); ?>')"
                                                            title="Delete Mother">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-4 text-muted">
                                                <i class="fas fa-female fa-2x mb-3 d-block"></i>
                                                <?php echo !empty($search) ? 'No mothers found matching your search.' : 'No mothers registered yet'; ?>
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

    <!-- Mother Details Modal -->
    <div class="modal fade mother-details-modal" id="motherDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Mother Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="motherDetailsContent">
                    <!-- Content will be loaded via AJAX -->
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading mother details...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="editMotherBtn">
                        <i class="fas fa-edit me-1"></i>Edit Information
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

        // View mother details
        document.addEventListener('DOMContentLoaded', function() {
            const viewButtons = document.querySelectorAll('.view-details');
            const modal = document.getElementById('motherDetailsModal');
            const content = document.getElementById('motherDetailsContent');
            const editBtn = document.getElementById('editMotherBtn');
            let currentMotherId = null;

            viewButtons.forEach(button => {
                button.addEventListener('click', function() {
                    currentMotherId = this.getAttribute('data-mother-id');
                    loadMotherDetails(currentMotherId);
                });
            });

            function loadMotherDetails(motherId) {
                fetch(`get_mother_details.php?id=${motherId}`)
                    .then(response => response.text())
                    .then(data => {
                        content.innerHTML = data;
                    })
                    .catch(error => {
                        content.innerHTML = `
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Failed to load mother details. Please try again.
                            </div>
                        `;
                    });
            }

            // Edit button handler
            editBtn.addEventListener('click', function() {
                if (currentMotherId) {
                    window.location.href = `forms/mother_registration.php?edit=${currentMotherId}`;
                }
            });

            // Reload details when modal is shown
            modal.addEventListener('show.bs.modal', function() {
                if (currentMotherId) {
                    loadMotherDetails(currentMotherId);
                }
            });
        });
    </script>
</body>
</html>