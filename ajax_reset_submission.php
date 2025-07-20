<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher' || !isset($_POST['id_entrega'])) {
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado.']);
    exit();
}

require_once 'db_manager.php';
$id_entrega = $_POST['id_entrega'];
$response = ['success' => false];

try {
    $conn = getDbConnection();
    // Esta consulta reinicia la calificación y el comentario, permitiendo una nueva entrega.
    $sql = "UPDATE lms_entregas SET calificacion = NULL, comentario_docente = NULL WHERE id = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $id_entrega);
        if ($stmt->execute()) {
            $response['success'] = true;
        } else {
            $response['message'] = 'Error al reiniciar la entrega.';
        }
        $stmt->close();
    }
    $conn->close();
} catch(Exception $e) {
    $response['message'] = 'Error de base de datos: ' . $e->getMessage();
}

echo json_encode($response);