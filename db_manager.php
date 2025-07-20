<?php
include_once 'db_config.php'; // Incluye las credenciales y el nombre de la DB maestra

/**
 * Obtiene una conexión a la base de datos especificada.
 * Si no se especifica un nombre de DB, intentará conectar a la DB del año académico actual/seleccionado.
 * Si se especifica 'master', conecta a la DB maestra.
 *
 * @param string|null $db_identifier Identificador de la DB (ej. 'master', o '2025'). Si es null, usa el año de la sesión.
 * @return mysqli La conexión a la base de datos.
 * @throws Exception Si la conexión a la base de datos falla.
 */
function getDbConnection($db_identifier = null) {
    global $db_host, $db_user, $db_pass, $master_db_name;

    $target_db_name = '';

    if ($db_identifier === 'master') {
        $target_db_name = $master_db_name;
    } elseif ($db_identifier === null) {
        // Año académico predeterminado: de sesión o actual
        $current_academic_year = $_SESSION['current_academic_year'] ?? date('Y');
        $target_db_name = "school_" . $current_academic_year;
    } else {
        // Conexión a una DB de año específica (ej. 'mas_2024')
        $target_db_name = "school_" . $db_identifier;
    }

    $conn = new mysqli($db_host, $db_user, $db_pass, $target_db_name);

    if ($conn->connect_error) {
        throw new Exception("Error de conexión a la base de datos '{$target_db_name}': " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}
?>