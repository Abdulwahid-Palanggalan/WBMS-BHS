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

$babyId = intval($_GET['id']);

// Get complete birth record details with proper table relationships
$query = "SELECT br.*, 
                 m.first_name as mother_first_name,
                 m.middle_name as mother_middle_name,
                 m.last_name as mother_last_name,
                 m.date_of_birth as mother_date_of_birth,
                 m.nationality as mother_nationality,
                 m.religion as mother_religion,
                 m.occupation as mother_occupation,
                 m.address as mother_address,
                 m.phone as mother_phone,
                 hp.first_name as father_first_name,
                 hp.middle_name as father_middle_name,
                 hp.last_name as father_last_name,
                 hp.date_of_birth as father_date_of_birth,
                 hp.citizenship as father_citizenship,
                 hp.religion as father_religion,
                 hp.occupation as father_occupation,
                 hp.phone as father_phone,
                 hp.marriage_date,
                 hp.marriage_place,
                 u.first_name as registered_by_firstname, 
                 u.last_name as registered_by_lastname,
                 bi.informant_name,
                 bi.informant_relationship,
                 bi.informant_address
          FROM birth_records br 
          LEFT JOIN mothers m ON br.mother_id = m.id
          LEFT JOIN husband_partners hp ON m.id = hp.mother_id
          LEFT JOIN birth_informants bi ON br.id = bi.birth_record_id
          LEFT JOIN users u ON br.registered_by = u.id
          WHERE br.id = ?";

$stmt = $pdo->prepare($query);
$stmt->execute([$babyId]);
$baby = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$baby) {
    echo '<div class="alert alert-danger">Birth record not found.</div>';
    exit;
}

// Helper functions
function displayData($value, $default = 'N/A') {
    return !empty($value) && $value != '0000-00-00' ? htmlspecialchars($value) : $default;
}

function displayDate($date, $format = 'F j, Y') {
    if (empty($date) || $date == '0000-00-00') return 'N/A';
    return date($format, strtotime($date));
}

function displayDateTime($date, $time, $format = 'F j, Y g:i A') {
    if (empty($date) || $date == '0000-00-00') return 'N/A';
    $datetime = $date . ' ' . $time;
    return date($format, strtotime($datetime));
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
                <!-- Avatar / Initials -->
                <?php $initials = strtoupper(substr($baby['first_name'], 0, 1) . substr($baby['last_name'], 0, 1)); ?>
                <div class="w-16 h-16 md:w-20 md:h-20 rounded-[1.8rem] bg-gradient-to-br from-health-400 to-health-600 p-0.5 shadow-lg shadow-health-950/20 flex-shrink-0">
                    <div class="w-full h-full bg-slate-900/40 backdrop-blur-xl rounded-[1.7rem] flex items-center justify-center text-2xl font-black text-white">
                        <?= $initials ?>
                    </div>
                </div>
                <!-- Identity -->
                <div>
                    <div class="flex items-center gap-3 mb-1">
                        <span class="px-2.5 py-1 rounded-full bg-health-500/20 border border-health-500/30 text-[9px] font-black uppercase tracking-widest text-health-400 font-sans">Birth Registry</span>
                        <span class="text-[10px] font-bold text-slate-500 tracking-widest uppercase font-sans">#B-<?= $baby['id'] ?></span>
                    </div>
                    <h2 class="text-2xl md:text-3xl font-black tracking-tight leading-none mb-2 font-sans">
                        <?php 
                        $babyName = htmlspecialchars($baby['first_name']);
                        if (!empty($baby['middle_name'])) $babyName .= ' ' . htmlspecialchars($baby['middle_name']);
                        $babyName .= ' ' . htmlspecialchars($baby['last_name']);
                        echo $babyName;
                        ?>
                    </h2>
                    <div class="flex flex-wrap items-center gap-x-5 gap-y-2 opacity-60">
                        <span class="text-sm font-medium flex items-center gap-2">
                            <i class="fas fa-calendar-alt text-health-400"></i>
                            <?= displayDate($baby['birth_date']) ?>
                        </span>
                        <span class="text-sm font-medium flex items-center gap-2">
                            <i class="fas fa-clock text-health-400"></i>
                            <?= !empty($baby['birth_time']) ? date('G:i A', strtotime($baby['birth_time'])) : 'N/A' ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Quick Stats in Banner -->
            <div class="flex items-center gap-3 md:text-right">
                <div class="bg-white/5 border border-white/10 rounded-2xl p-4 backdrop-blur-md">
                    <p class="text-[9px] font-black uppercase tracking-widest text-health-400 mb-1">Gender</p>
                    <p class="text-xl font-black text-white uppercase"><?= $baby['gender'] ?: 'Unk' ?></p>
                </div>
                <button onclick="window.print()" class="w-12 h-12 rounded-2xl bg-white/10 hover:bg-white/20 border border-white/10 flex items-center justify-center text-white transition-all active:scale-90" title="Print Birth Record">
                    <i class="fas fa-print"></i>
                </button>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- Left Section: Newborn & Delivery (8 cols) -->
        <div class="lg:col-span-8 space-y-6">
            
            <!-- 1. Newborn Measurements & Identity -->
            <div class="bg-white rounded-[2rem] border border-slate-100 p-6 shadow-sm">
                <div class="flex items-center gap-3 mb-6 pb-4 border-b border-slate-50">
                    <div class="w-10 h-10 rounded-xl bg-health-50 text-health-600 flex items-center justify-center text-lg shadow-sm">
                        <i class="fas fa-baby"></i>
                    </div>
                    <h4 class="text-sm font-black text-slate-900 tracking-tight uppercase">Newborn Vitality & Scale</h4>
                </div>
                
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div class="group bg-slate-50 border border-slate-100 p-5 rounded-3xl text-center transition-all hover:bg-emerald-50 hover:border-emerald-100">
                        <i class="fas fa-weight-scale text-emerald-500/40 mb-3 group-hover:scale-110 transition-transform"></i>
                        <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1.5 group-hover:text-emerald-600">Weight</p>
                        <p class="text-lg font-black text-slate-800 tracking-tight leading-none"><?= $baby['birth_weight'] ?: '—' ?></p>
                        <p class="text-[9px] font-bold text-slate-400 mt-1">Kilograms</p>
                    </div>
                    <div class="group bg-slate-50 border border-slate-100 p-5 rounded-3xl text-center transition-all hover:bg-sky-50 hover:border-sky-100">
                        <i class="fas fa-ruler-vertical text-sky-500/40 mb-3 group-hover:scale-110 transition-transform"></i>
                        <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1.5 group-hover:text-sky-600">Length</p>
                        <p class="text-lg font-black text-slate-800 tracking-tight leading-none"><?= $baby['birth_length'] ?: '—' ?></p>
                        <p class="text-[9px] font-bold text-slate-400 mt-1">Centimeters</p>
                    </div>
                    <div class="group bg-slate-50 border border-slate-100 p-5 rounded-3xl text-center transition-all hover:bg-amber-50 hover:border-amber-100">
                        <i class="fas fa-list-ol text-amber-500/40 mb-3 group-hover:scale-110 transition-transform"></i>
                        <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1.5 group-hover:text-amber-600">Birth Order</p>
                        <p class="text-lg font-black text-slate-800 tracking-tight leading-none"><?= $baby['birth_order'] ?: '—' ?></p>
                        <p class="text-[9px] font-bold text-slate-400 mt-1">Sequence</p>
                    </div>
                    <div class="group bg-slate-50 border border-slate-100 p-5 rounded-3xl text-center transition-all hover:bg-violet-50 hover:border-violet-100">
                        <i class="fas fa-children text-violet-500/40 mb-3 group-hover:scale-110 transition-transform"></i>
                        <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1.5 group-hover:text-violet-600">Birth Type</p>
                        <p class="text-base font-black text-slate-800 tracking-tight leading-none truncate px-1"><?= $baby['type_of_birth'] ?: 'Single' ?></p>
                        <p class="text-[9px] font-bold text-slate-400 mt-1">Classification</p>
                    </div>
                </div>
            </div>

            <!-- 2. Delivery Profile Panel -->
            <div class="bg-white rounded-[2rem] border border-slate-100 p-8 shadow-sm relative group overflow-hidden">
                <div class="absolute right-0 top-0 w-32 h-32 bg-health-500/5 -mr-16 -mt-16 rounded-full"></div>
                
                <h4 class="text-sm font-black text-slate-900 tracking-tight uppercase flex items-center gap-3 mb-8">
                    <span class="w-1.5 h-6 bg-health-500 rounded-full"></span>
                    Delivery & Clinical Context
                </h4>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 relative z-10">
                    <div class="space-y-6">
                        <div class="p-5 rounded-2xl bg-slate-50 border border-slate-100 shadow-sm transition-all hover:shadow-md">
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 flex items-center gap-2">
                                <i class="fas fa-stethoscope"></i> Delivery Methodology
                            </p>
                            <p class="text-sm text-slate-700 font-black tracking-tight flex items-center justify-between">
                                <span><?= $baby['delivery_type'] ?: 'Normal Spontaneous' ?></span>
                                <span class="text-[10px] text-health-600 bg-health-50 px-2 py-0.5 rounded-full border border-health-100">Verified</span>
                            </p>
                        </div>
                        <div class="p-5 rounded-2xl bg-health-50/50 border border-health-100/50">
                            <p class="text-[10px] font-black text-health-600 uppercase tracking-widest mb-2 flex items-center gap-2">
                                <i class="fas fa-user-doctor"></i> Attending Healthcare Provider
                            </p>
                            <p class="text-base font-black text-slate-800 tracking-tight">
                                <?= $baby['birth_attendant'] ?: 'Unspecified Provider' ?>
                            </p>
                            <p class="text-[10px] font-bold text-slate-400 uppercase mt-0.5"><?= $baby['birth_attendant_title'] ?: 'Medical Professional' ?></p>
                        </div>
                    </div>
                    
                    <div class="space-y-6">
                        <div class="p-6 rounded-[1.5rem] bg-slate-900 text-white shadow-xl shadow-slate-200">
                            <p class="text-[10px] font-black text-health-400 uppercase tracking-widest mb-3 flex items-center gap-2">
                                <i class="fas fa-building-circle-check"></i> Facility Information
                            </p>
                            <p class="text-sm font-bold leading-relaxed tracking-tight">
                                <?= $baby['birth_place'] ?: 'Facility Name Not Provided' ?>
                            </p>
                            <p class="text-[10px] font-bold text-slate-500 uppercase mt-1">Type: <?= $baby['birth_place_type'] ?: 'Clinical Center' ?></p>
                        </div>
                    </div>
                </div>

                <!-- Locality Footer -->
                <div class="mt-8 pt-6 border-t border-slate-50 flex items-center gap-6">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center text-slate-400">
                            <i class="fas fa-location-dot"></i>
                        </div>
                        <div>
                            <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Geographic Signature</p>
                            <p class="text-xs font-bold text-slate-700"><?= $baby['birth_city'] ?>, <?= $baby['birth_province'] ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 3. Informant Registry -->
            <?php if (!empty($baby['informant_name'])): ?>
            <div class="bg-slate-900 rounded-[2.5rem] p-8 text-white relative overflow-hidden group shadow-2xl">
                <i class="fas fa-file-signature absolute -right-4 -bottom-4 text-white/5 text-8xl group-hover:scale-110 transition-transform duration-500"></i>
                <div class="relative z-10">
                    <div class="flex items-center gap-4 mb-6">
                        <div class="w-10 h-10 rounded-xl bg-white/10 backdrop-blur-md flex items-center justify-center text-health-400 border border-white/10">
                            <i class="fas fa-user-pen"></i>
                        </div>
                        <h4 class="text-sm font-black text-white/70 tracking-tight uppercase">Statement Informant</h4>
                    </div>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-8">
                        <div>
                            <p class="text-[9px] font-black text-health-400 uppercase tracking-[0.2em] mb-1">Authenticated By</p>
                            <p class="text-lg font-black text-white"><?= $baby['informant_name'] ?></p>
                            <p class="text-[10px] font-bold text-slate-500 uppercase"><?= $baby['informant_relationship'] ?></p>
                        </div>
                        <div>
                            <p class="text-[9px] font-black text-health-400 uppercase tracking-[0.2em] mb-1">Informant Address</p>
                            <p class="text-sm font-bold text-slate-300 leading-relaxed"><?= $baby['informant_address'] ?></p>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Right Section: Parental Matrix (4 cols) -->
        <div class="lg:col-span-4 space-y-6">
            
            <!-- 4. Maternal Profile -->
            <div class="bg-white rounded-[2rem] border border-slate-100 p-6 shadow-sm overflow-hidden relative">
                <div class="absolute -right-4 top-1/2 -translate-y-1/2 w-20 h-20 bg-rose-50 rounded-full blur-2xl"></div>
                <h4 class="text-[10px] font-black text-rose-500 uppercase tracking-[0.2em] mb-5 flex items-center gap-2">
                    <span class="w-1 h-3 bg-rose-500 rounded-full"></span> Maternal Identity
                </h4>
                
                <div class="space-y-5 relative z-10">
                    <div>
                        <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1 text-center">Identity Signature</p>
                        <div class="p-3 bg-rose-50/50 rounded-2xl border border-rose-100 text-center">
                            <p class="text-sm font-black text-slate-800">
                                <?= htmlspecialchars($baby['mother_first_name'] . ' ' . $baby['mother_last_name']) ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 gap-2">
                        <div class="flex items-center justify-between p-3 bg-slate-50 rounded-2xl border border-slate-100/50 transition-all hover:border-slate-200">
                            <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Nationality</span>
                            <span class="text-xs font-bold text-slate-700"><?= $baby['mother_nationality'] ?: 'Filipino' ?></span>
                        </div>
                        <div class="flex items-center justify-between p-3 bg-slate-50 rounded-2xl border border-slate-100/50">
                            <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Occupation</span>
                            <span class="text-xs font-bold text-slate-700"><?= $baby['mother_occupation'] ?: 'None specified' ?></span>
                        </div>
                        <div class="flex items-center justify-between p-3 bg-slate-50 rounded-2xl border border-slate-100/50">
                            <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Religion</span>
                            <span class="text-xs font-bold text-slate-700"><?= $baby['mother_religion'] ?: 'N/A' ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 5. Paternal Profile -->
            <div class="bg-white rounded-[2rem] border border-slate-100 p-6 shadow-sm overflow-hidden relative">
                <div class="absolute -right-4 top-1/2 -translate-y-1/2 w-20 h-20 bg-sky-50 rounded-full blur-2xl"></div>
                <h4 class="text-[10px] font-black text-sky-500 uppercase tracking-[0.2em] mb-5 flex items-center gap-2">
                    <span class="w-1 h-3 bg-sky-500 rounded-full"></span> Paternal Identity
                </h4>
                
                <div class="space-y-5 relative z-10">
                    <div>
                        <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1 text-center">Identity Signature</p>
                        <div class="p-3 bg-sky-50/50 rounded-2xl border border-sky-100 text-center">
                            <p class="text-sm font-black text-slate-800">
                                <?= !empty($baby['father_first_name']) ? htmlspecialchars($baby['father_first_name'] . ' ' . $baby['father_last_name']) : 'Information Restricted' ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 gap-2">
                        <div class="flex items-center justify-between p-3 bg-slate-50 rounded-2xl border border-slate-100/50">
                            <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Citizenship</span>
                            <span class="text-xs font-bold text-slate-700"><?= $baby['father_citizenship'] ?: 'Filipino' ?></span>
                        </div>
                        <div class="flex items-center justify-between p-3 bg-slate-50 rounded-2xl border border-slate-100/50">
                            <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Occupation</span>
                            <span class="text-xs font-bold text-slate-700"><?= $baby['father_occupation'] ?: 'N/A' ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 6. Marriage Authentication -->
            <?php if (!empty($baby['marriage_date']) && $baby['marriage_date'] !== '0000-00-00'): ?>
            <div class="bg-amber-50 rounded-[2rem] border border-amber-100 p-6">
                <h4 class="text-[10px] font-black text-amber-600 uppercase tracking-[0.2em] mb-4">Marriage Registry</h4>
                <div class="space-y-3">
                    <div class="flex items-center gap-4 bg-white/50 p-3 rounded-2xl">
                        <i class="fas fa-calendar-heart text-amber-500"></i>
                        <div>
                            <p class="text-[8px] font-black text-slate-400 uppercase">Marriage Date</p>
                            <p class="text-xs font-black text-slate-800"><?= date('M d, Y', strtotime($baby['marriage_date'])) ?></p>
                        </div>
                    </div>
                    <?php if (!empty($baby['marriage_place'])): ?>
                    <div class="flex items-center gap-4 bg-white/50 p-3 rounded-2xl text-center">
                        <i class="fas fa-location-dot text-amber-500"></i>
                        <div class="text-left">
                            <p class="text-[8px] font-black text-slate-400 uppercase">Solemnized At</p>
                            <p class="text-[10px] font-bold text-slate-700 leading-tight"><?= $baby['marriage_place'] ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- 7. Registration Audit -->
            <div class="bg-gradient-to-br from-slate-800 to-slate-900 rounded-[2rem] p-6 text-white shadow-xl">
                <div class="space-y-5">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl bg-health-500/10 border border-health-500/20 flex items-center justify-center text-health-400 shadow-sm">
                            <i class="fas fa-clipboard-check text-lg"></i>
                        </div>
                        <div>
                            <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">System Record Date</p>
                            <p class="text-base font-black text-white"><?= displayDate($baby['created_at']) ?></p>
                        </div>
                    </div>
                    <div class="pt-5 border-t border-white/5">
                        <div class="flex items-center justify-between bg-white/5 rounded-2xl p-4">
                            <div class="text-center flex-1">
                                <p class="text-[8px] font-black text-health-500 uppercase mb-0.5">Registry Officer</p>
                                <p class="text-[11px] font-bold text-slate-200">
                                    <?= !empty($baby['registered_by_firstname']) ? htmlspecialchars($baby['registered_by_firstname'] . ' ' . $baby['registered_by_lastname']) : 'System Autom' ?>
                                </p>
                            </div>
                            <div class="w-px h-8 bg-white/10 mx-2"></div>
                            <div class="text-center flex-1">
                                <p class="text-[8px] font-black text-health-500 uppercase mb-0.5">Status</p>
                                <p class="text-[11px] font-bold text-emerald-400 tracking-widest uppercase">Certified</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- CSS for Print -->
<style>
@media print {
    body * { visibility: hidden; }
    #birthDetailsModal, #birthDetailsModal * { visibility: visible; }
    #birthDetailsModal { position: absolute !important; left: 0; top: 0; width: 100% !important; margin: 0 !important; padding: 0 !important; }
    .modal-dialog { max-width: 100% !important; width: 100% !important; border: none !important; margin: 0 !important; }
    .modal-content { border: none !important; box-shadow: none !important; }
    /* Hide modal UI elements */
    .modal-header button, .modal-footer, #editBirthBtn, button[title="Print Birth Record"] { display: none !important; }
}
</style>
