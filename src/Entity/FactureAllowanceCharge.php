<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class FactureAllowanceCharge
{
#[ORM\Id]
#[ORM\GeneratedValue]
#[ORM\Column(type: "integer")]
private int $id;

#[ORM\ManyToOne(targetEntity: Facture::class, inversedBy: "allowanceCharges")]
#[ORM\JoinColumn(name: "facture_id", referencedColumnName: "id", nullable: false)]
private ?Facture $facture;

#[ORM\Column(type: "float")]
private float $amount;

#[ORM\Column(type: "float", nullable: true)]
private ?float $tax_rate = null;

#[ORM\Column(type: "boolean")]
private bool $is_charge;

#[ORM\Column(type: "string", length: 255, nullable: true)]
private ?string $reason = null;

public function getId(): int { return $this->id; }
public function getFacture(): ?Facture { return $this->facture; }
public function setFacture(?Facture $facture): self { $this->facture = $facture; return $this; }
public function getAmount(): float { return $this->amount; }
public function setAmount(float $amount): self { $this->amount = $amount; return $this; }
public function getTaxRate(): ?float { return $this->tax_rate; }
public function setTaxRate(?float $tax_rate): self { $this->tax_rate = $tax_rate; return $this; }
public function getIsCharge(): bool { return $this->is_charge; }
public function setIsCharge(bool $is_charge): self { $this->is_charge = $is_charge; return $this; }
public function getReason(): ?string { return $this->reason; }
public function setReason(?string $reason): self { $this->reason = $reason; return $this; }
}