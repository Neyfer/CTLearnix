<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'teacher') {
    header('Location: login.php');
    exit();
}
$_SESSION['current_academic_year'] = '2025';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('Error: ID de actividad no válido.');
}
$id_actividad = $_GET['id'];

require_once 'db_manager.php';
$conn = getDbConnection();

$stmt_act = $conn->prepare("SELECT a.*, asi.subject, asi.grade FROM lms_actividades a JOIN asignaturas asi ON a.id_asignatura = asi.id WHERE a.id = ?");
$stmt_act->bind_param("i", $id_actividad);
$stmt_act->execute();
$actividad = $stmt_act->get_result()->fetch_assoc();
$stmt_act->close();

if (!$actividad) { die('Actividad no encontrada.'); }

$grade_string = (string)$actividad['grade'];
$grade_num = 0; $section_num = 0;
if (strlen($grade_string) >= 2 && strlen($grade_string) <= 3) {
    $grade_num = (int)substr($grade_string, 0, 1);
    $section_num = (int)substr($grade_string, 1);
} elseif (strlen($grade_string) >= 4) {
    $grade_num = (int)substr($grade_string, 0, 2);
    $section_num = (int)substr($grade_string, 2);
}

$sql_students = "
    SELECT s.id as student_id, s.name as student_name, e.id as entrega_id, e.texto_respuesta, e.ruta_archivo, e.calificacion, e.comentario_docente
    FROM students s
    LEFT JOIN lms_entregas e ON s.id = e.id_estudiante AND e.id_actividad = ?
    WHERE s.grade = ? AND s.seccion = ? ORDER BY s.name ASC
";
$stmt_students = $conn->prepare($sql_students);
$stmt_students->bind_param("iii", $id_actividad, $grade_num, $section_num);
$stmt_students->execute();
$entregas = $stmt_students->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_students->close();
$conn->close();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calificar: <?php echo htmlspecialchars($actividad['nombre_actividad']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <style> @import url('https://rsms.me/inter/inter.css'); html { font-family: 'Inter', sans-serif; } </style>
</head>
<body class="bg-gray-100">
    <div class="flex h-screen bg-gray-100">
        <div class="hidden md:flex md:flex-shrink-0">
            <div class="flex flex-col w-64 bg-white shadow-lg">
                <?php include 'components/aside.php'; ?>
            </div>
        </div>

        <div class="flex flex-col w-0 flex-1 overflow-hidden">
            <main class="flex-1 relative z-0 overflow-y-auto focus:outline-none p-6 md:p-8">
                <div class="pb-4 border-b border-gray-200 mb-6">
                    <a href="gestion_lms.php" class="text-sm text-indigo-600 hover:text-indigo-800 mb-2 inline-block"><i class="fas fa-arrow-left mr-2"></i>Volver a mis cursos</a>
                    <h1 class="text-3xl md:text-4xl font-bold text-gray-800"><?php echo htmlspecialchars($actividad['nombre_actividad']); ?></h1>
                    <p class="mt-1 text-md text-gray-600"><?php echo htmlspecialchars($actividad['subject']); ?> - Grado: <?php echo htmlspecialchars($actividad['grade']); ?></p>
                </div>

                <div class="bg-white rounded-xl shadow-md">
                    <div class="p-6 border-b"><h2 class="text-xl font-bold text-gray-800">Entregas de Alumnos</h2></div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="py-3 px-6 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Alumno</th>
                                    <th class="py-3 px-6 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Entrega</th>
                                    <th class="py-3 px-6 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Calificación ( / <?php echo $actividad['puntaje_maximo']; ?>)</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($entregas as $entrega): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 align-top font-medium text-gray-900"><?php echo htmlspecialchars($entrega['student_name']); ?></td>
                                        <td class="px-6 py-4 align-top text-sm text-gray-700">
                                            <?php if($entrega['entrega_id']): ?>
                                                <?php if($entrega['ruta_archivo']): ?>
                                                    <a href="<?php echo htmlspecialchars($entrega['ruta_archivo']); ?>" target="_blank" class="inline-flex items-center text-indigo-600 hover:underline font-semibold mb-2">
                                                        <i class="fas fa-file-download mr-2"></i>Ver Archivo Adjunto
                                                    </a>
                                                <?php endif; ?>
                                                <?php if($entrega['texto_respuesta']): ?>
                                                    <div class="mt-2 p-2 bg-gray-100 border rounded-md text-xs text-gray-600"><?php echo nl2br(htmlspecialchars($entrega['texto_respuesta'])); ?></div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="italic text-gray-500">Sin entregar</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 align-top">
                                            <?php if($entrega['entrega_id']): ?>
                                                <form class="form-calificar space-y-3">
                                                    <input type="hidden" name="id_entrega" value="<?php echo $entrega['entrega_id']; ?>">

                                                    <!-- Contenedor flex para alinear nota y comentario -->
                                                    <div class="flex flex-col sm:flex-row gap-4 items-start">
                                                        <!-- Campo de nota (más pequeño) -->
                                                        <div class="w-24 sm:w-28">
                                                            <label class="block text-xs font-medium text-gray-600">Nota</label>
                                                            <input type="number" step="0.01" min="0" max="100" name="calificacion" 
                                                                   class="mt-1 p-2 w-full border border-gray-300 rounded-md text-center" 
                                                                   value="<?php echo htmlspecialchars($entrega['calificacion']); ?>">
                                                        </div>

                                                        <!-- Campo de comentario (ocupa el resto del espacio) -->
                                                        <div class="flex-1 min-w-0">
                                                            <label class="block text-xs font-medium text-gray-600">Comentario</label>
                                                            <textarea name="comentario_docente" rows="1" 
                                                                      class="mt-1 p-2 w-full border border-gray-300 rounded-md" 
                                                                      placeholder="Escribe tu retroalimentación..."><?php echo htmlspecialchars($entrega['comentario_docente']); ?></textarea>
                                                        </div>
                                                    </div>

                                                    <!-- Botones (sin cambios) -->
                                                    <div class="flex space-x-2">
                                                        <button type="submit" class="flex-1 bg-green-600 text-white px-3 py-2 rounded-md hover:bg-green-700 transition">
                                                            Guardar
                                                        </button>
                                                        <button type="button" class="btn-reset-entrega flex-1 bg-yellow-500 text-white px-3 py-2 rounded-md hover:bg-yellow-600" 
                                                                data-entrega-id="<?php echo $entrega['entrega_id']; ?>">
                                                            Re-habilitar
                                                        </button>
                                                    </div>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <script src="../js/jquery.js"></script>
    <script>
    $(document).ready(function() {
        $('.form-calificar').on('submit', function(e) {
            e.preventDefault();
            const form = $(this);
            const submitButton = form.find('button[type="submit"]');
            submitButton.html('Guardando...').prop('disabled', true);

            $.ajax({
                url: 'ajax_grade_submission.php', type: 'POST', data: form.serialize(), dataType: 'json',
                success: function(response) {
                    if(response.success) {
                        submitButton.html('<i class="fas fa-check"></i> Guardado');
                        form.closest('tr').addClass('bg-green-50');
                    } else {
                         alert('Error: ' + (response.message || 'Error desconocido'));
                         submitButton.html('Guardar').prop('disabled', false);
                    }
                },
                error: function() {
                    alert('Error de comunicación.');
                    submitButton.html('Guardar').prop('disabled', false);
                }
            });
        });

        $('.btn-reset-entrega').on('click', function() {
            if (!confirm('¿Estás seguro? Se borrará la nota y el comentario para que el alumno pueda entregar de nuevo.')) return;
            
            const boton = $(this);
            const entregaId = boton.data('entrega-id');
            boton.prop('disabled', true);

            $.ajax({
                url: 'ajax_reset_submission.php', type: 'POST', data: { id_entrega: entregaId }, dataType: 'json',
                success: function(response) {
                    if(response.success) {
                        alert('¡Listo! El alumno ya puede entregar de nuevo.');
                        window.location.reload();
                    } else {
                         alert('Error: ' + response.message);
                         boton.prop('disabled', false);
                    }
                },
                error: function() {
                    alert('Error de comunicación.');
                    boton.prop('disabled', false);
                }
            });
        });
    });
    </script>
</body>
</html>