<?php
session_start();
// Enable error reporting for debugging. IMPORTANT: REMOVE THESE LINES IN PRODUCTION.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include('mysql.php'); // Your database connection file

$login_error = ''; // Initialize error message

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanitize user input to prevent SQL injection
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password']; // Password from form, will be verified against hash

    // Attempt to authenticate as a Student from the student_users table
    $query_student = mysqli_query($conn, "SELECT student_id, username, password_hash, first_login FROM student_users WHERE username = '$username'");

    // Check if the query was successful and if a user was found
    if ($query_student && mysqli_num_rows($query_student) > 0) {
        $student_user_row = mysqli_fetch_assoc($query_student);

        // --- CORE HASH VERIFICATION ---
        // password_verify() is the correct and secure way to check a plaintext password against a bcrypt hash.
        if (password_verify($password, $student_user_row['password_hash'])) {
            // Authentication successful! Set session variables.
            $_SESSION['username'] = $student_user_row['username']; // Store their username (ident)
            $_SESSION['student_id'] = $student_user_row['student_id']; // Crucial: Store the actual student_id
            $_SESSION['role'] = 'student'; // Explicitly set role for student

            // Check if it's the first login to force password change
            if ($student_user_row['first_login'] == 1) {
                header('Location: change_password.php'); // Redirect to mandatory password change page
                exit;
            } else {
                header('Location: index.php'); // Redirect to student's personalized profile page
                exit;
            }
        } else {
            // Password did not match the hash
            $login_error = "Usuario o contraseña incorrectos.";
        }
    } else {
        // Username not found in student_users table
        $login_error = "Usuario o contraseña incorrectos.";
    }

    // Close the database connection in this script after attempting login
    mysqli_close($conn);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión (Alumnos)</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background-color: #f0f2f5; /* Light gray background */
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .login-container {
            background-color: #ffffff;
            padding: 2.5rem; /* p-10 */
            border-radius: 1rem; /* rounded-xl */
            box-shadow: 0 10px 25px rgba(0,0,0,0.1); /* shadow-lg */
            width: 100%;
            max-width: 450px; /* Max width for larger forms */
            text-align: center;
            border: 1px solid #e2e8f0; /* border-gray-200 */
        }
        .login-container h2 {
            font-size: 2rem; /* text-3xl */
            font-weight: 700; /* font-bold */
            color: #1a202c; /* text-gray-900 */
            margin-bottom: 1.5rem; /* mb-6 */
        }
        .form-label {
            display: block; /* block */
            font-weight: 600; /* font-semibold */
            color: #4a5568; /* text-gray-700 */
            text-align: left;
            margin-bottom: 0.5rem; /* mb-2 */
            font-size: 0.875rem; /* text-sm */
        }
        .form-input {
            width: 100%; /* w-full */
            padding: 0.75rem 1rem; /* py-3 px-4 */
            border: 1px solid #cbd5e0; /* border-gray-300 */
            border-radius: 0.5rem; /* rounded-lg */
            background-color: #f7fafc; /* bg-gray-50 */
            color: #2d3748; /* text-gray-800 */
            font-size: 1rem; /* text-base */
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .form-input:focus {
            outline: none;
            border-color: #667eea; /* indigo-400 */
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2); /* ring-3 ring-indigo-200 */
        }
        .btn-submit {
            width: 100%; /* w-full */
            padding: 0.75rem 1.5rem; /* py-3 px-6 */
            background-color: #6366f1; /* indigo-500 */
            color: white;
            font-weight: 700; /* font-bold */
            border-radius: 0.5rem; /* rounded-lg */
            transition: background-color 0.2s ease;
            margin-top: 1.5rem; /* mt-6 */
        }
        .btn-submit:hover {
            background-color: #554edb; /* indigo-600 */
        }
        .alert-message {
            margin-top: 1.5rem; /* mt-6 */
            padding: 1rem 1.5rem; /* p-4 px-6 */
            border-radius: 0.5rem; /* rounded-lg */
            background-color: #fee2e2; /* red-100 */
            color: #ef4444; /* red-600 */
            border: 1px solid #fca5a5; /* border-red-300 */
            font-size: 0.875rem; /* text-sm */
        }
    </style>
</head>
<body>
    <div class="login-container">
      <img src="../img/mono.png" class="flex items-center justify-center" id="icon" alt="LRLC" style="width:20%; margin:auto;" />
        <h2 class="flex items-center justify-center">
            <i class="fas fa-user-graduate text-indigo-600 mr-3"></i> Acceso Alumnos
        </h2>
        <?php if (!empty($login_error)): ?>
            <div class="alert-message" role="alert">
                <?php echo $login_error; ?>
            </div>
        <?php endif; ?>
        <form action="student_login.php" method="post" class="mt-6">
            <div class="mb-4">
                <label for="username" class="form-label">Número de Identidad</label>
                <input type="text" class="form-input" id="username" name="username" required autocomplete="username">
            </div>
            <div class="mb-6">
                <label for="password" class="form-label">Contraseña</label>
                <input type="password" class="form-input" id="password" name="password" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn-submit">
                <i class="fas fa-sign-in-alt mr-2"></i> Entrar al Portal
            </button>
        </form>
    </div>
</body>
</html>