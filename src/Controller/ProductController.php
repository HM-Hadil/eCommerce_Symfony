<?php

namespace App\Controller;

use App\Entity\Product;
use App\Repository\ProductRepository;
use App\Repository\CategoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/products')]
class ProductController extends AbstractController
{
    private $productRepository;
    private $categoryRepository;

    public function __construct(
        ProductRepository $productRepository,
        CategoryRepository $categoryRepository
    ) {
        $this->productRepository = $productRepository;
        $this->categoryRepository = $categoryRepository;
    }

    #[Route('', name: 'app_product_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        // Get filter parameters
        $categoryId = $request->query->get('category');
        $searchTerm = $request->query->get('search');
        $sortBy = $request->query->get('sort', 'newest'); // Default sort by newest
        
        // Get all categories for the filter sidebar
        $categories = $this->categoryRepository->findAll();
        
        // Apply filters to get products
        $products = $this->productRepository->findByFilters($categoryId, $searchTerm, $sortBy);
        
        return $this->render('product/index.html.twig', [
            'products' => $products,
            'categories' => $categories,
            'currentCategory' => $categoryId,
            'searchTerm' => $searchTerm,
            'sortBy' => $sortBy
        ]);
    }

    #[Route('/{id}', name: 'app_product_show', methods: ['GET'])]
    public function show(Product $product): Response
    {
        // Get related products from the same category
        $relatedProducts = $this->productRepository->findRelatedProducts($product);
        
        return $this->render('product/show.html.twig', [
            'product' => $product,
            'relatedProducts' => $relatedProducts
        ]);
    }
}