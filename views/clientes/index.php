<?php
// ============================================================
// Vista: Gestión de Clientes
// ============================================================
session_start();

if (!isset($_SESSION['usuario'])) {
    header("Location: ../usuarios/login.php");
    exit;
}
$titulo = "Panel de clientes - Administrador";
require_once __DIR__ . '/../../config/databse.php';
require_once __DIR__ . '/../../models/cliente.php';

$database     = new Database();
$db           = $database->conectar();
$clienteModel = new Cliente($db);
$todosClientes = $clienteModel->obtenerTodos();

// Paginación
$porPagina     = 10;
$totalClientes = count($todosClientes);
$totalPags     = (int) ceil($totalClientes / $porPagina);
$pagActual     = max(1, min((int)($_GET['pagina'] ?? 1), max(1, $totalPags)));
$offset        = ($pagActual - 1) * $porPagina;
$clientes      = array_slice($todosClientes, $offset, $porPagina);

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';
?>

<style>
    .btn-primario {
        background:#00875F; color:#fff; border-radius:10px; border:none;
        font-weight:600; font-family:'Outfit',sans-serif; cursor:pointer;
        transition:background .18s,transform .15s,box-shadow .18s;
        box-shadow:0 4px 12px rgba(0,135,95,.22);
        display:inline-flex; align-items:center; gap:6px;
    }
    .btn-primario:hover { background:#01614B; transform:translateY(-2px); }
    .btn-peligro {
        background:#E53935; color:#fff; border-radius:10px; border:none;
        font-weight:600; font-family:'Outfit',sans-serif; cursor:pointer;
        transition:background .18s; display:inline-flex; align-items:center; gap:6px;
        text-decoration:none;
    }
    .btn-peligro:hover { background:#c62828; }
    .campo-input {
        width:100%; background:#fff; border:1.5px solid #E5E7EB; border-radius:10px;
        color:#171717; font-family:'Outfit',sans-serif; font-size:.95rem; outline:none;
        transition:border-color .2s,box-shadow .2s; padding:10px 14px;
    }
    .campo-input:focus { border-color:#61D0A7; box-shadow:0 0 0 4px rgba(97,208,167,.15); }
    .campo-input[readonly] { background:#F8F8F8; color:#5F6673; cursor:not-allowed; }
    @keyframes modalRise {
        from { opacity:0; transform:translateY(18px) scale(.98); }
        to   { opacity:1; transform:translateY(0) scale(1); }
    }
    .modal-anim { animation:modalRise .28s cubic-bezier(.22,1,.36,1) forwards; }
    .pag-btn {
        padding:7px 13px; border-radius:8px; border:1px solid #E5E7EB;
        background:#fff; color:#5F6673; font-size:.85rem; font-weight:600;
        text-decoration:none; transition:background .15s,border-color .15s,color .15s;
    }
    .pag-btn:hover        { background:#DDF5EC; border-color:#61D0A7; color:#01614B; }
    .pag-btn.activa       { background:#00875F; border-color:#00875F; color:#fff; }
    .pag-btn.deshabilitado { opacity:.4; pointer-events:none; }
</style>

<div class="max-w-7xl mx-auto font-sans-ventanet">

    <!-- Encabezado -->
    <div class="flex flex-col md:flex-row md:items-center justify-between mb-6 gap-4">
        <div>
            <h2 class="text-3xl font-bold font-serif-ventanet" style="color:#01614B;">Gestionar Clientes</h2>
            <p class="text-sm mt-1" style="color:#5F6673;">Administra los clientes de tu negocio</p>
        </div>
        <button onclick="abrirModal('modalCrear')" class="btn-primario px-5 py-2.5">
            <i class="fas fa-user-plus"></i> Nuevo Cliente
        </button>
    </div>

    <!-- Alerta -->
    <?php if (isset($_SESSION['alert'])): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon:  '<?= htmlspecialchars($_SESSION['alert']['icon'])  ?>',
                title: <?= json_encode($_SESSION['alert']['title']) ?>,
                text:  <?= json_encode($_SESSION['alert']['text'])  ?>,
                confirmButtonText: 'Entendido', confirmButtonColor: '#00875F',
                customClass: { popup:'rounded-[20px]', confirmButton:'rounded-lg px-6 py-2.5' }
            });
        });
    </script>
    <?php unset($_SESSION['alert']); endif; ?>

    <!-- Tabla -->
    <div class="bg-white rounded-2xl border overflow-hidden" style="border-color:#E5E7EB;">

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr style="background:#01614B;">
                        <th class="px-5 py-3 text-xs font-bold uppercase tracking-wide" style="color:#fff;">Nombre Completo</th>
                        <th class="px-5 py-3 text-xs font-bold uppercase tracking-wide" style="color:#fff;">Correo</th>
                        <th class="px-5 py-3 text-xs font-bold uppercase tracking-wide" style="color:#fff;">Teléfono</th>
                        <th class="px-5 py-3 text-xs font-bold uppercase tracking-wide text-center" style="color:#fff;">Estado</th>
                        <th class="px-5 py-3 text-xs font-bold uppercase tracking-wide text-center" style="color:#fff;">Fecha registro</th>
                        <th class="px-5 py-3 text-xs font-bold uppercase tracking-wide text-center" style="color:#fff;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clientes as $c):
                        $isActivo = isset($c['estado']) && strtolower($c['estado']) === 'activo';
                        $bgFila   = $isActivo ? '#fff'    : '#FDECEC';
                        $bgHover  = $isActivo ? '#F8F8F8' : '#fde0e0';
                    ?>
                    <tr style="border-bottom:1px solid #E5E7EB;background:<?= $bgFila ?>;transition:background .15s;"
                        onmouseover="this.style.background='<?= $bgHover ?>'"
                        onmouseout="this.style.background='<?= $bgFila ?>'">

                        <td class="px-5 py-3.5 font-bold text-sm" style="color:#171717;">
                            <?= htmlspecialchars($c['nombre']) ?>
                        </td>

                        <td class="px-5 py-3.5 text-sm" style="color:#5F6673;">
                            <?= !empty($c['correo']) ? htmlspecialchars($c['correo']) : '<span style="color:#9CA3AF;font-style:italic;">Sin correo</span>' ?>
                        </td>

                        <td class="px-5 py-3.5 text-sm" style="color:#5F6673;">
                            <?= !empty($c['telefono']) ? htmlspecialchars($c['telefono']) : '<span style="color:#9CA3AF;font-style:italic;">Sin teléfono</span>' ?>
                        </td>

                        <td class="px-5 py-3.5 text-center">
                            <?php if ($isActivo): ?>
                                <span style="background:#DDF5EC;color:#00875F;border:1px solid #61D0A7;padding:3px 12px;border-radius:999px;font-size:.75rem;font-weight:700;display:inline-block;">Activo</span>
                            <?php else: ?>
                                <span style="background:#fde8e8;color:#E53935;border:1px solid #E53935;padding:3px 12px;border-radius:999px;font-size:.75rem;font-weight:700;display:inline-block;">Inactivo</span>
                            <?php endif; ?>
                        </td>

                        <td class="px-5 py-3.5 text-center text-sm" style="color:#5F6673;">
                            <?= !empty($c['fecha_registro']) ? date('d/m/Y', strtotime($c['fecha_registro'])) : '—' ?>
                        </td>

                        <td class="px-5 py-3.5">
                            <div style="display:flex;align-items:center;justify-content:center;gap:8px;">
                                <!-- Editar -->
                                <button type="button" onclick="abrirModalEditar(<?= htmlspecialchars(json_encode($c)) ?>)"
                                    title="Editar"
                                    style="width:32px;height:32px;border-radius:8px;border:1px solid #E5E7EB;background:#fff;color:#00875F;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .15s;"
                                    onmouseover="this.style.background='#DDF5EC';this.style.borderColor='#61D0A7';"
                                    onmouseout="this.style.background='#fff';this.style.borderColor='#E5E7EB';">
                                    <i class="fas fa-pen" style="font-size:.75rem;"></i>
                                </button>

                                <!-- Activar/Desactivar -->
                                <button type="button"
                                    onclick="location.href='../../controllers/ClienteController.php?accion=toggleEstado&id=<?= $c['id_cliente'] ?>&estado=<?= $c['estado'] ?? 'activo' ?>'"
                                    title="<?= $isActivo ? 'Desactivar' : 'Activar' ?>"
                                    style="width:32px;height:32px;border-radius:8px;border:1px solid <?= $isActivo ? '#FFB51B' : '#E5E7EB' ?>;background:#fff;color:<?= $isActivo ? '#FFB51B' : '#00875F' ?>;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .15s;"
                                    onmouseover="this.style.opacity='.75';" onmouseout="this.style.opacity='1';">
                                    <i class="fas <?= $isActivo ? 'fa-ban' : 'fa-check' ?>" style="font-size:.75rem;"></i>
                                </button>

                                <!-- Eliminar -->
                                <button type="button"
                                    onclick="abrirModalEliminar(<?= $c['id_cliente'] ?>, '<?= htmlspecialchars(addslashes($c['nombre'])) ?>')"
                                    title="Eliminar"
                                    style="width:32px;height:32px;border-radius:8px;border:1px solid #fde8e8;background:#fff;color:#E53935;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .15s;"
                                    onmouseover="this.style.background='#fde8e8';this.style.borderColor='#E53935';"
                                    onmouseout="this.style.background='#fff';this.style.borderColor='#fde8e8';">
                                    <i class="fas fa-trash" style="font-size:.75rem;"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>

                    <?php if (empty($clientes)): ?>
                    <tr>
                        <td colspan="6" style="padding:48px;text-align:center;">
                            <div style="width:56px;height:56px;background:#F8F8F8;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;border:1px solid #E5E7EB;">
                                <i class="fas fa-users-slash" style="color:#9CA3AF;font-size:1.3rem;"></i>
                            </div>
                            <p style="color:#5F6673;font-weight:600;">No hay clientes registrados.</p>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Paginación -->
        <?php if ($totalPags > 1): ?>
        <div style="padding:14px 20px;border-top:1px solid #E5E7EB;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
            <span style="font-size:.8rem;color:#5F6673;">
                Página <strong style="color:#171717;"><?= $pagActual ?></strong>
                de <strong style="color:#171717;"><?= $totalPags ?></strong>
            </span>

            <div style="display:flex;gap:6px;flex-wrap:wrap;">
                <a href="?pagina=<?= $pagActual - 1 ?>" class="pag-btn <?= $pagActual <= 1 ? 'deshabilitado' : '' ?>">← Anterior</a>

                <?php for ($pg = 1; $pg <= $totalPags; $pg++): ?>
                    <a href="?pagina=<?= $pg ?>" class="pag-btn <?= $pg === $pagActual ? 'activa' : '' ?>"><?= $pg ?></a>
                <?php endfor; ?>

                <a href="?pagina=<?= $pagActual + 1 ?>" class="pag-btn <?= $pagActual >= $totalPags ? 'deshabilitado' : '' ?>">Siguiente →</a>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<!-- MODAL CREAR -->
<div id="modalCrear" class="fixed inset-0 hidden z-50 flex items-center justify-center p-4"
     style="background:rgba(0,0,0,.45);backdrop-filter:blur(4px);">
    <div class="bg-white rounded-2xl w-full max-w-lg shadow-2xl modal-anim overflow-hidden">
        <div style="background:#F8F8F8;border-bottom:1px solid #E5E7EB;padding:18px 24px;display:flex;align-items:center;justify-content:space-between;">
            <div style="display:flex;align-items:center;gap:12px;">
                <div style="width:38px;height:38px;background:#00875F;border-radius:10px;display:flex;align-items:center;justify-content:center;">
                    <i class="fas fa-user-plus" style="color:#fff;font-size:.9rem;"></i>
                </div>
                <h3 class="font-serif-ventanet" style="font-size:1.1rem;color:#171717;">Agregar Cliente</h3>
            </div>
            <button onclick="cerrarModal('modalCrear')" style="background:none;border:none;cursor:pointer;color:#9CA3AF;font-size:1.2rem;"><i class="fas fa-times"></i></button>
        </div>

        <form action="../../controllers/ClienteController.php?accion=registrar" method="POST" style="padding:24px;display:flex;flex-direction:column;gap:14px;">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label style="display:block;font-size:.83rem;font-weight:700;color:#171717;margin-bottom:5px;">Nombre *</label>
                    <input type="text" name="nombre" required class="campo-input" placeholder="Ej. Juan Pérez">
                </div>

                <div>
                    <label style="display:block;font-size:.83rem;font-weight:700;color:#171717;margin-bottom:5px;">Teléfono</label>
                    <input type="tel" name="telefono" class="campo-input" placeholder="3001234567">
                </div>
            </div>

            <div>
                <label style="display:block;font-size:.83rem;font-weight:700;color:#171717;margin-bottom:5px;">Correo</label>
                <input type="email" name="correo" class="campo-input" placeholder="correo@ejemplo.com">
            </div>

            <div style="display:flex;justify-content:flex-end;gap:10px;padding-top:14px;border-top:1px solid #E5E7EB;">
                <button type="button" onclick="cerrarModal('modalCrear')"
                    style="padding:9px 20px;border-radius:9px;border:1px solid #E5E7EB;background:#fff;color:#5F6673;font-weight:600;cursor:pointer;">Cancelar</button>

                <button type="submit" class="btn-primario" style="padding:9px 24px;">Guardar</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDITAR -->
<div id="modalEditar" class="fixed inset-0 hidden z-50 flex items-center justify-center p-4"
     style="background:rgba(0,0,0,.45);backdrop-filter:blur(4px);">
    <div class="bg-white rounded-2xl w-full max-w-lg shadow-2xl modal-anim overflow-hidden">
        <div style="background:#F8F8F8;border-bottom:1px solid #E5E7EB;padding:18px 24px;display:flex;align-items:center;justify-content:space-between;">
            <div style="display:flex;align-items:center;gap:12px;">
                <div style="width:38px;height:38px;background:#00875F;border-radius:10px;display:flex;align-items:center;justify-content:center;">
                    <i class="fas fa-user-edit" style="color:#fff;font-size:.9rem;"></i>
                </div>
                <h3 class="font-serif-ventanet" style="font-size:1.1rem;color:#171717;">Editar Cliente</h3>
            </div>

            <button onclick="cerrarModal('modalEditar')" style="background:none;border:none;cursor:pointer;color:#9CA3AF;font-size:1.2rem;">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form action="../../controllers/ClienteController.php?accion=editar" method="POST" style="padding:24px;display:flex;flex-direction:column;gap:14px;">
            <input type="hidden" name="id_cliente" id="editId">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label style="display:block;font-size:.83rem;font-weight:700;color:#171717;margin-bottom:5px;">Nombre *</label>
                    <input type="text" name="nombre" id="editNombre" required class="campo-input">
                </div>

                <div>
                    <label style="display:block;font-size:.83rem;font-weight:700;color:#171717;margin-bottom:5px;">Teléfono</label>
                    <input type="text" name="telefono" id="editTelefono" class="campo-input">
                </div>
            </div>

            <div>
                <label style="display:block;font-size:.83rem;font-weight:700;color:#171717;margin-bottom:5px;">Correo (no modificable)</label>
                <input type="email" id="editCorreo" readonly class="campo-input">
            </div>

            <div style="display:flex;justify-content:flex-end;gap:10px;padding-top:14px;border-top:1px solid #E5E7EB;">
                <button type="button" onclick="cerrarModal('modalEditar')"
                    style="padding:9px 20px;border-radius:9px;border:1px solid #E5E7EB;background:#fff;color:#5F6673;font-weight:600;cursor:pointer;">Cancelar</button>

                <button type="submit" class="btn-primario" style="padding:9px 24px;">Guardar</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL ELIMINAR -->
<div id="modalEliminar" class="fixed inset-0 hidden z-50 flex items-center justify-center p-4"
     style="background:rgba(0,0,0,.55);backdrop-filter:blur(4px);">
    <div class="bg-white rounded-2xl w-full max-w-md shadow-2xl modal-anim overflow-hidden">
        <div style="padding:36px 32px;text-align:center;">
            <div style="width:68px;height:68px;background:#fde8e8;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
                <i class="fas fa-exclamation-triangle" style="color:#E53935;font-size:1.7rem;"></i>
            </div>

            <h3 class="font-serif-ventanet" style="font-size:1.3rem;color:#171717;margin-bottom:8px;">Eliminar Cliente</h3>
            <p style="color:#5F6673;">¿Estás seguro de eliminar a:</p>
            <p id="elimNombre" style="font-weight:700;color:#171717;font-size:1rem;margin-top:4px;"></p>
        </div>

        <div style="background:#F8F8F8;border-top:1px solid #E5E7EB;padding:16px 24px;display:flex;justify-content:center;gap:10px;">
            <button type="button" onclick="cerrarModal('modalEliminar')"
                style="padding:9px 20px;border-radius:9px;border:1px solid #E5E7EB;background:#fff;color:#5F6673;font-weight:600;cursor:pointer;">Cancelar</button>

            <a id="elimLink" href="#" class="btn-peligro" style="padding:9px 24px;">Sí, eliminar</a>
        </div>
    </div>
</div>

<script>
    function abrirModal(id) {
        document.getElementById(id).classList.remove('hidden');
    }

    function cerrarModal(id) {
        document.getElementById(id).classList.add('hidden');
    }

    function abrirModalEditar(c) {
        document.getElementById('editId').value       = c.id_cliente;
        document.getElementById('editNombre').value   = c.nombre   ?? '';
        document.getElementById('editTelefono').value = c.telefono ?? '';
        document.getElementById('editCorreo').value   = c.correo   ?? '';
        abrirModal('modalEditar');
    }

    function abrirModalEliminar(id, nombre) {
        document.getElementById('elimNombre').textContent = nombre;
        document.getElementById('elimLink').href =
            '../../controllers/ClienteController.php?accion=eliminar&id=' + id;
        abrirModal('modalEliminar');
    }
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>