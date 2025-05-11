<?php

namespace App\Service;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Entity\Product;
use App\Entity\User;
use App\Repository\CartRepository;
use App\Repository\CartItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class CartService
{
    private $cartRepository;
    private $cartItemRepository;
    private $entityManager;
    private $security;

    public function __construct(
        CartRepository $cartRepository,
        CartItemRepository $cartItemRepository,
        EntityManagerInterface $entityManager,
        Security $security
    ) {
        $this->cartRepository = $cartRepository;
        $this->cartItemRepository = $cartItemRepository;
        $this->entityManager = $entityManager;
        $this->security = $security;
    }

    /**
     * Get the current user's cart or create a new one
     */
    public function getCart(): ?Cart
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return null;
        }

        $cart = $this->cartRepository->findOneByUser($user);
        
        if (!$cart) {
            $cart = new Cart();
            $cart->setUser($user);
            $this->entityManager->persist($cart);
            $this->entityManager->flush();
        }

        return $cart;
    }

    /**
     * Add a product to cart
     */
    public function addItem(Product $product, int $quantity = 1): bool
    {
        $cart = $this->getCart();
        if (!$cart) {
            return false;
        }

        // Check product availability
        if ($product->getStock() < $quantity || !$product->isActive()) {
            return false;
        }

        // Check if product already in cart
        $cartItem = $cart->findCartItemByProduct($product);

        if ($cartItem) {
            // Update quantity
            $newQuantity = $cartItem->getQuantity() + $quantity;
            
            // Check if we have enough stock
            if ($product->getStock() < $newQuantity) {
                return false;
            }
            
            $cartItem->setQuantity($newQuantity);
        } else {
            // Create new cart item
            $cartItem = new CartItem();
            $cartItem->setCart($cart)
                    ->setProduct($product)
                    ->setQuantity($quantity)
                    ->setPrice($product->getPrice());
            $this->entityManager->persist($cartItem);
        }

        // Update cart timestamp
        $cart->setUpdatedAt(new \DateTimeImmutable());
        
        $this->entityManager->flush();
        return true;
    }

    /**
     * Update item quantity
     */
    public function updateItemQuantity(CartItem $cartItem, int $quantity): bool
    {
        $product = $cartItem->getProduct();
        
        // Check product availability
        if ($product->getStock() < $quantity || !$product->isActive()) {
            return false;
        }
        
        if ($quantity <= 0) {
            $this->removeItem($cartItem);
            return true;
        }
        
        $cartItem->setQuantity($quantity);
        $cartItem->getCart()->setUpdatedAt(new \DateTimeImmutable());
        
        $this->entityManager->flush();
        return true;
    }

    /**
     * Remove an item from cart
     */
    public function removeItem(CartItem $cartItem): void
    {
        $cart = $cartItem->getCart();
        $cart->removeCartItem($cartItem);
        $cart->setUpdatedAt(new \DateTimeImmutable());
        
        $this->entityManager->remove($cartItem);
        $this->entityManager->flush();
    }

    /**
     * Clear cart
     */
    public function clearCart(): void
    {
        $cart = $this->getCart();
        if (!$cart) {
            return;
        }
        
        foreach ($cart->getCartItems() as $item) {
            $this->entityManager->remove($item);
        }
        
        $cart->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->flush();
    }

    /**
     * Get cart total
     */
    public function getTotal(): float
    {
        $cart = $this->getCart();
        if (!$cart) {
            return 0;
        }
        
        return $cart->getTotal();
    }

    /**
     * Get cart item count
     */
    public function getItemCount(): int
    {
        $cart = $this->getCart();
        if (!$cart) {
            return 0;
        }
        
        return $cart->getItemCount();
    }
}