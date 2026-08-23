<?php

/**
 * ============================================================
 * CONTROLADOR: CompraController.php
 * ============================================================
 *
 * Flujo:
 *
 *   VISTA -> CompraController -> Compra (modelo) -> BD
 *
 * El controlador NUNCA confía en precios, unidades ni
 * subtotales enviados desde el navegador -- todo eso lo
 * recalcula y verifica el modelo contra la base de datos.
 *
 * NOTA: cantidad y cantidad_por_unidad se castean a ENTERO
 * (no a float), porque esas columnas son INT en la base de
 * datos.
 *
 * NUEVO: se agrega el manejo de id_unidad_contenido[], la
 * segunda FK hacia unidades_medida que representa la unidad en
 * la que se expresa el contenido de la unidad de compra.
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
    header("Location: ../views/compra/index.php");
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
        header("Location: ../views/compra/index.php");
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
        header("Location: ../views/compra/index.php");
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
        header("Location: ../views/compra/index.php");
        exit;
    }

    // --------------------------------------------------------
    // PRODUCTOS
    //
    // id_producto[]            -> producto comprado
    // cantidad[]                -> presentaciones compradas
    // precio_compra[]           -> precio que el usuario negoció (por presentación)
    // id_unidad[]                -> unidad de compra elegida (FK al catálogo `unidades_medida`)
    // cantidad_por_unidad[]      -> cuánto contenido trae cada presentación (ej: 50)
    // id_unidad_contenido[]      -> unidad en la que se mide ese contenido (FK al mismo catálogo)
    //
    // Ninguno de estos valores se toma de un catálogo de precios:
    // todos los escribe/selecciona el usuario en el formulario.
    // --------------------------------------------------------
    $idsProducto          = $_POST['id_producto'] ?? [];
    $cantidades           = $_POST['cantidad'] ?? [];
    $preciosCompra        = $_POST['precio_compra'] ?? [];
    $idsUnidad            = $_POST['id_unidad'] ?? [];
    $cantidadesPorUnidad  = $_POST['cantidad_por_unidad'] ?? [];
    $idsUnidadContenido   = $_POST['id_unidad_contenido'] ?? [];

    if (
        !is_array($idsProducto) ||
        !is_array($cantidades) ||
        count($idsProducto) === 0 ||
        count($cantidades) === 0
    ) {
        $_SESSION['alert'] = [
            'icon'  => 'warning',
            'title' => 'Compra vacía',
            'text'  => 'Debes agregar al menos un producto a la compra.'
        ];
        header("Location: ../views/compra/index.php");
        exit;
    }

    // --------------------------------------------------------
    // CONSTRUIR ITEMS
    //
    // cantidad y cantidad_por_unidad se castean a ENTERO -- las
    // columnas son INT en la base de datos. Si el usuario escribe
    // "2.7", se rechaza aquí en vez de dejar que MySQL trunque en
    // silencio más adelante.
    // --------------------------------------------------------
    $items = [];
    $cantidadFilas = max(count($idsProducto), count($cantidades));

    for ($i = 0; $i < $cantidadFilas; $i++) {

        $idProducto          = isset($idsProducto[$i]) ? (int)$idsProducto[$i] : 0;
        $cantidadCruda       = isset($cantidades[$i]) ? trim((string)$cantidades[$i]) : '';
        $precioCruda         = isset($preciosCompra[$i]) ? trim((string)$preciosCompra[$i]) : '';
        $idUnidad            = isset($idsUnidad[$i]) ? (int)$idsUnidad[$i] : 0;
        $cantidadUnidadCruda = isset($cantidadesPorUnidad[$i]) ? trim((string)$cantidadesPorUnidad[$i]) : '';
        $idUnidadContenido   = isset($idsUnidadContenido[$i]) ? (int)$idsUnidadContenido[$i] : 0;

        // Ignorar filas completamente vacías
        if (
            $idProducto <= 0 && $cantidadCruda === '' && $precioCruda === '' &&
            $idUnidad <= 0 && $cantidadUnidadCruda === '' && $idUnidadContenido <= 0
        ) {
            continue;
        }

        if ($idProducto <= 0) {
            $_SESSION['alert'] = [
                'icon'  => 'warning',
                'title' => 'Producto inválido',
                'text'  => 'Hay una fila de la compra sin producto seleccionado.'
            ];
            header("Location: ../views/compra/index.php");
            exit;
        }

        // Cantidad: entero positivo (no "2.5", no "abc")
        if (!ctype_digit($cantidadCruda) || (int)$cantidadCruda <= 0) {
            $_SESSION['alert'] = [
                'icon'  => 'warning',
                'title' => 'Cantidad inválida',
                'text'  => 'Todas las cantidades deben ser números enteros mayores que cero.'
            ];
            header("Location: ../views/compra/index.php");
            exit;
        }

        // Precio de compra: numérico positivo, lo escribió el usuario
        if (!is_numeric($precioCruda) || (float)$precioCruda <= 0) {
            $_SESSION['alert'] = [
                'icon'  => 'warning',
                'title' => 'Precio inválido',
                'text'  => 'Debes escribir el precio de compra negociado con el proveedor para cada producto.'
            ];
            header("Location: ../views/compra/index.php");
            exit;
        }

        // Unidad de compra: debe haberse seleccionado del catálogo
        if ($idUnidad <= 0) {
            $_SESSION['alert'] = [
                'icon'  => 'warning',
                'title' => 'Unidad de compra requerida',
                'text'  => 'Debes seleccionar en qué unidad compraste cada producto (bulto, caja, etc).'
            ];
            header("Location: ../views/compra/index.php");
            exit;
        }

        // Cantidad por unidad: entero positivo
        if (!ctype_digit($cantidadUnidadCruda) || (int)$cantidadUnidadCruda <= 0) {
            $_SESSION['alert'] = [
                'icon'  => 'warning',
                'title' => 'Presentación inválida',
                'text'  => 'Debes indicar cuánto contenido trae cada presentación comprada.'
            ];
            header("Location: ../views/compra/index.php");
            exit;
        }

        // Unidad de contenido: debe haberse seleccionado del catálogo
        if ($idUnidadContenido <= 0) {
            $_SESSION['alert'] = [
                'icon'  => 'warning',
                'title' => 'Unidad de contenido requerida',
                'text'  => 'Debes seleccionar en qué unidad se mide el contenido de cada producto (ej. kilogramo, libra).'
            ];
            header("Location: ../views/compra/index.php");
            exit;
        }

        $items[] = [
            'id_producto'          => $idProducto,
            'cantidad'             => (int)$cantidadCruda,
            'precio_compra'        => (float)$precioCruda,
            'id_unidad'            => $idUnidad,
            'cantidad_por_unidad'  => (int)$cantidadUnidadCruda,
            'id_unidad_contenido'  => $idUnidadContenido,
        ];
    }

    if (empty($items)) {
        $_SESSION['alert'] = [
            'icon'  => 'warning',
            'title' => 'Compra vacía',
            'text'  => 'Debes seleccionar al menos un producto y especificar su cantidad.'
        ];
        header("Location: ../views/compra/index.php");
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

    header("Location: ../views/compra/index.php");
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
        header("Location: ../views/compra/index.php");
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

    header("Location: ../views/compra/index.php");
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

header("Location: ../views/compra/index.php");
exit;