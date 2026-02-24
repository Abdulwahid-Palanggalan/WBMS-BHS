<?php
// forms/immunization_form.php
require_once '../includes/auth.php';
require_once '../includes/functions.php';

if (!isAuthorized(['admin', 'midwife'])) {
    header("Location: ../login.php");
    exit();
}

$message = '';
$error = '';
$babyId = $_GET['baby_id'] ?? '';
$vaccines = [];

// Fetch Vaccines
$stmt = $pdo->query("SELECT * FROM vaccines ORDER BY recommended_age_weeks ASC");
$vaccines = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Babies
$babies = $pdo->query("SELECT id, first_name, last_name, birth_date FROM birth_records ORDER BY last_name ASC")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $babyId = $_POST['baby_id'];
    $vaccineId = $_POST['vaccine_id'];
    $doseNumber = $_POST['dose_number'];
    $dateGiven = $_POST['date_given'];
    $nextDueDate = !empty($_POST['next_due_date']) ? $_POST['next_due_date'] : null;
    $remarks = $_POST['remarks'];
    $recordedBy = $_SESSION['user_id'];

    if ($nextDueDate && $dateGiven && strtotime($nextDueDate) <= strtotime($dateGiven)) {
        $error = "Next due date must be after the date given.";
    } elseif (empty($babyId) || empty($vaccineId) || empty($dateGiven)) {
        $error = "Please fill in all required fields.";
    } else {
        $sql = "INSERT INTO immunization_records (baby_id, vaccine_id, dose_number, date_given, next_dose_date, remarks, health_worker_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute([$babyId, $vaccineId, $doseNumber, $dateGiven, $nextDueDate, $remarks, $recordedBy])) {
            $message = "Immunization record added successfully!";
            // Redirect to list after success
            echo "<script>
                setTimeout(function() {
                    window.location.href = '../immunization_records.php';
                }, 1500);
            </script>";
        } else {
            $error = "Failed to add record.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Record Immunization | Kibenes eBirth</title>
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
                            <i class="fas fa-syringe text-white text-xl"></i>
                        </span>
                        New Immunization Record
                    </h1>
                    <p class="text-slate-500 font-medium flex items-center gap-2">
                        <i class="fas fa-baby text-health-400"></i>
                        Tracking vaccination progress for Kibenes infants
                    </p>
                </div>
                <a href="../immunization_records.php" 
                   class="inline-flex items-center gap-2 px-6 py-3 bg-white text-slate-600 rounded-2xl font-bold transition-all hover:bg-slate-50 border border-slate-200 shadow-sm active:scale-95">
                    <i class="fas fa-arrow-left"></i>
                    Back to Records
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

            <form method="POST" id="immunizationForm" class="space-y-8">
                <!-- Patient & Vaccine Selection -->
                <section class="card-premium">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-user-md text-health-600"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-slate-800">Primary Information</h2>
                            <p class="text-xs text-slate-400 font-bold uppercase tracking-widest mt-0.5">Infant and vaccine identification</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div>
                            <label for="baby_id" class="form-label-premium">Infant Profile <span class="text-rose-500">*</span></label>
                            <div class="relative group">
                                <select name="baby_id" id="baby_id" class="form-input-premium appearance-none pr-10" required>
                                    <option value="">Select an infant</option>
                                    <?php foreach ($babies as $baby): ?>
                                        <option value="<?php echo $baby['id']; ?>" <?php echo ($babyId == $baby['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($baby['last_name'] . ', ' . $baby['first_name']); ?>
                                            (Born: <?php echo date('M d, Y', strtotime($baby['birth_date'])); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-slate-400 group-hover:text-health-500 transition-colors">
                                    <i class="fas fa-chevron-down text-xs"></i>
                                </div>
                            </div>
                            <div class="hidden mt-2 p-2 bg-rose-50 text-rose-600 text-xs font-bold rounded-lg border border-rose-100" id="baby_id_warning">
                                Please select an infant profile
                            </div>
                        </div>

                        <div>
                            <label for="vaccine_id" class="form-label-premium">Vaccine Type <span class="text-rose-500">*</span></label>
                            <div class="relative group">
                                <select name="vaccine_id" id="vaccine_id" class="form-input-premium appearance-none pr-10" required>
                                    <option value="">Select vaccine</option>
                                    <?php foreach ($vaccines as $vac): ?>
                                        <option value="<?php echo $vac['id']; ?>">
                                            <?php echo htmlspecialchars($vac['vaccine_name']); ?> 
                                            (Target: <?php echo $vac['recommended_age_weeks']; ?> weeks)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-slate-400 group-hover:text-health-500 transition-colors">
                                    <i class="fas fa-chevron-down text-xs"></i>
                                </div>
                            </div>
                            <div class="hidden mt-2 p-2 bg-rose-50 text-rose-600 text-xs font-bold rounded-lg border border-rose-100" id="vaccine_id_warning">
                                Please select a vaccine type
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Dose Administration Details -->
                <section class="card-premium">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-vial text-health-600"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-slate-800">Administration Details</h2>
                            <p class="text-xs text-slate-400 font-bold uppercase tracking-widest mt-0.5">Dose timing and scheduling</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                        <div>
                            <label for="dose_number" class="form-label-premium">Dose Number <span class="text-rose-500">*</span></label>
                            <input type="number" name="dose_number" id="dose_number" class="form-input-premium font-bold text-center" value="1" min="1" required>
                            <div class="hidden mt-2 p-2 bg-rose-50 text-rose-600 text-xs font-bold rounded-lg border border-rose-100" id="dose_number_warning">
                                Required
                            </div>
                        </div>

                        <div>
                            <label for="date_given" class="form-label-premium">Date Administered <span class="text-rose-500">*</span></label>
                            <input type="date" name="date_given" id="date_given" class="form-input-premium font-black text-health-700" value="<?php echo date('Y-m-d'); ?>" required>
                            <div class="hidden mt-2 p-2 bg-rose-50 text-rose-600 text-xs font-bold rounded-lg border border-rose-100" id="date_given_warning">
                                Required
                            </div>
                        </div>

                        <div>
                            <label for="next_due_date" class="form-label-premium text-emerald-700 group flex items-center gap-2">
                                Next Dose Due 
                                <span class="text-[10px] bg-emerald-100 text-emerald-600 px-2 py-0.5 rounded-full font-black uppercase">Optional</span>
                            </label>
                            <input type="date" name="next_due_date" id="next_due_date" class="form-input-premium border-emerald-100 focus:border-emerald-500 focus:ring-emerald-200">
                            <div class="hidden mt-2 p-2 bg-rose-50 text-rose-600 text-xs font-bold rounded-lg border border-rose-100" id="next_due_date_warning">
                                Must be after administration date
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Additional Notes -->
                <section class="card-premium">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-comment-medical text-health-600"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-slate-800">Additional Remarks</h2>
                            <p class="text-xs text-slate-400 font-bold uppercase tracking-widest mt-0.5">Notes on reaction or observations</p>
                        </div>
                    </div>

                    <div>
                        <textarea name="remarks" id="remarks" class="form-input-premium min-h-[120px] py-4 leading-relaxed" placeholder="Record any reactions, side effects, or special instructions here..."></textarea>
                    </div>
                </section>

                <!-- Form Actions -->
                <div class="flex flex-col sm:flex-row justify-end items-center gap-4 pt-8 pb-12">
                    <button type="button" onclick="window.history.back()" 
                            class="w-full sm:w-auto px-10 py-4 bg-slate-100 text-slate-600 rounded-2xl font-bold uppercase tracking-widest text-xs hover:bg-slate-200 transition-all active:scale-95">
                        Cancel Changes
                    </button>
                    <button type="submit" 
                            class="w-full sm:w-auto px-16 py-4 bg-health-600 text-white rounded-2xl font-black uppercase tracking-widest text-xs hover:bg-health-700 shadow-xl shadow-health-200 transition-all active:scale-95 flex items-center justify-center gap-3">
                        <i class="fas fa-save shadow-sm"></i>
                        Record Immunization
                    </button>
                </div>
            </form>
        </main>
    </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('immunizationForm');
            const dateGivenInput = document.getElementById('date_given');
            const nextDueDateInput = document.getElementById('next_due_date');

            // Real-time validation for required fields
            const requiredFields = ['baby_id', 'vaccine_id', 'dose_number', 'date_given'];
            
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
                const dateGiven = new Date(dateGivenInput.value);
                const nextDate = nextDueDateInput.value ? new Date(nextDueDateInput.value) : null;
                
                if (nextDate && nextDate <= dateGiven) {
                    showWarning('next_due_date');
                    return false;
                } else {
                    hideWarning('next_due_date');
                    return true;
                }
            }

            dateGivenInput.addEventListener('change', validateDates);
            nextDueDateInput.addEventListener('change', validateDates);

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
