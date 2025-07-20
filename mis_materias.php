<?php
// 1. INICIAMOS LA SESIÓN Y VALIDAMOS (SIN ARCHIVOS EXTERNOS)
session_start();
if (!isset($_SESSION['student_id']) || $_SESSION['role'] !== 'student') {
    header('Location: student_login.php');
    exit();
}

// 2. PREPARAMOS LA CONEXIÓN A LA BASE DE DATOS
require_once 'db_manager.php';
$conn = getDbConnection();
$student_id = $_SESSION['student_id'];

// 3. OBTENEMOS LOS DATOS DEL ESTUDIANTE (LÓGICA QUE YA FUNCIONA)
$stmt_student = $conn->prepare("SELECT name, grade, seccion FROM students WHERE id = ?");
$stmt_student->bind_param("i", $student_id);
$stmt_student->execute();
$student_info = $stmt_student->get_result()->fetch_assoc();
$student_grade = $student_info['grade'] ?? 0;
$student_seccion = $student_info['seccion'] ?? 0;
$stmt_student->close();

// 4. CONSTRUIMOS EL FILTRO EXACTO PARA LAS MATERIAS
$grade_filter = $student_grade . $student_seccion;

// 5. OBTENEMOS SOLO LA LISTA DE MATERIAS
$sql_asignaturas = "SELECT DISTINCT asi.id, asi.subject FROM asignaturas asi WHERE asi.grade = ?";
$stmt_asignaturas = $conn->prepare($sql_asignaturas);
$stmt_asignaturas->bind_param("s", $grade_filter);
$stmt_asignaturas->execute();
$lista_asignaturas = $stmt_asignaturas->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_asignaturas->close();
$conn->close();

// 6. INCLUIMOS EL HEADER VISUAL
$page_title = "Mis Materias"; 
require_once 'components/student_header.php'; 
?>

<div class="pb-4 border-b border-gray-200 mb-8">
    <h1 class="text-3xl sm:text-4xl font-bold text-gray-800">Mis Materias</h1>
    <p class="mt-2 text-md text-gray-600">Selecciona una materia para ver sus actividades, tareas y calificaciones.</p>
</div>

<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-6">
    <?php if (empty($lista_asignaturas)): ?>
        <div class="col-span-full bg-white text-center p-10 rounded-xl shadow-md">
            <i class="fas fa-box-open fa-3x text-gray-400"></i>
            <h3 class="mt-4 text-xl font-bold text-gray-800">No tienes materias asignadas</h3>
            <p class="mt-2 text-gray-600">Por favor, contacta a la administración de tu instituto.</p>
        </div>
    <?php else: ?>
        <?php 
            // Paleta de colores para las tarjetas
            $colors = [
                ['bg' => 'bg-indigo-100', 'text' => 'text-indigo-600', 'icon' => 'fas fa-book-open'],
                ['bg' => 'bg-blue-100', 'text' => 'text-blue-600', 'icon' => 'fas fa-atom'],
                ['bg' => 'bg-green-100', 'text' => 'text-green-600', 'icon' => 'fas fa-leaf'],
                ['bg' => 'bg-yellow-100', 'text' => 'text-yellow-600', 'icon' => 'fas fa-ruler-combined'],
                ['bg' => 'bg-red-100', 'text' => 'text-red-600', 'icon' => 'fas fa-heartbeat'],
                ['bg' => 'bg-purple-100', 'text' => 'text-purple-600', 'icon' => 'fas fa-palette'],
            ];
            $color_index = 0;
        ?>
        <?php foreach($lista_asignaturas as $asignatura): ?>
            <?php 
                $color_class = $colors[$color_index % count($colors)];
                $color_index++;
            ?>
            <a href="lms_actividades.php?id=<?php echo $asignatura['id']; ?>" 
               class="block bg-white rounded-xl shadow-lg hover:shadow-2xl hover:-translate-y-2 transform transition-all duration-300 overflow-hidden group">
                <div class="p-6">
                    <div class="flex justify-center items-center h-24 w-24 mx-auto <?php echo $color_class['bg']; ?> rounded-full transition-transform duration-300 group-hover:scale-110">
                        <i class="<?php echo $color_class['icon']; ?> text-4xl <?php echo $color_class['text']; ?>"></i>
                    </div>
                    <h3 class="mt-5 text-lg font-bold text-gray-900 text-center truncate">
                        <?php echo htmlspecialchars($asignatura['subject']); ?>
                    </h3>
                </div>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php 
// Incluimos el footer que cierra el HTML y carga los scripts necesarios
require_once 'components/student_footer.php'; 
?>