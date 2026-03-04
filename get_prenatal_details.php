<?php
/**
 * get_prenatal_details.php
 * Premium clinical dashboard UI for prenatal record details
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$rootPath = __DIR__;
require_once $rootPath . '/includes/auth.php';
require_once $rootPath . '/includes/functions.php';

// Authorization check
if (!isAuthorized(['admin', 'midwife'])) {
    http_response_code(403);
    echo '<div class="p-12 text-center bg-rose-50 rounded-[2.5rem] border border-rose-100">
            <div class="w-20 h-20 bg-rose-100 text-rose-500 rounded-3xl flex items-center justify-center text-3xl mx-auto mb-6 shadow-lg shadow-rose-100">
                <i class="fas fa-lock"></i>
            </div>
            <h3 class="text-xl font-black text-rose-900 mb-2">Access Denied</h3>
            <p class="text-sm text-rose-600 font-medium">You are not authorized to view clinical details.</p>
          </div>';
    exit();
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    http_response_code(400);
    echo '<div class="p-12 text-center bg-amber-50 rounded-[2.5rem] border border-amber-100">
            <div class="w-20 h-20 bg-amber-100 text-amber-500 rounded-3xl flex items-center justify-center text-3xl mx-auto mb-6 shadow-lg shadow-amber-100">
                <i class="fas fa-circle-exclamation"></i>
            </div>
            <h3 class="text-xl font-black text-amber-900 mb-2">Invalid Request</h3>
            <p class="text-sm text-amber-600 font-medium">The requested record identifier is missing.</p>
          </div>';
    exit();
}

$recordId = intval($_GET['id']);

try {
    // 1. Fetch Primary Record
    $stmt = $pdo->prepare("
        SELECT pr.*,
               m.id as mother_db_id, m.first_name as mother_first_name, m.last_name as mother_last_name,
               m.phone as mother_phone, m.email as mother_email, m.address, m.blood_type, m.rh_factor,
               pd.edc, pd.lmp, pd.gravida, pd.para, pd.living_children, pd.abortions,
               u_recorder.first_name as recorded_first_name, u_recorder.last_name as recorded_last_name
        FROM prenatal_records pr
        JOIN mothers m ON pr.mother_id = m.id
        LEFT JOIN pregnancy_details pd ON m.id = pd.mother_id
        LEFT JOIN users u_recorder ON pr.recorded_by = u_recorder.id
        WHERE pr.id = ?
    ");
    $stmt->execute([$recordId]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$record) {
        throw new Exception("Record with ID #PR-{$recordId} not found in our registry.");
    }

    // 2. Fetch History Summary (for Timeline/Context)
    $historyStmt = $pdo->prepare("
        SELECT id, visit_date, visit_number, weight, blood_pressure
        FROM prenatal_records 
        WHERE mother_id = ? 
        ORDER BY visit_date DESC 
        LIMIT 5
    ");
    $historyStmt->execute([$record['mother_id']]);
    $visitHistory = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Logic & Formatting
    $patientName = htmlspecialchars($record['mother_first_name'] . ' ' . $record['mother_last_name']);
    $initials = strtoupper(substr($record['mother_first_name'], 0, 1) . substr($record['mother_last_name'], 0, 1));
    
    // Gestational Age (AOG) Calculation
    $aogDisplay = 'N/A';
    if (!empty($record['lmp']) && $record['lmp'] !== '0000-00-00') {
        $lmp = new DateTime($record['lmp']);
        $visit = new DateTime($record['visit_date']);
        $diff = $lmp->diff($visit);
        $weeks = floor($diff->days / 7);
        $days = $diff->days % 7;
        $aogDisplay = "{$weeks}w {$days}d";
    }

?>

<!-- Details Container (Will be injected into Modal Body) -->
<div class="space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
    
    <!-- A. CLINICAL BANNER -->
    <div class="relative overflow-hidden bg-slate-900 rounded-[2.5rem] p-8 text-white shadow-2xl shadow-slate-200">
        <!-- Abstract Background Shape -->
        <div class="absolute -right-10 -top-10 w-64 h-64 bg-health-500/10 rounded-full blur-3xl"></div>
        <div class="absolute -left-10 -bottom-10 w-48 h-48 bg-sky-500/10 rounded-full blur-2xl"></div>
        
        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div class="flex items-center gap-6">
                <!-- Avatar -->
                <div class="w-16 h-16 md:w-20 md:h-20 rounded-[1.8rem] bg-gradient-to-br from-health-400 to-health-600 p-0.5 shadow-lg shadow-health-950/20 flex-shrink-0">
                    <div class="w-full h-full bg-slate-900/40 backdrop-blur-xl rounded-[1.7rem] flex items-center justify-center text-2xl font-black text-white">
                        <?= $initials ?>
                    </div>
                </div>
                <!-- Identity -->
                <div>
                    <div class="flex items-center gap-3 mb-1">
                        <span class="px-2.5 py-1 rounded-full bg-health-500/20 border border-health-500/30 text-[9px] font-black uppercase tracking-widest text-health-400">Prenatal Visit</span>
                        <span class="text-[10px] font-bold text-slate-500 tracking-widest">#PR-<?= $record['id'] ?></span>
                    </div>
                    <h2 class="text-2xl md:text-3xl font-black tracking-tight leading-none mb-2"><?= $patientName ?></h2>
                    <div class="flex flex-wrap items-center gap-x-5 gap-y-2 opacity-60">
                        <span class="text-sm font-medium flex items-center gap-2">
                            <i class="fas fa-calendar-alt text-health-400"></i>
                            <?= date('M d, Y', strtotime($record['visit_date'])) ?>
                        </span>
                        <span class="text-sm font-medium flex items-center gap-2">
                            <i class="fas fa-hashtag text-health-400"></i>
                            Visit Number <?= $record['visit_number'] ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Quick Stats in Banner -->
            <div class="flex items-center gap-3 md:text-right">
                <div class="bg-white/5 border border-white/10 rounded-2xl p-4 backdrop-blur-md">
                    <p class="text-[9px] font-black uppercase tracking-widest text-health-400 mb-1">AOG Result</p>
                    <p class="text-xl font-black text-white"><?= $aogDisplay ?></p>
                </div>
                <button onclick="window.print()" class="w-12 h-12 rounded-2xl bg-white/10 hover:bg-white/20 border border-white/10 flex items-center justify-center text-white transition-all active:scale-90" title="Print Record">
                    <i class="fas fa-print"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- B. DYNAMIC INFO GRID -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        
        <!-- Left Column: Vitals & Labs (7 cols) -->
        <div class="lg:col-span-8 space-y-6">
            
            <!-- 1. Vitals Scorecard -->
            <div class="bg-white rounded-[2rem] border border-slate-100 p-6 shadow-sm">
                <div class="flex items-center justify-between mb-6 pb-4 border-b border-slate-50">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-health-50 text-health-600 flex items-center justify-center text-lg">
                            <i class="fas fa-file-waveform"></i>
                        </div>
                        <h4 class="text-sm font-black text-slate-900 tracking-tight uppercase">Vital Signs Observation</h4>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div class="group bg-slate-50 border border-slate-100 p-5 rounded-3xl text-center transition-all hover:bg-rose-50 hover:border-rose-100">
                        <i class="fas fa-droplet text-rose-500/40 mb-3 group-hover:scale-110 transition-transform"></i>
                        <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1.5 group-hover:text-rose-600">Blood Pressure</p>
                        <p class="text-lg font-black text-slate-800 tracking-tight leading-none"><?= $record['blood_pressure'] ?: '—' ?></p>
                        <p class="text-[9px] font-bold text-slate-400 mt-1">mmHg</p>
                    </div>
                    <div class="group bg-slate-50 border border-slate-100 p-5 rounded-3xl text-center transition-all hover:bg-emerald-50 hover:border-emerald-100">
                        <i class="fas fa-weight-scale text-emerald-500/40 mb-3 group-hover:scale-110 transition-transform"></i>
                        <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1.5 group-hover:text-emerald-600">Current Weight</p>
                        <p class="text-lg font-black text-slate-800 tracking-tight leading-none"><?= $record['weight'] ?: '—' ?></p>
                        <p class="text-[9px] font-bold text-slate-400 mt-1">Kilograms</p>
                    </div>
                    <div class="group bg-slate-50 border border-slate-100 p-5 rounded-3xl text-center transition-all hover:bg-amber-50 hover:border-amber-100">
                        <i class="fas fa-thermometer-half text-amber-500/40 mb-3 group-hover:scale-110 transition-transform"></i>
                        <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1.5 group-hover:text-amber-600">Body Temp</p>
                        <p class="text-lg font-black text-slate-800 tracking-tight leading-none"><?= $record['temperature'] ? $record['temperature'] . '°' : '—' ?></p>
                        <p class="text-[9px] font-bold text-slate-400 mt-1">Celsius</p>
                    </div>
                    <div class="group bg-slate-50 border border-slate-100 p-5 rounded-3xl text-center transition-all hover:bg-sky-50 hover:border-sky-100">
                        <i class="fas fa-baby-carriage text-sky-500/40 mb-3 group-hover:scale-110 transition-transform"></i>
                        <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1.5 group-hover:text-sky-600">Gestational Age</p>
                        <p class="text-lg font-black text-slate-800 tracking-tight leading-none"><?= $record['gestational_age'] ?: '—' ?></p>
                        <p class="text-[9px] font-bold text-slate-400 mt-1">Weeks Recorded</p>
                    </div>
                </div>
            </div>

            <!-- 2. Clinical Evaluation Panel -->
            <div class="bg-white rounded-[2rem] border border-slate-100 p-8 shadow-sm relative group overflow-hidden">
                <div class="absolute right-0 top-0 w-32 h-32 bg-health-500/5 -mr-16 -mt-16 rounded-full"></div>
                
                <h4 class="text-sm font-black text-slate-900 tracking-tight uppercase flex items-center gap-3 mb-8">
                    <span class="w-1.5 h-6 bg-health-500 rounded-full"></span>
                    Diagnostic Evaluation
                </h4>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 relative z-10">
                    <div class="space-y-6">
                        <div class="p-5 rounded-2xl bg-rose-50/50 border border-rose-100/50">
                            <p class="text-[10px] font-black text-rose-500 uppercase tracking-widest mb-2 flex items-center gap-2">
                                <i class="fas fa-comment-medical"></i> Chief Complaint
                            </p>
                            <p class="text-sm text-slate-700 font-medium italic leading-relaxed">
                                "<?= $record['complaints'] ?: 'Patient reported no significant discomfort or complaints for this visit.' ?>"
                            </p>
                        </div>
                        <div class="p-5 rounded-2xl bg-slate-50 border border-slate-100">
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 flex items-center gap-2">
                                <i class="fas fa-magnifying-glass-chart"></i> Physical Findings
                            </p>
                            <p class="text-sm text-slate-700 leading-relaxed font-medium">
                                <?= $record['findings'] ?: 'Physical examination reveals normal progress. No acute concerns noted.' ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="space-y-6">
                        <div class="p-6 rounded-[1.5rem] bg-slate-900 text-white shadow-xl shadow-slate-200">
                            <p class="text-[10px] font-black text-health-400 uppercase tracking-widest mb-3 mb-2 flex items-center gap-2">
                                <i class="fas fa-stethoscope"></i> Clinical Diagnosis
                            </p>
                            <p class="text-base font-bold leading-relaxed tracking-tight">
                                <?= $record['diagnosis'] ?: 'Routine Pregnancy Monitoring — Stable progress expected.' ?>
                            </p>
                        </div>
                        <div class="p-5 rounded-2xl bg-health-50/50 border border-health-100/50">
                            <p class="text-[10px] font-black text-health-600 uppercase tracking-widest mb-2 flex items-center gap-2">
                                <i class="fas fa-hand-holding-medical"></i> Treatment Plan
                            </p>
                            <p class="text-sm text-slate-700 leading-relaxed font-bold">
                                <?= $record['treatment'] ?: 'Continue standard prenatal supplements and diet.' ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 3. Laboratory Findings -->
            <div class="bg-white rounded-[2rem] border border-slate-100 p-6 shadow-sm overflow-hidden relative">
                <i class="fas fa-microscope absolute -right-6 -bottom-6 text-slate-50 text-8xl"></i>
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center text-lg">
                        <i class="fas fa-flask"></i>
                    </div>
                    <h4 class="text-sm font-black text-slate-900 tracking-tight uppercase">Laboratory Profile</h4>
                </div>
                
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 relative z-10">
                    <div class="bg-slate-50 p-4 rounded-2xl flex items-center justify-between">
                        <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Hemoglobin</span>
                        <span class="text-sm font-black text-slate-700"><?= $record['hb_level'] ?: 'N/A' ?> <span class="text-[9px] text-slate-400">g/dL</span></span>
                    </div>
                    <div class="bg-slate-50 p-4 rounded-2xl flex items-center justify-between">
                        <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Blood Type</span>
                        <span class="text-sm font-black text-slate-700"><?= $record['blood_group'] ?: 'N/A' ?> <?= $record['rhesus_factor'] ?: '' ?></span>
                    </div>
                    <div class="bg-slate-50 p-4 rounded-2xl flex items-center justify-between">
                        <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Urinalysis</span>
                        <span class="text-xs font-bold text-slate-700 truncate ml-4"><?= $record['urinalysis'] ?: 'Normal' ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Right Column: Obstetric & Medications (4 cols) -->
        <div class="lg:col-span-4 space-y-6">
            
            <!-- 4. Obstetric History -->
            <div class="bg-white rounded-[2rem] border border-slate-100 p-6 shadow-sm">
                <h4 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-6">Obstetric Matrix</h4>
                
                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div class="bg-health-50 p-4 rounded-2xl text-center border border-health-100 group hover:bg-health-600 transition-colors duration-300">
                        <p class="text-[9px] font-black text-health-600 uppercase tracking-widest mb-1 group-hover:text-white/70">Gravida</p>
                        <p class="text-2xl font-black text-health-700 group-hover:text-white leading-none"><?= $record['gravida'] ?? '?' ?></p>
                    </div>
                    <div class="bg-sky-50 p-4 rounded-2xl text-center border border-sky-100 group hover:bg-sky-600 transition-colors duration-300">
                        <p class="text-[9px] font-black text-sky-600 uppercase tracking-widest mb-1 group-hover:text-white/70">Para</p>
                        <p class="text-2xl font-black text-sky-700 group-hover:text-white leading-none"><?= $record['para'] ?? '?' ?></p>
                    </div>
                    <div class="bg-emerald-50 p-4 rounded-2xl text-center border border-emerald-100 group hover:bg-emerald-600 transition-colors duration-300">
                        <p class="text-[9px] font-black text-emerald-600 uppercase tracking-widest mb-1 group-hover:text-white/70">Living Children</p>
                        <p class="text-2xl font-black text-emerald-700 group-hover:text-white leading-none"><?= $record['living_children'] ?? '0' ?></p>
                    </div>
                    <div class="bg-rose-50 p-4 rounded-2xl text-center border border-rose-100 group hover:bg-rose-600 transition-colors duration-300">
                        <p class="text-[9px] font-black text-rose-600 uppercase tracking-widest mb-1 group-hover:text-white/70">Abortions</p>
                        <p class="text-2xl font-black text-rose-700 group-hover:text-white leading-none"><?= $record['abortions'] ?? '0' ?></p>
                    </div>
                </div>
                
                <div class="space-y-3">
                    <div class="flex items-center justify-between p-3.5 bg-slate-50 rounded-2xl">
                        <div class="flex items-center gap-3">
                            <i class="fas fa-calendar-minus text-slate-400 text-xs"></i>
                            <span class="text-[10px] font-black text-slate-500 uppercase tracking-widest">LMP</span>
                        </div>
                        <span class="text-xs font-black text-slate-700"><?= (!empty($record['lmp']) && $record['lmp'] !== '0000-00-00') ? date('M d, Y', strtotime($record['lmp'])) : 'N/A' ?></span>
                    </div>
                    <div class="flex items-center justify-between p-3.5 bg-amber-50 rounded-2xl border border-amber-100/50">
                        <div class="flex items-center gap-3">
                            <i class="fas fa-calendar-star text-amber-500 text-xs"></i>
                            <span class="text-[10px] font-black text-amber-600 uppercase tracking-widest">Exp. Birth (EDC)</span>
                        </div>
                        <span class="text-xs font-black text-amber-700"><?= (!empty($record['edc']) && $record['edc'] !== '0000-00-00') ? date('M d, Y', strtotime($record['edc'])) : 'Pending' ?></span>
                    </div>
                </div>
            </div>

            <!-- 5. Medications Prescription -->
            <div class="bg-slate-50 rounded-[2rem] border border-slate-100 p-6">
                <h4 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-4">Medications</h4>
                <div class="space-y-3">
                    <?php 
                    $meds = [
                        ['id' => 'iron', 'label' => 'Iron Supplement', 'status' => $record['iron_supplement'], 'icon' => 'fa-capsules', 'color' => 'amber'],
                        ['id' => 'folic', 'label' => 'Folic Acid', 'status' => $record['folic_acid'], 'icon' => 'fa-leaf', 'color' => 'emerald'],
                        ['id' => 'calcium', 'label' => 'Calcium', 'status' => $record['calcium'], 'icon' => 'fa-bone', 'color' => 'sky']
                    ];
                    foreach ($meds as $m): ?>
                    <div class="flex items-center justify-between p-3 rounded-2xl <?= $m['status'] ? "bg-{$m['color']}-100/50 border border-{$m['color']}-200" : "bg-white opacity-40 border border-slate-100" ?> transition-all">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg <?= $m['status'] ? "bg-{$m['color']}-500 text-white" : "bg-slate-100 text-slate-400" ?> flex items-center justify-center text-xs shadow-sm">
                                <i class="fas <?= $m['icon'] ?>"></i>
                            </div>
                            <span class="text-xs font-bold <?= $m['status'] ? "text-{$m['color']}-800" : "text-slate-500" ?>"><?= $m['label'] ?></span>
                        </div>
                        <?php if ($m['status']): ?>
                            <i class="fas fa-check-circle text-<?= $m['color'] ?>-500 text-xs"></i>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if (!empty($record['other_meds'])): ?>
                    <div class="p-3 bg-violet-50 border border-violet-100 rounded-2xl">
                        <p class="text-[9px] font-black text-violet-500 uppercase tracking-widest mb-1">Additional Prescription</p>
                        <p class="text-xs font-bold text-violet-700"><?= htmlspecialchars($record['other_meds']) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 6. Audit & Next Visit -->
            <div class="bg-gradient-to-br from-slate-800 to-slate-900 rounded-[2rem] p-6 text-white shadow-xl">
                <div class="space-y-5">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl bg-health-500/10 border border-health-500/20 flex items-center justify-center text-health-400">
                            <i class="fas fa-calendar-plus text-lg"></i>
                        </div>
                        <div>
                            <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Next Follow-up</p>
                            <p class="text-base font-black text-white">
                                <?= (!empty($record['next_visit_date']) && $record['next_visit_date'] !== '0000-00-00') ? date('M d, Y', strtotime($record['next_visit_date'])) : 'To be determined' ?>
                            </p>
                        </div>
                    </div>
                    <div class="pt-5 border-t border-white/5">
                        <p class="text-[9px] font-black text-slate-500 uppercase tracking-widest mb-2 text-center">Documentation Footprint</p>
                        <div class="flex items-center justify-center gap-3 bg-white/5 rounded-2xl p-3">
                            <div class="text-center px-4">
                                <p class="text-[8px] font-black text-health-500 uppercase mb-0.5">Recorded By</p>
                                <p class="text-[11px] font-bold text-slate-200"><?= htmlspecialchars($record['recorded_first_name'] . ' ' . $record['recorded_last_name']) ?></p>
                            </div>
                            <div class="w-px h-8 bg-white/10"></div>
                            <div class="text-center px-4">
                                <p class="text-[8px] font-black text-health-500 uppercase mb-0.5">Timestamp</p>
                                <p class="text-[11px] font-bold text-slate-200"><?= date('H:i A', strtotime($record['recorded_at'])) ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- C. REMARKS BOX -->
    <?php if (!empty($record['remarks'])): ?>
    <div class="p-8 bg-amber-50 rounded-[2.5rem] border border-amber-100 relative group">
        <i class="fas fa-quote-right absolute right-8 top-8 text-amber-200/50 text-4xl"></i>
        <h4 class="text-[10px] font-black text-amber-600 uppercase tracking-[0.2em] mb-4 flex items-center gap-2">
            <i class="fas fa-sticky-note"></i> Clinical Remarks & Notes
        </h4>
        <p class="text-sm font-medium text-amber-900 leading-relaxed italic relative z-10">
            <?= nl2br(htmlspecialchars($record['remarks'])) ?>
        </p>
    </div>
    <?php endif; ?>

</div>

<!-- CSS for Print -->
<style>
@media print {
    body * { visibility: hidden; }
    #prenatalDetailsModal, #prenatalDetailsModal * { visibility: visible; }
    #prenatalDetailsModal { position: absolute; left: 0; top: 0; width: 100%; }
    /* Hide modal UI elements */
    .modal-header button, .modal-footer, #editPrenatalBtn, button[title="Print Record"] { display: none !important; }
}
</style>

<?php
} catch (Exception $e) {
    echo '<div class="p-12 text-center bg-rose-50 rounded-[2.5rem] border border-rose-100">
            <div class="w-20 h-20 bg-rose-100 text-rose-500 rounded-3xl flex items-center justify-center text-3xl mx-auto mb-6">
                <i class="fas fa-triangle-exclamation"></i>
            </div>
            <h3 class="text-xl font-black text-rose-900 mb-2">Error Encountered</h3>
            <p class="text-sm text-rose-600 font-medium">' . $e->getMessage() . '</p>
          </div>';
}
?>