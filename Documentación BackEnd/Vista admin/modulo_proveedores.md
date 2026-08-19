# Módulo de Proveedores — Vista del Administrador

**Archivos involucrados:**
- Vista: `views/proveedores/index.php`
- Controlador: `controllers/ProveedorController.php`
- Modelo: `models/proveedor.php`

---

## ¿Qué es este módulo?

Permite gestionar los proveedores del negocio. El administrador puede registrar nuevos proveedores, editar sus datos, activarlos o desactivarlos y eliminarlos. Los proveedores activos son los que aparecen disponibles al registrar una compra.

---

## ¿Qué muestra la pantalla?

Una tabla con todos los proveedores registrados:

| Columna | Qué muestra |
|---|---|
| Nombre / Empresa | Nombre del proveedor |
| Correo | Correo electrónico o "Sin correo" |
| Teléfono | Número de teléfono o "Sin teléfono" |
| Frecuencia Entrega | Ej: "Semanal", "Mensual" o "Sin frecuencia" |
| Estado | Badge 🟢 "Activo" o 🔴 "Inactivo" |
| Acciones | Tres botones: lápiz (editar), ban/check (activar/desactivar), basura (eliminar) |

---

## ¿Cómo se cargan los datos?

```sql
SELECT p.id_proveedor, p.frecuencia_entrega, p.id_persona,
       pe.nombre, pe.telefono, pe.correo, pe.activo AS estado
FROM proveedor p
INNER JOIN persona pe ON p.id_persona = pe.id_persona
ORDER BY pe.nombre ASC
```
Los datos del proveedor están en dos tablas: `proveedor` guarda la frecuencia de entrega y el vínculo, `persona` guarda el nombre, teléfono y correo.

---

## Acción 1: Registrar un proveedor

### ¿Cómo se activa?
Clic en **"Nuevo Proveedor"** → abre el modal con el formulario.

### Campos del formulario
- Nombre / Empresa (**obligatorio**)
- Teléfono (opcional)
- Correo electrónico (opcional, pero si se ingresa debe tener formato válido)
- Frecuencia de entrega (opcional, ej: "Semanal")

### ¿A dónde van los datos?
POST a `controllers/ProveedorController.php?accion=registrar`

### Validaciones del controlador

| # | Validación | Error si falla |
|---|---|---|
| 1 | Nombre no vacío | "El nombre del proveedor es obligatorio" |
| 2 | Formato de correo válido (si se ingresó) | "El formato del correo no es válido" |

### ¿Cómo se guarda en la BD?
```
Paso 1: INSERT INTO persona (nombre, telefono, correo, activo)
        → activo = 'activo' por defecto
        → Obtiene el id_persona generado

Paso 2: INSERT INTO proveedor (frecuencia_entrega, id_persona)
        → Vincula el proveedor a la persona creada
```

---

## Acción 2: Editar un proveedor

### ¿Cómo se activa?
Clic en el **lápiz** → JavaScript precarga los datos en el modal de edición.

### ¿Qué se puede cambiar?
- ✅ Nombre, Teléfono, Frecuencia de entrega
- ❌ Correo — no se puede modificar por seguridad

### ¿Qué hace el modelo?
```
Paso 1: SELECT id_persona FROM proveedor WHERE id_proveedor = ?
Paso 2: UPDATE persona SET nombre = ?, telefono = ?, correo = ? WHERE id_persona = ?
Paso 3: UPDATE proveedor SET frecuencia_entrega = ? WHERE id_proveedor = ?
```

---

## Acción 3: Activar / Desactivar

### ¿Cómo se activa?
Clic en el **ícono ban** (naranja) o **check** (verde).

### ¿Qué hace?
Llama a `ProveedorController.php?accion=toggleEstado&id=X&estado=Y` e invierte el estado en la tabla `persona`:
```sql
UPDATE persona SET activo = :activo WHERE id_persona = :id_persona
-- activo = 'activo' o 'inactivo'
```

### Consecuencia de desactivar
Un proveedor inactivo **no aparece** en el select al registrar una compra (el modelo filtra `WHERE p.activo = 'activo'`).

---

## Acción 4: Eliminar

### ¿Cómo se activa?
Clic en la **basura** → modal de confirmación → clic "Sí, eliminar".

### ¿Qué hace el modelo?
```
Paso 1: SELECT id_persona FROM proveedor WHERE id_proveedor = ?
Paso 2: DELETE FROM proveedor WHERE id_proveedor = ?  ← primero (tiene FK)
Paso 3: DELETE FROM persona WHERE id_persona = ?      ← luego
```
⚠️ **No se puede deshacer.**

---

## Métodos del Modelo (`models/proveedor.php`)

| Método | SQL que ejecuta | Qué retorna |
|---|---|---|
| `obtenerTodos()` | `SELECT ... FROM proveedor INNER JOIN persona ORDER BY nombre` | Array de proveedores |
| `obtenerPorId($id)` | `SELECT ... WHERE p.id_proveedor = ?` | Un proveedor o false |
| `registrar(...)` | `INSERT persona` + `INSERT proveedor` | true o error |
| `editar(...)` | `UPDATE persona` + `UPDATE proveedor` | true o error |
| `eliminar($id)` | `DELETE proveedor` + `DELETE persona` | true o error |
| `toggleEstado($id, $estado)` | `UPDATE persona SET activo = ?` | true o error |

---

## Flujo completo

```
views/proveedores/index.php
    ├── Carga proveedores con obtenerTodos()
    └── Muestra tabla con acciones

REGISTRAR: "Nuevo Proveedor" → modal → POST → ProveedorController (registrar)
    → 2 validaciones → registrar() → INSERT persona + INSERT proveedor

EDITAR: lápiz → modal precargado → POST → ProveedorController (editar)
    → editar() → UPDATE persona + UPDATE proveedor

TOGGLE: ban/check → URL → ProveedorController (toggleEstado)
    → toggleEstado() → UPDATE persona SET activo

ELIMINAR: basura → confirmación → URL → ProveedorController (eliminar)
    → eliminar() → DELETE proveedor + DELETE persona
```
