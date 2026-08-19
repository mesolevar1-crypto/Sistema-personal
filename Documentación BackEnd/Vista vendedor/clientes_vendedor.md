# Módulo de Clientes — Vista del Vendedor

**Archivos involucrados:**
- Vista: `views/clientes/index.php` (compartida con el admin)
- Controlador: `controllers/ClienteController.php`
- Modelo: `models/cliente.php`
- Sidebar: `views/layouts/sidebar_vendedor.php`

---

## ¿Qué puede hacer el vendedor con los clientes?

El vendedor tiene acceso **completo** al módulo de clientes — puede registrar, editar, activar/desactivar y eliminar clientes. Esto es igual que el administrador porque el vendedor necesita gestionar sus propios clientes para poder asociarlos a las ventas.

La vista es la misma (`views/clientes/index.php`), pero el sidebar que se carga es el del vendedor gracias al `sidebar_loader.php`.

---

## ¿Por qué el vendedor puede gestionar clientes?

Porque al registrar una venta, el vendedor necesita seleccionar un cliente. Si el cliente no existe, el vendedor debe poder crearlo sin depender del administrador. Esto agiliza el proceso de venta.

---

## ¿Qué muestra la pantalla?

Una tabla con todos los clientes registrados:

| Columna | Qué muestra |
|---|---|
| Nombre Completo | Nombre del cliente |
| Correo | Correo electrónico o "Sin correo" |
| Teléfono | Número de teléfono o "Sin teléfono" |
| Estado | Badge 🟢 "Activo" o 🔴 "Inactivo" |
| Acciones | Tres botones: lápiz (editar), ban/check (activar/desactivar), basura (eliminar) |

---

## Acción 1: Registrar un cliente

### ¿Cómo se activa?
Clic en **"Nuevo Cliente"** → abre el modal con el formulario.

### Campos del formulario
- Nombre completo (**obligatorio**)
- Teléfono (opcional)
- Correo electrónico (opcional, pero si se ingresa debe tener formato válido)

### Validaciones del controlador

| # | Validación | Error si falla |
|---|---|---|
| 1 | Nombre no vacío | "El nombre del cliente es obligatorio" |
| 2 | Formato de correo válido (si se ingresó) | "El formato del correo no es válido" |
| 3 | Correo no duplicado | "Ya existe un cliente con ese correo" |

### ¿Cómo se guarda?
Transacción en dos pasos:
```
Paso 1: INSERT INTO Persona (nombre, telefono, correo)
Paso 2: INSERT INTO Cliente (id_persona)
```

---

## Acción 2: Editar un cliente

### ¿Qué se puede cambiar?
- ✅ Nombre y Teléfono
- ❌ Correo — no se puede modificar

### ¿Qué hace el modelo?
```sql
UPDATE Persona SET nombre = ?, telefono = ? WHERE id_persona = ?
```

---

## Acción 3: Activar / Desactivar

Un cliente inactivo **no aparece** en el select al registrar una venta. Esto es importante para el vendedor porque si un cliente está inactivo, no podrá seleccionarlo.

```sql
UPDATE Cliente SET estado = ? WHERE id_cliente = ?
```

---

## Acción 4: Eliminar

Borra el cliente y su persona asociada en una transacción:
```
DELETE FROM Cliente WHERE id_cliente = ?
DELETE FROM Persona WHERE id_persona = ?
```
⚠️ **No se puede deshacer.**

---

## Flujo completo

```
Vendedor hace clic en "Clientes" en el sidebar
→ views/clientes/index.php (misma vista que el admin)
→ sidebar_loader.php detecta rol = Vendedor → carga sidebar_vendedor.php

REGISTRAR: "Nuevo Cliente" → modal → POST → ClienteController (registrar)
    → 3 validaciones → registrar() → INSERT Persona + INSERT Cliente

EDITAR: lápiz → modal precargado → POST → ClienteController (editar)
    → editarCompleto() → UPDATE Persona

TOGGLE: ban/check → URL → ClienteController (toggleEstado)
    → cambiarEstado() → UPDATE Cliente SET estado

ELIMINAR: basura → confirmación → URL → ClienteController (eliminar)
    → eliminar() → DELETE Cliente + DELETE Persona
```
