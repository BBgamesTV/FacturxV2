<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class PaymentMeans
{
#[ORM\Id]
#[ORM\GeneratedValue]
#[ORM\Column(type: "integer")]
private int $id;

#[ORM\ManyToOne(targetEntity: Facture::class, inversedBy: "paymentMeans")]
#[ORM\JoinColumn(name: "facture_id", referencedColumnName: "id", nullable: false)]
private ?Facture $facture;

#[ORM\Column(type: "string", length: 10)]
private string $code;

#[ORM\Column(type: "string", length: 255, nullable: true)]
private ?string $information = null;

public function getId(): int { return $this->id; }
public function getFacture(): ?Facture { return $this->facture; }
public function setFacture(?Facture $facture): self { $this->facture = $facture; return $this; }
public function getCode(): string { return $this->code; }
public function setCode(string $code): self { $this->code = $code; return $this; }
public function getInformation(): ?string { return $this->information; }
public function setInformation(?string $information): self { $this->information = $information; return $this; }
}