<?php

session_start();


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

require_once __DIR__ . '/../config/databse.php';
require_once __DIR__ . '/../models/cliente.php';


try {

    $database = new Database();

    $db = $database->conectar();

    $clienteModel = new Cliente($db);

} catch (Exception $e) {

    $_SESSION['alert'] = [
        'icon' => 'error',
        'title' => 'Error',
        'text' => 'No se pudo conectar con la base de datos.'
    ];

    header("Location: ../views/clientes/index.php");

    exit;
}


// ============================================================
// ACCIÓN
// ============================================================

$accion = $_GET['accion'] ?? '';


// ============================================================
// REGISTRAR
// ============================================================

if ($accion === 'registrar') {

    $nombre = trim($_POST['nombre'] ?? '');

    $telefono = trim($_POST['telefono'] ?? '');

    $correo = trim($_POST['correo'] ?? '');


    if ($nombre === '') {

        $_SESSION['alert'] = [
            'icon' => 'warning',
            'title' => 'Campo obligatorio',
            'text' => 'El nombre del cliente es obligatorio.'
        ];

        header("Location: ../views/clientes/index.php");

        exit;
    }


    if (
        $correo !== '' &&
        !filter_var($correo, FILTER_VALIDATE_EMAIL)
    ) {

        $_SESSION['alert'] = [
            'icon' => 'warning',
            'title' => 'Correo inválido',
            'text' => 'El formato del correo electrónico no es válido.'
        ];

        header("Location: ../views/clientes/index.php");

        exit;
    }


    if (
        $correo !== '' &&
        $clienteModel->existeCorreo($correo)
    ) {

        $_SESSION['alert'] = [
            'icon' => 'error',
            'title' => 'Correo duplicado',
            'text' => 'El correo electrónico ya está registrado.'
        ];

        header("Location: ../views/clientes/index.php");

        exit;
    }


    $resultado = $clienteModel->registrar([
        'nombre' => $nombre,
        'telefono' => $telefono,
        'correo' => $correo
    ]);


    $_SESSION['alert'] = $resultado === true

        ? [
            'icon' => 'success',
            'title' => '¡Cliente registrado!',
            'text' => 'El cliente fue registrado correctamente.'
        ]

        : [
            'icon' => 'error',
            'title' => 'Error',
            'text' => $resultado
        ];


    header("Location: ../views/clientes/index.php");

    exit;
}


// ============================================================
// EDITAR
// ============================================================

if ($accion === 'editar') {

    $id_cliente = (int)($_POST['id_cliente'] ?? 0);

    $nombre = trim($_POST['nombre'] ?? '');

    $telefono = trim($_POST['telefono'] ?? '');

    $correo = trim($_POST['correo'] ?? '');


    if ($id_cliente <= 0 || $nombre === '') {

        $_SESSION['alert'] = [
            'icon' => 'warning',
            'title' => 'Datos incompletos',
            'text' => 'El nombre del cliente es obligatorio.'
        ];

        header("Location: ../views/clientes/index.php");

        exit;
    }


    if (
        $correo !== '' &&
        !filter_var($correo, FILTER_VALIDATE_EMAIL)
    ) {

        $_SESSION['alert'] = [
            'icon' => 'warning',
            'title' => 'Correo inválido',
            'text' => 'El correo electrónico no es válido.'
        ];

        header("Location: ../views/clientes/index.php");

        exit;
    }


    $resultado = $clienteModel->editarCompleto(
        $id_cliente,
        $nombre,
        $telefono,
        $correo
    );


    $_SESSION['alert'] = $resultado === true

        ? [
            'icon' => 'success',
            'title' => '¡Actualizado!',
            'text' => 'Cliente actualizado correctamente.'
        ]

        : [
            'icon' => 'error',
            'title' => 'Error',
            'text' => $resultado
        ];


    header("Location: ../views/clientes/index.php");

    exit;
}


// ============================================================
// ACTIVAR / DESACTIVAR
// ============================================================

if ($accion === 'toggleEstado') {

    $id = (int)($_GET['id'] ?? 0);


    if ($id <= 0) {

        $_SESSION['alert'] = [
            'icon' => 'error',
            'title' => 'Error',
            'text' => 'Cliente no válido.'
        ];

        header("Location: ../views/clientes/index.php");

        exit;
    }


    $cliente = $clienteModel->obtenerPorId($id);


    if (!$cliente) {

        $_SESSION['alert'] = [
            'icon' => 'error',
            'title' => 'Error',
            'text' => 'Cliente no encontrado.'
        ];

        header("Location: ../views/clientes/index.php");

        exit;
    }


    $estadoActual = strtolower(
        (string)($cliente['estado'] ?? 'activo')
    );


    $nuevoEstado =
        $estadoActual === 'activo'
            ? 'inactivo'
            : 'activo';


    $resultado = $clienteModel->cambiarEstado(
        $id,
        $nuevoEstado
    );


    $_SESSION['alert'] = $resultado === true

        ? [
            'icon' => 'success',
            'title' => 'Estado actualizado',
            'text' => 'El cliente fue ' .
                (
                    $nuevoEstado === 'activo'
                        ? 'activado.'
                        : 'desactivado.'
                )
        ]

        : [
            'icon' => 'error',
            'title' => 'Error',
            'text' => $resultado
        ];


    header("Location: ../views/clientes/index.php");

    exit;
}


// ============================================================
// ELIMINAR
// ============================================================

if ($accion === 'eliminar') {

    $id = (int)($_GET['id'] ?? 0);


    if ($id <= 0) {

        $_SESSION['alert'] = [
            'icon' => 'error',
            'title' => 'Error',
            'text' => 'Cliente no válido.'
        ];

        header("Location: ../views/clientes/index.php");

        exit;
    }


    $resultado = $clienteModel->eliminar($id);


    $_SESSION['alert'] = $resultado === true

        ? [
            'icon' => 'success',
            'title' => 'Cliente eliminado',
            'text' => 'El cliente fue eliminado correctamente.'
        ]

        : [
            'icon' => 'error',
            'title' => 'No se puede eliminar',
            'text' => $resultado
        ];


    header("Location: ../views/clientes/index.php");

    exit;
}


// ============================================================
// ACCIÓN NO VÁLIDA
// ============================================================

header("Location: ../views/clientes/index.php");

exit;

?>