<?php
session_start();
// Enable error reporting for debugging. IMPORTANT: REMOVE THESE LINES IN PRODUCTION.


include('mysql.php'); // Tu archivo de conexión a la base de datos

// Asegurarse de que $conn esté definido y sea un objeto mysqli válido
if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Error: No se pudo establecer la conexión a la base de datos. Verifica 'mysql.php'.");
}

// Ensure only logged-in students with a 'student' role can access this page.
// And that they have a student_id in their session.


$student_id = $_SESSION['student_id'];
$message = ''; // Mensaje para mostrar al usuario

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Utilizar sentencias preparadas para mayor seguridad
    $query_current_pass_stmt = mysqli_prepare($conn, "SELECT password_hash, first_login FROM student_users WHERE student_id = ?");
    
    if ($query_current_pass_stmt) {
        mysqli_stmt_bind_param($query_current_pass_stmt, "i", $student_id);
        mysqli_stmt_execute($query_current_pass_stmt);
        $query_result = mysqli_stmt_get_result($query_current_pass_stmt);

        if (!$query_result || mysqli_num_rows($query_result) == 0) {
            $message = '<div class="alert-message bg-red-100 text-red-600">Error: No se encontró la cuenta de usuario.</div>';
        } else {
            $current_pass_row = mysqli_fetch_assoc($query_result);
            $stored_hash = $current_pass_row['password_hash'];
            $is_first_login = $current_pass_row['first_login'];

            // 1. Verificar la contraseña actual
            if (!password_verify($current_password, $stored_hash)) {
                $message = '<div class="alert-message bg-red-100 text-red-600">La contraseña actual es incorrecta.</div>';
            }
            // 2. Verificar si la nueva contraseña coincide con la confirmación
            elseif ($new_password !== $confirm_password) {
                $message = '<div class="alert-message bg-red-100 text-red-600">La nueva contraseña y la confirmación no coinciden.</div>';
            }
            // 3. Imponer longitud mínima de la contraseña
            elseif (strlen($new_password) < 8) { // Longitud mínima recomendada para seguridad
                $message = '<div class="alert-message bg-red-100 text-red-600">La nueva contraseña debe tener al menos 8 caracteres.</div>';
            }
            // 4. Opcional: Evitar cambiar a la misma contraseña (buena práctica)
            elseif (password_verify($new_password, $stored_hash)) {
                $message = '<div class="alert-message bg-red-100 text-red-600">La nueva contraseña no puede ser igual a la actual.</div>';
            }
            else {
                // Todas las verificaciones pasaron. Hashear la nueva contraseña y actualizar la base de datos.
                $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                // Actualizar password_hash y establecer first_login a 0
                $update_password_stmt = mysqli_prepare($conn, "UPDATE student_users SET password_hash = ?, first_login = 0 WHERE student_id = ?");
                
                if ($update_password_stmt) {
                    mysqli_stmt_bind_param($update_password_stmt, "si", $new_hashed_password, $student_id);
                    if (mysqli_stmt_execute($update_password_stmt)) {
                        $message = '<div class="alert-message bg-green-100 text-green-600">¡Contraseña actualizada con éxito!</div>';
                        
                        // Si la contraseña se actualizó con éxito, redirigir al perfil del estudiante.
                        // Usamos un echo de JavaScript y exit para asegurar la redirección después de que el mensaje pueda ser visto.
                        echo "<script>
                            setTimeout(function() {
                                window.location.href = 'index.php';
                            }, 1500); // Retraso de 1.5 segundos
                        </script>";
                        exit; // Detener la ejecución del script PHP
                    } else {
                        $message = '<div class="alert-message bg-red-100 text-red-600">Error al actualizar la contraseña en la base de datos: ' . mysqli_error($conn) . '</div>';
                    }
                    mysqli_stmt_close($update_password_stmt);
                } else {
                    $message = '<div class="alert-message bg-red-100 text-red-600">Error al preparar la consulta de actualización: ' . mysqli_error($conn) . '</div>';
                }
            }
        }
        mysqli_stmt_close($query_current_pass_stmt); // Cerrar el primer statement
    } else {
        $message = '<div class="alert-message bg-red-100 text-red-600">Error al preparar la consulta de obtención de contraseña: ' . mysqli_error($conn) . '</div>';
    }
}

// *** ¡CERRAR LA CONEXIÓN A LA BASE DE DATOS AQUÍ, UNA SOLA VEZ, AL FINAL DEL SCRIPT! ***
if (isset($conn) && $conn instanceof mysqli && !mysqli_connect_errno()) {
    mysqli_close($conn);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cambiar Contraseña</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background-color: #f0f2f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .card {
            background-color: #ffffff;
            padding: 2.5rem;
            border-radius: 1rem;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 450px;
            text-align: center;
            border: 1px solid #e2e8f0;
        }
        .card-header {
            background-color: #6366f1; /* indigo-500 */
            color: white;
            padding: 1.5rem;
            border-top-left-radius: 0.75rem;
            border-top-right-radius: 0.75rem;
            text-align: center;
            font-size: 1.5rem;
            font-weight: 700;
            margin: -2.5rem -2.5rem 1.5rem -2.5rem; /* Negative margin to pull it out */
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .form-label {
            display: block;
            font-weight: 600;
            color: #4a5568;
            text-align: left;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }
        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #cbd5e0;
            border-radius: 0.5rem;
            background-color: #f7fafc;
            color: #2d3748;
            font-size: 1rem;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .form-input:focus {
            outline: none;
            border-color: #667eea; /* indigo-400 */
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
        }
        .btn-submit {
            width: 100%;
            padding: 0.75rem 1.5rem;
            background-color: #6366f1; /* indigo-500 */
            color: white;
            font-weight: 700;
            border-radius: 0.5rem;
            transition: background-color 0.2s ease;
            margin-top: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .btn-submit:hover {
            background-color: #554edb; /* indigo-600 */
        }
        .alert-message {
            margin-bottom: 1.5rem; /* mb-6 */
            padding: 1rem 1.5rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            text-align: left;
            border: 1px solid;
            color: #333;
        }
        .alert-message.bg-red-100 { background-color: #fee2e2; border-color: #fca5a5; }
        .alert-message.text-red-600 { color: #ef4444; }
        .alert-message.bg-green-100 { background-color: #d1fae5; border-color: #a7f3d0; }
        .alert-message.text-green-600 { color: #059669; }
    </style>
</head>
<body>
    <div class="card">
        <div class="card-header">
            <img src="../img/mono.png" class="flex items-center justify-center" id="icon" alt="LRLC" style="width:10%;" /><span>  Cambiar Contraseña</span>
        </div>
        <div class="card-body">
          
            <?php if (!empty($message)): ?>
                <?php echo $message; ?>
            <?php endif; ?>
            <p class="text-gray-600 text-sm mb-6">
                Es su primer inicio de sesión o se ha solicitado un cambio de contraseña.
                Por favor, establezca una nueva contraseña.
            </p>
            <form action="change_password.php" method="post">
                <div class="mb-4">
                    <label for="current_password" class="form-label">Contraseña Actual (su número de identidad)</label>
                    <input type="password" class="form-input" id="current_password" name="current_password" required autocomplete="current-password">
                </div>
                <div class="mb-4">
                    <label for="new_password" class="form-label">Nueva Contraseña</label>
                    <input type="password" class="form-input" id="new_password" name="new_password" required autocomplete="new-password">
                </div>
                <div class="mb-6">
                    <label for="confirm_password" class="form-label">Confirmar Nueva Contraseña</label>
                    <input type="password" class="form-input" id="confirm_password" name="confirm_password" required autocomplete="new-password">
                </div>
                <button type="submit" class="btn-submit">
                    <i class="fas fa-key mr-2"></i> Actualizar Contraseña
                </button>
            </form>
        </div>
    </div>
</body>
</html>