<?php

namespace App\Controller;

use App\Entity\Order;
use App\Service\OrderService;
use App\Service\PdfGenerator; // Import the PdfGenerator service
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/order')]
#[IsGranted('ROLE_USER')]
class OrderController extends AbstractController
{
    private OrderService $orderService;
    private PdfGenerator $pdfGenerator; // Inject the PdfGenerator service

    public function __construct(
        OrderService $orderService,
        PdfGenerator $pdfGenerator // Inject PdfGenerator here
    ) {
        $this->orderService = $orderService;
        $this->pdfGenerator = $pdfGenerator; // Assign the injected service
    }

    #[Route('', name: 'app_order_index', methods: ['GET'])]
    public function index(): Response
    {
        $orders = $this->orderService->getUserOrders();

        return $this->render('order/index.html.twig', [
            'orders' => $orders,
        ]);
    }


    #[Route('/payment/{reference}', name: 'app_order_payment', methods: ['GET', 'POST'])]
    public function payment(Request $request, string $reference): Response
    {
        // Note: This route seems to implement a different payment flow than Stripe Checkout
        // initiated from the CheckoutController. If you are using Stripe Checkout,
        // this route might be redundant or for a different payment method.

        $order = $this->orderService->getOrderByReference($reference);

        if (!$order) {
            throw $this->createNotFoundException('Commande non trouvée');
        }

        if ($order->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à accéder à cette commande');
        }

        // Check if order is already paid or cancelled
        if ($order->getPaymentStatus() === Order::PAYMENT_STATUS_PAID || $order->getStatus() === Order::STATUS_CANCELLED) {
             $this->addFlash('info', 'Cette commande a déjà été traitée ou annulée.');
             return $this->redirectToRoute('app_order_show', ['reference' => $reference]);
        }

        // Check if order is pending payment
        if ($order->getPaymentStatus() !== Order::PAYMENT_STATUS_PENDING) {
             // Handle other payment statuses if necessary
             $this->addFlash('warning', 'Cette commande n\'est pas en attente de paiement.');
             return $this->redirectToRoute('app_order_show', ['reference' => $reference]);
        }


        // Handle payment submission (This seems to trigger a direct payment processing)
        if ($request->isMethod('POST')) {
             // ... (your existing payment logic for this route) ...
        }

        return $this->render('order/payment.html.twig', [
            'order' => $order,
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

        // Ensure the order is in a state where confirmation is appropriate (e.g., paid)
        // if ($order->getPaymentStatus() !== Order::PAYMENT_STATUS_PAID) {
        //      $this->addFlash('warning', 'Cette commande n\'a pas été payée.');
        //      return $this->redirectToRoute('app_order_show', ['reference' => $reference]);
        // }


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

        // The cancelOrder method in OrderService checks if cancellation is allowed (e.g., status PENDING)
        $success = $this->orderService->cancelOrder($order); // Assuming this method exists and is implemented

        if ($success) {
            $this->addFlash('success', 'Commande annulée avec succès');
        } else {
            // The cancelOrder method logs the reason if cancellation failed
            $this->addFlash('error', 'Impossible d\'annuler cette commande');
        }

        return $this->redirectToRoute('app_order_show', ['reference' => $reference]);
    }

    // ADDED: Method to download the invoice PDF
    #[Route('/{reference}/invoice', name: 'app_order_download_invoice', methods: ['GET'])]
    public function downloadInvoice(string $reference): Response
    {
        $order = $this->orderService->getOrderByReference($reference);

        if (!$order) {
            throw $this->createNotFoundException('Commande non trouvée');
        }

        // Ensure the user is authorized to download this invoice
        if ($order->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à télécharger cette facture');
        }

        // Ensure the order is paid before allowing invoice download (optional but recommended)
        if ($order->getPaymentStatus() !== Order::PAYMENT_STATUS_PAID) {
             $this->addFlash('warning', 'La facture n\'est disponible qu\'après paiement.');
             return $this->redirectToRoute('app_order_show', ['reference' => $reference]);
        }

        // Data to pass to the Twig template for the PDF
        $invoiceData = [
            'order' => $order,
            // You might add other necessary data here, like company info
        ];

        // Generate the PDF content using the PdfGenerator service
        $pdfContent = $this->pdfGenerator->generatePdf(
            'invoice/invoice.html.twig', // Path to your Twig template for the invoice
            $invoiceData
        );

        // Create a Response with the PDF content
        $response = new Response($pdfContent);

        // Set the headers to force download
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="facture_commande_%s.pdf"', $order->getReference()));

        return $response;
    }

    // Example placeholder for a non-Stripe payment processing method if needed for the /payment route
    // private function processNonStripePayment(Order $order, array $paymentData): bool
    // {
    //     // Implement your non-Stripe payment gateway integration logic here
    //     // Return true on success, false on failure
    //     // Log any errors
    //     return true; // Placeholder
    // }
}
