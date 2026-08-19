# Puertas de Entrada al Sistema — VentaNet

**Archivos involucrados:**
- Landing page: `public/index.php`
- Login: `views/usuarios/login.php`
- Registro: `views/usuarios/registre.php`
- Controlador de autenticación: `controllers/AuthController.php`
- Controlador de registro: `controllers/UsuarioController.php`
- Modelo: `models/usuario.php`

---

## 1. Landing Page (`public/index.php`)

### ¿Qué es y para qué sirve?

Es la **primera pantalla que ve cualquier persona** cuando entra a la URL raíz del sistema (`/public/index.php`). No requiere que el usuario haya iniciado sesión — es completamente pública. Su único propósito es presentar el sistema y dirigir al usuario hacia el login mediante botones de llamada a la acción.

### ¿Cómo está construida?

Usa **TailwindCSS** cargado desde CDN con una configuración personalizada que define la paleta de colores del sistema:

| Variable | Color | Uso |
|---|---|---|
| `brand-accent` | `#4A8C44` | Verde principal — botones, íconos, textos destacados |
| `brand-dark` | `#1C2E1A` | Verde oscuro — títulos y texto principal |
| `brand-muted` | `#4E6B4A` | Verde medio — texto secundario |
| `brand-light` | `#DFF0DC` | Verde claro — fondos de tarjetas |
| `brand-border` | `#C9E4C5` | Verde muy claro — bordes |

Las fuentes son **DM Serif Display** (para títulos elegantes) y **DM Sans** (para texto de cuerpo), cargadas desde Google Fonts.

### ¿Qué secciones tiene la página?

**Navbar fijo** — barra de navegación con logo, enlaces de ancla (`#inicio`, `#beneficios`, `#catalogo`) y botón "Ingresar" que lleva directamente a `views/usuarios/login.php`. Tiene efecto de sombra al hacer scroll.

**Hero** — sección principal con un carrusel de 3 imágenes de fondo (frutas y verduras de Unsplash) que rotan cada 5 segundos con transición de opacidad. Contiene el titular principal y dos botones: "Comenzar ahora" (va al login) y "Saber más" (baja a la sección de beneficios).

**Beneficios** — grid de 3 tarjetas que explican las funcionalidades principales: Control de Inventario, Ventas Diarias y Ganancias Claras.

**Catálogo** — muestra 4 productos de ejemplo (Manzana Roja, Plátano, Zanahoria, Papa) con imágenes de Unsplash para que el usuario vea cómo se verán sus productos en el sistema.

**Footer** — fondo semitransparente con logo, descripción del sistema y enlace al login.

### ¿Qué JavaScript usa?

Todo el JavaScript es nativo (sin librerías externas):

```javascript
// Carrusel de fondo — cambia la imagen cada 5 segundos
setInterval(() => {
    slides[currentSlide].classList.remove('active', 'opacity-100');
    slides[currentSlide].classList.add('opacity-0');
    currentSlide = (currentSlide + 1) % slides.length;
    slides[currentSlide].classList.remove('opacity-0');
    slides[currentSlide].classList.add('active', 'opacity-100');
}, 5000);

// Efecto de sombra en el navbar al hacer scroll
window.addEventListener('scroll', () => {
    if (window.scrollY > 10) {
        // Agrega sombra y fondo sólido cuando el usuario baja
    }
});
```

---

## 2. Módulo de Login (`views/usuarios/login.php`)

### ¿Cómo está diseñada la pantalla?

Es una tarjeta de **dos paneles** con animación de entrada:
- **Panel izquierdo** (visible solo en pantallas ≥900px): imagen de fondo `img/login-bg.png` con overlay degradado negro y texto descriptivo del negocio.
- **Panel derecho**: formulario blanco con logo circular, título "VentaNet", campos de correo y contraseña con íconos SVG, y botón de envío.

### ¿Qué pasa cuando el usuario hace clic en "Iniciar sesión"?

El formulario envía los datos por **POST** a `controllers/AuthController.php`. El proceso completo es:

```
Usuario escribe correo + contraseña → clic en "Iniciar sesión"
↓
AuthController.php recibe los datos por POST
↓
¿Está bloqueado por intentos? → SÍ → muestra contador regresivo
↓
¿Campos vacíos? → SÍ → alerta "Campos incompletos"
↓
¿Existe el correo en la BD? → NO → incrementa contador de intentos
↓
¿Contraseña correcta? → NO → incrementa contador de intentos
↓
¿3 intentos fallidos? → SÍ → bloquea 2 minutos
↓
Todo correcto → guarda sesión → redirige según rol
```

### ¿Cómo funciona el bloqueo por intentos fallidos?

El sistema guarda tres variables en la sesión PHP:

| Variable de sesión | Qué guarda |
|---|---|
| `$_SESSION['login_intentos']` | Número de intentos fallidos (0, 1, 2, 3) |
| `$_SESSION['login_bloqueado']` | true/false — si está bloqueado |
| `$_SESSION['login_tiempo']` | Timestamp del momento en que se bloqueó |

Al tercer intento fallido, `login_bloqueado` se pone en `true` y `login_tiempo` guarda el momento actual. En cada intento posterior, el sistema calcula `120 - (time() - login_tiempo)` para saber cuántos segundos faltan.

En la vista, un **contador regresivo en JavaScript** se actualiza cada segundo:
```javascript
var restantes = 120 - (Math.floor(Date.now()/1000) - bloqueadoEn);
// Muestra: "Espera 1:45 minutos."
```

### ¿Cómo se muestran los intentos restantes?

La vista lee `$_SESSION['login_intentos']` al cargar. Si hay intentos fallidos, muestra puntos de colores:
- 🔴 Punto rojo = intento fallido consumido
- ⚫ Punto gris = intento disponible
- Texto: "Intento 2 de 3 — Te queda 1 intento."

### ¿Cómo se muestran las alertas del servidor?

Al cargar la página, PHP lee `$_SESSION['alert']` y lo elimina inmediatamente con `unset()`. Si existía una alerta, la renderiza en un bloque `<script>` que ejecuta `Swal.fire()` al cargar el DOM. Esto permite que el servidor comunique resultados sin parámetros en la URL.

### ¿Qué hace el botón del ojo en la contraseña?

La función `togglePassword()` alterna el tipo del campo entre `password` (oculto) y `text` (visible), y cambia el ícono SVG entre el ojo abierto y el ojo tachado.

---

## 3. Módulo de Registro (`views/usuarios/registre.php`)

### ¿Cómo está diseñada la pantalla?

Mismo diseño de dos paneles que el login, con una diferencia importante:
- **Panel izquierdo**: usa una imagen de mercado de frutas y verduras cargada directamente desde **Unsplash CDN** (no se descarga al servidor).
- **Panel derecho**: formulario más extenso con campos en grid de dos columnas.

### ¿Qué campos solicita?

| Campo | Obligatorio | Validación |
|---|---|---|
| Nombre completo | ✅ Sí | No puede estar vacío |
| Teléfono | ❌ No | Solo formato si se ingresa |
| Correo electrónico | ✅ Sí | Debe tener formato válido (usuario@dominio.com) |
| Contraseña | ✅ Sí | Mínimo 6 caracteres |
| Confirmar contraseña | ✅ Sí | Debe coincidir exactamente con la contraseña |
| Rol del usuario | ✅ Sí | Administrador (1) o Vendedor (2) |

### ¿Qué hace el controlador al recibir el registro?

El formulario envía los datos a `controllers/UsuarioController.php`. El controlador aplica **5 validaciones en el servidor** (independientes del navegador):

**Validación 1 — Campos obligatorios:**
```php
if (empty($nombre) || empty($correo) || empty($password) || empty($confirmar_password)) {
    // Error: "Debe completar todos los campos"
}
```

**Validación 2 — Formato de correo:**
```php
if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    // Error: "Ingrese un correo válido"
}
```

**Validación 3 — Contraseñas coinciden:**
```php
if ($password !== $confirmar_password) {
    // Error: "Las contraseñas no coinciden"
}
```

**Validación 4 — Longitud mínima:**
```php
if (strlen($password) < 6) {
    // Error: "Mínimo 6 caracteres"
}
```

**Validación 5 — Correo duplicado:**
```php
if ($usuario->existeCorreo($correo)) {
    // Error: "Este correo ya está registrado"
    // El modelo ejecuta: SELECT id_persona FROM Persona WHERE correo = ?
}
```

### ¿Cómo se guarda la contraseña?

**Nunca se guarda en texto plano.** Se usa `password_hash($password, PASSWORD_DEFAULT)` que genera un hash bcrypt único. Aunque dos usuarios tengan la misma contraseña, sus hashes serán completamente diferentes. Esto protege las contraseñas incluso si alguien accede directamente a la base de datos.

### ¿Cómo se guarda el usuario en la BD?

El modelo `registrar()` abre una **transacción** (para garantizar que ambas inserciones se completen o ninguna):

```
Paso 1: INSERT INTO Persona (nombre, telefono, correo)
        → Obtiene el id_persona generado automáticamente

Paso 2: INSERT INTO Usuario (contraseña, id_persona, id_rol)
        → Usa el id_persona del paso anterior

Si algo falla → rollBack() → no queda nada a medias
Si todo sale bien → commit() → ambos registros quedan guardados
```

### ¿Qué pasa si el registro viene desde el panel de admin?

El formulario del modal en `admin.php` tiene un campo oculto:
```html
<input type="hidden" name="desde_admin" value="1">
```

El controlador detecta este campo y, en lugar de redirigir a `registre.php`, redirige de vuelta a `admin.php`. Esto evita que el administrador sea sacado del sistema al crear un nuevo usuario.

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
    ├── ¿Bloqueado? → muestra contador regresivo
    ├── ¿Campos vacíos? → alerta de campos incompletos
    ├── ¿Usuario no existe? → error + contador de intentos
    ├── ¿Contraseña incorrecta? → error + contador de intentos
    ├── ¿3 intentos fallidos? → bloqueo 2 minutos
    └── Todo correcto →
            session_regenerate_id(true)  ← seguridad
            $_SESSION['usuario'] = [id, nombre, correo, rol_id, rol]
            ├── rol_id = 1 → views/dashboard/admin.php
            └── rol_id = 2 → views/dashboard/vendedor.php

Ruta alternativa (crear cuenta nueva):
views/usuarios/registre.php
↓ envía formulario POST
controllers/UsuarioController.php
    ├── Validación 1: campos obligatorios
    ├── Validación 2: formato de correo
    ├── Validación 3: contraseñas coinciden
    ├── Validación 4: mínimo 6 caracteres
    ├── Validación 5: correo no duplicado
    └── Todo correcto →
            password_hash() ← cifra la contraseña
            models/usuario.php → registrar()
                ├── INSERT INTO Persona
                └── INSERT INTO Usuario
            → redirige a login.php (el usuario debe hacer login manualmente)
```

---

## 5. Datos que se guardan en la sesión al iniciar sesión

Cuando el login es exitoso, el sistema guarda en `$_SESSION['usuario']`:

| Clave | Qué contiene | Para qué se usa |
|---|---|---|
| `id_usuario` | ID numérico del usuario | Filtrar ventas propias del vendedor |
| `nombre` | Nombre completo | Mostrar en el header y en las tablas |
| `correo` | Correo electrónico | Referencia del usuario activo |
| `rol_id` | 1 (Admin) o 2 (Vendedor) | Redirigir al dashboard correcto |
| `rol` | "Administrador" o "Vendedor" | Mostrar en el header, controlar acceso |

Esta sesión se destruye completamente cuando el usuario hace clic en "Cerrar Sesión", que llama a `AuthController.php?accion=logout` y ejecuta `session_unset()` + `session_destroy()`.
