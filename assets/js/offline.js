// ============================================================
// OFFLINE.JS - SISTEMA DE TRABAJO OFFILNE
// ============================================================

// Clave para almacenar tickets offline
const OFFLINE_TICKETS_KEY = 'esg_offline_tickets';
const PENDING_SYNC_KEY = 'esg_pending_sync';

/**
 * Guarda un ticket en localStorage para sincronización offline
 */
function guardarTicketOffline(formData) {
    const ticket = {
        id: Date.now(),
        titulo: formData.get('titulo'),
        descripcion: formData.get('descripcion'),
        foto: formData.get('foto') ? formData.get('foto').name : null,
        fecha: new Date().toISOString(),
        pendiente_sync: true
    };
    
    let tickets = JSON.parse(localStorage.getItem(OFFLINE_TICKETS_KEY) || '[]');
    tickets.push(ticket);
    localStorage.setItem(OFFLINE_TICKETS_KEY, JSON.stringify(tickets));
    localStorage.setItem(PENDING_SYNC_KEY, 'true');
    
    Swal.fire({
        icon: 'info',
        title: 'Ticket guardado offline',
        text: 'El ticket se guardó localmente. Se sincronizará cuando recupere la conexión.',
        confirmButtonColor: '#1a3a5c'
    });
}

/**
 * Sincroniza tickets offline cuando hay conexión
 */
function sincronizarOffline() {
    if (!navigator.onLine) return;
    
    const pending = localStorage.getItem(PENDING_SYNC_KEY);
    if (!pending || pending !== 'true') return;
    
    let tickets = JSON.parse(localStorage.getItem(OFFLINE_TICKETS_KEY) || '[]');
    if (tickets.length === 0) {
        localStorage.removeItem(PENDING_SYNC_KEY);
        return;
    }
    
    // Filtrar solo los pendientes de sincronización
    const pendientes = tickets.filter(t => t.pendiente_sync);
    if (pendientes.length === 0) {
        localStorage.removeItem(PENDING_SYNC_KEY);
        return;
    }
    
    Swal.fire({
        title: 'Sincronizando tickets...',
        text: `Sincronizando ${pendientes.length} tickets guardados offline`,
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    // Procesar cada ticket pendiente
    let sincronizados = 0;
    let errores = 0;
    
    const procesarSiguiente = (index) => {
        if (index >= pendientes.length) {
            // Finalizar
            localStorage.setItem(OFFLINE_TICKETS_KEY, JSON.stringify(tickets.filter(t => !t.pendiente_sync)));
            if (sincronizados > 0) {
                localStorage.removeItem(PENDING_SYNC_KEY);
                Swal.fire({
                    icon: 'success',
                    title: '¡Sincronización completada!',
                    text: `Se sincronizaron ${sincronizados} tickets`,
                    confirmButtonColor: '#1a3a5c'
                }).then(() => {
                    location.reload();
                });
            } else {
                Swal.close();
            }
            return;
        }
        
        const ticket = pendientes[index];
        
        // Crear FormData para enviar
        const formData = new FormData();
        formData.append('action', 'crear_ticket');
        formData.append('titulo', ticket.titulo);
        formData.append('descripcion', ticket.descripcion);
        
        // Si tenía foto, intentar recuperarla (no se puede desde localStorage)
        // En producción, se podría almacenar la foto en base64
        
        fetch('api.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                sincronizados++;
                ticket.pendiente_sync = false;
                // Actualizar en localStorage
                const allTickets = JSON.parse(localStorage.getItem(OFFLINE_TICKETS_KEY) || '[]');
                const idx = allTickets.findIndex(t => t.id === ticket.id);
                if (idx !== -1) {
                    allTickets[idx].pendiente_sync = false;
                    localStorage.setItem(OFFLINE_TICKETS_KEY, JSON.stringify(allTickets));
                }
            } else {
                errores++;
                // Si falla, dejar pendiente
            }
        })
        .catch(() => {
            errores++;
        })
        .finally(() => {
            // Procesar siguiente
            procesarSiguiente(index + 1);
        });
    };
    
    procesarSiguiente(0);
}

/**
 * Actualiza el estado de conexión en la interfaz
 */
function actualizarEstadoConexion() {
    const estadoElement = document.getElementById('estado-internet');
    const textoElement = document.getElementById('estado-internet-texto');
    
    if (!estadoElement || !textoElement) return;
    
    if (navigator.onLine) {
        estadoElement.className = 'badge bg-success me-2';
        textoElement.textContent = 'Online';
        
        // Intentar sincronizar
        sincronizarOffline();
    } else {
        estadoElement.className = 'badge bg-danger me-2';
        textoElement.textContent = 'Offline';
        
        // Verificar si hay tickets pendientes
        const pending = localStorage.getItem(PENDING_SYNC_KEY);
        if (pending === 'true') {
            const tickets = JSON.parse(localStorage.getItem(OFFLINE_TICKETS_KEY) || '[]');
            const pendientes = tickets.filter(t => t.pendiente_sync);
            if (pendientes.length > 0) {
                estadoElement.innerHTML = `<i class="fas fa-database"></i> ${pendientes.length} pendientes`;
            }
        }
    }
}

/**
 * Verificar conexión con el servidor
 */
function verificarConexionServidor() {
    fetch('api.php?action=obtener_estadisticas', {
        method: 'GET',
        headers: {
            'Cache-Control': 'no-cache'
        }
    })
    .then(response => {
        if (response.ok) {
            actualizarEstadoConexion();
        } else {
            // Si el servidor responde pero no es OK, consideramos offline
            const estadoElement = document.getElementById('estado-internet');
            const textoElement = document.getElementById('estado-internet-texto');
            if (estadoElement && textoElement) {
                estadoElement.className = 'badge bg-danger me-2';
                textoElement.textContent = 'Offline';
            }
        }
    })
    .catch(() => {
        actualizarEstadoConexion();
    });
}

// Event Listeners
document.addEventListener('DOMContentLoaded', function() {
    // Estado inicial
    actualizarEstadoConexion();
    
    // Verificar conexión cada 30 segundos
    setInterval(verificarConexionServidor, 30000);
});

// Eventos de cambio de conexión
window.addEventListener('online', function() {
    actualizarEstadoConexion();
    // Intentar sincronizar inmediatamente
    setTimeout(sincronizarOffline, 1000);
});

window.addEventListener('offline', function() {
    actualizarEstadoConexion();
});

// Función para verificar si hay tickets offline pendientes
function hayTicketsOffline() {
    const pending = localStorage.getItem(PENDING_SYNC_KEY);
    return pending === 'true';
}

// Función para obtener tickets offline
function obtenerTicketsOffline() {
    return JSON.parse(localStorage.getItem(OFFLINE_TICKETS_KEY) || '[]');
}
