<?php
/**
 * library.php - Digital Health Library
 * Searchable repository for health resources
 */
require_once 'includes/auth.php';
require_once 'includes/functions.php';

redirectIfNotLoggedIn();

$resources = [
    [
        'title' => 'Breastfeeding Basics (Tagalog)',
        'category' => 'Nutrition',
        'type' => 'PDF',
        'icon' => 'fa-file-pdf',
        'color' => 'danger',
        'description' => 'A guide to exclusive breastfeeding during the first 6 months.'
    ],
    [
        'title' => 'Vaccination Schedule 2024',
        'category' => 'Immunization',
        'type' => 'Image',
        'icon' => 'fa-image',
        'color' => 'primary',
        'description' => 'Official DOH recommended vaccination schedule for infants.'
    ],
    [
        'title' => 'Prenatal Care Tips',
        'category' => 'Maternal Health',
        'type' => 'Video',
        'icon' => 'fa-video',
        'color' => 'success',
        'description' => 'Essential habits to keep you and your baby healthy during pregnancy.'
    ],
    [
        'title' => 'Family Planning Methods',
        'category' => 'Family Planning',
        'type' => 'Article',
        'icon' => 'fa-newspaper',
        'color' => 'warning',
        'description' => 'Detailed overview of available birth control options at the BHS.'
    ],
    [
        'title' => 'Postnatal Recovery Guide',
        'category' => 'Maternal Health',
        'type' => 'PDF',
        'icon' => 'fa-file-pdf',
        'color' => 'danger',
        'description' => 'How to recover and care for yourself after delivery.'
    ],
    [
        'title' => 'Infant Weight Tracking',
        'category' => 'Baby Care',
        'type' => 'Video',
        'icon' => 'fa-video',
        'color' => 'success',
        'description' => 'Understanding your baby\'s growth chart and nutrition needs.'
    ]
];

// Simple Search Filter
$search = $_GET['search'] ?? '';
if ($search) {
    $resources = array_filter($resources, function($r) use ($search) {
        return stripos($r['title'], $search) !== false || stripos($r['category'], $search) !== false;
    });
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Health Library - WBMS-BHS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include_once 'includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include_once 'includes/sidebar.php'; ?>
            
            <main class="main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="fw-bold mb-1">Digital Health Library</h2>
                        <p class="text-muted small mb-0">Learn and share validated health resources</p>
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <form method="GET" class="row g-3">
                            <div class="col-md-10">
                                <div class="input-group">
                                    <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                                    <input type="text" name="search" class="form-control border-start-0" placeholder="Search resources (e.g., Nutrition, Vaccines...)" value="<?= htmlspecialchars($search) ?>">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">Search</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="row g-4">
                    <?php if (!empty($resources)): ?>
                        <?php foreach ($resources as $res): ?>
                        <div class="col-xl-4 col-md-6">
                            <div class="card h-100 border-0 shadow-sm hover-shadow transition-all bg-white rounded-xl overflow-hidden">
                                <div class="p-4">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div class="bg-<?= $res['color'] ?>-light text-<?= $res['color'] ?> rounded-pill p-3" style="background: var(--primary-light); color: var(--primary);">
                                            <i class="fas <?= $res['icon'] ?> fa-lg"></i>
                                        </div>
                                        <span class="badge bg-light text-dark rounded-pill"><?= $res['type'] ?></span>
                                    </div>
                                    <h5 class="fw-bold mb-2"><?= $res['title'] ?></h5>
                                    <p class="text-muted small mb-4"><?= $res['description'] ?></p>
                                    <div class="d-flex justify-content-between align-items-center mt-auto">
                                        <span class="badge bg-primary-light text-primary"><?= $res['category'] ?></span>
                                        <a href="#" class="btn btn-sm btn-outline-primary px-3">View Resource</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12 text-center py-5">
                            <i class="fas fa-book-open fa-3x text-light mb-3"></i>
                            <h5 class="text-muted">No resources found for "<?= htmlspecialchars($search) ?>"</h5>
                            <a href="library.php" class="btn btn-link">Clear search</a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Suggested for You -->
                <div class="mt-5 mb-4">
                    <h5 class="fw-bold mb-3">Suggested for You</h5>
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm text-white overflow-hidden" style="background: linear-gradient(135deg, var(--secondary) 0%, var(--primary) 100%);">
                                <div class="card-body p-4">
                                    <h4 class="fw-bold mb-2">Safe Pregnancy Prep</h4>
                                    <p class="opacity-75 mb-4 small">Discover the 10 most important things to do before your third trimester.</p>
                                    <button class="btn btn-light btn-sm text-primary fw-bold">Watch Video</button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm text-white overflow-hidden" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                                <div class="card-body p-4">
                                    <h4 class="fw-bold mb-2">Healthy Baby Nutrition</h4>
                                    <p class="opacity-75 mb-4 small">Download our complementary feeding guide for babies aged 6-12 months.</p>
                                    <button class="btn btn-light btn-sm text-success fw-bold">Download PDF</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
