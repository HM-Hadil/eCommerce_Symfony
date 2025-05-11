<?php

namespace App\Service;

use App\Entity\Cart;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\User;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Security;

class OrderService
{
    private $orderRepository;
    private $cartService;
    private $entityManager;
    private $security;
    private $productRepository;

    public function __construct(
        OrderRepository $orderRepository,
        CartService $cartService,
        EntityManagerInterface $entityManager,
        Security $security,
        ProductRepository $productRepository
    ) {
        $this->orderRepository = $orderRepository;
        $this->cartService = $cartService;
        $this->entityManager = $entityManager;
        $this->security = $security;
        $this->productRepository = $productRepository;
    }

    /**
     * Create a new order from cart
     */
    public function createOrderFromCart(
        string $shippingAddress = null, 
        string $billingAddress = null,
        string $paymentMethod = null
    ): ?Order {
        $cart = $this->cartService->getCart();
        if (!$cart || $cart->getCartItems()->isEmpty()) {
            return null;
        }

        /** @var User $user */
        $user = $this->security->getUser();
        if (!$user) {
            return null;
        }

        // Create new order
        $order = new Order();
        $order->setUser($user)
              ->setStatus(Order::STATUS_PENDING)
              ->setShippingAddress($shippingAddress ?: $user->getShippingAddress())
              ->setBillingAddress($billingAddress ?: $user->getBillingAddress())
              ->setPaymentMethod($paymentMethod);

        $this->entityManager->persist($order);

        $totalAmount = 0;
        $stockError = false;

        // Add items to order
        foreach ($cart->getCartItems() as $cartItem) {
            $product = $cartItem->getProduct();
            $quantity = $cartItem->getQuantity();
            
            // Verify stock availability
            if (!$product->isActive() || $product->getStock() < $quantity) {
                $stockError = true;
                break;
            }

            // Create order item
            $orderItem = new OrderItem();
            $orderItem->setOrderRef($order)
                      ->setProduct($product)
                      ->setQuantity($quantity)
                      ->setPrice($cartItem->getPrice());
                      
            $this->entityManager->persist($orderItem);
            
            // Update total
            $totalAmount += $orderItem->getSubtotal();
        }

        if ($stockError) {
            return null;
        }

        $order->setTotalAmount($totalAmount);

        // Save everything
        $this->entityManager->flush();

        return $order;
    }

    /**
     * Process payment and finalize order
     */
    public function processPayment(Order $order): bool
    {
        // Here you would integrate with a payment gateway
        
        // For demonstration, we'll just simulate a successful payment
        $order->setStatus(Order::STATUS_PAID)
              ->setPaymentDate(new \DateTimeImmutable());
              
        // Update stock for each product
        foreach ($order->getOrderItems() as $orderItem) {
            $product = $orderItem->getProduct();
            $newStock = $product->getStock() - $orderItem->getQuantity();
            $product->setStock($newStock);
        }
        
        $this->entityManager->flush();
        
        // Clear the cart after successful order
        $this->cartService->clearCart();
        
        return true;
    }

    /**
     * Get order by reference
     */
    public function getOrderByReference(string $reference): ?Order
    {
        return $this->orderRepository->findOneByReference($reference);
    }

    /**
     * Get user orders
     */
    public function getUserOrders(): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return [];
        }
        
        return $this->orderRepository->findByUser($user);
    }
    
    /**
     * Cancel order
     */
    public function cancelOrder(Order $order): bool
    {
        if ($order->getStatus() !== Order::STATUS_PENDING) {
            return false;
        }
        
        $order->setStatus(Order::STATUS_CANCELLED);
        $this->entityManager->flush();
        
        return true;
    }
}