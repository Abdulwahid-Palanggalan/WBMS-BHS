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

// Build query
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

// Stats
$statsQuery = $pdo->query("SELECT COUNT(*) as total, COUNT(DISTINCT mother_id) as unique_mothers FROM prenatal_records");
$stats = $statsQuery->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prenatal Records - Kibenes eBirth</title>
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
                @apply p-2.5 rounded-xl transition-all duration-200 shadow-sm active:scale-90;
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

        <main class="flex-1 p-4 lg:p-10 space-y-8">

            <!-- PAGE HEADER -->
            <header class="bg-white rounded-[2.5rem] border border-slate-100 shadow-sm p-8 flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div class="flex items-center gap-5">
                    <div class="w-14 h-14 rounded-[1.2rem] bg-gradient-to-br from-health-600 to-health-700 flex items-center justify-center text-white text-2xl shadow-lg shadow-health-100">
                        <i class="fas fa-heartbeat"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-black text-slate-900 tracking-tight leading-tight">Prenatal Registry</h1>
                        <p class="text-sm text-slate-400 font-medium mt-0.5">Comprehensive maternal checkup monitoring system</p>
                    </div>
                </div>
                <a href="forms/prenatal_form.php"
                   class="inline-flex items-center gap-2.5 bg-health-600 hover:bg-health-700 text-white font-bold px-6 py-3.5 rounded-2xl transition-all shadow-lg shadow-health-100 active:scale-95 text-sm">
                    <i class="fas fa-plus text-xs"></i>
                    <span>New Clinical Visit</span>
                </a>
            </header>

            <!-- STAT CARDS -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                <div class="stat-chip">
                    <div class="stat-icon bg-health-50 text-health-600">
                        <i class="fas fa-file-medical"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-0.5">Total Visits</p>
                        <h3 class="text-2xl font-black text-slate-900"><?= number_format($stats['total'] ?? $totalRecords) ?></h3>
                    </div>
                </div>
                <div class="stat-chip">
                    <div class="stat-icon bg-sky-50 text-sky-500">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-0.5">Unique Patients</p>
                        <h3 class="text-2xl font-black text-slate-900"><?= number_format($stats['unique_mothers'] ?? 0) ?></h3>
                    </div>
                </div>
                <div class="stat-chip">
                    <div class="stat-icon bg-amber-50 text-amber-500">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-0.5">Showing Results</p>
                        <h3 class="text-2xl font-black text-slate-900"><?= number_format($totalRecords) ?></h3>
                    </div>
                </div>
            </div>

            <!-- SEARCH BAR -->
            <div class="flex items-center gap-4">
                <form method="GET" class="relative flex-1 group/search">
                    <i class="fas fa-search absolute left-5 top-1/2 -translate-y-1/2 text-slate-300 group-focus-within/search:text-health-600 transition-colors duration-200 text-sm"></i>
                    <input type="text" name="search" value="<?= htmlspecialchars($search); ?>"
                           class="w-full bg-white border border-slate-200 rounded-2xl py-4 pl-12 pr-32 text-sm font-medium text-slate-800 focus:border-health-500 focus:ring-4 focus:ring-health-600/10 outline-none transition-all placeholder:text-slate-300 shadow-sm"
                           placeholder="Search patient name or phone number...">
                    <button type="submit" class="absolute right-2.5 top-2 bottom-2 bg-health-600 hover:bg-health-700 text-white px-5 rounded-xl font-bold text-xs transition-all">
                        Search
                    </button>
                </form>
                <?php if (!empty($search)): ?>
                <a href="prenatal_records.php" class="flex items-center gap-2 text-xs font-bold text-slate-400 hover:text-rose-500 transition-colors bg-white border border-slate-200 px-4 py-3 rounded-2xl shadow-sm">
                    <i class="fas fa-times"></i> Clear
                </a>
                <?php endif; ?>
            </div>

            <!-- RECORDS TABLE -->
            <div class="bg-white rounded-[2.5rem] border border-slate-100 shadow-sm overflow-hidden">
                <!-- Table Header Label -->
                <div class="flex items-center justify-between px-8 py-5 border-b border-slate-50">
                    <div class="flex items-center gap-3">
                        <div class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></div>
                        <span class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">Patient Visit Timeline</span>
                    </div>
                    <span class="text-[10px] font-black text-slate-300 uppercase tracking-widest"><?= $totalRecords ?> Records</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-slate-50/50">
                                <th class="table-th">Patient</th>
                                <th class="table-th">Visit Date</th>
                                <th class="table-th">Pregnancy</th>
                                <th class="table-th">Vital Signs</th>
                                <th class="table-th">Weight</th>
                                <th class="table-th">Medications</th>
                                <th class="table-th">Lab Results</th>
                                <th class="table-th text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($prenatalRecords)): ?>
                                <?php foreach ($prenatalRecords as $record): ?>
                                <tr class="hover:bg-health-50/30 transition-colors duration-200 group">
                                    <!-- Patient -->
                                    <td class="table-td">
                                        <div class="flex items-center gap-3">
                                            <div class="w-9 h-9 rounded-xl bg-health-600 text-white flex items-center justify-center text-xs font-black shadow-sm shadow-health-100 flex-shrink-0 group-hover:scale-105 transition-transform">
                                                <?= strtoupper(substr($record['mother_first_name'], 0, 1) . substr($record['mother_last_name'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <p class="text-sm font-bold text-slate-800 group-hover:text-health-700 transition-colors leading-tight">
                                                    <?= htmlspecialchars($record['mother_first_name'] . ' ' . $record['mother_last_name']); ?>
                                                </p>
                                                <p class="text-[10px] text-slate-400 font-medium">#PR-<?= $record['id']; ?></p>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Visit Date -->
                                    <td class="table-td">
                                        <div class="flex flex-col">
                                            <span class="text-sm font-bold text-slate-700">
                                                <?= date('M d, Y', strtotime($record['visit_date'])); ?>
                                            </span>
                                            <span class="text-[10px] font-medium text-health-600 uppercase tracking-tight mt-0.5">
                                                Visit #<?= $record['visit_number']; ?>
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Pregnancy Status -->
                                    <td class="table-td">
                                        <div class="flex flex-col gap-1">
                                            <?php if ($record['gestational_weeks'] !== null): ?>
                                            <span class="text-sm font-black text-slate-800"><?= $record['gestational_weeks'] ?? '0.0'; ?> <span class="text-[10px] font-bold text-slate-400">wks</span></span>
                                            <?php endif; ?>
                                            <?php if (!empty($record['edc']) && $record['edc'] !== '0000-00-00'): ?>
                                            <span class="text-[10px] font-bold text-amber-600">EDC: <?= date('M d', strtotime($record['edc'])); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>

                                    <!-- Vital Signs -->
                                    <td class="table-td">
                                        <div class="space-y-1.5">
                                            <div class="flex items-center gap-1.5">
                                                <span class="inline-block text-[9px] font-black text-slate-400 uppercase bg-slate-50 border border-slate-100 px-1.5 py-0.5 rounded-md">BP</span>
                                                <span class="text-xs font-bold text-slate-700"><?= $record['blood_pressure'] ?: '—'; ?></span>
                                            </div>
                                            <div class="flex items-center gap-1.5">
                                                <span class="inline-block text-[9px] font-black text-slate-400 uppercase bg-slate-50 border border-slate-100 px-1.5 py-0.5 rounded-md">T°</span>
                                                <span class="text-xs font-bold text-slate-700"><?= $record['temperature'] ? $record['temperature'] . '°C' : '—'; ?></span>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Weight -->
                                    <td class="table-td">
                                        <div class="flex flex-col">
                                            <div class="flex items-baseline gap-1">
                                                <span class="text-sm font-black text-slate-800"><?= $record['weight'] ?: '—'; ?></span>
                                                <?php if ($record['weight']): ?>
                                                <span class="text-[9px] font-bold text-slate-400">kg</span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($record['previous_weight'] && $record['weight']): ?>
                                                <?php
                                                    $diff = $record['weight_change'];
                                                    $wColor = $diff > 0 ? 'text-emerald-500' : ($diff < 0 ? 'text-rose-500' : 'text-slate-400');
                                                    $wIcon  = $diff > 0 ? 'fa-arrow-up' : ($diff < 0 ? 'fa-arrow-down' : 'fa-minus');
                                                ?>
                                                <span class="text-[10px] font-black <?= $wColor; ?> flex items-center gap-0.5 mt-0.5">
                                                    <i class="fas <?= $wIcon; ?> text-[8px]"></i>
                                                    <?= abs($diff); ?> kg
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>

                                    <!-- Medications -->
                                    <td class="table-td">
                                        <div class="flex flex-wrap gap-1">
                                            <?php if ($record['iron_supplement']): ?>
                                                <span class="pill-badge bg-amber-50 text-amber-600 border-amber-200" title="Iron Supplement">Iron</span>
                                            <?php endif; ?>
                                            <?php if ($record['folic_acid']): ?>
                                                <span class="pill-badge bg-emerald-50 text-emerald-600 border-emerald-200" title="Folic Acid">Folic</span>
                                            <?php endif; ?>
                                            <?php if ($record['calcium']): ?>
                                                <span class="pill-badge bg-sky-50 text-sky-600 border-sky-200" title="Calcium">Ca</span>
                                            <?php endif; ?>
                                            <?php if (!$record['iron_supplement'] && !$record['folic_acid'] && !$record['calcium']): ?>
                                                <span class="text-[10px] font-medium text-slate-300 italic">None</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>

                                    <!-- Lab Results -->
                                    <td class="table-td">
                                        <div class="flex flex-col gap-1">
                                            <?php if ($record['hb_level']): ?>
                                                <?php $hbStatus = $record['hb_level'] < 11 ? 'bg-rose-50 text-rose-600 border-rose-200' : 'bg-emerald-50 text-emerald-600 border-emerald-200'; ?>
                                                <span class="pill-badge <?= $hbStatus; ?> w-fit">Hb: <?= $record['hb_level']; ?></span>
                                            <?php endif; ?>
                                            <?php if ($record['blood_group']): ?>
                                                <span class="pill-badge bg-slate-50 text-slate-600 border-slate-200 w-fit"><?= htmlspecialchars($record['blood_group']); ?></span>
                                            <?php endif; ?>
                                            <?php if (!$record['hb_level'] && !$record['blood_group']): ?>
                                                <span class="text-[10px] font-medium text-slate-300 italic">No labs</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>

                                    <!-- Actions -->
                                    <td class="table-td">
                                        <div class="flex items-center justify-end gap-1.5 pr-2">
                                            <button onclick="viewPrenatalDetails(<?= $record['id']; ?>)"
                                                    class="action-btn bg-sky-50 hover:bg-sky-100 text-sky-600 border border-sky-100"
                                                    title="View Details">
                                                <i class="fas fa-eye text-xs"></i>
                                            </button>
                                            <a href="forms/prenatal_form.php?edit=<?= $record['id']; ?>"
                                               class="action-btn bg-amber-50 hover:bg-amber-100 text-amber-600 border border-amber-100"
                                               title="Edit Record">
                                                <i class="fas fa-pen text-xs"></i>
                                            </a>
                                            <button onclick="confirmDelete('prenatal_record', <?= $record['id']; ?>, 'Visit #<?= $record['visit_number']; ?> — <?= htmlspecialchars(addslashes($record['mother_first_name'] . ' ' . $record['mother_last_name'])); ?>')"
                                                    class="action-btn bg-rose-50 hover:bg-rose-100 text-rose-500 border border-rose-100"
                                                    title="Delete Record">
                                                <i class="fas fa-trash text-xs"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="py-24 text-center">
                                        <div class="flex flex-col items-center gap-3 opacity-40">
                                            <div class="w-16 h-16 rounded-3xl bg-slate-100 flex items-center justify-center">
                                                <i class="fas fa-folder-open text-2xl text-slate-400"></i>
                                            </div>
                                            <div>
                                                <p class="text-sm font-black text-slate-400 uppercase tracking-widest">No Records Found</p>
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
                    <div class="flex gap-2">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1; ?>&search=<?= urlencode($search); ?>"
                               class="flex items-center gap-2 bg-white border border-slate-200 text-slate-600 px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-slate-50 transition-all shadow-sm">
                                <i class="fas fa-chevron-left"></i> Prev
                            </a>
                        <?php endif; ?>
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?= $page + 1; ?>&search=<?= urlencode($search); ?>"
                               class="flex items-center gap-2 bg-health-600 text-white px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-health-700 transition-all shadow-sm">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

        </main>
    </div>

    <!-- PRENATAL DETAILS MODAL -->
    <div id="prenatalDetailsModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
        <div class="bg-white rounded-[2.5rem] shadow-2xl w-full max-w-4xl max-h-[92vh] overflow-hidden flex flex-col animate-in zoom-in-95 duration-300">

            <!-- Modal Header -->
            <div class="flex items-center justify-between px-8 py-6 border-b border-slate-100 bg-slate-50/50 flex-shrink-0">
                <div class="flex items-center gap-4">
                    <div class="w-11 h-11 rounded-2xl bg-health-50 text-health-600 flex items-center justify-center text-lg">
                        <i class="fas fa-notes-medical"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-black text-slate-900 tracking-tight" id="modalPatientName">Clinical Record</h3>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-0.5">Detailed Prenatal Observation</p>
                    </div>
                </div>
                <button onclick="closeDetailsModal()"
                        class="w-9 h-9 rounded-xl bg-white border border-slate-200 flex items-center justify-center text-slate-400 hover:text-rose-500 hover:border-rose-200 transition-all active:scale-90">
                    <i class="fas fa-times text-sm"></i>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="flex-1 overflow-y-auto p-8" id="prenatalDetailsContent">
                <div class="flex flex-col items-center justify-center py-20 opacity-40">
                    <div class="w-10 h-10 border-[3px] border-health-100 border-t-health-600 rounded-full animate-spin"></div>
                    <p class="mt-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Loading...</p>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="px-8 py-5 bg-slate-50/50 border-t border-slate-100 flex justify-end gap-3 flex-shrink-0">
                <button onclick="closeDetailsModal()"
                        class="px-6 py-3 rounded-2xl text-slate-400 font-bold text-sm hover:bg-white hover:text-slate-600 transition-all">
                    Close
                </button>
                <button id="editPrenatalBtn"
                        class="bg-health-600 hover:bg-health-700 text-white font-bold px-8 py-3 rounded-2xl transition-all text-sm shadow-lg shadow-health-100 active:scale-95">
                    <i class="fas fa-pen mr-2 text-xs"></i>Edit Record
                </button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        let currentRecordId = null;

        function viewPrenatalDetails(recordId) {
            currentRecordId = recordId;
            const modal = document.getElementById('prenatalDetailsModal');
            const content = document.getElementById('prenatalDetailsContent');

            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.style.overflow = 'hidden';

            content.innerHTML = `
                <div class="flex flex-col items-center justify-center py-20 opacity-40">
                    <div class="w-10 h-10 border-[3px] border-health-100 border-t-health-600 rounded-full animate-spin"></div>
                    <p class="mt-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Loading record...</p>
                </div>
            `;

            fetch(`get_prenatal_details.php?id=${recordId}`)
                .then(r => r.text())
                .then(data => {
                    content.innerHTML = data;
                })
                .catch(() => {
                    content.innerHTML = `
                        <div class="bg-rose-50 border border-rose-100 p-8 rounded-[2rem] text-center">
                            <div class="w-14 h-14 rounded-2xl bg-rose-100 text-rose-500 flex items-center justify-center text-2xl mx-auto mb-4">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <p class="font-black text-rose-800 text-sm uppercase tracking-widest">Connection Error</p>
                            <p class="text-xs text-rose-600 mt-2 font-medium">Failed to retrieve clinical record. Please try again.</p>
                        </div>
                    `;
                });
        }

        function closeDetailsModal() {
            const modal = document.getElementById('prenatalDetailsModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.style.overflow = '';
        }

        function confirmDelete(type, id, name) {
            Swal.fire({
                title: 'Delete Record?',
                html: `This will permanently remove<br><b class="text-health-700">${name}</b>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#0D9488',
                cancelButtonColor: '#f43f5e',
                confirmButtonText: 'Yes, Delete',
                cancelButtonText: 'Cancel',
                customClass: {
                    popup: 'rounded-[2rem]',
                    confirmButton: 'rounded-xl font-bold',
                    cancelButton: 'rounded-xl font-bold'
                }
            }).then(result => {
                if (result.isConfirmed) {
                    window.location.href = `delete.php?type=${type}&id=${id}`;
                }
            });
        }

        document.getElementById('editPrenatalBtn').addEventListener('click', () => {
            if (currentRecordId) {
                window.location.href = `forms/prenatal_form.php?edit=${currentRecordId}`;
            }
        });

        // Close on backdrop click
        document.getElementById('prenatalDetailsModal').addEventListener('click', function(e) {
            if (e.target === this) closeDetailsModal();
        });

        // Close on ESC
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') closeDetailsModal();
        });
    </script>
</body>
</html>
