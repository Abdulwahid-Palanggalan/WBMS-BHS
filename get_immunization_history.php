<?php
// get_immunization_history.php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

if (!isAuthorized(['admin', 'midwife'])) {
    echo "Unauthorized access";
    exit();
}

if (!isset($_GET['baby_id'])) {
    echo "No baby ID provided";
    exit();
}

$babyId = intval($_GET['baby_id']);

// Get Baby Details
$stmt = $pdo->prepare("SELECT first_name, last_name, birth_date FROM birth_records WHERE id = ?");
$stmt->execute([$babyId]);
$baby = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$baby) {
    echo "Baby not found";
    exit();
}

// Get Immunization Records
$stmt = $pdo->prepare("
    SELECT ir.*, v.vaccine_name, u.first_name as worker_first, u.last_name as worker_last
    FROM immunization_records ir
    JOIN vaccines v ON ir.vaccine_id = v.id
    LEFT JOIN users u ON ir.health_worker_id = u.id
    WHERE ir.baby_id = ?
    ORDER BY ir.date_given DESC
");
$stmt->execute([$babyId]);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate Age
$dob = new DateTime($baby['birth_date']);
$now = new DateTime();
$age = $now->diff($dob);
$ageString = $age->y . "y " . $age->m . "m " . $age->d . "d";
?>

<div class="text-start mb-4">
    <h5><?php echo htmlspecialchars($baby['first_name'] . ' ' . $baby['last_name']); ?></h5>
    <p class="text-muted mb-0">DOB: <?php echo date('M j, Y', strtotime($baby['birth_date'])); ?> (<?php echo $ageString; ?>)</p>
</div>

<div class="table-responsive">
    <table class="table table-bordered table-striped">
        <thead>
            <tr>
                <th>Date Given</th>
                <th>Vaccine</th>
                <th>Dose #</th>
                <th>Next Due</th>
                <th>Administered By</th>
                <th>Remarks</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($records)): ?>
                <tr>
                    <td colspan="6" class="text-center text-muted">No immunization records found.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($records as $record): ?>
                <tr>
                    <td><?php echo date('M j, Y', strtotime($record['date_given'])); ?></td>
                    <td class="fw-bold text-primary"><?php echo htmlspecialchars($record['vaccine_name']); ?></td>
                    <td><?php echo $record['dose_number']; ?></td>
                    <td>
                        <?php 
                        if ($record['next_dose_date']) {
                            echo date('M j, Y', strtotime($record['next_dose_date']));
                        } else {
                            echo '<span class="text-muted">--</span>';
                        }
                        ?>
                    </td>
                    <td><?php echo htmlspecialchars($record['worker_first'] . ' ' . $record['worker_last']); ?></td>
                    <td><?php echo htmlspecialchars($record['remarks'] ?? ''); ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
