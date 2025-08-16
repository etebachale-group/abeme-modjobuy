// Función para inicializar el gráfico de beneficios
document.addEventListener('DOMContentLoaded', function() {
    // Datos de los socios y sus porcentajes
    const partners = {
        'FERNANDO CHALE': 18,
        'MARIA CARMEN NSUE': 18,
        'GENEROSA ABEME': 30,
        'MARIA ISABEL': 8,
        'CAJA': 16,
        'FONDOS DE SOCIOS': 10
    };

    // Inicializar el gráfico de beneficios
    initBenefitsChart(partners);

    // Configurar el modal
    const modal = document.getElementById('detailsModal');
    const closeBtn = document.querySelector('.close');
    
    if (closeBtn) {
        closeBtn.onclick = closeModal;
    }

    window.onclick = function(event) {
        if (event.target == modal) {
            closeModal();
        }
    }
    
    // Cerrar modal con tecla Escape
    window.onkeydown = function(event) {
        if (event.key === 'Escape' && modal.classList.contains('show')) {
            closeModal();
        }
    }

    // Función para cerrar el modal con animación
    function closeModal() {
        const modal = document.getElementById('detailsModal');
        if (!modal) return;
        
        modal.classList.remove('show');
        setTimeout(() => {
            modal.style.display = 'none';
        }, 200); // Esperar a que termine la animación
    }
    
    // Prevenir el cierre del modal al hacer clic dentro
    const modalContent = document.querySelector('.modal-content');
    if (modalContent) {
        modalContent.addEventListener('click', function(event) {
            event.stopPropagation();
        });
    }
});

// Función para inicializar el gráfico de beneficios
function initBenefitsChart(partners) {
    const ctx = document.getElementById('benefitsChart').getContext('2d');
    const partnerColors = {
        'FERNANDO CHALE': '#1976d2',      // Azul principal
        'MARIA CARMEN NSUE': '#2196f3',   // Azul secundario
        'GENEROSA ABEME': '#0d47a1',      // Azul oscuro
        'MARIA ISABEL': '#64b5f6',        // Azul claro
        'CAJA': '#90caf9',                // Azul más claro
        'FONDOS DE SOCIOS': '#bbdefb'     // Azul muy claro
    };

    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: Object.keys(partners),
            datasets: [{
                data: Object.values(partners),
                backgroundColor: Object.keys(partners).map(partner => partnerColors[partner]),
                borderColor: 'white',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        font: {
                            family: 'Roboto',
                            size: 12
                        },
                        color: '#333333',
                        padding: 15,
                        boxWidth: 12
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.raw || 0;
                            return `${label}: ${value}%`;
                        }
                    }
                }
            },
            cutout: '60%',
            animation: {
                animateRotate: true,
                animateScale: true
            }
        }
    });
}

// Función para mostrar detalles del socio
function viewDetails(partnerName) {
    const modal = document.getElementById('detailsModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalBody = document.getElementById('modalBody');
    if (!modal || !modalTitle || !modalBody) return;

    modalTitle.textContent = `Detalles de ${partnerName}`;
    modalBody.innerHTML = `
        <div class="partner-details">
            <div class="loading-spinner">
                <i class="fas fa-spinner fa-spin"></i>
                <span>Cargando datos del socio...</span>
            </div>
        </div>`;

    // Mostrar el modal con animación
    modal.style.display = 'block';
    // Forzar un reflow
    modal.offsetHeight;
    modal.classList.add('show');

    // Primero verificamos y actualizamos la estructura de la tabla
    fetch('api/update_table_structure.php')
        .then(response => response.json())
        .then(() => {
            // Luego verificamos las tablas completas
            return fetch('api/check_partner_tables.php');
        })
        .then(response => response.json())
        .then(() => {
            // Finalmente obtenemos los detalles del socio
            return fetch(`api/partner_details.php?partner=${encodeURIComponent(partnerName)}`);
        })
        .then(response => response.json())
        .then(details => {
            if (details.error) {
                modalBody.innerHTML = `
                    <div class="partner-details">
                        <div class="error-message">
                            <i class="fas fa-exclamation-circle"></i>
                            <span>${details.error}</span>
                        </div>
                    </div>`;
                return;
            }

            const formattedDate = details.joinDate ? new Date(details.joinDate).toLocaleDateString('es-ES', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            }) : 'No especificado';

            const lastPaymentDate = details.lastPayment ? new Date(details.lastPayment).toLocaleDateString('es-ES', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            }) : 'No especificado';

            modalBody.innerHTML = `
                <div class="partner-details">
                    <div class="partner-header">
                        <h3>${partnerName}</h3>
                    </div>
                    <div class="partner-summary">
                        <div class="summary-card">
                            <h4>Ganancias Totales</h4>
                            <p>XAF ${Number(details.totalEarnings).toLocaleString('es-ES')}</p>
                        </div>
                        <div class="summary-card">
                            <h4>Balance Actual</h4>
                            <p>XAF ${Number(details.currentBalance).toLocaleString('es-ES')}</p>
                        </div>
                    </div>
                </div>

                <div class="payment-history">
                    <h4>Historial de Pagos</h4>
                    <div class="table-responsive">
                        <table id="paymentsHistory" class="table">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Monto</th>
                                    <th>Estado</th>
                                    <th>Balance Anterior</th>
                                    <th>Nuevo Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${details.paymentHistory.payments.map(payment => `
                                    <tr class="${payment.confirmed ? 'confirmed' : 'pending'}">
                                        <td>${new Date(payment.payment_date).toLocaleDateString('es-ES')}</td>
                                        <td>XAF ${Number(payment.amount).toLocaleString('es-ES')}</td>
                                        <td><span class="status-badge ${payment.confirmed ? 'confirmed' : 'pending'}">${payment.confirmed ? 'Confirmado' : 'Pendiente'}</span></td>
                                        <td>XAF ${Number(payment.previous_balance).toLocaleString('es-ES')}</td>
                                        <td>XAF ${Number(payment.new_balance).toLocaleString('es-ES')}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
        })
        .catch(() => {
            modalBody.innerHTML = `<div class="partner-details"><span style='color:red;'>Error al cargar los datos</span></div>`;
        });

    modal.style.display = 'block';
}

// Función para actualizar las estadísticas en tiempo real
function updateStatistics() {
    // Esta función podría ser utilizada para actualizar las estadísticas en tiempo real
    // si se implementa una funcionalidad de actualización automática
    console.log('Actualizando estadísticas...');
}

// Función para exportar datos
function exportData() {
    // Esta función podría ser utilizada para exportar los datos de beneficios
    // a un archivo CSV o PDF
    alert('Funcionalidad de exportación en desarrollo');
}

// Función para solicitar pago y generar PDF
function requestPayment(partnerName, amount) {
    // 1. Generar PDF de solicitud de pago
    const pdfUrl = `generate_payment_request.php?partner_name=${encodeURIComponent(partnerName)}&amount=${amount}`;
    window.open(pdfUrl, '_blank');

    // 2. Abrir WhatsApp Web con mensaje pre-rellenado
    const whatsappNumber = '240222374204'; // Sin el '+'
    const message = `Hola, soy ${partnerName}. Adjunto mi solicitud de pago de beneficios por un monto de XAF ${amount.toLocaleString('es-GQ', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}. Por favor, revisen el PDF adjunto.`;
    const whatsappUrl = `https://wa.me/${whatsappNumber}?text=${encodeURIComponent(message)}`;
    
    // Dar un pequeño retraso para que el PDF tenga tiempo de empezar a descargarse
    setTimeout(() => {
        window.open(whatsappUrl, '_blank');
    }, 1000); // 1 segundo de retraso
}

// Función para confirmar pago
function confirmPayment(partnerName, amountPaid) {
    if (!confirm(`¿Estás seguro de que quieres confirmar el pago de XAF ${amountPaid.toLocaleString('es-GQ', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} a ${partnerName}?`)) {
        return; // User cancelled
    }

    // Make an AJAX request to confirm_payment.php
    fetch('confirm_payment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `partner_name=${encodeURIComponent(partnerName)}&amount_paid=${amountPaid}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload(); // Reload the page to show updated balances
        } else {
            alert('Error al confirmar el pago: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Ocurrió un error al procesar la solicitud.');
    });
}