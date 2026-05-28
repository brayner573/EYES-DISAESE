<?php
$pageTitle = 'Todas las Predicciones';
require VIEWS_PATH . '/layouts/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800 fw-bold">Predicciones Globales</h1>
</div>

<div class="card shadow-sm border-0 animate-fade-in">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">ID</th>
                        <th>Usuario</th>
                        <th>Imagen</th>
                        <th>Diagnóstico IA</th>
                        <th>Confianza</th>
                        <th>Modelo</th>
                        <th>Fecha</th>
                        <th class="text-end pe-4">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($data['predictions'])): ?>
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">No hay predicciones registradas.</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($data['predictions'] as $p): ?>
                        <?php $cls = DISEASE_CLASSES[$p['predicted_class']] ?? DISEASE_CLASSES['normal']; ?>
                        <tr>
                            <td class="ps-4 text-muted small">#<?= $p['id'] ?></td>
                            <td>
                                <div class="fw-bold"><?= sanitize($p['user_name']) ?></div>
                                <div class="small text-muted"><?= sanitize($p['user_email']) ?></div>
                            </td>
                            <td>
                                <a href="<?= BASE_URL ?>/<?= sanitize($p['image_path']) ?>" target="_blank">
                                    <img src="<?= BASE_URL ?>/<?= sanitize($p['image_path']) ?>" class="rounded shadow-sm" width="40" height="40" style="object-fit:cover">
                                </a>
                            </td>
                            <td>
                                <span class="badge text-white px-2 py-1" style="background-color: <?= $cls['color'] ?>">
                                    <?= $cls['label'] ?>
                                </span>
                            </td>
                            <td class="fw-bold"><?= $p['confidence'] ?>%</td>
                            <td><span class="badge bg-dark"><?= sanitize($p['model_used']) ?></span></td>
                            <td class="text-muted small"><?= date('d/m/Y H:i', strtotime($p['created_at'])) ?></td>
                            <td class="text-end pe-4">
                                <form action="<?= BASE_URL ?>/admin/predictions/delete" method="POST" onsubmit="return confirm('¿Eliminar esta predicción y su imagen?');">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="prediction_id" value="<?= $p['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
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
