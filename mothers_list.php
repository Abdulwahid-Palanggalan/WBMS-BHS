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
        .mother-details-modal .modal-content {
            border: none;
            border-radius: 2.5rem;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
        .mother-details-modal .modal-header {
            background: #fff;
            border-bottom: 1px solid #f1f5f9;
            padding: 1.5rem 2rem;
        }
        .mother-details-modal .modal-body {
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
                                    <span class="text-xs font-bold text-slate-800 uppercase tracking-widest">Maternal Records</span>
                                </div>
                            </li>
                        </ol>
                    </nav>
                    <h1 class="text-3xl font-black text-slate-800 tracking-tight">Registered Mothers</h1>
                    <p class="text-slate-500 font-medium mt-1">Directory of all registered maternal patients and their clinical profiles.</p>
                </div>
                <a href="forms/mother_registration.php" class="inline-flex items-center justify-center px-6 py-3.5 bg-health-600 hover:bg-health-700 text-white rounded-2xl font-bold text-sm transition-all hover:shadow-lg hover:shadow-health-200 active:scale-95 group">
                    <i class="fas fa-plus-circle mr-2 group-hover:rotate-90 transition-transform duration-500"></i>
                    Register New Mother
                </a>
            </div>

            <!-- Stats Overview -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white p-6 rounded-[2rem] border border-slate-100 shadow-sm flex items-center gap-5">
                    <div class="w-14 h-14 rounded-2xl bg-health-50 flex items-center justify-center text-health-600">
                        <i class="fas fa-user-friends text-2xl"></i>
                    </div>
                    <div>
                        <span class="text-xs font-bold text-slate-400 uppercase tracking-widest block">Total Registered</span>
                        <span class="text-2xl font-black text-slate-800"><?= number_format($totalMothers) ?></span>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-[2rem] border border-slate-100 shadow-sm flex items-center gap-5">
                    <div class="w-14 h-14 rounded-2xl bg-rose-50 flex items-center justify-center text-rose-600">
                        <i class="fas fa-female text-2xl"></i>
                    </div>
                    <div>
                        <span class="text-xs font-bold text-slate-400 uppercase tracking-widest block">Active Cases</span>
                        <span class="text-2xl font-black text-slate-800">Dynamic</span>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-[2rem] border border-slate-100 shadow-sm flex items-center gap-5">
                    <div class="w-14 h-14 rounded-2xl bg-indigo-50 flex items-center justify-center text-indigo-600">
                        <i class="fas fa-calendar-alt text-2xl"></i>
                    </div>
                    <div>
                        <span class="text-xs font-bold text-slate-400 uppercase tracking-widest block">Registrations</span>
                        <span class="text-2xl font-black text-slate-800"><?= date('M Y') ?></span>
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
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by name or contact..." class="bg-transparent border-none focus:ring-0 text-sm font-bold text-slate-800 w-full placeholder:text-slate-400">
                        </div>
                    </form>
                    <?php if (!empty($search)): ?>
                        <div class="flex items-center gap-3">
                            <span class="text-xs font-bold text-slate-400 uppercase tracking-widest">Active Search: <span class="text-health-600"><?= htmlspecialchars($search) ?></span></span>
                            <a href="mothers_list.php" class="text-[10px] font-black uppercase tracking-widest text-rose-500 hover:text-rose-600 bg-rose-50 px-3 py-1.5 rounded-lg transition-colors">Clear Search</a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Table Content -->
                <div class="table-responsive">
                    <table class="table modern-table mb-0">
                        <thead>
                            <tr>
                                <th>Mother Information</th>
                                <th>Contact Details</th>
                                <th>Pregnancy Score</th>
                                <th>LMP/EDC</th>
                                <th>Case Status</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($mothers)): ?>
                                <?php foreach ($mothers as $mother): ?>
                                    <tr class="hover:bg-slate-50/50 transition-colors group cursor-pointer" onclick="viewMotherDetails(<?= $mother['id'] ?>)">
                                        <td>
                                            <div class="flex items-center gap-3">
                                                <div class="w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center text-slate-400 group-hover:bg-health-100 group-hover:text-health-600 transition-colors">
                                                    <i class="fas fa-female text-sm"></i>
                                                </div>
                                                <div>
                                                    <div class="text-sm font-black text-slate-800 tracking-tight"><?= htmlspecialchars($mother['first_name'] . ' ' . $mother['last_name']) ?></div>
                                                    <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                                                        <?php 
                                                        if (!empty($mother['date_of_birth']) && $mother['date_of_birth'] != '0000-00-00') {
                                                            echo date_diff(date_create($mother['date_of_birth']), date_create('today'))->y . ' yrs old';
                                                        } else {
                                                            echo 'Age N/A';
                                                        }
                                                        ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="space-y-1">
                                                <div class="flex items-center gap-2 text-sm font-bold text-slate-800 tracking-tight">
                                                    <i class="fas fa-phone-alt text-[10px] text-health-500"></i>
                                                    <?= htmlspecialchars($mother['phone'] ?: 'N/A') ?>
                                                </div>
                                                <div class="text-[10px] font-bold text-slate-400 truncate max-w-[150px]">
                                                    <?= htmlspecialchars($mother['address'] ?: 'No address specified') ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="flex items-center gap-3">
                                                <div class="text-center">
                                                    <span class="text-[9px] font-black text-slate-400 uppercase tracking-tight block">G</span>
                                                    <span class="text-sm font-black text-slate-800"><?= $mother['gravida'] ?: '0' ?></span>
                                                </div>
                                                <div class="text-center">
                                                    <span class="text-[9px] font-black text-slate-400 uppercase tracking-tight block">P</span>
                                                    <span class="text-sm font-black text-slate-800"><?= $mother['para'] ?: '0' ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if (!empty($mother['lmp']) && $mother['lmp'] != '0000-00-00'): ?>
                                                <div class="space-y-1">
                                                    <div class="text-[10px] font-bold text-emerald-600 uppercase tracking-tight">EDC: <?= date('M j, Y', strtotime($mother['edc'])) ?></div>
                                                    <div class="text-[9px] font-bold text-slate-400">LMP: <?= date('M j, Y', strtotime($mother['lmp'])) ?></div>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-xs font-bold text-slate-300">NO ACTIVE PREGNANCY</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            if (!empty($mother['edc']) && $mother['edc'] != '0000-00-00') {
                                                $edc = new DateTime($mother['edc']);
                                                $today = new DateTime();
                                                $days_diff = $today->diff($edc)->days;
                                                
                                                if ($edc < $today) {
                                                    $statusClass = 'text-amber-600 bg-amber-50';
                                                    $statusText = 'Delivered';
                                                } else if ($days_diff <= 30) {
                                                    $statusClass = 'text-rose-600 bg-rose-50 animate-pulse';
                                                    $statusText = 'Due Soon';
                                                } else {
                                                    $statusClass = 'text-emerald-600 bg-emerald-50';
                                                    $statusText = 'Pregnant';
                                                }
                                            } else {
                                                $statusClass = 'text-slate-500 bg-slate-50';
                                                $statusText = 'Regular';
                                            }
                                            ?>
                                            <span class="inline-flex items-center px-3 py-1.5 rounded-xl text-[10px] font-black uppercase tracking-widest <?= $statusClass ?>">
                                                <?= $statusText ?>
                                            </span>
                                        </td>
                                        <td onclick="event.stopPropagation()">
                                            <div class="flex items-center justify-center gap-2">
                                                <button onclick="viewMotherDetails(<?= $mother['id'] ?>)" class="action-btn bg-health-50 text-health-600 hover:bg-health-600 hover:text-white" title="Clinical Profile">
                                                    <i class="fas fa-eye text-sm"></i>
                                                </button>
                                                <a href="forms/mother_registration.php?edit=<?= $mother['id'] ?>" class="action-btn bg-amber-50 text-amber-600 hover:bg-amber-600 hover:text-white" title="Edit Profile">
                                                    <i class="fas fa-edit text-sm"></i>
                                                </a>
                                                <button onclick="confirmDelete('mother', <?= $mother['id'] ?>, '<?= htmlspecialchars($mother['first_name'] . ' ' . $mother['last_name']) ?>')" class="action-btn bg-rose-50 text-rose-600 hover:bg-rose-600 hover:text-white" title="Delete Record">
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
                                                <i class="fas fa-user-slash text-4xl"></i>
                                            </div>
                                            <h3 class="text-lg font-black text-slate-800 uppercase tracking-tight">No Patients Found</h3>
                                            <p class="text-slate-400 font-medium max-w-xs mx-auto mt-2">Adjust your search or register a new mother to see them in this directory.</p>
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

    <!-- Mother Details Modal -->
    <div class="modal fade mother-details-modal" id="motherDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header flex items-center justify-between">
                    <div>
                        <h5 class="text-lg font-black text-slate-800 tracking-tight">Clinical Patient Profile</h5>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mt-0.5">Comprehensive Medical Record</p>
                    </div>
                    <button type="button" class="w-10 h-10 rounded-xl bg-slate-50 flex items-center justify-center text-slate-400 hover:bg-rose-50 hover:text-rose-500 transition-all border-none" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body" id="motherDetailsContent">
                    <div class="flex flex-col items-center justify-center py-20 text-center">
                        <div class="w-16 h-16 border-4 border-health-100 border-t-health-600 rounded-full animate-spin mb-4"></div>
                        <p class="text-sm font-black text-slate-800 uppercase tracking-widest">Retrieving Profile...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const motherModal = new bootstrap.Modal(document.getElementById('motherDetailsModal'));
        const modalContent = document.getElementById('motherDetailsContent');

        function viewMotherDetails(motherId) {
            // Close sidebar if open
            if (window.closeSidebar) window.closeSidebar();
            
            motherModal.show();
            modalContent.innerHTML = `
                <div class="flex flex-col items-center justify-center py-20 text-center">
                    <div class="w-16 h-16 border-4 border-health-100 border-t-health-600 rounded-full animate-spin mb-4"></div>
                    <p class="text-sm font-black text-slate-800 uppercase tracking-widest">Retrieving Profile...</p>
                </div>
            `;
            
            fetch(`get_mother_details.php?id=${motherId}`)
                .then(response => response.text())
                .then(data => {
                    modalContent.innerHTML = data;
                })
                .catch(error => {
                    modalContent.innerHTML = `
                        <div class="p-8 text-center text-rose-500">
                            <i class="fas fa-exclamation-triangle text-3xl mb-4"></i>
                            <p class="font-bold">Error loading profile details.</p>
                        </div>
                    `;
                });
        }

        function confirmDelete(type, id, name) {
            Swal.fire({
                title: '<span class="text-2xl font-black text-slate-800 uppercase tracking-tight">Confirm Deletion</span>',
                html: `<p class="text-slate-500 font-medium">Are you sure you want to permanently delete the clinical record for<br><strong class="text-slate-800">${name}</strong>?</p>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e11d48',
                cancelButtonColor: '#94a3b8',
                confirmButtonText: 'Delete Record',
                cancelButtonText: 'Keep Record',
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
