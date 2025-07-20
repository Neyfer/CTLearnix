<?php
// Usamos basename() para obtener el nombre del archivo actual (ej: mis_materias.php)
// Esto nos permite resaltar el enlace de la página en la que estamos.
$current_page = basename($_SERVER['PHP_SELF']);
?>

<aside class="hidden md:flex md:flex-shrink-0">
    <div class="flex flex-col w-64 bg-slate-800 text-slate-300 h-full">
        
        <div class="p-5 border-b border-slate-700">
            <div class="flex items-center justify-center">
                <img src="../img/mono.png" alt="Logo" class="h-20 w-20 rounded-full border-2 border-slate-600">
            </div>
            <h1 class="text-xl font-bold text-white text-center mt-3">Portal Estudiantil</h1>
        </div>

        <nav class="mt-4 flex-1 px-3 space-y-2">
            <a href="index.php" 
               class="flex items-center px-4 py-3 text-base font-medium rounded-lg transition-colors duration-200 group <?php echo ($current_page == 'index.php') ? 'bg-slate-900 text-white' : 'hover:bg-slate-700 hover:text-white'; ?>">
                <i class="fas fa-tachometer-alt w-6 text-center mr-3 text-slate-400 group-hover:text-white transition-colors"></i> Dashboard
            </a>

            <a href="mis_materias.php" 
               class="flex items-center px-4 py-3 text-base font-medium rounded-lg transition-colors duration-200 group <?php echo (in_array($current_page, ['mis_materias.php', 'lms_actividades.php', 'lms_entrega.php'])) ? 'bg-slate-900 text-white' : 'hover:bg-slate-700 hover:text-white'; ?>">
                <i class="fas fa-book w-6 text-center mr-3 text-slate-400 group-hover:text-white transition-colors"></i> Mis Materias
            </a>
            
             <a href="lms_tareas.php" 
               class="flex items-center px-4 py-3 text-base font-medium rounded-lg transition-colors duration-200 group <?php echo ($current_page == 'lms_tareas.php') ? 'bg-slate-900 text-white' : 'hover:bg-slate-700 hover:text-white'; ?>">
                <i class="fas fa-list-check w-6 text-center mr-3 text-slate-400 group-hover:text-white transition-colors"></i> Tareas Pendientes
            </a>

            <a href="notas.php" 
               class="flex items-center px-4 py-3 text-base font-medium rounded-lg transition-colors duration-200 group <?php echo ($current_page == 'notas.php') ? 'bg-slate-900 text-white' : 'hover:bg-slate-700 hover:text-white'; ?>">
                <i class="fas fa-clipboard-check w-6 text-center mr-3 text-slate-400 group-hover:text-white transition-colors"></i> Calificaciones
            </a>

            <a href="perfil.php" 
               class="flex items-center px-4 py-3 text-base font-medium rounded-lg transition-colors duration-200 group <?php echo ($current_page == 'perfil.php') ? 'bg-slate-900 text-white' : 'hover:bg-slate-700 hover:text-white'; ?>">
                <i class="fas fa-user-circle w-6 text-center mr-3 text-slate-400 group-hover:text-white transition-colors"></i> Mi Perfil
            </a>
        </nav>
        
        <div class="p-4 border-t border-slate-700">
             <a href="logout.php" class="flex items-center w-full px-4 py-3 text-base font-medium rounded-lg text-slate-300 hover:bg-red-500 hover:text-white transition-colors group">
                <i class="fas fa-sign-out-alt w-6 text-center mr-3 text-slate-400 group-hover:text-white"></i>
                Cerrar Sesión
            </a>
        </div>

    </div>
</aside>

<div id="mobile-sidebar" class="fixed inset-0 flex z-40 hidden">
    <div class="fixed inset-0 bg-gray-600 bg-opacity-75" id="mobile-backdrop"></div>
    
    <div class="relative flex-1 flex flex-col max-w-xs w-full bg-slate-800 text-slate-300">
        <div class="absolute top-0 right-0 -mr-12 pt-2">
            <button type="button" id="close-sidebar-button" class="ml-1 flex items-center justify-center h-10 w-10 rounded-full focus:outline-none focus:ring-2 focus:ring-inset focus:ring-white">
                <i class="fas fa-times text-white"></i>
            </button>
        </div>
        <div class="p-5 border-b border-slate-700">
            <div class="flex items-center justify-center"><img src="../img/mono.png" alt="Logo" class="h-20 w-20 rounded-full border-2 border-slate-600"></div>
            <h1 class="text-xl font-bold text-white text-center mt-3">Portal Estudiantil</h1>
        </div>
        <nav class="mt-4 flex-1 px-3 space-y-2">
             <a href="index.php" class="flex items-center px-4 py-3 text-base font-medium rounded-lg transition-colors duration-200 group <?php echo ($current_page == 'index.php') ? 'bg-slate-900 text-white' : 'hover:bg-slate-700 hover:text-white'; ?>">
                <i class="fas fa-tachometer-alt w-6 text-center mr-3 text-slate-400 group-hover:text-white transition-colors"></i> Dashboard
             </a>
             <a href="mis_materias.php" class="flex items-center px-4 py-3 text-base font-medium rounded-lg transition-colors duration-200 group <?php echo (in_array($current_page, ['mis_materias.php', 'lms_actividades.php', 'lms_entrega.php'])) ? 'bg-slate-900 text-white' : 'hover:bg-slate-700 hover:text-white'; ?>">
                <i class="fas fa-book w-6 text-center mr-3 text-slate-400 group-hover:text-white transition-colors"></i> Mis Materias
             </a>
             <a href="lms_tareas.php" class="flex items-center px-4 py-3 text-base font-medium rounded-lg transition-colors duration-200 group <?php echo ($current_page == 'lms_tareas.php') ? 'bg-slate-900 text-white' : 'hover:bg-slate-700 hover:text-white'; ?>">
                <i class="fas fa-list-check w-6 text-center mr-3 text-slate-400 group-hover:text-white transition-colors"></i> Tareas Pendientes
             </a>
             <a href="notas.php" class="flex items-center px-4 py-3 text-base font-medium rounded-lg transition-colors duration-200 group <?php echo ($current_page == 'notas.php') ? 'bg-slate-900 text-white' : 'hover:bg-slate-700 hover:text-white'; ?>">
                <i class="fas fa-clipboard-check w-6 text-center mr-3 text-slate-400 group-hover:text-white transition-colors"></i> Calificaciones
             </a>
             <a href="perfil.php" class="flex items-center px-4 py-3 text-base font-medium rounded-lg transition-colors duration-200 group <?php echo ($current_page == 'perfil.php') ? 'bg-slate-900 text-white' : 'hover:bg-slate-700 hover:text-white'; ?>">
                <i class="fas fa-user-circle w-6 text-center mr-3 text-slate-400 group-hover:text-white transition-colors"></i> Mi Perfil
             </a>
        </nav>
        <div class="p-4 border-t border-slate-700">
            <a href="logout.php" class="flex items-center w-full px-4 py-3 text-base font-medium rounded-lg text-slate-300 hover:bg-red-500 hover:text-white transition-colors group">
                <i class="fas fa-sign-out-alt w-6 text-center mr-3 text-slate-400 group-hover:text-white"></i>
                Cerrar Sesión
            </a>
        </div>
    </div>
    <div class="flex-shrink-0 w-14"></div>
</div>