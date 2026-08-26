<?php
/**
 * Controlador de Inventario
 * Acción: actualizar (ajuste manual de stock_actual y stock_minimo)
 */
session_start();

if (!isset($_SESSION['usuario'])) {
    header("Location: ../views/usuarios/login.php");
    exit;
}

require_once __DIR__ . '/../config/databse.php';
require_once __DIR__ . '/../models/inventario.php';

$database        = new Database();
$db              = $database->conectar();
$inventarioModel = new Inventario($db);

$accion = $_GET['accion'] ?? '';

// ============================================================
// RUTAS DE REGRESO SEGÚN EL ROL
// ============================================================
const VISTA_INVENTARIO_ADMIN    = '../views/inventario/index.php';
const VISTA_INVENTARIO_VENDEDOR = '../views/vendedor/inventario.php';

// ============================================================
// FUNCIÓN PARA MOSTRAR ALERTA Y REGRESAR
// (admin vuelve a su panel, vendedor vuelve al suyo, según
// el rol guardado en la sesión — no depende del Referer del
// navegador, que puede venir vacío por políticas de privacidad)
// ============================================================
function regresarConAlerta($icon, $title, $text)
{
    $_SESSION['alert'] = ['icon' => $icon, 'title' => $title, 'text' => $text];

    $rol = strtolower(trim($_SESSION['usuario']['rol'] ?? ''));

    $destino = $rol === 'vendedor'
        ? VISTA_INVENTARIO_VENDEDOR
        : VISTA_INVENTARIO_ADMIN;

    header("Location: " . $destino);

    exit;
}

switch ($accion) {

    // ── Ajuste manual de stock ──────────────────────────────
    case 'actualizar':
        $id_producto  = intval($_POST['id_producto']  ?? 0);
        $stock_actual = $_POST['stock_actual'] ?? '';
        $stock_minimo = $_POST['stock_minimo'] ?? '';

        // Validar id
        if ($id_producto === 0) {
            regresarConAlerta('error', 'Error', 'Producto no válido.');
        }

        // Validar stock_actual
        if ($stock_actual === '' || !is_numeric($stock_actual)) {
            regresarConAlerta('warning', 'Valor inválido', 'Ingresa un valor válido para el stock.');
        }
        if (intval($stock_actual) < 0) {
            regresarConAlerta('warning', 'Valor inválido', 'El stock no puede ser negativo.');
        }

        // Validar stock_minimo
        if ($stock_minimo === '' || !is_numeric($stock_minimo)) {
            regresarConAlerta('warning', 'Valor inválido', 'Ingresa un valor válido para el stock mínimo.');
        }
        if (intval($stock_minimo) < 0) {
            regresarConAlerta('warning', 'Valor inválido', 'El stock mínimo no puede ser negativo.');
        }

        $resultado = $inventarioModel->actualizarStock(
            $id_producto,
            intval($stock_actual),
            intval($stock_minimo)
        );

        if ($resultado === true) {
            regresarConAlerta('success', '¡Actualizado!', 'Inventario actualizado correctamente.');
        }

        regresarConAlerta('error', 'Error', 'No se pudo actualizar. Intenta nuevamente.');

    default:
        regresarConAlerta('error', 'Acción no válida', 'La acción solicitada no existe.');
}
?>