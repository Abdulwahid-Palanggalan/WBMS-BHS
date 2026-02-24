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
    <title>Family Planning Registration - Kibenes eBirth</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <?php include_once '../includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include_once '../includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Family Planning Registration</h1>
                    <a href="../family_planning.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i>Back</a>
                </div>

                <?php if ($message): ?> <div class="alert alert-success"><?php echo $message; ?></div> <?php endif; ?>
                <?php if ($error): ?> <div class="alert alert-danger"><?php echo $error; ?></div> <?php endif; ?>

                <div class="card shadow-sm">
                    <div class="card-body">
                        <form method="POST">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Client (Mother)</label>
                                    <select name="mother_id" class="form-select" required>
                                        <option value="">Select Mother</option>
                                        <?php foreach ($mothers as $m): ?>
                                            <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['last_name'] . ', ' . $m['first_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Method Used</label>
                                    <select name="method_id" class="form-select" required>
                                        <option value="">Select Method</option>
                                        <?php foreach ($methods as $method): ?>
                                            <option value="<?php echo $method['id']; ?>"><?php echo htmlspecialchars($method['method_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Registration Date</label>
                                    <input type="date" name="registration_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Next Service Date (Optional)</label>
                                    <input type="date" name="next_service_date" class="form-control">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Remarks</label>
                                <textarea name="remarks" class="form-control" rows="3"></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Save Record</button>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.querySelector('form').addEventListener('submit', function(e) {
            const regDate = document.querySelector('input[name="registration_date"]').value;
            const nextDate = document.querySelector('input[name="next_service_date"]').value;
            
            if (regDate && nextDate) {
                if (new Date(nextDate) <= new Date(regDate)) {
                    e.preventDefault();
                    alert('Next service date must be after the registration date.');
                }
            }
        });
    </script>
</body>
</html>
