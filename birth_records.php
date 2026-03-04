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
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Birth Registry - Kibenes eBirth</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include_once __DIR__ . '/includes/tailwind_config.php'; ?>
    <style type="text/tailwindcss">
        @layer components {
            .stat-chip {
                @apply bg-white border border-slate-100 rounded-[2rem] p-5 flex items-center gap-4 shadow-sm hover:shadow-md transition-all duration-300;
            }
            .stat-icon {
                @apply w-12 h-12 rounded-2xl flex items-center justify-center text-lg flex-shrink-0;
            }
            .table-th {
                @apply px-5 py-4 text-left text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] border-b border-slate-100;
            }
            .table-td {
                @apply px-5 py-4 border-b border-slate-50/80;
            }
            .pill-badge {
                @apply text-[9px] font-black px-2.5 py-1 rounded-full uppercase tracking-widest border;
            }
            .action-btn {
                @apply p-2.5 rounded-xl transition-all duration-200 shadow-sm active:scale-90 border;
            }
        }
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { @apply bg-slate-50; }
        ::-webkit-scrollbar-thumb { @apply bg-slate-200 rounded-full; }
        ::-webkit-scrollbar-thumb:hover { @apply bg-health-300; }
    </style>
</head>
<body class="bg-slate-50 min-h-full text-slate-900 font-sans antialiased">
    <?php include_once $rootPath . '/includes/header.php'; ?>
    
    <div class="flex flex-col lg:flex-row min-h-[calc(100vh-4rem)]">
        <?php include_once $rootPath . '/includes/sidebar.php'; ?>
        
        <main class="flex-1 p-4 lg:p-10 space-y-8 no-print">
            <!-- PAGE HEADER -->
            <header class="bg-white rounded-[2.5rem] border border-slate-100 shadow-sm p-8 flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div class="flex items-center gap-5">
                    <div class="w-14 h-14 rounded-[1.2rem] bg-gradient-to-br from-health-600 to-health-700 flex items-center justify-center text-white text-2xl shadow-lg shadow-health-100">
                        <i class="fas fa-baby"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-black text-slate-900 tracking-tight leading-tight">Birth Registry</h1>
                        <p class="text-sm text-slate-400 font-medium mt-0.5">Comprehensive newborn and delivery records</p>
                    </div>
                </div>
                <a href="forms/birth_registration.php"
                   class="inline-flex items-center gap-2.5 bg-health-600 hover:bg-health-700 text-white font-bold px-6 py-3.5 rounded-2xl transition-all shadow-lg shadow-health-100 active:scale-95 text-sm">
                    <i class="fas fa-plus text-xs"></i>
                    <span>Register New Birth</span>
                </a>
            </header>

            <!-- STAT CARDS -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                <div class="stat-chip">
                    <div class="stat-icon bg-health-50 text-health-600">
                        <i class="fas fa-id-card"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-0.5">Total Records</p>
                        <h3 class="text-2xl font-black text-slate-900"><?= number_format($totalBabies) ?></h3>
                    </div>
                </div>
                <div class="stat-chip">
                    <div class="stat-icon bg-sky-50 text-sky-500">
                        <i class="fas fa-mars-venus"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-0.5">Recent Deliveries</p>
                        <h3 class="text-2xl font-black text-slate-900"><?= count($babies) ?></h3>
                    </div>
                </div>
                <div class="stat-chip">
                    <div class="stat-icon bg-amber-50 text-amber-500">
                        <i class="fas fa-search"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-0.5">Search Results</p>
                        <h3 class="text-2xl font-black text-slate-900"><?= number_format($totalBabies) ?></h3>
                    </div>
                </div>
            </div>

            <!-- SEARCH BAR -->
            <div class="flex items-center gap-4">
                <form method="GET" class="relative flex-1 group/search">
                    <i class="fas fa-search absolute left-5 top-1/2 -translate-y-1/2 text-slate-300 group-focus-within/search:text-health-600 transition-colors duration-200 text-sm"></i>
                    <input type="text" name="search" value="<?= htmlspecialchars($search); ?>"
                           class="w-full bg-white border border-slate-200 rounded-2xl py-4 pl-12 pr-32 text-sm font-medium text-slate-800 focus:border-health-500 focus:ring-4 focus:ring-health-600/10 outline-none transition-all placeholder:text-slate-300 shadow-sm"
                           placeholder="Search by baby name or parents...">
                    <button type="submit" class="absolute right-2.5 top-2 bottom-2 bg-health-600 hover:bg-health-700 text-white px-5 rounded-xl font-bold text-xs transition-all">
                        Search
                    </button>
                </form>
                <?php if (!empty($search)): ?>
                <a href="birth_records.php" class="flex items-center gap-2 text-xs font-bold text-slate-400 hover:text-rose-500 transition-colors bg-white border border-slate-200 px-4 py-3 rounded-2xl shadow-sm">
                    <i class="fas fa-times"></i> Clear
                </a>
                <?php endif; ?>
            </div>

            <!-- BIRTH RECORDS TABLE -->
            <div class="bg-white rounded-[2.5rem] border border-slate-100 shadow-sm overflow-hidden">
                <div class="flex items-center justify-between px-8 py-5 border-b border-slate-50">
                    <div class="flex items-center gap-3">
                        <div class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></div>
                        <span class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">Newborn Registry Index</span>
                    </div>
                    <span class="text-[10px] font-black text-slate-300 uppercase tracking-widest"><?= $totalBabies ?> Records</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-slate-50/50">
                                <th class="table-th">Baby Information</th>
                                <th class="table-th">Gender</th>
                                <th class="table-th">Measurements</th>
                                <th class="table-th">Delivery</th>
                                <th class="table-th">Parents</th>
                                <th class="table-th text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($babies)): ?>
                                <?php foreach ($babies as $baby): ?>
                                <tr class="hover:bg-health-50/30 transition-colors duration-200 group">
                                    <td class="table-td">
                                        <div class="flex items-center gap-3">
                                            <div class="w-9 h-9 rounded-xl bg-health-600 text-white flex items-center justify-center text-xs font-black shadow-sm shadow-health-100 flex-shrink-0 group-hover:scale-105 transition-transform">
                                                <?= strtoupper(substr($baby['first_name'], 0, 1) . substr($baby['last_name'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <p class="text-sm font-bold text-slate-800 group-hover:text-health-700 transition-colors leading-tight">
                                                    <?php 
                                                    $babyName = htmlspecialchars($baby['first_name']);
                                                    if (!empty($baby['middle_name'])) $babyName .= ' ' . htmlspecialchars($baby['middle_name']);
                                                    $babyName .= ' ' . htmlspecialchars($baby['last_name']);
                                                    echo $babyName;
                                                    ?>
                                                </p>
                                                <div class="flex items-center gap-2 mt-1">
                                                    <span class="text-[10px] text-slate-400 font-medium">B-<?= $baby['id']; ?></span>
                                                    <span class="text-[10px] bg-slate-100 px-1.5 py-0.5 rounded text-slate-500 font-bold uppercase tracking-tighter">Order: <?= $baby['birth_order'] ?: 'N/A' ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="table-td">
                                        <?php if (!empty($baby['gender'])): ?>
                                            <?php $gColor = strtolower($baby['gender']) == 'male' ? 'bg-sky-50 text-sky-600 border-sky-100' : 'bg-rose-50 text-rose-600 border-rose-100'; ?>
                                            <span class="pill-badge <?= $gColor ?>">
                                                <i class="fas fa-<?php echo strtolower($baby['gender']) == 'male' ? 'mars' : 'venus'; ?> mr-1.5 text-[8px]"></i>
                                                <?= ucfirst($baby['gender']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-[10px] font-medium text-slate-300 italic">N/A</span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="table-td">
                                        <div class="space-y-1.5">
                                            <div class="flex items-center gap-1.5 text-xs">
                                                <span class="text-slate-400 font-bold w-4">W:</span>
                                                <span class="font-black text-slate-700"><?= $baby['birth_weight'] ?: '—'; ?></span>
                                                <span class="text-[9px] text-slate-400">kg</span>
                                            </div>
                                            <div class="flex items-center gap-1.5 text-xs">
                                                <span class="text-slate-400 font-bold w-4">L:</span>
                                                <span class="font-black text-slate-700"><?= $baby['birth_length'] ?: '—'; ?></span>
                                                <span class="text-[9px] text-slate-400">cm</span>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="table-td">
                                        <div class="flex flex-col gap-1">
                                            <span class="text-[10px] font-black text-health-600 uppercase tracking-tight"><?= htmlspecialchars($baby['delivery_type'] ?: 'Unknown'); ?></span>
                                            <span class="text-[10px] font-bold text-slate-400"><?= htmlspecialchars($baby['birth_attendant'] ?: 'Unspecified'); ?></span>
                                        </div>
                                    </td>

                                    <td class="table-td">
                                        <div class="flex flex-col gap-1.5">
                                            <div class="flex items-center gap-2">
                                                <span class="w-1 h-3 bg-emerald-400 rounded-full"></span>
                                                <span class="text-[10px] font-bold text-slate-700 truncate max-w-[120px]">
                                                    <?php 
                                                    $motherName = htmlspecialchars($baby['mother_first_name'] ?? '');
                                                    $motherName .= ' ' . htmlspecialchars($baby['mother_last_name'] ?? '');
                                                    echo !empty(trim($motherName)) ? $motherName : 'Mother N/A';
                                                    ?>
                                                </span>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <span class="w-1 h-3 bg-sky-400 rounded-full"></span>
                                                <span class="text-[10px] font-bold text-slate-700 truncate max-w-[120px]">
                                                    <?php 
                                                    $fatherName = htmlspecialchars($baby['father_first_name'] ?? '');
                                                    $fatherName .= ' ' . htmlspecialchars($baby['father_last_name'] ?? '');
                                                    echo !empty(trim($fatherName)) ? $fatherName : 'Father N/A';
                                                    ?>
                                                </span>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="table-td">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <button class="action-btn bg-sky-50 hover:bg-sky-100 text-sky-600 border-sky-100 view-details" 
                                                    data-baby-id="<?php echo $baby['id']; ?>"
                                                    title="View Full Profile">
                                                <i class="fas fa-eye text-xs"></i>
                                            </button>
                                            
                                            <?php if (in_array($_SESSION['role'], ['admin', 'midwife'])): ?>
                                            <a href="forms/birth_registration.php?edit=<?php echo $baby['id']; ?>" 
                                               class="action-btn bg-amber-50 hover:bg-amber-100 text-amber-600 border-amber-100" title="Edit Record">
                                                <i class="fas fa-edit text-xs"></i>
                                            </a>
                                            <button class="action-btn bg-rose-50 hover:bg-rose-100 text-rose-500 border-rose-100 delete-baby" 
                                                    data-baby-id="<?php echo $baby['id']; ?>"
                                                    data-baby-name="<?php echo htmlspecialchars($baby['first_name'] . ' ' . $baby['last_name']); ?>"
                                                    title="Remove Record">
                                                <i class="fas fa-trash text-xs"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="py-24 text-center">
                                        <div class="flex flex-col items-center gap-3 opacity-40">
                                            <div class="w-16 h-16 rounded-3xl bg-slate-100 flex items-center justify-center">
                                                <i class="fas fa-folder-open text-2xl text-slate-400"></i>
                                            </div>
                                            <div>
                                                <p class="text-sm font-black text-slate-400 uppercase tracking-widest">No Birth Records Found</p>
                                                <?php if (!empty($search)): ?>
                                                <p class="text-xs text-slate-300 mt-1">Try a different search term</p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- PAGINATION -->
                <?php if ($totalPages > 1): ?>
                <div class="px-8 py-5 border-t border-slate-50 flex items-center justify-between bg-slate-50/30">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">
                        Page <?= $page; ?> of <?= $totalPages; ?>
                    </p>
                    <div class="flex gap-2 text-xs font-black">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1; ?>&search=<?= urlencode($search); ?>"
                               class="flex items-center gap-2 bg-white border border-slate-200 text-slate-600 px-4 py-2 rounded-xl uppercase tracking-widest hover:bg-slate-50 transition-all shadow-sm">
                                <i class="fas fa-chevron-left"></i> Prev
                            </a>
                        <?php endif; ?>
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?= $page + 1; ?>&search=<?= urlencode($search); ?>"
                               class="flex items-center gap-2 bg-health-600 text-white px-4 py-2 rounded-xl uppercase tracking-widest hover:bg-health-700 transition-all shadow-sm">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- BIRTH DETAILS MODAL -->
    <div id="birthDetailsModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
        <div class="bg-white rounded-[2.5rem] shadow-2xl w-full max-w-4xl max-h-[92vh] overflow-hidden flex flex-col animate-in zoom-in-95 duration-300">
            <!-- Modal Header -->
            <div class="flex items-center justify-between px-8 py-6 border-b border-slate-100 bg-slate-50/50 flex-shrink-0">
                <div class="flex items-center gap-4">
                    <div class="w-11 h-11 rounded-2xl bg-health-50 text-health-600 flex items-center justify-center text-lg">
                        <i class="fas fa-certificate"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-black text-slate-900 tracking-tight">Birth Record Profile</h3>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-0.5">Certified Delivery Statement</p>
                    </div>
                </div>
                <button onclick="closeDetailsModal()"
                        class="w-9 h-9 rounded-xl bg-white border border-slate-200 flex items-center justify-center text-slate-400 hover:text-rose-500 hover:border-rose-200 transition-all active:scale-90">
                    <i class="fas fa-times text-sm"></i>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="flex-1 overflow-y-auto p-8" id="birthDetailsContent">
                <div class="flex flex-col items-center justify-center py-20 opacity-40">
                    <div class="w-10 h-10 border-[3px] border-health-100 border-t-health-600 rounded-full animate-spin"></div>
                    <p class="mt-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Compiling Records...</p>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="px-8 py-5 bg-slate-50/50 border-t border-slate-100 flex justify-end gap-3 flex-shrink-0">
                <button onclick="closeDetailsModal()"
                        class="px-6 py-3 rounded-2xl text-slate-400 font-bold text-sm hover:bg-white hover:text-slate-600 transition-all">
                    Close
                </button>
                <?php if (in_array($_SESSION['role'], ['admin', 'midwife'])): ?>
                <button id="editBirthBtn"
                        class="bg-health-600 hover:bg-health-700 text-white font-bold px-8 py-3 rounded-2xl transition-all text-sm shadow-lg shadow-health-100 active:scale-95">
                    <i class="fas fa-pen mr-2 text-xs"></i>Edit Record
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let currentBabyId = null;

            // View Details Handler
            document.querySelectorAll('.view-details').forEach(button => {
                button.addEventListener('click', function() {
                    currentBabyId = this.getAttribute('data-baby-id');
                    openDetailsModal(currentBabyId);
                });
            });

            function openDetailsModal(babyId) {
                const modal = document.getElementById('birthDetailsModal');
                const content = document.getElementById('birthDetailsContent');

                if (window.closeSidebar) window.closeSidebar();
                
                modal.classList.remove('hidden');
                modal.classList.add('flex');
                document.body.style.overflow = 'hidden';

                content.innerHTML = `
                    <div class="flex flex-col items-center justify-center py-20 opacity-40">
                        <div class="w-10 h-10 border-[3px] border-health-100 border-t-health-600 rounded-full animate-spin"></div>
                        <p class="mt-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Accessing Registry...</p>
                    </div>
                `;

                fetch(`get_birth_details.php?id=${babyId}`)
                    .then(r => r.text())
                    .then(data => {
                        content.innerHTML = data;
                    })
                    .catch(() => {
                        content.innerHTML = `
                            <div class="bg-rose-50 border border-rose-100 p-8 rounded-[2rem] text-center">
                                <p class="font-black text-rose-800 text-sm uppercase tracking-widest">Sync Error</p>
                                <p class="text-xs text-rose-600 mt-2 font-medium">Failed to retrieve birth profile. Please try again.</p>
                            </div>
                        `;
                    });
            }

            window.closeDetailsModal = function() {
                const modal = document.getElementById('birthDetailsModal');
                const sidebar = document.querySelector('.sidebar');
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                document.body.style.overflow = '';
                
                if (sidebar) sidebar.classList.remove('sidebar-force-hide');
            };

            // Edit button handler
            const editBtn = document.getElementById('editBirthBtn');
            if (editBtn) {
                editBtn.addEventListener('click', () => {
                    if (currentBabyId) window.location.href = `forms/birth_registration.php?edit=${currentBabyId}`;
                });
            }

            // Backdrop and ESC handlers
            const modalEl = document.getElementById('birthDetailsModal');
            modalEl.addEventListener('click', e => { if (e.target === modalEl) closeDetailsModal(); });
            document.addEventListener('keydown', e => { if (e.key === 'Escape') closeDetailsModal(); });

            // Delete functionality
            document.querySelectorAll('.delete-baby').forEach(button => {
                button.addEventListener('click', function() {
                    const babyId = this.getAttribute('data-baby-id');
                    const babyName = this.getAttribute('data-baby-name');
                    
                    Swal.fire({
                        title: 'Delete Record?',
                        html: `This will permanently remove<br><b class="text-health-700">${babyName}</b><br><small class="text-slate-400 mt-2 block">Related postnatal data will also be purged.</small>`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#f43f5e',
                        cancelButtonColor: '#64748b',
                        confirmButtonText: 'Yes, Delete',
                        cancelButtonText: 'Cancel',
                        showLoaderOnConfirm: true,
                        preConfirm: () => {
                            return fetch(`?delete=${babyId}`)
                                .then(response => {
                                    if (!response.ok) throw new Error('Delete request failed');
                                    return response;
                                })
                                .catch(error => Swal.showValidationMessage(`Error: ${error}`));
                        },
                        allowOutsideClick: () => !Swal.isLoading(),
                        customClass: {
                            popup: 'rounded-[2rem]',
                            confirmButton: 'rounded-xl font-bold px-6 py-3',
                            cancelButton: 'rounded-xl font-bold px-6 py-3'
                        }
                    }).then((result) => {
                        if (result.isConfirmed) {
                            Swal.fire({
                                title: 'Purged!',
                                text: 'The record has been removed from registry.',
                                icon: 'success',
                                timer: 1500,
                                showConfirmButton: false,
                                customClass: { popup: 'rounded-[2rem]' }
                            }).then(() => window.location.reload());
                        }
                    });
                });
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
