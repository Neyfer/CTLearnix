<?php
// Incluir el manager de conexión
include_once 'db_manager.php'; 

// Obtener la conexión predeterminada para el año académico actual/seleccionado
try {
    $conn = getDbConnection(); 
} catch (Exception $e) {
    // Manejar el error de conexión si getDbConnection lanza una excepción
    die("<h1>Problema de Conexión a la Base de Datos</h1><p>" . $e->getMessage() . "</p><p>Por favor, contacta al administrador del sistema.</p>");
}
?>