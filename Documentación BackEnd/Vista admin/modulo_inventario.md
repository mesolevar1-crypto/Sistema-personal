# Módulo de Inventario — Vista del Administrador

**Archivos involucrados:**
- Vista: `views/inventario/index.php`
- Controlador: `controllers/InventarioController.php`
- Modelo: `models/inventario.php`

---

## ¿Qué es este módulo?

Muestra el estado actual del stock de todos los productos. El administrador puede ver qué productos tienen stock normal, cuáles están bajos y cuáles están agotados. También puede actualizar el stock manualmente. El stock se actualiza automáticamente cuando se registran ventas (baja) o compras (sube).

---

## ¿Qué muestra la pantalla?

**4 tarjetas KPI:**

| Tarjeta | Qué muestra |
|---|---|
| Productos | Total de productos en el catálogo |
| Unidades | Suma de todos los stocks |
| Stock Bajo | Productos con 1 a 5 unidades |
| Agotados | Productos con 0 unidades |

**Filtros de estado:**
- **Normal** (activo por defecto al cargar) — stock > 5
- **Stock Bajo** — stock entre 1 y 5
- **Agotado** — stock = 0

**Buscador** — filtra por nombre o categoría en tiempo real.

**Tabla de inventario** con columnas: Producto, Categoría, Precio, Stock Actual, Estado, Acciones.

---

## ¿Cómo se determina el estado de cada producto?

```php
if ($stock == 0)           { $estado = 'agotado'; }  // Rojo
elseif ($stock <= 5)       { $estado = 'bajo'; }     // Amarillo
else                       { $estado = 'normal'; }   // Verde
```

El umbral de "stock bajo" es **5 unidades** (`$STOCK_MINIMO = 5`).

---

## ¿Cómo se cargan los datos?

```sql
SELECT p.id_producto, p.nombre AS producto, p.precio, p.stock, c.tipo AS categoria
FROM producto p
LEFT JOIN categoria c ON p.id_categoria = c.id_categoria
ORDER BY p.stock ASC, p.nombre ASC
```
Se ordena por stock ascendente para que los productos más críticos aparezcan primero.

---

## ¿Cómo funciona el filtro por defecto "Normal"?

Al cargar la página, los productos que NO son normales se renderizan con `style="display:none"` directamente desde PHP:

```php
<tr ... <?= $estado !== 'normal' ? 'style="display:none"' : '' ?>>
```

Esto garantiza que al cargar solo se vean los productos normales sin depender de JavaScript. Cuando el usuario hace clic en otro filtro, el JavaScript cambia el `style.display` de cada fila.

---

## ¿Cómo funciona el filtro por botones?

```javascript
var filtroActivo = 'normal';  // Filtro inicial

function filtrar(estado) {
    filtroActivo = estado;
    // Actualiza estilos de los botones
    // Llama a aplicarFiltros()
}

function aplicarFiltros() {
    filas.forEach(function(fila) {
        var ok = nombre.includes(busqueda) && (filtroActivo === 'todos' || estado === filtroActivo);
        fila.style.display = ok ? '' : 'none';  // Muestra u oculta la fila
    });
}
```

---

## Acción: Actualizar stock manualmente

### ¿Cómo se activa?
Clic en el **lápiz** de cualquier producto → abre el modal con el nombre del producto y su stock actual.

### ¿Qué hace el usuario?
Escribe la nueva cantidad de stock y hace clic en **"Guardar"**.

### ¿A dónde van los datos?
POST a `controllers/InventarioController.php?accion=actualizar`

### ¿Qué hace el controlador?
1. Valida que el `id_producto` sea mayor que 0.
2. Llama a `actualizarStock($id_producto, $stock)`.

### ¿Qué hace el modelo?
```sql
UPDATE producto SET stock = :stock WHERE id_producto = :id_producto
```
Actualiza directamente el campo `stock` de la tabla `producto`.

---

## Métodos del Modelo (`models/inventario.php`)

| Método | SQL que ejecuta | Qué retorna |
|---|---|---|
| `obtenerTodos()` | `SELECT ... FROM producto LEFT JOIN categoria ORDER BY stock ASC` | Array de productos con stock |
| `obtenerResumen()` | `SELECT COUNT(*), SUM(stock), SUM(CASE WHEN stock=0...)` | Objeto con estadísticas del inventario |
| `actualizarStock($id, $stock)` | `UPDATE producto SET stock = ? WHERE id_producto = ?` | true o mensaje de error |

### Detalle de `obtenerResumen()`
```sql
SELECT
    COUNT(*)                                                    AS total_productos,
    COALESCE(SUM(stock), 0)                                     AS total_unidades,
    SUM(CASE WHEN stock > 0 AND stock <= 5 THEN 1 ELSE 0 END)  AS stock_bajo,
    SUM(CASE WHEN stock = 0               THEN 1 ELSE 0 END)   AS agotados,
    SUM(CASE WHEN stock > 5               THEN 1 ELSE 0 END)   AS stock_normal
FROM producto
```
Usa `CASE WHEN` para contar cuántos productos caen en cada categoría de stock.

---

## Flujo completo

```
views/inventario/index.php
    ├── Carga inventario con obtenerTodos() (ordenado por stock ASC)
    ├── Carga resumen con obtenerResumen() (para las 4 tarjetas KPI)
    ├── PHP renderiza filas con display:none para los no-normales
    └── Muestra tabla con filtros y buscador

ACTUALIZAR STOCK: lápiz → modal → POST → InventarioController (actualizar)
    → actualizarStock() → UPDATE producto SET stock = nuevo_valor

AUTOMÁTICO (ventas): VentaController → UPDATE producto SET stock = stock - cantidad
AUTOMÁTICO (compras): CompraController → UPDATE producto SET stock = stock + cantidad
```
