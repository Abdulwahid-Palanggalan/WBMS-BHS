<?php
// get_postnatal_details.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$rootPath = __DIR__;
require_once $rootPath . '/includes/auth.php';
require_once $rootPath . '/includes/functions.php';

if (!isAuthorized(['admin', 'midwife'])) {
    http_response_code(403);
    echo '<div class="alert alert-danger">Unauthorized access.</div>';
    exit();
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    http_response_code(400);
    echo '<div class="alert alert-danger">Postnatal record ID is required.</div>';
    exit();
}

$recordId = intval($_GET['id']);

try {
    // Get postnatal record
    $stmt = $pdo->prepare("SELECT * FROM postnatal_records WHERE id = ?");
    $stmt->execute([$recordId]);
    $postnatalRecord = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$postnatalRecord) {
        http_response_code(404);
        echo '<div class="alert alert-danger">Postnatal record not found.</div>';
        exit();
    }
    
    // Get baby record
    $babyStmt = $pdo->prepare("SELECT * FROM birth_records WHERE id = ?");
    $babyStmt->execute([$postnatalRecord['baby_id']]);
    $babyRecord = $babyStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$babyRecord) {
        echo '<div class="alert alert-danger">Baby record not found.</div>';
        exit();
    }
    
    // Get mother record - CORRECTED: Get complete mother information
    $motherStmt = $pdo->prepare("SELECT first_name, middle_name, last_name, phone, address FROM mothers WHERE id = ?");
    $motherStmt->execute([$babyRecord['mother_id']]);
    $motherRecord = $motherStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$motherRecord) {
        $motherRecord = [
            'first_name' => null, 
            'middle_name' => null, 
            'last_name' => null, 
            'phone' => null, 
            'address' => null
        ];
    }
    
    // Get recorded by user
    $userStmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
    $userStmt->execute([$postnatalRecord['recorded_by']]);
    $userRecord = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    // Combine all data - CORRECTED: Use mother data from mothers table
    $record = array_merge($postnatalRecord, [
        'baby_first_name' => $babyRecord['first_name'],
        'baby_middle_name' => $babyRecord['middle_name'],
        'baby_last_name' => $babyRecord['last_name'],
        'birth_date' => $babyRecord['birth_date'],
        'birth_weight' => $babyRecord['birth_weight'],
        'gender' => $babyRecord['gender'],
        'mother_first_name' => $motherRecord['first_name'],
        'mother_middle_name' => $motherRecord['middle_name'],
        'mother_last_name' => $motherRecord['last_name'],
        'mother_phone' => $motherRecord['phone'],
        'mother_address' => $motherRecord['address'],
        'recorded_first_name' => $userRecord['first_name'] ?? '',
        'recorded_last_name' => $userRecord['last_name'] ?? ''
    ]);
    
    // Calculate days after birth
    $daysAfterBirth = '';
    if (!empty($record['visit_date']) && !empty($record['birth_date'])) {
        $daysAfterBirth = floor((strtotime($record['visit_date']) - strtotime($record['birth_date'])) / (60 * 60 * 24));
    }
    
    ?>

    <div class="space-y-6 animate-in fade-in slide-in-from-bottom-4 duration-700">
        <!-- Clinical Header & Actions -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-6 rounded-3xl border border-slate-100 shadow-sm no-print">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 rounded-2xl bg-health-50 flex items-center justify-center shrink-0">
                    <i class="fas fa-file-medical text-health-600 text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-xl font-bold text-slate-800">Postnatal Visit #<?= $record['visit_number'] ?></h2>
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mt-0.5">
                        Recorded on <?= date('M j, Y', strtotime($record['visit_date'])) ?>
                    </p>
                </div>
            </div>
            <button onclick="window.print()" class="flex items-center gap-2 px-6 py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-2xl font-bold text-sm transition-all active:scale-95">
                <i class="fas fa-print"></i>
                Print Clinical Record
            </button>
        </div>

        <!-- Metadata Grid -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <!-- Mother Card -->
            <div class="md:col-span-2 bg-gradient-to-br from-rose-50 to-white p-6 rounded-3xl border border-rose-100 shadow-sm relative overflow-hidden group">
                <div class="absolute -right-4 -top-4 w-24 h-24 bg-rose-500/5 rounded-full blur-2xl group-hover:bg-rose-500/10 transition-colors"></div>
                <div class="relative flex items-start gap-4">
                    <div class="bg-rose-500 text-white p-3 rounded-xl shadow-lg shadow-rose-200">
                        <i class="fas fa-venus text-lg"></i>
                    </div>
                    <div class="space-y-1">
                        <span class="text-[10px] font-black text-rose-400 uppercase tracking-widest leading-none">Mother's Information</span>
                        <h3 class="text-lg font-bold text-slate-800"><?= htmlspecialchars($record['mother_first_name'] . ' ' . $record['mother_last_name']) ?></h3>
                        <p class="text-sm text-slate-500 font-medium"><?= htmlspecialchars($record['mother_phone'] ?: 'No contact provided') ?></p>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-rose-100 flex items-center gap-2 text-xs text-rose-600 font-bold">
                    <i class="fas fa-map-marker-alt opacity-50"></i>
                    <span class="truncate"><?= htmlspecialchars($record['mother_address'] ?: 'No address specified') ?></span>
                </div>
            </div>

            <!-- Baby Card -->
            <div class="md:col-span-2 bg-gradient-to-br from-health-50 to-white p-6 rounded-3xl border border-health-100 shadow-sm relative overflow-hidden group">
                <div class="absolute -right-4 -top-4 w-24 h-24 bg-health-500/5 rounded-full blur-2xl group-hover:bg-health-500/10 transition-colors"></div>
                <div class="relative flex items-start gap-4">
                    <div class="bg-health-500 text-white p-3 rounded-xl shadow-lg shadow-health-200">
                        <i class="fas fa-baby text-lg"></i>
                    </div>
                    <div class="space-y-1">
                        <span class="text-[10px] font-black text-health-400 uppercase tracking-widest leading-none">Infant Profile</span>
                        <h3 class="text-lg font-bold text-slate-800"><?= htmlspecialchars($record['baby_first_name'] . ' ' . $record['baby_last_name']) ?></h3>
                        <p class="text-sm text-slate-500 font-medium"><?= ucfirst($record['gender']) ?> • Born <?= date('M j, Y', strtotime($record['birth_date'])) ?></p>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-health-100 flex items-center justify-between text-xs text-health-700 font-bold">
                    <span>Birth Weight: <?= $record['birth_weight'] ?> kg</span>
                    <span class="px-2 py-0.5 bg-white rounded-lg border border-health-100"><?= $daysAfterBirth ?> Days Postpartum</span>
                </div>
            </div>
        </div>

        <!-- Dual Column Health Matrix -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- COLUMN 1: Maternal Assessment -->
            <div class="space-y-6">
                <!-- Maternal Vitals Scorecard -->
                <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm space-y-4">
                    <div class="flex items-center gap-2 px-1">
                        <div class="w-1.5 h-4 bg-rose-500 rounded-full"></div>
                        <h3 class="text-sm font-black text-slate-800 uppercase tracking-tight">Maternal Vitals</h3>
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                        <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100 text-center">
                            <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-1">BP</span>
                            <span class="text-lg font-black text-slate-800"><?= htmlspecialchars($record['blood_pressure'] ?: '--/--') ?></span>
                            <p class="text-[9px] text-slate-400 font-medium">mmHg</p>
                        </div>
                        <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100 text-center">
                            <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-1">Weight</span>
                            <span class="text-lg font-black text-slate-800"><?= $record['weight'] ?: '--.-' ?></span>
                            <p class="text-[9px] text-slate-400 font-medium">kg</p>
                        </div>
                        <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100 text-center">
                            <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-1">Temp</span>
                            <span class="text-lg font-black text-slate-800"><?= $record['temperature'] ?: '--.-' ?></span>
                            <p class="text-[9px] text-slate-400 font-medium">°C</p>
                        </div>
                    </div>
                </div>

                <!-- Postpartum Clinical Panel -->
                <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm space-y-4">
                    <div class="flex items-center gap-2 px-1">
                        <div class="w-1.5 h-4 bg-health-600 rounded-full"></div>
                        <h3 class="text-sm font-black text-slate-800 uppercase tracking-tight">Postpartum Physical Appraisal</h3>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="space-y-4">
                            <!-- Uterus -->
                            <div class="flex items-center justify-between p-3 bg-slate-50 rounded-2xl">
                                <span class="text-xs font-bold text-slate-500">Uterine Involution</span>
                                <span class="px-3 py-1 bg-white rounded-xl text-xs font-black text-slate-800 border border-slate-100">
                                    <?= htmlspecialchars(ucfirst($record['uterus_status']) ?: 'Not Assessed') ?>
                                </span>
                            </div>
                            <!-- Lochia -->
                            <div class="flex items-center justify-between p-3 bg-slate-50 rounded-2xl">
                                <span class="text-xs font-bold text-slate-500">Lochia Characteristics</span>
                                <span class="px-3 py-1 bg-white rounded-xl text-xs font-black text-slate-800 border border-slate-100">
                                    <?= htmlspecialchars(ucfirst($record['lochia_status']) ?: 'Not Assessed') ?>
                                </span>
                            </div>
                        </div>
                        <div class="space-y-4">
                            <!-- Breasts -->
                            <div class="flex items-center justify-between p-3 bg-slate-50 rounded-2xl">
                                <span class="text-xs font-bold text-slate-500">Lactation/Breasts</span>
                                <span class="px-3 py-1 bg-white rounded-xl text-xs font-black text-slate-800 border border-slate-100">
                                    <?= htmlspecialchars(ucfirst($record['breasts_status']) ?: 'Not Assessed') ?>
                                </span>
                            </div>
                            <!-- Perineum -->
                            <div class="flex items-center justify-between p-3 bg-slate-50 rounded-2xl">
                                <span class="text-xs font-bold text-slate-500">Perineum/Wound</span>
                                <span class="px-3 py-1 bg-white rounded-xl text-xs font-black text-slate-800 border border-slate-100">
                                    <?= htmlspecialchars(ucfirst($record['perineum_status']) ?: 'Not Assessed') ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <!-- Emotional State -->
                    <div class="mt-4 p-4 bg-health-50 rounded-2xl border border-health-100">
                        <div class="flex items-center gap-3">
                            <i class="fas fa-smile text-health-600"></i>
                            <div>
                                <span class="text-[10px] font-black text-health-400 uppercase tracking-widest block leading-none">Emotional State</span>
                                <span class="text-sm font-bold text-slate-800"><?= htmlspecialchars(ucfirst(str_replace('-', ' ', $record['emotional_state'])) ?: 'Not Assessed') ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- COLUMN 2: Infant, Counseling & Management -->
            <div class="space-y-6">
                <!-- Infant Appraisal Scorecard -->
                <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm space-y-4">
                    <div class="flex items-center gap-2 px-1">
                        <div class="w-1.5 h-4 bg-health-400 rounded-full"></div>
                        <h3 class="text-sm font-black text-slate-800 uppercase tracking-tight">Infant Assessment</h3>
                    </div>
                    <div class="flex flex-col sm:flex-row gap-4">
                        <div class="flex-1 p-4 bg-health-50 rounded-2xl border border-health-100 flex items-center gap-4">
                            <div class="bg-white p-3 rounded-xl shadow-sm text-health-600">
                                <i class="fas fa-weight"></i>
                            </div>
                            <div>
                                <span class="text-[10px] font-black text-health-400 uppercase tracking-widest block leading-none">Current Weight</span>
                                <span class="text-lg font-black text-slate-800"><?= $record['baby_weight'] ?: '--.-' ?> <small class="text-[10px] text-slate-400">kg</small></span>
                            </div>
                        </div>
                        <div class="flex-1 p-4 bg-slate-50 rounded-2xl border border-slate-100 flex items-center gap-4">
                            <div class="bg-white p-3 rounded-xl shadow-sm text-indigo-500">
                                <i class="fas fa-apple-alt"></i>
                            </div>
                            <div>
                                <span class="text-[10px] font-black text-indigo-400 uppercase tracking-widest block leading-none">Feeding Method</span>
                                <span class="text-xs font-black text-slate-800 leading-tight"><?= htmlspecialchars(ucfirst(str_replace('-', ' ', $record['feeding_method'])) ?: 'Not Specified') ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Management & Counseling -->
                <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm space-y-6">
                    <!-- Complaints -->
                    <div class="space-y-2">
                        <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-2">
                            <i class="fas fa-exclamation-circle text-rose-500"></i> Complaints Reported
                        </span>
                        <div class="text-sm text-slate-700 leading-relaxed font-medium bg-slate-50 p-4 rounded-2xl">
                            <?= !empty($record['complaints']) ? nl2br(htmlspecialchars($record['complaints'])) : '<span class="italic text-slate-400">None reported during this visit.</span>' ?>
                        </div>
                    </div>
                    <!-- Management Plan -->
                    <div class="space-y-2">
                        <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-2">
                            <i class="fas fa-clipboard-check text-health-600"></i> Management/Treatment
                        </span>
                        <div class="text-sm text-slate-700 leading-relaxed font-medium bg-health-50 p-4 rounded-2xl border border-health-100">
                            <?= !empty($record['treatment']) ? nl2br(htmlspecialchars($record['treatment'])) : '<span class="italic text-slate-400">No specific treatment or medication provided.</span>' ?>
                        </div>
                    </div>
                    <!-- Counseling -->
                    <div class="space-y-2">
                        <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-2">
                            <i class="fas fa-graduation-cap text-indigo-500"></i> Counseling Provided
                        </span>
                        <div class="text-sm text-slate-700 font-bold bg-indigo-50/50 p-4 rounded-2xl border border-indigo-100/50">
                            <?= !empty($record['counseling_topics']) ? nl2br(htmlspecialchars($record['counseling_topics'])) : '<span class="italic text-slate-400">No counseling notes recorded.</span>' ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer: Next Steps -->
        <div class="flex flex-col md:flex-row gap-6 p-6 bg-slate-900 rounded-[2.5rem] shadow-xl relative overflow-hidden">
            <div class="absolute inset-0 bg-health-600 opacity-5"></div>
            <div class="relative flex-1 flex flex-col md:flex-row items-center gap-8">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-white/10 flex items-center justify-center text-white backdrop-blur-sm shadow-inner">
                        <i class="fas fa-calendar-alt text-xl"></i>
                    </div>
                    <div>
                        <span class="text-[10px] font-black text-white/40 uppercase tracking-widest block mb-0.5">Next Recommended Visit</span>
                        <span class="text-lg font-black text-white">
                            <?= !empty($record['next_visit_date']) && $record['next_visit_date'] != '0000-00-00' ? date('F j, Y', strtotime($record['next_visit_date'])) : 'Not Scheduled' ?>
                        </span>
                    </div>
                </div>
                <!-- Referral Logic -->
                <?php if ($record['referral_needed']): ?>
                    <div class="flex items-center gap-3 px-6 py-3 bg-rose-500/10 rounded-2xl border border-rose-500/20 backdrop-blur-sm shrink-0">
                        <i class="fas fa-hospital text-rose-400 animate-pulse"></i>
                        <span class="text-sm font-black text-rose-400 uppercase tracking-tight">Referral Required</span>
                    </div>
                <?php else: ?>
                    <div class="flex items-center gap-3 px-6 py-3 bg-emerald-500/10 rounded-2xl border border-emerald-500/20 backdrop-blur-sm shrink-0">
                        <i class="fas fa-check-circle text-emerald-400"></i>
                        <span class="text-sm font-black text-emerald-400 uppercase tracking-tight">Care Map Stabilized</span>
                    </div>
                <?php endif; ?>
            </div>
            <div class="relative flex items-center gap-4 text-white/60 text-[10px] font-bold italic border-l border-white/10 pl-6">
                <span>Certified By:</span>
                <span class="text-white not-italic uppercase tracking-widest"><?= htmlspecialchars($record['recorded_first_name'] . ' ' . $record['recorded_last_name']) ?></span>
            </div>
        </div>
    </div>

    <!-- Print-only Styles -->
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; margin: 0; padding: 20px; }
            .bg-slate-50, .bg-health-50, .bg-rose-50, .bg-indigo-50, .bg-white { background-color: white !important; }
            .border-slate-100, .border-health-100, .border-rose-100 { border-color: #eee !important; }
            .text-white { color: black !important; }
            .bg-slate-900 { background: #f8f9fa !important; color: black !important; border: 1px solid #ddd !important; }
            .bg-white\/10 { background: #eee !important; box-shadow: none !important; color: black !important; }
            .shadow-sm, .shadow-lg, .shadow-xl { shadow: none !important; box-shadow: none !important; }
            .animate-in { animation: none !important; }
            [class*="overflow-hidden"] { overflow: visible !important; }
            .md\:col-span-2 { grid-column: span 2 / span 2; }
            .rounded-3xl, .rounded-[2.5rem] { border-radius: 12px !important; }
        }
    </style>

    <?php
    
} catch (PDOException $e) {
    error_log("Database error in get_postnatal_details.php: " . $e->getMessage());
    echo '<div class="alert alert-danger p-6 rounded-3xl border border-rose-100 bg-rose-50 flex items-center gap-4">
            <i class="fas fa-database text-rose-500 text-xl"></i>
            <div><h4 class="font-black text-rose-900 uppercase text-xs mb-1">Database Error</h4><p class="text-rose-700 text-xs font-medium">' . htmlspecialchars($e->getMessage()) . '</p></div>
          </div>';
}
?>
