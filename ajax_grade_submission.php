<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher' || !isset($_POST['id_entrega'])) {
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado.']);
    exit();
}

require_once 'db_manager.php';

$id_entrega = $_POST['id_entrega'];
$calificacion = $_POST['calificacion'];
$comentario = $_POST['comentario_docente']; // Nuevo campo
$response = ['success' => false];

if (!is_numeric($calificacion) || $calificacion < 0) {
    $response['message'] = 'La calificación debe ser un número válido.';
    echo json_encode($response);
    exit();
}

try {
    $conn = getDbConnection();
    // CONSULTA ACTUALIZADA: Ahora incluye el campo `comentario_docente`
    $sql = "UPDATE lms_entregas SET calificacion = ?, comentario_docente = ? WHERE id = ?";
    if ($stmt = $conn->prepare($sql)) {
        // BIND_PARAM ACTUALIZADO: 'd' para la nota (double), 's' para el comentario (string), 'i' para el id (integer)
        $stmt->bind_param("dsi", $calificacion, $comentario, $id_entrega);
        if ($stmt->execute()) {
            $response['success'] = true;
        } else {
            $response['message'] = 'Error al actualizar la calificación.';
        }
        $stmt->close();
    }
    $conn->close();
} catch(Exception $e) {
    $response['message'] = 'Error de base de datos: ' . $e->getMessage();
}

echo json_encode($response);