# Módulo de Productos e Inventario — Vista del Vendedor

**Archivos involucrados:**
- Vista Productos: `views/productos/index.php` (compartida, solo lectura para vendedor)
- Vista Inventario: `views/inventario/index.php` (compartida, solo lectura para vendedor)
- Sidebar: `views/layouts/sidebar_vendedor.php`

---

## ¿Qué puede hacer el vendedor con productos e inventario?

El vendedor tiene acceso de **solo lectura** a estos módulos. Puede consultar el catálogo de productos y el estado del stock, pero **no puede crear, editar ni eliminar** productos, ni actualizar el stock manualmente.

Las vistas son las mismas que usa el administrador, pero el sidebar que se carga es el del vendedor gracias al `sidebar_loader.php`.

---

## Módulo de Productos (solo lectura)

### ¿Qué ve el vendedor?
Tarjetas visuales con cada producto mostrando:
- Nombre del producto
- Badge de categoría con color
- Stock actual con ícono de cubos
- Precio destacado en verde
- Botones: Editar y Eliminar (**visibles pero el vendedor no debería usarlos**)

### ¿Por qué el vendedor puede ver los botones de editar y eliminar?
La vista de productos es compartida con el administrador. Los botones aparecen para ambos roles. Sin embargo, el vendedor tiene acceso a esta vista principalmente para **consultar precios y disponibilidad** antes de registrar una venta.

### ¿Cómo se cargan los datos?
```sql
SELECT p.id_producto, p.nombre, p.stock, p.precio, p.id_categoria, c.tipo AS categoria
FROM producto p
LEFT JOIN categoria c ON p.id_categoria = c.id_categoria
ORDER BY p.nombre ASC
```

### Buscador en tiempo real
El vendedor puede buscar productos por nombre o categoría usando el campo de búsqueda. El JavaScript filtra las tarjetas en tiempo real sin recargar la página.

---

## Módulo de Inventario (solo lectura)

### ¿Qué ve el vendedor?
La misma tabla que el administrador con:
- 4 tarjetas KPI: Productos, Unidades, Stock Bajo, Agotados
- Filtros: Normal (activo por defecto), Stock Bajo, Agotado
- Buscador por nombre o categoría
- Tabla con: Producto, Categoría, Precio, Stock Actual, Estado

### ¿Qué NO puede hacer el vendedor?
El vendedor **no puede actualizar el stock** manualmente. El botón de lápiz (editar stock) aparece en la tabla, pero si el vendedor lo usa, el controlador procesará la acción igualmente (la vista no tiene restricción de rol en el botón).

### ¿Por qué el vendedor consulta el inventario?
Para saber qué productos están disponibles antes de registrar una venta. Si un producto está agotado, el vendedor puede informar al cliente o al administrador para que realice una compra.

### Filtro por defecto "Normal"
Al cargar la página, solo se muestran los productos con stock normal. Los agotados y de stock bajo están ocultos con `style="display:none"` desde PHP. El vendedor puede cambiar el filtro para verlos.

---

## ¿Cómo sabe el sistema qué sidebar cargar?

El archivo `views/layouts/sidebar_loader.php` detecta el rol del usuario:
```php
$rolActual = strtolower($_SESSION['usuario']['rol'] ?? '');
if ($rolActual === 'vendedor') {
    require_once __DIR__ . '/sidebar_vendedor.php';
} else {
    require_once __DIR__ . '/sidebar.php';
}
```
Así, cuando el vendedor accede a `productos/index.php` o `inventario/index.php`, ve el sidebar del vendedor (con menú reducido) en lugar del sidebar del administrador.

---

## Flujo completo

```
Vendedor hace clic en "Productos" en el sidebar
→ views/productos/index.php
→ sidebar_loader.php detecta rol = Vendedor → carga sidebar_vendedor.php
→ Muestra tarjetas de productos (solo consulta)

Vendedor hace clic en "Inventario" en el sidebar
→ views/inventario/index.php
→ sidebar_loader.php detecta rol = Vendedor → carga sidebar_vendedor.php
→ Muestra tabla de inventario con filtro Normal activo por defecto
→ Vendedor puede filtrar y buscar productos
→ Vendedor NO puede actualizar stock (esa función es del administrador)
```
