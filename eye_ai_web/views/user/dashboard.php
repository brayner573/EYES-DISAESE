<?php
$pageTitle = 'Dashboard Usuario';
require VIEWS_PATH . '/layouts/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800 fw-bold">Bienvenido, <?= sanitize($data['user']['name']) ?></h1>
    <a href="<?= BASE_URL ?>/prediction/form" class="btn btn-primary shadow-sm">
        <i class="bi bi-upload me-1"></i> Analizar Imagen
    </a>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-4 col-md-6">
        <div class="card h-100 border-0 border-start border-4 border-primary shadow-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col me-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Análisis</div>
                        <div class="h3 mb-0 font-weight-bold text-gray-800"><?= $data['total_predictions'] ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-activity fs-1 text-gray-300 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 animate-fade-in">
    <div class="card-header bg-white py-3 d-flex flex-row align-items-center justify-content-between border-bottom-0">
        <h6 class="m-0 font-weight-bold text-primary">Análisis Recientes</h6>
        <a href="<?= BASE_URL ?>/user/history" class="btn btn-sm btn-outline-primary">Ver todo</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Imagen</th>
                        <th>Resultado IA</th>
                        <th>Confianza</th>
                        <th>Modelo</th>
                        <th>Fecha</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($data['recent'])): ?>
                    <tr>
                        <td colspan="5" class="text-center py-4 text-muted">
                            <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                            Aún no has realizado ningún análisis.
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($data['recent'] as $p): ?>
                        <?php $cls = DISEASE_CLASSES[$p['predicted_class']] ?? DISEASE_CLASSES['normal']; ?>
                        <tr>
                            <td class="ps-4">
                                <img src="<?= BASE_URL ?>/<?= sanitize($p['image_path']) ?>" alt="Ojo" class="rounded" width="40" height="40" style="object-fit:cover">
                            </td>
                            <td>
                                <span class="badge text-white" style="background-color: <?= $cls['color'] ?>">
                                    <i class="bi <?= $cls['icon'] ?> me-1"></i> <?= $cls['label'] ?>
                                </span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <span class="me-2 fw-medium"><?= $p['confidence'] ?>%</span>
                                    <div class="progress flex-grow-1" style="height: 6px;">
                                        <div class="progress-bar" style="width: <?= $p['confidence'] ?>%; background-color: <?= $cls['color'] ?>"></div>
                                    </div>
                                </div>
                            </td>
                            <td><span class="badge bg-light text-dark border"><?= sanitize($p['model_used']) ?></span></td>
                            <td class="text-muted small"><?= date('d M, Y H:i', strtotime($p['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require VIEWS_PATH . '/layouts/footer.php'; ?>
