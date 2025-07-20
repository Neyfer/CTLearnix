<?php
// upload-image.php
// Backend para la carga de imágenes desde TinyMCE al Google Drive montado

// --- CONFIGURACIÓN ---
// ¡IMPORTANTE! Reemplaza 'main_uploads_drive' con el nombre EXACTO de la cuenta de Google Drive
// a la que quieres subir las imágenes. Esta cuenta debe estar MONTADA y ACCESIBLE vía SYMLINK.
// Ejemplo: si accedes a tu Drive montado 'coto' vía 'https://tudominio.com/coto/', usa 'coto'.
define('DRIVE_UPLOAD_ACCOUNT_NAME', 'main_uploads_drive'); // <-- ¡AJUSTA ESTE VALOR!

// Subcarpeta dentro de esa cuenta montada donde se guardarán las imágenes.
// Por ejemplo: 'images', 'uploads', 'blog_media'.
// El script intentará crear esta carpeta si no existe.
define('DRIVE_UPLOAD_SUBFOLDER', 'images'); 

// URL base de tu sitio web donde se accede al symlink de la cuenta montada.
// Asegúrate de que termine con una barra '/'.
// Ejemplo: si accedes a 'main_uploads_drive' via 'https://tudominio.com/main_uploads_drive/', usa 'https://tudominio.com/'
// Si tu dominio ya es https://neyfercoto.online/ y tu symlink es /var/www/html/main_uploads_drive, entonces usarías https://neyfercoto.online/
define('BASE_WEB_URL', 'https://neyfercoto.online/'); // <-- ¡AJUSTA ESTE VALOR!

// Directorio base de los puntos de montaje de rclone (normalmente /mnt/gdrive)
define('RCLONE_MOUNT_BASE_DIR', '/mnt/gdrive'); 

// ---------------------

header('Content-Type: application/json');

// 1. Verificar si se recibió un archivo
if (!isset($_FILES['file'])) {
    echo json_encode(['error' => 'No se recibió ningún archivo.']);
    exit();
}

$file = $_FILES['file'];

// 2. Validar errores de carga PHP (tamaño, corrupción, etc.)
if ($file['error'] !== UPLOAD_ERR_OK) {
    switch ($file['error']) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            echo json_encode(['error' => 'El archivo es demasiado grande (límites de PHP).']);
            break;
        case UPLOAD_ERR_PARTIAL:
            echo json_encode(['error' => 'La carga del archivo fue parcial.']);
            break;
        case UPLOAD_ERR_NO_FILE:
            echo json_encode(['error' => 'No se seleccionó ningún archivo para subir.']);
            break;
        case UPLOAD_ERR_NO_TMP_DIR:
            echo json_encode(['error' => 'Falta una carpeta temporal en el servidor para cargas.']);
            break;
        case UPLOAD_ERR_CANT_WRITE:
            echo json_encode(['error' => 'Fallo al escribir el archivo en disco (permisos de carpeta temporal).']);
            break;
        case UPLOAD_ERR_EXTENSION:
            echo json_encode(['error' => 'Una extensión de PHP detuvo la carga del archivo.']);
            break;
        default:
            echo json_encode(['error' => 'Error de carga desconocido: ' . $file['error']]);
            break;
    }
    exit();
}

// 3. Definir rutas de destino
$filename = basename($file['name']);
$target_dir_mounted = RCLONE_MOUNT_BASE_DIR . '/' . DRIVE_UPLOAD_ACCOUNT_NAME . '/' . DRIVE_UPLOAD_SUBFOLDER;
$target_file_mounted = $target_dir_mounted . '/' . $filename;

// Construir la URL web final para TinyMCE
$web_url_path = BASE_WEB_URL . DRIVE_UPLOAD_ACCOUNT_NAME . '/' . DRIVE_UPLOAD_SUBFOLDER . '/' . $filename;

// 4. Asegurarse de que el directorio de destino exista en el Google Drive montado
if (!is_dir($target_dir_mounted)) {
    $mkdir_command = "sudo mkdir -p \"{$target_dir_mounted}\" 2>&1";
    exec($mkdir_command, $output, $return_code);
    
    if ($return_code !== 0) {
        echo json_encode(['error' => 'No se pudo crear el directorio de destino en el Google Drive montado: ' . implode(" ", $output)]);
        exit();
    }
    $chown_command = "sudo chown www-data:www-data \"{$target_dir_mounted}\" 2>&1";
    exec($chown_command, $output, $return_code);
    if ($return_code !== 0) { error_log("Falló chown del nuevo directorio de carga: " . implode(" ", $output)); }
}

// 5. Mover el archivo subido desde la ubicación temporal a la carpeta final en el Google Drive montado
if (rename($file['tmp_name'], $target_file_mounted)) {
    $chown_file_command = "sudo chown www-data:www-data \"{$target_file_mounted}\" 2>&1";
    exec($chown_file_command, $output, $return_code);
    if ($return_code !== 0) { error_log("Falló chown del archivo subido: " . implode(" ", $output)); }

    echo json_encode(['location' => $web_url_path]);
} else {
    echo json_encode(['error' => 'Fallo al mover el archivo subido a su ubicación final en Google Drive. Verifique permisos o si el Drive está montado.']);
}

?>