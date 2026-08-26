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


// ============================================================
// RUTAS DE REGRESO SEGÚN EL ROL
// ============================================================

const VISTA_CLIENTES_ADMIN    = '../views/clientes/index.php';
const VISTA_CLIENTES_VENDEDOR = '../views/vendedor/clientes.php';


// ============================================================
// FUNCIÓN PARA MOSTRAR ALERTA Y REGRESAR
// (admin vuelve a su panel, vendedor vuelve al suyo, según
// el rol guardado en la sesión — no depende del Referer del
// navegador, que puede venir vacío por políticas de privacidad)
// ============================================================

function regresarConAlerta($icon, $title, $text)
{
    $_SESSION['alert'] = [
        'icon'  => $icon,
        'title' => $title,
        'text'  => $text
    ];

    $rol = strtolower(trim($_SESSION['usuario']['rol'] ?? ''));

    $destino = $rol === 'vendedor'
        ? VISTA_CLIENTES_VENDEDOR
        : VISTA_CLIENTES_ADMIN;

    header("Location: " . $destino);

    exit;
}


try {

    $database = new Database();

    $db = $database->conectar();

    $clienteModel = new Cliente($db);

} catch (Exception $e) {

    regresarConAlerta(
        'error',
        'Error',
        'No se pudo conectar con la base de datos.'
    );
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

        regresarConAlerta(
            'warning',
            'Campo obligatorio',
            'El nombre del cliente es obligatorio.'
        );
    }


    if (
        $correo !== '' &&
        !filter_var($correo, FILTER_VALIDATE_EMAIL)
    ) {

        regresarConAlerta(
            'warning',
            'Correo inválido',
            'El formato del correo electrónico no es válido.'
        );
    }


    if (
        $correo !== '' &&
        $clienteModel->existeCorreo($correo)
    ) {

        regresarConAlerta(
            'error',
            'Correo duplicado',
            'El correo electrónico ya está registrado.'
        );
    }


    $resultado = $clienteModel->registrar([
        'nombre' => $nombre,
        'telefono' => $telefono,
        'correo' => $correo
    ]);


    if ($resultado === true) {

        regresarConAlerta(
            'success',
            '¡Cliente registrado!',
            'El cliente fue registrado correctamente.'
        );
    }

    regresarConAlerta(
        'error',
        'Error',
        $resultado
    );
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

        regresarConAlerta(
            'warning',
            'Datos incompletos',
            'El nombre del cliente es obligatorio.'
        );
    }


    if (
        $correo !== '' &&
        !filter_var($correo, FILTER_VALIDATE_EMAIL)
    ) {

        regresarConAlerta(
            'warning',
            'Correo inválido',
            'El correo electrónico no es válido.'
        );
    }


    $resultado = $clienteModel->editarCompleto(
        $id_cliente,
        $nombre,
        $telefono,
        $correo
    );


    if ($resultado === true) {

        regresarConAlerta(
            'success',
            '¡Actualizado!',
            'Cliente actualizado correctamente.'
        );
    }

    regresarConAlerta(
        'error',
        'Error',
        $resultado
    );
}


// ============================================================
// ACTIVAR / DESACTIVAR
// ============================================================

if ($accion === 'toggleEstado') {

    $id = (int)($_GET['id'] ?? 0);


    if ($id <= 0) {

        regresarConAlerta(
            'error',
            'Error',
            'Cliente no válido.'
        );
    }


    $cliente = $clienteModel->obtenerPorId($id);


    if (!$cliente) {

        regresarConAlerta(
            'error',
            'Error',
            'Cliente no encontrado.'
        );
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


    if ($resultado === true) {

        regresarConAlerta(
            'success',
            'Estado actualizado',
            'El cliente fue ' .
                (
                    $nuevoEstado === 'activo'
                        ? 'activado.'
                        : 'desactivado.'
                )
        );
    }

    regresarConAlerta(
        'error',
        'Error',
        $resultado
    );
}


// ============================================================
// ELIMINAR
// ============================================================

if ($accion === 'eliminar') {

    $id = (int)($_GET['id'] ?? 0);


    if ($id <= 0) {

        regresarConAlerta(
            'error',
            'Error',
            'Cliente no válido.'
        );
    }


    $resultado = $clienteModel->eliminar($id);


    if ($resultado === true) {

        regresarConAlerta(
            'success',
            'Cliente eliminado',
            'El cliente fue eliminado correctamente.'
        );
    }

    regresarConAlerta(
        'error',
        'No se puede eliminar',
        $resultado
    );
}


// ============================================================
// ACCIÓN NO VÁLIDA
// ============================================================

regresarConAlerta(
    'error',
    'Acción no válida',
    'La acción solicitada no existe.'
);

?>