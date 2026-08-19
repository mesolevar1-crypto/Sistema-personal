# Módulo de Reportes — Vista del Administrador

**Archivos involucrados:**
- Vista general: `views/reportes/index.php`
- Vista ventas: `views/reportes/ventas.php`
- Vista compras: `views/reportes/compras.php`
- Vista inventario: `views/reportes/inventario.php`
- Modelo: `models/reporte.php`

---

## ¿Qué es este módulo?

Proporciona al administrador una visión analítica del negocio. Tiene cuatro secciones: un dashboard general y tres reportes especializados (ventas, compras e inventario). Cada reporte especializado permite filtrar por fechas y exportar a PDF.

---

## Navegación entre reportes

Desde `reportes/index.php` hay tres botones en el encabezado:
- **Reporte de Ventas** → `reportes/ventas.php`
- **Reporte de Compras** → `reportes/compras.php`
- **Reporte de Inventario** → `reportes/inventario.php`

Cada reporte especializado tiene un botón **← General** para volver al dashboard.

---

## 1. Dashboard General (`index.php`)

### ¿Qué muestra?

**Fila 1 — KPIs principales:**

| Tarjeta | Qué muestra |
|---|---|
| Ingresos | Suma total de todas las ventas |
| Gastos | Suma total de todas las compras |
| Ganancia Neta | Ingresos - Gastos (verde si positivo, rojo si negativo) |
| Hoy | Ventas del día actual |

**Fila 2 — Contadores:** Total clientes, proveedores y productos.

**Fila 3 — Barras por mes:** Ventas y compras de los últimos 6 meses con barras de progreso HTML.

**Fila 4 — Rankings:** Top 5 productos más vendidos y Top 5 mejores clientes.

**Fila 5 — Tablas:** Últimas 10 ventas y productos con stock bajo.

### ¿Cómo se cargan los datos?
El modelo ejecuta una consulta con **subconsultas** que trae todos los KPIs en una sola ejecución:
```sql
SELECT
    (SELECT COUNT(*) FROM venta)                         AS total_ventas,
    (SELECT COALESCE(SUM(total),0) FROM venta)           AS ingresos_totales,
    (SELECT COUNT(*) FROM compra)                        AS total_compras,
    (SELECT COALESCE(SUM(total),0) FROM compra)          AS gastos_totales,
    (SELECT COUNT(*) FROM cliente)                       AS total_clientes,
    (SELECT COUNT(*) FROM producto)                      AS total_productos,
    (SELECT COUNT(*) FROM proveedor)                     AS total_proveedores,
    (SELECT COALESCE(SUM(total),0) FROM venta WHERE fecha = CURDATE()) AS ventas_hoy,
    (SELECT COALESCE(SUM(total),0) FROM compra WHERE fecha = CURDATE()) AS compras_hoy
```

---

## 2. Reporte de Ventas (`ventas.php`)

### ¿Qué muestra?
- Filtro por rango de fechas (Desde / Hasta)
- 4 KPIs: total ventas, ingresos, promedio por venta, ganancia estimada
- Gráfico de barras por día (Chart.js)
- Tabla de ventas del periodo con total al pie
- Top 5 productos más vendidos en el periodo
- Botón **"Exportar PDF"**

### ¿Cómo funciona el filtro?
Los valores se envían por **GET** a la misma página (`?desde=YYYY-MM-DD&hasta=YYYY-MM-DD`). PHP ejecuta:
```sql
SELECT v.id_venta, v.fecha, v.total, pc.nombre AS cliente, pu.nombre AS vendedor
FROM venta v
LEFT JOIN cliente c  ON v.id_cliente = c.id_cliente
LEFT JOIN persona pc ON c.id_persona = pc.id_persona
LEFT JOIN usuario u  ON v.id_usuario = u.id_usuario
LEFT JOIN persona pu ON u.id_persona = pu.id_persona
WHERE v.fecha BETWEEN :desde AND :hasta
ORDER BY v.fecha DESC
```

### ¿Cómo funciona el gráfico?
PHP prepara los datos agrupados por día y los pasa al JavaScript como arrays JSON:
```php
$graficoDias    = json_encode(array_column($graficoData, 'dia'));
$graficoTotales = json_encode(array_column($graficoData, 'total'));
```
Chart.js usa esos arrays para dibujar las barras.

### ¿Cómo funciona el PDF?
Usa **jsPDF** y **jsPDF-AutoTable** (librerías cargadas desde CDN). La función `exportarPDF()`:
1. Crea un documento A4 vertical.
2. Dibuja un encabezado verde con el nombre del sistema y el periodo.
3. Genera una tabla de resumen con los KPIs.
4. Genera la tabla completa de ventas con total al pie.
5. Agrega número de página en cada hoja.
6. Descarga el archivo como `reporte-ventas-DESDE-HASTA.pdf`.

---

## 3. Reporte de Compras (`compras.php`)

### ¿Qué muestra?
- Filtro por rango de fechas
- 3 KPIs: total compras, gasto total, promedio por compra
- Tabla de compras del periodo
- Top 5 proveedores por gasto
- Top 5 productos más comprados
- Botón **"Exportar PDF"**

### ¿Cómo funciona?
Igual que el reporte de ventas pero con la tabla `compra`. El filtro usa `WHERE c.fecha BETWEEN :desde AND :hasta`.

---

## 4. Reporte de Inventario (`inventario.php`)

### ¿Qué muestra?
No tiene filtro de fecha — muestra el estado **actual** del stock:
- Alertas de productos agotados y con stock bajo
- 4 KPIs: total productos, unidades, valor del inventario (`stock × precio`), productos críticos
- Tabla completa con nombre, categoría, precio, stock y estado
- Barras de progreso por categoría
- Botón **"Exportar PDF"**

### ¿Cómo calcula el valor del inventario?
```sql
COALESCE(SUM(stock * precio), 0) AS valor_inventario
FROM producto
```
Multiplica el stock de cada producto por su precio y suma todo.

### ¿Cómo funciona el PDF del inventario?
Igual que los otros pero con colores por estado en la tabla:
- 🔴 Rojo para "Agotado"
- 🟠 Naranja para "Stock Bajo"
- 🟢 Verde para "Normal"

---

## Métodos del Modelo (`models/reporte.php`)

| Método | SQL que ejecuta | Para qué se usa |
|---|---|---|
| `obtenerResumenGeneral()` | Subconsultas a venta, compra, cliente, producto, proveedor | KPIs del dashboard general |
| `ventasPorMes()` | `GROUP BY DATE_FORMAT(fecha, '%Y-%m')` en venta | Barras de ventas por mes |
| `comprasPorMes()` | `GROUP BY DATE_FORMAT(fecha, '%Y-%m')` en compra | Barras de compras por mes |
| `topProductosVendidos()` | `SUM(dv.cantidad) GROUP BY p.id_producto ORDER BY total_vendido DESC LIMIT 5` | Top 5 productos |
| `topClientes()` | `SUM(v.total) GROUP BY c.id_cliente ORDER BY total_gastado DESC LIMIT 5` | Top 5 clientes |
| `ventasRecientes()` | `SELECT ... FROM venta ORDER BY fecha DESC LIMIT 10` | Últimas 10 ventas |
| `productosStockBajo()` | `SELECT ... WHERE p.stock <= 5 ORDER BY p.stock ASC LIMIT 5` | Productos críticos |

---

## Librerías externas usadas

| Librería | Versión | Para qué |
|---|---|---|
| Chart.js | 4.4.0 | Gráfico de barras en reporte de ventas |
| jsPDF | 2.5.1 | Generación del documento PDF |
| jsPDF-AutoTable | 3.8.2 | Tablas con estilos dentro del PDF |

Todas se cargan desde **CDN** — no requieren instalación en el servidor.

---

## Flujo completo

```
views/reportes/index.php
    ├── obtenerResumenGeneral() → KPIs con subconsultas
    ├── ventasPorMes() → barras de ventas
    ├── comprasPorMes() → barras de compras
    ├── topProductosVendidos() → ranking productos
    ├── topClientes() → ranking clientes
    ├── ventasRecientes() → últimas 10 ventas
    └── productosStockBajo() → productos críticos

views/reportes/ventas.php
    ├── Filtro GET ?desde=&hasta= → WHERE fecha BETWEEN
    ├── Gráfico Chart.js con datos JSON de PHP
    └── Exportar PDF con jsPDF + jsPDF-AutoTable

views/reportes/compras.php
    ├── Filtro GET ?desde=&hasta= → WHERE fecha BETWEEN
    └── Exportar PDF

views/reportes/inventario.php
    ├── Sin filtro de fecha (estado actual)
    └── Exportar PDF con colores por estado
```
