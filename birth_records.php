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

// Handle delete action - UPDATED FOR MIDWIFE ACCESS
if (isset($_GET['delete']) && !empty($_GET['delete']) && in_array($_SESSION['role'], ['admin', 'midwife'])) {
    $babyId = intval($_GET['delete']);
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // First, delete related records to maintain referential integrity
        // Delete from postnatal_records if exists
        $stmt = $pdo->prepare("DELETE FROM postnatal_records WHERE baby_id = ?");
        $stmt->execute([$babyId]);
        
        // Delete from birth_informants if exists
        $stmt = $pdo->prepare("DELETE FROM birth_informants WHERE birth_record_id = ?");
        $stmt->execute([$babyId]);
        
        // Now delete the birth record
        $stmt = $pdo->prepare("DELETE FROM birth_records WHERE id = ?");
        $stmt->execute([$babyId]);
        
        $pdo->commit();
        
        // Log the activity
        logActivity($_SESSION['user_id'], "Deleted birth record ID: $babyId (by " . $_SESSION['role'] . ")");
        
        // Redirect back with success message
        $_SESSION['success_message'] = "Birth record deleted successfully!";
        header("Location: " . str_replace('?delete=' . $babyId, '', $_SERVER['REQUEST_URI']));
        exit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "Error deleting birth record: " . $e->getMessage();
        header("Location: " . str_replace('?delete=' . $babyId, '', $_SERVER['REQUEST_URI']));
        exit();
    }
}

// Handle search and pagination
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Build query based on your database structure
$query = "SELECT br.*, 
                 m.first_name as mother_first_name, 
                 m.middle_name as mother_middle_name, 
                 m.last_name as mother_last_name,
                 m.phone as mother_phone,
                 hp.first_name as father_first_name,
                 hp.middle_name as father_middle_name, 
                 hp.last_name as father_last_name,
                 hp.phone as father_phone
          FROM birth_records br 
          LEFT JOIN mothers m ON br.mother_id = m.id 
          LEFT JOIN husband_partners hp ON m.id = hp.mother_id";

$countQuery = "SELECT COUNT(*) FROM birth_records br 
               LEFT JOIN mothers m ON br.mother_id = m.id 
               LEFT JOIN husband_partners hp ON m.id = hp.mother_id";

$whereConditions = [];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(br.first_name LIKE :search OR br.last_name LIKE :search 
                         OR m.first_name LIKE :search OR m.last_name LIKE :search
                         OR hp.first_name LIKE :search OR hp.last_name LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if (!empty($whereConditions)) {
    $whereClause = " WHERE " . implode(" AND ", $whereConditions);
    $query .= $whereClause;
    $countQuery .= $whereClause;
}

$query .= " ORDER BY br.birth_date DESC, br.created_at DESC LIMIT :limit OFFSET :offset";

// Get total count
$stmt = $pdo->prepare($countQuery);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$totalBabies = $stmt->fetchColumn();
$totalPages = ceil($totalBabies / $limit);

// Get babies
$stmt = $pdo->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$babies = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Birth Records - Kibenes eBirth</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .birth-details-modal .modal-lg {
            max-width: 1000px;
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
        .baby-info {
            background: linear-gradient(135deg, #e8f5e8 0%, #f0f8ff 100%);
            border-left: 4px solid #28a745;
        }
        .parents-info {
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
            vertical-align: middle;
        }
        .status-badge {
            font-size: 0.75em;
            padding: 0.35em 0.65em;
        }
        .action-buttons {
            min-width: 140px;
        }
        .gender-badge {
            font-size: 0.8em;
            padding: 0.4em 0.8em;
        }
        .status-pending { background-color: #ffc107; color: #000; }
        .status-approved { background-color: #28a745; color: #fff; }
        .status-rejected { background-color: #dc3545; color: #fff; }
        .alert-auto-close {
            animation: fadeOut 5s forwards;
        }
        @keyframes fadeOut {
            0% { opacity: 1; }
            80% { opacity: 1; }
            100% { opacity: 0; display: none; }
        }
    </style>
</head>
<body>
    <?php include_once $rootPath . '/includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include_once $rootPath . '/includes/sidebar.php'; ?>
            
            <main class="main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">All Birth Records</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="forms/birth_registration.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Register New Birth
                        </a>
                    </div>
                </div>

                <!-- Display Messages -->
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show alert-auto-close" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo $_SESSION['success_message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo $_SESSION['error_message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>

                <!-- Search and Stats -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <form method="GET" class="d-flex">
                            <input type="text" name="search" class="form-control me-2" placeholder="Search by baby name or parents..." value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i>
                            </button>
                            <?php if (!empty($search)): ?>
                                <a href="?" class="btn btn-outline-secondary ms-2">Clear</a>
                            <?php endif; ?>
                        </form>
                    </div>
                    <div class="col-md-6 text-end">
                        <div class="text-muted">
                            Total: <strong><?php echo $totalBabies; ?></strong> birth records found
                            <?php if (!empty($search)): ?>
                                <span class="badge bg-info">Search Active</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Birth Records Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th width="20%">Baby Information</th>
                                        <th width="8%">Gender</th>
                                        <th width="15%">Birth Details</th>
                                        <th width="15%">Delivery Information</th>
                                        <th width="22%">Parents Information</th>
                                        <th width="10%" class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($babies)): ?>
                                        <?php foreach ($babies as $baby): ?>
                                        <tr>
                                            <!-- Baby Information Column -->
                                            <td>
                                                <div class="fw-semibold text-primary">
                                                    <?php 
                                                    $babyName = htmlspecialchars($baby['first_name']);
                                                    if (!empty($baby['middle_name'])) {
                                                        $babyName .= ' ' . htmlspecialchars($baby['middle_name']);
                                                    }
                                                    $babyName .= ' ' . htmlspecialchars($baby['last_name']);
                                                    echo $babyName;
                                                    ?>
                                                </div>
                                                <div class="small text-muted mt-1">
                                                    <i class="fas fa-sort-numeric-up-alt me-1"></i>
                                                    Birth Order: <?php echo !empty($baby['birth_order']) ? htmlspecialchars($baby['birth_order']) : 'N/A'; ?>
                                                </div>
                                                <?php if (!empty($baby['birth_date']) && $baby['birth_date'] != '0000-00-00'): ?>
                                                    <div class="small text-muted">
                                                        <i class="fas fa-calendar-day me-1"></i>
                                                        <?php echo date('M j, Y', strtotime($baby['birth_date'])); ?>
                                                        <?php if (!empty($baby['birth_time'])): ?>
                                                            <span class="ms-1">• <?php echo date('g:i A', strtotime($baby['birth_time'])); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            
                                            <!-- Gender Column -->
                                            <td>
                                                <?php if (!empty($baby['gender'])): ?>
                                                    <span class="badge gender-badge bg-<?php echo strtolower($baby['gender']) == 'male' ? 'primary' : 'danger'; ?>">
                                                        <i class="fas fa-<?php echo strtolower($baby['gender']) == 'male' ? 'mars' : 'venus'; ?> me-1"></i>
                                                        <?php echo ucfirst($baby['gender']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted small">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            
                                            <!-- Birth Details Column -->
                                            <td>
                                                <?php if (!empty($baby['birth_weight'])): ?>
                                                    <div class="mb-1">
                                                        <strong class="small">Weight:</strong>
                                                        <span class="small fw-semibold text-success"><?php echo $baby['birth_weight']; ?> kg</span>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if (!empty($baby['birth_length'])): ?>
                                                    <div class="mb-1">
                                                        <strong class="small">Length:</strong>
                                                        <span class="small"><?php echo $baby['birth_length']; ?> cm</span>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if (empty($baby['birth_weight']) && empty($baby['birth_length'])): ?>
                                                    <span class="text-muted small">No measurements</span>
                                                <?php endif; ?>
                                            </td>
                                            
                                            <!-- Delivery Information Column -->
                                            <td>
                                                <div class="small">
                                                    <?php if (!empty($baby['delivery_type'])): ?>
                                                        <div class="mb-1">
                                                            <strong>Type:</strong> 
                                                            <span class="text-primary"><?php echo htmlspecialchars($baby['delivery_type']); ?></span>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($baby['type_of_birth'])): ?>
                                                        <div class="mb-1">
                                                            <strong>Birth Type:</strong> 
                                                            <?php echo htmlspecialchars($baby['type_of_birth']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($baby['birth_attendant'])): ?>
                                                        <div>
                                                            <strong>Attendant:</strong> 
                                                            <span class="text-success"><?php echo htmlspecialchars($baby['birth_attendant']); ?></span>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            
                                            <!-- Parents Information Column -->
                                            <td>
                                                <div class="small">
                                                    <div class="mb-2">
                                                        <strong class="text-success">Mother:</strong><br>
                                                        <?php 
                                                        $motherName = htmlspecialchars($baby['mother_first_name'] ?? '');
                                                        if (!empty($baby['mother_middle_name'])) {
                                                            $motherName .= ' ' . htmlspecialchars($baby['mother_middle_name']);
                                                        }
                                                        $motherName .= ' ' . htmlspecialchars($baby['mother_last_name'] ?? '');
                                                        echo !empty(trim($motherName)) ? $motherName : '<span class="text-muted">Not specified</span>';
                                                        ?>
                                                    </div>
                                                    <div>
                                                        <strong class="text-primary">Father:</strong><br>
                                                        <?php 
                                                        $fatherName = htmlspecialchars($baby['father_first_name'] ?? '');
                                                        if (!empty($baby['father_middle_name'])) {
                                                            $fatherName .= ' ' . htmlspecialchars($baby['father_middle_name']);
                                                        }
                                                        $fatherName .= ' ' . htmlspecialchars($baby['father_last_name'] ?? '');
                                                        echo !empty(trim($fatherName)) ? $fatherName : '<span class="text-muted">Not specified</span>';
                                                        ?>
                                                    </div>
                                                </div>
                                            </td>
                                            
                                            <!-- Status Column -->
                                            <!-- <td>
                                                <?php
                                                // Registration Status
                                                // if (!empty($baby['status'])) {
                                                //     $statusClass = 'status-' . $baby['status'];
                                                //     echo '<span class="badge ' . $statusClass . ' status-badge">' . ucfirst($baby['status']) . '</span>';
                                                // } else {
                                                //     echo '<span class="badge bg-secondary status-badge">Unknown</span>';
                                                // }
                                                ?>
                                            </td> -->
                                            
                                            <!-- Actions Column - UPDATED FOR MIDWIFE DELETE ACCESS -->
                                            <td class="text-center action-buttons">
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <!-- View Button - For everyone -->
                                                    <button class="btn btn-outline-info view-details" 
                                                            data-baby-id="<?php echo $baby['id']; ?>"
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#birthDetailsModal"
                                                            title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    
                                                    <!-- Edit Button - For admin and midwife -->
                                                    <?php if (in_array($_SESSION['role'], ['admin', 'midwife'])): ?>
                                                    <a href="forms/birth_registration.php?edit=<?php echo $baby['id']; ?>" 
                                                       class="btn btn-outline-warning" title="Edit Record">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <?php endif; ?>
                                                    
                                                    <!-- Delete Button - For admin and midwife -->
                                                    <?php if (in_array($_SESSION['role'], ['admin', 'midwife'])): ?>
                                                    <button class="btn btn-outline-danger delete-baby" 
                                                            data-baby-id="<?php echo $baby['id']; ?>"
                                                            data-baby-name="<?php echo htmlspecialchars($baby['first_name'] . ' ' . $baby['last_name']); ?>"
                                                            title="Delete Record">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-4 text-muted">
                                                <i class="fas fa-baby fa-2x mb-3 d-block"></i>
                                                <?php echo !empty($search) ? 'No birth records found matching your search.' : 'No birth records yet'; ?>
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

    <!-- Birth Details Modal -->
    <div class="modal fade birth-details-modal" id="birthDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Birth Record Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="birthDetailsContent">
                    <!-- Content will be loaded via AJAX -->
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading birth record details...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <?php if (in_array($_SESSION['role'], ['admin', 'midwife'])): ?>
                    <button type="button" class="btn btn-primary" id="editBirthBtn">
                        <i class="fas fa-edit me-1"></i>Edit Record
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Delete functionality
        document.addEventListener('DOMContentLoaded', function() {
            const deleteButtons = document.querySelectorAll('.delete-baby');
            
            deleteButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const babyId = this.getAttribute('data-baby-id');
                    const babyName = this.getAttribute('data-baby-name');
                    
                    Swal.fire({
                        title: 'Are you sure?',
                        html: `You are about to delete birth record for <strong>${babyName}</strong>. This action cannot be undone and will also delete related postnatal records.`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#3085d6',
                        confirmButtonText: 'Yes, delete it!',
                        cancelButtonText: 'Cancel',
                        showLoaderOnConfirm: true,
                        preConfirm: () => {
                            return fetch(`?delete=${babyId}`)
                                .then(response => {
                                    if (!response.ok) {
                                        throw new Error('Network response was not ok');
                                    }
                                    return response;
                                })
                                .catch(error => {
                                    Swal.showValidationMessage(`Request failed: ${error}`);
                                });
                        },
                        allowOutsideClick: () => !Swal.isLoading()
                    }).then((result) => {
                        if (result.isConfirmed) {
                            Swal.fire({
                                title: 'Deleted!',
                                text: 'Birth record has been deleted.',
                                icon: 'success',
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => {
                                window.location.reload();
                            });
                        }
                    });
                });
            });

            // View birth details
            const viewButtons = document.querySelectorAll('.view-details');
            const modal = document.getElementById('birthDetailsModal');
            const content = document.getElementById('birthDetailsContent');
            const editBtn = document.getElementById('editBirthBtn');
            let currentBabyId = null;

            viewButtons.forEach(button => {
                button.addEventListener('click', function() {
                    currentBabyId = this.getAttribute('data-baby-id');
                    loadBirthDetails(currentBabyId);
                });
            });

            function loadBirthDetails(babyId) {
                fetch(`get_birth_details.php?id=${babyId}`)
                    .then(response => response.text())
                    .then(data => {
                        content.innerHTML = data;
                    })
                    .catch(error => {
                        content.innerHTML = `
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Failed to load birth record details. Please try again.
                            </div>
                        `;
                    });
            }

            // Edit button handler
            if (editBtn) {
                editBtn.addEventListener('click', function() {
                    if (currentBabyId) {
                        window.location.href = `forms/birth_registration.php?edit=${currentBabyId}`;
                    }
                });
            }

            // Reload details when modal is shown
            modal.addEventListener('show.bs.modal', function() {
                if (currentBabyId) {
                    loadBirthDetails(currentBabyId);
                }
            });

            // Auto-close success alerts
            const autoCloseAlerts = document.querySelectorAll('.alert-auto-close');
            autoCloseAlerts.forEach(alert => {
                setTimeout(() => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
        });
    </script>
</body>
</html>