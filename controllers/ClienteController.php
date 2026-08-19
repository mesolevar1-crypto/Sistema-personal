<?php
/**
 * Controlador de Clientes
 * BD real: persona.estado ('activo'/'inactivo'), cliente tiene fecha_registro
 */
session_start();

if (!isset($_SESSION['usuario'])) {
    header("Location: ../views/usuarios/login.php"); exit;
}

require_once __DIR__ . '/../config/databse.php';
require_once __DIR__ . '/../models/cliente.php';

$database     = new Database();
$db           = $database->conectar();
$clienteModel = new Cliente($db);

$accion = $_GET['accion'] ?? '';

switch ($accion) {

    case 'registrar':
        $nombre   = trim($_POST['nombre']   ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $correo   = trim($_POST['correo']   ?? '');

        if (empty($nombre)) {
            $_SESSION['alert'] = ['icon' => 'warning', 'title' => 'Campo obligatorio',
                'text' => 'El nombre del cliente es obligatorio.'];
            header("Location: ../views/clientes/index.php"); exit;
        }
        if (!empty($correo) && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['alert'] = ['icon' => 'warning', 'title' => 'Correo inválido',
                'text' => 'El formato del correo electrónico no es válido.'];
            header("Location: ../views/clientes/index.php"); exit;
        }
        if (!empty($correo) && $clienteModel->existeCorreo($correo)) {
            $_SESSION['alert'] = ['icon' => 'error', 'title' => 'Correo duplicado',
                'text' => 'El correo electrónico ya está registrado.'];
            header("Location: ../views/clientes/index.php"); exit;
        }

        $resultado = $clienteModel->registrar([
            'nombre'   => $nombre,
            'telefono' => $telefono,
            'correo'   => $correo,
        ]);

        $_SESSION['alert'] = $resultado === true
            ? ['icon' => 'success', 'title' => '¡Cliente registrado!',
               'text' => 'Cliente registrado correctamente.']
            : ['icon' => 'error', 'title' => 'Error', 'text' => $resultado];
        header("Location: ../views/clientes/index.php"); exit;

    case 'editar':
        $id_cliente = intval($_POST['id_cliente'] ?? 0);
        $nombre     = trim($_POST['nombre']       ?? '');
        $telefono   = trim($_POST['telefono']     ?? '');
        $correo     = trim($_POST['correo']       ?? '');

        if (empty($nombre) || $id_cliente === 0) {
            $_SESSION['alert'] = ['icon' => 'warning', 'title' => 'Campo obligatorio',
                'text' => 'El nombre es obligatorio.'];
            header("Location: ../views/clientes/index.php"); exit;
        }

        $resultado = $clienteModel->editarCompleto($id_cliente, $nombre, $telefono, $correo);
        $_SESSION['alert'] = $resultado === true
            ? ['icon' => 'success', 'title' => '¡Actualizado!', 'text' => 'Cliente actualizado correctamente.']
            : ['icon' => 'error',   'title' => 'Error',         'text' => $resultado];
        header("Location: ../views/clientes/index.php"); exit;

    case 'toggleEstado':
        $id           = intval($_GET['id']     ?? 0);
        $estadoActual = $_GET['estado']        ?? 'activo';
        $nuevoEstado  = ($estadoActual === 'activo') ? 'inactivo' : 'activo';

        $resultado = $clienteModel->cambiarEstado($id, $nuevoEstado);
        $_SESSION['alert'] = $resultado === true
            ? ['icon' => 'success', 'title' => 'Estado actualizado',
               'text' => 'El cliente fue ' . ($nuevoEstado === 'activo' ? 'activado' : 'desactivado') . '.']
            : ['icon' => 'error', 'title' => 'Error', 'text' => $resultado];
        header("Location: ../views/clientes/index.php"); exit;

    case 'eliminar':
        $id = intval($_GET['id'] ?? 0);
        $resultado = $clienteModel->eliminar($id);
        $_SESSION['alert'] = $resultado === true
            ? ['icon' => 'success', 'title' => 'Eliminado',   'text' => 'Cliente eliminado correctamente.']
            : ['icon' => 'error',   'title' => 'Error',       'text' => $resultado];
        header("Location: ../views/clientes/index.php"); exit;

    default:
        header("Location: ../views/clientes/index.php"); exit;
}
?>
