<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class FactureLigne
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Facture::class, inversedBy: "lignes")]
    #[ORM\JoinColumn(nullable: false)]
    private Facture $facture;

    #[ORM\Column(type: "string", length: 255)]
    private string $designation; // description du produit/service

    #[ORM\Column(type: "string", length: 50, nullable: true)]
    private ?string $reference = null; // référence produit

    #[ORM\Column(type: "decimal", precision: 10, scale: 2)]
    private float $quantite;

    #[ORM\Column(type: "string", length: 20, nullable: true)]
    private ?string $unite = null; // unité de mesure (kg, h, pcs...)

    #[ORM\Column(type: "decimal", precision: 10, scale: 2)]
    private float $prixUnitaireHT;

    #[ORM\Column(type: "decimal", precision: 5, scale: 2)]
    private float $tauxTVA;

    #[ORM\Column(type: "decimal", precision: 10, scale: 2)]
    private float $montantHT;

    #[ORM\Column(type: "decimal", precision: 10, scale: 2)]
    private float $montantTVA;

    #[ORM\Column(type: "decimal", precision: 10, scale: 2)]
    private float $montantTTC;

    // ✅ Ajout du numéro de ligne
    #[ORM\Column(type: "integer", nullable: true)]
    private ?int $numeroLigne = null;

    // GETTERS & SETTERS...

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumeroLigne(): ?int
    {
        return $this->numeroLigne;
    }

    public function setNumeroLigne(?int $numeroLigne): self
    {
        $this->numeroLigne = $numeroLigne;
        return $this;
    }

    public function getFacture(): Facture
    {
        return $this->facture;
    }
    public function setFacture(?Facture $facture): self
    {
        $this->facture = $facture;
        return $this;
    }

    public function getDesignation(): string
    {
        return $this->designation;
    }
    public function setDesignation(string $designation): self
    {
        $this->designation = $designation;
        return $this;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }
    public function setReference(?string $reference): self
    {
        $this->reference = $reference;
        return $this;
    }

    public function getQuantite(): float
    {
        return $this->quantite;
    }
    public function setQuantite(float $quantite): self
    {
        $this->quantite = $quantite;
        return $this;
    }

    public function getUnite(): ?string
    {
        return $this->unite;
    }
    public function setUnite(?string $unite): self
    {
        $this->unite = $unite;
        return $this;
    }

    public function getPrixUnitaireHT(): float
    {
        return $this->prixUnitaireHT;
    }
    public function setPrixUnitaireHT(float $prixUnitaireHT): self
    {
        $this->prixUnitaireHT = $prixUnitaireHT;
        return $this;
    }

    public function getTauxTVA(): float
    {
        return $this->tauxTVA;
    }
    public function setTauxTVA(float $tauxTVA): self
    {
        $this->tauxTVA = $tauxTVA;
        return $this;
    }

    public function getMontantHT(): float
    {
        return $this->prixUnitaireHT * $this->quantite;
    }
    public function setMontantHT(float $montantHT): self
    {
        $this->montantHT = $montantHT;
        return $this;
    }

    public function getMontantTVA(): float
    {
        return $this->getMontantHT() * ($this->tauxTVA / 100);
    }
    public function setMontantTVA(float $montantTVA): self
    {
        $this->montantTVA = $montantTVA;
        return $this;
    }

    public function getMontantTTC(): float
    {
        return $this->getMontantHT() + $this->getMontantTVA();
    }
    public function setMontantTTC(float $montantTTC): self
    {
        $this->montantTTC = $montantTTC;
        return $this;
    }
}
