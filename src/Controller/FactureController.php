<?php

namespace App\Controller;

use Dompdf\Dompdf;
use Dompdf\Options;
use App\Entity\Facture;
use App\Entity\FactureLigne;
use App\Form\FactureType;
use App\Service\FacturxService;
use App\Repository\FactureRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class FactureController extends AbstractController
{
    #[Route('/facture/new', name: 'facture_new', methods: ['GET', 'POST'])]
    public function create(Request $request, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST')) {
            $facture = new Facture();

            // ✅ Hydratation des champs simples
            $facture
                ->setNumeroFacture($request->request->get('numeroFacture'))
                ->setDateFacture(new \DateTime($request->request->get('dateFacture')))
                ->setTypeFacture($request->request->get('typeFacture'))
                ->setDevise($request->request->get('devise'))
                ->setNomFournisseur($request->request->get('nomFournisseur'))
                ->setSirenFournisseur($request->request->get('sirenFournisseur'))
                ->setSiretFournisseur($request->request->get('siretFournisseur'))
                ->setTvaFournisseur($request->request->get('tvaFournisseur'))
                ->setCodePaysFournisseur($request->request->get('codePaysFournisseur'))
                ->setEmailFournisseur($request->request->get('emailFournisseur'))
                ->setNomAcheteur($request->request->get('nomAcheteur'))
                ->setSirenAcheteur($request->request->get('sirenAcheteur'))
                ->setEmailAcheteur($request->request->get('emailAcheteur'))
                ->setCommandeAcheteur($request->request->get('commandeAcheteur'))
                ->setTotalHT((float) $request->request->get('totalHT'))
                ->setTotalTVA((float) $request->request->get('totalTVA'))
                ->setTotalTTC((float) $request->request->get('totalTTC'))
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

            // ✅ Gestion des lignes
            $lignes = $request->request->all('lignes'); // récupère le tableau lignes[0], lignes[1], ...
            foreach ($lignes as $ligneData) {
                $ligne = new FactureLigne();
                $ligne
                    ->setDesignation($ligneData['designation'])
                    ->setReference($ligneData['reference'] ?? null)
                    ->setQuantite((float) $ligneData['quantite'])
                    ->setUnite($ligneData['unite'] ?? null)
                    ->setPrixUnitaireHT((float) $ligneData['prixUnitaireHT'])
                    ->setTauxTVA((float) $ligneData['tauxTVA']);

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

            $em->persist($facture);
            $em->flush();

            $this->addFlash('success', 'Facture créée avec succès ✅');

            return $this->redirectToRoute('facture_index');
        }

        return $this->render('facture/new.html.twig');
    }

    #[Route('/facture/index', name: 'facture_index', methods: ['GET'])]
    public function index(FactureRepository $factureRepository): Response
    {
        return $this->render('facture/index.html.twig', [
            'factures' => $factureRepository->findAll(),
        ]);
    }

    #[Route('/{id}', name: 'facture_show', methods: ['GET'])]
    public function show(Facture $facture): Response
    {
        return $this->render('facture/show.html.twig', [
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

    #[Route('/facture/{id}/download', name: 'facture_download_facturx', methods: ['GET'])]
    public function downloadFacturx(Facture $facture, FacturxService $fxService): Response
    {
        $xmlFilePath = $this->getParameter('kernel.project_dir') . '/public/factures/' . $facture->getNumeroFacture() . '.xml';
        $xml = $fxService->buildXml($facture, $xmlFilePath);

        // Recrée le PDF avec Dompdf (comme dans new)
        $html = $this->renderView('facture/pdf_template.html.twig', [
            'facture' => $facture,
        ]);

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->setIsRemoteEnabled(true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $pdfSource = $this->getParameter('kernel.project_dir') . '/public/factures/facture_' . $facture->getId() . '_template.pdf';
        file_put_contents($pdfSource, $dompdf->output());

        $pdfDest = $this->getParameter('kernel.project_dir') . '/public/factures/facture_' . $facture->getId() . '_fx.pdf';
        $fxService->embedXmlInPdf($pdfSource, $xml, $pdfDest);

        return $this->file(
            $pdfDest,
            'facture_' . $facture->getNumeroFacture() . '.pdf',
            ResponseHeaderBag::DISPOSITION_INLINE
        );
    }
}
