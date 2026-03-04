<?php
// Use correct path to includes - FIXED PATH
$rootPath = __DIR__; // Current directory where pregnant_women.php is located

require_once $rootPath . '/includes/auth.php';
require_once $rootPath . '/includes/functions.php';

if (!isAuthorized(['admin', 'midwife'])) {
    header("Location: login.php");
    exit();
}

// Get pregnant women data
global $pdo;

// ✅ Get pregnant women data for table - AUTOMATICALLY REMOVED AFTER BIRTH
$pregnantWomen = $pdo->query("
    SELECT 
        m.id,
        m.first_name,
        m.last_name,
        m.phone,
        MAX(pr.visit_date) as last_prenatal_visit,
        DATEDIFF(NOW(), MAX(pr.visit_date)) as days_since_visit,
        pd.edc,
        pd.lmp,
        pd.gravida,
        pd.para,
        pd.abortions,
        pd.living_children,
        DATEDIFF(CURDATE(), pd.lmp) as gestational_days,
        ROUND(DATEDIFF(CURDATE(), pd.lmp) / 7, 1) as gestational_weeks,
        DATEDIFF(pd.edc, CURDATE()) as days_until_due,
        (SELECT COUNT(*) FROM prenatal_records WHERE mother_id = m.id) as prenatal_visits,
        (SELECT COUNT(*) FROM birth_records WHERE mother_id = m.id AND birth_date >= DATE_SUB(NOW(), INTERVAL 3 MONTH)) as recent_births
    FROM mothers m
    INNER JOIN prenatal_records pr ON m.id = pr.mother_id
    LEFT JOIN pregnancy_details pd ON m.id = pd.mother_id
    WHERE pr.visit_date >= DATE_SUB(NOW(), INTERVAL 9 MONTH)
    AND m.id NOT IN (
        SELECT DISTINCT mother_id FROM birth_records 
        WHERE birth_date >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
    )
    AND (pd.edc IS NULL OR pd.edc > CURDATE())
    GROUP BY m.id, m.first_name, m.last_name, m.phone, pd.edc, pd.lmp, pd.gravida, pd.para, pd.abortions, pd.living_children
    ORDER BY pd.edc ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Set the base URL
if (!isset($GLOBALS['base_url'])) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $path = dirname($_SERVER['SCRIPT_NAME']);
    $GLOBALS['base_url'] = $protocol . "://" . $host . $path;
    $GLOBALS['base_url'] = rtrim($GLOBALS['base_url'], '/');
}

$baseUrl = $GLOBALS['base_url'];
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pregnant Women Management | Clinical Dashboard</title>
    
    <!-- CSS Dependencies -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include_once $rootPath . '/includes/tailwind_config.php'; ?>
    
    <style>
        .glass-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.07);
        }
        
        .premium-table thead th {
            font-size: 0.65rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #64748b;
            padding: 1.25rem 1rem;
            background: #f8fafc;
            border: none;
        }

        .premium-table tbody td {
            padding: 1.25rem 1rem;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
        }

        .action-btn {
            @apply w-9 h-9 flex items-center justify-center rounded-xl transition-all duration-300;
        }

        @keyframes subtle-float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }

        .float-animation {
            animation: subtle-float 4s ease-in-out infinite;
        }
    </style>
</head>
<body class="bg-slate-50 font-sans text-slate-900 overflow-x-hidden">
    <?php include_once $rootPath . '/includes/header.php'; ?>
    
    <div class="flex flex-col lg:flex-row min-h-screen">
        <?php include_once $rootPath . '/includes/sidebar.php'; ?>
        
        <main class="flex-1 p-4 lg:p-8 space-y-8 no-print">
            <!-- Header & Analytics -->
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-6">
                <div class="space-y-2">
                    <div class="flex items-center gap-3">
                        <div class="w-1.5 h-8 bg-health-600 rounded-full"></div>
                        <h1 class="text-3xl font-black tracking-tight text-slate-800">Pregnant Registry</h1>
                    </div>
                    <p class="text-slate-500 font-medium flex items-center gap-2">
                        <i class="fas fa-stethoscope text-health-500"></i>
                        Active Maternal Monitoring Dashboard
                    </p>
                </div>

                <div class="grid grid-cols-2 sm:flex items-center gap-4">
                    <!-- Quick Stats Cards -->
                    <div class="bg-white px-5 py-4 rounded-[1.8rem] border border-slate-100 shadow-sm flex items-center gap-4 min-w-[160px]">
                        <div class="w-12 h-12 rounded-2xl bg-health-50 text-health-600 flex items-center justify-center text-xl shadow-inner">
                            <i class="fas fa-female"></i>
                        </div>
                        <div>
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Total Active</p>
                            <h3 class="text-2xl font-black text-slate-800 leading-none"><?= count($pregnantWomen) ?></h3>
                        </div>
                    </div>
                    
                    <div class="bg-slate-900 px-5 py-4 rounded-[1.8rem] text-white flex items-center gap-4 min-w-[160px] shadow-xl shadow-slate-200">
                        <div class="w-12 h-12 rounded-2xl bg-white/10 text-health-400 flex items-center justify-center text-xl backdrop-blur-md">
                            <i class="fas fa-person-breastfeeding"></i>
                        </div>
                        <div>
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">New Case</p>
                            <a href="forms/mother_registration.php" class="text-xs font-black text-health-400 hover:text-health-300 transition-colors uppercase flex items-center gap-1">
                                Register <i class="fas fa-plus"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Registry Table -->
            <div class="glass-card rounded-[2.5rem] overflow-hidden border border-slate-100 shadow-xl shadow-slate-200/50 animate-in fade-in slide-in-from-bottom-6 duration-700">
                <div class="p-8 border-b border-slate-50 flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div class="relative group max-w-md w-full">
                        <i class="fas fa-search absolute left-5 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-health-500 transition-colors"></i>
                        <input type="text" id="pregnantSearch" placeholder="Search by name or contact..." 
                               class="w-full pl-12 pr-6 py-4 bg-slate-50 border-none rounded-2xl text-sm font-bold focus:ring-2 focus:ring-health-500/20 transition-all placeholder:text-slate-400">
                    </div>
                    <div class="flex items-center gap-3">
                        <button class="bg-slate-100 hover:bg-slate-200 p-4 rounded-2xl text-slate-600 transition-all active:scale-95 shadow-sm">
                            <i class="fas fa-filter"></i>
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="premium-table w-full text-left border-collapse" id="pregnantTable">
                        <thead>
                            <tr>
                                <th>Maternal Profile</th>
                                <th>Estimated EDC</th>
                                <th>Gestational Age</th>
                                <th>Obstetric Score</th>
                                <th>Last Checkup</th>
                                <th>Clinical Status</th>
                                <th class="text-right">Intervention</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50 font-sans">
                            <?php foreach ($pregnantWomen as $woman): 
                                $daysUntilDue = $woman['edc'] ? (strtotime($woman['edc']) - time()) / (24 * 60 * 60) : null;
                                $isHighRisk = ($woman['days_since_visit'] ?? 0) > 30 || ($daysUntilDue !== null && $daysUntilDue <= 14);
                            ?>
                            <tr class="group hover:bg-slate-50/50 transition-colors">
                                <td>
                                    <div class="flex items-center gap-4">
                                        <div class="w-11 h-11 rounded-[0.9rem] bg-gradient-to-br from-slate-100 to-slate-200 p-0.5 shadow-sm">
                                            <div class="w-full h-full bg-white rounded-[0.85rem] flex items-center justify-center text-slate-400 group-hover:text-health-500 transition-colors">
                                                <i class="fas fa-user-nurse text-lg"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <p class="text-sm font-black text-slate-800 leading-none mb-1"><?= htmlspecialchars($woman['first_name'] . ' ' . $woman['last_name']) ?></p>
                                            <p class="text-[10px] font-bold text-slate-400 tracking-wide"><?= $woman['phone'] ?: 'No Phone Linked' ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($woman['edc']): ?>
                                        <div class="space-y-1">
                                            <p class="text-xs font-black text-slate-700"><?= date('M j, Y', strtotime($woman['edc'])) ?></p>
                                            <p class="text-[9px] font-bold uppercase tracking-widest text-<?= $daysUntilDue <= 7 ? 'rose' : ($daysUntilDue <= 30 ? 'amber' : 'emerald') ?>-500">
                                                <?= $daysUntilDue > 0 ? floor($daysUntilDue) . ' days remaining' : 'At term/Overdue' ?>
                                            </p>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-[10px] font-bold text-slate-300 uppercase italic">Pending Data</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="flex items-center gap-3">
                                        <div class="w-1.5 h-8 bg-sky-200 rounded-full relative overflow-hidden">
                                            <div class="absolute bottom-0 left-0 w-full bg-sky-500" style="height: <?= min(($woman['gestational_weeks'] / 40) * 100, 100) ?>%"></div>
                                        </div>
                                        <div>
                                            <p class="text-xs font-black text-slate-800"><?= $woman['gestational_weeks'] ?: '—' ?> Weeks</p>
                                            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">Development</p>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="flex items-center gap-2">
                                        <span class="px-2 py-1 bg-slate-100 rounded-lg text-[10px] font-black text-slate-600">G<?= $woman['gravida'] ?: '?' ?></span>
                                        <span class="px-2 py-1 bg-indigo-50 rounded-lg text-[10px] font-black text-indigo-600">P<?= $woman['para'] ?: '?' ?></span>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($woman['last_prenatal_visit']): ?>
                                        <p class="text-xs font-black text-slate-700"><?= date('M d', strtotime($woman['last_prenatal_visit'])) ?></p>
                                        <p class="text-[9px] font-bold text-slate-400 uppercase"><?= $woman['days_since_visit'] ?> days ago</p>
                                    <?php else: ?>
                                        <span class="px-2 py-0.5 bg-rose-50 text-[9px] font-black text-rose-500 rounded-full border border-rose-100 uppercase tracking-widest">No Record</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($isHighRisk): ?>
                                        <span class="px-3 py-1 bg-rose-50 text-[9px] font-black text-rose-600 rounded-lg border border-rose-100 uppercase tracking-widest flex items-center gap-1.5 w-fit">
                                            <span class="w-1 h-1 bg-rose-600 rounded-full animate-pulse"></span>
                                            High Priority
                                        </span>
                                    <?php else: ?>
                                        <span class="px-3 py-1 bg-emerald-50 text-[9px] font-black text-emerald-600 rounded-lg border border-emerald-100 uppercase tracking-widest flex items-center gap-1.5 w-fit">
                                            <span class="w-1 h-1 bg-emerald-600 rounded-full"></span>
                                            Stable
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button onclick="viewMother(<?= $woman['id'] ?>)" class="action-btn bg-health-50 text-health-600 hover:bg-health-600 hover:text-white" title="View Profile">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <a href="forms/prenatal_form.php?mother_id=<?= $woman['id'] ?>" class="action-btn bg-amber-50 text-amber-600 hover:bg-amber-600 hover:text-white" title="New Visit">
                                            <i class="fas fa-plus"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($pregnantWomen)): ?>
                            <tr>
                                <td colspan="7" class="py-20 text-center">
                                    <div class="float-animation inline-block mb-6">
                                        <div class="w-20 h-20 rounded-[2rem] bg-slate-100 flex items-center justify-center text-slate-300 text-3xl mx-auto border-2 border-dashed border-slate-200">
                                            <i class="fas fa-female"></i>
                                        </div>
                                    </div>
                                    <h3 class="text-lg font-black text-slate-800 mb-2">Registry Empty</h3>
                                    <p class="text-sm text-slate-400 font-medium max-w-xs mx-auto mb-8 leading-relaxed">No pregnant women currently detected in the system.</p>
                                    <a href="forms/mother_registration.php" class="inline-flex items-center gap-2 px-8 py-4 bg-health-600 hover:bg-health-700 text-white rounded-2xl font-black text-xs uppercase tracking-widest transition-all shadow-lg shadow-health-100 active:scale-95">
                                        <i class="fas fa-user-plus"></i> New Enrollment
                                    </a>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Details Modal -->
    <div class="modal fade" id="motherDetailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content !rounded-[3rem] border-none shadow-2xl overflow-hidden bg-slate-50">
                <div class="modal-header border-none p-8 pb-0 flex items-center justify-between no-print">
                    <div class="flex items-center gap-3">
                        <div class="w-1.5 h-6 bg-health-600 rounded-full"></div>
                        <h5 class="modal-title text-sm font-black text-slate-800 uppercase tracking-tighter">Clinical Intake Review</h5>
                    </div>
                    <button type="button" class="w-10 h-10 rounded-xl bg-slate-100 hover:bg-slate-200 flex items-center justify-center text-slate-500 transition-all active:scale-95" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body p-8 pt-6" id="motherDetailsContent">
                    <!-- Dynamic Content -->
                </div>
            </div>
        </div>
    </div>

    <!-- JS Dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        function viewMother(id) {
            const modal = new bootstrap.Modal(document.getElementById('motherDetailsModal'));
            const content = document.getElementById('motherDetailsContent');
            
            content.innerHTML = `
                <div class="flex flex-col items-center justify-center py-24 space-y-4">
                    <div class="w-16 h-16 border-4 border-health-500/20 border-t-health-600 rounded-full animate-spin"></div>
                    <p class="text-xs font-black text-slate-400 uppercase tracking-[0.2em] animate-pulse">Syncing Medical Profile...</p>
                </div>
            `;
            
            modal.show();

            fetch(`get_mother_details.php?id=${id}`)
                .then(response => response.text())
                .then(data => {
                    content.innerHTML = data;
                })
                .catch(error => {
                    content.innerHTML = `
                        <div class="p-8 text-center">
                            <i class="fas fa-exclamation-circle text-rose-500 text-3xl mb-4"></i>
                            <p class="text-slate-700 font-bold">Failed to load profile. Please try again.</p>
                        </div>
                    `;
                    console.error('Error:', error);
                });
        }

        // Search functionality
        document.getElementById('pregnantSearch').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('#pregnantTable tbody tr:not(:last-child)');
            
            rows.forEach(row => {
                const name = row.querySelector('p.text-sm').textContent.toLowerCase();
                const phone = row.querySelector('p.text-[10px]').textContent.toLowerCase();
                
                if (name.includes(searchTerm) || phone.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>
