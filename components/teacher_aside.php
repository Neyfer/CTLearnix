<?php
// Usamos basename() para obtener el nombre del archivo actual (ej: teacher_dashboard.php)
$current_page = basename($_SERVER['PHP_SELF']);
// Obtener el parámetro 'view' de la URL para determinar la sección activa
$current_view = $_GET['view'] ?? 'lms_activities'; // Default to 'lms_activities'
?>

<aside class="hidden md:flex md:flex-shrink-0">
    <div class="flex flex-col w-64 bg-gray-800 text-gray-300 h-full">
        
        <div class="p-5 border-b border-gray-700">
            <div class="flex items-center justify-center">
                <img src="../img/mono.png" alt="Logo" class="h-20 w-20 rounded-full border-2 border-gray-600">
            </div>
            <h1 class="text-xl font-bold text-white text-center mt-3">Portal Docente</h1>
        </div>

        <nav class="mt-4 flex-1 px-3 space-y-2">
            <a href="teacher_dashboard.php?view=lms_activities"
               class="flex items-center px-4 py-3 text-base font-medium rounded-lg transition-colors duration-200 group <?php echo ($current_page == 'teacher_dashboard.php' && $current_view === 'lms_activities') ? 'bg-gray-900 text-white' : 'hover:bg-gray-700 hover:text-white'; ?>">
                <i class="fas fa-tasks w-6 text-center mr-3 text-gray-400 group-hover:text-white transition-colors"></i> Gestión LMS
            </a>

            <a href="teacher_dashboard.php?view=notes_management"
               class="flex items-center px-4 py-3 text-base font-medium rounded-lg transition-colors duration-200 group <?php echo ($current_page == 'teacher_dashboard.php' && $current_view === 'notes_management') ? 'bg-gray-900 text-white' : 'hover:bg-gray-700 hover:text-white'; ?>">
                <i class="fas fa-clipboard-check w-6 text-center mr-3 text-gray-400 group-hover:text-white transition-colors"></i> Gestión de Notas
            </a>

            </nav>
        
        <div class="p-4 border-t border-gray-700">
             <a href="logout.php" class="flex items-center w-full px-4 py-3 text-base font-medium rounded-lg text-gray-300 hover:bg-red-500 hover:text-white transition-colors group">
                <i class="fas fa-sign-out-alt w-6 text-center mr-3 text-gray-400 group-hover:text-white"></i>
                Cerrar Sesión
            </a>
        </div>

    </div>
</aside>