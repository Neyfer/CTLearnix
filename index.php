<?php
$page_title = "Dashboard";
session_start();
if (!isset($_SESSION['student_id'])) { 
    header('Location: student_login.php'); 
    exit(); 
}

require_once 'db_manager.php'; // Asegúrate de que esta ruta sea correcta
$conn = getDbConnection();
$student_id = $_SESSION['student_id'];

// --- 1. Obtener nombre y datos del estudiante para los contadores y perfil ---
$query_student_info = mysqli_query($conn, "SELECT name, grade, seccion, img FROM students WHERE id = $student_id");
$student_info = mysqli_fetch_assoc($query_student_info);
$student_name = $student_info['name'] ?? 'Alumno';
$grade_filter = ($student_info['grade'] ?? '') . ($student_info['seccion'] ?? '');
$profile_img = !empty($student_info['img']) ? '../img_students/' . htmlspecialchars($student_info['img']) : '../img/profile-circle.png';

// --- 2. Contar tareas pendientes ---
$sql_pendientes = "
    SELECT COUNT(act.id) as total 
    FROM asignaturas asi 
    JOIN lms_actividades act ON act.id_asignatura = asi.id 
    LEFT JOIN lms_entregas ent ON ent.id_actividad = act.id AND ent.id_estudiante = ? 
    WHERE asi.grade = ? AND ent.id IS NULL AND (act.fecha_entrega >= NOW() OR act.fecha_entrega IS NULL)
";
$stmt_pendientes = $conn->prepare($sql_pendientes);
$stmt_pendientes->bind_param("is", $student_id, $grade_filter);
$stmt_pendientes->execute();
$total_pendientes = $stmt_pendientes->get_result()->fetch_assoc()['total'];
$stmt_pendientes->close();

// --- 3. Obtener últimas actividades y estado de entrega del alumno ---
$sql_recent_activities = "
    SELECT 
        act.id as actividad_id, act.nombre_actividad, act.fecha_entrega, act.puntaje_maximo,
        asi.subject,
        ent.id as entrega_id, ent.calificacion, ent.fecha_entrega AS fecha_de_entrega_realizada
    FROM lms_actividades act
    JOIN asignaturas asi ON act.id_asignatura = asi.id
    LEFT JOIN lms_entregas ent ON ent.id_actividad = act.id AND ent.id_estudiante = ?
    WHERE asi.grade = ?
    ORDER BY act.fecha_publicacion DESC
    LIMIT 5
";
$stmt_recent_activities = $conn->prepare($sql_recent_activities);
$stmt_recent_activities->bind_param("is", $student_id, $grade_filter);
$stmt_recent_activities->execute();
$recent_activities = $stmt_recent_activities->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_recent_activities->close();

// --- 4. Obtener anuncios recientes ---
$sql_announcements = "
    SELECT title, content, date_posted 
    FROM announcements 
    WHERE target_role = 'all' OR target_role = 'student' 
    ORDER BY date_posted DESC 
    LIMIT 3
";
$stmt_announcements = $conn->prepare($sql_announcements);
$stmt_announcements->execute();
$announcements = $stmt_announcements->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_announcements->close();


// --- 5. Calcular Promedio General de Calificaciones Finales por Parcial (NUEVO CÓDIGO) ---
// Reutilizamos la lógica de notas.php
$query_average = "
    SELECT COALESCE(AVG(all_grades.nota), 0) AS total_average_grade 
    FROM (
        SELECT nota FROM primer_parcial WHERE student_id = ?
        UNION ALL SELECT nota FROM segundo_parcial WHERE student_id = ?
        UNION ALL SELECT nota FROM tercer_parcial WHERE student_id = ?
        UNION ALL SELECT nota FROM cuarto_parcial WHERE student_id = ?
    ) AS all_grades;
";
$stmt_average = $conn->prepare($query_average);
$stmt_average->bind_param("iiii", $student_id, $student_id, $student_id, $student_id);
$stmt_average->execute();
$average_row = $stmt_average->get_result()->fetch_assoc();
$total_average_final_grades = round($average_row['total_average_grade'], 2);
$stmt_average->close();


// Cierre de conexión a la DB
$conn->close();

require_once 'components/student_header.php'; 
?>

<div class="pb-4 border-b border-gray-200 mb-8">
    <h1 class="text-3xl font-bold text-gray-800">¡Bienvenido, <?php echo htmlspecialchars(explode(' ', $student_name)[0]); ?>!</h1>
    <p class="mt-1 text-md text-gray-600">Este es tu resumen académico.</p>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <a href="lms_tareas.php" class="block bg-white p-6 rounded-xl shadow-lg hover:shadow-xl hover:-translate-y-1 transform transition-all duration-300">
        <div class="flex items-center">
            <div class="bg-yellow-100 p-4 rounded-full"><i class="fas fa-list-check fa-lg text-yellow-600"></i></div>
            <div class="ml-4">
                <p class="text-4xl font-bold text-gray-800"><?php echo $total_pendientes; ?></p>
                <p class="text-gray-500">Tareas Pendientes</p>
            </div>
        </div>
    </a>
    
    <a href="mis_materias.php" class="block bg-white p-6 rounded-xl shadow-lg hover:shadow-xl hover:-translate-y-1 transform transition-all duration-300">
        <div class="flex items-center">
            <div class="bg-indigo-100 p-4 rounded-full"><i class="fas fa-book fa-lg text-indigo-600"></i></div>
            <div class="ml-4">
                <h3 class="text-xl font-bold text-gray-800">Mis Materias</h3>
                <p class="text-gray-500">Ver todas las actividades</p>
            </div>
        </div>
    </a>
    
    <a href="notas.php?tab=grades" class="block bg-white p-6 rounded-xl shadow-lg hover:shadow-xl hover:-translate-y-1 transform transition-all duration-300">
        <div class="flex items-center">
            <div class="bg-green-100 p-4 rounded-full"><i class="fas fa-clipboard-check fa-lg text-green-600"></i></div>
            <div class="ml-4">
                <h3 class="text-xl font-bold text-gray-800">Calificaciones</h3>
                <p class="text-gray-500">Revisa tu boletín completo</p>
            </div>
        </div>
    </a>

    <div class="bg-gradient-to-r from-blue-500 to-indigo-600 p-6 rounded-xl shadow-lg hover:shadow-xl hover:-translate-y-1 transform transition-all duration-300 text-white flex items-center justify-between col-span-1 md:col-span-2 lg:col-span-3">
        <div>
            <p class="text-xl font-semibold">Promedio General de Notas Finales</p>
            <p class="text-4xl font-bold mt-1"><?php echo htmlspecialchars($total_average_final_grades); ?> <span class="text-2xl text-white text-opacity-80"> %</span></p>
        </div>
        <i class="fas fa-chart-line text-5xl opacity-50"></i>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
    <div class="bg-white p-6 rounded-xl shadow-lg">
        <h2 class="text-xl font-bold text-gray-800 border-b pb-3 mb-4 flex items-center">
            <i class="fas fa-bullhorn text-indigo-500 mr-2"></i>Anuncios Recientes
        </h2>
        <?php if (!empty($announcements)): ?>
            <ul class="space-y-4">
                <?php foreach ($announcements as $announcement): ?>
                    <li class="border-b border-gray-100 pb-3 last:border-b-0 last:pb-0">
                        <h4 class="font-semibold text-gray-900"><?php echo htmlspecialchars($announcement['title']); ?></h4>
                        <p class="text-sm text-gray-600 mt-1"><?php echo nl2br(htmlspecialchars(mb_substr($announcement['content'], 0, 100))) . (mb_strlen($announcement['content']) > 100 ? '...' : ''); ?></p>
                        <p class="text-xs text-gray-400 mt-1">Publicado el: <?php echo date('d/m/Y', strtotime($announcement['date_posted'])); ?></p>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="text-gray-500 text-center py-4">No hay anuncios recientes.</p>
        <?php endif; ?>
    </div>

    <div class="bg-white p-6 rounded-xl shadow-lg">
        <h2 class="text-xl font-bold text-gray-800 border-b pb-3 mb-4 flex items-center">
            <i class="fas fa-clipboard-list-check text-green-500 mr-2"></i>Últimas Actividades y Entregas
        </h2>
        <?php if (!empty($recent_activities)): ?>
            <ul class="divide-y divide-gray-200">
                <?php foreach ($recent_activities as $act): ?>
                    <li class="py-3 px-1 hover:bg-gray-50 transition">
                        <a href="lms_entrega.php?id=<?php echo $act['actividad_id']; ?>" class="block">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-xs font-semibold text-gray-500"><?php echo htmlspecialchars($act['subject']); ?></p>
                                    <h4 class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($act['nombre_actividad']); ?></h4>
                                    <p class="text-xs text-gray-400">Límite: <?php echo $act['fecha_entrega'] ? date('d/m/Y', strtotime($act['fecha_entrega'])) : 'N/A'; ?></p>
                                </div>
                                <div class="text-right flex-shrink-0 ml-4">
                                    <?php if ($act['calificacion'] !== null): ?>
                                        <p class="font-bold text-sm text-green-600"><?php echo htmlspecialchars(number_format($act['calificacion'], 2)); ?> / <?php echo htmlspecialchars(number_format($act['puntaje_maximo'], 2)); ?></p>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 mt-1">Calificado</span>
                                    <?php elseif ($act['entrega_id'] !== null): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 mt-1">Entregado</span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 mt-1">Pendiente</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="text-gray-500 text-center py-4">No hay actividades recientes para mostrar.</p>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'components/student_footer.php'; ?>