<?php
// Start the session
session_start();


?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notas</title>
    <script src="../js/jquery.js"></script>
    <link href="../bootstrap/bootstrap.min.css" rel="stylesheet">
    <script src="../bootstrap/bootstrap.bundle.js"></script>
    <link href="../css/main.css" rel="stylesheet">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
  <script src="https://unpkg.com/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
  <!-- SweetAlert2 CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <meta http-equiv=”refresh” content="60" />
</head>
<body>
    <div class="container-fluid">
        <div class="row no-glutters " style="height: 6vh;">
            <div class="no-glutters col-sm-12" style="padding: 0; position:fixed; z-index:300">
                <?php
                    include_once("components/header.php");
                ?>
            </div>
        </div>

        <div class="row">
            
            <div class=" col-sm-12 container padding-3"> <br>
                <div class="container" style='width:90%;' id="add_teacher_container" tabindex="-1">
                    <form action="" id="add_grades" method="post">
                        <div class="row">
                            <div class="col-sm-2">
                                <label for="grade" class="form-label">Grado:</label><select name="grade" id="grado" class="form-select subject">
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
                            
                            <div class="col-sm-2" style="display: none;" id="semester-selector">
                                <label for="parcial" class="form-label">Semestre:</label><select class="form-select" name="1" id="parcial">
                                <option value=""></option>
                                </select>
                            </div>

                            <div class="col-sm-3">
                                <label for="parcial" class="form-label">Asignatura:</label><select class="form-select" aria-label="Disabled" disabled name="1" id="subject">
                                    <option value=""></option>
                                </select>
                            </div>
                          
                          <!-- Botones -->


                            <div class="col-sm-7 mt-4">
                              
                              <!-- Agrega esto en la sección de botones -->
                                  <button id="exportar_excel" class="btn btn-primary">
                                      <i class="fas fa-file-excel"></i> Descargar Archivo de Notas
                                  </button>
                                  <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#importarModal">
                                      <i class="fas fa-file-import"></i> Cargar Archivo de Notas
                                  </button>
                              
                                
                                <input type="submit"  class="btn btn-success" id="ingresar_notas" value="Guardar">
                            </div>
                            
                            </div>
                    </form><br>
                  


<!-- Modal para importar Excel -->
<div class="modal fade" id="importarModal" tabindex="-1" aria-labelledby="importarModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="importarModalLabel">Importar Notas desde Excel</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="form_importar" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="archivo_excel" class="form-label">Seleccionar archivo Excel:</label>
                        <input class="form-control" type="file" id="archivo_excel" name="archivo_excel" accept=".xlsx, .xls" required>
                    </div>
                    <div class="alert alert-info">
                        <strong>Nota:</strong> El archivo debe tener el formato correcto con las columnas: N°, N° Estudiante, Alumno, I Parcial, II Parcial, III Parcial, IV Parcial, Recuperacion
                    </div>
                    <input type="hidden" name="subject" id="import_subject">
                    <input type="hidden" name="grade" id="import_grade">
                    <input type="hidden" name="semester" id="import_semester">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal" id="btn_importar">Importar</button>
            </div>
        </div>
    </div>
</div>
                  

                    <div class="t-c">
                        
                    </div>
                </div>
                <footer style="text-align:center;">Copyright &copy 2023 by Neyfer Coto - All Rights Reserved</footer>
            </div>

            



<script src="../js/main.js?v=2"></script>

<?php
    include("mysql.php");
?>
</body>
</html>