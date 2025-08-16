// Función para mostrar notificaciones
function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.textContent = message;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.remove();
    }, 3000);
}

// Función para actualizar el estado de un envío
function updateStatus(select) {
    const id = select.dataset.id;
    const status = select.value;
    
    fetch('admin.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `update_status=1&id=${id}&status=${status}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Estado actualizado correctamente');
            if (status === 'delivered') {
                select.closest('tr').remove();
            }
        } else {
            showNotification('Error al actualizar el estado', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error al actualizar el estado', 'error');
    });
}

// Función para eliminar un envío
function deleteShipment(id) {
    if (confirm('¿Estás seguro de que quieres eliminar este envío?')) {
        const formData = new FormData();
        formData.append('delete_shipment', '1');
        formData.append('id', id);

        fetch('admin.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Error en la respuesta del servidor');
            }
            return response.text();
        })
        .then(() => {
            const row = document.querySelector(`tr[data-id="${id}"]`);
            if (row) {
                row.remove();
                showNotification('Envío eliminado correctamente');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error al eliminar el envío', 'error');
        });
    }
}

// Función para habilitar la edición
function makeEditable(button) {
    const row = button.closest('tr');
    row.classList.add('editing');
    
    // Mostrar botón de guardar y ocultar botón de editar
    row.querySelector('.save-btn').style.display = 'inline-block';
    button.style.display = 'none';
}

// Función para guardar los cambios
function saveShipment(button) {
    const row = button.closest('tr');
    const id = row.dataset.id;
    const formData = new FormData();
    
    // Recopilar todos los valores de los campos editables
    row.querySelectorAll('input, select').forEach(input => {
        formData.append(input.name, input.value);
    });
    
    formData.append('edit_shipment', '1');
    formData.append('id', id);

    fetch('admin.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Cambios guardados correctamente');
            
            // Actualizar los valores mostrados
            row.querySelectorAll('.editable').forEach(cell => {
                const input = cell.querySelector('input');
                const display = cell.querySelector('.display-text');
                
                if (input && display) {
                    if (input.name === 'weight') {
                        display.textContent = input.value + ' kg';
                    } else {
                        display.textContent = input.value;
                    }
                }
            });
            
            // Actualizar el saldo cuando se cambia el pago adelantado
            const advancePaymentInput = row.querySelector('input[name="advance_payment"]');
            const balanceCell = row.querySelector('td:nth-child(11) .display-text'); // Columna de saldo
            const totalPriceCell = row.querySelector('td:nth-child(5) .display-text'); // Columna de precio total
            
            if (advancePaymentInput && balanceCell && totalPriceCell) {
                const totalPriceText = totalPriceCell.textContent.replace(' kg', '').replace(' XAF', '').replace(/,/g, '');
                const totalPrice = parseFloat(totalPriceText) || 0;
                const advancePayment = parseFloat(advancePaymentInput.value) || 0;
                const balance = totalPrice - advancePayment;
                
                // Formatear el saldo con separadores de miles
                balanceCell.textContent = balance.toLocaleString('es-ES', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' XAF';
            }

            // Actualizar los nombres y teléfonos del remitente y destinatario
            const senderCell = row.querySelector('td:nth-child(2)');
            const receiverCell = row.querySelector('td:nth-child(3)');
            
            if (senderCell) {
                const senderName = senderCell.querySelector('input[name="sender_name"]').value;
                const senderPhone = senderCell.querySelector('input[name="sender_phone"]').value;
                senderCell.querySelector('.display-text').innerHTML = `${senderName}<br><small>${senderPhone}</small>`;
            }
            
            if (receiverCell) {
                const receiverName = receiverCell.querySelector('input[name="receiver_name"]').value;
                const receiverPhone = receiverCell.querySelector('input[name="receiver_phone"]').value;
                receiverCell.querySelector('.display-text').innerHTML = `${receiverName}<br><small>${receiverPhone}</small>`;
            }
            
            // Salir del modo edición
            row.classList.remove('editing');
            row.querySelector('.edit-btn').style.display = 'inline-block';
            row.querySelector('.save-btn').style.display = 'none';
        } else {
            showNotification('Error al guardar los cambios: ' + (data.error || 'Error desconocido'), 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error al guardar los cambios', 'error');
    });
}

// Inicializar los eventos cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    // Asignar eventos a los botones existentes
    document.querySelectorAll('.edit-btn').forEach(button => {
        button.onclick = () => makeEditable(button);
    });
    
    document.querySelectorAll('.save-btn').forEach(button => {
        button.onclick = () => saveShipment(button);
    });
});
