<?php

/**
 * ============================================================
 * CONTROLADOR: CompraController.php
 * ============================================================
 *
 * Flujo:
 *
 * VISTA -> CompraController -> Compra (modelo) -> BD
 *
 * La vista solo envía:
 *
 *   id_proveedor
 *   id_precio[]
 *   cantidad[]
 *
 * El controlador NUNCA confía en precios, unidades ni
 * subtotales enviados desde el navegador -- todo eso lo
 * recalcula y verifica el modelo contra la base de datos.
 *
 * CORRECCIÓN: cantidad se castea a ENTERO (no a float), porque
 * detalle_compra.cantidad es INT en la base de datos. Ver nota
 * completa en models/compra.php.
 *
 * ============================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/databse.php';
require_once __DIR__ . '/../models/compra.php';

// ============================================================
// VERIFICAR SESIÓN
// ============================================================

if (!isset($_SESSION['usuario'])) {
    header("Location: ../views/usuarios/login.php");
    exit;
}

// ============================================================
// CONEXIÓN
// ============================================================

try {

    $database = new Database();
    $db = $database->conectar();

    $compraModel = new Compra($db);

} catch (Exception $e) {

    $_SESSION['alert'] = [
        'icon'  => 'error',
        'title' => 'Error de conexión',
        'text'  => 'No fue posible conectar con la base de datos.'
    ];

    header("Location: ../views/compras/compra.php");
    exit;
}

// ============================================================
// ACCIÓN SOLICITADA
// ============================================================

$accion = $_GET['accion'] ?? '';


// ============================================================
// ACCIÓN: REGISTRAR COMPRA
// ============================================================

if ($accion === 'registrar') {

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

        $_SESSION['alert'] = [
            'icon'  => 'warning',
            'title' => 'Solicitud inválida',
            'text'  => 'La compra debe enviarse mediante el formulario.'
        ];

        header("Location: ../views/compras/compra.php");
        exit;
    }

    // --------------------------------------------------------
    // USUARIO DESDE SESIÓN (nunca desde el formulario)
    // --------------------------------------------------------

    $id_usuario =
        $_SESSION['usuario']['id_usuario']
        ?? $_SESSION['usuario']['id']
        ?? 0;

    $id_usuario = (int)$id_usuario;

    if ($id_usuario <= 0) {

        $_SESSION['alert'] = [
            'icon'  => 'error',
            'title' => 'Sesión inválida',
            'text'  => 'No se pudo identificar el usuario actual.'
        ];

        header("Location: ../views/compras/compra.php");
        exit;
    }

    // --------------------------------------------------------
    // PROVEEDOR
    // --------------------------------------------------------

    $id_proveedor = isset($_POST['id_proveedor']) ? (int)$_POST['id_proveedor'] : 0;

    if ($id_proveedor <= 0) {

        $_SESSION['alert'] = [
            'icon'  => 'warning',
            'title' => 'Proveedor requerido',
            'text'  => 'Debes seleccionar un proveedor activo.'
        ];

        header("Location: ../views/compras/compra.php");
        exit;
    }

    // --------------------------------------------------------
    // PRODUCTOS (id_precio[] / cantidad[])
    // --------------------------------------------------------

    $idsPrecio  = $_POST['id_precio'] ?? [];
    $cantidades = $_POST['cantidad'] ?? [];

    if (
        !is_array($idsPrecio) ||
        !is_array($cantidades) ||
        count($idsPrecio) === 0 ||
        count($cantidades) === 0
    ) {

        $_SESSION['alert'] = [
            'icon'  => 'warning',
            'title' => 'Compra vacía',
            'text'  => 'Debes agregar al menos un producto a la compra.'
        ];

        header("Location: ../views/compras/compra.php");
        exit;
    }

    // --------------------------------------------------------
    // CONSTRUIR ITEMS
    //
    // cantidad se castea a ENTERO -- detalle_compra.cantidad es
    // INT en la base de datos. Si el usuario escribe "2.7", se
    // rechaza aquí en vez de dejar que MySQL trunque en
    // silencio más adelante (eso era lo que descuadraba el
    // inventario al eliminar una compra).
    // --------------------------------------------------------

    $items = [];
    $cantidadFilas = max(count($idsPrecio), count($cantidades));

    for ($i = 0; $i < $cantidadFilas; $i++) {

        $idPrecio = isset($idsPrecio[$i]) ? (int)$idsPrecio[$i] : 0;

        $cantidadCruda = isset($cantidades[$i]) ? trim((string)$cantidades[$i]) : '';

        // Ignorar filas completamente vacías
        if ($idPrecio <= 0 && $cantidadCruda === '') {
            continue;
        }

        if ($idPrecio <= 0) {

            $_SESSION['alert'] = [
                'icon'  => 'warning',
                'title' => 'Producto inválido',
                'text'  => 'Hay una fila de la compra sin producto seleccionado.'
            ];

            header("Location: ../views/compras/compra.php");
            exit;
        }

        // Debe ser un entero positivo (no "2.5", no "abc")
        if (!ctype_digit($cantidadCruda) || (int)$cantidadCruda <= 0) {

            $_SESSION['alert'] = [
                'icon'  => 'warning',
                'title' => 'Cantidad inválida',
                'text'  => 'Todas las cantidades deben ser números enteros mayores que cero.'
            ];

            header("Location: ../views/compras/compra.php");
            exit;
        }

        $items[] = [
            'id_precio' => $idPrecio,
            'cantidad'  => (int)$cantidadCruda
        ];
    }

    if (empty($items)) {

        $_SESSION['alert'] = [
            'icon'  => 'warning',
            'title' => 'Compra vacía',
            'text'  => 'Debes seleccionar al menos un producto y especificar su cantidad.'
        ];

        header("Location: ../views/compras/compra.php");
        exit;
    }

    // --------------------------------------------------------
    // REGISTRAR EN EL MODELO
    // --------------------------------------------------------

    $resultado = $compraModel->registrar($id_usuario, $id_proveedor, $items);

    if ($resultado === true) {

        $_SESSION['alert'] = [
            'icon'  => 'success',
            'title' => 'Compra registrada',
            'text'  => 'La compra se registró correctamente y el inventario fue actualizado.'
        ];

    } else {

        $_SESSION['alert'] = [
            'icon'  => 'error',
            'title' => 'No se pudo registrar la compra',
            'text'  => (string)$resultado
        ];
    }

    header("Location: ../views/compras/compra.php");
    exit;
}


// ============================================================
// ACCIÓN: DETALLE DE COMPRA (JSON)
// ============================================================

if ($accion === 'detalle') {

    header('Content-Type: application/json; charset=utf-8');

    $id_compra = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if ($id_compra <= 0) {

        http_response_code(400);
        echo json_encode(['error' => 'ID de compra no válido.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {

        $detalle = $compraModel->obtenerDetalle($id_compra);
        echo json_encode($detalle, JSON_UNESCAPED_UNICODE);

    } catch (Exception $e) {

        http_response_code(500);
        echo json_encode(['error' => 'Error al obtener el detalle de la compra.'], JSON_UNESCAPED_UNICODE);
    }

    exit;
}


// ============================================================
// ACCIÓN: ELIMINAR COMPRA
// ============================================================

if ($accion === 'eliminar') {

    $id_compra = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if ($id_compra <= 0) {

        $_SESSION['alert'] = [
            'icon'  => 'error',
            'title' => 'Compra inválida',
            'text'  => 'El identificador de la compra no es válido.'
        ];

        header("Location: ../views/compras/compra.php");
        exit;
    }

    $resultado = $compraModel->eliminar($id_compra);

    if ($resultado === true) {

        $_SESSION['alert'] = [
            'icon'  => 'success',
            'title' => 'Compra eliminada',
            'text'  => 'La compra fue eliminada correctamente y el inventario fue actualizado.'
        ];

    } else {

        $_SESSION['alert'] = [
            'icon'  => 'error',
            'title' => 'No se pudo eliminar',
            'text'  => (string)$resultado
        ];
    }

    header("Location: ../views/compras/compra.php");
    exit;
}


// ============================================================
// ACCIÓN NO VÁLIDA
// ============================================================

$_SESSION['alert'] = [
    'icon'  => 'warning',
    'title' => 'Acción no válida',
    'text'  => 'La acción solicitada no existe.'
];

header("Location: ../views/compras/compra.php");
exit;