<?php

namespace App\Service;

use App\Entity\Facture;
use Atgp\FacturX\Writer;
use Atgp\FacturX\Utils\ProfileHandler;
use Twig\Environment;

class FacturxService
{
    private string $projectDir;
    private Environment $twig;

    public function __construct(Environment $twig,string $projectDir)
    {
        $this->projectDir = $projectDir;
        $this->twig = $twig;
    }


    /**
     * Génère le XML Factur-X et le sauvegarde dans public/factures/xml/
     */
    public function buildXml(Facture $facture): string
    {
        $xml = new \DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;

        // Racine
        $facturx = $xml->createElement('rsm:CrossIndustryInvoice');
        $facturx->setAttribute('xmlns:rsm', 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100');
        $facturx->setAttribute('xmlns:ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');
        $facturx->setAttribute('xmlns:udt', 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100');
        $facturx->setAttribute('xmlns:qdt', 'urn:un:unece:uncefact:data:standard:QualifiedDataType:100');
        $xml->appendChild($facturx);

        // -----------------------
        // ExchangedDocumentContext (obligatoire pour BASIC)
        // -----------------------
        $context = $xml->createElement('rsm:ExchangedDocumentContext');
        $guideline = $xml->createElement('ram:GuidelineSpecifiedDocumentContextParameter');
        $guideline->appendChild($xml->createElement('ram:ID', 'urn:cen.eu:en16931:2017'));
        $context->appendChild($guideline);
        $facturx->appendChild($context);

        // -----------------------
        // ExchangedDocument
        // -----------------------
        $doc = $xml->createElement('rsm:ExchangedDocument');
        $doc->appendChild($xml->createElement('ram:ID', $facture->getNumeroFacture()));
        $doc->appendChild($xml->createElement('ram:TypeCode', '380')); // Facture
        $issueDate = $xml->createElement('ram:IssueDateTime');
        $dateTime = $xml->createElement('udt:DateTimeString', $facture->getDateFacture()->format('Ymd'));
        $dateTime->setAttribute('format', '102');
        $issueDate->appendChild($dateTime);
        $doc->appendChild($issueDate);
        $facturx->appendChild($doc);

        // -----------------------
        // SupplyChainTradeTransaction
        // -----------------------
        $transaction = $xml->createElement('rsm:SupplyChainTradeTransaction');

        // Parties
        $tradeAgreement = $xml->createElement('ram:ApplicableHeaderTradeAgreement');

        // Seller
        $seller = $xml->createElement('ram:SellerTradeParty');
        $seller->appendChild($xml->createElement('ram:Name', $facture->getNomFournisseur()));
        $seller->appendChild($xml->createElement('ram:ID', $facture->getSirenFournisseur()));
        $tradeAgreement->appendChild($seller);

        // Buyer
        $buyer = $xml->createElement('ram:BuyerTradeParty');
        $buyer->appendChild($xml->createElement('ram:Name', $facture->getNomAcheteur()));
        $buyer->appendChild($xml->createElement('ram:ID', $facture->getSirenAcheteur()));
        $tradeAgreement->appendChild($buyer);

        $transaction->appendChild($tradeAgreement);

        // Montants
        $tradeSettlement = $xml->createElement('ram:ApplicableHeaderTradeSettlement');
        $tradeSettlement->appendChild($xml->createElement('ram:GrandTotalAmount', number_format($facture->getTotalTTC(), 2, '.', '')));
        $tradeSettlement->appendChild($xml->createElement('ram:DuePayableAmount', number_format($facture->getNetAPayer(), 2, '.', '')));
        $transaction->appendChild($tradeSettlement);

        // Lignes de facture
        foreach ($facture->getLignes()->toArray() as $ligne) {
            $lineItem = $xml->createElement('ram:IncludedSupplyChainTradeLineItem');

            $tradeLine = $xml->createElement('ram:TradeLineItem');
            $tradeLine->appendChild($xml->createElement('ram:Name', $ligne->getDesignation()));
            $tradeLine->appendChild($xml->createElement('ram:InvoicedQuantity', $ligne->getQuantite()));
            $tradeLine->appendChild($xml->createElement('ram:UnitPrice', number_format($ligne->getPrixUnitaireHT(), 2, '.', '')));

            // Montants
            $monetarySummation = $xml->createElement('ram:SpecifiedTradeSettlementLineMonetarySummation');
            $monetarySummation->appendChild($xml->createElement('ram:LineTotalAmount', number_format($ligne->getMontantTTC(), 2, '.', '')));
            $tradeLine->appendChild($monetarySummation);

            // ✅ Obligatoire EN16931 : ApplicableTradeTax
            $tax = $xml->createElement('ram:ApplicableTradeTax');
            $tax->appendChild($xml->createElement('ram:CalculatedAmount', number_format($ligne->getMontantTVA(), 2, '.', '')));
            $tax->appendChild($xml->createElement('ram:TypeCode', 'VAT'));
            $tax->appendChild($xml->createElement('ram:CategoryCode', 'S'));
            $tax->appendChild($xml->createElement('ram:RateApplicablePercent', number_format($ligne->getTauxTVA(), 2, '.', '')));
            $tradeLine->appendChild($tax);

            $lineItem->appendChild($tradeLine);
            $transaction->appendChild($lineItem);
        }

        $facturx->appendChild($transaction);

        // Sauvegarde dans public/factures/xml
        $xmlDir = $this->projectDir . '/public/factures/xml';
        if (!is_dir($xmlDir)) mkdir($xmlDir, 0777, true);

        $xmlFileName = $xmlDir . '/facture_' . $facture->getNumeroFacture() . '_fx.xml';
        $xml->save($xmlFileName);

        return $xmlFileName;
    }

    /**
     * Génère le PDF Factur-X avec XML imbriqué
     */
    public function buildPdfFacturX(Facture $facture, string $outputPdfPath): void
    {
        // Générer le XML
        $xmlFile = $this->buildXml($facture);
        $xmlContent = file_get_contents($xmlFile);

        // Générer un PDF simple avec le contenu HTML de la facture
        $html = $this->generateHtmlForPdf($facture); // ta méthode HTML

        $tmpPdf = tempnam(sys_get_temp_dir(), 'fx_') . '.pdf';
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->render();
        file_put_contents($tmpPdf, $dompdf->output());

        // Utiliser Writer pour imbriquer le XML
        $writer = new Writer();
        $pdfContent = $writer->generate(
            file_get_contents($tmpPdf),
            $xmlContent,
            ProfileHandler::PROFILE_FACTURX_BASIC,   // ou EXTENDED selon ton besoin
            false,                    // valider XSD
            [],                      // attachments
            true                     // ajouter logo
        );

        file_put_contents($outputPdfPath, $pdfContent);

        // Supprimer temporaire
        unlink($tmpPdf);
    }

    private function generateHtmlForPdf(Facture $facture): string
    {
        return $this->twig->render('facture/pdf_template.html.twig', [
            'facture' => $facture
        ]);
    }
}
