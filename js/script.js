// Función para buscar envíos
function trackShipment() {
    const code = document.getElementById('tracking-code').value.trim();
    const resultDiv = document.getElementById('tracking-result');
    const resultBody = document.getElementById('tracking-result-body');
    
    if (!code) {
        alert("Por favor, ingresa un código de seguimiento.");
        return;
    }
    
    // Hacer una solicitud AJAX al servidor
    fetch(`track_shipment.php?code=${code}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                resultBody.innerHTML = `
                    <tr>
                        <td>${data.shipment.code}</td>
                        <td>${data.shipment.product}</td>
                        <td>${data.shipment.weight} kg</td>
                        <td>€${data.shipment.sale_price}</td>
                        <td>${getStatusBadge(data.shipment.status)}</td>
                    </tr>
                `;
                resultDiv.style.display = 'block';
            } else {
                resultBody.innerHTML = `
                    <tr>
                        <td colspan="5" style="text-align: center;">${data.message}</td>
                    </tr>
                `;
                resultDiv.style.display = 'block';
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
}

// Función para obtener el badge de estado
function getStatusBadge(status) {
    const statusMap = {
        'pending': {class: 'status-pending', text: 'Pendiente'},
        'ontheway': {class: 'status-ontheway', text: 'En Camino'},
        'arrived': {class: 'status-arrived', text: 'Llegada'},
        'delayed': {class: 'status-delayed', text: 'Retraso'},
        'delivered': {class: 'status-delivered', text: 'Entregado'}
    };
    
    return `<span class="status ${statusMap[status].class}">${statusMap[status].text}</span>`;
}

// Función para calcular costos y beneficios
function calculateCosts() {
    const weight = parseFloat(document.getElementById('weight').value) || 0;
    const costPerKg = parseFloat(document.getElementById('cost-per-kg').value) || 0;
    const salePrice = parseFloat(document.getElementById('sale-price').value) || 0;
    
    const shippingCost = weight * costPerKg;
    const profit = salePrice - shippingCost;

    document.getElementById('shipping-cost').value = shippingCost.toFixed(2);
    document.getElementById('profit').value = profit.toFixed(2);
}

// Función para actualizar estado de envío
function updateStatus(selectElement) {
    const id = selectElement.getAttribute('data-id');
    const status = selectElement.value;
    
    fetch('update_status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ id, status })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert("Estado actualizado correctamente");
        } else {
            alert("Error al actualizar el estado");
            // Revertir el cambio
            selectElement.value = selectElement.dataset.originalValue;
        }
    });
}

// Función para eliminar envío
function deleteShipment(id) {
    if (confirm("¿Estás seguro de que deseas eliminar este envío?")) {
        fetch('delete_shipment.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ id })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert("Envío eliminado correctamente");
                // Recargar la tabla
                location.reload();
            } else {
                alert("Error al eliminar el envío");
            }
        });
    }
}

// Inicializar la página
document.addEventListener('DOMContentLoaded', function() {
    // Establecer fechas por defecto en el formulario de admin
    if (document.getElementById('ship-date')) {
        const today = new Date().toISOString().split('T')[0];
        const nextWeek = new Date();
        nextWeek.setDate(nextWeek.getDate() + 7);
        const nextWeekFormatted = nextWeek.toISOString().split('T')[0];
        
        document.getElementById('ship-date').value = today;
        document.getElementById('est-date').value = nextWeekFormatted;
    }
    
    // Guardar valor original de los selects de estado
    document.querySelectorAll('.status-select').forEach(select => {
        select.dataset.originalValue = select.value;
    });
    
    // Mostrar mensaje de contacto si existe
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('contact') === 'success') {
        alert("Gracias por contactarnos! Tu mensaje ha sido enviado a dreammotivationig@gmail.com");
    }

    // Lógica para el menú de hamburguesa
    const menuToggle = document.getElementById('menu-toggle');
    const nav = document.querySelector('nav ul');

    if (menu
});