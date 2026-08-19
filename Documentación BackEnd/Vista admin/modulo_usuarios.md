# Módulo de Usuarios — Vista del Administrador

**Archivos involucrados:**
- Vista principal: `views/dashboard/admin.php`
- Controlador de creación: `controllers/UsuarioController.php`
- Controlador de edición/eliminación/estado: `controllers/AdminUsuarioController.php`
- Modelo: `models/usuario.php`

---

## ¿Qué es este módulo?

Es la **pantalla principal del administrador** al iniciar sesión. Desde aquí puede ver todos los usuarios registrados en el sistema y realizar cuatro acciones sobre ellos: crear, editar, activar/desactivar y eliminar.

**Solo el administrador puede acceder.** Si alguien sin ese rol intenta entrar, el sistema lo redirige al login inmediatamente.

---

## ¿Qué muestra la pantalla?

Una tabla con todos los usuarios del sistema. Cada fila tiene estas columnas:

| Columna | Qué muestra |
|---|---|
| Nombre Completo | Nombre del usuario |
| Correo | Correo electrónico |
| Teléfono | Número de teléfono o "No registrado" si no tiene |
| Rol | Badge de color: 🔵 azul para Administrador, 🟡 amarillo para Vendedor |
| Estado | Badge 🟢 verde "Activo" o 🔴 rojo "Inactivo" |
| Acciones | Tres botones: lápiz (editar), ban/check (activar/desactivar), basura (eliminar) |

---

## ¿Cómo se cargan los datos de la tabla?

Al abrir la página, el PHP hace esto:

```
1. Verifica que el usuario sea Administrador → si no, redirige al login
2. Conecta a la base de datos
3. Llama a $usuarioModel->obtenerTodos()
   → Ejecuta: SELECT u.id_usuario, u.estado, p.nombre, p.telefono, p.correo, r.nombre_rol
              FROM Usuario u
              INNER JOIN Persona p ON u.id_persona = p.id_persona
              INNER JOIN Rol r ON u.id_rol = r.id_rol
4. Recorre el array de usuarios con foreach y construye la tabla HTML
```

---

## Acción 1: Crear un nuevo usuario

### ¿Cómo se activa?
El administrador hace clic en el botón **"Nuevo Usuario"** en la esquina superior derecha.

### ¿Qué pasa?
Se abre el **modal "Agregar Usuario"** con un formulario que pide:
- Nombre completo (obligatorio)
- Teléfono (opcional)
- Correo electrónico (obligatorio)
- Contraseña (obligatorio, mínimo 6 caracteres)
- Confirmar contraseña (debe coincidir)
- Rol: Administrador (1) o Vendedor (2)

### ¿A dónde van los datos?
El formulario envía los datos por **POST** a `controllers/UsuarioController.php`.

Hay un campo oculto importante:
```html
<input type="hidden" name="desde_admin" value="1">
```
Este campo le dice al controlador que la petición viene del panel de admin, para que al terminar redirija de vuelta al dashboard del admin (no a la página de registro pública).

### ¿Qué validaciones hace el controlador?

| # | Validación | Error si falla |
|---|---|---|
| 1 | Campos obligatorios completos | "Debe completar todos los campos" |
| 2 | Formato de correo válido | "Ingrese un correo válido" |
| 3 | Contraseñas coinciden | "Las contraseñas no coinciden" |
| 4 | Contraseña mínimo 6 caracteres | "Mínimo 6 caracteres" |
| 5 | Correo no duplicado en la BD | "Este correo ya está registrado" |

### ¿Cómo se guarda en la base de datos?
El modelo usa una **transacción** (si algo falla, no queda nada a medias):

```
Paso 1: INSERT INTO Persona (nombre, telefono, correo)
        → Guarda los datos personales
        → Obtiene el id_persona generado automáticamente

Paso 2: INSERT INTO Usuario (contraseña, id_persona, id_rol)
        → Guarda la contraseña CIFRADA (nunca en texto plano)
        → Usa el id_persona del paso anterior para vincular ambas tablas

Si algo falla → rollBack() → se revierten ambas inserciones
Si todo sale bien → commit() → ambos registros quedan guardados
```

---

## Acción 2: Editar un usuario

### ¿Cómo se activa?
El administrador hace clic en el **ícono de lápiz** (verde) en la fila del usuario.

### ¿Qué pasa?
La función JavaScript `openEditModal(usuario)` recibe el objeto del usuario (enviado desde PHP con `json_encode`) y precarga los datos en el formulario del modal "Editar Usuario":

```javascript
function openEditModal(usuario) {
    document.getElementById('edit_id_usuario').value = usuario.id_usuario;
    document.getElementById('edit_nombre').value     = usuario.nombre;
    document.getElementById('edit_telefono').value   = usuario.telefono;
    document.getElementById('edit_correo').value     = usuario.correo;  // Solo lectura
    document.getElementById('edit_rol').value        = usuario.id_rol;
    openModal('modalEditar');
}
```

### ¿Qué se puede cambiar?
- ✅ Nombre completo
- ✅ Teléfono
- ✅ Contraseña (opcional — si se deja vacío, no se cambia)
- ✅ Rol (Administrador o Vendedor)
- ❌ Correo — aparece como campo de solo lectura, **no se puede modificar por seguridad**

### ¿A dónde van los datos?
El formulario envía por **POST** a `controllers/AdminUsuarioController.php?accion=editar`.

### ¿Qué hace el controlador?
Actualiza dos tablas en una transacción:
```
UPDATE Persona SET nombre = ?, telefono = ? WHERE id_persona = ?
UPDATE Usuario SET id_rol = ? WHERE id_usuario = ?
```

---

## Acción 3: Activar o Desactivar un usuario

### ¿Cómo se activa?
El administrador hace clic en el **ícono naranja de ban** (para desactivar) o el **ícono verde de check** (para activar).

### ¿Qué pasa?
El botón llama directamente a la URL:
```
controllers/AdminUsuarioController.php?accion=toggleEstado&id=X&estado=Y
```
Donde `X` es el ID del usuario y `Y` es su estado actual (1 = activo, 0 = inactivo).

### ¿Qué hace el controlador?
Invierte el estado: si era 1 lo pone en 0, si era 0 lo pone en 1.
```php
$nuevo_estado = $estado == 1 ? 0 : 1;
UPDATE Usuario SET estado = ? WHERE id_usuario = ?
```

### ¿Qué consecuencias tiene desactivar un usuario?
- El usuario **no puede iniciar sesión** mientras esté inactivo.
- Sus datos y su historial de ventas se **conservan** en la base de datos.
- Puede ser reactivado en cualquier momento.

---

## Acción 4: Eliminar un usuario

### ¿Cómo se activa?
El administrador hace clic en el **ícono de basura** (rojo) en la fila del usuario.

### ¿Qué pasa?
Se abre el **modal de confirmación** que muestra el nombre del usuario y pregunta si está seguro. La función JavaScript configura el enlace de confirmación:

```javascript
function openDeleteModal(id, nombre) {
    document.getElementById('delete_nombre').textContent = nombre;
    document.getElementById('delete_link').href =
        '../../controllers/AdminUsuarioController.php?accion=eliminar&id=' + id;
    openModal('modalEliminar');
}
```

### ¿Qué hace el controlador al confirmar?
Elimina en dos tablas usando una transacción:
```
Paso 1: Busca el id_persona del usuario
Paso 2: DELETE FROM Usuario WHERE id_usuario = ?   ← primero el usuario (tiene FK)
Paso 3: DELETE FROM Persona WHERE id_persona = ?   ← luego la persona
```
El orden importa porque `Usuario` tiene una clave foránea que apunta a `Persona`. Si se borrara `Persona` primero, la base de datos daría error de integridad referencial.

### ⚠️ Esta acción NO se puede deshacer.

---

## ¿Cómo funcionan los modales?

Los tres modales (crear, editar, eliminar) usan el mismo sistema:

```javascript
// Mostrar modal: quita la clase 'hidden' del elemento
function openModal(id) {
    document.getElementById(id).classList.remove('hidden');
}

// Ocultar modal: agrega la clase 'hidden' al elemento
function closeModal(id) {
    document.getElementById(id).classList.add('hidden');
}
```

Todos los modales tienen una animación de entrada definida en CSS:
```css
@keyframes modalRise {
    from { opacity: 0; transform: translateY(20px) scale(0.98); }
    to   { opacity: 1; transform: translateY(0) scale(1); }
}
```

---

## ¿Cómo se muestran los mensajes de éxito o error?

Después de cada acción (crear, editar, activar, eliminar), el controlador guarda un mensaje en `$_SESSION['alert']`. Al redirigir de vuelta al dashboard, la vista lee esa variable y la muestra con **SweetAlert2**:

```php
// El controlador guarda el mensaje
$_SESSION['alert'] = ['icon' => 'success', 'title' => 'Eliminado', 'text' => 'Usuario eliminado'];

// La vista lo lee y lo muestra
Swal.fire({
    icon: 'success',
    title: 'Eliminado',
    text: 'Usuario eliminado',
    confirmButtonColor: '#4A8C44'
});
```

Después de mostrarlo, la variable se elimina con `unset($_SESSION['alert'])` para que no aparezca de nuevo al recargar la página.

---

## Flujo completo del módulo

```
Admin inicia sesión
↓
views/dashboard/admin.php
    ├── Verifica rol = Administrador
    ├── Carga todos los usuarios con obtenerTodos()
    └── Muestra tabla con acciones

CREAR:
    Clic "Nuevo Usuario" → modal → formulario POST
    → UsuarioController.php → 5 validaciones → registrar()
    → INSERT Persona + INSERT Usuario (transacción)
    → Redirige con mensaje de éxito

EDITAR:
    Clic lápiz → openEditModal() precarga datos → formulario POST
    → AdminUsuarioController.php?accion=editar
    → UPDATE Persona + UPDATE Usuario (transacción)
    → Redirige con mensaje de éxito

ACTIVAR/DESACTIVAR:
    Clic ban/check → URL directa
    → AdminUsuarioController.php?accion=toggleEstado
    → UPDATE Usuario SET estado = nuevo_estado
    → Redirige con mensaje de éxito

ELIMINAR:
    Clic basura → modal confirmación → clic "Sí, eliminar"
    → AdminUsuarioController.php?accion=eliminar
    → DELETE Usuario + DELETE Persona (transacción)
    → Redirige con mensaje de éxito
```
