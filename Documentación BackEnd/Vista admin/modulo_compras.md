# Módulo de Compras — Vista del Administrador

**Archivos involucrados:**
- Vista: `views/compra/index.php`
- Controlador: `controllers/CompraController.php`
- Modelo: `models/compra.php`

---

## ¿Qué es este módulo?

Permite registrar las compras realizadas a los proveedores. Cada vez que se confirma una compra, el stock de los productos comprados **sube automáticamente**. Solo el administrador puede registrar y eliminar compras.

---

## ¿Qué muestra la pantalla?

**4 tarjetas KPI:**

| Tarjeta | Qué muestra |
|---|---|
| Total Compras | Cantidad de compras registradas |
| Gasto Total | Suma de todos los totales de compras |
| Compras Hoy | Cuántas compras se hicieron hoy |
| Gasto Hoy | Cuánto se gastó hoy |

**Tabla de compras** con columnas: # Compra, Fecha, Proveedor, Registrado por, Total, Acciones (ojo + basura).

---

## ¿Cómo se cargan los datos?

```sql
SELECT c.id_compra, c.fecha, c.total,
       pp.nombre AS proveedor,
       pu.nombre AS comprador
FROM compra c
LEFT JOIN proveedor pr ON c.id_proveedor = pr.id_proveedor
LEFT JOIN persona   pp ON pr.id_persona  = pp.id_persona
LEFT JOIN usuario   u  ON c.id_usuario   = u.id_usuario
LEFT JOIN persona   pu ON u.id_persona   = pu.id_persona
ORDER BY c.fecha DESC
```
Se hacen 4 JOINs para obtener el nombre del proveedor y del usuario que registró la compra.

---

## Acción 1: Registrar una compra

### ¿Cómo se activa?
Clic en **"Nueva Compra"** → abre el modal con el formulario.

### ¿Qué hace el usuario?
1. Selecciona un **proveedor activo** (los inactivos no aparecen — HU-7).
2. Hace clic en **"Agregar producto"** para añadir filas.
3. Cada fila tiene: select de producto, campo de cantidad y subtotal calculado automáticamente.
4. El **total** se actualiza en tiempo real sumando todos los subtotales.
5. Hace clic en **"Confirmar Compra"**.

### ¿A dónde van los datos?
POST a `controllers/CompraController.php?accion=registrar`

### Validaciones del controlador

| # | Validación | Error si falla |
|---|---|---|
| 1 | Proveedor seleccionado | "Selecciona un proveedor y al menos un producto" |
| 2 | Al menos un producto con cantidad > 0 | "Agrega al menos un producto a la compra" |

### ¿Cómo se guarda en la BD? (Transacción de 3 pasos)

```
Paso 1: Calcula el total sumando todos los subtotales
        INSERT INTO compra (fecha, total, id_usuario, id_proveedor)
        → Obtiene el id_compra generado

Paso 2: Por cada producto:
        INSERT INTO detalle_compra (cantidad, precio, subtotal, id_compra, id_producto)

Paso 3: Por cada producto (HU-5: actualizar inventario):
        UPDATE producto SET stock = stock + :cantidad WHERE id_producto = ?

Si algo falla → rollBack() → no queda nada guardado
```

### ¿Por qué se usa transacción?
Porque si se guarda la compra pero falla la actualización del stock, el inventario quedaría desactualizado. La transacción garantiza que todo se guarda o nada.

### ¿Cómo funciona el botón Cancelar?
La función `cancelarCompra()` limpia el contenedor de productos, reinicia el contador y pone el total en $0, luego cierra el modal **sin enviar nada al servidor**.

---

## Acción 2: Ver detalle de una compra

### ¿Cómo se activa?
Clic en el **ícono del ojo** → abre el modal de detalle.

### ¿Cómo funciona?
El JavaScript hace una petición `fetch` a `CompraController.php?accion=detalle&id=X`. El controlador responde con un **JSON** que contiene los productos de esa compra. El JavaScript construye el HTML del modal con esos datos.

```javascript
fetch('../../controllers/CompraController.php?accion=detalle&id=' + id)
    .then(r => r.json())
    .then(data => { /* construye el HTML del modal */ });
```

---

## Acción 3: Eliminar una compra

### ¿Cómo se activa?
Clic en la **basura** → modal de confirmación → clic "Sí, eliminar".

### ¿Qué hace el modelo? (Transacción de 2 pasos)
```
Paso 1: DELETE FROM detalle_compra WHERE id_compra = ?  ← primero (tiene FK)
Paso 2: DELETE FROM compra WHERE id_compra = ?          ← luego
```
⚠️ **No se puede deshacer.** El stock NO se revierte al eliminar una compra.

---

## Métodos del Modelo (`models/compra.php`)

| Método | SQL que ejecuta | Qué retorna |
|---|---|---|
| `obtenerTodas()` | `SELECT ... FROM compra LEFT JOIN proveedor LEFT JOIN usuario` | Array de compras |
| `obtenerDetalle($id)` | `SELECT ... FROM detalle_compra INNER JOIN producto WHERE id_compra = ?` | Array de ítems |
| `obtenerResumen()` | `SELECT COUNT(*), SUM(total), SUM(CASE WHEN fecha=CURDATE()...)` | Objeto con KPIs |
| `obtenerProveedores()` | `SELECT ... WHERE p.activo = 'activo'` | Solo proveedores activos |
| `obtenerProductos()` | `SELECT id_producto, nombre, precio FROM producto` | Array de productos |
| `registrar($id_usuario, $id_proveedor, $items)` | INSERT compra + INSERT detalle_compra + UPDATE stock (transacción) | true o error |
| `eliminar($id_compra)` | DELETE detalle_compra + DELETE compra (transacción) | true o error |

---

## Flujo completo

```
views/compra/index.php
    ├── Carga compras con obtenerTodas()
    ├── Carga proveedores activos con obtenerProveedores()
    ├── Carga productos con obtenerProductos()
    └── Muestra tabla con KPIs

REGISTRAR: "Nueva Compra" → modal → POST → CompraController (registrar)
    → 2 validaciones → registrar()
    → INSERT compra + INSERT detalle_compra + UPDATE stock (transacción)

VER DETALLE: ojo → fetch → CompraController (detalle) → JSON → modal

ELIMINAR: basura → confirmación → URL → CompraController (eliminar)
    → eliminar() → DELETE detalle_compra + DELETE compra (transacción)
```
