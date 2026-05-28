<?php
$pageTitle = 'Reporte Clínico IA';
require VIEWS_PATH . '/layouts/header.php';

$pred = $data['prediction'];
$result = $data['result'];
$cls = DISEASE_CLASSES[$result['class']] ?? DISEASE_CLASSES['normal'];
$isDanger = in_array($cls['risk_level'], ['ALTO RIESGO', 'URGENCIA MÉDICA']);
?>

<style>
.glass-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.07);
}
.clinical-timeline {
    border-left: 2px solid #e2e8f0;
    margin-left: 1rem;
    padding-left: 1.5rem;
    position: relative;
}
.clinical-item {
    position: relative;
    margin-bottom: 1.5rem;
}
.clinical-item::before {
    content: '';
    position: absolute;
    left: -1.85rem;
    top: 0.2rem;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: var(--bs-primary);
    border: 2px solid white;
    box-shadow: 0 0 0 2px var(--bs-primary);
}
.pulse-alert { animation: pulse-red 2s infinite; }
@keyframes pulse-red {
    0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
    70% { box-shadow: 0 0 0 10px rgba(239, 68, 68, 0); }
    100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
}
@media print {
    body { background: white !important; }
    .no-print { display: none !important; }
    .glass-card { box-shadow: none !important; border: 1px solid #ddd !important; }
    .print-only { display: block !important; }
}
.print-only { display: none; }
</style>

<!-- Cabecera -->
<div class="d-flex justify-content-between align-items-center mb-4 no-print">
    <h1 class="h3 mb-0 fw-bold text-dark">
        <i class="bi bi-file-medical-fill me-2 text-primary"></i>Reporte Clínico
    </h1>
    <div class="d-flex gap-2">
        <button onclick="window.print()" class="btn btn-outline-dark fw-medium">
            <i class="bi bi-printer me-1"></i> Imprimir PDF
        </button>
        <a href="<?= BASE_URL ?>/prediction/form" class="btn btn-primary fw-medium">
            <i class="bi bi-plus-circle me-1"></i> Nuevo Análisis
        </a>
    </div>
</div>

<div class="print-only mb-4 text-center">
    <h2><i class="bi bi-eye-fill"></i> Eye Disease AI</h2>
    <h4 class="text-muted">Reporte Clínico Automatizado</h4>
    <p class="small text-muted">Generado el <?= date('d/m/Y H:i', strtotime($pred['created_at'] ?? 'now')) ?></p>
    <hr>
</div>

<!-- DISCLAIMER LEGAL -->
<div class="alert alert-danger border-0 border-start border-4 border-danger shadow-sm mb-4 animate-fade-in <?= $isDanger ? 'pulse-alert' : '' ?>">
    <div class="d-flex align-items-start">
        <i class="bi bi-shield-exclamation fs-3 me-3 mt-1"></i>
        <div>
            <h5 class="fw-bold mb-1">AVISO LEGAL MÉDICO</h5>
            <p class="mb-0 small">
                Este sistema es una <strong>herramienta de apoyo basada en Inteligencia Artificial</strong> y no reemplaza la evaluación, diagnóstico o tratamiento de un oftalmólogo certificado. <strong>Si presenta dolor o pérdida súbita de visión, acuda a urgencias.</strong>
            </p>
        </div>
    </div>
</div>

<!-- REPORTE MÉDICO -->
<div class="card glass-card border-0 rounded-4 mb-4 overflow-hidden animate-fade-in">
    <div class="row g-0">
        <!-- Diagnóstico -->
        <div class="col-lg-5 text-white position-relative" style="background-color: <?= $cls['color'] ?>;">
            <div class="p-4 p-md-5 h-100 d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <span class="badge bg-white text-dark rounded-pill px-3 py-2 fw-bold shadow-sm">
                            <i class="bi <?= $cls['icon'] ?> me-1" style="color: <?= $cls['color'] ?>"></i>
                            Nivel: <?= $cls['severity'] ?>
                        </span>
                    </div>
                    
                    <p class="text-white-50 text-uppercase fw-bold letter-spacing-1 mb-1 small">Diagnóstico IA</p>
                    <h2 class="display-5 fw-bold mb-3 text-white"><?= $cls['label'] ?></h2>
                    
                    <div class="bg-black bg-opacity-25 rounded-3 p-3 mb-4 border border-white border-opacity-25">
                        <p class="mb-0 fs-6 lh-sm"><?= $cls['description'] ?></p>
                    </div>

                    <div class="d-flex align-items-center gap-3 mb-4">
                        <div class="text-center">
                            <h3 class="fw-bold mb-0"><?= $result['confidence'] ?>%</h3>
                            <span class="small text-white-50 text-uppercase fw-bold">Confianza IA</span>
                        </div>
                        <div class="vr bg-white opacity-25"></div>
                        <div>
                            <span class="badge bg-dark bg-opacity-50 border border-white border-opacity-25 d-block mb-1">
                                <strong>Riesgo:</strong> <?= $cls['risk_level'] ?>
                            </span>
                            <span class="badge bg-dark bg-opacity-50 border border-white border-opacity-25 d-block">
                                <strong>Modelo:</strong> <?= $result['model'] ?>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="mt-auto">
                    <div class="bg-white rounded-3 p-2 text-center shadow-sm">
                        <img src="<?= BASE_URL ?>/<?= sanitize($pred['image_path']) ?>" class="img-fluid rounded" alt="Oftalmoscopio" style="max-height: 180px; width: 100%; object-fit: cover;">
                    </div>
                </div>
            </div>
        </div>

        <!-- Plan Clínico -->
        <div class="col-lg-7 bg-white p-4 p-md-5">
            <h4 class="fw-bold mb-4 text-dark border-bottom pb-3">
                <i class="bi bi-clipboard2-pulse text-primary me-2"></i>Recomendaciones Clínicas
            </h4>
            
            <?php if ($cls['warning']): ?>
            <div class="alert <?= $cls['badge'] ?> text-white border-0 shadow-sm d-flex align-items-center mb-4 rounded-3">
                <i class="bi bi-exclamation-diamond-fill fs-4 me-3"></i>
                <div class="fw-medium"><?= $cls['warning'] ?></div>
            </div>
            <?php endif; ?>

            <div class="clinical-timeline mt-4">
                <div class="clinical-item">
                    <h6 class="fw-bold text-dark mb-1"><i class="bi bi-lightbulb text-warning me-2"></i>Acción Sugerida</h6>
                    <p class="text-muted mb-0"><?= $cls['advice'] ?></p>
                </div>
                
                <div class="clinical-item">
                    <h6 class="fw-bold text-dark mb-1"><i class="bi bi-capsule text-danger me-2"></i>Tratamiento Habitual</h6>
                    <p class="text-muted mb-0"><?= $cls['treatment'] ?></p>
                </div>
                
                <div class="clinical-item">
                    <h6 class="fw-bold text-dark mb-1"><i class="bi bi-activity text-success me-2"></i>Síntomas</h6>
                    <p class="text-muted mb-0"><?= $cls['symptoms'] ?></p>
                </div>

                <div class="clinical-item">
                    <h6 class="fw-bold text-dark mb-1"><i class="bi bi-person-badge text-info me-2"></i>Especialista Recomendado</h6>
                    <p class="text-muted mb-0"><?= $cls['specialist'] ?>. <?= $cls['follow_up'] ?></p>
                </div>
            </div>

            <!-- Detalles Probabilidades -->
            <hr class="mt-5 mb-4 no-print">
            <h6 class="fw-bold text-muted text-uppercase small mb-3 no-print">Distribución de Probabilidades IA</h6>
            <div class="row g-3 no-print">
                <?php 
                $preds = json_decode($pred['all_predictions'], true) ?? []; arsort($preds);
                foreach ($preds as $c => $conf): $cInfo = DISEASE_CLASSES[$c];
                ?>
                <div class="col-md-6">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="small <?= $c === $result['class'] ? 'fw-bold text-dark' : 'text-muted' ?>">
                            <?= $cInfo['label'] ?>
                        </span>
                        <span class="small fw-bold"><?= $conf ?>%</span>
                    </div>
                    <div class="progress" style="height: 6px;">
                        <div class="progress-bar <?= $c !== $result['class'] ? 'opacity-40' : '' ?>"
                             style="width: <?= $conf ?>%; background-color: <?= $cInfo['color'] ?>"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php require VIEWS_PATH . '/layouts/footer.php'; ?>
