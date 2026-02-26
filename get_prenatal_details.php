<?php
// get_prenatal_details.php — Premium Tailwind Modal Content
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
    echo '<div class="p-6 bg-rose-50 border border-rose-100 rounded-3xl text-center">
            <i class="fas fa-lock text-rose-400 text-2xl mb-2"></i>
            <p class="text-xs font-black text-rose-800 uppercase tracking-widest">Unauthorized Access</p>
          </div>';
    exit();
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    http_response_code(400);
    echo '<div class="p-6 bg-amber-50 border border-amber-100 rounded-3xl text-center">
            <i class="fas fa-exclamation-circle text-amber-400 text-2xl mb-2"></i>
            <p class="text-xs font-black text-amber-800 uppercase tracking-widest">Missing Record ID</p>
          </div>';
    exit();
}

$recordId = intval($_GET['id']);

try {
    // Specific prenatal record
    $stmt = $pdo->prepare("
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
    ");
    $stmt->execute([$recordId]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$record) {
        http_response_code(404);
        echo '<div class="p-8 bg-rose-50 border border-rose-100 rounded-3xl text-center">
                <i class="fas fa-file-circle-xmark text-rose-400 text-3xl mb-3"></i>
                <p class="text-xs font-black text-rose-800 uppercase tracking-widest">Record Not Found</p>
              </div>';
        exit();
    }

    // All visits for this mother
    $motherId = $record['mother_id'];
    $allVisitsStmt = $pdo->prepare("
        SELECT pr.*,
               u.first_name as recorded_first_name,
               u.last_name as recorded_last_name
        FROM prenatal_records pr
        LEFT JOIN users u ON pr.recorded_by = u.id
        WHERE pr.mother_id = ?
        ORDER BY pr.visit_date ASC, pr.visit_number ASC
    ");
    $allVisitsStmt->execute([$motherId]);
    $allVisits = $allVisitsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Gestational age calculation
    $gestationalWeeks = '';
    $gestWeeksNum = null;
    if (!empty($record['lmp']) && $record['lmp'] !== '0000-00-00') {
        $lmpDate   = new DateTime($record['lmp']);
        $visitDate = new DateTime($record['visit_date']);
        $interval  = $lmpDate->diff($visitDate);
        $gestWeeksNum = floor($interval->days / 7);
        $gestDays     = $interval->days % 7;
        $gestationalWeeks = $gestWeeksNum . 'w ' . $gestDays . 'd';
    }

    $patientName = htmlspecialchars($record['mother_first_name'] . ' ' . $record['mother_last_name']);
    $initials    = strtoupper(substr($record['mother_first_name'], 0, 1) . substr($record['mother_last_name'], 0, 1));
    $visitDate   = date('F d, Y', strtotime($record['visit_date']));
    $totalVisits = count($allVisits);

?>

<div class="space-y-6">

    <!-- PATIENT IDENTITY BANNER -->
    <div class="bg-gradient-to-r from-health-600 to-health-700 rounded-[2rem] p-6 text-white flex flex-col sm:flex-row sm:items-center gap-5">
        <div class="w-16 h-16 rounded-[1.2rem] bg-white/15 border border-white/20 backdrop-blur-sm flex items-center justify-center text-2xl font-black flex-shrink-0">
            <?= $initials ?>
        </div>
        <div class="flex-1 min-w-0">
            <p class="text-[10px] font-black text-white/60 uppercase tracking-widest mb-1">Patient</p>
            <h4 class="text-xl font-black text-white tracking-tight leading-tight"><?= $patientName ?></h4>
            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-2">
                <span class="text-xs text-white/70 font-medium">
                    <i class="fas fa-calendar-day mr-1 opacity-60"></i><?= $visitDate ?>
                </span>
                <span class="text-xs text-white/70 font-medium">
                    <i class="fas fa-hashtag mr-1 opacity-60"></i>Visit <?= $record['visit_number'] ?> of <?= $totalVisits ?>
                </span>
                <?php if ($gestationalWeeks): ?>
                <span class="inline-flex items-center gap-1.5 bg-white/15 border border-white/20 rounded-full px-3 py-1 text-[10px] font-black text-white uppercase tracking-widest">
                    <i class="fas fa-baby text-[8px]"></i><?= $gestationalWeeks ?>
                </span>
                <?php endif; ?>
            </div>
        </div>
        <div class="text-right flex-shrink-0">
            <p class="text-[10px] font-black text-white/50 uppercase tracking-widest">Record ID</p>
            <p class="text-lg font-black text-white/90">#PR-<?= $record['id'] ?></p>
        </div>
    </div>

    <!-- INFO GRID: 2 COLUMNS -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

        <!-- Biological Profile Card -->
        <div class="bg-white border border-slate-100 rounded-[2rem] p-6 shadow-sm space-y-4">
            <div class="flex items-center gap-3 pb-3 border-b border-slate-50">
                <div class="w-9 h-9 rounded-xl bg-health-50 text-health-600 flex items-center justify-center text-sm">
                    <i class="fas fa-user-circle"></i>
                </div>
                <h5 class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Patient Profile</h5>
            </div>

            <?php
            $profileFields = [
                ['label' => 'Full Name',  'value' => $patientName, 'icon' => 'fa-id-card'],
                ['label' => 'Phone',      'value' => $record['mother_phone'] ?: '—', 'icon' => 'fa-phone'],
                ['label' => 'Email',      'value' => $record['mother_email'] ?: '—', 'icon' => 'fa-envelope'],
                ['label' => 'Blood Type', 'value' => (!empty($record['blood_type']) ? $record['blood_type'] . ($record['rh_factor'] ?? '') : 'N/A'), 'icon' => 'fa-droplet'],
            ];
            foreach ($profileFields as $f): ?>
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-1.5">
                    <i class="fas <?= $f['icon'] ?> opacity-50"></i><?= $f['label'] ?>
                </span>
                <span class="text-sm font-bold text-slate-700 text-right max-w-[55%] truncate"><?= htmlspecialchars($f['value']) ?></span>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Pregnancy Matrix Card -->
        <div class="bg-white border border-slate-100 rounded-[2rem] p-6 shadow-sm space-y-4">
            <div class="flex items-center gap-3 pb-3 border-b border-slate-50">
                <div class="w-9 h-9 rounded-xl bg-amber-50 text-amber-500 flex items-center justify-center text-sm">
                    <i class="fas fa-heart-pulse"></i>
                </div>
                <h5 class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Pregnancy Details</h5>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="bg-slate-50 rounded-2xl p-4">
                    <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Obstetric</p>
                    <p class="text-lg font-black text-slate-800">
                        G<?= $record['gravida'] ?? '?' ?> P<?= $record['para'] ?? '?' ?>
                    </p>
                    <?php if ($record['living_children'] !== null): ?>
                    <p class="text-[10px] font-medium text-slate-500 mt-0.5">LC: <?= $record['living_children'] ?></p>
                    <?php endif; ?>
                </div>
                <div class="bg-amber-50 rounded-2xl p-4">
                    <p class="text-[9px] font-black text-amber-500 uppercase tracking-widest mb-1">EDC</p>
                    <p class="text-sm font-black text-amber-700 leading-tight">
                        <?= (!empty($record['edc']) && $record['edc'] !== '0000-00-00') ? date('M d, Y', strtotime($record['edc'])) : 'TBD' ?>
                    </p>
                </div>
            </div>

            <?php if (!empty($record['lmp']) && $record['lmp'] !== '0000-00-00'): ?>
            <div class="bg-health-50 rounded-2xl p-4 flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-white text-health-600 flex items-center justify-center shadow-sm border border-health-100">
                    <i class="fas fa-calendar-check text-sm"></i>
                </div>
                <div>
                    <p class="text-[9px] font-black text-health-500 uppercase tracking-widest">LMP</p>
                    <p class="text-sm font-black text-health-700"><?= date('M d, Y', strtotime($record['lmp'])) ?></p>
                </div>
                <?php if ($gestationalWeeks): ?>
                <div class="ml-auto text-right">
                    <p class="text-[9px] font-black text-health-500 uppercase tracking-widest">AOG</p>
                    <p class="text-sm font-black text-health-700"><?= $gestationalWeeks ?></p>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- VITAL SIGNS GRID -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <?php
        $vitals = [
            ['label' => 'Blood Pressure', 'value' => $record['blood_pressure'] ?: '—',
             'iconBg' => 'bg-rose-50', 'iconColor' => 'text-rose-500', 'hoverBorder' => 'hover:border-rose-200',
             'icon' => 'fa-droplet', 'unit' => ''],
            ['label' => 'Weight',         'value' => $record['weight'] ? $record['weight'] . ' kg' : '—',
             'iconBg' => 'bg-emerald-50', 'iconColor' => 'text-emerald-500', 'hoverBorder' => 'hover:border-emerald-200',
             'icon' => 'fa-weight-scale', 'unit' => ''],
            ['label' => 'Temperature',    'value' => $record['temperature'] ? $record['temperature'] . '°C' : '—',
             'iconBg' => 'bg-amber-50', 'iconColor' => 'text-amber-500', 'hoverBorder' => 'hover:border-amber-200',
             'icon' => 'fa-temperature-half', 'unit' => ''],
            ['label' => 'Next Visit',     'value' => (!empty($record['next_visit_date']) && $record['next_visit_date'] !== '0000-00-00') ? date('M d, Y', strtotime($record['next_visit_date'])) : 'TBD',
             'iconBg' => 'bg-sky-50', 'iconColor' => 'text-sky-500', 'hoverBorder' => 'hover:border-sky-200',
             'icon' => 'fa-calendar-day', 'unit' => ''],
        ];
        foreach ($vitals as $v): ?>
        <div class="bg-white border border-slate-100 <?= $v['hoverBorder'] ?> rounded-[1.5rem] p-5 flex flex-col items-center text-center group transition-all duration-200 hover:shadow-sm">
            <div class="w-11 h-11 rounded-xl <?= $v['iconBg'] ?> <?= $v['iconColor'] ?> flex items-center justify-center text-lg mb-3 group-hover:scale-110 transition-transform duration-200">
                <i class="fas <?= $v['icon'] ?>"></i>
            </div>
            <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1"><?= $v['label'] ?></p>
            <p class="text-base font-black text-slate-800 leading-tight"><?= htmlspecialchars($v['value']) ?></p>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- CLINICAL FINDINGS PANEL -->
    <div class="bg-slate-900 rounded-[2rem] p-7 text-white relative overflow-hidden">
        <i class="fas fa-microscope absolute -right-4 -bottom-4 text-8xl text-white/5"></i>
        <div class="relative z-10 grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="md:col-span-2 space-y-5">
                <div>
                    <span class="inline-block bg-white/10 border border-white/10 rounded-full px-3 py-1 text-[9px] font-black uppercase tracking-widest text-white/70 mb-3">Observation Findings</span>
                    <h4 class="text-2xl font-black text-white leading-tight">Clinical Diagnosis<br><span class="text-health-400">& Treatment Plan</span></h4>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <p class="text-[9px] font-black text-white/40 uppercase tracking-widest mb-1.5">Chief Complaint</p>
                        <p class="text-sm text-white/80 font-medium italic leading-relaxed">
                            "<?= !empty($record['complaints']) ? htmlspecialchars($record['complaints']) : 'No complaints recorded.' ?>"
                        </p>
                    </div>
                    <div>
                        <p class="text-[9px] font-black text-white/40 uppercase tracking-widest mb-1.5">Findings</p>
                        <p class="text-sm text-white/80 font-medium leading-relaxed">
                            <?= !empty($record['findings']) ? htmlspecialchars($record['findings']) : 'No significant findings.' ?>
                        </p>
                    </div>
                </div>
            </div>

            <div class="bg-white/5 backdrop-blur border border-white/10 rounded-[1.5rem] p-5 flex flex-col justify-between gap-4">
                <div>
                    <p class="text-[9px] font-black text-white/40 uppercase tracking-widest mb-2">Diagnosis</p>
                    <p class="text-sm font-bold text-white leading-relaxed">
                        <?= !empty($record['diagnosis']) ? htmlspecialchars($record['diagnosis']) : 'Pending evaluation.' ?>
                    </p>
                </div>
                <div class="border-t border-white/10 pt-4">
                    <p class="text-[9px] font-black text-health-400 uppercase tracking-widest mb-1">Recorded By</p>
                    <p class="text-xs font-bold text-white/60">
                        <?= !empty($record['recorded_first_name']) ? htmlspecialchars($record['recorded_first_name'] . ' ' . $record['recorded_last_name']) : 'System' ?>
                    </p>
                    <p class="text-[10px] text-white/40 mt-0.5"><?= date('M d, Y @ h:i A', strtotime($record['recorded_at'])) ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- MEDICATIONS & LAB RESULTS -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

        <!-- Medications -->
        <div class="bg-white border border-slate-100 rounded-[2rem] p-6 shadow-sm">
            <div class="flex items-center gap-3 mb-5 pb-3 border-b border-slate-50">
                <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-sm">
                    <i class="fas fa-pills"></i>
                </div>
                <h5 class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Medications Prescribed</h5>
            </div>
            <div class="flex flex-wrap gap-2">
                <?php if ($record['iron_supplement']): ?>
                    <span class="inline-flex items-center gap-2 bg-amber-50 border border-amber-200 text-amber-700 text-xs font-bold px-4 py-2 rounded-2xl">
                        <i class="fas fa-capsules text-amber-500 text-xs"></i> Iron Supplement
                    </span>
                <?php endif; ?>
                <?php if ($record['folic_acid']): ?>
                    <span class="inline-flex items-center gap-2 bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs font-bold px-4 py-2 rounded-2xl">
                        <i class="fas fa-leaf text-emerald-500 text-xs"></i> Folic Acid
                    </span>
                <?php endif; ?>
                <?php if ($record['calcium']): ?>
                    <span class="inline-flex items-center gap-2 bg-sky-50 border border-sky-200 text-sky-700 text-xs font-bold px-4 py-2 rounded-2xl">
                        <i class="fas fa-bone text-sky-500 text-xs"></i> Calcium
                    </span>
                <?php endif; ?>
                <?php if (!empty($record['other_medications'])): ?>
                    <span class="inline-flex items-center gap-2 bg-violet-50 border border-violet-200 text-violet-700 text-xs font-bold px-4 py-2 rounded-2xl">
                        <i class="fas fa-prescription-bottle text-violet-500 text-xs"></i>
                        <?= htmlspecialchars($record['other_medications']) ?>
                    </span>
                <?php endif; ?>
                <?php if (!$record['iron_supplement'] && !$record['folic_acid'] && !$record['calcium'] && empty($record['other_medications'])): ?>
                    <p class="text-sm font-medium text-slate-300 italic">No medications prescribed this visit</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Lab Results -->
        <div class="bg-white border border-slate-100 rounded-[2rem] p-6 shadow-sm">
            <div class="flex items-center gap-3 mb-5 pb-3 border-b border-slate-50">
                <div class="w-9 h-9 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center text-sm">
                    <i class="fas fa-flask"></i>
                </div>
                <h5 class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Lab Results</h5>
            </div>
            <div class="space-y-3">
                <?php if ($record['hb_level']): ?>
                <?php $hbLow = $record['hb_level'] < 11; ?>
                <div class="flex items-center justify-between bg-<?= $hbLow ? 'rose' : 'emerald' ?>-50 border border-<?= $hbLow ? 'rose' : 'emerald' ?>-100 rounded-2xl px-4 py-3">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-flask text-<?= $hbLow ? 'rose' : 'emerald' ?>-500 text-xs"></i>
                        <span class="text-[10px] font-black text-<?= $hbLow ? 'rose' : 'emerald' ?>-600 uppercase tracking-widest">Hemoglobin</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-black text-<?= $hbLow ? 'rose' : 'emerald' ?>-700"><?= $record['hb_level'] ?> g/dL</span>
                        <?php if ($hbLow): ?>
                        <span class="text-[9px] font-black bg-rose-100 text-rose-600 px-2 py-0.5 rounded-full uppercase">Low</span>
                        <?php else: ?>
                        <span class="text-[9px] font-black bg-emerald-100 text-emerald-600 px-2 py-0.5 rounded-full uppercase">Normal</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($record['blood_group']): ?>
                <div class="flex items-center justify-between bg-slate-50 border border-slate-100 rounded-2xl px-4 py-3">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-droplet text-slate-400 text-xs"></i>
                        <span class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Blood Group</span>
                    </div>
                    <span class="text-sm font-black text-slate-700"><?= htmlspecialchars($record['blood_group']) ?></span>
                </div>
                <?php endif; ?>

                <?php if ($record['urinalysis']): ?>
                <div class="flex items-center justify-between bg-violet-50 border border-violet-100 rounded-2xl px-4 py-3">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-vial text-violet-400 text-xs"></i>
                        <span class="text-[10px] font-black text-violet-500 uppercase tracking-widest">Urinalysis</span>
                    </div>
                    <span class="text-xs font-bold text-violet-700"><?= htmlspecialchars($record['urinalysis']) ?></span>
                </div>
                <?php endif; ?>

                <?php if (!$record['hb_level'] && !$record['blood_group'] && !$record['urinalysis']): ?>
                    <p class="text-sm font-medium text-slate-300 italic py-2">No lab results recorded for this visit</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ALL VISITS HISTORY TABLE -->
    <?php if (count($allVisits) > 1): ?>
    <div class="bg-white border border-slate-100 rounded-[2rem] overflow-hidden shadow-sm">
        <div class="flex items-center gap-3 px-6 py-4 border-b border-slate-50 bg-slate-50/50">
            <div class="w-8 h-8 rounded-xl bg-health-50 text-health-600 flex items-center justify-center text-xs">
                <i class="fas fa-clock-rotate-left"></i>
            </div>
            <h5 class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Complete Visit History</h5>
            <span class="ml-auto text-[9px] font-black bg-health-50 text-health-600 border border-health-100 px-2.5 py-1 rounded-full uppercase tracking-widest"><?= count($allVisits) ?> Visits</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-slate-50/30">
                        <th class="px-5 py-3 text-left text-[9px] font-black text-slate-400 uppercase tracking-[0.15em]">Visit</th>
                        <th class="px-5 py-3 text-left text-[9px] font-black text-slate-400 uppercase tracking-[0.15em]">Date</th>
                        <th class="px-5 py-3 text-left text-[9px] font-black text-slate-400 uppercase tracking-[0.15em]">Weight</th>
                        <th class="px-5 py-3 text-left text-[9px] font-black text-slate-400 uppercase tracking-[0.15em]">BP</th>
                        <th class="px-5 py-3 text-left text-[9px] font-black text-slate-400 uppercase tracking-[0.15em]">Temp</th>
                        <th class="px-5 py-3 text-left text-[9px] font-black text-slate-400 uppercase tracking-[0.15em]">Next Visit</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allVisits as $v):
                        $isCurrent = ($v['id'] == $recordId);
                    ?>
                    <tr class="border-t border-slate-50 <?= $isCurrent ? 'bg-health-50/50' : 'hover:bg-slate-50/50' ?> transition-colors">
                        <td class="px-5 py-3">
                            <span class="text-[10px] font-black <?= $isCurrent ? 'bg-health-600 text-white' : 'bg-slate-100 text-slate-500' ?> px-2.5 py-1 rounded-full">
                                #<?= $v['visit_number'] ?>
                                <?php if ($isCurrent): ?><span class="ml-1 opacity-70">●</span><?php endif; ?>
                            </span>
                        </td>
                        <td class="px-5 py-3 text-xs font-bold text-slate-700"><?= date('M d, Y', strtotime($v['visit_date'])) ?></td>
                        <td class="px-5 py-3 text-xs font-bold text-slate-600"><?= $v['weight'] ? $v['weight'] . ' kg' : '—' ?></td>
                        <td class="px-5 py-3 text-xs font-bold text-slate-600"><?= $v['blood_pressure'] ?: '—' ?></td>
                        <td class="px-5 py-3 text-xs font-bold text-slate-600"><?= $v['temperature'] ? $v['temperature'] . '°C' : '—' ?></td>
                        <td class="px-5 py-3 text-xs font-medium text-slate-500">
                            <?= (!empty($v['next_visit_date']) && $v['next_visit_date'] !== '0000-00-00') ? date('M d, Y', strtotime($v['next_visit_date'])) : '—' ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- REMARKS / NOTES -->
    <?php if (!empty($record['remarks']) || !empty($record['treatment'])): ?>
    <div class="bg-amber-50 border border-amber-100 rounded-[2rem] p-6 space-y-3">
        <div class="flex items-center gap-2 mb-1">
            <i class="fas fa-sticky-note text-amber-500"></i>
            <h5 class="text-[10px] font-black text-amber-600 uppercase tracking-widest">Clinical Notes</h5>
        </div>
        <?php if (!empty($record['treatment'])): ?>
        <div>
            <p class="text-[9px] font-black text-amber-500/70 uppercase tracking-widest mb-1">Treatment</p>
            <p class="text-sm font-medium text-amber-900 leading-relaxed"><?= htmlspecialchars($record['treatment']) ?></p>
        </div>
        <?php endif; ?>
        <?php if (!empty($record['remarks'])): ?>
        <div>
            <p class="text-[9px] font-black text-amber-500/70 uppercase tracking-widest mb-1">Remarks</p>
            <p class="text-sm font-medium text-amber-900 leading-relaxed"><?= htmlspecialchars($record['remarks']) ?></p>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>

<?php
} catch (PDOException $e) {
    error_log("DB error in get_prenatal_details.php: " . $e->getMessage());
    http_response_code(500);
    echo '<div class="p-8 bg-rose-50 border border-rose-100 rounded-[2rem] text-center">
            <i class="fas fa-database text-rose-400 text-2xl mb-3 block"></i>
            <p class="text-xs font-black text-rose-800 uppercase tracking-widest">Database Error</p>
          </div>';
} catch (Exception $e) {
    error_log("Error in get_prenatal_details.php: " . $e->getMessage());
    http_response_code(500);
    echo '<div class="p-8 bg-amber-50 border border-amber-100 rounded-[2rem] text-center">
            <i class="fas fa-triangle-exclamation text-amber-400 text-2xl mb-3 block"></i>
            <p class="text-xs font-black text-amber-800 uppercase tracking-widest">Unexpected Error</p>
          </div>';
}
?>