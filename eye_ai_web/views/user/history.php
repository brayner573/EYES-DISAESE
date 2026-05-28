<?php
$pageTitle = 'Historial Clínico IA';
require VIEWS_PATH . '/layouts/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 fw-bold text-dark">
        <i class="bi bi-clock-history text-primary me-2"></i>Historial de Evaluaciones
    </h1>
    <a href="<?= BASE_URL ?>/prediction/form" class="btn btn-primary shadow-sm fw-medium">
        <i class="bi bi-plus-circle me-1"></i> Nueva Evaluación
    </a>
</div>

<div class="card shadow-sm border-0 animate-fade-in rounded-4 overflow-hidden">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 border-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4 py-3 text-uppercase small fw-bold text-muted letter-spacing-1 border-0">Fecha / Hora</th>
                        <th class="py-3 text-uppercase small fw-bold text-muted letter-spacing-1 border-0">Imagen</th>
                        <th class="py-3 text-uppercase small fw-bold text-muted letter-spacing-1 border-0">Diagnóstico IA</th>
                        <th class="py-3 text-uppercase small fw-bold text-muted letter-spacing-1 border-0">Confianza</th>
                        <th class="py-3 text-uppercase small fw-bold text-muted letter-spacing-1 border-0">Riesgo</th>
                        <th class="pe-4 py-3 text-uppercase small fw-bold text-muted letter-spacing-1 border-0 text-end">Acción</th>
                    </tr>
                </thead>
                <tbody class="border-top-0">
                    <?php if (empty($data['predictions'])): ?>
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="bi bi-folder-x display-4 d-block mb-3 text-gray-300"></i>
                            <h5 class="fw-medium text-gray-500">No hay evaluaciones en su historial</h5>
                            <p class="small">Realice un nuevo análisis para comenzar a registrar su historial clínico.</p>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($data['predictions'] as $p): ?>
                        <?php 
                        $cls = DISEASE_CLASSES[$p['predicted_class']] ?? DISEASE_CLASSES['normal']; 
                        $isHighRisk = in_array($cls['risk_level'], ['ALTO RIESGO', 'URGENCIA MÉDICA']);
                        ?>
                        <tr>
                            <td class="ps-4 text-muted small fw-medium">
                                <i class="bi bi-calendar3 me-1"></i> <?= date('d/m/Y', strtotime($p['created_at'])) ?><br>
                                <i class="bi bi-clock me-1"></i> <?= date('H:i', strtotime($p['created_at'])) ?>
                            </td>
                            <td>
                                <div class="position-relative d-inline-block">
                                    <img src="<?= BASE_URL ?>/<?= sanitize($p['image_path']) ?>" alt="Fondo de Ojo" class="rounded border shadow-sm" width="55" height="55" style="object-fit:cover;">
                                    <?php if(strpos($p['model_used'], 'COMPARE') !== false): ?>
                                    <span class="position-absolute top-0 start-100 translate-middle p-1 bg-info border border-light rounded-circle" title="Modo Comparativo">
                                        <span class="visually-hidden">Comparativo</span>
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge text-white px-3 py-2 rounded-pill shadow-sm" style="background-color: <?= $cls['color'] ?>;">
                                    <i class="bi <?= $cls['icon'] ?> me-1"></i> <?= $cls['label'] ?>
                                </span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="fw-bold <?= $p['confidence'] > 85 ? 'text-success' : 'text-warning' ?> fs-6 me-2">
                                        <?= $p['confidence'] ?>%
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if ($isHighRisk): ?>
                                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger rounded-pill px-3 py-1">
                                        <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= $cls['risk_level'] ?>
                                    </span>
                                <?php elseif ($cls['risk_level'] === 'NORMAL'): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success rounded-pill px-3 py-1">
                                        <i class="bi bi-shield-check me-1"></i> NORMAL
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary rounded-pill px-3 py-1">
                                        <i class="bi bi-info-circle-fill me-1"></i> <?= $cls['risk_level'] ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="pe-4 text-end">
                                <a href="<?= BASE_URL ?>/prediction/view/<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary fw-medium rounded-pill px-3">
                                    <i class="bi bi-file-medical me-1"></i> Ver Reporte
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require VIEWS_PATH . '/layouts/footer.php'; ?>
