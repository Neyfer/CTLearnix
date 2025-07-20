<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher' || !isset($_POST['id_asignatura'])) {
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado.']);
    exit();
}

require_once 'db_manager.php';

$response = ['success' => false, 'actividades' => []];
$id_asignatura = $_POST['id_asignatura'];

try {
    $conn = getDbConnection();
    $sql = "SELECT id, nombre_actividad, parcial, tipo_actividad, puntaje_maximo FROM lms_actividades WHERE id_asignatura = ? ORDER BY fecha_publicacion DESC";
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $id_asignatura);
        $stmt->execute();
        $result = $stmt->get_result();
        $response['actividades'] = $result->fetch_all(MYSQLI_ASSOC);
        $response['success'] = true;
        $stmt->close();
    }
    $conn->close();
} catch (Exception $e) {
    $response['message'] = 'Error de base de datos: ' . $e->getMessage();
}

echo json_encode($response);