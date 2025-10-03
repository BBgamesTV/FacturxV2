<?php

namespace App\Service;

use App\Entity\Facture;

use TCPDF;
use Twig\Environment;


class FacturxService
{
    private Environment $twig;

    public function __construct(Environment $twig)
    {
        $this->twig = $twig;
    }
    /**
     * Génère un XML Factur-X BASIC valide pour une facture.
     */
    public function buildXml(Facture $facture): string
    {
        $xml = new \DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;

        $facturx = $xml->createElement('rsm:CrossIndustryInvoice');
        $facturx->setAttribute('xmlns:rsm', 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100');
        $facturx->setAttribute('xmlns:udt', 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100');
        $facturx->setAttribute('xmlns:qdt', 'urn:un:unece:uncefact:data:standard:QualifiedDataType:100');
        $xml->appendChild($facturx);

        // Document
        $doc = $xml->createElement('rsm:ExchangedDocument');
        $doc->appendChild($xml->createElement('rsm:ID', $facture->getNumeroFacture()));
        $doc->appendChild($xml->createElement('rsm:TypeCode', $facture->getTypeFacture() ?? '380')); // Facture
        $facturx->appendChild($doc);

        // Parties
        $trade = $xml->createElement('rsm:ApplicableHeaderTradeAgreement');
        $seller = $xml->createElement('rsm:SellerTradeParty');
        $seller->appendChild($xml->createElement('rsm:Name', $facture->getNomFournisseur()));
        $seller->appendChild($xml->createElement('rsm:ID', $facture->getSirenFournisseur()));
        $trade->appendChild($seller);

        $buyer = $xml->createElement('rsm:BuyerTradeParty');
        $buyer->appendChild($xml->createElement('rsm:Name', $facture->getNomAcheteur()));
        $buyer->appendChild($xml->createElement('rsm:ID', $facture->getSirenAcheteur()));
        $trade->appendChild($buyer);

        $facturx->appendChild($trade);

        // Montants
        $settlement = $xml->createElement('rsm:ApplicableHeaderTradeSettlement');
        $settlement->appendChild($xml->createElement('rsm:GrandTotalAmount', number_format($facture->getTotalTTC(), 2, '.', '')));
        $settlement->appendChild($xml->createElement('rsm:DuePayableAmount', number_format($facture->getNetAPayer(), 2, '.', '')));
        $facturx->appendChild($settlement);

        // Lignes
        $linesParent = $xml->createElement('rsm:IncludedSupplyChainTradeLineItem');
        foreach ($facture->getLignes() as $ligne) {
            $line = $xml->createElement('rsm:TradeLineItem');
            $line->appendChild($xml->createElement('rsm:Name', $ligne->getDesignation()));
            $line->appendChild($xml->createElement('rsm:InvoicedQuantity', $ligne->getQuantite()));
            $line->appendChild($xml->createElement('rsm:UnitPrice', number_format($ligne->getPrixUnitaireHT(), 2, '.', '')));
            $line->appendChild($xml->createElement('rsm:LineTotalAmount', number_format($ligne->getMontantTTC(), 2, '.', '')));
            $linesParent->appendChild($line);
        }
        $facturx->appendChild($linesParent);

        $tmpXml = tempnam(sys_get_temp_dir(), 'facturx_') . '.xml';
        $xml->save($tmpXml);

        return $tmpXml;
    }

    /**
     * Génère un PDF Factur-X avec XML intégré
     */
    public function buildPdfFacturX(Facture $facture, string $outputPdfPath): void
    {
        // 1️⃣ Générer le XML
        $xmlPath = $this->buildXml($facture);

        // 2️⃣ Générer le PDF avec TCPDF
        $pdf = new TCPDF();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();

        // 3️⃣ Générer le HTML depuis Twig
        $html = $this->twig->render('/facture/pdf_template.html.twig', [
            'facture' => $facture
        ]);

        $pdf->writeHTML($html, true, false, true, false, '');

        // 4️⃣ Ajouter le XML en tant que pièce jointe (Factur-X compliant)
        $pdf->Annotation(
            0,
            0,
            1,
            1,
            $xmlPath,
            [
                'Subtype' => 'FileAttachment',
                'Name' => 'PushPin',
                'FS' => 'Factur-X.xml',
                'Contents' => 'Factur-X XML',
                'Type' => 'application/xml',
            ]
        );

        // 5️⃣ Ajouter les métadonnées
        $pdf->SetCreator('Factur-X PHP Service');
        $pdf->SetAuthor($facture->getNomFournisseur());
        $pdf->SetTitle("Facture {$facture->getNumeroFacture()}");
        $pdf->SetSubject('Factur-X Invoice');
        $pdf->SetKeywords('Factur-X, ZUGFeRD, Invoice, PDF, XML');

        // 6️⃣ Sauvegarde finale
        $pdf->Output($outputPdfPath, 'F');
    }
}
