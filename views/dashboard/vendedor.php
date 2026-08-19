
<?php
session_start();
if (!isset($_SESSION['usuario'])) { header("Location: ../usuarios/login.php"); exit; }
if (strtolower($_SESSION['usuario']['rol']) === 'administrador') { header("Location: ../dashboard/admin.php"); exit; }

require_once __DIR__ . '/../../config/databse.php';
require_once __DIR__ . '/../../models/venta.php';
require_once __DIR__ . '/../../models/inventario.php';
require_once __DIR__ . '/../../models/cliente.php';

$database        = new Database();
$db              = $database->conectar();
$ventaModel      = new Venta($db);
$inventarioModel = new Inventario($db);
$clienteModel    = new Cliente($db);

$id_usuario = $_SESSION['usuario']['id_usuario'];
$nombre     = $_SESSION['usuario']['nombre'];

// Productos y clientes para modal de venta
$productos = $ventaModel->obtenerProductos();
$clientes  = $ventaModel->obtenerClientes(); // HU-017 HU-014: solo activos

// Inventario para HU-015
$inventario  = $inventarioModel->obtenerTodos();
$STOCK_MIN   = 5;
$agotados    = array_filter($inventario, function($r){ return intval($r['stock']) == 0; });
$bajStock    = array_filter($inventario, function($r) use ($STOCK_MIN){ return intval($r['stock']) > 0 && intval($r['stock']) <= $STOCK_MIN; });

// HU-016: Filtro de mis ventas por fecha
$desdeV = $_GET['desde'] ?? '';
$hastaV = $_GET['hasta'] ?? '';

if ($desdeV && $hastaV) {
    $sqlMisVentas = "SELECT v.id_venta, v.fecha, v.total, pc.nombre AS cliente
                     FROM venta v
                     LEFT JOIN cliente c  ON v.id_cliente = c.id_cliente
                     LEFT JOIN persona pc ON c.id_persona = pc.id_persona
                     WHERE v.id_usuario = :id AND v.fecha BETWEEN :desde AND :hasta
                     ORDER BY v.fecha DESC, v.id_venta DESC";
    $stmtV = $db->prepare($sqlMisVentas);
    $stmtV->bindParam(':id',    $id_usuario);
    $stmtV->bindParam(':desde', $desdeV);
    $stmtV->bindParam(':hasta', $hastaV);
} else {
    $sqlMisVentas = "SELECT v.id_venta, v.fecha, v.total, pc.nombre AS cliente
                     FROM venta v
                     LEFT JOIN cliente c  ON v.id_cliente = c.id_cliente
                     LEFT JOIN persona pc ON c.id_persona = pc.id_persona
                     WHERE v.id_usuario = :id
                     ORDER BY v.fecha DESC, v.id_venta DESC
                     LIMIT 10";
    $stmtV = $db->prepare($sqlMisVentas);
    $stmtV->bindParam(':id', $id_usuario);
}
$stmtV->execute();
$misVentas = $stmtV->fetchAll(PDO::FETCH_ASSOC);

// KPIs del vendedor
$sqlKPI = "SELECT COUNT(*) AS mis_ventas,
                  COALESCE(SUM(total),0) AS mis_ingresos,
                  COALESCE(SUM(CASE WHEN fecha=CURDATE() THEN total ELSE 0 END),0) AS ingresos_hoy,
                  COALESCE(SUM(CASE WHEN fecha=CURDATE() THEN 1 ELSE 0 END),0) AS ventas_hoy
           FROM venta WHERE id_usuario = :id";
$stmtK = $db->prepare($sqlKPI);
$stmtK->bindParam(':id', $id_usuario);
$stmtK->execute();
$kpi = $stmtK->fetch(PDO::FETCH_ASSOC);

$titulo = 'Panel Vendedor';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar_vendedor.php';
?>

<style>
    .ventanet-btn-primary { background:#4A8C44;color:#fff;border-radius:10px;box-shadow:0 4px 12px rgba(74,140,68,.2);transition:transform .15s,box-shadow .2s,background .2s;font-family:'Outfit',sans-serif;font-weight:600; }
    .ventanet-btn-primary:hover { background:#376B32;transform:translateY(-2px); }
    .ventanet-input { background:#fff;border:1.5px solid #C9E4C5;border-radius:10px;color:#1C2E1A;font-family:'Outfit',sans-serif;font-size:.95rem;outline:none;transition:border-color .2s,box-shadow .2s; }
    .ventanet-input:focus { border-color:#4A8C44;box-shadow:0 0 0 4px rgba(74,140,68,.12); }
    @keyframes modalRise { from{opacity:0;transform:translateY(20px) scale(.98);}to{opacity:1;transform:translateY(0) scale(1);} }
    .modal-anim { animation:modalRise .3s cubic-bezier(.22,1,.36,1) forwards; }
    .stat-card { border-radius:16px;transition:transform .2s,box-shadow .2s; }
    .stat-card:hover { transform:translateY(-4px);box-shadow:0 10px 24px rgba(0,0,0,.08); }
    @keyframes fadeUp { from{opacity:0;transform:translateY(10px);}to{opacity:1;transform:translateY(0);} }
    .fade-up { animation:fadeUp .4s ease-out both; }
    @keyframes pulseAlert { 0%,100%{opacity:1;}50%{opacity:.8;} }
    .alert-banner { animation:pulseAlert 2.5s infinite; }
    .item-row { background:#f8fffe;border:1.5px solid #C9E4C5;border-radius:12px;padding:12px; }
    .tab-btn { transition:all .2s; }
    .tab-btn.active { background:#4A8C44;color:#fff;border-color:#4A8C44; }
</style>

<div class="font-sans-ventanet text-[#1C2E1A] max-w-7xl mx-auto">


    <!-- SweetAlert -->
    <?php if (isset($_SESSION['alert'])): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function(){
            Swal.fire({
                icon:'<?= htmlspecialchars($_SESSION['alert']['icon']) ?>',
                title:'<?= htmlspecialchars($_SESSION['alert']['title']) ?>',
                text:'<?= htmlspecialchars($_SESSION['alert']['text']) ?>',
                confirmButtonText:'Entendido',confirmButtonColor:'#4A8C44',
                customClass:{popup:'rounded-[20px]',confirmButton:'rounded-lg px-6 py-2.5 font-semibold'}
            });
        });
    </script>
    <?php unset($_SESSION['alert']); endif; ?>

    <!-- KPIs -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6 fade-up">
        <div class="stat-card bg-white border border-[#C9E4C5] p-5">
            <div class="flex items-center gap-3 mb-2"><div class="w-10 h-10 rounded-xl bg-[#DFF0DC] flex items-center justify-center"><i class="fas fa-receipt text-[#4A8C44] text-lg"></i></div><span class="text-xs font-bold text-[#4E6B4A] uppercase tracking-wide">Mis Ventas</span></div>
            <p class="text-3xl font-extrabold text-[#1C2E1A]"><?= intval($kpi['mis_ventas']??0) ?></p>
            <p class="text-xs text-[#96B092] mt-1">ventas totales</p>
        </div>
        <div class="stat-card bg-white border border-[#C9E4C5] p-5">
            <div class="flex items-center gap-3 mb-2"><div class="w-10 h-10 rounded-xl bg-[#DFF0DC] flex items-center justify-center"><i class="fas fa-dollar-sign text-[#4A8C44] text-lg"></i></div><span class="text-xs font-bold text-[#4E6B4A] uppercase tracking-wide">Mis Ingresos</span></div>
            <p class="text-2xl font-extrabold text-[#1C2E1A]">$<?= number_format($kpi['mis_ingresos']??0,0,',','.') ?></p>
            <p class="text-xs text-[#96B092] mt-1">acumulado total</p>
        </div>
        <div class="stat-card bg-white border border-[#C9E4C5] p-5">
            <div class="flex items-center gap-3 mb-2"><div class="w-10 h-10 rounded-xl bg-[#DFF0DC] flex items-center justify-center"><i class="fas fa-calendar-day text-[#4A8C44] text-lg"></i></div><span class="text-xs font-bold text-[#4E6B4A] uppercase tracking-wide">Ventas Hoy</span></div>
            <p class="text-3xl font-extrabold text-[#1C2E1A]"><?= intval($kpi['ventas_hoy']??0) ?></p>
            <p class="text-xs text-[#96B092] mt-1">ventas del dia</p>
        </div>
        <div class="stat-card bg-white border border-[#C9E4C5] p-5">
            <div class="flex items-center gap-3 mb-2"><div class="w-10 h-10 rounded-xl bg-[#DFF0DC] flex items-center justify-center"><i class="fas fa-coins text-[#4A8C44] text-lg"></i></div><span class="text-xs font-bold text-[#4E6B4A] uppercase tracking-wide">Ingresos Hoy</span></div>
            <p class="text-2xl font-extrabold text-[#1C2E1A]">$<?= number_format($kpi['ingresos_hoy']??0,0,',','.') ?></p>
            <p class="text-xs text-[#96B092] mt-1">del dia de hoy</p>
        </div>
    </div>

    <!-- MIS VENTAS (HU-016) -->
    <div id="tabVentas" class="fade-up mb-6">
        <div class="bg-white border border-[#C9E4C5] rounded-[20px] p-6">
            <div class="flex flex-col sm:flex-row items-start sm:items-end justify-between gap-3 mb-5">
                <h3 class="font-bold text-[#1C2E1A] text-lg flex items-center gap-2">
                    <i class="fas fa-clock text-[#4A8C44]"></i> Historial de mis ventas
                </h3>
                <!-- HU-016: Filtro por fecha -->
                <form method="GET" class="flex gap-2 items-end flex-wrap">
                    <div>
                        <label class="block text-xs font-bold text-[#4E6B4A] mb-1">Desde</label>
                        <input type="date" name="desde" value="<?= htmlspecialchars($desdeV) ?>" class="ventanet-input px-3 py-1.5 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-[#4E6B4A] mb-1">Hasta</label>
                        <input type="date" name="hasta" value="<?= htmlspecialchars($hastaV) ?>" class="ventanet-input px-3 py-1.5 text-sm">
                    </div>
                    <button type="submit" class="ventanet-btn-primary px-4 py-1.5 text-sm flex items-center gap-1">
                        <i class="fas fa-search text-xs"></i> Filtrar
                    </button>
                    <?php if ($desdeV || $hastaV): ?>
                    <a href="vendedor.php" class="px-4 py-1.5 text-sm text-[#4E6B4A] font-semibold bg-white border border-[#C9E4C5] hover:bg-[#F4FBF3] rounded-xl transition-colors">
                        Limpiar
                    </a>
                    <?php endif; ?>
                </form>
            </div>

            <?php if (empty($misVentas)): ?>
            <!-- HU-016 HU-3: Sin ventas en el periodo -->
            <div class="text-center py-10 text-[#96B092]">
                <i class="fas fa-receipt text-4xl mb-3"></i>
                <p class="font-semibold text-sm">
                    <?= ($desdeV || $hastaV) ? 'No hay ventas en el periodo seleccionado.' : 'Aun no tienes ventas registradas.' ?>
                </p>
                <?php if ($desdeV || $hastaV): ?>
                <p class="text-xs mt-1">Prueba con un rango de fechas diferente.</p>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-[#C9E4C5]">
                            <th class="pb-2 text-left text-xs font-bold text-[#4E6B4A] uppercase">Fecha</th>
                            <th class="pb-2 text-left text-xs font-bold text-[#4E6B4A] uppercase">Cliente</th>
                            <th class="pb-2 text-left text-xs font-bold text-[#4E6B4A] uppercase">Vendedor</th>
                            <th class="pb-2 text-right text-xs font-bold text-[#4E6B4A] uppercase">Total</th>
                            <th class="pb-2 text-center text-xs font-bold text-[#4E6B4A] uppercase">Detalle</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#f0f9ee]">
                        <?php foreach ($misVentas as $v): ?>
                        <tr class="hover:bg-[#f8fffe]">
                            <td class="py-2 text-[#4E6B4A]"><?= !empty($v['fecha']) ? date('d/m/Y', strtotime($v['fecha'])) : '---' ?></td>
                            <td class="py-2 font-semibold text-[#1C2E1A] truncate max-w-[120px]"><?= htmlspecialchars($v['cliente'] ?? 'Sin cliente') ?></td>
                            <td class="py-2 font-semibold text-[#1C2E1A] text-sm"><?= htmlspecialchars($nombre) ?></td>
                            <td class="py-2 text-right font-extrabold text-[#4A8C44]">$<?= number_format($v['total'],0,',','.') ?></td>
                            <td class="py-2 text-center">
                                <button type="button" onclick="verDetalle(<?= $v['id_venta'] ?>)"
                                    class="w-7 h-7 rounded-lg border border-[#C9E4C5] bg-white text-[#4A8C44] hover:bg-[#DFF0DC] transition-all flex items-center justify-center mx-auto">
                                    <i class="fas fa-eye text-xs"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- INVENTARIO (HU-015) -->
    <div id="tabInventario" class="fade-up" style="animation-delay:.1s">
        <div class="bg-white border border-[#C9E4C5] rounded-[20px] p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-bold text-[#1C2E1A] text-lg flex items-center gap-2">
                    <i class="fas fa-warehouse text-[#4A8C44]"></i> Productos disponibles
                </h3>
                <span class="text-xs text-[#96B092] font-medium"><?= count($inventario) ?> productos</span>
            </div>
            <!-- HU-015 HU-2: Buscador -->
            <div class="relative mb-4">
                <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-[#96B092] text-sm"></i>
                <input type="text" id="buscarInv" placeholder="Buscar producto por nombre o categoria..."
                    class="ventanet-input w-full pl-10 pr-4 py-2.5">
            </div>
            <!-- Filtros de estado -->
            <div class="flex gap-2 flex-wrap mb-4">
                <button onclick="filtrarInv('normal')" id="finv-normal" class="filtro-inv-btn px-3 py-1.5 rounded-xl text-xs font-bold border border-[#4A8C44] bg-[#4A8C44] text-white transition-all">Normal</button>
                <button onclick="filtrarInv('bajo')" id="finv-bajo" class="filtro-inv-btn px-3 py-1.5 rounded-xl text-xs font-bold border border-amber-200 bg-white text-amber-600 transition-all">Stock Bajo</button>
                <button onclick="filtrarInv('agotado')" id="finv-agotado" class="filtro-inv-btn px-3 py-1.5 rounded-xl text-xs font-bold border border-red-200 bg-white text-red-500 transition-all">Agotado</button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-[#C9E4C5]">
                            <th class="pb-2 text-left text-xs font-bold text-[#4E6B4A] uppercase">Producto</th>
                            <th class="pb-2 text-left text-xs font-bold text-[#4E6B4A] uppercase">Categoria</th>
                            <th class="pb-2 text-right text-xs font-bold text-[#4E6B4A] uppercase">Precio</th>
                            <th class="pb-2 text-center text-xs font-bold text-[#4E6B4A] uppercase">Stock</th>
                            <th class="pb-2 text-center text-xs font-bold text-[#4E6B4A] uppercase">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#f0f9ee]" id="tbodyInv">
                        <?php foreach ($inventario as $row):
                            $stock = intval($row['stock']);
                            if ($stock == 0)              { $est='agotado'; $sc='text-red-500'; }
                            elseif ($stock <= $STOCK_MIN) { $est='bajo';    $sc='text-amber-500'; }
                            else                          { $est='normal';  $sc='text-[#4A8C44]'; }
                        ?>
                        <tr class="hover:bg-[#f8fffe]"
                            data-estado="<?= $est ?>"
                            data-nombre="<?= strtolower(htmlspecialchars($row['producto'])) ?>"
                            data-cat="<?= strtolower(htmlspecialchars($row['categoria']??'')) ?>"
                            <?= $est !== 'normal' ? 'style="display:none"' : '' ?>>
                            <td class="py-2 font-semibold text-[#1C2E1A] truncate max-w-[130px]"><?= htmlspecialchars($row['producto']) ?></td>
                            <td class="py-2 text-[#4E6B4A] text-xs"><?= htmlspecialchars($row['categoria']??'Sin categoria') ?></td>
                            <td class="py-2 text-right text-[#4E6B4A]">$<?= number_format($row['precio']??0,0,',','.') ?></td>
                            <td class="py-2 text-center font-extrabold <?= $sc ?>"><?= $stock ?></td>
                            <td class="py-2 text-center">
                                <?php if ($est==='agotado'): ?>
                                    <span class="px-2 py-0.5 rounded-full text-[0.65rem] font-bold bg-red-100 text-red-700 border border-red-200">Agotado</span>
                                <?php elseif ($est==='bajo'): ?>
                                    <span class="px-2 py-0.5 rounded-full text-[0.65rem] font-bold bg-amber-100 text-amber-700 border border-amber-200">Stock Bajo</span>
                                <?php else: ?>
                                    <span class="px-2 py-0.5 rounded-full text-[0.65rem] font-bold bg-[#DFF0DC] text-[#376B32] border border-[#a8d6a2]">Normal</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div id="sinResultadosInv" class="hidden text-center py-6 text-[#96B092]">
                <i class="fas fa-search text-2xl mb-2"></i>
                <p class="text-sm">No se encontraron productos con ese criterio</p>
            </div>
        </div>
    </div>
</div>

<!-- MODAL NUEVA VENTA (HU-014) -->
<div id="modalVenta" class="fixed inset-0 bg-[#1C2E1A]/40 backdrop-blur-sm hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-[20px] w-full max-w-2xl shadow-2xl modal-anim overflow-hidden max-h-[90vh] flex flex-col">
        <div class="px-8 py-5 border-b border-[#C9E4C5] flex justify-between items-center bg-[#f2fcf1] flex-shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-[#4A8C44] text-white flex items-center justify-center shadow-md"><i class="fas fa-cash-register"></i></div>
                <h3 class="text-xl font-serif-ventanet text-[#1C2E1A] mt-1">Nueva Venta</h3>
            </div>
            <button onclick="cancelarVenta()" class="text-[#96B092] hover:text-[#4A8C44] transition-colors"><i class="fas fa-times text-xl"></i></button>
        </div>
        <form action="../../controllers/VentaController.php?accion=registrar" method="POST" class="p-8 space-y-5 overflow-y-auto">
            <!-- HU-014 HU-017: Cliente activo -->
            <div class="relative">
                <label class="block text-[.85rem] font-bold text-[#1C2E1A] mb-1.5">Cliente *</label>
                <?php if (empty($clientes)): ?>
                <div class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 text-amber-700 text-sm"><i class="fas fa-exclamation-triangle mr-2"></i>No hay clientes activos disponibles.</div>
                <?php else: ?>
                <select name="id_cliente" id="selectCliente" required class="ventanet-input w-full px-4 py-2.5 cursor-pointer appearance-none">
                    <option value=""> Selecciona un cliente </option>
                    <?php foreach ($clientes as $c): ?><option value="<?= $c['id_cliente'] ?>"><?= htmlspecialchars($c['nombre']) ?></option><?php endforeach; ?>
                </select>
                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 pt-6 text-[#4A8C44]"><i class="fas fa-chevron-down text-sm"></i></div>
                <?php endif; ?>
            </div>
            <!-- HU-014: Productos con stock -->
            <div>
                <div class="flex items-center justify-between mb-3">
                    <label class="block text-[.85rem] font-bold text-[#1C2E1A]">Productos *</label>
                    <button type="button" onclick="agregarItem()" class="text-xs font-bold text-[#4A8C44] border border-[#C9E4C5] bg-[#f2fcf1] hover:bg-[#DFF0DC] px-3 py-1.5 rounded-lg transition-all flex items-center gap-1">
                        <i class="fas fa-plus text-xs"></i> Agregar producto
                    </button>
                </div>
                <div id="itemsContainer" class="space-y-3"></div>
                <div class="mt-4 flex items-center justify-between bg-[#f2fcf1] border border-[#C9E4C5] rounded-xl px-5 py-3">
                    <span class="font-bold text-[#1C2E1A] text-sm">TOTAL</span>
                    <span class="text-2xl font-extrabold text-[#4A8C44]" id="totalVenta">$0</span>
                </div>
            </div>
            <div class="flex justify-end gap-3 pt-5 border-t border-[#C9E4C5]">
                <button type="button" onclick="cancelarVenta()" class="px-6 py-2 text-[#4E6B4A] font-semibold bg-white border border-[#C9E4C5] hover:bg-[#F4FBF3] rounded-xl transition-colors">Cancelar</button>
                <button type="submit" class="ventanet-btn-primary px-8 py-2"><i class="fas fa-check mr-2"></i> Confirmar Venta</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL DETALLE VENTA (HU-016 HU-4) -->
<div id="modalDetalle" class="fixed inset-0 bg-[#1C2E1A]/40 backdrop-blur-sm hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-[20px] w-full max-w-lg shadow-2xl modal-anim overflow-hidden">
        <div class="px-8 py-5 border-b border-[#C9E4C5] flex justify-between items-center bg-[#f2fcf1]">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-[#4A8C44] text-white flex items-center justify-center shadow-md"><i class="fas fa-receipt"></i></div>
                <h3 class="text-xl font-serif-ventanet text-[#1C2E1A] mt-1">Detalle de Venta</h3>
            </div>
            <button onclick="closeModal('modalDetalle')" class="text-[#96B092] hover:text-[#4A8C44] transition-colors"><i class="fas fa-times text-xl"></i></button>
        </div>
        <div class="p-8" id="detalleContenido">
            <div class="text-center py-8"><i class="fas fa-spinner fa-spin text-[#4A8C44] text-2xl"></i></div>
        </div>
    </div>
</div>

<script>
    var productosDisponibles = <?= json_encode($productos) ?>;
    var itemCount = 0;

    function openModal(id)  { document.getElementById(id).classList.remove('hidden'); }
    function closeModal(id) { document.getElementById(id).classList.add('hidden'); }

    // HU-014 HU-8: Cancelar limpia el formulario
    function cancelarVenta() {
        document.getElementById('itemsContainer').innerHTML = '';
        itemCount = 0;
        document.getElementById('totalVenta').textContent = '$0';
        closeModal('modalVenta');
    }

    // HU-014: Agregar producto con stock visible y limitado
    function agregarItem() {
        var container = document.getElementById('itemsContainer');
        var idx = itemCount++;
        var options = '<option value="">-- Producto --</option>';
        productosDisponibles.forEach(function(p) {
            var stockLabel = p.stock > 0 ? ' [' + p.stock + ' uds.]' : ' [AGOTADO]';
            var disabled   = p.stock <= 0 ? ' disabled' : '';
            options += '<option value="' + p.id_producto + '" data-precio="' + p.precio + '" data-stock="' + p.stock + '"' + disabled + '>' +
                p.nombre + ' ($' + Number(p.precio).toLocaleString('es-CO') + ')' + stockLabel + '</option>';
        });
        var div = document.createElement('div');
        div.className = 'item-row flex items-center gap-3';
        div.id = 'item-' + idx;
        div.innerHTML =
            '<div class="flex-1"><select name="id_producto[]" onchange="actualizarSubtotal(' + idx + ')" class="ventanet-input w-full px-3 py-2 text-sm">' + options + '</select></div>' +
            '<div class="w-20"><input type="number" name="cantidad[]" id="cant-' + idx + '" min="1" value="1" onchange="actualizarSubtotal(' + idx + ')" class="ventanet-input w-full px-3 py-2 text-sm text-center"></div>' +
            '<div class="w-28 text-right"><input type="hidden" name="subtotal[]" id="sub-' + idx + '" value="0"><span id="sub-display-' + idx + '" class="font-bold text-[#4A8C44] text-sm">$0</span></div>' +
            '<button type="button" onclick="eliminarItem(' + idx + ')" class="w-7 h-7 rounded-lg bg-red-50 text-red-400 hover:bg-red-100 flex items-center justify-center flex-shrink-0"><i class="fas fa-times text-xs"></i></button>';
        container.appendChild(div);
        calcularTotal();
    }

    function eliminarItem(idx) { var el=document.getElementById('item-'+idx); if(el){el.remove();calcularTotal();} }

    // HU-014 HU-4: Limitar cantidad al stock disponible
    function actualizarSubtotal(idx) {
        var select = document.querySelector('#item-' + idx + ' select');
        var cantInput = document.getElementById('cant-' + idx);
        var cant = parseInt(cantInput.value)||0;
        var precio=0, stock=0;
        if (select && select.selectedOptions[0]) {
            precio = parseFloat(select.selectedOptions[0].dataset.precio)||0;
            stock  = parseInt(select.selectedOptions[0].dataset.stock)||0;
        }
        if (cant > stock && stock > 0) { cantInput.value = stock; cant = stock; }
        cantInput.max = stock;
        var sub = precio * cant;
        document.getElementById('sub-'+idx).value = sub;
        document.getElementById('sub-display-'+idx).textContent = '$' + sub.toLocaleString('es-CO');
        calcularTotal();
    }

    function calcularTotal() {
        var total=0;
        document.querySelectorAll('input[name="subtotal[]"]').forEach(function(i){total+=parseFloat(i.value)||0;});
        document.getElementById('totalVenta').textContent = '$' + total.toLocaleString('es-CO');
    }

    // Agregar primer item al abrir modal
    document.querySelector('button[onclick="openModal(\'modalVenta\')"]').addEventListener('click', function(){
        setTimeout(function(){ if(document.getElementById('itemsContainer').children.length===0) agregarItem(); }, 100);
    });

    // HU-016 HU-4: Ver detalle de venta
    function verDetalle(id) {
        openModal('modalDetalle');
        document.getElementById('detalleContenido').innerHTML = '<div class="text-center py-8"><i class="fas fa-spinner fa-spin text-[#4A8C44] text-2xl"></i></div>';
        fetch('../../controllers/VentaController.php?accion=detalle&id=' + id)
            .then(function(r){return r.json();})
            .then(function(data){
                if (!data.length) { document.getElementById('detalleContenido').innerHTML='<p class="text-center text-[#96B092] py-8">Sin productos en esta venta.</p>'; return; }
                var html='<div class="space-y-3">';
                data.forEach(function(d){
                    html+='<div class="flex items-center justify-between py-2 border-b border-[#C9E4C5]"><div><p class="font-bold text-[#1C2E1A] text-sm">'+d.producto+'</p><p class="text-xs text-[#96B092]">Cantidad: '+d.cantidad+' x $'+Number(d.precio).toLocaleString('es-CO')+'</p></div><span class="font-extrabold text-[#4A8C44]">$'+Number(d.subtotal).toLocaleString('es-CO')+'</span></div>';
                });
                html+='</div>';
                document.getElementById('detalleContenido').innerHTML=html;
            })
            .catch(function(){ document.getElementById('detalleContenido').innerHTML='<p class="text-center text-red-500 py-8">Error al cargar el detalle.</p>'; });
    }

    // Tabs
    function showTab(tab) {
        ['tabVentas','tabInventario'].forEach(function(t){ document.getElementById(t).classList.add('hidden'); });
        document.getElementById(tab).classList.remove('hidden');
        document.getElementById('btnVentas').classList.remove('active');
        document.getElementById('btnInventario').classList.remove('active');
        if (tab==='tabVentas') document.getElementById('btnVentas').classList.add('active');
        else document.getElementById('btnInventario').classList.add('active');
    }

    // HU-017 HU-2 HU-3: Buscador de cliente en tiempo real
    var buscarClienteInput = document.getElementById('buscarCliente');
    if (buscarClienteInput) {
        buscarClienteInput.addEventListener('input', function() {
            var q = this.value.toLowerCase().trim();
            var select = document.getElementById('selectCliente');
            var options = select.querySelectorAll('option');
            var visibles = 0;
            options.forEach(function(opt) {
                if (!opt.value) return;
                var coincide = opt.textContent.toLowerCase().includes(q);
                opt.style.display = coincide ? '' : 'none';
                if (coincide) visibles++;
            });
            var noEncontrado = document.getElementById('clienteNoEncontrado');
            if (noEncontrado) noEncontrado.classList.toggle('hidden', visibles > 0 || q === '');
            if (select.selectedOptions[0] && select.selectedOptions[0].value) {
                if (!select.selectedOptions[0].textContent.toLowerCase().includes(q)) select.value = '';
            }
        });
    }

    // HU-015: Buscador y filtro de inventario
    var filtroInvActivo = 'normal';

    document.getElementById('buscarInv').addEventListener('input', aplicarFiltrosInv);

    function filtrarInv(estado) {
        filtroInvActivo = estado;
        document.querySelectorAll('.filtro-inv-btn').forEach(function(b){
            b.classList.remove('bg-[#4A8C44]','text-white','border-[#4A8C44]','bg-amber-500','bg-red-500');
            b.classList.add('bg-white');
        });
        var btn = document.getElementById('finv-' + estado);
        btn.classList.remove('bg-white');
        if (estado==='todos'||estado==='normal') btn.classList.add('bg-[#4A8C44]','text-white','border-[#4A8C44]');
        if (estado==='bajo')    btn.classList.add('bg-amber-500','text-white');
        if (estado==='agotado') btn.classList.add('bg-red-500','text-white');
        aplicarFiltrosInv();
    }

    function aplicarFiltrosInv() {
        var q = document.getElementById('buscarInv').value.toLowerCase().trim();
        var filas = document.querySelectorAll('#tbodyInv tr');
        var visibles = 0;
        filas.forEach(function(fila){
            var nombre = fila.dataset.nombre||'';
            var cat    = fila.dataset.cat||'';
            var estado = fila.dataset.estado||'';
            var ok = (nombre.includes(q)||cat.includes(q)) && (filtroInvActivo==='todos'||estado===filtroInvActivo);
            fila.style.display = ok ? '' : 'none';
            if (ok) visibles++;
        });
        document.getElementById('sinResultadosInv').classList.toggle('hidden', visibles>0);
    }

    // Aplicar filtro Normal al cargar la pagina
    window.addEventListener('load', function() {
        filtrarInv('normal');
    });
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
