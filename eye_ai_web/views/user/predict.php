<?php
$pageTitle = 'Análisis Clínico Ocular con IA';
require VIEWS_PATH . '/layouts/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-12">
        <div class="card border-0 shadow-lg mb-5" style="border-radius: 20px; overflow: hidden; background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px);">
            <!-- Header premium con gradiente médico moderno -->
            <div class="card-header border-0 py-4 px-4 text-white d-flex align-items-center justify-content-between" 
                 style="background: linear-gradient(135deg, #0f172a, #1e293b);">
                <div>
                    <h4 class="mb-1 fw-bold text-white d-flex align-items-center">
                        <i class="bi bi-robot me-3 fs-3 text-info"></i>
                        <span>Laboratorio de Diagnóstico por IA</span>
                    </h4>
                    <p class="mb-0 text-slate-400 small text-white-50">Sube una imagen de oftalmoscopio para un análisis clínico automatizado de retina y fondo de ojo.</p>
                </div>
                <span class="badge bg-info bg-opacity-20 text-info border border-info border-opacity-30 px-3 py-2 fw-semibold">
                    <i class="bi bi-shield-check me-1"></i> Entorno Clínico Seguro
                </span>
            </div>

            <div class="card-body p-4 p-md-5">
                <form action="<?= BASE_URL ?>/prediction/predict" method="POST" enctype="multipart/form-data" class="prediction-form" id="predictForm">
                    <?= csrfField() ?>
                    
                    <!-- ─── Selección de Modo de Análisis ─── -->
                    <div class="mb-5">
                        <div class="d-flex align-items-center mb-4">
                            <div class="bg-primary bg-opacity-10 p-2 rounded-3 me-3 text-primary">
                                <i class="bi bi-cpu fs-4"></i>
                            </div>
                            <h5 class="mb-0 fw-bold text-dark">1. Seleccionar Arquitectura de Red Neuronal</h5>
                        </div>
                        
                        <!-- Categoría de Comparativas -->
                        <div class="mb-4">
                            <span class="text-uppercase tracking-wider fw-bold text-xs text-muted mb-3 d-block" style="font-size: 0.75rem; letter-spacing: 1px;">Modos Comparativos Multi-Modelo</span>
                            <div class="row g-3">
                                <!-- Opción: COMPARAR TODOS (6 MODELOS) -->
                                <div class="col-md-6">
                                    <label class="w-100 h-100">
                                        <input type="radio" name="model" value="compare_all" class="btn-check" id="modelCompareAll" checked>
                                        <div class="card border-2 p-3 h-100 model-card premium-compare-card" style="cursor:pointer; transition: all .25s ease;">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <span class="badge" style="background: linear-gradient(135deg, #ec4899, #8b5cf6); color:white;">🔥 Máxima Certeza Clínica</span>
                                                <i class="bi bi-layers-half text-primary fs-4"></i>
                                            </div>
                                            <div class="fw-bold fs-5 mb-1 text-dark">Comparar Todos los Modelos (6x)</div>
                                            <p class="text-muted small mb-0">Ejecuta las 6 IAs (ResNet50, YOLOv11, Swin Transformer, DenseNet121, EfficientNetV2 y YOLOv8) en paralelo. Muestra un dashboard con consenso, velocidad y métricas comparativas lado a lado.</p>
                                        </div>
                                    </label>
                                </div>

                                <!-- Opción: COMPARAR DOS (ResNet50 + YOLOv8) -->
                                <div class="col-md-6">
                                    <label class="w-100 h-100">
                                        <input type="radio" name="model" value="compare_both" class="btn-check" id="modelCompareBoth">
                                        <div class="card border-2 p-3 h-100 model-card premium-compare-card-blue" style="cursor:pointer; transition: all .25s ease;">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <span class="badge" style="background: linear-gradient(135deg, #6366f1, #3b82f6); color:white;">⚡ Rápido y Balanceado</span>
                                                <i class="bi bi-layout-split text-info fs-4"></i>
                                            </div>
                                            <div class="fw-bold fs-5 mb-1 text-dark">Comparar ResNet50 + YOLOv8</div>
                                            <p class="text-muted small mb-0">Compara los dos modelos estándar de la plataforma. Proporciona una vista rápida e intuitiva para evaluar discrepancias entre CNN tradicional y YOLO.</p>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Modelos Individuales -->
                        <div>
                            <span class="text-uppercase tracking-wider fw-bold text-xs text-muted mb-3 d-block" style="font-size: 0.75rem; letter-spacing: 1px;">Modelos Individuales</span>
                            <div class="row g-3">
                                
                                <!-- ResNet50 -->
                                <div class="col-sm-6 col-md-4">
                                    <label class="w-100 h-100">
                                        <input type="radio" name="model" value="resnet50" class="btn-check" id="modelResnet">
                                        <div class="card border-2 p-3 h-100 model-card" style="cursor:pointer; transition: all .2s;">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span class="badge bg-indigo-subtle text-indigo px-2 py-1">CNN Clásica</span>
                                                <span class="dot bg-indigo"></span>
                                            </div>
                                            <div class="fw-bold text-dark">ResNet50</div>
                                            <small class="text-muted text-xs">Transfer Learning optimizado. Excelente consistencia clínica.</small>
                                        </div>
                                    </label>
                                </div>

                                <!-- Swin Transformer (SUNet) -->
                                <div class="col-sm-6 col-md-4">
                                    <label class="w-100 h-100">
                                        <input type="radio" name="model" value="sunet" class="btn-check" id="modelSunet">
                                        <div class="card border-2 p-3 h-100 model-card" style="cursor:pointer; transition: all .2s;">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span class="badge bg-warning-subtle text-warning-emphasis px-2 py-1">Atención (Transformer)</span>
                                                <span class="dot bg-warning"></span>
                                            </div>
                                            <div class="fw-bold text-dark">Swin Transformer (SUNet)</div>
                                            <small class="text-muted text-xs">Atención global mediante ventanas desplazadas. Detecta micro-patrones.</small>
                                        </div>
                                    </label>
                                </div>

                                <!-- DenseNet121 -->
                                <div class="col-sm-6 col-md-4">
                                    <label class="w-100 h-100">
                                        <input type="radio" name="model" value="densenet" class="btn-check" id="modelDensenet">
                                        <div class="card border-2 p-3 h-100 model-card" style="cursor:pointer; transition: all .2s;">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span class="badge bg-primary-subtle text-primary px-2 py-1">Reutilización F.</span>
                                                <span class="dot bg-primary"></span>
                                            </div>
                                            <div class="fw-bold text-dark">DenseNet121</div>
                                            <small class="text-muted text-xs">Dense blocks optimizados. Reduce desvanecimiento de gradiente.</small>
                                        </div>
                                    </label>
                                </div>

                                <!-- EfficientNetV2 -->
                                <div class="col-sm-6 col-md-4">
                                    <label class="w-100 h-100">
                                        <input type="radio" name="model" value="efficientnet" class="btn-check" id="modelEffnet">
                                        <div class="card border-2 p-3 h-100 model-card" style="cursor:pointer; transition: all .2s;">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span class="badge bg-info-subtle text-info-emphasis px-2 py-1">Liviana & Móvil</span>
                                                <span class="dot bg-info"></span>
                                            </div>
                                            <div class="fw-bold text-dark">EfficientNetV2</div>
                                            <small class="text-muted text-xs">Ajuste de parámetros dinámico. Recomendado para celulares.</small>
                                        </div>
                                    </label>
                                </div>

                                <!-- YOLOv11 -->
                                <div class="col-sm-6 col-md-4">
                                    <label class="w-100 h-100">
                                        <input type="radio" name="model" value="yolo11" class="btn-check" id="modelYolo11">
                                        <div class="card border-2 p-3 h-100 model-card" style="cursor:pointer; transition: all .2s;">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span class="badge bg-success-subtle text-success px-2 py-1">Última Gen (SOTA)</span>
                                                <span class="dot bg-success"></span>
                                            </div>
                                            <div class="fw-bold text-dark">YOLOv11</div>
                                            <small class="text-muted text-xs">Arquitectura SOTA de clasificación de Ultralytics. Ultra-rápido.</small>
                                        </div>
                                    </label>
                                </div>

                                <!-- YOLOv8 -->
                                <div class="col-sm-6 col-md-4">
                                    <label class="w-100 h-100">
                                        <input type="radio" name="model" value="yolov8" class="btn-check" id="modelYolov8">
                                        <div class="card border-2 p-3 h-100 model-card" style="cursor:pointer; transition: all .2s;">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span class="badge bg-secondary-subtle text-secondary-emphasis px-2 py-1">Estable</span>
                                                <span class="dot bg-secondary"></span>
                                            </div>
                                            <div class="fw-bold text-dark">YOLOv8</div>
                                            <small class="text-muted text-xs">Detector de clasificación ultraliviano y veloz. Excelente latencia.</small>
                                        </div>
                                    </label>
                                </div>

                            </div>
                        </div>
                    </div>

                    <!-- ─── Carga de la Imagen Oftalmológica ─── -->
                    <div class="mb-5">
                        <div class="d-flex align-items-center mb-4">
                            <div class="bg-primary bg-opacity-10 p-2 rounded-3 me-3 text-primary">
                                <i class="bi bi-image fs-4"></i>
                            </div>
                            <h5 class="mb-0 fw-bold text-dark">2. Cargar Imagen Oftalmológica de la Retina</h5>
                        </div>

                        <div class="upload-area py-5 px-4 text-center border-2 border-dashed" id="uploadArea" 
                             style="border-radius: 16px; transition: all 0.3s ease; background: #f8fafc; cursor: pointer;">
                            <input type="file" name="image" id="image" class="d-none" accept=".jpg,.jpeg,.png,.bmp,.webp" required>
                            
                            <div id="uploadText">
                                <div class="icon-circle bg-white shadow-sm d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px; border-radius: 50%;">
                                    <i class="bi bi-cloud-arrow-up-fill text-primary display-5" style="transform: translateY(-2px);"></i>
                                </div>
                                <h5 class="text-dark fw-bold mb-1">Arrastra tu imagen oftalmológica o haz clic aquí</h5>
                                <p class="text-muted small mb-0">Admite archivos JPG, PNG, WEBP y BMP (Tamaño máximo: 10 MB)</p>
                            </div>

                            <img id="previewImg" src="#" alt="Vista Previa de Retina" class="d-none img-fluid rounded shadow-lg mt-2 mx-auto" 
                                 style="max-height: 320px; border: 4px solid white;">
                        </div>
                    </div>

                    <!-- Mensaje dinámico de información clínica -->
                    <div class="alert alert-info border-0 p-4 mb-5 shadow-sm d-flex align-items-start" style="border-radius: 12px; background: rgba(14, 165, 233, 0.08);">
                        <i class="bi bi-info-circle-fill text-info fs-3 me-3 mt-1"></i>
                        <div>
                            <h6 class="fw-bold text-info-emphasis mb-1" id="infoTitle">Modo Comparativo Completo (6 Modelos)</h6>
                            <p class="mb-0 text-muted small" id="infoText">
                                El sistema ejecutará simultáneamente los 6 modelos clínicos optimizados de la plataforma. Evaluará la consistencia del diagnóstico y generará una tabla detallada con predicciones, probabilidades y tiempos de respuesta de cada arquitectura en tiempo real.
                            </p>
                        </div>
                    </div>

                    <!-- Botón de Envío Animado -->
                    <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold py-3 shadow-lg" id="submitBtn" 
                            style="border-radius: 12px; background: linear-gradient(135deg, #3b82f6, #1d4ed8); border: none; font-size: 1.15rem; letter-spacing: 0.5px;">
                        <i class="bi bi-bar-chart-line me-2"></i> Ejecutar Análisis Comparativo (Todos los Modelos)
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
/* Estilos premium de las tarjetas de modelos */
.model-card {
    border: 2px solid #e2e8f0 !important;
    background-color: #ffffff;
    border-radius: 14px;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02);
}
.model-card:hover {
    transform: translateY(-3px);
    border-color: #cbd5e1 !important;
    box-shadow: 0 10px 15px -3px rgba(0,0,0,0.06);
}
.btn-check:checked + .model-card {
    border-color: #3b82f6 !important;
    background-color: rgba(59, 130, 246, 0.04) !important;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
}

/* Premium compare card "Todos los modelos" */
.premium-compare-card {
    border: 2px solid #f1f5f9 !important;
    background: linear-gradient(145deg, #ffffff, #faf5ff) !important;
}
.premium-compare-card:hover {
    box-shadow: 0 15px 30px -10px rgba(139, 92, 246, 0.15) !important;
}
#modelCompareAll:checked + .premium-compare-card {
    border-color: #a855f7 !important;
    background: linear-gradient(145deg, rgba(168, 85, 247, 0.04), rgba(236, 72, 153, 0.04)) !important;
    box-shadow: 0 0 0 4px rgba(168, 85, 247, 0.2) !important;
}

/* Premium compare card "Comparar Ambos" */
.premium-compare-card-blue {
    border: 2px solid #f1f5f9 !important;
    background: linear-gradient(145deg, #ffffff, #f0f9ff) !important;
}
.premium-compare-card-blue:hover {
    box-shadow: 0 15px 30px -10px rgba(59, 130, 246, 0.15) !important;
}
#modelCompareBoth:checked + .premium-compare-card-blue {
    border-color: #3b82f6 !important;
    background: linear-gradient(145deg, rgba(59, 130, 246, 0.04), rgba(14, 165, 233, 0.04)) !important;
    box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.2) !important;
}

/* Puntos de color para identificar los modelos */
.dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    display: inline-block;
}
.bg-indigo { background-color: #6366f1 !important; }
.bg-indigo-subtle { background-color: rgba(99, 102, 241, 0.1) !important; color: #4f46e5 !important; }
.text-indigo { color: #4f46e5 !important; }

/* Upload area drag & drop effects */
.upload-area:hover {
    border-color: #3b82f6 !important;
    background: rgba(59, 130, 246, 0.02) !important;
}
.upload-area.dragover {
    border-color: #10b981 !important;
    background: rgba(16, 185, 129, 0.05) !important;
    transform: scale(1.01);
}
</style>

<script>
// Manejo dinámico de textos informativos y botones
const infoDetails = {
    compare_all: {
        title: "Modo Comparativo Completo (6 Modelos)",
        text: "El sistema ejecutará simultáneamente los 6 modelos clínicos optimizados de la plataforma. Evaluará la consistencia del diagnóstico y generará una tabla detallada con predicciones, probabilidades y tiempos de respuesta de cada arquitectura en tiempo real.",
        button: '<i class="bi bi-bar-chart-line me-2"></i> Ejecutar Análisis Comparativo (Todos los Modelos)'
    },
    compare_both: {
        title: "Modo Comparativo Estándar (ResNet50 + YOLOv8)",
        text: "La imagen será analizada con ResNet50 (Transfer Learning) y YOLOv8 (Detector Rápido). Permite comparar con agilidad si la estructura convolucional densa coincide con la regresión ágil de la familia YOLO.",
        button: '<i class="bi bi-layout-split me-2"></i> Comparar ResNet50 + YOLOv8'
    },
    resnet50: {
        title: "Modelo Individual: ResNet50",
        text: "Una de las redes convolucionales profundas más estables y consistentes para diagnóstico médico por imágenes. Con 50 capas de profundidad, es ideal para detectar cambios sutiles en la retina.",
        button: '<i class="bi bi-magic me-2"></i> Analizar con ResNet50'
    },
    sunet: {
        title: "Modelo Individual: Swin Transformer (SUNet)",
        text: "La vanguardia en Vision Transformers. Utiliza mecanismos de auto-atención en ventanas desplazadas, ideal para relacionar diferentes partes de la retina y capturar dependencias globales complejas.",
        button: '<i class="bi bi-magic me-2"></i> Analizar con Swin Transformer (SUNet)'
    },
    densenet: {
        title: "Modelo Individual: DenseNet121",
        text: "Estructura densa donde cada capa se conecta a todas las capas siguientes. Reutiliza los mapas de características al máximo, reduciendo desvanecimientos de gradiente en imágenes oftalmológicas de baja resolución.",
        button: '<i class="bi bi-magic me-2"></i> Analizar con DenseNet121'
    },
    efficientnet: {
        title: "Modelo Individual: EfficientNetV2",
        text: "Optimizado para ser altamente liviano y eficiente en parámetros sin perder precisión. Adecuado para implementarse en plataformas móviles y procesar imágenes capturadas con cámaras frontales o adaptadores de celular.",
        button: '<i class="bi bi-magic me-2"></i> Analizar con EfficientNetV2'
    },
    yolo11: {
        title: "Modelo Individual: YOLOv11 (SOTA)",
        text: "La red de clasificación de última generación de Ultralytics. Brinda el equilibrio definitivo entre velocidad ultra-rápida (ideal para uso clínico de tiempo real) y precisión de estado del arte (SOTA).",
        button: '<i class="bi bi-magic me-2"></i> Analizar con YOLOv11'
    },
    yolov8: {
        title: "Modelo Individual: YOLOv8",
        text: "El modelo clasificador ágil de Ultralytics. Diseñado para inferencias de latencia extremadamente baja y un uso de recursos balanceado.",
        button: '<i class="bi bi-magic me-2"></i> Analizar con YOLOv8'
    }
};

document.querySelectorAll('input[name="model"]').forEach(radio => {
    radio.addEventListener('change', function() {
        const val = this.value;
        const details = infoDetails[val];
        if (details) {
            document.getElementById('infoTitle').innerText = details.title;
            document.getElementById('infoText').innerText = details.text;
            document.getElementById('submitBtn').innerHTML = details.button;
        }
    });
});

// Drag & Drop de Archivos
const uploadArea = document.getElementById('uploadArea');
const fileInput = document.getElementById('image');
const previewImg = document.getElementById('previewImg');
const uploadText = document.getElementById('uploadText');

uploadArea.addEventListener('click', () => fileInput.click());

fileInput.addEventListener('change', function() {
    showPreview(this.files[0]);
});

uploadArea.addEventListener('dragover', (e) => {
    e.preventDefault();
    uploadArea.classList.add('dragover');
});

uploadArea.addEventListener('dragleave', () => {
    uploadArea.classList.remove('dragover');
});

uploadArea.addEventListener('drop', (e) => {
    e.preventDefault();
    uploadArea.classList.remove('dragover');
    if (e.dataTransfer.files.length) {
        fileInput.files = e.dataTransfer.files;
        showPreview(e.dataTransfer.files[0]);
    }
});

function showPreview(file) {
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            previewImg.src = e.target.result;
            previewImg.classList.remove('d-none');
            uploadText.classList.add('d-none');
        }
        reader.readAsDataURL(file);
    }
}
</script>

<?php require VIEWS_PATH . '/layouts/footer.php'; ?>
