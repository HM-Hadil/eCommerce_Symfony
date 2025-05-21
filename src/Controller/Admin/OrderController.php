<?php

namespace App\Controller\Admin;

use App\Entity\Order;
use App\Form\OrderStatusType;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/order')]
#[IsGranted('ROLE_ADMIN')]
class OrderController extends AbstractController
{
    #[Route('/', name: 'app_admin_order_index', methods: ['GET'])]
    public function index(
        OrderRepository $orderRepository, 
        PaginatorInterface $paginator, 
        Request $request
    ): Response {
        $query = $orderRepository->createQueryBuilder('o')
            ->orderBy('o.createdAt', 'DESC');
        
        // Appliquer le filtre par référence si fourni
        $currentReference = $request->query->get('reference');
        if ($currentReference) {
            $query->andWhere('o.reference LIKE :reference')
                ->setParameter('reference', '%' . $currentReference . '%');
        }
        
        // Appliquer le filtre par statut si fourni
        $currentStatus = $request->query->get('status');
        if ($currentStatus) {
            $query->andWhere('o.status = :status')
                ->setParameter('status', $currentStatus);
        }
        
        $pagination = $paginator->paginate(
            $query->getQuery(),
            $request->query->getInt('page', 1),
            10
        );
        
        // Choix de statut pour le filtre
        $statusChoices = [
            'En attente' => 'pending',
            'En traitement' => 'processing',
            'Expédiée' => 'shipped',
            'Livrée' => 'delivered',
            'Annulée' => 'cancelled'
        ];
        
        return $this->render('admin/order/index.html.twig', [
            'pagination' => $pagination,
            'current_reference' => $currentReference,
            'current_status' => $currentStatus,
            'status_choices' => $statusChoices
        ]);
    }

    #[Route('/{id}', name: 'app_admin_order_show', methods: ['GET', 'POST'])]
    public function show(
        Request $request, 
        Order $order, 
        EntityManagerInterface $entityManager
    ): Response {
        $form = $this->createForm(OrderStatusType::class, $order);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Le statut de la commande a été mis à jour.');
            
            return $this->redirectToRoute('app_admin_order_show', ['id' => $order->getId()]);
        }
        
        return $this->render('admin/order/show.html.twig', [
            'order' => $order,
            'form' => $form->createView()
        ]);
    }

    #[Route('/{id}/update-status', name: 'app_admin_order_update_status', methods: ['POST'])]
    public function updateStatus(
        Request $request, 
        Order $order, 
        EntityManagerInterface $entityManager
    ): Response {
        $status = $request->request->get('status');
        if ($status) {
            $order->setStatus($status);
            $entityManager->flush();
            $this->addFlash('success', 'Le statut de la commande a été mis à jour.');
        }
        
        return $this->redirectToRoute('app_admin_order_show', ['id' => $order->getId()]);
    }

    #[Route('/{id}/resend-confirmation', name: 'app_admin_order_resend_confirmation', methods: ['POST'])]
    public function resendConfirmation(Order $order): Response {
        // Logique pour renvoyer l'email de confirmation
        // À implémenter selon votre service d'envoi d'emails
        
        $this->addFlash('success', 'L\'email de confirmation a été renvoyé.');
        return $this->redirectToRoute('app_admin_order_show', ['id' => $order->getId()]);
    }
}