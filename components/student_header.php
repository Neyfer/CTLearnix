<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Portal del Estudiante'; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <style> @import url('https://rsms.me/inter/inter.css'); html { font-family: 'Inter', sans-serif; } </style>
  	<script src="../tinymce/tinymce.min.js"></script>
  <script src="/tinymce/langs/es.js"></script>

</head>
<body class="bg-gray-100">
    <div class="flex h-screen bg-gray-100">
        
        <?php
            // Este archivo ahora contiene tanto la barra lateral de escritorio
            // como la barra lateral oculta para móvil.
            include 'student_aside.php';
        ?>

        <div class="flex flex-col w-0 flex-1 overflow-hidden">
            
            <?php
                // Esta es la barra superior que solo aparece en móvil y contiene el botón de menú.
                include 'student_navbar.php';
            ?>

            <main class="flex-1 relative z-0 overflow-y-auto focus:outline-none p-4 md:p-8">