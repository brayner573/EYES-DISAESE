<?php
/**
 * index.php — Entry Point del Sistema
 * Eye Disease AI — Detección de Enfermedades Oculares
 */

// ─── Cargar configuración ────────────────────────────────────
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/session.php';

// ─── Iniciar sesión segura ───────────────────────────────────
initSession();

// ─── Cargar modelos ──────────────────────────────────────────
require_once __DIR__ . '/models/Database.php';
require_once __DIR__ . '/models/User.php';
require_once __DIR__ . '/models/Prediction.php';

// ─── Cargar controladores ────────────────────────────────────
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/UserController.php';
require_once __DIR__ . '/controllers/AdminController.php';
require_once __DIR__ . '/controllers/PredictionController.php';
require_once __DIR__ . '/controllers/ApiController.php';

// ─── Cargar rutas y resolver ─────────────────────────────────
require_once __DIR__ . '/routes/web.php';
resolveRoute($routes);
