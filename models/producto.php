<?php


class Producto
{
    private $conn;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    // =========================================================
    // OBTENER TODOS LOS PRODUCTOS
    // =========================================================

    public function obtenerTodos()
{
    try {

        $sql = "
            SELECT
                p.id_producto,
                p.nombre,
                p.descripcion,
                p.id_categoria,
                p.imagen,
                p.estado,

                c.tipo AS categoria,

                COALESCE(i.stock_actual, 0) AS stock_actual,
                COALESCE(i.stock_minimo, 0) AS stock_minimo

            FROM producto p

            LEFT JOIN categoria c
                ON p.id_categoria = c.id_categoria

            LEFT JOIN inventario i
                ON p.id_producto = i.id_producto

            ORDER BY p.id_producto DESC
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {

        error_log(
            "Error obtenerTodos productos: " .
            $e->getMessage()
        );

        return [];
    }
}

    // =========================================================
    // OBTENER PRODUCTO POR ID
    // =========================================================

    public function obtenerPorId($id_producto)
    {
        try {

            $sql = "
                SELECT
                    p.*,
                    c.tipo AS categoria

                FROM producto p

                LEFT JOIN categoria c
                    ON p.id_categoria = c.id_categoria

                WHERE p.id_producto = :id

                LIMIT 1
            ";

            $stmt = $this->conn->prepare($sql);

            $stmt->bindValue(
                ':id',
                (int)$id_producto,
                PDO::PARAM_INT
            );

            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {

            error_log(
                "Error obtenerPorId producto: " .
                $e->getMessage()
            );

            return false;
        }
    }

    // =========================================================
    // OBTENER CATEGORÍAS
    // =========================================================

    public function obtenerCategorias()
    {
        try {

            $sql = "
                SELECT
                    id_categoria,
                    tipo
                FROM categoria
                ORDER BY tipo ASC
            ";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {

            error_log(
                "Error obtenerCategorias: " .
                $e->getMessage()
            );

            return [];
        }
    }

    // =========================================================
    // REGISTRAR CATEGORÍA
    // =========================================================

    public function registrarCategoria($datos)
    {
        try {

            $tipo = trim($datos['tipo'] ?? '');

            $sql = "
                INSERT INTO categoria
                (
                    tipo
                )
                VALUES
                (
                    :tipo
                )
            ";

            $stmt = $this->conn->prepare($sql);

            $stmt->bindValue(
                ':tipo',
                $tipo,
                PDO::PARAM_STR
            );

            $stmt->execute();

            return true;

        } catch (PDOException $e) {

            error_log(
                "Error registrarCategoria: " .
                $e->getMessage()
            );

            return "Error al registrar categoría: " .
                $e->getMessage();
        }
    }

    // =========================================================
    // EDITAR CATEGORÍA
    // =========================================================

    public function editarCategoria($id_categoria, $datos)
    {
        try {

            $tipo = trim($datos['tipo'] ?? '');

            $sql = "
                UPDATE categoria
                SET
                    tipo = :tipo
                WHERE id_categoria = :id
            ";

            $stmt = $this->conn->prepare($sql);

            $stmt->bindValue(
                ':tipo',
                $tipo,
                PDO::PARAM_STR
            );

            $stmt->bindValue(
                ':id',
                (int)$id_categoria,
                PDO::PARAM_INT
            );

            $stmt->execute();

            return true;

        } catch (PDOException $e) {

            error_log(
                "Error editarCategoria: " .
                $e->getMessage()
            );

            return "Error al editar categoría: " .
                $e->getMessage();
        }
    }

    // =========================================================
    // ELIMINAR CATEGORÍA
    // =========================================================

    public function eliminarCategoria($id_categoria)
    {
        try {

            $sql = "
                DELETE FROM categoria
                WHERE id_categoria = :id
            ";

            $stmt = $this->conn->prepare($sql);

            $stmt->bindValue(
                ':id',
                (int)$id_categoria,
                PDO::PARAM_INT
            );

            $stmt->execute();

            return true;

        } catch (PDOException $e) {

            error_log(
                "Error eliminarCategoria: " .
                $e->getMessage()
            );

            return "No se puede eliminar la categoría porque tiene productos asociados.";
        }
    }

    // =========================================================
    // VERIFICAR SI YA EXISTE UNA CATEGORÍA CON ESE NOMBRE
    // =========================================================

    public function existeCategoriaTipo($tipo, $excluir_id = null)
    {
        try {

            if ($excluir_id !== null) {

                $sql = "
                    SELECT id_categoria
                    FROM categoria
                    WHERE tipo = :tipo
                      AND id_categoria != :id
                    LIMIT 1
                ";

                $stmt = $this->conn->prepare($sql);

                $stmt->bindValue(
                    ':tipo',
                    trim($tipo),
                    PDO::PARAM_STR
                );

                $stmt->bindValue(
                    ':id',
                    (int)$excluir_id,
                    PDO::PARAM_INT
                );

            } else {

                $sql = "
                    SELECT id_categoria
                    FROM categoria
                    WHERE tipo = :tipo
                    LIMIT 1
                ";

                $stmt = $this->conn->prepare($sql);

                $stmt->bindValue(
                    ':tipo',
                    trim($tipo),
                    PDO::PARAM_STR
                );
            }

            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC) !== false;

        } catch (PDOException $e) {

            error_log(
                "Error existeCategoriaTipo: " .
                $e->getMessage()
            );

            return false;
        }
    }

    // =========================================================
    // REGISTRAR PRODUCTO
    // =========================================================

    public function registrar($datos)
    {
        try {

            $sql = "
                INSERT INTO producto
                (
                    nombre,
                    descripcion,
                    id_categoria,
                    imagen,
                    estado
                )
                VALUES
                (
                    :nombre,
                    :descripcion,
                    :id_categoria,
                    :imagen,
                    1
                )
            ";

            $stmt = $this->conn->prepare($sql);

            $nombre = trim($datos['nombre'] ?? '');

            $descripcion = !empty($datos['descripcion'])
                ? trim($datos['descripcion'])
                : null;

            $id_categoria = !empty($datos['id_categoria'])
                ? (int)$datos['id_categoria']
                : null;

            $imagen = !empty($datos['imagen'])
                ? trim($datos['imagen'])
                : null;

            $stmt->bindValue(
                ':nombre',
                $nombre,
                PDO::PARAM_STR
            );

            $stmt->bindValue(
                ':descripcion',
                $descripcion,
                $descripcion === null
                    ? PDO::PARAM_NULL
                    : PDO::PARAM_STR
            );

            $stmt->bindValue(
                ':id_categoria',
                $id_categoria,
                $id_categoria === null
                    ? PDO::PARAM_NULL
                    : PDO::PARAM_INT
            );

            $stmt->bindValue(
                ':imagen',
                $imagen,
                $imagen === null
                    ? PDO::PARAM_NULL
                    : PDO::PARAM_STR
            );

            $stmt->execute();

            return (int)$this->conn->lastInsertId();

        } catch (PDOException $e) {

            error_log(
                "Error registrar producto: " .
                $e->getMessage()
            );

            return "Error al registrar producto: " .
                $e->getMessage();
        }
    }

    // =========================================================
    // EDITAR PRODUCTO
    // =========================================================

    public function editar($id_producto, $datos)
    {
        try {

            $imagen = !empty($datos['imagen'])
                ? trim($datos['imagen'])
                : null;

            if ($imagen !== null) {

                $sql = "
                    UPDATE producto
                    SET
                        nombre = :nombre,
                        descripcion = :descripcion,
                        id_categoria = :id_categoria,
                        imagen = :imagen
                    WHERE id_producto = :id
                ";

            } else {

                $sql = "
                    UPDATE producto
                    SET
                        nombre = :nombre,
                        descripcion = :descripcion,
                        id_categoria = :id_categoria
                    WHERE id_producto = :id
                ";
            }

            $stmt = $this->conn->prepare($sql);

            $nombre = trim($datos['nombre'] ?? '');

            $descripcion = !empty($datos['descripcion'])
                ? trim($datos['descripcion'])
                : null;

            $id_categoria = !empty($datos['id_categoria'])
                ? (int)$datos['id_categoria']
                : null;

            $stmt->bindValue(
                ':nombre',
                $nombre,
                PDO::PARAM_STR
            );

            $stmt->bindValue(
                ':descripcion',
                $descripcion,
                $descripcion === null
                    ? PDO::PARAM_NULL
                    : PDO::PARAM_STR
            );

            $stmt->bindValue(
                ':id_categoria',
                $id_categoria,
                $id_categoria === null
                    ? PDO::PARAM_NULL
                    : PDO::PARAM_INT
            );

            $stmt->bindValue(
                ':id',
                (int)$id_producto,
                PDO::PARAM_INT
            );

            if ($imagen !== null) {

                $stmt->bindValue(
                    ':imagen',
                    $imagen,
                    PDO::PARAM_STR
                );
            }

            $stmt->execute();

            return true;

        } catch (PDOException $e) {

            error_log(
                "Error editar producto: " .
                $e->getMessage()
            );

            return "Error al editar producto: " .
                $e->getMessage();
        }
    }

    // =========================================================
    // CAMBIAR ESTADO DEL PRODUCTO
    // =========================================================

    public function cambiarEstado($id_producto, $estado)
    {
        try {

            $estado = ((int)$estado === 1) ? 1 : 0;

            $sql = "
                UPDATE producto
                SET estado = :estado
                WHERE id_producto = :id
            ";

            $stmt = $this->conn->prepare($sql);

            $stmt->bindValue(
                ':estado',
                $estado,
                PDO::PARAM_INT
            );

            $stmt->bindValue(
                ':id',
                (int)$id_producto,
                PDO::PARAM_INT
            );

            $stmt->execute();

            return true;

        } catch (PDOException $e) {

            error_log(
                "Error cambiarEstado producto: " .
                $e->getMessage()
            );

            return "Error al cambiar estado: " .
                $e->getMessage();
        }
    }

    // =========================================================
    // VERIFICAR NOMBRE
    // =========================================================

    public function existeNombre($nombre, $excluir_id = null)
    {
        try {

            if ($excluir_id !== null) {

                $sql = "
                    SELECT id_producto
                    FROM producto
                    WHERE nombre = :nombre
                      AND id_producto != :id
                    LIMIT 1
                ";

                $stmt = $this->conn->prepare($sql);

                $stmt->bindValue(
                    ':nombre',
                    trim($nombre),
                    PDO::PARAM_STR
                );

                $stmt->bindValue(
                    ':id',
                    (int)$excluir_id,
                    PDO::PARAM_INT
                );

            } else {

                $sql = "
                    SELECT id_producto
                    FROM producto
                    WHERE nombre = :nombre
                    LIMIT 1
                ";

                $stmt = $this->conn->prepare($sql);

                $stmt->bindValue(
                    ':nombre',
                    trim($nombre),
                    PDO::PARAM_STR
                );
            }

            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC) !== false;

        } catch (PDOException $e) {

            error_log(
                "Error existeNombre producto: " .
                $e->getMessage()
            );

            return false;
        }
    }


    // =========================================================
    // ELIMINAR PRODUCTO
    // =========================================================

    public function eliminar($id_producto)
    {
        try {

            $producto = $this->obtenerPorId($id_producto);

            if (!$producto) {
                return "El producto no existe.";
            }

            $sql = "
                DELETE FROM producto
                WHERE id_producto = :id
            ";

            $stmt = $this->conn->prepare($sql);

            $stmt->bindValue(
                ':id',
                (int)$id_producto,
                PDO::PARAM_INT
            );

            $stmt->execute();

            return true;

        } catch (PDOException $e) {

            error_log(
                "Error eliminar producto: " .
                $e->getMessage()
            );

            return "No se puede eliminar el producto porque tiene información relacionada.";
        }
    }
}

?>