<?php
// ============================================================
// Layout: Cargador dinámico de Sidebar (sidebar_loader.php)
// Incluido en: vistas accesibles por ambos roles
//              (clientes, productos, inventario, ventas, reportes)
// Función: Detecta el rol del usuario en sesión y carga el
//          sidebar correspondiente:
//          - Vendedor  → sidebar_vendedor.php (menú reducido)
//          - Cualquier otro (Administrador) → sidebar.php (menú completo)
// ============================================================

// Obtener el rol del usuario en sesión (en minúsculas para comparación segura)
$rolActual = strtolower($_SESSION['usuario']['rol'] ?? '');

// Cargar el sidebar según el rol detectado
if ($rolActual === 'vendedor') {
    // El vendedor solo ve: Inicio, Ventas, Clientes, Productos, Inventario
    require_once __DIR__ . '/sidebar_vendedor.php';
} else {
    // El administrador ve el menú completo con todos los módulos
    require_once __DIR__ . '/sidebar.php';
}
?>
