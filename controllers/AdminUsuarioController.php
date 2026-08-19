<?php

session_start();

require_once __DIR__ . '/../config/databse.php';
require_once __DIR__ . '/../models/usuario.php';


/* =====================================================
   VERIFICAR SESIÓN
===================================================== */

if (!isset($_SESSION['usuario'])) {

    header("Location: ../views/usuarios/login.php");
    exit;
}


/* =====================================================
   VERIFICAR ADMINISTRADOR
===================================================== */

if (
    !isset($_SESSION['usuario']['rol']) ||
    strtolower(trim($_SESSION['usuario']['rol'])) !== 'administrador'
) {

    header("Location: ../views/usuarios/login.php");
    exit;
}


/* =====================================================
   CONEXIÓN
===================================================== */

$database = new Database();

$db = $database->conectar();

$usuarioModel = new Usuario($db);


$accion = $_GET['accion'] ?? '';


switch ($accion) {


    /* =====================================================
       EDITAR
    ===================================================== */

    case 'editar':

        $id = intval($_POST['id_usuario'] ?? 0);

        $nombre = trim(
            $_POST['nombre'] ?? ''
        );

        $telefono = trim(
            $_POST['telefono'] ?? ''
        );

        $rol = intval(
            $_POST['rol'] ?? 0
        );


        if (
            $id <= 0 ||
            $nombre === '' ||
            $rol <= 0
        ) {

            $_SESSION['alert'] = [

                'icon' => 'warning',

                'title' => 'Datos inválidos',

                'text' => 'Verifica los datos del usuario.'

            ];

        } else {


            $resultado =
                $usuarioModel->editarCompleto(

                    $id,

                    $nombre,

                    $telefono,

                    $rol

                );


            $_SESSION['alert'] = [

                'icon' =>
                    $resultado === true
                    ? 'success'
                    : 'error',

                'title' =>
                    $resultado === true
                    ? '¡Usuario actualizado!'
                    : 'Error',

                'text' =>
                    $resultado === true
                    ? 'Los datos fueron actualizados correctamente.'
                    : $resultado

            ];

        }


        header(
            "Location: ../views/dashboard/admin.php"
        );

        exit;



    /* =====================================================
       ACTIVAR / DESACTIVAR
    ===================================================== */

    case 'toggleEstado':


        /*
         * ID DEL USUARIO
         */

        $id = intval(
            $_GET['id'] ?? 0
        );


        /*
         * ESTADO ACTUAL
         *
         * Puede llegar como:
         *
         * activo
         * inactivo
         * 1
         * 0
         */

        $estadoActual =
            strtolower(
                trim(
                    $_GET['estado'] ?? 'activo'
                )
            );


        /*
         * VALIDAR ID
         */

        if ($id <= 0) {

            $_SESSION['alert'] = [

                'icon' => 'error',

                'title' => 'Error',

                'text' => 'Usuario no válido.'

            ];


            header(
                "Location: ../views/dashboard/admin.php"
            );

            exit;
        }


        /*
         * NORMALIZAR ESTADO ACTUAL
         */

        if (
            $estadoActual === '1' ||
            $estadoActual === 'activo'
        ) {

            $estadoActual = 'activo';

        } else {

            $estadoActual = 'inactivo';

        }


        /*
         * NO PERMITIR DESACTIVAR
         * LA PROPIA CUENTA
         */

        if (
            $id ===
            intval(
                $_SESSION['usuario']['id_usuario']
            )
            &&
            $estadoActual === 'activo'
        ) {

            $_SESSION['alert'] = [

                'icon' => 'warning',

                'title' => 'Acción no permitida',

                'text' =>
                    'No puedes desactivar tu propia cuenta.'

            ];


            header(
                "Location: ../views/dashboard/admin.php"
            );

            exit;
        }


        /*
         * CAMBIAR ESTADO
         *
         * ACTIVO -> INACTIVO
         *
         * INACTIVO -> ACTIVO
         */

        if ($estadoActual === 'activo') {

            $nuevoEstado = 'inactivo';

        } else {

            $nuevoEstado = 'activo';

        }


        /*
         * ACTUALIZAR EN LA BASE DE DATOS
         */

        $resultado =
            $usuarioModel->cambiarEstado(

                $id,

                $nuevoEstado

            );


        /*
         * MOSTRAR RESULTADO
         */

        if ($resultado === true) {

            if ($nuevoEstado === 'activo') {

                $_SESSION['alert'] = [

                    'icon' => 'success',

                    'title' => '¡Usuario activado!',

                    'text' =>
                        'El usuario ahora está activo.'

                ];

            } else {

                $_SESSION['alert'] = [

                    'icon' => 'success',

                    'title' => '¡Usuario desactivado!',

                    'text' =>
                        'El usuario ahora está inactivo.'

                ];

            }

        } else {

            $_SESSION['alert'] = [

                'icon' => 'error',

                'title' => 'Error',

                'text' => $resultado

            ];

        }


        /*
         * VOLVER AL PANEL
         */

        header(
            "Location: ../views/dashboard/admin.php"
        );

        exit;



    /* =====================================================
       ELIMINAR
    ===================================================== */

    case 'eliminar':


        $id = intval(
            $_GET['id'] ?? 0
        );


        if ($id <= 0) {


            $_SESSION['alert'] = [

                'icon' => 'error',

                'title' => 'Error',

                'text' => 'Usuario no válido.'

            ];


        } elseif (

            $id ===
            intval(
                $_SESSION['usuario']['id_usuario']
            )

        ) {


            $_SESSION['alert'] = [

                'icon' => 'warning',

                'title' => 'Acción no permitida',

                'text' =>
                    'No puedes eliminar tu propia cuenta.'

            ];


        } else {


            $resultado =
                $usuarioModel->eliminarCompleto(
                    $id
                );


            $_SESSION['alert'] = [

                'icon' =>
                    $resultado === true
                    ? 'success'
                    : 'error',

                'title' =>
                    $resultado === true
                    ? '¡Usuario eliminado!'
                    : 'Error',

                'text' =>
                    $resultado === true
                    ? 'El usuario fue eliminado correctamente.'
                    : $resultado

            ];

        }


        header(
            "Location: ../views/dashboard/admin.php"
        );

        exit;



    /* =====================================================
       LISTAR
    ===================================================== */

    case 'listar':


        $usuarios =
            $usuarioModel->obtenerTodos();


        header(
            'Content-Type: application/json'
        );


        echo json_encode(
            $usuarios
        );


        exit;



    /* =====================================================
       DEFAULT
    ===================================================== */

    default:


        header(
            "Location: ../views/dashboard/admin.php"
        );

        exit;

}

?>