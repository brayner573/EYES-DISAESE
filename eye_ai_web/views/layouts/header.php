<?php
/**
 * header.php — Layout Header
 * Navbar + inicio de body + CSS
 */
$flash = getFlash();
$pageTitle = $pageTitle ?? 'Eye Disease AI';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sistema de Detección de Enfermedades Oculares con IA">
    <title><?= sanitize($pageTitle) ?> — Eye Disease AI</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body class="<?= isLoggedIn() ? 'has-sidebar' : '' ?>">

<?php if (isLoggedIn()): ?>
<!-- ─── Sidebar ───────────────────────────────────────────────── -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <i class="bi bi-eye-fill"></i>
        <span>Eye Disease AI</span>
    </div>

    <nav class="sidebar-nav">
        <?php if (isAdmin()): ?>
        <div class="nav-section">ADMINISTRACIÓN</div>
        <a href="<?= BASE_URL ?>/admin/dashboard" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/admin/dashboard') !== false ? 'active' : '' ?>">
            <i class="bi bi-grid-1x2-fill"></i> Dashboard
        </a>
        <a href="<?= BASE_URL ?>/admin/users" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/admin/users') !== false ? 'active' : '' ?>">
            <i class="bi bi-people-fill"></i> Usuarios
        </a>
        <a href="<?= BASE_URL ?>/admin/predictions" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/admin/predictions') !== false ? 'active' : '' ?>">
            <i class="bi bi-clipboard2-data-fill"></i> Predicciones
        </a>
        <?php endif; ?>

        <div class="nav-section">PRINCIPAL</div>
        <a href="<?= BASE_URL ?>/user/dashboard" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/user/dashboard') !== false ? 'active' : '' ?>">
            <i class="bi bi-house-fill"></i> Mi Panel
        </a>
        <a href="<?= BASE_URL ?>/prediction/form" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/prediction/form') !== false ? 'active' : '' ?>">
            <i class="bi bi-upload"></i> Nueva Predicción
        </a>
        <a href="<?= BASE_URL ?>/user/history" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/user/history') !== false ? 'active' : '' ?>">
            <i class="bi bi-clock-history"></i> Historial
        </a>

        <div class="nav-section">CUENTA</div>
        <a href="<?= BASE_URL ?>/user/profile" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/user/profile') !== false ? 'active' : '' ?>">
            <i class="bi bi-person-circle"></i> Mi Perfil
        </a>
        <a href="<?= BASE_URL ?>/auth/logout" class="nav-link nav-logout">
            <i class="bi bi-box-arrow-left"></i> Cerrar Sesión
        </a>
    </nav>

    <div class="sidebar-footer">
        <small><?= sanitize(currentUserName()) ?></small>
        <span class="badge bg-<?= isAdmin() ? 'danger' : 'primary' ?>"><?= currentUserRole() ?></span>
    </div>
</aside>

<!-- ─── Topbar Mobile ─────────────────────────────────────────── -->
<nav class="topbar d-lg-none">
    <button class="btn btn-link text-white" onclick="toggleSidebar()">
        <i class="bi bi-list fs-4"></i>
    </button>
    <span class="topbar-brand">Eye Disease AI</span>
    <a href="<?= BASE_URL ?>/auth/logout" class="btn btn-link text-white">
        <i class="bi bi-box-arrow-left"></i>
    </a>
</nav>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
<?php endif; ?>

<!-- ─── Main Content ──────────────────────────────────────────── -->
<main class="main-content <?= !isLoggedIn() ? 'full-width' : '' ?>">

<?php if ($flash): ?>
<div class="container-fluid px-4 pt-3">
    <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?> alert-dismissible fade show animate-fade-in" role="alert">
        <i class="bi bi-<?= $flash['type'] === 'error' ? 'exclamation-circle' : ($flash['type'] === 'success' ? 'check-circle' : 'info-circle') ?>-fill me-2"></i>
        <?= $flash['message'] ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
</div>
<?php endif; ?>
