<?php

session_start();


// ============================================================
// VERIFICAR SESIÓN
// ============================================================

if (!isset($_SESSION['usuario'])) {

    header("Location: ../usuarios/login.php");

    exit;
}


// ============================================================
// CONEXIÓN
// ============================================================

$titulo = "Panel de clientes - Administrador";

require_once __DIR__ . '/../../config/databse.php';
require_once __DIR__ . '/../../models/cliente.php';


try {

    $database = new Database();

    $db = $database->conectar();

    $clienteModel = new Cliente($db);

    $todosClientes = $clienteModel->obtenerTodos();

} catch (Exception $e) {

    $todosClientes = [];

    $_SESSION['alert'] = [
        'icon' => 'error',
        'title' => 'Error',
        'text' => 'No se pudieron cargar los clientes.'
    ];
}


// ============================================================
// PAGINACIÓN
// ============================================================

$porPagina = 5;

$totalClientes = count($todosClientes);

$totalPaginas = max(
    1,
    (int)ceil($totalClientes / $porPagina)
);


$paginaActual = isset($_GET['pagina'])
    ? (int)$_GET['pagina']
    : 1;


$paginaActual = max(
    1,
    min($paginaActual, $totalPaginas)
);


$inicio = ($paginaActual - 1) * $porPagina;


$clientes = array_slice(
    $todosClientes,
    $inicio,
    $porPagina
);


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


.btn-accion {

    width:34px;

    height:34px;

    padding:0;

    border-radius:8px;

    background:white;

    display:inline-flex;

    align-items:center;

    justify-content:center;

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

    max-width:600px;

    border-radius:18px;

    box-shadow:0 20px 60px rgba(0,0,0,.20);

    overflow:hidden;

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


    <!-- ========================================================
         ENCABEZADO
    ========================================================= -->

    <div class="flex flex-col md:flex-row md:items-center justify-between mb-6 gap-4">

        <div>

            <h2
                class="text-3xl font-bold font-serif-ventanet"
                style="color:#01614B;"
            >
                Gestionar Clientes
            </h2>


            <p
                class="text-sm mt-1"
                style="color:#5F6673;"
            >
                Administra los clientes de tu negocio
            </p>

        </div>


        <button
            type="button"
            onclick="abrirModal('modalCrear')"
            class="btn-primario"
        >

            <i class="fas fa-user-plus"></i>

            Nuevo Cliente

        </button>

    </div>


    <!-- ========================================================
         ALERTAS
    ========================================================= -->

    <?php if (isset($_SESSION['alert'])): ?>

        <script>

        document.addEventListener(
            'DOMContentLoaded',
            function() {

                Swal.fire({

                    icon:
                        <?= json_encode(
                            $_SESSION['alert']['icon'] ?? 'info'
                        ) ?>,

                    title:
                        <?= json_encode(
                            $_SESSION['alert']['title'] ?? 'Aviso'
                        ) ?>,

                    text:
                        <?= json_encode(
                            $_SESSION['alert']['text'] ?? ''
                        ) ?>,

                    confirmButtonText:
                        'Entendido',

                    confirmButtonColor:
                        '#00875F'

                });

            }
        );

        </script>

        <?php unset($_SESSION['alert']); ?>

    <?php endif; ?>


    <!-- ========================================================
         TABLA
    ========================================================= -->

    <div
        class="bg-white rounded-2xl border overflow-hidden"
        style="border-color:#E5E7EB;"
    >

        <div class="overflow-x-auto">

            <table class="w-full text-left border-collapse">

                <thead>

                    <tr style="background:#01614B;">

                        <th class="px-5 py-3 text-xs font-bold uppercase text-white">
                            Nombre Completo
                        </th>

                        <th class="px-5 py-3 text-xs font-bold uppercase text-white">
                            Correo
                        </th>

                        <th class="px-5 py-3 text-xs font-bold uppercase text-white">
                            Teléfono
                        </th>

                        <th class="px-5 py-3 text-xs font-bold uppercase text-white text-center">
                            Estado
                        </th>

                        <th class="px-5 py-3 text-xs font-bold uppercase text-white text-center">
                            Fecha registro
                        </th>

                        <th class="px-5 py-3 text-xs font-bold uppercase text-white text-center">
                            Acciones
                        </th>

                    </tr>

                </thead>


                <tbody>


                <?php foreach ($clientes as $c): ?>


                    <?php

                    $estado = strtolower(
                        (string)($c['estado'] ?? '')
                    );


                    $activo =
                        $estado === 'activo' ||
                        $estado === '1';


                    $fila = $activo
                        ? '#FFFFFF'
                        : '#FDECEC';


                    $hover = $activo
                        ? '#F8F8F8'
                        : '#FDE0E0';


                    $idCliente =
                        (int)($c['id_cliente'] ?? 0);


                    $nombre =
                        $c['nombre'] ?? '';

                    ?>


                    <tr

                        style="
                            border-bottom:1px solid #E5E7EB;
                            background:<?= $fila ?>;
                        "

                        onmouseover="
                            this.style.background='<?= $hover ?>'
                        "

                        onmouseout="
                            this.style.background='<?= $fila ?>'
                        "
                    >


                        <!-- NOMBRE -->

                        <td
                            class="px-5 py-3.5 font-bold text-sm"
                        >

                            <?= htmlspecialchars(
                                $nombre,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </td>


                        <!-- CORREO -->

                        <td
                            class="px-5 py-3.5 text-sm"
                            style="color:#5F6673;"
                        >

                            <?php if (!empty($c['correo'])): ?>

                                <?= htmlspecialchars(
                                    $c['correo'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            <?php else: ?>

                                <span
                                    style="
                                        color:#9CA3AF;
                                        font-style:italic;
                                    "
                                >
                                    Sin correo
                                </span>

                            <?php endif; ?>

                        </td>


                        <!-- TELÉFONO -->

                        <td
                            class="px-5 py-3.5 text-sm"
                            style="color:#5F6673;"
                        >

                            <?php if (!empty($c['telefono'])): ?>

                                <?= htmlspecialchars(
                                    $c['telefono'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            <?php else: ?>

                                <span
                                    style="
                                        color:#9CA3AF;
                                        font-style:italic;
                                    "
                                >
                                    Sin teléfono
                                </span>

                            <?php endif; ?>

                        </td>


                        <!-- ESTADO -->

                        <td
                            class="px-5 py-3.5 text-center"
                        >

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


                        <!-- FECHA -->

                        <td
                            class="px-5 py-3.5 text-center text-sm"
                            style="color:#5F6673;"
                        >

                            <?php

                            if (!empty($c['fecha_registro'])) {

                                echo date(
                                    'd/m/Y',
                                    strtotime(
                                        $c['fecha_registro']
                                    )
                                );

                            } else {

                                echo '—';

                            }

                            ?>

                        </td>


                        <!-- ACCIONES -->

                        <td
                            class="px-5 py-3.5"
                        >

                            <div
                                style="
                                    display:flex;
                                    align-items:center;
                                    justify-content:center;
                                    gap:8px;
                                "
                            >


                                <!-- EDITAR -->

                                <button
                                    type="button"

                                    class="btn-accion"

                                    title="Editar"

                                    onclick='abrirModalEditar(
                                        <?= json_encode(
                                            $c,
                                            JSON_HEX_TAG |
                                            JSON_HEX_APOS |
                                            JSON_HEX_QUOT |
                                            JSON_HEX_AMP
                                        ) ?>
                                    )'

                                    style="
                                        color:#00875F;
                                        border:1px solid #61D0A7;
                                    "
                                >

                                    <i class="fas fa-pen"></i>

                                </button>


                                <!-- ACTIVAR / DESACTIVAR -->

                                <button
                                    type="button"

                                    class="btn-accion"

                                    title="<?= $activo
                                        ? 'Desactivar'
                                        : 'Activar'
                                    ?>"

                                    onclick="
                                        cambiarEstadoCliente(
                                            <?= $idCliente ?>
                                        )
                                    "

                                    style="
                                        color:<?= $activo
                                            ? '#FFB51B'
                                            : '#00875F'
                                        ?>;

                                        border:1px solid <?= $activo
                                            ? '#FFB51B'
                                            : '#61D0A7'
                                        ?>;
                                    "
                                >

                                    <i
                                        class="fas <?= $activo
                                            ? 'fa-ban'
                                            : 'fa-check'
                                        ?>"
                                    ></i>

                                </button>


                                <!-- ELIMINAR -->

                                <button
                                    type="button"

                                    class="btn-accion"

                                    title="Eliminar"

                                    onclick='abrirModalEliminar(
                                        <?= $idCliente ?>,
                                        <?= json_encode(
                                            $nombre,
                                            JSON_HEX_TAG |
                                            JSON_HEX_APOS |
                                            JSON_HEX_QUOT |
                                            JSON_HEX_AMP
                                        ) ?>
                                    )'

                                    style="
                                        color:#E53935;
                                        border:1px solid #E53935;
                                    "
                                >

                                    <i class="fas fa-trash"></i>

                                </button>


                            </div>

                        </td>

                    </tr>


                <?php endforeach; ?>


                <?php if (empty($clientes)): ?>

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

                            No hay clientes registrados.

                        </td>

                    </tr>

                <?php endif; ?>


                </tbody>

            </table>

        </div>


        <!-- ======================================================
             PAGINACIÓN
        ======================================================= -->

        <?php if ($totalClientes > $porPagina): ?>

            <nav class="paginacion">


                <!-- ANTERIOR -->

                <a
                    class="pag
                        <?= $paginaActual <= 1
                            ? 'deshabilitado'
                            : ''
                        ?>"

                    href="?pagina=<?= max(
                        1,
                        $paginaActual - 1
                    ) ?>"
                >
                    «
                </a>


                <!-- NÚMEROS -->

                <?php for (
                    $i = 1;
                    $i <= $totalPaginas;
                    $i++
                ): ?>

                    <a
                        class="pag
                            <?= $i === $paginaActual
                                ? 'activa'
                                : ''
                            ?>"

                        href="?pagina=<?= $i ?>"
                    >

                        <?= $i ?>

                    </a>

                <?php endfor; ?>


                <!-- SIGUIENTE -->

                <a
                    class="pag
                        <?= $paginaActual >= $totalPaginas
                            ? 'deshabilitado'
                            : ''
                        ?>"

                    href="?pagina=<?= min(
                        $totalPaginas,
                        $paginaActual + 1
                    ) ?>"
                >
                    »
                </a>


            </nav>

        <?php endif; ?>


    </div>

</div>


<!-- ============================================================
     MODAL CREAR
============================================================ -->

<div
    id="modalCrear"
    class="modal-fondo oculto"
>

    <div class="modal-caja">


        <div class="modal-header">

            <h3>
                Agregar Cliente
            </h3>


            <button
                type="button"
                class="btn-cerrar"
                onclick="cerrarModal('modalCrear')"
            >

                <i class="fas fa-times"></i>

            </button>

        </div>


        <form
            action="../../controllers/ClienteController.php?accion=registrar"
            method="POST"
        >


            <div class="modal-body">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">


                    <!-- NOMBRE -->

                    <div>

                        <label>
                            Nombre *
                        </label>

                        <input
                            type="text"
                            name="nombre"
                            required
                            class="campo-input"
                        >

                    </div>


                    <!-- TELÉFONO -->

                    <div>

                        <label>
                            Teléfono
                        </label>

                        <input
                            type="text"
                            name="telefono"
                            class="campo-input"
                        >

                    </div>


                    <!-- CORREO -->

                    <div class="md:col-span-2">

                        <label>
                            Correo
                        </label>

                        <input
                            type="email"
                            name="correo"
                            class="campo-input"
                        >

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
                    Guardar Cliente
                </button>

            </div>


        </form>

    </div>

</div>


<!-- ============================================================
     MODAL EDITAR
============================================================ -->

<div
    id="modalEditar"
    class="modal-fondo oculto"
>

    <div class="modal-caja">


        <div class="modal-header">

            <h3>
                Editar Cliente
            </h3>


            <button
                type="button"
                class="btn-cerrar"
                onclick="cerrarModal('modalEditar')"
            >

                <i class="fas fa-times"></i>

            </button>

        </div>


        <form
            action="../../controllers/ClienteController.php?accion=editar"
            method="POST"
        >


            <input
                type="hidden"
                name="id_cliente"
                id="editId"
            >


            <div class="modal-body">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">


                    <!-- NOMBRE -->

                    <div>

                        <label>
                            Nombre *
                        </label>

                        <input
                            type="text"
                            name="nombre"
                            id="editNombre"
                            required
                            class="campo-input"
                        >

                    </div>


                    <!-- TELÉFONO -->

                    <div>

                        <label>
                            Teléfono
                        </label>

                        <input
                            type="text"
                            name="telefono"
                            id="editTelefono"
                            class="campo-input"
                        >

                    </div>


                    <!-- CORREO -->

                    <div class="md:col-span-2">

                        <label>
                            Correo
                        </label>

                        <input
                            type="email"
                            name="correo"
                            id="editCorreo"
                            class="campo-input"
                        >

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


<!-- ============================================================
     MODAL ELIMINAR
============================================================ -->

<div
    id="modalEliminar"
    class="modal-fondo oculto"
>

    <div class="modal-caja pequeno">


        <div
            style="
                padding:35px 30px 20px;
                text-align:center;
            "
        >

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
                    style="
                        color:#E53935;
                        font-size:28px;
                    "
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
                Eliminar Cliente
            </h3>


            <p
                style="
                    margin:0;
                    color:#5F6673;
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


// ============================================================
// ABRIR MODAL
// ============================================================

function abrirModal(id)
{
    const modal =
        document.getElementById(id);

    if (modal) {

        modal.classList.remove('oculto');

    }
}


// ============================================================
// CERRAR MODAL
// ============================================================

function cerrarModal(id)
{
    const modal =
        document.getElementById(id);

    if (modal) {

        modal.classList.add('oculto');

    }
}


// ============================================================
// EDITAR
// ============================================================

function abrirModalEditar(cliente)
{

    document.getElementById('editId').value =
        cliente.id_cliente || '';


    document.getElementById('editNombre').value =
        cliente.nombre || '';


    document.getElementById('editTelefono').value =
        cliente.telefono || '';


    document.getElementById('editCorreo').value =
        cliente.correo || '';


    abrirModal('modalEditar');
}


// ============================================================
// ELIMINAR
// ============================================================

let clienteEliminar = null;


function abrirModalEliminar(
    id,
    nombre
)
{

    clienteEliminar = id;


    document.getElementById(
        'elimNombre'
    ).textContent = nombre;


    abrirModal('modalEliminar');
}


function confirmarEliminar()
{

    if (!clienteEliminar) {

        return;

    }


    window.location.href =
        '../../controllers/ClienteController.php' +
        '?accion=eliminar' +
        '&id=' +
        encodeURIComponent(clienteEliminar);
}


// ============================================================
// CAMBIAR ESTADO
// ============================================================

function cambiarEstadoCliente(id)
{

    if (!id) {

        return;

    }


    window.location.href =
        '../../controllers/ClienteController.php' +
        '?accion=toggleEstado' +
        '&id=' +
        encodeURIComponent(id);
}


// ============================================================
// CERRAR MODAL AL HACER CLICK AFUERA
// ============================================================

document
    .querySelectorAll('.modal-fondo')
    .forEach(function(modal) {

        modal.addEventListener(
            'click',
            function(e) {

                if (e.target === modal) {

                    modal.classList.add('oculto');

                }

            }
        );

    });


// ============================================================
// ESC PARA CERRAR
// ============================================================

document.addEventListener(
    'keydown',
    function(e) {

        if (e.key === 'Escape') {

            document
                .querySelectorAll('.modal-fondo')
                .forEach(function(modal) {

                    modal.classList.add('oculto');

                });

        }

    }
);

</script>


<?php

require_once __DIR__ . '/../layouts/footer.php';

?>