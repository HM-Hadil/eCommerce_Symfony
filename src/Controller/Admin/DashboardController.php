<?php

namespace App\Controller\Admin;

use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_admin_dashboard')]
    public function index(ProductRepository $productRepository, CategoryRepository $categoryRepository): Response
    {
        $user = $this->getUser();
        
        // Get statistics
        $productsCount = count($productRepository->findByUser($user));
        $categoriesCount = count($categoryRepository->findAll());
        
        return $this->render('admin/dashboard.html.twig', [
            'user' => $user,
            'productsCount' => $productsCount,
            'categoriesCount' => $categoriesCount
        ]);
    }
}