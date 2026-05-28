<?php
$pageTitle = 'Reporte Comparativo de Diagnósticos IA';
require VIEWS_PATH . '/layouts/header.php';

$imagePath = $data['image_path'];
$compareAllMode = isset($data['compare_all_mode']) && $data['compare_all_mode'];

if ($compareAllMode) {
    $results = $data['results'];
    
    // Contar consensos y clasificaciones
    $validPredictions = [];
    $modelNames = [];
    $totalTime = 0;
    $fastestKey = null;
    $fastestTime = floatval(999999);
    
    foreach ($results as $key => $res) {
        if ($res && !isset($res['error'])) {
            $validPredictions[$key] = $res;
            $totalTime += $res['processing_time'];
            if ($res['processing_time'] < $fastestTime) {
                $fastestTime = $res['processing_time'];
                $fastestKey = $key;
            }
        }
    }
    
    // Encontrar clases de consenso
    $classCounts = [];
    $bestConf = -1;
    $bestResult = null;
    
    foreach ($validPredictions as $key => $res) {
        $c = $res['class'];
        $classCounts[$c] = ($classCounts[$c] ?? 0) + 1;
        if ($res['confidence'] > $bestConf) {
            $bestConf = $res['confidence'];
            $bestResult = $res;
        }
    }
    
    // Consenso Clínico
    arsort($classCounts);
    $consensusClass = key($classCounts);
    $consensusCount = current($classCounts);
    $totalValid = count($validPredictions);
    
    $consensusLevel = "Sin Consenso";
    $consensusAlertClass = "alert-danger";
    $consensusBadge = "bg-danger";
    
    if ($totalValid > 0) {
        $ratio = $consensusCount / $totalValid;
        if ($ratio >= 1.0) {
            $consensusLevel = "Consenso Absoluto (100% de Coincidencia)";
            $consensusAlertClass = "alert-success border-success";
            $consensusBadge = "bg-success";
        } elseif ($ratio >= 0.66) {
            $consensusLevel = "Consenso Fuerte (" . $consensusCount . "/" . $totalValid . " modelos)";
            $consensusAlertClass = "alert-primary border-primary";
            $consensusBadge = "bg-primary";
        } elseif ($ratio >= 0.5) {
            $consensusLevel = "Consenso Moderado (" . $consensusCount . "/" . $totalValid . " modelos)";
            $consensusAlertClass = "alert-warning border-warning";
            $consensusBadge = "bg-warning text-dark";
        } else {
            $consensusLevel = "Discrepancia Diagnóstica — Se aconseja revisión especializada urgente";
            $consensusAlertClass = "alert-danger border-danger";
            $consensusBadge = "bg-danger";
        }
    }

    $finalResult = $bestResult;
    $finalCls = $finalResult ? (DISEASE_CLASSES[$consensusClass] ?? DISEASE_CLASSES['normal']) : null;
    $isDanger = $finalCls && in_array($finalCls['risk_level'], ['ALTO RIESGO', 'URGENCIA MÉDICA']);
} else {
    // Modo Dual Clásico (ResNet50 + YOLOv8)
    $resnet = $data['resnet'];
    $yolo   = $data['yolo'];
    
    $resnetOk = !isset($resnet['error']);
    $yoloOk   = !isset($yolo['error']);
    
    $consensus = ($resnetOk && $yoloOk && $resnet['class'] === $yolo['class']);
    
    $winner = null;
    if ($resnetOk && $yoloOk) {
        $winner = ($resnet['confidence'] >= $yolo['confidence']) ? 'resnet50' : 'yolov8';
    } elseif ($resnetOk) {
        $winner = 'resnet50';
    } elseif ($yoloOk) {
        $winner = 'yolov8';
    }
    
    $finalResult = $winner === 'resnet50' ? $resnet : ($winner ? $yolo : null);
    $finalCls    = $finalResult ? (DISEASE_CLASSES[$finalResult['class']] ?? DISEASE_CLASSES['normal']) : null;
    $isDanger    = $finalCls && in_array($finalCls['risk_level'], ['ALTO RIESGO', 'URGENCIA MÉDICA']);
}
?>

<style>
.glass-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(226, 232, 240, 0.8);
    box-shadow: 0 10px 30px 0 rgba(31, 38, 135, 0.05);
    border-radius: 16px;
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
.bg-purple { background-color: #8b5cf6 !important; color: white; }
.pulse-alert {
    animation: pulse-red 2s infinite;
}
@keyframes pulse-red {
    0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
    70% { box-shadow: 0 0 0 10px rgba(239, 68, 68, 0); }
    100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
}
@media print {
    body { background: white !important; }
    .no-print { display: none !important; }
    .glass-card, .card { box-shadow: none !important; border: 1px solid #ddd !important; }
    .print-only { display: block !important; }
}
.print-only { display: none; }
</style>

<!-- Cabecera de Reporte -->
<div class="d-flex justify-content-between align-items-center mb-4 no-print animate-fade-in">
    <h1 class="h3 mb-0 fw-bold text-dark d-flex align-items-center">
        <i class="bi bi-file-medical-fill me-2 text-primary"></i>
        <span>Reporte Clínico Comparativo de IA</span>
    </h1>
    <div class="d-flex gap-2">
        <button onclick="window.print()" class="btn btn-outline-dark fw-medium rounded-pill px-3">
            <i class="bi bi-printer me-1"></i> Exportar a PDF
        </button>
        <a href="<?= BASE_URL ?>/prediction/form" class="btn btn-primary fw-medium rounded-pill px-4" 
           style="background: linear-gradient(135deg,#3b82f6,#1d4ed8); border:none;">
            <i class="bi bi-plus-circle me-1"></i> Analizar Nueva Imagen
        </a>
    </div>
</div>

<div class="print-only mb-4 text-center">
    <h2><i class="bi bi-eye-fill"></i> Eye Disease AI System</h2>
    <h4 class="text-muted">Reporte Diagnóstico Comparativo Automatizado</h4>
    <p class="small text-muted">Generado el <?= date('d/m/Y H:i') ?></p>
    <hr>
</div>

<!-- AVISO LEGAL MÉDICO -->
<div class="alert alert-danger border-0 border-start border-4 border-danger shadow-sm mb-4 animate-fade-in <?= $isDanger ? 'pulse-alert' : '' ?>" style="border-radius: 12px;">
    <div class="d-flex align-items-start">
        <i class="bi bi-shield-exclamation fs-3 me-3 mt-1 text-danger"></i>
        <div>
            <h5 class="fw-bold mb-1 text-danger-emphasis">AVISO LEGAL DE ASISTENCIA CLÍNICA</h5>
            <p class="mb-0 small text-muted">
                Este sistema es una **herramienta de apoyo basada en Inteligencia Artificial** para soporte diagnóstico de retina. Los resultados representados son predicciones algorítmicas y bajo ninguna circunstancia reemplazan la evaluación, diagnóstico u opinión de un oftalmólogo clínico colegiado. **En caso de pérdida visual súbita, acuda de inmediato a urgencias.**
            </p>
        </div>
    </div>
</div>

<!-- MODO COMPARAR TODOS (6 MODELOS) -->
<?php if ($compareAllMode): ?>
    
    <!-- CONCORDANCIA Y CONSENSO CLÍNICO -->
    <div class="alert <?= $consensusAlertClass ?> border border-2 shadow-sm p-4 mb-4 rounded-4 animate-fade-in d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center">
            <div class="bg-white rounded-circle p-3 me-3 text-center shadow-sm d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                <i class="bi bi-people-fill fs-3 text-dark"></i>
            </div>
            <div>
                <h5 class="fw-bold mb-1 text-dark">Estatus de Consenso Ocular</h5>
                <p class="mb-0 small text-muted">El diagnóstico predominante es <strong><?= $finalCls['label'] ?></strong> con un nivel de <strong><?= $consensusLevel ?></strong>.</p>
            </div>
        </div>
        <span class="badge <?= $consensusBadge ?> px-3 py-2 fs-6 rounded-pill shadow-sm">
            <?= $consensusCount ?> de <?= $totalValid ?> Modelos
        </span>
    </div>

    <!-- REPORTE MÉDICO PRINCIPAL -->
    <?php if ($finalResult): ?>
    <div class="card glass-card border-0 mb-4 overflow-hidden animate-fade-in">
        <div class="row g-0">
            <!-- Diagnóstico y Datos Clínicos -->
            <div class="col-lg-5 text-white position-relative" style="background-color: <?= $finalCls['color'] ?>;">
                <div class="p-4 p-md-5 h-100 d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <span class="badge bg-white text-dark rounded-pill px-3 py-2 fw-bold shadow-sm">
                                <i class="bi <?= $finalCls['icon'] ?> me-1" style="color: <?= $finalCls['color'] ?>"></i>
                                Nivel: <?= $finalCls['severity'] ?>
                            </span>
                            <i class="bi <?= $finalCls['icon'] ?> opacity-25" style="font-size: 3.5rem; position: absolute; top: 1.5rem; right: 1.5rem;"></i>
                        </div>
                        
                        <p class="text-white-50 text-uppercase fw-bold letter-spacing-1 mb-1 small">Diagnóstico por Consenso IA</p>
                        <h2 class="display-5 fw-bold mb-3 text-white"><?= $finalCls['label'] ?></h2>
                        
                        <div class="bg-black bg-opacity-25 rounded-3 p-3 mb-4 border border-white border-opacity-25">
                            <p class="mb-0 fs-6 lh-sm"><?= $finalCls['description'] ?></p>
                        </div>

                        <div class="d-flex align-items-center gap-3 mb-4">
                            <div class="text-center">
                                <h3 class="fw-bold mb-0"><?= $bestConf ?>%</h3>
                                <span class="small text-white-50 text-uppercase fw-bold">Confianza Max.</span>
                            </div>
                            <div class="vr bg-white opacity-25"></div>
                            <div>
                                <span class="badge bg-dark bg-opacity-50 border border-white border-opacity-25 d-block mb-1">
                                    <strong>Riesgo:</strong> <?= $finalCls['risk_level'] ?>
                                </span>
                                <span class="badge bg-dark bg-opacity-50 border border-white border-opacity-25 d-block">
                                    <strong>Especialista:</strong> <?= $finalCls['specialist'] ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-auto">
                        <div class="bg-white rounded-3 p-2 text-center shadow-sm">
                            <img src="<?= BASE_URL ?>/<?= sanitize($imagePath) ?>" class="img-fluid rounded" alt="Oftalmoscopio" style="max-height: 200px; width: 100%; object-fit: cover;">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recomendaciones Clínicas -->
            <div class="col-lg-7 bg-white p-4 p-md-5">
                <h4 class="fw-bold mb-4 text-dark border-bottom pb-3">
                    <i class="bi bi-clipboard2-pulse text-primary me-2"></i>Acción y Plan Clínico
                </h4>
                
                <?php if ($finalCls['warning']): ?>
                <div class="alert <?= $finalCls['badge'] ?> text-white border-0 shadow-sm d-flex align-items-center mb-4 rounded-3">
                    <i class="bi bi-exclamation-diamond-fill fs-4 me-3"></i>
                    <div class="fw-medium"><?= $finalCls['warning'] ?></div>
                </div>
                <?php endif; ?>

                <div class="clinical-timeline mt-4">
                    <div class="clinical-item">
                        <h6 class="fw-bold text-dark mb-1"><i class="bi bi-lightbulb text-warning me-2"></i>Acción Inmediata Sugerida</h6>
                        <p class="text-muted mb-0"><?= $finalCls['advice'] ?></p>
                    </div>
                    
                    <div class="clinical-item">
                        <h6 class="fw-bold text-dark mb-1"><i class="bi bi-capsule text-danger me-2"></i>Tratamiento Clínico Habitual</h6>
                        <p class="text-muted mb-0"><?= $finalCls['treatment'] ?></p>
                    </div>
                    
                    <div class="clinical-item">
                        <h6 class="fw-bold text-dark mb-1"><i class="bi bi-activity text-success me-2"></i>Posibles Síntomas de Alerta</h6>
                        <p class="text-muted mb-0"><?= $finalCls['symptoms'] ?></p>
                    </div>

                    <div class="clinical-item">
                        <h6 class="fw-bold text-dark mb-1"><i class="bi bi-shield-check text-info me-2"></i>Prevención y Seguimiento Continuo</h6>
                        <p class="text-muted mb-0"><?= $finalCls['prevention'] ?><br><strong>Próximo paso:</strong> <?= $finalCls['follow_up'] ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- TABLA DETALLADA DE COMPARATIVA CLÍNICA -->
    <div class="card glass-card border-0 mb-4 animate-fade-in">
        <div class="card-header bg-light py-3 border-0">
            <h5 class="mb-0 fw-bold text-secondary d-flex justify-content-between align-items-center">
                <span><i class="bi bi-grid-3x3-gap-fill me-2"></i>Matriz Analítica Comparativa (6 Modelos)</span>
                <span class="small text-muted fs-6">Tiempo total de análisis: <?= round($totalTime, 1) ?> ms</span>
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size: 0.95rem;">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Modelo</th>
                            <th>Tipo Arquitectura</th>
                            <th>Diagnóstico Sugerido</th>
                            <th class="text-center">Confianza (%)</th>
                            <th class="text-center">Tiempo (ms)</th>
                            <th class="pe-4 text-end">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $types = [
                            'resnet50'     => 'CNN ResNet (Convolucional Profunda)',
                            'efficientnet' => 'CNN EfficientNet (Optimizada Móvil)',
                            'densenet'     => 'CNN DenseNet (Dense Blocks)',
                            'sunet'        => 'Transformer (Auto-atención)',
                            'yolov8'       => 'YOLOv8m (Ultralytics)',
                            'yolo11'       => 'YOLOv11m (Ultralytics - SOTA)'
                        ];
                        foreach ($results as $key => $res):
                            $name = $res['model'] ?? ($types[$key] ?? $key);
                            $ok = !isset($res['error']);
                        ?>
                        <tr>
                            <td class="ps-4 fw-bold text-dark"><?= $name ?></td>
                            <td class="text-muted small"><?= $types[$key] ?? 'IA Model' ?></td>
                            <td>
                                <?php if ($ok): 
                                    $cInf = DISEASE_CLASSES[$res['class']] ?? DISEASE_CLASSES['normal'];
                                ?>
                                    <span class="badge text-white px-3 py-2 rounded-pill" style="background-color: <?= $cInf['color'] ?>;">
                                        <i class="bi <?= $cInf['icon'] ?> me-1"></i> <?= $cInf['label'] ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted italic small"><i class="bi bi-x-circle text-danger"></i> No entrenado / Error</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center fw-semibold">
                                <?php if ($ok): ?>
                                    <?= $res['confidence'] ?>%
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td class="text-center text-muted">
                                <?php if ($ok): ?>
                                    <?= round($res['processing_time'], 1) ?> ms
                                    <?php if ($key === $fastestKey): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success ms-1 small"><i class="bi bi-lightning-fill"></i> Rápido</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td class="pe-4 text-end">
                                <?php if ($ok): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-2"><i class="bi bi-check-circle-fill"></i> Disponible</span>
                                <?php else: ?>
                                    <span class="badge bg-warning bg-opacity-10 text-warning text-dark rounded-pill px-2" title="<?= htmlspecialchars($res['error']) ?>"><i class="bi bi-exclamation-triangle-fill"></i> Inactivo</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- DETALLE DINÁMICO DE PREDICCIONES POR MODELO -->
    <div class="row g-4 no-print animate-fade-in">
        <h5 class="fw-bold text-dark mb-1 px-3"><i class="bi bi-bar-chart-fill me-2 text-primary"></i>Distribución de Probabilidades por Arquitectura</h5>
        
        <?php foreach ($validPredictions as $key => $res): 
            $cInf = DISEASE_CLASSES[$res['class']] ?? DISEASE_CLASSES['normal'];
        ?>
        <div class="col-md-6 col-lg-4">
            <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; background: #ffffff;">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-bold text-dark"><?= $res['model'] ?></span>
                        <span class="badge text-white px-2" style="background-color: <?= $cInf['color'] ?>; font-size: 0.75rem;">
                            <?= $cInf['label'] ?>
                        </span>
                    </div>
                    <div class="text-muted text-xs mb-3 d-flex justify-content-between">
                        <span><i class="bi bi-stopwatch"></i> <?= round($res['processing_time'], 1) ?> ms</span>
                        <span class="fw-bold text-dark"><?= $res['confidence'] ?>% Conf.</span>
                    </div>
                    
                    <!-- Barra de progreso principal -->
                    <div class="progress mb-3" style="height: 8px; border-radius: 4px;">
                        <div class="progress-bar" style="width: <?= $res['confidence'] ?>%; background-color: <?= $cInf['color'] ?>;"></div>
                    </div>

                    <!-- Todas las probabilidades -->
                    <?php 
                    $preds = $res['all_predictions'] ?? []; arsort($preds);
                    foreach ($preds as $cKey => $conf): $cInfo = DISEASE_CLASSES[$cKey] ?? DISEASE_CLASSES['normal'];
                    ?>
                    <div class="mb-1 d-flex align-items-center" style="font-size: 0.75rem;">
                        <div class="progress flex-grow-1 me-2" style="height: 5px;">
                            <div class="progress-bar" style="width: <?= $conf ?>%; background-color: <?= $cInfo['color'] ?>"></div>
                        </div>
                        <span class="text-muted" style="width: 125px; text-overflow: ellipsis; overflow: hidden; white-space: nowrap;"><?= $cInfo['label'] ?> (<?= $conf ?>%)</span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

<!-- MODO COMPARAR DOS MODELOS (ResNet50 + YOLOv8) -->
<?php else: ?>

    <div class="alert alert-info border-0 p-4 mb-4 shadow-sm animate-fade-in d-flex align-items-center justify-content-between" style="border-radius: 12px;">
        <div class="d-flex align-items-center">
            <div class="bg-white rounded-circle p-2 me-3 text-center shadow-sm" style="width: 50px; height: 50px; display: flex; align-items:center; justify-content-center;">
                <i class="bi bi-shuffle fs-3 text-primary"></i>
            </div>
            <div>
                <h5 class="fw-bold mb-1 text-dark">Estatus de Consenso Dual</h5>
                <p class="mb-0 small text-muted">Ambos modelos clínicos fueron evaluados.</p>
            </div>
        </div>
        <?php if ($consensus): ?>
            <span class="badge bg-success px-3 py-2 fs-6 rounded-pill shadow-sm"><i class="bi bi-check-circle-fill me-1"></i> Consenso Logrado</span>
        <?php else: ?>
            <span class="badge bg-warning text-dark px-3 py-2 fs-6 rounded-pill shadow-sm"><i class="bi bi-exclamation-triangle-fill me-1"></i> Discrepancia de Modelos</span>
        <?php endif; ?>
    </div>

    <!-- REPORTE MÉDICO PRINCIPAL -->
    <?php if ($finalResult): ?>
    <div class="card glass-card border-0 mb-4 overflow-hidden animate-fade-in">
        <div class="row g-0">
            <!-- Diagnóstico y Datos Clínicos -->
            <div class="col-lg-5 text-white position-relative" style="background-color: <?= $finalCls['color'] ?>;">
                <div class="p-4 p-md-5 h-100 d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <span class="badge bg-white text-dark rounded-pill px-3 py-2 fw-bold shadow-sm">
                                <i class="bi <?= $finalCls['icon'] ?> me-1" style="color: <?= $finalCls['color'] ?>"></i>
                                Nivel: <?= $finalCls['severity'] ?>
                            </span>
                            <i class="bi <?= $finalCls['icon'] ?> opacity-25" style="font-size: 3.5rem; position: absolute; top: 1.5rem; right: 1.5rem;"></i>
                        </div>
                        
                        <p class="text-white-50 text-uppercase fw-bold letter-spacing-1 mb-1 small">Diagnóstico por Consenso IA</p>
                        <h2 class="display-5 fw-bold mb-3 text-white"><?= $finalCls['label'] ?></h2>
                        
                        <div class="bg-black bg-opacity-25 rounded-3 p-3 mb-4 border border-white border-opacity-25">
                            <p class="mb-0 fs-6 lh-sm"><?= $finalCls['description'] ?></p>
                        </div>

                        <div class="d-flex align-items-center gap-3 mb-4">
                            <div class="text-center">
                                <h3 class="fw-bold mb-0"><?= $finalResult['confidence'] ?>%</h3>
                                <span class="small text-white-50 text-uppercase fw-bold">Confianza</span>
                            </div>
                            <div class="vr bg-white opacity-25"></div>
                            <div>
                                <span class="badge bg-dark bg-opacity-50 border border-white border-opacity-25 d-block mb-1">
                                    <strong>Riesgo:</strong> <?= $finalCls['risk_level'] ?>
                                </span>
                                <span class="badge bg-dark bg-opacity-50 border border-white border-opacity-25 d-block">
                                    <strong>Especialista:</strong> <?= $finalCls['specialist'] ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-auto">
                        <div class="bg-white rounded-3 p-2 text-center shadow-sm">
                            <img src="<?= BASE_URL ?>/<?= sanitize($imagePath) ?>" class="img-fluid rounded" alt="Oftalmoscopio" style="max-height: 200px; width: 100%; object-fit: cover;">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recomendaciones Clínicas -->
            <div class="col-lg-7 bg-white p-4 p-md-5">
                <h4 class="fw-bold mb-4 text-dark border-bottom pb-3">
                    <i class="bi bi-clipboard2-pulse text-primary me-2"></i>Acción y Plan Clínico
                </h4>
                
                <?php if ($finalCls['warning']): ?>
                <div class="alert <?= $finalCls['badge'] ?> text-white border-0 shadow-sm d-flex align-items-center mb-4 rounded-3">
                    <i class="bi bi-exclamation-diamond-fill fs-4 me-3"></i>
                    <div class="fw-medium"><?= $finalCls['warning'] ?></div>
                </div>
                <?php endif; ?>

                <div class="clinical-timeline mt-4">
                    <div class="clinical-item">
                        <h6 class="fw-bold text-dark mb-1"><i class="bi bi-lightbulb text-warning me-2"></i>Acción Inmediata Sugerida</h6>
                        <p class="text-muted mb-0"><?= $finalCls['advice'] ?></p>
                    </div>
                    
                    <div class="clinical-item">
                        <h6 class="fw-bold text-dark mb-1"><i class="bi bi-capsule text-danger me-2"></i>Tratamiento Clínico Habitual</h6>
                        <p class="text-muted mb-0"><?= $finalCls['treatment'] ?></p>
                    </div>
                    
                    <div class="clinical-item">
                        <h6 class="fw-bold text-dark mb-1"><i class="bi bi-activity text-success me-2"></i>Posibles Síntomas de Alerta</h6>
                        <p class="text-muted mb-0"><?= $finalCls['symptoms'] ?></p>
                    </div>

                    <div class="clinical-item">
                        <h6 class="fw-bold text-dark mb-1"><i class="bi bi-shield-check text-info me-2"></i>Prevención y Seguimiento Continuo</h6>
                        <p class="text-muted mb-0"><?= $finalCls['prevention'] ?><br><strong>Próximo paso:</strong> <?= $finalCls['follow_up'] ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Detalles Técnicos IA Dual -->
    <div class="card glass-card border-0 rounded-4 mb-4 shadow-sm no-print animate-fade-in">
        <div class="card-header bg-light py-3 border-0">
            <h5 class="mb-0 fw-bold text-secondary"><i class="bi bi-cpu me-2"></i>Detalles Técnicos del Análisis IA</h5>
        </div>
        <div class="card-body p-4">
            <div class="row g-4">
                <!-- ResNet50 -->
                <div class="col-md-6 border-end">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold text-primary mb-0">ResNet50 (Transfer Learning)</h6>
                        <?php if ($resnetOk && $winner === 'resnet50'): ?><span class="badge bg-success">Ganador</span><?php endif; ?>
                    </div>
                    <?php if ($resnetOk): ?>
                        <?php $rCls = DISEASE_CLASSES[$resnet['class']] ?? DISEASE_CLASSES['normal']; ?>
                        <p class="mb-2"><strong>Diagnóstico:</strong> <span style="color: <?= $rCls['color'] ?>"><?= $rCls['label'] ?></span> (<?= $resnet['confidence'] ?>%)</p>
                        <p class="small text-muted mb-3"><i class="bi bi-stopwatch"></i> Tiempo: <?= $resnet['processing_time'] ?> ms</p>
                        <?php 
                        $preds = $resnet['all_predictions'] ?? []; arsort($preds);
                        foreach ($preds as $cls => $conf): $cInfo = DISEASE_CLASSES[$cls] ?? DISEASE_CLASSES['normal'];
                        ?>
                        <div class="mb-1 d-flex align-items-center" style="font-size: 0.8rem;">
                            <div class="progress flex-grow-1 me-2" style="height: 6px;">
                                <div class="progress-bar" style="width: <?= $conf ?>%; background-color: <?= $cInfo['color'] ?>"></div>
                            </div>
                            <span class="text-muted" style="width: 140px;"><?= $cInfo['label'] ?> (<?= $conf ?>%)</span>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="alert alert-warning py-2 small">Modelo no disponible o en entrenamiento.</div>
                    <?php endif; ?>
                </div>

                <!-- YOLOv8 -->
                <div class="col-md-6">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold text-success mb-0">YOLOv8m (Classification)</h6>
                        <?php if ($yoloOk && $winner === 'yolov8'): ?><span class="badge bg-success">Ganador</span><?php endif; ?>
                    </div>
                    <?php if ($yoloOk): ?>
                        <?php $yCls = DISEASE_CLASSES[$yolo['class']] ?? DISEASE_CLASSES['normal']; ?>
                        <p class="mb-2"><strong>Diagnóstico:</strong> <span style="color: <?= $yCls['color'] ?>"><?= $yCls['label'] ?></span> (<?= $yolo['confidence'] ?>%)</p>
                        <p class="small text-muted mb-3"><i class="bi bi-stopwatch"></i> Tiempo: <?= $yolo['processing_time'] ?> ms</p>
                        <?php 
                        $preds = $yolo['all_predictions'] ?? []; arsort($preds);
                        foreach ($preds as $cls => $conf): $cInfo = DISEASE_CLASSES[$cls] ?? DISEASE_CLASSES['normal'];
                        ?>
                        <div class="mb-1 d-flex align-items-center" style="font-size: 0.8rem;">
                            <div class="progress flex-grow-1 me-2" style="height: 6px;">
                                <div class="progress-bar" style="width: <?= $conf ?>%; background-color: <?= $cInfo['color'] ?>"></div>
                            </div>
                            <span class="text-muted" style="width: 140px;"><?= $cInfo['label'] ?> (<?= $conf ?>%)</span>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="alert alert-warning py-2 small">Modelo no disponible o en entrenamiento.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

<?php endif; ?>

<?php require VIEWS_PATH . '/layouts/footer.php'; ?>
