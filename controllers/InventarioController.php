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

switch ($accion) {

    // ── Ajuste manual de stock ──────────────────────────────
    case 'actualizar':
        $id_producto  = intval($_POST['id_producto']  ?? 0);
        $stock_actual = $_POST['stock_actual'] ?? '';
        $stock_minimo = $_POST['stock_minimo'] ?? '';

        // Validar id
        if ($id_producto === 0) {
            $_SESSION['alert'] = ['icon' => 'error', 'title' => 'Error',
                'text' => 'Producto no válido.'];
            header("Location: ../views/inventario/index.php"); exit;
        }

        // Validar stock_actual
        if ($stock_actual === '' || !is_numeric($stock_actual)) {
            $_SESSION['alert'] = ['icon' => 'warning', 'title' => 'Valor inválido',
                'text' => 'Ingresa un valor válido para el stock.'];
            header("Location: ../views/inventario/index.php"); exit;
        }
        if (intval($stock_actual) < 0) {
            $_SESSION['alert'] = ['icon' => 'warning', 'title' => 'Valor inválido',
                'text' => 'El stock no puede ser negativo.'];
            header("Location: ../views/inventario/index.php"); exit;
        }

        // Validar stock_minimo
        if ($stock_minimo === '' || !is_numeric($stock_minimo)) {
            $_SESSION['alert'] = ['icon' => 'warning', 'title' => 'Valor inválido',
                'text' => 'Ingresa un valor válido para el stock mínimo.'];
            header("Location: ../views/inventario/index.php"); exit;
        }
        if (intval($stock_minimo) < 0) {
            $_SESSION['alert'] = ['icon' => 'warning', 'title' => 'Valor inválido',
                'text' => 'El stock mínimo no puede ser negativo.'];
            header("Location: ../views/inventario/index.php"); exit;
        }

        $resultado = $inventarioModel->actualizarStock(
            $id_producto,
            intval($stock_actual),
            intval($stock_minimo)
        );

        $_SESSION['alert'] = $resultado === true
            ? ['icon' => 'success', 'title' => '¡Actualizado!',
               'text' => 'Inventario actualizado correctamente.']
            : ['icon' => 'error',   'title' => 'Error',
               'text' => 'No se pudo actualizar. Intenta nuevamente.'];

        header("Location: ../views/inventario/index.php");
        exit;

    default:
        header("Location: ../views/inventario/index.php");
        exit;
}
?>
