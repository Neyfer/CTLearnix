</main> </div> </div> <div id="modalEntregarTarea" class="fixed inset-0 bg-gray-800 bg-opacity-75 flex items-center justify-center hidden z-50 p-4">
        <div class="relative bg-white rounded-lg shadow-xl w-full max-w-2xl max-h-[90vh] flex flex-col">
            <div class="p-5 border-b flex justify-between items-center">
                <h3 id="modalTitle" class="text-xl font-bold text-gray-800"></h3>
                <button type="button" class="btn-cerrar-modal text-gray-400 hover:text-gray-500"><i class="fas fa-times fa-lg"></i></button>
            </div>
            <form id="formEntregarTarea" enctype="multipart/form-data" class="flex-1 flex flex-col">
                <div class="p-6 overflow-y-auto space-y-4">
                    <div id="seccionCalificacion" class="hidden"></div>
                    <div id="seccionComentario" class="hidden"></div>
                    <div class="mb-4">
                        <h4 class="font-bold text-gray-800">Instrucciones:</h4>
                        <p id="modalDescription" class="text-gray-600 mt-1 whitespace-pre-wrap"></p>
                    </div>
                    <div id="zonaDeEntrega" class="space-y-4 pt-4 border-t">
                        <h4 class="font-semibold text-gray-800">Tu Entrega</h4>
                        <input type="hidden" name="id_actividad" id="modal_id_actividad">
                        <input type="hidden" name="id_estudiante" value="<?php echo $student_id; ?>">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Escribe una respuesta (opcional)</label>
                            <textarea name="texto_respuesta" class="w-full p-2 border border-gray-300 rounded-md" rows="5"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Adjuntar Archivo (opcional)</label>
                            <input type="file" name="archivo_entrega" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 cursor-pointer">
                        </div>
                        <div id="entregaError" class="text-red-500 text-sm hidden"></div>
                    </div>
                </div>
                <div class="mt-auto p-4 bg-gray-50 flex justify-end space-x-3 rounded-b-lg border-t">
                    <button type="button" class="btn-cerrar-modal bg-gray-200 text-gray-800 px-4 py-2 rounded-md hover:bg-gray-300 font-semibold">Cancelar</button>
                    <button type="submit" id="submitButton" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 font-semibold">Confirmar Entrega</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../js/jquery.js"></script>
    <script>
    $(document).ready(function() {
        const modal = $('#modalEntregarTarea');

        function abrirModal(actividad) {
            modal.find('#modalTitle').text(actividad.nombre_actividad);
            modal.find('#modalDescription').text(actividad.descripcion || 'No hay instrucciones adicionales.');
            modal.find('#modal_id_actividad').val(actividad.actividad_id);
            modal.find('textarea[name="texto_respuesta"]').val(actividad.texto_respuesta || '');
            const seccionCalificacion = modal.find('#seccionCalificacion');
            if (actividad.calificacion !== null) {
                seccionCalificacion.removeClass('hidden').html(`<div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg"><h4 class="font-bold text-green-800">Calificación</h4><p class="mb-0 text-2xl font-bold">${actividad.calificacion} <span class="text-base text-gray-600">/ ${actividad.puntaje_maximo}</span></p></div>`);
            } else { seccionCalificacion.addClass('hidden'); }

            const seccionComentario = modal.find('#seccionComentario');
            if (actividad.comentario_docente && actividad.comentario_docente.trim() !== '') {
                seccionComentario.removeClass('hidden').html(`<div class="mb-4 p-4 bg-sky-50 border border-sky-200 rounded-lg"><h4 class="font-bold text-blue-800">Retroalimentación</h4><p class="mt-2">${actividad.comentario_docente}</p></div>`);
            } else { seccionComentario.addClass('hidden'); }
            
            if (actividad.calificacion !== null) {
                modal.find('#zonaDeEntrega, #submitButton').hide();
            } else {
                modal.find('#zonaDeEntrega, #submitButton').show();
            }
            modal.removeClass('hidden');
        }

        $(document).on('click', '.btn-ver-entregar', function() {
            const actividadData = $(this).data('actividad');
            abrirModal(actividadData);
        });

        modal.find('.btn-cerrar-modal').on('click', () => modal.addClass('hidden'));
        
        $('#formEntregarTarea').on('submit', function(e) {
            e.preventDefault();
            let formData = new FormData(this);
            let submitButton = $(this).find('button[type="submit"]');
            submitButton.html('<i class="fas fa-spinner fa-spin mr-2"></i> Enviando...').prop('disabled', true);
            $('#entregaError').addClass('hidden');
            $.ajax({
                url: 'ajax_submit_assignment.php', type: 'POST', data: formData, processData: false, contentType: false, dataType: 'json',
                success: function(response) {
                    if(response.success) {
                        alert('¡Entrega procesada con éxito!');
                        window.location.reload();
                    } else {
                        $('#entregaError').removeClass('hidden').text(response.message || 'Ocurrió un error.');
                        submitButton.html('Confirmar Entrega').prop('disabled', false);
                    }
                }, error: function() {
                    $('#entregaError').removeClass('hidden').text('Error de comunicación.');
                    submitButton.html('Confirmar Entrega').prop('disabled', false);
                }
            });
        });

        // --- INICIO: LÓGICA PARA NAVEGACIÓN MÓVIL ---
        const mobileSidebar = $('#mobile-sidebar');
        const openBtn = $('#mobile-menu-button');
        const closeBtn = $('#close-sidebar-button');
        const backdrop = $('#mobile-backdrop');

        // Abrir menú lateral
        openBtn.on('click', function() {
            mobileSidebar.removeClass('hidden');
        });

        // Cerrar menú lateral con el botón 'X'
        closeBtn.on('click', function() {
            mobileSidebar.addClass('hidden');
        });

        // Cerrar menú lateral al hacer clic en el fondo oscuro
        backdrop.on('click', function() {
            mobileSidebar.addClass('hidden');
        });
        // --- FIN: LÓGICA PARA NAVEGACIÓN MÓVIL ---
    });
    </script>
</body>
</html>