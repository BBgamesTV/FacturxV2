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

    #[ORM\Column(type: "string", length: 50)]
    private string $numeroFacture;

    #[ORM\Column(type: "date")]
    private \DateTimeInterface $dateFacture;

    #[ORM\Column(type: "string", length: 10)]
    private string $typeFacture;

    #[ORM\Column(type: "string", length: 3)]
    private string $devise;

    #[ORM\Column(type: "string", length: 255)]
    private string $nomFournisseur;

    #[ORM\Column(type: "string", length: 20)]
    private string $sirenFournisseur;

    #[ORM\Column(type: "string", length: 20, nullable: true)]
    private ?string $siretFournisseur = null;

    #[ORM\Column(type: "string", length: 20, nullable: true)]
    private ?string $tvaFournisseur = null;

    #[ORM\Column(type: "string", length: 2)]
    private string $codePaysFournisseur;

    #[ORM\Column(type: "string", length: 255)]
    private string $emailFournisseur;

    #[ORM\Column(type: "string", length: 255)]
    private string $nomAcheteur;

    #[ORM\Column(type: "string", length: 20)]
    private string $sirenAcheteur;

    #[ORM\Column(type: "string", length: 255)]
    private string $emailAcheteur;

    #[ORM\Column(type: "string", length: 20, nullable: true)]
    private ?string $commandeAcheteur = null;

    #[ORM\Column(type: "decimal", precision: 10, scale: 2)]
    private float $totalHT;

    #[ORM\Column(type: "decimal", precision: 10, scale: 2)]
    private float $totalTVA;

    #[ORM\Column(type: "decimal", precision: 10, scale: 2)]
    private float $totalTTC;

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
    private iterable $lignes;
    public function __construct()
    {
        $this->lignes = new ArrayCollection();
    }


    // GETTERS & SETTERS

    public function getId(): ?int
    {
        return $this->id;
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

    public function getNomFournisseur(): string
    {
        return $this->nomFournisseur;
    }
    public function setNomFournisseur(string $nomFournisseur): self
    {
        $this->nomFournisseur = $nomFournisseur;
        return $this;
    }

    public function getSirenFournisseur(): string
    {
        return $this->sirenFournisseur;
    }
    public function setSirenFournisseur(string $sirenFournisseur): self
    {
        $this->sirenFournisseur = $sirenFournisseur;
        return $this;
    }

    public function getSiretFournisseur(): ?string
    {
        return $this->siretFournisseur;
    }
    public function setSiretFournisseur(?string $siretFournisseur): self
    {
        $this->siretFournisseur = $siretFournisseur;
        return $this;
    }

    public function getTvaFournisseur(): ?string
    {
        return $this->tvaFournisseur;
    }
    public function setTvaFournisseur(?string $tvaFournisseur): self
    {
        $this->tvaFournisseur = $tvaFournisseur;
        return $this;
    }

    public function getCodePaysFournisseur(): string
    {
        return $this->codePaysFournisseur;
    }
    public function setCodePaysFournisseur(string $codePaysFournisseur): self
    {
        $this->codePaysFournisseur = $codePaysFournisseur;
        return $this;
    }

    public function getEmailFournisseur(): string
    {
        return $this->emailFournisseur;
    }
    public function setEmailFournisseur(string $emailFournisseur): self
    {
        $this->emailFournisseur = $emailFournisseur;
        return $this;
    }

    public function getNomAcheteur(): string
    {
        return $this->nomAcheteur;
    }
    public function setNomAcheteur(string $nomAcheteur): self
    {
        $this->nomAcheteur = $nomAcheteur;
        return $this;
    }

    public function getSirenAcheteur(): string
    {
        return $this->sirenAcheteur;
    }
    public function setSirenAcheteur(string $sirenAcheteur): self
    {
        $this->sirenAcheteur = $sirenAcheteur;
        return $this;
    }

    public function getEmailAcheteur(): string
    {
        return $this->emailAcheteur;
    }
    public function setEmailAcheteur(string $emailAcheteur): self
    {
        $this->emailAcheteur = $emailAcheteur;
        return $this;
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

    public function getTotalHT(): float
    {
        return array_sum(array_map(fn($l) => $l->getMontantHT(), $this->lignes->toArray()));
    }
    public function setTotalHT(float $totalHT): self
    {
        $this->totalHT = $totalHT;
        return $this;
    }

    public function getTotalTVA(): float
    {
        return array_sum(array_map(fn($l) => $l->getMontantTVA(), $this->lignes->toArray()));
    }
    public function setTotalTVA(float $totalTVA): self
    {
        $this->totalTVA = $totalTVA;
        return $this;
    }

    public function getTotalTTC(): float
    {
        return array_sum(array_map(fn($l) => $l->getMontantTTC(), $this->lignes->toArray()));
    }
    public function setTotalTTC(float $totalTTC): self
    {
        $this->totalTTC = $totalTTC;
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

    public function getLignes()
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
}
