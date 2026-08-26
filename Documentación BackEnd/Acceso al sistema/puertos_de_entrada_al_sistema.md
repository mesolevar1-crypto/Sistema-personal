# Puertas de Entrada al Sistema — VentaNet

**Archivos involucrados:**
- Landing page: `public/index.php`
- Login: `views/usuarios/login.php`
- Registro: `views/usuarios/registre.php`
- Controlador de autenticación: `controllers/AuthController.php`
- Controlador de registro: `controllers/UsuarioController.php`
- Modelo: `models/usuario.php`
- Conexión: `config/databse.php` *(nombre real del archivo tal como está en el repo)*

> **Nota:** la sección 1 (Landing Page) no se actualizó en esta revisión porque no se recibió el código actual de `public/index.php`. El resto del documento fue verificado directamente contra `AuthController.php`, `UsuarioController.php`, `login.php` y `registre.php`.

---

## 1. Landing Page (`public/index.php`)

*(Sin cambios respecto a la versión anterior — pendiente de revisar contra el código actual)*

### ¿Qué es y para qué sirve?

Es la **primera pantalla que ve cualquier persona** cuando entra a la URL raíz del sistema (`/public/index.php`). No requiere que el usuario haya iniciado sesión — es completamente pública. Su único propósito es presentar el sistema y dirigir al usuario hacia el login mediante botones de llamada a la acción.

### ¿Cómo está construida?

Usa **TailwindCSS** cargado desde CDN con una paleta de colores personalizada (verdes de marca) y las fuentes **DM Serif Display** (títulos) y **DM Sans** (cuerpo), cargadas desde Google Fonts.

### ¿Qué secciones tiene?

Navbar fijo, Hero con carrusel de imágenes, sección de Beneficios (3 tarjetas), Catálogo de ejemplo (4 productos) y Footer con enlace al login. El JavaScript es nativo, sin librerías externas.

---

## 2. Módulo de Login (`views/usuarios/login.php`)

### ¿Cómo está diseñada la pantalla?

Es una tarjeta de **dos paneles** con animación de entrada:
- **Panel izquierdo**: formulario blanco con logo circular, título "VentaNet", campos de correo y contraseña con íconos SVG, casilla "Recordar mi sesión", enlace "¿Olvidaste tu contraseña?" (aún sin funcionalidad — `href="#"`) y botón de envío.
- **Panel derecho** (visible solo en pantallas ≥900px): imagen de fondo cargada desde **Unsplash** (ya no es `img/login-bg.png` local) con overlay degradado, botón "← Regresar al Inicio" y texto descriptivo del negocio.

> **Cambio respecto a la versión anterior:** el layout está invertido. Antes la imagen iba a la izquierda y el formulario a la derecha; ahora es al revés.

### ¿Qué pasa cuando el usuario hace clic en "Iniciar sesión"?

El formulario envía los datos por **POST** a `controllers/AuthController.php`. El proceso completo es:

```
Usuario escribe correo + contraseña → clic en "Iniciar sesión"
↓
AuthController.php recibe los datos por POST
↓
¿Campos vacíos? → SÍ → alerta "Campos incompletos"
↓
¿Está bloqueado por intentos? → SÍ → muestra tiempo restante en el modal
↓
Conecta a la BD (config/databse.php) → si falla, alerta "Error del sistema"
↓
¿Existe el correo en la BD? → NO → incrementa contador de intentos
↓
¿Cuenta activa (estado_usuario = 1 y estado_persona = 1)? → NO → alerta "Cuenta inactiva" (no cuenta como intento fallido)
↓
¿Contraseña correcta (password_verify)? → NO → incrementa contador de intentos
↓
¿3 intentos fallidos? → SÍ → bloquea 2 minutos
↓
Todo correcto → resetea intentos → regenera sesión → guarda $_SESSION['usuario'] → redirige según rol
```

### Validación de cuenta activa (nuevo)

Antes de verificar la contraseña, el controlador revisa que la cuenta esté activa en **dos tablas**:

```php
if (!isset($usuario['estado_usuario']) || (int)$usuario['estado_usuario'] !== 1) {
    // "Cuenta inactiva. Contacta al administrador."
}
if (!isset($usuario['estado_persona']) || (int)$usuario['estado_persona'] !== 1) {
    // "Cuenta inactiva. Contacta al administrador."
}
```

Esto permite desactivar un usuario (por ejemplo, un empleado que ya no trabaja ahí) sin borrarlo de la base de datos. **Importante:** esta validación redirige directo con el mensaje de error, **no cuenta como intento fallido** — no incrementa `login_intentos`.

### ¿Cómo funciona el bloqueo por intentos fallidos?

Sigue guardando tres variables en la sesión PHP:

| Variable de sesión | Qué guarda |
|---|---|
| `$_SESSION['login_intentos']` | Número de intentos fallidos (0, 1, 2, 3) |
| `$_SESSION['login_bloqueado']` | true/false — si está bloqueado |
| `$_SESSION['login_tiempo']` | Timestamp del momento en que se bloqueó |

Al tercer intento fallido (correo no encontrado o contraseña incorrecta), `falloLogin()` marca `login_bloqueado = true` y guarda `login_tiempo`. Tanto el **controlador** como la **vista** revisan si ya pasaron los 120 segundos: si sí, resetean las tres variables automáticamente (doble verificación, por si el usuario recarga la página de login sin volver a enviar el formulario).

> **Cambio respecto a la versión anterior:** ya **no se muestran los puntos de colores** ("🔴🔴⚫ Intento 2 de 3"). Ahora el único indicador visual es que el botón "Iniciar sesión" queda `disabled` mientras dure el bloqueo, y el mensaje con los intentos restantes aparece dentro del texto del modal (ej: *"La contraseña no coincide. Te quedan 2 intento(s)."*).

### ¿Cómo se muestran las alertas del servidor?

**Cambio respecto a la versión anterior:** ya no se usa `Swal.fire()` (SweetAlert2). Ahora el propio `login.php` construye un **modal HTML propio** (`.modal-overlay` / `.modal-caja`) directamente en PHP, según el contenido de `$_SESSION['alert']`:

- `icon: success` → ícono verde de check
- `icon: warning` → ícono amarillo de advertencia
- cualquier otro valor (`error`) → ícono rojo de X

El botón "OK" del modal simplemente vuelve a `login.php` (recarga limpia, sin alerta pendiente).

### ¿Qué hace el botón del ojo en la contraseña?

La función ahora se llama `togglePass()` (antes `togglePassword()`) y alterna el campo `#campo-pass` entre `password` y `text`, cambiando el ícono SVG entre ojo abierto y ojo tachado.

---

## 3. Módulo de Registro (`views/usuarios/registre.php`)

### ¿Cómo está diseñada la pantalla?

Dos paneles, en el mismo orden que antes:
- **Panel izquierdo**: imagen de fondo (Unsplash) con overlay, botón "← Volver al Login" y texto de bienvenida.
- **Panel derecho**: formulario con campos en grid de dos columnas (Nombre + Teléfono, luego Contraseña + Confirmar contraseña).

### ¿Qué campos solicita?

| Campo | Obligatorio | Validación |
|---|---|---|
| Nombre completo | ✅ Sí | No puede estar vacío |
| Teléfono | ❌ No | Sin validación de formato en el HTML |
| Correo electrónico | ✅ Sí | `type="email"` + validación del navegador |
| Contraseña | ✅ Sí | Campo `required`, sin longitud mínima visible en el HTML |
| Confirmar contraseña | ✅ Sí | Campo `required` |
| Términos y condiciones | ✅ Sí (checkbox) | **Nuevo** — obligatorio marcarlo para registro público |
| Rol del usuario | — | **Ya no es un campo que el usuario elige.** Se envía oculto: `<input type="hidden" name="rol" value="1">` |

> ⚠️ **Observación para verificar:** el formulario público ahora envía siempre `rol=1` de forma fija, sin selector. Según `AuthController.php`, el rol `1` es el que redirige a `views/inicio/index.php` (antes, en la documentación previa, el rol `1` correspondía a "Administrador"). No quedó claro si esto es intencional (por ejemplo, si los roles se renumeraron y ahora `1` significa "Cliente") o si es un descuido que estaría dando permisos de administrador a cualquiera que se registre desde la página pública. Vale la pena confirmarlo con quien mantiene `models/usuario.php` antes de dar esto por documentado como comportamiento definitivo.

### ¿Qué hace el controlador al recibir el registro?

`UsuarioController.php` ahora maneja **dos flujos distintos** según de dónde venga la petición:

**Flujo público** (formulario de `registre.php`):
1. Verifica que sea POST.
2. Recibe los datos (`nombre`, `telefono`, `correo`, `password`, `confirmar_password`).
3. **Valida que se haya marcado la casilla de términos y condiciones** — si no, alerta "Debe aceptar los términos y condiciones" (nuevo, no estaba en la versión anterior).
4. Conecta a la base de datos.
5. Llama a `$usuarioModel->registrar($datos)`.
6. Si el resultado es `true` → guarda `$_SESSION['registro_alert']` (success) y **redirige de vuelta a `registre.php`** (ya no a `login.php`), con el mensaje "Tu cuenta fue creada correctamente. Ahora puedes iniciar sesión."
7. Si falla → guarda `$_SESSION['registro_alert']` (error) con el mensaje que devuelva el modelo, y vuelve a `registre.php`.

**Flujo desde administrador** (cuando llega `desde_admin=1` en el POST, típicamente desde un modal en `admin.php`):
1. Primero verifica que quien está haciendo la petición realmente tenga sesión activa **con rol "administrador"** — si no, lo manda directo a `login.php`.
2. **No exige la casilla de términos y condiciones** (ese paso se salta).
3. Usa `$_SESSION['alert']` (no `registro_alert`) para los mensajes.
4. Si todo sale bien, **no cierra la sesión del admin ni lo redirige al login o registro** — vuelve a `admin.php` con un mensaje de éxito, para que pueda seguir gestionando usuarios sin interrupciones.
5. Si falla, también vuelve a `admin.php` con el error.

> **Cambio respecto a la versión anterior:** las claves de sesión usadas para las alertas ahora son distintas según el flujo (`registro_alert` para público, `alert` para admin). Si estás depurando por qué no aparece una alerta, revisa que estés mirando la clave correcta.

### ¿Cómo se guarda la contraseña?

*(Sin cambios visibles en este código — sigue dependiendo de `models/usuario.php`, no incluido en esta revisión. Se mantiene la descripción anterior: se asume `password_hash()` con `PASSWORD_DEFAULT`, pero esto no se pudo reverificar porque el modelo no fue proporcionado esta vez.)*

### ¿Cómo se muestran las alertas?

Igual que en el login: **ya no usa SweetAlert2**. `registre.php` arma su propio modal HTML. El botón "OK" del modal es dinámico:
- Si la alerta fue de éxito (`icon: success`) → el botón lleva a `login.php`.
- Cualquier otro caso (error, warning) → el botón vuelve a `registre.php` para reintentar.

### Diferencia con el login: no hay botón de mostrar/ocultar contraseña

A diferencia de `login.php`, los campos de contraseña en `registre.php` **no tienen el ícono de ojo** para alternar visibilidad — son campos `type="password"` simples.

---

## 4. Flujo completo de entrada al sistema

```
Usuario abre el navegador
↓
public/index.php  (Landing — sin sesión requerida)
↓ clic en "Ingresar" o "Comenzar ahora"
views/usuarios/login.php
↓ envía formulario POST
controllers/AuthController.php
    ├── ¿No es POST? → redirige a login.php
    ├── ¿Campos vacíos? → alerta "Campos incompletos"
    ├── ¿Bloqueado (< 2 min desde el 3er intento)? → muestra tiempo restante
    ├── Conecta a BD (config/databse.php) → si falla, "Error del sistema"
    ├── ¿Correo no existe? → incrementa intentos → posible bloqueo
    ├── ¿Cuenta inactiva (estado_usuario o estado_persona ≠ 1)? → alerta directa, NO cuenta como intento
    ├── ¿Contraseña incorrecta? → incrementa intentos → posible bloqueo
    └── Todo correcto →
            resetea intentos/bloqueo
            session_regenerate_id(true)  ← seguridad
            $_SESSION['usuario'] = [id_usuario, id_persona, nombre, email, telefono, rol_id, rol]
            ├── rol_id = 1 → views/inicio/index.php
            └── cualquier otro rol_id → views/dashboard/vendedor.php

Ruta alternativa (crear cuenta nueva — flujo público):
views/usuarios/registre.php
↓ envía formulario POST (rol=1 fijo, oculto)
controllers/UsuarioController.php
    ├── ¿No es POST? → redirige a registre.php
    ├── ¿No marcó términos y condiciones? → alerta "Debe aceptar los términos"
    ├── Conecta a BD
    ├── models/usuario.php → registrar()  (validaciones internas del modelo)
    └── Todo correcto →
            $_SESSION['registro_alert'] = éxito
            → redirige a registre.php (el usuario debe hacer login manualmente)

Ruta alternativa (crear usuario desde el panel admin):
admin.php (modal) → POST con desde_admin=1
controllers/UsuarioController.php
    ├── ¿Sesión activa con rol "administrador"? → si no, a login.php
    ├── NO exige términos y condiciones
    ├── models/usuario.php → registrar()
    └── Todo correcto o con error →
            $_SESSION['alert'] = resultado
            → siempre vuelve a admin.php (nunca cierra la sesión del admin)
```

---

## 5. Datos que se guardan en la sesión al iniciar sesión

Cuando el login es exitoso, el sistema guarda en `$_SESSION['usuario']`:

| Clave | Qué contiene | Para qué se usa |
|---|---|---|
| `id_usuario` | ID numérico del usuario | Filtrar ventas propias del vendedor |
| `id_persona` | ID de la tabla Persona asociada | **Nuevo** — vincula con datos personales |
| `nombre` | Nombre completo | Mostrar en el header y en las tablas |
| `email` | Correo electrónico | **Renombrado** — antes se documentaba como `correo`, el código real usa la clave `email` |
| `telefono` | Teléfono del usuario | **Nuevo** — no estaba documentado antes |
| `rol_id` | ID numérico del rol | Redirigir al dashboard correcto |
| `rol` | Nombre del rol (ej. "Administrador") | Mostrar en el header, controlar acceso |

La sesión se destruye completamente al llamar a `AuthController.php?accion=logout`, que limpia el arreglo `$_SESSION`, borra la cookie de sesión (usando `session_get_cookie_params()`) y ejecuta `session_destroy()`.