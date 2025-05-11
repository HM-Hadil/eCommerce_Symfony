<?php

namespace App\Controller;

use App\Entity\Order;
use App\Form\CheckoutType;
use App\Service\CartService;
use App\Service\OrderService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/order')]
#[IsGranted('ROLE_USER')]
class OrderController extends AbstractController
{
    private $orderService;
    private $cartService;

    public function __construct(
        OrderService $orderService,
        CartService $cartService
    ) {
        $this->orderService = $orderService;
        $this->cartService = $cartService;
    }

    #[Route('', name: 'app_order_index', methods: ['GET'])]
    public function index(): Response
    {
        $orders = $this->orderService->getUserOrders();

        return $this->render('order/index.html.twig', [
            'orders' => $orders,
        ]);
    }

    #[Route('/checkout', name: 'app_order_checkout', methods: ['GET', 'POST'])]
    public function checkout(Request $request): Response
    {
        $cart = $this->cartService->getCart();
        
        if (!$cart || $cart->getCartItems()->isEmpty()) {
            $this->addFlash('error', 'Votre panier est vide');
            return $this->redirectToRoute('app_cart_index');
        }
        
        $form = $this->createForm(CheckoutType::class);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $formData = $form->getData();
            
            $order = $this->orderService->createOrderFromCart(
                $formData['shippingAddress'],
                $formData['billingAddress'],
                $formData['paymentMethod']
            );
            
            if (!$order) {
                $this->addFlash('error', 'Impossible de créer la commande. Veuillez vérifier la disponibilité des produits.');
                return $this->redirectToRoute('app_cart_index');
            }
            
            return $this->redirectToRoute('app_order_payment', [
                'reference' => $order->getReference()
            ]);
        }
        
        return $this->render('order/checkout.html.twig', [
            'cart' => $cart,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/payment/{reference}', name: 'app_order_payment', methods: ['GET', 'POST'])]
    public function payment(Request $request, string $reference): Response
    {
        $order = $this->orderService->getOrderByReference($reference);
        
        if (!$order) {
            throw $this->createNotFoundException('Commande non trouvée');
        }
        
        if ($order->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à accéder à cette commande');
        }
        
        if ($order->getStatus() !== Order::STATUS_PENDING) {
            $this->addFlash('error', 'Cette commande a déjà été traitée');
            return $this->redirectToRoute('app_order_show', ['reference' => $reference]);
        }
        
        // Handle payment submission
        if ($request->isMethod('POST')) {
            $success = $this->orderService->processPayment($order);
            
            if ($success) {
                $this->addFlash('success', 'Paiement effectué avec succès');
                return $this->redirectToRoute('app_order_confirmation', [
                    'reference' => $order->getReference()
                ]);
            } else {
                $this->addFlash('error', 'Échec du paiement. Veuillez réessayer.');
            }
        }
        
        return $this->render('order/payment.html.twig', [
            'order' => $order
        ]);
    }

    #[Route('/confirmation/{reference}', name: 'app_order_confirmation', methods: ['GET'])]
    public function confirmation(string $reference): Response
    {
        $order = $this->orderService->getOrderByReference($reference);
        
        if (!$order) {
            throw $this->createNotFoundException('Commande non trouvée');
        }
        
        if ($order->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à accéder à cette commande');
        }
        
        return $this->render('order/confirmation.html.twig', [
            'order' => $order
        ]);
    }

    #[Route('/{reference}', name: 'app_order_show', methods: ['GET'])]
    public function show(string $reference): Response
    {
        $order = $this->orderService->getOrderByReference($reference);
        
        if (!$order) {
            throw $this->createNotFoundException('Commande non trouvée');
        }
        
        if ($order->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à accéder à cette commande');
        }
        
        return $this->render('order/show.html.twig', [
            'order' => $order,
        ]);
    }

    #[Route('/cancel/{reference}', name: 'app_order_cancel', methods: ['POST'])]
    public function cancel(string $reference): Response
    {
        $order = $this->orderService->getOrderByReference($reference);
        
        if (!$order) {
            throw $this->createNotFoundException('Commande non trouvée');
        }
        
        if ($order->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à annuler cette commande');
        }
        
        $success = $this->orderService->cancelOrder($order);
        
        if ($success) {
            $this->addFlash('success', 'Commande annulée avec succès');
        } else {
            $this->addFlash('error', 'Impossible d\'annuler cette commande');
        }
        
        return $this->redirectToRoute('app_order_show', ['reference' => $reference]);
    }
}