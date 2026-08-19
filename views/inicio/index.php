<?php
// ============================================================
// Vista: Inicio del Administrador
// Acceso: Solo Administrador
// Función: Panel principal con tarjetas de resumen.
//          Los valores se conectarán módulo por módulo.
// ============================================================

session_start();

if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] !== 'Administrador') {
    header("Location: ../usuarios/login.php");
    exit;
}

$titulo = "Panel de Inicio - Administrador";
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';

// Nombre del usuario autenticado para el saludo
$nombreUsuario = htmlspecialchars($_SESSION['usuario']['nombre']);

// ── Valores de las tarjetas ──────────────────────────────────
// Cada variable se inicializa en "--".
// Cuando se desarrolle el módulo correspondiente,
// se reemplazará esta línea por la consulta real.

$ventasDia     = "--";   // Se conectará con: módulo Ventas
$ventasMes     = "--";   // Se conectará con: módulo Ventas
$gananciasSemana = "--"; // Se conectará con: módulo Ganancias
$stockBajo     = "--";   // Se conectará con: módulo Inventario
$totalProductos = "--";  // Se conectará con: módulo Productos
?>

<!-- ── Estilos locales del Inicio ── -->
<style>
    /* Tarjeta de resumen */
    .tarjeta-resumen {
        background: #ffffff;
        border: 1px solid #E5E7EB;
        border-radius: 16px;
        padding: 24px;
        display: flex;
        flex-direction: column;
        gap: 8px;
        transition: box-shadow 0.2s, transform 0.2s;
    }
    .tarjeta-resumen:hover {
        box-shadow: 0 8px 24px rgba(0,135,95,0.10);
        transform: translateY(-3px);
    }
    .tarjeta-icono {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        margin-bottom: 4px;
    }
    .tarjeta-valor {
        font-size: 2rem;
        font-weight: 800;
        color: #171717;
        line-height: 1;
        font-family: 'DM Serif Display', serif;
    }
    .tarjeta-valor.sin-datos {
        font-size: 1.6rem;
        color: #9CA3AF;
        font-family: 'Outfit', sans-serif;
        font-weight: 600;
    }
    .tarjeta-label {
        font-size: 0.8rem;
        font-weight: 700;
        color: #5F6673;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    .tarjeta-pendiente {
        font-size: 0.75rem;
        color: #9CA3AF;
        font-style: italic;
        margin-top: 2px;
    }

    /* Sección de panel grande */
    .panel-grande {
        background: #ffffff;
        border: 1px solid #E5E7EB;
        border-radius: 16px;
        overflow: hidden;
    }
    .panel-header {
        padding: 18px 24px;
        border-bottom: 1px solid #E5E7EB;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .panel-header h3 {
        font-size: 1rem;
        font-weight: 700;
        color: #171717;
    }
    .panel-body {
        padding: 32px 24px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        min-height: 180px;
        gap: 10px;
        text-align: center;
    }
    .panel-body .panel-vacio-icono {
        width: 52px;
        height: 52px;
        border-radius: 50%;
        background: #F3F4F6;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
        color: #9CA3AF;
    }
    .panel-body p {
        font-size: 0.9rem;
        color: #9CA3AF;
        font-style: italic;
    }
    .panel-body span {
        font-size: 0.75rem;
        color: #C4C9D4;
    }

    /* Badge de alerta */
    .badge-aviso {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: #fffbeb;
        border: 1px solid #FFB51B;
        color: #92400E;
        border-radius: 20px;
        padding: 4px 12px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    .badge-ok {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: #DDF5EC;
        border: 1px solid #61D0A7;
        color: #01614B;
        border-radius: 20px;
        padding: 4px 12px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    /* Sección título */
    .seccion-titulo {
        font-size: 0.7rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #9CA3AF;
        margin-bottom: 12px;
        margin-top: 8px;
        padding-left: 2px;
    }
</style>

<!-- ════════════════════════════════════════════
     CONTENIDO PRINCIPAL — INICIO
════════════════════════════════════════════ -->
<div class="max-w-7xl mx-auto font-sans-ventanet">

    <!-- ── Saludo ── -->
    <div class="mb-8">
        <h2 class="text-4xl font-bold font-serif-ventanet text-[#1C2E1A] leading-tight">
            Bienvenido, <?= $nombreUsuario ?> 👋
        </h2>
        <p class="text-[#5F6673] mt-1 text-sm">
            Aquí tienes el resumen general del sistema. Los datos se activarán conforme se desarrollen los módulos.
        </p>
    </div>


    <!-- ════════════════
         FILA 1 — 5 tarjetas de resumen
    ════════════════ -->
    <p class="seccion-titulo">Resumen general</p>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-5 mb-8">

        <!-- Ventas del día -->
        <div class="tarjeta-resumen">
            <div class="tarjeta-icono" style="background:#DDF5EC;">
                <i class="fas fa-cash-register" style="color:#00875F;"></i>
            </div>
            <p class="tarjeta-label">Ventas del día</p>
            <p class="tarjeta-valor <?= $ventasDia === '--' ? 'sin-datos' : '' ?>">
                <?= $ventasDia ?>
            </p>
        </div>

        <!-- Ventas del mes -->
        <div class="tarjeta-resumen">
            <div class="tarjeta-icono" style="background:#DDF5EC;">
                <i class="fas fa-calendar-alt" style="color:#00875F;"></i>
            </div>
            <p class="tarjeta-label">Ventas del mes</p>
            <p class="tarjeta-valor <?= $ventasMes === '--' ? 'sin-datos' : '' ?>">
                <?= $ventasMes ?>
            </p>
        </div>

        <!-- Ganancias de la semana -->
        <div class="tarjeta-resumen">
            <div class="tarjeta-icono" style="background:#fffbeb;">
                <i class="fas fa-chart-line" style="color:#FFB51B;"></i>
            </div>
            <p class="tarjeta-label">Ganancias semana</p>
            <p class="tarjeta-valor <?= $gananciasSemana === '--' ? 'sin-datos' : '' ?>">
                <?= $gananciasSemana ?>
            </p>
        </div>

        <!-- Stock bajo -->
        <div class="tarjeta-resumen">
            <div class="tarjeta-icono" style="background:#fde8e8;">
                <i class="fas fa-exclamation-triangle" style="color:#E53935;"></i>
            </div>
            <p class="tarjeta-label">Stock bajo</p>
            <p class="tarjeta-valor <?= $stockBajo === '--' ? 'sin-datos' : '' ?>">
                <?= $stockBajo ?>
            </p>
        </div>

        <!-- Total de productos -->
        <div class="tarjeta-resumen">
            <div class="tarjeta-icono" style="background:#EBF5FF;">
                <i class="fas fa-box-open" style="color:#1F3552;"></i>
            </div>
            <p class="tarjeta-label">Total productos</p>
            <p class="tarjeta-valor <?= $totalProductos === '--' ? 'sin-datos' : '' ?>">
                <?= $totalProductos ?>
            </p>
            
        </div>

    </div>


    <!-- ════════════════
         FILA 2 — Gráfica de ventas + Productos más vendidos
    ════════════════ -->
    <p class="seccion-titulo">Análisis de ventas</p>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-8">

        <!-- Ventas últimos 7 días (ocupa 2/3) -->
        <div class="panel-grande lg:col-span-2">
            <div class="panel-header">
                <h3><i class="fas fa-chart-bar mr-2" style="color:#00875F;"></i>Ventas — últimos 7 días</h3>
                <span class="badge-aviso">
                    <i class="fas fa-clock text-[10px]"></i> Pendiente
                </span>
            </div>
            <div class="panel-body">
                <div class="panel-vacio-icono">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <p>Sin datos disponibles</p>
                <span>Se activará al desarrollar el módulo de Ventas</span>
            </div>
        </div>

        <!-- Productos más vendidos (ocupa 1/3) -->
        <div class="panel-grande">
            <div class="panel-header">
                <h3><i class="fas fa-fire mr-2" style="color:#E53935;"></i>Más vendidos</h3>
                <span class="badge-aviso">
                    <i class="fas fa-clock text-[10px]"></i> Pendiente
                </span>
            </div>
            <div class="panel-body">
                <div class="panel-vacio-icono">
                    <i class="fas fa-box-open"></i>
                </div>
                <p>Sin datos disponibles</p>
                <span>Se activará al desarrollar el módulo de Ventas</span>
            </div>
        </div>

    </div>


    

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
