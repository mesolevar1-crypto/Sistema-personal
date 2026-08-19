<?php
/**
 * Controlador de Productos (ProductoController)
 * Maneja todas las operaciones CRUD de productos del sistema.
 * Permite crear, editar y eliminar productos con sus respectivas validaciones.
 * Requiere que el usuario esté autenticado para acceder a cualquier acción.
 */
session_start();

// Verificamos que el usuario esté logueado; si no, redirigimos al login
if (!isset($_SESSION['usuario'])) {
    header("Location: ../views/usuarios/login.php");
    exit;
}

// Incluimos la clase de conexión y el modelo de producto
require_once __DIR__ . '/../config/databse.php';
require_once __DIR__ . '/../models/producto.php';

// Conectamos a la base de datos e instanciamos el modelo
$database = new Database();
$db = $database->conectar();
$productoModel = new Producto($db);

// Obtenemos la acción que viene por el parámetro GET
$accion = $_GET['accion'] ?? '';

switch ($accion) {

    // -----------------------------------------------
    // CREAR producto nuevo
    // -----------------------------------------------
    case 'crear':
        $nombre       = trim($_POST['nombre']         ?? '');
        $stock        = intval($_POST['stock']         ?? 0);
        $precio       = trim($_POST['precio']         ?? '');
        $id_categoria = intval($_POST['id_categoria'] ?? 0);

        if (empty($nombre) || empty($precio)) {
            $_SESSION['alert'] = ['icon'=>'warning','title'=>'Campos incompletos','text'=>'El nombre y el precio son obligatorios.'];
            header("Location: ../views/productos/index.php"); exit;
        }
        if ($productoModel->existeNombre($nombre)) {
            $_SESSION['alert'] = ['icon'=>'warning','title'=>'Nombre duplicado','text'=>'Ya existe un producto con ese nombre.'];
            header("Location: ../views/productos/index.php"); exit;
        }

        // Manejo de imagen
        $imagen = null;
        if (!empty($_FILES['imagen']['name'])) {
            $ext = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
            $permitidos = ['jpg','jpeg','png','gif','webp'];
            if (!in_array($ext, $permitidos)) {
                $_SESSION['alert'] = ['icon'=>'warning','title'=>'Formato inválido','text'=>'Solo se permiten imágenes JPG, PNG, GIF o WEBP.'];
                header("Location: ../views/productos/index.php"); exit;
            }
            if ($_FILES['imagen']['size'] > 2 * 1024 * 1024) {
                $_SESSION['alert'] = ['icon'=>'warning','title'=>'Imagen muy grande','text'=>'La imagen no puede superar 2MB.'];
                header("Location: ../views/productos/index.php"); exit;
            }
            $carpeta = __DIR__ . '/../img/productos/';
            if (!is_dir($carpeta)) mkdir($carpeta, 0755, true);
            $nombreArchivo = 'prod_' . time() . '_' . rand(100,999) . '.' . $ext;
            if (move_uploaded_file($_FILES['imagen']['tmp_name'], $carpeta . $nombreArchivo)) {
                $imagen = 'img/productos/' . $nombreArchivo;
            }
        }

        $resultado = $productoModel->registrar([
            'nombre'      => $nombre,
            'stock'       => $stock,
            'precio'      => $precio,
            'id_categoria'=> $id_categoria ?: null,
            'imagen'      => $imagen,
        ]);

        $_SESSION['alert'] = $resultado === true
            ? ['icon'=>'success','title'=>'¡Producto creado!','text'=>'El producto fue agregado correctamente.']
            : ['icon'=>'error',  'title'=>'Error',            'text'=>$resultado];
        header("Location: ../views/productos/index.php"); exit;

    // -----------------------------------------------
    // EDITAR producto existente
    // -----------------------------------------------
    case 'editar':
        $id_producto  = intval($_POST['id_producto']  ?? 0);
        $nombre       = trim($_POST['nombre']         ?? '');
        $stock        = intval($_POST['stock']         ?? 0);
        $precio       = trim($_POST['precio']         ?? '');
        $id_categoria = intval($_POST['id_categoria'] ?? 0);

        if (empty($nombre) || empty($precio) || $id_producto === 0) {
            $_SESSION['alert'] = ['icon'=>'warning','title'=>'Campos incompletos','text'=>'El nombre y el precio son obligatorios.'];
            header("Location: ../views/productos/index.php"); exit;
        }
        if ($productoModel->existeNombre($nombre, $id_producto)) {
            $_SESSION['alert'] = ['icon'=>'warning','title'=>'Nombre duplicado','text'=>'Ya existe otro producto con ese nombre.'];
            header("Location: ../views/productos/index.php"); exit;
        }

        // Manejo de imagen al editar
        $imagen = null;
        if (!empty($_FILES['imagen']['name'])) {
            $ext = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
            $permitidos = ['jpg','jpeg','png','gif','webp'];
            if (in_array($ext, $permitidos) && $_FILES['imagen']['size'] <= 2 * 1024 * 1024) {
                $carpeta = __DIR__ . '/../img/productos/';
                if (!is_dir($carpeta)) mkdir($carpeta, 0755, true);
                $nombreArchivo = 'prod_' . time() . '_' . rand(100,999) . '.' . $ext;
                if (move_uploaded_file($_FILES['imagen']['tmp_name'], $carpeta . $nombreArchivo)) {
                    $imagen = 'img/productos/' . $nombreArchivo;
                    // Eliminar imagen anterior si existe
                    $imagenAnterior = trim($_POST['imagen_actual'] ?? '');
                    if ($imagenAnterior && file_exists(__DIR__ . '/../' . $imagenAnterior)) {
                        @unlink(__DIR__ . '/../' . $imagenAnterior);
                    }
                }
            }
        }

        $resultado = $productoModel->editar($id_producto, [
            'nombre'      => $nombre,
            'stock'       => $stock,
            'precio'      => $precio,
            'id_categoria'=> $id_categoria ?: null,
            'imagen'      => $imagen,
        ]);

        $_SESSION['alert'] = $resultado === true
            ? ['icon'=>'success','title'=>'¡Producto actualizado!','text'=>'Los datos fueron actualizados correctamente.']
            : ['icon'=>'error',  'title'=>'Error',                 'text'=>$resultado];
        header("Location: ../views/productos/index.php"); exit;

    // -----------------------------------------------
    // ELIMINAR producto
    // -----------------------------------------------
    case 'eliminar':
        // Obtenemos el ID del producto a eliminar desde el parámetro GET
        $id_producto = intval($_GET['id'] ?? 0);

        // Validamos que el ID sea válido (mayor que 0)
        if ($id_producto === 0) {
            $_SESSION['alert'] = ['icon' => 'error', 'title' => 'Error', 'text' => 'Producto no válido.'];
            header("Location: ../views/productos/index.php");
            exit;
        }

        // Llamamos al modelo para eliminar el producto
        $resultado = $productoModel->eliminar($id_producto);

        // Guardamos el mensaje de resultado en sesión
        $_SESSION['alert'] = $resultado === true
            ? ['icon' => 'success', 'title' => '¡Producto eliminado!', 'text' => 'El producto fue eliminado correctamente.']
            : ['icon' => 'error',   'title' => 'Error',                'text' => $resultado];

        header("Location: ../views/productos/index.php");
        exit;

    // Acción no reconocida: redirigir a la lista de productos
    default:
        header("Location: ../views/productos/index.php");
        exit;
}
?>
