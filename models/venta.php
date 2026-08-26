<?php
/**
 * Modelo Venta — alineado a la estructura REAL de bdventas.
 *
 * No existe tabla de precios de producto: el precio de venta se
 * digita línea por línea en el formulario. El costo se obtiene
 * automáticamente de la ÚLTIMA compra registrada del producto.
 *
 * Todas las cantidades se normalizan a una unidad de "contenido"
 * (ej. kilogramos) para poder comparar costo de compra vs precio
 * de venta aunque se compre en Bulto y se venda en Libra.
 *
 * obtenerTodas() y obtenerResumen() aceptan un parámetro opcional
 * $idUsuario:
 *   - null (o no se pasa) -> Administrador: ve TODAS las ventas
 *   - un id_usuario       -> filtra solo las ventas de ESE vendedor
 */
class Venta
{
    private $conn;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    // =========================================================
    // LISTA DE VENTAS (ganancia calculada al vuelo, no se guarda)
    // =========================================================
    public function obtenerTodas($idUsuario = null)
    {
        try {
            $sql = "
                SELECT
                    v.id_venta,
                    v.fecha,
                    v.total,
                    v.estado,
                    v.metodo_pago,
                    pc.nombre AS cliente,
                    pu.nombre AS vendedor,
                    f.numero_factura,
                    COALESCE(g.ganancia, 0) AS ganancia
                FROM venta v
                LEFT JOIN cliente  c  ON v.id_cliente = c.id_cliente
                LEFT JOIN persona  pc ON c.id_persona  = pc.id_persona
                LEFT JOIN usuario  u  ON v.id_usuario  = u.id_usuario
                LEFT JOIN persona  pu ON u.id_persona  = pu.id_persona
                LEFT JOIN factura  f  ON f.id_venta    = v.id_venta
                LEFT JOIN (
                    SELECT
                        id_venta,
                        SUM(subtotal - (costo_unitario * cantidad)) AS ganancia
                    FROM detalle_venta
                    GROUP BY id_venta
                ) g ON g.id_venta = v.id_venta"
                 . ($idUsuario ? " WHERE v.id_usuario = :id" : "") . "
                ORDER BY v.fecha DESC, v.id_venta DESC
            ";

            $stmt = $this->conn->prepare($sql);
            if ($idUsuario) {
                $stmt->bindValue(':id', $idUsuario, PDO::PARAM_INT);
            }
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("Error obtenerTodas ventas: " . $e->getMessage());
            return [];
        }
    }

    // =========================================================
    // KPIs DEL PANEL
    // Histórico completo: no se filtra por estado, así el
    // acumulado no cambia cuando una venta se anula o reactiva.
    // =========================================================
    public function obtenerResumen($idUsuario = null)
    {
        try {
            $sql = "
                SELECT
                    COUNT(*) AS total_ventas,
                    COALESCE(SUM(total), 0) AS ingresos_total,
                    SUM(CASE WHEN DATE(fecha) = CURDATE() THEN 1 ELSE 0 END) AS ventas_hoy,
                    SUM(CASE WHEN DATE(fecha) = CURDATE() THEN total ELSE 0 END) AS ingresos_hoy
                FROM venta"
                 . ($idUsuario ? " WHERE id_usuario = :id" : "");

            $stmt = $this->conn->prepare($sql);
            if ($idUsuario) {
                $stmt->bindValue(':id', $idUsuario, PDO::PARAM_INT);
            }
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("Error obtenerResumen ventas: " . $e->getMessage());
            return ['total_ventas' => 0, 'ingresos_total' => 0, 'ventas_hoy' => 0, 'ingresos_hoy' => 0];
        }
    }

    // =========================================================
    // CLIENTES ACTIVOS (para el select del modal)
    // Compartidos: cualquier vendedor puede venderle a cualquier
    // cliente activo del sistema.
    // =========================================================
    public function obtenerClientes()
    {
        try {
            $sql = "
                SELECT c.id_cliente, p.nombre
                FROM cliente c
                INNER JOIN persona p ON c.id_persona = p.id_persona
                WHERE c.estado = 1
                ORDER BY p.nombre ASC
            ";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("Error obtenerClientes: " . $e->getMessage());
            return [];
        }
    }

    // =========================================================
    // PRODUCTOS ACTIVOS + STOCK (para el select del modal)
    // No trae precio: el precio se digita al vender.
    // =========================================================
    public function obtenerProductosDisponibles()
    {
        try {
            $sql = "
                SELECT
                    p.id_producto,
                    p.nombre,
                    p.imagen,
                    COALESCE(i.stock_actual, 0) AS stock
                FROM producto p
                LEFT JOIN inventario i ON p.id_producto = i.id_producto
                WHERE p.estado = 1
                ORDER BY p.nombre ASC
            ";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("Error obtenerProductosDisponibles: " . $e->getMessage());
            return [];
        }
    }

    // =========================================================
    // ALIAS: obtenerProductos()
    // =========================================================
    public function obtenerProductos()
    {
        return $this->obtenerProductosDisponibles();
    }

    // =========================================================
    // UNIDADES DE MEDIDA (para los selects de unidad / contenido)
    // =========================================================
    public function obtenerUnidades()
    {
        try {
            $sql = "SELECT id_unidad, nombre FROM unidades_medida ORDER BY nombre ASC";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("Error obtenerUnidades: " . $e->getMessage());
            return [];
        }
    }

    // =========================================================
    // COSTO DE REFERENCIA DE UN PRODUCTO
    // Toma la ÚLTIMA compra registrada y devuelve el costo
    // por UNIDAD DE CONTENIDO (ej. costo por kg).
    // =========================================================
    private function obtenerCostoPorContenido($id_producto)
    {
        try {
            $sql = "
                SELECT
                    dc.precio_compra,
                    dc.cantidad_por_unidad
                FROM detalle_compra dc
                INNER JOIN compra c ON dc.id_compra = c.id_compra
                WHERE dc.id_producto = :id_producto
                ORDER BY c.fecha DESC, dc.id_detalle DESC
                LIMIT 1
            ";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':id_producto', (int)$id_producto, PDO::PARAM_INT);
            $stmt->execute();

            $fila = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$fila) {
                return 0;
            }

            $precioCompra   = (float)$fila['precio_compra'];
            $cantPorUnidad  = (int)($fila['cantidad_por_unidad'] ?? 0);

            return $cantPorUnidad > 0
                ? round($precioCompra / $cantPorUnidad, 2)
                : $precioCompra;

        } catch (PDOException $e) {
            error_log("Error obtenerCostoPorContenido: " . $e->getMessage());
            return 0;
        }
    }

    // =========================================================
    // DETALLE DE UNA VENTA (con ganancia por línea)
    // =========================================================
    public function obtenerDetalle($id_venta)
    {
        try {
            $sql = "
                SELECT
                    dv.id_detalle,
                    dv.id_producto,
                    p.nombre AS producto,
                    dv.cantidad,
                    dv.precio_venta,
                    dv.descuento_porcentaje,
                    dv.descuento_valor,
                    dv.subtotal,
                    dv.costo_unitario,
                    (dv.subtotal - (dv.costo_unitario * dv.cantidad)) AS ganancia_linea,
                    dv.cantidad_por_unidad,
                    uv.nombre AS unidad_venta,
                    uc.nombre AS unidad_contenido
                FROM detalle_venta dv
                INNER JOIN producto p ON dv.id_producto = p.id_producto
                LEFT JOIN unidades_medida uv ON dv.id_unidad = uv.id_unidad
                LEFT JOIN unidades_medida uc ON dv.id_unidad_contenido = uc.id_unidad
                WHERE dv.id_venta = :id_venta
                ORDER BY dv.id_detalle ASC
            ";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':id_venta', (int)$id_venta, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("Error obtenerDetalle venta: " . $e->getMessage());
            return [];
        }
    }

    // =========================================================
    // CABECERA COMPLETA DE UNA VENTA (para el comprobante)
    // Incluye id_usuario para poder validar dueño (vendedor)
    // antes de mostrar la factura.
    // =========================================================
    public function obtenerVentaCompleta($id_venta)
    {
        try {
            $sql = "
                SELECT
                    v.id_venta,
                    v.id_usuario,
                    v.fecha,
                    v.total,
                    v.metodo_pago,
                    v.estado,
                    pc.nombre AS cliente,
                    pu.nombre AS vendedor,
                    f.numero_factura,
                    f.fecha_emision,
                    f.subtotal AS factura_subtotal,
                    f.descuento_valor AS factura_descuento
                FROM venta v
                LEFT JOIN cliente c  ON v.id_cliente = c.id_cliente
                LEFT JOIN persona pc ON c.id_persona  = pc.id_persona
                LEFT JOIN usuario u  ON v.id_usuario  = u.id_usuario
                LEFT JOIN persona pu ON u.id_persona  = pu.id_persona
                LEFT JOIN factura f  ON f.id_venta    = v.id_venta
                WHERE v.id_venta = :id_venta
                LIMIT 1
            ";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':id_venta', (int)$id_venta, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("Error obtenerVentaCompleta: " . $e->getMessage());
            return false;
        }
    }

    // =========================================================
    // REGISTRAR VENTA COMPLETA (TRANSACCIÓN)
    //
    // $items[] = [
    //   'id_producto', 'cantidad', 'precio_venta', 'descuento_porcentaje',
    //   'id_unidad', 'cantidad_por_unidad', 'id_unidad_contenido'
    // ]
    // =========================================================
    public function registrar($id_usuario, $id_cliente, $metodo_pago, $items)
    {
        try {
            $this->conn->beginTransaction();

            $stmtCli = $this->conn->prepare(
                "SELECT id_cliente FROM cliente WHERE id_cliente = :id AND estado = 1 LIMIT 1"
            );
            $stmtCli->bindValue(':id', (int)$id_cliente, PDO::PARAM_INT);
            $stmtCli->execute();

            if (!$stmtCli->fetch()) {
                $this->conn->rollBack();
                return "El cliente seleccionado no existe o está inactivo.";
            }

            $itemsCalculados = [];
            $total = 0;

            foreach ($items as $item) {

                $id_producto  = (int)$item['id_producto'];
                $cantidad     = (int)$item['cantidad'];
                $precio_venta = (float)$item['precio_venta'];
                $desc_pct     = (float)($item['descuento_porcentaje'] ?? 0);
                $id_unidad    = !empty($item['id_unidad']) ? (int)$item['id_unidad'] : null;
                $cant_x_und   = !empty($item['cantidad_por_unidad']) ? (int)$item['cantidad_por_unidad'] : 1;
                $id_und_cont  = !empty($item['id_unidad_contenido']) ? (int)$item['id_unidad_contenido'] : $id_unidad;

                if ($cantidad <= 0 || $precio_venta <= 0) {
                    $this->conn->rollBack();
                    return "Cantidad o precio inválido en uno de los productos.";
                }

                if ($desc_pct < 0 || $desc_pct > 100) {
                    $this->conn->rollBack();
                    return "El descuento debe estar entre 0% y 100%.";
                }

                $stmtProd = $this->conn->prepare(
                    "SELECT p.nombre, COALESCE(i.stock_actual, 0) AS stock
                     FROM producto p
                     LEFT JOIN inventario i ON p.id_producto = i.id_producto
                     WHERE p.id_producto = :id AND p.estado = 1
                     LIMIT 1 FOR UPDATE"
                );
                $stmtProd->bindValue(':id', $id_producto, PDO::PARAM_INT);
                $stmtProd->execute();
                $prod = $stmtProd->fetch(PDO::FETCH_ASSOC);

                if (!$prod) {
                    $this->conn->rollBack();
                    return "Uno de los productos ya no existe o está inactivo.";
                }

                $cantidadContenido = $cantidad * $cant_x_und;

                if ($cantidadContenido > (int)$prod['stock']) {
                    $this->conn->rollBack();
                    return "Stock insuficiente para \"{$prod['nombre']}\". Disponible: {$prod['stock']}.";
                }

                $descuento_valor = round($precio_venta * $cantidad * $desc_pct / 100, 2);
                $subtotal        = round(($precio_venta * $cantidad) - $descuento_valor, 2);

                $costoPorContenido = $this->obtenerCostoPorContenido($id_producto);
                $costo_unitario    = round($costoPorContenido * $cant_x_und, 2);

                $total += $subtotal;

                $itemsCalculados[] = [
                    'id_producto'          => $id_producto,
                    'cantidad'             => $cantidad,
                    'precio_venta'         => $precio_venta,
                    'descuento_porcentaje' => $desc_pct,
                    'descuento_valor'      => $descuento_valor,
                    'subtotal'             => $subtotal,
                    'id_unidad'            => $id_unidad,
                    'cantidad_por_unidad'  => $cant_x_und,
                    'id_unidad_contenido'  => $id_und_cont,
                    'costo_unitario'       => $costo_unitario,
                    'cantidad_contenido'   => $cantidadContenido,
                ];
            }

            if (empty($itemsCalculados)) {
                $this->conn->rollBack();
                return "Debes agregar al menos un producto.";
            }

            $stmtV = $this->conn->prepare(
                "INSERT INTO venta (id_cliente, id_usuario, fecha, total, metodo_pago, estado)
                 VALUES (:id_cliente, :id_usuario, NOW(), :total, :metodo_pago, 1)"
            );
            $stmtV->execute([
                ':id_cliente'  => $id_cliente,
                ':id_usuario'  => $id_usuario,
                ':total'       => $total,
                ':metodo_pago' => $metodo_pago,
            ]);
            $id_venta = (int)$this->conn->lastInsertId();

            $stmtD = $this->conn->prepare(
                "INSERT INTO detalle_venta
                     (id_venta, id_producto, cantidad, precio_venta,
                      descuento_porcentaje, descuento_valor, subtotal,
                      id_unidad, cantidad_por_unidad, id_unidad_contenido, costo_unitario)
                 VALUES
                     (:id_venta, :id_producto, :cantidad, :precio_venta,
                      :descuento_porcentaje, :descuento_valor, :subtotal,
                      :id_unidad, :cantidad_por_unidad, :id_unidad_contenido, :costo_unitario)"
            );

            $stmtI = $this->conn->prepare(
                "UPDATE inventario
                 SET stock_actual = stock_actual - :cantidad_contenido,
                     fecha_actualizacion = NOW()
                 WHERE id_producto = :id_producto"
            );

            foreach ($itemsCalculados as $it) {

                $stmtD->execute([
                    ':id_venta'             => $id_venta,
                    ':id_producto'          => $it['id_producto'],
                    ':cantidad'             => $it['cantidad'],
                    ':precio_venta'         => $it['precio_venta'],
                    ':descuento_porcentaje' => $it['descuento_porcentaje'],
                    ':descuento_valor'      => $it['descuento_valor'],
                    ':subtotal'             => $it['subtotal'],
                    ':id_unidad'            => $it['id_unidad'],
                    ':cantidad_por_unidad'  => $it['cantidad_por_unidad'],
                    ':id_unidad_contenido'  => $it['id_unidad_contenido'],
                    ':costo_unitario'       => $it['costo_unitario'],
                ]);

                $stmtI->execute([
                    ':cantidad_contenido' => $it['cantidad_contenido'],
                    ':id_producto'        => $it['id_producto'],
                ]);
            }

            $descuentoTotal   = array_sum(array_column($itemsCalculados, 'descuento_valor'));
            $subtotalSinDesc  = $total + $descuentoTotal;
            $numeroFactura    = $this->generarNumeroFactura();

            $stmtF = $this->conn->prepare(
                "INSERT INTO factura
                     (id_venta, numero_factura, fecha_emision, subtotal, descuento_valor, total, estado)
                 VALUES
                     (:id_venta, :numero, NOW(), :subtotal, :descuento, :total, 1)"
            );
            $stmtF->execute([
                ':id_venta'  => $id_venta,
                ':numero'    => $numeroFactura,
                ':subtotal'  => $subtotalSinDesc,
                ':descuento' => $descuentoTotal,
                ':total'     => $total,
            ]);

            $this->conn->commit();

            return ['id_venta' => $id_venta, 'numero_factura' => $numeroFactura];

        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Error registrar venta: " . $e->getMessage());
            return "Error al registrar la venta: " . $e->getMessage();
        }
    }

    // =========================================================
    // NÚMERO DE FACTURA CORRELATIVO
    // =========================================================
    private function generarNumeroFactura()
    {
        $stmt = $this->conn->prepare("SELECT COUNT(*) AS total FROM factura");
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $numero = (int)$row['total'] + 1;

        return 'FAC-' . str_pad($numero, 6, '0', STR_PAD_LEFT);
    }

    // =========================================================
    // ANULAR VENTA (no se borra físicamente, se conserva el
    // histórico y se devuelve el stock al inventario)
    // =========================================================
    public function eliminar($id_venta)
    {
        try {
            $this->conn->beginTransaction();

            $stmtV = $this->conn->prepare(
                "SELECT id_venta FROM venta WHERE id_venta = :id AND estado = 1 LIMIT 1 FOR UPDATE"
            );
            $stmtV->bindValue(':id', (int)$id_venta, PDO::PARAM_INT);
            $stmtV->execute();

            if (!$stmtV->fetch()) {
                $this->conn->rollBack();
                return "La venta no existe o ya está anulada.";
            }

            $stmtLineas = $this->conn->prepare(
                "SELECT id_producto, cantidad, cantidad_por_unidad
                 FROM detalle_venta
                 WHERE id_venta = :id"
            );
            $stmtLineas->bindValue(':id', (int)$id_venta, PDO::PARAM_INT);
            $stmtLineas->execute();
            $lineas = $stmtLineas->fetchAll(PDO::FETCH_ASSOC);

            $stmtRestaurar = $this->conn->prepare(
                "UPDATE inventario
                 SET stock_actual = stock_actual + :cantidad_contenido,
                     fecha_actualizacion = NOW()
                 WHERE id_producto = :id_producto"
            );

            foreach ($lineas as $l) {
                $cantPorUnidad = (int)($l['cantidad_por_unidad'] ?: 1);
                $cantidadContenido = (int)$l['cantidad'] * $cantPorUnidad;

                $stmtRestaurar->execute([
                    ':cantidad_contenido' => $cantidadContenido,
                    ':id_producto'        => $l['id_producto'],
                ]);
            }

            $this->conn->prepare("UPDATE venta   SET estado = 0 WHERE id_venta = ?")->execute([$id_venta]);
            $this->conn->prepare("UPDATE factura SET estado = 0 WHERE id_venta = ?")->execute([$id_venta]);

            $this->conn->commit();
            return true;

        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Error anular venta: " . $e->getMessage());
            return "Error al anular la venta: " . $e->getMessage();
        }
    }

    // =========================================================
    // REACTIVAR VENTA (revierte una anulación: vuelve a poner
    // la venta y su factura como activas, y vuelve a descontar
    // el inventario. Si ya no hay stock suficiente, se rechaza.)
    // =========================================================
    public function reactivar($id_venta)
    {
        try {
            $this->conn->beginTransaction();

            $stmtV = $this->conn->prepare(
                "SELECT id_venta FROM venta WHERE id_venta = :id AND estado = 0 LIMIT 1 FOR UPDATE"
            );
            $stmtV->bindValue(':id', (int)$id_venta, PDO::PARAM_INT);
            $stmtV->execute();

            if (!$stmtV->fetch()) {
                $this->conn->rollBack();
                return "La venta no existe o ya está activa.";
            }

            $stmtLineas = $this->conn->prepare(
                "SELECT dv.id_producto, dv.cantidad, dv.cantidad_por_unidad,
                        p.nombre, COALESCE(i.stock_actual, 0) AS stock
                 FROM detalle_venta dv
                 INNER JOIN producto p ON p.id_producto = dv.id_producto
                 LEFT JOIN inventario i ON i.id_producto = dv.id_producto
                 WHERE dv.id_venta = :id
                 FOR UPDATE"
            );
            $stmtLineas->bindValue(':id', (int)$id_venta, PDO::PARAM_INT);
            $stmtLineas->execute();
            $lineas = $stmtLineas->fetchAll(PDO::FETCH_ASSOC);

            foreach ($lineas as $l) {
                $cantPorUnidad     = (int)($l['cantidad_por_unidad'] ?: 1);
                $cantidadContenido = (int)$l['cantidad'] * $cantPorUnidad;

                if ($cantidadContenido > (int)$l['stock']) {
                    $this->conn->rollBack();
                    return "Stock insuficiente para \"{$l['nombre']}\" al reactivar. Disponible: {$l['stock']}, se necesitan: {$cantidadContenido}.";
                }
            }

            $stmtDescontar = $this->conn->prepare(
                "UPDATE inventario
                 SET stock_actual = stock_actual - :cantidad_contenido,
                     fecha_actualizacion = NOW()
                 WHERE id_producto = :id_producto"
            );

            foreach ($lineas as $l) {
                $cantPorUnidad     = (int)($l['cantidad_por_unidad'] ?: 1);
                $cantidadContenido = (int)$l['cantidad'] * $cantPorUnidad;

                $stmtDescontar->execute([
                    ':cantidad_contenido' => $cantidadContenido,
                    ':id_producto'        => $l['id_producto'],
                ]);
            }

            $this->conn->prepare("UPDATE venta   SET estado = 1 WHERE id_venta = ?")->execute([$id_venta]);
            $this->conn->prepare("UPDATE factura SET estado = 1 WHERE id_venta = ?")->execute([$id_venta]);

            $this->conn->commit();
            return true;

        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Error reactivar venta: " . $e->getMessage());
            return "Error al reactivar la venta: " . $e->getMessage();
        }
    }
}