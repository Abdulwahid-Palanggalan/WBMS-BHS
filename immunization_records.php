<?php
// immunization_records.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

// Query to get babies and their latest immunization status
// We use a LEFT JOIN on a subquery to get the latest immunization date
$query = "
    SELECT 
        br.id as baby_id,
        br.first_name, 
        br.last_name, 
        br.birth_date, 
        br.gender,
        m.first_name as mother_first_name,
        m.last_name as mother_last_name,
        m.phone as mother_phone,
        MAX(ir.date_given) as last_vaccine_date,
        MAX(ir.next_dose_date) as next_due_date,
        COUNT(ir.id) as vaccine_count
    FROM birth_records br
    JOIN mothers m ON br.mother_id = m.id
    LEFT JOIN immunization_records ir ON br.id = ir.baby_id
";

$whereConditions = [];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(br.first_name LIKE :search OR br.last_name LIKE :search 
                          OR m.first_name LIKE :search OR m.last_name LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if (!empty($whereConditions)) {
    $query .= " WHERE " . implode(" AND ", $whereConditions);
}

$query .= " GROUP BY br.id, br.first_name, br.last_name, br.birth_date, br.gender, m.first_name, m.last_name, m.phone";
$query .= " ORDER BY br.birth_date DESC LIMIT :limit OFFSET :offset";

// Count for pagination
$countQuery = "SELECT COUNT(DISTINCT br.id) FROM birth_records br JOIN mothers m ON br.mother_id = m.id";
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

// Execute Main Query
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
    <title>Immunization Records - Kibenes eBirth</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .badge-vaccine {
            font-size: 0.8em;
            padding: 0.4em 0.8em;
        }
        .status-up-to-date {
            border-left: 4px solid #28a745;
        }
        .status-due {
            border-left: 4px solid #ffc107;
        }
        .status-overdue {
            border-left: 4px solid #dc3545;
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
                    <h1 class="h2">Immunization Records</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="forms/immunization_form.php" class="btn btn-primary">
                            <i class="fas fa-syringe me-2"></i>Record New Vaccine
                        </a>
                    </div>
                </div>

                <!-- Search -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <form method="GET" class="d-flex">
                            <input type="text" name="search" class="form-control me-2" placeholder="Search baby or mother..." value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                        </form>
                    </div>
                </div>

                <!-- Babies Table -->
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Baby Details</th>
                                        <th>Mother</th>
                                        <th>Age</th>
                                        <th>Last Vaccine</th>
                                        <th>Next Due</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($babies)): ?>
                                        <tr><td colspan="6" class="text-center py-4 text-muted">No records found.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($babies as $baby): 
                                            $dob = new DateTime($baby['birth_date']);
                                            $now = new DateTime();
                                            $age = $now->diff($dob);
                                            
                                            $ageString = "";
                                            if ($age->y > 0) $ageString .= $age->y . "y ";
                                            if ($age->m > 0) $ageString .= $age->m . "m ";
                                            if ($age->d > 0) $ageString .= $age->d . "d";
                                            if (empty($ageString)) $ageString = "Newborn";

                                            $rowClass = '';
                                            if ($baby['next_due_date']) {
                                                $dueDate = new DateTime($baby['next_due_date']);
                                                if ($dueDate < $now) $rowClass = 'status-overdue';
                                                elseif ($dueDate <= (clone $now)->modify('+7 days')) $rowClass = 'status-due';
                                                else $rowClass = 'status-up-to-date';
                                            }
                                        ?>
                                        <tr class="<?php echo $rowClass; ?>">
                                            <td>
                                                <div class="fw-bold"><?php echo htmlspecialchars($baby['first_name'] . ' ' . $baby['last_name']); ?></div>
                                                <div class="small text-muted">
                                                    <i class="<?php echo ($baby['gender'] == 'Male') ? 'fas fa-mars text-primary' : 'fas fa-venus text-danger'; ?>"></i>
                                                    <?php echo $baby['gender']; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div><?php echo htmlspecialchars($baby['mother_first_name'] . ' ' . $baby['mother_last_name']); ?></div>
                                                <div class="small text-muted"><i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($baby['mother_phone'] ?? 'N/A'); ?></div>
                                            </td>
                                            <td><?php echo $ageString; ?></td>
                                            <td>
                                                <?php if ($baby['last_vaccine_date']): ?>
                                                    <?php echo date('M j, Y', strtotime($baby['last_vaccine_date'])); ?>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">None</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($baby['next_due_date']): ?>
                                                    <span class="<?php echo ($rowClass == 'status-overdue') ? 'text-danger fw-bold' : ''; ?>">
                                                        <?php echo date('M j, Y', strtotime($baby['next_due_date'])); ?>
                                                    </span>
                                                    <?php if ($rowClass == 'status-overdue'): ?>
                                                        <i class="fas fa-exclamation-circle text-danger ms-1" title="Overdue"></i>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted small">--</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn btn-outline-info view-history" 
                                                            data-baby-id="<?php echo $baby['baby_id']; ?>" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#historyModal"
                                                            title="View History">
                                                        <i class="fas fa-list-alt"></i>
                                                    </button>
                                                    <a href="forms/immunization_form.php?baby_id=<?php echo $baby['baby_id']; ?>" class="btn btn-outline-primary" title="Add Record">
                                                        <i class="fas fa-plus"></i>
                                                    </a>
                                                    <a href="infant_growth_chart.php?baby_id=<?php echo $baby['baby_id']; ?>" class="btn btn-outline-success" title="Growth Chart">
                                                        <i class="fas fa-chart-line"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Search Pagination -->
                        <?php if ($totalPages > 1): ?>
                            <nav aria-label="Page navigation">
                                <ul class="pagination justify-content-center">
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- History Modal -->
    <div class="modal fade" id="historyModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Vaccination History</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="historyContent" class="text-center">
                        <div class="spinner-border text-primary" role="status"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const historyModal = document.getElementById('historyModal');
            historyModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const babyId = button.getAttribute('data-baby-id');
                const contentDiv = document.getElementById('historyContent');
                
                contentDiv.innerHTML = '<div class="text-center"><div class="spinner-border text-primary"></div><p class="mt-2">Loading...</p></div>';

                fetch('get_immunization_history.php?baby_id=' + babyId)
                    .then(response => response.text())
                    .then(html => {
                        contentDiv.innerHTML = html;
                    })
                    .catch(err => {
                        contentDiv.innerHTML = '<p class="text-danger">Error loading history.</p>';
                    });
            });
        });
    </script>
</body>
</html>
