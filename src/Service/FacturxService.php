<?php

namespace App\Service;

use App\Entity\Facture;
use Atgp\FacturX\Writer;
use Atgp\FacturX\Utils\ProfileHandler;
use Dompdf\Dompdf;
use Twig\Environment;

class FacturxService
{
    private Environment $twig;
    private string $projectDir;

    public function __construct(Environment $twig, string $projectDir)
    {
        $this->twig = $twig;
        $this->projectDir = rtrim($projectDir, '/');
    }

    /**
     * Génère un XML Factur-X BASIC valide (EN16931)
     */
    public function buildXml(Facture $facture): string
    {
        $xml = new \DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;

        // Root avec les bons namespaces et guillemets standards
        $root = $xml->createElement('rsm:CrossIndustryInvoice');
        $root->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $root->setAttribute('xmlns:qdt', 'urn:un:unece:uncefact:data:standard:QualifiedDataType:100');
        $root->setAttribute('xmlns:udt', 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100');
        $root->setAttribute('xmlns:rsm', 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100');
        $root->setAttribute('xmlns:ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');
        $xml->appendChild($root);

        // ---- CONTEXT ----
        $context = $xml->createElement('rsm:ExchangedDocumentContext');
        $process = $xml->createElement('ram:BusinessProcessSpecifiedDocumentContextParameter');
        $process->appendChild($xml->createElement('ram:ID', 'A1'));
        $context->appendChild($process);

        $guideline = $xml->createElement('ram:GuidelineSpecifiedDocumentContextParameter');
        $guideline->appendChild($xml->createElement('ram:ID', 'urn:factur-x.eu:1p0:basic'));
        $context->appendChild($guideline);

        $root->appendChild($context);

        // ---- DOCUMENT HEADER ----
        $doc = $xml->createElement('rsm:ExchangedDocument');
        $doc->appendChild($xml->createElement('ram:ID', $facture->getNumeroFacture()));
        $doc->appendChild($xml->createElement('ram:TypeCode', '380')); // Invoice
        $issue = $xml->createElement('ram:IssueDateTime');
        $dt = $xml->createElement('udt:DateTimeString', $facture->getDateFacture()->format('Ymd'));
        $dt->setAttribute('format', '102');
        $issue->appendChild($dt);
        $doc->appendChild($issue);
        $root->appendChild($doc);

        // ---- TRANSACTION ----

        $transaction = $xml->createElement('rsm:SupplyChainTradeTransaction');

        $lines = $facture->getLignes()->toArray();
        // 1. Ajouter tous les line items d'abord
        foreach ($lines as $line) {
            $lineItem = $xml->createElement('ram:IncludedSupplyChainTradeLineItem');
            // ...ajoute les sous-éléments ligne...
            $transaction->appendChild($lineItem);
        }

        // 2. Puis l’entête Agreement, Delivery, Settlement (1 exemplaire chacun, max)
        $agreement = $xml->createElement('ram:ApplicableHeaderTradeAgreement');
        // ...sous-éléments...
        $transaction->appendChild($agreement);

        $delivery = $xml->createElement('ram:ApplicableHeaderTradeDelivery');
        // ...sous-éléments...
        $transaction->appendChild($delivery);

        $settlement = $xml->createElement('ram:ApplicableHeaderTradeSettlement');
        // ...sous-éléments...
        $transaction->appendChild($settlement);

// Enfin, ajouter $transaction au document principal


        // --- HeaderTradeAgreement ---
        $agreement = $xml->createElement('ram:ApplicableHeaderTradeAgreement');
        $agreement->appendChild($xml->createElement('ram:BuyerReference', 'BUYERREF'));

        // Seller
        $seller = $xml->createElement('ram:SellerTradeParty');
        $seller->appendChild($xml->createElement('ram:Name', $facture->getFournisseur()?->getNom() ?? ''));
        $legalSeller = $xml->createElement('ram:SpecifiedLegalOrganization');
        $legalSeller->appendChild($xml->createElement('ram:ID', $facture->getFournisseur()?->getSiren() ?? ''));
        $legalSeller->setAttribute('schemeID', '0002');
        $seller->appendChild($legalSeller);
        $addrSeller = $xml->createElement('ram:PostalTradeAddress');
        $addrSeller->appendChild($xml->createElement('ram:CountryID', 'FR'));
        $seller->appendChild($addrSeller);
        $vatSeller = $xml->createElement('ram:SpecifiedTaxRegistration');
        $vatSeller->appendChild($xml->createElement('ram:ID', $facture->getFournisseur()?->getTva() ?? 'FRXX000000000'));
        $vatSeller->setAttribute('schemeID', 'VA');
        $seller->appendChild($vatSeller);
        $agreement->appendChild($seller);

        // Buyer
        $buyer = $xml->createElement('ram:BuyerTradeParty');
        $buyer->appendChild($xml->createElement('ram:Name', $facture->getAcheteur()?->getNom() ?? ''));
        $legalBuyer = $xml->createElement('ram:SpecifiedLegalOrganization');
        $legalBuyer->appendChild($xml->createElement('ram:ID', $facture->getAcheteur()?->getSiren() ?? ''));
        $legalBuyer->setAttribute('schemeID', '0002');
        $buyer->appendChild($legalBuyer);
        $agreement->appendChild($buyer);

        // Order reference
        $order = $xml->createElement('ram:BuyerOrderReferencedDocument');
        $order->appendChild($xml->createElement('ram:IssuerAssignedID', $facture->getAcheteur()->getNumCommande() ?? 'NUMCOMMANDE'));
        $agreement->appendChild($order);

        $transaction->appendChild($agreement);

        // --- HeaderTradeDelivery (vide mais obligatoire) ---
        $transaction->appendChild($xml->createElement('ram:ApplicableHeaderTradeDelivery'));

        // --- HeaderTradeSettlement ---
        $settlement = $xml->createElement('ram:ApplicableHeaderTradeSettlement');
        $settlement->appendChild($xml->createElement('ram:InvoiceCurrencyCode', 'EUR'));

        $monetary = $xml->createElement('ram:SpecifiedTradeSettlementHeaderMonetarySummation');
        $monetary->appendChild($xml->createElement('ram:TaxBasisTotalAmount', number_format($facture->getTotalHT(), 2, '.', '')));
        $monetary->appendChild($xml->createElement('ram:TaxTotalAmount', number_format($facture->getTotalTVA(), 2, '.', '')));
        $monetary->setAttribute('currencyID', 'EUR');
        $monetary->appendChild($xml->createElement('ram:GrandTotalAmount', number_format($facture->getTotalTTC(), 2, '.', '')));
        $monetary->appendChild($xml->createElement('ram:DuePayableAmount', number_format($facture->getTotalTTC(), 2, '.', '')));
        $settlement->appendChild($monetary);

        $transaction->appendChild($settlement);
        $root->appendChild($transaction);

        // ---- Sauvegarde ----
        $xmlDir = $this->projectDir . '/public/factures/xml';
        if (!is_dir($xmlDir)) {
            mkdir($xmlDir, 0777, true);
        }

        $file = sprintf('%s/facture_%s_fx.xml', $xmlDir, $facture->getNumeroFacture());
        $xml->save($file);

        return $file;
    }

    /**
     * Génère le PDF avec le XML Factur-X embarqué.
     */
    public function buildPdfFacturX(Facture $facture, string $outputPdfPath): void
    {
        $xmlFile = $this->buildXml($facture);
        $xmlContent = file_get_contents($xmlFile);

        // PDF
        $html = $this->twig->render('facture/pdf_template.html.twig', ['facture' => $facture]);
        $tmpPdf = tempnam(sys_get_temp_dir(), 'fx_') . '.pdf';

        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        file_put_contents($tmpPdf, $dompdf->output());

        // Fusion XML + PDF
        $writer = new Writer();
        $pdfContent = $writer->generate(
            file_get_contents($tmpPdf),
            $xmlContent,
            ProfileHandler::PROFILE_FACTURX_BASIC,
            false
        );

        file_put_contents($outputPdfPath, $pdfContent);
        @unlink($tmpPdf);
    }
}
