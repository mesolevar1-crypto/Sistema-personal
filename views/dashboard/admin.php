<?php

session_start();

if (
    !isset($_SESSION['usuario']) ||
    strtolower(trim($_SESSION['usuario']['rol'] ?? '')) !== 'administrador'
) {
    header("Location: ../usuarios/login.php");
    exit;
}

require_once __DIR__ . '/../../config/databse.php';
require_once __DIR__ . '/../../models/usuario.php';

$database = new Database();
$db = $database->conectar();
$usuarioModel = new Usuario($db);

$todosUsuarios = $usuarioModel->obtenerTodos();
$roles = $usuarioModel->obtenerRoles();

$porPagina = 5;
$totalUsuarios = is_array($todosUsuarios) ? count($todosUsuarios) : 0;
$totalPaginas = max(1, ceil($totalUsuarios / $porPagina));

$pagina = max(1, intval($_GET['pagina'] ?? 1));

if ($pagina > $totalPaginas) {
    $pagina = $totalPaginas;
}

$inicio = ($pagina - 1) * $porPagina;
$usuarios = array_slice($todosUsuarios, $inicio, $porPagina);

$titulo = "Panel de usuarios - Administrador";

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';

?>

<style>

.btn-primario {
    background:#00875F;
    color:white;
    border:0;
    border-radius:10px;
    padding:10px 18px;
    font-weight:600;
    cursor:pointer;
}

.btn-primario:hover {
    background:#01614B;
}

.btn-accion {
    width:34px;
    height:34px;
    padding:0;
    border-radius:8px;
    background:white;
    border:1px solid;
    display:flex;
    align-items:center;
    justify-content:center;
    cursor:pointer;
}

.campo-input {
    width:100%;
    padding:10px 14px;
    border:1px solid #E5E7EB;
    border-radius:10px;
    outline:none;
    background:white;
}

.paginacion {
    padding:14px;
    border-top:1px solid #E5E7EB;
    display:flex;
    justify-content:center;
    gap:6px;
}

.pag {
    width:36px;
    height:36px;
    display:flex;
    align-items:center;
    justify-content:center;
    border:1px solid #E5E7EB;
    border-radius:9px;
    text-decoration:none;
    color:#5F6673;
    font-weight:600;
}

.pag.activa {
    background:#00875F;
    color:white;
    border-color:#00875F;
}

.modal {
    position:fixed;
    inset:0;
    background:rgba(0,0,0,.45);
    backdrop-filter:blur(4px);
    z-index:50;
    display:flex;
    align-items:center;
    justify-content:center;
    padding:20px;
}

.modal-box {
    background:white;
    width:100%;
    max-width:680px;
    border-radius:18px;
    overflow:hidden;
}

.modal-head {
    padding:18px 24px;
    border-bottom:1px solid #E5E7EB;
    display:flex;
    justify-content:space-between;
    align-items:center;
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

</style>


<div class="max-w-7xl mx-auto">

    <!-- ENCABEZADO -->

    <div class="flex justify-between items-center mb-6">

        <div>
            <h2
                class="text-3xl font-bold font-serif-ventanet"
                style="color:#01614B;"
            >
                Gestionar Usuarios
            </h2>

            <p
                class="text-sm mt-1"
                style="color:#5F6673;"
            >
                Gestiona los accesos y permisos de forma centralizada
            </p>
        </div>

        <button
            class="btn-primario"
            onclick="abrirModal('modalCrear')"
        >
            <i class="fas fa-user-plus"></i>
            Nuevo Usuario
        </button>

    </div>


    <!-- ALERTAS -->

    <?php if (isset($_SESSION['alert'])): ?>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: <?= json_encode($_SESSION['alert']['icon']) ?>,
                title: <?= json_encode($_SESSION['alert']['title']) ?>,
                text: <?= json_encode($_SESSION['alert']['text']) ?>,
                confirmButtonColor:'#00875F'
            });
        });
        </script>

        <?php unset($_SESSION['alert']); ?>

    <?php endif; ?>


    <!-- TABLA -->

    <div
        class="bg-white rounded-2xl border overflow-hidden"
        style="border-color:#E5E7EB"
    >

        <div class="overflow-x-auto">

            <table class="w-full text-left">

                <thead>

                    <tr style="background:#01614B;color:white">

                        <th class="px-5 py-3 text-xs uppercase">Nombre</th>
                        <th class="px-5 py-3 text-xs uppercase">Correo</th>
                        <th class="px-5 py-3 text-xs uppercase">Teléfono</th>
                        <th class="px-5 py-3 text-xs uppercase text-center">Rol</th>
                        <th class="px-5 py-3 text-xs uppercase text-center">Estado</th>
                        <th class="px-5 py-3 text-xs uppercase text-center">Acciones</th>

                    </tr>

                </thead>

                <tbody>

                <?php foreach ($usuarios as $u): ?>

                    <?php
                    $activo = strtolower(trim($u['estado'] ?? 'activo')) === 'activo';
                    $nombre = htmlspecialchars($u['nombre'] ?? '');
                    ?>

                    <tr style="border-bottom:1px solid #E5E7EB">

                        <td class="px-5 py-4 font-bold">
                            <?= $nombre ?>
                        </td>

                        <td
                            class="px-5 py-4"
                            style="color:#5F6673"
                        >
                            <?= htmlspecialchars($u['correo'] ?? '') ?>
                        </td>

                        <td
                            class="px-5 py-4"
                            style="color:#5F6673"
                        >
                            <?= htmlspecialchars($u['telefono'] ?? 'No registrado') ?>
                        </td>

                        <td class="px-5 py-4 text-center">

                            <span
                                style="
                                    background:#EBF5FF;
                                    color:#1F3552;
                                    border:1px solid #BFDBFE;
                                    padding:4px 12px;
                                    border-radius:999px;
                                    font-size:.75rem;
                                    font-weight:700;
                                "
                            >
                                <?= htmlspecialchars($u['nombre_rol'] ?? '—') ?>
                            </span>

                        </td>

                        <td class="px-5 py-4 text-center">

                            <?php if ($activo): ?>

                                <span
                                    style="
                                        background:#DDF5EC;
                                        color:#00875F;
                                        border:1px solid #61D0A7;
                                        padding:4px 12px;
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
                                        padding:4px 12px;
                                        border-radius:999px;
                                        font-size:.75rem;
                                        font-weight:700;
                                    "
                                >
                                    Inactivo
                                </span>

                            <?php endif; ?>

                        </td>


                        <!-- ACCIONES -->

                        <td class="px-5 py-4">

                            <div
                                style="
                                    display:flex;
                                    justify-content:center;
                                    gap:8px;
                                "
                            >

                                <!-- EDITAR -->

                                <button
                                    class="btn-accion"
                                    title="Editar"
                                    style="
                                        color:#00875F;
                                        border-color:#61D0A7;
                                    "
                                    onclick='abrirEditar(<?= json_encode($u) ?>)'
                                >
                                    <i class="fas fa-pen"></i>
                                </button>


                                <!-- ACTIVAR / DESACTIVAR -->

                                <button
                                    class="btn-accion"
                                    title="<?= $activo ? 'Desactivar' : 'Activar' ?>"
                                    style="
                                        color:<?= $activo ? '#FFB51B' : '#00875F' ?>;
                                        border-color:<?= $activo ? '#FFB51B' : '#61D0A7' ?>;
                                    "
                                    onclick="cambiarEstado(
                                        <?= intval($u['id_usuario']) ?>,
                                        '<?= $activo ? 'activo' : 'inactivo' ?>'
                                    )"
                                >
                                    <i class="fas <?= $activo ? 'fa-ban' : 'fa-check' ?>"></i>
                                </button>


                                <!-- ELIMINAR -->

                                <button
                                    class="btn-accion"
                                    title="Eliminar"
                                    style="
                                        color:#E53935;
                                        border-color:#E53935;
                                    "
                                    onclick='abrirEliminar(
                                        <?= intval($u['id_usuario']) ?>,
                                        <?= json_encode($u['nombre'] ?? '') ?>
                                    )'
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
                            class="text-center"
                            style="padding:40px;color:#5F6673"
                        >
                            No hay usuarios registrados.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>


        <!-- PAGINACIÓN -->

        <?php if ($totalPaginas > 1): ?>

            <div class="paginacion">

                <?php if ($pagina > 1): ?>

                    <a
                        class="pag"
                        href="?pagina=<?= $pagina - 1 ?>"
                    >
                        ‹
                    </a>

                <?php endif; ?>


                <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>

                    <a
                        class="pag <?= $i == $pagina ? 'activa' : '' ?>"
                        href="?pagina=<?= $i ?>"
                    >
                        <?= $i ?>
                    </a>

                <?php endfor; ?>


                <?php if ($pagina < $totalPaginas): ?>

                    <a
                        class="pag"
                        href="?pagina=<?= $pagina + 1 ?>"
                    >
                        ›
                    </a>

                <?php endif; ?>

            </div>

        <?php endif; ?>

    </div>

</div>



<!-- ========================================================= -->
<!-- MODAL CREAR -->
<!-- ========================================================= -->

<div
    id="modalCrear"
    class="modal"
    style="display:none"
>

    <div class="modal-box">

        <div class="modal-head">

            <h3 class="font-bold text-lg">
                Agregar Usuario
            </h3>

            <button onclick="cerrarModal('modalCrear')">
                <i class="fas fa-times"></i>
            </button>

        </div>


        <form
            action="../../controllers/UsuarioController.php"
            method="POST"
        >

            <!-- IMPORTANTE -->

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
                            class="campo-input"
                            required
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
                            class="campo-input"
                            required
                        >
                    </div>

                    <div>
                        <label>Usuario</label>
                        <input
                            type="text"
                            name="usuario"
                            class="campo-input"
                        >
                    </div>

                    <div>
                        <label>Contraseña *</label>
                        <input
                            type="password"
                            name="password"
                            class="campo-input"
                            required
                        >
                    </div>

                    <div>
                        <label>Confirmar contraseña *</label>
                        <input
                            type="password"
                            name="confirmar_password"
                            class="campo-input"
                            required
                        >
                    </div>

                </div>

                <div class="mt-4">

                    <label>Rol *</label>

                    <select
                        name="rol"
                        class="campo-input"
                        required
                    >

                        <?php foreach ($roles as $r): ?>

                            <option value="<?= $r['id_rol'] ?>">
                                <?= htmlspecialchars($r['nombre']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

            </div>


            <div class="modal-footer">

                <button
                    type="button"
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



<!-- ========================================================= -->
<!-- MODAL EDITAR -->
<!-- ========================================================= -->

<div
    id="modalEditar"
    class="modal"
    style="display:none"
>

    <div class="modal-box">

        <div class="modal-head">

            <h3 class="font-bold text-lg">
                Editar Usuario
            </h3>

            <button onclick="cerrarModal('modalEditar')">
                <i class="fas fa-times"></i>
            </button>

        </div>


        <form
            action="../../controllers/AdminUsuarioController.php?accion=editar"
            method="POST"
        >

            <div class="modal-body">

                <input
                    type="hidden"
                    name="id_usuario"
                    id="editId"
                >

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                    <div>
                        <label>Nombre completo *</label>
                        <input
                            type="text"
                            name="nombre"
                            id="editNombre"
                            class="campo-input"
                            required
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
                            class="campo-input"
                            readonly
                        >
                    </div>

                    <div>
                        <label>Rol *</label>

                        <select
                            name="rol"
                            id="editRol"
                            class="campo-input"
                            required
                        >

                            <?php foreach ($roles as $r): ?>

                                <option value="<?= $r['id_rol'] ?>">
                                    <?= htmlspecialchars($r['nombre']) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                </div>

            </div>


            <div class="modal-footer">

                <button
                    type="button"
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



<!-- ========================================================= -->
<!-- MODAL ELIMINAR -->
<!-- ========================================================= -->

<div
    id="modalEliminar"
    class="modal"
    style="display:none"
>

    <div
        class="modal-box"
        style="max-width:420px"
    >

        <div class="modal-body text-center">

            <div
                style="
                    width:65px;
                    height:65px;
                    margin:auto;
                    border-radius:50%;
                    background:#FDE8E8;
                    display:flex;
                    align-items:center;
                    justify-content:center;
                "
            >
                <i
                    class="fas fa-trash"
                    style="
                        color:#E53935;
                        font-size:24px;
                    "
                ></i>
            </div>

            <h3 class="font-bold text-xl mt-4">
                Eliminar Usuario
            </h3>

            <p class="mt-2" style="color:#5F6673">
                ¿Seguro que deseas eliminar a:
            </p>

            <strong id="elimNombre"></strong>

        </div>


        <div class="modal-footer">

            <button
                type="button"
                onclick="cerrarModal('modalEliminar')"
            >
                Cancelar
            </button>

            <a
                id="elimLink"
                href="#"
                style="
                    background:#E53935;
                    color:white;
                    padding:9px 18px;
                    border-radius:10px;
                    text-decoration:none;
                    font-weight:600;
                "
            >
                Sí, eliminar
            </a>

        </div>

    </div>

</div>



<script>

function abrirModal(id) {
    document.getElementById(id).style.display = 'flex';
}

function cerrarModal(id) {
    document.getElementById(id).style.display = 'none';
}


/* EDITAR */

function abrirEditar(u) {

    document.getElementById('editId').value =
        u.id_usuario || '';

    document.getElementById('editNombre').value =
        u.nombre || '';

    document.getElementById('editTelefono').value =
        u.telefono || '';

    document.getElementById('editCorreo').value =
        u.correo || '';

    document.getElementById('editRol').value =
        u.id_rol || '';

    abrirModal('modalEditar');
}


/* ELIMINAR */

function abrirEliminar(id, nombre) {

    document.getElementById('elimNombre').textContent =
        nombre;

    document.getElementById('elimLink').href =
        '../../controllers/AdminUsuarioController.php?accion=eliminar&id=' + id;

    abrirModal('modalEliminar');
}


/* ACTIVAR / DESACTIVAR */

function cambiarEstado(id, estado) {

    window.location.href =
        '../../controllers/AdminUsuarioController.php' +
        '?accion=toggleEstado' +
        '&id=' + id +
        '&estado=' + encodeURIComponent(estado);
}


/* CERRAR MODAL HACIENDO CLICK AFUERA */

document.querySelectorAll('.modal').forEach(function(modal) {

    modal.addEventListener('click', function(e) {

        if (e.target === modal) {
            modal.style.display = 'none';
        }

    });

});

</script>


<?php require_once __DIR__ . '/../layouts/footer.php'; ?>