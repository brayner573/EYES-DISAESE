<?php
/**
 * router.php — Router para el servidor integrado de PHP
 * Permite servir archivos estáticos directamente y redirigir el resto al index.php
 */
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false; // Deja que el servidor sirva el archivo estático directamente
}

require_once __DIR__ . '/index.php';
