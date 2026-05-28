<?php
/**
 * 404.php — Página de Error
 */
$pageTitle = 'No encontrado';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 — Eye Disease AI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body class="auth-page">
    <div class="text-center" style="padding-top:15vh">
        <i class="bi bi-eye-slash display-1 text-primary"></i>
        <h1 class="mt-3 text-white">404</h1>
        <p class="text-light opacity-75">Página no encontrada</p>
        <a href="<?= BASE_URL ?>/auth/login" class="btn btn-primary mt-3">
            <i class="bi bi-house-fill me-1"></i> Volver al inicio
        </a>
    </div>
</body>
</html>
