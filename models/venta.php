<?php
/**
 * Modelo Venta
 * BD real:
 *   venta (id_venta, id_cliente, id_usuario, fecha, total, estado, metodo_pago)
 *   detalle_venta (id_detalle, id_venta, id_precio, cantidad, precio_unitario,
 *                  descuento_porcentaje, descuento_valor, subtotal)
 *   productos_precio (id_precio, id_producto, id_proveedor, precio_compra,
 *                     precio_venta, unidades_por_presentacion, id_unidad_venta, id_unidad_compra)
 *   inventario (id_inventario, id_producto, stock_actual, stock_minimo, fecha_actualizacion)
 *   factura (id_factura, id_venta, numero_factura, fecha_emision, subtotal,
 *            descuento_valor, total, estado)
 *
 * Ganancia por unidad = precio_venta - (precio_compra / unidades_por_presentacion)
 *   cuando unidad_compra ≠ unidad_venta (conversión)
 * Ganancia por unidad = precio_venta - precio_compra
 *   cuando unidad_compra = unidad_venta (sin conversión)
 */
class Venta {

    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    // ── Lista todas las ventas ──────────────────────────────
    public function obtenerTodas() {
        $sql = "SELECT
                    v.id_venta,
                    v.fecha,
                    v.total,
                    v.estado,
                    v.metodo_pago,
                    pc.nombre  AS cliente,
                    pu.nombre  AS vendedor,
                    f.numero_factura
                FROM venta v
                LEFT JOIN cliente  c  ON v.id_cliente  = c.id_cliente
                LEFT JOIN persona  pc ON c.id_persona  = pc.id_persona
                LEFT JOIN usuarios u  ON v.id_usuario  = u.id_usuario
                LEFT JOIN persona  pu ON u.id_persona  = pu.id_persona
                LEFT JOIN factura  f  ON f.id_venta    = v.id_venta
                ORDER BY v.fecha DESC, v.id_venta DESC";
        $stmt = $this->conn->prepare($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Detalle de una venta con ganancia por línea ─────────
    public function obtenerDetalle($id_venta) {
        $sql = "SELECT
                    dv.id_detalle,
                    dv.cantidad,
                    dv.precio_unitario,
                    dv.descuento_porcentaje,
                    dv.descuento_valor,
                    dv.subtotal,
                    pr.nombre                           AS producto,
                    uc.nombre                           AS unidad_compra,
                    uv.nombre                           AS unidad_venta,
                    pp.precio_compra,
                    pp.precio_venta,
                    pp.unidades_por_presentacion,
                    pp.id_unidad_compra,
                    pp.id_unidad_venta,
                    -- Costo unitario de venta (con conversión si aplica)
                    CASE
                        WHEN pp.id_unidad_compra = pp.id_unidad_venta
                             THEN pp.precio_compra
                        ELSE ROUND(pp.precio_compra / pp.unidades_por_presentacion, 2)
                    END AS costo_unitario_venta,
                    -- Ganancia por unidad
                    CASE
                        WHEN pp.id_unidad_compra = pp.id_unidad_venta
                             THEN pp.precio_venta - pp.precio_compra
                        ELSE pp.precio_venta
                             - ROUND(pp.precio_compra / pp.unidades_por_presentacion, 2)
                    END AS ganancia_por_unidad,
                    -- Ganancia total de la línea
                    dv.cantidad * (
                        CASE
                            WHEN pp.id_unidad_compra = pp.id_unidad_venta
                                 THEN pp.precio_venta - pp.precio_compra
                            ELSE pp.precio_venta
                                 - ROUND(pp.precio_compra / pp.unidades_por_presentacion, 2)
                        END
                    ) AS ganancia_linea
                FROM detalle_venta dv
                INNER JOIN productos_precio pp ON dv.id_precio        = pp.id_precio
                INNER JOIN producto         pr ON pp.id_producto      = pr.id_producto
                INNER JOIN unidades_medida  uc ON pp.id_unidad_compra = uc.id_unidad
                INNER JOIN unidades_medida  uv ON pp.id_unidad_venta  = uv.id_unidad
                WHERE dv.id_venta = :id_venta";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id_venta', $id_venta);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── KPIs ────────────────────────────────────────────────
    public function obtenerResumen() {
        $sql = "SELECT
                    COUNT(*)                                                  AS total_ventas,
                    COALESCE(SUM(total), 0)                                   AS ingresos_total,
                    SUM(CASE WHEN fecha = CURDATE() THEN 1     ELSE 0 END)    AS ventas_hoy,
                    SUM(CASE WHEN fecha = CURDATE() THEN total ELSE 0 END)    AS ingresos_hoy
                FROM venta WHERE estado = 'activa'";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ── Clientes activos para el select del modal ──────────
    public function obtenerClientes() {
        $sql = "SELECT c.id_cliente, p.nombre
                FROM cliente c
                INNER JOIN persona p ON c.id_persona = p.id_persona
                WHERE p.estado = 'activo'
                ORDER BY p.nombre ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Productos disponibles para el modal de nueva venta.
     * Carga desde productos_precio (precio_venta y costo).
     * Stock desde inventario.stock_actual.
     */
    public function obtenerProductosDisponibles() {
        $sql = "SELECT
                    pp.id_precio,
                    pp.id_producto,
                    pp.precio_venta,
                    pp.precio_compra,
                    pp.unidades_por_presentacion,
                    pp.id_unidad_compra,
                    pp.id_unidad_venta,
                    pr.nombre                           AS producto,
                    uv.nombre                           AS unidad_venta,
                    uc.nombre                           AS unidad_compra,
                    COALESCE(i.stock_actual, 0)         AS stock,
                    -- Ganancia estimada por unidad
                    CASE
                        WHEN pp.id_unidad_compra = pp.id_unidad_venta
                             THEN pp.precio_venta - pp.precio_compra
                        ELSE pp.precio_venta
                             - ROUND(pp.precio_compra / pp.unidades_por_presentacion, 2)
                    END AS ganancia_estimada
                FROM productos_precio pp
                INNER JOIN producto        pr ON pp.id_producto      = pr.id_producto
                INNER JOIN unidades_medida uv ON pp.id_unidad_venta  = uv.id_unidad
                INNER JOIN unidades_medida uc ON pp.id_unidad_compra = uc.id_unidad
                LEFT JOIN  inventario      i  ON pp.id_producto      = i.id_producto
                WHERE pp.estado = 'activo'
                  AND pr.estado = 'activo'
                ORDER BY pr.nombre ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Registra una venta completa en una TRANSACCIÓN:
     * 1. INSERT venta
     * 2. INSERT detalle_venta × N
     * 3. UPDATE inventario.stock_actual -= cantidad
     * 4. INSERT factura
     */
    public function registrar($id_usuario, $id_cliente, $metodo_pago, $items) {
        try {
            $this->conn->beginTransaction();

            // Calcular totales
            $total          = 0;
            $descuento_total = 0;
            foreach ($items as $item) {
                $total          += $item['subtotal'];
                $descuento_total += $item['descuento_valor'] ?? 0;
            }

            // PASO 1: INSERT venta
            $stmt = $this->conn->prepare(
                "INSERT INTO venta (id_cliente, id_usuario, fecha, total, estado, metodo_pago)
                 VALUES (:id_cliente, :id_usuario, CURDATE(), :total, 'activa', :metodo_pago)"
            );
            $stmt->execute([
                ':id_cliente'  => $id_cliente,
                ':id_usuario'  => $id_usuario,
                ':total'       => $total,
                ':metodo_pago' => $metodo_pago,
            ]);
            $id_venta = $this->conn->lastInsertId();

            // PASO 2: INSERT detalle_venta
            $stmtD = $this->conn->prepare(
                "INSERT INTO detalle_venta
                     (id_venta, id_precio, cantidad, precio_unitario,
                      descuento_porcentaje, descuento_valor, subtotal)
                 VALUES
                     (:id_venta, :id_precio, :cantidad, :precio_unitario,
                      :desc_pct, :desc_val, :subtotal)"
            );
            foreach ($items as $item) {
                $stmtD->execute([
                    ':id_venta'       => $id_venta,
                    ':id_precio'      => $item['id_precio'],
                    ':cantidad'       => $item['cantidad'],
                    ':precio_unitario'=> $item['precio_unitario'],
                    ':desc_pct'       => $item['descuento_porcentaje'] ?? 0,
                    ':desc_val'       => $item['descuento_valor'] ?? 0,
                    ':subtotal'       => $item['subtotal'],
                ]);
            }

            // PASO 3: Descontar inventario
            $stmtI = $this->conn->prepare(
                "UPDATE inventario
                 SET stock_actual        = GREATEST(0, stock_actual - :cantidad),
                     fecha_actualizacion = CURDATE()
                 WHERE id_producto = :id_producto"
            );
            foreach ($items as $item) {
                $stmtI->execute([
                    ':cantidad'    => $item['cantidad'],
                    ':id_producto' => $item['id_producto'],
                ]);
            }

            // PASO 4: Generar factura automática
            $numero = $this->generarNumeroFactura();
            $subtotalSinDesc = $total + $descuento_total;
            $stmtF = $this->conn->prepare(
                "INSERT INTO factura
                     (id_venta, numero_factura, fecha_emision, subtotal,
                      descuento_valor, total, estado)
                 VALUES
                     (:id_venta, :numero, CURDATE(), :subtotal,
                      :descuento, :total, 'activa')"
            );
            $stmtF->execute([
                ':id_venta'  => $id_venta,
                ':numero'    => $numero,
                ':subtotal'  => $subtotalSinDesc,
                ':descuento' => $descuento_total,
                ':total'     => $total,
            ]);

            $this->conn->commit();
            return ['id_venta' => $id_venta, 'numero_factura' => $numero];

        } catch (Exception $e) {
            $this->conn->rollBack();
            return "Error al registrar: " . $e->getMessage();
        }
    }

    // ── Generar número de factura único ─────────────────────
    private function generarNumeroFactura() {
        $stmt = $this->conn->prepare(
            "SELECT COUNT(*) AS total FROM factura"
        );
        $stmt->execute();
        $row    = $stmt->fetch(PDO::FETCH_ASSOC);
        $numero = intval($row['total']) + 1;
        return 'FAC-' . str_pad($numero, 6, '0', STR_PAD_LEFT);
    }

    // ── Eliminar venta y sus detalles ───────────────────────
    public function eliminar($id_venta) {
        try {
            $this->conn->beginTransaction();
            $this->conn->prepare("DELETE FROM factura       WHERE id_venta = ?")->execute([$id_venta]);
            $this->conn->prepare("DELETE FROM detalle_venta WHERE id_venta = ?")->execute([$id_venta]);
            $this->conn->prepare("DELETE FROM venta         WHERE id_venta = ?")->execute([$id_venta]);
            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            return "Error al eliminar: " . $e->getMessage();
        }
    }

    // ── Obtener factura de una venta ────────────────────────
    public function obtenerFactura($id_venta) {
        $stmt = $this->conn->prepare(
            "SELECT * FROM factura WHERE id_venta = :id LIMIT 1"
        );
        $stmt->bindParam(':id', $id_venta);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>
