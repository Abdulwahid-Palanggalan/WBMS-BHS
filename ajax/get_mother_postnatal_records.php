<?php
require_once dirname(__FILE__) . '/../config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isAuthorized(['mother'])) {
    header('HTTP/1.1 403 Forbidden');
    echo '<div class="alert alert-danger">Unauthorized access</div>';
    exit();
}

global $pdo;

$userId = $_SESSION['user_id'];

// Get mother's ID
$motherStmt = $pdo->prepare("SELECT id FROM mothers WHERE user_id = ?");
$motherStmt->execute([$userId]);
$mother = $motherStmt->fetch(PDO::FETCH_ASSOC);

if (!$mother) {
    echo '<div class="alert alert-danger">Mother profile not found</div>';
    exit();
}

// Get comprehensive postnatal records - ALL FIELDS RETAINED
$postnatalRecords = $pdo->prepare("
    SELECT 
        pn.*,
        b.first_name as baby_first_name,
        b.last_name as baby_last_name,
        b.birth_date as baby_birth_date,
        b.gender as baby_gender
    FROM postnatal_records pn
    JOIN birth_records b ON pn.baby_id = b.id
    WHERE pn.mother_id = ?
    ORDER BY pn.visit_date DESC, pn.visit_number DESC
");
$postnatalRecords->execute([$mother['id']]);
$records = $postnatalRecords->fetchAll(PDO::FETCH_ASSOC);

function displayData($value, $default = 'N/A') {
    return !empty($value) && $value != '0000-00-00' ? htmlspecialchars($value) : $default;
}

function displayDate($date, $format = 'M j, Y') {
    if (empty($date) || $date == '0000-00-00') return 'N/A';
    return date($format, strtotime($date));
}

function calculateBabyAge($birthDate) {
    if (empty($birthDate) || $birthDate == '0000-00-00') return 'N/A';
    
    $birth = new DateTime($birthDate);
    $now = new DateTime();
    $interval = $birth->diff($now);
    
    if ($interval->y > 0) {
        return $interval->y . ' year' . ($interval->y > 1 ? 's' : '');
    } elseif ($interval->m > 0) {
        return $interval->m . ' month' . ($interval->m > 1 ? 's' : '');
    } else {
        return $interval->d . ' day' . ($interval->d > 1 ? 's' : '');
    }
}
?>

<div class="modal-header bg-success text-white py-2">
    <h6 class="modal-title mb-0">
        <i class="fas fa-baby-carriage me-2"></i>My Postnatal Records
        <span class="badge bg-light text-success ms-2"><?php echo count($records); ?> visits</span>
    </h6>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body p-2" style="max-height: 70vh; overflow-y: auto;">
    <?php if (!empty($records)): ?>
        <div class="accordion" id="postnatalAccordion">
            <?php foreach ($records as $index => $record): ?>
            <div class="accordion-item border-0 mb-2">
                <div class="accordion-header">
                    <button class="accordion-button collapsed py-2 px-3" type="button" data-bs-toggle="collapse" 
                            data-bs-target="#postnatal<?php echo $record['id']; ?>" aria-expanded="false">
                        <div class="d-flex w-100 justify-content-between align-items-center">
                            <div class="d-flex align-items-center">
                                <span class="fw-semibold me-2">Visit #<?php echo displayData($record['visit_number']); ?></span>
                                <small class="text-muted"><?php echo displayDate($record['visit_date']); ?></small>
                            </div>
                            <div class="d-flex align-items-center gap-1">
                                <span class="badge bg-info"><?php echo htmlspecialchars($record['baby_first_name'] . ' ' . substr($record['baby_last_name'], 0, 1)); ?>.</span>
                                <?php if ($index === 0): ?>
                                <span class="badge bg-success">Latest</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </button>
                </div>
                <div id="postnatal<?php echo $record['id']; ?>" class="accordion-collapse collapse" 
                     data-bs-parent="#postnatalAccordion">
                    <div class="accordion-body p-3">
                        <!-- ALL FIELDS RETAINED - Compact Layout -->
                        
                        <!-- Baby Information - Compact Row -->
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <small class="text-muted d-block">Baby Name</small>
                                <strong><?php echo htmlspecialchars($record['baby_first_name'] . ' ' . $record['baby_last_name']); ?></strong>
                            </div>
                            <div class="col-3">
                                <small class="text-muted d-block">Gender</small>
                                <strong><?php echo displayData($record['baby_gender']); ?></strong>
                            </div>
                            <div class="col-3">
                                <small class="text-muted d-block">Age</small>
                                <strong><?php echo calculateBabyAge($record['baby_birth_date']); ?></strong>
                            </div>
                        </div>

                        <!-- Mother's Vital Signs - Compact Grid -->
                        <div class="border-top pt-2 mb-3">
                            <h6 class="small fw-semibold text-muted mb-2">Mother's Health</h6>
                            <div class="row g-2 small">
                                <div class="col-4">
                                    <span class="text-muted">BP:</span>
                                    <strong><?php echo displayData($record['blood_pressure']); ?></strong>
                                </div>
                                <div class="col-4">
                                    <span class="text-muted">Weight:</span>
                                    <strong><?php echo displayData($record['weight']); ?> kg</strong>
                                </div>
                                <div class="col-4">
                                    <span class="text-muted">Temp:</span>
                                    <strong><?php echo displayData($record['temperature']); ?> °C</strong>
                                </div>
                            </div>
                        </div>

                        <!-- Postpartum Assessment - Compact Grid -->
                        <div class="border-top pt-2 mb-3">
                            <h6 class="small fw-semibold text-muted mb-2">Postpartum Assessment</h6>
                            <div class="row g-2 small">
                                <?php if (!empty($record['uterus_status'])): ?>
                                <div class="col-6">
                                    <span class="text-muted">Uterus:</span>
                                    <strong><?php echo displayData($record['uterus_status']); ?></strong>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($record['lochia_status'])): ?>
                                <div class="col-6">
                                    <span class="text-muted">Lochia:</span>
                                    <strong><?php echo displayData($record['lochia_status']); ?></strong>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($record['perineum_status'])): ?>
                                <div class="col-6">
                                    <span class="text-muted">Perineum:</span>
                                    <strong><?php echo displayData($record['perineum_status']); ?></strong>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($record['breasts_status'])): ?>
                                <div class="col-6">
                                    <span class="text-muted">Breasts:</span>
                                    <strong><?php echo displayData($record['breasts_status']); ?></strong>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($record['emotional_state'])): ?>
                                <div class="col-12">
                                    <span class="text-muted">Emotional State:</span>
                                    <strong><?php echo displayData($record['emotional_state']); ?></strong>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Mother's Complaints & Treatment -->
                        <?php if (!empty($record['complaints']) || !empty($record['treatment'])): ?>
                        <div class="border-top pt-2 mb-3">
                            <h6 class="small fw-semibold text-muted mb-2">Mother's Medical Notes</h6>
                            <div class="small">
                                <?php if (!empty($record['complaints'])): ?>
                                <div class="mb-1">
                                    <span class="text-muted">Complaints:</span>
                                    <div><?php echo displayData($record['complaints']); ?></div>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($record['treatment'])): ?>
                                <div class="mb-1">
                                    <span class="text-muted">Treatment:</span>
                                    <strong><?php echo displayData($record['treatment']); ?></strong>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Baby's Health - Compact Grid -->
                        <div class="border-top pt-2 mb-3">
                            <h6 class="small fw-semibold text-muted mb-2">Baby's Health</h6>
                            <div class="row g-2 small">
                                <?php if (!empty($record['baby_weight'])): ?>
                                <div class="col-6">
                                    <span class="text-muted">Weight:</span>
                                    <strong><?php echo displayData($record['baby_weight']); ?> kg</strong>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($record['feeding_method'])): ?>
                                <div class="col-6">
                                    <span class="text-muted">Feeding:</span>
                                    <strong><?php echo displayData($record['feeding_method']); ?></strong>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($record['baby_issues'])): ?>
                                <div class="col-12">
                                    <span class="text-muted">Health Issues:</span>
                                    <div><?php echo displayData($record['baby_issues']); ?></div>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($record['baby_treatment'])): ?>
                                <div class="col-12">
                                    <span class="text-muted">Baby Treatment:</span>
                                    <strong><?php echo displayData($record['baby_treatment']); ?></strong>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Counseling & Follow-up -->
                        <div class="border-top pt-2 mb-3">
                            <h6 class="small fw-semibold text-muted mb-2">Counseling & Follow-up</h6>
                            <div class="small">
                                <?php if (!empty($record['counseling_topics'])): ?>
                                <div class="mb-1">
                                    <span class="text-muted">Topics Discussed:</span>
                                    <div><?php echo displayData($record['counseling_topics']); ?></div>
                                </div>
                                <?php endif; ?>
                                <?php if ($record['referral_needed']): ?>
                                <div class="mb-1">
                                    <span class="text-muted">Referral:</span>
                                    <strong class="text-danger">Yes</strong>
                                    <?php if (!empty($record['referral_details'])): ?>
                                    <div class="mt-1"><?php echo displayData($record['referral_details']); ?></div>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Next Visit -->
                        <?php if (!empty($record['next_visit_date'])): ?>
                        <div class="border-top pt-2">
                            <h6 class="small fw-semibold text-success mb-1">
                                <i class="fas fa-calendar-check me-1"></i>
                                Next Appointment
                            </h6>
                            <div class="small">
                                <strong><?php echo displayDate($record['next_visit_date']); ?></strong>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="text-center py-4">
            <i class="fas fa-baby-carriage fa-2x text-muted mb-3"></i>
            <p class="text-muted mb-0">No postnatal visits recorded yet.</p>
        </div>
    <?php endif; ?>
</div>
<div class="modal-footer py-2 px-3">
    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
    <?php if (!empty($records)): ?>
    <?php endif; ?>
</div>

<style>
.accordion-button {
    font-size: 0.9rem;
    padding: 0.5rem 1rem;
    background-color: #f8f9fa;
}

.accordion-button:not(.collapsed) {
    background-color: #e8f5e8;
    color: #198754;
}

.accordion-body {
    font-size: 0.85rem;
    background-color: #f8f9fa;
    border-radius: 0 0 0.375rem 0.375rem;
}

.badge {
    font-size: 0.75rem;
}

.modal-body {
    font-size: 0.9rem;
}

.small {
    font-size: 0.8rem;
}
</style>

<script>
function printPostnatalRecords() {
    const modalContent = document.querySelector('#postnatalModal .modal-content').cloneNode(true);
    const printWindow = window.open('', '_blank');
    
    // Remove buttons and make accordion expanded
    const footer = modalContent.querySelector('.modal-footer');
    if (footer) footer.remove();
    
    // Expand all accordion items for printing
    const accordionItems = modalContent.querySelectorAll('.accordion-collapse');
    accordionItems.forEach(item => {
        item.classList.add('show');
    });
    
    // Remove accordion toggle functionality for print
    const accordionButtons = modalContent.querySelectorAll('.accordion-button');
    accordionButtons.forEach(button => {
        button.classList.remove('collapsed');
        button.setAttribute('aria-expanded', 'true');
    });
    
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>My Postnatal Records</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>
                body { padding: 15px; font-size: 12px; }
                .accordion-item { margin-bottom: 10px; border: 1px solid #dee2e6 !important; }
                .accordion-button { display: none !important; }
                .accordion-collapse { display: block !important; }
                .badge { font-size: 0.7rem; }
                @media print {
                    .no-print { display: none; }
                    body { font-size: 11px; }
                }
            </style>
        </head>
        <body>
            <h5 class="text-center mb-3">My Postnatal Records</h5>
            <p class="text-center text-muted small mb-4">Generated on: ${new Date().toLocaleDateString()}</p>
            ${modalContent.innerHTML}
            <div class="no-print text-center mt-4">
                <button onclick="window.print()" class="btn btn-sm btn-success me-2">Print</button>
                <button onclick="window.close()" class="btn btn-sm btn-secondary">Close</button>
            </div>
        </body>
        </html>
    `);
    
    printWindow.document.close();
}
</script>