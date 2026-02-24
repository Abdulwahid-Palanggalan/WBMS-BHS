<?php
// forms/family_planning_form.php
require_once '../includes/auth.php';
require_once '../includes/functions.php';

if (!isAuthorized(['admin', 'midwife'])) {
    header("Location: ../login.php");
    exit();
}

$message = '';
$error = '';
$mothers = $pdo->query("SELECT id, first_name, last_name FROM mothers ORDER BY last_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$methods = $pdo->query("SELECT * FROM family_planning_methods")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $motherId = $_POST['mother_id'];
    $methodId = $_POST['method_id'];
    $regDate = $_POST['registration_date'];
    $nextDate = !empty($_POST['next_service_date']) ? $_POST['next_service_date'] : null;
    $remarks = $_POST['remarks'];
    $workerId = $_SESSION['user_id'];

    if ($nextDate && $regDate && strtotime($nextDate) <= strtotime($regDate)) {
        $error = "Next service date must be after the registration date.";
    } elseif (empty($motherId) || empty($methodId) || empty($regDate)) {
        $error = "Please fill in required fields.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO family_planning_records (mother_id, method_id, registration_date, next_service_date, remarks, health_worker_id) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$motherId, $methodId, $regDate, $nextDate, $remarks, $workerId])) {
            $message = "Record saved successfully!";
             echo "<script>setTimeout(function() { window.location.href = '../family_planning.php'; }, 1500);</script>";
        } else {
            $error = "Failed to save record.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Family Planning Registration | Kibenes eBirth</title>
    <!-- Modern Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <!-- Tailwind CSS Components -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        health: {
                            50: '#f0f9ff', 100: '#e0f2fe', 200: '#bae6fd', 
                            300: '#7dd3fc', 400: '#38bdf8', 500: '#0ea5e9', 
                            600: '#0284c7', 700: '#0369a1', 800: '#075985', 900: '#0c4a6e'
                        }
                    },
                    fontFamily: { inter: ['Inter', 'sans-serif'] }
                }
            }
        }
    </script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="bg-health-50 font-inter text-slate-900 antialiased selection:bg-health-100 selection:text-health-700">
    <?php include_once '../includes/header.php'; ?>
    
    <div class="flex flex-col lg:flex-row min-h-[calc(100vh-4rem)]">
        <?php include_once '../includes/sidebar.php'; ?>
        
        <main class="flex-1 p-4 lg:p-8 space-y-8 no-print">
            <!-- Header Section -->
            <header class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="space-y-1">
                    <h1 class="text-3xl font-black text-slate-900 tracking-tight flex items-center gap-3">
                        <span class="w-12 h-12 rounded-2xl bg-health-600 flex items-center justify-center shadow-lg shadow-health-200">
                            <i class="fas fa-venus text-white text-xl"></i>
                        </span>
                        Family Planning Registration
                    </h1>
                    <p class="text-slate-500 font-medium flex items-center gap-2">
                        <i class="fas fa-info-circle text-health-400"></i>
                        Registering comprehensive reproductive health services
                    </p>
                </div>
                <a href="../family_planning.php" 
                   class="inline-flex items-center gap-2 px-6 py-3 bg-white text-slate-600 rounded-2xl font-bold transition-all hover:bg-slate-50 border border-slate-200 shadow-sm active:scale-95">
                    <i class="fas fa-arrow-left"></i>
                    Back to Services
                </a>
            </header>

            <?php if ($message): ?>
                <div class="p-4 bg-emerald-50 border border-emerald-100 rounded-2xl flex items-center gap-4 text-emerald-700 font-bold animate-in fade-in slide-in-from-top-4 duration-500">
                    <i class="fas fa-check-circle text-emerald-500 text-xl"></i>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="p-4 bg-rose-50 border border-rose-100 rounded-2xl flex items-center gap-4 text-rose-700 font-bold animate-in fade-in slide-in-from-top-4 duration-500">
                    <i class="fas fa-exclamation-circle text-rose-500 text-xl"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="fpForm" class="space-y-8">
                <!-- Registration Primary Details -->
                <section class="card-premium">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-user-friends text-health-600"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-slate-800">Client & Method Selection</h2>
                            <p class="text-xs text-slate-400 font-bold uppercase tracking-widest mt-0.5">Primary registration parameters</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div>
                            <label for="mother_id" class="form-label-premium">Client Name (Mother) <span class="text-rose-500">*</span></label>
                            <div class="relative group">
                                <select name="mother_id" id="mother_id" class="form-input-premium appearance-none pr-10" required>
                                    <option value="">Select Mother Profile</option>
                                    <?php foreach ($mothers as $m): ?>
                                        <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['last_name'] . ', ' . $m['first_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-slate-400 group-hover:text-health-500 transition-colors">
                                    <i class="fas fa-chevron-down text-xs"></i>
                                </div>
                            </div>
                            <div class="hidden mt-2 p-2 bg-rose-50 text-rose-600 text-xs font-bold rounded-lg border border-rose-100" id="mother_id_warning">
                                Required field
                            </div>
                        </div>

                        <div>
                            <label for="method_id" class="form-label-premium">Contraceptive Method <span class="text-rose-500">*</span></label>
                            <div class="relative group">
                                <select name="method_id" id="method_id" class="form-input-premium appearance-none pr-10" required>
                                    <option value="">Select Method</option>
                                    <?php foreach ($methods as $method): ?>
                                        <option value="<?php echo $method['id']; ?>"><?php echo htmlspecialchars($method['method_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-slate-400 group-hover:text-health-500 transition-colors">
                                    <i class="fas fa-chevron-down text-xs"></i>
                                </div>
                            </div>
                            <div class="hidden mt-2 p-2 bg-rose-50 text-rose-600 text-xs font-bold rounded-lg border border-rose-100" id="method_id_warning">
                                Required field
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Scheduling & Dates -->
                <section class="card-premium">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-calendar-alt text-health-600"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-slate-800">Visit Scheduling</h2>
                            <p class="text-xs text-slate-400 font-bold uppercase tracking-widest mt-0.5">Registration and follow-up timing</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div>
                            <label for="registration_date" class="form-label-premium">Registration Date <span class="text-rose-500">*</span></label>
                            <input type="date" name="registration_date" id="registration_date" class="form-input-premium font-black text-health-700" value="<?php echo date('Y-m-d'); ?>" required>
                            <div class="hidden mt-2 p-2 bg-rose-50 text-rose-600 text-xs font-bold rounded-lg border border-rose-100" id="registration_date_warning">
                                Required field
                            </div>
                        </div>
                        <div>
                            <label for="next_service_date" class="form-label-premium group flex items-center gap-2">
                                Next Follow-up Date
                                <span class="text-[10px] bg-sky-100 text-sky-600 px-2 py-0.5 rounded-full font-black uppercase">Optional</span>
                            </label>
                            <input type="date" name="next_service_date" id="next_service_date" class="form-input-premium border-sky-100 focus:border-sky-500 focus:ring-sky-200">
                            <div class="hidden mt-2 p-2 bg-rose-50 text-rose-600 text-xs font-bold rounded-lg border border-rose-100" id="next_service_date_warning">
                                Must be after registration date
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Additional Information -->
                <section class="card-premium">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-clipboard-list text-health-600"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-slate-800">Remarks & Observations</h2>
                            <p class="text-xs text-slate-400 font-bold uppercase tracking-widest mt-0.5">Clinical notes or patient history</p>
                        </div>
                    </div>

                    <div>
                        <textarea name="remarks" id="remarks" class="form-input-premium min-h-[140px] py-4 leading-relaxed" placeholder="Enter clinical notes, patient preferences, or historical observations..."></textarea>
                    </div>
                </section>

                <!-- Form Actions -->
                <div class="flex flex-col sm:flex-row justify-end items-center gap-4 pt-8 pb-12">
                    <button type="button" onclick="window.history.back()" 
                            class="w-full sm:w-auto px-10 py-4 bg-slate-100 text-slate-600 rounded-2xl font-bold uppercase tracking-widest text-xs hover:bg-slate-200 transition-all active:scale-95">
                        Cancel Registration
                    </button>
                    <button type="submit" 
                            class="w-full sm:w-auto px-16 py-4 bg-health-600 text-white rounded-2xl font-black uppercase tracking-widest text-xs hover:bg-health-700 shadow-xl shadow-health-200 transition-all active:scale-95 flex items-center justify-center gap-3">
                        <i class="fas fa-save shadow-sm"></i>
                        Save Record
                    </button>
                </div>
            </form>
        </main>
    </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('fpForm');
            const regDateInput = document.getElementById('registration_date');
            const nextDateInput = document.getElementById('next_service_date');

            // Real-time validation for required fields
            const requiredFields = ['mother_id', 'method_id', 'registration_date'];
            
            requiredFields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (!field) return;

                field.addEventListener('blur', function() {
                    validateField(this);
                });
                
                field.addEventListener('input', function() {
                    if (this.value.trim() !== '') {
                        hideWarning(this.id);
                    }
                });
            });

            function validateField(field) {
                if (field.value.trim() === '') {
                    showWarning(field.id);
                    return false;
                }
                hideWarning(field.id);
                return true;
            }

            function showWarning(fieldId) {
                const warning = document.getElementById(fieldId + '_warning');
                const field = document.getElementById(fieldId);
                if (warning) warning.classList.remove('hidden');
                if (field) {
                    field.classList.add('border-rose-500', 'ring-2', 'ring-rose-200');
                    field.classList.add('animate-shake');
                    setTimeout(() => field.classList.remove('animate-shake'), 500);
                }
            }

            function hideWarning(fieldId) {
                const warning = document.getElementById(fieldId + '_warning');
                const field = document.getElementById(fieldId);
                if (warning) warning.classList.add('hidden');
                if (field) field.classList.remove('border-rose-500', 'ring-2', 'ring-rose-200');
            }

            function validateDates() {
                const regDate = new Date(regDateInput.value);
                const nextDate = nextDateInput.value ? new Date(nextDateInput.value) : null;
                
                if (nextDate && nextDate <= regDate) {
                    showWarning('next_service_date');
                    return false;
                } else {
                    hideWarning('next_service_date');
                    return true;
                }
            }

            regDateInput.addEventListener('change', validateDates);
            nextDateInput.addEventListener('change', validateDates);

            form.addEventListener('submit', function(e) {
                let isValid = true;
                
                requiredFields.forEach(fieldId => {
                    const field = document.getElementById(fieldId);
                    if (field && !validateField(field)) {
                        isValid = false;
                    }
                });
                
                if (!validateDates()) {
                    isValid = false;
                }
                
                if (!isValid) {
                    e.preventDefault();
                    
                    // Find first error and scroll
                    const firstError = document.querySelector('[id$="_warning"]:not(.hidden)');
                    if (firstError) {
                        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        const errorField = document.getElementById(firstError.id.replace('_warning', ''));
                        if (errorField) {
                            errorField.classList.add('animate-pulse');
                            setTimeout(() => errorField.classList.remove('animate-pulse'), 1000);
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>
