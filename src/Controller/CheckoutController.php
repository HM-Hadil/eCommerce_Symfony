<?php

namespace App\Controller;

use App\Entity\Order;
use App\Form\CheckoutType;
use App\Service\CartService;
use App\Service\OrderService;
use App\Service\StripeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
// Import LoggerInterface if you plan to use $this->logger in the controller
// use Psr\Log\LoggerInterface;

class CheckoutController extends AbstractController
{
    private CartService $cartService;
    private OrderService $orderService;
    private StripeService $stripeService;
    // Inject LoggerInterface if needed for controller-specific logging
    // private LoggerInterface $logger;

    public function __construct(
        CartService $cartService,
        OrderService $orderService,
        StripeService $stripeService
        // Inject LoggerInterface if needed
        // LoggerInterface $logger
    ) {
        $this->cartService = $cartService;
        $this->orderService = $orderService;
        $this->stripeService = $stripeService;
        // Assign LoggerInterface if injected
        // $this->logger = $logger;
    }

    #[Route('/checkout', name: 'app_checkout')]
    public function index(Request $request): Response
    {
        $cart = $this->cartService->getCart();

        // Vérifier si le panier est vide
        if (!$cart || $cart->getCartItems()->isEmpty()) {
            if ($request->isXmlHttpRequest() || $request->headers->get('Accept') === 'application/json') {
                return new JsonResponse(['error' => 'Votre panier est vide.'], Response::HTTP_BAD_REQUEST);
            }
            $this->addFlash('error', 'Votre panier est vide.');
            return $this->redirectToRoute('app_cart_index');
        }

        $form = $this->createForm(CheckoutType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $formData = $form->getData();

            try {
                // Créer la commande via le service
                // The OrderService handles persistence and transaction internally
                $order = $this->orderService->createOrderFromCart(
                    $formData['shippingAddress'],
                    $formData['billingAddress'],
                    'stripe'
                );

                // If createOrderFromCart returns null, it means order creation failed
                // (e.g., empty cart, no user, or no valid items after checks)
                if (!$order) {
                    if ($request->isXmlHttpRequest() || $request->headers->get('Accept') === 'application/json') {
                         // The OrderService logs the specific reason.
                        return new JsonResponse(['error' => 'Impossible de créer votre commande. Veuillez vérifier votre panier et la disponibilité des produits.'], Response::HTTP_BAD_REQUEST);
                    }
                    $this->addFlash('error', 'Impossible de créer votre commande. Veuillez vérifier votre panier et la disponibilité des produits.');
                    return $this->redirectToRoute('app_cart_index');
                }

                // Create Stripe Checkout Session using the created order
                $sessionId = $this->stripeService->createCheckoutSession($order);

                // If Stripe session creation failed
                if (!$sessionId) {
                     // Error creating Stripe session (logged in StripeService)
                    if ($request->isXmlHttpRequest() || $request->headers->get('Accept') === 'application/json') {
                        return new JsonResponse(['error' => 'Erreur lors de la création de la session de paiement.'], Response::HTTP_INTERNAL_SERVER_ERROR); // Use 500 for server error
                    }
                    $this->addFlash('error', 'Erreur lors de la création de la session de paiement.');
                    return $this->redirectToRoute('app_cart_index'); // Redirect to cart or checkout page
                }

                // Handle AJAX vs non-AJAX response
                if ($request->isXmlHttpRequest() || $request->headers->get('Accept') === 'application/json') {
                    // Return the session ID for the frontend JS to redirect
                    return new JsonResponse(['sessionId' => $sessionId]);
                } else {
                    // For non-AJAX requests (less common for Stripe Checkout initiation)
                    return $this->redirectToRoute('app_order_stripe_success', [
                        'reference' => $order->getReference(),
                        'session_id' => $sessionId
                    ]);
                }

            } catch (\Exception $e) {
                // Catch any exceptions during order creation or session creation
                // Log the exception server-side
                // $this->logger->error('Checkout process failed', ['exception' => $e]); // Use injected logger

                if ($request->isXmlHttpRequest() || $request->headers->get('Accept') === 'application/json') {
                    return new JsonResponse(['error' => 'Une erreur interne est survenue. Veuillez réessayer.'], Response::HTTP_INTERNAL_SERVER_ERROR);
                }
                $this->addFlash('error', 'Une erreur est survenue : ' . $e->getMessage()); // Display specific error for non-AJAX
                return $this->redirectToRoute('app_checkout');
            }
        }

        // Render the checkout form page for GET requests or initial display
        return $this->render('checkout/index.html.twig', [
            'form' => $form->createView(),
            'cart' => $cart,
            'stripe_public_key' => $this->stripeService->getPublicKey(),
        ]);
    }

    #[Route('/order/stripe/success', name: 'app_order_stripe_success')]
    public function stripeSuccess(Request $request): Response
    {
        $reference = $request->query->get('reference');
        $sessionId = $request->query->get('session_id');

        // Basic check for required parameters
        if (!$reference || !$sessionId) {
            $this->addFlash('error', 'Paramètres de confirmation de paiement manquants.');
            return $this->redirectToRoute('app_cart_index'); // Redirect to cart or a generic error page
        }

        $order = $this->orderService->getOrderByReference($reference);

        if (!$order) {
            // Log this as a potential issue - why did Stripe redirect with a non-existent order ref?
            // $this->logger->error('Stripe success redirect for non-existent order reference', ['reference' => $reference]);
            $this->addFlash('error', 'Commande non trouvée.');
            return $this->redirectToRoute('app_cart_index');
        }

        // IMPORTANT: The most reliable way to confirm payment is via Stripe Webhooks.
        // This success page logic is a fallback/user-facing confirmation.
        // The finalizeOrder logic should ideally be triggered by the webhook.

        // Verify payment was successful using the StripeService
        // The finalizeOrder method itself also includes stock checks within a transaction.
        // Calling finalizeOrder here directly is okay if you trust the StripeService::isPaymentSuccessful check
        // and understand the limitations without webhooks.
        if ($this->stripeService->isPaymentSuccessful($sessionId)) {
            // Finalize the order (update status, reduce stock, clear cart, etc.)
            // finalizeOrder includes transaction and stock checks.
            $success = $this->orderService->finalizeOrder($order, $sessionId); // Pass session ID as payment reference

            if ($success) {
                 // Message de succès ajouté ici
                 $this->addFlash('success', 'Votre commande a été traitée avec succès !');
                 // Redirige vers la page de confirmation de commande (likely in OrderController)
                 return $this->redirectToRoute('app_order_confirmation', [
                     'reference' => $order->getReference()
                 ]);
            } else {
                 // Finalization failed (e.g., stock issue during finalization transaction)
                 // The finalizeOrder method logs the specific reason.
                 $this->addFlash('error', 'Une erreur est survenue lors de la finalisation de votre commande.');
                 // Redirect to a relevant page, maybe order details page showing the status
                 return $this->redirectToRoute('app_order_show', ['reference' => $order->getReference()]); // Assuming order status is updated to failed/review
            }

        } else {
            // Payment status from Stripe session is not 'paid'
            // The isPaymentSuccessful method logs the reason if retrieval failed.
            $this->addFlash('error', 'Le paiement n\'a pas été confirmé par Stripe. Veuillez vérifier le statut de votre commande ou réessayer.');
            // Redirect to cart, checkout, or order details page
            return $this->redirectToRoute('app_cart_index'); // Or app_checkout, or app_order_show
        }
    }

    #[Route('/order/stripe/cancel', name: 'app_order_stripe_cancel')]
    public function stripeCancel(Request $request): Response
    {
        $reference = $request->query->get('reference');

        // Optionally retrieve the order and update its status to cancelled or payment_cancelled
        // $order = $this->orderService->getOrderByReference($reference);
        // if ($order) {
        //     // Ensure you have a cancelOrder method in OrderService that handles status update and potential stock return
        //     // $this->orderService->cancelOrder($order);
        // }

        $this->addFlash('error', 'Le paiement a été annulé. Votre commande n\'a pas été finalisée.');

        // Redirect back to the checkout page or cart page
        return $this->redirectToRoute('app_checkout'); // Or app_cart_index
    }

    // REMOVED: The confirmation route is handled by OrderController
    // #[Route('/order/confirmation/{reference}', name: 'app_order_confirmation')]
    // public function confirmation(string $reference): Response
    // {
    //     // ... logic likely moved to OrderController ...
    // }
}
