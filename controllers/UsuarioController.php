<?php

session_start();

require_once __DIR__ . '/../config/databse.php';
require_once __DIR__ . '/../models/usuario.php';


// ============================================================
// DETECTAR SI VIENE DESDE EL PANEL DE ADMINISTRADOR
// ============================================================

$desdeAdmin = isset($_POST['desde_admin']) &&
              $_POST['desde_admin'] == '1';


// ============================================================
// SI VIENE DESDE ADMINISTRADOR
// VERIFICAR QUE REALMENTE SEA ADMINISTRADOR
// ============================================================

if ($desdeAdmin) {

    if (
        !isset($_SESSION['usuario']) ||
        !isset($_SESSION['usuario']['rol']) ||
        strtolower(trim($_SESSION['usuario']['rol'])) !== 'administrador'
    ) {

        header(
            'Location: ../views/usuarios/login.php'
        );

        exit;
    }
}


// ============================================================
// VERIFICAR POST
// ============================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    if ($desdeAdmin) {

        header(
            'Location: ../views/dashboard/admin.php'
        );

    } else {

        header(
            'Location: ../views/usuarios/registre.php'
        );
    }

    exit;
}


// ============================================================
// RECIBIR DATOS
// ============================================================

$datos = [

    'nombre' => trim(
        $_POST['nombre'] ?? ''
    ),

    'telefono' => trim(
        $_POST['telefono'] ?? ''
    ),

    'correo' => trim(
        $_POST['correo'] ?? ''
    ),

    'password' => $_POST['password'] ?? '',

    'confirmar_password' =>
        $_POST['confirmar_password'] ?? ''
];


// ============================================================
// VALIDAR TÉRMINOS
// SOLO PARA REGISTRO PÚBLICO
// ============================================================

if (!$desdeAdmin && !isset($_POST['terminos'])) {

    $_SESSION['registro_alert'] = [

        'icon'  => 'warning',

        'title' => 'Términos y condiciones',

        'text'  => 'Debe aceptar los términos y condiciones.'
    ];

    header(
        'Location: ../views/usuarios/registre.php'
    );

    exit;
}


// ============================================================
// CONEXIÓN
// ============================================================

try {

    $database = new Database();

    $db = $database->conectar();

    $usuarioModel = new Usuario($db);

} catch (Exception $e) {

    // ========================================================
    // ERROR DESDE ADMIN
    // ========================================================

    if ($desdeAdmin) {

        $_SESSION['alert'] = [

            'icon'  => 'error',

            'title' => 'Error del sistema',

            'text'  => 'No se pudo conectar con la base de datos.'
        ];

        header(
            'Location: ../views/dashboard/admin.php'
        );

        exit;
    }


    // ========================================================
    // ERROR REGISTRO PÚBLICO
    // ========================================================

    $_SESSION['registro_alert'] = [

        'icon'  => 'error',

        'title' => 'Error del sistema',

        'text'  => 'No se pudo conectar con la base de datos.'
    ];

    header(
        'Location: ../views/usuarios/registre.php'
    );

    exit;
}


// ============================================================
// REGISTRAR USUARIO
// ============================================================

$resultado = $usuarioModel->registrar($datos);


// ============================================================
// REGISTRO CORRECTO
// ============================================================

if ($resultado === true) {


    // ========================================================
    // SI LO CREÓ EL ADMINISTRADOR
    // ========================================================

    if ($desdeAdmin) {

        $_SESSION['alert'] = [

            'icon'  => 'success',

            'title' => '¡Usuario creado!',

            'text'  => 'El usuario fue creado correctamente.'
        ];

        // IMPORTANTE:
        // NO cerramos la sesión.
        // NO enviamos al login.
        // NO enviamos al registro.

        header(
            'Location: ../views/dashboard/admin.php'
        );

        exit;
    }


    // ========================================================
    // SI ES REGISTRO PÚBLICO
    // ========================================================

    $_SESSION['registro_alert'] = [

        'icon'  => 'success',

        'title' => 'Cuenta creada',

        'text'  => 'Tu cuenta fue creada correctamente. '
                . 'Ahora puedes iniciar sesión.'
    ];

    header(
        'Location: ../views/usuarios/registre.php'
    );

    exit;
}


// ============================================================
// ERROR AL CREAR USUARIO
// ============================================================


// ============================================================
// ERROR DESDE ADMINISTRADOR
// ============================================================

if ($desdeAdmin) {

    $_SESSION['alert'] = [

        'icon'  => 'error',

        'title' => 'No se pudo crear el usuario',

        'text'  => $resultado
    ];

    header(
        'Location: ../views/dashboard/admin.php'
    );

    exit;
}


// ============================================================
// ERROR REGISTRO PÚBLICO
// ============================================================

$_SESSION['registro_alert'] = [

    'icon'  => 'error',

    'title' => 'No se pudo crear la cuenta',

    'text'  => $resultado
];


header(
    'Location: ../views/usuarios/registre.php'
);

exit;

?>