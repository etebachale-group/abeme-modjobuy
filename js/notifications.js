function notifyGroupArrival(groupCode, formattedDate) {
    // Obtener el botón que fue clickeado
    const button = event.currentTarget;
    const totalPrice = button.dataset.total;

    // Mostrar un mensaje de confirmación
    if (confirm(`¿Deseas notificar la llegada del grupo de envíos del ${formattedDate}?`)) {
        // Hacer la petición AJAX para actualizar los estados
        fetch('update_group_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                group_code: groupCode,
                status: 'arrived'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Actualizar la interfaz
                alert(`Llegada notificada con éxito para el grupo del ${formattedDate}`);
                // Refrescar la página o actualizar la UI según necesites
                location.reload();
            } else {
                alert('Error al notificar la llegada');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al procesar la solicitud');
        });
    }
}
