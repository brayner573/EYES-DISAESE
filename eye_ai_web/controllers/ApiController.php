<?php
/**
 * ApiController.php — Controlador de la API para la Aplicación Móvil
 * Permite autenticación, predicciones con IA y consulta de historial.
 */

class ApiController {
    private User $userModel;
    private Prediction $predModel;

    public function __construct() {
        $this->userModel = new User();
        $this->predModel = new Prediction();
        
        // Configurar CORS
        $this->setHeaders();
        
        // Manejar autenticación por cabecera Authorization (si se provee)
        $this->initApiSession();
    }

    /**
     * Configurar cabeceras de API y CORS
     */
    private function setHeaders() {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Content-Type: application/json; charset=utf-8');

        // Manejar petición preflight de CORS OPTIONS
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }

    /**
     * Inicializar sesión mediante Bearer Token (Session ID)
     */
    private function initApiSession() {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        
        if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            $sessionId = $matches[1];
            if (session_status() === PHP_SESSION_NONE) {
                session_id($sessionId);
                session_name('EYE_AI_SESSION');
                session_start();
            }
        } else {
            // Inicializar sesión por cookie normal si la cabecera no está presente (compatibilidad)
            if (session_status() === PHP_SESSION_NONE) {
                session_name('EYE_AI_SESSION');
                session_start();
            }
        }
    }

    /**
     * Verificar si el usuario está autenticado
     */
    private function checkAuth() {
        if (!isLoggedIn()) {
            http_response_code(401);
            echo json_encode([
                'status' => 'error',
                'message' => 'No autorizado. Por favor inicie sesión.'
            ]);
            exit;
        }
    }

    /**
     * Procesar login desde la app móvil
     */
    public function login() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['status' => 'error', 'message' => 'Método no permitido.']);
            return;
        }

        // Obtener datos JSON o POST normal
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        $email    = sanitize($data['email'] ?? '');
        $password = $data['password'] ?? '';

        if (empty($email) || empty($password)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Completa todos los campos.']);
            return;
        }

        $user = $this->userModel->findByEmail($email);

        if (!$user || !$this->userModel->verifyPassword($password, $user['password_hash'])) {
            $this->userModel->logActivity(null, 'API_LOGIN_FAILED', "Email: $email");
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Credenciales incorrectas.']);
            return;
        }

        // Iniciar sesión y regenerar ID
        if (session_status() === PHP_SESSION_NONE) {
            session_name('EYE_AI_SESSION');
            session_start();
        }
        session_regenerate_id(true);

        $_SESSION['user_id']    = $user['id'];
        $_SESSION['user_name']  = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['role']       = $user['role'];
        $_SESSION['_last_regeneration'] = time();

        $this->userModel->logActivity($user['id'], 'API_LOGIN_SUCCESS');

        echo json_encode([
            'status' => 'success',
            'message' => 'Autenticación exitosa',
            'token' => session_id(), // Token de portador (Bearer) para llamadas subsiguientes
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role'],
                'avatar' => $user['avatar']
            ]
        ]);
    }

    /**
     * Registro de usuario desde la app móvil
     */
    public function register() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['status' => 'error', 'message' => 'Método no permitido.']);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        $name     = sanitize($data['name'] ?? '');
        $email    = sanitize($data['email'] ?? '');
        $password = $data['password'] ?? '';
        $confirm  = $data['password_confirm'] ?? '';

        $errors = [];
        if (strlen($name) < 2)                    $errors[] = 'El nombre debe tener al menos 2 caracteres.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email inválido.';
        if (strlen($password) < 6)                 $errors[] = 'La contraseña debe tener al menos 6 caracteres.';
        if ($password !== $confirm)                $errors[] = 'Las contraseñas no coinciden.';
        if ($this->userModel->emailExists($email)) $errors[] = 'Este email ya está registrado.';

        if (!empty($errors)) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error', 
                'errors' => $errors, 
                'message' => implode(' ', $errors)
            ]);
            return;
        }

        if ($this->userModel->register($name, $email, $password)) {
            $this->userModel->logActivity(null, 'API_REGISTER', "Email: $email");
            echo json_encode([
                'status' => 'success',
                'message' => 'Registro exitoso. Ya puede iniciar sesión.'
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Error al registrar el usuario.']);
        }
    }

    /**
     * Procesar predicción enviada desde el móvil
     */
    public function predict() {
        $this->checkAuth();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['status' => 'error', 'message' => 'Método no permitido.']);
            return;
        }

        $modelKey = sanitize($_POST['model'] ?? 'resnet50');
        $validKeys = ['resnet50', 'efficientnet', 'densenet', 'sunet', 'yolov8', 'yolo11'];

        if (!in_array($modelKey, $validKeys)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Modelo inválido.']);
            return;
        }

        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'No se subió ninguna imagen o hubo un error al subirla.']);
            return;
        }

        $file = $_FILES['image'];

        if ($file['size'] > MAX_FILE_SIZE) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'La imagen supera el límite de 10 MB.']);
            return;
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ALLOWED_EXTENSIONS)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Formato de imagen no permitido.']);
            return;
        }

        // Guardar imagen en la carpeta del usuario
        $userDir = UPLOAD_PATH . '/' . currentUserId();
        if (!is_dir($userDir)) mkdir($userDir, 0755, true);
        $filename = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $destPath = $userDir . '/' . $filename;
        $relativePath = 'assets/uploads/' . currentUserId() . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Error al guardar la imagen en el servidor.']);
            return;
        }

        // Ejecutar predicción
        $result = $this->runPrediction($destPath, $modelKey);

        if ($result === null || isset($result['error_raw'])) {
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'Error al ejecutar el modelo de IA.',
                'details' => $result['error_raw'] ?? 'Error desconocido en Python.'
            ]);
            return;
        }

        // Guardar predicción en la base de datos
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
            currentUserId(), 'API_PREDICTION',
            "Móvil - Class: {$result['class']}, Conf: {$result['confidence']}%, Model: {$result['model']}"
        );

        // Obtener datos clínicos correspondientes
        $clinicalData = DISEASE_CLASSES[$result['class']] ?? null;

        // Construir URL de la imagen
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $fullImageUrl = $protocol . $host . BASE_URL . '/' . $relativePath;

        echo json_encode([
            'status' => 'success',
            'prediction_id' => $predId,
            'image_path' => $relativePath,
            'image_url' => $fullImageUrl,
            'result' => [
                'class' => $result['class'],
                'confidence' => $result['confidence'],
                'model' => $result['model'],
                'processing_time' => $result['processing_time'],
                'all_predictions' => $result['all_predictions'] ?? []
            ],
            'clinical_data' => $clinicalData
        ]);
    }

    /**
     * Ejecutar script Python de predicción
     */
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
            return ["error_raw" => "Output vacío del comando Python."];
        }

        // Buscar patrón JSON en el output de consola
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

    /**
     * Obtener historial del usuario móvil
     */
    public function history() {
        $this->checkAuth();

        $predictions = $this->predModel->findByUser(currentUserId(), 100);
        
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        $formatted = [];
        foreach ($predictions as $pred) {
            $allPreds = json_decode($pred['all_predictions'], true) ?? [];
            $formatted[] = [
                'id' => $pred['id'],
                'image_path' => $pred['image_path'],
                'image_url' => $protocol . $host . BASE_URL . '/' . $pred['image_path'],
                'image_original' => $pred['image_original'],
                'predicted_class' => $pred['predicted_class'],
                'confidence' => (float)$pred['confidence'],
                'model_used' => $pred['model_used'],
                'all_predictions' => $allPreds,
                'processing_time' => (float)$pred['processing_time'],
                'created_at' => $pred['created_at'],
                'clinical_data' => DISEASE_CLASSES[$pred['predicted_class']] ?? null
            ];
        }

        echo json_encode([
            'status' => 'success',
            'predictions' => $formatted
        ]);
    }

    /**
     * Detalle de un reporte específico
     */
    public function viewPrediction(int $id) {
        $this->checkAuth();

        $pred = $this->predModel->findById($id);

        if (!$pred || (int)$pred['user_id'] !== (int)currentUserId()) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Reporte no encontrado o sin permisos.']);
            return;
        }

        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $allPreds = json_decode($pred['all_predictions'], true) ?? [];

        echo json_encode([
            'status' => 'success',
            'prediction' => [
                'id' => $pred['id'],
                'image_path' => $pred['image_path'],
                'image_url' => $protocol . $host . BASE_URL . '/' . $pred['image_path'],
                'image_original' => $pred['image_original'],
                'predicted_class' => $pred['predicted_class'],
                'confidence' => (float)$pred['confidence'],
                'model_used' => $pred['model_used'],
                'all_predictions' => $allPreds,
                'processing_time' => (float)$pred['processing_time'],
                'created_at' => $pred['created_at'],
                'clinical_data' => DISEASE_CLASSES[$pred['predicted_class']] ?? null
            ]
        ]);
    }
}
