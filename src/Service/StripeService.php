<?php

namespace App\Service;

use App\Entity\Order;
use Psr\Log\LoggerInterface;
use Stripe\Stripe;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class StripeService
{
    private RouterInterface $router;
    private string $stripeSecretKey;
    private string $stripePublicKey;
    private string $currency;
    private LoggerInterface $logger;
    private ParameterBagInterface $parameterBag;

    public function __construct(
        RouterInterface $router,
        ParameterBagInterface $parameterBag,
        LoggerInterface $logger
    ) {
        $this->router = $router;
        $this->parameterBag = $parameterBag;
        $this->logger = $logger;

        $this->stripeSecretKey = $this->parameterBag->get('stripe_secret_key');
        $this->stripePublicKey = $this->parameterBag->get('stripe_public_key');
        $this->currency = strtolower($this->parameterBag->get('app.currency') ?? 'usd');

        // It's often recommended to set the API key globally once, e.g., in an EventSubscriber
        // Stripe::setApiKey($this->stripeSecretKey);
    }

    public function getPublicKey(): string
    {
        return $this->stripePublicKey;
    }

    /**
     * Crée une session de paiement Stripe pour une commande
     *
     * @param Order $order The order entity
     * @return string|null The Stripe Checkout Session ID or null on failure
     */
    public function createCheckoutSession(Order $order): ?string
    {
        // Ensure Stripe API key is set before making API calls
        Stripe::setApiKey($this->stripeSecretKey);

        try {
            $lineItems = [];

            foreach ($order->getOrderItems() as $item) {
                $product = $item->getProduct();

                $lineItems[] = [
                    'price_data' => [
                        'currency' => $this->currency,
                        'product_data' => [
                            'name' => $item->getProductName() ?? 'Product',
                            'description' => $product ? ($product->getDescription() ?? '') : '',
                        ],
                        'unit_amount' => (int)($item->getPrice() * 100), // Stripe uses cents
                    ],
                    'quantity' => $item->getQuantity(),
                ];
            }

            if (empty($lineItems)) {
                 $this->logger->error(sprintf('No valid line items created for order %s. This might indicate all products in the order were missing or invalid.', $order->getReference() ?? 'N/A'));
                 return null;
            }

            // CORRECTED: Generate the success_url with the placeholder correctly placed
            $successUrl = $this->router->generate('app_order_stripe_success', [
                'reference' => $order->getReference(),
                // Pass a placeholder value here, Stripe will replace it in the final URL
                'session_id' => 'STRIPE_SESSION_ID_PLACEHOLDER' // Use a different placeholder name if needed
            ], UrlGeneratorInterface::ABSOLUTE_URL);

            // Replace the custom placeholder with Stripe's required placeholder
            // This ensures Stripe recognizes where to inject the actual session ID
            $finalSuccessUrl = str_replace('STRIPE_SESSION_ID_PLACEHOLDER', '{CHECKOUT_SESSION_ID}', $successUrl);


            $checkoutSession = Session::create([
                'payment_method_types' => ['card'],
                'line_items' => $lineItems,
                'mode' => 'payment',
                'customer_email' => $order->getUser() ? $order->getUser()->getEmail() : null,
                // Use the corrected success URL
                'success_url' => $finalSuccessUrl,
                'cancel_url' => $this->router->generate('app_order_stripe_cancel', [
                    'reference' => $order->getReference()
                ], UrlGeneratorInterface::ABSOLUTE_URL),
                'metadata' => [
                    'order_reference' => $order->getReference(),
                    'order_id' => $order->getId(),
                ],
            ]);

            // Consider storing the session ID on the Order entity here
            // $order->setPaymentReference($checkoutSession->id);
            // This would need to be flushed later.

            return $checkoutSession->id;

        } catch (ApiErrorException $e) {
            $stripeError = $e->getError();
            $this->logger->error('Stripe Checkout Session creation failed: ' . $e->getMessage(), [
                'stripe_error_code' => $stripeError ? $stripeError->code : 'N/A',
                'stripe_error_type' => $stripeError ? $stripeError->type : 'N/A',
                'stripe_error_param' => $stripeError ? $stripeError->param : 'N/A',
                'order_reference' => $order->getReference() ?? 'N/A',
                'exception' => $e,
            ]);
            return null;
        } catch (\Exception $e) {
             $this->logger->error('An unexpected error occurred during Stripe Checkout Session creation: ' . $e->getMessage(), [
                 'order_reference' => $order->getReference() ?? 'N/A',
                 'exception' => $e,
             ]);
             return null;
        }
    }

    /**
     * Vérifie si une session de paiement est complétée avec succès
     *
     * @param string $sessionId The Stripe Checkout Session ID
     * @return bool True if the payment status is 'paid', false otherwise or on error
     */
    public function isPaymentSuccessful(string $sessionId): bool
    {
        Stripe::setApiKey($this->stripeSecretKey);

        try {
            $session = Session::retrieve($sessionId);

            // ADDED LOGGING: Log the retrieved session details
            $this->logger->info(sprintf(
                'Stripe Session Retrieved for ID %s. Status: %s, Payment Status: %s, Amount Total: %s',
                $sessionId,
                $session->status,
                $session->payment_status,
                $session->amount_total ?? 'N/A' // Log amount total if available
            ));


            // Check payment status. 'paid' indicates successful payment.
            // 'complete' status means the session is closed, regardless of payment status.
            // We rely on 'payment_status' === 'paid'
            return $session->payment_status === 'paid';

        } catch (ApiErrorException $e) {
            $stripeError = $e->getError();
            $this->logger->error('Stripe Session retrieval failed for session ID ' . $sessionId . ': ' . $e->getMessage(), [
                'stripe_error_code' => $stripeError ? $stripeError->code : 'N/A',
                'stripe_error_type' => $stripeError ? $stripeError->type : 'N/A',
                'stripe_error_param' => $stripeError ? $stripeError->param : 'N/A',
                'exception' => $e,
            ]);
            return false;
        } catch (\Exception $e) {
             $this->logger->error('An unexpected error occurred during Stripe Session retrieval: ' . $sessionId . ': ' . $e->getMessage(), [
                 'session_id' => $sessionId,
                 'exception' => $e,
             ]);
             return false;
        }
    }

    // Consider adding a method to handle Stripe webhooks for reliable payment confirmation
    // public function handleWebhook(string $payload, string $signature): bool
    // {
    //     // ... Stripe webhook verification and event processing logic ...
    // }
}
