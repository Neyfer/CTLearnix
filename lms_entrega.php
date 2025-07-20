<?php
session_start();
if (!isset($_SESSION['student_id']) || $_SESSION['role'] !== 'student') {
    header('Location: student_login.php'); exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('Error: ID de actividad no válido.');
}
$actividad_id = $_GET['id'];
$student_id = $_SESSION['student_id'];
$message = '';

require_once 'db_manager.php';

// --- CONFIGURACIÓN PARA SUBIDA DE ARCHIVOS DE ENTREGA A GOOGLE DRIVE MONTADO ---
// ¡IMPORTANTE! Reemplaza 'lms_files' con el nombre de la cuenta de Google Drive
// a la que quieres subir los archivos de entrega. Esta cuenta debe estar MONTADA y con SYMLINK.
// Ejemplo: si tu cuenta de Drive para el LMS se llama 'lms_drive', usa 'lms_drive'.
define('ENTREGA_DRIVE_ACCOUNT_NAME', 'lms_files'); // <-- ¡AJUSTA ESTE VALOR!

// Subcarpeta dentro de esa cuenta montada donde se guardarán las entregas.
define('ENTREGA_DRIVE_SUBFOLDER', 'entregas');

// URL base de tu sitio web donde se accede al symlink de la cuenta montada.
// Asegúrate de que coincida con tu dominio y ruta base.
// Ejemplo: si tu symlink 'lms_files' está en https://tudominio.com/lms_files/
define('ENTREGA_BASE_WEB_URL', 'https://neyfercoto.online/'); // <-- ¡AJUSTA ESTE VALOR!
define('RCLONE_MOUNT_BASE_DIR', '/mnt/gdrive'); // Directorio base donde rclone monta

// -----------------------------------------------------------------------------

// --- LÓGICA PARA PROCESAR EL FORMULARIO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = getDbConnection();
    // Prevenir edición si ya está calificado (seguridad en el backend)
    $stmt_check_cal = $conn->prepare("SELECT calificacion FROM lms_entregas WHERE id_actividad = ? AND id_estudiante = ?");
    $stmt_check_cal->bind_param("ii", $actividad_id, $student_id);
    $stmt_check_cal->execute();
    $calif_result = $stmt_check_cal->get_result()->fetch_assoc();
    $stmt_check_cal->close();

    if ($calif_result && $calif_result['calificacion'] !== null) {
        $message = '<div class="bg-red-100 text-red-800 p-4 rounded-lg mb-6">Error: Esta tarea ya ha sido calificada y no se puede modificar.</div>';
    } else {
        $texto_respuesta = $_POST['texto_respuesta'];
        $ruta_archivo_virtual = $_POST['existing_file']; // Mantener el archivo existente si no se sube uno nuevo

        // Almacenar la ruta del archivo antiguo si existe una entrega previa
        $old_ruta_archivo = '';
        if ($actividad['entrega_id']) { // Solo si ya existe una entrega previa
            $stmt_old_path = $conn->prepare("SELECT ruta_archivo FROM lms_entregas WHERE id = ?");
            $stmt_old_path->bind_param("i", $actividad['entrega_id']);
            $stmt_old_path->execute();
            $old_path_result = $stmt_old_path->get_result()->fetch_assoc();
            $old_ruta_archivo = $old_path_result['ruta_archivo'] ?? '';
            $stmt_old_path->close();
        }

        // ✨ CÓDIGO REAL DE SUBIDA A GOOGLE DRIVE MONTADO PARA ARCHIVO_ENTREGA ✨
        if (isset($_FILES['archivo_entrega']) && $_FILES['archivo_entrega']['error'] == 0) {
            $file_ext = strtolower(pathinfo($_FILES['archivo_entrega']['name'], PATHINFO_EXTENSION));
            $new_filename = "entrega_" . $actividad_id . "_est_" . $student_id . "_" . time() . "." . $file_ext;
            
            $target_dir_mounted = RCLONE_MOUNT_BASE_DIR . '/' . ENTREGA_DRIVE_ACCOUNT_NAME . '/' . ENTREGA_DRIVE_SUBFOLDER;
            $target_file_mounted = $target_dir_mounted . '/' . $new_filename;

            // Construir la URL web final para guardar en la BD
            $ruta_archivo_virtual = ENTREGA_BASE_WEB_URL . ENTREGA_DRIVE_ACCOUNT_NAME . '/' . ENTREGA_DRIVE_SUBFOLDER . '/' . $new_filename;

            // Asegurarse de que el directorio de destino exista en el Google Drive montado y sea propiedad de www-data
            if (!is_dir($target_dir_mounted)) {
                $mkdir_command = "sudo mkdir -p \"{$target_dir_mounted}\" 2>&1";
                exec($mkdir_command, $output, $return_code);
                if ($return_code !== 0) {
                    $message = '<div class="bg-red-100 text-red-800 p-4 rounded-lg mb-6">Error: No se pudo crear el directorio de destino para la entrega: ' . implode(" ", $output) . '</div>';
                }
            }
            // Asegurar chown AUNQUE el directorio ya existiera o haya sido creado por root.
            $chown_command = "sudo chown www-data:www-data \"{$target_dir_mounted}\" 2>&1";
            exec($chown_command, $output, $return_code);
            if ($return_code !== 0) { error_log("Falló chown del nuevo directorio de entrega: " . implode(" ", $output)); }

            // --- CAMBIO CLAVE: Usar copy() en lugar de rename() ---
            if (empty($message) && copy($_FILES['archivo_entrega']['tmp_name'], $target_file_mounted)) {
                 // Asegurar chown del archivo subido inmediatamente después de copiarlo.
                 $chown_file_command = "sudo chown www-data:www-data \"{$target_file_mounted}\" 2>&1";
                 exec($chown_file_command, $output, $return_code);
                 if ($return_code !== 0) { error_log("Falló chown del archivo de entrega subido: " . implode(" ", $output)); }
                 
                 unlink($_FILES['archivo_entrega']['tmp_name']); // Eliminar el archivo temporal original

                 // --- NUEVO: Eliminar el archivo antiguo si se subió uno nuevo y existía uno viejo ---
                 if (!empty($old_ruta_archivo) && $old_ruta_archivo !== $ruta_archivo_virtual) {
                    // Convertir URL web a ruta física para borrar
                    $old_physical_path = str_replace(ENTREGA_BASE_WEB_URL . ENTREGA_DRIVE_ACCOUNT_NAME . '/', RCLONE_MOUNT_BASE_DIR . '/' . ENTREGA_DRIVE_ACCOUNT_NAME . '/', $old_ruta_archivo);
                    
                    if (file_exists($old_physical_path)) { // Solo si el archivo realmente existe
                        $rm_command = "sudo rm \"{$old_physical_path}\" 2>&1";
                        exec($rm_command, $rm_output, $rm_return_code);
                        if ($rm_return_code !== 0) {
                            error_log("Falló al eliminar archivo de entrega antiguo: " . implode(" ", $rm_output));
                            $message .= '<div class="bg-yellow-100 text-yellow-800 p-4 rounded-lg mt-4">Advertencia: No se pudo eliminar el archivo anterior. Limpieza manual podría ser necesaria.</div>';
                        }
                    }
                 }
            } else if (empty($message)) { // Solo si no hay ya un mensaje de error previo
                $message = '<div class="bg-red-100 text-red-800 p-4 rounded-lg mb-6">Error al mover/copiar el archivo de entrega a su ubicación final. Verifique permisos o si el Drive está montado.</div>';
            }
        }
        
        // Solo procesar la base de datos si no hay errores de subida de archivo
        if (empty($message)) { 
            $stmt_check = $conn->prepare("SELECT id FROM lms_entregas WHERE id_actividad = ? AND id_estudiante = ?");
            $stmt_check->bind_param("ii", $actividad_id, $student_id);
            $stmt_check->execute();
            $entrega_existente = $stmt_check->get_result()->fetch_assoc();
            $stmt_check->close();

            if ($entrega_existente) {
                // Actualizar entrega existente
                $sql = "UPDATE lms_entregas SET texto_respuesta = ?, ruta_archivo = ?, fecha_entrega = NOW(), calificacion = NULL, comentario_docente = NULL WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssi", $texto_respuesta, $ruta_archivo_virtual, $entrega_existente['id']);
            } else {
                // Insertar nueva entrega
                $sql = "INSERT INTO lms_entregas (id_actividad, id_estudiante, texto_respuesta, ruta_archivo, fecha_entrega) VALUES (?, ?, ?, ?, NOW())";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iiss", $actividad_id, $student_id, $texto_respuesta, $ruta_archivo_virtual);
            }
            
            if ($stmt->execute()) {
                header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $actividad_id . '&status=success');
                exit();
            } else {
                $message = '<div class="bg-red-100 text-red-800 p-4 rounded-lg mb-6">Error al procesar la entrega en la base de datos.</div>';
            }
            $stmt->close();
        }
    }
    $conn->close();
}

// --- LÓGICA PARA MOSTRAR LA PÁGINA ---
$conn = getDbConnection();
$sql = "SELECT act.*, asi.subject, asi.id as materia_id, ent.id as entrega_id, ent.calificacion, ent.comentario_docente, ent.ruta_archivo, ent.texto_respuesta, ent.fecha_entrega AS fecha_de_entrega FROM lms_actividades act JOIN asignaturas asi ON act.id_asignatura = asi.id LEFT JOIN lms_entregas ent ON ent.id_actividad = act.id AND ent.id_estudiante = ? WHERE act.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $student_id, $actividad_id);
$stmt->execute();
$actividad = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();
if (!$actividad) { die("Actividad no encontrada."); }

$page_title = $actividad['nombre_actividad'];
require_once 'components/student_header.php';
?>

<?php 
    if (isset($_GET['status']) && $_GET['status'] == 'success' && !$message) {
        echo '<div class="bg-green-100 text-green-800 p-4 rounded-lg mb-6 shadow-sm"><strong>¡Éxito!</strong> Tu entrega ha sido procesada correctamente.</div>';
    }
    echo $message; 
?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    
    <div class="lg:col-span-2 space-y-8">
        <div class="bg-white p-6 rounded-xl shadow-md">
            <a href="lms_actividades.php?id=<?php echo $actividad['materia_id']; ?>" class="text-sm text-indigo-600 hover:text-indigo-800 mb-4 inline-block"><i class="fas fa-arrow-left mr-2"></i>Volver a la lista de tareas</a>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-800"><?php echo htmlspecialchars($actividad['nombre_actividad']); ?></h1>
            <p class="text-gray-500 mt-1">Materia: <?php echo htmlspecialchars($actividad['subject']); ?></p>
            <hr class="my-4">
            <h3 class="font-bold text-lg mb-2 text-gray-700">Instrucciones del Docente</h3>
            <div class="prose max-w-none text-gray-700 whitespace-pre-wrap"><?php echo !empty($actividad['descripcion']) ? nl2br(htmlspecialchars($actividad['descripcion'])) : 'No hay instrucciones detalladas.'; ?></div>
        </div>

        <?php if ($actividad['entrega_id']): ?>
            <div id="entrega-actual-card" class="bg-white p-6 rounded-xl shadow-md">
                <h3 class="text-xl font-bold border-b pb-2 mb-4 text-gray-800 flex items-center">
                    <i class="fas fa-box-open mr-2 text-indigo-500"></i>Tu Entrega Actual
                </h3>
                <p class="text-sm text-gray-600 mb-3">Entregado el: <?php echo date('d/m/Y h:i A', strtotime($actividad['fecha_de_entrega'])); ?></p>
                
                <div id="entrega-read-only-view" class="<?php echo ($actividad['calificacion'] === null && $actividad['entrega_id']) ? '' : ''; ?>">
                    <?php if (!empty($actividad['texto_respuesta'])): ?>
                        <div class="mb-4">
                            <h4 class="font-semibold text-gray-700 mb-2">Respuesta de Texto:</h4>
                            <div class="prose max-w-none p-3 bg-gray-50 rounded-lg border border-gray-200">
                                <?php echo $actividad['texto_respuesta']; // No se usa htmlspecialchars porque TinyMCE ya inserta HTML ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($actividad['ruta_archivo'])): ?>
                        <div class="mb-4">
                            <h4 class="font-semibold text-gray-700 mb-2">Archivo Adjunto:</h4>
                            <a href="<?php echo htmlspecialchars($actividad['ruta_archivo']); ?>" target="_blank" class="inline-flex items-center px-4 py-2 bg-indigo-500 text-white font-bold rounded-lg hover:bg-indigo-600 transition">
                                <i class="fas fa-file-download mr-2"></i> Descargar/Ver Archivo
                            </a>
                            <span class="text-sm text-gray-600 ml-3"><?php echo htmlspecialchars(basename($actividad['ruta_archivo'])); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($actividad['calificacion'] === null): // Mostrar botón de editar solo si NO está calificado ?>
                        <button type="button" id="btn-editar-entrega" class="w-full mt-4 bg-yellow-500 text-white font-bold py-2 rounded-lg hover:bg-yellow-600 transition">
                            <i class="fas fa-edit mr-2"></i>Editar Entrega
                        </button>
                    <?php endif; ?>
                </div>

                <div id="entrega-edit-form" class="space-y-4 <?php echo ($actividad['calificacion'] === null && $actividad['entrega_id'] && !isset($_GET['edit_mode'])) ? 'hidden' : ''; ?>">
                     <form method="POST" enctype="multipart/form-data" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Adjuntar Archivo (opcional)</label>
                            <input type="file" name="archivo_entrega" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                            <input type="hidden" name="existing_file" value="<?php echo htmlspecialchars($actividad['ruta_archivo'] ?? ''); ?>">
                            
                            <?php if ($actividad['entrega_id'] && !empty($actividad['ruta_archivo'])): ?>
                                <p class="text-sm text-gray-600 mt-2">
                                    Archivo adjunto existente: 
                                    <a href="<?php echo htmlspecialchars($actividad['ruta_archivo']); ?>" target="_blank" class="text-indigo-600 hover:underline">
                                        <i class="fas fa-file-alt mr-1"></i> <?php echo htmlspecialchars(basename($actividad['ruta_archivo'])); ?>
                                    </a>
                                    (Se reemplazará si subes uno nuevo)
                                </p>
                            <?php endif; ?>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tu Respuesta (si es necesario)</label>
                            <textarea id="mytextarea" name="texto_respuesta" class="w-full p-2 border border-gray-300 rounded-md" rows="10"><?php echo $actividad['texto_respuesta'] ?? ''; ?></textarea>
                        </div>
                        
                        <div class="flex items-center gap-2 pt-2">
                            <?php if ($actividad['entrega_id']): ?>
                                <button type="button" id="btn-cancelar-edicion" class="w-full bg-gray-200 text-gray-800 font-bold py-3 rounded-lg hover:bg-gray-300 transition">Cancelar</button>
                            <?php endif; ?>
                            <button type="submit" class="w-full bg-indigo-600 text-white font-bold py-3 rounded-lg hover:bg-indigo-700 transition">
                                <i class="fas fa-paper-plane mr-2"></i> <?php echo $actividad['entrega_id'] ? 'Actualizar Entrega' : 'Confirmar Entrega'; ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($actividad['calificacion'] === null && !$actividad['entrega_id']): // Mostrar formulario de entrega inicial solo si NO hay entrega y NO está calificado?>
            <div id="formulario-entrega-initial" class="bg-white p-6 rounded-xl shadow-md">
                <h3 class="text-xl font-bold border-b pb-2 mb-4 text-gray-800">Realizar Entrega</h3>
                <form method="POST" enctype="multipart/form-data" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Adjuntar Archivo (opcional)</label>
                        <input type="file" name="archivo_entrega" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tu Respuesta (si es necesario)</label>
                        <textarea id="mytextarea_initial" name="texto_respuesta" class="w-full p-2 border border-gray-300 rounded-md" rows="10"></textarea>
                    </div>
                    <div class="flex items-center gap-2 pt-2">
                        <button type="submit" class="w-full bg-indigo-600 text-white font-bold py-3 rounded-lg hover:bg-indigo-700 transition">
                            <i class="fas fa-paper-plane mr-2"></i> Confirmar Entrega
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <div class="lg:col-span-1 space-y-6">
        <div class="bg-white p-6 rounded-xl shadow-md">
             <h3 class="text-xl font-bold border-b pb-2 mb-4 text-gray-800">Detalles de la Actividad</h3>
             <div class="flex flex-wrap text-sm text-gray-600 gap-x-4 gap-y-2">
                <span><strong>Puntaje Máx:</strong> <?php echo htmlspecialchars($actividad['puntaje_maximo']); ?></span>
                <span><strong>Límite:</strong> <?php echo $actividad['fecha_entrega'] ? date('d/m/Y', strtotime($actividad['fecha_entrega'])) : 'N/A'; ?></span>
            </div>
            <hr class="my-4">
             <?php if ($actividad['entrega_id']): ?>
                <div class="text-center p-4 bg-blue-50 text-blue-800 rounded-lg">
                    <p class="font-bold text-lg">¡Ya has entregado!</p>
                    <p class="text-sm">Entregado el: <?php echo date('d/m/Y h:i A', strtotime($actividad['fecha_de_entrega'])); ?></p>
                </div>
                 <?php if ($actividad['calificacion'] === null): // Este botón se moverá a la tarjeta de entrega actual. ?>
                    <?php endif; ?>
             <?php else: ?>
                 <div class="text-center p-4 bg-yellow-50 text-yellow-800 rounded-lg">
                    <p class="font-bold text-lg">Pendiente</p>
                    <p class="text-sm">Aún no has realizado tu entrega.</p>
                </div>
             <?php endif; ?>
        </div>
        
        <?php if ($actividad['calificacion'] !== null): ?>
            <div class="bg-white p-6 rounded-xl shadow-md">
                <h3 class="text-xl font-bold border-b pb-2 mb-4 text-green-700">Calificación Recibida</h3>
                <div class="text-center">
                    <p class="text-5xl font-bold text-green-600"><?php echo htmlspecialchars($actividad['calificacion']); ?></p>
                    <p class="text-gray-500">de <?php echo htmlspecialchars($actividad['puntaje_maximo']); ?> puntos</p>
                </div>
            </div>
            <?php if (!empty($actividad['comentario_docente'])): ?>
                <div class="bg-white p-6 rounded-xl shadow-md">
                     <h3 class="text-xl font-bold border-b pb-2 mb-4 flex items-center"><i class="fas fa-comment-dots mr-2 text-sky-500"></i>Retroalimentación</h3>
                     <p class="text-gray-700 whitespace-pre-wrap"><?php echo htmlspecialchars($actividad['comentario_docente']); ?></p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'components/student_footer.php'; ?>

<script>
tinymce.init({
  selector: 'textarea', // Esto inicializa todos los textareas. Si hay varios, usar IDs.
  plugins: 'lists link image table',
  toolbar: 'undo redo | bold italic underline | alignleft aligncenter alignright | bullist numlist | link image table',
  promotion: false,  // Elimina el botón "Get all features"
  language: 'es',
  
   images_upload_handler: function (blobInfo, success, failure) {
    const formData = new FormData();
    formData.append('file', blobInfo.blob(), blobInfo.filename());

    fetch('upload-image.php', {
      method: 'POST',
      body: formData
    })
    .then(function(response) {
      if (!response.ok) {
        throw new Error('Error al subir la imagen.');
      }
      return response.json();
    })
    .then(function(data) {
      if (data.location) {
        success(data.location);
      } else {
        failure('No se recibió URL de la imagen.');
      }
    })
    .catch(function(error) {
      console.error(error);
      failure('Error en la carga de la imagen.');
    });
  }
});

$(document).ready(function() {
    // Referencias a los contenedores
    const entregaActualCard = $('#entrega-actual-card');
    const entregaReadOnlyView = $('#entrega-read-only-view');
    const entregaEditForm = $('#entrega-edit-form');
    const btnEditarEntrega = $('#btn-editar-entrega');
    const btnCancelarEdicion = $('#btn-cancelar-edicion');
    
    // Lógica para el botón "Editar Entrega"
    if (btnEditarEntrega.length) { // Asegurarse de que el botón existe
        btnEditarEntrega.on('click', function() {
            entregaReadOnlyView.slideUp(); // Oculta la vista de solo lectura
            entregaEditForm.slideDown();   // Muestra el formulario de edición
        });
    }

    // Lógica para el botón "Cancelar" dentro del formulario de edición
    if (btnCancelarEdicion.length) { // Asegurarse de que el botón existe
        btnCancelarEdicion.on('click', function() {
            entregaEditForm.slideUp();     // Oculta el formulario de edición
            entregaReadOnlyView.slideDown(); // Muestra la vista de solo lectura
        });
    }

    // Si ya existe una entrega y NO está calificada, por defecto se muestra el botón editar en la tarjeta.
    // El formulario de edición está oculto por defecto en el HTML (hidden class).
    // Solo si no hay entrega, se muestra el formulario inicial.

    // Ajuste para TinyMCE: Si tienes más de un textarea y solo quieres inicializar uno
    // puedes usar un selector más específico, por ejemplo:
    // selector: 'textarea#mytextarea',
    // Si quieres el inicial, también inicialízalo por su ID:
    // tinymce.init({ selector: 'textarea#mytextarea_initial', ... });
});
</script>