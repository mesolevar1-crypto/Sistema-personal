Documentación — Módulo de Gestión de Ventas

Archivos del módulo

ArchivoRolviews/venta/index.phpVista principal del administradormodels/venta.phpClase Venta, toda la lógica de base de datoscontrollers/VentaController.phpRecibe acciones y conecta vista con modelo

Tablas involucradas: venta, detalle_venta, producto, cliente, usuario, persona

Vista — views/venta/index.php

Protección de acceso
Al cargar la página se verifica que exista $_SESSION['usuario']. Si no hay sesión activa, redirige al login. Si el rol es vendedor, redirige a views/vendedor/ventas.php. Solo el administrador puede ver esta pantalla.

Tarjetas KPI
Se muestran cuatro tarjetas con datos traídos del modelo: total de ventas registradas, ingresos acumulados, ventas del día e ingresos del día. Los valores vienen del método obtenerResumen().

Tabla de ventas
Usa un foreach sobre el arreglo $ventas para dibujar una fila por cada venta. Cada fila muestra fecha, cliente, vendedor y total. Si el arreglo está vacío, aparece un mensaje de estado vacío. Cada fila tiene dos botones: ver detalle y eliminar.

Modal — nueva venta

Se abre con openModal('modalCrear') y agrega automáticamente la primera fila de producto.El select de cliente solo muestra clientes activos (estado = 1), cargados con obtenerClientes().Cada fila de producto muestra nombre, precio y stock disponible. Si el stock es 0, la opción aparece deshabilitada.Al cambiar producto o cantidad se ejecuta actualizarSubtotal(), que limita la cantidad al stock disponible y recalcula el subtotal de esa fila.calcularTotal() suma todos los subtotales y actualiza el total en pantalla en tiempo real.Al confirmar, el formulario envía por POST a VentaController.php?accion=registrar.Cancelar limpia todos los ítems y cierra el modal sin enviar datos.

Modal — detalle de venta
Al hacer clic en el ojo, verDetalle(id) abre el modal y hace una petición fetch a VentaController.php?accion=detalle&id=X. Mientras carga muestra un spinner. La respuesta llega en JSON y se dibuja como lista de productos con cantidad, precio unitario y subtotal de cada ítem.

Modal — eliminar venta
openDeleteModal(id) arma el enlace de confirmación con el ID y abre el modal. Si el administrador confirma, navega a VentaController.php?accion=eliminar&id=X mediante GET.

Alertas de resultado
Después de cada acción el controlador guarda un mensaje en $_SESSION['alert']. Al volver a la vista, si existe esa clave se lanza un Swal.fire() con ícono, título y texto. Luego se borra con unset para que no reaparezca al recargar la página.

Modelo — models/venta.php

obtenerTodas()
Trae todas las ventas con nombre del cliente y del vendedor. Hace cuatro LEFT JOIN: venta → cliente → persona para obtener el nombre del cliente, y venta → usuario → persona para obtener el nombre del vendedor. Ordena por fecha descendente para mostrar las más recientes primero.

obtenerDetalle($id_venta)
Devuelve los productos de una venta específica. Une detalle_venta con producto mediante INNER JOIN para obtener el nombre y precio de cada ítem vendido. Recibe el ID de la venta como parámetro.

obtenerResumen()
Retorna en una sola consulta cuatro métricas: total de ventas, ingresos acumulados, ventas del día e ingresos del día. Usa CASE WHEN fecha = CURDATE() para filtrar los valores del día sin necesidad de hacer dos consultas separadas.

obtenerClientes()
Trae solo clientes con estado = 1 para poblar el select del formulario de nueva venta. Une cliente con persona para obtener el nombre completo. Ordena alfabéticamente.

obtenerProductos()
Devuelve todos los productos con id, nombre, precio y stock. El stock se envía al frontend para que JavaScript pueda deshabilitar opciones agotadas y limitar la cantidad máxima seleccionable por el usuario.

registrar($id_usuario, $id_cliente, $items) — usa transacción

Abre una transacción con beginTransaction().Suma los subtotales del arreglo $items para calcular el total de la venta.Inserta el encabezado en la tabla venta y guarda el ID generado con lastInsertId().Recorre el arreglo de ítems e inserta una fila en detalle_venta por cada producto.Descuenta el stock de cada producto con UPDATE producto SET stock = stock - cantidad.Si todo sale bien ejecuta commit(); si ocurre cualquier error ejecuta rollBack() y devuelve el mensaje de la excepción.

Si falla cualquiera de los tres pasos, ningún cambio queda guardado en la base de datos.

eliminar($id_venta) — usa transacción

Abre transacción.Borra los registros de detalle_venta que corresponden a esa venta, ya que tienen clave foránea hacia venta.Borra el encabezado en la tabla venta.commit() o rollBack() según el resultado.

El orden importa: si se eliminara el encabezado primero, la clave foránea impediría borrar los detalles.

Controlador — controllers/VentaController.php

Al iniciar verifica que exista $_SESSION['usuario']; si no hay sesión redirige al login. Luego lee la acción desde $_GET['accion'] y entra al switch correspondiente.

accion=registrar — POST

Lee id_cliente y los arreglos id_producto[], cantidad[], subtotal[] del $_POST.Valida que el cliente y al menos un producto estén presentes. Si no, guarda alerta de advertencia y redirige.Construye el arreglo $items filtrando entradas con cantidad igual a 0 o producto vacío.Consulta el stock de cada producto directamente en el controlador. Si alguno no tiene suficiente, bloquea la venta con un mensaje de error que indica el nombre del producto y el stock disponible.Llama a $ventaModel->registrar() y guarda el mensaje de éxito o error en sesión.Redirige a views/venta/index.php.

accion=eliminar — GET
Valida que el ID sea mayor a 0. Llama a $ventaModel->eliminar(). Guarda el mensaje de resultado en sesión y redirige a la lista.

accion=detalle — responde JSON
Llama a $ventaModel->obtenerDetalle($id_venta) y responde directamente con json_encode() sin redirigir. Este endpoint es consumido por el fetch() del JavaScript en la vista para poblar el modal de detalle.

Acción no reconocida
Si llega cualquier otra acción (o ninguna), redirige a views/venta/index.php.