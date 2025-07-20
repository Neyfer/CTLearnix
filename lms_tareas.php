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

// Obtener info del estudiante (grado y sección)
$stmt_student = $conn->prepare("SELECT name, grade, seccion FROM students WHERE id = ?");
$stmt_student->bind_param("i", $student_id);
$stmt_student->execute();
$student_info = $stmt_student->get_result()->fetch_assoc();
$student_name = $student_info['name'] ?? 'Alumno';
$student_grade = $student_info['grade'] ?? 0;
$student_seccion = $student_info['seccion'] ?? 0;
$stmt_student->close();

// === LÓGICA DE FILTRADO CORREGIDA (USANDO TU MÉTODO) ===
$grade_filter = $student_grade . $student_seccion;

// === CONSULTA SQL CORREGIDA ===
// Usa tu método de filtro (asi.grade = ?) y busca solo las que no tienen entrega (ent.id IS NULL)
$sql_pendientes = "
    SELECT 
        asi.subject, 
        act.id as actividad_id, 
        act.nombre_actividad, 
        act.descripcion, 
        act.tipo_actividad,
        act.puntaje_maximo, 
        act.fecha_entrega,
        NULL as entrega_id, 
        NULL as calificacion, 
        NULL as comentario_docente,
        NULL as ruta_archivo,
        NULL as texto_respuesta
    FROM asignaturas asi
    JOIN lms_actividades act ON act.id_asignatura = asi.id
    LEFT JOIN lms_entregas ent ON ent.id_actividad = act.id AND ent.id_estudiante = ?
    WHERE asi.grade = ? AND ent.id IS NULL
    ORDER BY 
        CASE WHEN act.fecha_entrega IS NULL THEN 1 ELSE 0 END, 
        act.fecha_entrega ASC
";
$stmt_pendientes = $conn->prepare($sql_pendientes);
$stmt_pendientes->bind_param("is", $student_id, $grade_filter);
$stmt_pendientes->execute();
$tareas_pendientes = $stmt_pendientes->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_pendientes->close();
$conn->close();

include 'components/student_header.php'; // Incluimos el header estandarizado
?>

<div class="pb-4 border-b border-gray-200 mb-6">
    <h1 class="text-2xl sm:text-3xl font-bold text-gray-800">Tareas Pendientes</h1>
    <p class="mt-1 text-sm md:text-md text-gray-600">Aquí están todas las actividades que tienes por hacer.</p>
</div>

<div class="space-y-4">
    <?php if (empty($tareas_pendientes)): ?>
        <div class="bg-white text-center p-8 rounded-xl shadow-md">
            <i class="fas fa-check-circle fa-3x text-green-500"></i>
            <h3 class="mt-4 text-xl font-bold text-gray-800">¡Felicitaciones! Estás al día.</h3>
            <p class="mt-2 text-gray-600">No tienes ninguna tarea pendiente por entregar.</p>
        </div>
    <?php else: ?>
        <?php foreach ($tareas_pendientes as $act): ?>
            <div class="bg-white rounded-lg shadow-sm p-4 hover:shadow-md transition">
                <div class="grid grid-cols-3 gap-2 items-start">
                    <div class="col-span-3 sm:col-span-2">
                        <p class="text-xs font-semibold text-indigo-600 uppercase tracking-wider"><?php echo htmlspecialchars($act['subject']); ?></p>
                        <h4 class="text-base font-semibold text-gray-900"><?php echo htmlspecialchars($act['nombre_actividad']); ?></h4>
                    </div>
                    <div class="col-span-3 sm:col-span-1 text-left sm:text-right">
                        <p class="text-sm text-gray-500 mt-1 sm:mt-0">
                            Límite: <span class="font-medium"><?php echo $act['fecha_entrega'] ? date('d/m/Y h:i A', strtotime($act['fecha_entrega'])) : 'Sin fecha límite'; ?></span>
                        </p>
                    </div>
                </div>
                <div class="flex justify-between items-center mt-3 border-t pt-3">
                    <div>
                        <?php if ($act['fecha_entrega'] && new DateTime() > new DateTime($act['fecha_entrega'])): ?>
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">Vencido</span>
                        <?php else: ?>
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Pendiente</span>
                        <?php endif; ?>
                    </div>
                   <a href="lms_entrega.php?id=<?php echo $act['actividad_id']; ?>">
                    <button type="button" class="btn-ver-entregar bg-indigo-600 text-white text-sm font-bold py-2 px-4 rounded-lg hover:bg-indigo-700 transition">
                        <i class="fas fa-paper-plane mr-2"></i> Realizar Entrega
                    </button>
                  </a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php 
// Incluimos el footer que cierra el HTML y contiene el JavaScript funcional
include 'components/student_footer.php'; 
?>