<?php
// ============================================================
// Reporte de Inventario — stock desde inventario.stock_actual
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
$buscar  = trim($_GET['buscar'] ?? '');
$idCat   = intval($_GET['id_categoria'] ?? 0);
$estado  = trim($_GET['estado'] ?? '');

$inventario  = $reporteModel->reporteInventario($buscar, $idCat, $estado);
$resumen     = $reporteModel->resumenInventario();
$categorias  = $reporteModel->listaCategorias();

$titulo = 'Reporte de Inventario';
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
    .filtro-link {
        padding:7px 14px;border-radius:9px;border:1px solid #E5E7EB;
        background:#fff;color:#5F6673;font-size:.82rem;font-weight:600;
        text-decoration:none;display:inline-block;
        transition:background .15s,border-color .15s,color .15s;
    }
    .filtro-link:hover      { background:#DDF5EC;border-color:#61D0A7;color:#01614B; }
    .filtro-link.f-todos    { background:#00875F;border-color:#00875F;color:#fff; }
    .filtro-link.f-disp     { background:#00875F;border-color:#00875F;color:#fff; }
    .filtro-link.f-bajo     { background:#FFB51B;border-color:#FFB51B;color:#fff; }
    .filtro-link.f-agot     { background:#E53935;border-color:#E53935;color:#fff; }
</style>

<div class="max-w-7xl mx-auto font-sans-ventanet">

    <!-- Encabezado -->
    <div class="flex flex-col md:flex-row md:items-center justify-between mb-6 gap-4">
        <div style="display:flex;align-items:center;gap:12px;">
            <a href="index.php"
               style="padding:8px 14px;border-radius:9px;border:1px solid #E5E7EB;background:#fff;color:#00875F;font-size:.82rem;font-weight:700;text-decoration:none;display:inline-flex;align-items:center;gap:5px;">
               <i class="fas fa-arrow-left" style="font-size:.7rem;"></i> Volver
            </a>
            <div>
                <h2 class="text-3xl font-bold font-serif-ventanet" style="color:#01614B;">Reporte de Inventario</h2>
                <p class="text-sm mt-1" style="color:#5F6673;">Consulta el estado actual de tu inventario</p>
            </div>
        </div>
    </div>

    <!-- KPIs reales -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="kpi">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
                <div style="width:36px;height:36px;background:#DDF5EC;border-radius:9px;display:flex;align-items:center;justify-content:center;">
                    <i class="fas fa-boxes" style="color:#00875F;font-size:.85rem;"></i>
                </div>
                <span style="font-size:.7rem;font-weight:700;color:#5F6673;text-transform:uppercase;">Total productos</span>
            </div>
            <p style="font-size:1.8rem;font-weight:800;color:#171717;line-height:1;"><?= intval($resumen['total_productos'] ?? 0) ?></p>
            <p style="font-size:.72rem;color:#9CA3AF;margin-top:3px;">en catálogo</p>
        </div>

        <div class="kpi">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
                <div style="width:36px;height:36px;background:#DDF5EC;border-radius:9px;display:flex;align-items:center;justify-content:center;">
                    <i class="fas fa-cubes" style="color:#00875F;font-size:.85rem;"></i>
                </div>
                <span style="font-size:.7rem;font-weight:700;color:#5F6673;text-transform:uppercase;">Unidades disponibles</span>
            </div>
            <p style="font-size:1.8rem;font-weight:800;color:#171717;line-height:1;"><?= number_format($resumen['total_unidades'] ?? 0) ?></p>
            <p style="font-size:.72rem;color:#9CA3AF;margin-top:3px;">en inventario</p>
        </div>

        <div class="kpi" style="border-color:<?= intval($resumen['stock_bajo'] ?? 0) > 0 ? '#FFB51B' : '#E5E7EB' ?>;">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
                <div style="width:36px;height:36px;background:#fffbeb;border-radius:9px;display:flex;align-items:center;justify-content:center;">
                    <i class="fas fa-exclamation-triangle" style="color:#FFB51B;font-size:.85rem;"></i>
                </div>
                <span style="font-size:.7rem;font-weight:700;color:#5F6673;text-transform:uppercase;">Stock bajo</span>
            </div>
            <p style="font-size:1.8rem;font-weight:800;color:#FFB51B;line-height:1;"><?= intval($resumen['stock_bajo'] ?? 0) ?></p>
            <p style="font-size:.72rem;color:#9CA3AF;margin-top:3px;">con poco stock</p>
        </div>

        <div class="kpi" style="border-color:<?= intval($resumen['agotados'] ?? 0) > 0 ? '#fde8e8' : '#E5E7EB' ?>;">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
                <div style="width:36px;height:36px;background:#fde8e8;border-radius:9px;display:flex;align-items:center;justify-content:center;">
                    <i class="fas fa-times-circle" style="color:#E53935;font-size:.85rem;"></i>
                </div>
                <span style="font-size:.7rem;font-weight:700;color:#5F6673;text-transform:uppercase;">Agotados</span>
            </div>
            <p style="font-size:1.8rem;font-weight:800;color:#E53935;line-height:1;"><?= intval($resumen['agotados'] ?? 0) ?></p>
            <p style="font-size:.72rem;color:#9CA3AF;margin-top:3px;">sin unidades</p>
        </div>
    </div>

    <!-- Filtros: buscador + categoría via GET -->
    <form method="GET" class="panel mb-4">
        <div class="panel-head">
            <h3 style="font-size:.88rem;font-weight:700;color:#171717;display:flex;align-items:center;gap:8px;">
                <i class="fas fa-filter" style="color:#00875F;"></i> Filtros
            </h3>
        </div>
        <div style="padding:18px;display:grid;grid-template-columns:1fr 1fr auto auto;gap:12px;align-items:end;">
            <div style="position:relative;">
                <label style="display:block;font-size:.78rem;font-weight:700;color:#5F6673;margin-bottom:5px;">Buscar producto</label>
                <i class="fas fa-search" style="position:absolute;left:11px;bottom:11px;color:#9CA3AF;font-size:.78rem;pointer-events:none;"></i>
                <input type="text" name="buscar" value="<?= htmlspecialchars($buscar) ?>"
                       placeholder="Nombre del producto..."
                       class="campo-input" style="padding-left:32px;">
                <?php if ($estado): ?>
                    <input type="hidden" name="estado" value="<?= htmlspecialchars($estado) ?>">
                <?php endif; ?>
                <?php if ($idCat): ?>
                    <input type="hidden" name="id_categoria" value="<?= $idCat ?>">
                <?php endif; ?>
            </div>
            <div style="position:relative;">
                <label style="display:block;font-size:.78rem;font-weight:700;color:#5F6673;margin-bottom:5px;">Categoría</label>
                <select name="id_categoria" class="campo-input" style="appearance:none;cursor:pointer;">
                    <option value="0">Todas las categorías</option>
                    <?php foreach ($categorias as $cat): ?>
                        <option value="<?= $cat['id_categoria'] ?>" <?= $idCat == $cat['id_categoria'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['tipo']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <i class="fas fa-chevron-down" style="position:absolute;right:10px;bottom:11px;color:#00875F;font-size:.7rem;pointer-events:none;"></i>
            </div>
            <button type="submit" class="btn-primario">
                <i class="fas fa-search" style="font-size:.8rem;"></i> Buscar
            </button>
            <a href="inventario.php"
               style="padding:9px 16px;border-radius:9px;border:1px solid #E5E7EB;background:#fff;color:#5F6673;font-size:.85rem;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:5px;">
               <i class="fas fa-times" style="font-size:.75rem;"></i> Limpiar
            </a>
        </div>
    </form>

    <!-- Filtros de estado (links GET) -->
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;">
        <a href="?buscar=<?= urlencode($buscar) ?>&id_categoria=<?= $idCat ?>"
           class="filtro-link <?= $estado === '' ? 'f-todos' : '' ?>">Todos</a>
        <a href="?buscar=<?= urlencode($buscar) ?>&id_categoria=<?= $idCat ?>&estado=disponible"
           class="filtro-link <?= $estado === 'disponible' ? 'f-disp' : '' ?>">✓ Disponible</a>
        <a href="?buscar=<?= urlencode($buscar) ?>&id_categoria=<?= $idCat ?>&estado=bajo"
           class="filtro-link <?= $estado === 'bajo' ? 'f-bajo' : '' ?>">⚠ Stock bajo</a>
        <a href="?buscar=<?= urlencode($buscar) ?>&id_categoria=<?= $idCat ?>&estado=agotado"
           class="filtro-link <?= $estado === 'agotado' ? 'f-agot' : '' ?>">✕ Agotado</a>
    </div>

    <!-- Tabla de inventario -->
    <div class="panel">
        <div class="panel-head" style="display:flex;align-items:center;justify-content:space-between;">
            <h3 style="font-size:.88rem;font-weight:700;color:#171717;">Estado del inventario</h3>
            <span style="font-size:.78rem;color:#5F6673;">
                <?= count($inventario) ?> resultado(s)
                <?= $buscar ? '· "<strong>' . htmlspecialchars($buscar) . '</strong>"' : '' ?>
                <?= $estado ? '· <strong>' . htmlspecialchars($estado) . '</strong>' : '' ?>
            </span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr style="background:#01614B;">
                        <th class="px-5 py-3 text-xs font-bold uppercase tracking-wide" style="color:#fff;">Producto</th>
                        <th class="px-5 py-3 text-xs font-bold uppercase tracking-wide" style="color:#fff;">Categoría</th>
                        <th class="px-5 py-3 text-xs font-bold uppercase tracking-wide text-center" style="color:#fff;">Stock actual</th>
                        <th class="px-5 py-3 text-xs font-bold uppercase tracking-wide text-center" style="color:#fff;">Stock mínimo</th>
                        <th class="px-5 py-3 text-xs font-bold uppercase tracking-wide text-center" style="color:#fff;">Estado</th>
                        <th class="px-5 py-3 text-xs font-bold uppercase tracking-wide text-center" style="color:#fff;">Última actualización</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($inventario)): ?>
                        <?php foreach ($inventario as $row):
                            $sa = intval($row['stock_actual']);
                            $sm = intval($row['stock_minimo']);

                            if ($sa === 0)        { $est = 'agotado';    $bgFila = '#FDECEC'; $bgHov = '#fde0e0'; }
                            elseif ($sa <= $sm)   { $est = 'bajo';       $bgFila = '#fffbeb'; $bgHov = '#fef3c7'; }
                            else                  { $est = 'disponible'; $bgFila = '#fff';    $bgHov = '#F8F8F8'; }
                        ?>
                        <tr style="border-bottom:1px solid #E5E7EB;background:<?= $bgFila ?>;transition:background .15s;"
                            onmouseover="this.style.background='<?= $bgHov ?>'"
                            onmouseout="this.style.background='<?= $bgFila ?>'">

                            <td class="px-5 py-3.5 font-bold text-sm" style="color:#171717;">
                                <?= htmlspecialchars($row['producto']) ?>
                            </td>
                            <td class="px-5 py-3.5 text-sm" style="color:#5F6673;">
                                <?php if (!empty($row['categoria'])): ?>
                                    <span style="background:#EBF5FF;color:#1F3552;border:1px solid #BFDBFE;padding:2px 10px;border-radius:999px;font-size:.72rem;font-weight:700;">
                                        <?= htmlspecialchars($row['categoria']) ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color:#9CA3AF;font-style:italic;">Sin categoría</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-3.5 text-center">
                                <?php
                                $cStock = $est === 'agotado' ? '#E53935'
                                        : ($est === 'bajo'  ? '#FFB51B' : '#00875F');
                                ?>
                                <span style="font-size:1.3rem;font-weight:800;color:<?= $cStock ?>;"><?= $sa ?></span>
                                <span style="font-size:.7rem;color:#9CA3AF;margin-left:2px;">uds.</span>
                            </td>
                            <td class="px-5 py-3.5 text-center text-sm font-semibold" style="color:#5F6673;"><?= $sm ?></td>
                            <td class="px-5 py-3.5 text-center">
                                <?php if ($est === 'agotado'): ?>
                                    <span style="background:#fde8e8;color:#E53935;border:1px solid #E53935;padding:3px 12px;border-radius:999px;font-size:.75rem;font-weight:700;display:inline-flex;align-items:center;gap:4px;">
                                        <span style="width:6px;height:6px;background:#E53935;border-radius:50%;display:inline-block;"></span>
                                        Agotado
                                    </span>
                                <?php elseif ($est === 'bajo'): ?>
                                    <span style="background:#fffbeb;color:#92400E;border:1px solid #FFB51B;padding:3px 12px;border-radius:999px;font-size:.75rem;font-weight:700;display:inline-flex;align-items:center;gap:4px;">
                                        <span style="width:6px;height:6px;background:#FFB51B;border-radius:50%;display:inline-block;"></span>
                                        Stock bajo
                                    </span>
                                <?php else: ?>
                                    <span style="background:#DDF5EC;color:#00875F;border:1px solid #61D0A7;padding:3px 12px;border-radius:999px;font-size:.75rem;font-weight:700;display:inline-flex;align-items:center;gap:4px;">
                                        <span style="width:6px;height:6px;background:#00875F;border-radius:50%;display:inline-block;"></span>
                                        Disponible
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-3.5 text-center text-sm" style="color:#5F6673;">
                                <?php if (!empty($row['fecha_actualizacion'])): ?>
                                    <?= date('d/m/Y', strtotime($row['fecha_actualizacion'])) ?>
                                <?php else: ?>
                                    <span style="color:#9CA3AF;font-style:italic;">Sin registro</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="padding:48px;text-align:center;">
                                <div style="width:56px;height:56px;background:#F8F8F8;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;border:1px solid #E5E7EB;">
                                    <i class="fas fa-warehouse" style="color:#9CA3AF;font-size:1.3rem;"></i>
                                </div>
                                <p style="color:#5F6673;font-weight:600;">
                                    <?= ($buscar || $estado || $idCat) ? 'No se encontraron productos con ese criterio.' : 'No hay productos en inventario.' ?>
                                </p>
                                <?php if ($buscar || $estado || $idCat): ?>
                                <a href="inventario.php" style="color:#00875F;font-size:.82rem;font-weight:600;margin-top:8px;display:inline-block;">
                                    Ver todo el inventario
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
