<?php

namespace App\Service;

use App\Entity\Facture;
use Atgp\FacturX\Writer;
use Atgp\FacturX\Utils\ProfileHandler;
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
     * Génère un XML Factur-X (Basic) conforme EN16931 et le sauvegarde dans public/factures/xml/
     * Retourne le chemin du fichier XML sauvegardé.
     */
    public function buildXml(Facture $facture): string
    {
        $xml = new \DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;

        // --- root & namespaces
        $root = $xml->createElement('rsm:CrossIndustryInvoice');
        $root->setAttribute('xmlns:rsm', 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100');
        $root->setAttribute('xmlns:ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');
        $root->setAttribute('xmlns:udt', 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100');
        $root->setAttribute('xmlns:qdt', 'urn:un:unece:uncefact:data:standard:QualifiedDataType:100');
        $xml->appendChild($root);

        // --- ExchangedDocumentContext (indique EN16931)
        $ctx = $xml->createElement('rsm:ExchangedDocumentContext');
        $guideline = $xml->createElement('ram:GuidelineSpecifiedDocumentContextParameter');
        $guideline->appendChild($xml->createElement('ram:ID', 'urn:cen.eu:en16931:2017'));
        $ctx->appendChild($guideline);
        $root->appendChild($ctx);

        // --- ExchangedDocument (ID, TypeCode, IssueDateTime)
        $doc = $xml->createElement('rsm:ExchangedDocument');
        $doc->appendChild($xml->createElement('ram:ID', $facture->getNumeroFacture()));

        // TypeCode (380 = invoice)
        $doc->appendChild($xml->createElement('ram:TypeCode', '380'));

        // IssueDateTime (udt:DateTimeString format 102 = YYYYMMDD)
        $issue = $xml->createElement('ram:IssueDateTime');
        $dateStr = $facture->getDateFacture() instanceof \DateTimeInterface
            ? $facture->getDateFacture()->format('Ymd')
            : (new \DateTime())->format('Ymd');
        $dt = $xml->createElement('udt:DateTimeString', $dateStr);
        $dt->setAttribute('format', '102');
        $issue->appendChild($dt);
        $doc->appendChild($issue);

        $root->appendChild($doc);

        // --- SupplyChainTradeTransaction
        $transaction = $xml->createElement('rsm:SupplyChainTradeTransaction');

        // --- (A) IncludedSupplyChainTradeLineItem* (les lignes)
        // Important : dans chaque IncludedSupplyChainTradeLineItem l'ordre des noeuds est strict :
        // AssociatedDocumentLineDocument, SpecifiedTradeProduct, SpecifiedLineTradeAgreement,
        // SpecifiedLineTradeDelivery, SpecifiedLineTradeSettlement
        foreach ($facture->getLignes() as $index => $ligne) {
            $lineItem = $xml->createElement('ram:IncludedSupplyChainTradeLineItem');

            // AssociatedDocumentLineDocument -> LineID
            $assoc = $xml->createElement('ram:AssociatedDocumentLineDocument');
            $assoc->appendChild($xml->createElement('ram:LineID', (string)($index + 1)));
            $lineItem->appendChild($assoc);

            // SpecifiedTradeProduct (article / nom) — doit venir avant SpecifiedLineTradeAgreement
            $product = $xml->createElement('ram:SpecifiedTradeProduct');
            $product->appendChild($xml->createElement('ram:Name', $ligne->getDesignation() ?? ''));
            $lineItem->appendChild($product);

            // SpecifiedLineTradeAgreement -> quantité + unité
            $agreement = $xml->createElement('ram:SpecifiedLineTradeAgreement');
            $billedQty = $xml->createElement('ram:BilledQuantity', number_format((float)$ligne->getQuantite(), 2, '.', ''));
            // unitCode : si absent, on met 'C62' (piece), sinon le code fourni
            if ($ligne->getUnite()) {
                $billedQty->setAttribute('unitCode', $ligne->getUnite());
            } else {
                $billedQty->setAttribute('unitCode', 'C62');
            }
            $agreement->appendChild($billedQty);
            $lineItem->appendChild($agreement);

            // SpecifiedLineTradeDelivery (vide si rien)
            $delivery = $xml->createElement('ram:SpecifiedLineTradeDelivery');
            $lineItem->appendChild($delivery);

            // SpecifiedLineTradeSettlement -> LineTotalAmount + ApplicableTradeTax (pas CalculatedAmount si profil Basic/EN16931 exige autre)
            $settlement = $xml->createElement('ram:SpecifiedLineTradeSettlement');

            // LineTotalAmount doit être le montant HT de la ligne (BT-131)
            $settlement->appendChild($xml->createElement('ram:LineTotalAmount', number_format((float)$ligne->getMontantHT(), 2, '.', '')));

            // ApplicableTradeTax (la TVA de la ligne) : pour EN16931 il faut CategoryCode et RateApplicablePercent
            $tax = $xml->createElement('ram:ApplicableTradeTax');
            // ATTENTION : certains validateurs n'attendent pas CalculatedAmount sur la ligne — on met RateApplicablePercent et CategoryCode
            // mais pour compatibilité on peut ajouter CalculatedAmount si nécessaire (ici on l'ajoute)
            $tax->appendChild($xml->createElement('ram:CalculatedAmount', number_format((float)$ligne->getMontantTVA(), 2, '.', '')));
            $tax->appendChild($xml->createElement('ram:TypeCode', 'VAT'));
            // CategoryCode: S = standard (changer si nécessaire)
            $tax->appendChild($xml->createElement('ram:CategoryCode', 'S'));
            $tax->appendChild($xml->createElement('ram:RateApplicablePercent', number_format((float)$ligne->getTauxTVA(), 2, '.', '')));
            $settlement->appendChild($tax);

            // SpecifiedTradeProduct already present — parfois product details are also under settlement as trade product
            // we leave it as is (product above)

            $lineItem->appendChild($settlement);
            $transaction->appendChild($lineItem);
        }

        // --- (B) ApplicableHeaderTradeAgreement (Seller & Buyer)
        $tradeAgreement = $xml->createElement('ram:ApplicableHeaderTradeAgreement');

        // SellerTradeParty
        $seller = $xml->createElement('ram:SellerTradeParty');
        // SpecifiedTaxRegistration (ex: SIREN)
        $taxRegSeller = $xml->createElement('ram:SpecifiedTaxRegistration');
        $taxRegSeller->appendChild($xml->createElement('ram:ID', $facture->getFournisseur()?->getSiren() ?? ''));
        $seller->appendChild($taxRegSeller);
        // Name (BT-31 seller name) — nécessaire pour Writer::extractInvoiceInformations
        $seller->appendChild($xml->createElement('ram:Name', $facture->getFournisseur()?->getNom() ?? ''));
        // PostalTradeAddress
        $postalSeller = $xml->createElement('ram:PostalTradeAddress');
        $postalSeller->appendChild($xml->createElement('ram:LineOne', $facture->getFournisseur()?->getAdresse() ?? ''));
        $postalSeller->appendChild($xml->createElement('ram:CityName', $facture->getFournisseur()?->getVille() ?? ''));
        $postalSeller->appendChild($xml->createElement('ram:PostcodeCode', $facture->getFournisseur()?->getCodePostal() ?? ''));
        $postalSeller->appendChild($xml->createElement('ram:CountryID', $facture->getFournisseur()?->getCodePays() ?? 'FR'));
        $seller->appendChild($postalSeller);

        $tradeAgreement->appendChild($seller);

        // BuyerTradeParty
        $buyer = $xml->createElement('ram:BuyerTradeParty');
        $taxRegBuyer = $xml->createElement('ram:SpecifiedTaxRegistration');
        $taxRegBuyer->appendChild($xml->createElement('ram:ID', $facture->getAcheteur()?->getSiren() ?? ''));
        $buyer->appendChild($taxRegBuyer);
        // Name (buyer)
        $buyer->appendChild($xml->createElement('ram:Name', $facture->getAcheteur()?->getNom() ?? ''));
        // PostalTradeAddress buyer
        $postalBuyer = $xml->createElement('ram:PostalTradeAddress');
        $postalBuyer->appendChild($xml->createElement('ram:LineOne', $facture->getAcheteur()?->getAdresse() ?? ''));
        $postalBuyer->appendChild($xml->createElement('ram:CityName', $facture->getAcheteur()?->getVille() ?? ''));
        $postalBuyer->appendChild($xml->createElement('ram:PostcodeCode', $facture->getAcheteur()?->getCodePostal() ?? ''));
        $postalBuyer->appendChild($xml->createElement('ram:CountryID', $facture->getAcheteur()?->getCodePays() ?? 'FR'));
        $buyer->appendChild($postalBuyer);

        $tradeAgreement->appendChild($buyer);

        $transaction->appendChild($tradeAgreement);

        // --- (C) ApplicableHeaderTradeDelivery (obligatoire dans l'ordre, peut être vide)
        $tradeDelivery = $xml->createElement('ram:ApplicableHeaderTradeDelivery');
        // on peut ajouter dates ici si tu les veux : DeliveryNoteReferencedDocument etc.
        $transaction->appendChild($tradeDelivery);

        // --- (D) ApplicableHeaderTradeSettlement (global totals & tax breakdown)
        $tradeSettlement = $xml->createElement('ram:ApplicableHeaderTradeSettlement');
        // Invoice currency
        $tradeSettlement->appendChild($xml->createElement('ram:InvoiceCurrencyCode', $facture->getDevise() ?: 'EUR'));
        // GrandTotalAmount = total TTC
        $tradeSettlement->appendChild($xml->createElement('ram:GrandTotalAmount', number_format((float)$facture->getTotalTTC(), 2, '.', '')));
        // TaxTotalAmount
        $tradeSettlement->appendChild($xml->createElement('ram:TaxTotalAmount', number_format((float)$facture->getTotalTVA(), 2, '.', '')));

        // Répartition TVA (BG-23) : au moins un ApplicableTradeTax récapitulatif par taux
        // Ici nous construisons un simple groupe unique reprenant la TVA totale
        $appTax = $xml->createElement('ram:ApplicableTradeTax');
        $appTax->appendChild($xml->createElement('ram:CalculatedAmount', number_format((float)$facture->getTotalTVA(), 2, '.', '')));
        $appTax->appendChild($xml->createElement('ram:TypeCode', 'VAT'));
        $appTax->appendChild($xml->createElement('ram:CategoryCode', 'S'));
        // On peut ajouter un rate si disponible (choix : calculer rate moyen ou laisser vide)
        $tradeSettlement->appendChild($appTax);

        $transaction->appendChild($tradeSettlement);

        // Append transaction to root
        $root->appendChild($transaction);

        // --- Save XML to public/factures/xml
        $xmlDir = $this->projectDir . '/public/factures/xml';
        if (!is_dir($xmlDir)) {
            mkdir($xmlDir, 0777, true);
        }

        $fileName = sprintf('%s/facture_%s_fx.xml', $xmlDir, preg_replace('/[^a-zA-Z0-9_\-]/', '_', $facture->getNumeroFacture()));
        $xml->save($fileName);

        return $fileName;
    }

    /**
     * Génère PDF (HTML via Twig) puis imbrique le XML Factur-X dans le PDF en sortie.
     * $profile peut être modifié à PROFILE_FACTURX_EN16931 ou PROFILE_FACTURX_BASIC selon besoin.
     */
    public function buildPdfFacturX(Facture $facture, string $outputPdfPath, string $profile = ProfileHandler::PROFILE_FACTURX_BASIC): void
    {
        // 1) build xml and get contents
        $xmlPath = $this->buildXml($facture);
        $xmlContent = file_get_contents($xmlPath);

        // 2) generate PDF from Twig HTML
        $html = $this->generateHtmlForPdf($facture);
        $tmpPdf = tempnam(sys_get_temp_dir(), 'fx_') . '.pdf';

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        file_put_contents($tmpPdf, $dompdf->output());

        // 3) use Writer to embed xml into PDF
        $writer = new Writer();
        // validateXSD = true pour vérifier la conformité (va lancer exception si invalide)
        $pdfString = $writer->generate(
            file_get_contents($tmpPdf),
            $xmlContent,
            $profile,
            true,   // validate XSD (true recommandé)
            [],     // additional attachments
            true    // add logo
        );

        // 4) save final pdf
        file_put_contents($outputPdfPath, $pdfString);

        // cleanup
        @unlink($tmpPdf);
    }

    private function generateHtmlForPdf(Facture $facture): string
    {
        return $this->twig->render('facture/pdf_template.html.twig', [
            'facture' => $facture
        ]);
    }
}
