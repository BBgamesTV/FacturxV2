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

        // ExchangedDocumentContext
        $context = $xml->createElement('rsm:ExchangedDocumentContext');
        $guideline = $xml->createElement('ram:GuidelineSpecifiedDocumentContextParameter');
        $guideline->appendChild($xml->createElement('ram:ID', 'urn:cen.eu:en16931:2017'));
        $context->appendChild($guideline);
        $facturx->appendChild($context);

        // ExchangedDocument
        $doc = $xml->createElement('rsm:ExchangedDocument');
        $doc->appendChild($xml->createElement('ram:ID', $facture->getNumeroFacture()));
        $doc->appendChild($xml->createElement('ram:TypeCode', '380'));
        $issueDate = $xml->createElement('ram:IssueDateTime');
        $dateTime = $xml->createElement('udt:DateTimeString', $facture->getDateFacture()->format('Ymd'));
        $dateTime->setAttribute('format', '102');
        $issueDate->appendChild($dateTime);
        $doc->appendChild($issueDate);
        $facturx->appendChild($doc);

        // SupplyChainTradeTransaction
        $transaction = $xml->createElement('rsm:SupplyChainTradeTransaction');

        // Lignes de facture
        foreach ($facture->getLignes()->toArray() as $index => $ligne) {
            $lineItem = $xml->createElement('ram:IncludedSupplyChainTradeLineItem');

            $assocDocLine = $xml->createElement('ram:AssociatedDocumentLineDocument');
            $assocDocLine->appendChild($xml->createElement('ram:LineID', $index + 1));
            $lineItem->appendChild($assocDocLine);

            $delivery = $xml->createElement('ram:SpecifiedLineTradeDelivery');
            $billedQty = $xml->createElement('ram:BilledQuantity', number_format($ligne->getQuantite(), 2, '.', ''));
            $billedQty->setAttribute('unitCode', $ligne->getUnite() ?: 'C62');
            $delivery->appendChild($billedQty);
            $lineItem->appendChild($delivery);

            $settlement = $xml->createElement('ram:SpecifiedLineTradeSettlement');
            $settlement->appendChild($xml->createElement('ram:LineTotalAmount', number_format($ligne->getMontantHT(), 2, '.', '')));

            $tax = $xml->createElement('ram:ApplicableTradeTax');
            $tax->appendChild($xml->createElement('ram:CalculatedAmount', number_format($ligne->getMontantTVA(), 2, '.', '')));
            $tax->appendChild($xml->createElement('ram:TypeCode', 'VAT'));
            $tax->appendChild($xml->createElement('ram:CategoryCode', 'S'));
            $tax->appendChild($xml->createElement('ram:RateApplicablePercent', number_format($ligne->getTauxTVA(), 2, '.', '')));
            $settlement->appendChild($tax);

            $price = $xml->createElement('ram:SpecifiedTradeProduct');
            $price->appendChild($xml->createElement('ram:Name', $ligne->getDesignation()));
            $settlement->appendChild($price);

            $lineItem->appendChild($settlement);
            $transaction->appendChild($lineItem);
        }

        // Parties & Montants globaux
        $tradeAgreement = $xml->createElement('ram:ApplicableHeaderTradeAgreement');

        // Seller
        $fournisseur = $facture->getFournisseur();
        $seller = $xml->createElement('ram:SellerTradeParty');
        $seller->appendChild($xml->createElement('ram:Name', $fournisseur ? $fournisseur->getNom() : ''));
        $seller->appendChild($xml->createElement('ram:ID', $fournisseur ? $fournisseur->getSiren() : ''));
        $sellerAddress = $xml->createElement('ram:PostalTradeAddress');
        $sellerAddress->appendChild($xml->createElement('ram:LineOne', $fournisseur?->getAdresse() ?? ''));
        $sellerAddress->appendChild($xml->createElement('ram:CityName', $fournisseur?->getVille() ?? ''));
        $sellerAddress->appendChild($xml->createElement('ram:PostcodeCode', $fournisseur?->getCodePostal() ?? ''));
        $sellerAddress->appendChild($xml->createElement('ram:CountryID', $fournisseur?->getCodePays() ?? 'FR'));
        $seller->appendChild($sellerAddress);
        $tradeAgreement->appendChild($seller);

        // Buyer
        $acheteur = $facture->getAcheteur();
        $buyer = $xml->createElement('ram:BuyerTradeParty');
        $buyer->appendChild($xml->createElement('ram:Name', $acheteur ? $acheteur->getNom() : ''));
        $buyer->appendChild($xml->createElement('ram:ID', $acheteur ? $acheteur->getSiren() : ''));
        $buyerAddress = $xml->createElement('ram:PostalTradeAddress');
        $buyerAddress->appendChild($xml->createElement('ram:LineOne', $acheteur?->getAdresse() ?? ''));
        $buyerAddress->appendChild($xml->createElement('ram:CityName', $acheteur?->getVille() ?? ''));
        $buyerAddress->appendChild($xml->createElement('ram:PostcodeCode', $acheteur?->getCodePostal() ?? ''));
        $buyerAddress->appendChild($xml->createElement('ram:CountryID', $acheteur?->getCodePays() ?? 'FR'));
        $buyer->appendChild($buyerAddress);
        $tradeAgreement->appendChild($buyer);

        $transaction->appendChild($tradeAgreement);

        // Montants globaux
        $tradeSettlement = $xml->createElement('ram:ApplicableHeaderTradeSettlement');
        $tradeSettlement->appendChild($xml->createElement('ram:InvoiceCurrencyCode', $facture->getDevise() ?: 'EUR'));
        $tradeSettlement->appendChild($xml->createElement('ram:GrandTotalAmount', number_format($facture->getTotalTTC(), 2, '.', '')));
        $tradeSettlement->appendChild($xml->createElement('ram:TaxTotalAmount', number_format($facture->getTotalTVA(), 2, '.', '')));
        $transaction->appendChild($tradeSettlement);

        $facturx->appendChild($transaction);

        $xmlDir = $this->projectDir . '/public/factures/xml';
        if (!is_dir($xmlDir)) mkdir($xmlDir, 0777, true);

        $xmlFileName = $xmlDir . '/facture_' . $facture->getNumeroFacture() . '_fx.xml';
        $xml->save($xmlFileName);

        return $xmlFileName;
    }

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
