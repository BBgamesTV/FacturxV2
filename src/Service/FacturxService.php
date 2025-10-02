<?php
// src/Service/FacturxService.php
namespace App\Service;

use App\Entity\Facture;
use Atgp\FacturX\XsdValidator;
use Atgp\FacturX\Writer;
use DOMDocument;

class FacturxService
{
    /**
     * Crée un XML Factur-X valide à partir d'une facture et l'enregistre si $filePath fourni
     *
     * @param Facture $facture
     * @param string|null $filePath Chemin pour enregistrer le XML (optionnel)
     * @return string Contenu XML
     */
    public function buildXml(Facture $facture, ?string $filePath = null): string
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        // --- Racine correcte avec namespace principal ---
        $root = $doc->createElementNS(
            'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
            'CrossIndustryInvoice'
        );

        // Préfixes internes
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:rsm', 'urn:ferd:CrossIndustryDocument:invoice:1p0');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:12');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:udt', 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:15');
        $doc->appendChild($root);

        // --- ExchangedDocumentContext / GuidelineSpecifiedDocumentContextParameter ---
        $context = $doc->createElement('rsm:ExchangedDocumentContext');
        $guideline = $doc->createElement('ram:GuidelineSpecifiedDocumentContextParameter');
        $allowedProfiles = ['minimum', 'basicwl', 'basic', 'en16931', 'extended'];
        $profile = strtolower($facture->getProfilFacturX() ?: 'minimum');

        if (!in_array($profile, $allowedProfiles, true)) {
            $profile = 'minimum';
        }

        $id = $doc->createElement('ram:ID', "urn:factur-x:pdfa:CrossIndustryDocument:invoice:1p0:$profile");
        $guideline->appendChild($id);
        $context->appendChild($guideline);
        $root->appendChild($context);

        // --- ExchangedDocument ---
        $exDoc = $doc->createElement('rsm:ExchangedDocument');
        $idEl = $doc->createElement('ram:ID', $facture->getNumeroFacture());
        $dateEl = $doc->createElement('ram:IssueDateTime');
        $dateStr = $doc->createElement('udt:DateTimeString', $facture->getDateFacture()->format('Ymd'));
        $dateStr->setAttribute('format', '102'); // format EN16931
        $dateEl->appendChild($dateStr);
        $exDoc->appendChild($idEl);
        $exDoc->appendChild($dateEl);
        $root->appendChild($exDoc);

        // --- SupplyChainTradeTransaction ---
        $sctt = $doc->createElement('rsm:SupplyChainTradeTransaction');
        $root->appendChild($sctt);

        // HeaderTradeAgreement : Fournisseur & Acheteur
        $agreement = $doc->createElement('ram:ApplicableHeaderTradeAgreement');
        $sctt->appendChild($agreement);

        // Seller
        $seller = $doc->createElement('ram:SellerTradeParty');
        $seller->appendChild($doc->createElement('ram:Name', $facture->getNomFournisseur()));
        $seller->appendChild($doc->createElement('ram:ID', $facture->getSirenFournisseur()));
        $agreement->appendChild($seller);

        // Buyer
        $buyer = $doc->createElement('ram:BuyerTradeParty');
        $buyer->appendChild($doc->createElement('ram:Name', $facture->getNomAcheteur()));
        $buyer->appendChild($doc->createElement('ram:ID', $facture->getSirenAcheteur()));
        $agreement->appendChild($buyer);

        // HeaderTradeSettlement / Totaux
        $settlement = $doc->createElement('ram:ApplicableHeaderTradeSettlement');
        $sctt->appendChild($settlement);

        $sum = $doc->createElement('ram:SpecifiedTradeSettlementMonetarySummation');
        $sum->appendChild($doc->createElement('ram:LineTotalAmount', number_format($facture->getTotalHT(), 2, '.', '')));
        $sum->appendChild($doc->createElement('ram:TaxTotalAmount', number_format($facture->getTotalTVA(), 2, '.', '')));
        $sum->appendChild($doc->createElement('ram:GrandTotalAmount', number_format($facture->getTotalTTC(), 2, '.', '')));
        $sum->appendChild($doc->createElement('ram:DuePayableAmount', number_format($facture->getNetAPayer(), 2, '.', '')));
        $settlement->appendChild($sum);

        // Dates optionnelles
        if ($facture->getDateEcheance()) {
            $invoiceDue = $doc->createElement('ram:InvoiceDueDateTime');
            $dateString = $doc->createElement('udt:DateTimeString', $facture->getDateEcheance()->format('Ymd'));
            $dateString->setAttribute('format', '102');
            $invoiceDue->appendChild($dateString);
            $settlement->appendChild($invoiceDue);
        }

        if ($facture->getDateLivraison()) {
            $delivery = $doc->createElement('ram:ActualDeliverySupplyChainEvent');
            $occurrence = $doc->createElement('ram:OccurrenceDateTime');
            $dateString = $doc->createElement('udt:DateTimeString', $facture->getDateLivraison()->format('Ymd'));
            $dateString->setAttribute('format', '102');
            $occurrence->appendChild($dateString);
            $delivery->appendChild($occurrence);
            $sctt->appendChild($delivery);
        }

        // Référence paiement
        if ($facture->getReferencePaiement()) {
            $settlement->appendChild($doc->createElement('ram:PaymentReference', $facture->getReferencePaiement()));
        }

        // --- Enregistrement optionnel ---
        if ($filePath) {
            $doc->save($filePath);
        }

        return $doc->saveXML();
    }

    // /**
    //  * Valide le XML Factur-X
    //  */
    // public function validateXml(string $xml): void
    // {
    //     $validator = new XsdValidator();
    //     if (!$validator->validate($xml)) {
    //         $errors = $validator->getErrors();
    //         throw new \RuntimeException("XML Factur-X invalide : " . implode("; ", $errors));
    //     }
    // }

    /**
     * Embed le XML dans un PDF/A-3
     */
    public function embedXmlInPdf(string $pdfPath, string $xmlContent, string $destPdf): void
    {
        $writer = new Writer();
        $writer->generate($pdfPath, $xmlContent, $destPdf);
    }
}
