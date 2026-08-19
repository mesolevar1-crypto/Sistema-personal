<?php

session_start();

if (
    !isset($_SESSION['usuario']) ||
    ($_SESSION['usuario']['rol'] ?? '') !== 'Administrador'
) {
    header("Location: ../usuarios/login.php");
    exit;
}

require_once __DIR__ . '/../../config/databse.php';
require_once __DIR__ . '/../../models/usuario.php';

try {
    $database = new Database();
    $db = $database->conectar();
    $usuarioModel = new Usuario($db);
} catch (Exception $e) {
    $_SESSION['alert'] = [
        'icon' => 'error',
        'title' => 'Error',
        'text' => 'No se pudo conectar con la base de datos.'
    ];

    header("Location: admin.php");
    exit;
}

$todosUsuarios = $usuarioModel->obtenerTodos();
$roles = $usuarioModel->obtenerRoles();

$todosUsuarios = is_array($todosUsuarios) ? $todosUsuarios : [];
$roles = is_array($roles) ? $roles : [];

$porPagina = 5;
$totalUsuarios = count($todosUsuarios);
$totalPaginas = max(1, (int)ceil($totalUsuarios / $porPagina));

$paginaActual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$paginaActual = max(1, min($paginaActual, $totalPaginas));

$inicio = ($paginaActual - 1) * $porPagina;
$usuarios = array_slice($todosUsuarios, $inicio, $porPagina);

$titulo = "Gestionar Usuarios";

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';
?>

<style>
.btn-primario {
    background:#00875F;
    color:white;
    border:none;
    border-radius:10px;
    padding:10px 20px;
    font-weight:600;
    cursor:pointer;
}

.btn-primario:hover {
    background:#01614B;
}

.campo-input {
    width:100%;
    padding:10px 14px;
    background:white;
    border:1.5px solid #E5E7EB;
    border-radius:10px;
    font-size:.95rem;
    outline:none;
    box-sizing:border-box;
}

.campo-input:focus {
    border-color:#61D0A7;
}

@keyframes modalRise {
    from {
        opacity:0;
        transform:translateY(15px) scale(.98);
    }
    to {
        opacity:1;
        transform:translateY(0) scale(1);
    }
}

.btn-accion {
    width:34px !important;
    height:34px !important;
    padding:0 !important;
    border-radius:8px !important;
    border:1px solid !important;
    background:white !important;
    display:inline-flex !important;
    align-items:center !important;
    justify-content:center !important;
    cursor:pointer;
}

.paginacion {
    padding:14px 20px;
    border-top:1px solid #E5E7EB;
    display:flex;
    justify-content:center;
    align-items:center;
    gap:6px;
}

.pag {
    width:36px;
    height:36px;
    border:1px solid #E5E7EB;
    border-radius:9px;
    background:white;
    color:#5F6673;
    display:flex;
    align-items:center;
    justify-content:center;
    text-decoration:none;
    font-weight:600;
}

.pag:hover {
    background:#DDF5EC;
    border-color:#61D0A7;
    color:#01614B;
}

.pag.activa {
    background:#00875F;
    border-color:#00875F;
    color:white;
}

.pag.deshabilitado {
    opacity:.4;
    pointer-events:none;
}

.modal-fondo {
    position:fixed;
    inset:0;
    background:rgba(0,0,0,.45);
    backdrop-filter:blur(4px);
    display:flex;
    align-items:center;
    justify-content:center;
    padding:20px;
    z-index:9999;
}

.modal-fondo.oculto {
    display:none;
}

.modal-caja {
    background:white;
    width:100%;
    max-width:650px;
    border-radius:18px;
    box-shadow:0 20px 60px rgba(0,0,0,.20);
    overflow:hidden;
    animation:modalRise .25s ease;
}

.modal-caja.pequeno {
    max-width:430px;
}

.modal-header {
    padding:18px 24px;
    border-bottom:1px solid #E5E7EB;
    display:flex;
    align-items:center;
    justify-content:space-between;
}

.modal-header h3 {
    margin:0;
    color:#01614B;
    font-size:20px;
    font-weight:700;
}

.btn-cerrar {
    background:none;
    border:none;
    cursor:pointer;
    font-size:20px;
    color:#5F6673;
}

.modal-body {
    padding:24px;
}

.modal-footer {
    padding:16px 24px;
    border-top:1px solid #E5E7EB;
    display:flex;
    justify-content:flex-end;
    gap:10px;
}

.btn-cancelar {
    background:white;
    border:1px solid #D1D5DB;
    border-radius:10px;
    padding:10px 20px;
    cursor:pointer;
    font-weight:600;
}

.btn-eliminar {
    background:#E53935;
    color:white;
    border:none;
    border-radius:10px;
    padding:10px 20px;
    cursor:pointer;
    font-weight:600;
}
</style>

<div class="max-w-7xl mx-auto">

    <div class="flex flex-col md:flex-row md:items-center justify-between mb-6 gap-4">
        <div>
            <h2
                class="text-3xl font-bold font-serif-ventanet"
                style="color:#01614B;"
            >
                Gestionar Usuarios
            </h2>

            <p class="text-sm mt-1" style="color:#5F6673;">
                Gestiona los accesos y permisos de forma centralizada
            </p>
        </div>

        <button
            type="button"
            onclick="abrirModal('modalCrear')"
            class="btn-primario"
        >
            <i class="fas fa-user-plus"></i>
            Nuevo Usuario
        </button>
    </div>

    <?php if (isset($_SESSION['alert'])): ?>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: <?= json_encode($_SESSION['alert']['icon'] ?? 'info') ?>,
                title: <?= json_encode($_SESSION['alert']['title'] ?? 'Aviso') ?>,
                text: <?= json_encode($_SESSION['alert']['text'] ?? '') ?>,
                confirmButtonText: 'Entendido',
                confirmButtonColor: '#00875F'
            });
        });
        </script>

        <?php unset($_SESSION['alert']); ?>

    <?php endif; ?>

    <div
        class="bg-white rounded-2xl border overflow-hidden"
        style="border-color:#E5E7EB;"
    >

        <div class="overflow-x-auto">

            <table class="w-full text-left border-collapse">

                <thead>
                    <tr style="background:#01614B;">
                        <th class="px-5 py-3 text-xs font-bold uppercase text-white">
                            Nombre
                        </th>

                        <th class="px-5 py-3 text-xs font-bold uppercase text-white">
                            Correo
                        </th>

                        <th class="px-5 py-3 text-xs font-bold uppercase text-white">
                            Teléfono
                        </th>

                        <th class="px-5 py-3 text-xs font-bold uppercase text-white text-center">
                            Rol
                        </th>

                        <th class="px-5 py-3 text-xs font-bold uppercase text-white text-center">
                            Estado
                        </th>

                        <th class="px-5 py-3 text-xs font-bold uppercase text-white text-center">
                            Acciones
                        </th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($usuarios as $u): ?>

                    <?php
                    $estadoUsuario = $u['estado_usuario'] ?? ($u['estado'] ?? 1);

                    $activo =
                        $estadoUsuario === 1 ||
                        $estadoUsuario === '1' ||
                        strtolower((string)$estadoUsuario) === 'activo';

                    $fila = $activo ? '#FFFFFF' : '#FDECEC';
                    $hover = $activo ? '#F8F8F8' : '#FDE0E0';
                    $idUsuario = (int)($u['id_usuario'] ?? 0);

                    $admin =
                        strtolower($u['nombre_rol'] ?? '') === 'administrador';
                    ?>

                    <tr
                        style="
                            border-bottom:1px solid #E5E7EB;
                            background:<?= $fila ?>;
                        "
                        onmouseover="this.style.background='<?= $hover ?>'"
                        onmouseout="this.style.background='<?= $fila ?>'"
                    >

                        <td class="px-5 py-3.5 font-bold text-sm">
                            <?= htmlspecialchars(
                                $u['nombre'] ?? '',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </td>

                        <td
                            class="px-5 py-3.5 text-sm"
                            style="color:#5F6673;"
                        >
                            <?= htmlspecialchars(
                                $u['correo'] ?? '',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </td>

                        <td
                            class="px-5 py-3.5 text-sm"
                            style="color:#5F6673;"
                        >
                            <?= htmlspecialchars(
                                !empty($u['telefono'])
                                    ? $u['telefono']
                                    : 'No registrado',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </td>

                        <td class="px-5 py-3.5 text-center">

                            <span
                                style="
                                    <?= $admin
                                        ? 'background:#EBF5FF;color:#1F3552;border:1px solid #BFDBFE;'
                                        : 'background:#FFFBEB;color:#92400E;border:1px solid #FFB51B;'
                                    ?>

                                    padding:3px 12px;
                                    border-radius:999px;
                                    font-size:.75rem;
                                    font-weight:700;
                                "
                            >
                                <?= htmlspecialchars(
                                    $u['nombre_rol'] ?? '—',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </span>

                        </td>

                        <td class="px-5 py-3.5 text-center">

                            <?php if ($activo): ?>

                                <span
                                    style="
                                        background:#DDF5EC;
                                        color:#00875F;
                                        border:1px solid #61D0A7;
                                        padding:3px 12px;
                                        border-radius:999px;
                                        font-size:.75rem;
                                        font-weight:700;
                                    "
                                >
                                    Activo
                                </span>

                            <?php else: ?>

                                <span
                                    style="
                                        background:#FDE8E8;
                                        color:#E53935;
                                        border:1px solid #E53935;
                                        padding:3px 12px;
                                        border-radius:999px;
                                        font-size:.75rem;
                                        font-weight:700;
                                    "
                                >
                                    Inactivo
                                </span>

                            <?php endif; ?>

                        </td>

                        <td class="px-5 py-3.5">

                            <div
                                style="
                                    display:flex;
                                    justify-content:center;
                                    align-items:center;
                                    gap:8px;
                                "
                            >

                                <button
                                    type="button"
                                    class="btn-accion"
                                    title="Editar"
                                    onclick='abrirModalEditar(
                                        <?= json_encode(
                                            $u,
                                            JSON_HEX_TAG |
                                            JSON_HEX_APOS |
                                            JSON_HEX_QUOT |
                                            JSON_HEX_AMP
                                        ) ?>
                                    )'
                                    style="
                                        color:#00875F;
                                        border-color:#61D0A7 !important;
                                    "
                                >
                                    <i class="fas fa-pen"></i>
                                </button>

                                <button
                                    type="button"
                                    class="btn-accion"
                                    title="<?= $activo ? 'Desactivar usuario' : 'Activar usuario' ?>"
                                    onclick="cambiarEstadoUsuario(
                                        <?= $idUsuario ?>,
                                        <?= $activo ? 1 : 0 ?>
                                    )"
                                    style="
                                        color:<?= $activo ? '#FFB51B' : '#00875F' ?>;
                                        border-color:<?= $activo ? '#FFB51B' : '#61D0A7' ?> !important;
                                    "
                                >
                                    <i class="fas <?= $activo ? 'fa-ban' : 'fa-check' ?>"></i>
                                </button>

                                <button
                                    type="button"
                                    class="btn-accion"
                                    title="Eliminar"
                                    onclick='abrirModalEliminar(
                                        <?= $idUsuario ?>,
                                        <?= json_encode(
                                            $u['nombre'] ?? '',
                                            JSON_HEX_TAG |
                                            JSON_HEX_APOS |
                                            JSON_HEX_QUOT |
                                            JSON_HEX_AMP
                                        ) ?>
                                    )'
                                    style="
                                        color:#E53935;
                                        border-color:#E53935 !important;
                                    "
                                >
                                    <i class="fas fa-trash"></i>
                                </button>

                            </div>

                        </td>

                    </tr>

                <?php endforeach; ?>

                <?php if (empty($usuarios)): ?>

                    <tr>
                        <td
                            colspan="6"
                            style="
                                padding:48px;
                                text-align:center;
                                color:#5F6673;
                                font-weight:600;
                            "
                        >
                            No hay usuarios registrados.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

        <?php if ($totalUsuarios > $porPagina): ?>

            <nav class="paginacion">

                <a
                    class="pag <?= $paginaActual <= 1 ? 'deshabilitado' : '' ?>"
                    href="?pagina=<?= max(1, $paginaActual - 1) ?>"
                >
                    «
                </a>

                <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>

                    <a
                        class="pag <?= $i === $paginaActual ? 'activa' : '' ?>"
                        href="?pagina=<?= $i ?>"
                    >
                        <?= $i ?>
                    </a>

                <?php endfor; ?>

                <a
                    class="pag <?= $paginaActual >= $totalPaginas ? 'deshabilitado' : '' ?>"
                    href="?pagina=<?= min($totalPaginas, $paginaActual + 1) ?>"
                >
                    »
                </a>

            </nav>

        <?php endif; ?>

    </div>

</div>


<!-- MODAL CREAR -->

<div id="modalCrear" class="modal-fondo oculto">

    <div class="modal-caja">

        <div class="modal-header">
            <h3>Agregar Usuario</h3>

            <button
                type="button"
                class="btn-cerrar"
                onclick="cerrarModal('modalCrear')"
            >
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form
            action="../../controllers/UsuarioController.php"
            method="POST"
        >

            <input
                type="hidden"
                name="desde_admin"
                value="1"
            >

            <div class="modal-body">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                    <div>
                        <label>Nombre completo *</label>
                        <input
                            type="text"
                            name="nombre"
                            required
                            class="campo-input"
                        >
                    </div>

                    <div>
                        <label>Teléfono</label>
                        <input
                            type="text"
                            name="telefono"
                            class="campo-input"
                        >
                    </div>

                    <div>
                        <label>Correo *</label>
                        <input
                            type="email"
                            name="correo"
                            required
                            class="campo-input"
                        >
                    </div>

                    <div>
                        <label>Contraseña *</label>
                        <input
                            type="password"
                            name="password"
                            required
                            class="campo-input"
                        >
                    </div>

                    <div>
                        <label>Confirmar contraseña *</label>
                        <input
                            type="password"
                            name="confirmar_password"
                            required
                            class="campo-input"
                        >
                    </div>

                    <div>
                        <label>Rol *</label>

                        <select
                            name="rol"
                            required
                            class="campo-input"
                        >
                            <?php foreach ($roles as $r): ?>
                                <option value="<?= (int)$r['id_rol'] ?>">
                                    <?= htmlspecialchars(
                                        $r['nombre'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                    </div>

                </div>

            </div>

            <div class="modal-footer">

                <button
                    type="button"
                    class="btn-cancelar"
                    onclick="cerrarModal('modalCrear')"
                >
                    Cancelar
                </button>

                <button
                    type="submit"
                    class="btn-primario"
                >
                    Guardar Usuario
                </button>

            </div>

        </form>

    </div>

</div>


<!-- MODAL EDITAR -->

<div id="modalEditar" class="modal-fondo oculto">

    <div class="modal-caja">

        <div class="modal-header">

            <h3>Editar Usuario</h3>

            <button
                type="button"
                class="btn-cerrar"
                onclick="cerrarModal('modalEditar')"
            >
                <i class="fas fa-times"></i>
            </button>

        </div>

        <form
            action="../../controllers/AdminUsuarioController.php?accion=editar"
            method="POST"
        >

            <input
                type="hidden"
                name="id_usuario"
                id="editId"
            >

            <div class="modal-body">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                    <div>
                        <label>Nombre completo *</label>

                        <input
                            type="text"
                            name="nombre"
                            id="editNombre"
                            required
                            class="campo-input"
                        >
                    </div>

                    <div>
                        <label>Teléfono</label>

                        <input
                            type="text"
                            name="telefono"
                            id="editTelefono"
                            class="campo-input"
                        >
                    </div>

                    <div>
                        <label>Correo</label>

                        <input
                            type="email"
                            id="editCorreo"
                            readonly
                            class="campo-input"
                        >
                    </div>

                    <div>
                        <label>Nueva contraseña</label>

                        <input
                            type="password"
                            name="password"
                            id="editPassword"
                            class="campo-input"
                        >
                    </div>

                    <div>
                        <label>Rol *</label>

                        <select
                            name="rol"
                            id="editRol"
                            required
                            class="campo-input"
                        >
                            <?php foreach ($roles as $r): ?>

                                <option value="<?= (int)$r['id_rol'] ?>">
                                    <?= htmlspecialchars(
                                        $r['nombre'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </option>

                            <?php endforeach; ?>
                        </select>

                    </div>

                </div>

            </div>

            <div class="modal-footer">

                <button
                    type="button"
                    class="btn-cancelar"
                    onclick="cerrarModal('modalEditar')"
                >
                    Cancelar
                </button>

                <button
                    type="submit"
                    class="btn-primario"
                >
                    Guardar Cambios
                </button>

            </div>

        </form>

    </div>

</div>


<!-- MODAL ELIMINAR -->

<div id="modalEliminar" class="modal-fondo oculto">

    <div class="modal-caja pequeno">

        <div style="padding:35px 30px 20px;text-align:center;">

            <div
                style="
                    width:65px;
                    height:65px;
                    margin:0 auto 18px;
                    border-radius:50%;
                    background:#FDE8E8;
                    display:flex;
                    align-items:center;
                    justify-content:center;
                "
            >
                <i
                    class="fas fa-exclamation-triangle"
                    style="color:#E53935;font-size:28px;"
                ></i>
            </div>

            <h3
                style="
                    margin:0 0 10px;
                    font-size:21px;
                    font-weight:700;
                    color:#171717;
                "
            >
                Eliminar Usuario
            </h3>

            <p
                style="
                    margin:0;
                    color:#5F6673;
                    line-height:1.5;
                "
            >
                ¿Estás seguro de que deseas eliminar a:
            </p>

            <p
                id="elimNombre"
                style="
                    margin:8px 0 0;
                    font-weight:700;
                    color:#171717;
                "
            ></p>

        </div>

        <div class="modal-footer">

            <button
                type="button"
                class="btn-cancelar"
                onclick="cerrarModal('modalEliminar')"
            >
                Cancelar
            </button>

            <button
                type="button"
                class="btn-eliminar"
                onclick="confirmarEliminar()"
            >
                Sí, eliminar
            </button>

        </div>

    </div>

</div>


<script>

function abrirModal(id) {
    const modal = document.getElementById(id);

    if (modal) {
        modal.classList.remove('oculto');
    }
}

function cerrarModal(id) {
    const modal = document.getElementById(id);

    if (modal) {
        modal.classList.add('oculto');
    }
}

function abrirModalEditar(usuario) {

    document.getElementById('editId').value =
        usuario.id_usuario || '';

    document.getElementById('editNombre').value =
        usuario.nombre || '';

    document.getElementById('editTelefono').value =
        usuario.telefono || '';

    document.getElementById('editCorreo').value =
        usuario.correo || '';

    document.getElementById('editRol').value =
        usuario.id_rol || '';

    document.getElementById('editPassword').value = '';

    abrirModal('modalEditar');
}

let usuarioEliminar = null;

function abrirModalEliminar(id, nombre) {

    usuarioEliminar = id;

    document.getElementById('elimNombre').textContent =
        nombre;

    abrirModal('modalEliminar');
}

function confirmarEliminar() {

    if (!usuarioEliminar) return;

    window.location.href =
        '../../controllers/AdminUsuarioController.php' +
        '?accion=eliminar&id=' +
        encodeURIComponent(usuarioEliminar);
}

function cambiarEstadoUsuario(id) {

    if (!id || id <= 0) return;

    window.location.href =
        '../../controllers/AdminUsuarioController.php' +
        '?accion=toggleEstado&id=' +
        encodeURIComponent(id);
}

document.querySelectorAll('.modal-fondo').forEach(function(modal) {

    modal.addEventListener('click', function(e) {

        if (e.target === modal) {
            modal.classList.add('oculto');
        }

    });

});

document.addEventListener('keydown', function(e) {

    if (e.key === 'Escape') {

        document.querySelectorAll('.modal-fondo').forEach(function(modal) {
            modal.classList.add('oculto');
        });

    }

});

</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>