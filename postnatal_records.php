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
        .premium-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.07);
            border-radius: 2rem;
        }
        .modern-table thead th {
            background: #f8fafc;
            text-transform: uppercase;
            font-size: 0.65rem;
            font-weight: 800;
            letter-spacing: 0.05em;
            color: #64748b;
            border-top: none;
            padding: 1.25rem 1rem;
        }
        .modern-table tbody td {
            padding: 1.25rem 1rem;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
        }
        .action-btn {
            width: 38px;
            height: 38px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .search-input-group {
            background: #f1f5f9;
            border-radius: 1.25rem;
            padding: 0.5rem 1rem;
            border: 2px solid transparent;
            transition: all 0.3s;
        }
        .search-input-group:focus-within {
            background: white;
            border-color: #0ea5e9;
            box-shadow: 0 0 0 4px rgba(14, 165, 233, 0.1);
        }
        .postnatal-details-modal .modal-content {
            border: none;
            border-radius: 2.5rem;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
        .postnatal-details-modal .modal-header {
            background: #fff;
            border-bottom: 1px solid #f1f5f9;
            padding: 1.5rem 2rem;
        }
        .postnatal-details-modal .modal-body {
            padding: 2rem;
            background: #f8fafc;
        }
    </style>
</head>
<body class="bg-slate-50">
    <?php include_once $rootPath . '/includes/header.php'; ?>
    
    <div class="flex flex-col lg:flex-row min-h-[calc(100vh-4rem)]">
        <?php include_once $rootPath . '/includes/sidebar.php'; ?>
        
        <main class="flex-1 p-4 lg:p-8 space-y-8 no-print">
            <!-- Header Section -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div>
                    <nav class="flex mb-3" aria-label="Breadcrumb">
                        <ol class="inline-flex items-center space-x-1 md:space-x-3">
                            <li class="inline-flex items-center">
                                <a href="dashboards/admin.php" class="text-xs font-bold text-slate-400 hover:text-health-600 transition-colors uppercase tracking-widest">Dashboard</a>
                            </li>
                            <li>
                                <div class="flex items-center">
                                    <i class="fas fa-chevron-right text-[10px] text-slate-300 mx-2"></i>
                                    <span class="text-xs font-bold text-slate-800 uppercase tracking-widest">Postnatal Records</span>
                                </div>
                            </li>
                        </ol>
                    </nav>
                    <h1 class="text-3xl font-black text-slate-800 tracking-tight">Postnatal Care</h1>
                    <p class="text-slate-500 font-medium mt-1">Manage and track postpartum recovery and infant health.</p>
                </div>
                <a href="forms/postnatal_form.php" class="inline-flex items-center justify-center px-6 py-3.5 bg-health-600 hover:bg-health-700 text-white rounded-2xl font-bold text-sm transition-all hover:shadow-lg hover:shadow-health-200 active:scale-95 group">
                    <i class="fas fa-plus-circle mr-2 group-hover:rotate-90 transition-transform duration-500"></i>
                    New Postnatal Visit
                </a>
            </div>

            <!-- Stats Overview -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white p-6 rounded-[2rem] border border-slate-100 shadow-sm flex items-center gap-5">
                    <div class="w-14 h-14 rounded-2xl bg-health-50 flex items-center justify-center text-health-600">
                        <i class="fas fa-stethoscope text-2xl"></i>
                    </div>
                    <div>
                        <span class="text-xs font-bold text-slate-400 uppercase tracking-widest block">Total Records</span>
                        <span class="text-2xl font-black text-slate-800"><?= number_format($totalRecords) ?></span>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-[2rem] border border-slate-100 shadow-sm flex items-center gap-5">
                    <div class="w-14 h-14 rounded-2xl bg-rose-50 flex items-center justify-center text-rose-600">
                        <i class="fas fa-venus-mars text-2xl"></i>
                    </div>
                    <div>
                        <span class="text-xs font-bold text-slate-400 uppercase tracking-widest block">Infant Tracking</span>
                        <span class="text-2xl font-black text-slate-800">Active</span>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-[2rem] border border-slate-100 shadow-sm flex items-center gap-5">
                    <div class="w-14 h-14 rounded-2xl bg-indigo-50 flex items-center justify-center text-indigo-600">
                        <i class="fas fa-calendar-check text-2xl"></i>
                    </div>
                    <div>
                        <span class="text-xs font-bold text-slate-400 uppercase tracking-widest block">Today's Visits</span>
                        <span class="text-2xl font-black text-slate-800"><?= date('M j') ?></span>
                    </div>
                </div>
            </div>

            <!-- Content Card -->
            <div class="premium-card">
                <!-- Search and Filters -->
                <div class="p-6 border-bottom border-slate-100 flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <form method="GET" class="relative group w-full md:w-96">
                        <div class="search-input-group flex items-center">
                            <i class="fas fa-search text-slate-400 mr-3"></i>
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search mother or baby..." class="bg-transparent border-none focus:ring-0 text-sm font-bold text-slate-800 w-full placeholder:text-slate-400">
                        </div>
                    </form>
                    <?php if (!empty($search)): ?>
                        <div class="flex items-center gap-3">
                            <span class="text-xs font-bold text-slate-400 uppercase tracking-widest">Active Search: <span class="text-health-600"><?= htmlspecialchars($search) ?></span></span>
                            <a href="postnatal_records.php" class="text-[10px] font-black uppercase tracking-widest text-rose-500 hover:text-rose-600 bg-rose-50 px-3 py-1.5 rounded-lg transition-colors">Clear</a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Table Content -->
                <div class="table-responsive">
                    <table class="table modern-table mb-0">
                        <thead>
                            <tr>
                                <th>Clinical Visit</th>
                                <th>Mother Information</th>
                                <th>Infant Profile</th>
                                <th>Postpartum Age</th>
                                <th>Assessment</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($postnatalRecords)): ?>
                                <?php foreach ($postnatalRecords as $record): ?>
                                    <tr class="hover:bg-slate-50/50 transition-colors group cursor-pointer" onclick="viewPostnatalDetails(<?= $record['id'] ?>)">
                                        <td>
                                            <div class="flex items-center gap-3">
                                                <div class="w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center text-slate-400 group-hover:bg-health-100 group-hover:text-health-600 transition-colors">
                                                    <i class="fas fa-clipboard-check text-sm"></i>
                                                </div>
                                                <div>
                                                    <div class="text-sm font-black text-slate-800 tracking-tight"><?= date('M j, Y', strtotime($record['visit_date'])) ?></div>
                                                    <div class="text-[10px] font-black text-health-500 uppercase tracking-widest">Visit #<?= $record['visit_number'] ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="space-y-1">
                                                <div class="text-sm font-bold text-slate-800"><?= htmlspecialchars($record['mother_first_name'] . ' ' . $record['mother_last_name']) ?></div>
                                                <div class="flex items-center gap-2 text-[10px] font-bold text-slate-400">
                                                    <i class="fas fa-phone-alt text-[8px]"></i>
                                                    <?= htmlspecialchars($record['mother_phone'] ?: 'N/A') ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="flex items-center gap-2">
                                                <div class="w-2 h-2 rounded-full bg-<?= strtolower($record['gender']) == 'male' ? 'blue-500' : 'rose-500' ?>"></div>
                                                <div class="text-sm font-bold text-slate-800 whitespace-nowrap"><?= htmlspecialchars($record['baby_first_name'] . ' ' . $record['baby_last_name']) ?></div>
                                            </div>
                                            <div class="text-[10px] font-bold text-slate-400 mt-1 uppercase tracking-tight">Birth Wt: <?= $record['birth_weight'] ?> kg</div>
                                        </td>
                                        <td>
                                            <?php 
                                            $days = $record['days_after_birth'] ?? 0;
                                            $ageStatus = $days <= 7 ? 'text-rose-600 bg-rose-50' : ($days <= 30 ? 'text-amber-600 bg-amber-50' : 'text-emerald-600 bg-emerald-50');
                                            ?>
                                            <span class="inline-flex items-center px-3 py-1.5 rounded-xl text-[10px] font-black uppercase tracking-widest <?= $ageStatus ?>">
                                                <?= $days ?> Days Old
                                            </span>
                                        </td>
                                        <td>
                                            <div class="flex items-center gap-4">
                                                <div class="text-center">
                                                    <span class="text-[9px] font-black text-slate-400 uppercase tracking-tight block">Weight</span>
                                                    <span class="text-sm font-black text-slate-800"><?= $record['baby_weight'] ?> <small class="text-[10px] text-slate-400">kg</small></span>
                                                </div>
                                                <div class="text-center">
                                                    <span class="text-[9px] font-black text-slate-400 uppercase tracking-tight block">BP</span>
                                                    <span class="text-sm font-black text-slate-800"><?= $record['blood_pressure'] ?: '--/--' ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td onclick="event.stopPropagation()">
                                            <div class="flex items-center justify-center gap-2">
                                                <button onclick="viewPostnatalDetails(<?= $record['id'] ?>)" class="action-btn bg-health-50 text-health-600 hover:bg-health-600 hover:text-white" title="View Dashboard">
                                                    <i class="fas fa-eye text-sm"></i>
                                                </button>
                                                <a href="forms/postnatal_form.php?edit=<?= $record['id'] ?>" class="action-btn bg-amber-50 text-amber-600 hover:bg-amber-600 hover:text-white" title="Edit Visit">
                                                    <i class="fas fa-edit text-sm"></i>
                                                </a>
                                                <button onclick="confirmDelete('postnatal_record', <?= $record['id'] ?>, 'Postnatal visit for <?= htmlspecialchars($record['baby_first_name']) ?>')" class="action-btn bg-rose-50 text-rose-600 hover:bg-rose-600 hover:text-white" title="Delete Record">
                                                    <i class="fas fa-trash text-sm"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="py-20 text-center">
                                        <div class="flex flex-col items-center">
                                            <div class="w-20 h-20 bg-slate-50 rounded-[2.5rem] flex items-center justify-center text-slate-300 mb-6">
                                                <i class="fas fa-baby-carriage text-4xl"></i>
                                            </div>
                                            <h3 class="text-lg font-black text-slate-800 uppercase tracking-tight">No Records Found</h3>
                                            <p class="text-slate-400 font-medium max-w-xs mx-auto mt-2">We couldn't find any postnatal visits. Try adjusting your search or add a new record.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="p-6 border-t border-slate-100 flex items-center justify-center">
                        <nav class="flex items-center gap-1">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>" class="action-btn bg-slate-50 text-slate-600 hover:bg-health-600 hover:text-white">
                                    <i class="fas fa-chevron-left text-xs"></i>
                                </a>
                            <?php endif; ?>

                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);
                            for ($i = $startPage; $i <= $endPage; $i++): ?>
                                <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>" class="action-btn <?= $i == $page ? 'bg-health-600 text-white' : 'bg-slate-50 text-slate-600 hover:bg-slate-100' ?> font-black text-xs"><?= $i ?></a>
                            <?php endfor; ?>

                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>" class="action-btn bg-slate-50 text-slate-600 hover:bg-health-600 hover:text-white">
                                    <i class="fas fa-chevron-right text-xs"></i>
                                </a>
                            <?php endif; ?>
                        </nav>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Postnatal Details Modal -->
    <div class="modal fade postnatal-details-modal" id="postnatalDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header flex items-center justify-between">
                    <div>
                        <h5 class="text-lg font-black text-slate-800 tracking-tight">Clinical Record Profile</h5>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mt-0.5">Postnatal Care Assessment</p>
                    </div>
                    <button type="button" class="w-10 h-10 rounded-xl bg-slate-50 flex items-center justify-center text-slate-400 hover:bg-rose-50 hover:text-rose-500 transition-all border-none" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body" id="postnatalDetailsContent">
                    <div class="flex flex-col items-center justify-center py-20 text-center">
                        <div class="w-16 h-16 border-4 border-health-100 border-t-health-600 rounded-full animate-spin mb-4"></div>
                        <p class="text-sm font-black text-slate-800 uppercase tracking-widest">Retrieving Record...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const postnatalModal = new bootstrap.Modal(document.getElementById('postnatalDetailsModal'));
        const modalContent = document.getElementById('postnatalDetailsContent');

        function viewPostnatalDetails(recordId) {
            // Close sidebar if open (using global function from header)
            if (window.closeSidebar) window.closeSidebar();
            
            postnatalModal.show();
            // Show loader
            modalContent.innerHTML = `
                <div class="flex flex-col items-center justify-center py-20 text-center">
                    <div class="w-16 h-16 border-4 border-health-100 border-t-health-600 rounded-full animate-spin mb-4"></div>
                    <p class="text-sm font-black text-slate-800 uppercase tracking-widest">Retrieving Record...</p>
                </div>
            `;
            
            fetch(`get_postnatal_details.php?id=${recordId}`)
                .then(response => response.text())
                .then(data => {
                    modalContent.innerHTML = data;
                })
                .catch(error => {
                    modalContent.innerHTML = `
                        <div class="p-8 text-center">
                            <i class="fas fa-exclamation-triangle text-rose-500 text-3xl mb-4"></i>
                            <p class="text-slate-800 font-bold">Failed to load record details. Please try again.</p>
                        </div>
                    `;
                });
        }

        function confirmDelete(type, id, name) {
            Swal.fire({
                title: '<span class="text-2xl font-black text-slate-800 uppercase tracking-tight">Confirm Deletion</span>',
                html: `<p class="text-slate-500 font-medium">Are you sure you want to delete the record for<br><strong class="text-slate-800">${name}</strong>?</p>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e11d48',
                cancelButtonColor: '#94a3b8',
                confirmButtonText: 'Yes, Delete Record',
                cancelButtonText: 'No, Cancel',
                background: '#fff',
                borderRadius: '1.5rem',
                customClass: {
                    confirmButton: 'px-6 py-3 rounded-xl font-bold text-sm uppercase tracking-widest',
                    cancelButton: 'px-6 py-3 rounded-xl font-bold text-sm uppercase tracking-widest'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `delete.php?type=${type}&id=${id}`;
                }
            });
        }
    </script>
</body>
</html>
