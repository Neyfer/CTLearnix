<?php
session_start();
header('Content-Type: application/json');

// Seguridad: Solo un estudiante logueado que envía un ID de materia puede usar esto.
if (!isset($_SESSION['student_id']) || !isset($_POST['subject_id'])) {
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado o faltan datos.']);
    exit();
}

require_once 'db_manager.php';

$student_id = $_SESSION['student_id'];
$subject_id = $_POST['subject_id'];
$response = ['success' => false, 'actividades' => []];

try {
    $conn = getDbConnection();
    // Esta consulta trae todas las actividades (pendientes, entregadas, calificadas) de UNA SOLA materia
    // y las ordena por fecha de publicación, la más nueva primero.
    $sql = "
        SELECT 
            asi.subject, act.id as actividad_id, act.nombre_actividad, act.descripcion, act.tipo_actividad,
            act.puntaje_maximo, act.fecha_entrega, ent.id as entrega_id, ent.calificacion, ent.comentario_docente,
            ent.ruta_archivo, ent.texto_respuesta
        FROM asignaturas asi
        JOIN lms_actividades act ON act.id_asignatura = asi.id
        LEFT JOIN lms_entregas ent ON ent.id_actividad = act.id AND ent.id_estudiante = ?
        WHERE act.id_asignatura = ?
        ORDER BY act.fecha_publicacion DESC
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $student_id, $subject_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $response['actividades'] = $result->fetch_all(MYSQLI_ASSOC);
    $response['success'] = true;
    $stmt->close();
    $conn->close();

} catch(Exception $e) {
    $response['message'] = 'Error de base de datos: ' . $e->getMessage();
}

echo json_encode($response);