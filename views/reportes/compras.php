<?php
// ============================================================
// Reporte de Compras — filtros por fecha y proveedor
// ============================================================
session_start();

if (!isset($_SESSION['usuario'])) {
    header("Location: ../usuarios/login.php");
    exit;
}

require_once __DIR__ . '/../../config/databse.php';
require_once __DIR__ . '/../../models/reporte.php';

$database     = new Database();
$db           = $database->conectar();
$reporteModel = new Reporte($db);

// Filtros
$desde   = $_GET['desde']   ?? date('Y-m-01');
$hasta   = $_GET['hasta']   ?? date('Y-m-d');
$idProv  = intval($_GET['id_proveedor'] ?? 0);

// Validar fechas
if ($desde > $hasta) $desde = $hasta;

$compras     = $reporteModel->reporteCompras($desde, $hasta, $idProv);
$proveedores = $reporteModel->listaProveedores();

// Resumen
$totalRegistros = count($compras);
$totalValor     = array_sum(array_column($compras, 'total'));
$promedio       = $totalRegistros > 0 ? $totalValor / $totalRegistros : 0;

// Nombre de archivo sugerido para cuando el usuario guarde el PDF
$nombreArchivoPDF = 'Reporte_Compras_' . date('Y-m-d', strtotime($desde)) . '_a_' . date('Y-m-d', strtotime($hasta));

$titulo = 'Reporte de Compras';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';
?>

<style>
    .btn-primario {
        background:#00875F;color:#fff;border-radius:10px;border:none;font-weight:600;
        font-family:'Outfit',sans-serif;cursor:pointer;
        transition:background .18s,transform .15s;
        box-shadow:0 4px 12px rgba(0,135,95,.22);
        display:inline-flex;align-items:center;gap:6px;padding:9px 20px;
        text-decoration:none;
    }
    .btn-primario:hover { background:#01614B;transform:translateY(-2px); }
    .btn-secundario {
        padding:9px 16px;border-radius:9px;border:1px solid #E5E7EB;background:#fff;
        color:#5F6673;font-size:.85rem;font-weight:600;text-decoration:none;
        display:inline-flex;align-items:center;gap:5px;cursor:pointer;
        font-family:'Outfit',sans-serif;transition:background .15s,border-color .15s;
    }
    .btn-secundario:hover { background:#F8F8F8;border-color:#00875F;color:#00875F; }
    .campo-input {
        background:#fff;border:1.5px solid #E5E7EB;border-radius:10px;
        color:#171717;font-family:'Outfit',sans-serif;font-size:.9rem;
        outline:none;padding:9px 12px;width:100%;
        transition:border-color .2s,box-shadow .2s;
    }
    .campo-input:focus { border-color:#61D0A7;box-shadow:0 0 0 4px rgba(97,208,167,.15); }
    .kpi { background:#fff;border:1px solid #E5E7EB;border-radius:14px;padding:18px; }
    .panel { background:#fff;border:1px solid #E5E7EB;border-radius:14px;overflow:hidden; }
    .panel-head { background:#F8F8F8;border-bottom:1px solid #E5E7EB;padding:14px 20px; }

    /* ===== Exportar a PDF (misma pestaña, sin abrir ventanas nuevas) ===== */
    .encabezado-impresion { display:none; }

    @media print {
        body * { visibility: hidden; }
        #reporteImprimir, #reporteImprimir * { visibility: visible; }
        #reporteImprimir {
            position: absolute; left: 0; top: 0; width: 100%; padding: 10px;
        }
        .no-imprimir { display: none !important; }

        /* Evitar que las filas y bloques se corten feo entre páginas */
        tr, .kpi, .panel { break-inside: avoid; page-break-inside: avoid; }
        thead { break-after: avoid; }

        .encabezado-impresion { display: block !important; margin-bottom: 16px; }

        /* Márgenes de página al imprimir/exportar */
        @page {
            margin: 15mm 12mm;
        }
    }
</style>

<div class="max-w-7xl mx-auto font-sans-ventanet" id="reporteImprimir">

    <!-- Encabezado solo visible al imprimir/exportar -->
    <div class="encabezado-impresion">
        <h2 style="font-size:1.4rem;font-weight:800;color:#01614B;">VentaNet — Reporte de Compras</h2>
        <p style="font-size:.8rem;color:#5F6673;">
            Período: <?= date('d/m/Y', strtotime($desde)) ?> al <?= date('d/m/Y', strtotime($hasta)) ?>
            <?php if ($idProv > 0):
                foreach ($proveedores as $pv) {
                    if ($pv['id_proveedor'] == $idProv) {
                        echo '· Proveedor: ' . htmlspecialchars($pv['nombre']);
                        break;
                    }
                }
            endif; ?>
            · Generado: <?= date('d/m/Y H:i') ?>
        </p>
    </div>

    <!-- Encabezado -->
    <div class="flex flex-col md:flex-row md:items-center justify-between mb-6 gap-4 no-imprimir">
        <div style="display:flex;align-items:center;gap:12px;">
            <a href="index.php"
               style="padding:8px 14px;border-radius:9px;border:1px solid #E5E7EB;background:#fff;color:#00875F;font-size:.82rem;font-weight:700;text-decoration:none;display:inline-flex;align-items:center;gap:5px;">
               <i class="fas fa-arrow-left" style="font-size:.7rem;"></i> Volver
            </a>
            <div>
                <h2 class="text-3xl font-bold font-serif-ventanet" style="color:#01614B;">Reporte de Compras</h2>
                <p class="text-sm mt-1" style="color:#5F6673;">Consulta las compras realizadas a tus proveedores</p>
            </div>
        </div>
        <button type="button" class="btn-primario" onclick="exportarPDF()" <?= empty($compras) ? 'disabled title="No hay datos para exportar"' : '' ?>>
            <i class="fas fa-file-pdf" style="font-size:.85rem;"></i> Exportar a PDF
        </button>
    </div>

    <!-- Filtros -->
    <form method="GET" class="panel mb-6 no-imprimir">
        <div class="panel-head">
            <h3 style="font-size:.88rem;font-weight:700;color:#171717;display:flex;align-items:center;gap:8px;">
                <i class="fas fa-filter" style="color:#00875F;"></i> Filtros
            </h3>
        </div>
        <div style="padding:18px;display:grid;grid-template-columns:1fr 1fr 1fr auto auto;gap:12px;align-items:end;flex-wrap:wrap;">
            <div>
                <label style="display:block;font-size:.78rem;font-weight:700;color:#5F6673;margin-bottom:5px;">Fecha inicial</label>
                <input type="date" name="desde" value="<?= htmlspecialchars($desde) ?>" class="campo-input">
            </div>
            <div>
                <label style="display:block;font-size:.78rem;font-weight:700;color:#5F6673;margin-bottom:5px;">Fecha final</label>
                <input type="date" name="hasta" value="<?= htmlspecialchars($hasta) ?>" class="campo-input">
            </div>
            <div style="position:relative;">
                <label style="display:block;font-size:.78rem;font-weight:700;color:#5F6673;margin-bottom:5px;">Proveedor</label>
                <select name="id_proveedor" class="campo-input" style="appearance:none;cursor:pointer;">
                    <option value="0">Todos los proveedores</option>
                    <?php foreach ($proveedores as $pv): ?>
                        <option value="<?= $pv['id_proveedor'] ?>" <?= $idProv == $pv['id_proveedor'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($pv['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <i class="fas fa-chevron-down" style="position:absolute;right:10px;bottom:11px;color:#00875F;font-size:.7rem;pointer-events:none;"></i>
            </div>
            <button type="submit" class="btn-primario">
                <i class="fas fa-search" style="font-size:.8rem;"></i> Buscar
            </button>
            <a href="compras.php" class="btn-secundario">
               <i class="fas fa-times" style="font-size:.75rem;"></i> Limpiar
            </a>
        </div>
        <div style="padding:0 18px 14px;font-size:.75rem;color:#9CA3AF;">
            Mostrando del <strong style="color:#171717;"><?= date('d/m/Y', strtotime($desde)) ?></strong>
            al <strong style="color:#171717;"><?= date('d/m/Y', strtotime($hasta)) ?></strong>
        </div>
    </form>

    <?php if (empty($compras)): ?>
    <!-- Sin resultados -->
    <div class="panel" style="padding:48px;text-align:center;">
        <div style="width:56px;height:56px;background:#F8F8F8;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;border:1px solid #E5E7EB;">
            <i class="fas fa-shopping-bag" style="color:#9CA3AF;font-size:1.3rem;"></i>
        </div>
        <p style="color:#5F6673;font-weight:600;">Sin compras en el período seleccionado.</p>
        <p style="color:#9CA3AF;font-size:.82rem;margin-top:4px;">Prueba con un rango de fechas diferente o limpia los filtros.</p>
    </div>

    <?php else: ?>

    <!-- KPIs -->
    <div class="grid grid-cols-3 gap-4 mb-6">
        <div class="kpi">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
                <div style="width:36px;height:36px;background:#DDF5EC;border-radius:9px;display:flex;align-items:center;justify-content:center;">
                    <i class="fas fa-shopping-bag" style="color:#00875F;font-size:.85rem;"></i>
                </div>
                <span style="font-size:.7rem;font-weight:700;color:#5F6673;text-transform:uppercase;">Total compras</span>
            </div>
            <p style="font-size:1.8rem;font-weight:800;color:#171717;line-height:1;"><?= $totalRegistros ?></p>
            <p style="font-size:.72rem;color:#9CA3AF;margin-top:3px;">en el período</p>
        </div>
        <div class="kpi">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
                <div style="width:36px;height:36px;background:#DDF5EC;border-radius:9px;display:flex;align-items:center;justify-content:center;">
                    <i class="fas fa-dollar-sign" style="color:#00875F;font-size:.85rem;"></i>
                </div>
                <span style="font-size:.7rem;font-weight:700;color:#5F6673;text-transform:uppercase;">Valor total</span>
            </div>
            <p style="font-size:1.3rem;font-weight:800;color:#171717;line-height:1;">$<?= number_format($totalValor, 0, ',', '.') ?></p>
            <p style="font-size:.72rem;color:#9CA3AF;margin-top:3px;">total comprado</p>
        </div>
        <div class="kpi">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
                <div style="width:36px;height:36px;background:#DDF5EC;border-radius:9px;display:flex;align-items:center;justify-content:center;">
                    <i class="fas fa-chart-line" style="color:#00875F;font-size:.85rem;"></i>
                </div>
                <span style="font-size:.7rem;font-weight:700;color:#5F6673;text-transform:uppercase;">Promedio</span>
            </div>
            <p style="font-size:1.3rem;font-weight:800;color:#171717;line-height:1;">$<?= number_format($promedio, 0, ',', '.') ?></p>
            <p style="font-size:.72rem;color:#9CA3AF;margin-top:3px;">por compra</p>
        </div>
    </div>

    <!-- Tabla -->
    <div class="panel">
        <div class="panel-head" style="display:flex;align-items:center;justify-content:space-between;">
            <h3 style="font-size:.88rem;font-weight:700;color:#171717;">Historial de compras</h3>
            <span style="font-size:.78rem;color:#5F6673;"><?= $totalRegistros ?> registro(s)</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr style="background:#01614B;">
                        <th class="px-5 py-3 text-xs font-bold uppercase tracking-wide" style="color:#fff;">ID</th>
                        <th class="px-5 py-3 text-xs font-bold uppercase tracking-wide" style="color:#fff;">Fecha</th>
                        <th class="px-5 py-3 text-xs font-bold uppercase tracking-wide" style="color:#fff;">Proveedor</th>
                        <th class="px-5 py-3 text-xs font-bold uppercase tracking-wide" style="color:#fff;">Usuario</th>
                        <th class="px-5 py-3 text-xs font-bold uppercase tracking-wide text-right" style="color:#fff;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($compras as $c): ?>
                    <tr style="border-bottom:1px solid #E5E7EB;transition:background .15s;"
                        onmouseover="this.style.background='#F8F8F8'"
                        onmouseout="this.style.background=''">
                        <td class="px-5 py-3 text-sm" style="color:#9CA3AF;">#<?= $c['id_compra'] ?></td>
                        <td class="px-5 py-3 text-sm" style="color:#171717;"><?= date('d/m/Y', strtotime($c['fecha'])) ?></td>
                        <td class="px-5 py-3 text-sm font-bold" style="color:#171717;"><?= htmlspecialchars($c['proveedor'] ?? '---') ?></td>
                        <td class="px-5 py-3 text-sm" style="color:#5F6673;"><?= htmlspecialchars($c['comprador'] ?? '---') ?></td>
                        <td class="px-5 py-3 text-sm font-bold text-right" style="color:#00875F;">$<?= number_format($c['total'], 0, ',', '.') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background:#F8F8F8;border-top:2px solid #E5E7EB;">
                        <td colspan="4" class="px-5 py-3 font-bold text-sm" style="color:#171717;">TOTAL</td>
                        <td class="px-5 py-3 font-bold text-right" style="color:#00875F;font-size:1rem;">$<?= number_format($totalValor, 0, ',', '.') ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    <?php endif; ?>

</div>

<script>
    // Guarda el título original para restaurarlo después de exportar
    const tituloOriginal = document.title;

    function exportarPDF() {
        // Cambia el título temporalmente: el navegador lo usa como nombre sugerido del PDF
        document.title = "<?= $nombreArchivoPDF ?>";
        window.print();
    }

    // Restaura el título normal cuando termina el diálogo de impresión
    window.onafterprint = function () {
        document.title = tituloOriginal;
    };
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>