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
    <title>Postnatal Registry - Kibenes eBirth</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include_once $rootPath . '/includes/tailwind_config.php'; ?>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap');
        body { font-family: 'Inter', sans-serif; }
        
        .table-modern thead th {
            @apply px-6 py-4 text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 border-b border-slate-50 bg-slate-50/50;
        }
        .table-modern tbody tr {
            @apply border-b border-slate-50 hover:bg-health-50/30 transition-all duration-300;
        }
        .table-modern td {
            @apply px-6 py-5 align-middle;
        }
        .card-premium {
            @apply bg-white border-2 border-slate-50 rounded-[2.5rem] shadow-sm hover:shadow-xl hover:shadow-health-500/5 transition-all duration-500 overflow-hidden;
        }
        .form-input-premium {
            @apply w-full bg-slate-50 border-2 border-transparent rounded-2xl px-6 py-4 text-sm font-medium transition-all duration-300 focus:bg-white focus:border-health-500 focus:outline-none focus:ring-4 focus:ring-health-500/10;
        }
        .modal-premium {
            @apply bg-white/95 backdrop-blur-xl rounded-[3rem] border-2 border-slate-50 shadow-2xl;
        }
    </style>
</head>
<body class="bg-[#FBFBFE] text-slate-900 antialiased">
    <?php include_once $rootPath . '/includes/header.php'; ?>
    
    <div class="flex flex-col lg:flex-row min-h-[calc(100vh-4rem)]">
        <?php include_once $rootPath . '/includes/sidebar.php'; ?>
        
        <main class="flex-1 p-4 lg:p-12 space-y-10 no-print">
            <!-- PREMIUM CLINICAL HEADER -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div class="space-y-2">
                    <div class="flex items-center gap-3">
                        <span class="inline-block px-4 py-1.5 bg-rose-50 text-rose-600 text-[10px] font-black uppercase tracking-widest rounded-full border border-rose-100 italic">Postnatal Care Registry</span>
                    </div>
                    <h1 class="text-4xl md:text-5xl font-black text-slate-900 tracking-tight leading-tight">Newborn & <br><span class="text-health-600">Recovery Care</span></h1>
                    <p class="text-slate-400 text-sm font-medium max-w-md italic font-serif">Monitoring the health of newborns and the recovery progress of mothers in the vital postpartum period.</p>
                </div>
                
                <div class="flex items-center gap-4">
                    <a href="forms/postnatal_form.php" class="group flex items-center gap-3 bg-slate-900 text-white px-8 py-5 rounded-[2rem] hover:bg-slate-800 transition-all duration-500 shadow-lg shadow-slate-900/20 active:scale-95">
                        <span class="text-sm font-black uppercase tracking-widest">Register Visit</span>
                        <div class="w-8 h-8 rounded-xl bg-white/10 flex items-center justify-center group-hover:rotate-90 transition-transform">
                            <i class="fas fa-plus text-xs"></i>
                        </div>
                    </a>
                </div>
            </div>

            <!-- SEARCH & FILTERS -->
            <div class="bg-white border-2 border-slate-50 rounded-[2.5rem] p-4 shadow-sm">
                <form method="GET" class="flex flex-col md:flex-row items-center gap-4">
                    <div class="relative flex-1 group">
                        <i class="fas fa-search absolute left-6 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-health-500 transition-colors"></i>
                        <input type="text" name="search" placeholder="Search by mother or baby name..." value="<?= htmlspecialchars($search); ?>" class="form-input-premium pl-14">
                    </div>
                    <button type="submit" class="bg-health-500 text-white px-8 py-4 rounded-2xl font-black text-xs uppercase tracking-[0.2em] hover:bg-health-600 transition-all active:scale-95 shadow-lg shadow-health-500/20">
                        Query Registry
                    </button>
                    <?php if (!empty($search)): ?>
                        <a href="?" class="text-xs font-black text-slate-400 hover:text-rose-500 px-4 py-4 rounded-2xl uppercase tracking-widest transition-all">Reset</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- POSTNATAL REGISTRY TABLE -->
            <div class="card-premium">
                <div class="overflow-x-auto">
                    <table class="w-full text-left table-modern">
                        <thead>
                            <tr>
                                <th>Visit Detail</th>
                                <th>Mother Profile</th>
                                <th>Newborn Identity</th>
                                <th>Observation</th>
                                <th>Feeding Mode</th>
                                <th class="text-center">Protocol</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($postnatalRecords)): ?>
                                <?php foreach ($postnatalRecords as $record): ?>
                                    <tr>
                                        <td>
                                            <div class="flex flex-col">
                                                <span class="text-sm font-black text-slate-800 italic"><?= date('F d, Y', strtotime($record['visit_date'])) ?></span>
                                                <div class="flex items-center gap-2 mt-1">
                                                    <span class="px-2 py-0.5 bg-slate-100 text-slate-500 text-[9px] font-black rounded uppercase tracking-widest">Visit #<?= $record['visit_number'] ?? '1' ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="flex items-center gap-4">
                                                <div class="w-10 h-10 rounded-2xl bg-slate-900 flex items-center justify-center text-white font-black text-xs shadow-lg shadow-slate-900/10">
                                                    <?= strtoupper(substr($record['mother_first_name'], 0, 1)) ?>
                                                </div>
                                                <div class="flex flex-col">
                                                    <span class="text-sm font-bold text-slate-800"><?= htmlspecialchars(($record['mother_first_name'] ?? '') . ' ' . ($record['mother_last_name'] ?? '')) ?></span>
                                                    <span class="text-[10px] text-slate-400 font-bold tracking-tight"><?= $record['mother_phone'] ?: 'No Phone'; ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="flex flex-col">
                                                <span class="text-sm font-bold text-slate-800"><?= htmlspecialchars(($record['baby_first_name'] ?? '') . ' ' . ($record['baby_last_name'] ?? '')) ?></span>
                                                <div class="flex items-center gap-2 mt-1">
                                                    <span class="px-2 py-0.5 <?= strtolower($record['gender']) == 'male' ? 'bg-health-50 text-health-600' : 'bg-rose-50 text-rose-600'; ?> text-[8px] font-black rounded-lg uppercase tracking-tight"><?= $record['gender'] ?></span>
                                                    <span class="text-[9px] text-slate-400 font-medium italic">Born <?= date('M d', strtotime($record['birth_date'])) ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="flex flex-col gap-1">
                                                <div class="flex items-center gap-2">
                                                    <span class="w-1.5 h-1.5 rounded-full <?= $record['days_after_birth'] <= 7 ? 'bg-rose-500' : ($record['days_after_birth'] <= 30 ? 'bg-amber-500' : 'bg-emerald-500'); ?>"></span>
                                                    <span class="text-xs font-black text-slate-600 uppercase tracking-tighter italic"><?= $record['days_after_birth'] ?> Days PP</span>
                                                </div>
                                                <div class="flex items-center gap-3">
                                                    <span class="text-[10px] text-slate-400 font-bold uppercase"><i class="fas fa-weight-scale mr-1 text-emerald-500"></i><?= $record['baby_weight'] ?> kg</span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if (!empty($record['feeding_method'])): ?>
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-widest border <?= strpos($record['feeding_method'], 'breast') !== false ? 'bg-emerald-50 text-emerald-600 border-emerald-100' : 'bg-amber-50 text-amber-600 border-amber-100'; ?>">
                                                    <?= str_replace('-', ' ', $record['feeding_method']) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-[10px] text-slate-300 font-black italic uppercase">Undetermined</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="flex items-center justify-center gap-2">
                                                <button onclick="openVisitDetails(<?= $record['id'] ?>)" class="w-10 h-10 rounded-2xl bg-slate-50 text-slate-400 flex items-center justify-center hover:bg-slate-900 hover:text-white transition-all duration-300 group">
                                                    <i class="fas fa-microscope text-xs group-hover:scale-110 transition-transform"></i>
                                                </button>
                                                <a href="forms/postnatal_form.php?edit=<?= $record['id'] ?>" class="w-10 h-10 rounded-2xl bg-health-50 text-health-600 flex items-center justify-center hover:bg-health-600 hover:text-white transition-all duration-300">
                                                    <i class="fas fa-pen-nib text-xs"></i>
                                                </a>
                                                <button onclick="confirmDelete('postnatal_record', <?= $record['id'] ?>, 'Visit for <?= htmlspecialchars($record['baby_first_name']) ?>')" class="w-10 h-10 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center hover:bg-rose-600 hover:text-white transition-all duration-300">
                                                    <i class="fas fa-trash-can text-xs"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-20">
                                        <div class="flex flex-col items-center gap-4">
                                            <div class="w-20 h-20 rounded-[2rem] bg-slate-50 flex items-center justify-center text-slate-200 text-3xl">
                                                <i class="fas fa-baby-carriage"></i>
                                            </div>
                                            <div class="space-y-1">
                                                <p class="text-slate-400 font-black uppercase text-xs tracking-widest">No Postnatal Data Found</p>
                                                <p class="text-slate-300 text-[10px] font-medium font-serif italic">The postnatal care registry is currently empty.</p>
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
                    <div class="px-8 py-6 border-t border-slate-50 bg-slate-50/20 flex items-center justify-between">
                        <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Page <?= $page ?> of <?= $totalPages ?></span>
                        <div class="flex items-center gap-2">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>" class="w-10 h-10 rounded-2xl bg-white border border-slate-100 flex items-center justify-center text-slate-400 hover:border-health-500 hover:text-health-500 transition-all group">
                                    <i class="fas fa-chevron-left text-[10px] group-active:scale-75 transition-transform"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>" class="w-10 h-10 rounded-2xl flex items-center justify-center text-xs font-black uppercase tracking-widest transition-all <?= $i == $page ? 'bg-slate-900 text-white shadow-lg shadow-slate-900/10' : 'bg-white border border-slate-100 text-slate-400 hover:border-health-100 hover:bg-slate-50' ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>" class="w-10 h-10 rounded-2xl bg-white border border-slate-100 flex items-center justify-center text-slate-400 hover:border-health-500 hover:text-health-500 transition-all group">
                                    <i class="fas fa-chevron-right text-[10px] group-active:scale-75 transition-transform"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- POSTNATAL ANALYSIS MODAL -->
    <div id="postnatalDetailsModal" class="hidden fixed inset-0 z-[100] overflow-y-auto overflow-x-hidden">
        <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity" onclick="hideModal()"></div>
        <div class="relative min-h-screen flex items-center justify-center p-4">
            <div class="relative modal-premium w-full max-w-4xl max-h-[90vh] flex flex-col animate-in zoom-in-95 duration-300">
                <!-- Modal Header -->
                <div class="p-8 flex items-center justify-between border-b border-slate-50">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-500 flex items-center justify-center">
                            <i class="fas fa-microscope text-xl"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-black text-slate-800 tracking-tight">Clinical Analysis Repository</h2>
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest italic">Verification of Postnatal Health Vectors</p>
                        </div>
                    </div>
                    <button onclick="hideModal()" class="w-10 h-10 rounded-2xl bg-slate-50 text-slate-400 flex items-center justify-center hover:bg-rose-50 hover:text-rose-500 transition-all">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <!-- Modal Content -->
                <div class="flex-1 overflow-y-auto p-10 custom-scrollbar" id="modalContentContainer">
                    <div class="text-center py-20">
                        <div class="w-12 h-12 border-4 border-health-500 border-t-transparent rounded-full animate-spin mx-auto mb-4"></div>
                        <p class="text-xs font-black text-slate-400 uppercase tracking-[0.2em] italic animate-pulse">Accessing Clinical Data Matrix...</p>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="p-8 border-t border-slate-50 flex justify-end items-center gap-4">
                    <button onclick="hideModal()" class="px-8 py-4 rounded-2xl text-xs font-black text-slate-400 uppercase tracking-widest hover:bg-slate-50 transition-all">Dismiss Profile</button>
                    <button id="modalEditBtn" class="px-8 py-4 rounded-2xl bg-health-500 text-white text-xs font-black uppercase tracking-widest hover:bg-health-600 transition-all shadow-lg shadow-health-500/20 active:scale-95">Edit Analysis</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function openVisitDetails(recordId) {
            const modal = document.getElementById('postnatalDetailsModal');
            const content = document.getElementById('modalContentContainer');
            const editBtn = document.getElementById('modalEditBtn');
            
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            
            // Set initial loader
            content.innerHTML = `
                <div class="text-center py-20">
                    <div class="w-12 h-12 border-4 border-health-500 border-t-transparent rounded-full animate-spin mx-auto mb-4"></div>
                    <p class="text-xs font-black text-slate-400 uppercase tracking-[0.2em] italic animate-pulse">Syncing Diagnostic Data...</p>
                </div>
            `;
            
            fetch(`get_postnatal_details.php?id=${recordId}`)
                .then(response => response.text())
                .then(html => {
                    content.innerHTML = html;
                    editBtn.onclick = () => window.location.href = `forms/postnatal_form.php?edit=${recordId}`;
                })
                .catch(err => {
                    content.innerHTML = `
                        <div class="bg-rose-50 border border-rose-100 p-8 rounded-[2rem] text-center">
                            <i class="fas fa-exclamation-triangle text-rose-500 text-3xl mb-4"></i>
                            <p class="font-bold text-rose-800 uppercase tracking-widest text-xs">Registry Connection Failure</p>
                        </div>
                    `;
                });
        }

        function hideModal() {
            document.getElementById('postnatalDetailsModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        function confirmDelete(type, id, name) {
            Swal.fire({
                title: '<span class="text-xl font-black uppercase tracking-tight">Security Protocol</span>',
                html: `<p class="text-sm font-medium text-slate-500">Are you certain you wish to purge the clinical record for <br><b class="text-slate-900 font-bold">${name}</b>? This action is irreversible.</p>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#000000',
                cancelButtonColor: '#F8FAFC',
                confirmButtonText: '<span class="text-[10px] font-black uppercase tracking-widest px-4">Confirm Purge</span>',
                cancelButtonText: '<span class="text-[10px] font-black uppercase tracking-widest text-slate-400 px-4">Cancel</span>',
                customClass: {
                    popup: 'rounded-[3rem] border-2 border-slate-50 shadow-2xl',
                    confirmButton: 'rounded-2xl shadow-xl shadow-slate-900/20',
                    cancelButton: 'rounded-2xl border border-slate-100'
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