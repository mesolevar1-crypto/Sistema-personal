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

                ORDER BY p.nombre ASC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
                WHERE correo = :correo
                LIMIT 1";

        $stmt = $this->conn->prepare($sql);

        $stmt->bindValue(
            ':correo',
            $correo,
            PDO::PARAM_STR
        );

        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    }


    // ============================================================
    // OBTENER USUARIO POR CORREO
    // ============================================================
    //
    // EL LOGIN UTILIZA:
    //
    // correo
    // contraseña
    //
    // El correo está en persona.
    // La contraseña está en usuario.
    //
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

                WHERE p.correo = :email

                LIMIT 1";

        $stmt = $this->conn->prepare($sql);

        $stmt->bindValue(
            ':email',
            $email,
            PDO::PARAM_STR
        );

        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }


    // ============================================================
    // REGISTRAR USUARIO
    // ============================================================

    public function registrar($datos)
    {
        try {

            $this->conn->beginTransaction();


            // ====================================================
            // RECIBIR DATOS
            // ====================================================

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


            // ====================================================
            // VALIDAR NOMBRE
            // ====================================================

            if ($nombre === '') {

                $this->conn->rollBack();

                return 'El nombre es obligatorio.';
            }


            // ====================================================
            // VALIDAR CORREO
            // ====================================================

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


            // ====================================================
            // VALIDAR CONTRASEÑA
            // ====================================================

            if ($password === '') {

                $this->conn->rollBack();

                return 'La contraseña es obligatoria.';
            }


            // ====================================================
            // VALIDAR CONFIRMACIÓN
            // ====================================================

            if ($confirmarPassword === '') {

                $this->conn->rollBack();

                return 'Debe confirmar la contraseña.';
            }


            if ($password !== $confirmarPassword) {

                $this->conn->rollBack();

                return 'Las contraseñas no coinciden.';
            }


            // ====================================================
            // VERIFICAR CORREO EXISTENTE
            // ====================================================

            $stmtCorreo = $this->conn->prepare(
                "SELECT id_persona
                 FROM persona
                 WHERE correo = ?
                 LIMIT 1"
            );

            $stmtCorreo->execute([
                $correo
            ]);

            if ($stmtCorreo->fetch(PDO::FETCH_ASSOC)) {

                $this->conn->rollBack();

                return 'El correo ya está registrado.';
            }


            // ====================================================
            // ROL POR DEFECTO
            // ====================================================

            $rol = 1;


            // ====================================================
            // VERIFICAR ROL
            // ====================================================

            $stmtRol = $this->conn->prepare(
                "SELECT id_rol, nombre
                 FROM rol
                 WHERE id_rol = ?
                 LIMIT 1"
            );

            $stmtRol->execute([
                $rol
            ]);

            $rolBD =
                $stmtRol->fetch(PDO::FETCH_ASSOC);

            if (!$rolBD) {

                $this->conn->rollBack();

                return 'No existe el rol Administrador en la base de datos.';
            }


            // ====================================================
            // INSERTAR PERSONA
            // ====================================================

            $sqlPersona = "INSERT INTO persona
                            (
                                nombre,
                                telefono,
                                correo,
                                estado
                            )
                           VALUES
                            (
                                :nombre,
                                :telefono,
                                :correo,
                                1
                            )";

            $stmtPersona =
                $this->conn->prepare($sqlPersona);


            $stmtPersona->bindValue(
                ':nombre',
                $nombre,
                PDO::PARAM_STR
            );


            if ($telefono === '') {

                $stmtPersona->bindValue(
                    ':telefono',
                    null,
                    PDO::PARAM_NULL
                );

            } else {

                $stmtPersona->bindValue(
                    ':telefono',
                    $telefono,
                    PDO::PARAM_STR
                );
            }


            $stmtPersona->bindValue(
                ':correo',
                $correo,
                PDO::PARAM_STR
            );


            $stmtPersona->execute();


            // ====================================================
            // OBTENER ID PERSONA
            // ====================================================

            $id_persona =
                $this->conn->lastInsertId();


            if (!$id_persona) {

                throw new Exception(
                    'No se pudo obtener el id_persona generado.'
                );
            }


            // ====================================================
            // ENCRIPTAR CONTRASEÑA
            // ====================================================

            $passwordHash =
                password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );


            if ($passwordHash === false) {

                throw new Exception(
                    'No se pudo encriptar la contraseña.'
                );
            }


            // ====================================================
            // INSERTAR USUARIO
            // ====================================================

            $sqlUsuario = "INSERT INTO usuario
                            (
                                `contraseña`,
                                id_persona,
                                id_rol,
                                estado
                            )
                           VALUES
                            (
                                :password,
                                :id_persona,
                                :id_rol,
                                1
                            )";

            $stmtUsuario =
                $this->conn->prepare($sqlUsuario);


            $stmtUsuario->bindValue(
                ':password',
                $passwordHash,
                PDO::PARAM_STR
            );


            $stmtUsuario->bindValue(
                ':id_persona',
                $id_persona,
                PDO::PARAM_INT
            );


            $stmtUsuario->bindValue(
                ':id_rol',
                $rol,
                PDO::PARAM_INT
            );


            $stmtUsuario->execute();


            // ====================================================
            // CONFIRMAR
            // ====================================================

            $this->conn->commit();

            return true;


        } catch (Exception $e) {

            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            return 'Error al registrar: '
                . $e->getMessage();
        }
    }


    // ============================================================
    // ELIMINAR USUARIO + PERSONA
    // ============================================================

    public function eliminarCompleto($id_usuario)
    {
        try {

            $this->conn->beginTransaction();


            $stmt = $this->conn->prepare(
                "SELECT id_persona
                 FROM usuario
                 WHERE id_usuario = ?"
            );

            $stmt->execute([
                $id_usuario
            ]);

            $data =
                $stmt->fetch(PDO::FETCH_ASSOC);


            if (!$data) {

                $this->conn->rollBack();

                return 'Usuario no encontrado.';
            }


            $id_persona =
                $data['id_persona'];


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

            return 'Error al eliminar: '
                . $e->getMessage();
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

            $this->conn->beginTransaction();


            $stmt = $this->conn->prepare(
                "SELECT id_persona
                 FROM usuario
                 WHERE id_usuario = ?"
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
                $data['id_persona'];


            $stmtPersona =
                $this->conn->prepare(
                    "UPDATE persona
                     SET nombre = ?,
                         telefono = ?
                     WHERE id_persona = ?"
                );

            $stmtPersona->execute([
                $nombre,
                $telefono,
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

            return 'Error al editar: '
                . $e->getMessage();
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


        /*
         * ACEPTAR:
         *
         * 1
         * 0
         * activo
         * inactivo
         *
         * Y CONVERTIR TODO A 1 / 0
         */

        if (is_string($estado)) {

            $estado =
                strtolower(
                    trim($estado)
                );


            if (
                $estado === 'activo' ||
                $estado === '1'
            ) {

                $estado = 1;

            } elseif (
                $estado === 'inactivo' ||
                $estado === '0'
            ) {

                $estado = 0;

            } else {

                return 'Estado no válido.';

            }

        } else {


            $estado =
                intval($estado);


            if (
                $estado !== 0 &&
                $estado !== 1
            ) {

                return 'El estado debe ser 0 o 1.';

            }

        }


        /*
         * VALIDAR ID
         */

        $id_usuario =
            intval($id_usuario);


        if ($id_usuario <= 0) {

            return 'Usuario no válido.';

        }


        /*
         * INICIAR TRANSACCIÓN
         */

        $this->conn->beginTransaction();


        /*
         * ACTUALIZAR USUARIO
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
         * ACTUALIZAR PERSONA
         */

        $stmtPersona =
            $this->conn->prepare(

                "UPDATE persona
                 SET estado = ?
                 WHERE id_persona = (
                     SELECT id_persona
                     FROM usuario
                     WHERE id_usuario = ?
                 )"

            );


        $stmtPersona->execute([

            $estado,

            $id_usuario

        ]);


        /*
         * CONFIRMAR
         */

        $this->conn->commit();


        return true;


    } catch (Exception $e) {


        /*
         * CANCELAR SI HUBO ERROR
         */

        if (
            $this->conn->inTransaction()
        ) {

            $this->conn->rollBack();

        }


        return
            'Error al cambiar estado: ' .
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