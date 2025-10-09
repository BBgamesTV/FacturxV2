<?php

namespace App\Service;

use App\Entity\Facture;
use App\Entity\FactureLigne;
use Dompdf\Options;
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
        $guideline->appendChild($dom->createElement('ram:ID', 'urn:cen.eu:en16931:2017'));
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

        $tradeTransaction = $dom->createElement('rsm:SupplyChainTradeTransaction');
        $root->appendChild($tradeTransaction);

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
            $tax->appendChild($dom->createElement('ram:CalculatedAmount', $ligne->getMontantHt() * $ligne->getTauxTva()/100));
            $tax->appendChild($dom->createElement('ram:TypeCode', 'VAT'));
            $tax->appendChild($dom->createElement('ram:CategoryCode', 'S'));
            $tax->appendChild($dom->createElement('ram:RateApplicablePercent', number_format($ligne->getTauxTva(), 2, '.', '')));
            $settlementLine->appendChild($tax);

            $sum = $dom->createElement('ram:SpecifiedTradeSettlementLineMonetarySummation');
            $sum->appendChild($dom->createElement('ram:LineTotalAmount', number_format($ligne->getMontantHt(), 2, '.', '')));
            $settlementLine->appendChild($sum);

            $line->appendChild($settlementLine);

            $tradeTransaction->appendChild($line);
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

        $tradeTransaction->appendChild($agreement);

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
        $tradeTransaction->appendChild($delivery);


        // === (4) Enfin settlement ===
        $settlement = $dom->createElement('ram:ApplicableHeaderTradeSettlement');
        $devise = $facture->getDevise() ?: 'EUR';
        $settlement->appendChild($dom->createElement('ram:InvoiceCurrencyCode', $devise));

        // === Moyens de paiement (virement par défaut ou ceux de la facture) ===
        if ($facture->getPaymentMeans() && count($facture->getPaymentMeans()) > 0) {
            foreach ($facture->getPaymentMeans() as $paymentMean) {
                $pm = $dom->createElement('ram:SpecifiedTradeSettlementPaymentMeans');
                $pm->appendChild($dom->createElement('ram:TypeCode', $paymentMean->getCode() ?: '42'));
                $pm->appendChild($dom->createElement('ram:Information', $paymentMean->getInformation() ?: 'Paiement par virement bancaire'));

                $account = $dom->createElement('ram:PayeePartyCreditorFinancialAccount');
                $iban = $dom->createElement('ram:IBANID', $paymentMean->getInformation() ?: 'FR7630006000011234567890189');
                $account->appendChild($iban);
                $account->appendChild($dom->createElement('ram:AccountName', $facture->getFournisseur()->getNom()));
                $pm->appendChild($account);

                $bank = $dom->createElement('ram:PayeeSpecifiedCreditorFinancialInstitution');
                $bic = $dom->createElement('ram:BICID', 'AGRIFRPPXXX');
                $bank->appendChild($bic);
                // $bank->appendChild($dom->createElement('ram:Name', 'Crédit Agricole'));
                // $pm->appendChild($bank);

                $settlement->appendChild($pm);
            }
        } else {
            // Paiement par défaut : virement bancaire Oroya
            $pm = $dom->createElement('ram:SpecifiedTradeSettlementPaymentMeans');
            $pm->appendChild($dom->createElement('ram:TypeCode', '42'));
            $pm->appendChild($dom->createElement('ram:Information', 'Paiement par virement bancaire'));

            $account = $dom->createElement('ram:PayeePartyCreditorFinancialAccount');
            $iban = $dom->createElement('ram:IBANID', 'FR7630006000011234567890189');
            $account->appendChild($iban);
            $account->appendChild($dom->createElement('ram:AccountName', 'Oroya'));
            $pm->appendChild($account);

            $bank = $dom->createElement('ram:PayeeSpecifiedCreditorFinancialInstitution');
            $bic = $dom->createElement('ram:BICID', 'AGRIFRPPXXX');
            $bank->appendChild($bic);
            // $bank->appendChild($dom->createElement('ram:Name', 'Crédit Agricole'));
            // $pm->appendChild($bank);

            $settlement->appendChild($pm);
        }



        // === Taxes document (groupées par taux) ===   
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
            $calcAmount = $dom->createElement('ram:CalculatedAmount', number_format($calc, 2, '.', ''));
            $calcAmount->setAttribute('currencyID', $devise);
            $taxNode->appendChild($calcAmount);
            $taxNode->appendChild($dom->createElement('ram:TypeCode', 'VAT'));
            $taxNode->appendChild($dom->createElement('ram:BasisAmount', number_format($base, 2, '.', '')));
            $taxNode->appendChild($dom->createElement('ram:CategoryCode', 'S'));
            $taxNode->appendChild($dom->createElement('ram:RateApplicablePercent', number_format($rate, 2, '.', '')));
            $settlement->appendChild($taxNode);
        }

        // === Conditions de paiement ===
        $terms = $dom->createElement('ram:SpecifiedTradePaymentTerms');
        $dueDate = $facture->getDateEcheance() ?? "30 jours";
        $dueDateNode = $dom->createElement('ram:DueDateDateTime');
        $dateString = $dom->createElement('udt:DateTimeString', $dueDate->format('Ymd'));
        $dateString->setAttribute('format', '102');
        $dueDateNode->appendChild($dateString);
        $terms->appendChild($dueDateNode);
        // $terms->appendChild($dom->createElement('ram:Description', 'Paiement à 30 jours fin de mois'));
        $settlement->appendChild($terms);

        // === Totaux ===
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

        $taxBasis = round($totalHT - $totalAllow + $totalCharge, 2);
        $taxTotal = round($totalTax, 2);
        $ttc = round($taxBasis + $taxTotal, 2);

        $monetary = $dom->createElement('ram:SpecifiedTradeSettlementHeaderMonetarySummation');
        $elements = [
            'LineTotalAmount' => $totalHT,
            'ChargeTotalAmount' => $totalCharge,
            'AllowanceTotalAmount' => $totalAllow,
            'TaxBasisTotalAmount' => $taxBasis,
            'TaxTotalAmount' => $taxTotal,
            'GrandTotalAmount' => $ttc,
            'DuePayableAmount' => $ttc,
        ];
        foreach ($elements as $tag => $value) {
            $node = $dom->createElement('ram:' . $tag, number_format($value, 2, '.', ''));
            $node->setAttribute('currencyID', $devise);
            $monetary->appendChild($node);
        }
        $settlement->appendChild($monetary);

        // rattacher settlement à la transaction
        $tradeTransaction->appendChild($settlement);



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
        $html = $this->twig->render('facture/pdfA3.html.twig', ['facture' => $facture]);
        $tmpPdf = tempnam(sys_get_temp_dir(), 'fx_') . '.pdf';

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isFontSubsettingEnabled', true);
        $options->set('fontDir', __DIR__ . '/public/fonts'); // -> adapte le chemin selon ton projet
        $options->set('fontCache', __DIR__ . '/public/fonts');
        
        $dompdf = new Dompdf($options);
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
