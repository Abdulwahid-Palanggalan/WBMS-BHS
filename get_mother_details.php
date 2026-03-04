<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session only if none is active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// CORRECTED PATH - adjust based on your actual file structure
$rootPath = __DIR__;
require_once $rootPath . '/config/config.php';

// FIX: Allow both admin and midwife
$allowedRoles = ['admin', 'midwife'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowedRoles)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

global $pdo;

if (isset($_GET['id']) && !empty($_GET['id'])) {
    $motherId = intval($_GET['id']);
    
    try {
        // Get complete mother details with ALL related information from new tables
        $query = "SELECT 
                    m.*, 
                    u.first_name, u.last_name, u.email, u.phone, u.created_at as registered_date,
                    pd.lmp, pd.edc, pd.gravida, pd.para, pd.abortions, pd.living_children,
                    pd.planned_pregnancy, pd.first_prenatal_visit, pd.referred_by,
                    hp.first_name as husband_first_name, hp.middle_name as husband_middle_name, 
                    hp.last_name as husband_last_name, hp.date_of_birth as husband_birthdate,
                    hp.occupation as husband_occupation, hp.education as husband_education,
                    hp.phone as husband_phone, hp.citizenship as husband_citizenship,
                    hp.religion as husband_religion, hp.marriage_date, hp.marriage_place,
                    mh.allergies, mh.medical_conditions, mh.previous_surgeries,
                    mh.family_history, mh.contraceptive_use, mh.previous_complications
                  FROM mothers m 
                  JOIN users u ON m.user_id = u.id 
                  LEFT JOIN pregnancy_details pd ON m.id = pd.mother_id
                  LEFT JOIN husband_partners hp ON m.id = hp.mother_id
                  LEFT JOIN medical_histories mh ON m.id = mh.mother_id
                  WHERE m.id = ?";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$motherId]);
        $mother = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$mother) {
            echo '<div class="alert alert-danger p-6 rounded-3xl border border-rose-100 bg-rose-50 flex items-center gap-4">
                    <i class="fas fa-user-slash text-rose-500 text-xl"></i>
                    <div><h4 class="font-black text-rose-900 uppercase text-xs mb-1">Not Found</h4><p class="text-rose-700 text-xs font-medium">Mother record not found.</p></div>
                  </div>';
            exit;
        }

        // Helper functions
        function calculateAge($birthDate) {
            if (empty($birthDate) || $birthDate == '0000-00-00') return 'N/A';
            return date_diff(date_create($birthDate), date_create('today'))->y;
        }

        function formatDate($date) {
            if (empty($date) || $date == '0000-00-00') return 'Not Set';
            return date('M j, Y', strtotime($date));
        }
?>

<div class="space-y-6 animate-in fade-in slide-in-from-bottom-4 duration-700">
    <!-- Profile Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 bg-white p-6 rounded-[2.5rem] border border-slate-100 shadow-sm no-print">
        <div class="flex items-center gap-5">
            <div class="w-16 h-16 rounded-3xl bg-health-600 text-white flex items-center justify-center text-2xl shadow-lg shadow-health-200">
                <i class="fas fa-female"></i>
            </div>
            <div>
                <h2 class="text-2xl font-black text-slate-800 tracking-tight"><?= htmlspecialchars($mother['first_name'] . ' ' . $mother['last_name']) ?></h2>
                <div class="flex flex-wrap items-center gap-3 mt-1">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-widest flex items-center gap-1.5 border-r border-slate-200 pr-3">
                        <i class="fas fa-id-card text-health-500"></i>
                        Clinical Profile #<?= str_pad($mother['id'], 5, '0', STR_PAD_LEFT) ?>
                    </span>
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-widest flex items-center gap-1.5 border-r border-slate-200 pr-3">
                        <i class="fas fa-calendar-alt text-health-500"></i>
                        Joined <?= date('M Y', strtotime($mother['registered_date'])) ?>
                    </span>
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-widest flex items-center gap-1.5 text-health-600">
                        <i class="fas fa-check-circle"></i>
                        Active Patient
                    </span>
                </div>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <button onclick="window.print()" class="flex items-center gap-2 px-6 py-3.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-2xl font-bold text-xs uppercase tracking-widest transition-all active:scale-95 group">
                <i class="fas fa-print group-hover:scale-110 transition-transform"></i>
                Print Clinical Profile
            </button>
            <a href="forms/mother_registration.php?edit=<?= $mother['id'] ?>" class="flex items-center gap-2 px-6 py-3.5 bg-health-600 hover:bg-health-700 text-white rounded-2xl font-bold text-xs uppercase tracking-widest transition-all shadow-lg shadow-health-100 active:scale-95 group">
                <i class="fas fa-edit group-hover:rotate-12 transition-transform"></i>
                Edit Information
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Clinical Content (Column 1 & 2) -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Pregnancy Profile (Obstetric Scorecard) -->
            <div class="bg-white p-8 rounded-[2rem] border border-slate-100 shadow-sm relative overflow-hidden group">
                <div class="absolute -right-6 -top-6 w-32 h-32 bg-health-600/5 rounded-full blur-3xl group-hover:bg-health-600/10 transition-colors"></div>
                <div class="relative space-y-6">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <div class="w-1.5 h-5 bg-health-600 rounded-full"></div>
                            <h3 class="text-sm font-black text-slate-800 uppercase tracking-tight">Obstetric Profile</h3>
                        </div>
                        <?php if (!empty($mother['edc'])): ?>
                            <span class="px-3 py-1 bg-health-50 rounded-lg text-[10px] font-black text-health-600 uppercase tracking-widest border border-health-100">
                                Estimated EDC: <?= formatDate($mother['edc']) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                        <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100 text-center">
                            <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-1">Gravida (G)</span>
                            <span class="text-2xl font-black text-slate-800 tracking-tight"><?= $mother['gravida'] ?: '0' ?></span>
                        </div>
                        <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100 text-center">
                            <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-1">Para (P)</span>
                            <span class="text-2xl font-black text-slate-800 tracking-tight"><?= $mother['para'] ?: '0' ?></span>
                        </div>
                        <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100 text-center">
                            <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-1">Abortion</span>
                            <span class="text-2xl font-black text-slate-800 tracking-tight"><?= $mother['abortions'] ?: '0' ?></span>
                        </div>
                        <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100 text-center">
                            <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-1">Living Children</span>
                            <span class="text-2xl font-black text-slate-800 tracking-tight"><?= $mother['living_children'] ?: '0' ?></span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="p-5 bg-health-50/50 rounded-2xl border border-health-100 flex items-center gap-4">
                            <div class="w-10 h-10 rounded-xl bg-white flex items-center justify-center text-health-600 shadow-sm">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <div>
                                <span class="text-[9px] font-black text-health-400 uppercase tracking-widest block leading-none">Last Menstrual Period</span>
                                <span class="text-sm font-bold text-slate-800"><?= formatDate($mother['lmp']) ?></span>
                            </div>
                        </div>
                        <div class="p-5 bg-indigo-50/50 rounded-2xl border border-indigo-100 flex items-center gap-4">
                            <div class="w-10 h-10 rounded-xl bg-white flex items-center justify-center text-indigo-500 shadow-sm">
                                <i class="fas fa-clinic-medical"></i>
                            </div>
                            <div>
                                <span class="text-[9px] font-black text-indigo-400 uppercase tracking-widest block leading-none">First Prenatal Visit</span>
                                <span class="text-sm font-bold text-slate-800"><?= formatDate($mother['first_prenatal_visit']) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Medical History Panel -->
            <div class="bg-white p-8 rounded-[2rem] border border-slate-100 shadow-sm space-y-6">
                <div class="flex items-center gap-2">
                    <div class="w-1.5 h-5 bg-rose-500 rounded-full"></div>
                    <h3 class="text-sm font-black text-slate-800 uppercase tracking-tight">Clinical History & Allergies</h3>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-4">
                        <div class="p-4 bg-rose-50 rounded-2xl border border-rose-100 flex items-start gap-4">
                            <div class="text-rose-500 mt-1"><i class="fas fa-exclamation-triangle"></i></div>
                            <div>
                                <span class="text-[10px] font-black text-rose-400 uppercase tracking-widest block mb-1">Known Allergies</span>
                                <p class="text-sm font-bold text-rose-900 leading-tight"><?= htmlspecialchars($mother['allergies'] ?: 'No known allergies reported') ?></p>
                            </div>
                        </div>
                        <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100 flex items-start gap-4">
                            <div class="text-slate-400 mt-1"><i class="fas fa-heartbeat"></i></div>
                            <div>
                                <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-1">Medical Conditions</span>
                                <p class="text-sm font-bold text-slate-800 leading-tight"><?= htmlspecialchars($mother['medical_conditions'] ?: 'No chronic conditions recorded') ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="space-y-4">
                        <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100 flex items-start gap-4">
                            <div class="text-slate-400 mt-1"><i class="fas fa-procedures"></i></div>
                            <div>
                                <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-1">Previous Surgeries</span>
                                <p class="text-sm font-bold text-slate-800 leading-tight"><?= htmlspecialchars($mother['previous_surgeries'] ?: 'No surgical history recorded') ?></p>
                            </div>
                        </div>
                        <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100 flex items-start gap-4">
                            <div class="text-slate-400 mt-1"><i class="fas fa-users-cog"></i></div>
                            <div>
                                <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-1">Family Medical History</span>
                                <p class="text-sm font-bold text-slate-800 leading-tight"><?= htmlspecialchars($mother['family_history'] ?: 'No family medical history provided') ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="p-5 bg-amber-50 rounded-2xl border border-amber-100 flex items-start gap-4">
                    <div class="text-amber-500 mt-1"><i class="fas fa-info-circle"></i></div>
                    <div>
                        <span class="text-[10px] font-black text-amber-500 uppercase tracking-widest block mb-1">Special Clinical Notes on Previous Complications</span>
                        <p class="text-sm font-bold text-amber-900 leading-relaxed"><?= htmlspecialchars($mother['previous_complications'] ?: 'No previous obstetric complications recorded.') ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar Details (Column 3) -->
        <div class="space-y-6">
            <!-- Personal Summary -->
            <div class="bg-slate-900 p-8 rounded-[2rem] shadow-xl relative overflow-hidden group">
                <div class="absolute inset-0 bg-health-600 opacity-10"></div>
                <div class="relative space-y-6">
                    <div class="flex items-center gap-2">
                        <div class="w-1.5 h-4 bg-health-500 rounded-full"></div>
                        <h3 class="text-sm font-black text-white/50 uppercase tracking-widest">Personal Summary</h3>
                    </div>
                    <div class="space-y-4">
                        <div class="flex justify-between items-center pb-3 border-b border-white/10">
                            <span class="text-xs font-bold text-white/40">Clinical Age</span>
                            <span class="text-sm font-black text-white"><?= calculateAge($mother['date_of_birth']) ?> Years Old</span>
                        </div>
                        <div class="flex justify-between items-center pb-3 border-b border-white/10">
                            <span class="text-xs font-bold text-white/40">Blood Profile</span>
                            <span class="text-sm font-black text-white"><?= $mother['blood_type'] ?: 'N/A' ?> <?= $mother['rh_factor'] ?: '' ?></span>
                        </div>
                        <div class="flex justify-between items-center pb-3 border-b border-white/10">
                            <span class="text-xs font-bold text-white/40">Civil Status</span>
                            <span class="text-sm font-black text-white"><?= htmlspecialchars($mother['civil_status'] ?: 'N/A') ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-xs font-bold text-white/40">Phone Number</span>
                            <span class="text-sm font-black text-white"><?= htmlspecialchars($mother['phone'] ?: 'N/A') ?></span>
                        </div>
                    </div>
                    <div class="mt-4 p-4 bg-white/5 rounded-2xl border border-white/10 flex items-start gap-3">
                        <i class="fas fa-map-marked-alt text-health-500 mt-1"></i>
                        <div>
                            <span class="text-[9px] font-black text-white/30 uppercase tracking-widest block mb-1">Permanent Address</span>
                            <p class="text-xs font-bold text-white/80 leading-snug"><?= htmlspecialchars($mother['address'] ?: 'No address specified') ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Partner Profile -->
            <div class="bg-white p-8 rounded-[2rem] border border-slate-100 shadow-sm relative group">
                <div class="space-y-5">
                    <div class="flex items-center gap-2">
                        <div class="w-1.5 h-4 bg-indigo-500 rounded-full"></div>
                        <h3 class="text-sm font-black text-slate-800 uppercase tracking-tight">Partner Information</h3>
                    </div>
                    <?php if (!empty($mother['husband_first_name'])): ?>
                    <div class="flex items-center gap-4 py-2">
                        <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-500 flex items-center justify-center text-xl">
                            <i class="fas fa-user-friends"></i>
                        </div>
                        <div>
                            <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest block leading-none">Legal Partner</span>
                            <h4 class="text-sm font-black text-slate-800"><?= htmlspecialchars($mother['husband_first_name'] . ' ' . $mother['husband_last_name']) ?></h4>
                        </div>
                    </div>
                    <div class="space-y-4 pt-2">
                        <div class="flex justify-between items-center text-xs font-bold">
                            <span class="text-slate-400">Occupation</span>
                            <span class="text-slate-800"><?= htmlspecialchars($mother['husband_occupation'] ?: 'N/A') ?></span>
                        </div>
                        <div class="flex justify-between items-center text-xs font-bold">
                            <span class="text-slate-400">Education</span>
                            <span class="text-slate-800"><?= htmlspecialchars($mother['husband_education'] ?: 'N/A') ?></span>
                        </div>
                        <div class="flex justify-between items-center text-xs font-bold">
                            <span class="text-slate-400">Contact</span>
                            <span class="text-slate-800"><?= htmlspecialchars($mother['husband_phone'] ?: 'N/A') ?></span>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="py-10 text-center">
                        <div class="w-12 h-12 bg-slate-50 flex items-center justify-center rounded-2xl mx-auto text-slate-300 mb-4">
                            <i class="fas fa-heart-broken text-lg"></i>
                        </div>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">No Partner Data Linked</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Emergency Contact Card -->
            <div class="bg-rose-50 p-6 rounded-[2rem] border border-rose-100 relative overflow-hidden group">
                <div class="relative flex items-start gap-4">
                    <div class="bg-rose-500 text-white p-3 rounded-xl shadow-lg shadow-rose-200">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div class="space-y-1">
                        <span class="text-[9px] font-black text-rose-400 uppercase tracking-widest block leading-none">Emergency Protocol</span>
                        <h4 class="text-sm font-black text-slate-800"><?= htmlspecialchars($mother['emergency_contact'] ?: 'Contact Not Specified') ?></h4>
                        <p class="text-xs font-black text-rose-500 tracking-widest"><?= htmlspecialchars($mother['emergency_phone'] ?: '--') ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Print-only Styles -->
<style>
    @media print {
        .no-print { display: none !important; }
        body { background: white !important; margin: 0; padding: 20px; }
        .bg-slate-50, .bg-health-50, .bg-rose-50, .bg-indigo-50, .bg-white, .bg-amber-50 { background-color: white !important; border: 1px solid #eee !important; }
        .bg-slate-900 { background: #f8f9fa !important; border: 1px solid #ddd !important; }
        .bg-slate-900 span, .bg-slate-900 p, .bg-slate-900 h2, .bg-slate-900 h3, .bg-slate-900 h4 { color: black !important; }
        .border-slate-100, .border-health-100, .border-rose-100, .border-indigo-100 { border-color: #eee !important; }
        .text-white { color: black !important; }
        .shadow-sm, .shadow-lg, .shadow-xl { shadow: none !important; box-shadow: none !important; }
        .animate-in { animation: none !important; }
        [class*="overflow-hidden"] { overflow: visible !important; }
        .grid { display: block !important; }
        .grid > div { margin-bottom: 20px; page-break-inside: avoid; }
        .rounded-3xl, .rounded-[2.5rem], .rounded-[2rem] { border-radius: 12px !important; }
    }
</style>

<?php
    } catch (PDOException $e) {
        error_log("Database error in get_mother_details: " . $e->getMessage());
        echo '<div class="alert alert-danger p-6 rounded-3xl border border-rose-100 bg-rose-50 flex items-center gap-4">
                <i class="fas fa-database text-rose-500 text-xl"></i>
                <div><h4 class="font-black text-rose-900 uppercase text-xs mb-1">Database Error</h4><p class="text-rose-700 text-xs font-medium">' . htmlspecialchars($e->getMessage()) . '</p></div>
              </div>';
    }
} else {
    echo '<div class="alert alert-danger p-6 rounded-3xl border border-rose-100 bg-rose-50 flex items-center gap-4">
            <i class="fas fa-search text-rose-500 text-xl"></i>
            <div><h4 class="font-black text-rose-900 uppercase text-xs mb-1">Invalid Request</h4><p class="text-rose-700 text-xs font-medium">No valid mother ID provided for clinical profile retrieval.</p></div>
          </div>';
}
?>
