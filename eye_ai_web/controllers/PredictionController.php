<?php
/**
 * PredictionController.php — Controlador de Predicciones IA
 * Soporta análisis con un modelo individual o comparativa entre ambos modelos.
 */

class PredictionController {
    private Prediction $predModel;
    private User $userModel;

    public function __construct() {
        $this->predModel = new Prediction();
        $this->userModel = new User();
    }

    /** Formulario de predicción */
    public function form() {
        requireLogin();
        $data = ['models' => AI_MODELS];
        require VIEWS_PATH . '/user/predict.php';
    }

    /** Procesar predicción */
    public function predict() {
        requireLogin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/prediction/form');
        if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
            setFlash('error', 'Token inválido.'); redirect('/prediction/form');
        }

        // Detectar si es modo comparativo o individual
        $modelKey = sanitize($_POST['model'] ?? 'resnet50');
        $validKeys = ['resnet50', 'efficientnet', 'densenet', 'sunet', 'yolov8', 'yolo11'];
        $compareMode = ($modelKey === 'compare_both' || $modelKey === 'compare_all');

        if (!$compareMode && !in_array($modelKey, $validKeys)) {
            setFlash('error', 'Modelo inválido.'); redirect('/prediction/form');
        }

        // Validar archivo
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            $errorMsg = $this->getUploadError($_FILES['image']['error'] ?? -1);
            setFlash('error', 'Error al subir imagen: ' . $errorMsg);
            redirect('/prediction/form');
        }

        $file = $_FILES['image'];

        if ($file['size'] > MAX_FILE_SIZE) {
            setFlash('error', 'La imagen supera el límite de 10 MB.');
            redirect('/prediction/form');
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ALLOWED_EXTENSIONS)) {
            setFlash('error', 'Formato no permitido. Usa: JPG, PNG, BMP, WEBP.');
            redirect('/prediction/form');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        if (!in_array($mimeType, ALLOWED_MIME_TYPES)) {
            setFlash('error', 'El archivo no es una imagen válida.');
            redirect('/prediction/form');
        }

        $imageInfo = @getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            setFlash('error', 'El archivo no es una imagen válida.');
            redirect('/prediction/form');
        }

        // Guardar imagen
        $userDir = UPLOAD_PATH . '/' . currentUserId();
        if (!is_dir($userDir)) mkdir($userDir, 0755, true);
        $filename = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $destPath = $userDir . '/' . $filename;
        $relativePath = 'assets/uploads/' . currentUserId() . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            setFlash('error', 'Error al guardar la imagen.');
            redirect('/prediction/form');
        }

        // ─── MODO COMPARAR TODOS LOS MODELOS (6 MODELOS) ──────────────────
        if ($modelKey === 'compare_all') {
            $modelsToRun = [
                'resnet50'     => 'ResNet50',
                'efficientnet' => 'EfficientNetV2',
                'densenet'     => 'DenseNet121',
                'sunet'        => 'Swin Transformer (SUNet)',
                'yolov8'       => 'YOLOv8',
                'yolo11'       => 'YOLOv11'
            ];
            $results = [];
            $totalTime = 0;
            $bestResult = null;

            foreach ($modelsToRun as $key => $name) {
                $res = $this->runPrediction($destPath, $key);
                if ($res === null || isset($res['error_raw'])) {
                    $results[$key] = [
                        'error' => $res['error_raw'] ?? "$name no disponible (pesos no encontrados).",
                        'model' => $name
                    ];
                } else {
                    $results[$key] = $res;
                    $totalTime += $res['processing_time'] ?? 0;
                    if ($bestResult === null || (!isset($res['error']) && $res['confidence'] > $bestResult['confidence'])) {
                        $bestResult = $res;
                    }
                }
            }

            // Guardar en la BD el resultado con mayor confianza
            $predId = null;
            if ($bestResult && !isset($bestResult['error'])) {
                $predId = $this->predModel->create([
                    'user_id'         => currentUserId(),
                    'image_path'      => $relativePath,
                    'image_original'  => sanitize($file['name']),
                    'predicted_class' => $bestResult['class'],
                    'confidence'      => $bestResult['confidence'],
                    'model_used'      => 'COMPARE: Todos los Modelos',
                    'all_predictions' => json_encode($bestResult['all_predictions'] ?? []),
                    'processing_time' => $totalTime,
                ]);
                $this->userModel->logActivity(
                    currentUserId(), 'PREDICTION_COMPARE_ALL',
                    "Comparativa Completa de Modelos (Mejor: {$bestResult['model']})"
                );
            }

            $data = [
                'compare_all_mode' => true,
                'image_path'       => $relativePath,
                'results'          => $results,
                'prediction'       => $predId ? $this->predModel->findById($predId) : null,
            ];
            require VIEWS_PATH . '/user/result_compare.php';
            return;
        }

        // ─── MODO COMPARAR DOS MODELOS (ResNet50 + YOLOv8) ────────────────
        if ($modelKey === 'compare_both') {
            $resnetResult = $this->runPrediction($destPath, 'resnet50');
            $yoloResult   = $this->runPrediction($destPath, 'yolov8');

            if ($resnetResult === null || isset($resnetResult['error_raw'])) {
                $resnetResult = ['error' => $resnetResult['error_raw'] ?? 'ResNet50 no disponible (modelo no entrenado aún).', 'model' => 'ResNet50'];
            }
            if ($yoloResult === null || isset($yoloResult['error_raw'])) {
                $yoloResult = ['error' => $yoloResult['error_raw'] ?? 'YOLOv8 no disponible (modelo no entrenado aún).', 'model' => 'YOLOv8'];
            }

            // Guardar el mejor resultado
            $primaryResult = null;
            if (!isset($resnetResult['error']) && !isset($yoloResult['error'])) {
                $primaryResult = ($resnetResult['confidence'] >= $yoloResult['confidence']) ? $resnetResult : $yoloResult;
            } elseif (!isset($resnetResult['error'])) {
                $primaryResult = $resnetResult;
            } elseif (!isset($yoloResult['error'])) {
                $primaryResult = $yoloResult;
            }

            $predId = null;
            if ($primaryResult) {
                $predId = $this->predModel->create([
                    'user_id'         => currentUserId(),
                    'image_path'      => $relativePath,
                    'image_original'  => sanitize($file['name']),
                    'predicted_class' => $primaryResult['class'],
                    'confidence'      => $primaryResult['confidence'],
                    'model_used'      => 'COMPARE: ResNet50 + YOLOv8',
                    'all_predictions' => json_encode($primaryResult['all_predictions'] ?? []),
                    'processing_time' => ($resnetResult['processing_time'] ?? 0) + ($yoloResult['processing_time'] ?? 0),
                ]);
                $this->userModel->logActivity(
                    currentUserId(), 'PREDICTION_COMPARE',
                    "Comparativa: ResNet={$resnetResult['class']} YOLOv8={$yoloResult['class']}"
                );
            }

            $data = [
                'compare_mode' => true,
                'image_path'   => $relativePath,
                'resnet'       => $resnetResult,
                'yolo'         => $yoloResult,
                'prediction'   => $predId ? $this->predModel->findById($predId) : null,
            ];
            require VIEWS_PATH . '/user/result_compare.php';
            return;
        }

        // ─── Modo Individual ─────────────────────────────────────────────
        $result = $this->runPrediction($destPath, $modelKey);

        if ($result === null || isset($result['error_raw'])) {
            $errorMsg = $result['error_raw'] ?? 'Error desconocido al ejecutar Python.';
            setFlash('error', 'Error al ejecutar el modelo de IA: <br><pre style="white-space: pre-wrap; font-size: 0.8rem; margin-top: 10px;">' . sanitize($errorMsg) . '</pre>');
            redirect('/prediction/form');
        }

        $predId = $this->predModel->create([
            'user_id'         => currentUserId(),
            'image_path'      => $relativePath,
            'image_original'  => sanitize($file['name']),
            'predicted_class' => $result['class'],
            'confidence'      => $result['confidence'],
            'model_used'      => $result['model'],
            'all_predictions' => json_encode($result['all_predictions'] ?? []),
            'processing_time' => $result['processing_time'] ?? 0,
        ]);

        $this->userModel->logActivity(
            currentUserId(), 'PREDICTION',
            "Class: {$result['class']}, Conf: {$result['confidence']}%, Model: {$result['model']}"
        );

        $data = [
            'prediction' => $this->predModel->findById($predId),
            'result'     => $result,
        ];
        require VIEWS_PATH . '/user/result.php';
    }

    /** Ejecutar script Python para predicción */
    private function runPrediction(string $imagePath, string $modelKey): ?array {
        $pythonPath = PYTHON_PATH;
        $scriptPath = PREDICT_SCRIPT;

        $imagePath = str_replace('/', '\\', $imagePath);
        $cmd = sprintf(
            '%s "%s" "%s" "%s" 2>&1',
            escapeshellarg($pythonPath),
            $scriptPath,
            $imagePath,
            $modelKey
        );

        $startTime = microtime(true);
        $output    = shell_exec($cmd);
        $elapsed   = round((microtime(true) - $startTime) * 1000, 2);

        if (empty($output)) {
            return ["error_raw" => "Output vacío. Comando: " . $cmd];
        }

        if (preg_match('/\{[^{}]*"class"[^{}]*\}/', $output, $matches)) {
            $result = json_decode($matches[0], true);
            if ($result && isset($result['class'])) {
                $result['processing_time'] = $elapsed;
                return $result;
            }
        }

        $result = json_decode(trim($output), true);
        if ($result && isset($result['class'])) {
            $result['processing_time'] = $elapsed;
            return $result;
        }

        return ["error_raw" => "Error Python: " . $output];
    }

    /** Historial del usuario */
    public function history() {
        requireLogin();
        $data = ['predictions' => $this->predModel->findByUser(currentUserId(), 100)];
        require VIEWS_PATH . '/user/history.php';
    }

    /** Ver un reporte pasado */
    public function view(int $id) {
        requireLogin();
        $pred = $this->predModel->findById($id);
        
        if (!$pred || $pred['user_id'] !== currentUserId()) {
            setFlash('error', 'Reporte no encontrado o sin permisos.');
            redirect('/prediction/history');
        }

        // Reconstruir el formato esperado por result.php
        $result = [
            'class'           => $pred['predicted_class'],
            'confidence'      => $pred['confidence'],
            'model'           => $pred['model_used'],
            'all_predictions' => json_decode($pred['all_predictions'], true) ?? [],
            'processing_time' => $pred['processing_time'],
        ];

        $data = [
            'prediction' => $pred,
            'result'     => $result,
        ];

        require VIEWS_PATH . '/user/result.php';
    }

    /** Mensajes de error de upload */
    private function getUploadError(int $code): string {
        return match($code) {
            UPLOAD_ERR_INI_SIZE   => 'Archivo demasiado grande (límite PHP).',
            UPLOAD_ERR_FORM_SIZE  => 'Archivo demasiado grande (límite formulario).',
            UPLOAD_ERR_PARTIAL    => 'Archivo subido parcialmente.',
            UPLOAD_ERR_NO_FILE    => 'No se seleccionó ningún archivo.',
            UPLOAD_ERR_NO_TMP_DIR => 'Carpeta temporal no encontrada.',
            UPLOAD_ERR_CANT_WRITE => 'Error de escritura en disco.',
            default               => 'Error desconocido.',
        };
    }
}
