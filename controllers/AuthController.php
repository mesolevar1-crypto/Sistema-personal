<?php

session_start();

require_once __DIR__ . '/../config/databse.php';
require_once __DIR__ . '/../models/usuario.php';

class AuthController
{
    public function login()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ../views/usuarios/login.php');
            exit;
        }

        $correo = trim($_POST['correo'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($correo === '' || $password === '') {
            $_SESSION['alert'] = [
                'icon' => 'warning',
                'title' => 'Campos incompletos',
                'text' => 'Debe ingresar correo y contraseña.'
            ];

            header('Location: ../views/usuarios/login.php');
            exit;
        }

        /* =========================
           CONTROL DE BLOQUEO
        ========================= */

        $intentos = $_SESSION['login_intentos'] ?? 0;
        $bloqueado = $_SESSION['login_bloqueado'] ?? false;
        $tiempoBloqueo = $_SESSION['login_tiempo'] ?? 0;

        if ($bloqueado) {

            $transcurrido = time() - $tiempoBloqueo;

            if ($transcurrido < 120) {

                $restante = 120 - $transcurrido;

                $_SESSION['alert'] = [
                    'icon' => 'error',
                    'title' => 'Acceso bloqueado',
                    'text' => 'Demasiados intentos fallidos. Espere '
                            . $restante
                            . ' segundos.'
                ];

                header('Location: ../views/usuarios/login.php');
                exit;
            }

            /* YA PASARON LOS 2 MINUTOS */

            $_SESSION['login_intentos'] = 0;
            $_SESSION['login_bloqueado'] = false;
            $_SESSION['login_tiempo'] = 0;
        }

        /* =========================
           CONEXIÓN
        ========================= */

        try {

            $database = new Database();
            $db = $database->conectar();

            $usuarioModel = new Usuario($db);

        } catch (Exception $e) {

            $_SESSION['alert'] = [
                'icon' => 'error',
                'title' => 'Error del sistema',
                'text' => 'No se pudo conectar con la base de datos.'
            ];

            header('Location: ../views/usuarios/login.php');
            exit;
        }

        /* =========================
           BUSCAR USUARIO
        ========================= */

        $usuario = $usuarioModel->obtenerPorEmail($correo);

        if (!$usuario) {

            $this->falloLogin(
                'Correo no encontrado',
                'El correo no está registrado.'
            );

            exit;
        }

        /* =========================
           VERIFICAR ESTADO
        ========================= */

        if (
            !isset($usuario['estado_usuario']) ||
            (int)$usuario['estado_usuario'] !== 1
        ) {

            $_SESSION['alert'] = [
                'icon' => 'error',
                'title' => 'Cuenta inactiva',
                'text' => 'Tu cuenta está desactivada. Contacta al administrador.'
            ];

            header('Location: ../views/usuarios/login.php');
            exit;
        }

        if (
            !isset($usuario['estado_persona']) ||
            (int)$usuario['estado_persona'] !== 1
        ) {

            $_SESSION['alert'] = [
                'icon' => 'error',
                'title' => 'Cuenta inactiva',
                'text' => 'Tu cuenta está desactivada. Contacta al administrador.'
            ];

            header('Location: ../views/usuarios/login.php');
            exit;
        }

        /* =========================
           CONTRASEÑA
        ========================= */

        if (
            !isset($usuario['contraseña']) ||
            !password_verify(
                $password,
                $usuario['contraseña']
            )
        ) {

            $this->falloLogin(
                'Contraseña incorrecta',
                'La contraseña no coincide.'
            );

            exit;
        }

        /* =========================
           LOGIN CORRECTO
        ========================= */

        $_SESSION['login_intentos'] = 0;
        $_SESSION['login_bloqueado'] = false;
        $_SESSION['login_tiempo'] = 0;

        session_regenerate_id(true);

        $_SESSION['usuario'] = [
            'id_usuario' => $usuario['id_usuario'],
            'id_persona' => $usuario['id_persona'],
            'nombre' => $usuario['nombre'],
            'email' => $usuario['email'],
            'telefono' => $usuario['telefono'],
            'rol_id' => $usuario['id_rol'],
            'rol' => $usuario['nombre_rol']
        ];

        /* =========================
           REDIRECCIÓN
        ========================= */

        if ((int)$usuario['id_rol'] === 1) {

            header(
                'Location: ../views/inicio/index.php'
            );

        } else {

            header(
                'Location: ../views/dashboard/vendedor.php'
            );
        }

        exit;
    }


    /* =========================
       LOGIN FALLIDO
    ========================= */

    private function falloLogin($titulo, $mensaje)
    {
        $_SESSION['login_intentos'] =
            ($_SESSION['login_intentos'] ?? 0) + 1;

        $intentos = $_SESSION['login_intentos'];
        $maxIntentos = 3;

        if ($intentos >= $maxIntentos) {

            $_SESSION['login_bloqueado'] = true;
            $_SESSION['login_tiempo'] = time();

            $_SESSION['alert'] = [
                'icon' => 'error',
                'title' => 'Acceso bloqueado',
                'text' => 'Has superado los 3 intentos fallidos. Espera 2 minutos.'
            ];

        } else {

            $restantes = $maxIntentos - $intentos;

            $_SESSION['alert'] = [
                'icon' => 'error',
                'title' => $titulo,
                'text' => $mensaje .
                        ' Te quedan ' .
                        $restantes .
                        ' intento(s).'
            ];
        }

        header(
            'Location: ../views/usuarios/login.php'
        );

        exit;
    }


    /* =========================
       CERRAR SESIÓN
    ========================= */

    public function logout()
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {

            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();

        header(
            'Location: ../views/usuarios/login.php'
        );

        exit;
    }
}


/* =========================
   EJECUTAR
========================= */

$controller = new AuthController();

$accion = $_GET['accion'] ?? 'login';

if ($accion === 'logout') {

    $controller->logout();

} else {

    $controller->login();
}

?>