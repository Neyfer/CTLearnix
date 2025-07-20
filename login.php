<?php
session_start();
$loginError = '';

if(isset($_POST['submit'])) {
    $username_input = $_POST['login'];
    $password_input = $_POST['password'];

    // Incluir el manager de conexión (que a su vez incluye db_config.php)
    include_once 'db_manager.php'; 

    $master_conn = null; // Inicializar a null
    try {
        // Conectar a la base de datos maestra para la autenticación
        $master_conn = getDbConnection('master'); 
    } catch (Exception $e) {
        // Si no se puede conectar a la DB maestra, es un error crítico
        $loginError = "Error de conexión al sistema. Por favor, intente más tarde.";
        error_log("Login DB Master Connection Error: " . $e->getMessage()); // Loguea el error en el servidor
    }

    if ($master_conn) {
        // Usar sentencias preparadas para prevenir inyecciones SQL
        $stmt = $master_conn->prepare("SELECT id, username, password_hash, role FROM users WHERE username = ?");
        
        if ($stmt) {
            $stmt->bind_param("s", $username_input);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows == 1) {
                $row = $result->fetch_assoc();
                $hashed_password_from_db = $row['password_hash'];

                // Verificar la contraseña ingresada con el hash almacenado
                if (password_verify($password_input, $hashed_password_from_db)) {
                    // Autenticación exitosa
                    $_SESSION['username'] = $row['username'];
                    $_SESSION['role'] = $row['role'];
                  	$role = $row['role'];
                  	$fecha = date("Y-m-d H:i:s");
                    $_SESSION['user_id'] = $row['id']; // Almacenar el ID del usuario global
                  
                  	$stmt = $master_conn->prepare("INSERT INTO logins (`user`, `role`, `hora`) VALUES (?, ?, ?)");
                    $stmt->bind_param("sss", $username_input, $role, $fecha);
                    $stmt->execute();
                    $stmt->close();

                    // Redirigir según el rol
                    if ($row['role'] == 'admin') {
                        header('Location: index.php'); // Redirige al dashboard del admin
                    } else if ($row['role'] == 'teacher') {
                        header('Location: gestion_lms.php'); // Redirige a la página de maestros
                    } else {
                        // Si hay otros roles, podrías tener más lógica aquí
                        $loginError = "Rol de usuario no reconocido o no autorizado.";
                    }
                    exit; // Termina la ejecución del script después de la redirección
                } else {
                    $loginError = "Usuario o contraseña incorrectos.";
                }
            } else {
                $loginError = "Usuario o contraseña incorrectos.";
            }
            $stmt->close();
        } else {
            $loginError = "Error interno del sistema. Por favor, intente más tarde.";
            error_log("Login Prepare Statement Error: " . $master_conn->error);
        }
        $master_conn->close(); // Cerrar la conexión a la DB maestra
    }
}
?>

<!DOCTYPE html>
<html lang="es"> <head>
<meta charset="UTF-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login</title>
<script src="../js/jquery.js"></script>
<link href="../bootstrap/bootstrap.min.css" rel="stylesheet">
<script src="../bootstrap/bootstrap.bundle.js"></script>
<link href="../css/login.css" rel="stylesheet">
</head>
<body>
<div class="wrapper fadeInDown">
  <div id="formContent">
    <br>
    <div class="fadeIn first">
      <img src="../img/mono.png" id="icon" alt="CEMGT LRLC" /><br> <h4 class="card-title" style="color: #000; font-family:sans-serif; font-type:cursive;"></h4>
    </div><br>

    <form action="login.php" method="POST">
      <input type="text" id="login" class="fadeIn fields second" name="login" placeholder="Usuario" required><br> <input type="password" id="password" class="fadeIn fields third" name="password" placeholder="Contraseña" required><br><br> <?php if (!empty($loginError)): ?>
        <div class="alert alert-danger" role="alert" style="font-size:0.9em; padding: 0.75rem;">
          <?php echo $loginError; ?>
        </div>
      <?php endif; ?>
      <input type="submit" name="submit" class="fadeIn fourth" value="Entrar">
    </form>

    <div id="formFooter">
      </div>

  </div>
</div>
<footer style="text-align:center;">Copyright &copy 2023 by Neyfer Coto - All Rights Reserved</footer>
</body>
</html>