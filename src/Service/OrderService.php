<?php

namespace App\Service;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\User;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Security;
// Assuming you have these services and they are correctly configured and injected
// use App\Service\StripeService; // Injected in CheckoutController, not necessarily here
// use App\Service\PdfGenerator;
// use App\Service\EmailService;
// use Symfony\Contracts\EventDispatcher\EventDispatcherInterface; // For decoupling

class OrderService
{
    private EntityManagerInterface $em;
    private CartService $cartService; // Injected to clear the cart
    private Security $security;
    private OrderRepository $orderRepository;
    private LoggerInterface $logger;
    // Add other services if needed
    // private PdfGenerator $pdfGenerator;
    // private EmailService $emailService;
    // private EventDispatcherInterface $eventDispatcher;


    public function __construct(
        EntityManagerInterface $em,
        CartService $cartService, // Inject CartService
        Security $security,
        OrderRepository $orderRepository,
        LoggerInterface $logger
        // Add other service interfaces here
        // PdfGenerator $pdfGenerator,
        // EmailService $emailService,
        // EventDispatcherInterface $eventDispatcher
    ) {
        $this->em = $em;
        $this->cartService = $cartService; // Assign CartService
        $this->security = $security;
        $this->orderRepository = $orderRepository;
        $this->logger = $logger;
        // Assign other services
        // $this->pdfGenerator = $pdfGenerator;
        // $this->emailService = $emailService;
        // $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * Crée une commande à partir du panier de l'utilisateur
     *
     * @param string|null $shippingAddress Adresse de livraison
     * @param string|null $billingAddress Adresse de facturation
     * @param string|null $paymentMethod Méthode de paiement
     * @return Order|null La commande créée ou null en cas d'échec (panier vide, pas d'utilisateur, stock insuffisant pour TOUS les items)
     * Consider throwing specific exceptions for better error handling in calling code.
     */
    public function createOrderFromCart(
        ?string $shippingAddress = null,
        ?string $billingAddress = null,
        ?string $paymentMethod = null
    ): ?Order {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            $this->logger->error('Tentative de création de commande sans utilisateur connecté');
            return null;
        }

        $cart = $this->cartService->getCart();
        if (!$cart || $cart->getCartItems()->isEmpty()) {
            $this->logger->error('Tentative de création de commande avec un panier vide');
            return null;
        }

        // Start a transaction
        $this->em->beginTransaction();

        try {
            // Create a new order
            $order = new Order();
            $order->setUser($user);
            $order->setPaymentMethod($paymentMethod);
            // Use provided addresses or user's default addresses
            $order->setShippingAddress($shippingAddress ?? ($user->getShippingAddress() ?? ''));
            $order->setBillingAddress($billingAddress ?? ($user->getBillingAddress() ?? ''));
            $order->setStatus(Order::STATUS_PENDING);
            $order->setPaymentStatus(Order::PAYMENT_STATUS_PENDING);

            $this->em->persist($order); // Persist the order first

            $hasValidItems = false; // Flag to track if any valid items were added

            // Add cart items to the order and check stock
            foreach ($cart->getCartItems() as $cartItem) {
                $product = $cartItem->getProduct();

                // Check that the product exists, is active, and stock is sufficient
                 if (!$product || !$product->isActive() || $product->getStock() < $cartItem->getQuantity()) {
                    $this->logger->warning('Ignorer le produit indisponible ou stock insuffisant lors de la création de commande', [
                        'product_id' => $product ? $product->getId() : 'null',
                        'quantity_requested' => $cartItem->getQuantity(),
                        'available_stock' => $product ? $product->getStock() : 0,
                        'user_id' => $user->getId()
                    ]);
                    // We skip this item but continue processing other items in the cart.
                    continue;
                }

                // Create an order item
                $orderItem = new OrderItem();
                $orderItem->setProduct($product);
                $orderItem->setQuantity($cartItem->getQuantity());
                // Use the price from the cart item
                $orderItem->setPrice($cartItem->getPrice());
                // Use the product name from the product at the time of creation
                $orderItem->setProductName($product->getName()); // Make sure OrderItem has setProductName
                // Use setOrder() now that the mapping is fixed
                $orderItem->setOrder($order); // Associate with the order

                // Also add the item to the order's collection (this is important for calculateAndSetTotal)
                $order->addOrderItem($orderItem); // Make sure Order has addOrderItem

                $this->em->persist($orderItem); // Persist the order item
                $hasValidItems = true; // Mark that at least one valid item was added
            }

            // If no valid items were added to the order after checking all cart items
            if (!$hasValidItems) {
                 $this->logger->error('Aucun produit valide dans le panier pour créer la commande', [
                     'user_id' => $user->getId()
                 ]);
                 $this->em->rollback(); // Rollback if no valid items were added at all
                 return null;
            }

            // Calculate and set the total amount based on the valid items added
            $order->calculateAndSetTotal(); // Make sure Order has calculateAndSetTotal

            // Flush changes to the database within the transaction
            $this->em->flush();

            // Commit the transaction
            $this->em->commit();

            // Cart clearing happens AFTER successful payment confirmation (in finalizeOrder)

            return $order;

        } catch (\Exception $e) {
            // Rollback the transaction on error
            if ($this->em->getConnection()->isTransactionActive()) {
                 $this->em->rollback();
            }
            $this->logger->error('Erreur lors de la création de la commande: ' . $e->getMessage(), [
                'user_id' => $user->getId() ?? 'N/A',
                'exception' => $e
            ]);
            // Re-throw the exception to be caught in the controller
            throw $e;
            // return null; // Or return null after logging
        }
    }

     /**
      * Handles the successful Stripe payment confirmation.
      * Verifies the payment session and finalizes the order.
      *
      * @param Order $order The order entity
      * @param string $sessionId The Stripe Checkout Session ID
      * @return bool True if payment was successful and order finalized, false otherwise.
      */
    public function processStripeSuccess(Order $order, string $sessionId): bool
    {
        // Assuming StripeService is injected and has isPaymentSuccessful method
        // You would uncomment this and inject StripeService
        // if (!$this->stripeService->isPaymentSuccessful($sessionId)) {
        //     $this->logger->warning(sprintf('Stripe payment verification failed for session %s, order %s', $sessionId, $order->getReference()));
        //     // Optionally update order payment status to failed
        //     $order->setPaymentStatus(Order::PAYMENT_STATUS_FAILED);
        //     $this->em->flush();
        //     return false;
        // }

        // Finalize the order process
        // Pass the session ID to finalizeOrder to store as payment reference
        return $this->finalizeOrder($order, $sessionId);
    }


    /**
     * Finalize order after successful payment verification.
     * Updates order statuses, reduces stock, clears cart, sends invoice.
     * This logic should ideally be triggered by a reliable source like a Stripe Webhook.
     *
     * @param Order $order The order entity
     * @param string|null $paymentReference The payment gateway reference (e.g., Stripe Session ID)
     * @return bool True if finalization was successful, false otherwise.
     */
    public function finalizeOrder(Order $order, ?string $paymentReference = null): bool
    {
        // Prevent double finalization
        if ($order->getPaymentStatus() === Order::PAYMENT_STATUS_PAID) {
            $this->logger->info(sprintf('Order %s already finalized (payment status is PAID)', $order->getReference()));
            return true;
        }

        // Start a transaction for critical updates (status, stock)
        $this->em->beginTransaction();

        try {
            // Update order statuses and payment reference
            $order->setStatus(Order::STATUS_PROCESSING); // Or STATUS_PAID
            $order->setPaymentStatus(Order::PAYMENT_STATUS_PAID);
            if ($paymentReference) {
                 $order->setPaymentReference($paymentReference);
            }
            // paidAt and updatedAt handled by entity setters/lifecycle

            // Update stock for each product
            foreach ($order->getOrderItems() as $orderItem) {
                $product = $orderItem->getProduct();
                $quantity = $orderItem->getQuantity();

                 // Re-check stock just before updating within the transaction
                 // This is a crucial re-check for race conditions.
                 if (!$product || !$product->isActive() || $product->getStock() < $quantity) {
                    // This indicates a severe issue (stock changed between order creation and payment).
                     $this->logger->critical('Stock insufficient during order finalization transaction!', [
                         'order_reference' => $order->getReference(),
                         'product_id' => $product ? $product->getId() : 'null',
                         'quantity_requested' => $quantity,
                         'available_stock' => $product ? $product->getStock() : 0
                     ]);
                     $this->em->rollback();
                     // Consider setting order status to 'payment_failed_stock_issue' or similar
                     // $order->setStatus('payment_failed_stock_issue');
                     // $this->em->flush();
                     return false; // Indicate failure
                 }

                $newStock = $product->getStock() - $quantity;
                $product->setStock($newStock);
                $this->em->persist($product); // Ensure product changes are tracked
            }

            // Flush all changes (order status, payment status, payment reference, stock updates)
            $this->em->flush();

            // Commit the transaction
            $this->em->commit();

            // ----- Actions outside the main transaction (can be retried) -----

            // Clear the cart after successful order finalization
            // This should ideally happen after payment is CONFIRMED, not just on the success page redirect.
            // Webhooks are the best place for this.
            $this->cartService->clearCart();

            // Generate and send invoice email (assuming services are injected)
            // $this->generateAndSendInvoice($order);

            // Dispatch an event (assuming EventDispatcherInterface is injected)
            // $event = new OrderPaidEvent($order);
            // $this->eventDispatcher->dispatch($event);

            $this->logger->info(sprintf('Order %s finalized successfully.', $order->getReference()));
            return true;

        } catch (\Exception $e) {
            // Rollback the transaction on error
             if ($this->em->getConnection()->isTransactionActive()) {
                $this->em->rollback();
            }
            $this->logger->error('Erreur lors de la finalisation de la commande: ' . $e->getMessage(), [
                'order_reference' => $order->getReference() ?? 'N/A',
                'exception' => $e
            ]);
            // Consider setting order payment status to failed here
            // $order->setPaymentStatus(Order::PAYMENT_STATUS_FAILED);
            // $this->em->flush();
            // Re-throw the exception
            throw $e;
            // return false;
        }
    }


    /**
     * Récupère une commande par sa référence
     */
    public function getOrderByReference(string $reference): ?Order
    {
        return $this->orderRepository->findOneBy(['reference' => $reference]);
    }

    /**
     * Récupère les commandes de l'utilisateur connecté
     */
    public function getUserOrders(): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return [];
        }

        return $this->orderRepository->findBy(
            ['user' => $user],
            ['createdAt' => 'DESC']
        );
    }

    // Add other methods like cancelOrder, getInvoicePdf, generateAndSendInvoice etc.
}
