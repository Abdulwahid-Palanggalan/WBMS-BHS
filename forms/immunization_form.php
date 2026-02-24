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
    <title>Record Immunization - Kibenes eBirth</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <?php include_once '../includes/header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include_once '../includes/sidebar.php'; ?>

            <main class="main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Record Immunization</h1>
                    <a href="../immunization_records.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to List
                    </a>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success"><?php echo $message; ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <div class="card shadow-sm">
                    <div class="card-body">
                        <form method="POST">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Baby Name <span class="text-danger">*</span></label>
                                    <select name="baby_id" class="form-select" required>
                                        <option value="">Select Baby</option>
                                        <?php foreach ($babies as $baby): ?>
                                            <option value="<?php echo $baby['id']; ?>" <?php echo ($babyId == $baby['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($baby['last_name'] . ', ' . $baby['first_name']); ?>
                                                (DOB: <?php echo $baby['birth_date']; ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Vaccine <span class="text-danger">*</span></label>
                                    <select name="vaccine_id" class="form-select" required>
                                        <option value="">Select Vaccine</option>
                                        <?php foreach ($vaccines as $vac): ?>
                                            <option value="<?php echo $vac['id']; ?>">
                                                <?php echo htmlspecialchars($vac['vaccine_name']); ?> 
                                                (Rec. Age: <?php echo $vac['recommended_age_weeks']; ?> wks)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label class="form-label">Dose Number</label>
                                    <input type="number" name="dose_number" class="form-control" value="1" min="1" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Date Given <span class="text-danger">*</span></label>
                                    <input type="date" name="date_given" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Next Dose Due (Optional)</label>
                                    <input type="date" name="next_due_date" class="form-control">
                                    <small class="text-muted">For subsequent doses</small>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Remarks</label>
                                <textarea name="remarks" class="form-control" rows="3" placeholder="Any reactions or notes..."></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Save Record
                            </button>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.querySelector('form').addEventListener('submit', function(e) {
            const dateGiven = document.querySelector('input[name="date_given"]').value;
            const nextDate = document.querySelector('input[name="next_due_date"]').value;
            
            if (dateGiven && nextDate) {
                if (new Date(nextDate) <= new Date(dateGiven)) {
                    e.preventDefault();
                    alert('Next due date must be after the date given.');
                }
            }
        });
    </script>
</body>
</html>
