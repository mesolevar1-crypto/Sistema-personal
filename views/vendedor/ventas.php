<?php
session_start();
if (!isset($_SESSION['usuario'])) { header("Location: ../usuarios/login.php"); exit; }
if (strtolower($_SESSION['usuario']['rol']) === 'administrador') { header("Location: ../venta/index.php"); exit; }

require_once __DIR__ . '/../../config/databse.php';
require_once __DIR__ . '/../../models/venta.php';

$database   = new Database();
$db         = $database->conectar();
$ventaModel = new Venta($db);
$resumen    = $ventaModel->obtenerResumen();
$clientes   = $ventaModel->obtenerClientes();
$productos  = $ventaModel->obtenerProductos();

$id_usuario = $_SESSION['usuario']['id_usuario'];

// Solo las ventas del vendedor autenticado
$sqlV = "SELECT v.id_venta, v.fecha, v.total, pc.nombre AS cliente
         FROM venta v
         LEFT JOIN cliente c  ON v.id_cliente = c.id_cliente
         LEFT JOIN persona pc ON c.id_persona = pc.id_persona
         WHERE v.id_usuario = :id
         ORDER BY v.fecha DESC, v.id_venta DESC";
$stmtV = $db->prepare($sqlV);
$stmtV->bindParam(':id', $id_usuario);
$stmtV->execute();
$ventas = $stmtV->fetchAll(PDO::FETCH_ASSOC);

// KPIs propios
$sqlK = "SELECT COUNT(*) AS total_ventas,
                COALESCE(SUM(total),0) AS ingresos_total,
                COALESCE(SUM(CASE WHEN fecha=CURDATE() THEN 1 ELSE 0 END),0) AS ventas_hoy,
                COALESCE(SUM(CASE WHEN fecha=CURDATE() THEN total ELSE 0 END),0) AS ingresos_hoy
         FROM venta WHERE id_usuario = :id";
$stmtK = $db->prepare($sqlK);
$stmtK->bindParam(':id', $id_usuario);
$stmtK->execute();
$kpi = $stmtK->fetch(PDO::FETCH_ASSOC);

$titulo = 'Mis Ventas';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar_vendedor.php';
?>

<style>
    .ventanet-btn-primary { background:#4A8C44;color:#fff;border-radius:10px;box-shadow:0 4px 12px rgba(74,140,68,.2);transition:transform .15s,box-shadow .2s,background .2s;font-family:'Outfit',sans-serif;font-weight:600; }
    .ventanet-btn-primary:hover { background:#376B32;transform:translateY(-2px); }
    .ventanet-btn-danger { background:#dc2626;color:#fff;border-radius:10px;transition:transform .15s,background .2s;font-family:'Outfit',sans-serif;font-weight:600; }
    .ventanet-btn-danger:hover { background:#b91c1c;transform:translateY(-2px); }
    .ventanet-input { background:#fff;border:1.5px solid #C9E4C5;border-radius:10px;color:#1C2E1A;font-family:'Outfit',sans-serif;font-size:.95rem;outline:none;transition:border-color .2s,box-shadow .2s; }
    .ventanet-input:focus { border-color:#4A8C44;box-shadow:0 0 0 4px rgba(74,140,68,.12); }
    @keyframes modalRise { from{opacity:0;transform:translateY(20px) scale(.98);}to{opacity:1;transform:translateY(0) scale(1);} }
    .modal-anim { animation:modalRise .3s cubic-bezier(.22,1,.36,1) forwards; }
    .stat-card { border-radius:16px;transition:transform .2s,box-shadow .2s; }
    .stat-card:hover { transform:translateY(-4px);box-shadow:0 10px 24px rgba(0,0,0,.08); }
    @keyframes rowIn { from{opacity:0;transform:translateX(-8px);}to{opacity:1;transform:translateX(0);} }
    .row-anim { animation:rowIn .3s ease-out both; }
    .item-row { background:#f8fffe;border:1.5px solid #C9E4C5;border-radius:12px;padding:12px;margin-bottom:10px; }
</style>

<div class="font-sans-ventanet text-[#1C2E1A] max-w-7xl mx-auto">

    <div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
        <div>
            <h2 class="text-5xl font-extrabold font-serif-ventanet text-green-800 leading-tight">Mis Ventas</h2>
            <p class="text-[#4E6B4A] text-sm mt-1 font-medium tracking-wide">Registra y consulta tus ventas realizadas</p>
        </div>
        <button onclick="openModal('modalCrear')" class="ventanet-btn-primary px-5 py-2.5 flex items-center gap-2 self-start md:self-auto">
            <i class="fas fa-plus-circle"></i> Nueva Venta
        </button>
    </div>

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

    <!-- KPIs solo del vendedor -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div class="stat-card bg-white border border-[#C9E4C5] p-5">
            <div class="flex items-center gap-3 mb-2"><div class="w-10 h-10 rounded-xl bg-[#DFF0DC] flex items-center justify-center"><i class="fas fa-receipt text-[#4A8C44] text-lg"></i></div><span class="text-xs font-bold text-[#4E6B4A] uppercase tracking-wide">Mis Ventas</span></div>
            <p class="text-3xl font-extrabold text-[#1C2E1A]"><?= intval($kpi['total_ventas']??0) ?></p>
            <p class="text-xs text-[#96B092] mt-1">ventas registradas</p>
        </div>
        <div class="stat-card bg-white border border-[#C9E4C5] p-5">
            <div class="flex items-center gap-3 mb-2"><div class="w-10 h-10 rounded-xl bg-[#DFF0DC] flex items-center justify-center"><i class="fas fa-dollar-sign text-[#4A8C44] text-lg"></i></div><span class="text-xs font-bold text-[#4E6B4A] uppercase tracking-wide">Mis Ingresos</span></div>
            <p class="text-2xl font-extrabold text-[#1C2E1A]">$<?= number_format($kpi['ingresos_total']??0,0,',','.') ?></p>
            <p class="text-xs text-[#96B092] mt-1">acumulado</p>
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

    <!-- Tabla solo con las ventas del vendedor -->
    <div class="overflow-x-auto rounded-[16px] border border-[#C9E4C5] bg-[#ebf7ea]">
        <table class="w-full text-left border-collapse">
            <thead class="bg-[#dcf0d8] text-[#3c5938] border-b border-[#C9E4C5]">
                <tr>
                    <th class="p-4 font-bold text-[0.8rem] tracking-wide uppercase">Fecha</th>
                    <th class="p-4 font-bold text-[0.8rem] tracking-wide uppercase">Cliente</th>
                    <th class="p-4 font-bold text-[0.8rem] tracking-wide uppercase">Vendedor</th>
                    <th class="p-4 font-bold text-[0.8rem] tracking-wide uppercase text-right">Total</th>
                    <th class="p-4 font-bold text-[0.8rem] tracking-wide uppercase text-center">Detalle</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#C9E4C5]">
                <?php if (!empty($ventas)): ?>
                    <?php foreach ($ventas as $idx => $v): ?>
                    <tr class="row-anim hover:bg-[#e4f5e2] transition-colors" style="animation-delay:<?= $idx*.04 ?>s">
                        <td class="p-4 text-[#1C2E1A] font-medium text-sm"><?= !empty($v['fecha']) ? date('d/m/Y', strtotime($v['fecha'])) : '---' ?></td>
                        <td class="p-4 font-semibold text-[#1C2E1A] text-sm"><?= htmlspecialchars($v['cliente'] ?? 'Sin cliente') ?></td>
                        <td class="p-4 font-semibold text-[#1C2E1A] text-sm"><?= htmlspecialchars($nombre) ?></td>
                        <td class="p-4 text-right"><span class="text-lg font-extrabold text-[#4A8C44]">$<?= number_format($v['total'],0,',','.') ?></span></td>
                        <td class="p-4 text-center">
                            <button type="button" onclick="verDetalle(<?= $v['id_venta'] ?>)"
                                class="w-8 h-8 rounded-lg border border-[#C9E4C5] bg-white text-[#4A8C44] hover:bg-[#DFF0DC] hover:border-[#4A8C44] transition-all flex items-center justify-center shadow-sm mx-auto">
                                <i class="fas fa-eye text-xs"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5" class="p-16 text-center">
                        <div class="w-20 h-20 mx-auto bg-[#e4f5e2] rounded-full flex items-center justify-center text-[#96B092] text-3xl mb-4 border-4 border-white shadow"><i class="fas fa-cash-register"></i></div>
                        <p class="text-[#4E6B4A] font-semibold text-lg">Aun no tienes ventas registradas</p>
                    </td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL NUEVA VENTA -->
<div id="modalCrear" class="fixed inset-0 bg-[#1C2E1A]/40 backdrop-blur-sm hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-[20px] w-full max-w-2xl shadow-2xl modal-anim overflow-hidden max-h-[90vh] flex flex-col">
        <div class="px-8 py-5 border-b border-[#C9E4C5] flex justify-between items-center bg-[#f2fcf1] flex-shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-[#4A8C44] text-white flex items-center justify-center shadow-md"><i class="fas fa-cash-register"></i></div>
                <h3 class="text-xl font-serif-ventanet text-[#1C2E1A] mt-1">Nueva Venta</h3>
            </div>
            <button onclick="cancelarVenta()" class="text-[#96B092] hover:text-[#4A8C44] transition-colors"><i class="fas fa-times text-xl"></i></button>
        </div>
        <form action="../../controllers/VentaController.php?accion=registrar" method="POST" class="p-8 space-y-5 overflow-y-auto">
            <div class="relative">
                <label class="block text-[.85rem] font-bold text-[#1C2E1A] mb-1.5">Cliente *</label>
                <?php if (empty($clientes)): ?>
                <div class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 text-amber-700 text-sm"><i class="fas fa-exclamation-triangle mr-2"></i>No hay clientes activos.</div>
                <?php else: ?>
                <select name="id_cliente" id="selectCliente" required class="ventanet-input w-full px-4 py-2.5 cursor-pointer appearance-none">
                    <option value="">-- Selecciona un cliente --</option>
                    <?php foreach ($clientes as $c): ?><option value="<?= $c['id_cliente'] ?>"><?= htmlspecialchars($c['nombre']) ?></option><?php endforeach; ?>
                </select>
                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 pt-6 text-[#4A8C44]"><i class="fas fa-chevron-down text-sm"></i></div>
                <?php endif; ?>
            </div>
            <div>
                <div class="flex items-center justify-between mb-3">
                    <label class="block text-[.85rem] font-bold text-[#1C2E1A]">Productos *</label>
                    <button type="button" onclick="agregarItem()" class="text-xs font-bold text-[#4A8C44] border border-[#C9E4C5] bg-[#f2fcf1] hover:bg-[#DFF0DC] px-3 py-1.5 rounded-lg transition-all flex items-center gap-1"><i class="fas fa-plus text-xs"></i> Agregar</button>
                </div>
                <div id="itemsContainer" class="space-y-3"></div>
                <div class="mt-4 flex items-center justify-between bg-[#f2fcf1] border border-[#C9E4C5] rounded-xl px-5 py-3">
                    <span class="font-bold text-[#1C2E1A] text-sm">TOTAL</span>
                    <span class="text-2xl font-extrabold text-[#4A8C44]" id="totalVenta">$0</span>
                </div>
            </div>
            <div class="flex justify-end gap-3 pt-5 border-t border-[#C9E4C5]">
                <button type="button" onclick="cancelarVenta()" class="px-6 py-2 text-[#4E6B4A] font-semibold bg-white border border-[#C9E4C5] hover:bg-[#F4FBF3] rounded-xl transition-colors">Cancelar</button>
                <button type="submit" class="ventanet-btn-primary px-8 py-2"><i class="fas fa-check mr-2"></i>Confirmar Venta</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL DETALLE -->
<div id="modalDetalle" class="fixed inset-0 bg-[#1C2E1A]/40 backdrop-blur-sm hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-[20px] w-full max-w-lg shadow-2xl modal-anim overflow-hidden">
        <div class="px-8 py-5 border-b border-[#C9E4C5] flex justify-between items-center bg-[#f2fcf1]">
            <div class="flex items-center gap-3"><div class="w-10 h-10 rounded-xl bg-[#4A8C44] text-white flex items-center justify-center shadow-md"><i class="fas fa-receipt"></i></div><h3 class="text-xl font-serif-ventanet text-[#1C2E1A] mt-1">Detalle de Venta</h3></div>
            <button onclick="closeModal('modalDetalle')" class="text-[#96B092] hover:text-[#4A8C44] transition-colors"><i class="fas fa-times text-xl"></i></button>
        </div>
        <div class="p-8" id="detalleContenido"><div class="text-center py-8"><i class="fas fa-spinner fa-spin text-[#4A8C44] text-2xl"></i></div></div>
    </div>
</div>

<script>
    var productosDisponibles = <?= json_encode($productos) ?>;
    var itemCount = 0;
    function openModal(id)  { document.getElementById(id).classList.remove('hidden'); }
    function closeModal(id) { document.getElementById(id).classList.add('hidden'); }
    function cancelarVenta() { document.getElementById('itemsContainer').innerHTML=''; itemCount=0; document.getElementById('totalVenta').textContent='$0'; closeModal('modalCrear'); }
    function agregarItem() {
        var idx=itemCount++; var options='<option value="">-- Producto --</option>';
        productosDisponibles.forEach(function(p){ var sl=p.stock>0?' ['+p.stock+' uds.]':' [AGOTADO]'; var dis=p.stock<=0?' disabled':''; options+='<option value="'+p.id_producto+'" data-precio="'+p.precio+'" data-stock="'+p.stock+'"'+dis+'>'+p.nombre+' ($'+Number(p.precio).toLocaleString('es-CO')+')'+sl+'</option>'; });
        var div=document.createElement('div'); div.className='item-row flex items-center gap-3'; div.id='item-'+idx;
        div.innerHTML='<div class="flex-1"><select name="id_producto[]" onchange="actualizarSubtotal('+idx+')" class="ventanet-input w-full px-3 py-2 text-sm">'+options+'</select></div><div class="w-20"><input type="number" name="cantidad[]" id="cant-'+idx+'" min="1" value="1" onchange="actualizarSubtotal('+idx+')" class="ventanet-input w-full px-3 py-2 text-sm text-center"></div><div class="w-28 text-right"><input type="hidden" name="subtotal[]" id="sub-'+idx+'" value="0"><span id="sub-display-'+idx+'" class="font-bold text-[#4A8C44] text-sm">$0</span></div><button type="button" onclick="eliminarItem('+idx+')" class="w-7 h-7 rounded-lg bg-red-50 text-red-400 hover:bg-red-100 flex items-center justify-center flex-shrink-0"><i class="fas fa-times text-xs"></i></button>';
        document.getElementById('itemsContainer').appendChild(div); calcularTotal();
    }
    function eliminarItem(idx){ var el=document.getElementById('item-'+idx); if(el){el.remove();calcularTotal();} }
    function actualizarSubtotal(idx){ var sel=document.querySelector('#item-'+idx+' select'); var cant=parseInt(document.getElementById('cant-'+idx).value)||0; var precio=0,stock=0; if(sel&&sel.selectedOptions[0]){precio=parseFloat(sel.selectedOptions[0].dataset.precio)||0;stock=parseInt(sel.selectedOptions[0].dataset.stock)||0;} if(cant>stock&&stock>0){document.getElementById('cant-'+idx).value=stock;cant=stock;} document.getElementById('cant-'+idx).max=stock; var sub=precio*cant; document.getElementById('sub-'+idx).value=sub; document.getElementById('sub-display-'+idx).textContent='$'+sub.toLocaleString('es-CO'); calcularTotal(); }
    function calcularTotal(){ var t=0; document.querySelectorAll('input[name="subtotal[]"]').forEach(function(i){t+=parseFloat(i.value)||0;}); document.getElementById('totalVenta').textContent='$'+t.toLocaleString('es-CO'); }
    function verDetalle(id){ openModal('modalDetalle'); document.getElementById('detalleContenido').innerHTML='<div class="text-center py-8"><i class="fas fa-spinner fa-spin text-[#4A8C44] text-2xl"></i></div>'; fetch('../../controllers/VentaController.php?accion=detalle&id='+id).then(function(r){return r.json();}).then(function(data){ if(!data.length){document.getElementById('detalleContenido').innerHTML='<p class="text-center text-[#96B092] py-8">Sin productos.</p>';return;} var html='<div class="space-y-3">'; data.forEach(function(d){html+='<div class="flex items-center justify-between py-2 border-b border-[#C9E4C5]"><div><p class="font-bold text-[#1C2E1A] text-sm">'+d.producto+'</p><p class="text-xs text-[#96B092]">Cantidad: '+d.cantidad+' x $'+Number(d.precio).toLocaleString('es-CO')+'</p></div><span class="font-extrabold text-[#4A8C44]">$'+Number(d.subtotal).toLocaleString('es-CO')+'</span></div>';}); html+='</div>'; document.getElementById('detalleContenido').innerHTML=html; }).catch(function(){document.getElementById('detalleContenido').innerHTML='<p class="text-center text-red-500 py-8">Error.</p>';}); }
    document.querySelector('button[onclick="openModal(\'modalCrear\')"]').addEventListener('click',function(){setTimeout(function(){if(document.getElementById('itemsContainer').children.length===0)agregarItem();},100);});
    var buscarClienteInput=document.getElementById('buscarCliente');
    if(buscarClienteInput){buscarClienteInput.addEventListener('input',function(){var q=this.value.toLowerCase().trim();var sel=document.getElementById('selectCliente');var opts=sel.querySelectorAll('option');var vis=0;opts.forEach(function(o){if(!o.value)return;var ok=o.textContent.toLowerCase().includes(q);o.style.display=ok?'':'none';if(ok)vis++;});var ne=document.getElementById('clienteNoEncontrado');if(ne)ne.classList.toggle('hidden',vis>0||q==='');});}
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
