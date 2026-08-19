<?php
/**
 * Modelo Proveedor
 * BD real:
 * proveedor (id_proveedor, id_persona, frecuencia_entrega)
 * persona (id_persona, nombre, telefono, correo, estado)
 *
 * estado:
 * 1 = Activo
 * 0 = Inactivo
 */
class Proveedor {

    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    // ── Lista todos los proveedores ─────────────────────────
    public function obtenerTodos() {

        $sql = "SELECT
                    pr.id_proveedor,
                    pr.frecuencia_entrega,
                    pe.id_persona,
                    pe.nombre,
                    pe.telefono,
                    pe.correo,
                    pe.estado
                FROM proveedor pr
                INNER JOIN persona pe 
                    ON pr.id_persona = pe.id_persona
                ORDER BY pe.nombre ASC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Obtener proveedor por ID ────────────────────────────
    public function obtenerPorId($id) {

        $sql = "SELECT
                    pr.id_proveedor,
                    pr.id_persona,
                    pr.frecuencia_entrega,
                    pe.nombre,
                    pe.telefono,
                    pe.correo,
                    pe.estado
                FROM proveedor pr
                INNER JOIN persona pe 
                    ON pr.id_persona = pe.id_persona
                WHERE pr.id_proveedor = :id
                LIMIT 1";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ── Proveedores activos para selects ────────────────────
    public function obtenerActivos() {

        $sql = "SELECT
                    pr.id_proveedor,
                    pe.nombre
                FROM proveedor pr
                INNER JOIN persona pe 
                    ON pr.id_persona = pe.id_persona
                WHERE pe.estado = 1
                ORDER BY pe.nombre ASC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Registrar proveedor ─────────────────────────────────
    public function registrar(
        $nombre,
        $telefono,
        $correo,
        $frecuencia_entrega
    ) {

        try {

            $this->conn->beginTransaction();

            // PASO 1: Insertar en persona
            $stmt = $this->conn->prepare(
                "INSERT INTO persona 
                    (nombre, telefono, correo, estado)
                 VALUES 
                    (:nombre, :telefono, :correo, 1)"
            );

            $stmt->bindParam(':nombre', $nombre);
            $stmt->bindParam(':telefono', $telefono);
            $stmt->bindParam(':correo', $correo);

            $stmt->execute();

            $id_persona = $this->conn->lastInsertId();

            // PASO 2: Insertar en proveedor
            $stmt2 = $this->conn->prepare(
                "INSERT INTO proveedor
                    (id_persona, frecuencia_entrega)
                 VALUES
                    (:id_persona, :frecuencia_entrega)"
            );

            $stmt2->bindParam(':id_persona', $id_persona);
            $stmt2->bindParam(
                ':frecuencia_entrega',
                $frecuencia_entrega
            );

            $stmt2->execute();

            $this->conn->commit();

            return true;

        } catch (Exception $e) {

            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            return "Error al registrar: " . $e->getMessage();
        }
    }

    // ── Editar proveedor ────────────────────────────────────
    public function editar(
        $id_proveedor,
        $nombre,
        $telefono,
        $correo,
        $frecuencia_entrega
    ) {

        try {

            $this->conn->beginTransaction();

            // Buscar persona relacionada
            $stmt = $this->conn->prepare(
                "SELECT id_persona
                 FROM proveedor
                 WHERE id_proveedor = ?"
            );

            $stmt->execute([$id_proveedor]);

            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {

                $this->conn->rollBack();

                return "Proveedor no encontrado";
            }

            $id_persona = $row['id_persona'];

            // Actualizar persona
            $stmtPersona = $this->conn->prepare(
                "UPDATE persona
                 SET nombre = ?,
                     telefono = ?,
                     correo = ?
                 WHERE id_persona = ?"
            );

            $stmtPersona->execute([
                $nombre,
                $telefono,
                $correo,
                $id_persona
            ]);

            // Actualizar proveedor
            $stmtProveedor = $this->conn->prepare(
                "UPDATE proveedor
                 SET frecuencia_entrega = ?
                 WHERE id_proveedor = ?"
            );

            $stmtProveedor->execute([
                $frecuencia_entrega,
                $id_proveedor
            ]);

            $this->conn->commit();

            return true;

        } catch (Exception $e) {

            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            return "Error al actualizar: " . $e->getMessage();
        }
    }

    // ── Toggle estado ───────────────────────────────────────
    public function toggleEstado(
        $id_proveedor,
        $estadoActual
    ) {

        try {

            $stmt = $this->conn->prepare(
                "SELECT id_persona
                 FROM proveedor
                 WHERE id_proveedor = ?"
            );

            $stmt->execute([$id_proveedor]);

            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                return "Proveedor no encontrado";
            }

            // 1 = activo → 0 = inactivo
            // 0 = inactivo → 1 = activo
            $nuevoEstado = ((int)$estadoActual === 1)
                ? 0
                : 1;

            $stmt = $this->conn->prepare(
                "UPDATE persona
                 SET estado = ?
                 WHERE id_persona = ?"
            );

            $stmt->execute([
                $nuevoEstado,
                $row['id_persona']
            ]);

            return true;

        } catch (Exception $e) {

            return "Error al cambiar estado: " . $e->getMessage();
        }
    }

    // ── Eliminar proveedor ──────────────────────────────────
    public function eliminar($id_proveedor) {

        try {

            // Verificar si tiene compras
            $stmtV = $this->conn->prepare(
                "SELECT COUNT(*) AS total
                 FROM compra
                 WHERE id_proveedor = ?"
            );

            $stmtV->execute([$id_proveedor]);

            $totalCompras = intval(
                $stmtV->fetch(PDO::FETCH_ASSOC)['total']
            );

            if ($totalCompras > 0) {

                return "No se puede eliminar: este proveedor tiene compras registradas.";
            }

            $this->conn->beginTransaction();

            // Buscar persona relacionada
            $stmt = $this->conn->prepare(
                "SELECT id_persona
                 FROM proveedor
                 WHERE id_proveedor = ?"
            );

            $stmt->execute([$id_proveedor]);

            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {

                $this->conn->rollBack();

                return "Proveedor no encontrado";
            }

            $id_persona = $row['id_persona'];

            // Eliminar productos_precio asociados
            $stmt = $this->conn->prepare(
                "DELETE FROM productos_precio
                 WHERE id_proveedor = ?"
            );

            $stmt->execute([$id_proveedor]);

            // Eliminar proveedor
            $stmt = $this->conn->prepare(
                "DELETE FROM proveedor
                 WHERE id_proveedor = ?"
            );

            $stmt->execute([$id_proveedor]);

            // Eliminar persona
            $stmt = $this->conn->prepare(
                "DELETE FROM persona
                 WHERE id_persona = ?"
            );

            $stmt->execute([$id_persona]);

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