<?php

class Usuario
{
    private $conn;

    public function __construct($db)
    {
        $this->conn = $db;
    }


    // ============================================================
    // OBTENER TODOS LOS USUARIOS
    // USUARIOS NUEVOS PRIMERO
    // ============================================================

    public function obtenerTodos()
    {
        $sql = "SELECT
                    u.id_usuario,
                    p.id_persona,
                    p.nombre,
                    p.telefono,
                    p.correo,
                    p.estado AS estado_persona,
                    u.estado AS estado_usuario,
                    r.id_rol,
                    r.nombre AS nombre_rol

                FROM usuario u

                INNER JOIN persona p
                    ON u.id_persona = p.id_persona

                INNER JOIN rol r
                    ON u.id_rol = r.id_rol

                ORDER BY u.id_usuario DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    // ============================================================
    // OBTENER USUARIO POR ID
    // ============================================================

    public function obtenerPorId($id_usuario)
    {
        $sql = "SELECT
                    u.id_usuario,
                    u.estado AS estado_usuario,
                    u.id_rol,
                    u.id_persona,

                    p.nombre,
                    p.telefono,
                    p.correo,
                    p.estado AS estado_persona,

                    r.nombre AS nombre_rol

                FROM usuario u

                INNER JOIN persona p
                    ON u.id_persona = p.id_persona

                INNER JOIN rol r
                    ON u.id_rol = r.id_rol

                WHERE u.id_usuario = ?

                LIMIT 1";

        $stmt = $this->conn->prepare($sql);

        $stmt->execute([
            (int)$id_usuario
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }


    // ============================================================
    // VERIFICAR EMAIL
    // ============================================================

    public function existeEmail($email)
    {
        return $this->existeCorreo($email);
    }


    // ============================================================
    // VERIFICAR CORREO
    // ============================================================

    public function existeCorreo($correo)
    {
        $sql = "SELECT id_persona
                FROM persona
                WHERE correo = ?
                LIMIT 1";

        $stmt = $this->conn->prepare($sql);

        $stmt->execute([
            $correo
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    }


    // ============================================================
    // OBTENER USUARIO POR CORREO
    // ============================================================

    public function obtenerPorEmail($email)
    {
        $sql = "SELECT
                    u.id_usuario,
                    u.`contraseña`,

                    p.id_persona,
                    p.nombre,
                    p.telefono,
                    p.correo AS email,
                    p.estado AS estado_persona,

                    u.estado AS estado_usuario,

                    r.id_rol,
                    r.nombre AS nombre_rol

                FROM usuario u

                INNER JOIN persona p
                    ON u.id_persona = p.id_persona

                INNER JOIN rol r
                    ON u.id_rol = r.id_rol

                WHERE p.correo = ?

                LIMIT 1";

        $stmt = $this->conn->prepare($sql);

        $stmt->execute([
            $email
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }


    // ============================================================
    // REGISTRAR USUARIO
    // ============================================================

    public function registrar($datos)
    {
        try {

            $this->conn->beginTransaction();

            $nombre = trim(
                $datos['nombre'] ?? ''
            );

            $telefono = trim(
                $datos['telefono'] ?? ''
            );

            $correo = trim(
                $datos['correo'] ?? ''
            );

            $password =
                $datos['password'] ?? '';

            $confirmarPassword =
                $datos['confirmar_password'] ?? '';


            if ($nombre === '') {

                $this->conn->rollBack();

                return 'El nombre es obligatorio.';
            }


            if ($correo === '') {

                $this->conn->rollBack();

                return 'El correo es obligatorio.';
            }


            if (!filter_var(
                $correo,
                FILTER_VALIDATE_EMAIL
            )) {

                $this->conn->rollBack();

                return 'El correo electrónico no es válido.';
            }


            if ($password === '') {

                $this->conn->rollBack();

                return 'La contraseña es obligatoria.';
            }


            if ($confirmarPassword === '') {

                $this->conn->rollBack();

                return 'Debe confirmar la contraseña.';
            }


            if ($password !== $confirmarPassword) {

                $this->conn->rollBack();

                return 'Las contraseñas no coinciden.';
            }


            if ($this->existeCorreo($correo)) {

                $this->conn->rollBack();

                return 'El correo ya está registrado.';
            }


            $rol = 1;


            $stmtRol = $this->conn->prepare(
                "SELECT id_rol
                 FROM rol
                 WHERE id_rol = ?
                 LIMIT 1"
            );

            $stmtRol->execute([
                $rol
            ]);


            if (!$stmtRol->fetch(PDO::FETCH_ASSOC)) {

                $this->conn->rollBack();

                return 'No existe el rol Administrador.';
            }


            $sqlPersona = "INSERT INTO persona
                            (
                                nombre,
                                telefono,
                                correo,
                                estado
                            )
                           VALUES
                            (
                                ?,
                                ?,
                                ?,
                                1
                            )";

            $stmtPersona =
                $this->conn->prepare($sqlPersona);

            $stmtPersona->execute([
                $nombre,
                $telefono !== '' ? $telefono : null,
                $correo
            ]);


            $id_persona =
                $this->conn->lastInsertId();


            if (!$id_persona) {

                throw new Exception(
                    'No se pudo crear la persona.'
                );
            }


            $passwordHash =
                password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );


            if (!$passwordHash) {

                throw new Exception(
                    'No se pudo encriptar la contraseña.'
                );
            }


            $sqlUsuario = "INSERT INTO usuario
                            (
                                `contraseña`,
                                id_persona,
                                id_rol,
                                estado
                            )
                           VALUES
                            (
                                ?,
                                ?,
                                ?,
                                1
                            )";

            $stmtUsuario =
                $this->conn->prepare($sqlUsuario);

            $stmtUsuario->execute([
                $passwordHash,
                $id_persona,
                $rol
            ]);


            $this->conn->commit();

            return true;


        } catch (Exception $e) {

            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            return 'Error al registrar: ' .
                $e->getMessage();
        }
    }


    // ============================================================
    // CAMBIAR ESTADO
    // ============================================================

    public function cambiarEstado(
        $id_usuario,
        $estado
    ) {
        try {

            $id_usuario = (int)$id_usuario;
            $estado = (int)$estado;


            if ($id_usuario <= 0) {
                return 'Usuario no válido.';
            }


            if ($estado !== 0 && $estado !== 1) {
                return 'Estado no válido.';
            }


            /*
             * Primero buscamos el usuario.
             * Esto evita depender de una subconsulta
             * al momento de actualizar persona.
             */

            $stmt = $this->conn->prepare(
                "SELECT
                    id_usuario,
                    id_persona,
                    estado
                 FROM usuario
                 WHERE id_usuario = ?
                 LIMIT 1"
            );

            $stmt->execute([
                $id_usuario
            ]);


            $usuario =
                $stmt->fetch(PDO::FETCH_ASSOC);


            if (!$usuario) {

                return 'Usuario no encontrado.';
            }


            $id_persona =
                (int)$usuario['id_persona'];


            /*
             * Actualizamos usuario.
             */

            $stmtUsuario =
                $this->conn->prepare(
                    "UPDATE usuario
                     SET estado = ?
                     WHERE id_usuario = ?"
                );


            $stmtUsuario->execute([
                $estado,
                $id_usuario
            ]);


            /*
             * Actualizamos persona.
             */

            $stmtPersona =
                $this->conn->prepare(
                    "UPDATE persona
                     SET estado = ?
                     WHERE id_persona = ?"
                );


            $stmtPersona->execute([
                $estado,
                $id_persona
            ]);


            /*
             * Verificamos que usuario sí haya quedado
             * con el nuevo estado.
             */

            $stmtVerificar =
                $this->conn->prepare(
                    "SELECT estado
                     FROM usuario
                     WHERE id_usuario = ?
                     LIMIT 1"
                );


            $stmtVerificar->execute([
                $id_usuario
            ]);


            $estadoBD =
                $stmtVerificar->fetchColumn();


            if ((int)$estadoBD !== $estado) {

                return 'No se pudo actualizar el estado del usuario.';
            }


            return true;


        } catch (Exception $e) {

            return 'Error al cambiar estado: ' .
                $e->getMessage();
        }
    }


    // ============================================================
    // EDITAR USUARIO
    // ============================================================

    public function editarCompleto(
        $id,
        $nombre,
        $telefono,
        $rol
    ) {
        try {

            $id = (int)$id;
            $rol = (int)$rol;

            $nombre = trim($nombre);
            $telefono = trim($telefono);


            if ($id <= 0) {
                return 'Usuario no válido.';
            }


            if ($nombre === '') {
                return 'El nombre es obligatorio.';
            }


            if ($rol <= 0) {
                return 'Debe seleccionar un rol.';
            }


            $this->conn->beginTransaction();


            $stmt = $this->conn->prepare(
                "SELECT id_persona
                 FROM usuario
                 WHERE id_usuario = ?
                 LIMIT 1"
            );

            $stmt->execute([
                $id
            ]);


            $data =
                $stmt->fetch(PDO::FETCH_ASSOC);


            if (!$data) {

                $this->conn->rollBack();

                return 'Usuario no encontrado.';
            }


            $id_persona =
                (int)$data['id_persona'];


            $stmtPersona =
                $this->conn->prepare(
                    "UPDATE persona
                     SET nombre = ?,
                         telefono = ?
                     WHERE id_persona = ?"
                );


            $stmtPersona->execute([
                $nombre,
                $telefono !== '' ? $telefono : null,
                $id_persona
            ]);


            $stmtUsuario =
                $this->conn->prepare(
                    "UPDATE usuario
                     SET id_rol = ?
                     WHERE id_usuario = ?"
                );


            $stmtUsuario->execute([
                $rol,
                $id
            ]);


            $this->conn->commit();

            return true;


        } catch (Exception $e) {

            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            return 'Error al editar: ' .
                $e->getMessage();
        }
    }


    // ============================================================
    // CAMBIAR CONTRASEÑA
    // ============================================================

    public function cambiarPassword(
        $id_usuario,
        $password
    ) {
        try {

            $id_usuario = (int)$id_usuario;


            if ($id_usuario <= 0) {
                return 'Usuario no válido.';
            }


            if ($password === '') {
                return true;
            }


            $hash =
                password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );


            $stmt =
                $this->conn->prepare(
                    "UPDATE usuario
                     SET `contraseña` = ?
                     WHERE id_usuario = ?"
                );


            $stmt->execute([
                $hash,
                $id_usuario
            ]);


            return true;


        } catch (Exception $e) {

            return 'Error al cambiar contraseña: ' .
                $e->getMessage();
        }
    }


    // ============================================================
    // ELIMINAR USUARIO
    // ============================================================

    public function eliminarCompleto($id_usuario)
    {
        try {

            $id_usuario = (int)$id_usuario;


            if ($id_usuario <= 0) {
                return 'Usuario no válido.';
            }


            $stmt = $this->conn->prepare(
                "SELECT id_persona
                 FROM usuario
                 WHERE id_usuario = ?
                 LIMIT 1"
            );


            $stmt->execute([
                $id_usuario
            ]);


            $data =
                $stmt->fetch(PDO::FETCH_ASSOC);


            if (!$data) {
                return 'Usuario no encontrado.';
            }


            $id_persona =
                (int)$data['id_persona'];


            try {

                $stmtCompra =
                    $this->conn->prepare(
                        "SELECT COUNT(*) AS total
                         FROM compra
                         WHERE id_usuario = ?"
                    );


                $stmtCompra->execute([
                    $id_usuario
                ]);


                $compra =
                    $stmtCompra->fetch(PDO::FETCH_ASSOC);


                if (
                    $compra &&
                    (int)$compra['total'] > 0
                ) {

                    return 'No se puede eliminar este usuario porque tiene compras registradas. Puedes desactivarlo en lugar de eliminarlo.';
                }

            } catch (Exception $e) {

                // Si compra no existe, continuar.
            }


            $this->conn->beginTransaction();


            $stmtUsuario =
                $this->conn->prepare(
                    "DELETE FROM usuario
                     WHERE id_usuario = ?"
                );


            $stmtUsuario->execute([
                $id_usuario
            ]);


            $stmtPersona =
                $this->conn->prepare(
                    "DELETE FROM persona
                     WHERE id_persona = ?"
                );


            $stmtPersona->execute([
                $id_persona
            ]);


            $this->conn->commit();


            return true;


        } catch (Exception $e) {

            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            return 'Error al eliminar: ' .
                $e->getMessage();
        }
    }


    // ============================================================
    // OBTENER ROLES
    // ============================================================

    public function obtenerRoles()
    {
        $stmt =
            $this->conn->prepare(
                "SELECT
                    id_rol,
                    nombre
                 FROM rol
                 ORDER BY nombre ASC"
            );


        $stmt->execute();


        return $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );
    }
}

?>