<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isAuthorized(['mother'])) {
    header("Location: ../login.php");
    exit();
}

redirectIfNotLoggedIn();

global $pdo;

$userId = $_SESSION['user_id'];

// Get mother's details
$mother = $pdo->prepare("
    SELECT m.*, u.first_name, u.last_name, u.email, u.phone 
    FROM mothers m 
    JOIN users u ON m.user_id = u.id 
    WHERE m.user_id = ?
");
$mother->execute([$userId]);
$motherData = $mother->fetch(PDO::FETCH_ASSOC);

if (!$motherData) {
    $showRegistrationPrompt = true;
    $mother = [
        'first_name' => $_SESSION['first_name'],
        'last_name' => $_SESSION['last_name'],
        'id' => null
    ];
} else {
    $mother = $motherData;
    $showRegistrationPrompt = false;
    
    // Check pregnancy status
    $pregnancyStmt = $pdo->prepare("
        SELECT lmp, edc, gravida, para 
        FROM pregnancy_details 
        WHERE mother_id = ? 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $pregnancyStmt->execute([$mother['id']]);
    $pregnancyData = $pregnancyStmt->fetch(PDO::FETCH_ASSOC);
    
    $isPregnant = false;
    $weeksPregnant = 0;
    if ($pregnancyData && !empty($pregnancyData['lmp'])) {
        $lmp = new DateTime($pregnancyData['lmp']);
        $now = new DateTime();
        $diff = $lmp->diff($now);
        $weeksPregnant = floor($diff->days / 7);
        if ($weeksPregnant <= 42) $isPregnant = true;
    }

    // Stats
    $prenatalCount = $pdo->query("SELECT COUNT(*) FROM prenatal_records WHERE mother_id = " . (int)$mother['id'])->fetchColumn() ?: 0;
    
    $birthRecords = $pdo->prepare("SELECT * FROM birth_records WHERE mother_id = ? ORDER BY birth_date DESC");
    $birthRecords->execute([$mother['id']]);
    $birthRecords = $birthRecords->fetchAll(PDO::FETCH_ASSOC);

    $postnatalCount = $pdo->prepare("
        SELECT COUNT(*) FROM postnatal_records pr 
        JOIN birth_records br ON pr.baby_id = br.id 
        WHERE br.mother_id = ?
    ");
    $postnatalCount->execute([$mother['id']]);
    $postnatalCount = $postnatalCount->fetchColumn() ?: 0;

    $nextAppointment = null;
    if ($isPregnant) {
        $nextAppStmt = $pdo->prepare("
            SELECT * FROM prenatal_records 
            WHERE mother_id = ? AND visit_date >= CURDATE() 
            ORDER BY visit_date ASC 
            LIMIT 1
        ");
        $nextAppStmt->execute([$mother['id']]);
        $nextAppointment = $nextAppStmt->fetch(PDO::FETCH_ASSOC);
    }
}

$baseUrl = $GLOBALS['base_url'] ?? '';
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mother's Portal - Health Station System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php include_once __DIR__ . '/../includes/tailwind_config.php'; ?>
    <style type="text/tailwindcss">
        @layer components {
            .btn-sos {
                @apply bg-rose-600 text-white font-bold py-2.5 px-6 rounded-full shadow-lg shadow-rose-200 hover:bg-rose-700 hover:-translate-y-0.5 transition-all text-sm uppercase tracking-wider flex items-center gap-2;
            }
            .timeline-step {
                @apply relative flex flex-col items-center flex-1;
            }
            .timeline-dot {
                @apply w-4 h-4 rounded-full border-4 border-white shadow-md z-10 transition-all;
            }
            .timeline-dot-active { @apply bg-health-600 scale-125; }
            .timeline-dot-inactive { @apply bg-slate-200; }
            .timeline-dot-completed { @apply bg-emerald-500 scale-110; }
        }
    </style>
</head>
<body class="bg-slate-50 min-h-full">
    <?php include_once __DIR__ . '/../includes/header.php'; ?>
    
    <div class="flex flex-col lg:flex-row min-h-[calc(100vh-4rem)]">
        <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
        
        <main class="flex-1 p-4 lg:p-8 space-y-8">
            <!-- Registration Alert -->
            <?php if ($showRegistrationPrompt): ?>
            <div class="bg-health-600 rounded-2xl p-6 shadow-xl shadow-health-100 flex flex-col md:flex-row items-center gap-6 text-white overflow-hidden relative">
                <div class="absolute top-0 right-0 -translate-y-1/2 translate-x-1/4 opacity-10">
                    <i class="fas fa-sparkles text-[120px]"></i>
                </div>
                <div class="bg-white/20 p-4 rounded-3xl backdrop-blur-md">
                    <i class="fas fa-sparkles text-3xl"></i>
                </div>
                <div class="flex-1 text-center md:text-left z-10">
                    <h3 class="text-xl font-bold">Welcome to your Health Portal!</h3>
                    <p class="text-health-50 mt-1">Complete your profile to unlock pregnancy tracking, digital records, and health reminders.</p>
                </div>
                <a href="<?= $baseUrl ?>/forms/mother_self_registration.php" class="bg-white text-health-600 font-bold px-6 py-3 rounded-xl hover:bg-health-50 transition-colors shadow-lg z-10">
                    Complete Profile Now
                </a>
            </div>
            <?php endif; ?>

            <!-- Portal Header -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">Hello, <?= htmlspecialchars($mother['first_name']); ?>! 👋</h1>
                    <p class="text-slate-500 text-sm mt-1">Your personal health companion at Barangay Kibenes.</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <button class="btn-sos group w-full md:w-auto justify-center" id="sosTrigger">
                        <i class="fas fa-exclamation-triangle animate-pulse text-amber-300"></i>
                        <span>SOS Emergency</span>
                    </button>
                    <?php if ($isPregnant): ?>
                    <div class="bg-emerald-50 text-emerald-700 px-4 py-2 rounded-full font-bold flex items-center gap-2 border border-emerald-100 shadow-sm">
                        <i class="fas fa-baby"></i>
                        <span class="text-sm"><?= $weeksPregnant; ?> Weeks Pregnant</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Bento Stats -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="card-health p-6 flex items-center gap-5 border-t-4 border-health-600">
                    <div class="w-12 h-12 bg-health-50 text-health-600 rounded-2xl flex items-center justify-center text-xl">
                        <i class="fas fa-children"></i>
                    </div>
                    <div>
                        <h3 class="text-2xl font-bold text-slate-900 leading-none"><?= count($birthRecords); ?></h3>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mt-1">My Children</p>
                    </div>
                </div>
                
                <div class="card-health p-6 flex items-center gap-5 border-t-4 border-sky-600">
                    <div class="w-12 h-12 bg-sky-50 text-sky-600 rounded-2xl flex items-center justify-center text-xl">
                        <i class="fas fa-clipboard-heart"></i>
                    </div>
                    <div>
                        <h3 class="text-2xl font-bold text-slate-900 leading-none"><?= $prenatalCount; ?></h3>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mt-1">Prenatal Visits</p>
                    </div>
                </div>

                <div class="card-health p-6 flex items-center gap-5 border-t-4 border-amber-600">
                    <div class="w-12 h-12 bg-amber-50 text-amber-600 rounded-2xl flex items-center justify-center text-xl">
                        <i class="fas fa-house-medical-check"></i>
                    </div>
                    <div>
                        <h3 class="text-2xl font-bold text-slate-900 leading-none"><?= $postnatalCount; ?></h3>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mt-1">Postnatal Checks</p>
                    </div>
                </div>

                <div class="card-health p-6 flex items-center gap-5 border-t-4 border-rose-600">
                    <div class="w-12 h-12 bg-rose-50 text-rose-600 rounded-2xl flex items-center justify-center text-xl">
                        <i class="fas fa-bell"></i>
                    </div>
                    <div>
                        <h3 class="text-2xl font-bold text-slate-900 leading-none"><?= $nextAppointment ? '1' : '0'; ?></h3>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mt-1">Reminders</p>
                    </div>
                </div>
            </div>

            <!-- Dashboard Content Layout -->
            <div class="grid grid-cols-1 xl:grid-cols-12 gap-8">
                <!-- Main Journey Column -->
                <div class="xl:col-span-8 space-y-8">
                    <!-- Pregnancy Timeline -->
                    <div class="card-health p-8 pb-10">
                        <div class="flex items-center justify-between mb-10">
                            <div>
                                <h3 class="text-lg font-bold text-slate-800">Health Journey Timeline</h3>
                                <p class="text-xs text-slate-400 font-bold uppercase tracking-widest mt-0.5">Pregnancy Progress Tracker</p>
                            </div>
                            <?php if ($isPregnant): ?>
                                <span class="bg-indigo-50 text-indigo-600 px-3 py-1 rounded-lg text-[10px] font-bold uppercase tracking-wider border border-indigo-100 italic">Day by day, step by step</span>
                            <?php endif; ?>
                        </div>

                        <?php if ($isPregnant): ?>
                        <div class="relative pt-4">
                            <!-- Timeline Background Line -->
                            <div class="absolute top-6 left-0 right-0 h-1 bg-slate-100 rounded-full"></div>
                            <!-- Active Progress Line -->
                            <div class="absolute top-6 left-0 h-1 bg-health-600 rounded-full transition-all duration-1000" style="width: <?= min(100, ($weeksPregnant / 40) * 100); ?>%;"></div>
                            
                            <div class="flex justify-between items-start gap-2">
                                <div class="timeline-step">
                                    <div class="timeline-dot timeline-dot-completed"></div>
                                    <div class="mt-4 text-center">
                                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-tighter">LMP</span>
                                        <div class="text-[11px] font-bold text-slate-800"><?= date('M j', strtotime($pregnancyData['lmp'])); ?></div>
                                    </div>
                                </div>
                                <div class="timeline-step">
                                    <div class="timeline-dot <?= $weeksPregnant >= 12 ? 'timeline-dot-completed' : ($weeksPregnant >= 1 ? 'timeline-dot-active' : 'timeline-dot-inactive'); ?>"></div>
                                    <div class="mt-4 text-center">
                                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-tighter">Tri 1</span>
                                        <div class="text-[11px] font-bold text-slate-800">Wk 12</div>
                                    </div>
                                </div>
                                <div class="timeline-step">
                                    <div class="timeline-dot <?= $weeksPregnant >= 26 ? 'timeline-dot-completed' : ($weeksPregnant >= 13 ? 'timeline-dot-active' : 'timeline-dot-inactive'); ?>"></div>
                                    <div class="mt-4 text-center">
                                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-tighter">Tri 2</span>
                                        <div class="text-[11px] font-bold text-slate-800">Wk 26</div>
                                    </div>
                                </div>
                                <div class="timeline-step">
                                    <div class="timeline-dot <?= $weeksPregnant >= 40 ? 'timeline-dot-completed' : ($weeksPregnant >= 27 ? 'timeline-dot-active' : 'timeline-dot-inactive'); ?>"></div>
                                    <div class="mt-4 text-center">
                                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-tighter">Tri 3</span>
                                        <div class="text-[11px] font-bold text-slate-800">Wk 40</div>
                                    </div>
                                </div>
                                <div class="timeline-step">
                                    <div class="timeline-dot timeline-dot-inactive"></div>
                                    <div class="mt-4 text-center">
                                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-tighter">EDC</span>
                                        <div class="text-[11px] font-bold text-slate-800"><?= date('M j', strtotime($pregnancyData['edc'])); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-12 bg-slate-900 rounded-2xl p-5 flex items-center gap-5 shadow-inner">
                            <div class="w-12 h-12 bg-health-600 rounded-full flex items-center justify-center text-white shrink-0">
                                <i class="fas fa-heart-pulse"></i>
                            </div>
                            <div class="text-white">
                                <p class="text-xs opacity-60 font-bold uppercase tracking-widest leading-none mb-1 text-health-400">Current Status</p>
                                <p class="text-sm font-medium">You are currently in <strong>Trimester <?= $weeksPregnant <= 12 ? '1' : ($weeksPregnant <= 26 ? '2' : '3'); ?></strong>. Your baby is growing day by day!</p>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="py-16 text-center">
                            <i class="fas fa-heart-pulse text-6xl text-slate-100 mb-4 scale-110"></i>
                            <p class="text-slate-400 font-bold uppercase tracking-[0.2em] text-xs">No active pregnancy records found</p>
                            <p class="text-slate-300 text-xs mt-2">Update your status in settings to enable the tracker.</p>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- My Children Grid -->
                    <div class="card-health overflow-hidden">
                        <div class="px-8 py-6 flex items-center justify-between bg-white border-b border-slate-100">
                            <h3 class="text-lg font-bold text-slate-800">My Children</h3>
                            <a href="<?= $baseUrl ?>/forms/birth_registration.php" class="text-xs font-bold text-health-600 hover:text-health-700 uppercase tracking-widest flex items-center gap-1.5 transition-colors">
                                <i class="fas fa-plus-circle"></i> Add Record
                            </a>
                        </div>
                        <div class="p-8">
                            <?php if (!empty($birthRecords)): ?>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <?php foreach ($birthRecords as $record): ?>
                                        <div class="group p-5 rounded-2xl border border-slate-100 hover:border-health-200 hover:shadow-xl hover:shadow-health-100/30 transition-all duration-300 bg-white">
                                            <div class="flex items-center gap-4 mb-4">
                                                <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-xl shadow-soft <?php echo $record['gender'] == 'male' ? 'bg-sky-50 text-sky-600' : 'bg-rose-50 text-rose-600'; ?>">
                                                    <i class="fas fa-baby"></i>
                                                </div>
                                                <div class="flex-1">
                                                    <h4 class="font-bold text-slate-900 leading-none"><?= htmlspecialchars($record['first_name']); ?></h4>
                                                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mt-1"><?= date('F d, Y', strtotime($record['birth_date'])); ?></p>
                                                </div>
                                            </div>
                                            <div class="grid grid-cols-2 gap-3">
                                                <div class="px-3 py-2 bg-slate-50 rounded-xl">
                                                    <span class="text-[9px] font-bold text-slate-400 uppercase block tracking-tighter">Gender</span>
                                                    <span class="text-xs font-bold text-slate-700"><?= ucfirst($record['gender']); ?></span>
                                                </div>
                                                <div class="px-3 py-2 bg-slate-50 rounded-xl text-right">
                                                    <span class="text-[9px] font-bold text-slate-400 uppercase block tracking-tighter">Weight</span>
                                                    <span class="text-xs font-bold text-slate-700"><?= $record['birth_weight'] ?> kg</span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="py-16 text-center space-y-4 border-2 border-dashed border-slate-100 rounded-2xl">
                                    <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center text-slate-200 text-2xl mx-auto">
                                        <i class="fas fa-children"></i>
                                    </div>
                                    <p class="text-slate-400 font-bold text-xs uppercase tracking-widest">No registered children records found</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Digital ID Column -->
                <div class="xl:col-span-4 space-y-8">
                    <!-- Digital Health ID Card -->
                    <div class="group perspective">
                        <div class="relative bg-slate-900 rounded-[2.5rem] p-8 shadow-2xl shadow-slate-300 overflow-hidden transform group-hover:rotate-y-6 transition-all duration-700 min-h-[300px] flex flex-col items-center justify-center">
                            <!-- Background Decorations -->
                            <div class="absolute -top-10 -right-10 w-40 h-40 bg-health-600/20 blur-[80px] rounded-full"></div>
                            <div class="absolute -bottom-10 -left-10 w-40 h-40 bg-indigo-600/20 blur-[80px] rounded-full"></div>
                            
                            <div class="relative w-full flex flex-col h-full justify-between items-center text-center">
                                <div class="flex flex-col items-center gap-4 mb-8">
                                    <div class="bg-white p-2.5 rounded-[1.5rem] shadow-xl shadow-slate-900/50 scale-110">
                                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=MOTHER-<?= $mother['id'] ?>&color=0d9488" alt="QR ID" class="w-24 h-24">
                                    </div>
                                    <div>
                                        <h6 class="text-[10px] font-bold text-health-400 uppercase tracking-[0.3em] mb-1">Electronic Health Identity</h6>
                                        <h4 class="text-xl font-black text-white tracking-widest">ID: BHS-<?= str_pad($mother['id'] ?? 0, 5, '0', STR_PAD_LEFT) ?></h4>
                                    </div>
                                </div>
                                
                                <div class="space-y-1">
                                    <h5 class="text-xl font-bold text-white"><?= htmlspecialchars($mother['first_name'] . ' ' . $mother['last_name']) ?></h5>
                                    <p class="text-xs text-slate-400 font-medium tracking-wide"><?= htmlspecialchars($mother['phone'] ?? 'Phone not provided') ?></p>
                                </div>

                                <div class="mt-10 pt-6 border-t border-white/5 w-full flex items-center justify-between">
                                    <div class="text-[9px] font-bold text-slate-500 uppercase tracking-widest text-left">
                                        Barangay Kibenes<br>Health Station
                                    </div>
                                    <i class="fas fa-shield-heart text-health-600/50 text-2xl group-hover:scale-125 transition-transform duration-500"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Side Record Links -->
                    <div class="card-health p-2 space-y-1">
                        <button class="w-full p-4 rounded-3xl hover:bg-slate-50 transition-all flex items-center gap-4 group" onclick="document.getElementById('prenatalModalTrigger').click()">
                            <div class="w-11 h-11 bg-health-50 text-health-600 rounded-2xl flex items-center justify-center text-lg group-hover:bg-health-600 group-hover:text-white transition-all">
                                <i class="fas fa-file-medical"></i>
                            </div>
                            <div class="text-left flex-1">
                                <h4 class="text-sm font-bold text-slate-800">Prenatal Records</h4>
                                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider"><?= $prenatalCount; ?> visits found</p>
                            </div>
                            <i class="fas fa-chevron-right text-slate-200 text-xs group-hover:translate-x-1 transition-transform"></i>
                        </button>

                        <button class="w-full p-4 rounded-3xl hover:bg-slate-50 transition-all flex items-center gap-4 group" onclick="document.getElementById('postnatalModalTrigger').click()">
                            <div class="w-11 h-11 bg-sky-50 text-sky-600 rounded-2xl flex items-center justify-center text-lg group-hover:bg-sky-600 group-hover:text-white transition-all">
                                <i class="fas fa-file-prescription"></i>
                            </div>
                            <div class="text-left flex-1">
                                <h4 class="text-sm font-bold text-slate-800">Postnatal Checks</h4>
                                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider"><?= $postnatalCount; ?> checks found</p>
                            </div>
                            <i class="fas fa-chevron-right text-slate-200 text-xs group-hover:translate-x-1 transition-transform"></i>
                        </button>

                        <a href="<?= $baseUrl ?>/library.php" class="w-full p-4 rounded-3xl hover:bg-slate-50 transition-all flex items-center gap-4 group">
                            <div class="w-11 h-11 bg-amber-50 text-amber-600 rounded-2xl flex items-center justify-center text-lg group-hover:bg-amber-600 group-hover:text-white transition-all">
                                <i class="fas fa-book-medical"></i>
                            </div>
                            <div class="text-left flex-1">
                                <h4 class="text-sm font-bold text-slate-800">Health Library</h4>
                                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Tips for Moms</p>
                            </div>
                            <i class="fas fa-chevron-right text-slate-200 text-xs group-hover:translate-x-1 transition-transform"></i>
                        </a>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Hidden triggers for existing modals -->
    <button id="prenatalModalTrigger" data-bs-toggle="modal" data-bs-target="#prenatalModal" class="hidden"></button>
    <button id="postnatalModalTrigger" data-bs-toggle="modal" data-bs-target="#postnatalModal" class="hidden"></button>

    <!-- Modals (Backwards compatibility with Bootstrap JS for now) -->
    <div class="modal fade" id="prenatalModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content overflow-hidden border-0 rounded-[2rem] shadow-2xl"></div>
        </div>
    </div>
    <div class="modal fade" id="postnatalModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content overflow-hidden border-0 rounded-[2rem] shadow-2xl"></div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // AJAX Loaders for Modals
            const loadModal = (id, url) => {
                document.getElementById(id).addEventListener('show.bs.modal', function() {
                    const modalContent = this.querySelector('.modal-content');
                    modalContent.innerHTML = '<div class="p-20 flex flex-col items-center justify-center gap-4"><div class="w-10 h-10 border-4 border-health-600/20 border-t-health-600 rounded-full animate-spin"></div><p class="text-xs font-bold text-slate-400 uppercase tracking-widest">Loading Records...</p></div>';
                    fetch(url).then(r => r.text()).then(d => modalContent.innerHTML = d);
                });
            };
            loadModal('prenatalModal', '<?= $baseUrl ?>/ajax/get_mother_prenatal_records.php');
            loadModal('postnatalModal', '<?= $baseUrl ?>/ajax/get_mother_postnatal_records.php');

            // SOS Handler
            document.getElementById('sosTrigger').addEventListener('click', function() {
                Swal.fire({
                    title: 'Trigger Emergency SOS?',
                    text: "The midwife will receive your location and status immediately.",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#e11d48',
                    cancelButtonColor: '#94a3b8',
                    confirmButtonText: 'Yes, Send SOS!',
                    padding: '2rem',
                    customClass: {
                        popup: 'rounded-[2rem]',
                        confirmButton: 'rounded-xl font-bold py-3 px-6 px-10',
                        cancelButton: 'rounded-xl font-bold py-3 px-6'
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        navigator.geolocation.getCurrentPosition(position => {
                            sendSos(`${position.coords.latitude}, ${position.coords.longitude}`);
                        }, () => {
                            sendSos('Location access denied');
                        });
                    }
                });
            });

            function sendSos(location) {
                fetch('<?= $baseUrl ?>/ajax/trigger_sos.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `alert_type=Urgent SOS&location=${encodeURIComponent(location)}`
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            title: 'SOS Sent!',
                            text: data.message,
                            icon: 'success',
                            customClass: { popup: 'rounded-[2rem]' }
                        });
                    } else {
                        Swal.fire({
                            title: 'Error',
                            text: data.message,
                            icon: 'error',
                            customClass: { popup: 'rounded-[2rem]' }
                        });
                    }
                });
            }
        });
    </script>
</body>
</html>