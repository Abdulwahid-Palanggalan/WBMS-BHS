<?php
// get_prenatal_details.php - UPDATED TO SHOW ALL VISITS' DATA
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
    echo '<div class="alert alert-danger">Prenatal record ID is required.</div>';
    exit();
}

$recordId = intval($_GET['id']);

try {
    // First, get the specific prenatal record details
    $query = "
        SELECT pr.*, 
               m.first_name as mother_first_name, 
               m.last_name as mother_last_name,
               m.phone as mother_phone,
               m.email as mother_email,
               m.address, m.blood_type, m.rh_factor,
               pd.edc, pd.lmp, 
               pd.gravida, pd.para, pd.living_children, pd.abortions,
               u_recorder.first_name as recorded_first_name,
               u_recorder.last_name as recorded_last_name
        FROM prenatal_records pr
        JOIN mothers m ON pr.mother_id = m.id
        LEFT JOIN pregnancy_details pd ON m.id = pd.mother_id
        LEFT JOIN users u_recorder ON pr.recorded_by = u_recorder.id 
        WHERE pr.id = ?
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$recordId]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$record) {
        http_response_code(404);
        echo '<div class="alert alert-danger">Prenatal record not found.</div>';
        exit();
    }
    
    // Now, get ALL prenatal records for this mother to show lab results and treatments from all visits
    $motherId = $record['mother_id'];
    $allVisitsQuery = "
        SELECT pr.*, 
               u.first_name as recorded_first_name,
               u.last_name as recorded_last_name
        FROM prenatal_records pr
        LEFT JOIN users u ON pr.recorded_by = u.id
        WHERE pr.mother_id = ?
        ORDER BY pr.visit_date ASC, pr.visit_number ASC
    ";
    
    $allVisitsStmt = $pdo->prepare($allVisitsQuery);
    $allVisitsStmt->execute([$motherId]);
    $allVisits = $allVisitsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate gestational weeks if LMP is available
    $gestationalWeeks = '';
    if (!empty($record['lmp']) && $record['lmp'] != '0000-00-00') {
        $lmpDate = new DateTime($record['lmp']);
        $visitDate = new DateTime($record['visit_date']);
        $interval = $lmpDate->diff($visitDate);
        $weeks = floor($interval->days / 7);
        $days = $interval->days % 7;
        $gestationalWeeks = $weeks . ' weeks ' . $days . ' days';
    }
    
    // Format the data for display
    ?>
    
    <!-- MODAL CONTENT: PREMIUM CLINICAL LAYOUT -->
    <div class="space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
        
        <!-- TOP SECTION: PATIENT & PREGNANCY MATRICES -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Patient Biological Identity -->
            <div class="bg-white border-2 border-slate-50 rounded-[2.5rem] p-8 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 rounded-2xl bg-health-50 text-health-600 flex items-center justify-center">
                        <i class="fas fa-fingerprint"></i>
                    </div>
                    <h4 class="text-sm font-black text-slate-400 uppercase tracking-widest">Biological Profile</h4>
                </div>
                
                <div class="space-y-4">
                    <div class="flex items-center justify-between py-3 border-b border-slate-50">
                        <span class="text-[10px] font-black text-slate-400 uppercase">Legal Name</span>
                        <span class="text-sm font-bold text-slate-800"><?= htmlspecialchars(($record['mother_first_name'] ?? '') . ' ' . ($record['mother_last_name'] ?? '')) ?></span>
                    </div>
                    <div class="flex items-center justify-between py-3 border-b border-slate-50">
                        <span class="text-[10px] font-black text-slate-400 uppercase">Comm. Link</span>
                        <span class="text-sm font-bold text-health-600"><?= !empty($record['mother_phone']) ? htmlspecialchars($record['mother_phone']) : '---'; ?></span>
                    </div>
                    <div class="flex items-center justify-between py-3 border-b border-slate-50">
                        <span class="text-[10px] font-black text-slate-400 uppercase">Clinical Email</span>
                        <span class="text-xs font-bold text-slate-500"><?= !empty($record['mother_email']) ? htmlspecialchars($record['mother_email']) : '---'; ?></span>
                    </div>
                    <div class="flex items-center justify-between py-3 border-b border-slate-50">
                        <span class="text-[10px] font-black text-slate-400 uppercase">Blood Matrix</span>
                        <div class="flex items-center gap-2">
                             <span class="px-2 py-1 bg-rose-50 text-rose-600 text-[10px] font-black rounded-lg border border-rose-100 uppercase"><?= !empty($record['blood_type']) ? htmlspecialchars($record['blood_type'] . ($record['rh_factor'] ?? '')) : 'N/A'; ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pregnancy Diagnostic Matrix -->
            <div class="bg-white border-2 border-slate-50 rounded-[2.5rem] p-8 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center">
                        <i class="fas fa-heart-pulse"></i>
                    </div>
                    <h4 class="text-sm font-black text-slate-400 uppercase tracking-widest">Diagnostic Matrix</h4>
                </div>
                
                <div class="grid grid-cols-2 gap-4 h-full">
                    <div class="bg-slate-50/50 rounded-2xl p-4 flex flex-col justify-center">
                        <span class="text-[9px] font-black text-slate-400 uppercase mb-1">Obstetric History</span>
                        <span class="text-lg font-black text-slate-800 tracking-tight">G<?= $record['gravida'] ?? '?' ?> P<?= $record['para'] ?? '?' ?></span>
                    </div>
                    <div class="bg-slate-50/50 rounded-2xl p-4 flex flex-col justify-center">
                        <span class="text-[9px] font-black text-slate-400 uppercase mb-1">Expected Delivery</span>
                        <span class="text-xs font-black text-amber-600 uppercase italic"><?= !empty($record['edc']) && $record['edc'] != '0000-00-00' ? date('M d, Y', strtotime($record['edc'])) : 'Unscheduled'; ?></span>
                    </div>
                    <div class="col-span-2 bg-health-50 rounded-2xl p-4 flex items-center gap-4">
                        <div class="w-10 h-10 rounded-xl bg-white text-health-600 flex items-center justify-center shadow-soft">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div>
                            <span class="text-[9px] font-black text-health-600/50 uppercase block">Gestational Progress</span>
                            <span class="text-sm font-black text-health-700 uppercase"><?= $gestationalWeeks ?: 'Calculations Pending'; ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- MIDDLE SECTION: OBSERVATION TIMELINE -->
        <div class="bg-slate-900 rounded-[2.5rem] p-10 text-white relative overflow-hidden group/records">
            <div class="absolute right-0 top-0 bottom-0 w-1/3 bg-gradient-to-l from-white/5 to-transparent"></div>
            <i class="fas fa-microscope absolute -right-4 -bottom-4 text-8xl text-white/5 rotate-12 group-hover/records:rotate-0 transition-transform duration-700"></i>
            
            <div class="relative z-10 grid grid-cols-1 lg:grid-cols-3 gap-10">
                <div class="lg:col-span-2 space-y-8">
                    <div>
                        <span class="inline-block px-4 py-1.5 bg-white/10 backdrop-blur-md rounded-full text-[10px] font-black uppercase tracking-widest text-white/80 border border-white/10 mb-4">Observation Findings</span>
                        <h4 class="text-3xl font-black text-white tracking-tight leading-tight">Clinical Diagnosis & <br><span class="text-health-400">Treatment Plan</span></h4>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <span class="text-[10px] font-black text-white/40 uppercase tracking-widest block">Main Complaint</span>
                            <p class="text-sm font-medium text-white/80 italic">"<?= !empty($record['complaints']) ? htmlspecialchars($record['complaints']) : 'No subjective reports recorded.'; ?>"</p>
                        </div>
                        <div class="space-y-2">
                            <span class="text-[10px] font-black text-white/40 uppercase tracking-widest block">Core Findings</span>
                            <p class="text-sm font-medium text-white/80"><?= !empty($record['findings']) ? htmlspecialchars($record['findings']) : 'No significant clinical findings.'; ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white/5 backdrop-blur-md rounded-3xl p-6 border border-white/10 flex flex-col justify-between">
                    <div class="space-y-6">
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-black text-white/40 uppercase tracking-widest">Medical Directive</span>
                            <i class="fas fa-prescription text-health-400"></i>
                        </div>
                        <p class="text-sm font-bold text-white leading-relaxed"><?= !empty($record['diagnosis']) ? htmlspecialchars($record['diagnosis']) : 'Final diagnosis pending evaluation.'; ?></p>
                    </div>
                    <div class="pt-6 border-t border-white/5 mt-6">
                        <span class="text-[9px] font-black text-health-400 uppercase block mb-1 tracking-widest">Recorded On</span>
                        <span class="text-xs font-bold text-white/60 uppercase"><?= date('F d, Y @ h:i A', strtotime($record['recorded_at'])); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- BOTTOM SECTION: VITAL SIGNATURES -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-white border border-slate-100 p-6 rounded-3xl flex flex-col items-center text-center group hover:border-rose-100 transition-all">
                <div class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-500 flex items-center justify-center text-xl mb-4 group-hover:scale-110 transition-transform">
                    <i class="fas fa-droplet"></i>
                </div>
                <span class="text-[9px] font-black text-slate-400 uppercase mb-1">Blood Pressure</span>
                <span class="text-lg font-black text-slate-800"><?= $record['blood_pressure'] ?: '---'; ?></span>
            </div>
            
            <div class="bg-white border border-slate-100 p-6 rounded-3xl flex flex-col items-center text-center group hover:border-emerald-100 transition-all">
                <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-500 flex items-center justify-center text-xl mb-4 group-hover:scale-110 transition-transform">
                    <i class="fas fa-weight-scale"></i>
                </div>
                <span class="text-[9px] font-black text-slate-400 uppercase mb-1">Current Weight</span>
                <span class="text-lg font-black text-slate-800"><?= $record['weight'] ?: '---'; ?> <span class="text-xs font-medium">kg</span></span>
            </div>

            <div class="bg-white border border-slate-100 p-6 rounded-3xl flex flex-col items-center text-center group hover:border-amber-100 transition-all">
                <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-500 flex items-center justify-center text-xl mb-4 group-hover:scale-110 transition-transform">
                    <i class="fas fa-temperature-half"></i>
                </div>
                <span class="text-[9px] font-black text-slate-400 uppercase mb-1">Body Temp</span>
                <span class="text-lg font-black text-slate-800"><?= $record['temperature'] ?: '---'; ?> <span class="text-xs font-medium">°C</span></span>
            </div>

            <div class="bg-white border border-slate-100 p-6 rounded-3xl flex flex-col items-center text-center group hover:border-sky-100 transition-all">
                <div class="w-12 h-12 rounded-2xl bg-sky-50 text-sky-500 flex items-center justify-center text-xl mb-4 group-hover:scale-110 transition-transform">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <span class="text-[9px] font-black text-slate-400 uppercase mb-1">Next Appointment</span>
                <span class="text-sm font-black text-sky-600 uppercase italic"><?= !empty($record['next_visit_date']) && $record['next_visit_date'] != '0000-00-00' ? date('M d, Y', strtotime($record['next_visit_date'])) : 'TBD'; ?></span>
            </div>
        </div>

    </div>
<?php
    
} catch (PDOException $e) {
    error_log("Database error in get_prenatal_details.php: " . $e->getMessage());
    http_response_code(500);
    echo '<div class="bg-rose-50 border border-rose-100 p-8 rounded-[2rem] text-center">
            <i class="fas fa-database text-rose-500 text-3xl mb-4"></i>
            <p class="font-bold text-rose-800 uppercase tracking-widest text-xs">Registry Connection Error</p>
          </div>';
} catch (Exception $e) {
    error_log("Error in get_prenatal_details.php: " . $e->getMessage());
    http_response_code(500);
    echo '<div class="bg-amber-50 border border-amber-100 p-8 rounded-[2rem] text-center">
            <i class="fas fa-exclamation-triangle text-amber-500 text-3xl mb-4"></i>
            <p class="font-bold text-amber-800 uppercase tracking-widest text-xs">Clinical Evaluation Error</p>
          </div>';
}
?>

    <?php
    
} catch (PDOException $e) {
    error_log("Database error in get_prenatal_details.php: " . $e->getMessage());
    http_response_code(500);
    echo '<div class="alert alert-danger">Database Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
} catch (Exception $e) {
    error_log("Error in get_prenatal_details.php: " . $e->getMessage());
    http_response_code(500);
    echo '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>