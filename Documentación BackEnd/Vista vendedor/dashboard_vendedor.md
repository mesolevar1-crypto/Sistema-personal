# Dashboard del Vendedor

**Archivos involucrados:**
- Vista principal: `views/dashboard/vendedor.php`
- Sidebar: `views/layouts/sidebar_vendedor.php`
- Modelos usados: `models/venta.php`, `models/inventario.php`, `models/cliente.php`
- Controlador de ventas: `controllers/VentaController.php`

---

## ¿Qué es esta vista?

Es la **pantalla principal del vendedor** al iniciar sesión. Desde aquí puede ver sus propios indicadores de rendimiento, consultar su historial de ventas, registrar nuevas ventas y revisar el inventario de productos disponibles.

**Importante:** Si un administrador intenta acceder a esta URL, es redirigido automáticamente a `admin.php`.

---

## ¿Cómo se protege el acceso?

```php
if (strtolower($_SESSION['usuario']['rol']) === 'administrador') {
    header("Location: ../dashboard/admin.php");
    exit;
}
```

---

## ¿Qué datos carga al abrir la página?

El PHP ejecuta estas consultas al cargar:

### 1. KPIs del vendedor (solo sus ventas)
```sql
SELECT COUNT(*) AS mis_ventas,
       COALESCE(SUM(total),0) AS mis_ingresos,
       COALESCE(SUM(CASE WHEN fecha=CURDATE() THEN total ELSE 0 END),0) AS ingresos_hoy,
       COALESCE(SUM(CASE WHEN fecha=CURDATE() THEN 1 ELSE 0 END),0) AS ventas_hoy
FROM venta WHERE id_usuario = :id
```
El `:id` es el `id_usuario` de la sesión — así cada vendedor solo ve sus propios números.

### 2. Historial de ventas (últimas 10 por defecto)
```sql
SELECT v.id_venta, v.fecha, v.total, pc.nombre AS cliente
FROM venta v
LEFT JOIN cliente c  ON v.id_cliente = c.id_cliente
LEFT JOIN persona pc ON c.id_persona = pc.id_persona
WHERE v.id_usuario = :id
ORDER BY v.fecha DESC LIMIT 10
```

### 3. Inventario completo (para la sección de productos)
```sql
SELECT p.id_producto, p.nombre AS producto, p.precio, p.stock, c.tipo AS categoria
FROM producto p
LEFT JOIN categoria c ON p.id_categoria = c.id_categoria
ORDER BY p.stock ASC
```

### 4. Clientes activos y productos (para el modal de nueva venta)
- `obtenerClientes()` → solo clientes con `estado = 1`
- `obtenerProductos()` → todos los productos con su stock

---

## ¿Qué muestra la pantalla?

### Tarjetas KPI (4 tarjetas)

| Tarjeta | Qué muestra |
|---|---|
| Mis Ventas | Total de ventas que ha registrado el vendedor |
| Mis Ingresos | Suma de dinero de todas sus ventas |
| Ventas Hoy | Cuántas ventas hizo hoy |
| Ingresos Hoy | Cuánto dinero generó hoy |

### Sección "Historial de mis ventas"
Tabla con columnas: Fecha, Cliente, Vendedor, Total, Detalle.

Incluye **filtro por fecha** (Desde / Hasta). Cuando el vendedor filtra:
```sql
WHERE v.id_usuario = :id AND v.fecha BETWEEN :desde AND :hasta
```
Si no hay ventas en el periodo, muestra el mensaje: *"No hay ventas en el periodo seleccionado."*

### Sección "Productos disponibles"
Tabla con columnas: Producto, Categoría, Precio, Stock, Estado.

Al cargar, el filtro **Normal** está activo por defecto — los productos agotados y de stock bajo están ocultos con `style="display:none"` desde PHP:
```php
<?= $est !== 'normal' ? 'style="display:none"' : '' ?>
```

---

## ¿Cómo funciona el modal "Nueva Venta"?

### ¿Cómo se activa?
Clic en el botón **"Nueva Venta"** en la esquina superior derecha.

### ¿Qué hace el usuario?
1. Selecciona un **cliente activo** del select (los inactivos no aparecen).
2. Hace clic en **"Agregar producto"** para añadir filas de productos.
3. Cada producto muestra su stock entre corchetes. Los agotados están deshabilitados.
4. La cantidad se limita automáticamente al stock disponible.
5. El subtotal se calcula en tiempo real: `precio × cantidad`.
6. El total se actualiza sumando todos los subtotales.
7. Hace clic en **"Confirmar Venta"**.

### ¿A dónde van los datos?
POST a `controllers/VentaController.php?accion=registrar`

### ¿Qué hace el botón Cancelar?
La función `cancelarVenta()` limpia el contenedor de productos, reinicia el contador y pone el total en $0, luego cierra el modal **sin enviar nada al servidor**.

---

## ¿Cómo funciona ver el detalle de una venta?

Clic en el **ícono del ojo** → el JavaScript hace una petición `fetch`:
```javascript
fetch('../../controllers/VentaController.php?accion=detalle&id=' + id)
    .then(r => r.json())
    .then(data => { /* construye el HTML del modal con los productos */ });
```
El servidor responde con un JSON con los productos, cantidades, precios y subtotales de esa venta.

---

## ¿Cómo funciona el filtro de inventario?

Los botones **Normal / Stock Bajo / Agotado** llaman a `filtrarInv(estado)`:
```javascript
var filtroInvActivo = 'normal';  // Activo por defecto

function filtrarInv(estado) {
    filtroInvActivo = estado;
    // Actualiza estilos de botones
    aplicarFiltrosInv();
}

function aplicarFiltrosInv() {
    filas.forEach(function(fila) {
        var ok = (nombre.includes(q) || cat.includes(q)) && estado === filtroInvActivo;
        fila.style.display = ok ? '' : 'none';
    });
}
```

---

## Sidebar del Vendedor (`sidebar_vendedor.php`)

El sidebar del vendedor tiene un menú reducido con solo 5 ítems:

| Ítem | Ruta | Acceso |
|---|---|---|
| Inicio | `dashboard/vendedor.php` | Dashboard propio |
| Ventas | `vendedor/ventas.php` | Su vista de ventas (no la del admin) |
| Clientes | `clientes/index.php` | Puede gestionar clientes |
| Productos | `productos/index.php` | Solo lectura |
| Inventario | `inventario/index.php` | Solo lectura |

**No tiene acceso a:** Usuarios, Compras, Reportes.

El ítem activo se detecta comparando `$_SERVER['PHP_SELF']` con el nombre de cada sección.

---

## Flujo completo del dashboard

```
Vendedor inicia sesión → AuthController → views/dashboard/vendedor.php
    ├── Verifica que NO sea administrador
    ├── Carga KPIs propios (WHERE id_usuario = :id)
    ├── Carga últimas 10 ventas propias
    ├── Carga inventario completo
    ├── Carga clientes activos y productos para el modal
    └── Muestra pantalla con KPIs + historial + inventario

NUEVA VENTA: botón → modal → POST → VentaController (registrar)
    → INSERT venta + INSERT detalle_venta + UPDATE stock (transacción)

VER DETALLE: ojo → fetch → VentaController (detalle) → JSON → modal

FILTRAR VENTAS: formulario GET → ?desde=&hasta= → recarga con filtro de fechas

FILTRAR INVENTARIO: botones Normal/Stock Bajo/Agotado → JavaScript → style.display
```
