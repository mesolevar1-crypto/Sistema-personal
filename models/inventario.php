<?php
/**
 * Modelo Inventario
 * Fuente principal de stock: inventario.stock_actual
 * Tablas: inventario, producto, categoria
 *
 * NOTA IMPORTANTE SOBRE NOMBRES DE COLUMNA:
 * La tabla real `inventario` tiene la columna `stcok_minimo` (así,
 * con el typo -- no es un error de este archivo, es el nombre real
 * en la base de datos). Todo el SQL de este modelo usa `stcok_minimo`
 * tal cual. Hacia afuera (arrays devueltos a la vista/controlador)
 * se sigue usando la clave `stock_minimo` (bien escrita) mediante
 * alias `AS`, para no tener que tocar la vista ni el controlador
 * que ya funcionan con ese nombre de clave.
 */
class Inventario {

    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Lista todos los productos con su inventario (LEFT JOIN para incluir
     * productos que aún no tienen registro en inventario).
     * Acepta búsqueda por nombre y filtro por estado.
     *
     * @param string $buscar  Texto a buscar en nombre del producto
     * @param string $estado  'disponible' | 'bajo' | 'agotado' | '' (todos)
     */
    public function obtenerTodos($buscar = '', $estado = '') {
        $sql = "SELECT
                    p.id_producto,
                    p.nombre                                 AS producto,
                    c.tipo                                   AS categoria,
                    COALESCE(i.stock_actual, 0)              AS stock_actual,
                    COALESCE(i.stcok_minimo, 0)              AS stock_minimo,
                    i.fecha_actualizacion,
                    i.id_inventario
                FROM producto p
                LEFT JOIN categoria  c ON p.id_categoria  = c.id_categoria
                LEFT JOIN inventario i ON p.id_producto   = i.id_producto
                WHERE 1=1";

        $params = [];

        // Filtro de búsqueda por nombre
        if ($buscar !== '') {
            $sql .= " AND p.nombre LIKE :buscar";
            $params[':buscar'] = '%' . $buscar . '%';
        }

        // Filtro por estado calculado
        if ($estado === 'agotado') {
            $sql .= " AND COALESCE(i.stock_actual, 0) = 0";
        } elseif ($estado === 'bajo') {
            $sql .= " AND COALESCE(i.stock_actual, 0) > 0
                      AND COALESCE(i.stock_actual, 0) <= COALESCE(i.stcok_minimo, 0)";
        } elseif ($estado === 'disponible') {
            $sql .= " AND COALESCE(i.stock_actual, 0) > COALESCE(i.stcok_minimo, 0)";
        }

        // Del más reciente al más antiguo según fecha_actualizacion.
        // Los productos que aún no tienen registro en inventario
        // (fecha_actualizacion NULL) quedan al final -- MySQL ordena
        // los NULL al final en DESC de forma natural.
        $sql .= " ORDER BY i.fecha_actualizacion DESC, p.nombre ASC";

        $stmt = $this->conn->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Estadísticas reales para las tarjetas superiores.
     * Usa inventario.stock_actual como fuente de verdad.
     */
    public function obtenerResumen() {
        $sql = "SELECT
                    COUNT(p.id_producto)                                                   AS total_productos,
                    COALESCE(SUM(COALESCE(i.stock_actual, 0)), 0)                          AS total_unidades,
                    SUM(CASE
                        WHEN COALESCE(i.stock_actual,0) > 0
                         AND COALESCE(i.stock_actual,0) <= COALESCE(i.stcok_minimo,0)
                        THEN 1 ELSE 0 END)                                                 AS stock_bajo,
                    SUM(CASE
                        WHEN COALESCE(i.stock_actual,0) = 0
                        THEN 1 ELSE 0 END)                                                 AS agotados
                FROM producto p
                LEFT JOIN inventario i ON p.id_producto = i.id_producto";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Actualiza stock_actual y stock_minimo (columna real: stcok_minimo)
     * en la tabla inventario. Si el producto no tiene registro en
     * inventario lo crea.
     *
     * @param int $id_producto
     * @param int $stock_actual  Nuevo valor de stock
     * @param int $stock_minimo  Nuevo valor de stock mínimo
     */
    public function actualizarStock($id_producto, $stock_actual, $stock_minimo) {
        try {
            // ¿Existe ya registro de inventario para este producto?
            $stmtBuscar = $this->conn->prepare(
                "SELECT id_inventario FROM inventario WHERE id_producto = :id LIMIT 1"
            );
            $stmtBuscar->bindParam(':id', $id_producto);
            $stmtBuscar->execute();
            $existe = $stmtBuscar->fetch(PDO::FETCH_ASSOC);

            if ($existe) {
                // Actualizar registro existente
                $stmt = $this->conn->prepare(
                    "UPDATE inventario
                     SET stock_actual        = :stock_actual,
                         stcok_minimo        = :stock_minimo,
                         fecha_actualizacion = CURDATE()
                     WHERE id_producto = :id_producto"
                );
            } else {
                // Crear nuevo registro
                $stmt = $this->conn->prepare(
                    "INSERT INTO inventario (stock_actual, stcok_minimo, fecha_actualizacion, id_producto)
                     VALUES (:stock_actual, :stock_minimo, CURDATE(), :id_producto)"
                );
            }

            $stmt->bindParam(':stock_actual', $stock_actual);
            $stmt->bindParam(':stock_minimo', $stock_minimo);
            $stmt->bindParam(':id_producto',  $id_producto);
            $stmt->execute();
            return true;

        } catch (Exception $e) {
            return "Error: " . $e->getMessage();
        }
    }
}
?>