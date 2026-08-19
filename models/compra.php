<?php
/**
 * Modelo Compra
 * BD real:
 *   compra (id_compra, id_proveedor, id_usuario, fecha, total, estado)
 *   detalle_compra (id_detalle, id_compra, id_precio, cantidad, precio_unitario, subtotal)
 *   productos_precio (id_precio, id_producto, id_proveedor, precio_compra, ...)
 *   inventario (id_inventario, id_producto, stock_actual, stock_minimo, fecha_actualizacion)
 *   usuarios (tabla correcta, no "usuario")
 */
class Compra {

    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    // ── Lista todas las compras ─────────────────────────────
    public function obtenerTodas() {
        $sql = "SELECT
                    c.id_compra,
                    c.fecha,
                    c.total,
                    c.estado,
                    pe_prov.nombre  AS proveedor,
                    pe_usr.nombre   AS comprador
                FROM compra c
                LEFT JOIN proveedor  pr      ON c.id_proveedor = pr.id_proveedor
                LEFT JOIN persona    pe_prov ON pr.id_persona  = pe_prov.id_persona
                LEFT JOIN usuarios   u       ON c.id_usuario   = u.id_usuario
                LEFT JOIN persona    pe_usr  ON u.id_persona   = pe_usr.id_persona
                ORDER BY c.fecha DESC, c.id_compra DESC";
        $stmt = $this->conn->prepare($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Detalle de una compra ───────────────────────────────
    public function obtenerDetalle($id_compra) {
        $sql = "SELECT
                    dc.id_detalle,
                    dc.cantidad,
                    dc.precio_unitario,
                    dc.subtotal,
                    pr.nombre   AS producto,
                    uc.nombre   AS unidad
                FROM detalle_compra dc
                INNER JOIN productos_precio pp ON dc.id_precio        = pp.id_precio
                INNER JOIN producto         pr ON pp.id_producto      = pr.id_producto
                INNER JOIN unidades_medida  uc ON pp.id_unidad_compra = uc.id_unidad
                WHERE dc.id_compra = :id_compra";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id_compra', $id_compra);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── KPIs ────────────────────────────────────────────────
    public function obtenerResumen() {
        $sql = "SELECT
                    COUNT(*)    AS total_compras,
                    COALESCE(SUM(total), 0)  AS gasto_total,
                    SUM(CASE WHEN fecha = CURDATE() THEN 1     ELSE 0 END) AS compras_hoy,
                    SUM(CASE WHEN fecha = CURDATE() THEN total ELSE 0 END) AS gasto_hoy
                FROM compra";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ── Proveedores activos para el select ──────────────────
    public function obtenerProveedores() {
        $sql = "SELECT pr.id_proveedor, pe.nombre, pr.empresa
                FROM proveedor pr
                INNER JOIN persona pe ON pr.id_persona = pe.id_persona
                WHERE pe.estado = 'activo'
                ORDER BY pe.nombre ASC";
        $stmt = $this->conn->prepare($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Productos/precios activos filtrados por proveedor ───
    public function obtenerProductosPrecios() {
        $sql = "SELECT
                    pp.id_precio,
                    pp.id_proveedor,
                    pp.id_producto,
                    pp.precio_compra,
                    pp.unidades_por_presentacion,
                    pr.nombre   AS producto,
                    uc.nombre   AS unidad,
                    uc.id_unidad AS id_unidad_compra
                FROM productos_precio pp
                INNER JOIN producto        pr ON pp.id_producto      = pr.id_producto
                INNER JOIN unidades_medida uc ON pp.id_unidad_compra = uc.id_unidad
                WHERE pp.estado = 'activo'
                  AND pr.estado = 'activo'
                ORDER BY pr.nombre ASC";
        $stmt = $this->conn->prepare($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Registra una compra completa en una TRANSACCIÓN:
     * 1. INSERT compra
     * 2. INSERT detalle_compra × N
     * 3. UPSERT inventario (crear o sumar stock_actual)
     */
    public function registrar($id_usuario, $id_proveedor, $items) {
        try {
            $this->conn->beginTransaction();

            $total = array_sum(array_column($items, 'subtotal'));

            // PASO 1: cabecera
            $stmt = $this->conn->prepare(
                "INSERT INTO compra (id_proveedor, id_usuario, fecha, total, estado)
                 VALUES (:id_proveedor, :id_usuario, CURDATE(), :total, 'activa')"
            );
            $stmt->execute([
                ':id_proveedor' => $id_proveedor,
                ':id_usuario'   => $id_usuario,
                ':total'        => $total,
            ]);
            $id_compra = $this->conn->lastInsertId();

            // PASO 2: detalles
            $stmtD = $this->conn->prepare(
                "INSERT INTO detalle_compra (id_compra, id_precio, cantidad, precio_unitario, subtotal)
                 VALUES (:id_compra, :id_precio, :cantidad, :precio_unitario, :subtotal)"
            );
            foreach ($items as $item) {
                $stmtD->execute([
                    ':id_compra'      => $id_compra,
                    ':id_precio'      => $item['id_precio'],
                    ':cantidad'       => $item['cantidad'],
                    ':precio_unitario'=> $item['precio_unitario'],
                    ':subtotal'       => $item['subtotal'],
                ]);
            }

            // PASO 3: inventario UPSERT
            $stmtBuscar = $this->conn->prepare(
                "SELECT id_inventario FROM inventario WHERE id_producto = :id LIMIT 1"
            );
            $stmtUpdate = $this->conn->prepare(
                "UPDATE inventario
                 SET stock_actual = stock_actual + :cantidad, fecha_actualizacion = CURDATE()
                 WHERE id_producto = :id_producto"
            );
            $stmtInsert = $this->conn->prepare(
                "INSERT INTO inventario (id_producto, stock_actual, stock_minimo, fecha_actualizacion)
                 VALUES (:id_producto, :stock_actual, 0, CURDATE())"
            );

            foreach ($items as $item) {
                $stmtBuscar->bindParam(':id', $item['id_producto']);
                $stmtBuscar->execute();
                $existe = $stmtBuscar->fetch(PDO::FETCH_ASSOC);

                if ($existe) {
                    $stmtUpdate->execute([
                        ':cantidad'    => $item['cantidad'],
                        ':id_producto' => $item['id_producto'],
                    ]);
                } else {
                    $stmtInsert->execute([
                        ':id_producto'  => $item['id_producto'],
                        ':stock_actual' => $item['cantidad'],
                    ]);
                }
            }

            $this->conn->commit();
            return true;

        } catch (Exception $e) {
            $this->conn->rollBack();
            return "Error al registrar: " . $e->getMessage();
        }
    }

    // ── Eliminar compra ─────────────────────────────────────
    public function eliminar($id_compra) {
        try {
            $this->conn->beginTransaction();
            $this->conn->prepare("DELETE FROM detalle_compra WHERE id_compra = ?")->execute([$id_compra]);
            $this->conn->prepare("DELETE FROM compra WHERE id_compra = ?")->execute([$id_compra]);
            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            return "Error al eliminar: " . $e->getMessage();
        }
    }
}
?>
