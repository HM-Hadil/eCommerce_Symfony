<?php

namespace App\Controller;

use App\Entity\Category;
use App\Repository\ProductRepository;
use App\Repository\CategoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Knp\Component\Pager\PaginatorInterface;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(
        Request $request,
        ProductRepository $productRepository,
        CategoryRepository $categoryRepository,
        PaginatorInterface $paginator
    ): Response {
        // Récupérer la catégorie sélectionnée (s'il y en a une)
        $categoryId = $request->query->get('category');
        $category = null;
        
        if ($categoryId) {
            $category = $categoryRepository->find($categoryId);
        }
        
        // Récupérer le terme de recherche (s'il y en a un)
        $search = $request->query->get('search');
        
        // Créer la requête de base pour les produits
        $queryBuilder = $productRepository->createQueryBuilder('p')
            ->where('p.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('p.createdAt', 'DESC');
        
        // Ajouter le filtre par catégorie si nécessaire
        if ($category) {
            $queryBuilder->andWhere('p.category = :category')
                ->setParameter('category', $category);
        }
        
        // Ajouter le filtre de recherche si nécessaire
        if ($search) {
            $queryBuilder->andWhere('p.name LIKE :search OR p.description LIKE :search')
                ->setParameter('search', '%'.$search.'%');
        }
        
        // Créer la pagination
        $pagination = $paginator->paginate(
            $queryBuilder->getQuery(),
            $request->query->getInt('page', 1), // Numéro de page, 1 par défaut
            12 // Nombre d'éléments par page
        );
        
        // Récupérer toutes les catégories pour le filtre
        $categories = $categoryRepository->findAll();
        
        return $this->render('home/index.html.twig', [
            'pagination' => $pagination,
            'categories' => $categories,
            'currentCategory' => $category,
            'search' => $search
        ]);
    }
}