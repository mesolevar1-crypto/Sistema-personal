<?php

class Cliente
{
    private $conn;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    // ============================================================
    // OBTENER TODOS LOS CLIENTES
    // ============================================================

    public function obtenerTodos()
    {
        $sql = "SELECT
                    c.id_cliente,
                    c.id_persona,
                    c.fecha_registro,
                    c.estado,
                    p.nombre,
                    p.telefono,
                    p.correo
                FROM cliente c
                INNER JOIN persona p
                    ON c.id_persona = p.id_persona
                ORDER BY c.fecha_registro DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    // ============================================================
    // OBTENER CLIENTE POR ID
    // ============================================================

    public function obtenerPorId($id_cliente)
    {
        $sql = "SELECT
                    c.id_cliente,
                    c.id_persona,
                    c.fecha_registro,
                    c.estado,
                    p.nombre,
                    p.telefono,
                    p.correo
                FROM cliente c
                INNER JOIN persona p
                    ON c.id_persona = p.id_persona
                WHERE c.id_cliente = :id
                LIMIT 1";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', $id_cliente, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }


    // ============================================================
    // VERIFICAR CORREO
    // ============================================================

    public function existeCorreo($correo)
    {
        if (empty($correo)) {
            return false;
        }

        $sql = "SELECT id_persona
                FROM persona
                WHERE correo = :correo
                LIMIT 1";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':correo', $correo);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    }


    // ============================================================
    // REGISTRAR CLIENTE
    // ============================================================

    public function registrar($datos)
    {
        try {

            $this->conn->beginTransaction();


            // ----------------------------------------------------
            // 1. CREAR PERSONA
            // ----------------------------------------------------

            $sqlPersona = "INSERT INTO persona
                            (nombre, telefono, correo)
                           VALUES
                            (:nombre, :telefono, :correo)";

            $stmt = $this->conn->prepare($sqlPersona);

            $stmt->bindValue(
                ':nombre',
                $datos['nombre']
            );

            $stmt->bindValue(
                ':telefono',
                $datos['telefono']
            );

            $stmt->bindValue(
                ':correo',
                $datos['correo']
            );

            $stmt->execute();


            $idPersona = $this->conn->lastInsertId();


            // ----------------------------------------------------
            // 2. CREAR CLIENTE
            // ----------------------------------------------------
            // fecha_registro usa CURRENT_TIMESTAMP
            // estado empieza en 1 = activo
            // ----------------------------------------------------

            $sqlCliente = "INSERT INTO cliente
                            (id_persona, fecha_registro, estado)
                           VALUES
                            (:id_persona, CURRENT_TIMESTAMP, 1)";

            $stmt = $this->conn->prepare($sqlCliente);

            $stmt->bindValue(
                ':id_persona',
                $idPersona,
                PDO::PARAM_INT
            );

            $stmt->execute();


            $this->conn->commit();

            return true;

        } catch (Exception $e) {

            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            return "Error al registrar el cliente: " . $e->getMessage();
        }
    }


    // ============================================================
    // EDITAR CLIENTE
    // ============================================================

    public function editarCompleto(
        $id_cliente,
        $nombre,
        $telefono,
        $correo
    ) {
        try {

            $stmt = $this->conn->prepare(
                "SELECT id_persona
                 FROM cliente
                 WHERE id_cliente = ?
                 LIMIT 1"
            );

            $stmt->execute([$id_cliente]);

            $cliente = $stmt->fetch(PDO::FETCH_ASSOC);


            if (!$cliente) {
                return "Cliente no encontrado.";
            }


            $idPersona = $cliente['id_persona'];


            $stmt = $this->conn->prepare(
                "UPDATE persona
                 SET nombre = ?,
                     telefono = ?,
                     correo = ?
                 WHERE id_persona = ?"
            );

            $stmt->execute([
                $nombre,
                $telefono,
                $correo,
                $idPersona
            ]);


            return true;

        } catch (Exception $e) {

            return "Error al actualizar: " . $e->getMessage();
        }
    }


    // ============================================================
    // CAMBIAR ESTADO
    // ============================================================

    public function cambiarEstado($id_cliente)
    {
        try {

            $stmt = $this->conn->prepare(
                "SELECT estado
                 FROM cliente
                 WHERE id_cliente = ?
                 LIMIT 1"
            );

            $stmt->execute([$id_cliente]);

            $cliente = $stmt->fetch(PDO::FETCH_ASSOC);


            if (!$cliente) {
                return "Cliente no encontrado.";
            }


            $estadoActual = (int)$cliente['estado'];

            $nuevoEstado = $estadoActual === 1 ? 0 : 1;


            $stmt = $this->conn->prepare(
                "UPDATE cliente
                 SET estado = ?
                 WHERE id_cliente = ?"
            );

            $stmt->execute([
                $nuevoEstado,
                $id_cliente
            ]);


            return true;

        } catch (Exception $e) {

            return "Error al cambiar el estado: " . $e->getMessage();
        }
    }


    // ============================================================
    // VERIFICAR SI TIENE VENTAS
    // ============================================================

    public function tieneVentas($id_cliente)
    {
        try {

            $stmt = $this->conn->prepare(
                "SELECT COUNT(*) AS total
                 FROM venta
                 WHERE id_cliente = ?"
            );

            $stmt->execute([$id_cliente]);

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

            return (int)$resultado['total'] > 0;

        } catch (Exception $e) {

            return false;
        }
    }


    // ============================================================
    // ELIMINAR CLIENTE
    // ============================================================

    public function eliminar($id_cliente)
    {
        try {

            // ----------------------------------------------------
            // BUSCAR CLIENTE
            // ----------------------------------------------------

            $stmt = $this->conn->prepare(
                "SELECT id_persona
                 FROM cliente
                 WHERE id_cliente = ?
                 LIMIT 1"
            );

            $stmt->execute([$id_cliente]);

            $cliente = $stmt->fetch(PDO::FETCH_ASSOC);


            if (!$cliente) {
                return "Cliente no encontrado.";
            }


            // ----------------------------------------------------
            // SI TIENE VENTAS NO SE ELIMINA
            // ----------------------------------------------------

            if ($this->tieneVentas($id_cliente)) {

                return "No se puede eliminar este cliente porque tiene ventas registradas.";
            }


            $idPersona = $cliente['id_persona'];


            $this->conn->beginTransaction();


            // ----------------------------------------------------
            // ELIMINAR CLIENTE
            // ----------------------------------------------------

            $stmt = $this->conn->prepare(
                "DELETE FROM cliente
                 WHERE id_cliente = ?"
            );

            $stmt->execute([$id_cliente]);


            // ----------------------------------------------------
            // ELIMINAR PERSONA
            // ----------------------------------------------------

            $stmt = $this->conn->prepare(
                "DELETE FROM persona
                 WHERE id_persona = ?"
            );

            $stmt->execute([$idPersona]);


            $this->conn->commit();


            return true;

        } catch (Exception $e) {

            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            return "Error al eliminar: " . $e->getMessage();
        }
    }
}
?>