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
        'mother_first_name' => $motherRecord['first_name'],  // From mothers table
        'mother_middle_name' => $motherRecord['middle_name'], // From mothers table
        'mother_last_name' => $motherRecord['last_name'],    // From mothers table
        'mother_phone' => $motherRecord['phone'],
        'mother_address' => $motherRecord['address'],
        'recorded_first_name' => $userRecord['first_name'] ?? '',
        'recorded_last_name' => $userRecord['last_name'] ?? ''
    ]);
    
    // Calculate days after birth manually
    $daysAfterBirth = '';
    if (!empty($record['visit_date']) && !empty($record['birth_date'])) {
        $daysAfterBirth = floor((strtotime($record['visit_date']) - strtotime($record['birth_date'])) / (60 * 60 * 24));
    }
    
    // Continue with your HTML display code...
    ?>
    
    <!-- MODAL CONTENT: PREMIUM POSTNATAL DIAGNOSTIC MATRIX -->
    <div class="space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
        
        <!-- TOP SECTION: PATIENT & NEWBORN MATRICES -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Mother Recovery Profile -->
            <div class="bg-white border-2 border-slate-50 rounded-[2.5rem] p-8 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 rounded-2xl bg-health-50 text-health-600 flex items-center justify-center">
                        <i class="fas fa-female"></i>
                    </div>
                    <h4 class="text-sm font-black text-slate-400 uppercase tracking-widest">Recovery Profile</h4>
                </div>
                
                <div class="space-y-4">
                    <div class="flex items-center justify-between py-3 border-b border-slate-50">
                        <span class="text-[10px] font-black text-slate-400 uppercase">Mother Name</span>
                        <span class="text-sm font-bold text-slate-800"><?= htmlspecialchars($record['mother_first_name'] . ' ' . $record['mother_last_name']) ?></span>
                    </div>
                    <div class="flex items-center justify-between py-3 border-b border-slate-50">
                        <span class="text-[10px] font-black text-slate-400 uppercase">Comm. Link</span>
                        <span class="text-sm font-bold text-health-600"><?= !empty($record['mother_phone']) ? htmlspecialchars($record['mother_phone']) : '---'; ?></span>
                    </div>
                    <div class="flex items-center justify-between py-3 border-b border-slate-50">
                        <span class="text-[10px] font-black text-slate-400 uppercase">Vitals @ Visit</span>
                        <div class="flex items-center gap-2">
                             <span class="px-2 py-1 bg-slate-50 text-slate-600 text-[10px] font-black rounded-lg border border-slate-100 uppercase"><?= $record['blood_pressure'] ?: '---'; ?></span>
                             <span class="px-2 py-1 bg-amber-50 text-amber-600 text-[10px] font-black rounded-lg border border-amber-100 uppercase"><?= $record['temperature'] ? $record['temperature'] . '°C' : '---'; ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Newborn Identity Matrix -->
            <div class="bg-white border-2 border-slate-50 rounded-[2.5rem] p-8 shadow-sm hover:shadow-md transition-shadow relative overflow-hidden group">
                <div class="absolute right-0 top-0 w-32 h-32 bg-health-50 rounded-full blur-3xl opacity-20 -mr-16 -mt-16"></div>
                
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center">
                        <i class="fas fa-baby"></i>
                    </div>
                    <h4 class="text-sm font-black text-slate-400 uppercase tracking-widest">Newborn Identity</h4>
                </div>
                
                <div class="grid grid-cols-2 gap-4 h-full relative z-10">
                    <div class="bg-slate-50/50 rounded-2xl p-4 flex flex-col justify-center">
                        <span class="text-[9px] font-black text-slate-400 uppercase mb-1">Clinical ID</span>
                        <span class="text-sm font-black text-slate-800 tracking-tight leading-tight"><?= htmlspecialchars($record['baby_first_name'] . ' ' . $record['baby_last_name']) ?></span>
                    </div>
                    <div class="bg-slate-50/50 rounded-2xl p-4 flex flex-col justify-center">
                        <span class="text-[9px] font-black text-slate-400 uppercase mb-1">Observation Age</span>
                        <span class="text-lg font-black text-rose-600 tracking-tight italic"><?= $daysAfterBirth ?> <span class="text-[10px] uppercase tracking-widest text-rose-300">Days</span></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- MIDDLE SECTION: POSTPARTUM VECTORS -->
        <div class="bg-slate-900 rounded-[2.5rem] p-10 text-white relative overflow-hidden group/records border-4 border-white">
            <div class="absolute right-0 top-0 bottom-0 w-1/3 bg-gradient-to-l from-white/5 to-transparent"></div>
            <i class="fas fa-heart-pulse absolute -right-4 -bottom-4 text-8xl text-white/5 rotate-12 group-hover/records:rotate-0 transition-transform duration-700"></i>
            
            <div class="relative z-10 flex flex-col gap-10">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 border-b border-white/5 pb-8">
                    <div class="space-y-2">
                        <span class="inline-block px-4 py-1.5 bg-white/10 backdrop-blur-md rounded-full text-[10px] font-black uppercase tracking-widest text-white/80 border border-white/10 italic">Core Health Observation</span>
                        <h4 class="text-3xl font-black text-white tracking-tight">Diagnostic <span class="text-health-400">Findings</span></h4>
                    </div>
                    <div class="flex items-center gap-3">
                         <div class="px-6 py-4 bg-white/5 rounded-3xl border border-white/10 text-center">
                            <span class="text-[9px] font-black text-white/40 uppercase block mb-1">Visit Number</span>
                            <span class="text-xl font-black text-health-400 uppercase italic">№ <?= $record['visit_number'] ?></span>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div class="bg-white/5 p-6 rounded-3xl border border-white/10">
                        <span class="text-[9px] font-black text-white/40 uppercase block mb-3 tracking-widest">Uterus Status</span>
                        <span class="text-sm font-bold text-white"><?= !empty($record['uterus_status']) ? ucfirst($record['uterus_status']) : '---'; ?></span>
                    </div>
                    <div class="bg-white/5 p-6 rounded-3xl border border-white/10">
                        <span class="text-[9px] font-black text-white/40 uppercase block mb-3 tracking-widest">Lochia Vectors</span>
                        <span class="text-sm font-bold text-white"><?= !empty($record['lochia_status']) ? ucfirst($record['lochia_status']) : '---'; ?></span>
                    </div>
                    <div class="bg-white/5 p-6 rounded-3xl border border-white/10">
                        <span class="text-[9px] font-black text-white/40 uppercase block mb-3 tracking-widest">Breasts Eval.</span>
                        <span class="text-sm font-bold text-white"><?= !empty($record['breasts_status']) ? ucfirst($record['breasts_status']) : '---'; ?></span>
                    </div>
                    <div class="bg-white/5 p-6 rounded-3xl border border-white/10">
                        <span class="text-[9px] font-black text-white/40 uppercase block mb-3 tracking-widest">Emotional State</span>
                        <span class="text-sm font-bold text-white uppercase italic"><?= !empty($record['emotional_state']) ? str_replace('-', ' ', $record['emotional_state']) : '---'; ?></span>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 pt-6">
                    <div class="space-y-3">
                        <div class="flex items-center gap-2">
                            <div class="w-1.5 h-1.5 rounded-full bg-health-400"></div>
                            <span class="text-[10px] font-black text-white/40 uppercase tracking-[0.2em]">Subjective Complaints</span>
                        </div>
                        <p class="text-sm font-medium text-white/70 leading-relaxed italic">"<?= !empty($record['complaints']) ? htmlspecialchars($record['complaints']) : 'Negative; patient reports no immediate subjective issues.'; ?>"</p>
                    </div>
                    <div class="space-y-3">
                        <div class="flex items-center gap-2">
                            <div class="w-1.5 h-1.5 rounded-full bg-health-400"></div>
                            <span class="text-[10px] font-black text-white/40 uppercase tracking-[0.2em]">Medical Interventions Given</span>
                        </div>
                        <p class="text-sm font-bold text-white leading-relaxed"><?= !empty($record['treatment']) ? htmlspecialchars($record['treatment']) : 'No pharmacological interventions required at this time.'; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- BOTTOM SECTION: NEWBORN MONITORING -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Baby Weight Component -->
            <div class="md:col-span-1 bg-emerald-50 border border-emerald-100 p-8 rounded-[2.5rem] flex flex-col justify-between group hover:shadow-lg hover:shadow-emerald-500/5 transition-all">
                <div class="flex items-center justify-between mb-8">
                    <div class="w-12 h-12 rounded-2xl bg-white text-emerald-500 flex items-center justify-center text-xl shadow-sm group-hover:scale-110 transition-transform">
                        <i class="fas fa-weight-scale"></i>
                    </div>
                    <span class="px-3 py-1 bg-white text-emerald-600 text-[9px] font-black rounded-xl border border-emerald-100">Weight Tracking</span>
                </div>
                <div>
                     <span class="text-[9px] font-black text-emerald-600/50 uppercase block mb-1">Clinical Weight (kg)</span>
                     <div class="flex items-baseline gap-2">
                        <span class="text-4xl font-black text-emerald-800 tracking-tighter"><?= $record['baby_weight'] ?: '---'; ?></span>
                        <span class="text-xs font-bold text-emerald-400 uppercase tracking-widest italic">Stable</span>
                     </div>
                </div>
            </div>

            <!-- Feeding & Health Component -->
            <div class="md:col-span-2 bg-white border-2 border-slate-50 p-8 rounded-[2.5rem] shadow-sm flex flex-col justify-between">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-500 flex items-center justify-center">
                            <i class="fas fa-person-breastfeeding text-xl"></i>
                        </div>
                        <div>
                            <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest block mb-1">Nutritional Model</span>
                            <span class="text-sm font-black text-slate-800 uppercase italic leading-tight"><?= !empty($record['feeding_method']) ? str_replace('-', ' ', $record['feeding_method']) : 'Not Specified'; ?></span>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="text-[9px] font-black text-slate-400 uppercase block mb-1">Next Expected Visit</span>
                        <span class="text-sm font-black text-health-600 uppercase italic"><?= !empty($record['next_visit_date']) && $record['next_visit_date'] != '0000-00-00' ? date('M d, Y', strtotime($record['next_visit_date'])) : 'Unscheduled'; ?></span>
                    </div>
                </div>
                
                <div class="mt-8 pt-8 border-t border-slate-50 grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div class="space-y-2">
                        <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest block">Pediatric Observations</span>
                        <p class="text-xs font-bold text-slate-600 leading-relaxed italic">"<?= !empty($record['baby_issues']) ? htmlspecialchars($record['baby_issues']) : 'No pediatric concerns reported.'; ?>"</p>
                    </div>
                    <div class="space-y-2">
                        <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest block">Health Directives</span>
                        <p class="text-xs font-black text-slate-500 leading-relaxed"><?= !empty($record['counseling_topics']) ? htmlspecialchars($record['counseling_topics']) : 'Standard neonatal hygiene and feeding protocols verified.'; ?></p>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <?php
    
} catch (PDOException $e) {
    error_log("Database error in get_postnatal_details.php: " . $e->getMessage());
    http_response_code(500);
    echo '<div class="alert alert-danger">Database Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
} catch (Exception $e) {
    error_log("Error in get_postnatal_details.php: " . $e->getMessage());
    http_response_code(500);
    echo '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>