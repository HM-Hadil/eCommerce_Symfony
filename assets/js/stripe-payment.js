// This is the Stripe payment Javascript file to be placed in public/js/stripe-payment.js

document.addEventListener('DOMContentLoaded', function() {
    // Initialize Stripe
    // Assumes stripePublicKey is defined in the Twig template before this script is included
    const stripe = Stripe(stripePublicKey);
    const checkoutForm = document.getElementById('checkout-form');
    const paymentButton = document.getElementById('payment-button');
    const paymentErrors = document.getElementById('payment-errors');

    if (checkoutForm) {
        checkoutForm.addEventListener('submit', function(event) {
            event.preventDefault();

            // Disable the submit button to prevent multiple clicks
            paymentButton.disabled = true;
            paymentButton.textContent = 'Traitement en cours...';

            // Hide any previous error message
            paymentErrors.style.display = 'none';

            // Create a FormData object from the form
            const formData = new FormData(checkoutForm);

            // Send the form data to the server
            fetch(checkoutForm.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => {
                 // Check for non-ok responses (e.g., 400, 500)
                 if (!response.ok) {
                     // Parse JSON error response
                     return response.json().then(data => {
                         const errorMessage = data.error || 'Une erreur inconnue est survenue côté serveur.';
                         showError(errorMessage);
                         // Re-enable button and stop
                         paymentButton.disabled = false;
                         paymentButton.textContent = 'Procéder au paiement';
                         return Promise.reject(new Error(errorMessage)); // Propagate error
                     });
                 }
                 // Parse successful JSON response
                 return response.json();
            })
            .then(data => {
                if (data.sessionId) {
                    // Redirect to Stripe Checkout
                    stripe.redirectToCheckout({
                        sessionId: data.sessionId
                    }).then(function(result) {
                        if (result.error) {
                            // If redirectToCheckout itself fails
                            showError(result.error.message);
                            // Re-enable button
                            paymentButton.disabled = false;
                            paymentButton.textContent = 'Procéder au paiement';
                        }
                    });
                } else {
                    // Should not happen if server returns 200 but no sessionId
                    showError('La session de paiement Stripe n\'a pas été créée (réponse serveur inattendue).');
                     paymentButton.disabled = false;
                     paymentButton.textContent = 'Procéder au paiement';
                }
            })
            .catch(error => {
                // Catch network errors or errors from the .then() blocks
                console.error('Error during checkout process:', error);
                 // Display a generic error if no specific error was shown
                 if (paymentErrors.style.display === 'none') {
                     showError('Une erreur est survenue lors du processus de paiement. Veuillez réessayer.');
                 }
                 // Ensure button is re-enabled
                 paymentButton.disabled = false;
                 paymentButton.textContent = 'Procéder au paiement';
            });
        });
    } else {
         console.error("Checkout form with ID 'checkout-form' not found.");
    }

    // Function to display error messages
    function showError(message) {
        paymentErrors.textContent = message;
        paymentErrors.style.display = 'block';
        // Scroll to error message
        paymentErrors.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
});
