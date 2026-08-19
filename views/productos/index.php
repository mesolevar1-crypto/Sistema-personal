<?php
// ============================================================
// Vista: Gestión de Productos
// Acceso: Administrador y Vendedor
// Función: Muestra el catálogo de productos en formato de
//          tarjetas visuales con color por categoría.
//          Permite crear, editar y eliminar productos.
// Controlador destino: controllers/ProductoController.php
// ============================================================
session_start();

// Verificar que el usuario tenga sesión activa
if (!isset($_SESSION["usuario"])) {
    header("Location: ../usuarios/login.php");
    exit;
}

// Cargar la conexión a la base de datos y el modelo de productos
require_once __DIR__ . '/../../config/databse.php';
require_once __DIR__ . '/../../models/producto.php';

// Instanciar la conexión y obtener todos los productos y categorías
$database      = new Database();
$db            = $database->conectar();
$productoModel = new Producto($db);
$productos     = $productoModel->obtenerTodos();    // Lista de productos con stock, precio y categoría
$categorias    = $productoModel->obtenerCategorias(); // Lista de categorías para el select del formulario

// Definir el título de la página
$titulo = "Panel de productos - Administrador";
// Incluir el encabezado HTML global
require_once __DIR__ . '/../layouts/header.php';
// Cargar el sidebar del administrador
require_once __DIR__ . '/../layouts/sidebar.php';

/*
 * Paleta de colores para las tarjetas de productos.
 * Se asigna un color según el id_categoria del producto (módulo del total de colores).
 * Cada entrada tiene: gradiente de fondo, ícono FontAwesome y estilo del badge de categoría.
 */
$paleta = [
    ['bg' => 'from-emerald-400 to-green-500',  'icon' => 'fa-leaf',        'badge' => 'bg-emerald-100 text-emerald-700 border-emerald-200'],
    ['bg' => 'from-sky-400 to-blue-500',        'icon' => 'fa-box-open',    'badge' => 'bg-sky-100 text-sky-700 border-sky-200'],
    ['bg' => 'from-violet-400 to-purple-500',   'icon' => 'fa-tags',        'badge' => 'bg-violet-100 text-violet-700 border-violet-200'],
    ['bg' => 'from-amber-400 to-orange-500',    'icon' => 'fa-star',        'badge' => 'bg-amber-100 text-amber-700 border-amber-200'],
    ['bg' => 'from-rose-400 to-pink-500',       'icon' => 'fa-heart',       'badge' => 'bg-rose-100 text-rose-700 border-rose-200'],
    ['bg' => 'from-teal-400 to-cyan-500',       'icon' => 'fa-cubes',       'badge' => 'bg-teal-100 text-teal-700 border-teal-200'],
    ['bg' => 'from-lime-400 to-green-400',      'icon' => 'fa-seedling',    'badge' => 'bg-lime-100 text-lime-700 border-lime-200'],
    ['bg' => 'from-indigo-400 to-blue-600',     'icon' => 'fa-layer-group', 'badge' => 'bg-indigo-100 text-indigo-700 border-indigo-200'],
];
?>

<style>
    .ventanet-btn-primary {
        background: #00875F; color: #fff; border-radius: 10px;
        box-shadow: 0 4px 12px rgba(0,135,95,.22);
        transition: transform .15s, box-shadow .2s, background .2s;
        font-family: 'Outfit', sans-serif; font-weight: 600;
    }
    .ventanet-btn-primary:hover { background: #01614B; transform: translateY(-2px); box-shadow: 0 6px 18px rgba(0,135,95,.30); }
    .ventanet-btn-danger {
        background: #E53935; color: #fff; border-radius: 10px;
        box-shadow: 0 4px 12px rgba(229,57,53,.22);
        transition: transform .15s, box-shadow .2s, background .2s;
        font-family: 'Outfit', sans-serif; font-weight: 600;
    }
    .ventanet-btn-danger:hover { background: #c62828; transform: translateY(-2px); }
    .ventanet-input {
        background: #fff; border: 1.5px solid #E5E7EB; border-radius: 10px;
        color: #171717; font-family: 'Outfit', sans-serif; font-size: .95rem;
        outline: none; transition: border-color .2s, box-shadow .2s;
    }
    .ventanet-input:focus { border-color: #61D0A7; box-shadow: 0 0 0 4px rgba(97,208,167,.15); }
    @keyframes modalRise {
        from { opacity: 0; transform: translateY(20px) scale(.98); }
        to   { opacity: 1; transform: translateY(0) scale(1); }
    }
    .modal-anim { animation: modalRise .3s cubic-bezier(.22,1,.36,1) forwards; }
    @keyframes cardIn { from { opacity:0; transform:translateY(14px); } to { opacity:1; transform:translateY(0); } }
    .product-card {
        background: #fff; border: 1.5px solid #E5E7EB; border-radius: 20px;
        overflow: hidden; animation: cardIn .4s ease-out both;
        transition: transform .3s cubic-bezier(.22,1,.36,1), box-shadow .3s, border-color .3s;
    }
    .product-card:hover { transform: translateY(-8px); box-shadow: 0 16px 32px rgba(0,0,0,.10); border-color: #00875F; }
    .card-header { height: 130px; display: flex; align-items: center; justify-content: center; position: relative; }
    .card-header-icon { font-size: 3.5rem; color: rgba(255,255,255,.85); filter: drop-shadow(0 2px 6px rgba(0,0,0,.15)); }
</style>

<div class="font-sans-ventanet text-[#1C2E1A] max-w-7xl mx-auto">

    <!-- ── Encabezado de la página con título y botón de acción ── -->
    <div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
        <div>
            <h2 class="text-3xl font-bold font-serif-ventanet" style="color:#01614B;">Gestionar Productos</h2>
            <p class="text-sm mt-1" style="color:#5F6673;">Administra tu catálogo de productos</p>
        </div>
        <!-- Botón que abre el modal de creación de producto -->
        <button onclick="openModal('modalCrear')" class="ventanet-btn-primary px-5 py-2.5 flex items-center gap-2 self-start md:self-auto">
            <i class="fas fa-plus-circle"></i> Nuevo Producto
        </button>
    </div>

    <!-- SweetAlert -->
    <?php if (isset($_SESSION['alert'])): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            Swal.fire({
                icon: '<?= htmlspecialchars($_SESSION['alert']['icon']) ?>',
                title: '<?= htmlspecialchars($_SESSION['alert']['title']) ?>',
                text: '<?= htmlspecialchars($_SESSION['alert']['text']) ?>',
                confirmButtonColor: '#00875F',
                customClass: { popup: 'rounded-[20px]', confirmButton: 'rounded-lg px-6 py-2.5 font-semibold' }
            });
        });
    </script>
    <?php unset($_SESSION['alert']); endif; ?>

    <!-- ── Buscador en tiempo real de productos por nombre o categoría ── -->
    <!-- HU-3: Buscador de productos en tiempo real -->
    <div class="relative mb-6">
        <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-[#96B092] text-sm"></i>
        <input type="text" id="buscadorProductos" placeholder="Buscar producto por nombre o categoría..."
            class="ventanet-input w-full pl-11 pr-4 py-2.5">
        <span id="contadorResultados" class="absolute right-4 top-1/2 -translate-y-1/2 text-xs text-[#96B092] font-medium"></span>
    </div>

    <!-- ── Grid de tarjetas de productos ── -->
    <!-- Cada tarjeta muestra: nombre, categoría, stock, precio y botones de acción -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6" id="gridProductos">

        <?php if (!empty($productos)): ?>
            <?php foreach ($productos as $i => $p):
                // Seleccionar color de la paleta según la categoría del producto
                $colorIdx = !empty($p['id_categoria']) ? (($p['id_categoria'] - 1) % count($paleta)) : ($i % count($paleta));
                $color    = $paleta[$colorIdx];
            ?>
            <div class="product-card" style="animation-delay:<?= $i * .06 ?>s"
                 data-nombre="<?= strtolower(htmlspecialchars($p['nombre'])) ?>"
                 data-categoria="<?= strtolower(htmlspecialchars($p['categoria'] ?? '')) ?>">

                <!-- Cuerpo -->
                <div class="p-5">

                    <!-- Imagen del producto -->
                    <?php if (!empty($p['imagen'])): ?>
                        <div class="mb-3 rounded-xl overflow-hidden h-36 bg-[#f0f9ee]">
                            <img src="../../<?= htmlspecialchars($p['imagen']) ?>"
                                 alt="<?= htmlspecialchars($p['nombre']) ?>"
                                 class="w-full h-full object-cover">
                        </div>
                    <?php else: ?>
                        <div class="mb-3 rounded-xl h-36 bg-gradient-to-br <?= $color['bg'] ?> flex items-center justify-center">
                            <i class="fas <?= $color['icon'] ?> text-5xl text-white/80"></i>
                        </div>
                    <?php endif; ?>

                    <h3 class="text-[1rem] font-bold text-[#1C2E1A] mb-1 truncate" title="<?= htmlspecialchars($p['nombre']) ?>">
                        <?= htmlspecialchars($p['nombre']) ?>
                    </h3>

                    <!-- Categoría -->
                    <?php if (!empty($p['categoria'])): ?>
                        <span class="inline-block px-2.5 py-1 rounded-full text-[0.7rem] font-bold border <?= $color['badge'] ?> mb-3">
                            <?= htmlspecialchars($p['categoria']) ?>
                        </span>
                    <?php endif; ?>

                    <!-- Stock -->
                    <p class="text-xs text-[#4E6B4A] mb-3">
                        <i class="fas fa-cubes text-[#96B092] mr-1"></i>
                        Stock: <span class="font-semibold"><?= intval($p['stock'] ?? 0) ?> uds.</span>
                    </p>

                    <!-- Precio -->
                    <div class="flex items-end justify-between mb-4">
                        <span class="text-xs text-[#4E6B4A] font-medium uppercase tracking-wide">Precio:</span>
                        <span class="text-2xl font-extrabold text-[#4A8C44] leading-none">
                            $<?= number_format($p['precio'] ?? 0, 0, ',', '.') ?>
                        </span>
                    </div>

                    <!-- Acciones -->
                    <div class="flex items-center gap-2 pt-3 border-t border-[#C9E4C5]">
                        <button type="button" onclick="openEditModal(<?= htmlspecialchars(json_encode($p)) ?>)"
                            class="flex-1 px-3 py-2 rounded-xl border border-[#C9E4C5] bg-white text-[#4A8C44]
                                   hover:bg-[#DFF0DC] hover:border-[#4A8C44] transition-all flex items-center
                                   justify-center gap-2 font-semibold text-sm">
                            <i class="fas fa-pen text-xs"></i> Editar
                        </button>
                        <button type="button"
                            onclick="openDeleteModal(<?= $p['id_producto'] ?>, '<?= htmlspecialchars(addslashes($p['nombre'])) ?>')"
                            class="px-3 py-2 rounded-xl border border-red-200 bg-white text-red-400
                                   hover:bg-red-50 hover:text-red-600 hover:border-red-400 transition-all flex items-center justify-center"
                            title="Eliminar">
                            <i class="fas fa-trash text-sm"></i>
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

        <?php else: ?>
            <div class="col-span-full">
                <div class="bg-white rounded-[20px] border-2 border-dashed border-[#C9E4C5] p-16 text-center">
                    <div class="w-24 h-24 mx-auto bg-[#e4f5e2] rounded-full flex items-center justify-center text-[#96B092] text-4xl mb-6 border-4 border-white shadow-lg">
                        <i class="fas fa-box-open"></i>
                    </div>
                    <h3 class="text-2xl font-serif-ventanet text-[#1C2E1A] mb-2">No hay productos registrados</h3>
                    <p class="text-[#4E6B4A] font-medium mb-6">Comienza agregando tu primer producto al catálogo</p>
                    <button onclick="openModal('modalCrear')" class="ventanet-btn-primary px-6 py-3 inline-flex items-center gap-2">
                        <i class="fas fa-plus-circle"></i> Agregar Producto
                    </button>
                </div>
            </div>
        <?php endif; ?>

    </div>
</div>

<!-- ── Modal Crear Producto ──
     Formulario para registrar un nuevo producto en el catálogo.
     Envía datos a: controllers/ProductoController.php?accion=crear (POST)
     Campos: nombre, precio, stock, categoría -->
<!-- ================================================================ MODAL CREAR ================================================================ -->
<div id="modalCrear" class="fixed inset-0 bg-[#1C2E1A]/40 backdrop-blur-sm hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-[20px] w-full max-w-lg shadow-2xl modal-anim overflow-hidden">

        <div class="px-8 py-5 border-b border-[#E5E7EB] flex justify-between items-center bg-[#F8F8F8]">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-[#00875F] text-white flex items-center justify-center shadow-md">
                    <i class="fas fa-plus-circle"></i>
                </div>
                <h3 class="text-xl font-serif-ventanet text-[#171717] mt-1">Agregar Producto</h3>
            </div>
            <button onclick="closeModal('modalCrear')" class="text-[#9CA3AF] hover:text-[#00875F] transition-colors">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <form action="../../controllers/ProductoController.php?accion=crear" method="POST" enctype="multipart/form-data" class="p-8 space-y-5">

            <div>
                <label class="block text-[.85rem] font-bold text-[#1C2E1A] mb-1.5">Nombre del Producto *</label>
                <input type="text" name="nombre" required class="ventanet-input w-full px-4 py-2.5" placeholder="Ej. Manzana Roja">
            </div>

            <div class="grid grid-cols-3 gap-5">
                <div>
                    <label class="block text-[.85rem] font-bold text-[#1C2E1A] mb-1.5">Precio *</label>
                    <input type="number" name="precio" required min="0" step="0.01" class="ventanet-input w-full px-4 py-2.5" placeholder="0">
                </div>
                <div>
                    <label class="block text-[.85rem] font-bold text-[#1C2E1A] mb-1.5">Stock *</label>
                    <input type="number" name="stock" required min="0" class="ventanet-input w-full px-4 py-2.5" placeholder="0">
                </div>
                <div class="relative">
                    <label class="block text-[.85rem] font-bold text-[#1C2E1A] mb-1.5">Categoría</label>
                    <select name="id_categoria" class="ventanet-input w-full px-4 py-2.5 cursor-pointer appearance-none">
                        <option value="">Seleccione</option>
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?= $cat['id_categoria'] ?>"><?= htmlspecialchars($cat['tipo']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 pt-6 text-[#4A8C44]">
                        <i class="fas fa-chevron-down text-sm"></i>
                    </div>
                </div>
            </div>

            <!-- Campo de imagen -->
            <div>
                <label class="block text-[.85rem] font-bold text-[#1C2E1A] mb-1.5">Imagen del Producto</label>
                <div id="dropZonaCrear" class="border-2 border-dashed border-[#C9E4C5] rounded-xl p-4 text-center cursor-pointer hover:border-[#4A8C44] hover:bg-[#f2fcf1] transition-all"
                     onclick="document.getElementById('imagenCrear').click()">
                    <img id="previewCrear" src="" alt="" class="hidden mx-auto mb-2 max-h-32 rounded-lg object-cover">
                    <div id="placeholderCrear">
                        <i class="fas fa-cloud-upload-alt text-3xl text-[#96B092] mb-2"></i>
                        <p class="text-sm text-[#4E6B4A] font-medium">Haz clic para subir una imagen</p>
                        <p class="text-xs text-[#96B092] mt-1">JPG, PNG, GIF, WEBP — máx. 2MB</p>
                    </div>
                    <input type="file" id="imagenCrear" name="imagen" accept="image/*" class="hidden"
                           onchange="previewImagen(this, 'previewCrear', 'placeholderCrear')">
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-5 border-t border-[#C9E4C5]">
                <button type="button" onclick="closeModal('modalCrear')" class="px-6 py-2 text-[#4E6B4A] font-semibold bg-white border border-[#C9E4C5] hover:bg-[#F4FBF3] rounded-xl transition-colors">
                    Cancelar
                </button>
                <button type="submit" class="ventanet-btn-primary px-8 py-2">Guardar Producto</button>
            </div>
        </form>
    </div>
</div>

<!-- ── Modal Editar Producto ──
     Formulario para modificar datos de un producto existente.
     Los datos se precargan con JavaScript (openEditModal).
     Envía datos a: controllers/ProductoController.php?accion=editar (POST) -->
<!-- ================================================================ MODAL EDITAR ================================================================ -->
<div id="modalEditar" class="fixed inset-0 bg-[#1C2E1A]/40 backdrop-blur-sm hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-[20px] w-full max-w-lg shadow-2xl modal-anim overflow-hidden">

        <div class="px-8 py-5 border-b border-[#E5E7EB] flex justify-between items-center bg-[#F8F8F8]">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-[#00875F] text-white flex items-center justify-center shadow-md">
                    <i class="fas fa-edit"></i>
                </div>
                <h3 class="text-xl font-serif-ventanet text-[#171717] mt-1">Editar Producto</h3>
            </div>
            <button onclick="closeModal('modalEditar')" class="text-[#9CA3AF] hover:text-[#00875F] transition-colors">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <form action="../../controllers/ProductoController.php?accion=editar" method="POST" enctype="multipart/form-data" class="p-8 space-y-5">
            <input type="hidden" name="id_producto" id="edit_id_producto">
            <input type="hidden" name="imagen_actual" id="edit_imagen_actual">

            <div>
                <label class="block text-[.85rem] font-bold text-[#1C2E1A] mb-1.5">Nombre del Producto *</label>
                <input type="text" name="nombre" id="edit_nombre" required class="ventanet-input w-full px-4 py-2.5">
            </div>

            <div class="grid grid-cols-3 gap-5">
                <div>
                    <label class="block text-[.85rem] font-bold text-[#1C2E1A] mb-1.5">Precio *</label>
                    <input type="number" name="precio" id="edit_precio" required min="0" step="0.01" class="ventanet-input w-full px-4 py-2.5">
                </div>
                <div>
                    <label class="block text-[.85rem] font-bold text-[#1C2E1A] mb-1.5">Stock *</label>
                    <input type="number" name="stock" id="edit_stock" required min="0" class="ventanet-input w-full px-4 py-2.5">
                </div>
                <div class="relative">
                    <label class="block text-[.85rem] font-bold text-[#1C2E1A] mb-1.5">Categoría</label>
                    <select name="id_categoria" id="edit_id_categoria" class="ventanet-input w-full px-4 py-2.5 cursor-pointer appearance-none">
                        <option value="">Seleccione </option>
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?= $cat['id_categoria'] ?>"><?= htmlspecialchars($cat['tipo']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 pt-6 text-[#4A8C44]">
                        <i class="fas fa-chevron-down text-sm"></i>
                    </div>
                </div>
            </div>

            <!-- Campo de imagen al editar -->
            <div>
                <label class="block text-[.85rem] font-bold text-[#1C2E1A] mb-1.5">
                    Imagen del Producto
                    <span class="font-normal text-[#96B092] text-xs ml-1">(deja vacío para mantener la actual)</span>
                </label>
                <div id="dropZonaEditar" class="border-2 border-dashed border-[#C9E4C5] rounded-xl p-4 text-center cursor-pointer hover:border-[#4A8C44] hover:bg-[#f2fcf1] transition-all"
                     onclick="document.getElementById('imagenEditar').click()">
                    <img id="previewEditar" src="" alt="" class="hidden mx-auto mb-2 max-h-32 rounded-lg object-cover">
                    <div id="placeholderEditar">
                        <i class="fas fa-cloud-upload-alt text-3xl text-[#96B092] mb-2"></i>
                        <p class="text-sm text-[#4E6B4A] font-medium">Haz clic para cambiar la imagen</p>
                        <p class="text-xs text-[#96B092] mt-1">JPG, PNG, GIF, WEBP — máx. 2MB</p>
                    </div>
                    <input type="file" id="imagenEditar" name="imagen" accept="image/*" class="hidden"
                           onchange="previewImagen(this, 'previewEditar', 'placeholderEditar')">
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-5 border-t border-[#C9E4C5]">
                <button type="button" onclick="closeModal('modalEditar')" class="px-6 py-2 text-[#4E6B4A] font-semibold bg-white border border-[#C9E4C5] hover:bg-[#F4FBF3] rounded-xl transition-colors">
                    Cancelar
                </button>
                <button type="submit" class="ventanet-btn-primary px-8 py-2">Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>

<!-- ── Modal Eliminar Producto ──
     Confirmación antes de eliminar permanentemente un producto.
     El enlace apunta a: controllers/ProductoController.php?accion=eliminar&id=X -->
<!-- ================================================================ MODAL ELIMINAR ================================================================ -->
<div id="modalEliminar" class="fixed inset-0 bg-[#1C2E1A]/60 backdrop-blur-sm hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-[20px] w-full max-w-md shadow-2xl modal-anim overflow-hidden">
        <div class="p-8 text-center">
            <div class="w-20 h-20 mx-auto bg-red-50 rounded-full flex items-center justify-center mb-5 border-4 border-white shadow-md">
                <i class="fas fa-exclamation-triangle text-3xl text-red-500"></i>
            </div>
            <h3 class="text-2xl font-serif-ventanet text-[#1C2E1A] mb-2">Eliminar Producto</h3>
            <p class="text-[#4E6B4A] mb-1">¿Estás seguro de eliminar:</p>
            <p class="text-[#1C2E1A] font-bold text-lg" id="delete_nombre"></p>
        </div>
        <div class="px-6 py-5 bg-[#F8F8F8] border-t border-[#E5E7EB] flex justify-center gap-3">
            <button type="button" onclick="closeModal('modalEliminar')" class="px-5 py-2.5 text-[#4E6B4A] font-semibold bg-white border border-[#C9E4C5] hover:bg-[#F4FBF3] rounded-xl transition-colors">
                Cancelar
            </button>
            <a id="delete_link" href="#" class="ventanet-btn-danger px-6 py-2.5 flex items-center">
                Sí, eliminar
            </a>
        </div>
    </div>
</div>

<script>
    /**
     * openModal(id) - Muestra el modal con el id dado
     */
    function openModal(id)  { document.getElementById(id).classList.remove('hidden'); }
    /**
     * closeModal(id) - Oculta el modal con el id dado
     */
    function closeModal(id) { document.getElementById(id).classList.add('hidden'); }

    /**
     * openEditModal(p)
     * Precarga los datos del producto en el formulario de edición y abre el modal.
     * @param {Object} p - Objeto con los datos del producto (viene de json_encode en PHP)
     */
    function openEditModal(p) {
        document.getElementById('edit_id_producto').value  = p.id_producto;
        document.getElementById('edit_nombre').value       = p.nombre       ?? '';
        document.getElementById('edit_precio').value       = p.precio       ?? '';
        document.getElementById('edit_stock').value        = p.stock        ?? '';
        document.getElementById('edit_id_categoria').value = p.id_categoria ?? '';
        document.getElementById('edit_imagen_actual').value = p.imagen      ?? '';

        // Mostrar imagen actual si existe
        var preview = document.getElementById('previewEditar');
        var placeholder = document.getElementById('placeholderEditar');
        if (p.imagen) {
            preview.src = '../../' + p.imagen;
            preview.classList.remove('hidden');
            placeholder.classList.add('hidden');
        } else {
            preview.src = '';
            preview.classList.add('hidden');
            placeholder.classList.remove('hidden');
        }
        // Limpiar el input de archivo
        document.getElementById('imagenEditar').value = '';
        openModal('modalEditar');
    }

    // Preview de imagen antes de subir
    function previewImagen(input, previewId, placeholderId) {
        var preview = document.getElementById(previewId);
        var placeholder = document.getElementById(placeholderId);
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.classList.remove('hidden');
                placeholder.classList.add('hidden');
            };
            reader.readAsDataURL(input.files[0]);
        }
    }

    /**
     * openDeleteModal(id, nombre)
     * Muestra el nombre del producto a eliminar y configura el enlace de confirmación.
     * @param {number} id     - ID del producto a eliminar
     * @param {string} nombre - Nombre del producto para mostrar en el modal
     */
    function openDeleteModal(id, nombre) {
        document.getElementById('delete_nombre').textContent = nombre;
        document.getElementById('delete_link').href = '../../controllers/ProductoController.php?accion=eliminar&id=' + id;
        openModal('modalEliminar');
    }

    // HU-3: Buscador en tiempo real — filtra tarjetas por nombre o categoría
    var buscador = document.getElementById('buscadorProductos');
    if (buscador) {
        buscador.addEventListener('input', function () {
            var q       = this.value.toLowerCase().trim();
            var tarjetas = document.querySelectorAll('#gridProductos .product-card');
            var visibles = 0;
            tarjetas.forEach(function (card) {
                var nombre    = card.dataset.nombre    || '';
                var categoria = card.dataset.categoria || '';
                // Mostrar la tarjeta si el nombre o la categoría coinciden con la búsqueda
                var coincide  = nombre.includes(q) || categoria.includes(q);
                card.style.display = coincide ? '' : 'none';
                if (coincide) visibles++;
            });
            // Actualizar el contador de resultados visibles
            var contador = document.getElementById('contadorResultados');
            if (contador) {
                contador.textContent = q ? visibles + ' resultado' + (visibles !== 1 ? 's' : '') : '';
            }
        });
    }
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
