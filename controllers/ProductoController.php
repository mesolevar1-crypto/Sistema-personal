<?php


// ============================================================
// SESIÓN
// ============================================================

session_start();

// ============================================================
// VERIFICAR SESIÓN
// ============================================================

if (!isset($_SESSION["usuario"])) {
    header("Location: ../views/usuarios/login.php");
    exit;
}

// ============================================================
// CONEXIÓN Y MODELO
// ============================================================

require_once __DIR__ . '/../config/databse.php';
require_once __DIR__ . '/../models/producto.php';

// ============================================================
// CONECTAR BASE DE DATOS
// ============================================================

$database = new Database();
$db = $database->conectar();

$productoModel = new Producto($db);

// ============================================================
// OBTENER ACCIÓN
// ============================================================

$accion = $_GET['accion'] ?? '';


// ============================================================
// FUNCIÓN PARA MOSTRAR ALERTA Y REGRESAR
// ============================================================

function regresarConAlerta($icon, $title, $text)
{
    $_SESSION['alert'] = [
        'icon'  => $icon,
        'title' => $title,
        'text'  => $text
    ];

    // ----------------------------------------------------------
    // REGRESAR A LA MISMA VISTA DESDE DONDE SE HIZO LA ACCIÓN
    // (admin vuelve a su panel, vendedor vuelve al suyo)
    // ----------------------------------------------------------

    $destino = $_SERVER['HTTP_REFERER'] ?? null;

    if ($destino) {
        header("Location: " . $destino);
    } else {
        // Fallback por si el navegador no envía referer
        header("Location: ../views/productos/index.php");
    }

    exit;
}


// ============================================================
// FUNCIÓN PARA GUARDAR IMAGEN
// ============================================================

function guardarImagen($archivo)
{
    // --------------------------------------------------------
    // NO SE RECIBIÓ ARCHIVO
    // --------------------------------------------------------

    if (
        !isset($archivo) ||
        !isset($archivo['name']) ||
        $archivo['error'] === UPLOAD_ERR_NO_FILE
    ) {
        return null;
    }

    // --------------------------------------------------------
    // ERROR DE SUBIDA
    // --------------------------------------------------------

    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    // --------------------------------------------------------
    // VALIDAR TAMAÑO
    // MÁXIMO 2 MB
    // --------------------------------------------------------

    if ($archivo['size'] > 2 * 1024 * 1024) {
        return false;
    }

    // --------------------------------------------------------
    // VALIDAR MIME REAL
    // --------------------------------------------------------

    $finfo = finfo_open(FILEINFO_MIME_TYPE);

    if (!$finfo) {
        return false;
    }

    $mime = finfo_file(
        $finfo,
        $archivo['tmp_name']
    );

    finfo_close($finfo);

    $tiposPermitidos = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp'
    ];

    if (!isset($tiposPermitidos[$mime])) {
        return false;
    }

    // --------------------------------------------------------
    // DIRECTORIO DE IMÁGENES
    // --------------------------------------------------------

    $directorio = __DIR__ . '/../uploads/productos/';

    if (!is_dir($directorio)) {

        if (!mkdir($directorio, 0755, true)) {
            return false;
        }
    }

    // --------------------------------------------------------
    // GENERAR NOMBRE ÚNICO
    // --------------------------------------------------------

    $extension = $tiposPermitidos[$mime];

    $nombreArchivo =
        'producto_' .
        date('YmdHis') .
        '_' .
        bin2hex(random_bytes(5)) .
        '.' .
        $extension;

    $rutaCompleta =
        $directorio . $nombreArchivo;

    // --------------------------------------------------------
    // MOVER ARCHIVO
    // --------------------------------------------------------

    if (
        !move_uploaded_file(
            $archivo['tmp_name'],
            $rutaCompleta
        )
    ) {
        return false;
    }

    // --------------------------------------------------------
    // RUTA QUE SE GUARDA EN BD
    // --------------------------------------------------------

    return 'uploads/productos/' . $nombreArchivo;
}


// ============================================================
// CREAR PRODUCTO
// ============================================================

if ($accion === 'crear') {

    // --------------------------------------------------------
    // SOLO POST
    // --------------------------------------------------------

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

        regresarConAlerta(
            'error',
            'Solicitud inválida',
            'La operación solicitada no es válida.'
        );
    }

    // --------------------------------------------------------
    // DATOS
    // --------------------------------------------------------

    $nombre = trim(
        $_POST['nombre'] ?? ''
    );

    $descripcion = trim(
        $_POST['descripcion'] ?? ''
    );

    $id_categoria = !empty($_POST['id_categoria'])
        ? (int) $_POST['id_categoria']
        : null;

    // --------------------------------------------------------
    // VALIDAR NOMBRE
    // --------------------------------------------------------

    if ($nombre === '') {

        regresarConAlerta(
            'warning',
            'Falta información',
            'Debes ingresar el nombre del producto.'
        );
    }

    // --------------------------------------------------------
    // VALIDAR CATEGORÍA
    // --------------------------------------------------------

    if ($id_categoria === null) {

        regresarConAlerta(
            'warning',
            'Falta información',
            'Debes seleccionar una categoría.'
        );
    }

    // --------------------------------------------------------
    // VERIFICAR NOMBRE DUPLICADO
    // --------------------------------------------------------

    if ($productoModel->existeNombre($nombre)) {

        regresarConAlerta(
            'warning',
            'Producto duplicado',
            'Ya existe un producto registrado con ese nombre.'
        );
    }

    // --------------------------------------------------------
    // GUARDAR IMAGEN
    // --------------------------------------------------------

    $imagen = null;

    if (
        isset($_FILES['imagen']) &&
        $_FILES['imagen']['error'] !== UPLOAD_ERR_NO_FILE
    ) {

        $imagen = guardarImagen(
            $_FILES['imagen']
        );

        if ($imagen === false) {

            regresarConAlerta(
                'error',
                'Imagen inválida',
                'La imagen no es válida, supera los 2 MB o no se pudo guardar.'
            );
        }
    }

    // --------------------------------------------------------
    // DATOS PARA EL MODELO
    // --------------------------------------------------------

    $datos = [
        'nombre'       => $nombre,
        'descripcion'  => $descripcion,
        'id_categoria' => $id_categoria,
        'imagen'       => $imagen
    ];

    // --------------------------------------------------------
    // REGISTRAR
    // --------------------------------------------------------

    $resultado = $productoModel->registrar(
        $datos
    );

    // --------------------------------------------------------
    // RESULTADO
    // --------------------------------------------------------

    if (is_int($resultado) && $resultado > 0) {

        regresarConAlerta(
            'success',
            'Producto registrado',
            'El producto se registró correctamente.'
        );
    }

    // --------------------------------------------------------
    // ERROR
    // --------------------------------------------------------

    regresarConAlerta(
        'error',
        'No se pudo registrar',
        is_string($resultado)
            ? $resultado
            : 'No fue posible registrar el producto.'
    );
}


// ============================================================
// EDITAR PRODUCTO
// ============================================================

if ($accion === 'editar') {

    // --------------------------------------------------------
    // SOLO POST
    // --------------------------------------------------------

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

        regresarConAlerta(
            'error',
            'Solicitud inválida',
            'La operación solicitada no es válida.'
        );
    }

    // --------------------------------------------------------
    // ID
    // --------------------------------------------------------

    $id_producto = isset($_POST['id_producto'])
        ? (int) $_POST['id_producto']
        : 0;

    if ($id_producto <= 0) {

        regresarConAlerta(
            'error',
            'Producto inválido',
            'No se recibió un producto válido.'
        );
    }

    // --------------------------------------------------------
    // VERIFICAR PRODUCTO
    // --------------------------------------------------------

    $producto = $productoModel->obtenerPorId(
        $id_producto
    );

    if (!$producto) {

        regresarConAlerta(
            'error',
            'Producto no encontrado',
            'El producto que intentas editar no existe.'
        );
    }

    // --------------------------------------------------------
    // DATOS
    // --------------------------------------------------------

    $nombre = trim(
        $_POST['nombre'] ?? ''
    );

    $descripcion = trim(
        $_POST['descripcion'] ?? ''
    );

    $id_categoria = !empty($_POST['id_categoria'])
        ? (int) $_POST['id_categoria']
        : null;

    // --------------------------------------------------------
    // VALIDAR NOMBRE
    // --------------------------------------------------------

    if ($nombre === '') {

        regresarConAlerta(
            'warning',
            'Falta información',
            'Debes ingresar el nombre del producto.'
        );
    }

    // --------------------------------------------------------
    // VALIDAR CATEGORÍA
    // --------------------------------------------------------

    if ($id_categoria === null) {

        regresarConAlerta(
            'warning',
            'Falta información',
            'Debes seleccionar una categoría.'
        );
    }

    // --------------------------------------------------------
    // VERIFICAR NOMBRE DUPLICADO
    // EXCLUYENDO EL MISMO PRODUCTO
    // --------------------------------------------------------

    if (
        $productoModel->existeNombre(
            $nombre,
            $id_producto
        )
    ) {

        regresarConAlerta(
            'warning',
            'Producto duplicado',
            'Ya existe otro producto con ese nombre.'
        );
    }

    // --------------------------------------------------------
    // NUEVA IMAGEN
    // --------------------------------------------------------

    $imagen = null;

    if (
        isset($_FILES['imagen']) &&
        $_FILES['imagen']['error'] !== UPLOAD_ERR_NO_FILE
    ) {

        $imagen = guardarImagen(
            $_FILES['imagen']
        );

        if ($imagen === false) {

            regresarConAlerta(
                'error',
                'Imagen inválida',
                'La nueva imagen no es válida, supera los 2 MB o no se pudo guardar.'
            );
        }
    }

    // --------------------------------------------------------
    // DATOS
    // --------------------------------------------------------

    $datos = [
        'nombre'       => $nombre,
        'descripcion'  => $descripcion,
        'id_categoria' => $id_categoria
    ];

    // --------------------------------------------------------
    // SI HAY NUEVA IMAGEN
    // --------------------------------------------------------

    if ($imagen !== null) {
        $datos['imagen'] = $imagen;
    }

    // --------------------------------------------------------
    // EDITAR
    // --------------------------------------------------------

    $resultado = $productoModel->editar(
        $id_producto,
        $datos
    );

    // --------------------------------------------------------
    // SI SE EDITÓ CORRECTAMENTE
    // --------------------------------------------------------

    if ($resultado === true) {

        // ----------------------------------------------------
        // ELIMINAR IMAGEN ANTERIOR SI SE CAMBIÓ
        // ----------------------------------------------------

        if ($imagen !== null) {

            $imagenAnterior =
                $producto['imagen'] ?? '';

            if (
                !empty($imagenAnterior) &&
                strpos(
                    $imagenAnterior,
                    'uploads/productos/'
                ) === 0
            ) {

                $rutaAnterior =
                    __DIR__ .
                    '/../' .
                    $imagenAnterior;

                if (
                    is_file($rutaAnterior) &&
                    $rutaAnterior !==
                    __DIR__ . '/../' . $imagen
                ) {

                    @unlink($rutaAnterior);
                }
            }
        }

        regresarConAlerta(
            'success',
            'Producto actualizado',
            'Los cambios del producto se guardaron correctamente.'
        );
    }

    // --------------------------------------------------------
    // ERROR
    // --------------------------------------------------------

    regresarConAlerta(
        'error',
        'No se pudo actualizar',
        is_string($resultado)
            ? $resultado
            : 'No fue posible actualizar el producto.'
    );
}


// ============================================================
// ACTIVAR PRODUCTO
// ============================================================

if ($accion === 'activar') {

    // --------------------------------------------------------
    // SOLO POST
    // --------------------------------------------------------

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

        regresarConAlerta(
            'error',
            'Solicitud inválida',
            'La operación solicitada no es válida.'
        );
    }

    // --------------------------------------------------------
    // ID
    // --------------------------------------------------------

    $id_producto = isset($_POST['id_producto'])
        ? (int) $_POST['id_producto']
        : 0;

    if ($id_producto <= 0) {

        regresarConAlerta(
            'error',
            'Producto inválido',
            'No se recibió un producto válido.'
        );
    }

    // --------------------------------------------------------
    // VERIFICAR PRODUCTO
    // --------------------------------------------------------

    $producto = $productoModel->obtenerPorId(
        $id_producto
    );

    if (!$producto) {

        regresarConAlerta(
            'error',
            'Producto no encontrado',
            'El producto que intentas activar no existe.'
        );
    }

    // --------------------------------------------------------
    // CAMBIAR ESTADO
    // --------------------------------------------------------

    $resultado = $productoModel->cambiarEstado(
        $id_producto,
        1
    );

    // --------------------------------------------------------
    // RESULTADO
    // --------------------------------------------------------

    if ($resultado === true) {

        regresarConAlerta(
            'success',
            'Producto activado',
            'El producto ahora está disponible en el catálogo.'
        );
    }

    regresarConAlerta(
        'error',
        'No se pudo activar',
        is_string($resultado)
            ? $resultado
            : 'No fue posible activar el producto.'
    );
}


// ============================================================
// DESACTIVAR PRODUCTO
// ============================================================

if ($accion === 'desactivar') {

    // --------------------------------------------------------
    // SOLO POST
    // --------------------------------------------------------

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

        regresarConAlerta(
            'error',
            'Solicitud inválida',
            'La operación solicitada no es válida.'
        );
    }

    // --------------------------------------------------------
    // ID
    // --------------------------------------------------------

    $id_producto = isset($_POST['id_producto'])
        ? (int) $_POST['id_producto']
        : 0;

    if ($id_producto <= 0) {

        regresarConAlerta(
            'error',
            'Producto inválido',
            'No se recibió un producto válido.'
        );
    }

    // --------------------------------------------------------
    // VERIFICAR PRODUCTO
    // --------------------------------------------------------

    $producto = $productoModel->obtenerPorId(
        $id_producto
    );

    if (!$producto) {

        regresarConAlerta(
            'error',
            'Producto no encontrado',
            'El producto que intentas desactivar no existe.'
        );
    }

    // --------------------------------------------------------
    // CAMBIAR ESTADO
    // --------------------------------------------------------

    $resultado = $productoModel->cambiarEstado(
        $id_producto,
        0
    );

    // --------------------------------------------------------
    // RESULTADO
    // --------------------------------------------------------

    if ($resultado === true) {

        regresarConAlerta(
            'success',
            'Producto desactivado',
            'El producto ahora está marcado como inactivo.'
        );
    }

    regresarConAlerta(
        'error',
        'No se pudo desactivar',
        is_string($resultado)
            ? $resultado
            : 'No fue posible desactivar el producto.'
    );
}


// ============================================================
// ELIMINAR PRODUCTO
// ============================================================

if ($accion === 'eliminar') {

    // --------------------------------------------------------
    // OBTENER ID
    // --------------------------------------------------------

    $id_producto = isset($_GET['id'])
        ? (int) $_GET['id']
        : 0;

    if ($id_producto <= 0) {

        regresarConAlerta(
            'error',
            'Producto inválido',
            'No se recibió un producto válido.'
        );
    }

    // --------------------------------------------------------
    // OBTENER PRODUCTO
    // --------------------------------------------------------

    $producto = $productoModel->obtenerPorId(
        $id_producto
    );

    if (!$producto) {

        regresarConAlerta(
            'error',
            'Producto no encontrado',
            'El producto que intentas eliminar no existe.'
        );
    }

    // --------------------------------------------------------
    // ELIMINAR
    // --------------------------------------------------------

    $resultado = $productoModel->eliminar(
        $id_producto
    );

    // --------------------------------------------------------
    // ELIMINACIÓN CORRECTA
    // --------------------------------------------------------

    if ($resultado === true) {

        // ----------------------------------------------------
        // ELIMINAR IMAGEN DEL SERVIDOR
        // ----------------------------------------------------

        if (!empty($producto['imagen'])) {

            if (
                strpos(
                    $producto['imagen'],
                    'uploads/productos/'
                ) === 0
            ) {

                $rutaImagen =
                    __DIR__ .
                    '/../' .
                    $producto['imagen'];

                if (is_file($rutaImagen)) {
                    @unlink($rutaImagen);
                }
            }
        }

        regresarConAlerta(
            'success',
            'Producto eliminado',
            'El producto fue eliminado correctamente.'
        );
    }

    // --------------------------------------------------------
    // NO SE PUDO ELIMINAR
    // --------------------------------------------------------

    regresarConAlerta(
        'error',
        'No se puede eliminar',
        'Este producto tiene información relacionada, como precios o registros de compras. Puedes desactivarlo en lugar de eliminarlo.'
    );
}


// ============================================================
// CREAR CATEGORÍA
// ============================================================

if ($accion === 'crearCategoria') {

    // --------------------------------------------------------
    // SOLO POST
    // --------------------------------------------------------

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

        regresarConAlerta(
            'error',
            'Solicitud inválida',
            'La operación solicitada no es válida.'
        );
    }

    // --------------------------------------------------------
    // DATOS
    // --------------------------------------------------------

    $tipo = trim(
        $_POST['tipo'] ?? ''
    );

    // --------------------------------------------------------
    // VALIDAR
    // --------------------------------------------------------

    if ($tipo === '') {

        regresarConAlerta(
            'warning',
            'Falta información',
            'Debes ingresar el nombre de la categoría.'
        );
    }

    // --------------------------------------------------------
    // DUPLICADO
    // --------------------------------------------------------

    if ($productoModel->existeCategoriaTipo($tipo)) {

        regresarConAlerta(
            'warning',
            'Categoría duplicada',
            'Ya existe una categoría con ese nombre.'
        );
    }

    // --------------------------------------------------------
    // DATOS
    // --------------------------------------------------------

    $datos = [
        'tipo' => $tipo
    ];

    // --------------------------------------------------------
    // REGISTRAR
    // --------------------------------------------------------

    $resultado =
        $productoModel->registrarCategoria(
            $datos
        );

    // --------------------------------------------------------
    // RESULTADO
    // --------------------------------------------------------

    if ($resultado === true) {

        regresarConAlerta(
            'success',
            'Categoría registrada',
            'La categoría se registró correctamente.'
        );
    }

    regresarConAlerta(
        'error',
        'No se pudo registrar',
        is_string($resultado)
            ? $resultado
            : 'No fue posible registrar la categoría.'
    );
}


// ============================================================
// EDITAR CATEGORÍA
// ============================================================

if ($accion === 'editarCategoria') {

    // --------------------------------------------------------
    // SOLO POST
    // --------------------------------------------------------

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

        regresarConAlerta(
            'error',
            'Solicitud inválida',
            'La operación solicitada no es válida.'
        );
    }

    // --------------------------------------------------------
    // ID
    // --------------------------------------------------------

    $id_categoria = isset($_POST['id_categoria'])
        ? (int) $_POST['id_categoria']
        : 0;

    if ($id_categoria <= 0) {

        regresarConAlerta(
            'error',
            'Categoría inválida',
            'No se recibió una categoría válida.'
        );
    }

    // --------------------------------------------------------
    // DATOS
    // --------------------------------------------------------

    $tipo = trim(
        $_POST['tipo'] ?? ''
    );

    // --------------------------------------------------------
    // VALIDAR
    // --------------------------------------------------------

    if ($tipo === '') {

        regresarConAlerta(
            'warning',
            'Falta información',
            'Debes ingresar el nombre de la categoría.'
        );
    }

    // --------------------------------------------------------
    // DUPLICADO
    // --------------------------------------------------------

    if (
        $productoModel->existeCategoriaTipo(
            $tipo,
            $id_categoria
        )
    ) {

        regresarConAlerta(
            'warning',
            'Categoría duplicada',
            'Ya existe otra categoría con ese nombre.'
        );
    }

    // --------------------------------------------------------
    // DATOS
    // --------------------------------------------------------

    $datos = [
        'tipo' => $tipo
    ];

    // --------------------------------------------------------
    // EDITAR
    // --------------------------------------------------------

    $resultado =
        $productoModel->editarCategoria(
            $id_categoria,
            $datos
        );

    // --------------------------------------------------------
    // RESULTADO
    // --------------------------------------------------------

    if ($resultado === true) {

        regresarConAlerta(
            'success',
            'Categoría actualizada',
            'La categoría se actualizó correctamente.'
        );
    }

    regresarConAlerta(
        'error',
        'No se pudo actualizar',
        is_string($resultado)
            ? $resultado
            : 'No fue posible actualizar la categoría.'
    );
}


// ============================================================
// ELIMINAR CATEGORÍA
// ============================================================

if ($accion === 'eliminarCategoria') {

    // --------------------------------------------------------
    // ID
    // --------------------------------------------------------

    $id_categoria = isset($_GET['id'])
        ? (int) $_GET['id']
        : 0;

    if ($id_categoria <= 0) {

        regresarConAlerta(
            'error',
            'Categoría inválida',
            'No se recibió una categoría válida.'
        );
    }

    // --------------------------------------------------------
    // ELIMINAR
    // --------------------------------------------------------

    $resultado =
        $productoModel->eliminarCategoria(
            $id_categoria
        );

    // --------------------------------------------------------
    // RESULTADO
    // --------------------------------------------------------

    if ($resultado === true) {

        regresarConAlerta(
            'success',
            'Categoría eliminada',
            'La categoría fue eliminada correctamente.'
        );
    }

    regresarConAlerta(
        'error',
        'No se puede eliminar',
        'Esta categoría tiene productos asociados. Debes eliminar o reasignar esos productos antes de eliminar la categoría.'
    );
}


// ============================================================
// ACCIÓN NO RECONOCIDA
// ============================================================

regresarConAlerta(
    'error',
    'Acción no válida',
    'La acción solicitada no existe.'
);

?>