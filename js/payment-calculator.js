// Función para calcular el precio con descuento
function calculateDiscountedPrice() {
    const discountInput = document.getElementById('discount-percentage');
    const weightInput = document.getElementById('weight');
    
    if (discountInput && weightInput) {
        // Calcular el precio total basado en el peso (6500 XAF por kg)
        const weight = parseFloat(weightInput.value) || 0;
        const totalPrice = weight * 6500;
        
        // Obtener el porcentaje de descuento
        const discountPercentage = parseFloat(discountInput.value) || 0;
        
        // Validar que el porcentaje esté entre 0 y 100
        if (discountPercentage < 0 || discountPercentage > 100) {
            // Mostrar error en la interfaz si es necesario
            return { totalPrice: totalPrice, discountAmount: 0, discountedPrice: totalPrice };
        }
        
        // Calcular el monto de descuento
        const discountAmount = totalPrice * (discountPercentage / 100);
        
        // Calcular el precio con descuento
        const discountedPrice = totalPrice - discountAmount;
        
        return { totalPrice: totalPrice, discountAmount: discountAmount, discountedPrice: discountedPrice };
    }
    
    return { totalPrice: 0, discountAmount: 0, discountedPrice: 0 };
}

// Función para actualizar la visualización del descuento
function updateDiscountDisplay() {
    const result = calculateDiscountedPrice();
    
    // Actualizar los elementos de visualización
    const basePriceElement = document.getElementById('base-price');
    const discountAmountElement = document.getElementById('discount-amount');
    const discountedPriceElement = document.getElementById('discounted-price');
    
    if (basePriceElement) {
        basePriceElement.textContent = result.totalPrice.toLocaleString('es-ES', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' XAF';
    }
    
    if (discountAmountElement) {
        discountAmountElement.textContent = result.discountAmount.toLocaleString('es-ES', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' XAF';
    }
    
    if (discountedPriceElement) {
        discountedPriceElement.textContent = result.discountedPrice.toLocaleString('es-ES', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' XAF';
    }
}

// Función para calcular el saldo en tiempo real
function calculateBalance() {
    const advancePaymentInput = document.getElementById('advance_payment');
    const weightInput = document.getElementById('weight');
    const balanceDisplay = document.getElementById('balance-display');
    
    if (advancePaymentInput && weightInput && balanceDisplay) {
        // Calcular el precio total basado en el peso (6500 XAF por kg)
        const weight = parseFloat(weightInput.value) || 0;
        const totalPrice = weight * 6500;
        
        // Obtener el pago adelantado
        const advancePayment = parseFloat(advancePaymentInput.value) || 0;
        
        // Calcular el saldo
        const balance = totalPrice - advancePayment;
        
        // Mostrar el saldo
        balanceDisplay.textContent = balance.toLocaleString('es-ES', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' XAF';
        
        // Actualizar el mensaje de WhatsApp si existe
        updateWhatsAppMessage(totalPrice, advancePayment, balance);
    }
}

// Función para actualizar el mensaje de WhatsApp
function updateWhatsAppMessage(totalPrice, advancePayment, balance) {
    const whatsappMessage = document.getElementById('whatsapp-message');
    if (whatsappMessage) {
        let message = "¡Hola! Tu envío ha sido registrado con éxito.\n\n";
        message += "Detalles del pago:\n";
        message += "Precio total: " + totalPrice.toLocaleString('es-ES', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + " XAF\n";
        
        // Obtener el porcentaje de descuento
        const discountInput = document.getElementById('discount-percentage');
        const discountPercentage = discountInput ? parseFloat(discountInput.value) || 0 : 0;
        
        if (discountPercentage > 0) {
            const discountAmount = totalPrice * (discountPercentage / 100);
            const discountedPrice = totalPrice - discountAmount;
            message += "Descuento (" + discountPercentage + "%): -" + discountAmount.toLocaleString('es-ES', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + " XAF\n";
            message += "Precio con descuento: " + discountedPrice.toLocaleString('es-ES', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + " XAF\n";
        }
        
        if (advancePayment > 0) {
            message += "Pago adelantado: " + advancePayment.toLocaleString('es-ES', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + " XAF\n";
            message += "Saldo pendiente: " + balance.toLocaleString('es-ES', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + " XAF\n";
        }
        
        message += "\nPuedes ver y descargar tu ticket en el siguiente enlace: [LINK]";
        whatsappMessage.textContent = message;
    }
}

// Inicializar los eventos cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    const advancePaymentInput = document.getElementById('advance_payment');
    const weightInput = document.getElementById('weight');
    const discountInput = document.getElementById('discount-percentage');
    
    if (advancePaymentInput) {
        advancePaymentInput.addEventListener('input', calculateBalance);
    }
    
    if (weightInput) {
        weightInput.addEventListener('input', function() {
            calculateBalance();
            updateDiscountDisplay();
        });
    }
    
    if (discountInput) {
        discountInput.addEventListener('input', function() {
            // Validar que el valor esté entre 0 y 100
            const value = parseFloat(discountInput.value) || 0;
            if (value < 0) {
                discountInput.value = 0;
            } else if (value > 100) {
                discountInput.value = 100;
            }
            updateDiscountDisplay();
        });
    }
    
    // Calcular el saldo inicial y actualizar la visualización del descuento
    calculateBalance();
    updateDiscountDisplay();
});