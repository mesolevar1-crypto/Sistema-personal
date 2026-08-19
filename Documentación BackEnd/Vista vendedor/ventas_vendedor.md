# Módulo de Ventas — Vista del Vendedor

**Archivos involucrados:**
- Vista: `views/vendedor/ventas.php`
- Controlador: `controllers/VentaController.php`
- Modelo: `models/venta.php`

---

## ¿Qué es esta vista?

Es la vista de ventas **exclusiva del vendedor**. Es diferente a la del administrador (`views/venta/index.php`). El vendedor solo puede ver y registrar **sus propias ventas** — no puede ver las de otros vendedores ni del administrador. Tampoco puede eliminar ventas.

Si un administrador intenta acceder a esta URL, es redirigido a `views/venta/index.php`.

---

## Diferencia con la vista del Administrador

| Característica | Admin (`venta/index.php`) | Vendedor (`vendedor/ventas.php`) |
|---|---|---|
| Ventas que ve | Todas las del sistema | Solo las suyas (`WHERE id_usuario = :id`) |
| Columna Vendedor | ✅ Muestra quién vendió | ✅ Siempre su nombre |
| Botón eliminar | ✅ Puede eliminar | ❌ No puede eliminar |
| KPIs | Totales del sistema | Solo los suyos |

---

## ¿Cómo se cargan los datos?

### Ventas del vendedor (solo las suyas)
```sql
SELECT v.id_venta, v.fecha, v.total, pc.nombre AS cliente
FROM venta v
LEFT JOIN cliente c  ON v.id_cliente = c.id_cliente
LEFT JOIN persona pc ON c.id_persona = pc.id_persona
WHERE v.id_usuario = :id
ORDER BY v.fecha DESC, v.id_venta DESC
```
El `:id` es el `id_usuario` de la sesión — garantiza que cada vendedor solo vea sus ventas.

### KPIs propios del vendedor
```sql
SELECT COUNT(*) AS total_ventas,
       COALESCE(SUM(total),0) AS ingresos_total,
       COALESCE(SUM(CASE WHEN fecha=CURDATE() THEN 1 ELSE 0 END),0) AS ventas_hoy,
       COALESCE(SUM(CASE WHEN fecha=CURDATE() THEN total ELSE 0 END),0) AS ingresos_hoy
FROM venta WHERE id_usuario = :id
```

---

## ¿Qué muestra la pantalla?

### 4 tarjetas KPI (solo del vendedor)

| Tarjeta | Qué muestra |
|---|---|
| Mis Ventas | Total de ventas que ha registrado |
| Mis Ingresos | Suma de dinero de todas sus ventas |
| Ventas Hoy | Cuántas vendió hoy |
| Ingresos Hoy | Cuánto generó hoy |

### Tabla de ventas
Columnas: Fecha, Cliente, Vendedor, Total, Detalle.

- La columna **Vendedor** siempre muestra el nombre del vendedor autenticado (`$nombre` de la sesión).
- No hay columna `#` ni botón de eliminar.
- Si no hay ventas, muestra el mensaje: *"Aun no tienes ventas registradas"*.

---

## Acción: Registrar una venta

### ¿Cómo se activa?
Clic en **"Nueva Venta"** → abre el modal.

### ¿Qué hace el usuario?
1. Selecciona un **cliente activo** (los inactivos no aparecen).
2. Hace clic en **"Agregar"** para añadir productos.
3. Cada producto muestra su stock disponible entre corchetes. Los agotados están deshabilitados.
4. La cantidad se limita automáticamente al stock disponible.
5. El subtotal se calcula en tiempo real: `precio × cantidad`.
6. El total se actualiza automáticamente.
7. Hace clic en **"Confirmar Venta"**.

### ¿A dónde van los datos?
POST a `controllers/VentaController.php?accion=registrar`

### ¿Qué validaciones hace el controlador?

| # | Validación | Error si falla |
|---|---|---|
| 1 | Cliente seleccionado y al menos un producto | "Selecciona un cliente y al menos un producto" |
| 2 | Al menos un ítem con cantidad > 0 | "Agrega al menos un producto a la venta" |
| 3 | Stock suficiente para cada producto | "El producto X solo tiene Y unidades disponibles" |

### ¿Cómo se guarda en la BD? (Transacción de 3 pasos)
```
Paso 1: INSERT INTO venta (fecha, total, id_usuario, id_cliente)
        → El id_usuario viene de la sesión (identifica al vendedor)

Paso 2: Por cada producto:
        INSERT INTO detalle_venta (cantidad, subtotal, id_venta, id_producto)

Paso 3: Por cada producto:
        UPDATE producto SET stock = stock - :cantidad WHERE id_producto = ?

Si algo falla → rollBack() → no queda nada guardado
```

### ¿Cómo funciona el botón Cancelar?
La función `cancelarVenta()` limpia el contenedor de productos, reinicia el contador y pone el total en $0, luego cierra el modal **sin enviar nada al servidor**.

---

## Acción: Ver detalle de una venta

### ¿Cómo se activa?
Clic en el **ícono del ojo** → abre el modal de detalle.

### ¿Cómo funciona?
```javascript
fetch('../../controllers/VentaController.php?accion=detalle&id=' + id)
    .then(r => r.json())
    .then(data => { /* construye el HTML con productos, cantidades y precios */ });
```
El servidor responde con un JSON. El JavaScript construye el HTML del modal con los productos de esa venta.

---

## ¿Cómo funciona el JavaScript del modal de venta?

### `agregarItem()`
Crea una fila de producto con:
- Select de productos (con stock visible y agotados deshabilitados)
- Campo de cantidad (limitado al stock disponible)
- Subtotal calculado automáticamente

### `actualizarSubtotal(idx)`
Se ejecuta cuando el usuario cambia el producto o la cantidad:
```javascript
// Limita la cantidad al stock disponible
if (cant > stock && stock > 0) { cantInput.value = stock; cant = stock; }
cantInput.max = stock;
// Calcula el subtotal
var sub = precio * cant;
```

### `calcularTotal()`
Suma todos los subtotales de las filas activas y actualiza el total visible.

### `cancelarVenta()`
Limpia el formulario sin enviar datos al servidor.

---

## Flujo completo

```
views/vendedor/ventas.php
    ├── Verifica que NO sea administrador
    ├── Carga ventas propias (WHERE id_usuario = :id)
    ├── Carga KPIs propios
    ├── Carga clientes activos y productos para el modal
    └── Muestra tabla con KPIs

REGISTRAR: "Nueva Venta" → modal → POST → VentaController (registrar)
    → 3 validaciones (incluyendo stock) → registrar()
    → INSERT venta + INSERT detalle_venta + UPDATE stock (transacción)

VER DETALLE: ojo → fetch → VentaController (detalle) → JSON → modal
```
