<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'teacher') {
    header('Location: login.php');
    exit();
}
$_SESSION['current_academic_year'] = '2025';

require_once 'db_manager.php';

$conn = getDbConnection();
$teacher_name = $_SESSION['username'];
$teacher_id = $_SESSION['user_id'];
$asignaturas = [];

$sql = "SELECT id, subject, grade FROM asignaturas WHERE teacher = ?";
if ($stmt_asignaturas = $conn->prepare($sql)) {
    $stmt_asignaturas->bind_param("s", $teacher_name);
    $stmt_asignaturas->execute();
    $result = $stmt_asignaturas->get_result();
    $asignaturas = $result->fetch_all(MYSQLI_ASSOC);
    $stmt_asignaturas->close();
}
$conn->close();

// No incluimos el header de bootstrap, usaremos nuestros propios estilos.
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal LMS del Docente</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <style>
        /* Pequeños ajustes para pulir el diseño */
        @import url('https://rsms.me/inter/inter.css');
        html { font-family: 'Inter', sans-serif; }
        .form-select {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 0.5rem center;
            background-repeat: no-repeat;
            background-size: 1.5em 1.5em;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
        }
    </style>
</head>
<body class="bg-gray-100">

    <div class="flex h-screen bg-gray-100">
        <div class="hidden md:flex md:flex-shrink-0">
            <div class="flex flex-col w-64">
                <div class="flex flex-col h-0 flex-1 bg-white shadow-lg">
                    <?php include 'components/aside.php'; ?>
                </div>
            </div>
        </div>

        <div class="flex flex-col w-0 flex-1 overflow-hidden">
            <main class="flex-1 relative z-0 overflow-y-auto focus:outline-none p-6 md:p-8">
                
                <div class="pb-4 border-b border-gray-200 mb-8">
                    <h1 class="text-3xl md:text-4xl font-bold text-gray-800">Portal de Gestión LMS</h1>
                    <p class="mt-1 text-md text-gray-600">Bienvenido de nuevo, <span class="font-semibold text-indigo-600"><?php echo htmlspecialchars($teacher_name); ?></span></p>
                </div>

                <div class="bg-white rounded-xl shadow-md p-6 mb-8">
                    <h2 class="text-xl font-semibold text-gray-900 flex items-center mb-4">
                        <span class="flex items-center justify-center w-8 h-8 bg-indigo-100 text-indigo-600 rounded-full mr-3 font-bold">1</span>
                        Seleccione una Asignatura
                    </h2>
                    <select class="form-select w-full p-3 border-gray-300 rounded-lg text-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" id="asignaturaSelector">
                        <option value="">Elija una materia para empezar...</option>
                        <?php foreach ($asignaturas as $asignatura): ?>
                            <option value="<?php echo $asignatura['id']; ?>"><?php echo htmlspecialchars($asignatura['subject']) . " - Grado: " . htmlspecialchars($asignatura['grade']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="lmsContent" class="hidden">
                    <div class="bg-white rounded-xl shadow-md">
                        <div class="p-6 border-b border-gray-200 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                            <div>
                                <h3 id="selectedAsignaturaTitle" class="text-2xl font-bold text-gray-900"></h3>
                                <p class="text-gray-500">Gestione las actividades para esta clase.</p>
                            </div>
                            <button id="openModalBtn" class="w-full md:w-auto bg-indigo-600 text-white font-bold py-2 px-5 rounded-lg hover:bg-indigo-700 transition duration-300 flex items-center justify-center">
                                <i class="fas fa-plus mr-2"></i>Crear Actividad
                            </button>
                        </div>
                        <div id="listaActividadesContainer" class="p-2 md:p-6">
                            </div>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <div id="modalCrearActividad" class="fixed inset-0 bg-gray-800 bg-opacity-75 flex items-center justify-center hidden z-50 p-4">
        <div class="relative bg-white rounded-lg shadow-xl w-full max-w-2xl">
            <form id="formCrearActividad" class="w-full">
                <div class="flex justify-between items-center p-5 border-b">
                    <h3 class="text-2xl font-semibold text-gray-800">Nueva Actividad</h3>
                    <button type="button" id="closeModalBtn" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
                </div>
                <div class="p-6 space-y-4">
                    <div id="formError" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded hidden"></div>
                    <input type="hidden" name="id_asignatura" id="id_asignatura_actividad">
                    <input type="hidden" name="id_docente" value="<?php echo $teacher_id; ?>">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Parcial</label>
                            <select name="parcial" class="form-select mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm" required>
                                <option>Primer Parcial</option><option>Segundo Parcial</option><option>Tercer Parcial</option><option>Cuarto Parcial</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de Actividad</label>
                            <select name="tipo_actividad" class="form-select mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm" required>
                                <option>Tarea</option><option>Examen</option><option>Proyecto</option><option>Otro</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nombre de la Actividad</label>
                        <input type="text" name="nombre_actividad" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Instrucciones</label>
                        <textarea name="descripcion" rows="3" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm"></textarea>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Puntaje Máximo</label>
                            <input type="number" step="0.01" name="puntaje_maximo" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Fecha Límite de Entrega (Opcional)</label>
                            <input type="datetime-local" name="fecha_entrega" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm">
                        </div>
                    </div>
                </div>
                <div class="flex justify-end p-5 border-t bg-gray-50 rounded-b-lg">
                    <button type="button" id="cancelModalBtn" class="bg-gray-200 text-gray-700 font-bold py-2 px-4 rounded-lg mr-2 hover:bg-gray-300">Cerrar</button>
                    <button type="submit" class="bg-indigo-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-indigo-700">Guardar Actividad</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../js/jquery.js"></script>
    <script>
    $(document).ready(function() {
        // --- Manejo del Modal ---
        const modal = $('#modalCrearActividad');
        $('#openModalBtn').on('click', () => modal.removeClass('hidden'));
        $('#closeModalBtn, #cancelModalBtn').on('click', () => modal.addClass('hidden'));

        function cargarActividades(asignaturaId) {
            const container = $('#listaActividadesContainer');
            container.html('<p class="text-center text-gray-500 py-8">Cargando...</p>');
            $.ajax({
                url: 'ajax_get_activities.php', type: 'POST', data: { id_asignatura: asignaturaId }, dataType: 'json',
                success: function(response) {
                    if (response.success && response.actividades.length > 0) {
                        let table = '<div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200"><thead class="bg-gray-50"><tr>' +
                            '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actividad</th>' +
                            '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Parcial</th>' +
                            '<th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Puntaje</th>' +
                            '<th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th></tr></thead><tbody class="bg-white divide-y divide-gray-200">';
                        response.actividades.forEach(act => {
                            table += `<tr class="hover:bg-gray-50"><td class="px-6 py-4 whitespace-nowrap"><div class="text-sm font-medium text-gray-900">${act.nombre_actividad}</div><div class="text-sm text-gray-500">${act.tipo_actividad}</div></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${act.parcial}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-semibold text-gray-900">${act.puntaje_maximo}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium"><a href="calificar_actividad.php?id=${act.id}" class="text-indigo-600 hover:text-indigo-900">Ver y Calificar</a></td></tr>`;
                        });
                        table += '</tbody></table></div>';
                        container.html(table);
                    } else {
                        container.html(`<div class="text-center py-10 px-6 bg-gray-50 rounded-lg">
                            <i class="fas fa-folder-open fa-3x text-gray-400"></i>
                            <h3 class="mt-4 text-lg font-medium text-gray-900">Aún no hay actividades</h3>
                            <p class="mt-1 text-sm text-gray-500">¡Anímate a crear la primera para esta asignatura!</p></div>`);
                    }
                },
                error: function() { container.html('<p class="p-4 text-center text-red-500">Hubo un error al cargar las actividades. Revisa la conexión y el archivo ajax_get_activities.php.</p>'); }
            });
        }

        $('#asignaturaSelector').on('change', function() {
            const asignaturaId = $(this).val();
            const asignaturaTexto = $(this).find('option:selected').text();
            if (asignaturaId) {
                $('#lmsContent').removeClass('hidden');
                $('#selectedAsignaturaTitle').text(asignaturaTexto.trim());
                $('#id_asignatura_actividad').val(asignaturaId);
                cargarActividades(asignaturaId);
            } else {
                $('#lmsContent').addClass('hidden');
            }
        });

        $('#formCrearActividad').on('submit', function(e) {
            e.preventDefault();
            const asignaturaId = $('#id_asignatura_actividad').val();
            $.ajax({
                url: 'ajax_save_activity.php', type: 'POST', data: $(this).serialize(), dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        modal.addClass('hidden');
                        $('#formCrearActividad')[0].reset();
                        cargarActividades(asignaturaId);
                    } else {
                        $('#formError').removeClass('hidden').text(response.message || 'Ocurrió un error desconocido.');
                    }
                },
                error: function() { $('#formError').removeClass('hidden').text('Error de comunicación al guardar.'); }
            });
        });
    });
    </script>
</body>
</html>