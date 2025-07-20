<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado.']);
    exit();
}

require_once 'db_manager.php';

// Validaciones básicas de los datos recibidos
if (empty($_POST['nombre_actividad']) || empty($_POST['puntaje_maximo'])) {
    echo json_encode(['success' => false, 'message' => 'El nombre y el puntaje son obligatorios.']);
    exit();
}

$response = ['success' => false];

try {
    $conn = getDbConnection();
    $sql = "INSERT INTO lms_actividades (id_asignatura, id_docente, parcial, nombre_actividad, descripcion, tipo_actividad, puntaje_maximo, fecha_entrega) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    
    if ($stmt = $conn->prepare($sql)) {
        // Formatear la fecha para MySQL o dejarla NULL si está vacía
        $fecha_entrega = !empty($_POST['fecha_entrega']) ? date('Y-m-d H:i:s', strtotime($_POST['fecha_entrega'])) : null;

        $stmt->bind_param(
            "iisssssd",
            $_POST['id_asignatura'],
            $_POST['id_docente'],
            $_POST['parcial'],
            $_POST['nombre_actividad'],
            $_POST['descripcion'],
            $_POST['tipo_actividad'],
            $_POST['puntaje_maximo'],
            $fecha_entrega
        );
        
        if ($stmt->execute()) {
            $response['success'] = true;
        } else {
            $response['message'] = 'Error al guardar en la base de datos.';
        }
        $stmt->close();
    } else {
        $response['message'] = 'Error al preparar la consulta.';
    }
    $conn->close();
} catch (Exception $e) {
    $response['message'] = 'Error de base de datos: ' . $e->getMessage();
}

echo json_encode($response);