<?php
// ============================================================
// Vista: Gestión de Proveedores
// Acceso: Solo Administrador
// ============================================================
session_start();

if (!isset($_SESSION['usuario'])) {
    header("Location: ../usuarios/login.php");
    exit;
}

require_once __DIR__ . '/../../config/databse.php';
require_once __DIR__ . '/../../models/proveedor.php';

$database       = new Database();
$db             = $database->conectar();
$proveedorModel = new Proveedor($db);
$todosProveedores = $proveedorModel->obtenerTodos();

// ── Paginación: 10 por página ────────────────────────────────
$porPagina        = 10;
$totalProveedores = count($todosProveedores);
$totalPaginas     = (int) ceil($totalProveedores / $porPagina);
$paginaActual     = max(1, min((int)($_GET['pagina'] ?? 1), max(1, $totalPaginas)));
$offset           = ($paginaActual - 1) * $porPagina;
$proveedores      = array_slice($todosProveedores, $offset, $porPagina);

$titulo = "Panel de proveedores - Administrador";
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';
?>

<style>
    /* ── Botones ── */
    .btn-primario {
        background: #00875F; color: #fff;
        border-radius: 10px; border: none; font-weight: 600;
        font-family: 'Outfit', sans-serif; cursor: pointer;
        transition: background .18s, transform .15s, box-shadow .18s;
        box-shadow: 0 4px 12px rgba(0,135,95,.22);
        display: inline-flex; align-items: center; gap: 6px;
    }
    .btn-primario:hover { background: #01614B; transform: translateY(-2px); box-shadow: 0 6px 18px rgba(0,135,95,.30); }

    .btn-peligro {
        background: #E53935; color: #fff;
        border-radius: 10px; border: none; font-weight: 600;
        font-family: 'Outfit', sans-serif; cursor: pointer;
        transition: background .18s, transform .15s;
        box-shadow: 0 4px 12px rgba(229,57,53,.22);
        display: inline-flex; align-items: center; gap: 6px;
    }
    .btn-peligro:hover { background: #c62828; transform: translateY(-2px); }

    /* ── Inputs de los modales ── */
    .campo-input {
        width: 100%; background: #fff;
        border: 1.5px solid #E5E7EB; border-radius: 10px;
        color: #171717; font-family: 'Outfit', sans-serif;
        font-size: 0.95rem; outline: none;
        transition: border-color .2s, box-shadow .2s;
        padding: 10px 14px;
    }
    .campo-input:focus { border-color: #61D0A7; box-shadow: 0 0 0 4px rgba(97,208,167,.15); }
    .campo-input[readonly] { background: #F8F8F8; color: #5F6673; cursor: not-allowed; }

    /* ── Animación modal ── */
    @keyframes modalRise {
        from { opacity: 0; transform: translateY(18px) scale(0.98); }
        to   { opacity: 1; transform: translateY(0)    scale(1); }
    }
    .modal-anim { animation: modalRise .28s cubic-bezier(0.22,1,0.36,1) forwards; }

    /* ── Paginación ── */
    .pag-btn {
        padding: 7px 13px; border-radius: 8px;
        border: 1px solid #E5E7EB; background: #fff;
        color: #5F6673; font-size: 0.85rem; font-weight: 600;
        text-decoration: none;
        transition: background .15s, border-color .15s, color .15s;
    }
    .pag-btn:hover       { background: #DDF5EC; border-color: #61D0A7; color: #01614B; }
    .pag-btn.activa      { background: #00875F; border-color: #00875F; color: #fff; }
    .pag-btn.deshabilitado { opacity: .4; pointer-events: none; }
</style>

<!-- ════════════════════════════════════════════
     CONTENIDO PRINCIPAL
════════════════════════════════════════════ -->
<div class="max-w-7xl mx-auto font-sans-ventanet">

    <!-- Encabezado + botón -->
    <div class="flex flex-col md:flex-row md:items-center justify-between mb-6 gap-4">
        <div>
            <h2 class="text-3xl font-bold font-serif-ventanet" style="color:#01614B;">
                Gestionar Proveedores
            </h2>
            <p class="text-sm mt-1" style="color:#5F6673;">
                Administra los proveedores de tu negocio
            </p>
        </div>
        <button onclick="openModal('modalCrear')" class="btn-primario px-5 py-2.5">
            <i class="fas fa-truck"></i> Nuevo Proveedor
        </button>
    </div>

    <!-- Alerta de sesión -->
    <?php if (isset($_SESSION['alert'])): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon:  '<?= htmlspecialchars($_SESSION['alert']['icon'])  ?>',
                title: <?= json_encode($_SESSION['alert']['title']) ?>,
                text:  <?= json_encode($_SESSION['alert']['text'])  ?>,
                confirmButtonText:  'Entendido',
                confirmButtonColor: '#00875F',
                customClass: { popup: 'rounded-[20px]', confirmButton: 'rounded-lg px-6 py-2.5 font-semibold' }
            });
        });
    </script>
    <?php unset($_SESSION['alert']); endif; ?>

    <!-- ── Tabla ── -->
    <div class="bg-white rounded-2xl border overflow-hidden" style="border-color:#E5E7EB;">

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr style="background:#01614B;">
                        <th class="px-5 py-3 text-xs font-bold uppercase tracking-wide" style="color:#fff;">Nombre / Empresa</th>
                        <th class="px-5 py-3 text-xs font-bold uppercase tracking-wide" style="color:#fff;">Correo</th>
                        <th class="px-5 py-3 text-xs font-bold uppercase tracking-wide" style="color:#fff;">Teléfono</th>
                        <th class="px-5 py-3 text-xs font-bold uppercase tracking-wide" style="color:#fff;">Frecuencia Entrega</th>
                        <th class="px-5 py-3 text-xs font-bold uppercase tracking-wide text-center" style="color:#fff;">Estado</th>
                        <th class="px-5 py-3 text-xs font-bold uppercase tracking-wide text-center" style="color:#fff;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($proveedores as $p):
                        $isActivo = !isset($p['estado']) || $p['estado'] == 'activo';
                        $bgFila   = $isActivo ? '#fff'    : '#FDECEC';
                        $bgHover  = $isActivo ? '#F8F8F8' : '#fde0e0';
                    ?>
                    <tr style="border-bottom:1px solid #E5E7EB; background:<?= $bgFila ?>; transition:background .15s;"
                        onmouseover="this.style.background='<?= $bgHover ?>'"
                        onmouseout="this.style.background='<?= $bgFila ?>'">

                        <!-- Nombre -->
                        <td class="px-5 py-3.5 font-bold text-sm" style="color:#171717;">
                            <?= htmlspecialchars($p['nombre']) ?>
                        </td>

                        <!-- Correo -->
                        <td class="px-5 py-3.5 text-sm" style="color:#5F6673;">
                            <?php if (!empty($p['correo'])): ?>
                                <?= htmlspecialchars($p['correo']) ?>
                            <?php else: ?>
                                <span style="color:#9CA3AF; font-style:italic;">Sin correo</span>
                            <?php endif; ?>
                        </td>

                        <!-- Teléfono -->
                        <td class="px-5 py-3.5 text-sm" style="color:#5F6673;">
                            <?php if (!empty($p['telefono'])): ?>
                                <?= htmlspecialchars($p['telefono']) ?>
                            <?php else: ?>
                                <span style="color:#9CA3AF; font-style:italic;">Sin teléfono</span>
                            <?php endif; ?>
                        </td>

                        <!-- Frecuencia -->
                        <td class="px-5 py-3.5 text-sm" style="color:#5F6673;">
                            <?php if (!empty($p['frecuencia_entrega'])): ?>
                                <?= htmlspecialchars($p['frecuencia_entrega']) ?>
                            <?php else: ?>
                                <span style="color:#9CA3AF; font-style:italic;">Sin frecuencia</span>
                            <?php endif; ?>
                        </td>

                        <!-- Estado -->
                        <td class="px-5 py-3.5 text-center">
                            <?php if ($isActivo): ?>
                                <span style="background:#DDF5EC; color:#00875F; border:1px solid #61D0A7; padding:3px 12px; border-radius:999px; font-size:.75rem; font-weight:700; display:inline-block;">
                                    Activo
                                </span>
                            <?php else: ?>
                                <span style="background:#fde8e8; color:#E53935; border:1px solid #E53935; padding:3px 12px; border-radius:999px; font-size:.75rem; font-weight:700; display:inline-block;">
                                    Inactivo
                                </span>
                            <?php endif; ?>
                        </td>

                        <!-- Acciones -->
                        <td class="px-5 py-3.5">
                            <div style="display:flex; align-items:center; justify-content:center; gap:8px;">

                                <!-- Editar -->
                                <button type="button"
                                    onclick="openEditModal(<?= htmlspecialchars(json_encode($p)) ?>)"
                                    title="Editar"
                                    style="width:32px; height:32px; border-radius:8px; border:1px solid #E5E7EB; background:#fff; color:#00875F; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:all .15s;"
                                    onmouseover="this.style.background='#DDF5EC'; this.style.borderColor='#61D0A7';"
                                    onmouseout="this.style.background='#fff'; this.style.borderColor='#E5E7EB';">
                                    <i class="fas fa-pen" style="font-size:.75rem;"></i>
                                </button>

                                <!-- Activar / Desactivar -->
                                <button type="button"
                                    onclick="window.location.href='../../controllers/ProveedorController.php?accion=toggleEstado&id=<?= $p['id_proveedor'] ?>&estado=<?= $p['estado'] ?? 'activo' ?>'"
                                    title="<?= $isActivo ? 'Desactivar' : 'Activar' ?>"
                                    style="width:32px; height:32px; border-radius:8px; border:1px solid <?= $isActivo ? '#FFB51B' : '#E5E7EB' ?>; background:#fff; color:<?= $isActivo ? '#FFB51B' : '#00875F' ?>; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:all .15s;"
                                    onmouseover="this.style.opacity='.75';"
                                    onmouseout="this.style.opacity='1';">
                                    <i class="fas <?= $isActivo ? 'fa-ban' : 'fa-check' ?>" style="font-size:.75rem;"></i>
                                </button>

                                <!-- Eliminar -->
                                <button type="button"
                                    onclick="openDeleteModal(<?= $p['id_proveedor'] ?>, '<?= htmlspecialchars(addslashes($p['nombre'])) ?>')"
                                    title="Eliminar"
                                    style="width:32px; height:32px; border-radius:8px; border:1px solid #fde8e8; background:#fff; color:#E53935; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:all .15s;"
                                    onmouseover="this.style.background='#fde8e8'; this.style.borderColor='#E53935';"
                                    onmouseout="this.style.background='#fff'; this.style.borderColor='#fde8e8';">
                                    <i class="fas fa-trash" style="font-size:.75rem;"></i>
                                </button>

                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>

                    <?php if (empty($proveedores)): ?>
                    <tr>
                        <td colspan="6" style="padding:48px; text-align:center;">
                            <div style="width:56px; height:56px; background:#F8F8F8; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 12px; border:1px solid #E5E7EB;">
                                <i class="fas fa-truck" style="color:#9CA3AF; font-size:1.3rem;"></i>
                            </div>
                            <p style="color:#5F6673; font-weight:600;">No hay proveedores registrados.</p>
                            <p style="color:#9CA3AF; font-size:.85rem; margin-top:4px;">Comienza agregando tu primer proveedor.</p>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- ── Paginación ── -->
        <?php if ($totalPaginas > 1): ?>
        <div style="padding:16px 20px; border-top:1px solid #E5E7EB; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;">
            <span style="font-size:.82rem; color:#5F6673;">
                Página <strong style="color:#171717;"><?= $paginaActual ?></strong>
                de <strong style="color:#171717;"><?= $totalPaginas ?></strong>
            </span>
            <div style="display:flex; gap:6px; align-items:center; flex-wrap:wrap;">
                <a href="?pagina=<?= $paginaActual - 1 ?>" class="pag-btn <?= $paginaActual <= 1 ? 'deshabilitado' : '' ?>">← Anterior</a>
                <?php for ($p2 = 1; $p2 <= $totalPaginas; $p2++): ?>
                    <a href="?pagina=<?= $p2 ?>" class="pag-btn <?= $p2 === $paginaActual ? 'activa' : '' ?>"><?= $p2 ?></a>
                <?php endfor; ?>
                <a href="?pagina=<?= $paginaActual + 1 ?>" class="pag-btn <?= $paginaActual >= $totalPaginas ? 'deshabilitado' : '' ?>">Siguiente →</a>
            </div>
        </div>
        <?php endif; ?>

    </div><!-- /tabla -->

</div><!-- /max-w-7xl -->


<!-- ════════════════════════════════════════════
     MODALES — lógica sin cambios
════════════════════════════════════════════ -->

<!-- Modal: Crear Proveedor -->
<div id="modalCrear" class="fixed inset-0 hidden z-50 flex items-center justify-center p-4"
     style="background:rgba(0,0,0,.45); backdrop-filter:blur(4px);">
    <div class="bg-white rounded-2xl w-full max-w-2xl shadow-2xl modal-anim overflow-hidden">

        <div style="background:#F8F8F8; border-bottom:1px solid #E5E7EB; padding:18px 24px; display:flex; align-items:center; justify-content:space-between;">
            <div style="display:flex; align-items:center; gap:12px;">
                <div style="width:38px; height:38px; background:#00875F; border-radius:10px; display:flex; align-items:center; justify-content:center;">
                    <i class="fas fa-truck" style="color:#fff; font-size:.9rem;"></i>
                </div>
                <h3 class="font-serif-ventanet" style="font-size:1.1rem; color:#171717;">Agregar Nuevo Proveedor</h3>
            </div>
            <button onclick="closeModal('modalCrear')" style="background:none; border:none; cursor:pointer; color:#9CA3AF; font-size:1.2rem; line-height:1;">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form action="../../controllers/ProveedorController.php?accion=registrar" method="POST" style="padding:24px; display:flex; flex-direction:column; gap:16px;">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label style="display:block; font-size:.83rem; font-weight:700; color:#171717; margin-bottom:5px;">Nombre / Empresa *</label>
                    <input type="text" name="nombre" required class="campo-input" placeholder="Ej. Distribuidora Norte">
                </div>
                <div>
                    <label style="display:block; font-size:.83rem; font-weight:700; color:#171717; margin-bottom:5px;">Teléfono</label>
                    <input type="tel" name="telefono" class="campo-input" placeholder="Ej. 3007081694">
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label style="display:block; font-size:.83rem; font-weight:700; color:#171717; margin-bottom:5px;">Correo Electrónico</label>
                    <input type="email" name="correo" class="campo-input" placeholder="proveedor@correo.com">
                </div>
                <div>
                    <label style="display:block; font-size:.83rem; font-weight:700; color:#171717; margin-bottom:5px;">Frecuencia de Entrega</label>
                    <input type="text" name="frecuencia_entrega" class="campo-input" placeholder="Ej. Semanal, Mensual">
                </div>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:10px; padding-top:16px; border-top:1px solid #E5E7EB; margin-top:4px;">
                <button type="button" onclick="closeModal('modalCrear')"
                    style="padding:9px 20px; border-radius:9px; border:1px solid #E5E7EB; background:#fff; color:#5F6673; font-weight:600; cursor:pointer;">
                    Cancelar
                </button>
                <button type="submit" class="btn-primario" style="padding:9px 24px;">
                    Guardar Proveedor
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Editar Proveedor -->
<div id="modalEditar" class="fixed inset-0 hidden z-50 flex items-center justify-center p-4"
     style="background:rgba(0,0,0,.45); backdrop-filter:blur(4px);">
    <div class="bg-white rounded-2xl w-full max-w-2xl shadow-2xl modal-anim overflow-hidden">

        <div style="background:#F8F8F8; border-bottom:1px solid #E5E7EB; padding:18px 24px; display:flex; align-items:center; justify-content:space-between;">
            <div style="display:flex; align-items:center; gap:12px;">
                <div style="width:38px; height:38px; background:#00875F; border-radius:10px; display:flex; align-items:center; justify-content:center;">
                    <i class="fas fa-truck" style="color:#fff; font-size:.9rem;"></i>
                </div>
                <h3 class="font-serif-ventanet" style="font-size:1.1rem; color:#171717;">Editar Proveedor</h3>
            </div>
            <button onclick="closeModal('modalEditar')" style="background:none; border:none; cursor:pointer; color:#9CA3AF; font-size:1.2rem; line-height:1;">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form action="../../controllers/ProveedorController.php?accion=editar" method="POST" style="padding:24px; display:flex; flex-direction:column; gap:16px;">
            <input type="hidden" name="id_proveedor" id="edit_id_proveedor">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label style="display:block; font-size:.83rem; font-weight:700; color:#171717; margin-bottom:5px;">Nombre / Empresa *</label>
                    <input type="text" name="nombre" id="edit_nombre" required class="campo-input">
                </div>
                <div>
                    <label style="display:block; font-size:.83rem; font-weight:700; color:#171717; margin-bottom:5px;">Teléfono</label>
                    <input type="text" name="telefono" id="edit_telefono" class="campo-input">
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label style="display:block; font-size:.83rem; font-weight:700; color:#171717; margin-bottom:5px;">Correo Electrónico (No modificable)</label>
                    <input type="hidden" name="correo" id="edit_correo_hidden">
                    <input type="email" id="edit_correo" readonly class="campo-input">
                    <p style="font-size:.75rem; color:#9CA3AF; margin-top:4px;">Por motivos de seguridad, el correo no puede ser modificado.</p>
                </div>
                <div>
                    <label style="display:block; font-size:.83rem; font-weight:700; color:#171717; margin-bottom:5px;">Frecuencia de Entrega</label>
                    <input type="text" name="frecuencia_entrega" id="edit_frecuencia_entrega" class="campo-input" placeholder="Ej. Semanal, Mensual">
                </div>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:10px; padding-top:16px; border-top:1px solid #E5E7EB; margin-top:4px;">
                <button type="button" onclick="closeModal('modalEditar')"
                    style="padding:9px 20px; border-radius:9px; border:1px solid #E5E7EB; background:#fff; color:#5F6673; font-weight:600; cursor:pointer;">
                    Cancelar
                </button>
                <button type="submit" class="btn-primario" style="padding:9px 24px;">
                    Guardar Cambios
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Eliminar Proveedor -->
<div id="modalEliminar" class="fixed inset-0 hidden z-50 flex items-center justify-center p-4"
     style="background:rgba(0,0,0,.55); backdrop-filter:blur(4px);">
    <div class="bg-white rounded-2xl w-full max-w-md shadow-2xl modal-anim overflow-hidden">

        <div style="padding:36px 32px; text-align:center;">
            <div style="width:72px; height:72px; background:#fde8e8; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 16px;">
                <i class="fas fa-exclamation-triangle" style="color:#E53935; font-size:1.8rem;"></i>
            </div>
            <h3 class="font-serif-ventanet" style="font-size:1.4rem; color:#171717; margin-bottom:8px;">Eliminar Proveedor</h3>
            <p style="color:#5F6673; margin-bottom:4px;">¿Estás seguro de eliminar a:</p>
            <p id="delete_nombre" style="color:#171717; font-weight:700; font-size:1rem;"></p>
        </div>

        <div style="background:#F8F8F8; border-top:1px solid #E5E7EB; padding:18px 24px; display:flex; justify-content:center; gap:10px;">
            <button type="button" onclick="closeModal('modalEliminar')"
                style="padding:9px 20px; border-radius:9px; border:1px solid #E5E7EB; background:#fff; color:#5F6673; font-weight:600; cursor:pointer;">
                Cancelar
            </button>
            <a id="delete_link" href="#" class="btn-peligro" style="padding:9px 24px; text-decoration:none;">
                Sí, eliminar
            </a>
        </div>
    </div>
</div>


<script>
    function openModal(id)  { document.getElementById(id).classList.remove('hidden'); }
    function closeModal(id) { document.getElementById(id).classList.add('hidden'); }

    function openEditModal(proveedor) {
        document.getElementById('edit_id_proveedor').value       = proveedor.id_proveedor;
        document.getElementById('edit_nombre').value             = proveedor.nombre             ?? '';
        document.getElementById('edit_telefono').value           = proveedor.telefono           ?? '';
        document.getElementById('edit_correo').value             = proveedor.correo             ?? '';
        document.getElementById('edit_correo_hidden').value      = proveedor.correo             ?? '';
        document.getElementById('edit_frecuencia_entrega').value = proveedor.frecuencia_entrega ?? '';
        openModal('modalEditar');
    }

    function openDeleteModal(id, nombre) {
        document.getElementById('delete_nombre').textContent = nombre;
        document.getElementById('delete_link').href = '../../controllers/ProveedorController.php?accion=eliminar&id=' + id;
        openModal('modalEliminar');
    }
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
