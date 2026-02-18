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

// FIXED QUERY - Remove references to deleted columns and use JOIN with pregnancy_details
$prenatalRecords = $pdo->prepare("
    SELECT 
        pr.*,
        m.first_name as mother_first_name,
        m.last_name as mother_last_name,
        pd.lmp,
        pd.edc,
        pd.gravida,
        pd.para
    FROM prenatal_records pr
    JOIN mothers m ON pr.mother_id = m.id
    LEFT JOIN pregnancy_details pd ON pr.mother_id = pd.mother_id
    WHERE pr.mother_id = ? 
    ORDER BY pr.visit_date DESC, pr.visit_number DESC
");
$prenatalRecords->execute([$mother['id']]);
$records = $prenatalRecords->fetchAll(PDO::FETCH_ASSOC);

function displayData($value, $default = 'N/A') {
    return !empty($value) && $value != '0000-00-00' ? htmlspecialchars($value) : $default;
}

function displayDate($date, $format = 'M j, Y') {
    if (empty($date) || $date == '0000-00-00') return 'N/A';
    return date($format, strtotime($date));
}
?>

<div class="modal-header bg-primary text-white py-2">
    <h6 class="modal-title mb-0">
        <i class="fas fa-heartbeat me-2"></i>My Prenatal Records
        <span class="badge bg-light text-primary ms-2"><?php echo count($records); ?> visits</span>
    </h6>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body p-2" style="max-height: 70vh; overflow-y: auto;">
    <?php if (!empty($records)): ?>
        <div class="accordion" id="prenatalAccordion">
            <?php foreach ($records as $index => $record): ?>
            <div class="accordion-item border-0 mb-2">
                <div class="accordion-header">
                    <button class="accordion-button collapsed py-2 px-3" type="button" data-bs-toggle="collapse" 
                            data-bs-target="#prenatal<?php echo $record['id']; ?>" aria-expanded="false">
                        <div class="d-flex w-100 justify-content-between align-items-center">
                            <div class="d-flex align-items-center">
                                <span class="fw-semibold me-2">Visit #<?php echo displayData($record['visit_number']); ?></span>
                                <small class="text-muted"><?php echo displayDate($record['visit_date']); ?></small>
                            </div>
                            <div class="d-flex align-items-center gap-1">
                                <span class="badge bg-primary"><?php echo displayData($record['gestational_age']); ?></span>
                                <?php if ($index === 0): ?>
                                <span class="badge bg-success">Latest</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </button>
                </div>
                <div id="prenatal<?php echo $record['id']; ?>" class="accordion-collapse collapse" 
                     data-bs-parent="#prenatalAccordion">
                    <div class="accordion-body p-3">
                        <!-- ALL FIELDS RETAINED - Compact Layout -->
                        
                        <!-- Vital Signs - Compact Row -->
                        <div class="row g-2 mb-3">
                            <div class="col-4">
                                <small class="text-muted d-block">Blood Pressure</small>
                                <strong><?php echo displayData($record['blood_pressure']); ?></strong>
                            </div>
                            <div class="col-4">
                                <small class="text-muted d-block">Weight</small>
                                <strong><?php echo displayData($record['weight']); ?> kg</strong>
                            </div>
                            <div class="col-4">
                                <small class="text-muted d-block">Temperature</small>
                                <strong><?php echo displayData($record['temperature']); ?> °C</strong>
                            </div>
                        </div>

                        <!-- Laboratory Results - Compact Grid -->
                        <div class="border-top pt-2 mb-3">
                            <h6 class="small fw-semibold text-muted mb-2">Laboratory Results</h6>
                            <div class="row g-2 small">
                                <div class="col-6">
                                    <span class="text-muted">Hemoglobin:</span>
                                    <strong><?php echo displayData($record['hb_level']); ?> g/dL</strong>
                                </div>
                                <div class="col-6">
                                    <span class="text-muted">Blood Group:</span>
                                    <strong><?php echo displayData($record['blood_group']); ?></strong>
                                </div>
                                <div class="col-6">
                                    <span class="text-muted">Rhesus Factor:</span>
                                    <strong><?php echo displayData($record['rhesus_factor']); ?></strong>
                                </div>
                                <div class="col-6">
                                    <span class="text-muted">Blood Sugar:</span>
                                    <strong><?php echo displayData($record['blood_sugar']); ?> mg/dL</strong>
                                </div>
                                <?php if (!empty($record['urinalysis'])): ?>
                                <div class="col-12">
                                    <span class="text-muted">Urinalysis:</span>
                                    <div class="small"><?php echo displayData($record['urinalysis']); ?></div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Medications - Compact Badges -->
                        <div class="border-top pt-2 mb-3">
                            <h6 class="small fw-semibold text-muted mb-2">Medications & Supplements</h6>
                            <div class="d-flex flex-wrap gap-1">
                                <?php if ($record['iron_supplement']): ?>
                                <span class="badge bg-warning text-dark">Iron Supplement</span>
                                <?php endif; ?>
                                <?php if ($record['folic_acid']): ?>
                                <span class="badge bg-warning text-dark">Folic Acid</span>
                                <?php endif; ?>
                                <?php if ($record['calcium']): ?>
                                <span class="badge bg-warning text-dark">Calcium</span>
                                <?php endif; ?>
                                <?php if (!empty($record['other_meds'])): ?>
                                <span class="badge bg-info">Other: <?php echo $record['other_meds']; ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Disease Screening - Compact Grid -->
                        <div class="border-top pt-2 mb-3">
                            <h6 class="small fw-semibold text-muted mb-2">Infectious Disease Screening</h6>
                            <div class="row g-2 small">
                                <div class="col-6">
                                    <span class="text-muted">HIV Status:</span>
                                    <strong class="text-<?php echo $record['hiv_status'] == 'Negative' ? 'success' : 'danger'; ?>">
                                        <?php echo displayData($record['hiv_status']); ?>
                                    </strong>
                                </div>
                                <div class="col-6">
                                    <span class="text-muted">Hepatitis B:</span>
                                    <strong class="text-<?php echo $record['hepatitis_b'] == 'Negative' ? 'success' : 'danger'; ?>">
                                        <?php echo displayData($record['hepatitis_b']); ?>
                                    </strong>
                                </div>
                                <div class="col-6">
                                    <span class="text-muted">VDRL/RPR:</span>
                                    <strong class="text-<?php echo $record['vdrl'] == 'Non-reactive' ? 'success' : 'danger'; ?>">
                                        <?php echo displayData($record['vdrl']); ?>
                                    </strong>
                                </div>
                                <?php if (!empty($record['other_tests'])): ?>
                                <div class="col-12">
                                    <span class="text-muted">Other Tests:</span>
                                    <div class="small"><?php echo displayData($record['other_tests']); ?></div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Medical Assessment - Compact Layout -->
                        <div class="border-top pt-2 mb-3">
                            <h6 class="small fw-semibold text-muted mb-2">Medical Assessment</h6>
                            <div class="small">
                                <?php if (!empty($record['complaints'])): ?>
                                <div class="mb-1">
                                    <span class="text-muted">Complaints:</span>
                                    <div><?php echo displayData($record['complaints']); ?></div>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($record['findings'])): ?>
                                <div class="mb-1">
                                    <span class="text-muted">Findings:</span>
                                    <div><?php echo displayData($record['findings']); ?></div>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($record['diagnosis'])): ?>
                                <div class="mb-1">
                                    <span class="text-muted">Diagnosis:</span>
                                    <strong><?php echo displayData($record['diagnosis']); ?></strong>
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
            <i class="fas fa-heartbeat fa-2x text-muted mb-3"></i>
            <p class="text-muted mb-0">No prenatal visits recorded yet.</p>
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
    background-color: #e7f1ff;
    color: #0d6efd;
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
function printPrenatalRecords() {
    const modalContent = document.querySelector('#prenatalModal .modal-content').cloneNode(true);
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
            <title>My Prenatal Records</title>
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
            <h5 class="text-center mb-3">My Prenatal Records</h5>
            <p class="text-center text-muted small mb-4">Generated on: ${new Date().toLocaleDateString()}</p>
            ${modalContent.innerHTML}
            <div class="no-print text-center mt-4">
                <button onclick="window.print()" class="btn btn-sm btn-primary me-2">Print</button>
                <button onclick="window.close()" class="btn btn-sm btn-secondary">Close</button>
            </div>
        </body>
        </html>
    `);
    
    printWindow.document.close();
}
</script>