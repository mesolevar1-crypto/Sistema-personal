<?php
/**
 * Modelo Producto
 * BD real: producto (id_producto, nombre, descripcion, id_categoria, imagen, estado)
 *          SIN columnas precio ni stock — esas están en productos_precio e inventario
 * categoria: id_categoria, tipo, estado
 */
class Producto {

    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Lista todos los productos con categoría, precio de venta (primer precio activo)
     * y stock actual (inventario).
     */
    public function obtenerTodos() {
        $sql = "SELECT
                    p.id_producto,
                    p.nombre,
                    p.descripcion,
                    p.imagen,
                    p.estado,
                    p.id_categoria,
                    c.tipo                              AS categoria,
                    COALESCE(i.stock_actual, 0)         AS stock_actual,
                    COALESCE(i.stock_minimo, 0)         AS stock_minimo,
                    -- Primer precio de venta activo
                    (SELECT pp.precio_venta
                     FROM productos_precio pp
                     WHERE pp.id_producto = p.id_producto AND pp.estado = 'activo'
                     LIMIT 1)                           AS precio_venta,
                    -- Primer precio de compra activo
                    (SELECT pp.precio_compra
                     FROM productos_precio pp
                     WHERE pp.id_producto = p.id_producto AND pp.estado = 'activo'
                     LIMIT 1)                           AS precio_compra
                FROM producto p
                LEFT JOIN categoria  c ON p.id_categoria = c.id_categoria
                LEFT JOIN inventario i ON p.id_producto  = i.id_producto
                ORDER BY p.nombre ASC";
        $stmt = $this->conn->prepare($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Obtener producto por ID ─────────────────────────────
    public function obtenerPorId($id_producto) {
        $sql = "SELECT p.*, c.tipo AS categoria
                FROM producto p
                LEFT JOIN categoria c ON p.id_categoria = c.id_categoria
                WHERE p.id_producto = :id LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id_producto);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ── Precios de un producto (productos_precio) ───────────
    public function obtenerPrecios($id_producto) {
        $sql = "SELECT
                    pp.*,
                    uc.nombre AS unidad_compra_nombre,
                    uv.nombre AS unidad_venta_nombre,
                    pe.nombre AS proveedor_nombre
                FROM productos_precio pp
                INNER JOIN unidades_medida uc ON pp.id_unidad_compra = uc.id_unidad
                INNER JOIN unidades_medida uv ON pp.id_unidad_venta  = uv.id_unidad
                INNER JOIN proveedor       pr ON pp.id_proveedor     = pr.id_proveedor
                INNER JOIN persona         pe ON pr.id_persona       = pe.id_persona
                WHERE pp.id_producto = :id
                ORDER BY pp.id_precio ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id_producto);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Categorías para el select ───────────────────────────
    public function obtenerCategorias() {
        $stmt = $this->conn->prepare(
            "SELECT id_categoria, tipo FROM categoria WHERE estado = 'activo' ORDER BY tipo ASC"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Registrar producto ──────────────────────────────────
    public function registrar($datos) {
        try {
            $sql = "INSERT INTO producto (nombre, descripcion, id_categoria, imagen, estado)
                    VALUES (:nombre, :descripcion, :id_categoria, :imagen, 'activo')";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':nombre',       $datos['nombre']);
            $stmt->bindParam(':descripcion',  $datos['descripcion']);
            $stmt->bindParam(':id_categoria', $datos['id_categoria']);
            $stmt->bindParam(':imagen',       $datos['imagen']);
            $stmt->execute();
            return $this->conn->lastInsertId();
        } catch (Exception $e) {
            return "Error al registrar: " . $e->getMessage();
        }
    }

    // ── Editar producto ─────────────────────────────────────
    public function editar($id_producto, $datos) {
        try {
            if (!empty($datos['imagen'])) {
                $sql = "UPDATE producto
                        SET nombre = :nombre, descripcion = :descripcion,
                            id_categoria = :id_categoria, imagen = :imagen
                        WHERE id_producto = :id";
            } else {
                $sql = "UPDATE producto
                        SET nombre = :nombre, descripcion = :descripcion,
                            id_categoria = :id_categoria
                        WHERE id_producto = :id";
            }
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':nombre',       $datos['nombre']);
            $stmt->bindParam(':descripcion',  $datos['descripcion']);
            $stmt->bindParam(':id_categoria', $datos['id_categoria']);
            $stmt->bindParam(':id',           $id_producto);
            if (!empty($datos['imagen'])) $stmt->bindParam(':imagen', $datos['imagen']);
            $stmt->execute();
            return true;
        } catch (Exception $e) {
            return "Error al editar: " . $e->getMessage();
        }
    }

    // ── Cambiar estado del producto ─────────────────────────
    public function cambiarEstado($id_producto, $estado) {
        try {
            $this->conn->prepare(
                "UPDATE producto SET estado = ? WHERE id_producto = ?"
            )->execute([$estado, $id_producto]);
            return true;
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    // ── Verificar nombre duplicado ──────────────────────────
    public function existeNombre($nombre, $excluir_id = null) {
        if ($excluir_id) {
            $sql  = "SELECT id_producto FROM producto WHERE nombre = :nombre AND id_producto != :id LIMIT 1";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':nombre', $nombre);
            $stmt->bindParam(':id',     $excluir_id);
        } else {
            $sql  = "SELECT id_producto FROM producto WHERE nombre = :nombre LIMIT 1";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':nombre', $nombre);
        }
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    // ── Registrar precio en productos_precio ─────────────────
    public function registrarPrecio($datos) {
        try {
            $sql = "INSERT INTO productos_precio
                        (id_producto, id_proveedor, id_unidad_compra, precio_compra,
                         unidades_por_presentacion, cantidad_venta, id_unidad_venta, precio_venta, estado)
                    VALUES
                        (:id_producto, :id_proveedor, :id_unidad_compra, :precio_compra,
                         :unidades_por_presentacion, :cantidad_venta, :id_unidad_venta, :precio_venta, 'activo')";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':id_producto'              => $datos['id_producto'],
                ':id_proveedor'             => $datos['id_proveedor'],
                ':id_unidad_compra'         => $datos['id_unidad_compra'],
                ':precio_compra'            => $datos['precio_compra'],
                ':unidades_por_presentacion'=> $datos['unidades_por_presentacion'],
                ':cantidad_venta'           => $datos['cantidad_venta'],
                ':id_unidad_venta'          => $datos['id_unidad_venta'],
                ':precio_venta'             => $datos['precio_venta'],
            ]);
            return true;
        } catch (Exception $e) {
            return "Error al registrar precio: " . $e->getMessage();
        }
    }
}
?>
