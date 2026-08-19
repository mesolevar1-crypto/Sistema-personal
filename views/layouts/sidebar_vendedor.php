<?php
// ============================================================
// Layout: Sidebar del Vendedor (sidebar_vendedor.php)
// Acceso: Solo Vendedor
// Función: Barra lateral de navegación con menú reducido para
//          el rol Vendedor. Solo muestra los módulos a los que
//          tiene acceso: Inicio, Ventas, Clientes, Productos
//          e Inventario (solo lectura).
//          También renderiza el header superior con nombre,
//          rol y botón de cierre de sesión.
// ============================================================

// Datos del usuario autenticado desde la sesión
$rol    = $_SESSION['usuario']['rol'];
$nombre = $_SESSION['usuario']['nombre'];
$rolDisplay = htmlspecialchars(ucfirst($rol)); // Capitalizar el rol para mostrar
?>

<!-- ── Sidebar de navegación del Vendedor ── -->
<aside class="w-[260px] min-w-[260px] bg-[#4A8C44] text-white shadow-xl flex flex-col relative z-20">

    <!-- ── Logo y nombre del sistema ── -->
    <div class="flex items-center justify-center border-b border-white/20 px-4 py-8">
        <div class="text-center flex flex-col items-center">
            <div class="bg-white p-2 rounded-full mb-2 shadow-md">
                <img src="../../img/icon.png" width="40" height="40" alt="Logo">
            </div>
            <div class="font-serif-ventanet text-3xl tracking-tight leading-none text-white mt-1">
                VentaNet
            </div>
        </div>
    </div>

    <!-- ── Menú de navegación reducido para el vendedor ── -->
    <nav class="mt-6 px-4 flex flex-col gap-2 flex-1 font-sans-ventanet font-medium">
        <style>
            /* Estilo base de cada ítem del menú */
            .nav-item {
                color: #e6f6e4;
                transition: all 0.2s ease;
                position: relative;
                overflow: hidden;
            }
            /* Hover: fondo verde más claro */
            .nav-item:hover { color: #ffffff; background-color: #5faa58; }
            /* Ítem activo: fondo verde oscuro con barra lateral blanca */
            .nav-item.sidebar-active {
                color: #ffffff;
                background-color: #376B32;
                box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);
            }
            /* Barra blanca a la izquierda del ítem activo */
            .nav-item.sidebar-active::before {
                content: '';
                position: absolute;
                left: 0; top: 0; bottom: 0;
                width: 4px;
                background-color: #ffffff;
                border-radius: 0 4px 4px 0;
            }
        </style>

        <!-- Ítem: Inicio (Dashboard del vendedor) → dashboard/vendedor.php -->
        <a href="../dashboard/vendedor.php"
            class="<?= strpos($_SERVER['PHP_SELF'], 'vendedor.php') !== false ? 'sidebar-active' : '' ?> nav-item flex items-center gap-3 px-4 py-3.5 rounded-xl text-[0.95rem]">
            <i class="fas fa-home w-5 text-center text-lg"></i>
            <span>Inicio</span>
        </a>

        <!-- Ítem: Ventas (acceso rápido para registrar ventas) → vendedor/ventas.php -->
        <a href="../vendedor/ventas.php"
            class="<?= strpos($_SERVER['PHP_SELF'], '/vendedor/ventas') !== false ? 'sidebar-active' : '' ?> nav-item flex items-center gap-3 px-4 py-3.5 rounded-xl text-[0.95rem]">
            <i class="fas fa-cash-register w-5 text-center text-lg"></i>
            <span>Ventas</span>
        </a>

        <!-- Ítem: Clientes (el vendedor puede gestionar clientes) → clientes/index.php -->
        <a href="../clientes/index.php"
            class="<?= strpos($_SERVER['PHP_SELF'], 'clientes') !== false ? 'sidebar-active' : '' ?> nav-item flex items-center gap-3 px-4 py-3.5 rounded-xl text-[0.95rem]">
            <i class="fas fa-user-tie w-5 text-center text-lg"></i>
            <span>Clientes</span>
        </a>

        <!-- Ítem: Productos (solo lectura para el vendedor) → productos/index.php -->
        <a href="../productos/index.php"
            class="<?= strpos($_SERVER['PHP_SELF'], 'productos') !== false ? 'sidebar-active' : '' ?> nav-item flex items-center gap-3 px-4 py-3.5 rounded-xl text-[0.95rem]">
            <i class="fas fa-box-open w-5 text-center text-lg"></i>
            <span>Productos</span>
        </a>

        <!-- Ítem: Inventario (solo lectura para el vendedor) → inventario/index.php -->
        <a href="../inventario/index.php"
            class="<?= strpos($_SERVER['PHP_SELF'], 'inventario') !== false ? 'sidebar-active' : '' ?> nav-item flex items-center gap-3 px-4 py-3.5 rounded-xl text-[0.95rem]">
            <i class="fas fa-warehouse w-5 text-center text-lg"></i>
            <span>Inventario</span>
        </a>

    </nav>
</aside>

<!-- ── Contenedor principal del contenido (se abre aquí, se cierra en footer.php) ── -->
<main class="flex-1 flex flex-col relative z-10 overflow-hidden">

    <!-- ── Header superior: título, nombre de usuario y botón de logout ── -->
    <header class="flex items-center justify-between px-10 py-5 bg-[#DFF0DC] border-b border-[#C9E4C5] shadow-sm sticky top-0 z-30">
        <!-- Título del dashboard del vendedor -->
        <h1 class="font-serif-ventanet text-[#1C2E1A] text-3xl tracking-tight font-bold">
            Dahsboard Vendedor
        </h1>
        <div class="flex items-center gap-4">
            <!-- Nombre y rol del usuario autenticado -->
            <div class="flex flex-col text-right">
                <div class="font-bold text-[#1C2E1A] text-[0.95rem] leading-tight"><?= htmlspecialchars($nombre) ?></div>
                <div class="text-[0.75rem] text-[#4A8C44] font-medium uppercase tracking-wider"><?= $rolDisplay ?></div>
            </div>
            <div class="w-px h-8 bg-[#C9E4C5] mx-1"></div>
            <!-- Botón de cierre de sesión → AuthController.php?accion=logout -->
            <a href="../../controllers/AuthController.php?accion=logout"
                class="flex items-center gap-2 text-red-500 hover:text-red-700 hover:bg-red-100 px-3 py-2 rounded-lg transition-colors font-bold text-sm">
                <i class="fas fa-sign-out-alt text-lg"></i>
                <span>Cerrar Sesión</span>
            </a>
        </div>
    </header>

    <!-- ── Sección de contenido principal (se cierra en footer.php con </section>) ── -->
    <section class="p-8 flex-1 overflow-y-auto">
