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

    public function __construct(Environment $twig, string $projectDir)
    {
        $this->projectDir = $projectDir;
        $this->twig = $twig;
    }

    /**
     * Génère le XML Factur-X BASIC conforme EN16931
     */
    public function buildXml(Facture $facture): string
    {
        $xml = new \DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;

        // Racine
        $root = $xml->createElement('rsm:CrossIndustryInvoice');
        $root->setAttribute('xmlns:rsm', 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100');
        $root->setAttribute('xmlns:ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');
        $root->setAttribute('xmlns:udt', 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100');
        $root->setAttribute('xmlns:qdt', 'urn:un:unece:uncefact:data:standard:QualifiedDataType:100');
        $xml->appendChild($root);

        // ExchangedDocumentContext
        $context = $xml->createElement('rsm:ExchangedDocumentContext');
        $guideline = $xml->createElement('ram:GuidelineSpecifiedDocumentContextParameter');
        $guideline->appendChild($xml->createElement('ram:ID', 'urn:cen.eu:en16931:2017'));
        $context->appendChild($guideline);
        $root->appendChild($context);

        // ExchangedDocument
        $doc = $xml->createElement('rsm:ExchangedDocument');
        $doc->appendChild($xml->createElement('ram:ID', $facture->getNumeroFacture()));
        $issueDateTime = $xml->createElement('ram:IssueDateTime');
        $date = $xml->createElement('udt:DateTimeString', $facture->getDateFacture()->format('Ymd'));
        $date->setAttribute('format', '102');
        $issueDateTime->appendChild($date);
        $doc->appendChild($issueDateTime);
        $root->appendChild($doc);

        // SupplyChainTradeTransaction
        $transaction = $xml->createElement('rsm:SupplyChainTradeTransaction');

        // Lignes de facture
        foreach ($facture->getLignes() as $index => $ligne) {
            $lineItem = $xml->createElement('ram:IncludedSupplyChainTradeLineItem');

            // Ligne ID
            $assocDocLine = $xml->createElement('ram:AssociatedDocumentLineDocument');
            $assocDocLine->appendChild($xml->createElement('ram:LineID', $index + 1));
            $lineItem->appendChild($assocDocLine);

            // Accord sur la ligne (quantité)
            $lineAgreement = $xml->createElement('ram:SpecifiedLineTradeAgreement');
            $billedQty = $xml->createElement('ram:BilledQuantity', number_format($ligne->getQuantite(), 2, '.', ''));
            $billedQty->setAttribute('unitCode', $ligne->getUnite() ?: 'C62');
            $lineAgreement->appendChild($billedQty);
            $lineItem->appendChild($lineAgreement);

            // Livraison sur la ligne
            $lineDelivery = $xml->createElement('ram:SpecifiedLineTradeDelivery');
            $lineItem->appendChild($lineDelivery);

            // Montants sur la ligne
            $lineSettlement = $xml->createElement('ram:SpecifiedLineTradeSettlement');
            $lineSettlement->appendChild($xml->createElement('ram:LineTotalAmount', number_format($ligne->getMontantHT(), 2, '.', '')));

            // TVA
            $tax = $xml->createElement('ram:ApplicableTradeTax');
            $tax->appendChild($xml->createElement('ram:CalculatedAmount', number_format($ligne->getMontantTVA(), 2, '.', '')));
            $tax->appendChild($xml->createElement('ram:TypeCode', 'VAT'));
            $tax->appendChild($xml->createElement('ram:CategoryCode', 'S'));
            $tax->appendChild($xml->createElement('ram:RateApplicablePercent', number_format($ligne->getTauxTVA(), 2, '.', '')));
            $lineSettlement->appendChild($tax);

            // Produit
            $product = $xml->createElement('ram:SpecifiedTradeProduct');
            $product->appendChild($xml->createElement('ram:Name', $ligne->getDesignation()));
            $lineSettlement->appendChild($product);

            $lineItem->appendChild($lineSettlement);
            $transaction->appendChild($lineItem);
        }

        // Parties
        $tradeAgreement = $xml->createElement('ram:ApplicableHeaderTradeAgreement');

        // Fournisseur
        $seller = $xml->createElement('ram:SellerTradeParty');
        $taxReg = $xml->createElement('ram:SpecifiedTaxRegistration');
        $taxReg->appendChild($xml->createElement('ram:ID', $facture->getFournisseur()?->getSiren() ?? ''));
        $seller->appendChild($taxReg);
        $address = $xml->createElement('ram:PostalTradeAddress');
        $address->appendChild($xml->createElement('ram:LineOne', $facture->getFournisseur()?->getAdresse() ?? ''));
        $address->appendChild($xml->createElement('ram:CityName', $facture->getFournisseur()?->getVille() ?? ''));
        $address->appendChild($xml->createElement('ram:PostcodeCode', $facture->getFournisseur()?->getCodePostal() ?? ''));
        $address->appendChild($xml->createElement('ram:CountryID', $facture->getFournisseur()?->getCodePays() ?? 'FR'));
        $seller->appendChild($address);
        $tradeAgreement->appendChild($seller);

        // Acheteur
        $buyer = $xml->createElement('ram:BuyerTradeParty');
        $taxReg = $xml->createElement('ram:SpecifiedTaxRegistration');
        $taxReg->appendChild($xml->createElement('ram:ID', $facture->getAcheteur()?->getSiren() ?? ''));
        $buyer->appendChild($taxReg);
        $address = $xml->createElement('ram:PostalTradeAddress');
        $address->appendChild($xml->createElement('ram:LineOne', $facture->getAcheteur()?->getAdresse() ?? ''));
        $address->appendChild($xml->createElement('ram:CityName', $facture->getAcheteur()?->getVille() ?? ''));
        $address->appendChild($xml->createElement('ram:PostcodeCode', $facture->getAcheteur()?->getCodePostal() ?? ''));
        $address->appendChild($xml->createElement('ram:CountryID', $facture->getAcheteur()?->getCodePays() ?? 'FR'));
        $buyer->appendChild($address);
        $tradeAgreement->appendChild($buyer);

        $transaction->appendChild($tradeAgreement);

        // Montants globaux
        $tradeSettlement = $xml->createElement('ram:ApplicableHeaderTradeSettlement');

        $payee = $xml->createElement('ram:PayeeTradeParty');
        $tradeSettlement->appendChild($payee);

        $paymentMeans = $xml->createElement('ram:SpecifiedTradeSettlementPaymentMeans');
        $tradeSettlement->appendChild($paymentMeans);

        $taxTotal = $xml->createElement('ram:ApplicableTradeTax');
        $taxTotal->appendChild($xml->createElement('ram:CalculatedAmount', number_format($facture->getTotalTVA(), 2, '.', '')));
        $tradeSettlement->appendChild($taxTotal);

        $transaction->appendChild($tradeSettlement);

        $root->appendChild($transaction);

        // Sauvegarde
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
        $xmlFile = $this->buildXml($facture);
        $xmlContent = file_get_contents($xmlFile);

        $html = $this->generateHtmlForPdf($facture);

        $tmpPdf = tempnam(sys_get_temp_dir(), 'fx_') . '.pdf';
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->render();
        file_put_contents($tmpPdf, $dompdf->output());

        $writer = new Writer();
        $pdfContent = $writer->generate(
            file_get_contents($tmpPdf),
            $xmlContent,
            ProfileHandler::PROFILE_FACTURX_BASIC,
            false,
            [],
            true
        );

        file_put_contents($outputPdfPath, $pdfContent);
        unlink($tmpPdf);
    }

    private function generateHtmlForPdf(Facture $facture): string
    {
        return $this->twig->render('facture/pdf_template.html.twig', [
            'facture' => $facture
        ]);
    }
}
