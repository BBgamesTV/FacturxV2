<?php

namespace App\Controller;

use App\Entity\Facture;
use App\Entity\FactureLigne;
use App\Repository\FactureRepository;
use App\Entity\Client;
use App\Service\FacturxService;
use App\Form\FactureType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class FactureController extends AbstractController
{
    #[Route('/new', name: 'facture_new', methods: ['GET', 'POST'])]
    public function create(Request $request, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST')) {
            $facture = new Facture();

            // ✅ Hydratation des champs simples de la facture
            $facture
                ->setNumeroFacture($request->request->get('numeroFacture'))
                ->setDateFacture(new \DateTime($request->request->get('dateFacture')))
                ->setTypeFacture($request->request->get('typeFacture'))
                ->setDevise($request->request->get('devise'))
                ->setCommandeAcheteur($request->request->get('commandeAcheteur'))
                ->setNetAPayer((float) $request->request->get('netAPayer'))
                ->setDateEcheance($request->request->get('dateEcheance') ? new \DateTime($request->request->get('dateEcheance')) : null)
                ->setDateLivraison($request->request->get('dateLivraison') ? new \DateTime($request->request->get('dateLivraison')) : null)
                ->setModePaiement($request->request->get('modePaiement'))
                ->setReferencePaiement($request->request->get('referencePaiement'))
                ->setTvaDetails($request->request->get('tvaDetails') ? json_decode($request->request->get('tvaDetails'), true) : null)
                ->setRemisePied((float) $request->request->get('remisePied'))
                ->setChargesPied((float) $request->request->get('chargesPied'))
                ->setReferenceContrat($request->request->get('referenceContrat'))
                ->setReferenceBonLivraison($request->request->get('referenceBonLivraison'))
                ->setProfilFacturX($request->request->get('profilFacturX'));

            // ✅ Création du client fournisseur
        if (!$facture->getFournisseur()) {
            $fournisseur = new Client();
            $fournisseur
                ->setNom($request->request->get('nomFournisseur'))
                ->setSiren($request->request->get('sirenFournisseur'))
                ->setSiret($request->request->get('siretFournisseur'))
                ->setNumeroTva($request->request->get('tvaFournisseur'))
                ->setCodePays($request->request->get('codePaysFournisseur'))
                ->setEmail($request->request->get('emailFournisseur'))
                ->setAdresse($request->request->get('adresseFournisseur'))
                ->setVille($request->request->get('villeFournisseur'))
                ->setCodePostal($request->request->get('codePostalFournisseur'));
            $em->persist($fournisseur);
            $facture->setFournisseur($fournisseur);
        }

        // ✅ Acheteur
        if (!$facture->getAcheteur()) {
            $acheteur = new Client();
            $acheteur
                ->setNom($request->request->get('nomAcheteur'))
                ->setSiren($request->request->get('sirenAcheteur'))
                ->setNumeroTva($request->request->get('tvaAcheteur'))
                ->setCodePays($request->request->get('codePaysAcheteur'))
                ->setEmail($request->request->get('emailAcheteur'))
                ->setAdresse($request->request->get('adresseAcheteur'))
                ->setVille($request->request->get('villeAcheteur'))
                ->setCodePostal($request->request->get('codePostalAcheteur'));
            $em->persist($acheteur);
            $facture->setAcheteur($acheteur);
        }

            // ✅ Gestion des lignes
            $lignes = $request->request->all('lignes');
            foreach ($lignes as $index => $ligneData) {
                $ligne = new FactureLigne();
                $ligne
                    ->setDesignation($ligneData['designation'])
                    ->setReference($ligneData['reference'] ?? null)
                    ->setQuantite((float) $ligneData['quantite'])
                    ->setUnite($ligneData['unite'] ?? null)
                    ->setPrixUnitaireHT((float) $ligneData['prixUnitaireHT'])
                    ->setTauxTVA((float) $ligneData['tauxTVA'])
                    ->setNumeroLigne($index + 1); // ✅ Respect BR-21 (identifiant unique par ligne)

                // Calculs automatiques
                $montantHT = $ligne->getQuantite() * $ligne->getPrixUnitaireHT();
                $montantTVA = $montantHT * ($ligne->getTauxTVA() / 100);
                $montantTTC = $montantHT + $montantTVA;

                $ligne
                    ->setMontantHT($montantHT)
                    ->setMontantTVA($montantTVA)
                    ->setMontantTTC($montantTTC);

                $facture->addLigne($ligne);
            }

            $em->persist($fournisseur);
            $em->persist($acheteur);
            $em->persist($facture);
            $em->flush();

            $this->addFlash('success', 'Facture créée avec succès ✅');

            return $this->redirectToRoute('facture_index');
        }

        return $this->render('facture/new.html.twig');
    }

    #[Route('/', name: 'facture_index', methods: ['GET'])]
    public function index(FactureRepository $factureRepository): Response
    {
        return $this->render('facture/index.html.twig', [
            'factures' => $factureRepository->findAll(),
        ]);
    }

    #[Route('/{id}', name: 'facture_show', methods: ['GET'])]
    public function show(Facture $facture): Response
    {
        return $this->render('facture/pdf_template.html.twig', [
            'facture' => $facture,
        ]);
    }

    #[Route('/{id}/edit', name: 'facture_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Facture $facture, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(FactureType::class, $facture);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Facture mise à jour ✅');
            return $this->redirectToRoute('facture_index');
        }

        return $this->render('facture/edit.html.twig', [
            'facture' => $facture,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/delete', name: 'facture_delete', methods: ['POST'])]
    public function delete(Request $request, Facture $facture, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $facture->getId(), $request->request->get('_token'))) {
            $em->remove($facture);
            $em->flush();
            $this->addFlash('danger', 'Facture supprimée ❌');
        }

        return $this->redirectToRoute('facture_index');
    }

    #[Route('/{id}/download', name: 'facture_download_facturx', methods: ['GET'])]
    public function downloadFacturx(Facture $facture, FacturxService $fxService): Response
    {
        $publicDir = $this->getParameter('kernel.project_dir') . '/public/factures';
        if (!is_dir($publicDir)) {
            mkdir($publicDir, 0777, true);
        }

        $pdfFileName = 'facture_' . $facture->getNumeroFacture() . '_fx.pdf';
        $pdfFilePath = $publicDir . '/' . $pdfFileName;

        $fxService->buildPdfFacturX($facture, $pdfFilePath);

        return $this->file($pdfFilePath, $pdfFileName, ResponseHeaderBag::DISPOSITION_INLINE);
    }
}
