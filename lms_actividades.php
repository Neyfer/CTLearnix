<?php
session_start();
if (!isset($_SESSION['student_id']) || $_SESSION['role'] !== 'student') {
    header('Location: student_login.php');
    exit();
}
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('ID de materia no válido.');
}
$materia_id = $_GET['id'];
$student_id = $_SESSION['student_id'];

require_once 'db_manager.php';
$conn = getDbConnection();

// Obtenemos el nombre de la materia para el título
$stmt_subject = $conn->prepare("SELECT subject FROM asignaturas WHERE id = ?");
$stmt_subject->bind_param("i", $materia_id);
$stmt_subject->execute();
$subject_info = $stmt_subject->get_result()->fetch_assoc();
$page_title = $subject_info['subject'] ?? 'Actividades';
$stmt_subject->close();

// ✨ NUEVA CONSULTA: Calcular el puntaje total acumulado del estudiante en esta materia ✨
$stmt_total_score = $conn->prepare(
    "SELECT SUM(le.calificacion) as total_score 
     FROM lms_entregas le
     JOIN lms_actividades la ON le.id_actividad = la.id
     WHERE le.id_estudiante = ? AND la.id_asignatura = ? AND le.calificacion IS NOT NULL"
);
$stmt_total_score->bind_param("ii", $student_id, $materia_id);
$stmt_total_score->execute();
$total_score_result = $stmt_total_score->get_result()->fetch_assoc();
$total_score = $total_score_result['total_score'] ?? 0;
$stmt_total_score->close();


// Obtenemos todas las actividades de esta materia para este alumno
$sql = "
    SELECT 
        act.id as actividad_id, act.nombre_actividad, act.fecha_entrega, act.puntaje_maximo,
        ent.id as entrega_id, ent.calificacion, ent.comentario_docente
    FROM lms_actividades act
    LEFT JOIN lms_entregas ent ON ent.id_actividad = act.id AND ent.id_estudiante = ?
    WHERE act.id_asignatura = ?
    ORDER BY act.fecha_publicacion DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $student_id, $materia_id);
$stmt->execute();
$actividades = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

require_once 'components/student_header.php';
?>
  <a href="mis_materias.php" class="text-sm text-indigo-600 hover:text-indigo-800 mb-4 inline-block"><i class="fas fa-arrow-left mr-2"></i>Volver a mis Asignaturas</a>

<div class="pb-4 border-b border-gray-200 mb-6 flex items-center">

    <div>
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-800">Actividades de: <span class="text-indigo-600"><?php echo htmlspecialchars($page_title); ?></span></h1>
        <p class="mt-1 text-sm md:text-md text-gray-600">Revisa el estado de todas las actividades de esta materia.</p>
    </div>
</div>

<div class="bg-indigo-100 text-indigo-700 rounded-md shadow-sm p-4 mb-4 flex items-center justify-between">
    <div>
        <p class="text-sm font-medium">Puntaje Acumulado</p>
        <p class="text-xl font-semibold"><?php echo number_format($total_score, 2); ?> <span class="text-sm text-gray-600"> / 100 pts (estimado)</span></p>
    </div>
    <i class="fas fa-chart-bar fa-2x"></i>
</div>


<div class="bg-white rounded-xl shadow-md">
    <ul class="divide-y divide-gray-200">
        <?php if (empty($actividades)): ?>
            <li class="p-6 text-center text-gray-500">
                <i class="fas fa-folder-open fa-3x text-gray-300"></i>
                <p class="mt-3 font-semibold">No hay actividades publicadas todavía.</p>
            </li>
        <?php else: ?>
            <?php foreach ($actividades as $act): ?>
                <li class="hover:bg-gray-50 transition">
                    <a href="lms_entrega.php?id=<?php echo $act['actividad_id']; ?>" class="block p-4 sm:p-6">
                        <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-3">
                            <div class="flex-1">
                                <h4 class="text-base sm:text-lg font-semibold text-gray-900 hover:text-indigo-600"><?php echo htmlspecialchars($act['nombre_actividad']); ?></h4>
                                <p class="text-xs sm:text-sm text-gray-500 mt-1">Límite: <span class="font-medium"><?php echo $act['fecha_entrega'] ? date('d/m/Y', strtotime($act['fecha_entrega'])) : 'N/A'; ?></span></p>
                            </div>
                            <div class="w-full sm:w-auto flex justify-between items-center mt-2 sm:mt-0">
                                <div class="flex items-center space-x-3">
                                    <?php if ($act['calificacion'] !== null): ?>
                                        <div class="text-right">
                                            <p class="font-bold text-lg text-gray-800"><?php echo htmlspecialchars(number_format($act['calificacion'], 2)); ?><span class="text-sm text-gray-500"> / <?php echo htmlspecialchars(number_format($act['puntaje_maximo'], 2)); ?></span></p>
                                        </div>
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            <i class="fas fa-check-circle mr-1.5"></i> Calificado
                                            <?php if(!empty($act['comentario_docente'])) echo '<i class="fas fa-comment-dots ml-1.5 text-sky-600" title="Hay un comentario"></i>'; ?>
                                        </span>
                                    <?php elseif ($act['entrega_id'] !== null): ?>
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            <i class="fas fa-paper-plane mr-1.5"></i> Entregado
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                            <i class="fas fa-hourglass-half mr-1.5"></i> Pendiente
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <i class="fas fa-chevron-right text-gray-400 ml-4 hidden sm:block"></i>
                            </div>
                        </div>
                    </a>
                </li>
            <?php endforeach; ?>
        <?php endif; ?>
    </ul>
</div>

<?php require_once 'components/student_footer.php'; ?>