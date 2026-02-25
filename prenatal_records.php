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
    <title>Prenatal Records - Kibenes eBirth</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php include_once __DIR__ . '/includes/tailwind_config.php'; ?>
    <style type="text/tailwindcss">
        @layer components {
            .stat-card-clinical {
                @apply bg-white p-6 rounded-3xl border border-slate-100 shadow-sm hover:shadow-md transition-all duration-300;
            }
            .table-modern th {
                @apply px-6 py-4 text-left text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em] border-b border-slate-50;
            }
            .table-modern td {
                @apply px-6 py-4 text-sm text-slate-600 border-b border-slate-50;
            }
            .user-avatar-premium {
                @apply w-10 h-10 rounded-2xl flex items-center justify-center text-sm font-bold shadow-soft transition-transform duration-300 group-hover:scale-110;
            }
            .badge-clinical {
                @apply text-[9px] font-black px-3 py-1.5 rounded-full uppercase tracking-widest border italic;
            }
        }
        
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { @apply bg-slate-50; }
        ::-webkit-scrollbar-thumb { @apply bg-slate-200 rounded-full hover:bg-slate-300 transition-colors; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen text-slate-900 font-sans antialiased">
    <?php include_once $rootPath . '/includes/header.php'; ?>
    
    <div class="flex flex-col lg:flex-row min-h-[calc(100vh-4rem)]">
        <?php include_once $rootPath . '/includes/sidebar.php'; ?>
        
        <main class="flex-1 p-4 lg:p-10 space-y-10 group/main">
            <!-- PAGE HEADER -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div>
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-2 h-8 bg-health-600 rounded-full"></div>
                        <h1 class="text-3xl font-black text-slate-900 tracking-tight">Prenatal Registry</h1>
                    </div>
                    <p class="text-slate-500 font-medium ml-5">Comprehensive maternal checkup monitoring system.</p>
                </div>
                
                <div class="flex items-center gap-3">
                    <a href="forms/prenatal_form.php" class="bg-health-600 hover:bg-health-700 text-white font-bold px-6 py-4 rounded-[2rem] transition-all shadow-lg shadow-health-100 flex items-center gap-2 active:scale-95">
                        <i class="fas fa-plus"></i>
                        <span>New Clinical Visit</span>
                    </a>
                </div>
            </div>

            <!-- SEARCH & STATS -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-end">
                <div class="lg:col-span-8">
                    <form method="GET" class="relative group/search">
                        <i class="fas fa-search absolute left-6 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within/search:text-health-600 transition-colors"></i>
                        <input type="text" name="search" value="<?= htmlspecialchars($search); ?>" 
                               class="w-full bg-white border-2 border-slate-100 rounded-[2rem] py-5 pl-14 pr-32 text-sm font-bold text-slate-900 focus:border-health-600 focus:ring-4 focus:ring-health-600/5 transition-all outline-none placeholder:text-slate-300 shadow-sm"
                               placeholder="Search patient name or clinical identity...">
                        <button type="submit" class="absolute right-3 top-2 bottom-2 bg-slate-900 text-white px-6 rounded-full font-bold text-xs hover:bg-health-700 transition-all">
                            Run Query
                        </button>
                    </form>
                </div>
                
                <div class="lg:col-span-4">
                    <div class="bg-white px-8 py-5 rounded-[2rem] border border-slate-100 shadow-sm flex items-center justify-between group/total">
                        <div>
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Total Records</p>
                            <h3 class="text-2xl font-black text-slate-900"><?= number_format($totalRecords); ?></h3>
                        </div>
                        <div class="w-12 h-12 rounded-2xl bg-health-50 text-health-600 flex items-center justify-center text-xl shadow-soft group-hover/total:rotate-12 transition-transform">
                            <i class="fas fa-file-waveform"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- REGISTRY TABLE -->
            <div class="space-y-6">
                <div class="flex items-center justify-between px-2">
                    <h3 class="text-sm font-black text-slate-400 uppercase tracking-[0.3em]">Patient Visit Timeline</h3>
                    <div class="flex items-center gap-2">
                        <div class="w-2 h-2 rounded-full bg-emerald-500"></div>
                        <span class="text-[9px] font-black text-slate-400 uppercase">Live Database</span>
                    </div>
                </div>

                <div class="bg-white rounded-[3rem] border border-slate-100 shadow-xl shadow-slate-200/50 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full table-modern">
                            <thead>
                                <tr>
                                    <th>Patient Profile</th>
                                    <th>Pregnancy Status</th>
                                    <th>Vital Signs</th>
                                    <th>Weight Tracking</th>
                                    <th>Medications</th>
                                    <th>Lab Tests</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                <?php if (!empty($prenatalRecords)): ?>
                                    <?php foreach ($prenatalRecords as $record): ?>
                                    <tr class="hover:bg-slate-50/50 transition-all duration-300 group">
                                        <td class="py-6">
                                            <div class="flex items-center gap-4">
                                                <div class="user-avatar-premium bg-health-600 text-white shadow-health-100">
                                                    <?= strtoupper(substr($record['mother_first_name'], 0, 1) . substr($record['mother_last_name'], 0, 1)); ?>
                                                </div>
                                                <div class="flex flex-col">
                                                    <span class="font-bold text-slate-900 group-hover:text-health-700 transition-colors"><?= htmlspecialchars($record['mother_first_name'] . ' ' . $record['mother_last_name']); ?></span>
                                                    <span class="text-[10px] font-medium text-slate-400 italic">Patient ID: #PR-<?= $record['id']; ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="flex flex-col">
                                                <span class="text-xs font-black text-slate-900"><?= $record['gestational_weeks'] ?? '0.0'; ?> Weeks</span>
                                                <span class="text-[10px] font-bold text-health-600 uppercase tracking-tighter mt-1">Visit No. <?= $record['visit_number']; ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="space-y-1">
                                                <div class="flex items-center gap-2">
                                                    <span class="text-[9px] font-black text-slate-400 uppercase px-1.5 py-0.5 bg-slate-50 rounded">BP</span>
                                                    <span class="text-xs font-bold text-slate-700"><?= $record['blood_pressure'] ?: '--'; ?></span>
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <span class="text-[9px] font-black text-slate-400 uppercase px-1.5 py-0.5 bg-slate-50 rounded">Temp</span>
                                                    <span class="text-xs font-bold text-slate-700"><?= $record['temperature'] ?: '--'; ?>°C</span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="flex flex-col">
                                                <div class="flex items-baseline gap-1">
                                                    <span class="text-xs font-black text-slate-900"><?= $record['weight']; ?></span>
                                                    <span class="text-[9px] font-bold text-slate-400 uppercase">kg</span>
                                                </div>
                                                <?php if ($record['previous_weight']): ?>
                                                    <?php 
                                                        $diff = $record['weight_change'];
                                                        $color = $diff > 0 ? 'text-emerald-500' : ($diff < 0 ? 'text-rose-500' : 'text-slate-400');
                                                        $icon = $diff > 0 ? 'fa-caret-up' : ($diff < 0 ? 'fa-caret-down' : 'fa-minus');
                                                    ?>
                                                    <span class="text-[10px] font-black <?= $color; ?> flex items-center gap-1">
                                                        <i class="fas <?= $icon; ?>"></i>
                                                        <?= abs($diff); ?> kg
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="flex flex-wrap gap-1.5">
                                                <?php if ($record['iron_supplement']): ?>
                                                    <span class="px-2 py-1 bg-amber-50 text-amber-600 text-[9px] font-black rounded-lg border border-amber-100 uppercase" title="Iron Supplement">Iron</span>
                                                <?php endif; ?>
                                                <?php if ($record['folic_acid']): ?>
                                                    <span class="px-2 py-1 bg-emerald-50 text-emerald-600 text-[9px] font-black rounded-lg border border-emerald-100 uppercase" title="Folic Acid">Folic</span>
                                                <?php endif; ?>
                                                <?php if ($record['calcium']): ?>
                                                    <span class="px-2 py-1 bg-sky-50 text-sky-600 text-[9px] font-black rounded-lg border border-sky-100 uppercase" title="Calcium">Calc</span>
                                                <?php endif; ?>
                                                <?php if (!$record['iron_supplement'] && !$record['folic_acid'] && !$record['calcium']): ?>
                                                    <span class="text-[10px] font-bold text-slate-300 italic">None</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="flex flex-col gap-1">
                                                <?php if ($record['hb_level']): ?>
                                                    <?php $hbStatus = $record['hb_level'] < 11 ? 'bg-rose-50 text-rose-600 border-rose-100' : 'bg-emerald-50 text-emerald-600 border-emerald-100'; ?>
                                                    <span class="px-2 py-1 <?= $hbStatus; ?> text-[9px] font-black rounded-lg border uppercase w-fit">Hb: <?= $record['hb_level']; ?></span>
                                                <?php endif; ?>
                                                <?php if ($record['blood_group']): ?>
                                                    <span class="px-2 py-1 bg-slate-50 text-slate-600 text-[9px] font-black rounded-lg border border-slate-100 uppercase w-fit">Group: <?= $record['blood_group']; ?></span>
                                                <?php endif; ?>
                                                <?php if (!$record['hb_level'] && !$record['blood_group']): ?>
                                                    <span class="text-[10px] font-bold text-slate-300 italic">No Labs</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="text-right">
                                            <div class="flex justify-end gap-2 pr-4">
                                                <button onclick="viewPrenatalDetails(<?= $record['id']; ?>)" class="bg-sky-50 hover:bg-sky-100 text-sky-600 p-3 rounded-2xl transition-all shadow-sm active:scale-90" title="View Full Analysis">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <a href="forms/prenatal_form.php?edit=<?= $record['id']; ?>" class="bg-amber-50 hover:bg-amber-100 text-amber-600 p-3 rounded-2xl transition-all shadow-sm active:scale-90" title="Modify Record">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button onclick="confirmDelete('prenatal_record', <?= $record['id']; ?>, 'Prenatal Visit #<?= $record['visit_number']; ?> for <?= htmlspecialchars($record['mother_first_name'] . ' ' . $record['mother_last_name']); ?>')" 
                                                        class="bg-rose-50 hover:bg-rose-100 text-rose-500 p-3 rounded-2xl transition-all shadow-sm active:scale-90" title="Permanently Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="py-20 text-center">
                                            <div class="flex flex-col items-center opacity-30">
                                                <i class="fas fa-folder-open text-6xl mb-4 text-slate-300"></i>
                                                <p class="text-sm font-black text-slate-400 uppercase tracking-widest">Registry entry not found</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- PAGINATION -->
                    <?php if ($totalPages > 1): ?>
                    <div class="p-8 border-t border-slate-50 flex items-center justify-between bg-slate-50/30">
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">
                            Page <?= $page; ?> of <?= $totalPages; ?>
                        </p>
                        <div class="flex gap-2">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?= $page - 1; ?>&search=<?= urlencode($search); ?>" class="bg-white border border-slate-200 text-slate-600 px-5 py-2.5 rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-slate-50 transition-all shadow-sm active:scale-95">
                                    <i class="fas fa-chevron-left mr-2 font-bold"></i>Previous
                                </a>
                            <?php endif; ?>

                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?= $page + 1; ?>&search=<?= urlencode($search); ?>" class="bg-slate-900 text-white px-5 py-2.5 rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-slate-800 transition-all shadow-lg shadow-slate-200 active:scale-95">
                                    Next<i class="fas fa-chevron-right ml-2 font-bold"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- PRENATAL DETAILS MODAL (Premium Tailwind) -->
    <div id="prenatalDetailsModal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-[2.5rem] shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden animate-in zoom-in duration-300 flex flex-col">
            <div class="flex items-center justify-between p-8 border-b border-slate-50 bg-slate-50/50">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-sky-100 text-sky-600 flex items-center justify-center text-xl">
                        <i class="fas fa-notes-medical"></i>
                    </div>
                    <div>
                        <h3 class="text-2xl font-black text-slate-900 tracking-tight" id="modalTitle">Clinical Analysis</h3>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mt-1">Detailed Prenatal Observation Record</p>
                    </div>
                </div>
                <button onclick="closeDetailsModal()" class="w-10 h-10 rounded-2xl bg-white border border-slate-100 flex items-center justify-center text-slate-400 hover:text-rose-600 transition-all active:scale-90">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="flex-1 overflow-y-auto p-8 custom-scrollbar">
                <div id="prenatalDetailsContent">
                    <!-- Loaded via AJAX -->
                    <div class="flex flex-col items-center justify-center py-20 opacity-50">
                        <div class="w-12 h-12 border-4 border-sky-100 border-t-sky-600 rounded-full animate-spin"></div>
                        <p class="mt-4 font-black text-[10px] text-slate-400 uppercase tracking-widest">Processing Data...</p>
                    </div>
                </div>
            </div>

            <div class="p-8 bg-slate-50/50 border-t border-slate-100 flex justify-end gap-3">
                <button onclick="closeDetailsModal()" class="px-8 py-4 rounded-2xl text-slate-400 font-bold hover:bg-white transition-all">Dismiss</button>
                <button id="editPrenatalBtn" class="bg-health-600 hover:bg-health-700 text-white font-bold px-10 py-4 rounded-2xl transition-all shadow-lg shadow-health-100 active:scale-95">
                    Modify Records
                </button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        let currentRecordId = null;

        function viewPrenatalDetails(recordId) {
            currentRecordId = recordId;
            const modal = document.getElementById('prenatalDetailsModal');
            const content = document.getElementById('prenatalDetailsContent');
            
            // Show Modal
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            
            // Reset Content
            content.innerHTML = `
                <div class="flex flex-col items-center justify-center py-20 opacity-50">
                    <div class="w-12 h-12 border-4 border-sky-100 border-t-sky-600 rounded-full animate-spin"></div>
                    <p class="mt-4 font-black text-[10px] text-slate-400 uppercase tracking-widest">Processing Data...</p>
                </div>
            `;

            // Load Data
            fetch(`get_prenatal_details.php?id=${recordId}`)
                .then(response => response.text())
                .then(data => {
                    content.innerHTML = data;
                })
                .catch(error => {
                    content.innerHTML = `
                        <div class="bg-rose-50 border border-rose-100 p-8 rounded-[2rem] text-center">
                            <i class="fas fa-exclamation-triangle text-rose-500 text-3xl mb-4"></i>
                            <p class="font-bold text-rose-800">Connection Interrupted</p>
                            <p class="text-xs text-rose-600 mt-2">Failed to retrieve clinical documentation. Please try again.</p>
                        </div>
                    `;
                });
        }

        function closeDetailsModal() {
            const modal = document.getElementById('prenatalDetailsModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        function confirmDelete(type, id, name) {
            Swal.fire({
                title: 'Confirm Deletion',
                html: `Are you sure you want to permanently remove <br><b class="text-health-600">${name}</b>?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#f43f5e',
                confirmButtonText: 'Yes, Delete Record',
                cancelButtonText: 'Cancel Removal',
                padding: '2rem',
                customClass: {
                    popup: 'rounded-[2.5rem]',
                    confirmButton: 'rounded-2xl font-bold py-4 px-8',
                    cancelButton: 'rounded-2xl font-bold py-4 px-8'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `delete.php?type=${type}&id=${id}`;
                }
            });
        }

        document.getElementById('editPrenatalBtn').addEventListener('click', function() {
            if (currentRecordId) {
                window.location.href = `forms/prenatal_form.php?edit=${currentRecordId}`;
            }
        });

        // Close modal on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeDetailsModal();
        });
    </script>
</body>
</html>