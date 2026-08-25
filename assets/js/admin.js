// ============================================================
// ADMIN.JS - FUNCIONES DEL PANEL DE ADMINISTRACIÓN
// ============================================================

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

/**
 * Ver detalle de un ticket
 */
function verTicketAdmin(id) {
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
                    text: data.error || 'No se pudo obtener el ticket'
                });
            }
        })
        .catch(error => {
            Swal.fire({
                icon: 'error',
                title: 'Error de conexión',
                text: 'No se pudo obtener el ticket'
            });
        });
}

/**
 * Abrir modal para aprobar/rechazar ticket
 */
function abrirAprobacion(id) {
    document.getElementById('aprobacion-ticket-id').value = id;
    document.getElementById('motivo-rechazo').style.display = 'none';
    document.getElementById('motivo').value = '';
    
    const modal = new bootstrap.Modal(document.getElementById('modal-aprobar'));
    modal.show();
}

/**
 * Aprobar ticket
 */
function aprobarTicket() {
    const id = document.getElementById('aprobacion-ticket-id').value;
    
    Swal.fire({
        title: '¿Aprobar este ticket?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, aprobar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#28a745'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=aprobar&ticket_id=${id}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Aprobado!',
                        text: data.message
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.error
                    });
                }
            });
        }
    });
}

/**
 * Mostrar campo de motivo de rechazo
 */
function mostrarRechazo() {
    document.getElementById('motivo-rechazo').style.display = 'block';
}

/**
 * Cancelar rechazo
 */
function cancelarRechazo() {
    document.getElementById('motivo-rechazo').style.display = 'none';
    document.getElementById('motivo').value = '';
}

/**
 * Rechazar ticket
 */
function rechazarTicket() {
    const id = document.getElementById('aprobacion-ticket-id').value;
    const motivo = document.getElementById('motivo').value.trim();
    
    if (!motivo) {
        Swal.fire({
            icon: 'warning',
            title: 'Motivo requerido',
            text: 'Debe especificar un motivo para el rechazo'
        });
        return;
    }
    
    Swal.fire({
        title: '¿Rechazar este ticket?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, rechazar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc3545'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=rechazar&ticket_id=${id}&motivo=${encodeURIComponent(motivo)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Rechazado',
                        text: data.message
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.error
                    });
                }
            });
        }
    });
}

/**
 * Abrir modal para asignar ticket
 */
function abrirAsignacion(id) {
    document.getElementById('asignar-ticket-id').value = id;
    
    // Cargar técnicos disponibles
    fetch('api.php?action=obtener_tecnicos')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const select = document.getElementById('tecnico-select');
                select.innerHTML = '<option value="">Seleccionar técnico...</option>';
                data.data.forEach(tecnico => {
                    const option = document.createElement('option');
                    option.value = tecnico.id;
                    option.textContent = `${tecnico.nombre_completo} (${tecnico.departamento_nombre || 'Sin departamento'})`;
                    select.appendChild(option);
                });
            }
        });
    
    const modal = new bootstrap.Modal(document.getElementById('modal-asignar'));
    modal.show();
}

/**
 * Asignar ticket a técnico
 */
function asignarTicket() {
    const id = document.getElementById('asignar-ticket-id').value;
    const tecnicoId = document.getElementById('tecnico-select').value;
    
    if (!tecnicoId) {
        Swal.fire({
            icon: 'warning',
            title: 'Seleccione un técnico',
            text: 'Debe seleccionar un técnico para asignar el ticket'
        });
        return;
    }
    
    Swal.fire({
        title: '¿Asignar este ticket?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, asignar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#17a2b8'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=asignar&ticket_id=${id}&tecnico_id=${tecnicoId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Asignado!',
                        text: data.message
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.error
                    });
                }
            });
        }
    });
}

/**
 * Resolver ticket
 */
function resolverTicket(id) {
    Swal.fire({
        title: 'Resolver Ticket',
        html: `
            <div class="mb-3">
                <label for="comentarios-resolucion" class="form-label">Comentarios (opcional)</label>
                <textarea id="comentarios-resolucion" class="form-control" rows="3" placeholder="Agregar comentarios sobre la resolución..."></textarea>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Resolver',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#28a745',
        preConfirm: () => {
            const comentarios = document.getElementById('comentarios-resolucion').value.trim();
            return { comentarios };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const comentarios = result.value.comentarios;
            
            fetch('api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=resolver&ticket_id=${id}&comentarios=${encodeURIComponent(comentarios)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Resuelto!',
                        text: data.message
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.error
                    });
                }
            });
        }
    });
}

/**
 * Eliminar ticket (SuperAdmin)
 */
function eliminarTicket(id) {
    Swal.fire({
        title: '¿Eliminar este ticket?',
        text: 'Esta acción no se puede deshacer',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc3545'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=eliminar&ticket_id=${id}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Eliminado',
                        text: data.message
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.error
                    });
                }
            });
        }
    });
}
