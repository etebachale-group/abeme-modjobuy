// Función para inicializar el gráfico de beneficios
document.addEventListener('DOMContentLoaded', function() {
    // Datos de los socios y sus porcentajes (preferir window.partners si viene del servidor)
    const partners = (typeof window !== 'undefined' && window.partners)
        ? window.partners
        : {
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

    // Obtener solo saldos vía API unificada
    fetch(`api/get_wallet_balances.php?partner=${encodeURIComponent(partnerName)}`)
        .then(async (response) => {
            const txt = await response.text();
            let j; try { j = JSON.parse(txt); } catch { throw new Error(`Respuesta no válida: ${txt.slice(0,200)}`); }
            if (!response.ok || !j.success) {
                throw new Error(j.message || 'No se pudo obtener saldos');
            }
            const d = j.data || { total_earnings: 0, current_balance: 0 };
            modalBody.innerHTML = `
                <div class="partner-details">
                    <div class="partner-header">
                        <h3>${partnerName}</h3>
                    </div>
                    <div class="partner-summary">
                        <div class="summary-card">
                            <h4>Ganancias Totales</h4>
                            <p>XAF ${Number(d.total_earnings || 0).toLocaleString('es-ES')}</p>
                        </div>
                        <div class="summary-card">
                            <h4>Balance Actual</h4>
                            <p>XAF ${Number(d.current_balance || 0).toLocaleString('es-ES')}</p>
                        </div>
                    </div>
                </div>`;
        })
        .catch((e) => {
            console.error(e);
            modalBody.innerHTML = `<div class="partner-details"><span style='color:red;'>${e.message || 'Error al cargar los datos'}</span></div>`;
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

// Función para depositar al monedero del socio (convierte saldo pendiente en saldo de monedero)
function confirmPayment(partnerName, amountPaid) {
    if (!confirm(`¿Depositar XAF ${amountPaid.toLocaleString('es-GQ', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} al monedero de ${partnerName}?`)) {
        return; // cancelado
    }

    fetch('api/deposit_to_wallet.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' },
        body: `partner_name=${encodeURIComponent(partnerName)}&amount=${amountPaid}&notes=${encodeURIComponent('Depósito desde Beneficios')}`,
        credentials: 'same-origin',
        cache: 'no-cache'
    })
    .then(async (res) => {
        const txt = await res.text();
        let data = null;
        try { data = JSON.parse(txt); } catch {}
        if (!res.ok || !data || !data.success) {
            const msg = data && data.message ? data.message : (txt ? txt.slice(0,200) : 'No se pudo depositar');
            throw new Error(`HTTP ${res.status} ${res.statusText} | ${msg}`);
        }
        return data;
    })
    .then(async () => {
        // Refrescar solo el saldo del socio afectado
        try {
            const res2 = await fetch(`api/get_wallet_balances.php?partner=${encodeURIComponent(partnerName)}`, { credentials: 'same-origin', cache: 'no-cache' });
            const j2 = await res2.json();
            if (j2 && j2.success && j2.data) {
                const fmt = (n) => 'XAF ' + Number(n).toLocaleString('es-GQ', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                const nodes = document.querySelectorAll(`[data-partner="${partnerName.replace(/"/g, '\\"')}"]`);
                nodes.forEach(el => { el.textContent = fmt(j2.data.current_balance || 0); });
                alert('Depósito realizado al monedero');
                return;
            }
        } catch (_) { /* fallback to reload below */ }
        // Si falla la actualización en sitio, recargar la página
        location.reload();
    })
    .catch(err => {
        console.error(err);
        alert('Error: ' + (err && err.message ? err.message : 'Error de red al depositar'));
    });
}