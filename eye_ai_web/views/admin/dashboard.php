<?php
$pageTitle = 'Dashboard Admin';
require VIEWS_PATH . '/layouts/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800 fw-bold">Panel de Administración</h1>
</div>

<div class="row g-4 mb-4 animate-fade-in">
    <div class="col-xl-3 col-md-6">
        <div class="card h-100 border-0 shadow-sm bg-primary text-white">
            <div class="card-body stat-card">
                <div class="stat-icon bg-white text-primary">
                    <i class="bi bi-people-fill"></i>
                </div>
                <div>
                    <h6 class="text-uppercase fw-bold mb-1 opacity-75">Usuarios Totales</h6>
                    <h2 class="mb-0 fw-bold"><?= $data['total_users'] ?></h2>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card h-100 border-0 shadow-sm bg-success text-white">
            <div class="card-body stat-card">
                <div class="stat-icon bg-white text-success">
                    <i class="bi bi-clipboard2-data-fill"></i>
                </div>
                <div>
                    <h6 class="text-uppercase fw-bold mb-1 opacity-75">Predicciones Totales</h6>
                    <h2 class="mb-0 fw-bold"><?= $data['total_predictions'] ?></h2>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card h-100 border-0 shadow-sm bg-info text-white">
            <div class="card-body stat-card">
                <div class="stat-icon bg-white text-info">
                    <i class="bi bi-calendar-check-fill"></i>
                </div>
                <div>
                    <h6 class="text-uppercase fw-bold mb-1 opacity-75">Predicciones Hoy</h6>
                    <h2 class="mb-0 fw-bold"><?= $data['today_predictions'] ?></h2>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 animate-fade-in">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 fw-bold text-primary">Últimas Predicciones Globales</h6>
                <a href="<?= BASE_URL ?>/admin/predictions" class="btn btn-sm btn-outline-primary">Ver todas</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Usuario</th>
                                <th>Diagnóstico</th>
                                <th>Confianza</th>
                                <th>Fecha</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($data['recent'] as $p): ?>
                            <?php $cls = DISEASE_CLASSES[$p['predicted_class']] ?? DISEASE_CLASSES['normal']; ?>
                            <tr>
                                <td class="ps-4 fw-medium"><?= sanitize($p['user_name']) ?></td>
                                <td>
                                    <span class="badge text-white" style="background-color: <?= $cls['color'] ?>">
                                        <?= $cls['label'] ?>
                                    </span>
                                </td>
                                <td class="fw-bold"><?= $p['confidence'] ?>%</td>
                                <td class="text-muted small"><?= date('d M, H:i', strtotime($p['created_at'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 fw-bold text-primary">Estadísticas por Enfermedad</h6>
            </div>
            <div class="card-body">
                <?php foreach ($data['stats_class'] as $stat): ?>
                <?php 
                $cls = DISEASE_CLASSES[$stat['predicted_class']] ?? DISEASE_CLASSES['normal']; 
                $percent = $data['total_predictions'] > 0 ? round(($stat['total'] / $data['total_predictions']) * 100) : 0;
                ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="fw-medium small"><?= $cls['label'] ?></span>
                        <span class="fw-bold small"><?= $stat['total'] ?> (<?= $percent ?>%)</span>
                    </div>
                    <div class="progress" style="height: 6px;">
                        <div class="progress-bar" style="width: <?= $percent ?>%; background-color: <?= $cls['color'] ?>"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php require VIEWS_PATH . '/layouts/footer.php'; ?>
