document.addEventListener('DOMContentLoaded', function() {
    // Función para mostrar notificaciones
    function showNotification(message, type = 'success', duration = 3000) {
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.textContent = message;
        document.body.appendChild(notification);

        // Quitar la notificación después de la duración especificada
        setTimeout(() => {
            notification.remove();
        }, duration);
    }

    // Función para notificar la llegada de un grupo
    window.notifyGroupArrival = function(groupCode, shipDate) {
        if (!confirm(`¿Estás seguro de que quieres notificar la llegada del grupo ${groupCode}?`)) {
            return;
        }

        showNotification('Preparando notificaciones...', 'info');

        const formData = new FormData();
        formData.append('groupCode', groupCode);
        formData.append('shipDate', shipDate);

        fetch('notify_arrival.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('¡URLs de notificación generadas!', 'success');
                
                // Abrir las URLs de WhatsApp en nuevas pestañas
                if (data.adminUrl) {
                    window.open(data.adminUrl, '_blank');
                }
                if (data.groupUrl) {
                    // Pequeña demora para evitar que el navegador bloquee las ventanas emergentes
                    setTimeout(() => {
                        window.open(data.groupUrl, '_blank');
                    }, 500);
                }
            } else {
                showNotification(data.error || 'Error al generar las notificaciones.', 'error');
            }
        })
        .catch(error => {
            console.error('Error en la notificación:', error);
            showNotification('Error de conexión al notificar.', 'error');
        });
    };

    // Función para cambiar el estado de un envío
    function updateShipmentStatus(id, status) {
        fetch('admin.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: `update_status=1&id=${id}&status=${status}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const statusCell = document.querySelector(`#shipment-${id} .status-cell`);
                if (statusCell) {
                    statusCell.textContent = status.charAt(0).toUpperCase() + status.slice(1);
                    statusCell.className = 'status-cell ' + status;
                }
                showNotification('Estado actualizado correctamente', 'success');
            } else {
                showNotification('Error al actualizar el estado', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error al actualizar el estado', 'error');
        });
    }

    // Función para mostrar/ocultar los envíos de un grupo
    function toggleGroupShipments(groupCode) {
        const groupContent = document.querySelector(`#group-${groupCode}-content`);
        if (groupContent) {
            groupContent.classList.toggle('hidden');
            const toggleIcon = document.querySelector(`#group-${groupCode}-toggle i`);
            if (toggleIcon) {
                toggleIcon.classList.toggle('fa-chevron-down');
                toggleIcon.classList.toggle('fa-chevron-up');
            }
        }
    }

    // Event Listeners
    document.querySelectorAll('.status-select').forEach(select => {
        select.addEventListener('change', function() {
            const shipmentId = this.getAttribute('data-shipment-id');
            updateShipmentStatus(shipmentId, this.value);
        });
    });

    document.querySelectorAll('.group-toggle').forEach(button => {
        button.addEventListener('click', function() {
            const groupCode = this.getAttribute('data-group-code');
            toggleGroupShipments(groupCode);
        });
    });
});
