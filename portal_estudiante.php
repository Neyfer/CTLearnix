<?php
session_start();
if (!isset($_SESSION['student_id']) || $_SESSION['role'] !== 'student') {
    header('Location: student_login.php');
    exit();
}
$_SESSION['current_academic_year'] = '2025';
require_once 'db_manager.php';
$conn = getDbConnection();
$student_id = $_SESSION['student_id'];

// --- OBTENER TODA LA INFORMACIÓN PARA EL PORTAL ---

// 1. Info del Estudiante para el Perfil
$stmt_student = $conn->prepare("SELECT s.*, p.name as parent_name, p.tel as parent_tel FROM students s LEFT JOIN parents p ON s.id = p.student_id WHERE s.id = ?");
$stmt_student->bind_param("i", $student_id);
$stmt_student->execute();
$student_info = $stmt_student->get_result()->fetch_assoc();
$student_name = $student_info['name'] ?? 'Alumno';
$student_grade = $student_info['grade'] ?? 0;
$student_seccion = $student_info['seccion'] ?? 0;
$stmt_student->close();
$grade_filter = $student_grade . $student_seccion;

// 2. Lista de Asignaturas
$sql_asignaturas = "SELECT DISTINCT asi.id, asi.subject FROM asignaturas asi WHERE asi.grade = ?";
$stmt_asignaturas = $conn->prepare($sql_asignaturas);
$stmt_asignaturas->bind_param("s", $grade_filter);
$stmt_asignaturas->execute();
$lista_asignaturas = $stmt_asignaturas->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_asignaturas->close();

// 3. Tareas Pendientes
$sql_pendientes = "
    SELECT 
        asi.subject, act.id as actividad_id, act.nombre_actividad, act.descripcion, act.tipo_actividad,
        act.puntaje_maximo, act.fecha_entrega, NULL as entrega_id, NULL as calificacion, NULL as comentario_docente
    FROM asignaturas asi
    JOIN lms_actividades act ON act.id_asignatura = asi.id
    LEFT JOIN lms_entregas ent ON ent.id_actividad = act.id AND ent.id_estudiante = ?
    WHERE asi.grade = ? AND ent.id IS NULL AND (act.fecha_entrega >= NOW() OR act.fecha_entrega IS NULL)
    ORDER BY act.fecha_entrega ASC, act.id DESC
";
$stmt_pendientes = $conn->prepare($sql_pendientes);
$stmt_pendientes->bind_param("is", $student_id, $grade_filter);
$stmt_pendientes->execute();
$tareas_pendientes = $stmt_pendientes->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_pendientes->close();

// 4. Notas Finales para el boletín
$parciales = ['primer_parcial', 'segundo_parcial', 'tercer_parcial', 'cuarto_parcial'];
$notas_finales = [];
foreach ($lista_asignaturas as $asignatura) {
    $subject_name = $asignatura['subject'];
    $notas_finales[$subject_name] = [];
    foreach ($parciales as $parcial) {
        $stmt_nota = $conn->prepare("SELECT nota FROM `$parcial` WHERE student_id = ? AND subject = ?");
        if ($stmt_nota) {
            $stmt_nota->bind_param("is", $student_id, $subject_name);
            $stmt_nota->execute();
            $nota_result = $stmt_nota->get_result()->fetch_assoc();
            $notas_finales[$subject_name][$parcial] = $nota_result['nota'] ?? '-';
            $stmt_nota->close();
        }
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal del Estudiante</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <style> 
        @import url('https://rsms.me/inter/inter.css'); 
        html { font-family: 'Inter', sans-serif; }
        .tab-main-btn { @apply cursor-pointer whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-200; }
        .tab-main-btn.active { @apply border-indigo-500 text-indigo-600; }
        .tab-main-btn:not(.active) { @apply border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300; }
    </style>
</head>
<body class="bg-gray-100">
    <div class="flex h-screen bg-gray-100">
        <div class="hidden md:flex md:flex-shrink-0"><div class="flex flex-col w-64 bg-white shadow-lg"><?php include 'components/student_aside.php'; ?></div></div>
        <div class="flex flex-col w-0 flex-1 overflow-hidden">
            <main class="flex-1 relative z-0 overflow-y-auto focus:outline-none p-4 md:p-8">
                <div class="pb-4 border-b border-gray-200 mb-6 flex justify-between items-center">
                    <div><h1 class="text-2xl sm:text-3xl font-bold text-gray-800">Portal del Estudiante</h1><p class="mt-1 text-sm md:text-md text-gray-600">Bienvenido, <span class="font-semibold text-indigo-600"><?php echo htmlspecialchars($student_name); ?></span>.</p></div>
                    <img src="../img/mono.png" alt="Logo Institución" class="h-12 w-12 rounded-full">
                </div>

                <div class="mb-6"><div class="border-b border-gray-200"><nav class="-mb-px flex space-x-6" aria-label="Tabs">
                    <button class="tab-main-btn active" data-tab-target="#lms-panel">LMS - Tareas</button>
                    <button class="tab-main-btn" data-tab-target="#notas-panel">Mis Calificaciones</button>
                    <button class="tab-main-btn" data-tab-target="#perfil-panel">Mi Perfil</button>
                    <button class="tab-main-btn" data-tab-target="#acerca-panel">Acerca de</button>
                </nav></div></div>

                <div id="lms-panel" class="tab-panel">
                    <nav class="flex space-x-4 mb-4" aria-label="LMS Views">
                        <button class="lms-tab-btn font-semibold text-white bg-indigo-600 py-2 px-4 rounded-md" data-view="pendientes">Tareas Pendientes <span class="bg-indigo-800 text-white ml-2 py-0.5 px-2 rounded-full text-xs"><?php echo count($tareas_pendientes); ?></span></button>
                        <button class="lms-tab-btn font-semibold text-gray-600 bg-gray-200 hover:bg-gray-300 py-2 px-4 rounded-md" data-view="materias">Buscar por Materia</button>
                    </nav>
                    <div id="view-pendientes" class="lms-view-container space-y-4">
                        <?php if(empty($tareas_pendientes)): ?>
                            <div class="bg-white text-center p-8 rounded-xl shadow-md"><i class="fas fa-check-circle fa-3x text-green-500"></i><h3 class="mt-4 text-xl font-bold">¡Todo en orden!</h3><p class="mt-2">No tienes tareas pendientes.</p></div>
                        <?php else: ?>
                            <?php foreach($tareas_pendientes as $act): ?>
                                <div class="bg-white rounded-lg shadow-sm p-4 hover:shadow-md transition">
                                    <div class="grid grid-cols-3 gap-2 items-start"><div class="col-span-3 sm:col-span-2"><p class="text-xs font-semibold text-indigo-600"><?php echo htmlspecialchars($act['subject']); ?></p><h4 class="text-base font-semibold text-gray-900"><?php echo htmlspecialchars($act['nombre_actividad']); ?></h4></div><div class="col-span-3 sm:col-span-1 text-left sm:text-right"><p class="text-xs text-gray-500 mt-1">Límite: <span class="font-medium"><?php echo $act['fecha_entrega'] ? date('d/m/Y h:i A', strtotime($act['fecha_entrega'])) : 'N/A'; ?></span></p></div></div>
                                    <div class="flex justify-end items-center mt-3"><button type="button" class="btn-ver-entregar bg-indigo-600 text-white text-sm font-bold py-2 px-4 rounded-lg hover:bg-indigo-700 transition" data-actividad='<?php echo json_encode($act, JSON_HEX_APOS | JSON_HEX_QUOT); ?>'><i class="fas fa-paper-plane mr-2"></i> Realizar Entrega</button></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div id="view-materias" class="lms-view-container hidden">
                        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                            <?php foreach($lista_asignaturas as $asignatura): ?>
                                <div class="subject-card bg-white p-4 rounded-lg shadow-sm hover:shadow-lg hover:border-indigo-500 border-2 border-transparent transition cursor-pointer text-center" data-id="<?php echo $asignatura['id']; ?>"><h3 class="font-bold text-gray-800"><?php echo htmlspecialchars($asignatura['subject']); ?></h3></div>
                            <?php endforeach; ?>
                        </div>
                        <div id="actividades-por-materia-container" class="mt-8"></div>
                    </div>
                </div>
                <div id="notas-panel" class="tab-panel hidden"></div>
                <div id="perfil-panel" class="tab-panel hidden"></div>
                <div id="acerca-panel" class="tab-panel hidden"></div>
            </main>
        </div>
    </div>
    
    <div id="modalEntregarTarea" class="fixed inset-0 bg-gray-800 bg-opacity-75 flex items-center justify-center hidden z-50 p-4"></div>

    <script src="../js/jquery.js"></script>
    <script>
    $(document).ready(function() {
        const modal = $('#modalEntregarTarea');

        // --- LÓGICA DE PESTAÑAS ---
        $('.tab-main-btn').on('click', function() {
            const targetPanel = $(this).data('tab-target');
            $('.tab-main-btn').removeClass('active');
            $(this).addClass('active');
            $('.tab-panel').addClass('hidden');
            $(targetPanel).removeClass('hidden');
        });

        $('.lms-tab-btn').on('click', function() {
            const viewToShow = $(this).data('view');
            $('.lms-tab-btn').removeClass('bg-indigo-600 text-white').addClass('bg-gray-200 text-gray-600');
            $(this).removeClass('bg-gray-200 text-gray-600').addClass('bg-indigo-600 text-white');
            $('.lms-view-container').addClass('hidden');
            $('#view-' + viewToShow).removeClass('hidden');
            $('#actividades-por-materia-container').html('');
            $('.subject-card').removeClass('bg-indigo-100 border-indigo-500');
        });
        
        // --- LÓGICA PARA CARGAR ACTIVIDADES POR MATERIA ---
        $('.subject-card').on('click', function() {
            const subjectId = $(this).data('id');
            const container = $('#actividades-por-materia-container');
            $('.subject-card').removeClass('bg-indigo-100 border-indigo-500');
            $(this).addClass('bg-indigo-100 border-indigo-500');
            container.html('<p class="text-center text-gray-500 py-8">Cargando...</p>');
            $.ajax({
                url: 'ajax_get_subject_activities.php', type: 'POST', data: { subject_id: subjectId }, dataType: 'json',
                success: function(response) {
                    let content = `<div class="bg-white rounded-xl shadow-md mt-6"><ul class="divide-y divide-gray-200">`;
                    if (response.success && response.actividades.length > 0) {
                        response.actividades.forEach(act => {
                            let calificacionHtml = (act.calificacion !== null) ? `<p class="font-bold text-xl text-gray-800">${act.calificacion}<span class="text-sm text-gray-500">/${act.puntaje_maximo}</span></p>` : '';
                            let commentIcon = (act.comentario_docente) ? '<i class="fas fa-comment-dots text-sky-600" title="Hay un comentario"></i>' : '';
                            let estadoHtml = '';
                            if (act.calificacion !== null) { estadoHtml = `<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">Calificado ${commentIcon}</span>`; } 
                            else if (act.entrega_id !== null) { estadoHtml = `<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">Entregado</span>`; } 
                            else { estadoHtml = `<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Pendiente</span>`; }
                            let botonTexto = '<i class="fas fa-paper-plane mr-1"></i> Realizar Entrega';
                            if (act.calificacion !== null) { botonTexto = '<i class="fas fa-eye mr-1"></i> Ver Detalles'; }
                            else if (act.entrega_id !== null) { botonTexto = '<i class="fas fa-edit mr-1"></i> Editar Entrega'; }
                            content += `<li class="p-4 hover:bg-gray-50"><div class="grid grid-cols-3 gap-2 items-start"><div class="col-span-2"><h4 class="text-base sm:text-lg font-semibold">${act.nombre_actividad}</h4><p class="text-xs sm:text-sm text-gray-500 mt-1">${act.fecha_entrega ? 'Límite: ' + new Date(act.fecha_entrega).toLocaleDateString() : 'N/A'}</p></div><div class="text-right">${calificacionHtml}</div></div><div class="flex justify-between items-center mt-3"><div class="flex items-center space-x-2">${estadoHtml}</div><button type="button" class="btn-ver-entregar bg-indigo-600 text-white text-xs sm:text-sm font-bold py-2 px-3 rounded-lg" data-actividad='${JSON.stringify(act, null, 2)}'>${botonTexto}</button></div></li>`;
                        });
                    } else {
                        content += '<li class="p-4 text-center text-gray-500">No hay actividades para esta materia.</li>';
                    }
                    content += `</ul></div>`;
                    container.html(content);
                },
                error: function() { container.html('<p class="text-center text-red-500 py-8">Error al cargar las actividades.</p>');}
            });
        });
        
        // --- SCRIPT FUNCIONAL PARA EL MODAL Y FORMULARIO ---
        function abrirModal(actividad) {
            modal.find('#modalTitle').text(actividad.nombre_actividad);
            modal.find('#modalDescription').text(actividad.descripcion || 'No hay instrucciones adicionales.');
            modal.find('#modal_id_actividad').val(actividad.actividad_id);
            modal.find('textarea[name="texto_respuesta"]').val(actividad.texto_respuesta || '');
            const seccionCalificacion = modal.find('#seccionCalificacion');
            if (actividad.calificacion !== null) {
                seccionCalificacion.removeClass('hidden').html(`<h4 class="font-bold text-lg text-green-700">Calificación</h4><p class="mb-0 text-2xl font-bold">${actividad.calificacion} <span class="text-base text-gray-600">/ ${actividad.puntaje_maximo}</span></p>`);
            } else {
                seccionCalificacion.addClass('hidden');
            }
            const seccionComentario = modal.find('#seccionComentario');
            if (actividad.comentario_docente && actividad.comentario_docente.trim() !== '') {
                seccionComentario.removeClass('hidden').html(`<h4 class="font-bold text-lg text-blue-700">Retroalimentación</h4><p class="mt-2 p-3 bg-gray-50 rounded">${actividad.comentario_docente}</p>`);
            } else {
                seccionComentario.addClass('hidden');
            }
            if (actividad.calificacion !== null) {
                modal.find('#zonaDeEntrega, #submitButton').hide();
            } else {
                modal.find('#zonaDeEntrega, #submitButton').show();
            }
            modal.removeClass('hidden');
        }
        $(document).on('click', '.btn-ver-entregar', function() {
            abrirModal($(this).data('actividad'));
        });
        modal.find('.btn-cerrar-modal').on('click', function() {
            modal.addClass('hidden');
        });
        $('#formEntregarTarea').on('submit', function(e) {
            e.preventDefault();
            let formData = new FormData(this);
            let submitButton = $(this).find('button[type="submit"]');
            submitButton.html('<i class="fas fa-spinner fa-spin mr-2"></i> Enviando...').prop('disabled', true);
            $('#entregaError').addClass('hidden');
            $.ajax({
                url: 'ajax_submit_assignment.php', type: 'POST', data: formData, processData: false, contentType: false, dataType: 'json',
                success: function(response) {
                    if(response.success) {
                        alert('¡Entrega procesada con éxito!');
                        window.location.reload();
                    } else {
                        $('#entregaError').removeClass('hidden').text(response.message || 'Ocurrió un error.');
                        submitButton.html('Confirmar Entrega').prop('disabled', false);
                    }
                }, error: function() {
                    $('#entregaError').removeClass('hidden').text('Error de comunicación.');
                    submitButton.html('Confirmar Entrega').prop('disabled', false);
                }
            });
        });
    });
    </script>
</body>
</html>