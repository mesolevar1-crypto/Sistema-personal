<?php
/**
 * Controlador de Ventas
 * BD real: detalle_venta usa id_precio (de productos_precio), no id_producto directamente
 * Stock: inventario.stock_actual
 * Genera factura automáticamente tras cada venta
 */
session_start();

if (!isset($_SESSION['usuario'])) {
    header("Location: ../views/usuarios/login.php"); exit;
}

require_once __DIR__ . '/../config/databse.php';
require_once __DIR__ . '/../models/venta.php';

$database   = new Database();
$db         = $database->conectar();
$ventaModel = new Venta($db);

$accion = $_GET['accion'] ?? '';

switch ($accion) {

    // ── DETALLE (JSON) ───────────────────────────────────────
    case 'detalle':
        $id_venta = intval($_GET['id'] ?? 0);
        $detalle  = $ventaModel->obtenerDetalle($id_venta);
        header('Content-Type: application/json');
        echo json_encode($detalle);
        exit;

    // ── REGISTRAR ────────────────────────────────────────────
    case 'registrar':
        $id_usuario  = intval($_SESSION['usuario']['id_usuario'] ?? 0);
        $id_cliente  = intval($_POST['id_cliente']  ?? 0);
        $metodo_pago = trim($_POST['metodo_pago']   ?? 'efectivo');
        $ids_precio  = $_POST['id_precio']          ?? [];
        $ids_prod    = $_POST['id_producto']        ?? [];
        $cantidades  = $_POST['cantidad']           ?? [];
        $desc_pct    = $_POST['descuento_pct']      ?? [];
        $desc_val    = $_POST['descuento_val']      ?? [];

        if ($id_usuario === 0) {
            $_SESSION['alert'] = ['icon' => 'error', 'title' => 'Sin sesión',
                'text' => 'Tu sesión expiró.'];
            header("Location: ../views/venta/index.php"); exit;
        }
        if ($id_cliente === 0) {
            $_SESSION['alert'] = ['icon' => 'warning', 'title' => 'Cliente requerido',
                'text' => 'Debes seleccionar un cliente.'];
            header("Location: ../views/venta/index.php"); exit;
        }
        if (empty($ids_precio)) {
            $_SESSION['alert'] = ['icon' => 'warning', 'title' => 'Sin productos',
                'text' => 'Debes agregar al menos un producto.'];
            header("Location: ../views/venta/index.php"); exit;
        }

        $items = [];
        foreach ($ids_precio as $i => $id_precio) {
            $id_precio  = intval($id_precio);
            $id_prod    = intval($ids_prod[$i]  ?? 0);
            $cantidad   = intval($cantidades[$i] ?? 0);
            $dpct       = floatval($desc_pct[$i] ?? 0);

            if ($id_precio === 0 || $id_prod === 0) continue;

            if ($cantidad <= 0) {
                $_SESSION['alert'] = ['icon' => 'warning', 'title' => 'Cantidad inválida',
                    'text' => 'La cantidad debe ser mayor a 0.'];
                header("Location: ../views/venta/index.php"); exit;
            }

            // Obtener precio real desde BD
            $stmtPP = $db->prepare(
                "SELECT pp.precio_venta, pr.nombre AS producto_nombre
                 FROM productos_precio pp
                 INNER JOIN producto pr ON pp.id_producto = pr.id_producto
                 WHERE pp.id_precio = :id LIMIT 1"
            );
            $stmtPP->bindParam(':id', $id_precio);
            $stmtPP->execute();
            $pp = $stmtPP->fetch(PDO::FETCH_ASSOC);

            if (!$pp) {
                $_SESSION['alert'] = ['icon' => 'error', 'title' => 'Precio inválido',
                    'text' => 'Un producto seleccionado no tiene precio registrado.'];
                header("Location: ../views/venta/index.php"); exit;
            }

            // Verificar stock en inventario
            $stmtStk = $db->prepare(
                "SELECT COALESCE(stock_actual, 0) AS stock
                 FROM inventario WHERE id_producto = :id LIMIT 1"
            );
            $stmtStk->bindParam(':id', $id_prod);
            $stmtStk->execute();
            $stk = $stmtStk->fetch(PDO::FETCH_ASSOC);
            $stock = intval($stk['stock'] ?? 0);

            if ($stock === 0) {
                $_SESSION['alert'] = ['icon' => 'error', 'title' => 'Producto agotado',
                    'text' => 'El producto "' . $pp['producto_nombre'] . '" está agotado.'];
                header("Location: ../views/venta/index.php"); exit;
            }
            if ($cantidad > $stock) {
                $_SESSION['alert'] = ['icon' => 'error', 'title' => 'Stock insuficiente',
                    'text' => 'El producto "' . $pp['producto_nombre'] . '" solo tiene ' . $stock . ' unidades disponibles.'];
                header("Location: ../views/venta/index.php"); exit;
            }

            // Calcular en servidor
            $precio_u  = floatval($pp['precio_venta']);
            $dval      = round($precio_u * $cantidad * $dpct / 100, 2);
            $subtotal  = round(($precio_u * $cantidad) - $dval, 2);

            $items[] = [
                'id_precio'           => $id_precio,
                'id_producto'         => $id_prod,
                'cantidad'            => $cantidad,
                'precio_unitario'     => $precio_u,
                'descuento_porcentaje'=> $dpct,
                'descuento_valor'     => $dval,
                'subtotal'            => $subtotal,
            ];
        }

        if (empty($items)) {
            $_SESSION['alert'] = ['icon' => 'warning', 'title' => 'Sin productos válidos',
                'text' => 'Debes agregar al menos un producto.'];
            header("Location: ../views/venta/index.php"); exit;
        }

        $resultado = $ventaModel->registrar($id_usuario, $id_cliente, $metodo_pago, $items);

        if (is_array($resultado)) {
            $_SESSION['alert'] = ['icon' => 'success', 'title' => '¡Venta registrada!',
                'text' => 'Venta guardada. Factura: ' . $resultado['numero_factura']];
        } else {
            $_SESSION['alert'] = ['icon' => 'error', 'title' => 'Error',
                'text' => 'No fue posible registrar la venta. Intenta nuevamente.'];
        }
        header("Location: ../views/venta/index.php"); exit;

    // ── ELIMINAR ─────────────────────────────────────────────
    case 'eliminar':
        $id_venta = intval($_GET['id'] ?? 0);
        if ($id_venta === 0) {
            $_SESSION['alert'] = ['icon' => 'error', 'title' => 'Error', 'text' => 'Venta no válida.'];
            header("Location: ../views/venta/index.php"); exit;
        }
        $resultado = $ventaModel->eliminar($id_venta);
        $_SESSION['alert'] = $resultado === true
            ? ['icon' => 'success', 'title' => 'Venta eliminada', 'text' => 'La venta fue eliminada.']
            : ['icon' => 'error',   'title' => 'Error',           'text' => $resultado];
        header("Location: ../views/venta/index.php"); exit;

    default:
        header("Location: ../views/venta/index.php"); exit;
}
?>
