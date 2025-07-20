<?php
$page_title = "Mis Calificaciones";
session_start();
include('mysql.php'); //
if (!isset($_SESSION['student_id'])) { header('Location: student_login.php'); exit(); } //
$student_id = $_SESSION['student_id']; //

// --- LÓGICA COMPLETA PARA CARGAR CALIFICACIONES (sin cambios) ---
$query_student_data = mysqli_query($conn, "SELECT grade, seccion, modalidad FROM students WHERE id = $student_id"); //
$student_info = mysqli_fetch_assoc($query_student_data); //
$grade = $student_info['grade']; //
$seccion = $student_info['seccion']; //
$modalidad_id = $student_info['modalidad']; //
$sanitized_student_id = mysqli_real_escape_string($conn, $student_id); //
$asignaturas_grade_filter = strval($grade) . strval($seccion); //
if ($grade >= 11) { $asignaturas_grade_filter .= strval($modalidad_id); } //
$sanitized_asignaturas_grade_filter = mysqli_real_escape_string($conn, $asignaturas_grade_filter); //
$grades_query = "SELECT a.subject, COALESCE(pp.nota, NULL) AS primer_parcial_nota, COALESCE(sp.nota, NULL) AS segundo_parcial_nota, COALESCE(tp.nota, NULL) AS tercer_parcial_nota, COALESCE(cp.nota, NULL) AS cuarto_parcial_nota FROM asignaturas a LEFT JOIN primer_parcial pp ON pp.student_id = {$sanitized_student_id} AND pp.subject = a.subject LEFT JOIN segundo_parcial sp ON sp.student_id = {$sanitized_student_id} AND sp.subject = a.subject LEFT JOIN tercer_parcial tp ON tp.student_id = {$sanitized_student_id} AND tp.subject = a.subject LEFT JOIN cuarto_parcial cp ON cp.student_id = {$sanitized_student_id} AND cp.subject = a.subject WHERE a.grade = '{$sanitized_asignaturas_grade_filter}' ORDER BY a.subject;"; //
$grades_result = mysqli_query($conn, $grades_query); //
if (!$grades_result) { die("Error al consultar calificaciones: " . mysqli_error($conn)); } //
$student_grades_by_subject = [];
while ($grade_row = mysqli_fetch_assoc($grades_result)) { $student_grades_by_subject[] = $grade_row; } //
$filtered_student_grades = [];
$seen_signatures = [];
foreach ($student_grades_by_subject as $grade_row) {
    $signature = $grade_row['subject'] . '|' . ($grade_row['primer_parcial_nota'] ?? 'N') . '|' . ($grade_row['segundo_parcial_nota'] ?? 'N') . '|' . ($grade_row['tercer_parcial_nota'] ?? 'N') . '|' . ($grade_row['cuarto_parcial_nota'] ?? 'N'); //
    if (!isset($seen_signatures[$signature])) { //
        $filtered_student_grades[] = $grade_row; //
        $seen_signatures[$signature] = true; //
    }
}
$query_average = "SELECT COALESCE(AVG(all_grades.nota), 0) AS total_average_grade FROM (SELECT nota FROM primer_parcial WHERE student_id = $student_id UNION ALL SELECT nota FROM segundo_parcial WHERE student_id = $student_id UNION ALL SELECT nota FROM tercer_parcial WHERE student_id = $student_id UNION ALL SELECT nota FROM cuarto_parcial WHERE student_id = $student_id) AS all_grades;"; //
$result_average = mysqli_query($conn, $query_average); //
$average_row = mysqli_fetch_assoc($result_average); //
$total_average = round($average_row['total_average_grade'], 2); //

function getGradeColorClass($grade) {
    if ($grade === null || $grade === 'N/A' || $grade === '-') return 'text-gray-700';
    if ($grade < 70) return 'text-red-500 font-bold';
    if ($grade >= 90) return 'text-green-600 font-bold';
    return 'text-gray-800';
}

require_once 'components/student_header.php'; 
?>

<div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
    <div class="pb-4 border-b border-gray-200 mb-6">
        <h1 class="text-3xl font-bold text-gray-900">Boletín de Calificaciones</h1>
        <p class="mt-1 text-md text-gray-600">Resumen de tus notas finales por parcial.</p>
    </div>

    <div class="bg-white rounded-2xl shadow-lg p-6 mb-8">
        <div class="flex justify-between items-center">
            <div>
                <p class="text-sm font-medium text-gray-500">Promedio General Global</p>
                <p class="text-4xl font-bold text-indigo-600"><?php echo htmlspecialchars($total_average); ?></p>
            </div>
            <div class="flex items-center justify-center h-16 w-16 bg-indigo-100 rounded-full">
                <i class="fas fa-chart-line fa-2x text-indigo-500"></i>
            </div>
        </div>
    </div>
    
    <div class="hidden md:block bg-white shadow-xl rounded-2xl overflow-hidden">
        <table class="min-w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Asignatura</th>
                    <th class="px-6 py-4 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Parcial 1</th>
                    <th class="px-6 py-4 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Parcial 2</th>
                    <th class="px-6 py-4 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Parcial 3</th>
                    <th class="px-6 py-4 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Parcial 4</th>
                    <th class="px-6 py-4 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Promedio Final</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($filtered_student_grades)): ?>
                    <tr><td colspan="6" class="text-center text-gray-500 py-8">No hay calificaciones disponibles.</td></tr>
                <?php else: ?>
                    <?php foreach ($filtered_student_grades as $grade_row): ?>
                        <?php
                            $notas = [$grade_row['primer_parcial_nota'], $grade_row['segundo_parcial_nota'], $grade_row['tercer_parcial_nota'], $grade_row['cuarto_parcial_nota']]; //
                            $notas_numericas = array_filter($notas, 'is_numeric'); //
                            $subject_average = count($notas_numericas) > 0 ? round(array_sum($notas_numericas) / count($notas_numericas), 2) : 'N/A'; //
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap font-semibold text-gray-800"><?php echo htmlspecialchars($grade_row['subject']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-lg <?php echo getGradeColorClass($notas[0]); ?>"><?php echo $notas[0] ?? '-'; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-lg <?php echo getGradeColorClass($notas[1]); ?>"><?php echo $notas[1] ?? '-'; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-lg <?php echo getGradeColorClass($notas[2]); ?>"><?php echo $notas[2] ?? '-'; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-lg <?php echo getGradeColorClass($notas[3]); ?>"><?php echo $notas[3] ?? '-'; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-lg font-bold <?php echo getGradeColorClass($subject_average); ?>"><?php echo $subject_average; ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="block md:hidden space-y-4">
        <?php if (empty($filtered_student_grades)): ?>
            <div class="bg-white rounded-2xl shadow-lg p-6 text-center text-gray-500">No hay calificaciones disponibles.</div>
        <?php else: ?>
            <?php foreach ($filtered_student_grades as $grade_row): ?>
                 <?php
                    $notas = [$grade_row['primer_parcial_nota'], $grade_row['segundo_parcial_nota'], $grade_row['tercer_parcial_nota'], $grade_row['cuarto_parcial_nota']]; //
                    $notas_numericas = array_filter($notas, 'is_numeric'); //
                    $subject_average = count($notas_numericas) > 0 ? round(array_sum($notas_numericas) / count($notas_numericas), 2) : 'N/A'; //
                ?>
                <div class="bg-white rounded-2xl shadow-lg p-4">
                    <div class="flex justify-between items-center border-b pb-3 mb-3">
                        <h3 class="font-bold text-lg text-gray-800"><?php echo htmlspecialchars($grade_row['subject']); ?></h3>
                        <div class="text-right">
                            <p class="text-xs text-gray-500">Promedio</p>
                            <p class="text-xl font-bold <?php echo getGradeColorClass($subject_average); ?>"><?php echo $subject_average; ?></p>
                        </div>
                    </div>
                    <div class="grid grid-cols-4 gap-2 text-center">
                        <div>
                            <p class="text-xs text-gray-500">P1</p>
                            <p class="text-lg <?php echo getGradeColorClass($notas[0]); ?>"><?php echo $notas[0] ?? '-'; ?></p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">P2</p>
                            <p class="text-lg <?php echo getGradeColorClass($notas[1]); ?>"><?php echo $notas[1] ?? '-'; ?></p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">P3</p>
                            <p class="text-lg <?php echo getGradeColorClass($notas[2]); ?>"><?php echo $notas[2] ?? '-'; ?></p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">P4</p>
                            <p class="text-lg <?php echo getGradeColorClass($notas[3]); ?>"><?php echo $notas[3] ?? '-'; ?></p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'components/student_footer.php'; ?>