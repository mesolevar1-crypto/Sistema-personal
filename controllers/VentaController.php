<?php
session_start();

if (!isset($_SESSION['usuario'])) {
    header("Location: ../views/usuarios/login.php");
    exit;
}

require_once __DIR__ . '/../config/databse.php';
require_once __DIR__ . '/../models/venta.php';

$database   = new Database();
$db         = $database->conectar();
$ventaModel = new Venta($db);

$accion = $_GET['accion'] ?? '';

// ============================================================
// RUTAS DE REGRESO SEGÚN EL ROL
// ============================================================
const VISTA_VENTAS_ADMIN    = '../views/venta/index.php';
const VISTA_VENTAS_VENDEDOR = '../views/vendedor/ventas.php';

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
        ? VISTA_VENTAS_VENDEDOR
        : VISTA_VENTAS_ADMIN;

    header("Location: " . $destino);

    exit;
}

switch ($accion) {

    // =========================================================
    // DETALLE DE UNA VENTA (JSON, usado por el modal)
    // =========================================================
    case 'detalle':
        $id_venta = (int)($_GET['id'] ?? 0);
        $detalle  = $ventaModel->obtenerDetalle($id_venta);
        header('Content-Type: application/json');
        echo json_encode($detalle);
        exit;

    // =========================================================
    // REGISTRAR VENTA
    // =========================================================
    case 'registrar':

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            regresarConAlerta('error', 'Solicitud inválida', 'La operación solicitada no es válida.');
        }

        // ----------------------------------------------------
        // EL USUARIO SIEMPRE SE TOMA DE LA SESIÓN, NUNCA DEL FORMULARIO
        // ----------------------------------------------------
        $id_usuario = (int)($_SESSION['usuario']['id_usuario'] ?? 0);

        if ($id_usuario === 0) {
            regresarConAlerta('error', 'Sin sesión', 'Tu sesión expiró, vuelve a iniciar sesión.');
        }

        $id_cliente  = (int)($_POST['id_cliente'] ?? 0);
        $metodo_pago = trim($_POST['metodo_pago'] ?? 'efectivo');

        $ids_producto = $_POST['id_producto']          ?? [];
        $cantidades   = $_POST['cantidad']              ?? [];
        $precios      = $_POST['precio_venta']          ?? [];
        $descPct      = $_POST['descuento_porcentaje']  ?? [];
        $ids_unidad   = $_POST['id_unidad']             ?? [];
        $cantXUnidad  = $_POST['cantidad_por_unidad']   ?? [];
        $ids_undCont  = $_POST['id_unidad_contenido']   ?? [];

        if ($id_cliente === 0) {
            regresarConAlerta('warning', 'Cliente requerido', 'Debes seleccionar un cliente.');
        }

        if (empty($ids_producto)) {
            regresarConAlerta('warning', 'Sin productos', 'Debes agregar al menos un producto.');
        }

        // ----------------------------------------------------
        // ARMAR ITEMS (el controlador solo recolecta, el modelo
        // vuelve a calcular y validar todo — nunca confiamos en
        // subtotales que vengan del navegador)
        // ----------------------------------------------------
        $items = [];

        foreach ($ids_producto as $i => $id_producto) {

            $id_producto = (int)$id_producto;

            if ($id_producto === 0) {
                continue;
            }

            $items[] = [
                'id_producto'          => $id_producto,
                'cantidad'             => (int)($cantidades[$i] ?? 0),
                'precio_venta'         => (float)($precios[$i] ?? 0),
                'descuento_porcentaje' => (float)($descPct[$i] ?? 0),
                'id_unidad'            => !empty($ids_unidad[$i]) ? (int)$ids_unidad[$i] : null,
                'cantidad_por_unidad'  => !empty($cantXUnidad[$i]) ? (int)$cantXUnidad[$i] : 1,
                'id_unidad_contenido'  => !empty($ids_undCont[$i]) ? (int)$ids_undCont[$i] : null,
            ];
        }

        if (empty($items)) {
            regresarConAlerta('warning', 'Sin productos válidos', 'Debes agregar al menos un producto.');
        }

        $resultado = $ventaModel->registrar($id_usuario, $id_cliente, $metodo_pago, $items);

        if (is_array($resultado)) {
            $_SESSION['ultima_venta_id'] = $resultado['id_venta'];
            regresarConAlerta(
                'success',
                '¡Venta registrada!',
                'Venta guardada correctamente. Factura: ' . $resultado['numero_factura']
            );
        }

        regresarConAlerta(
            'error',
            'No se pudo registrar',
            is_string($resultado) ? $resultado : 'No fue posible registrar la venta.'
        );

    // =========================================================
    // ANULAR VENTA
    // =========================================================
    case 'eliminar':

        $id_venta = (int)($_GET['id'] ?? 0);

        if ($id_venta === 0) {
            regresarConAlerta('error', 'Venta inválida', 'No se recibió una venta válida.');
        }

        $resultado = $ventaModel->eliminar($id_venta);

        if ($resultado === true) {
            regresarConAlerta('success', 'Venta anulada', 'La venta fue anulada y el inventario fue restaurado.');
        }

        regresarConAlerta(
            'error',
            'No se pudo anular',
            is_string($resultado) ? $resultado : 'No fue posible anular la venta.'
        );

    // =========================================================
    // REACTIVAR VENTA
    // =========================================================
    case 'reactivar':

        $id_venta = (int)($_GET['id'] ?? 0);

        if ($id_venta === 0) {
            regresarConAlerta('error', 'Venta inválida', 'No se recibió una venta válida.');
        }

        $resultado = $ventaModel->reactivar($id_venta);

        if ($resultado === true) {
            regresarConAlerta('success', 'Venta reactivada', 'La venta fue reactivada y el inventario fue descontado nuevamente.');
        }

        regresarConAlerta(
            'error',
            'No se pudo reactivar',
            is_string($resultado) ? $resultado : 'No fue posible reactivar la venta.'
        );

    // =========================================================
    // ACCIÓN NO RECONOCIDA
    // =========================================================
    default:
        regresarConAlerta('error', 'Acción no válida', 'La acción solicitada no existe.');
}