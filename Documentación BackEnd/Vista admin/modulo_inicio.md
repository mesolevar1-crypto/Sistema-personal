# Módulo de Inicio — Administrador

## 1. Descripción general

El módulo de **Inicio del Administrador** corresponde al panel principal del sistema. Su función es presentar de manera resumida el estado actual del negocio mediante indicadores, estadísticas de ventas y productos más vendidos.

La información mostrada en el panel se obtiene directamente de la base de datos y se actualiza cada vez que se carga la vista.

El módulo está diseñado exclusivamente para usuarios con el rol de **Administrador**.

---

## 2. Objetivo del módulo

El objetivo principal del módulo es proporcionar al administrador una vista general del comportamiento del negocio sin necesidad de ingresar individualmente a cada módulo.

Desde el panel se puede consultar:

* Ventas realizadas durante el día.
* Ventas acumuladas durante el mes.
* Ganancias estimadas de los últimos siete días.
* Cantidad de productos con stock bajo.
* Total de productos activos.
* Total de usuarios activos.
* Cantidad de ventas realizadas hoy.
* Cantidad de ventas realizadas durante el mes.
* Comportamiento de las ventas de los últimos siete días.
* Productos más vendidos.

---

## 3. Acceso al módulo

El acceso al módulo está restringido mediante la sesión del usuario.

Antes de mostrar la información, el sistema verifica que exista una sesión activa y que el rol del usuario sea exactamente **Administrador**.

Si el usuario no tiene una sesión iniciada o su rol no corresponde a Administrador, es redirigido al inicio de sesión.

```php
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] !== 'Administrador') {
    header("Location: ../usuarios/login.php");
    exit;
}
```

Esta validación evita que usuarios con otros roles puedan acceder directamente al panel administrativo.

---

## 4. Archivos que componen el módulo

El módulo está compuesto principalmente por los siguientes archivos:

### Vista

```text
views/inicio/index.php
```

Es responsable de construir la interfaz visual del panel, mostrar las tarjetas de información, generar la gráfica y presentar los productos más vendidos.

### Modelo

```text
models/inicio.php
```

Contiene los métodos encargados de realizar las consultas a la base de datos y obtener los datos utilizados por la vista.

### Configuración de base de datos

```text
config/databse.php
```

Se utiliza para establecer la conexión con la base de datos.

### Layout del sistema

```text
views/layouts/header.php
views/layouts/sidebar.php
views/layouts/footer.php
```

Estos archivos permiten mantener la estructura general del sistema y reutilizar el encabezado, menú lateral y pie de página.

---

# 5. Funcionamiento general

El flujo del módulo funciona de la siguiente manera:

```text
Usuario
   │
   ▼
Inicio de sesión
   │
   ▼
Verificación de sesión
   │
   ├── No es Administrador ──► Login
   │
   └── Es Administrador
              │
              ▼
       Conexión a BD
              │
              ▼
       Modelo Inicio
              │
              ├── Ventas del día
              ├── Ventas del mes
              ├── Ganancias
              ├── Stock bajo
              ├── Total productos
              ├── Total usuarios
              ├── Ventas últimos 7 días
              └── Productos más vendidos
              │
              ▼
        Vista Inicio
              │
              ▼
       Panel administrativo
```

---

# 6. Conexión con el modelo

La vista carga la configuración de la base de datos y el modelo `Inicio`.

```php
require_once __DIR__ . '/../../config/databse.php';
require_once __DIR__ . '/../../models/inicio.php';

$database = new Database();
$conn = $database->conectar();
$inicio = new Inicio($conn);
```

El modelo recibe la conexión mediante su constructor:

```php
public function __construct($db)
{
    $this->conn = $db;
}
```

De esta manera, los métodos del modelo pueden utilizar la conexión para ejecutar las consultas SQL.

---

# 7. Nombre del usuario

El panel muestra un saludo personalizado utilizando el nombre del usuario autenticado.

```php
$nombreUsuario = htmlspecialchars($_SESSION['usuario']['nombre']);
```

Posteriormente se muestra en la interfaz:

```php
Bienvenido, <?= $nombreUsuario ?> 👋
```

La función `htmlspecialchars()` permite escapar caracteres especiales antes de mostrar el nombre en HTML.

---

# 8. Indicadores principales — KPI

El panel cuenta con seis tarjetas principales.

## 8.1 Ventas del día

Muestra el valor total de las ventas realizadas durante el día actual.

El modelo utiliza el método:

```php
$inicio->ventasDia();
```

La consulta únicamente considera ventas activas:

```sql
WHERE estado = 1
AND DATE(fecha) = CURDATE()
```

También se muestra debajo de la cantidad total el número de ventas realizadas durante el día.

```php
$inicio->contarVentasHoy();
```

### Información mostrada

* Valor total vendido hoy.
* Número de ventas realizadas hoy.

---

## 8.2 Ventas del mes

Muestra el total de ventas realizadas durante el mes y año actuales.

Método utilizado:

```php
$inicio->ventasMes();
```

La consulta compara:

```sql
MONTH(fecha) = MONTH(CURDATE())
AND YEAR(fecha) = YEAR(CURDATE())
```

También se obtiene la cantidad de ventas realizadas durante el mes mediante:

```php
$inicio->contarVentasMes();
```

### Información mostrada

* Valor total vendido durante el mes.
* Número de ventas realizadas durante el mes.

---

## 8.3 Ganancias de la semana

Muestra la ganancia estimada correspondiente a los últimos siete días.

Método:

```php
$inicio->gananciasSemana();
```

La ganancia se calcula utilizando:

```text
Subtotal de la venta
-
(Costo unitario × cantidad)
```

El cálculo se realiza sobre los detalles de venta y las ventas activas.

Esta información es de uso exclusivo del Administrador porque involucra el costo de los productos.

---

## 8.4 Stock bajo

Muestra la cantidad de productos cuyo inventario actual es menor o igual al stock mínimo configurado.

Método:

```php
$inicio->stockBajo();
```

La condición utilizada es:

```sql
i.stock_actual <= i.stock_minimo
```

Solo se consideran productos activos.

Cuando existen productos con stock bajo, la tarjeta cambia visualmente para mostrar una alerta.

### Información mostrada

```text
X productos con poco stock
```

---

## 8.5 Total de productos

Muestra la cantidad total de productos activos registrados en el sistema.

Método:

```php
$inicio->totalProductos();
```

La consulta utiliza:

```sql
SELECT COUNT(*)
FROM producto
WHERE estado = 1
```

Por lo tanto, los productos inactivos no se incluyen en el indicador.

---

## 8.6 Usuarios registrados

Muestra la cantidad de usuarios activos registrados en el sistema.

Método:

```php
$inicio->totalUsuarios();
```

La consulta considera únicamente usuarios cuyo estado sea activo:

```sql
SELECT COUNT(*)
FROM usuario
WHERE estado = 1
```

Este indicador es exclusivo del panel administrativo.

---

# 9. Análisis de ventas

Debajo de las tarjetas principales se encuentra la sección **Análisis de ventas**.

Esta sección está dividida en dos partes:

1. Gráfica de ventas de los últimos siete días.
2. Lista de productos más vendidos.

---

# 10. Gráfica de ventas de los últimos 7 días

La gráfica permite observar visualmente el comportamiento de las ventas durante los últimos siete días, incluyendo el día actual.

El modelo utiliza:

```php
$inicio->ventasUltimos7Dias();
```

El método devuelve los datos organizados por fecha:

```text
[
    '2026-08-20' => 125000,
    '2026-08-21' => 85000,
    '2026-08-22' => 150000
]
```

El sistema rellena automáticamente los días que no tuvieron ventas con un valor de `0`.

Esto permite que siempre se representen los siete días completos.

---

## 10.1 Conversión de las fechas

Las fechas obtenidas desde la base de datos se transforman para mostrarlas de una manera más amigable.

Ejemplo:

```text
2026-08-21
```

se convierte en:

```text
Vie 21
```

Para esto se utilizan los nombres de los días:

```php
$dias = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
```

---

# 11. Chart.js

La gráfica utiliza la biblioteca **Chart.js**.

La vista carga la biblioteca mediante:

```html
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
```

La gráfica se configura como un gráfico de barras:

```javascript
new Chart(ctxVentas, {
    type: 'bar',
```

Los valores obtenidos desde PHP se convierten a formato JSON para poder utilizarlos desde JavaScript.

```php
json_encode($etiquetasDias, JSON_UNESCAPED_UNICODE)
```

y:

```php
json_encode($valoresDias)
```

---

# 12. Productos más vendidos

El panel incluye una sección llamada **Más vendidos**.

Esta sección muestra los cinco productos con mayor cantidad de unidades vendidas.

La vista solicita:

```php
$inicio->productosMasVendidos(5);
```

El modelo agrupa los productos y suma las cantidades vendidas:

```sql
SUM(dv.cantidad) AS cantidad_vendida
```

Posteriormente los ordena de mayor a menor:

```sql
ORDER BY cantidad_vendida DESC
```

y limita el resultado:

```sql
LIMIT :limite
```

---

## 12.1 Información mostrada

Cada producto contiene:

* Posición en el ranking.
* Nombre del producto.
* Cantidad de unidades vendidas.

Ejemplo:

```text
1   Producto A     25 und
2   Producto B     19 und
3   Producto C     15 und
4   Producto D     11 und
5   Producto E      8 und
```

---

# 13. Estado sin ventas

Si todavía no existen ventas registradas, el módulo no deja la sección vacía.

La vista comprueba:

```php
if (empty($masVendidos))
```

y muestra un mensaje indicando:

```text
Sin ventas registradas aún
```

También informa que los productos aparecerán en la sección cuando se registren ventas.

---

# 14. Formato de moneda

Los valores monetarios se presentan utilizando pesos colombianos.

Para esto se utiliza la función:

```php
function formatoMoneda($valor)
{
    return '$' . number_format((float) $valor, 0, ',', '.');
}
```

Por ejemplo:

```text
125000
```

se muestra como:

```text
$125.000
```

El formato utiliza:

* `$` como símbolo monetario.
* `.` como separador de miles.
* `,` como separador decimal.
* Cero decimales.

---

# 15. Seguridad

El módulo implementa diferentes medidas básicas de seguridad.

## 15.1 Control de acceso

Se verifica que exista un usuario autenticado y que su rol sea Administrador.

## 15.2 Protección del nombre del usuario

Se utiliza:

```php
htmlspecialchars()
```

para evitar que contenido HTML ingresado como nombre sea interpretado directamente por el navegador.

## 15.3 Consultas preparadas

El modelo utiliza:

```php
$this->conn->prepare($sql);
```

y parámetros mediante:

```php
$stmt->bindValue()
```

Esto permite trabajar con consultas parametrizadas.

---

# 16. Manejo de errores

Los métodos del modelo utilizan bloques `try/catch` para controlar errores relacionados con la base de datos.

Ejemplo:

```php
try {
    // Consulta
} catch (PDOException $e) {
    error_log("Error ventasDia: " . $e->getMessage());
    return 0;
}
```

Cuando ocurre un error:

1. Se registra información en el log del servidor.
2. El método devuelve un valor predeterminado.
3. Se evita mostrar directamente el error de la base de datos al usuario.

---

# 17. Métodos principales del modelo

| Método                   | Función                                  | Alcance                  |
| ------------------------ | ---------------------------------------- | ------------------------ |
| `ventasDia()`            | Obtiene ventas del día                   | Administrador / vendedor |
| `ventasMes()`            | Obtiene ventas del mes                   | Administrador / vendedor |
| `gananciasSemana()`      | Calcula ganancias de los últimos 7 días  | Administrador            |
| `totalIngresos()`        | Obtiene ingresos acumulados              | Administrador / vendedor |
| `ticketPromedio()`       | Calcula el promedio por venta            | Administrador / vendedor |
| `stockBajo()`            | Cuenta productos con stock bajo          | Global                   |
| `totalClientes()`        | Cuenta clientes registrados              | Global                   |
| `totalProductos()`       | Cuenta productos activos                 | Global                   |
| `contarVentasHoy()`      | Cuenta ventas del día                    | Administrador / vendedor |
| `contarVentasMes()`      | Cuenta ventas del mes                    | Administrador / vendedor |
| `totalUsuarios()`        | Cuenta usuarios activos                  | Administrador            |
| `ventasUltimos7Dias()`   | Obtiene ventas de los últimos 7 días     | Administrador / vendedor |
| `productosMasVendidos()` | Obtiene el ranking de productos vendidos | Administrador / vendedor |

---

# 18. Consideraciones sobre vendedores

El modelo `Inicio` está preparado para trabajar también con vendedores.

Los métodos relacionados con ventas permiten recibir opcionalmente un:

```php
$idUsuario
```

Cuando no se proporciona:

```php
$idUsuario = null
```

el sistema consulta las ventas de todo el negocio.

Cuando se proporciona un identificador:

```php
$idUsuario
```

la consulta filtra las ventas correspondientes únicamente a ese usuario.

Ejemplo:

```sql
AND id_usuario = :id
```

Sin embargo, la vista documentada en este módulo corresponde al **Inicio del Administrador** y utiliza los métodos sin filtrar por vendedor.

---

# 19. Datos utilizados de la base de datos

El módulo obtiene información principalmente de las siguientes tablas:

```text
venta
detalle_venta
producto
inventario
usuario
cliente
```

### `venta`

Se utiliza para obtener:

* Total de ventas.
* Ventas del día.
* Ventas del mes.
* Ventas de los últimos siete días.
* Cantidad de ventas.
* Ingresos acumulados.
* Usuario que realizó la venta.

### `detalle_venta`

Se utiliza para:

* Calcular ganancias.
* Obtener cantidades vendidas por producto.
* Construir el ranking de productos más vendidos.

### `producto`

Se utiliza para:

* Contar productos activos.
* Identificar los productos vendidos.
* Relacionar productos con inventario.

### `inventario`

Se utiliza para:

* Consultar el stock actual.
* Compararlo con el stock mínimo.
* Detectar productos con stock bajo.

### `usuario`

Se utiliza para:

* Contar usuarios activos.
* Mostrar información relacionada con el usuario autenticado.

### `cliente`

El modelo dispone de un método para contar clientes registrados mediante:

```php
totalClientes()
```

Este método actualmente existe en el modelo, pero no es utilizado por la vista actual del Inicio del Administrador.

---

# 20. Interfaz visual

El módulo utiliza una interfaz basada en:

* Tarjetas KPI.
* Paneles informativos.
* Iconos de Font Awesome.
* Colores diferenciados según el tipo de información.
* Gráfica de barras.
* Badges de estado.
* Diseño responsive mediante clases de Tailwind CSS.

Las tarjetas utilizan efectos visuales al pasar el cursor:

```css
.kpi:hover
```

permitiendo elevar ligeramente la tarjeta y generar una sombra.

---

# 21. Indicadores visuales

El módulo utiliza diferentes colores para facilitar la interpretación de la información.

### Verde

Se utiliza principalmente para:

* Ventas.
* Información positiva.
* Estados activos.

### Amarillo

Se utiliza para:

* Ganancias.
* Alertas de stock bajo.

### Azul

Se utiliza para:

* Información relacionada con productos.

### Morado

Se utiliza para:

* Usuarios registrados.

### Rojo

Se utiliza como elemento visual para identificar la sección de productos más vendidos.

---

# 22. Dependencias externas

La vista utiliza Chart.js para la generación de la gráfica.

Dependencia utilizada:

```text
Chart.js 4.4.0
```

También utiliza iconos de Font Awesome y clases de Tailwind CSS provenientes de la estructura general del sistema.

---

# 23. Resumen del módulo

El módulo de Inicio del Administrador funciona como un **dashboard central de información del negocio**.

Su propósito es concentrar en una sola pantalla los principales indicadores que permiten al administrador conocer rápidamente:

```text
Ventas del día
       +
Ventas del mes
       +
Ganancias de la semana
       +
Alertas de stock
       +
Productos activos
       +
Usuarios activos
       +
Comportamiento de ventas
       +
Productos más vendidos
```

La información se obtiene directamente de la base de datos mediante el modelo `Inicio`, mientras que la vista se encarga de presentar los resultados de forma visual y comprensible.

---

# 24. Archivos relacionados

```text
config/
└── databse.php

models/
└── inicio.php

views/
├── inicio/
│   └── index.php
│
└── layouts/
    ├── header.php
    ├── sidebar.php
    └── footer.php
```

---

# 25. Estado actual del módulo

**Estado:** Implementado y funcional.

### Funcionalidades implementadas

* [x] Control de acceso para Administrador.
* [x] Saludo personalizado.
* [x] Ventas del día.
* [x] Ventas del mes.
* [x] Ganancias de los últimos siete días.
* [x] Alerta de stock bajo.
* [x] Total de productos activos.
* [x] Total de usuarios activos.
* [x] Cantidad de ventas del día.
* [x] Cantidad de ventas del mes.
* [x] Gráfica de ventas de los últimos siete días.
* [x] Ranking de cinco productos más vendidos.
* [x] Estado visual cuando no existen ventas.
* [x] Formato de moneda colombiana.
* [x] Manejo de errores mediante `try/catch`.
* [x] Consultas preparadas mediante PDO.
* [x] Diseño responsive.

---

## 26. Observación técnica

El modelo contiene funcionalidades adicionales preparadas para otros escenarios, como el filtrado de ventas por `idUsuario`, el cálculo de ingresos acumulados, el ticket promedio y el conteo de clientes.

Estas funcionalidades forman parte del modelo general `Inicio`, pero **no todas son utilizadas actualmente por la vista del Administrador**.

Por lo tanto, cualquier modificación futura de la vista debe mantener la diferencia entre:

* Información global del negocio.
* Información exclusiva del Administrador.
* Información filtrada por vendedor.
