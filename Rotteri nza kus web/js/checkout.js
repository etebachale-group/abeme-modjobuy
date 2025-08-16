document.addEventListener('DOMContentLoaded', function() {
    // Payment method selection
    const paymentMethods = document.querySelectorAll('.payment-method');
    const paymentMethodInput = document.getElementById('paymentMethodInput');
    const paymentDetailsContainer = document.getElementById('paymentDetails');
    const transferDetails = document.getElementById('transferDetails');
    const cardDetails = document.getElementById('cardDetails');

    // Function to show/hide payment details sections
    function showPaymentDetails(method) {
        if (method === 'transfer') {
            transferDetails.classList.add('active');
            cardDetails.classList.remove('active');
        } else if (method === 'card') {
            transferDetails.classList.remove('active');
            cardDetails.classList.add('active');
            // Initialize payment gateway SDK here if not already done
            // For example, if using Stripe Elements:
            // if (!window.stripeElementsInitialized) {
            //     initializeStripeElements();
            //     window.stripeElementsInitialized = true;
            // }
        }
    }
    
    paymentMethods.forEach(method => {
        method.addEventListener('click', function() {
            paymentMethods.forEach(m => m.classList.remove('active'));
            this.classList.add('active');
            const selectedMethod = this.getAttribute('data-method');
            paymentMethodInput.value = selectedMethod;
            showPaymentDetails(selectedMethod);
        });
    });

    // Initialize payment details display based on default selected method
    showPaymentDetails(paymentMethodInput.value);

    // Client-side form validation
    const checkoutForm = document.getElementById('checkoutForm');
    if (checkoutForm) {
        checkoutForm.addEventListener('submit', function(event) {
            let isValid = true;
            const formFields = this.querySelectorAll('input[required], textarea[required], select[required]');

            formFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.classList.add('is-invalid');
                    // Optionally, display a specific error message next to the field
                } else {
                    field.classList.remove('is-invalid');
                }
            });

            // Specific email validation
            const emailField = this.querySelector('input[name="email"]');
            if (emailField && !/^[\w.-]+@[\w.-]+\.\w+$/.test(emailField.value)) {
                isValid = false;
                emailField.classList.add('is-invalid');
            }

            // If card payment is selected, ensure card details are valid (conceptual)
            const selectedPaymentMethod = paymentMethodInput.value;
            if (selectedPaymentMethod === 'card') {
                // This is where you'd typically validate card details via a payment gateway SDK
                // For example, with Stripe:
                // if (!stripeTokenGenerated) { // Assume stripeTokenGenerated is a flag set by Stripe.js
                //     isValid = false;
                //     SafeUtils.showError('Por favor, complete los detalles de la tarjeta.');
                // }
            }

            if (!isValid) {
                event.preventDefault(); // Prevent form submission
                SafeUtils.showError('Por favor, complete todos los campos requeridos y corrija los errores.');
            }
        });

        // Remove validation class on input
        checkoutForm.querySelectorAll('input, textarea, select').forEach(field => {
            field.addEventListener('input', function() {
                this.classList.remove('is-invalid');
            });
            field.addEventListener('change', function() {
                this.classList.remove('is-invalid');
            });
        });
    }

    // Conceptual function for payment gateway initialization (e.g., Stripe Elements)
    // function initializeStripeElements() {
    //     const stripe = Stripe('YOUR_STRIPE_PUBLISHABLE_KEY'); // Replace with your actual key
    //     const elements = stripe.elements();
    //     const card = elements.create('card');
    //     card.mount('#card-element');

    //     card.addEventListener('change', function(event) {
    //         const displayError = document.getElementById('card-errors');
    //         if (event.error) {
    //             displayError.textContent = event.error.message;
    //         } else {
    //             displayError.textContent = '';
    //         }
    //     });

    //     // Handle form submission for card payment
    //     const form = document.getElementById('checkoutForm');
    //     form.addEventListener('submit', function(event) {
    //         event.preventDefault(); // Prevent default form submission

    //         // If payment method is card, tokenize it
    //         if (paymentMethodInput.value === 'card') {
    //             stripe.createToken(card).then(function(result) {
    //                 if (result.error) {
    //                     // Inform the user if there was an error
    //                     const errorElement = document.getElementById('card-errors');
    //                     errorElement.textContent = result.error.message;
    //                     SafeUtils.showError(result.error.message);
    //                 } else {
    //                     // Send the token to your server
    //                     // Add the token to a hidden input and submit the form
    //                     const hiddenInput = document.createElement('input');
    //                     hiddenInput.setAttribute('type', 'hidden');
    //                     hiddenInput.setAttribute('name', 'stripeToken');
    //                     hiddenInput.setAttribute('value', result.token.id);
    //                     form.appendChild(hiddenInput);
    //                     form.submit(); // Submit the form with the token
    //                 }
    //             });
    //         } else {
    //             form.submit(); // Submit form for other payment methods
    //         }
    //     });
    // }