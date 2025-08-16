let currentSort = 'DESC';

function toggleSort() {
    currentSort = currentSort === 'DESC' ? 'ASC' : 'DESC';
    updateRecentShipments();
}

function updateRecentShipments() {
    fetch(`get_recent_shipments.php?sort=${currentSort}`)
        .then(response => response.json())
        .then(data => {
            const tbody = document.getElementById('recent-shipments');
            tbody.innerHTML = '';
            
            data.shipments.forEach(shipment => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${shipment.weight} kg</td>
                    <td>${formatDate(shipment.ship_date)}</td>
                    <td>${formatPrice(shipment.sale_price)} XAF</td>
                    <td>${formatPrice(shipment.advance_payment)} XAF</td>
                    <td>${formatPrice(shipment.balance)} XAF</td>
                    <td>${shipment.status_badge}</td>
                `;
                tbody.appendChild(tr);
            });
        })
        .catch(error => console.error('Error:', error));
}

function trackShipment() {
    const code = document.getElementById('tracking-code').value;
    if (!code) {
        alert('Por favor, ingresa un código de seguimiento');
        return;
    }

    fetch(`track_shipment.php?code=${encodeURIComponent(code)}`, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
        .then(response => {
            if (!response.ok) {
                throw new Error('Error en la respuesta del servidor');
            }
            return response.json();
        })
        .then(data => {
            const tbody = document.getElementById('tracking-result-body');
            tbody.innerHTML = '';

            if (data.error) {
                alert(data.error);
                return;
            }

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${data.code}</td>
                <td>${data.product}</td>
                <td>${data.weight} kg</td>
                <td>${formatPrice(data.sale_price)} XAF</td>
                <td>${data.status_badge}</td>
            `;
            tbody.appendChild(tr);
            
            // Mostrar la sección de resultados
            document.getElementById('tracking-result').style.display = 'block';
        })
        .catch(error => {
            console.error('Error:', error);
            alert('No se encontró ningún envío con ese código. Por favor, verifica e intenta de nuevo.');
        });
}

function formatDate(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleDateString('es-ES');
}

function formatPrice(price) {
    return new Intl.NumberFormat('es-ES').format(price);
}
