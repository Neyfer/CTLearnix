<?php

session_start();



// Include your database connection file
include('mysql.php'); // Asegúrate de que este archivo conecta a tu base de datos

// Get the logged-in student's ID directly from the session.
// This ID identifies the specific student whose data should be displayed.
$student_id = 5;

// --- Handle Photo Upload (if form is submitted) ---
$upload_message = '';
if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] == UPLOAD_ERR_OK) {
    // Define the target directory for uploaded images.
    // IMPORTANT: ADJUST THIS PATH TO YOUR ACTUAL IMAGE FOLDER.
    // Example: If student_profile.php is in 'admin/' and images are in 'uploads/student_images/',
    // then '../uploads/student_images/' is correct.
    $target_dir = '../img_students/';

    // Get file extension and convert to lowercase for consistent checking
    $file_extension = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
    // Allowed image file extensions for security and compatibility
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

    // Server-side validation for file type and size
    if (!in_array($file_extension, $allowed_extensions)) {
        $upload_message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Error: Solo se permiten archivos JPG, JPEG, PNG y GIF.</div>';
    } elseif ($_FILES['profile_photo']['size'] > 5000000) { // Max 5MB file size limit
        $upload_message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Error: El archivo es demasiado grande (máx. 5MB).</div>';
    } else {
        // Generate a unique filename using student ID and timestamp.
        // This ensures each student's photo has a distinct name and avoids overwriting issues.
        $new_filename = 'student_' . $student_id . '_' . time() . '.' . $file_extension;
        $target_file = $target_dir . $new_filename;

        // Attempt to move the uploaded file from its temporary server location to your designated directory
        if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $target_file)) {
            // Fetch the old image filename from the database for the current student
            $old_img_query = mysqli_query($conn, "SELECT img FROM students WHERE id = $student_id");
            $old_img_row = mysqli_fetch_assoc($old_img_query);
            $old_filename = $old_img_row['img'];

            // Delete the old image file from the server if it exists and is different from the new one
            if (!empty($old_filename) && $old_filename !== $new_filename && file_exists($target_dir . $old_filename)) {
                unlink($target_dir . $old_filename); // Permanently delete the old file
            }

            // Update the 'img' column in the 'students' table with the new filename
            $update_img_query = mysqli_query($conn, "UPDATE students SET img = '$new_filename' WHERE id = $student_id");
            if ($update_img_query) {
                $upload_message = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">¡Foto de perfil actualizada con éxito!</div>';
            } else {
                $upload_message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Error al actualizar la base de datos: ' . mysqli_error($conn) . '</div>';
            }
        } else {
            $upload_message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Error al subir la foto.</div>';
        }
    }
}
// --- Fin Manejo de Subida de Foto ---

// --- Manejo de Actualización de Datos Personales (SOLO PHP) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    // Sanitizar y validar los datos recibidos del formulario
    $ident = mysqli_real_escape_string($conn, $_POST['ident'] ?? '');
    $birth = mysqli_real_escape_string($conn, $_POST['birth'] ?? '');
    $age = mysqli_real_escape_string($conn, $_POST['age'] ?? '');
    $sexo = mysqli_real_escape_string($conn, $_POST['sexo'] ?? '');
    $tel_a = mysqli_real_escape_string($conn, $_POST['tel_a'] ?? '');
    $address = mysqli_real_escape_string($conn, $_POST['address'] ?? '');
    $encargado = mysqli_real_escape_string($conn, $_POST['encargado'] ?? '');
    $tel_p = mysqli_real_escape_string($conn, $_POST['tel_p'] ?? '');

    // Construir la consulta UPDATE (asegúrate de que los nombres de las columnas coincidan con tu DB)
    $update_query = "
        UPDATE students SET
            ident = '$ident',
            birth = '$birth',
            age = '$age',
            sexo = '$sexo',
            tel_a = '$tel_a',
            address = '$address',
            encargado = '$encargado',
            tel_p = '$tel_p'
        WHERE id = $student_id";

    if (mysqli_query($conn, $update_query)) {
        $notification_message = '¡Información actualizada con éxito!';
        $notification_type = 'success';
    } else {
        $notification_message = 'Error al actualizar información: ' . mysqli_error($conn);
        $notification_type = 'error';
    }
}
// --- Fin Manejo de Actualización de Datos Personales ---


// --- Cargar Datos del Alumno para Mostrar ---
$query_student_data = mysqli_query($conn, "SELECT * FROM students WHERE id = $student_id");
if (!$query_student_data) {
    die("Error al consultar datos del alumno: " . mysqli_error($conn));
}
$num_student_data = mysqli_num_rows($query_student_data);

if ($num_student_data > 0) {
    $student_info = mysqli_fetch_assoc($query_student_data);

    $grade = $student_info['grade'];
    $seccion = $student_info['seccion'];
    $modalidad_id = $student_info['modalidad'];
    $nombreModalidad = '';

    if ($grade < 10) { 
        $nombreModalidad = "Educación Básica";
    } else if ($grade == 10) {
        $nombreModalidad = "Bachillerato Técnico Profesional";
    } else if ($grade >= 11 && $modalidad_id == 3) {
        $nombreModalidad = "Bachiller en Ciencias y Humanidades";
    } else if ($grade >= 11 && $modalidad_id == 4) {
        $nombreModalidad = "Bachiller Técnico Profesional en Informática";
    } else {
        $nombreModalidad = "Modalidad Desconocida";
        if ($modalidad_id !== NULL) {
            $nombreModalidad .= " ($modalidad_id)";
        }
    }

    // --- MEJORA: Lógica para la ruta de la foto de perfil ---
    // Ajusta $target_dir si es necesario para la visualización. Ya está definido arriba.
    $photo_path_display = !empty($student_info['img']) ? $target_dir . $student_info['img'] : '';
    // Usa un placeholder predeterminado si no hay foto o si el archivo no existe
    $default_photo_placeholder = 'https://via.placeholder.com/150/cccccc/ffffff?text=SIN+FOTO';
    if (empty($photo_path_display) || !file_exists($photo_path_display)) {
        $photo_path_display = $default_photo_placeholder;
    }
    // --- FIN MEJORA ---


    // --- ¡OPTIMIZACIÓN! Consulta de Calificaciones con LEFT JOINs (Recomendado) ---
    // Asegúrate de que $student_id y $asignaturas_grade_filter estén sanitizados
    $sanitized_student_id = mysqli_real_escape_string($conn, $student_id);
    $asignaturas_grade_filter = strval($grade) . strval($seccion);
    if ($grade >= 11) {
        $asignaturas_grade_filter .= strval($modalidad_id);
    }
    $sanitized_asignaturas_grade_filter = mysqli_real_escape_string($conn, $asignaturas_grade_filter);

    $grades_query = "
        SELECT
            a.subject,
            COALESCE(pp.nota, NULL) AS primer_parcial_nota,
            COALESCE(sp.nota, NULL) AS segundo_parcial_nota,
            COALESCE(tp.nota, NULL) AS tercer_parcial_nota,
            COALESCE(cp.nota, NULL) AS cuarto_parcial_nota
        FROM asignaturas a
        LEFT JOIN primer_parcial pp ON pp.student_id = {$sanitized_student_id} AND pp.subject = a.subject
        LEFT JOIN segundo_parcial sp ON sp.student_id = {$sanitized_student_id} AND sp.subject = a.subject
        LEFT JOIN tercer_parcial tp ON tp.student_id = {$sanitized_student_id} AND tp.subject = a.subject
        LEFT JOIN cuarto_parcial cp ON cp.student_id = {$sanitized_student_id} AND cp.subject = a.subject
        WHERE a.grade = '{$sanitized_asignaturas_grade_filter}'
        ORDER BY a.subject;
    ";
    $grades_result = mysqli_query($conn, $grades_query);
    if (!$grades_result) {
        die("Error al consultar calificaciones por asignatura: " . mysqli_error($conn) . " Query: " . $grades_query);
    }
    $student_grades_by_subject = [];
    while ($grade_row = mysqli_fetch_assoc($grades_result)) {
        $student_grades_by_subject[] = $grade_row;
    }

    // --- INICIO: CÓDIGO PARA FILTRAR MATERIAS DUPLICADAS CON LAS MISMAS NOTAS ---
    $filtered_student_grades = [];
    $seen_signatures = []; // Para rastrear combinaciones de 'materia|notaP1|notaP2|notaP3|notaP4'

    foreach ($student_grades_by_subject as $grade_row) {
        $subject_name = $grade_row['subject'];
        
        // Normalizar las notas a string para una comparación consistente.
        // Usar un marcador distintivo para NULL para diferenciarlo de una nota '0' o string vacío.
        $p1_str = $grade_row['primer_parcial_nota'] === null ? 'NULL_VAL' : (string)$grade_row['primer_parcial_nota'];
        $p2_str = $grade_row['segundo_parcial_nota'] === null ? 'NULL_VAL' : (string)$grade_row['segundo_parcial_nota'];
        $p3_str = $grade_row['tercer_parcial_nota'] === null ? 'NULL_VAL' : (string)$grade_row['tercer_parcial_nota'];
        $p4_str = $grade_row['cuarto_parcial_nota'] === null ? 'NULL_VAL' : (string)$grade_row['cuarto_parcial_nota'];

        // Crear una firma única para la materia y el conjunto de sus notas.
        $signature = $subject_name . '|' . $p1_str . '|' . $p2_str . '|' . $p3_str . '|' . $p4_str;

        if (!isset($seen_signatures[$signature])) {
            // Si esta firma no se ha visto antes, agregar la materia y sus notas al array filtrado.
            $filtered_student_grades[] = $grade_row;
            // Marcar esta firma como vista.
            $seen_signatures[$signature] = true;
        }
    }
    // --- FIN: CÓDIGO PARA FILTRAR MATERIAS DUPLICADAS ---

    // Calcular el promedio total (tu código existente)
    $query_average = "
        SELECT COALESCE(AVG(all_grades.nota), 0) AS total_average_grade
        FROM (
            SELECT nota FROM primer_parcial WHERE student_id = $student_id
            UNION ALL SELECT nota FROM segundo_parcial WHERE student_id = $student_id
            UNION ALL SELECT nota FROM tercer_parcial WHERE student_id = $student_id
            UNION ALL SELECT nota FROM cuarto_parcial WHERE student_id = $student_id
        ) AS all_grades;
    ";
    $result_average = mysqli_query($conn, $query_average);
    if (!$result_average) {
        die("Error al calcular promedio: " . mysqli_error($conn));
    }
    $average_row = mysqli_fetch_assoc($result_average);
    $total_average = round($average_row['total_average_grade'], 2);

} else {
    echo "<p class='text-center alert alert-danger'>Error: No se encontró información para el alumno con ID: " . htmlspecialchars($student_id) . "</p>";
    exit;
}

// Variable para controlar el modo de edición
$is_editing = isset($_GET['edit']) && $_GET['edit'] === 'true';

// Mapeo para el sexo (para mostrar texto en lugar de números)
$sexo_map = [
    '1' => 'Femenino',
    '2' => 'Masculino',
    '' => 'N/A'
];
$display_sexo = $sexo_map[$student_info['sexo']] ?? 'N/A'; // Usa el operador null coalescing para seguridad
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perfil Estudiantil</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background-color: #f8fafc; /* Tailwind gray-50 */
            padding-top: 64px; /* Space for fixed navbar */
        }
        /* Estilo para la superposición de edición de foto */
        .profile-pic-container:hover .edit-overlay {
            opacity: 1;
        }
        .data-item {
            transition: all 0.3s ease;
        }
        /* La clase editing ya no se usa para ocultar/mostrar elementos, solo para estilo visual */
        .data-item.editing {
            background-color: #f0f9ff; /* Tailwind blue-50 */
            border-radius: 0.5rem;
            box-shadow: 0 0 0 2px #93c5fd; /* Tailwind blue-300 */
        }
        @media (max-width: 640px) {
            .tab-btn {
                font-size: 0.875rem; /* text-sm */
                padding-left: 0.5rem;
                padding-right: 0.5rem;
            }
        }
        .social-icon {
            transition: all 0.3s ease;
        }
        .social-icon:hover {
            transform: translateY(-3px);
        }

        /* Custom Modal Styles (Tailwind friendly) */
        .modal {
            display: none; /* Hidden by default */
            position: fixed; /* Stay in place */
            z-index: 1000; /* Sit on top */
            left: 0;
            top: 0;
            width: 100%; /* Full width */
            height: 100%; /* Full height */
            overflow: auto; /* Enable scroll if needed */
            background-color: rgba(0,0,0,0.4); /* Black w/ opacity */
            justify-content: center; /* Use flexbox to center content */
            align-items: center;
        }
        .modal-content {
            background-color: #fefefe;
            padding: 0; /* Remove default padding */
            border-radius: 0.75rem; /* rounded-lg */
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            width: 90%;
            max-width: 500px;
            animation-name: animatetop;
            animation-duration: 0.4s;
        }
        @keyframes animatetop {
            from {top: -300px; opacity: 0}
            to {top: 0; opacity: 1}
        }
        .modal-header {
            padding: 1rem 1.5rem; /* p-4 sm:p-6 equivalent */
            border-bottom: 1px solid #e5e7eb; /* border-gray-200 */
            background-color: #6366f1; /* Tailwind indigo-500 */
            color: white;
            border-top-left-radius: 0.75rem;
            border-top-right-radius: 0.75rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 600; /* font-semibold */
        }
        .modal-header h5 {
            margin: 0; /* Remove default margin from h5 */
            font-size: 1.25rem; /* text-xl */
        }
        .modal-close-btn {
            color: white;
            font-size: 1.75rem; /* Larger for better click target */
            line-height: 1; /* Adjust line height */
            font-weight: bold;
            cursor: pointer;
            border: none;
            background: none;
            padding: 0; /* Remove padding */
        }
        .modal-close-btn:hover {
            opacity: 0.7;
        }
        .modal-body {
            padding: 1.5rem; /* p-6 */
        }
        .modal-footer {
            padding: 1rem 1.5rem; /* p-4 sm:p-6 */
            border-top: 1px solid #e5e7eb; /* border-gray-200 */
            text-align: right;
            display: flex; /* Use flexbox for button alignment */
            justify-content: flex-end; /* Align buttons to the right */
            gap: 0.5rem; /* gap-2 */
        }

        /* Notification styles */
        .notification {
            position: fixed;
            bottom: 1rem;
            left: 50%;
            transform: translateX(-50%); /* Center horizontally */
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 1050;
            transition: transform 0.5s ease-in-out, opacity 0.5s ease-in-out;
            opacity: 0; /* Start hidden */
        }
        .notification.show {
            opacity: 1; /* Show state */
            transform: translateX(-50%) translateY(0); /* Final position */
        }
        .notification.success { background-color: #22c55e; color: white; } /* Tailwind green-500 */
        .notification.error { background-color: #ef4444; color: white; } /* Tailwind red-500 */
        .notification.info { background-color: #3b82f6; color: white; } /* Tailwind blue-500 */
        .notification i { margin-right: 0.5rem; }

        /* Grade table colors */
        .grade-color-indicator.grade-good { background-color: #22c55e; } /* Tailwind green-500 */
        .grade-color-indicator.grade-ok { background-color: #3b82f6; } /* Tailwind blue-500 */
        .grade-color-indicator.grade-warning { background-color: #f59e0b; } /* Tailwind amber-500 (yellow-500 is very light) */
        .grade-color-indicator.grade-bad { background-color: #ef4444; } /* Tailwind red-500 */

        .grade-avg-cell.grade-good { color: #22c55e; }
        .grade-avg-cell.grade-ok { color: #3b82f6; }
        .grade-avg-cell.grade-warning { color: #f59e0b; }
        .grade-avg-cell.grade-bad { color: #ef4444; }

    </style>
</head>
<body>

    <div class="min-h-screen bg-gradient-to-b from-blue-50 to-indigo-50">
        <div class="max-w-4xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <?php echo $upload_message; // Mensajes de subida de foto ?>
            <?php 
            if (!empty($notification_message)) {
                $alert_class = '';
                if ($notification_type === 'success') {
                    $alert_class = 'bg-green-100 border border-green-400 text-green-700';
                } elseif ($notification_type === 'error') {
                    $alert_class = 'bg-red-100 border border-red-400 text-red-700';
                }
                echo '<div class="' . $alert_class . ' px-4 py-3 rounded relative mb-4" role="alert">';
                echo $notification_message;
                echo '</div>';
            }
            ?>

            <div class="bg-white shadow-lg rounded-lg overflow-hidden mb-6">
                <div class="p-6">
                    <div class="flex flex-col sm:flex-row items-center">
                        <div class="relative profile-pic-container mb-4 sm:mb-0 sm:mr-8 cursor-pointer" 
                             onclick="document.getElementById('photoUploadModal').style.display = 'flex';">
                            <div class="w-28 h-28 sm:w-32 sm:h-32 rounded-full overflow-hidden border-4 border-indigo-500">
                                <img id="profile-pic" src="<?php echo htmlspecialchars($photo_path_display); ?>" alt="Foto de perfil" class="w-full h-full object-cover">
                            </div>
                            <div class="absolute inset-0 flex items-center justify-center rounded-full bg-black bg-opacity-50 opacity-0 transition-opacity edit-overlay">
                                <span class="text-white text-sm font-medium flex items-center"><i class="fas fa-camera mr-2"></i> Cambiar foto</span>
                            </div>
                        </div>
                        
                        <div class="text-center sm:text-left flex-1">
                            <h1 id="student-name" class="text-2xl font-bold text-gray-800"><?php echo htmlspecialchars($student_info['name']); ?></h1>
                            <p class="text-gray-600" id="modality-jornada-display"><?php echo htmlspecialchars($nombreModalidad); ?> - Jornada: <?php echo htmlspecialchars($student_info['jornada']); ?></p>
                            <div class="mt-4 flex flex-wrap justify-center sm:justify-start gap-3">
                                <div class="bg-gradient-to-r from-indigo-500 to-purple-500 rounded-lg p-3 text-center min-w-[100px] shadow-md">
                                    <div class="text-2xl font-bold text-white" id="average-display"><?php echo htmlspecialchars($total_average); ?></div>
                                    <div class="text-sm text-white text-opacity-90">Promedio</div>
                                </div>
                                <div class="bg-gradient-to-r from-blue-500 to-indigo-500 rounded-lg p-3 text-center min-w-[100px] shadow-md">
                                    <div class="text-2xl font-bold text-white" id="grade-display"><?php echo htmlspecialchars($student_info['grade']); ?>°</div>
                                    <div class="text-sm text-white text-opacity-90">Grado</div>
                                </div>
                                <div class="bg-gradient-to-r from-purple-500 to-pink-500 rounded-lg p-3 text-center min-w-[100px] shadow-md">
                                    <div class="text-2xl font-bold text-white" id="section-display"><?php echo htmlspecialchars($student_info['seccion']); ?></div>
                                    <div class="text-sm text-white text-opacity-90">Sección</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="personal-info-panel" class="tab-content-panel <?php echo (!isset($_GET['tab']) || $_GET['tab'] == 'info' || $is_editing) ? 'active-panel' : 'hidden'; ?>">
                <div class="bg-white shadow-lg rounded-lg overflow-hidden">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-xl font-semibold text-gray-800">Detalles Personales</h2>
                            <?php if (!$is_editing): ?>
                                <a href="?edit=true" class="px-4 py-2 bg-indigo-500 text-white rounded-md hover:bg-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 flex items-center transition-colors duration-200">
                                    <i class="fas fa-pencil-alt mr-2"></i>Editar
                                </a>
                            <?php endif; ?>
                        </div>
                        
                        <form id="personal-info-form" action="student_profile.php" method="post">
                            <input type="hidden" name="action" value="update_profile">
                            <div class="space-y-6">
                                <div class="bg-gray-50 rounded-lg p-4">
                                    <h3 class="text-lg font-medium text-gray-700 mb-3">Información Básica</h3>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div class="data-item p-2 <?php echo $is_editing ? 'editing' : ''; ?>">
                                            <div class="text-sm font-medium text-gray-500 mb-1">Identidad</div>
                                            <?php if ($is_editing): ?>
                                                <input type="text" id="identity-input" name="ident" class="w-full p-2 border border-gray-300 rounded-md" value="<?php echo htmlspecialchars($student_info['ident']); ?>">
                                            <?php else: ?>
                                                <div id="identity-display" class="text-gray-800"><?php echo htmlspecialchars($student_info['ident']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="data-item p-2 <?php echo $is_editing ? 'editing' : ''; ?>">
                                            <div class="text-sm font-medium text-gray-500 mb-1">Fecha de Nacimiento</div>
                                            <?php if ($is_editing): ?>
                                                <input type="date" id="birthdate-input" name="birth" class="w-full p-2 border border-gray-300 rounded-md" value="<?php echo htmlspecialchars($student_info['birth']); ?>">
                                            <?php else: ?>
                                                <div id="birthdate-display" class="text-gray-800"><?php echo htmlspecialchars($student_info['birth']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="data-item p-2 <?php echo $is_editing ? 'editing' : ''; ?>">
                                            <div class="text-sm font-medium text-gray-500 mb-1">Edad</div>
                                            <?php if ($is_editing): ?>
                                                <input type="number" id="age-input" name="age" class="w-full p-2 border border-gray-300 rounded-md" value="<?php echo htmlspecialchars($student_info['age']); ?>">
                                            <?php else: ?>
                                                <div id="age-display" class="text-gray-800"><?php echo htmlspecialchars($student_info['age']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="data-item p-2 <?php echo $is_editing ? 'editing' : ''; ?>">
                                            <div class="text-sm font-medium text-gray-500 mb-1">Sexo</div>
                                            <?php if ($is_editing): ?>
                                                <select id="gender-input" name="sexo" class="w-full p-2 border border-gray-300 rounded-md">
                                                    <option value="1" <?php echo ($student_info['sexo'] == '1') ? 'selected' : ''; ?>>Femenino</option>
                                                    <option value="2" <?php echo ($student_info['sexo'] == '2') ? 'selected' : ''; ?>>Masculino</option>
                                                    <option value="" <?php echo (empty($student_info['sexo'])) ? 'selected' : ''; ?>>N/A</option>
                                                </select>
                                            <?php else: ?>
                                                <div id="gender-display" class="text-gray-800"><?php echo htmlspecialchars($display_sexo); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="bg-gray-50 rounded-lg p-4">
                                    <h3 class="text-lg font-medium text-gray-700 mb-3">Contacto</h3>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div class="data-item p-2 <?php echo $is_editing ? 'editing' : ''; ?>">
                                            <div class="text-sm font-medium text-gray-500 mb-1">Tel. Alumno</div>
                                            <?php if ($is_editing): ?>
                                                <input type="tel" id="student-phone-input" name="tel_a" class="w-full p-2 border border-gray-300 rounded-md" value="<?php echo htmlspecialchars($student_info['tel_a']); ?>">
                                            <?php else: ?>
                                                <div id="student-phone-display" class="text-gray-800"><?php echo htmlspecialchars($student_info['tel_a']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="data-item p-2 <?php echo $is_editing ? 'editing' : ''; ?>">
                                            <div class="text-sm font-medium text-gray-500 mb-1">Dirección</div>
                                            <?php if ($is_editing): ?>
                                                <input type="text" id="address-input" name="address" class="w-full p-2 border border-gray-300 rounded-md" value="<?php echo htmlspecialchars($student_info['address']); ?>">
                                            <?php else: ?>
                                                <div id="address-display" class="text-gray-800"><?php echo htmlspecialchars($student_info['address']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="bg-gray-50 rounded-lg p-4">
                                    <h3 class="text-lg font-medium text-gray-700 mb-3">Contacto Encargado</h3>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div class="data-item p-2 <?php echo $is_editing ? 'editing' : ''; ?>">
                                            <div class="text-sm font-medium text-gray-500 mb-1">Encargado</div>
                                            <?php if ($is_editing): ?>
                                                <input type="text" id="guardian-input" name="encargado" class="w-full p-2 border border-gray-300 rounded-md" value="<?php echo htmlspecialchars($student_info['encargado']); ?>">
                                            <?php else: ?>
                                                <div id="guardian-display" class="text-gray-800"><?php echo htmlspecialchars($student_info['encargado']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="data-item p-2 <?php echo $is_editing ? 'editing' : ''; ?>">
                                            <div class="text-sm font-medium text-gray-500 mb-1">Tel. Encargado</div>
                                            <?php if ($is_editing): ?>
                                                <input type="tel" id="guardian-phone-input" name="tel_p" class="w-full p-2 border border-gray-300 rounded-md" value="<?php echo htmlspecialchars($student_info['tel_p']); ?>">
                                            <?php else: ?>
                                                <div id="guardian-phone-display" class="text-gray-800"><?php echo htmlspecialchars($student_info['tel_p']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div id="form-buttons" class="mt-8 flex justify-end gap-4 <?php echo $is_editing ? '' : 'hidden'; ?>">
                                <button type="submit" class="px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-500 flex items-center transition-colors duration-200">
                                    <i class="fas fa-save mr-2"></i>Guardar Cambios
                                </button>
                                <a href="student_profile.php" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-400 flex items-center transition-colors duration-200">
                                    <i class="fas fa-times mr-2"></i>Cancelar
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div id="grades-panel" class="tab-content-panel hidden">
                <div class="bg-white shadow-lg rounded-lg overflow-hidden">
                    <div class="p-4 sm:p-6">
                        <h2 class="text-xl font-semibold text-gray-800 mb-6">Calificaciones por Materia</h2>
                        
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Materia</th>
                                        <th scope="col" class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">P1</th>
                                        <th scope="col" class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">P2</th>
                                        <th scope="col" class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">P3</th>
                                        <th scope="col" class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">P4</th>
                                        <th scope="col" class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Prom.</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($filtered_student_grades)): // MODIFICADO AQUÍ ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-gray-500 py-4">No hay calificaciones disponibles para este grado/sección.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($filtered_student_grades as $grade_row): // MODIFICADO AQUÍ 
                                        $p1 = $grade_row['primer_parcial_nota'] !== null ? (float)$grade_row['primer_parcial_nota'] : null;
                                        $p2 = $grade_row['segundo_parcial_nota'] !== null ? (float)$grade_row['segundo_parcial_nota'] : null;
                                        $p3 = $grade_row['tercer_parcial_nota'] !== null ? (float)$grade_row['tercer_parcial_nota'] : null;
                                        $p4 = $grade_row['cuarto_parcial_nota'] !== null ? (float)$grade_row['cuarto_parcial_nota'] : null;

                                        $sum = 0;
                                        $count = 0;
                                        if ($p1 !== null) { $sum += $p1; $count++; }
                                        if ($p2 !== null) { $sum += $p2; $count++; }
                                        if ($p3 !== null) { $sum += $p3; $count++; }
                                        if ($p4 !== null) { $sum += $p4; $count++; }

                                        $subject_average = $count > 0 ? number_format($sum / $count, 2) : 'N/A';

                                        $avg_color_class = '';
                                        $avg_text_color_class = ''; // Para el texto del promedio
                                        if (is_numeric($subject_average)) {
                                            if ($subject_average >= 90) { $avg_color_class = 'grade-good'; $avg_text_color_class = 'grade-avg-cell grade-good'; }
                                            else if ($subject_average >= 80) { $avg_color_class = 'grade-ok'; $avg_text_color_class = 'grade-avg-cell grade-ok';}
                                            else if ($subject_average >= 70) { $avg_color_class = 'grade-warning'; $avg_text_color_class = 'grade-avg-cell grade-warning';}
                                            else { $avg_color_class = 'grade-bad'; $avg_text_color_class = 'grade-avg-cell grade-bad';}
                                        }
                                    ?>
                                    <tr>
                                        <td class="px-3 sm:px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="w-3 h-3 rounded-full mr-3 grade-color-indicator <?php echo $avg_color_class; ?>"></div>
                                                <span class="font-medium text-gray-900 text-sm sm:text-base"><?php echo htmlspecialchars($grade_row['subject']); ?></span>
                                            </div>
                                        </td>
                                        <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo ($p1 !== null) ? htmlspecialchars(number_format($p1, 2)) : 'N/A'; ?></td>
                                        <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo ($p2 !== null) ? htmlspecialchars(number_format($p2, 2)) : 'N/A'; ?></td>
                                        <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo ($p3 !== null) ? htmlspecialchars(number_format($p3, 2)) : 'N/A'; ?></td>
                                        <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo ($p4 !== null) ? htmlspecialchars(number_format($p4, 2)) : 'N/A'; ?></td>
                                        <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm font-medium <?php echo $avg_text_color_class; ?>"><?php echo htmlspecialchars($subject_average); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mt-8 p-4 bg-gradient-to-r from-indigo-500 to-purple-500 rounded-lg shadow-md flex justify-between items-center">
                            <span class="text-lg font-medium text-white">Promedio General:</span>
                            <span class="text-xl font-bold text-white" id="overall-average-bottom"><?php echo htmlspecialchars($total_average); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div id="developer-panel" class="tab-content-panel hidden">
                <div class="bg-gradient-to-br from-gray-700 via-gray-800 to-indigo-900 shadow-lg rounded-lg overflow-hidden">
                    <div class="p-6 md:p-8">
                        <div class="flex flex-col items-center">
                            <div class="relative mb-6">
                                <div class="blob bg-gradient-to-br from-blue-500 via-indigo-600 to-purple-700 w-36 h-36 md:w-40 md:h-40 flex items-center justify-center rounded-full overflow-hidden">
                                    <img src="../img/neyferimg.jpg" alt="Developer" class="w-40 h-40 md:w-40 md:h-40 object-cover rounded-full p-1">
                                </div>
                                <div class="absolute -bottom-2 -right-2 bg-white rounded-full p-2 shadow-lg">
                                    <div class="bg-gradient-to-r from-green-500 to-teal-500 text-white rounded-full w-10 h-10 flex items-center justify-center">
                                        <i class="fas fa-terminal text-xl"></i> </div>
                                </div>
                            </div>
                            
                            <h2 class="text-3xl md:text-4xl font-bold text-white mb-2">Neyfer Coto</h2>
                            <p class="text-white text-opacity-90 text-lg mb-6">FullStack Web Developer</p>
                            
                            <div class="flex flex-wrap justify-center gap-4 mb-8">
                                <a href="https://github.com/neyfercoto" target="_blank" class="social-icon bg-white rounded-full shadow-lg hover:scale-110 transition-transform duration-200">
                                    <i class="fab fa-github text-3xl text-gray-800"></i>
                                </a>
                                <a href="https://www.linkedin.com/in/neyfer-coto-40b4692b9/?originalSubdomain=hn" target="_blank" class="social-icon bg-white rounded-full shadow-lg hover:scale-110 transition-transform duration-200">
                                    <i class="fab fa-linkedin text-3xl text-blue-600"></i>
                                </a>
                                <a href="https://twitter.com/neyfercoto" target="_blank" class="social-icon bg-white rounded-full shadow-lg hover:scale-110 transition-transform duration-200">
                                    <i class="fab fa-twitter text-3xl text-blue-400"></i>
                                </a>
                                <a href="https://instagram.com/neyfercoto" target="_blank" class="social-icon bg-white rounded-full shadow-lg hover:scale-110 transition-transform duration-200">
                                    <i class="fab fa-instagram text-3xl text-pink-600"></i>
                                </a>
                                <a href="mailto:neyfer.coto@example.com" class="social-icon bg-white rounded-full shadow-lg hover:scale-110 transition-transform duration-200">
                                    <i class="fas fa-envelope text-3xl text-red-500"></i>
                                </a>
                            </div>

                            <style>
                            /* ... tu CSS existente ... */

                            .social-icon {
                                /* Eliminamos p-3 y w-12 h-12 de las clases de Tailwind aquí, 
                                   y los reemplazamos con estos estilos CSS fijos y centrados para mayor control. */
                                display: inline-flex;
                                align-items: center;
                                justify-content: center;
                                width: 50px; /* Tamaño fijo, un poco más grande que w-12 (48px) para el text-3xl */
                                height: 50px; /* Asegura que sea un cuadrado perfecto */
                                padding: 0; /* Asegura que no haya padding que pueda deformar */
                                flex-shrink: 0; /* Evita que el contenedor se encoja */
                                /* Tus transiciones y sombras de Tailwind ya están en el HTML */
                            }

                            .social-icon i {
                                /* Asegura que el ícono no se estire y ocupe el espacio */
                                line-height: 1; /* Ayuda a la alineación vertical */
                            }

                            /* ... el resto de tus estilos ... */
                            </style>
            </div>
            	<div class="card" style="width: 22rem;">
                        <img src="../img/background.jpg" height="170px" class="card-img-top" alt="...">
                            <div class="rounded-circle overflow-hidden position-relative" style="width:40%; top: -60px; margin:auto;border: 0.5rem solid #fff;">
                                <img src="../img/neyferimg.jpg" style="max-width: 100%;" alt="" srcset="">
                            </div>
                    
                    <div class="card-body" style="">
                        <h4 class="card-title" style="color: #000;">Neyfer Enrique Coto</h4>
                        <p>@neyfercoto</p>
                        <h6>Full Stack Web Developer</h6>
                    </div>

                    <div class="card-footer">
                        <a href="https://www.fiverr.com/neyfercoto" target=”_blank” class="social-links"><img src='../icons/fiverr.svg' width='30'height='30'></a>
                        <a href="https://github.com/Neyfer" target=”_blank” class="social-links"><img src='../icons/github.svg' width='26'height='26'></a>
                        <a href="https://www.instagram.com/neyfercoto/" target=”_blank” class="social-links"><img src='../icons/instagram.svg' width='26'height='26'></a>
                        <a href="mailto: neyfercoto2005@gmail.com " target=”_blank” class="social-links"><img src='../icons/mail.svg' width='26'height='26'></a>
                    </div>
                    </div>            
                        
            </div>


            <footer class="bg-white mt-8 py-6 px-4">
                <div class="max-w-7xl mx-auto text-center text-gray-500 text-sm">
                    <p>CEMGT Rafael Leonardo Callejas</p>
                    <p class="mt-2">Desarrollado con <i class="fas fa-heart text-red-500"></i> por Neyfer Coto</p>
                </div>
            </footer>
        </div>

        <div class="modal" id="photoUploadModal" style="display: none;">
          <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="photoUploadModalLabel">Subir o Cambiar Foto de Perfil</h5>
                <button type="button" class="modal-close-btn" id="closeModalBtn">&times;</button>
              </div>
              <form action="student_profile.php" method="post" enctype="multipart/form-data">
                  <div class="modal-body">
                      <div class="mb-4">
                          <label for="profile_photo" class="block text-sm font-medium text-gray-700 mb-2">Seleccionar nueva foto:</label>
                          <input class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 focus:outline-none" type="file" id="profile_photo" name="profile_photo" accept="image/jpeg, image/png, image/gif" required>
                          <p class="mt-1 text-xs text-gray-500">Archivos permitidos: JPG, JPEG, PNG, GIF. Tamaño máximo: 5MB.</p>
                      </div>
                  </div>
                  <div class="modal-footer">
                    <button type="button" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400" id="cancelModalBtn">Cancelar</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-500 text-white rounded-md hover:bg-indigo-600">Subir Foto</button>
                  </div>
              </form>
          </div>
        </div>

        <div id="notification-area" style="position: fixed; bottom: 1rem; left: 50%; transform: translateX(-50%); z-index: 1050;"></div>
        
        <script>
            // Las funciones formatDateForDisplay y htmlspecialchars_js se mantienen por si se necesitan.
            // Simplified returns para evitar errores si no se usan
            function formatDateForDisplay(dateString) { return dateString; } 
            function htmlspecialchars_js(str) { return str; } 

            // --- Funciones para la Modal de Subida de Fotos (requiere JS) ---
            // Los elementos del modal deben existir
            const photoUploadModal = document.getElementById('photoUploadModal');
            // El trigger original "profile-pic-trigger" no existe, el contenedor de la imagen tiene el onclick directamente.
            // const profilePicTrigger = document.querySelector('.profile-pic-container'); // Usar el contenedor como trigger
            const closeModalBtn = document.getElementById('closeModalBtn');
            const cancelModalBtn = document.getElementById('cancelModalBtn');
            const profilePhotoInput = document.getElementById('profile_photo'); // El input file

            // El evento onclick ya está en el div profile-pic-container: onclick="document.getElementById('photoUploadModal').style.display = 'flex';"

            if (closeModalBtn) {
                closeModalBtn.addEventListener('click', function() {
                    if (photoUploadModal) photoUploadModal.style.display = 'none';
                    if (profilePhotoInput) profilePhotoInput.value = '';
                });
            }
            if (cancelModalBtn) {
                cancelModalBtn.addEventListener('click', function() {
                    if (photoUploadModal) photoUploadModal.style.display = 'none';
                    if (profilePhotoInput) profilePhotoInput.value = '';
                });
            }
            window.addEventListener('click', (event) => {
                if (photoUploadModal && event.target === photoUploadModal) {
                    photoUploadModal.style.display = 'none';
                    if (profilePhotoInput) profilePhotoInput.value = '';
                }
            });

            // Función de Notificación (JS) - Se mantiene
            function showNotification(message, type) {
                const notificationArea = document.getElementById('notification-area');
                if (!notificationArea) { console.error("Elemento 'notification-area' no encontrado."); return; } // Depuración
                let notification = notificationArea.querySelector('.notification');
                if (!notification) {
                    notification = document.createElement('div');
                    notificationArea.appendChild(notification);
                }
                notification.className = `notification ${type} text-white`; // Asegúrate que `type` corresponda a clases CSS definidas (success, error)
                notification.innerHTML = message;
                
                setTimeout(() => { notification.classList.add('show'); }, 50);
                setTimeout(() => {
                    notification.classList.remove('show');
                    setTimeout(() => {
                        if (notificationArea.contains(notification)) { notificationArea.removeChild(notification); }
                    }, 500);
                }, 3000);
            }

            // --- Lógica de Pestañas (Tabs) - Restaurada a JS puro ---
            document.addEventListener('DOMContentLoaded', () => {
                const tabButtons = document.querySelectorAll('.tab-btn');
                const tabPanels = document.querySelectorAll('.tab-content-panel');

                // Función principal para activar una pestaña
                function activateTab(targetPanelId) {
                    console.log(`Intentando activar pestaña: ${targetPanelId}`); 
                    // Desactivar todas las pestañas y ocultar todos los paneles
                    tabButtons.forEach(btn => {
                        btn.classList.remove('border-indigo-500', 'text-indigo-600');
                        btn.classList.add('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300');
                    });
                    tabPanels.forEach(panel => {
                        panel.classList.remove('active-panel');
                        panel.classList.add('hidden');
                    });
                    
                    // Activar el botón de la pestaña objetivo
                    const activeButton = document.querySelector(`.tab-btn[data-tab-target="${targetPanelId}"]`);
                    if (activeButton) {
                        activeButton.classList.remove('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300');
                        activeButton.classList.add('border-indigo-500', 'text-indigo-600');
                        console.log(`Botón de pestaña activo: ${activeButton.dataset.tabName}`);
                    } else {
                        console.error(`Error: Botón de pestaña con data-tab-target="${targetPanelId}" no encontrado.`);
                    }
                    
                    // Mostrar el panel de contenido objetivo
                    const targetPanel = document.getElementById(targetPanelId);
                    if (targetPanel) {
                        targetPanel.classList.add('active-panel');
                        targetPanel.classList.remove('hidden');
                        console.log(`Panel activo: ${targetPanelId}`);
                    } else {
                        console.error(`Error: Panel con id="${targetPanelId}" no encontrado.`);
                    }

                    // Actualizar la URL sin recargar la página (para compartir enlaces)
                    const url = new URL(window.location);
                    url.searchParams.set('tab', targetPanelId.replace('-panel', ''));
                    // Asegurarse de que el parámetro 'edit' se elimina al cambiar de pestaña
                    url.searchParams.delete('edit'); 
                    window.history.pushState({}, '', url);
                    console.log(`URL actualizada: ${url.toString()}`);
                }

                // Asignar listeners a los botones de pestaña
                tabButtons.forEach(button => {
                    button.addEventListener('click', function(event) {
                        event.preventDefault(); // ¡CLAVE! Evita la recarga de página para los botones de pestaña
                        const targetPanelId = this.dataset.tabTarget;
                        activateTab(targetPanelId);
                    });
                });

                // Lógica para activar la pestaña correcta al cargar la página (basada en URL)
                const urlParams = new URLSearchParams(window.location.search);
                const activeTabParam = urlParams.get('tab');
                const isEditingParam = urlParams.get('edit') === 'true'; 

                // Determinar la pestaña activa inicial
                let initialTabToActivate = 'personal-info-panel'; // Por defecto

                if (isEditingParam) {
                    initialTabToActivate = 'personal-info-panel'; // Si estamos editando, info
                } else if (activeTabParam) {
                    const potentialPanelId = `${activeTabParam}-panel`;
                    // Verificar si el panel existe antes de intentar activarlo
                    if (document.getElementById(potentialPanelId)) {
                         initialTabToActivate = potentialPanelId; // Si hay param 'tab', úsalo
                    } else {
                        console.warn(`Panel para el parámetro de tab '${activeTabParam}' no encontrado. Volviendo al panel por defecto.`);
                    }
                }
                
                // Activar la pestaña inicial
                activateTab(initialTabToActivate);
                console.log("Scripts de pestaña y modal inicializados.");
            });
        </script>
</body>
</html>