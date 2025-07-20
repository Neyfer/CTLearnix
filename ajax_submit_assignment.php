<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['student_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado.']);
    exit();
}

require_once 'db_manager.php';

$id_actividad = $_POST['id_actividad'];
$id_estudiante = $_POST['id_estudiante'];
$texto_respuesta = $_POST['texto_respuesta'];
$ruta_archivo_relativa = null;

$response = ['success' => false];

// --- Manejo de la subida de archivo ---
if (isset($_FILES['archivo_entrega']) && $_FILES['archivo_entrega']['error'] == 0) {
    $upload_dir_absoluta = __DIR__ . '/../uploads/entregas/';
    if (!is_dir($upload_dir_absoluta)) {
        mkdir($upload_dir_absoluta, 0777, true);
    }
    
    $file_ext = strtolower(pathinfo($_FILES['archivo_entrega']['name'], PATHINFO_EXTENSION));
    $new_filename = "entrega_" . $id_actividad . "_est_" . $id_estudiante . "_" . time() . "." . $file_ext;
    $upload_file_absoluto = $upload_dir_absoluta . $new_filename;
    
    $ruta_archivo_relativa = '../uploads/entregas/' . $new_filename;

    if (!move_uploaded_file($_FILES['archivo_entrega']['tmp_name'], $upload_file_absoluto)) {
        $response['message'] = 'Error al guardar el archivo subido.';
        echo json_encode($response);
        exit();
    }
}

if (empty($ruta_archivo_relativa) && empty(trim($texto_respuesta))) {
    $response['message'] = 'Debes subir un archivo o escribir una respuesta para entregar.';
    echo json_encode($response);
    exit();
}

try {
    $conn = getDbConnection();
    // 1. VERIFICAR SI YA EXISTE UNA ENTREGA PARA ESTA TAREA
    $sql_check = "SELECT id, ruta_archivo FROM lms_entregas WHERE id_actividad = ? AND id_estudiante = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("ii", $id_actividad, $id_estudiante);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $entrega_existente = $result_check->fetch_assoc();
    $stmt_check->close();

    if ($entrega_existente) {
        // SI EXISTE, HACEMOS UN UPDATE
        $id_entrega = $entrega_existente['id'];
        
        // Si se sube un nuevo archivo, actualizamos la ruta y borramos el viejo.
        if ($ruta_archivo_relativa) {
             // Borrar archivo anterior si existe
            if ($entrega_existente['ruta_archivo'] && file_exists(__DIR__ . '/'.$entrega_existente['ruta_archivo'])) {
                unlink(__DIR__ . '/'.$entrega_existente['ruta_archivo']);
            }
             $sql = "UPDATE lms_entregas SET texto_respuesta = ?, ruta_archivo = ?, fecha_entrega = NOW(), calificacion = NULL, comentario_docente = NULL WHERE id = ?";
             $stmt = $conn->prepare($sql);
             $stmt->bind_param("ssi", $texto_respuesta, $ruta_archivo_relativa, $id_entrega);
        } else {
            // Si no se sube archivo nuevo, solo actualizamos el texto
            $sql = "UPDATE lms_entregas SET texto_respuesta = ?, fecha_entrega = NOW(), calificacion = NULL, comentario_docente = NULL WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $texto_respuesta, $id_entrega);
        }

    } else {
        // SI NO EXISTE, HACEMOS UN INSERT (como antes)
        $sql = "INSERT INTO lms_entregas (id_actividad, id_estudiante, texto_respuesta, ruta_archivo, fecha_entrega) VALUES (?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiss", $id_actividad, $id_estudiante, $texto_respuesta, $ruta_archivo_relativa);
    }

    if ($stmt->execute()) {
        $response['success'] = true;
    } else {
        $response['message'] = 'Error al procesar la entrega en la base de datos.';
    }
    $stmt->close();
    $conn->close();

} catch(Exception $e) {
    $response['message'] = 'Error de base de datos: ' . $e->getMessage();
}

echo json_encode($response);