// Función para notificar la llegada de un grupo
function notifyGroupArrival(groupCode, shipDate) {
    // Mostrar un mensaje de carga
    const notification = document.createElement('div');
    notification.className = 'notification info';
    notification.textContent = 'Enviando notificaciones...';
    document.body.appendChild(notification);
    
    // Realizar la solicitud AJAX
    fetch('notify_arrival.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `groupCode=${encodeURIComponent(groupCode)}&shipDate=${encodeURIComponent(shipDate)}`
    })
    .then(response => response.json())
    .then(data => {
        // Remover el mensaje de carga
        notification.remove();
        
        if (data.success) {
            // Mostrar notificación de éxito
            const successNotification = document.createElement('div');
            successNotification.className = 'notification success';
            successNotification.innerHTML = `
                Notificaciones enviadas correctamente.<br>
                <a href="${data.adminUrl}" target="_blank" style="color: white; text-decoration: underline;">Mensaje al administrador</a><br>
                <a href="${data.groupUrl}" target="_blank" style="color: white; text-decoration: underline;">Mensaje al grupo público</a>
            `;
            document.body.appendChild(successNotification);
            
            // Remover la notificación después de 5 segundos
            setTimeout(() => {
                successNotification.remove();
            }, 5000);
        } else {
            // Mostrar notificación de error
            const errorNotification = document.createElement('div');
            errorNotification.className = 'notification error';
            errorNotification.textContent = data.error || 'Error al enviar notificaciones';
            document.body.appendChild(errorNotification);
            
            // Remover la notificación después de 5 segundos
            setTimeout(() => {
                errorNotification.remove();
            }, 5000);
        }
    })
    .catch(error => {
        // Remover el mensaje de carga
        notification.remove();
        
        // Mostrar notificación de error
        const errorNotification = document.createElement('div');
        errorNotification.className = 'notification error';
        errorNotification.textContent = 'Error de conexión al enviar notificaciones';
        document.body.appendChild(errorNotification);
        
        // Remover la notificación después de 5 segundos
        setTimeout(() => {
            errorNotification.remove();
        }, 5000);
    });
}