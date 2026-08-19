<?php
/**
 * Modelo Cliente
 * BD real: cliente (id_cliente, id_persona, fecha_registro)
 *          persona (id_persona, nombre, telefono, correo, estado)
 * Estado en persona.estado ('activo' / 'inactivo')
 */
class Cliente {

    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    // ── Lista todos los clientes con datos de persona ───────
    public function obtenerTodos() {
        $sql = "SELECT
                    c.id_cliente,
                    c.fecha_registro,
                    p.id_persona,
                    p.nombre,
                    p.telefono,
                    p.correo,
                    p.estado
                FROM cliente c
                INNER JOIN persona p ON c.id_persona = p.id_persona
                ORDER BY p.nombre ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Verificar correo duplicado ──────────────────────────
    public function existeCorreo($correo) {
        $stmt = $this->conn->prepare(
            "SELECT id_persona FROM persona WHERE correo = :correo LIMIT 1"
        );
        $stmt->bindParam(':correo', $correo);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    // ── Obtener cliente por ID ──────────────────────────────
    public function obtenerPorId($id_cliente) {
        $sql = "SELECT c.id_cliente, c.fecha_registro, p.*
                FROM cliente c
                INNER JOIN persona p ON c.id_persona = p.id_persona
                WHERE c.id_cliente = :id LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id_cliente);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ── Registrar cliente (persona + cliente) ───────────────
    public function registrar($datos) {
        try {
            $this->conn->beginTransaction();

            // PASO 1: Insertar en persona
            $stmt = $this->conn->prepare(
                "INSERT INTO persona (nombre, telefono, correo, estado)
                 VALUES (:nombre, :telefono, :correo, 'activo')"
            );
            $stmt->bindParam(':nombre',   $datos['nombre']);
            $stmt->bindParam(':telefono', $datos['telefono']);
            $stmt->bindParam(':correo',   $datos['correo']);
            $stmt->execute();
            $id_persona = $this->conn->lastInsertId();

            // PASO 2: Insertar en cliente
            $stmt2 = $this->conn->prepare(
                "INSERT INTO cliente (id_persona, fecha_registro)
                 VALUES (:id_persona, CURDATE())"
            );
            $stmt2->bindParam(':id_persona', $id_persona);
            $stmt2->execute();

            $this->conn->commit();
            return true;

        } catch (Exception $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            return "Error al registrar: " . $e->getMessage();
        }
    }

    // ── Editar cliente (actualiza en persona) ───────────────
    public function editarCompleto($id_cliente, $nombre, $telefono, $correo) {
        try {
            $this->conn->beginTransaction();

            $stmt = $this->conn->prepare("SELECT id_persona FROM cliente WHERE id_cliente = ?");
            $stmt->execute([$id_cliente]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$data) return "Cliente no encontrado";

            $id_persona = $data['id_persona'];

            $this->conn->prepare(
                "UPDATE persona SET nombre = ?, telefono = ?, correo = ? WHERE id_persona = ?"
            )->execute([$nombre, $telefono, $correo, $id_persona]);

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            return $e->getMessage();
        }
    }

    // ── Cambiar estado en persona ───────────────────────────
    public function cambiarEstado($id_cliente, $nuevoEstado) {
        try {
            $stmt = $this->conn->prepare("SELECT id_persona FROM cliente WHERE id_cliente = ?");
            $stmt->execute([$id_cliente]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$data) return "Cliente no encontrado";

            $this->conn->prepare(
                "UPDATE persona SET estado = ? WHERE id_persona = ?"
            )->execute([$nuevoEstado, $data['id_persona']]);
            return true;
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    // ── Verificar si el cliente tiene ventas ────────────────
    public function tieneVentas($id_cliente) {
        $stmt = $this->conn->prepare(
            "SELECT COUNT(*) AS total FROM venta WHERE id_cliente = :id LIMIT 1"
        );
        $stmt->bindParam(':id', $id_cliente);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return intval($row['total']) > 0;
    }

    // ── Eliminar cliente (verifica ventas primero) ──────────
    public function eliminar($id_cliente) {
        try {
            if ($this->tieneVentas($id_cliente)) {
                return "No se puede eliminar este cliente porque tiene ventas registradas.";
            }

            $this->conn->beginTransaction();

            $stmt = $this->conn->prepare("SELECT id_persona FROM cliente WHERE id_cliente = ?");
            $stmt->execute([$id_cliente]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$data) return "Cliente no encontrado";

            $id_persona = $data['id_persona'];

            $this->conn->prepare("DELETE FROM cliente WHERE id_cliente = ?")->execute([$id_cliente]);
            $this->conn->prepare("DELETE FROM persona WHERE id_persona = ?")->execute([$id_persona]);

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            return $e->getMessage();
        }
    }
}
?>
