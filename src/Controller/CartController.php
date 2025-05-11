<?php

namespace App\Controller;

use App\Entity\CartItem;
use App\Entity\Product;
use App\Form\CartItemType;
use App\Service\CartService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/cart')]
#[IsGranted('ROLE_USER')]
class CartController extends AbstractController
{
    private $cartService;

    public function __construct(CartService $cartService)
    {
        $this->cartService = $cartService;
    }

    #[Route('', name: 'app_cart_index', methods: ['GET'])]
    public function index(): Response
    {
        $cart = $this->cartService->getCart();

        return $this->render('cart/index.html.twig', [
            'cart' => $cart,
        ]);
    }

    #[Route('/add/{id}', name: 'app_cart_add', methods: ['POST'])]
    public function addToCart(Request $request, Product $product): Response
    {
        $quantity = (int) $request->request->get('quantity', 1);
        
        if ($quantity <= 0) {
            $this->addFlash('error', 'La quantité doit être supérieure à 0');
            return $this->redirectToRoute('app_product_show', ['id' => $product->getId()]);
        }
        
        $result = $this->cartService->addItem($product, $quantity);
        
        if (!$result) {
            $this->addFlash('error', 'Impossible d\'ajouter ce produit au panier. Vérifiez sa disponibilité.');
            return $this->redirectToRoute('app_product_show', ['id' => $product->getId()]);
        }
        
        $this->addFlash('success', 'Produit ajouté au panier avec succès');
        
        // If ajax request, return success json
        if ($request->isXmlHttpRequest()) {
            return $this->json([
                'success' => true,
                'itemCount' => $this->cartService->getItemCount(),
                'total' => $this->cartService->getTotal()
            ]);
        }
        
        return $this->redirectToRoute('app_cart_index');
    }

    #[Route('/update/{id}', name: 'app_cart_update', methods: ['POST'])]
    public function updateCartItem(Request $request, CartItem $cartItem): Response
    {
        $quantity = (int) $request->request->get('quantity', 1);
        
        $result = $this->cartService->updateItemQuantity($cartItem, $quantity);
        
        if (!$result) {
            $this->addFlash('error', 'Impossible de mettre à jour la quantité. Vérifiez la disponibilité du produit.');
        } else {
            $this->addFlash('success', 'Panier mis à jour avec succès');
        }
        
        // If ajax request, return success json
        if ($request->isXmlHttpRequest()) {
            $cart = $this->cartService->getCart();
            return $this->json([
                'success' => $result,
                'itemCount' => $this->cartService->getItemCount(),
                'total' => $this->cartService->getTotal(),
                'subtotal' => $cartItem->getSubtotal()
            ]);
        }
        
        return $this->redirectToRoute('app_cart_index');
    }

    #[Route('/remove/{id}', name: 'app_cart_remove', methods: ['POST', 'GET'])]
    public function removeCartItem(CartItem $cartItem): Response
    {
        $this->cartService->removeItem($cartItem);
        
        $this->addFlash('success', 'Produit retiré du panier');
        
        return $this->redirectToRoute('app_cart_index');
    }

    #[Route('/clear', name: 'app_cart_clear', methods: ['POST', 'GET'])]
    public function clearCart(): Response
    {
        $this->cartService->clearCart();
        
        $this->addFlash('success', 'Panier vidé avec succès');
        
        return $this->redirectToRoute('app_cart_index');
    }
}