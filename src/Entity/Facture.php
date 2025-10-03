<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Facture
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $commandeAcheteur = null;

    #[ORM\ManyToOne(cascade: ["persist"])]
    private ?Client $fournisseur = null;

    #[ORM\ManyToOne(cascade: ["persist"])]
    private ?Client $acheteur = null;

    #[ORM\Column(type: "string", length: 50)]
    private string $numeroFacture;

    #[ORM\Column(type: "date")]
    private \DateTimeInterface $dateFacture;

    #[ORM\Column(type: "string", length: 10)]
    private string $typeFacture;

    #[ORM\Column(type: "string", length: 3)]
    private string $devise;

    #[ORM\Column(type: "decimal", precision: 10, scale: 2)]
    private float $netAPayer;

    #[ORM\Column(type: "date", nullable: true)]
    private ?\DateTimeInterface $dateEcheance = null;

    #[ORM\Column(type: "date", nullable: true)]
    private ?\DateTimeInterface $dateLivraison = null;

    #[ORM\Column(type: "string", length: 10, nullable: true)]
    private ?string $modePaiement = null;

    #[ORM\Column(type: "string", length: 100, nullable: true)]
    private ?string $referencePaiement = null;

    #[ORM\Column(type: "json", nullable: true)]
    private ?array $tvaDetails = null;

    #[ORM\Column(type: "decimal", precision: 10, scale: 2, nullable: true)]
    private ?float $remisePied = 0;

    #[ORM\Column(type: "decimal", precision: 10, scale: 2, nullable: true)]
    private ?float $chargesPied = 0;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $referenceContrat = null;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $referenceBonLivraison = null;

    #[ORM\Column(type: "string", length: 20)]
    private string $profilFacturX;

    #[ORM\OneToMany(mappedBy: "facture", targetEntity: FactureLigne::class, cascade: ["persist", "remove"], orphanRemoval: true)]
    private Collection $lignes;

    public function __construct()
    {
        $this->lignes = new ArrayCollection();
    }

    // ---------------- GETTERS & SETTERS ----------------

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCommandeAcheteur(): ?string
    {
        return $this->commandeAcheteur;
    }

    public function setCommandeAcheteur(?string $commandeAcheteur): self
    {
        $this->commandeAcheteur = $commandeAcheteur;
        return $this;
    }

    public function getFournisseur(): ?Client
    {
        return $this->fournisseur;
    }

    public function setFournisseur(?Client $fournisseur): self
    {
        $this->fournisseur = $fournisseur;
        return $this;
    }

    public function getAcheteur(): ?Client
    {
        return $this->acheteur;
    }

    public function setAcheteur(?Client $acheteur): self
    {
        $this->acheteur = $acheteur;
        return $this;
    }

    public function getNumeroFacture(): string
    {
        return $this->numeroFacture;
    }

    public function setNumeroFacture(string $numeroFacture): self
    {
        $this->numeroFacture = $numeroFacture;
        return $this;
    }

    public function getDateFacture(): \DateTimeInterface
    {
        return $this->dateFacture;
    }

    public function setDateFacture(\DateTimeInterface $dateFacture): self
    {
        $this->dateFacture = $dateFacture;
        return $this;
    }

    public function getTypeFacture(): string
    {
        return $this->typeFacture;
    }

    public function setTypeFacture(string $typeFacture): self
    {
        $this->typeFacture = $typeFacture;
        return $this;
    }

    public function getDevise(): string
    {
        return $this->devise;
    }

    public function setDevise(string $devise): self
    {
        $this->devise = $devise;
        return $this;
    }

    public function getNetAPayer(): float
    {
        return $this->netAPayer;
    }

    public function setNetAPayer(float $netAPayer): self
    {
        $this->netAPayer = $netAPayer;
        return $this;
    }

    public function getDateEcheance(): ?\DateTimeInterface
    {
        return $this->dateEcheance;
    }

    public function setDateEcheance(?\DateTimeInterface $dateEcheance): self
    {
        $this->dateEcheance = $dateEcheance;
        return $this;
    }

    public function getDateLivraison(): ?\DateTimeInterface
    {
        return $this->dateLivraison;
    }

    public function setDateLivraison(?\DateTimeInterface $dateLivraison): self
    {
        $this->dateLivraison = $dateLivraison;
        return $this;
    }

    public function getModePaiement(): ?string
    {
        return $this->modePaiement;
    }

    public function setModePaiement(?string $modePaiement): self
    {
        $this->modePaiement = $modePaiement;
        return $this;
    }

    public function getReferencePaiement(): ?string
    {
        return $this->referencePaiement;
    }

    public function setReferencePaiement(?string $referencePaiement): self
    {
        $this->referencePaiement = $referencePaiement;
        return $this;
    }

    public function getTvaDetails(): ?array
    {
        return $this->tvaDetails;
    }

    public function setTvaDetails(?array $tvaDetails): self
    {
        $this->tvaDetails = $tvaDetails;
        return $this;
    }

    public function getRemisePied(): ?float
    {
        return $this->remisePied;
    }

    public function setRemisePied(?float $remisePied): self
    {
        $this->remisePied = $remisePied;
        return $this;
    }

    public function getChargesPied(): ?float
    {
        return $this->chargesPied;
    }

    public function setChargesPied(?float $chargesPied): self
    {
        $this->chargesPied = $chargesPied;
        return $this;
    }

    public function getReferenceContrat(): ?string
    {
        return $this->referenceContrat;
    }

    public function setReferenceContrat(?string $referenceContrat): self
    {
        $this->referenceContrat = $referenceContrat;
        return $this;
    }

    public function getReferenceBonLivraison(): ?string
    {
        return $this->referenceBonLivraison;
    }

    public function setReferenceBonLivraison(?string $referenceBonLivraison): self
    {
        $this->referenceBonLivraison = $referenceBonLivraison;
        return $this;
    }

    public function getProfilFacturX(): string
    {
        return $this->profilFacturX;
    }

    public function setProfilFacturX(string $profilFacturX): self
    {
        $this->profilFacturX = $profilFacturX;
        return $this;
    }

    public function getLignes(): Collection
    {
        return $this->lignes;
    }

    public function addLigne(FactureLigne $ligne): self
    {
        if (!$this->lignes->contains($ligne)) {
            $this->lignes[] = $ligne;
            $ligne->setFacture($this);
        }
        return $this;
    }

    public function removeLigne(FactureLigne $ligne): self
    {
        if ($this->lignes->removeElement($ligne)) {
            if ($ligne->getFacture() === $this) {
                $ligne->setFacture(null);
            }
        }
        return $this;
    }

    // ---------------- CALCUL DES TOTALS ----------------

    public function getTotalHT(): float
    {
        return array_sum(array_map(fn($l) => $l->getMontantHT(), $this->lignes->toArray()));
    }

    public function getTotalTVA(): float
    {
        return array_sum(array_map(fn($l) => $l->getMontantTVA(), $this->lignes->toArray()));
    }

    public function getTotalTTC(): float
    {
        return array_sum(array_map(fn($l) => $l->getMontantTTC(), $this->lignes->toArray()));
    }
}
