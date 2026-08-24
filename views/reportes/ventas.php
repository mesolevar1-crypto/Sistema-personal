<?php
// ============================================================
// Reporte de Ventas — filtros por fecha y usuario
// Ganancia: (precio_venta - precio_compra_promedio) × cantidad
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
$desde      = $_GET['desde']      ?? date('Y-m-01');
$hasta      = $_GET['hasta']      ?? date('Y-m-d');
$idUsuario  = intval($_GET['id_usuario'] ?? 0);
if ($desde > $hasta) $desde = $hasta;

$ventas   = $reporteModel->reporteVentas($desde, $hasta, $idUsuario);
$usuarios = $reporteModel->listaUsuarios();

// Resumen
$totalRegistros = count($ventas);
$totalVendido   = array_sum(array_column($ventas, 'total'));
$totalGanancia  = array_sum(array_column($ventas, 'ganancia'));
$margenGeneral  = $totalVendido > 0
    ? round(($totalGanancia / $totalVendido) * 100, 1)
    : 0;

// Nombre de archivo sugerido para el PDF
$nombreArchivoPDF = 'Reporte_Ventas_' . date('Y-m-d', strtotime($desde)) . '_a_' . date('Y-m-d', strtotime($hasta));

$titulo = 'Reporte de Ventas';
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
    .btn-primario:disabled { background:#9CA3AF;cursor:not-allowed;box-shadow:none;transform:none; }
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
    .kpi  { background:#fff;border:1px solid #E5E7EB;border-radius:14px;padding:18px; }
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
        .kpi, .panel { break-inside: avoid; box-shadow: none !important; }
        tr { break-inside: avoid; page-break-inside: avoid; }
        thead { break-after: avoid; }
        .encabezado-impresion { display: block !important; margin-bottom: 16px; }

        @page {
            margin: 15mm 12mm;
        }
    }
</style>

<div class="max-w-7xl mx-auto font-sans-ventanet" id="reporteImprimir">

    <!-- Encabezado solo visible al imprimir/exportar -->
    <div class="encabezado-impresion">
        <h2 style="font-size:1.4rem;font-weight:800;color:#01614B;">VentaNet — Reporte de Ventas</h2>
        <p style="font-size:.8rem;color:#5F6673;">
            Período: <?= date('d/m/Y', strtotime($desde)) ?> al <?= date('d/m/Y', strtotime($hasta)) ?>
            <?php if ($idUsuario > 0):
                foreach ($usuarios as $u) {
                    if ($u['id_usuario'] == $idUsuario) {
                        echo '· Usuario: ' . htmlspecialchars($u['nombre']);
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
                <h2 class="text-3xl font-bold font-serif-ventanet" style="color:#01614B;">Reporte de Ventas</h2>
                <p class="text-sm mt-1" style="color:#5F6673;">Consulta las ventas realizadas y sus ganancias</p>
            </div>
        </div>
        <button type="button" class="btn-primario" onclick="exportarPDF()" <?= empty($ventas) ? 'disabled title="No hay datos para exportar"' : '' ?>>
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
        <div style="padding:18px;display:grid;grid-template-columns:1fr 1fr 1fr auto auto;gap:12px;align-items:end;">
            <div>
                <label style="display:block;font-size:.78rem;font-weight:700;color:#5F6673;margin-bottom:5px;">Fecha inicial</label>
                <input type="date" name="desde" value="<?= htmlspecialchars($desde) ?>" class="campo-input">
            </div>
            <div>
                <label style="display:block;font-size:.78rem;font-weight:700;color:#5F6673;margin-bottom:5px;">Fecha final</label>
                <input type="date" name="hasta" value="<?= htmlspecialchars($hasta) ?>" class="campo-input">
            </div>
            <div style="position:relative;">
                <label style="display:block;font-size:.78rem;font-weight:700;color:#5F6673;margin-bottom:5px;">Usuario</label>
                <select name="id_usuario" class="campo-input" style="appearance:none;cursor:pointer;">
                    <option value="0">Todos los usuarios</option>
                    <?php foreach ($usuarios as $u): ?>
                        <option value="<?= $u['id_usuario'] ?>" <?= $idUsuario == $u['id_usuario'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($u['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <i class="fas fa-chevron-down" style="position:absolute;right:10px;bottom:11px;color:#00875F;font-size:.7rem;pointer-events:none;"></i>
            </div>
            <button type="submit" class="btn-primario">
                <i class="fas fa-search" style="font-size:.8rem;"></i> Buscar
            </button>
            <a href="ventas.php" class="btn-secundario">
               <i class="fas fa-times" style="font-size:.75rem;"></i> Limpiar
            </a>
        </div>
        <div style="padding:0 18px 14px;font-size:.75rem;color:#9CA3AF;">
            Mostrando del <strong style="color:#171717;"><?= date('d/m/Y', strtotime($desde)) ?></strong>
            al <strong style="color:#171717;"><?= date('d/m/Y', strtotime($hasta)) ?></strong>
        </div>
    </form>

    <?php if (empty($ventas)): ?>
    <!-- Sin resultados -->
    <div class="panel" style="padding:48px;text-align:center;">
        <div style="width:56px;height:56px;background:#F8F8F8;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;border:1px solid #E5E7EB;">
            <i class="fas fa-receipt" style="color:#9CA3AF;font-size:1.3rem;"></i>
        </div>
        <p style="color:#5F6673;font-weight:600;">Sin ventas en el período seleccionado.</p>
        <p style="color:#9CA3AF;font-size:.82rem;margin-top:4px;">Prueba con un rango de fechas diferente o limpia los filtros.</p>
    </div>

    <?php else: ?>

    <!-- KPIs -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">

        <div class="kpi">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
                <div style="width:36px;height:36px;background:#DDF5EC;border-radius:9px;display:flex;align-items:center;justify-content:center;">
                    <i class="fas fa-receipt" style="color:#00875F;font-size:.85rem;"></i>
                </div>
                <span style="font-size:.7rem;font-weight:700;color:#5F6673;text-transform:uppercase;">Cantidad</span>
            </div>
            <p style="font-size:1.8rem;font-weight:800;color:#171717;line-height:1;"><?= $totalRegistros ?></p>
            <p style="font-size:.72rem;color:#9CA3AF;margin-top:3px;">ventas registradas</p>
        </div>

        <div class="kpi">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
                <div style="width:36px;height:36px;background:#DDF5EC;border-radius:9px;display:flex;align-items:center;justify-content:center;">
                    <i class="fas fa-dollar-sign" style="color:#00875F;font-size:.85rem;"></i>
                </div>
                <span style="font-size:.7rem;font-weight:700;color:#5F6673;text-transform:uppercase;">Total vendido</span>
            </div>
            <p style="font-size:1.3rem;font-weight:800;color:#171717;line-height:1;">$<?= number_format($totalVendido, 0, ',', '.') ?></p>
            <p style="font-size:.72rem;color:#9CA3AF;margin-top:3px;">en el período</p>
        </div>

        <?php $colorGan = $totalGanancia >= 0 ? '#00875F' : '#E53935'; ?>
        <div class="kpi" style="border-color:<?= $totalGanancia < 0 ? '#fde8e8' : '#E5E7EB' ?>;">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
                <div style="width:36px;height:36px;background:<?= $totalGanancia >= 0 ? '#DDF5EC' : '#fde8e8' ?>;border-radius:9px;display:flex;align-items:center;justify-content:center;">
                    <i class="fas fa-chart-line" style="color:<?= $colorGan ?>;font-size:.85rem;"></i>
                </div>
                <span style="font-size:.7rem;font-weight:700;color:#5F6673;text-transform:uppercase;">Ganancia total</span>
            </div>
            <p style="font-size:1.3rem;font-weight:800;color:<?= $colorGan ?>;line-height:1;">
                $<?= number_format(abs($totalGanancia), 0, ',', '.') ?>
            </p>
            <p style="font-size:.72rem;color:#9CA3AF;margin-top:3px;"><?= $totalGanancia >= 0 ? 'ganancia neta' : 'pérdida' ?></p>
        </div>

        <div class="kpi">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
                <div style="width:36px;height:36px;background:#DDF5EC;border-radius:9px;display:flex;align-items:center;justify-content:center;">
                    <i class="fas fa-percent" style="color:#00875F;font-size:.85rem;"></i>
                </div>
                <span style="font-size:.7rem;font-weight:700;color:#5F6673;text-transform:uppercase;">Margen general</span>
            </div>
            <p style="font-size:1.8rem;font-weight:800;color:<?= $margenGeneral >= 0 ? '#00875F' : '#E53935' ?>;line-height:1;">
                <?= $margenGeneral ?>%
            </p>
            <p style="font-size:.72rem;color:#9CA3AF;margin-top:3px;">ganancia / total vendido</p>
        </div>

    </div>

    <!-- Tabla -->
    <div class="panel">
        <div class="panel-head" style="display:flex;align-items:center;justify-content:space-between;">
            <h3 style="font-size:.88rem;font-weight:700;color:#171717;">Detalle de ventas</h3>
            <span style="font-size:.78rem;color:#5F6673;"><?= $totalRegistros ?> registro(s)</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr style="background:#01614B;">
                        <th class="px-4 py-3 text-xs font-bold uppercase tracking-wide" style="color:#fff;">ID</th>
                        <th class="px-4 py-3 text-xs font-bold uppercase tracking-wide" style="color:#fff;">Fecha</th>
                        <th class="px-4 py-3 text-xs font-bold uppercase tracking-wide" style="color:#fff;">Cliente</th>
                        <th class="px-4 py-3 text-xs font-bold uppercase tracking-wide" style="color:#fff;">Vendedor</th>
                        <th class="px-4 py-3 text-xs font-bold uppercase tracking-wide text-right" style="color:#fff;">Total venta</th>
                        <th class="px-4 py-3 text-xs font-bold uppercase tracking-wide text-right" style="color:#fff;">Ganancia</th>
                        <th class="px-4 py-3 text-xs font-bold uppercase tracking-wide text-right" style="color:#fff;">Margen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ventas as $v):
                        $gan    = floatval($v['ganancia']);
                        $margen = floatval($v['total']) > 0
                            ? round(($gan / floatval($v['total'])) * 100, 1)
                            : 0;
                        $cg = $gan >= 0 ? '#00875F' : '#E53935';
                    ?>
                    <tr style="border-bottom:1px solid #E5E7EB;transition:background .15s;"
                        onmouseover="this.style.background='#F8F8F8'"
                        onmouseout="this.style.background=''">
                        <td class="px-4 py-3 text-sm" style="color:#9CA3AF;">#<?= $v['id_venta'] ?></td>
                        <td class="px-4 py-3 text-sm" style="color:#171717;"><?= date('d/m/Y', strtotime($v['fecha'])) ?></td>
                        <td class="px-4 py-3 text-sm font-bold" style="color:#171717;"><?= htmlspecialchars($v['cliente'] ?? '---') ?></td>
                        <td class="px-4 py-3 text-sm" style="color:#5F6673;"><?= htmlspecialchars($v['vendedor'] ?? '---') ?></td>
                        <td class="px-4 py-3 text-sm font-bold text-right" style="color:#00875F;">$<?= number_format($v['total'], 0, ',', '.') ?></td>
                        <td class="px-4 py-3 text-sm font-bold text-right" style="color:<?= $cg ?>;">
                            $<?= number_format(abs($gan), 0, ',', '.') ?>
                        </td>
                        <td class="px-4 py-3 text-sm font-bold text-right" style="color:<?= $cg ?>;">
                            <?= $margen ?>%
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background:#F8F8F8;border-top:2px solid #E5E7EB;">
                        <td colspan="4" class="px-4 py-3 font-bold text-sm" style="color:#171717;">TOTALES</td>
                        <td class="px-4 py-3 font-bold text-right" style="color:#00875F;font-size:.95rem;">
                            $<?= number_format($totalVendido, 0, ',', '.') ?>
                        </td>
                        <td class="px-4 py-3 font-bold text-right" style="color:<?= $totalGanancia >= 0 ? '#00875F' : '#E53935' ?>;font-size:.95rem;">
                            $<?= number_format(abs($totalGanancia), 0, ',', '.') ?>
                        </td>
                        <td class="px-4 py-3 font-bold text-right" style="color:<?= $margenGeneral >= 0 ? '#00875F' : '#E53935' ?>;font-size:.95rem;">
                            <?= $margenGeneral ?>%
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    <?php endif; ?>

</div>

<script>
    const tituloOriginal = document.title;

    function exportarPDF() {
        document.title = "<?= $nombreArchivoPDF ?>";
        window.print();
    }

    window.onafterprint = function () {
        document.title = tituloOriginal;
    };
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>