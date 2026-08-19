<?php
// ============================================================
// Vista: Módulo de Ventas
// Stock fuente: inventario.stock_actual
// Ganancia: (precio_venta - precio_compra) × cantidad
// ============================================================
session_start();

if (!isset($_SESSION['usuario'])) {
    header("Location: ../usuarios/login.php");
    exit;
}
$titulo = "Panel de ventas - Administrador";
require_once __DIR__ . '/../../config/databse.php';
require_once __DIR__ . '/../../models/venta.php';

$database   = new Database();
$db         = $database->conectar();
$ventaModel = new Venta($db);

$todasVentas = $ventaModel->obtenerTodas();
$resumen     = $ventaModel->obtenerResumen();
$clientes    = $ventaModel->obtenerClientes();


// Paginación
$porPagina   = 10;
$totalVentas = count($todasVentas);
$totalPags   = (int) ceil($totalVentas / $porPagina);
$pagActual   = max(1, min((int)($_GET['pagina'] ?? 1), max(1, $totalPags)));
$offset      = ($pagActual - 1) * $porPagina;
$ventasPag   = array_slice($todasVentas, $offset, $porPagina);

$titulo = 'Ventas';
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
        background:#fff; border:1.5px solid #E5E7EB; border-radius:10px;
        color:#171717; font-family:'Outfit',sans-serif; font-size:.9rem;
        outline:none; padding:9px 12px;
        transition:border-color .2s,box-shadow .2s;
    }
    .campo-input:focus { border-color:#61D0A7; box-shadow:0 0 0 4px rgba(97,208,167,.15); }

    @keyframes modalRise {
        from { opacity:0; transform:translateY(18px) scale(.98); }
        to   { opacity:1; transform:translateY(0) scale(1); }
    }
    .modal-anim { animation:modalRise .28s cubic-bezier(.22,1,.36,1) forwards; }

    .kpi-card {
        background:#fff; border:1px solid #E5E7EB; border-radius:14px;
        padding:18px; transition:transform .18s,box-shadow .18s;
    }
    .kpi-card:hover { transform:translateY(-3px); box-shadow:0 8px 24px rgba(0,135,95,.10); }

    /* Fila del modal de nueva venta */
    .item-row {
        background:#F8F8F8; border:1.5px solid #E5E7EB; border-radius:10px;
        padding:10px 12px;
        display:grid; grid-template-columns:2fr 70px 90px 28px; gap:8px; align-items:center;
    }

    .pag-btn {
        padding:7px 13px; border-radius:8px; border:1px solid #E5E7EB;
        background:#fff; color:#5F6673; font-size:.85rem; font-weight:600;
        text-decoration:none; transition:background .15s,border-color .15s,color .15s;
    }
    .pag-btn:hover         { background:#DDF5EC; border-color:#61D0A7; color:#01614B; }
    .pag-btn.activa        { background:#00875F; border-color:#00875F; color:#fff; }
    .pag-btn.deshabilitado { opacity:.4; pointer-events:none; }
</style>

<div class="max-w-7xl mx-auto font-sans-ventanet">

    <!-- Encabezado -->
    <div class="flex flex-col md:flex-row md:items-center justify-between mb-7 gap-4">
        <div>
            <h2 class="text-3xl font-bold font-serif-ventanet" style="color:#01614B;">Ventas</h2>
            <p class="text-sm mt-1" style="color:#5F6673;">Registra y administra las ventas realizadas</p>
        </div>
        <button onclick="abrirModalNuevaVenta()" class="btn-primario px-5 py-2.5">
            <i class="fas fa-plus-circle"></i> + Nueva venta
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

    <!-- KPIs -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-7">
        <div class="kpi-card">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
                <div style="width:38px;height:38px;background:#DDF5EC;border-radius:10px;display:flex;align-items:center;justify-content:center;">
                    <i class="fas fa-receipt" style="color:#00875F;"></i>
                </div>
                <span style="font-size:.7rem;font-weight:700;color:#5F6673;text-transform:uppercase;letter-spacing:.04em;">Total ventas</span>
            </div>
            <p style="font-size:1.8rem;font-weight:800;color:#171717;line-height:1;"><?= intval($resumen['total_ventas'] ?? 0) ?></p>
            <p style="font-size:.72rem;color:#9CA3AF;margin-top:3px;">registradas</p>
        </div>
        <div class="kpi-card">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
                <div style="width:38px;height:38px;background:#DDF5EC;border-radius:10px;display:flex;align-items:center;justify-content:center;">
                    <i class="fas fa-dollar-sign" style="color:#00875F;"></i>
                </div>
                <span style="font-size:.7rem;font-weight:700;color:#5F6673;text-transform:uppercase;letter-spacing:.04em;">Ingresos total</span>
            </div>
            <p style="font-size:1.4rem;font-weight:800;color:#171717;line-height:1;">$<?= number_format($resumen['ingresos_total'] ?? 0, 0, ',', '.') ?></p>
            <p style="font-size:.72rem;color:#9CA3AF;margin-top:3px;">acumulado</p>
        </div>
        <div class="kpi-card">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
                <div style="width:38px;height:38px;background:#fffbeb;border-radius:10px;display:flex;align-items:center;justify-content:center;">
                    <i class="fas fa-calendar-day" style="color:#FFB51B;"></i>
                </div>
                <span style="font-size:.7rem;font-weight:700;color:#5F6673;text-transform:uppercase;letter-spacing:.04em;">Ventas hoy</span>
            </div>
            <p style="font-size:1.8rem;font-weight:800;color:#171717;line-height:1;"><?= intval($resumen['ventas_hoy'] ?? 0) ?></p>
            <p style="font-size:.72rem;color:#9CA3AF;margin-top:3px;">del día</p>
        </div>
        <div class="kpi-card">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
                <div style="width:38px;height:38px;background:#fffbeb;border-radius:10px;display:flex;align-items:center;justify-content:center;">
                    <i class="fas fa-coins" style="color:#FFB51B;"></i>
                </div>
                <span style="font-size:.7rem;font-weight:700;color:#5F6673;text-transform:uppercase;letter-spacing:.04em;">Ingresos hoy</span>
            </div>
            <p style="font-size:1.4rem;font-weight:800;color:#171717;line-height:1;">$<?= number_format($resumen['ingresos_hoy'] ?? 0, 0, ',', '.') ?></p>
            <p style="font-size:.72rem;color:#9CA3AF;margin-top:3px;">del día</p>
        </div>
    </div>

    <!-- Tabla de ventas -->
    <div class="bg-white rounded-2xl border overflow-hidden" style="border-color:#E5E7EB;">


        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr style="background:#01614B;">
                        <th class="px-4 py-3 text-xs font-bold uppercase tracking-wide" style="color:#fff;">#</th>
                        <th class="px-4 py-3 text-xs font-bold uppercase tracking-wide" style="color:#fff;">Fecha</th>
                        <th class="px-4 py-3 text-xs font-bold uppercase tracking-wide" style="color:#fff;">Cliente</th>
                        <th class="px-4 py-3 text-xs font-bold uppercase tracking-wide" style="color:#fff;">Usuario</th>
                        <th class="px-4 py-3 text-xs font-bold uppercase tracking-wide text-right" style="color:#fff;">Total</th>
                        <th class="px-4 py-3 text-xs font-bold uppercase tracking-wide text-right" style="color:#fff;">Ganancia</th>
                        <th class="px-4 py-3 text-xs font-bold uppercase tracking-wide text-right" style="color:#fff;">Margen</th>
                        <th class="px-4 py-3 text-xs font-bold uppercase tracking-wide text-center" style="color:#fff;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($ventasPag)): ?>
                        <?php foreach ($ventasPag as $v):
                            $gan    = floatval($v['ganancia'] ?? 0);
                            $total  = floatval($v['total']   ?? 0);
                            $margen = $total > 0 ? round(($gan / $total) * 100, 1) : 0;
                            $cg     = $gan >= 0 ? '#00875F' : '#E53935';
                        ?>
                        <tr style="border-bottom:1px solid #E5E7EB;transition:background .15s;"
                            onmouseover="this.style.background='#F8F8F8'"
                            onmouseout="this.style.background=''">
                            <td class="px-4 py-3 text-sm" style="color:#9CA3AF;">#<?= $v['id_venta'] ?></td>
                            <td class="px-4 py-3 text-sm" style="color:#171717;">
                                <?= !empty($v['fecha']) ? date('d/m/Y', strtotime($v['fecha'])) : '---' ?>
                            </td>
                            <td class="px-4 py-3 text-sm font-bold" style="color:#171717;">
                                <?= htmlspecialchars($v['cliente'] ?? 'Sin cliente') ?>
                            </td>
                            <td class="px-4 py-3 text-sm" style="color:#5F6673;">
                                <?= htmlspecialchars($v['vendedor'] ?? '---') ?>
                            </td>
                            <td class="px-4 py-3 text-sm font-bold text-right" style="color:#00875F;">
                                $<?= number_format($total, 0, ',', '.') ?>
                            </td>
                            <td class="px-4 py-3 text-sm font-bold text-right" style="color:<?= $cg ?>;">
                                $<?= number_format(abs($gan), 0, ',', '.') ?>
                            </td>
                            <td class="px-4 py-3 text-sm font-bold text-right" style="color:<?= $cg ?>;">
                                <?= $margen ?>%
                            </td>
                            <td class="px-4 py-3">
                                <div style="display:flex;align-items:center;justify-content:center;gap:8px;">
                                    <button type="button" onclick="verDetalle(<?= $v['id_venta'] ?>)"
                                        title="Ver detalle"
                                        style="width:30px;height:30px;border-radius:8px;border:1px solid #E5E7EB;background:#fff;color:#00875F;cursor:pointer;display:flex;align-items:center;justify-content:center;"
                                        onmouseover="this.style.background='#DDF5EC';this.style.borderColor='#61D0A7';"
                                        onmouseout="this.style.background='#fff';this.style.borderColor='#E5E7EB';">
                                        <i class="fas fa-eye" style="font-size:.72rem;"></i>
                                    </button>
                                    <button type="button" onclick="abrirModalEliminar(<?= $v['id_venta'] ?>)"
                                        title="Eliminar"
                                        style="width:30px;height:30px;border-radius:8px;border:1px solid #fde8e8;background:#fff;color:#E53935;cursor:pointer;display:flex;align-items:center;justify-content:center;"
                                        onmouseover="this.style.background='#fde8e8';this.style.borderColor='#E53935';"
                                        onmouseout="this.style.background='#fff';this.style.borderColor='#fde8e8';">
                                        <i class="fas fa-trash" style="font-size:.72rem;"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="padding:48px;text-align:center;">
                                <div style="width:56px;height:56px;background:#F8F8F8;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;border:1px solid #E5E7EB;">
                                    <i class="fas fa-cash-register" style="color:#9CA3AF;font-size:1.3rem;"></i>
                                </div>
                                <p style="color:#5F6673;font-weight:600;">No hay ventas registradas.</p>
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

    </div><!-- /tabla -->

</div><!-- /max-w -->


<!-- ═══════════════════════════════════
     MODAL: NUEVA VENTA
════════════════════════════════════ -->
<div id="modalCrear" class="fixed inset-0 hidden z-50 flex items-center justify-center p-4"
     style="background:rgba(0,0,0,.50);backdrop-filter:blur(4px);">
    <div class="bg-white rounded-2xl w-full shadow-2xl modal-anim overflow-hidden"
         style="max-width:700px;max-height:90vh;display:flex;flex-direction:column;">

        <!-- Cabecera -->
        <div style="background:#F8F8F8;border-bottom:1px solid #E5E7EB;padding:18px 24px;display:flex;align-items:center;justify-content:space-between;flex-shrink:0;">
            <div style="display:flex;align-items:center;gap:12px;">
                <div style="width:38px;height:38px;background:#00875F;border-radius:10px;display:flex;align-items:center;justify-content:center;">
                    <i class="fas fa-cash-register" style="color:#fff;font-size:.9rem;"></i>
                </div>
                <h3 class="font-serif-ventanet" style="font-size:1.1rem;color:#171717;">Nueva Venta</h3>
            </div>
            <button onclick="cerrarModalCrear()" style="background:none;border:none;cursor:pointer;color:#9CA3AF;font-size:1.2rem;">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <!-- Formulario -->
        <form action="../../controllers/VentaController.php?accion=registrar" method="POST"
              style="padding:24px;display:flex;flex-direction:column;gap:18px;overflow-y:auto;flex:1;">

            <!-- Cliente -->
            <div>
                <label style="display:block;font-size:.83rem;font-weight:700;color:#171717;margin-bottom:5px;">Cliente *</label>
                <?php if (empty($clientes)): ?>
                    <div style="background:#fffbeb;border:1px solid #FFB51B;border-radius:10px;padding:12px 14px;color:#92400E;font-size:.85rem;">
                        <i class="fas fa-exclamation-triangle" style="color:#FFB51B;margin-right:6px;"></i>
                        No hay clientes activos. Activa o registra uno primero.
                    </div>
                <?php else: ?>
                    <div style="position:relative;">
                        <select name="id_cliente" required class="campo-input" style="appearance:none;cursor:pointer;">
                            <option value="">Selecciona un cliente...</option>
                            <?php foreach ($clientes as $cl): ?>
                                <option value="<?= $cl['id_cliente'] ?>"><?= htmlspecialchars($cl['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <i class="fas fa-chevron-down" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);color:#00875F;font-size:.75rem;pointer-events:none;"></i>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Productos -->
            <div>
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                    <label style="font-size:.83rem;font-weight:700;color:#171717;">Productos *</label>
                    <button type="button" onclick="agregarFila()"
                        style="background:#DDF5EC;color:#00875F;border:1px solid #61D0A7;border-radius:8px;padding:5px 12px;font-size:.8rem;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:5px;">
                        <i class="fas fa-plus" style="font-size:.7rem;"></i> Agregar producto
                    </button>
                </div>

                <!-- Encabezados columnas -->
                <div style="display:grid;grid-template-columns:2fr 70px 90px 28px;gap:8px;padding:0 4px;margin-bottom:4px;">
                    <span style="font-size:.7rem;font-weight:700;color:#9CA3AF;text-transform:uppercase;">Producto</span>
                    <span style="font-size:.7rem;font-weight:700;color:#9CA3AF;text-transform:uppercase;text-align:center;">Cantidad</span>
                    <span style="font-size:.7rem;font-weight:700;color:#9CA3AF;text-transform:uppercase;text-align:right;">Subtotal</span>
                    <span></span>
                </div>

                <div id="filasVenta" style="display:flex;flex-direction:column;gap:8px;"></div>
            </div>

            <!-- Total -->
            <div style="display:flex;align-items:center;justify-content:space-between;background:#DDF5EC;border:1px solid #61D0A7;border-radius:12px;padding:13px 18px;">
                <span style="font-size:.82rem;font-weight:700;color:#01614B;text-transform:uppercase;letter-spacing:.04em;">Total de la venta</span>
                <span id="totalVenta" style="font-size:1.5rem;font-weight:800;color:#00875F;">$0</span>
            </div>

            <!-- Botones -->
            <div style="display:flex;justify-content:flex-end;gap:10px;padding-top:12px;border-top:1px solid #E5E7EB;">
                <button type="button" onclick="cerrarModalCrear()"
                    style="padding:9px 20px;border-radius:9px;border:1px solid #E5E7EB;background:#fff;color:#5F6673;font-weight:600;cursor:pointer;">
                    Cancelar
                </button>
                <button type="submit" class="btn-primario" style="padding:9px 24px;">
                    <i class="fas fa-check"></i> Confirmar venta
                </button>
            </div>
        </form>
    </div>
</div>


<!-- ═══════════════════════════════════
     MODAL: DETALLE CON GANANCIA
════════════════════════════════════ -->
<div id="modalDetalle" class="fixed inset-0 hidden z-50 flex items-center justify-center p-4"
     style="background:rgba(0,0,0,.50);backdrop-filter:blur(4px);">
    <div class="bg-white rounded-2xl w-full shadow-2xl modal-anim overflow-hidden" style="max-width:600px;">

        <div style="background:#F8F8F8;border-bottom:1px solid #E5E7EB;padding:18px 24px;display:flex;align-items:center;justify-content:space-between;">
            <div style="display:flex;align-items:center;gap:12px;">
                <div style="width:38px;height:38px;background:#00875F;border-radius:10px;display:flex;align-items:center;justify-content:center;">
                    <i class="fas fa-receipt" style="color:#fff;font-size:.9rem;"></i>
                </div>
                <div>
                    <h3 class="font-serif-ventanet" style="font-size:1.1rem;color:#171717;">Detalle de Venta</h3>
                    <p id="detalleEncabezado" style="font-size:.78rem;color:#5F6673;"></p>
                </div>
            </div>
            <button onclick="cerrarModal('modalDetalle')" style="background:none;border:none;cursor:pointer;color:#9CA3AF;font-size:1.2rem;">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div id="detalleCuerpo" style="padding:24px;max-height:460px;overflow-y:auto;">
            <div style="text-align:center;padding:32px 0;">
                <i class="fas fa-spinner fa-spin" style="color:#00875F;font-size:1.5rem;"></i>
            </div>
        </div>
    </div>
</div>


<!-- ═══════════════════════════════════
     MODAL: ELIMINAR
════════════════════════════════════ -->
<div id="modalEliminar" class="fixed inset-0 hidden z-50 flex items-center justify-center p-4"
     style="background:rgba(0,0,0,.55);backdrop-filter:blur(4px);">
    <div class="bg-white rounded-2xl w-full shadow-2xl modal-anim overflow-hidden" style="max-width:420px;">
        <div style="padding:36px 32px;text-align:center;">
            <div style="width:68px;height:68px;background:#fde8e8;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
                <i class="fas fa-exclamation-triangle" style="color:#E53935;font-size:1.7rem;"></i>
            </div>
            <h3 class="font-serif-ventanet" style="font-size:1.3rem;color:#171717;margin-bottom:8px;">Eliminar Venta</h3>
            <p style="color:#5F6673;font-size:.9rem;">¿Estás seguro? Esta acción no se puede deshacer.</p>
        </div>
        <div style="background:#F8F8F8;border-top:1px solid #E5E7EB;padding:16px 24px;display:flex;justify-content:center;gap:10px;">
            <button type="button" onclick="cerrarModal('modalEliminar')"
                style="padding:9px 20px;border-radius:9px;border:1px solid #E5E7EB;background:#fff;color:#5F6673;font-weight:600;cursor:pointer;">
                Cancelar
            </button>
            <a id="linkEliminar" href="#" class="btn-peligro" style="padding:9px 24px;">
                Sí, eliminar
            </a>
        </div>
    </div>
</div>


<script>
// ── Datos inyectados desde PHP ──────────────────────────────
// Cada producto trae: id_producto, nombre, precio_venta, stock, precio_compra
var productosDisponibles = <?= json_encode($productos) ?>;
var filaCount = 0;

function abrirModal(id)  { document.getElementById(id).classList.remove('hidden'); }
function cerrarModal(id) { document.getElementById(id).classList.add('hidden'); }

function abrirModalNuevaVenta() {
    abrirModal('modalCrear');
    if (document.getElementById('filasVenta').children.length === 0) {
        agregarFila();
    }
}

function cerrarModalCrear() {
    cerrarModal('modalCrear');
    document.getElementById('filasVenta').innerHTML = '';
    document.getElementById('totalVenta').textContent = '$0';
    filaCount = 0;
}

function abrirModalEliminar(id) {
    document.getElementById('linkEliminar').href =
        '../../controllers/VentaController.php?accion=eliminar&id=' + id;
    abrirModal('modalEliminar');
}

/* ── Agregar fila de producto ── */
function agregarFila() {
    var container = document.getElementById('filasVenta');
    var idx = filaCount++;

    var options = '<option value="">-- Selecciona un producto --</option>';
    productosDisponibles.forEach(function(p) {
        var stock = parseInt(p.stock) || 0;
        if (stock <= 0) {
            // Producto agotado: mostrar deshabilitado
            options += '<option value="' + p.id_producto + '" disabled style="color:#9CA3AF;">'
                     + p.nombre + ' — AGOTADO</option>';
        } else {
            options += '<option value="' + p.id_producto
                     + '" data-precio="' + p.precio_venta
                     + '" data-stock="' + stock + '">'
                     + p.nombre + ' — $' + Number(p.precio_venta).toLocaleString('es-CO')
                     + ' [' + stock + ' uds.]</option>';
        }
    });

    var div = document.createElement('div');
    div.className = 'item-row';
    div.id = 'fila-' + idx;
    div.innerHTML =
        '<select name="id_producto[]" onchange="onProdChange(' + idx + ')" class="campo-input" style="font-size:.82rem;padding:8px 10px;">'
        + options + '</select>'

        + '<input type="number" name="cantidad[]" id="cant-' + idx + '" min="1" value="1"'
        + ' oninput="calcFila(' + idx + ')"'
        + ' class="campo-input" style="text-align:center;font-size:.85rem;padding:8px;">'

        + '<div style="text-align:right;">'
        +   '<input type="hidden" name="subtotal[]" id="sub-' + idx + '" value="0">'
        +   '<span id="subdisplay-' + idx + '" style="font-weight:800;color:#00875F;font-size:.9rem;">$0</span>'
        + '</div>'

        + '<button type="button" onclick="quitarFila(' + idx + ')"'
        + ' style="width:26px;height:26px;border-radius:6px;background:#fde8e8;border:none;color:#E53935;cursor:pointer;display:flex;align-items:center;justify-content:center;">'
        + '<i class="fas fa-times" style="font-size:.65rem;"></i></button>';

    container.appendChild(div);
    recalcularTotal();
}

function quitarFila(idx) {
    var el = document.getElementById('fila-' + idx);
    if (el) { el.remove(); recalcularTotal(); }
}

function onProdChange(idx) {
    var sel   = document.querySelector('#fila-' + idx + ' select');
    var opt   = sel ? sel.selectedOptions[0] : null;
    var stock = opt ? parseInt(opt.dataset.stock) || 0 : 0;
    var ci    = document.getElementById('cant-' + idx);
    ci.max = stock;
    if (parseInt(ci.value) > stock) ci.value = stock;
    calcFila(idx);
}

function calcFila(idx) {
    var sel    = document.querySelector('#fila-' + idx + ' select');
    var opt    = sel ? sel.selectedOptions[0] : null;
    var precio = opt ? parseFloat(opt.dataset.precio) || 0 : 0;
    var cant   = parseFloat(document.getElementById('cant-' + idx).value) || 0;
    var sub    = precio * cant;
    document.getElementById('sub-' + idx).value = sub;
    document.getElementById('subdisplay-' + idx).textContent = '$' + sub.toLocaleString('es-CO');
    recalcularTotal();
}

function recalcularTotal() {
    var total = 0;
    document.querySelectorAll('input[name="subtotal[]"]').forEach(function(i) {
        total += parseFloat(i.value) || 0;
    });
    document.getElementById('totalVenta').textContent = '$' + total.toLocaleString('es-CO');
}

/* ── Ver detalle con ganancia por ítem ── */
function verDetalle(id) {
    document.getElementById('detalleEncabezado').textContent = 'Venta #' + id;
    document.getElementById('detalleCuerpo').innerHTML =
        '<div style="text-align:center;padding:32px 0;"><i class="fas fa-spinner fa-spin" style="color:#00875F;font-size:1.5rem;"></i></div>';
    abrirModal('modalDetalle');

    fetch('../../controllers/VentaController.php?accion=detalle&id=' + id)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.length) {
                document.getElementById('detalleCuerpo').innerHTML =
                    '<p style="text-align:center;color:#9CA3AF;padding:24px 0;">Sin productos en esta venta.</p>';
                return;
            }

            var html = '<table style="width:100%;border-collapse:collapse;">'
                + '<thead><tr style="background:#F8F8F8;">'
                + '<th style="padding:8px 10px;text-align:left;font-size:.72rem;color:#5F6673;text-transform:uppercase;">Producto</th>'
                + '<th style="padding:8px 10px;text-align:center;font-size:.72rem;color:#5F6673;text-transform:uppercase;">Cant.</th>'
                + '<th style="padding:8px 10px;text-align:right;font-size:.72rem;color:#5F6673;text-transform:uppercase;">P. Venta</th>'
                + '<th style="padding:8px 10px;text-align:right;font-size:.72rem;color:#5F6673;text-transform:uppercase;">Subtotal</th>'
                + '<th style="padding:8px 10px;text-align:right;font-size:.72rem;color:#5F6673;text-transform:uppercase;">Ganancia</th>'
                + '</tr></thead><tbody>';

            var totalV = 0, totalG = 0;
            data.forEach(function(d) {
                totalV += parseFloat(d.subtotal) || 0;
                var gan = parseFloat(d.ganancia_linea) || 0;
                totalG += gan;
                var cg = gan >= 0 ? '#00875F' : '#E53935';
                html += '<tr style="border-bottom:1px solid #E5E7EB;">'
                    + '<td style="padding:9px 10px;font-weight:700;color:#171717;font-size:.85rem;">' + d.producto + '</td>'
                    + '<td style="padding:9px 10px;text-align:center;color:#171717;font-size:.85rem;">' + d.cantidad + '</td>'
                    + '<td style="padding:9px 10px;text-align:right;color:#5F6673;font-size:.82rem;">$' + Number(d.precio_venta).toLocaleString('es-CO') + '</td>'
                    + '<td style="padding:9px 10px;text-align:right;font-weight:700;color:#00875F;font-size:.85rem;">$' + Number(d.subtotal).toLocaleString('es-CO') + '</td>'
                    + '<td style="padding:9px 10px;text-align:right;font-weight:700;color:' + cg + ';font-size:.85rem;">$' + Number(Math.abs(gan)).toLocaleString('es-CO') + '</td>'
                    + '</tr>';
            });
            html += '</tbody></table>';

            // Resumen: total, ganancia y margen
            var margen = totalV > 0 ? (totalG / totalV * 100).toFixed(1) : 0;
            html += '<div style="margin-top:14px;padding:14px 10px;background:#DDF5EC;border-radius:10px;border:1px solid #61D0A7;">'
                + '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">'
                +   '<span style="font-size:.78rem;font-weight:700;color:#01614B;text-transform:uppercase;">Total venta</span>'
                +   '<span style="font-weight:800;color:#00875F;font-size:1.1rem;">$' + totalV.toLocaleString('es-CO') + '</span>'
                + '</div>'
                + '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">'
                +   '<span style="font-size:.78rem;font-weight:700;color:#01614B;text-transform:uppercase;">Ganancia total</span>'
                +   '<span style="font-weight:800;color:#00875F;font-size:1.1rem;">$' + Math.abs(totalG).toLocaleString('es-CO') + '</span>'
                + '</div>'
                + '<div style="display:flex;justify-content:space-between;align-items:center;">'
                +   '<span style="font-size:.78rem;font-weight:700;color:#01614B;text-transform:uppercase;">Margen</span>'
                +   '<span style="font-weight:800;color:#00875F;font-size:1.1rem;">' + margen + '%</span>'
                + '</div>'
                + '</div>';

            document.getElementById('detalleCuerpo').innerHTML = html;
        })
        .catch(function() {
            document.getElementById('detalleCuerpo').innerHTML =
                '<p style="text-align:center;color:#E53935;padding:24px 0;">Error al cargar el detalle.</p>';
        });
}
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
