<?php
// ============================================================
// Layout: Encabezado HTML global (header.php)
// Incluido en: todas las vistas autenticadas del sistema.
// Función: Inicia sesión, verifica autenticación y genera
//          el <head> con todos los recursos necesarios.
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirigir al login si no hay sesión activa
if (!isset($_SESSION['usuario'])) {
    header("Location: ../usuarios/login.php");
    exit;
}

$usuario = $_SESSION['usuario'];
$titulo  = $titulo ?? 'Dashboard';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titulo) ?> | VentaNet</title>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        /* Fondo general del sistema */
        body {
            font-family: 'Outfit', 'DM Sans', sans-serif;
            background: #F8F8F8;
            color: #171717;
            margin: 0;
            padding: 0;
        }

        /* Tipografías reutilizables */
        .font-serif-ventanet { font-family: 'DM Serif Display', serif; }
        .font-sans-ventanet  { font-family: 'DM Sans', sans-serif; }
    </style>
</head>
<body class="min-h-screen">
<!-- Contenedor flex principal: sidebar izquierdo + contenido derecho -->
<div class="flex min-h-screen">
