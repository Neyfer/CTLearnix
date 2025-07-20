<?php
$page_title = "Mi Perfil";
session_start();
include('mysql.php'); //

if (!isset($_SESSION['student_id'])) { //
    header('Location: student_login.php'); //
    exit(); //
}
$student_id = $_SESSION['student_id']; //

$upload_message = ''; //
$notification_message = ''; //
$notification_type = ''; //

// --- LÓGICA DE PHP PARA SUBIR FOTO Y ACTUALIZAR DATOS (sin cambios) ---
if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] == UPLOAD_ERR_OK) { //
    $target_dir = '../img_students/'; //
    if (!is_dir($target_dir)) { mkdir($target_dir, 0777, true); } //
    $file_extension = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION)); //
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif']; //

    if (!in_array($file_extension, $allowed_extensions)) { //
        $upload_message = '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert"><p class="font-bold">Error:</p><p>Solo se permiten archivos de imagen (JPG, PNG, GIF).</p></div>'; //
    } elseif ($_FILES['profile_photo']['size'] > 5000000) { //
        $upload_message = '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert"><p class="font-bold">Error:</p><p>El archivo es demasiado grande (máx. 5MB).</p></div>'; //
    } else {
        $new_filename = 'student_' . $student_id . '_' . time() . '.' . $file_extension; //
        $target_file = $target_dir . $new_filename; //
        if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $target_file)) { //
            $old_img_query = mysqli_query($conn, "SELECT img FROM students WHERE id = $student_id"); //
            $old_img_row = mysqli_fetch_assoc($old_img_query); //
            if (!empty($old_img_row['img']) && file_exists($target_dir . $old_img_row['img'])) { //
                unlink($target_dir . $old_img_row['img']); //
            }
            $stmt = mysqli_prepare($conn, "UPDATE students SET img = ? WHERE id = ?"); //
            mysqli_stmt_bind_param($stmt, "si", $new_filename, $student_id); //
            if (mysqli_stmt_execute($stmt)) { //
                header('Location: perfil.php?upload_status=success'); //
                exit(); //
            } else {
                 $upload_message = '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert"><p class="font-bold">Error de Base de Datos:</p><p>No se pudo actualizar el nombre de la imagen.</p></div>'; //
            }
             mysqli_stmt_close($stmt); //
        } else {
            $upload_message = '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert"><p class="font-bold">Error del Servidor:</p><p>No se pudo mover el archivo. Verifica los permisos de la carpeta `img_students/`.</p></div>'; //
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') { //
    $ident = mysqli_real_escape_string($conn, $_POST['ident'] ?? ''); //
    $birth = mysqli_real_escape_string($conn, $_POST['birth'] ?? ''); //
    $age = mysqli_real_escape_string($conn, $_POST['age'] ?? ''); //
    $sexo = mysqli_real_escape_string($conn, $_POST['sexo'] ?? ''); //
    $tel_a = mysqli_real_escape_string($conn, $_POST['tel_a'] ?? ''); //
    $address = mysqli_real_escape_string($conn, $_POST['address'] ?? ''); //
    $encargado = mysqli_real_escape_string($conn, $_POST['encargado'] ?? ''); //
    $tel_p = mysqli_real_escape_string($conn, $_POST['tel_p'] ?? ''); //
    $update_query = "UPDATE students SET ident = '$ident', birth = '$birth', age = '$age', sexo = '$sexo', tel_a = '$tel_a', address = '$address', encargado = '$encargado', tel_p = '$tel_p' WHERE id = $student_id"; //
    if (mysqli_query($conn, $update_query)) { //
        $notification_message = '¡Información actualizada con éxito!'; $notification_type = 'success'; //
    } else {
        $notification_message = 'Error al actualizar: ' . mysqli_error($conn); $notification_type = 'error'; //
    }
}

$query_student_data = mysqli_query($conn, "SELECT * FROM students WHERE id = $student_id"); //
$student_info = mysqli_fetch_assoc($query_student_data); //
if (!$student_info) { die("No se encontró información para el alumno."); } //

$grade = $student_info['grade']; //
$modalidad_id = $student_info['modalidad']; //
$nombreModalidad = 'Modalidad Desconocida'; //
if ($grade < 10) { $nombreModalidad = "Educación Básica"; } //
elseif ($grade >= 11 && $modalidad_id == 3) { $nombreModalidad = "Bachiller en Ciencias y Humanidades"; } //
elseif ($grade >= 11 && $modalidad_id == 4) { $nombreModalidad = "Bachiller Técnico Profesional en Informática"; } //

$query_average = "SELECT COALESCE(AVG(all_grades.nota), 0) AS total_average_grade FROM (SELECT nota FROM primer_parcial WHERE student_id = $student_id UNION ALL SELECT nota FROM segundo_parcial WHERE student_id = $student_id UNION ALL SELECT nota FROM tercer_parcial WHERE student_id = $student_id UNION ALL SELECT nota FROM cuarto_parcial WHERE student_id = $student_id) AS all_grades;"; //
$result_average = mysqli_query($conn, $query_average); //
$average_row = mysqli_fetch_assoc($result_average); //
$total_average = round($average_row['total_average_grade'], 2); //

$target_dir_display = '../img_students/'; //
$photo_path_display = !empty($student_info['img']) ? $target_dir_display . $student_info['img'] : 'https://via.placeholder.com/150/cccccc/ffffff?text=SIN+FOTO'; //
if (empty($student_info['img']) || !file_exists(str_replace(' ', '%20', $photo_path_display))) { //
    $photo_path_display = 'https://via.placeholder.com/150/cccccc/ffffff?text=SIN+FOTO'; //
}

$is_editing = isset($_GET['edit']) && $_GET['edit'] === 'true'; //
$sexo_map = ['1' => 'Femenino', '2' => 'Masculino', '' => 'N/A']; //
$display_sexo = $sexo_map[$student_info['sexo']] ?? 'N/A'; //

require_once 'components/student_header.php'; 
?>

<div class="max-w-5xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
    <?php 
        if (isset($_GET['upload_status'])) echo '<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-lg" role="alert"><p class="font-bold">Éxito:</p><p>Foto de perfil actualizada.</p></div>'; //
        echo $upload_message; //
        if (!empty($notification_message)) { //
            $alert_class = ($notification_type === 'success') ? 'bg-green-100 border-green-400 text-green-700' : 'bg-red-100 border-red-400 text-red-700'; //
            echo '<div class="' . $alert_class . ' p-4 rounded-lg mb-6">' . $notification_message . '</div>'; //
        }
    ?>

    <div class="bg-white rounded-2xl shadow-xl p-6 mb-8">
        <div class="flex flex-col md:flex-row items-center md:items-start md:space-x-6">
            <div id="photo-upload-trigger" class="relative group cursor-pointer mb-4 md:mb-0 flex-shrink-0">
                <img src="<?php echo htmlspecialchars($photo_path_display); ?>" alt="Foto de Perfil" class="h-32 w-32 rounded-full object-cover border-4 border-gray-100 shadow-md">
                <div class="absolute inset-0 flex items-center justify-center bg-black bg-opacity-50 rounded-full opacity-0 group-hover:opacity-100 transition-opacity">
                    <i class="fas fa-camera text-white text-3xl"></i>
                </div>
            </div>
            
            <div class="w-full text-center md:text-left">
                <h1 class="text-3xl font-bold text-gray-800"><?php echo htmlspecialchars($student_info['name']); ?></h1>
                <p class="text-md text-gray-500 mt-1"><?php echo htmlspecialchars($nombreModalidad); ?></p>
                
                <div class="mt-4 pt-4 border-t border-gray-200 grid grid-cols-3 gap-4 text-center">
                    <div>
                        <p class="text-2xl font-bold text-indigo-600"><?php echo htmlspecialchars($total_average); ?></p>
                        <p class="text-sm font-medium text-gray-500">Índice Global</p>
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-gray-700"><?php echo htmlspecialchars($student_info['grade']); ?>°</p>
                        <p class="text-sm font-medium text-gray-500">Grado</p>
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-gray-700">'<?php echo htmlspecialchars($student_info['seccion']); ?>'</p>
                        <p class="text-sm font-medium text-gray-500">Sección</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <form action="perfil.php<?php echo $is_editing ? '?edit=true' : ''; ?>" method="post">
        <input type="hidden" name="action" value="update_profile">
        <div class="bg-white shadow-xl rounded-2xl p-6">
            <div class="flex justify-between items-center pb-4 border-b border-gray-200 mb-6">
                <h2 class="text-xl font-semibold text-gray-800">Información Detallada</h2>
                <?php if (!$is_editing): ?>
                    <a href="perfil.php?edit=true" class="bg-indigo-500 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-indigo-600 transition shadow-sm flex items-center">
                        <i class="fas fa-pencil-alt mr-2"></i>Editar Perfil
                    </a>
                <?php endif; ?>
            </div>
            
            <h3 class="text-lg font-semibold text-gray-700 mb-4">Información Académica y Personal</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                <div><label class="block text-sm font-medium text-gray-500">Identidad</label><?php if($is_editing):?><input type="text" name="ident" class="mt-1 w-full p-2 bg-white border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500" value="<?php echo htmlspecialchars($student_info['ident']);?>"><?php else:?><p class="mt-1 text-md text-gray-900 font-semibold"><?php echo htmlspecialchars($student_info['ident'] ?: 'N/A');?></p><?php endif;?></div>
                <div><label class="block text-sm font-medium text-gray-500">Fecha de Nacimiento</label><?php if($is_editing):?><input type="date" name="birth" class="mt-1 w-full p-2 bg-white border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500" value="<?php echo htmlspecialchars($student_info['birth']);?>"><?php else:?><p class="mt-1 text-md text-gray-900 font-semibold"><?php echo htmlspecialchars($student_info['birth'] ?: 'N/A');?></p><?php endif;?></div>
                <div><label class="block text-sm font-medium text-gray-500">Edad</label><?php if($is_editing):?><input type="number" name="age" class="mt-1 w-full p-2 bg-white border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500" value="<?php echo htmlspecialchars($student_info['age']);?>"><?php else:?><p class="mt-1 text-md text-gray-900 font-semibold"><?php echo htmlspecialchars($student_info['age'] ?: 'N/A');?></p><?php endif;?></div>
                <div><label class="block text-sm font-medium text-gray-500">Sexo</label><?php if($is_editing):?><select name="sexo" class="mt-1 w-full p-2 bg-white border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"><option value="1" <?php if($student_info['sexo']=='1') echo 'selected';?>>Femenino</option><option value="2" <?php if($student_info['sexo']=='2') echo 'selected';?>>Masculino</option></select><?php else:?><p class="mt-1 text-md text-gray-900 font-semibold"><?php echo htmlspecialchars($display_sexo);?></p><?php endif;?></div>
                <div class="md:col-span-2"><label class="block text-sm font-medium text-gray-500">Dirección</label><?php if($is_editing):?><input type="text" name="address" class="mt-1 w-full p-2 bg-white border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500" value="<?php echo htmlspecialchars($student_info['address']);?>"><?php else:?><p class="mt-1 text-md text-gray-900 font-semibold"><?php echo htmlspecialchars($student_info['address'] ?: 'N/A');?></p><?php endif;?></div>
            </div>

            <hr class="my-6">

            <h3 class="text-lg font-semibold text-gray-700 mb-4">Contacto de Emergencia</h3>
             <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                <div><label class="block text-sm font-medium text-gray-500">Teléfono Personal</label><?php if($is_editing):?><input type="tel" name="tel_a" class="mt-1 w-full p-2 bg-white border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500" value="<?php echo htmlspecialchars($student_info['tel_a']);?>"><?php else:?><p class="mt-1 text-md text-gray-900 font-semibold"><?php echo htmlspecialchars($student_info['tel_a'] ?: 'N/A');?></p><?php endif;?></div>
                <div><label class="block text-sm font-medium text-gray-500">Nombre del Encargado</label><?php if($is_editing):?><input type="text" name="encargado" class="mt-1 w-full p-2 bg-white border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500" value="<?php echo htmlspecialchars($student_info['encargado']);?>"><?php else:?><p class="mt-1 text-md text-gray-900 font-semibold"><?php echo htmlspecialchars($student_info['encargado'] ?: 'N/A');?></p><?php endif;?></div>
                <div class="md:col-span-2"><label class="block text-sm font-medium text-gray-500">Teléfono del Encargado</label><?php if($is_editing):?><input type="tel" name="tel_p" class="mt-1 w-full p-2 bg-white border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500" value="<?php echo htmlspecialchars($student_info['tel_p']);?>"><?php else:?><p class="mt-1 text-md text-gray-900 font-semibold"><?php echo htmlspecialchars($student_info['tel_p'] ?: 'N/A');?></p><?php endif;?></div>
            </div>

            <div class="mt-8 flex justify-end gap-3 <?php if(!$is_editing) echo 'hidden'; ?>">
                <a href="perfil.php" class="bg-gray-200 text-gray-800 font-semibold px-4 py-2 rounded-lg hover:bg-gray-300 transition">Cancelar</a>
                <button type="submit" class="bg-green-600 text-white font-semibold px-4 py-2 rounded-lg hover:bg-green-700 transition flex items-center">
                    <i class="fas fa-save mr-2"></i>Guardar Cambios
                </button>
            </div>
        </div>
    </form>
</div>

<div id="photoUploadModal" class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center hidden z-50 p-4">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
        <form action="perfil.php" method="post" enctype="multipart/form-data">
            <div class="p-5 border-b flex justify-between items-center">
                <h5 class="text-lg font-semibold">Actualizar Foto de Perfil</h5>
                <button type="button" class="btn-cerrar-modal text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
            </div>
            <div class="p-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Seleccionar nueva foto:</label>
                <input class="block w-full text-sm text-gray-700 border border-gray-300 rounded-lg cursor-pointer bg-white focus:outline-none file:mr-4 file:py-2 file:px-4 file:rounded-l-lg file:border-0 file:font-semibold file:bg-indigo-600 file:text-white hover:file:bg-indigo-700" type="file" name="profile_photo" accept="image/jpeg, image/png, image/gif" required>
                <p class="mt-1 text-xs text-gray-500">JPG, PNG, GIF (Tamaño máximo: 5MB)</p>
            </div>
            <div class="p-4 bg-gray-50 flex justify-end gap-3 rounded-b-lg">
                <button type="button" class="btn-cerrar-modal px-4 py-2 bg-gray-200 text-gray-800 rounded-md font-semibold hover:bg-gray-300">Cancelar</button>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md font-semibold hover:bg-indigo-700">Subir Foto</button>
            </div>
        </form>
    </div>
</div>

<?php require_once 'components/student_footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const photoUploadModal = document.getElementById('photoUploadModal');
    const openBtn = document.getElementById('photo-upload-trigger');
    const closeBtns = document.querySelectorAll('.btn-cerrar-modal');
    
    if (openBtn) { 
        openBtn.addEventListener('click', () => photoUploadModal.classList.remove('hidden')); 
    }
    
    closeBtns.forEach(btn => { 
        btn.addEventListener('click', () => photoUploadModal.classList.add('hidden')); 
    });
    
    if (photoUploadModal) { 
        window.addEventListener('click', (event) => { 
            if (event.target == photoUploadModal) { 
                photoUploadModal.classList.add('hidden'); 
            }
        }); 
    }
});
</script>