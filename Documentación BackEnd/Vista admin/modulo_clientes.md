# Módulo de Clientes — Vista del Administrador

Archivos involucrados:
- Vista: `views/clientes/index.php`
- Controlador: `controllers/ClienteController.php`
- Modelo: `models/cliente.php`



¿Qué es este módulo?

Permite gestionar los clientes del negocio. El administrador puede registrar nuevos clientes, editar sus datos, activarlos o desactivarlos y eliminarlos. Los clientes activos son los que aparecen disponibles al registrar una venta.



¿Qué muestra la pantalla?

Una tabla con todos los clientes registrados:

| Columna | Qué muestra |
|---|---|
| Nombre Completo | Nombre del cliente |
| Correo | Correo electrónico o "Sin correo" si no tiene |
| Teléfono | Número de teléfono o "Sin teléfono" si no tiene |
| Estado | Badge 🟢 "Activo" o 🔴 "Inactivo" |
| Acciones | Tres botones: lápiz (editar), ban/check (activar/desactivar), basura (eliminar) |




 ¿Cómo se cargan los datos?

sql
SELECT c.id_cliente, c.estado, p.nombre, p.telefono, p.correo
FROM Cliente c
INNER JOIN Persona p ON c.id_persona = p.id_persona
```
Los datos del cliente están en dos tablas: `Cliente` guarda el estado y el vínculo, `Persona` guarda el nombre, teléfono y correo.

---

Acción 1: Registrar un cliente

¿Cómo se activa?
Clic en **"Nuevo Cliente"** → abre el modal con el formulario.

 Campos del formulario
- Nombre completo (**obligatorio**)
- Teléfono (opcional)
- Correo electrónico (opcional, pero si se ingresa debe tener formato válido)

¿A dónde van los datos?
POST a `controllers/ClienteController.php?accion=registrar`

Validaciones del controlador

| # | Validación | Error si falla |
|---|---|---|
| 1 | Nombre no vacío | "El nombre del cliente es obligatorio" |
| 2 | Formato de correo válido (si se ingresó) | "El formato del correo no es válido" |
| 3 | Correo no duplicado en la BD | "Ya existe un cliente con ese correo" |

### ¿Cómo se guarda en la BD?
Transacción en dos pasos:
```
Paso 1: INSERT INTO Persona (nombre, telefono, correo)
        → Obtiene el id_persona generado

Paso 2: INSERT INTO Cliente (id_persona)
        → Vincula el cliente a la persona creada

Si algo falla → rollBack()
```

---

## Acción 2: Editar un cliente

### ¿Cómo se activa?
Clic en el **lápiz** → JavaScript precarga los datos en el modal de edición.

### ¿Qué se puede cambiar?
- ✅ Nombre
- ✅ Teléfono
- ❌ Correo — no se puede modificar por seguridad

### ¿A dónde van los datos?
POST a `controllers/ClienteController.php?accion=editar`

### ¿Qué hace el controlador?
```sql
UPDATE Persona SET nombre = ?, telefono = ? WHERE id_persona = ?
```
Primero busca el `id_persona` del cliente, luego actualiza la tabla `Persona`.

---

Acción 3: Activar / Desactivar

¿Cómo se activa?
Clic en el **ícono ban** (naranja) para desactivar o **check** (verde) para activar.

¿Qué hace?
Llama a `ClienteController.php?accion=toggleEstado&id=X&estado=Y` e invierte el estado:
```php
$nuevoEstado = $estadoActual == 1 ? 0 : 1;
UPDATE Cliente SET estado = ? WHERE id_cliente = ?
```

Consecuencia de desactivar
Un cliente inactivo **no aparece** en el select al registrar una venta, pero su historial se conserva.



Acción 4: Eliminar

¿Cómo se activa?
Clic en la **basura** → modal de confirmación → clic "Sí, eliminar".

¿Qué hace el controlador?
```
Paso 1: SELECT id_persona FROM Cliente WHERE id_cliente = ?
Paso 2: DELETE FROM Cliente WHERE id_cliente = ?   ← primero (tiene FK)
Paso 3: DELETE FROM Persona WHERE id_persona = ?   ← luego
```
No se puede deshacer.

---

## Métodos del Modelo (`models/cliente.php`)

| Método | SQL que ejecuta | Qué retorna |
|---|---|---|
| `obtenerTodos()` | `SELECT ... FROM Cliente INNER JOIN Persona` | Array con todos los clientes |
| `existeCorreo($correo)` | `SELECT id_persona FROM Persona WHERE correo = ?` | true/false |
| `obtenerPorId($id)` | `SELECT ... WHERE c.id_cliente = ?` | Un cliente o false |
| `registrar($datos)` | `INSERT Persona` + `INSERT Cliente` (transacción) | true o mensaje de error |
| `editarCompleto($id, ...)` | `UPDATE Persona SET nombre, telefono` | true o mensaje de error |
| `cambiarEstado($id, $estado)` | `UPDATE Cliente SET estado = ?` | true o mensaje de error |
| `eliminar($id)` | `DELETE Cliente` + `DELETE Persona` (transacción) | true o mensaje de error |

---

Flujo completo

```
views/clientes/index.php
    ├── Carga todos los clientes con obtenerTodos()
    └── Muestra tabla con acciones

REGISTRAR: modal → POST → ClienteController (registrar)
    → 3 validaciones → registrar() → INSERT Persona + INSERT Cliente

EDITAR: lápiz → modal precargado → POST → ClienteController (editar)
    → editarCompleto() → UPDATE Persona

TOGGLE: ban/check → URL → ClienteController (toggleEstado)
    → cambiarEstado() → UPDATE Cliente SET estado

ELIMINAR: basura → confirmación → URL → ClienteController (eliminar)
    → eliminar() → DELETE Cliente + DELETE Persona
```
