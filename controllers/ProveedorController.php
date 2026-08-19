<?php
/**
 * Controlador de Proveedores
 *
 * estado en persona:
 * 1 = Activo
 * 0 = Inactivo
 */

session_start();

if (!isset($_SESSION['usuario'])) {
    header("Location: ../views/usuarios/login.php");
    exit;
}

require_once __DIR__ . '/../config/databse.php';
require_once __DIR__ . '/../models/proveedor.php';

$database = new Database();
$db = $database->conectar();

$proveedorModel = new Proveedor($db);

$accion = $_GET['accion'] ?? '';

switch ($accion) {

    // ========================================================
    // REGISTRAR
    // ========================================================
    case 'registrar':

        $nombre = trim($_POST['nombre'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $correo = trim($_POST['correo'] ?? '');
        $frecuencia_entrega = trim($_POST['frecuencia_entrega'] ?? '');

        if (empty($nombre)) {

            $_SESSION['alert'] = [
                'icon' => 'warning',
                'title' => 'Campo requerido',
                'text' => 'El nombre del proveedor es obligatorio.'
            ];

            header("Location: ../views/proveedores/index.php");
            exit;
        }

        if (!empty($correo) && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {

            $_SESSION['alert'] = [
                'icon' => 'warning',
                'title' => 'Correo inválido',
                'text' => 'El formato del correo no es válido.'
            ];

            header("Location: ../views/proveedores/index.php");
            exit;
        }

        $resultado = $proveedorModel->registrar(
            $nombre,
            $telefono,
            $correo,
            $frecuencia_entrega
        );

        $_SESSION['alert'] = $resultado === true
            ? [
                'icon' => 'success',
                'title' => '¡Proveedor registrado!',
                'text' => 'Proveedor agregado correctamente.'
            ]
            : [
                'icon' => 'error',
                'title' => 'Error',
                'text' => $resultado
            ];

        header("Location: ../views/proveedores/index.php");
        exit;


    // ========================================================
    // EDITAR
    // ========================================================
    case 'editar':

        $id = intval($_POST['id_proveedor'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $correo = trim($_POST['correo'] ?? '');
        $frecuencia_entrega = trim($_POST['frecuencia_entrega'] ?? '');

        if (empty($nombre) || $id === 0) {

            $_SESSION['alert'] = [
                'icon' => 'warning',
                'title' => 'Datos inválidos',
                'text' => 'El nombre es obligatorio.'
            ];

            header("Location: ../views/proveedores/index.php");
            exit;
        }

        if (!empty($correo) && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {

            $_SESSION['alert'] = [
                'icon' => 'warning',
                'title' => 'Correo inválido',
                'text' => 'El formato del correo no es válido.'
            ];

            header("Location: ../views/proveedores/index.php");
            exit;
        }

        $resultado = $proveedorModel->editar(
            $id,
            $nombre,
            $telefono,
            $correo,
            $frecuencia_entrega
        );

        $_SESSION['alert'] = $resultado === true
            ? [
                'icon' => 'success',
                'title' => '¡Actualizado!',
                'text' => 'Proveedor actualizado correctamente.'
            ]
            : [
                'icon' => 'error',
                'title' => 'Error',
                'text' => $resultado
            ];

        header("Location: ../views/proveedores/index.php");
        exit;


    // ========================================================
    // ACTIVAR / DESACTIVAR
    // ========================================================
    case 'toggleEstado':

        $id = intval($_GET['id'] ?? 0);

        $estadoActual = isset($_GET['estado'])
            ? intval($_GET['estado'])
            : 1;

        $resultado = $proveedorModel->toggleEstado(
            $id,
            $estadoActual
        );

        $nuevoEstado = ($estadoActual === 1)
            ? 'desactivado'
            : 'activado';

        $_SESSION['alert'] = $resultado === true
            ? [
                'icon' => 'success',
                'title' => '¡Estado actualizado!',
                'text' => "El proveedor fue $nuevoEstado correctamente."
            ]
            : [
                'icon' => 'error',
                'title' => 'Error',
                'text' => $resultado
            ];

        header("Location: ../views/proveedores/index.php");
        exit;


    // ========================================================
    // ELIMINAR
    // ========================================================
    case 'eliminar':

        $id = intval($_GET['id'] ?? 0);

        $resultado = $proveedorModel->eliminar($id);

        $_SESSION['alert'] = $resultado === true
            ? [
                'icon' => 'success',
                'title' => 'Eliminado',
                'text' => 'Proveedor eliminado correctamente.'
            ]
            : [
                'icon' => 'error',
                'title' => 'Error',
                'text' => $resultado
            ];

        header("Location: ../views/proveedores/index.php");
        exit;


    // ========================================================
    // ACCIÓN NO VÁLIDA
    // ========================================================
    default:

        header("Location: ../views/proveedores/index.php");
        exit;
}
?>