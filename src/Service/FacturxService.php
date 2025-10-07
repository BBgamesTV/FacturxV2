<?php

namespace App\Service;

use App\Entity\Facture;
use App\Entity\FactureLigne;
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
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        // Root + namespaces
        $root = $dom->createElement('rsm:CrossIndustryInvoice');
        $root->setAttribute('xmlns:rsm', 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100');
        $root->setAttribute('xmlns:qdt', 'urn:un:unece:uncefact:data:standard:QualifiedDataType:100');
        $root->setAttribute('xmlns:ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');
        $root->setAttribute('xmlns:udt', 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100');
        $dom->appendChild($root);

        // Context
        $context = $dom->createElement('rsm:ExchangedDocumentContext');
        $guideline = $dom->createElement('ram:GuidelineSpecifiedDocumentContextParameter');
        $guideline->appendChild($dom->createElement('ram:ID', 'urn:cen.eu:en16931:2017#conformant#urn:factur-x.eu:1p0:extended'));
        $context->appendChild($guideline);
        $root->appendChild($context);

        // Document header
        $document = $dom->createElement('rsm:ExchangedDocument');
        $document->appendChild($dom->createElement('ram:ID', $facture->getNumeroFacture()));
        $document->appendChild($dom->createElement('ram:TypeCode', '380'));

        $issueDate = $dom->createElement('ram:IssueDateTime');
        $dateStr = $dom->createElement('udt:DateTimeString', $facture->getDateFacture()->format('Ymd'));
        $dateStr->setAttribute('format', '102');
        $issueDate->appendChild($dateStr);
        $document->appendChild($issueDate);

        if ($facture->getCommentaire()) {
            $note = $dom->createElement('ram:IncludedNote');
            $note->appendChild($dom->createElement('ram:Content', $facture->getCommentaire()));
            $document->appendChild($note);
        }
        $root->appendChild($document);

        // SupplyChainTradeTransaction
        $transaction = $dom->createElement('rsm:SupplyChainTradeTransaction');

        // === (1) Toutes les lignes d'abord ===
        foreach ($facture->getLignes() as $idx => $ligne) {
            $line = $dom->createElement('ram:IncludedSupplyChainTradeLineItem');
            $lineDoc = $dom->createElement('ram:AssociatedDocumentLineDocument');
            $lineDoc->appendChild($dom->createElement('ram:LineID', $idx + 1));
            $line->appendChild($lineDoc);

            $product = $dom->createElement('ram:SpecifiedTradeProduct');
            $product->appendChild($dom->createElement('ram:Name', $ligne->getDesignation()));
            $line->appendChild($product);

            $price = $dom->createElement('ram:SpecifiedLineTradeAgreement');
            $netPrice = $dom->createElement('ram:NetPriceProductTradePrice');
            $netPrice->appendChild($dom->createElement('ram:ChargeAmount', number_format($ligne->getPrixUnitaireHt(), 2, '.', '')));
            $price->appendChild($netPrice);
            $line->appendChild($price);

            $deliveryL = $dom->createElement('ram:SpecifiedLineTradeDelivery');
            $qty = $dom->createElement('ram:BilledQuantity', number_format($ligne->getQuantite(), 4, '.', ''));
            $qty->setAttribute('unitCode', 'H87');
            $deliveryL->appendChild($qty);
            $line->appendChild($deliveryL);

            $settlementLine = $dom->createElement('ram:SpecifiedLineTradeSettlement');
            $tax = $dom->createElement('ram:ApplicableTradeTax');
            $tax->appendChild($dom->createElement('ram:TypeCode', 'VAT'));
            $tax->appendChild($dom->createElement('ram:CategoryCode', 'S'));
            $tax->appendChild($dom->createElement('ram:RateApplicablePercent', number_format($ligne->getTauxTva(), 2, '.', '')));
            $settlementLine->appendChild($tax);

            $sum = $dom->createElement('ram:SpecifiedTradeSettlementLineMonetarySummation');
            $sum->appendChild($dom->createElement('ram:LineTotalAmount', number_format($ligne->getMontantHt(), 2, '.', '')));
            $settlementLine->appendChild($sum);

            $line->appendChild($settlementLine);

            $transaction->appendChild($line);
        }

        // === (2) Ensuite l'entête parties/accord ===
        $agreement = $dom->createElement('ram:ApplicableHeaderTradeAgreement');
        // Seller
        $seller = $dom->createElement('ram:SellerTradeParty');
        $fournisseur = $facture->getFournisseur();
        $seller->appendChild($dom->createElement('ram:Name', $fournisseur->getNom()));
        $sellerAddr = $dom->createElement('ram:PostalTradeAddress');
        if ($fournisseur->getCodePostal()) $sellerAddr->appendChild($dom->createElement('ram:PostcodeCode', $fournisseur->getCodePostal()));
        if ($fournisseur->getAdresse()) $sellerAddr->appendChild($dom->createElement('ram:LineOne', $fournisseur->getAdresse()));
        if ($fournisseur->getVille()) $sellerAddr->appendChild($dom->createElement('ram:CityName', $fournisseur->getVille()));
        $sellerAddr->appendChild($dom->createElement('ram:CountryID', $fournisseur->getCodePays() ?: 'FR'));
        $seller->appendChild($sellerAddr);

        if ($fournisseur->getNumeroTva()) {
            $taxReg = $dom->createElement('ram:SpecifiedTaxRegistration');
            $id = $dom->createElement('ram:ID', $fournisseur->getNumeroTva());
            $id->setAttribute('schemeID', 'VA');
            $taxReg->appendChild($id);
            $seller->appendChild($taxReg);
        }
        // if ($fournisseur->getSiren()) {
        //     $taxReg2 = $dom->createElement('ram:SpecifiedTaxRegistration');
        //     $id2 = $dom->createElement('ram:ID', $fournisseur->getSiren());
        //     $id2->setAttribute('schemeID', 'FC');
        //     $taxReg2->appendChild($id2);
        //     $seller->appendChild($taxReg2);
        // }
        if ($fournisseur->getSiret()) {
            $taxReg3 = $dom->createElement('ram:SpecifiedTaxRegistration');
            $id3 = $dom->createElement('ram:ID', $fournisseur->getSiret());
            $id3->setAttribute('schemeID', 'SIRET');
            $taxReg3->appendChild($id3);
            $seller->appendChild($taxReg3);
        }
        $agreement->appendChild($seller);

        // Buyer
        $acheteur = $facture->getAcheteur();
        $buyer = $dom->createElement('ram:BuyerTradeParty');
        $buyer->appendChild($dom->createElement('ram:Name', $acheteur->getNom()));
        $buyerAddr = $dom->createElement('ram:PostalTradeAddress');
        if ($acheteur->getCodePostal()) $buyerAddr->appendChild($dom->createElement('ram:PostcodeCode', $acheteur->getCodePostal()));
        if ($acheteur->getAdresse()) $buyerAddr->appendChild($dom->createElement('ram:LineOne', $acheteur->getAdresse()));
        if ($acheteur->getVille()) $buyerAddr->appendChild($dom->createElement('ram:CityName', $acheteur->getVille()));
        $buyerAddr->appendChild($dom->createElement('ram:CountryID', $acheteur->getCodePays() ?: 'FR'));
        $buyer->appendChild($buyerAddr);
        $agreement->appendChild($buyer);
        $transaction->appendChild($agreement);

        // === (3) Ensuite la livraison ===
        $delivery = $dom->createElement('ram:ApplicableHeaderTradeDelivery');
        if ($facture->getDateLivraison()) {
            $deliveryDate = $dom->createElement('ram:ActualDeliverySupplyChainEvent');
            $occ = $dom->createElement('ram:OccurrenceDateTime');
            $occDate = $dom->createElement('udt:DateTimeString', $facture->getDateLivraison()->format('Ymd'));
            $occDate->setAttribute('format', '102');
            $occ->appendChild($occDate);
            $deliveryDate->appendChild($occ);
            $delivery->appendChild($deliveryDate);
        }
        $transaction->appendChild($delivery);

        // === (4) Enfin settlement ===
        $settlement = $dom->createElement('ram:ApplicableHeaderTradeSettlement');
        $devise = $facture->getDevise();
        $settlement->appendChild($dom->createElement('ram:InvoiceCurrencyCode', $devise));

        if ($facture->getPaymentMeans()) {
            foreach ($facture->getPaymentMeans() as $paymentMean) {
                $pm = $dom->createElement('ram:SpecifiedTradeSettlementPaymentMeans');
                $pm->appendChild($dom->createElement('ram:TypeCode', $paymentMean->getCode() ?: '58'));
                if ($paymentMean->getInformation())
                    $account = $dom->createElement('ram:PayeePartyCreditorFinancialAccount');
                    $account->appendChild($dom->createElement('ram:IBANID', $paymentMean->getInformation()?: "FR7612345678901234567890123")); // remplace par la vraie valeur
                    $pm->appendChild($account);
                $settlement->appendChild($pm);
            }
        }

        $transaction->appendChild($settlement);

        // $terms = $dom->createElement('ram:SpecifiedTradePaymentTerms');
        // $terms->appendChild($dom->createElement('ram:Description', 'Net 30 jours'));
        // $dateEcheance = $facture->getDateEcheance();
        // if ($dateEcheance) {
        //     $due = $dom->createElement('ram:DueDateDateTime');
        //     $dstr = $dom->createElement('udt:DateTimeString', $dateEcheance->format('Ymd'));
        //     $dstr->setAttribute('format', '102');
        //     $due->appendChild($dstr);
        //     $terms->appendChild($due);  
        // }
        // $settlement->appendChild($terms);


        // Taxes document (groupées par taux)
        $taxGroups = [];
        foreach ($facture->getLignes() as $ligne) {
            $r = round($ligne->getTauxTva(), 2);
            if (!isset($taxGroups[$r])) $taxGroups[$r] = ['base' => 0.0];
            $taxGroups[$r]['base'] += $ligne->getMontantHt();
        }
        foreach ($facture->getAllowanceCharges() as $ac) {
            $rate = round($ac->getTaxRate(), 2);
            if (!isset($taxGroups[$rate])) $taxGroups[$rate] = ['base' => 0.0];
            $taxGroups[$rate]['base'] += $ac->getIsCharge() ? $ac->getAmount() : -$ac->getAmount();
        }
        $totalTax = 0.0;
        foreach ($taxGroups as $rate => $data) {
            $base = round($data['base'], 2);
            $calc = round($base * $rate / 100.0, 2);
            $totalTax += $calc;
            $taxNode = $dom->createElement('ram:ApplicableTradeTax');
            $taxNode->appendChild($dom->createElement('ram:CalculatedAmount', number_format($calc, 2, '.', '')));
            $taxNode->appendChild($dom->createElement('ram:TypeCode', 'VAT'));
            $taxNode->appendChild($dom->createElement('ram:BasisAmount', number_format($base, 2, '.', '')));
            $taxNode->appendChild($dom->createElement('ram:CategoryCode', 'S'));
            $taxNode->appendChild($dom->createElement('ram:RateApplicablePercent', number_format($rate, 2, '.', '')));
            $settlement->appendChild($taxNode);
        }

        foreach ($facture->getAllowanceCharges() as $ac) {
            $stac = $dom->createElement('ram:SpecifiedTradeAllowanceCharge');
            $chargeIndicator = $dom->createElement('ram:ChargeIndicator');
            $chargeIndicator->appendChild($dom->createElement('udt:Indicator', $ac->getIsCharge() ? 'true' : 'false'));
            $stac->appendChild($chargeIndicator);

            $stac->appendChild($dom->createElement('ram:ActualAmount', number_format($ac->getAmount(), 2, '.', '')));
            if ($ac->getReason())
                $stac->appendChild($dom->createElement('ram:Reason', $ac->getReason()));

            $cat = $dom->createElement('ram:CategoryTradeTax');
            $cat->appendChild($dom->createElement('ram:TypeCode', 'VAT'));
            $cat->appendChild($dom->createElement('ram:CategoryCode', 'S'));
            $cat->appendChild($dom->createElement('ram:RateApplicablePercent', number_format($ac->getTaxRate(), 2, '.', '')));
            $stac->appendChild($cat);

            $settlement->appendChild($stac);
        }

        // Calcul cohérent pour les totaux
        $totalHT = 0.0;
        $totalAllow = 0.0;
        $totalCharge = 0.0;
        foreach ($facture->getLignes() as $ligne) {
            $totalHT += $ligne->getMontantHt();
        }
        foreach ($facture->getAllowanceCharges() as $ac) {
            if ($ac->getIsCharge()) $totalCharge += $ac->getAmount();
            else $totalAllow += $ac->getAmount();
        }

        $monetary = $dom->createElement('ram:SpecifiedTradeSettlementHeaderMonetarySummation');
        $taxBasis = round($totalHT - $totalAllow + $totalCharge, 2);
        $taxTotal = round($totalTax, 2);
        $ttc = round($taxBasis + $taxTotal, 2);

        $root->appendChild($transaction);
        $monetary = $dom->createElement('ram:SpecifiedTradeSettlementHeaderMonetarySummation');
        $monetary->appendChild($dom->createElement('ram:LineTotalAmount', number_format($totalHT, 2, '.', '')));
        $monetary->appendChild($dom->createElement('ram:ChargeTotalAmount', number_format($totalCharge, 2, '.', '')));
        $monetary->appendChild($dom->createElement('ram:AllowanceTotalAmount', number_format($totalAllow, 2, '.', '')));
        $monetary->appendChild($dom->createElement('ram:TaxBasisTotalAmount', number_format($taxBasis, 2, '.', '')));
        $monetary->appendChild($dom->createElement('ram:TaxTotalAmount', number_format($taxTotal, 2, '.', '')));
        $grand = $dom->createElement('ram:GrandTotalAmount', number_format($ttc, 2, '.', ''));
        $monetary->appendChild($grand);
        $due = $dom->createElement('ram:DuePayableAmount', number_format($ttc, 2, '.', ''));
        $monetary->appendChild($due);
        $settlement->appendChild($monetary);
        

        // Écriture fichier XML
        $xmlDir = $this->projectDir . '/public/factures/xml';
        if (!is_dir($xmlDir)) {
            mkdir($xmlDir, 0777, true);
        }
        $invoiceNumber = $facture->getNumeroFacture();
        $fileName = sprintf('%s/facture_%s_fx.xml', $xmlDir, $invoiceNumber);
        $dom->save($fileName);

        return $fileName;
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
            ProfileHandler::PROFILE_FACTURX_EN16931,
            true,
            [],
            true
        );

        file_put_contents($outputPdfPath, $pdfContent);
        @unlink($tmpPdf);
    }
}
