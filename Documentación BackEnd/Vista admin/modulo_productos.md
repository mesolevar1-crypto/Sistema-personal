Documentación — Módulo de Gestión de Productos

Archivos del módulo

ArchivoRolviews/productos/index.phpVista principal (administrador y vendedor)models/producto.phpClase Producto, toda la lógica de base de datoscontrollers/ProductoController.phpRecibe acciones y conecta vista con modelo

Tablas involucradas: producto, categoria

Vista — views/productos/index.php

Protección de acceso
Al cargar la página se verifica que exista $_SESSION['usuario']. Si no hay sesión activa, redirige al login. A diferencia del módulo de ventas, esta vista es accesible tanto para el administrador como para el vendedor; no hay redirección por rol.

Paleta de colores por categoría
Se define un arreglo $paleta con 8 combinaciones de gradiente, ícono FontAwesome y estilo de badge. El índice de color se calcula como (id_categoria - 1) % 8, de modo que cada categoría siempre recibe el mismo color. Si el producto no tiene categoría, se usa el índice de iteración del foreach como alternativa.

Grid de tarjetas
Se usa un foreach sobre $productos para renderizar una tarjeta por cada producto. Cada tarjeta muestra nombre, badge de categoría (si existe), stock en unidades y precio formateado. Si el arreglo está vacío se muestra un estado vacío con un botón directo para agregar el primer producto. Cada tarjeta tiene dos botones: editar y eliminar.

Buscador en tiempo real (HU-3)
Un campo de texto con id buscadorProductos escucha el evento input. Por cada pulsación recorre todas las tarjetas del grid y compara la búsqueda contra los atributos data-nombre y data-categoria de cada tarjeta. Las que no coinciden se ocultan con display:none. Un contador en el extremo derecho del input muestra cuántos resultados son visibles.

Modal — nuevo producto
Se abre con openModal('modalCrear'). El formulario envía por POST a ProductoController.php?accion=crear. Campos: nombre (obligatorio), precio (obligatorio), stock y categoría (opcional, se puebla con obtenerCategorias()). Cancelar simplemente cierra el modal sin enviar datos.

Modal — editar producto
openEditModal(p) recibe el objeto del producto serializado con json_encode desde PHP y precarga todos los campos del formulario de edición: id_producto (campo oculto), nombre, precio, stock e id_categoria. El formulario envía por POST a ProductoController.php?accion=editar.

Modal — eliminar producto
openDeleteModal(id, nombre) escribe el nombre del producto en el modal de confirmación y construye el enlace de confirmación con el ID. Si el administrador confirma, navega a ProductoController.php?accion=eliminar&id=X mediante GET.

Alertas de resultado
Después de cada acción el controlador guarda un mensaje en $_SESSION['alert']. Al volver a la vista, si existe esa clave se lanza un Swal.fire() con ícono, título y texto. Luego se borra con unset para que no reaparezca al recargar la página.

Modelo — models/producto.php

obtenerTodos()
Trae todos los productos con el nombre de su categoría. Hace un LEFT JOIN entre producto y categoria para incluir también productos sin categoría asignada. Ordena alfabéticamente por nombre de producto.

obtenerPorId($id_producto)
Devuelve todos los campos de un producto específico junto con el nombre de su categoría. Usa LEFT JOIN con categoria y filtra por id_producto. Retorna un solo registro o false si no existe.

obtenerCategorias()
Trae el id_categoria y el tipo de todas las categorías para poblar el select de los formularios. Ordena alfabéticamente por nombre de categoría.

registrar($datos)
Inserta un nuevo producto en la tabla producto con nombre, stock, precio e id_categoria. El campo id_categoria puede llegar como null si el usuario no seleccionó categoría. Devuelve true si tuvo éxito o el mensaje de la excepción si falló.

editar($id_producto, $datos)
Actualiza los cuatro campos editables (nombre, stock, precio, id_categoria) del producto identificado por su ID. El campo id_categoria también puede ser null. Devuelve true o el mensaje de error.

eliminar($id_producto)
Borra el registro de la tabla producto usando su ID como filtro. Devuelve true o el mensaje de la excepción. No usa transacción porque es una sola operación sobre una sola tabla.

existeNombre($nombre, $excluir_id = null)
Verifica si ya existe otro producto con el mismo nombre para evitar duplicados. Recibe un segundo parámetro opcional: si se proporciona, excluye ese ID de la búsqueda, lo que permite que un producto se edite sin que su propio nombre sea detectado como duplicado. Devuelve true si el nombre ya está en uso, false si está disponible.

Controlador — controllers/ProductoController.php

Al iniciar verifica que exista $_SESSION['usuario']; si no hay sesión redirige al login. Luego lee la acción desde $_GET['accion'] y entra al switch correspondiente.

accion=crear — POST
Lee nombre, stock, precio e id_categoria del $_POST. Valida que nombre y precio no estén vacíos (HU-8); si faltan, guarda alerta de advertencia y redirige. Llama a existeNombre($nombre) para verificar duplicados (HU-4); si el nombre ya existe, guarda alerta y redirige. Si pasa ambas validaciones, llama a $productoModel->registrar() y guarda el mensaje de éxito o error en sesión. Redirige a views/productos/index.php.

accion=editar — POST
Lee id_producto además de los mismos campos del crear. Valida que nombre, precio e ID sean válidos (HU-8). Llama a existeNombre($nombre, $id_producto) excluyendo el propio producto de la verificación de duplicados (HU-4). Si pasa las validaciones, llama a $productoModel->editar() y guarda el resultado en sesión. Redirige a la lista.

accion=eliminar — GET
Lee el ID desde $_GET['id'] y valida que sea mayor a 0. Llama a $productoModel->eliminar(). Guarda el mensaje de resultado en sesión y redirige a la lista.

Acción no reconocida
Si llega cualquier otra acción (o ninguna), redirige a views/productos/index.php.