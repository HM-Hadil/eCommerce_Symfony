<?php

namespace App\Entity;

use App\Repository\OrderItemRepository; // Assuming you have an OrderItemRepository
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrderItemRepository::class)]
class OrderItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // CORRECTED: Property name changed to 'order' to match standard convention
    #[ORM\ManyToOne(inversedBy: 'orderItems')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Order $order = null; // Property name should match mappedBy in Order entity

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Product $product = null;

    #[ORM\Column]
    private ?int $quantity = null;

    #[ORM\Column]
    private ?float $price = null; // Price at the time of order

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $productName = null; // Storing product name for historical purposes

    public function getId(): ?int
    {
        return $this->id;
    }

    // CORRECTED: Getter and setter names changed to match the property name
    public function getOrder(): ?Order
    {
        return $this->order;
    }

    public function setOrder(?Order $order): static
    {
        $this->order = $order;
        return $this;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): static
    {
        $this->product = $product;
        if ($product) {
            // It's good practice to store the product name and price
            // at the time of order creation in the OrderItem itself,
            // in case the original product is modified or deleted later.
            $this->setProductName($product->getName());
            $this->setPrice($product->getPrice());
        }
        return $this;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;
        return $this;
    }

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function setPrice(float $price): static
    {
        $this->price = $price;
        return $this;
    }

    public function getProductName(): ?string
    {
        return $this->productName;
    }

    public function setProductName(?string $productName): static
    {
        $this->productName = $productName;
        return $this;
    }


    /**
     * Calculate subtotal for this item
     */
    public function getSubtotal(): float
    {
        // Ensure price and quantity are not null before calculation
        if ($this->price !== null && $this->quantity !== null) {
            return $this->price * $this->quantity;
        }
        return 0.0; // Return 0 if data is incomplete
    }
}
