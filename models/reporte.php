<?php
/**
 * Modelo Reporte
 *
 * Corregido para usar la BD real (verificada contra Compra y Venta):
 *   - Tabla `usuario` (singular), no `usuarios`.
 *   - `persona.estado`, `venta.estado`, `categoria.estado` son
 *     numéricos (1 = activo, 0 = inactivo/anulado), no texto.
 *   - No existe tabla `productos_precio`. La ganancia por línea de
 *     venta se calcula igual que en Venta::obtenerTodas():
 *         detalle_venta.subtotal - (detalle_venta.costo_unitario * detalle_venta.cantidad)
 *     Esto garantiza que los reportes muestren EXACTAMENTE los
 *     mismos números que el módulo de Ventas.
 */
class Reporte {

    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    // ── KPIs para el index ──────────────────────────────────

    public function ventasHoy() {
        $sql = "SELECT COALESCE(SUM(total), 0) AS valor, COUNT(*) AS cantidad
                FROM venta WHERE fecha = CURDATE() AND estado = 1";
        return $this->conn->query($sql)->fetch(PDO::FETCH_ASSOC);
    }

    public function ventasMes() {
        $sql = "SELECT COALESCE(SUM(total), 0) AS valor, COUNT(*) AS cantidad
                FROM venta
                WHERE YEAR(fecha) = YEAR(CURDATE())
                  AND MONTH(fecha) = MONTH(CURDATE())
                  AND estado = 1";
        return $this->conn->query($sql)->fetch(PDO::FETCH_ASSOC);
    }

    public function comprasMes() {
        // `compra` no tiene concepto de "anulada": Compra::eliminar()
        // borra la fila físicamente, así que no hace falta filtrar
        // por estado -- lo que existe en la tabla ya está vigente.
        $sql = "SELECT COALESCE(SUM(total), 0) AS valor, COUNT(*) AS cantidad
                FROM compra
                WHERE YEAR(fecha) = YEAR(CURDATE())
                  AND MONTH(fecha) = MONTH(CURDATE())";
        return $this->conn->query($sql)->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Ganancias del mes.
     * Misma fórmula por línea que usa Venta::obtenerTodas():
     *   ganancia_linea = subtotal - (costo_unitario * cantidad)
     */
    public function gananciasMes() {
        $sql = "SELECT COALESCE(SUM(dv.subtotal - (dv.costo_unitario * dv.cantidad)), 0) AS valor
                FROM detalle_venta dv
                INNER JOIN venta v ON dv.id_venta = v.id_venta
                WHERE YEAR(v.fecha) = YEAR(CURDATE())
                  AND MONTH(v.fecha) = MONTH(CURDATE())
                  AND v.estado = 1";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function contarStockBajo() {
        $sql = "SELECT COUNT(*) AS cantidad FROM inventario
                WHERE stock_actual > 0 AND stock_actual <= stock_minimo";
        $row = $this->conn->query($sql)->fetch(PDO::FETCH_ASSOC);
        return intval($row['cantidad'] ?? 0);
    }

    public function contarAgotados() {
        $sql = "SELECT COUNT(*) AS cantidad FROM inventario WHERE stock_actual = 0";
        $row = $this->conn->query($sql)->fetch(PDO::FETCH_ASSOC);
        return intval($row['cantidad'] ?? 0);
    }

    // ── Reporte de compras con filtros ──────────────────────
    public function reporteCompras($desde, $hasta, $idProv = 0) {
        $sql = "SELECT
                    c.id_compra,
                    c.fecha,
                    c.total,
                    pe_prov.nombre  AS proveedor,
                    pe_usr.nombre   AS comprador
                FROM compra c
                LEFT JOIN proveedor  pr      ON c.id_proveedor = pr.id_proveedor
                LEFT JOIN persona    pe_prov ON pr.id_persona  = pe_prov.id_persona
                LEFT JOIN usuario    u       ON c.id_usuario   = u.id_usuario
                LEFT JOIN persona    pe_usr  ON u.id_persona   = pe_usr.id_persona
                WHERE c.fecha BETWEEN :desde AND :hasta";
        if ($idProv > 0) $sql .= " AND c.id_proveedor = :idprov";
        $sql .= " ORDER BY c.fecha DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':desde', $desde);
        $stmt->bindParam(':hasta', $hasta);
        if ($idProv > 0) $stmt->bindParam(':idprov', $idProv);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Detalle de productos comprados para el rango/proveedor filtrado,
     * agrupado por id_compra para que la vista pueda mostrar el
     * desglose de cada compra al expandirla (sin hacer una consulta
     * por fila).
     *
     * NOTA: se asume que `unidades_medida` tiene una columna `nombre`
     * para mostrar la unidad (ej. "kg", "caja", "unidad"). Si en tu
     * tabla se llama distinto (p.ej. `abreviatura` o `descripcion`),
     * cambia el alias `um.nombre` más abajo por el nombre correcto.
     *
     * @return array [ id_compra => [ fila, fila, ... ] ]
     */
    public function detalleComprasPorRango($desde, $hasta, $idProv = 0) {
        $sql = "SELECT
                    dc.id_compra,
                    p.nombre           AS producto,
                    dc.cantidad,
                    dc.precio_compra,
                    dc.subtotal,
                    um.nombre          AS unidad
                FROM detalle_compra dc
                INNER JOIN compra    c  ON dc.id_compra  = c.id_compra
                INNER JOIN producto  p  ON dc.id_producto = p.id_producto
                LEFT JOIN unidades_medida um ON dc.id_unidad = um.id_unidad
                WHERE c.fecha BETWEEN :desde AND :hasta";
        if ($idProv > 0) $sql .= " AND c.id_proveedor = :idprov";
        $sql .= " ORDER BY dc.id_compra ASC, p.nombre ASC";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':desde', $desde);
        $stmt->bindParam(':hasta', $hasta);
        if ($idProv > 0) $stmt->bindParam(':idprov', $idProv);
        $stmt->execute();
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $agrupado = [];
        foreach ($filas as $f) {
            $agrupado[$f['id_compra']][] = $f;
        }
        return $agrupado;
    }

    public function listaProveedores() {
        $sql = "SELECT pr.id_proveedor, pe.nombre
                FROM proveedor pr INNER JOIN persona pe ON pr.id_persona = pe.id_persona
                WHERE pe.estado = 1 ORDER BY pe.nombre ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Reporte de ventas con ganancias ─────────────────────
    public function reporteVentas($desde, $hasta, $idUsuario = 0) {
        $sql = "SELECT
                    v.id_venta,
                    v.fecha,
                    v.total,
                    v.metodo_pago,
                    f.numero_factura,
                    pc.nombre  AS cliente,
                    pu.nombre  AS vendedor,
                    COALESCE((
                        SELECT SUM(dv2.subtotal - (dv2.costo_unitario * dv2.cantidad))
                        FROM detalle_venta dv2
                        WHERE dv2.id_venta = v.id_venta
                    ), 0) AS ganancia
                FROM venta v
                LEFT JOIN cliente  c  ON v.id_cliente = c.id_cliente
                LEFT JOIN persona  pc ON c.id_persona = pc.id_persona
                LEFT JOIN usuario  u  ON v.id_usuario = u.id_usuario
                LEFT JOIN persona  pu ON u.id_persona = pu.id_persona
                LEFT JOIN factura  f  ON f.id_venta   = v.id_venta
                WHERE v.fecha BETWEEN :desde AND :hasta
                  AND v.estado = 1";
        if ($idUsuario > 0) $sql .= " AND v.id_usuario = :idusuario";
        $sql .= " ORDER BY v.fecha DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':desde', $desde);
        $stmt->bindParam(':hasta', $hasta);
        if ($idUsuario > 0) $stmt->bindParam(':idusuario', $idUsuario);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Ganancias agrupadas (día / semana / mes) para el gráfico ───
    public function gananciasPorPeriodo($desde, $hasta, $agrupacion = 'dia', $idUsuario = 0) {
        switch ($agrupacion) {
            case 'semana':
                $grupo    = "YEARWEEK(v.fecha, 3)";
                $etiqueta = "CONCAT('Sem. ', WEEK(v.fecha, 3), ' · ', YEAR(v.fecha))";
                break;
            case 'mes':
                $grupo    = "DATE_FORMAT(v.fecha, '%Y-%m')";
                $etiqueta = "DATE_FORMAT(v.fecha, '%M %Y')";
                break;
            default: // dia
                $grupo    = "v.fecha";
                $etiqueta = "DATE_FORMAT(v.fecha, '%d/%m')";
        }

        $sql = "SELECT
                    $grupo AS periodo_key,
                    MIN($etiqueta)   AS periodo_label,
                    MIN(v.fecha)     AS fecha_orden,
                    COUNT(*)         AS cantidad,
                    COALESCE(SUM(v.total), 0) AS total_vendido,
                    COALESCE(SUM((
                        SELECT SUM(dv2.subtotal - (dv2.costo_unitario * dv2.cantidad))
                        FROM detalle_venta dv2
                        WHERE dv2.id_venta = v.id_venta
                    )), 0) AS ganancia
                FROM venta v
                WHERE v.fecha BETWEEN :desde AND :hasta
                  AND v.estado = 1";
        if ($idUsuario > 0) $sql .= " AND v.id_usuario = :idusuario";
        $sql .= " GROUP BY $grupo ORDER BY fecha_orden ASC";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':desde', $desde);
        $stmt->bindParam(':hasta', $hasta);
        if ($idUsuario > 0) $stmt->bindParam(':idusuario', $idUsuario);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listaUsuarios() {
        $sql = "SELECT u.id_usuario, p.nombre
                FROM usuario u INNER JOIN persona p ON u.id_persona = p.id_persona
                ORDER BY p.nombre ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Reporte de inventario con filtros ───────────────────
    public function reporteInventario($buscar = '', $idCat = 0, $estado = '') {
        $sql = "SELECT
                    p.id_producto,
                    p.nombre     AS producto,
                    c.tipo       AS categoria,
                    COALESCE(i.stock_actual, 0)  AS stock_actual,
                    COALESCE(i.stock_minimo, 0)  AS stock_minimo,
                    i.fecha_actualizacion
                FROM producto p
                LEFT JOIN categoria  c ON p.id_categoria = c.id_categoria
                LEFT JOIN inventario i ON p.id_producto  = i.id_producto
                WHERE 1=1";

        $params = [];
        if ($buscar !== '') {
            $sql .= " AND p.nombre LIKE :buscar";
            $params[':buscar'] = '%' . $buscar . '%';
        }
        if ($idCat > 0) {
            $sql .= " AND p.id_categoria = :idcat";
            $params[':idcat'] = $idCat;
        }
        if ($estado === 'agotado') {
            $sql .= " AND COALESCE(i.stock_actual,0) = 0";
        } elseif ($estado === 'bajo') {
            $sql .= " AND COALESCE(i.stock_actual,0) > 0
                      AND COALESCE(i.stock_actual,0) <= COALESCE(i.stock_minimo,0)";
        } elseif ($estado === 'disponible') {
            $sql .= " AND COALESCE(i.stock_actual,0) > COALESCE(i.stock_minimo,0)";
        }
        $sql .= " ORDER BY COALESCE(i.stock_actual,0) ASC, p.nombre ASC";

        $stmt = $this->conn->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function resumenInventario() {
        $sql = "SELECT
                    COUNT(p.id_producto)                                          AS total_productos,
                    COALESCE(SUM(COALESCE(i.stock_actual,0)),0)                   AS total_unidades,
                    SUM(CASE WHEN COALESCE(i.stock_actual,0) > 0
                              AND COALESCE(i.stock_actual,0) <= COALESCE(i.stock_minimo,0)
                             THEN 1 ELSE 0 END)                                   AS stock_bajo,
                    SUM(CASE WHEN COALESCE(i.stock_actual,0) = 0
                             THEN 1 ELSE 0 END)                                   AS agotados
                FROM producto p
                LEFT JOIN inventario i ON p.id_producto = i.id_producto";
        return $this->conn->query($sql)->fetch(PDO::FETCH_ASSOC);
    }

    // ── Se quitó el filtro "WHERE estado = 1" porque dejaba la
    //    lista vacía (la tabla `categoria` no tiene esa columna,
    //    o ninguna fila tenía ese valor). También se agregó el
    //    execute() que faltaba antes del fetchAll().
    public function listaCategorias() {
        $stmt = $this->conn->prepare(
            "SELECT id_categoria, tipo FROM categoria ORDER BY tipo ASC"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}