<?php
/**
 * Modelo Inicio — KPIs y datos del panel principal del Administrador.
 *
 * Se apoya en las mismas tablas que el módulo de Ventas:
 * venta, detalle_venta, producto, inventario.
 *
 * Stock bajo se calcula comparando i.stock_actual contra
 * i.stock_minimo (columna real de la tabla inventario).
 */
class Inicio
{
    private $conn;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    // =========================================================
    // VENTAS DEL DÍA (solo ventas activas, estado = 1)
    // =========================================================
    public function ventasDia()
    {
        try {
            $sql = "SELECT COALESCE(SUM(total), 0) AS total
                    FROM venta
                    WHERE estado = 1 AND DATE(fecha) = CURDATE()";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute();

            return (float) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        } catch (PDOException $e) {
            error_log("Error ventasDia: " . $e->getMessage());
            return 0;
        }
    }

    // =========================================================
    // VENTAS DEL MES ACTUAL
    // =========================================================
    public function ventasMes()
    {
        try {
            $sql = "SELECT COALESCE(SUM(total), 0) AS total
                    FROM venta
                    WHERE estado = 1
                      AND MONTH(fecha) = MONTH(CURDATE())
                      AND YEAR(fecha)  = YEAR(CURDATE())";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute();

            return (float) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        } catch (PDOException $e) {
            error_log("Error ventasMes: " . $e->getMessage());
            return 0;
        }
    }

    // =========================================================
    // GANANCIAS DE LOS ÚLTIMOS 7 DÍAS (incluye hoy)
    // Ganancia = subtotal - (costo_unitario * cantidad) por línea
    // =========================================================
    public function gananciasSemana()
    {
        try {
            $sql = "SELECT COALESCE(SUM(dv.subtotal - (dv.costo_unitario * dv.cantidad)), 0) AS ganancia
                    FROM detalle_venta dv
                    INNER JOIN venta v ON v.id_venta = dv.id_venta
                    WHERE v.estado = 1
                      AND v.fecha >= (CURDATE() - INTERVAL 6 DAY)";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute();

            return (float) $stmt->fetch(PDO::FETCH_ASSOC)['ganancia'];

        } catch (PDOException $e) {
            error_log("Error gananciasSemana: " . $e->getMessage());
            return 0;
        }
    }

    // =========================================================
    // PRODUCTOS CON STOCK BAJO
    // Compara el stock_actual contra el stock_minimo propio
    // de cada producto (columna real de la tabla inventario).
    // =========================================================
    public function stockBajo()
    {
        try {
            $sql = "SELECT COUNT(*) AS total
                    FROM inventario i
                    INNER JOIN producto p ON p.id_producto = i.id_producto
                    WHERE p.estado = 1
                      AND i.stock_actual <= i.stock_minimo";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute();

            return (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        } catch (PDOException $e) {
            error_log("Error stockBajo: " . $e->getMessage());
            return 0;
        }
    }

    // =========================================================
    // DETALLE DE PRODUCTOS CON STOCK BAJO (para tooltip/modal futuro)
    // =========================================================
    public function detalleStockBajo()
    {
        try {
            $sql = "SELECT p.nombre, i.stock_actual, i.stock_minimo
                    FROM inventario i
                    INNER JOIN producto p ON p.id_producto = i.id_producto
                    WHERE p.estado = 1
                      AND i.stock_actual <= i.stock_minimo
                    ORDER BY (i.stock_minimo - i.stock_actual) DESC";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("Error detalleStockBajo: " . $e->getMessage());
            return [];
        }
    }

    // =========================================================
    // TOTAL DE PRODUCTOS ACTIVOS
    // =========================================================
    public function totalProductos()
    {
        try {
            $sql = "SELECT COUNT(*) AS total FROM producto WHERE estado = 1";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute();

            return (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        } catch (PDOException $e) {
            error_log("Error totalProductos: " . $e->getMessage());
            return 0;
        }
    }

    // =========================================================
    // CANTIDAD DE VENTAS DE HOY (para el subtítulo de la tarjeta)
    // =========================================================
    public function contarVentasHoy()
    {
        try {
            $sql = "SELECT COUNT(*) AS total
                    FROM venta
                    WHERE estado = 1 AND DATE(fecha) = CURDATE()";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute();

            return (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        } catch (PDOException $e) {
            error_log("Error contarVentasHoy: " . $e->getMessage());
            return 0;
        }
    }

    // =========================================================
    // CANTIDAD DE VENTAS DEL MES (para el subtítulo de la tarjeta)
    // =========================================================
    public function contarVentasMes()
    {
        try {
            $sql = "SELECT COUNT(*) AS total
                    FROM venta
                    WHERE estado = 1
                      AND MONTH(fecha) = MONTH(CURDATE())
                      AND YEAR(fecha)  = YEAR(CURDATE())";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute();

            return (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        } catch (PDOException $e) {
            error_log("Error contarVentasMes: " . $e->getMessage());
            return 0;
        }
    }

    // =========================================================
    // TOTAL DE USUARIOS REGISTRADOS (activos)
    // =========================================================
    public function totalUsuarios()
    {
        try {
            $sql = "SELECT COUNT(*) AS total FROM usuario WHERE estado = 1";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute();

            return (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        } catch (PDOException $e) {
            error_log("Error totalUsuarios: " . $e->getMessage());
            return 0;
        }
    }

    // =========================================================
    // VENTAS DE LOS ÚLTIMOS 7 DÍAS (para la gráfica)
    // Devuelve un arreglo asociativo ['2026-08-17' => 125000.0, ...]
    // con los 7 días completos, incluso los que no tuvieron ventas.
    // =========================================================
    public function ventasUltimos7Dias()
    {
        try {
            $sql = "SELECT DATE(fecha) AS dia, COALESCE(SUM(total), 0) AS total
                    FROM venta
                    WHERE estado = 1
                      AND fecha >= (CURDATE() - INTERVAL 6 DAY)
                    GROUP BY DATE(fecha)
                    ORDER BY dia ASC";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Rellenar los 7 días aunque algún día no tenga ventas
            $resultado = [];
            for ($i = 6; $i >= 0; $i--) {
                $fecha = date('Y-m-d', strtotime("-$i day"));
                $resultado[$fecha] = 0.0;
            }
            foreach ($filas as $f) {
                $resultado[$f['dia']] = (float) $f['total'];
            }

            return $resultado;

        } catch (PDOException $e) {
            error_log("Error ventasUltimos7Dias: " . $e->getMessage());
            return [];
        }
    }

    // =========================================================
    // TOP PRODUCTOS MÁS VENDIDOS (por cantidad, ventas activas)
    // =========================================================
    public function productosMasVendidos($limite = 5)
    {
        try {
            $sql = "SELECT p.nombre, SUM(dv.cantidad) AS cantidad_vendida
                    FROM detalle_venta dv
                    INNER JOIN venta v    ON v.id_venta = dv.id_venta
                    INNER JOIN producto p ON p.id_producto = dv.id_producto
                    WHERE v.estado = 1
                    GROUP BY dv.id_producto, p.nombre
                    ORDER BY cantidad_vendida DESC
                    LIMIT :limite";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':limite', (int) $limite, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("Error productosMasVendidos: " . $e->getMessage());
            return [];
        }
    }
}