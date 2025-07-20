<?php
session_start();

// Redirect if not logged in or not a teacher
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'teacher') {
    header('Location: login.php');
    exit();
}

// Determine the current view (lms_activities or notes_management)
$current_view = $_GET['view'] ?? 'lms_activities'; // Default to LMS activities

// Set current academic year if not already set or changed via GET
if (isset($_GET['year']) && is_numeric($_GET['year'])) {
    $_SESSION['current_academic_year'] = (int)$_GET['year'];
}
if (!isset($_SESSION['current_academic_year'])) {
    $_SESSION['current_academic_year'] = date('Y'); // Default to current year
}

require_once 'db_manager.php'; // Ensure this path is correct

$conn = null;
$master_conn_local = null; // Needed for academic years

try {
    $conn = getDbConnection(); // Connects to the current academic year's DB
    $master_conn_local = getDbConnection('master'); // Connects to the master DB
} catch (Exception $e) {
    // Handle critical DB connection error
    die("Error de conexión a la base de datos: " . $e->getMessage());
}

$teacher_name = $_SESSION['username'];
$teacher_id = $_SESSION['user_id'];
$asignaturas = [];

// Fetch subjects for the teacher (from gestion_lms.php)
$sql_asignaturas = "SELECT id, subject, grade FROM asignaturas WHERE teacher = ?";
if ($stmt_asignaturas = $conn->prepare($sql_asignaturas)) {
    $stmt_asignaturas->bind_param("s", $teacher_name);
    $stmt_asignaturas->execute();
    $result_asignaturas = $stmt_asignaturas->get_result();
    $asignaturas = $result_asignaturas->fetch_all(MYSQLI_ASSOC);
    $stmt_asignaturas->close();
} else {
    error_log("Error preparing asignaturas query: " . $conn->error);
}


// Fetch available academic years (from your header logic)
$available_years = [];
$years_query = mysqli_query($master_conn_local, "SELECT year, display_name FROM academic_years WHERE is_active = 1 ORDER BY year DESC");
if ($years_query) {
    while ($row = mysqli_fetch_assoc($years_query)) {
        $available_years[] = $row;
    }
} else {
    error_log("Error fetching academic years: " . mysqli_error($master_conn_local));
    $available_years[] = ['year' => date('Y'), 'display_name' => 'Error de conexión']; // Fallback
}


// Close master connection as it's not needed for the rest of the page logic
if ($master_conn_local && !mysqli_connect_errno()) {
    mysqli_close($master_conn_local);
}

// Close the main academic year DB connection at the end of PHP script if no more queries needed.
// Or keep it open if later AJAX functions might implicitly use it.
// For this merged page, we will assume AJAX calls will establish their own connections via db_manager.
if ($conn && !mysqli_connect_errno()) {
    mysqli_close($conn);
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Docente - Sistema MAS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        @import url('https://rsms.me/inter/inter.css');
        html { font-family: 'Inter', sans-serif; }
        .form-select {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 0.5rem center;
            background-repeat: no-repeat;
            background-size: 1.5em 1.5em;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
        }
        /* Custom styles for the combined header */
        .top-header {
            background-color: #1a202c; /* Tailwind's gray-900 / dark-blue for header */
            height: 3.5rem; /* Consistent height */
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000; /* Ensure it stays on top */
            display: flex;
            align-items: center;
            padding-left: 1.5rem; /* ms-3 equivalent */
            padding-right: 1.5rem; /* me-3 equivalent */
        }
        .top-header .school-name {
            font-size: 1.2rem;
            font-weight: 600;
            color: #e2e8f0; /* Tailwind's gray-200 */
        }
        .top-header .year-selector-form {
            display: flex;
            align-items: center;
            margin-left: auto; /* Push to the right */
            margin-right: 1rem; /* Space before logout */
        }
        .top-header .year-selector-form label {
            color: #cbd5e0; /* Tailwind's gray-300 */
            font-weight: bold;
            margin-right: 0.5rem;
        }
        .top-header .year-selector-form select {
            background-color: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #e2e8f0;
            border-radius: 0.375rem; /* Tailwind's rounded-md */
            padding: 0.25rem 0.75rem; /* Adjust padding for select */
            font-size: 0.875rem; /* text-sm */
        }
        .top-header .year-selector-form select option {
            background-color: #2d3748; /* Tailwind's gray-800 */
            color: #e2e8f0;
        }
        .top-header .logout-button {
            color: #ef4444; /* Tailwind's red-500 */
            display: flex;
            align-items: center;
            font-weight: 600;
            text-decoration: none;
            padding: 0.25rem 0.5rem;
            border-radius: 0.375rem;
            transition: background-color 0.2s;
        }
        .top-header .logout-button:hover {
            background-color: rgba(239, 68, 68, 0.1); /* Light red background on hover */
        }
        .top-header .logout-button i {
            margin-right: 0.25rem;
        }
        /* Adjust main content padding for fixed header */
        .main-content-area {
            padding-top: 3.5rem; /* Height of the header */
            flex-grow: 1; /* Allow content to grow */
            overflow-y: auto; /* Enable scrolling for content */
        }
        /* Responsive adjustments for header */
        @media (max-width: 767px) {
            .top-header .school-name-full { display: none; }
            .top-header .school-name-mobile { display: inline !important; }
            .top-header .year-selector-form { display: none !important; }
            .top-header .logout-button { display: none !important; }
            /* Mobile menu button will be handled by components/aside.php if it's responsive */
        }
    </style>
</head>
<body class="bg-gray-100">

    <div class="flex h-screen bg-gray-100">
        <div class="top-header">
            <a href="./index.php" class="flex items-center text-light-200 no-underline mr-auto">
                <img src="../img/mono.png" width="40" height="40" alt="Logo">
                <span class="school-name school-name-full ml-2">C.E.M.G.T Lic Rafael Leonardo Callejas</span>
                <span class="school-name school-name-mobile ml-2 hidden">CEMGT LRLC</span>
            </a>

            <form action="" method="GET" class="year-selector-form hidden md:flex">
                <label for="select_year" class="text-sm">Año:</label>
                <select name="year" id="select_year" onchange="this.form.submit()">
                    <?php
                    $current_system_year = date('Y');
                    $selected_year = $_SESSION['current_academic_year'] ?? $current_system_year;
                    if (!empty($available_years)) {
                        foreach ($available_years as $year_data) {
                            $year_value = htmlspecialchars($year_data['year']);
                            $display_text = htmlspecialchars($year_data['display_name'] ?: $year_value);
                            $selected = ($selected_year == $year_value) ? 'selected' : '';
                            echo "<option value='{$year_value}' {$selected}>{$display_text}</option>";
                        }
                    } else {
                        echo "<option value='{$current_system_year}' selected>Año Actual: {$current_system_year}</option>";
                        echo "<option value='".($current_system_year - 1)."'>".($current_system_year - 1)."</option>";
                    }
                    ?>
                </select>
            </form>

            <a href="logout.php" class="logout-button hidden md:flex">
                <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
            </a>
        </div>

        <div class="hidden md:flex md:flex-shrink-0">
            <div class="flex flex-col w-64 pt-14"> <div class="flex flex-col h-0 flex-1 bg-white shadow-lg">
                    <?php include 'components/teacher_aside.php'; // New Tailwind-compatible teacher aside ?>
                </div>
            </div>
        </div>

        <div class="flex flex-col w-0 flex-1 overflow-hidden main-content-area">
            <main class="flex-1 relative z-0 overflow-y-auto focus:outline-none p-6 md:p-8">

                <section id="lms_activities_section" class="<?php echo ($current_view === 'lms_activities') ? '' : 'hidden'; ?>">
                    <div class="pb-4 border-b border-gray-200 mb-8">
                        <h1 class="text-3xl md:text-4xl font-bold text-gray-800">LMS: Gestión de Actividades</h1>
                        <p class="mt-1 text-md text-gray-600">Bienvenido de nuevo, <span class="font-semibold text-indigo-600"><?php echo htmlspecialchars($teacher_name); ?></span>. Administra las actividades para tus cursos.</p>
                    </div>

                    <div class="bg-white rounded-xl shadow-md p-6 mb-8">
                        <h2 class="text-xl font-semibold text-gray-900 flex items-center mb-4">
                            <span class="flex items-center justify-center w-8 h-8 bg-indigo-100 text-indigo-600 rounded-full mr-3 font-bold">1</span>
                            Seleccione una Asignatura
                        </h2>
                        <select class="form-select w-full p-3 border-gray-300 rounded-lg text-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" id="asignaturaSelector">
                            <option value="">Elija una materia para empezar...</option>
                            <?php foreach ($asignaturas as $asignatura): ?>
                                <option value="<?php echo $asignatura['id']; ?>"><?php echo htmlspecialchars($asignatura['subject']) . " - Grado: " . htmlspecialchars($asignatura['grade']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div id="lmsContent" class="hidden">
                        <div class="bg-white rounded-xl shadow-md">
                            <div class="p-6 border-b border-gray-200 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                                <div>
                                    <h3 id="selectedAsignaturaTitle" class="text-2xl font-bold text-gray-900"></h3>
                                    <p class="text-gray-500">Gestione las actividades para esta clase.</p>
                                </div>
                                <button id="openModalBtn" class="w-full md:w-auto bg-indigo-600 text-white font-bold py-2 px-5 rounded-lg hover:bg-indigo-700 transition duration-300 flex items-center justify-center">
                                    <i class="fas fa-plus mr-2"></i>Crear Actividad
                                </button>
                            </div>
                            <div id="listaActividadesContainer" class="p-2 md:p-6">
                                </div>
                        </div>
                    </div>
                </section>

                <section id="notes_management_section" class="<?php echo ($current_view === 'notes_management') ? '' : 'hidden'; ?>">
                    <div class="pb-4 border-b border-gray-200 mb-8">
                        <h1 class="text-3xl md:text-4xl font-bold text-gray-800">Notas: Gestión de Calificaciones Finales</h1>
                        <p class="mt-1 text-md text-gray-600">Introduce o actualiza las calificaciones finales por parcial y asignatura.</p>
                    </div>

                    <div class="bg-white rounded-xl shadow-md p-6">
                        <form action="" id="add_grades" method="post" class="space-y-4">
                            <div class="flex flex-wrap items-end -mx-2 mb-4"> <div class="w-full sm:w-1/4 px-2 mb-4 sm:mb-0">
                                    <label for="grado" class="block text-sm font-medium text-gray-700">Grado:</label>
                                    <select name="grade" id="grado" class="form-select mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 subject">
                                        <option value="0"></option>
                                        <option value="71">Septimo1</option>
                                        <option value="72">Septimo2</option>
                                        <option value="73">Septimo3</option>
                                        <option value="81">Octavo1</option>
                                        <option value="82">Octavo2</option>
                                        <option value="83">Octavo3</option>
                                        <option value="91">Noveno1</option>
                                        <option value="92">Noveno2</option>
                                        <option value="93">Noveno3</option>
                                        <option value="101">Decimo1</option>
                                        <option value="102">Decimo2</option>
                                        <option value="103">Decimo3</option>
                                        <option value="1113">Undecimo1 BHC</option>
                                        <option value="1123">Undecimo2 BHC</option>
                                        <option value="1133">Undecimo3 BHC</option>
                                        <option value="1114">Undecimo1 BTPI</option>
                                        <option value="1124">Undecimo2 BTPI</option>
                                        <option value="1134">Undecimo3 BTPI</option>
                                    </select>
                                </div>
                                
                                <div class="w-full sm:w-1/4 px-2 mb-4 sm:mb-0">
                                    <label for="parcial" class="block text-sm font-medium text-gray-700">Asignatura:</label> <select class="form-select mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm" aria-label="Disabled" disabled name="1" id="subject">
                                        <option value=""></option>
                                    </select>
                                </div>

                                <div class="w-full sm:w-2/4 px-2 mb-4 sm:mb-0 flex flex-wrap gap-2 justify-end">
                                    <button type="button" id="exportar_excel" class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 flex items-center justify-center font-bold">
                                        <i class="fas fa-file-excel mr-2"></i> Descargar Archivo de Notas
                                    </button>
                                    <button type="button" class="px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 flex items-center justify-center font-bold" data-bs-toggle="modal" data-bs-target="#importarModal">
                                        <i class="fas fa-file-import mr-2"></i> Cargar Archivo de Notas
                                    </button>
                                    <button type="submit" id="ingresar_notas" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 flex items-center justify-center font-bold">
                                        <i class="fas fa-save mr-2"></i> Guardar
                                    </button>
                                </div>
                            </div>
                            
                            <div id="notes_table_container" class="mt-6 overflow-x-auto w-full">
                                </div>
                        </form>
                    </div>
                </section>

                <div id="modalCrearActividad" class="fixed inset-0 bg-gray-800 bg-opacity-75 flex items-center justify-center hidden z-50 p-4">
                    <div class="relative bg-white rounded-lg shadow-xl w-full max-w-2xl">
                        <form id="formCrearActividad" class="w-full">
                            <div class="flex justify-between items-center p-5 border-b">
                                <h3 class="text-2xl font-semibold text-gray-800">Nueva Actividad</h3>
                                <button type="button" id="closeModalBtn" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
                            </div>
                            <div class="p-6 space-y-4">
                                <div id="formError" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded hidden"></div>
                                <input type="hidden" name="id_asignatura" id="id_asignatura_actividad">
                                <input type="hidden" name="id_docente" value="<?php echo $teacher_id; ?>">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Parcial</label>
                                        <select name="parcial" class="form-select mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm" required>
                                            <option>Primer Parcial</option><option>Segundo Parcial</option><option>Tercer Parcial</option><option>Cuarto Parcial</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de Actividad</label>
                                        <select name="tipo_actividad" class="form-select mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm" required>
                                            <option>Tarea</option><option>Examen</option><option>Proyecto</option><option>Otro</option>
                                        </select>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Nombre de la Actividad</label>
                                    <input type="text" name="nombre_actividad" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm" required>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Instrucciones</label>
                                    <textarea name="descripcion" rows="3" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm"></textarea>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Puntaje Máximo</label>
                                        <input type="number" step="0.01" name="puntaje_maximo" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm" required>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Fecha Límite de Entrega (Opcional)</label>
                                        <input type="datetime-local" name="fecha_entrega" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm">
                                    </div>
                                </div>
                            </div>
                            <div class="flex justify-end p-5 border-t bg-gray-50 rounded-b-lg">
                                <button type="button" id="cancelModalBtn" class="bg-gray-200 text-gray-700 font-bold py-2 px-4 rounded-lg mr-2 hover:bg-gray-300">Cerrar</button>
                                <button type="submit" class="bg-indigo-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-indigo-700">Guardar Actividad</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="fixed inset-0 bg-gray-800 bg-opacity-75 flex items-center justify-center hidden z-50 p-4" id="importarModal" data-modal-backdrop="static" tabindex="-1" aria-labelledby="importarModalLabel" aria-hidden="true">
                    <div class="relative bg-white rounded-lg shadow-xl w-full max-w-md">
                        <div class="flex justify-between items-center p-5 border-b">
                            <h5 class="text-2xl font-semibold text-gray-800" id="importarModalLabel">Importar Notas desde Excel</h5>
                            <button type="button" class="text-gray-400 hover:text-gray-600 text-2xl" data-modal-hide="importarModal" id="closeImportModalBtn">&times;</button>
                        </div>
                        <div class="p-6 space-y-4">
                            <form id="form_importar" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <label for="archivo_excel" class="block text-sm font-medium text-gray-700">Seleccionar archivo Excel:</label>
                                    <input class="mt-1 block w-full p-2 border border-gray-300 rounded-md" type="file" id="archivo_excel" name="archivo_excel" accept=".xlsx, .xls" required>
                                </div>
                                <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded text-sm">
                                    <strong>Nota:</strong> El archivo debe tener el formato correcto con las columnas: N°, N° Estudiante, Alumno, I Parcial, II Parcial, III Parcial, IV Parcial, Recuperacion
                                </div>
                                <input type="hidden" name="subject" id="import_subject">
                                <input type="hidden" name="grade" id="import_grade">
                                <input type="hidden" name="semester" id="import_semester">
                            </form>
                        </div>
                        <div class="flex justify-end p-5 border-t bg-gray-50 rounded-b-lg">
                            <button type="button" class="bg-gray-200 text-gray-700 font-bold py-2 px-4 rounded-lg mr-2 hover:bg-gray-300" data-modal-hide="importarModal" id="cancelImportModalBtn">Cancelar</button>
                            <button type="button" class="bg-green-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-green-700" id="btn_importar">Importar</button>
                        </div>
                    </div>
                </div>

                <footer class="text-center mt-12 text-gray-500 text-sm">Copyright &copy 2023 by Neyfer Coto - All Rights Reserved</footer>
            </main>
        </div>
    </div>

    <script src="../js/jquery.js"></script>
    <script src="../js/main.js"></script> <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

    <script>
    $(document).ready(function() {
        // --- LMS Activity Management (from gestion_lms.php) ---
        const modalCrearActividad = $('#modalCrearActividad');
        $('#openModalBtn').on('click', () => modalCrearActividad.removeClass('hidden'));
        $('#closeModalBtn, #cancelModalBtn').on('click', () => modalCrearActividad.addClass('hidden'));

        function cargarActividades(asignaturaId) {
            const container = $('#listaActividadesContainer');
            container.html('<p class="text-center text-gray-500 py-8">Cargando...</p>');
            $.ajax({
                url: 'ajax_get_activities.php', type: 'POST', data: { id_asignatura: asignaturaId }, dataType: 'json',
                success: function(response) {
                    if (response.success && response.actividades.length > 0) {
                        let table = '<div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200"><thead class="bg-gray-50"><tr>' +
                            '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actividad</th>' +
                            '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Parcial</th>' +
                            '<th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Puntaje</th>' +
                            '<th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th></tr></thead><tbody class="bg-white divide-y divide-gray-200">';
                        response.actividades.forEach(act => {
                            table += `<tr class="hover:bg-gray-50"><td class="px-6 py-4 whitespace-nowrap"><div class="text-sm font-medium text-gray-900">${act.nombre_actividad}</div><div class="text-sm text-gray-500">${act.tipo_actividad}</div></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${act.parcial}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-semibold text-gray-900">${act.puntaje_maximo}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium"><a href="calificar_actividad.php?id=${act.id}" class="text-indigo-600 hover:text-indigo-900">Ver y Calificar</a></td></tr>`;
                        });
                        table += '</tbody></table></div>';
                        container.html(table);
                    } else {
                        container.html(`<div class="text-center py-10 px-6 bg-gray-50 rounded-lg">
                            <i class="fas fa-folder-open fa-3x text-gray-400"></i>
                            <h3 class="mt-4 text-lg font-medium text-gray-900">Aún no hay actividades</h3>
                            <p class="mt-1 text-sm text-gray-500">¡Anímate a crear la primera para esta asignatura!</p></div>`);
                    }
                },
                error: function() { container.html('<p class="p-4 text-center text-red-500">Hubo un error al cargar las actividades. Revisa la conexión y el archivo ajax_get_activities.php.</p>'); }
            });
        }

        $('#asignaturaSelector').on('change', function() {
            const asignaturaId = $(this).val();
            const asignaturaTexto = $(this).find('option:selected').text();
            if (asignaturaId) {
                $('#lmsContent').removeClass('hidden');
                $('#selectedAsignaturaTitle').text(asignaturaTexto.trim());
                $('#id_asignatura_actividad').val(asignaturaId);
                cargarActividades(asignaturaId);
            } else {
                $('#lmsContent').addClass('hidden');
            }
        });

        $('#formCrearActividad').on('submit', function(e) {
            e.preventDefault();
            const asignaturaId = $('#id_asignatura_actividad').val();
            $.ajax({
                url: 'ajax_save_activity.php', type: 'POST', data: $(this).serialize(), dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        modalCrearActividad.addClass('hidden');
                        $('#formCrearActividad')[0].reset();
                        cargarActividades(asignaturaId);
                        Swal.fire('¡Éxito!', response.message || 'Actividad creada con éxito.', 'success');
                    } else {
                        $('#formError').removeClass('hidden').text(response.message || 'Ocurrió un error desconocido.');
                        Swal.fire('Error', response.message || 'Error al crear actividad.', 'error');
                    }
                },
                error: function() {
                    $('#formError').removeClass('hidden').text('Error de comunicación al guardar.');
                    Swal.fire('Error', 'Error de comunicación al guardar la actividad.', 'error');
                }
            });
        });

        // --- Notes Management (Identical to notas_maestros.php logic) ---

        // Function to load subjects based on selected grade (for notes section)
        function loadSubjectsForGrade(gradeId, targetSelectId) {
            $.ajax({
                url: 'ajax_get_subjects_by_grade.php', // You'll need to create this AJAX endpoint
                type: 'GET',
                data: { grade: gradeId },
                dataType: 'json',
                success: function(response) {
                    let subjectSelect = $(targetSelectId);
                    subjectSelect.empty().append('<option value="">Seleccione Asignatura</option>');
                    if (response.success && response.subjects.length > 0) {
                        response.subjects.forEach(subject => {
                            subjectSelect.append(`<option value="${subject.subject}">${subject.subject}</option>`);
                        });
                        subjectSelect.prop('disabled', false);
                    } else {
                        subjectSelect.prop('disabled', true);
                    }
                },
                error: function() {
                    console.error("Error loading subjects for grade.");
                    $(targetSelectId).empty().append('<option value="">Error al cargar</option>').prop('disabled', true);
                }
            });
        }

        // Function to load notes table
        function loadNotesTable() {
            const grade = $('#grado').val(); // Changed to #grado
            const subject = $('#subject').val(); // Changed to #subject
            const partial = $('#parcial_notas').val(); // Kept #parcial_notas for the quarter selector, used in backend calls.
            const container = $('#notes_table_container');

            if (grade === "0" || subject === "") { // Removed partial from this condition
                container.html('<p class="text-center text-gray-500 py-8">Seleccione Grado y Asignatura para cargar las notas.</p>');
                return;
            }

            container.html('<p class="text-center text-gray-500 py-8">Cargando tabla de notas...</p>');

            $.ajax({
                url: 'ajax_get_notes_table.php', // You'll need to create this AJAX endpoint
                type: 'POST',
                // Send current partial selection to backend even if it's not in the main UI
                data: { grade: grade, subject: subject, partial: partial },
                success: function(data) {
                    container.html(data);
                },
                error: function() {
                    container.html('<p class="p-4 text-center text-red-500">Error al cargar la tabla de notas. Asegúrese de que ajax_get_notes_table.php existe y funciona correctamente.</p>');
                }
            });
        }

        // Event listeners for notes section selectors
        $('#grado').on('change', function() { // Changed to #grado
            const gradeId = $(this).val();
            if (gradeId !== "0") {
                loadSubjectsForGrade(gradeId, '#subject'); // Changed to #subject
            } else {
                $('#subject').empty().append('<option value="">Seleccione Asignatura</option>').prop('disabled', true); // Changed to #subject
                $('#notes_table_container').empty();
            }
            // Reset subject when grade changes
            $('#subject').val(''); // Changed to #subject
            // The conceptual 'parcial' (quarter) selector is not in the main UI, so we keep its value if it was set
            // or assume a default like 'primer_parcial' for data operations if it's not explicitly chosen elsewhere.
            // For now, loadNotesTable uses #parcial_notas, which is hidden in this UI.
            // You might need a default value for #parcial_notas, e.g., on first load.
        });

        // Trigger loadNotesTable only when subject changes (since parcial select is gone from main UI)
        $('#subject').on('change', function() { // Changed #subject
             // Set a default partial for operations if no specific partial selector is present in main UI
            // This is crucial. If 'partial' is selected only in the import/export modals,
            // we need a default for initial table load. Let's make it 'primer_parcial' if not set.
            if ($('#parcial_notas').val() === '') {
                $('#parcial_notas').val('primer_parcial'); // Default to primer_parcial if not explicitly selected
            }
            loadNotesTable();
            // Also update hidden fields for Excel import/export
            $('#import_grade').val($('#grado').val()); // Changed to #grado
            $('#import_subject').val($('#subject').val()); // Changed to #subject
            $('#import_semester').val($('#parcial_notas').val()); // This assumes parcial_notas always holds the current desired partial
        });

        // --- Excel Export (from notas_maestros.php) ---
        $('#exportar_excel').on('click', function(e) {
            e.preventDefault();
            const grade = $('#grado').val(); // Changed to #grado
            const subject = $('#subject').val(); // Changed to #subject
            // For export, we MUST have a partial. If not in UI, it must come from somewhere, e.g., default or modal.
            // The example in notes_maestros.php also expects 'partial'.
            const partial = $('#parcial_notas').val(); // This is the conceptual 'partial' for the table.

            if (grade === "0" || subject === "" || partial === "") {
                Swal.fire('¡Atención!', 'Por favor, seleccione Grado y Asignatura para descargar el archivo de notas. (Asegúrese de que el Parcial esté seleccionado si su sistema lo requiere).', 'warning');
                return;
            }

            // You'll need an AJAX endpoint to generate and provide the Excel file
            // For now, this just triggers a download from the backend
            window.location.href = `ajax_export_excel.php?grade=${grade}&subject=${subject}&partial=${partial}`; // You'll create this file
        });

        // --- Excel Import (from notas_maestros.php) ---
        const modalImportar = $('#importarModal');
        // Manually show/hide for import modal since data-bs-toggle is not used with Tailwind modal logic
        $('[data-bs-toggle="modal"][data-bs-target="#importarModal"]').on('click', () => modalImportar.removeClass('hidden')); // Using the exact selector from original button
        $('#closeImportModalBtn, #cancelImportModalBtn').on('click', () => modalImportar.addClass('hidden')); // Using specific IDs for close/cancel buttons


        $('#btn_importar').on('click', function(e) {
            e.preventDefault();
            const fileInput = $('#archivo_excel')[0];
            const file = fileInput.files[0];

            if (!file) {
                Swal.fire('Error', 'Por favor, seleccione un archivo Excel para importar.', 'error');
                return;
            }

            const grade = $('#import_grade').val();
            const subject = $('#import_subject').val();
            const partial = $('#import_semester').val(); // Corrected ID usage

            if (grade === "0" || subject === "" || partial === "") {
                Swal.fire('¡Atención!', 'Por favor, seleccione Grado y Asignatura antes de importar. (Asegúrese de que el Parcial esté seleccionado si su sistema lo requiere).', 'warning');
                return;
            }

            const reader = new FileReader();
            reader.onload = function(e) {
                const data = new Uint8Array(e.target.result);
                const workbook = XLSX.read(data, { type: 'array' });
                const firstSheetName = workbook.SheetNames[0];
                const worksheet = workbook.Sheets[firstSheetName];
                const jsonData = XLSX.utils.sheet_to_json(worksheet, { header: 1 });

                // Assuming first row is header, actual data starts from second row (index 1)
                const headers = jsonData[0]; // Headers for potential validation
                const studentData = jsonData.slice(1); // Actual student rows

                // Basic validation: Check if required headers exist
                const requiredHeaders = ['N°', 'N° Estudiante', 'Alumno', 'I Parcial', 'II Parcial', 'III Parcial', 'IV Parcial', 'Recuperacion'];
                const hasAllHeaders = requiredHeaders.every(header => headers.includes(header));

                if (!hasAllHeaders) {
                    Swal.fire('Error', 'El archivo Excel no tiene el formato de columnas esperado. Asegúrese de que incluye: N°, N° Estudiante, Alumno, I Parcial, II Parcial, III Parcial, IV Parcial, Recuperacion.', 'error');
                    $('#form_importar')[0].reset(); // Clear file input
                    return;
                }

                // Process data to match backend expectation, using column names directly for robustness
                const processedData = studentData.map(row => {
                    const rowData = {};
                    headers.forEach((header, index) => {
                        rowData[header] = row[index];
                    });
                    return {
                        'student_number': rowData['N° Estudiante'], // Assuming this is student IDENT, or internal ID
                        'student_name': rowData['Alumno'],
                        'note_p1': parseFloat(rowData['I Parcial']) || null,
                        'note_p2': parseFloat(rowData['II Parcial']) || null,
                        'note_p3': parseFloat(rowData['III Parcial']) || null,
                        'note_p4': parseFloat(rowData['IV Parcial']) || null,
                        'note_recuperacion': parseFloat(rowData['Recuperacion']) || null
                    };
                });

                // Send processed data via AJAX to a new endpoint
                $.ajax({
                    url: 'ajax_import_excel_notes.php', // You'll create this AJAX endpoint
                    type: 'POST',
                    contentType: 'application/json', // Important for sending JSON
                    data: JSON.stringify({
                        grade: grade, // Pass grade and subject for context in backend
                        subject: subject,
                        partial: partial,
                        notes_data: processedData
                    }),
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('¡Éxito!', response.message, 'success');
                            modalImportar.addClass('hidden');
                            $('#form_importar')[0].reset(); // Clear file input
                            loadNotesTable(); // Reload table after import
                        } else {
                            Swal.fire('Error', response.message || 'Error al importar las notas.', 'error');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("AJAX Error:", status, error, xhr.responseText);
                        Swal.fire('Error', 'Error de comunicación al importar las notas.', 'error');
                    }
                });
            };
            reader.readAsArrayBuffer(file);
        });

        // --- Save Notes (from notas_maestros.php) ---
        $('#add_grades').on('submit', function(e) {
            e.preventDefault(); // Prevent default form submission

            const grade = $('#grado').val(); // Changed to #grado
            const subject = $('#subject').val(); // Changed to #subject
            const partial = $('#parcial_notas').val(); // Kept #parcial_notas

            if (grade === "0" || subject === "" || partial === "") {
                Swal.fire('¡Atención!', 'Por favor, seleccione Grado y Asignatura antes de guardar las notas. (Asegúrese de que el Parcial esté seleccionado si su sistema lo requiere).', 'warning');
                return;
            }

            let notesData = [];
            $('#notes_table_container tbody tr').each(function() {
                const studentId = $(this).data('student-id');
                const noteInput = $(this).find('input[name="nota[]"]');
                const nota = noteInput.val();
                if (studentId && (nota !== '' && nota !== null)) { // Only send if note is entered
                    notesData.push({
                        student_id: studentId,
                        nota: parseFloat(nota) || 0 // Ensure it's a number
                    });
                }
            });

            if (notesData.length === 0) {
                 Swal.fire('¡Atención!', 'No hay notas para guardar o los campos están vacíos.', 'warning');
                 return;
            }

            $.ajax({
                url: 'ajax_save_notes.php', // You'll create this AJAX endpoint
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    grade: grade, // Pass grade and subject for context in backend
                    subject: subject,
                    partial: partial,
                    notes: notesData
                }),
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire('¡Éxito!', response.message, 'success');
                        loadNotesTable(); // Reload table to reflect saved changes
                    } else {
                        Swal.fire('Error', response.message || 'Error al guardar las notas.', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error("AJAX Error:", status, error, xhr.responseText);
                    Swal.fire('Error', 'Error de comunicación al guardar las notas.', 'error');
                }
            });
        });

    });
    </script>
</body>
</html>