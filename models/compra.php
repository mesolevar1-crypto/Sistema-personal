<?php

/**
 * ============================================================
 * MODELO: Compra
 * ============================================================
 *
 * Reglas de negocio implementadas (según especificación del
 * módulo de Compras):
 *
 *  - El precio de compra, la unidad de compra, la cantidad y
 *    la cantidad por unidad las escribe el usuario en cada
 *    compra. El sistema NUNCA los calcula ni los toma de un
 *    catálogo de precios.
 *  - Este módulo solo usa `compra`, `detalle_compra` y
 *    `unidades_medida`. No consulta precio de venta ni ninguna
 *    tabla de precios; eso es responsabilidad del módulo de Ventas.
 *  - `unidades_medida` es un catálogo (id_unidad, nombre): el
 *    usuario selecciona la unidad, no la escribe como texto libre.
 *  - subtotal = cantidad * precio_compra (calculado aquí,
 *    nunca confiado desde el navegador).
 *  - total = suma de subtotales (calculado aquí).
 *  - El inventario aumenta en: cantidad * cantidad_por_unidad.
 *  - Cada fila de detalle_compra guarda una COPIA (snapshot) de
 *    precio_compra, id_unidad y cantidad_por_unidad en el
 *    momento de la compra, para que compras antiguas no cambien
 *    si luego cambian las condiciones del producto.
 *  - registrar() y eliminar() corren dentro de una transacción:
 *    todo o nada.
 *  - eliminar() revierte el inventario que la compra había
 *    agregado, pero solo si el stock actual alcanza para
 *    revertir sin quedar negativo (evita romper ventas ya
 *    hechas con ese stock).
 *
 *  NUEVO (ampliación, sin romper lo anterior):
 *  - `detalle_compra.id_unidad_contenido` es una SEGUNDA FK
 *    hacia `unidades_medida`. Representa la unidad en la que se
 *    expresa el CONTENIDO de la unidad de compra.
 *      id_unidad            -> unidad de compra (ej: Bulto)
 *      id_unidad_contenido  -> unidad del contenido (ej: Libra)
 *    Ejemplo: 1 Bulto = 50 Libras -> cantidad_por_unidad = 50,
 *    id_unidad_contenido = Libra.
 *    No cambia el significado de `cantidad` (sigue siendo el
 *    número entero de unidades de compra adquiridas), ni el
 *    cálculo de subtotal/total/inventario.
 * ============================================================
 */

class Compra
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    // ============================================================
    // REGISTRAR COMPRA
    //
    // $items = [
    //   [
    //     'id_producto' => int,
    //     'cantidad' => int,                 // presentaciones compradas
    //     'precio_compra' => float,          // precio por presentación, lo pone el usuario
    //     'id_unidad' => int,                // FK al catálogo de unidades (unidad de compra)
    //     'cantidad_por_unidad' => int,      // ej. 10 (kg por bulto)
    //     'id_unidad_contenido' => int,      // FK al catálogo de unidades (unidad del contenido)
    //   ],
    //   ...
    // ]
    //
    // Retorna true si se registró correctamente, o un string
    // con el mensaje de error si algo falló.
    // ============================================================
    public function registrar(int $id_usuario, int $id_proveedor, array $items)
    {
        if ($id_usuario <= 0) {
            return 'Usuario inválido.';
        }

        if ($id_proveedor <= 0) {
            return 'Debes seleccionar un proveedor.';
        }

        if (empty($items)) {
            return 'La compra debe tener al menos un producto.';
        }

        try {
            // --------------------------------------------------------
            // VALIDAR PROVEEDOR ACTIVO (nunca confiar en el navegador)
            // --------------------------------------------------------
            $stmt = $this->db->prepare("
                SELECT pr.id_proveedor
                FROM proveedor pr
                INNER JOIN persona pe ON pe.id_persona = pr.id_persona
                WHERE pr.id_proveedor = :id_proveedor
                  AND pe.estado = 1
                LIMIT 1
            ");
            $stmt->execute([':id_proveedor' => $id_proveedor]);

            if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
                return 'El proveedor seleccionado no existe o no está activo.';
            }

            // --------------------------------------------------------
            // VALIDAR CADA ITEM Y CALCULAR SUBTOTALES
            // --------------------------------------------------------
            $itemsValidados = [];
            $total = 0.0;

            // El producto solo necesita existir y estar activo -- el
            // proveedor y el producto se registran por separado, es
            // la compra la que los relaciona, no al revés.
            $stmtProducto = $this->db->prepare("
                SELECT p.id_producto
                FROM producto p
                WHERE p.id_producto = :id_producto
                  AND p.estado = 1
                LIMIT 1
            ");

            // La unidad debe existir en el catálogo (se usa tanto para
            // id_unidad como para id_unidad_contenido, es la misma tabla)
            $stmtUnidad = $this->db->prepare("
                SELECT id_unidad
                FROM unidades_medida
                WHERE id_unidad = :id_unidad
                LIMIT 1
            ");

            foreach ($items as $item) {

                $id_producto = (int)($item['id_producto'] ?? 0);
                $cantidad = $item['cantidad'] ?? null;
                $precio_compra = $item['precio_compra'] ?? null;
                $id_unidad = (int)($item['id_unidad'] ?? 0);
                $cantidad_por_unidad = $item['cantidad_por_unidad'] ?? null;
                $id_unidad_contenido = (int)($item['id_unidad_contenido'] ?? 0);

                if ($id_producto <= 0) {
                    return 'Hay un producto inválido en la compra.';
                }

                // Producto activo
                $stmtProducto->execute([':id_producto' => $id_producto]);
                if (!$stmtProducto->fetch(PDO::FETCH_ASSOC)) {
                    return 'Uno de los productos seleccionados no existe o no está activo.';
                }

                // Cantidad: entero mayor que cero (nunca truncar silenciosamente)
                if (!is_int($cantidad) || $cantidad <= 0) {
                    return 'La cantidad debe ser un número entero mayor que cero.';
                }

                // Precio de compra: numérico mayor que cero, lo puso el usuario
                if (!is_numeric($precio_compra) || (float)$precio_compra <= 0) {
                    return 'El precio de compra debe ser un valor numérico mayor que cero.';
                }
                $precio_compra = round((float)$precio_compra, 2);

                // Unidad de compra: debe existir en el catálogo
                if ($id_unidad <= 0) {
                    return 'Debes seleccionar la unidad de compra.';
                }
                $stmtUnidad->execute([':id_unidad' => $id_unidad]);
                if (!$stmtUnidad->fetch(PDO::FETCH_ASSOC)) {
                    return 'La unidad de compra seleccionada no es válida.';
                }

                // Cantidad por unidad: entero mayor que cero
                if (!is_int($cantidad_por_unidad) || $cantidad_por_unidad <= 0) {
                    return 'La cantidad por unidad debe ser un número entero mayor que cero.';
                }

                // Unidad de contenido: debe existir en el catálogo
                // (misma tabla unidades_medida, distinta FK)
                if ($id_unidad_contenido <= 0) {
                    return 'Debes seleccionar la unidad de contenido de cada producto.';
                }
                $stmtUnidad->execute([':id_unidad' => $id_unidad_contenido]);
                if (!$stmtUnidad->fetch(PDO::FETCH_ASSOC)) {
                    return 'La unidad de contenido seleccionada no es válida.';
                }

                $subtotal = round($cantidad * $precio_compra, 2);
                $total += $subtotal;

                $itemsValidados[] = [
                    'id_producto' => $id_producto,
                    'cantidad' => $cantidad,
                    'precio_compra' => $precio_compra,
                    'id_unidad' => $id_unidad,
                    'cantidad_por_unidad' => $cantidad_por_unidad,
                    'id_unidad_contenido' => $id_unidad_contenido,
                    'subtotal' => $subtotal,
                ];
            }

            $total = round($total, 2);

            // --------------------------------------------------------
            // TRANSACCIÓN: crear compra + detalles + actualizar inventario
            // --------------------------------------------------------
            $this->db->beginTransaction();

            $stmtCompra = $this->db->prepare("
                INSERT INTO compra (id_proveedor, id_usuario, fecha, total, estado)
                VALUES (:id_proveedor, :id_usuario, NOW(), :total, 1)
            ");
            $stmtCompra->execute([
                ':id_proveedor' => $id_proveedor,
                ':id_usuario' => $id_usuario,
                ':total' => $total,
            ]);
            $id_compra = (int)$this->db->lastInsertId();

            $stmtDetalle = $this->db->prepare("
                INSERT INTO detalle_compra
                    (id_compra, id_producto, cantidad, precio_compra,
                     cantidad_por_unidad, id_unidad, id_unidad_contenido, subtotal)
                VALUES
                    (:id_compra, :id_producto, :cantidad, :precio_compra,
                     :cantidad_por_unidad, :id_unidad, :id_unidad_contenido, :subtotal)
            ");

            $stmtInventarioExiste = $this->db->prepare("
                SELECT id_inventario, stock_actual
                FROM inventario
                WHERE id_producto = :id_producto
                LIMIT 1
                FOR UPDATE
            ");

            $stmtInventarioActualizar = $this->db->prepare("
                UPDATE inventario
                SET stock_actual = stock_actual + :unidades,
                    fecha_actualizacion = NOW()
                WHERE id_producto = :id_producto
            ");

            $stmtInventarioCrear = $this->db->prepare("
                INSERT INTO inventario (id_producto, stock_actual, stcok_minimo, fecha_actualizacion)
                VALUES (:id_producto, :unidades, 0, NOW())
            ");

            foreach ($itemsValidados as $item) {

                $stmtDetalle->execute([
                    ':id_compra' => $id_compra,
                    ':id_producto' => $item['id_producto'],
                    ':cantidad' => $item['cantidad'],
                    ':precio_compra' => $item['precio_compra'],
                    ':cantidad_por_unidad' => $item['cantidad_por_unidad'],
                    ':id_unidad' => $item['id_unidad'],
                    ':id_unidad_contenido' => $item['id_unidad_contenido'],
                    ':subtotal' => $item['subtotal'],
                ]);

                $unidadesInventario = $item['cantidad'] * $item['cantidad_por_unidad'];

                $stmtInventarioExiste->execute([':id_producto' => $item['id_producto']]);
                $filaInventario = $stmtInventarioExiste->fetch(PDO::FETCH_ASSOC);

                if ($filaInventario) {
                    $stmtInventarioActualizar->execute([
                        ':unidades' => $unidadesInventario,
                        ':id_producto' => $item['id_producto'],
                    ]);
                } else {
                    $stmtInventarioCrear->execute([
                        ':id_producto' => $item['id_producto'],
                        ':unidades' => $unidadesInventario,
                    ]);
                }
            }

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return 'DEBUG: ' . $e->getMessage();
        }
    }

    // ============================================================
    // ELIMINAR COMPRA
    //
    // Revierte el inventario que la compra había agregado.
    // Si el stock actual no alcanza para revertir (porque ya se
    // vendió parte de ese stock), se cancela la eliminación para
    // no dejar el inventario en un estado inválido.
    // ============================================================
    public function eliminar(int $id_compra)
    {
        if ($id_compra <= 0) {
            return 'Compra inválida.';
        }

        try {
            $this->db->beginTransaction();

            $stmtDetalles = $this->db->prepare("
                SELECT id_producto, cantidad, cantidad_por_unidad
                FROM detalle_compra
                WHERE id_compra = :id_compra
            ");
            $stmtDetalles->execute([':id_compra' => $id_compra]);
            $detalles = $stmtDetalles->fetchAll(PDO::FETCH_ASSOC);

            if (empty($detalles)) {
                $this->db->rollBack();
                return 'La compra no existe o ya fue eliminada.';
            }

            $stmtInventario = $this->db->prepare("
                SELECT stock_actual
                FROM inventario
                WHERE id_producto = :id_producto
                LIMIT 1
                FOR UPDATE
            ");

            $stmtRestar = $this->db->prepare("
                UPDATE inventario
                SET stock_actual = stock_actual - :unidades,
                    fecha_actualizacion = NOW()
                WHERE id_producto = :id_producto
            ");

            // Primero se valida TODO antes de tocar nada (todo o nada)
            foreach ($detalles as $d) {
                $unidadesARevertir = $d['cantidad'] * $d['cantidad_por_unidad'];

                $stmtInventario->execute([':id_producto' => $d['id_producto']]);
                $filaInventario = $stmtInventario->fetch(PDO::FETCH_ASSOC);

                $stockActual = $filaInventario ? (float)$filaInventario['stock_actual'] : 0;

                if ($stockActual < $unidadesARevertir) {
                    $this->db->rollBack();
                    return 'No se puede eliminar: parte de este stock ya fue utilizado (vendido) y revertirlo dejaría el inventario en negativo.';
                }
            }

            // Ahora sí se revierte
            foreach ($detalles as $d) {
                $unidadesARevertir = $d['cantidad'] * $d['cantidad_por_unidad'];
                $stmtRestar->execute([
                    ':unidades' => $unidadesARevertir,
                    ':id_producto' => $d['id_producto'],
                ]);
            }

            $stmtBorrarDetalle = $this->db->prepare("DELETE FROM detalle_compra WHERE id_compra = :id_compra");
            $stmtBorrarDetalle->execute([':id_compra' => $id_compra]);

            $stmtBorrarCompra = $this->db->prepare("DELETE FROM compra WHERE id_compra = :id_compra");
            $stmtBorrarCompra->execute([':id_compra' => $id_compra]);

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return 'Ocurrió un error al eliminar la compra. Inténtalo nuevamente.';
        }
    }

    // ============================================================
    // OBTENER DETALLE (para el modal de "Ver detalle")
    //
    // Se hace LEFT JOIN con `unidades_medida` DOS VECES:
    //   u  -> unidad de compra (id_unidad)
    //   uc -> unidad de contenido (id_unidad_contenido)
    // Los alias `unidad_compra` y `precio_unitario` se mantienen
    // para no tener que tocar el JS existente de la vista. Se
    // agrega el alias nuevo `unidad_contenido`.
    // ============================================================
    public function obtenerDetalle(int $id_compra): array
    {
        $stmt = $this->db->prepare("
            SELECT
                dc.id_detalle,
                p.nombre AS producto,
                u.nombre AS unidad_compra,
                dc.cantidad,
                dc.precio_compra AS precio_unitario,
                dc.cantidad_por_unidad,
                uc.nombre AS unidad_contenido,
                dc.subtotal
            FROM detalle_compra dc
            INNER JOIN producto p ON p.id_producto = dc.id_producto
            LEFT JOIN unidades_medida u ON u.id_unidad = dc.id_unidad
            LEFT JOIN unidades_medida uc ON uc.id_unidad = dc.id_unidad_contenido
            WHERE dc.id_compra = :id_compra
            ORDER BY dc.id_detalle ASC
        ");
        $stmt->execute([':id_compra' => $id_compra]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ============================================================
    // OBTENER TODAS LAS COMPRAS (para la tabla principal)
    // ============================================================
    public function obtenerTodas(): array
    {
        $stmt = $this->db->query("
            SELECT
                c.id_compra,
                c.fecha,
                c.total,
                pe.nombre AS proveedor,
                peu.nombre AS comprador
            FROM compra c
            INNER JOIN proveedor pr ON pr.id_proveedor = c.id_proveedor
            INNER JOIN persona pe ON pe.id_persona = pr.id_persona
            LEFT JOIN usuario u ON u.id_usuario = c.id_usuario
            LEFT JOIN persona peu ON peu.id_persona = u.id_persona
            ORDER BY c.fecha DESC, c.id_compra DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ============================================================
    // RESUMEN (KPIs del panel)
    // ============================================================
    public function obtenerResumen(): array
    {
        $stmt = $this->db->query("
            SELECT
                COUNT(*) AS total_compras,
                COALESCE(SUM(total), 0) AS gasto_total,
                SUM(CASE WHEN DATE(fecha) = CURDATE() THEN 1 ELSE 0 END) AS compras_hoy,
                COALESCE(SUM(CASE WHEN DATE(fecha) = CURDATE() THEN total ELSE 0 END), 0) AS gasto_hoy
            FROM compra
        ");
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ?: [
            'total_compras' => 0,
            'gasto_total' => 0,
            'compras_hoy' => 0,
            'gasto_hoy' => 0,
        ];
    }

    // ============================================================
    // PROVEEDORES ACTIVOS (para el select del formulario)
    // ============================================================
    public function obtenerProveedores(): array
    {
        $stmt = $this->db->query("
            SELECT
                pr.id_proveedor,
                pe.nombre
            FROM proveedor pr
            INNER JOIN persona pe ON pe.id_persona = pr.id_persona
            WHERE pe.estado = 1
            ORDER BY pe.nombre ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ============================================================
    // UNIDADES DE COMPRA (catálogo, para el select del formulario)
    //
    // Se reutiliza para AMBOS selects (unidad de compra y unidad
    // de contenido) porque ambas FK apuntan a la misma tabla.
    // ============================================================
    public function obtenerUnidades(): array
    {
        $stmt = $this->db->query("
            SELECT id_unidad, nombre
            FROM unidades_medida
            ORDER BY nombre ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ============================================================
    // PRODUCTOS ACTIVOS DEL CATÁLOGO
    //
    // El proveedor se registra aparte, el producto se registra
    // aparte -- es la COMPRA la que los relaciona, no al revés.
    // Por eso aquí se listan TODOS los productos activos.
    //
    // El módulo de Compras solo usa `compra`, `detalle_compra` y
    // `unidades_medida` -- no consulta precio de venta ni ninguna
    // tabla de precios. Ese dato es responsabilidad del módulo de
    // Ventas.
    // ============================================================
    public function obtenerProductos(): array
    {
        $stmt = $this->db->query("
            SELECT
                p.id_producto,
                p.nombre AS producto,
                cat.tipo AS categoria
            FROM producto p
            LEFT JOIN categoria cat ON cat.id_categoria = p.id_categoria
            WHERE p.estado = 1
            ORDER BY p.nombre ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}