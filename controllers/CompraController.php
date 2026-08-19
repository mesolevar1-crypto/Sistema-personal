<?php
/**
 * Controlador de Compras
 * BD real: tabla "usuarios", detalle_compra usa id_precio (no id_producto)
 */
session_start();

if (!isset($_SESSION['usuario'])) {
    header("Location: ../views/usuarios/login.php"); exit;
}

require_once __DIR__ . '/../config/databse.php';
require_once __DIR__ . '/../models/compra.php';

$database    = new Database();
$db          = $database->conectar();
$compraModel = new Compra($db);

$accion = $_GET['accion'] ?? '';

switch ($accion) {

    // ── DETALLE (JSON) ───────────────────────────────────────
    case 'detalle':
        $id_compra = intval($_GET['id'] ?? 0);
        $detalle   = $compraModel->obtenerDetalle($id_compra);
        header('Content-Type: application/json');
        echo json_encode($detalle);
        exit;

    // ── REGISTRAR ────────────────────────────────────────────
    case 'registrar':
        $id_usuario   = intval($_SESSION['usuario']['id_usuario'] ?? 0);
        $id_proveedor = intval($_POST['id_proveedor'] ?? 0);
        $ids_precio   = $_POST['id_precio']       ?? [];
        $ids_producto = $_POST['id_producto']     ?? [];
        $cantidades   = $_POST['cantidad']        ?? [];
        $precios      = $_POST['precio_unitario'] ?? [];

        if ($id_usuario === 0) {
            $_SESSION['alert'] = ['icon' => 'error', 'title' => 'Sin sesión',
                'text' => 'Tu sesión expiró.'];
            header("Location: ../views/compra/index.php"); exit;
        }
        if ($id_proveedor === 0) {
            $_SESSION['alert'] = ['icon' => 'warning', 'title' => 'Proveedor requerido',
                'text' => 'Debes seleccionar un proveedor.'];
            header("Location: ../views/compra/index.php"); exit;
        }
        if (empty($ids_precio)) {
            $_SESSION['alert'] = ['icon' => 'warning', 'title' => 'Sin productos',
                'text' => 'Debes agregar al menos un producto.'];
            header("Location: ../views/compra/index.php"); exit;
        }

        $items = [];
        foreach ($ids_precio as $i => $id_precio) {
            $id_precio  = intval($id_precio);
            $id_prod    = intval($ids_producto[$i] ?? 0);
            $cantidad   = intval($cantidades[$i]   ?? 0);

            if ($id_precio === 0 || $id_prod === 0) continue;

            if ($cantidad <= 0) {
                $_SESSION['alert'] = ['icon' => 'warning', 'title' => 'Cantidad inválida',
                    'text' => 'La cantidad debe ser mayor a 0.'];
                header("Location: ../views/compra/index.php"); exit;
            }

            // Obtener precio real desde BD (nunca confiar en el frontend)
            $stmtPP = $db->prepare(
                "SELECT precio_compra FROM productos_precio WHERE id_precio = :id LIMIT 1"
            );
            $stmtPP->bindParam(':id', $id_precio);
            $stmtPP->execute();
            $pp = $stmtPP->fetch(PDO::FETCH_ASSOC);

            if (!$pp) {
                $_SESSION['alert'] = ['icon' => 'error', 'title' => 'Precio inválido',
                    'text' => 'Un producto seleccionado no tiene precio registrado.'];
                header("Location: ../views/compra/index.php"); exit;
            }

            $precio_u = floatval($pp['precio_compra']);
            $subtotal = round($precio_u * $cantidad, 2);

            $items[] = [
                'id_precio'      => $id_precio,
                'id_producto'    => $id_prod,
                'cantidad'       => $cantidad,
                'precio_unitario'=> $precio_u,
                'subtotal'       => $subtotal,
            ];
        }

        if (empty($items)) {
            $_SESSION['alert'] = ['icon' => 'warning', 'title' => 'Sin productos válidos',
                'text' => 'Debes agregar al menos un producto.'];
            header("Location: ../views/compra/index.php"); exit;
        }

        $resultado = $compraModel->registrar($id_usuario, $id_proveedor, $items);

        $_SESSION['alert'] = $resultado === true
            ? ['icon' => 'success', 'title' => '¡Compra registrada!',
               'text' => 'La compra fue guardada correctamente.']
            : ['icon' => 'error',   'title' => 'Error',
               'text' => 'No fue posible registrar la compra. Intenta nuevamente.'];
        header("Location: ../views/compra/index.php"); exit;

    // ── ELIMINAR ─────────────────────────────────────────────
    case 'eliminar':
        $id_compra = intval($_GET['id'] ?? 0);
        if ($id_compra === 0) {
            $_SESSION['alert'] = ['icon' => 'error', 'title' => 'Error', 'text' => 'Compra no válida.'];
            header("Location: ../views/compra/index.php"); exit;
        }
        $resultado = $compraModel->eliminar($id_compra);
        $_SESSION['alert'] = $resultado === true
            ? ['icon' => 'success', 'title' => 'Compra eliminada', 'text' => 'La compra fue eliminada.']
            : ['icon' => 'error',   'title' => 'Error',            'text' => $resultado];
        header("Location: ../views/compra/index.php"); exit;

    default:
        header("Location: ../views/compra/index.php"); exit;
}
?>
