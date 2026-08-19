<?php

session_start();


// ============================================================
// VERIFICAR ADMINISTRADOR
// ============================================================

if (
    !isset($_SESSION['usuario']) ||
    ($_SESSION['usuario']['rol'] ?? '') !== 'Administrador'
) {

    header('Location: ../views/usuarios/login.php');
    exit;
}


// ============================================================
// CONEXIÓN
// ============================================================

require_once __DIR__ . '/../config/databse.php';
require_once __DIR__ . '/../models/usuario.php';


try {

    $database = new Database();

    $db = $database->conectar();

    $usuarioModel = new Usuario($db);

} catch (Exception $e) {

    $_SESSION['alert'] = [
        'icon' => 'error',
        'title' => 'Error',
        'text' => 'No se pudo conectar con la base de datos.'
    ];

    header('Location: ../views/dashboard/admin.php');
    exit;
}


// ============================================================
// RUTA CORRECTA DEL ADMIN
// ============================================================

$rutaAdmin = '../views/dashboard/admin.php';


// ============================================================
// ACCIÓN
// ============================================================

$accion = $_GET['accion'] ?? '';


// ============================================================
// ACTIVAR / DESACTIVAR
// ============================================================

if ($accion === 'toggleEstado') {

    $id = isset($_GET['id'])
        ? (int) $_GET['id']
        : 0;


    if ($id <= 0) {

        $_SESSION['alert'] = [
            'icon' => 'error',
            'title' => 'Error',
            'text' => 'Usuario no válido.'
        ];

        header("Location: $rutaAdmin");
        exit;
    }


    // --------------------------------------------------------
    // OBTENER USUARIO
    // --------------------------------------------------------

    $usuario = $usuarioModel->obtenerPorId($id);


    if (!$usuario) {

        $_SESSION['alert'] = [
            'icon' => 'error',
            'title' => 'Error',
            'text' => 'El usuario no existe.'
        ];

        header("Location: $rutaAdmin");
        exit;
    }


    // --------------------------------------------------------
    // ESTADO ACTUAL
    // --------------------------------------------------------

    $estadoActual = (int) (
        $usuario['estado_usuario']
        ?? $usuario['estado']
        ?? 0
    );


    // 1 = activo
    // 0 = inactivo

    $nuevoEstado = ($estadoActual === 1)
        ? 0
        : 1;


    // --------------------------------------------------------
    // CAMBIAR ESTADO
    // --------------------------------------------------------

    $resultado = $usuarioModel->cambiarEstado(
        $id,
        $nuevoEstado
    );


    if ($resultado === true) {

        $_SESSION['alert'] = [

            'icon' => 'success',

            'title' => ($nuevoEstado === 1)
                ? 'Usuario activado'
                : 'Usuario desactivado',

            'text' => ($nuevoEstado === 1)
                ? 'El usuario fue activado correctamente.'
                : 'El usuario fue desactivado correctamente.'
        ];

    } else {

        $_SESSION['alert'] = [

            'icon' => 'error',

            'title' => 'Error',

            'text' => is_string($resultado)
                ? $resultado
                : 'No se pudo cambiar el estado del usuario.'
        ];
    }


    header("Location: $rutaAdmin");
    exit;
}


// ============================================================
// ELIMINAR
// ============================================================

if ($accion === 'eliminar') {

    $id = isset($_GET['id'])
        ? (int) $_GET['id']
        : 0;


    if ($id <= 0) {

        $_SESSION['alert'] = [
            'icon' => 'error',
            'title' => 'Error',
            'text' => 'Usuario no válido.'
        ];

        header("Location: $rutaAdmin");
        exit;
    }


    // --------------------------------------------------------
    // ELIMINAR
    // --------------------------------------------------------

    $resultado = $usuarioModel->eliminarCompleto($id);


    if ($resultado === true) {

        $_SESSION['alert'] = [

            'icon' => 'success',

            'title' => 'Usuario eliminado',

            'text' => 'El usuario fue eliminado correctamente.'
        ];

    } else {

        $_SESSION['alert'] = [

            'icon' => 'error',

            'title' => 'No se puede eliminar',

            'text' => is_string($resultado)
                ? $resultado
                : 'No se pudo eliminar el usuario.'
        ];
    }


    header("Location: $rutaAdmin");
    exit;
}


// ============================================================
// EDITAR
// ============================================================

if ($accion === 'editar') {

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

        $_SESSION['alert'] = [
            'icon' => 'warning',
            'title' => 'Solicitud no válida',
            'text' => 'La edición debe realizarse mediante POST.'
        ];

        header("Location: $rutaAdmin");
        exit;
    }


    // --------------------------------------------------------
    // DATOS
    // --------------------------------------------------------

    $id = (int) (
        $_POST['id_usuario']
        ?? 0
    );


    $nombre = trim(
        $_POST['nombre']
        ?? ''
    );


    $telefono = trim(
        $_POST['telefono']
        ?? ''
    );


    $rol = (int) (
        $_POST['rol']
        ?? 0
    );


    $password =
        $_POST['password']
        ?? '';


    // --------------------------------------------------------
    // VALIDAR ID
    // --------------------------------------------------------

    if ($id <= 0) {

        $_SESSION['alert'] = [
            'icon' => 'error',
            'title' => 'Error',
            'text' => 'El usuario no es válido.'
        ];

        header("Location: $rutaAdmin");
        exit;
    }


    // --------------------------------------------------------
    // VALIDAR NOMBRE
    // --------------------------------------------------------

    if ($nombre === '') {

        $_SESSION['alert'] = [
            'icon' => 'error',
            'title' => 'Error',
            'text' => 'El nombre es obligatorio.'
        ];

        header("Location: $rutaAdmin");
        exit;
    }


    // --------------------------------------------------------
    // VALIDAR ROL
    // --------------------------------------------------------

    if ($rol <= 0) {

        $_SESSION['alert'] = [
            'icon' => 'error',
            'title' => 'Error',
            'text' => 'Debes seleccionar un rol.'
        ];

        header("Location: $rutaAdmin");
        exit;
    }


    // --------------------------------------------------------
    // EDITAR DATOS
    // --------------------------------------------------------

    $resultado = $usuarioModel->editarCompleto(
        $id,
        $nombre,
        $telefono,
        $rol
    );


    if ($resultado !== true) {

        $_SESSION['alert'] = [

            'icon' => 'error',

            'title' => 'Error al editar',

            'text' => is_string($resultado)
                ? $resultado
                : 'No se pudieron actualizar los datos.'
        ];

        header("Location: $rutaAdmin");
        exit;
    }


    // --------------------------------------------------------
    // CAMBIAR CONTRASEÑA
    // --------------------------------------------------------

    if ($password !== '') {

        $resultadoPassword =
            $usuarioModel->cambiarPassword(
                $id,
                $password
            );


        if ($resultadoPassword !== true) {

            $_SESSION['alert'] = [

                'icon' => 'error',

                'title' => 'Error',

                'text' => is_string($resultadoPassword)
                    ? $resultadoPassword
                    : 'No se pudo cambiar la contraseña.'
            ];

            header("Location: $rutaAdmin");
            exit;
        }
    }


    // --------------------------------------------------------
    // ÉXITO
    // --------------------------------------------------------

    $_SESSION['alert'] = [

        'icon' => 'success',

        'title' => 'Usuario actualizado',

        'text' =>
            'Los datos del usuario fueron actualizados correctamente.'
    ];


    header("Location: $rutaAdmin");
    exit;
}


// ============================================================
// ACCIÓN NO VÁLIDA
// ============================================================

$_SESSION['alert'] = [

    'icon' => 'warning',

    'title' => 'Acción no válida',

    'text' => 'La acción solicitada no existe.'
];


header("Location: $rutaAdmin");
exit;

?>