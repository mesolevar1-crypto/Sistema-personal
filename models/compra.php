<?php

/**
 * ============================================================
 * MODELO: Compra
 * ============================================================
 *
 * CORRECCIÓN IMPORTANTE SOBRE LA VERSIÓN ANTERIOR:
 *
 * detalle_compra.cantidad es de tipo INT (confirmado por
 * DESCRIBE). La versión anterior trataba la cantidad como
 * decimal (float) en todo el flujo, lo que causaba un
 * descuadre real:
 *
 *   1. Se ingresaba una cantidad decimal (ej. 2.5 bultos).
 *   2. El subtotal y el inventario se calculaban con 2.5.
 *   3. Al guardar, MySQL truncaba cantidad a 2 (por ser INT).
 *   4. Al eliminar la compra, eliminar() releía cantidad desde
 *      la BD (ya truncada a 2) y revertía el inventario con
 *      2 en vez de 2.5 -> el inventario quedaba mal para
 *      siempre.
 *
 * SOLUCIÓN: cantidad se trata como entero en TODO el flujo
 * (modelo, controlador y vista), así lo que se calcula, se
 * guarda y se revierte es siempre exactamente el mismo número.
 * unidades_por_presentacion también es INT, así que toda la
 * aritmética de inventario queda en enteros.
 *
 * Si en el futuro necesitas cantidades fraccionarias (ej.
 * comprar 2.5 kg), hay que alterar detalle_compra.cantidad a
 * DECIMAL(10,2) primero -- avísame y ajusto el código.
 *
 * Tablas reales involucradas:
 *
 * compras
 * - id_compra (PK)
 * - id_proveedor (FK -> proveedor)
 * - id_usuario (FK -> usuario)
 * - fecha
 * - total
 * - estado
 *
 * detalle_compra
 * - id_detalle (PK)
 * - id_compra (FK -> compras)
 * - id_precio (FK -> producto_precios)
 * - cantidad            INT
 * - precio_unitario     DECIMAL
 * - subtotal            DECIMAL
 *
 * producto
 * - id_producto (PK)
 * - nombre
 * - descripcion
 * - id_categoria (FK -> categoria)
 * - imagen
 * - estado
 *
 * categoria
 * - id_categoria (PK)
 * - tipo
 *
 * producto_precios
 * - id_precio (PK)
 * - id_producto (FK -> producto)
 * - id_proveedor (FK -> proveedor)
 * - precio_compra              decimal(10,2)
 * - unidades_por_presentacion  int
 * - precio_venta               decimal(10,2)
 * - estado                     tinyint(1)
 * - unidad_compra              varchar(60)  -- TEXTO LIBRE, no FK
 * - unidad_venta               varchar(50)  -- TEXTO LIBRE, no FK
 *
 * inventario
 * - id_inventario (PK)
 * - id_producto (FK -> producto)
 * - stock_actual
 * - stock_minimo
 * - fecha_actualizacion
 *
 * proveedor
 * - id_proveedor (PK)
 * - id_persona (FK -> persona)
 * - frecuencia_entrega
 *
 * NOTA:
 * El estado activo/inactivo del proveedor vive en
 * persona.estado, porque proveedor no tiene columna propia.
 *
 * ============================================================
 */

class Compra
{
    private $db;


    public function __construct($db)
    {
        $this->db = $db;
    }


    /**
     * ========================================================
     * OBTENER TODAS LAS COMPRAS
     * ========================================================
     */

    public function obtenerTodas()
    {
        try {

            $sql = "
                SELECT
                    c.id_compra,
                    c.id_proveedor,
                    c.id_usuario,
                    c.fecha,
                    c.total,
                    c.estado,

                    COALESCE(per.nombre, 'Sin nombre') AS proveedor,

                    TRIM(
                        CONCAT(
                            COALESCE(u.nombre, ''),
                            ' ',
                            COALESCE(u.apellido, '')
                        )
                    ) AS comprador

                FROM compras c

                INNER JOIN proveedor pr
                    ON pr.id_proveedor = c.id_proveedor

                INNER JOIN persona per
                    ON per.id_persona = pr.id_persona

                INNER JOIN usuario u
                    ON u.id_usuario = c.id_usuario

                WHERE c.estado = 1

                ORDER BY
                    c.fecha DESC,
                    c.id_compra DESC
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {

            error_log('Error obtenerTodas compras: ' . $e->getMessage());
            return [];
        }
    }


    /**
     * ========================================================
     * RESUMEN DE COMPRAS
     * ========================================================
     */

    public function obtenerResumen()
    {
        try {

            $sql = "
                SELECT

                    COUNT(*) AS total_compras,

                    COALESCE(SUM(total), 0) AS gasto_total,

                    COALESCE(
                        SUM(CASE WHEN DATE(fecha) = CURDATE() THEN 1 ELSE 0 END),
                        0
                    ) AS compras_hoy,

                    COALESCE(
                        SUM(CASE WHEN DATE(fecha) = CURDATE() THEN total ELSE 0 END),
                        0
                    ) AS gasto_hoy

                FROM compras

                WHERE estado = 1
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

            return $resultado ?: [
                'total_compras' => 0,
                'gasto_total'   => 0,
                'compras_hoy'   => 0,
                'gasto_hoy'     => 0
            ];

        } catch (PDOException $e) {

            error_log('Error obtenerResumen compras: ' . $e->getMessage());

            return [
                'total_compras' => 0,
                'gasto_total'   => 0,
                'compras_hoy'   => 0,
                'gasto_hoy'     => 0
            ];
        }
    }


    /**
     * ========================================================
     * OBTENER PROVEEDORES ACTIVOS
     * ========================================================
     */

    public function obtenerProveedores()
    {
        try {

            $sql = "
                SELECT
                    pr.id_proveedor,
                    pr.id_persona,
                    COALESCE(per.nombre, 'Sin nombre') AS nombre

                FROM proveedor pr

                INNER JOIN persona per
                    ON per.id_persona = pr.id_persona

                WHERE per.estado = 1

                ORDER BY per.nombre ASC
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {

            error_log('Error obtenerProveedores: ' . $e->getMessage());
            return [];
        }
    }


    /**
     * ========================================================
     * OBTENER PRODUCTOS/PRECIOS
     * ========================================================
     *
     * Devuelve las condiciones de compra/venta (producto_precios)
     * ya pactadas, activas, para productos activos. La vista
     * filtra en JS por id_proveedor.
     * ========================================================
     */

    public function obtenerProductosPrecios()
    {
        try {

            $sql = "
                SELECT

                    pp.id_precio,
                    pp.id_producto,
                    pp.id_proveedor,

                    pp.precio_compra,
                    pp.unidades_por_presentacion,
                    pp.precio_venta,
                    pp.estado,
                    pp.unidad_compra,
                    pp.unidad_venta,

                    p.nombre AS producto,
                    p.descripcion,
                    p.id_categoria,
                    p.imagen,

                    c.tipo AS categoria

                FROM producto_precios pp

                INNER JOIN producto p
                    ON p.id_producto = pp.id_producto

                LEFT JOIN categoria c
                    ON c.id_categoria = p.id_categoria

                WHERE
                    pp.estado = 1
                    AND p.estado = 1

                ORDER BY p.nombre ASC
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {

            error_log('Error obtenerProductosPrecios: ' . $e->getMessage());
            return [];
        }
    }


    /**
     * ========================================================
     * REGISTRAR COMPRA
     * ========================================================
     *
     * Flujo:
     *
     * 1. Valida usuario, proveedor e items.
     * 2. Verifica que el proveedor esté activo.
     * 3. Por cada item, verifica que id_precio pertenezca a
     *    ese proveedor y esté activo (producto también activo).
     * 4. Toma precio_compra REAL desde producto_precios
     *    (nunca confía en datos del navegador).
     * 5. cantidad se castea SIEMPRE a entero (coincide con el
     *    tipo INT real de detalle_compra.cantidad), así el
     *    subtotal, el inventario y lo que queda guardado en BD
     *    son siempre el mismo número -- sin truncamientos
     *    silenciosos de MySQL.
     * 6. Crea cabecera en compras, detalle_compra, actualiza
     *    inventario (crea la fila si no existe).
     * 7. Actualiza el total real de la compra.
     *
     * Todo dentro de una transacción.
     * ========================================================
     */

    public function registrar($id_usuario, $id_proveedor, $items)
    {
        if ((int)$id_usuario <= 0) {
            return 'El usuario no es válido.';
        }

        if ((int)$id_proveedor <= 0) {
            return 'Debes seleccionar un proveedor.';
        }

        if (!is_array($items) || empty($items)) {
            return 'Debes agregar al menos un producto a la compra.';
        }

        try {

            $this->db->beginTransaction();

            // --------------------------------------------------
            // VERIFICAR PROVEEDOR ACTIVO
            // --------------------------------------------------

            $sqlProveedor = "
                SELECT pr.id_proveedor

                FROM proveedor pr

                INNER JOIN persona per
                    ON per.id_persona = pr.id_persona

                WHERE
                    pr.id_proveedor = :id_proveedor
                    AND per.estado = 1

                LIMIT 1
            ";

            $stmtProveedor = $this->db->prepare($sqlProveedor);
            $stmtProveedor->execute([':id_proveedor' => (int)$id_proveedor]);

            if (!$stmtProveedor->fetch()) {
                $this->db->rollBack();
                return 'El proveedor seleccionado no existe o no está activo.';
            }

            // --------------------------------------------------
            // CONSULTA DEL PRODUCTO/PRECIO REAL
            // --------------------------------------------------

            $sqlPrecio = "
                SELECT

                    pp.id_precio,
                    pp.id_producto,
                    pp.id_proveedor,
                    pp.precio_compra,
                    pp.unidades_por_presentacion,
                    pp.unidad_compra,
                    pp.unidad_venta,

                    p.nombre AS producto

                FROM producto_precios pp

                INNER JOIN producto p
                    ON p.id_producto = pp.id_producto

                WHERE
                    pp.id_precio = :id_precio
                    AND pp.id_proveedor = :id_proveedor
                    AND pp.estado = 1
                    AND p.estado = 1

                LIMIT 1
            ";

            $stmtPrecio = $this->db->prepare($sqlPrecio);

            // --------------------------------------------------
            // INSERT CABECERA DE COMPRA
            // --------------------------------------------------

            $sqlCompra = "
                INSERT INTO compras
                (id_proveedor, id_usuario, fecha, total, estado)
                VALUES
                (:id_proveedor, :id_usuario, NOW(), 0, 1)
            ";

            $stmtCompra = $this->db->prepare($sqlCompra);

            $stmtCompra->execute([
                ':id_proveedor' => (int)$id_proveedor,
                ':id_usuario'   => (int)$id_usuario
            ]);

            $idCompra = (int)$this->db->lastInsertId();

            if ($idCompra <= 0) {
                throw new Exception('No se pudo crear la compra.');
            }

            // --------------------------------------------------
            // INSERT DETALLE
            // --------------------------------------------------

            $sqlDetalle = "
                INSERT INTO detalle_compra
                (id_compra, id_precio, cantidad, precio_unitario, subtotal)
                VALUES
                (:id_compra, :id_precio, :cantidad, :precio_unitario, :subtotal)
            ";

            $stmtDetalle = $this->db->prepare($sqlDetalle);

            // --------------------------------------------------
            // INVENTARIO
            // --------------------------------------------------

            $sqlInventario = "
                SELECT id_inventario, stock_actual

                FROM inventario

                WHERE id_producto = :id_producto

                LIMIT 1

                FOR UPDATE
            ";

            $stmtInventario = $this->db->prepare($sqlInventario);

            $sqlActualizarInventario = "
                UPDATE inventario

                SET
                    stock_actual = stock_actual + :cantidad,
                    fecha_actualizacion = NOW()

                WHERE id_inventario = :id_inventario
            ";

            $stmtActualizarInventario = $this->db->prepare($sqlActualizarInventario);

            $sqlCrearInventario = "
                INSERT INTO inventario
                (id_producto, stock_actual, stock_minimo, fecha_actualizacion)
                VALUES
                (:id_producto, :stock_actual, 0, NOW())
            ";

            $stmtCrearInventario = $this->db->prepare($sqlCrearInventario);

            $totalCompra = 0;

            // --------------------------------------------------
            // PROCESAR CADA PRODUCTO DE LA COMPRA
            // --------------------------------------------------

            foreach ($items as $item) {

                $idPrecio = isset($item['id_precio']) ? (int)$item['id_precio'] : 0;

                // cantidad SIEMPRE entera: coincide con el tipo
                // real de detalle_compra.cantidad (INT). Evita
                // el descuadre de inventario al truncar MySQL.
                $cantidad = isset($item['cantidad']) ? (int)$item['cantidad'] : 0;

                if ($idPrecio <= 0 || $cantidad <= 0) {
                    continue;
                }

                // ----------------------------------------------
                // BUSCAR PRECIO REAL (NUNCA CONFIAR EN EL FRONT)
                // ----------------------------------------------

                $stmtPrecio->execute([
                    ':id_precio'    => $idPrecio,
                    ':id_proveedor' => (int)$id_proveedor
                ]);

                $productoPrecio = $stmtPrecio->fetch(PDO::FETCH_ASSOC);

                if (!$productoPrecio) {
                    throw new Exception(
                        'Uno de los productos seleccionados no pertenece al proveedor elegido o está inactivo.'
                    );
                }

                $idProducto = (int)$productoPrecio['id_producto'];
                $precioCompra = (float)$productoPrecio['precio_compra'];

                $unidadesPresentacion = (int)(
                    $productoPrecio['unidades_por_presentacion'] ?? 1
                );

                if ($unidadesPresentacion <= 0) {
                    $unidadesPresentacion = 1;
                }

                // ----------------------------------------------
                // SUBTOTAL
                // ----------------------------------------------

                $subtotal = $cantidad * $precioCompra;
                $totalCompra += $subtotal;

                // ----------------------------------------------
                // INSERTAR DETALLE
                // ----------------------------------------------

                $stmtDetalle->execute([
                    ':id_compra'       => $idCompra,
                    ':id_precio'       => $idPrecio,
                    ':cantidad'        => $cantidad,
                    ':precio_unitario' => $precioCompra,
                    ':subtotal'        => $subtotal
                ]);

                // ----------------------------------------------
                // CANTIDAD REAL PARA INVENTARIO
                //
                // Ejemplo: 2 bultos, unidades_por_presentacion
                // = 20 -> se suman 40 unidades al inventario.
                // Ambos operandos son enteros -> sin decimales
                // fantasma que luego no cuadren.
                // ----------------------------------------------

                $cantidadInventario = $cantidad * $unidadesPresentacion;

                $stmtInventario->execute([':id_producto' => $idProducto]);
                $inventario = $stmtInventario->fetch(PDO::FETCH_ASSOC);

                if ($inventario) {

                    $stmtActualizarInventario->execute([
                        ':cantidad'      => $cantidadInventario,
                        ':id_inventario' => (int)$inventario['id_inventario']
                    ]);

                } else {

                    $stmtCrearInventario->execute([
                        ':id_producto'  => $idProducto,
                        ':stock_actual' => $cantidadInventario
                    ]);
                }
            }

            if ($totalCompra <= 0) {
                throw new Exception('La compra no contiene productos válidos.');
            }

            // --------------------------------------------------
            // ACTUALIZAR TOTAL REAL DE LA COMPRA
            // --------------------------------------------------

            $sqlActualizarCompra = "
                UPDATE compras
                SET total = :total
                WHERE id_compra = :id_compra
            ";

            $stmtActualizarCompra = $this->db->prepare($sqlActualizarCompra);

            $stmtActualizarCompra->execute([
                ':total'     => $totalCompra,
                ':id_compra' => $idCompra
            ]);

            $this->db->commit();

            return true;

        } catch (Exception $e) {

            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            error_log('Error registrar compra: ' . $e->getMessage());
            return $e->getMessage();
        }
    }


    /**
     * ========================================================
     * OBTENER DETALLE DE UNA COMPRA
     * ========================================================
     */

    public function obtenerDetalle($id_compra)
    {
        try {

            $sql = "
                SELECT

                    dc.id_detalle,
                    dc.id_compra,
                    dc.id_precio,
                    dc.cantidad,
                    dc.precio_unitario,
                    dc.subtotal,

                    pp.id_producto,
                    pp.unidad_compra,
                    pp.unidad_venta,
                    pp.unidades_por_presentacion,
                    pp.precio_venta,

                    p.nombre AS producto

                FROM detalle_compra dc

                INNER JOIN producto_precios pp
                    ON pp.id_precio = dc.id_precio

                INNER JOIN producto p
                    ON p.id_producto = pp.id_producto

                WHERE dc.id_compra = :id_compra

                ORDER BY dc.id_detalle ASC
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id_compra' => (int)$id_compra]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {

            error_log('Error obtenerDetalle compra: ' . $e->getMessage());
            return [];
        }
    }


    /**
     * ========================================================
     * ELIMINAR COMPRA
     * ========================================================
     *
     * 1. Busca los detalles (cantidad ya es entera y exacta,
     *    igual a la que se usó al registrar -- ver nota arriba).
     * 2. Resta del inventario cantidad x unidades_por_presentacion
     *    (sin dejarlo negativo).
     * 3. Elimina detalle_compra y compras.
     *
     * Todo dentro de una transacción.
     * ========================================================
     */

    public function eliminar($id_compra)
    {
        if ((int)$id_compra <= 0) {
            return 'El ID de la compra no es válido.';
        }

        try {

            $this->db->beginTransaction();

            $sqlCompra = "
                SELECT id_compra, id_proveedor, id_usuario, fecha, total, estado

                FROM compras

                WHERE id_compra = :id_compra

                LIMIT 1

                FOR UPDATE
            ";

            $stmtCompra = $this->db->prepare($sqlCompra);
            $stmtCompra->execute([':id_compra' => (int)$id_compra]);

            $compra = $stmtCompra->fetch(PDO::FETCH_ASSOC);

            if (!$compra) {
                $this->db->rollBack();
                return 'La compra no existe.';
            }

            $sqlDetalles = "
                SELECT

                    dc.id_detalle,
                    dc.id_precio,
                    dc.cantidad,

                    pp.id_producto,
                    pp.unidades_por_presentacion

                FROM detalle_compra dc

                INNER JOIN producto_precios pp
                    ON pp.id_precio = dc.id_precio

                WHERE dc.id_compra = :id_compra
            ";

            $stmtDetalles = $this->db->prepare($sqlDetalles);
            $stmtDetalles->execute([':id_compra' => (int)$id_compra]);

            $detalles = $stmtDetalles->fetchAll(PDO::FETCH_ASSOC);

            $sqlRestarInventario = "
                UPDATE inventario

                SET
                    stock_actual = stock_actual - :cantidad,
                    fecha_actualizacion = NOW()

                WHERE id_producto = :id_producto
            ";

            $stmtRestarInventario = $this->db->prepare($sqlRestarInventario);

            $sqlCorregirStock = "
                UPDATE inventario

                SET stock_actual = 0

                WHERE
                    id_producto = :id_producto
                    AND stock_actual < 0
            ";

            $stmtCorregirStock = $this->db->prepare($sqlCorregirStock);

            foreach ($detalles as $detalle) {

                // cantidad ya viene como entero real de BD -- ya
                // no hay riesgo de que difiera de lo que se sumó
                // al inventario en registrar().
                $cantidad = (int)$detalle['cantidad'];

                $unidadesPresentacion = (int)(
                    $detalle['unidades_por_presentacion'] ?? 1
                );

                if ($unidadesPresentacion <= 0) {
                    $unidadesPresentacion = 1;
                }

                $cantidadInventario = $cantidad * $unidadesPresentacion;

                $stmtRestarInventario->execute([
                    ':cantidad'    => $cantidadInventario,
                    ':id_producto' => (int)$detalle['id_producto']
                ]);

                $stmtCorregirStock->execute([
                    ':id_producto' => (int)$detalle['id_producto']
                ]);
            }

            $sqlEliminarDetalles = "
                DELETE FROM detalle_compra
                WHERE id_compra = :id_compra
            ";

            $stmtEliminarDetalles = $this->db->prepare($sqlEliminarDetalles);
            $stmtEliminarDetalles->execute([':id_compra' => (int)$id_compra]);

            $sqlEliminarCompra = "
                DELETE FROM compras
                WHERE id_compra = :id_compra
            ";

            $stmtEliminarCompra = $this->db->prepare($sqlEliminarCompra);
            $stmtEliminarCompra->execute([':id_compra' => (int)$id_compra]);

            $this->db->commit();

            return true;

        } catch (Exception $e) {

            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            error_log('Error eliminar compra: ' . $e->getMessage());
            return $e->getMessage();
        }
    }
}