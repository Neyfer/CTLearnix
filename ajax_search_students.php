<?php
include('mysql.php'); // Tu archivo de conexión a la base de datos

$search_query = isset($_GET['query']) ? mysqli_real_escape_string($conn, $_GET['query']) : '';
$recommendations = [];

if (strlen($search_query) >= 2) { // Solo busca si hay al menos 2 caracteres
    $sql = "SELECT id, name, grade, seccion, modalidad FROM students WHERE name LIKE '%" . $search_query . "%' LIMIT 10";
    $result = mysqli_query($conn, $sql);

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $modality_full = '';
            // Construye el nombre de la modalidad para mostrar en las recomendaciones
            // Puedes usar una versión simplificada de getModalidadDisplayNames si quieres
            if ($row['grade'] >= 10 && ($row['modalidad'] == 3 || $row['modalidad'] == 4)) {
                $modality_full = ($row['modalidad'] == 3 ? 'BCH' : ($row['modalidad'] == 4 ? 'BTPI' : ''));
            }

            $recommendations[] = '<span class="names_r" data-id="' . htmlspecialchars($row['id']) . '" data-name="' . htmlspecialchars($row['name']) . '">' .
                                 htmlspecialchars($row['name']) . 
                                 ' (Grado: ' . htmlspecialchars($row['grade']) . 
                                 ' Secc: ' . htmlspecialchars($row['seccion']) . 
                                 ($modality_full ? ' Modalidad: ' . $modality_full : '') .
                                 ')</span>';
        }
    }
}
echo implode('', $recommendations);
mysqli_close($conn);
?>