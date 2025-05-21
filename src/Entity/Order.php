<?php

namespace App\Entity;

use App\Repository\OrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: '`order`')]
#[ORM\HasLifecycleCallbacks]
class Order
{
    // Define constants for statuses
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SHIPPED = 'shipped';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_CANCELLED = 'cancelled';

    // Add payment status constants
    public const PAYMENT_STATUS_PENDING = 'pending';
    public const PAYMENT_STATUS_PAID = 'paid';
    public const PAYMENT_STATUS_FAILED = 'failed';
    public const PAYMENT_STATUS_REFUNDED = 'refunded';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $reference = null;

    #[ORM\Column]
    private ?float $totalAmount = 0.0; // Stored total

    #[ORM\Column(length: 255)]
    private ?string $status = self::STATUS_PENDING; // Default status

    #[ORM\Column(length: 50)]
    private ?string $paymentStatus = self::PAYMENT_STATUS_PENDING; // Default payment status

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $paymentMethod = null;

    #[ORM\ManyToOne(inversedBy: 'orders')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    // CORRECTED: mappedBy should match the property name in OrderItem entity ('order')
    #[ORM\OneToMany(mappedBy: 'order', targetEntity: OrderItem::class, orphanRemoval: true, cascade: ['persist'])]
    private Collection $orderItems;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    // Assuming simple string storage for addresses
    #[ORM\Column(type: 'text')]
    private ?string $shippingAddress = null;

    #[ORM\Column(type: 'text')]
    private ?string $billingAddress = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $paymentReference = null; // e.g., Stripe Session ID or Payment Intent ID

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    // Add other timestamps if needed (shippedAt, deliveredAt)
#[ORM\Column(type: 'text', nullable: true)]
private ?string $notes = null;


    public function __construct()
    {
        $this->orderItems = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->reference = Uuid::v4()->toRfc4122();
        $this->status = self::STATUS_PENDING;
        $this->paymentStatus = self::PAYMENT_STATUS_PENDING;
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNotes(): ?string
{
    return $this->notes;
}
public function setNotes(?string $notes): self
{
    $this->notes = $notes;
    return $this;
}
    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function getTotalAmount(): ?float
    {
        return $this->totalAmount;
    }

    public function setTotalAmount(float $totalAmount): self
    {
        $this->totalAmount = $totalAmount;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
         if ($this->status !== $status) {
             $this->status = $status;
         }
        return $this;
    }

    public function getPaymentStatus(): ?string
    {
        return $this->paymentStatus;
    }

    public function setPaymentStatus(string $paymentStatus): self
    {
        if ($this->paymentStatus !== $paymentStatus) {
            $this->paymentStatus = $paymentStatus;
             if ($paymentStatus === self::PAYMENT_STATUS_PAID) {
                 $this->setPaidAt(new \DateTimeImmutable());
             } else {
                 $this->setPaidAt(null);
             }
        }
        return $this;
    }

    public function getPaymentMethod(): ?string
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(?string $paymentMethod): self
    {
        $this->paymentMethod = $paymentMethod;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @return Collection<int, OrderItem>
     */
    public function getOrderItems(): Collection
    {
        return $this->orderItems;
    }

    public function addOrderItem(OrderItem $orderItem): self
    {
        if (!$this->orderItems->contains($orderItem)) {
            $this->orderItems->add($orderItem);
            // Ensure the OrderItem knows about its Order
            // This method call now matches the corrected OrderItem entity
            if ($orderItem->getOrder() !== $this) {
                $orderItem->setOrder($this);
            }
        }
        return $this;
    }

    public function removeOrderItem(OrderItem $orderItem): self
    {
        if ($this->orderItems->removeElement($orderItem)) {
            // set the owning side to null (unless already changed)
            // This method call now matches the corrected OrderItem entity
            if ($orderItem->getOrder() === $this) {
                $orderItem->setOrder(null);
            }
        }
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getShippingAddress(): ?string
    {
        return $this->shippingAddress;
    }

    public function setShippingAddress(string $shippingAddress): self
    {
        $this->shippingAddress = $shippingAddress;
        return $this;
    }

    public function getBillingAddress(): ?string
    {
        return $this->billingAddress;
    }

    public function setBillingAddress(string $billingAddress): self
    {
        $this->billingAddress = $billingAddress;
        return $this;
    }

    public function getPaymentReference(): ?string
    {
        return $this->paymentReference;
    }

    public function setPaymentReference(?string $paymentReference): self
    {
        $this->paymentReference = $paymentReference;
        return $this;
    }

    public function getPaidAt(): ?\DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function setPaidAt(?\DateTimeImmutable $paidAt): self
    {
        $this->paidAt = $paidAt;
        return $this;
    }

    /**
     * Calcule le total de la commande et le stocke dans la propriété totalAmount
     */
    public function calculateAndSetTotal(): float
    {
        $total = 0.0;
        foreach ($this->orderItems as $item) {
            if ($item->getPrice() !== null && $item->getQuantity() !== null) {
                 $total += $item->getPrice() * $item->getQuantity();
            }
        }
        $this->setTotalAmount($total);
        return $total;
    }

    // You can keep a simple getTotal() method for convenience
    public function getTotal(): ?float
    {
        return $this->getTotalAmount();
    }

    // Add helper methods for status labels
     public function getStatusLabel(): string
     {
         $labels = [
             self::STATUS_PENDING => 'En attente',
             self::STATUS_PROCESSING => 'En traitement',
             self::STATUS_SHIPPED => 'Expédiée',
             self::STATUS_DELIVERED => 'Livrée',
             self::STATUS_CANCELLED => 'Annulée'
         ];
         return $labels[$this->status] ?? $this->status;
     }

     public function getPaymentStatusLabel(): string
     {
         $labels = [
             self::PAYMENT_STATUS_PENDING => 'En attente',
             self::PAYMENT_STATUS_PAID => 'Payée',
             self::PAYMENT_STATUS_FAILED => 'Échouée',
             self::PAYMENT_STATUS_REFUNDED => 'Remboursée'
         ];
         return $labels[$this->paymentStatus] ?? $this->paymentStatus;
     }
}
