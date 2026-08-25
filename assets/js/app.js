// ============================================================
// APP.JS - FUNCIONES PRINCIPALES
// ============================================================

/**
 * Crea un nuevo ticket
 */
function crearTicket(form) {
    const formData = new FormData(form);
    const descripcion = formData.get('descripcion');
    
    // Validar descripción
    if (descripcion.length < 10) {
        Swal.fire({
            icon: 'warning',
            title: 'Descripción muy corta',
            text: 'La descripción debe tener al menos 10 caracteres',
            confirmButtonColor: '#1a3a5c'
        });
        return;
    }
    
    // Mostrar loading
    Swal.fire({
        title: 'Enviando ticket...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    fetch('api.php?action=crear_ticket', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: '¡Ticket creado!',
                text: data.message,
                confirmButtonColor: '#1a3a5c'
            }).then(() => {
                location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.error || 'Error al crear el ticket',
                confirmButtonColor: '#1a3a5c'
            });
        }
    })
    .catch(error => {
        // Si no hay conexión, guardar offline
        if (!navigator.onLine) {
            guardarTicketOffline(formData);
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error de conexión',
                text: 'No se pudo conectar al servidor',
                confirmButtonColor: '#1a3a5c'
            });
        }
    });
}

/**
 * Ver detalle de un ticket
 */
function verTicket(id) {
    fetch(`api.php?action=obtener_ticket&id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const t = data.data;
                const modalBody = document.getElementById('modal-ticket-body');
                
                let html = `
                    <div class="row">
                        <div class="col-md-6">
                            <strong>ID:</strong> #${t.id}
                        </div>
                        <div class="col-md-6">
                            <strong>Estado:</strong> 
                            <span class="badge bg-${getEstadoColor(t.estado)}">
                                ${t.estado.replace(/_/g, ' ').toUpperCase()}
                            </span>
                        </div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-12">
                            <strong>Título:</strong> ${t.titulo}
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-12">
                            <strong>Descripción:</strong><br>
                            ${t.descripcion}
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-md-6">
                            <strong>Departamento:</strong> ${t.departamento_nombre || 'Sin asignar'}
                        </div>
                        <div class="col-md-6">
                            <strong>Usuario:</strong> ${t.usuario_nombre || t.usuario_origen}
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-md-6">
                            <strong>Fecha:</strong> ${new Date(t.fecha).toLocaleString()}
                        </div>
                        <div class="col-md-6">
                            <strong>PC Origen:</strong> ${t.pc_origen}
                        </div>
                    </div>
                `;
                
                // Foto
                if (t.foto) {
                    html += `
                        <div class="row mt-2">
                            <div class="col-12">
                                <strong>Foto:</strong><br>
                                <img src="uploads/${t.foto}" class="img-fluid img-thumbnail" style="max-height: 200px;" alt="Foto del ticket">
                            </div>
                        </div>
                    `;
                }
                
                // Información de aprobación
                if (t.aprobado_por) {
                    html += `
                        <hr>
                        <div class="row">
                            <div class="col-md-6">
                                <strong>Aprobado por:</strong> ${t.aprobado_por_nombre || t.aprobado_por}
                            </div>
                            <div class="col-md-6">
                                <strong>Fecha aprobación:</strong> ${t.fecha_aprobacion ? new Date(t.fecha_aprobacion).toLocaleString() : 'N/A'}
                            </div>
                        </div>
                    `;
                }
                
                // Motivo de rechazo
                if (t.motivo_rechazo) {
                    html += `
                        <div class="row mt-2">
                            <div class="col-12">
                                <strong>Motivo de rechazo:</strong><br>
                                <span class="text-danger">${t.motivo_rechazo}</span>
                            </div>
                        </div>
                    `;
                }
                
                // Información de asignación
                if (t.asignado_a) {
                    html += `
                        <hr>
                        <div class="row">
                            <div class="col-md-6">
                                <strong>Técnico asignado:</strong> ${t.tecnico_asignado || 'Sin asignar'}
                            </div>
                            <div class="col-md-6">
                                <strong>Fecha asignación:</strong> ${t.fecha_asignacion ? new Date(t.fecha_asignacion).toLocaleString() : 'N/A'}
                            </div>
                        </div>
                    `;
                }
                
                // Comentarios técnicos
                if (t.comentarios_tecnicos) {
                    html += `
                        <div class="row mt-2">
                            <div class="col-12">
                                <strong>Comentarios técnicos:</strong><br>
                                ${t.comentarios_tecnicos}
                            </div>
                        </div>
                    `;
                }
                
                modalBody.innerHTML = html;
                const modal = new bootstrap.Modal(document.getElementById('modal-ticket'));
                modal.show();
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.error || 'No se pudo obtener el ticket',
                    confirmButtonColor: '#1a3a5c'
                });
            }
        })
        .catch(error => {
            Swal.fire({
                icon: 'error',
                title: 'Error de conexión',
                text: 'No se pudo obtener el ticket',
                confirmButtonColor: '#1a3a5c'
            });
        });
}

/**
 * Obtiene el color correspondiente al estado de un ticket
 */
function getEstadoColor(estado) {
    const colores = {
        'pendiente_aprobacion': 'warning',
        'aprobado': 'success',
        'rechazado': 'danger',
        'en_progreso': 'info',
        'resuelto': 'secondary'
    };
    return colores[estado] || 'secondary';
}
