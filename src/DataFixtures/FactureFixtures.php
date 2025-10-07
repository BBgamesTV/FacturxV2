<?php

namespace App\DataFixtures;

use App\Entity\Client;
use App\Entity\Facture;
use App\Entity\FactureLigne;
use App\Entity\FactureAllowanceCharge;
use App\Entity\PaymentMeans;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class FactureFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // ========== Client Fournisseur ==========
        $fournisseur = new Client();
        $fournisseur
            ->setNom('Oroya SARL')
            ->setSiren('123456789')
            ->setSiret('12345678900025')
            ->setNumeroTva('FR00123456789')
            ->setCodePays('FR')
            ->setEmail('contact@oroya.fr')
            ->setAdresse('5 rue de la République')
            ->setVille('Tours')
            ->setCodePostal('37000');
        $manager->persist($fournisseur);

        // ========== Client Acheteur ==========
        $acheteur = new Client();
        $acheteur
            ->setNom('Société ClientTest')
            ->setSiren('987654321')
            ->setNumeroTva('FR00987654321')
            ->setCodePays('FR')
            ->setEmail('client@demo.fr')
            ->setAdresse('27 avenue du Général')
            ->setVille('Poitiers')
            ->setCodePostal('86000');
        $manager->persist($acheteur);

        // ========== Facture ==========
        $facture = new Facture();
        $facture
            ->setNumeroFacture('F2025-001')
            ->setDateFacture(new \DateTime('2025-10-07'))
            ->setDevise('EUR')
            ->setTypeFacture('FA') // FA: Facture, FC: Avoir, FN: Note de frais
            ->setCommandeAcheteur('PO-45678')
            ->setDateEcheance((new \DateTime())->modify('+30 days'))
            ->setDateLivraison(new \DateTime('2025-10-08'))
            ->setModePaiement('Virement')
            ->setReferencePaiement('REF-002346')
            ->setCommentaire('Merci de votre confiance.')
            ->setFournisseur($fournisseur)
            ->setAcheteur($acheteur)
            ->setNetApayer('0.0') // Valeur initiale, sera calculée plus tard
            ->setCharges(0.0);

        // ========== Lignes de facture ==========
        $ligne1 = new FactureLigne();
        $ligne1
            ->setDesignation('Licence Logiciel Pro')
            ->setReference('LIC-2025')
            ->setQuantite(2)
            ->setUnite('unité')
            ->setPrixUnitaireHt(500)
            ->setTauxTva(20);
        $montantHT1 = $ligne1->getQuantite() * $ligne1->getPrixUnitaireHt();
        $montantTVA1 = $montantHT1 * ($ligne1->getTauxTva() / 100);
        $ligne1->setMontantHt($montantHT1)
            ->setMontantTva($montantTVA1)
            ->setMontantTtc($montantHT1 + $montantTVA1);
        $ligne1->setFacture($facture);
        $facture->addLigne($ligne1);
        $manager->persist($ligne1);

        $ligne2 = new FactureLigne();
        $ligne2
            ->setDesignation('Prestation installation')
            ->setReference('INST-01')
            ->setQuantite(1)
            ->setUnite('prestation')
            ->setPrixUnitaireHt(200)
            ->setTauxTva(10);
        $montantHT2 = $ligne2->getQuantite() * $ligne2->getPrixUnitaireHt();
        $montantTVA2 = $montantHT2 * ($ligne2->getTauxTva() / 100);
        $ligne2->setMontantHt($montantHT2)
            ->setMontantTva($montantTVA2)
            ->setMontantTtc($montantHT2 + $montantTVA2);
        $ligne2->setFacture($facture);
        $facture->addLigne($ligne2);
        $manager->persist($ligne2);

        // ========== Allowance/Charge ==========
        $allowance = new FactureAllowanceCharge();
        $allowance
            ->setAmount(50) // Remise
            ->setTaxRate(20)
            ->setIsCharge(false)
            ->setReason('Remise fidélité')
            ->setFacture($facture);
        $facture->addAllowanceCharge($allowance);
        $manager->persist($allowance);

        // ========== PaymentMeans ==========
        $payment = new PaymentMeans();
        $payment
            ->setCode('VIR')
            ->setInformation('Paiement via IBAN FR76...')
            ->setFacture($facture);
        $facture->addPaymentMeans($payment);
        $manager->persist($payment);

        $manager->persist($facture);
        $manager->flush();
    }
}
