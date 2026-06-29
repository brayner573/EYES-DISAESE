<?php
/**
 * config.php — Configuración Global del Sistema
 * Eye Disease AI — Sistema de Detección de Enfermedades Oculares
 */

// ─── Modo de desarrollo ──────────────────────────────────────
define('APP_ENV', 'development'); // development | production
define('APP_DEBUG', true);

// ─── Información de la aplicación ────────────────────────────
define('APP_NAME', 'Eye Disease AI');
define('APP_VERSION', '1.0.0');

// ─── URL base dinámica ────────────────────────────────────────
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$baseUrl = '';
if (strpos($scriptName, '/index.php') !== false) {
    $baseUrl = str_replace('/index.php', '', $scriptName);
} else {
    $baseUrl = '/eye_ai_web'; // Fallback estándar
}
define('BASE_URL', $baseUrl);

// ─── Rutas del sistema ──────────────────────────────────────
define('ROOT_PATH', dirname(__DIR__));
define('ASSETS_PATH', ROOT_PATH . '/assets');
define('UPLOAD_PATH', ROOT_PATH . '/assets/uploads');
define('VIEWS_PATH', ROOT_PATH . '/views');
define('CONTROLLERS_PATH', ROOT_PATH . '/controllers');
define('MODELS_PATH', ROOT_PATH . '/models');

// ─── Configuración de uploads ────────────────────────────────
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10 MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'bmp', 'webp']);
define('ALLOWED_MIME_TYPES', [
    'image/jpeg', 'image/png', 'image/bmp', 'image/webp'
]);

// ─── Configuración de IA / Python (Microservicio API REST FastAPI) ─────────
define('AI_SERVICE_URL', 'http://127.0.0.1:8000');
define('AI_SERVICE_TIMEOUT', 30); // Tiempo límite en segundos para peticiones HTTP
define('PYTHON_PATH', 'D:\\MODELO_EYES\\eye_disease_ai\\venv\\Scripts\\python.exe');
define('AI_PROJECT_PATH', 'D:\\MODELO_EYES\\eye_disease_ai');
define('PREDICT_SCRIPT', ROOT_PATH . '/python_ai/fastapi_server.py');


// Modelos disponibles
define('AI_MODELS', [
    'resnet50' => [
        'name'    => 'ResNet50',
        'weights' => AI_PROJECT_PATH . '\\models\\resnet\\resnet50_eye_disease_best.pth',
        'classes' => AI_PROJECT_PATH . '\\models\\resnet\\resnet50_classes.json',
    ],
    'efficientnet' => [
        'name'    => 'EfficientNetV2',
        'weights' => AI_PROJECT_PATH . '\\models\\efficientnet\\efficientnet_v2_best.pth',
        'classes' => AI_PROJECT_PATH . '\\models\\efficientnet\\efficientnet_classes.json',
    ],
    'densenet' => [
        'name'    => 'DenseNet121',
        'weights' => AI_PROJECT_PATH . '\\models\\densenet\\densenet_best.pth',
        'classes' => AI_PROJECT_PATH . '\\models\\densenet\\densenet_classes.json',
    ],
    'sunet' => [
        'name'    => 'Swin Transformer (SUNet)',
        'weights' => AI_PROJECT_PATH . '\\models\\sunet\\sunet_best.pth',
        'classes' => AI_PROJECT_PATH . '\\models\\sunet\\sunet_classes.json',
    ],
    'yolov8' => [
        'name'    => 'YOLOv8',
        'weights' => AI_PROJECT_PATH . '\\models\\yolo\\yolov8_eye_disease_best.pt',
    ],
    'yolo11' => [
        'name'    => 'YOLOv11',
        'weights' => AI_PROJECT_PATH . '\\models\\yolo11\\yolo11_eye_disease_best.pt',
    ],
]);

// ─── Clases de enfermedades y Datos Clínicos ──────────────────
define('DISEASE_CLASSES', [
    'cataract' => [
        'label'       => 'Catarata',
        'description' => 'Opacidad del cristalino del ojo que normalmente es transparente, lo que causa visión borrosa y disminución de la agudeza visual.',
        'urgency'     => 'Media',
        'severity'    => 'Moderada',
        'advice'      => 'Consulte a un oftalmólogo para evaluar el nivel de opacidad. El tratamiento no siempre es inmediato a menos que afecte significativamente la calidad de vida.',
        'treatment'   => 'Cirugía ambulatoria para extraer el cristalino opaco y reemplazarlo por un lente intraocular artificial (facoemulsificación).',
        'symptoms'    => 'Visión borrosa o nublada, dificultad con la visión nocturna, sensibilidad a la luz y resplandores, necesidad de luz más brillante para leer.',
        'prevention'  => 'Uso regular de gafas de sol con protección UV, dejar de fumar, controlar el azúcar en sangre y seguir una dieta rica en antioxidantes.',
        'specialist'  => 'Oftalmólogo Cirujano de Segmento Anterior',
        'follow_up'   => 'Control anual o semestral, dependiendo de la progresión.',
        'risk_level'  => 'RIESGO MODERADO',
        'color'       => '#3b82f6', // blue
        'icon'        => 'bi-eye-slash',
        'badge'       => 'bg-primary',
        'warning'     => 'La cirugía es altamente efectiva y segura. No deje que progrese hasta la ceguera.'
    ],
    'diabetic_retinopathy' => [
        'label'       => 'Retinopatía Diabética',
        'description' => 'Complicación de la diabetes que daña los vasos sanguíneos del tejido sensible a la luz en la parte posterior del ojo (retina).',
        'urgency'     => 'Alta',
        'severity'    => 'Grave',
        'advice'      => 'Debe acudir con urgencia al oftalmólogo y a su endocrinólogo. El control del azúcar en sangre es crítico e inmediato.',
        'treatment'   => 'Control estricto de la diabetes, terapia con láser (fotocoagulación), inyecciones intravítreas (anti-VEGF) o vitrectomía en casos severos.',
        'symptoms'    => 'Manchas o hebras oscuras flotando en la visión (moscas volantes), visión fluctuante, áreas de visión oscuras o vacías, pérdida de visión.',
        'prevention'  => 'Control riguroso de la glucosa en sangre, presión arterial y colesterol. Examen de fondo de ojo dilatado al menos una vez al año.',
        'specialist'  => 'Retinólogo / Endocrinólogo',
        'follow_up'   => 'Seguimiento cada 3 a 6 meses según la severidad.',
        'risk_level'  => 'ALTO RIESGO',
        'color'       => '#ef4444', // red
        'icon'        => 'bi-droplet-half',
        'badge'       => 'bg-danger',
        'warning'     => 'Si no se trata a tiempo, la retinopatía diabética puede causar ceguera irreversible.'
    ],
    'glaucoma' => [
        'label'       => 'Glaucoma',
        'description' => 'Grupo de afecciones oculares que dañan el nervio óptico, a menudo asociadas con una presión intraocular anormalmente alta.',
        'urgency'     => 'Urgencia Médica',
        'severity'    => 'Muy Grave',
        'advice'      => 'Acuda a un especialista inmediatamente. El daño al nervio óptico es irreversible, pero el progreso se puede detener con medicación rápida.',
        'treatment'   => 'Gotas oftalmológicas diarias para reducir la presión intraocular, medicamentos orales, tratamiento con láser (trabeculoplastia) o microcirugía.',
        'symptoms'    => 'A menudo asintomático en etapas tempranas. Luego: pérdida de visión periférica, halos alrededor de las luces, dolor ocular severo y náuseas (en caso agudo).',
        'prevention'  => 'Exámenes oftalmológicos regulares, especialmente si tiene antecedentes familiares, presión arterial alta o es mayor de 40 años.',
        'specialist'  => 'Oftalmólogo Especialista en Glaucoma',
        'follow_up'   => 'Monitoreo frecuente de la presión intraocular (cada 1-3 meses).',
        'risk_level'  => 'URGENCIA MÉDICA',
        'color'       => '#8b5cf6', // purple
        'icon'        => 'bi-bullseye',
        'badge'       => 'bg-purple', // custom class needed in css
        'warning'     => '¡El Glaucoma es conocido como "el ladrón silencioso de la vista"! El tratamiento diario es estricto y de por vida.'
    ],
    'normal' => [
        'label'       => 'Normal (Sano)',
        'description' => 'No se han detectado anomalías significativas en la retina o el fondo de ojo. El globo ocular parece estar en condiciones saludables.',
        'urgency'     => 'Baja',
        'severity'    => 'Ninguna',
        'advice'      => '¡Excelente noticia! Mantenga sus buenos hábitos. Un resultado de IA normal es un buen indicador, pero no reemplaza un chequeo clínico.',
        'treatment'   => 'Ninguno requerido. Mantener hábitos de higiene visual.',
        'symptoms'    => 'Visión clara y sin molestias crónicas.',
        'prevention'  => 'Mantenga una dieta rica en Vitamina A y Omega-3, use protección UV, aplique la regla 20-20-20 (descansos visuales frente a pantallas).',
        'specialist'  => 'Oftalmólogo General',
        'follow_up'   => 'Revisión preventiva rutinaria cada 1 o 2 años.',
        'risk_level'  => 'NORMAL',
        'color'       => '#10b981', // emerald green
        'icon'        => 'bi-check-circle-fill',
        'badge'       => 'bg-success',
        'warning'     => 'Recuerde que el análisis por IA es complementario. Si presenta dolor ocular, pérdida súbita de visión o destellos, acuda a urgencias.'
    ],
    'retina_disease' => [
        'label'       => 'Enfermedad Retiniana',
        'description' => 'Anomalía inespecífica en la retina (como degeneración macular, desgarros, u oclusiones venosas) que afecta el tejido sensible a la luz.',
        'urgency'     => 'Alta',
        'severity'    => 'Grave',
        'advice'      => 'Se requiere un diagnóstico diferencial urgente. Las enfermedades de la retina pueden avanzar rápidamente y poner en riesgo la visión central.',
        'treatment'   => 'Depende de la patología exacta. Puede incluir inyecciones antiangiogénicas (anti-VEGF), terapia fotodinámica láser o cirugía vitreorretiniana.',
        'symptoms'    => 'Visión central distorsionada o borrosa (las líneas rectas se ven onduladas), punto ciego en el centro del campo visual, destellos de luz repentinos.',
        'prevention'  => 'Controlar presión arterial, evitar el tabaquismo, usar gafas de sol y consultar rápidamente ante síntomas visuales repentinos (moscas o destellos).',
        'specialist'  => 'Retinólogo (Especialista en Retina y Vítreo)',
        'follow_up'   => 'Evaluación urgente para determinar el diagnóstico específico.',
        'risk_level'  => 'ALTO RIESGO',
        'color'       => '#f59e0b', // amber
        'icon'        => 'bi-exclamation-octagon-fill',
        'badge'       => 'bg-warning text-dark',
        'warning'     => '¡Importante! No se frote los ojos ni realice esfuerzos físicos fuertes hasta ser evaluado por un retinólogo.'
    ],
]);

// ─── Zona horaria ────────────────────────────────────────────
date_default_timezone_set('America/Bogota');

// ─── Errores ─────────────────────────────────────────────────
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}
