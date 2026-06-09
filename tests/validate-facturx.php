<?php
/**
 * Factur-X BASIC validation: XSD + key EN 16931 business rules (BR-*).
 *
 * Usage:
 *   php tests/validate-facturx.php path/to/invoice.pdf
 *   php tests/validate-facturx.php path/to/invoice.xml
 */

require __DIR__ . '/../vendor/autoload.php';

use Atgp\FacturX\Reader;
use Atgp\FacturX\XsdValidator;
use Atgp\FacturX\Utils\ProfileHandler;

// ── 1. Load XML ──────────────────────────────────────────────────────────────

$file = $argv[1] ?? null;
if ( ! $file || ! is_file( $file ) ) {
    fwrite( STDERR, "Usage: php validate-facturx.php <invoice.pdf|invoice.xml>\n" );
    exit( 1 );
}

if ( str_ends_with( strtolower( $file ), '.pdf' ) ) {
    echo "Extraction XML depuis le PDF…\n";
    $reader = new Reader();
    $xml    = $reader->extractXML( file_get_contents( $file ), false );
} else {
    $xml = file_get_contents( $file );
}

if ( '' === trim( $xml ) ) {
    fwrite( STDERR, "Impossible de lire le XML.\n" );
    exit( 1 );
}

// ── 2. XSD validation ────────────────────────────────────────────────────────

echo "\n=== XSD (schéma structurel) ===\n";
$validator = new XsdValidator();
try {
    $validator->validateWithException( $xml, ProfileHandler::PROFILE_FACTURX_BASIC );
    echo "✅ XSD BASIC valide\n";
} catch ( \Throwable $e ) {
    echo "❌ XSD invalide : " . $e->getMessage() . "\n";
    exit( 1 );
}

// ── 3. Parse DOM for BR checks ───────────────────────────────────────────────

$doc = new DOMDocument();
$doc->loadXML( $xml );
$xpath = new DOMXPath( $doc );
$xpath->registerNamespace( 'rsm', 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100' );
$xpath->registerNamespace( 'ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100' );
$xpath->registerNamespace( 'udt', 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100' );

$errors   = [];
$warnings = [];

function xval( DOMXPath $xp, string $expr, DOMNode $ctx = null ): string {
    $nodes = $ctx ? $xp->query( $expr, $ctx ) : $xp->query( $expr );
    return $nodes && $nodes->length > 0 ? trim( $nodes->item(0)->nodeValue ) : '';
}

function xnodes( DOMXPath $xp, string $expr, DOMNode $ctx = null ): DOMNodeList {
    return $ctx ? $xp->query( $expr, $ctx ) : $xp->query( $expr );
}

// ── BR-1 Specification identifier ────────────────────────────────────────────
$profile_id = xval( $xpath, '//ram:GuidelineSpecifiedDocumentContextParameter/ram:ID' );
if ( '' === $profile_id ) {
    $errors[] = 'BR-1 : Specification identifier (GuidelineSpecifiedDocumentContextParameter/ID) manquant';
} else {
    echo "   Profile URN : $profile_id\n";
}

// ── BR-2 Invoice number ───────────────────────────────────────────────────────
$inv_id = xval( $xpath, '//rsm:ExchangedDocument/ram:ID' );
if ( '' === $inv_id ) {
    $errors[] = 'BR-2 : Numéro de facture (ExchangedDocument/ID) manquant';
}

// ── BR-3 Invoice issue date ───────────────────────────────────────────────────
$issue_date = xval( $xpath, '//rsm:ExchangedDocument/ram:IssueDateTime/udt:DateTimeString' );
if ( '' === $issue_date ) {
    $errors[] = 'BR-3 : Date d\'émission manquante';
}

// ── BR-4 Invoice type code (must be 380 commercial invoice or 381 credit note) ─
$type_code = xval( $xpath, '//rsm:ExchangedDocument/ram:TypeCode' );
if ( ! in_array( $type_code, [ '380', '381', '384', '389', '261', '262' ], true ) ) {
    $errors[] = "BR-4 : TypeCode '$type_code' invalide (attendu 380)";
}

// ── BR-5 Invoice currency code ────────────────────────────────────────────────
$currency = xval( $xpath, '//ram:ApplicableHeaderTradeSettlement/ram:InvoiceCurrencyCode' );
if ( '' === $currency ) {
    $errors[] = 'BR-5 : Devise (InvoiceCurrencyCode) manquante';
} else {
    echo "   Devise : $currency\n";
}

// ── BR-6 Seller name ──────────────────────────────────────────────────────────
$seller_name = xval( $xpath, '//ram:SellerTradeParty/ram:Name' );
if ( '' === $seller_name ) {
    $errors[] = 'BR-6 : Raison sociale vendeur manquante';
}

// ── BR-7 Buyer name ───────────────────────────────────────────────────────────
$buyer_name = xval( $xpath, '//ram:BuyerTradeParty/ram:Name' );
if ( '' === $buyer_name ) {
    $errors[] = 'BR-7 : Nom acheteur manquant';
}

// ── BR-8 Seller postal address country ───────────────────────────────────────
$seller_country = xval( $xpath, '//ram:SellerTradeParty/ram:PostalTradeAddress/ram:CountryID' );
if ( '' === $seller_country ) {
    $errors[] = 'BR-8 / BR-9 : Pays vendeur (PostalTradeAddress/CountryID) manquant';
}

// ── BR-9 Buyer postal address country ────────────────────────────────────────
$buyer_country = xval( $xpath, '//ram:BuyerTradeParty/ram:PostalTradeAddress/ram:CountryID' );
if ( '' === $buyer_country ) {
    $errors[] = 'BR-9 : Pays acheteur (PostalTradeAddress/CountryID) manquant';
}

// ── BR-CO-15 : Grand total = basis + tax ─────────────────────────────────────
$basis = (float) xval( $xpath, '//ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:TaxBasisTotalAmount' );
$tax   = (float) xval( $xpath, '//ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:TaxTotalAmount' );
$grand = (float) xval( $xpath, '//ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:GrandTotalAmount' );
$due   = (float) xval( $xpath, '//ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:DuePayableAmount' );
$lines_total = (float) xval( $xpath, '//ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:LineTotalAmount' );

$expected_grand = round( $basis + $tax, 2 );
if ( abs( $grand - $expected_grand ) > 0.01 ) {
    $errors[] = "BR-CO-15 : GrandTotal ($grand) ≠ TaxBasis ($basis) + Tax ($tax) = $expected_grand";
}

// ── BR-CO-16 : Due = grand total (no pre-payment in this invoice) ─────────────
if ( abs( $due - $grand ) > 0.01 ) {
    $warnings[] = "BR-CO-16 : DuePayable ($due) ≠ GrandTotal ($grand) — normal si acompte saisi";
}

// ── BR-CO-10 : Line total = sum of line amounts ───────────────────────────────
$lines_sum = 0.0;
foreach ( xnodes( $xpath, '//ram:IncludedSupplyChainTradeLineItem' ) as $line ) {
    $lines_sum += (float) xval( $xpath, './/ram:LineTotalAmount', $line );
}
if ( abs( $lines_total - round( $lines_sum, 2 ) ) > 0.01 ) {
    $errors[] = "BR-CO-10 : LineTotalAmount header ($lines_total) ≠ somme des lignes (" . round( $lines_sum, 2 ) . ")";
}

// ── BR-Z-1/BR-E-1 : Tax category completeness ────────────────────────────────
$tax_groups = xnodes( $xpath, '//ram:ApplicableHeaderTradeSettlement/ram:ApplicableTradeTax' );
if ( 0 === $tax_groups->length ) {
    $errors[] = 'BR-CO-18 : Aucun groupe de TVA dans le règlement';
} else {
    foreach ( $tax_groups as $tg ) {
        $cat  = xval( $xpath, './/ram:CategoryCode', $tg );
        $rate = xval( $xpath, './/ram:RateApplicablePercent', $tg );
        $calc = xval( $xpath, './/ram:CalculatedAmount', $tg );
        $bs   = xval( $xpath, './/ram:BasisAmount', $tg );
        echo "   TVA groupe : catégorie=$cat  taux=$rate%  base=$bs  montant=$calc\n";

        if ( 'S' === $cat && '' === $rate ) {
            $errors[] = "BR-S-6 : Taux TVA manquant pour catégorie S";
        }
        if ( 'Z' === $cat && (float) $calc !== 0.0 ) {
            $errors[] = "BR-Z-10 : TVA calculée (" . $calc . ") doit être 0 pour catégorie Z";
        }
        if ( 'E' === $cat && '' === xval( $xpath, './/ram:ExemptionReason', $tg )
             && '' === xval( $xpath, './/ram:ExemptionReasonCode', $tg ) ) {
            $warnings[] = "BR-E-10 : Catégorie E (exonéré) sans motif d'exonération (recommandé)";
        }
    }
}

// ── BR-CO-25 : Line tax category matches header ───────────────────────────────
foreach ( xnodes( $xpath, '//ram:IncludedSupplyChainTradeLineItem' ) as $i => $line ) {
    $line_cat  = xval( $xpath, './/ram:ApplicableTradeTax/ram:CategoryCode', $line );
    $line_rate = xval( $xpath, './/ram:ApplicableTradeTax/ram:RateApplicablePercent', $line );
    $found = false;
    foreach ( $tax_groups as $tg ) {
        if ( xval( $xpath, './/ram:CategoryCode', $tg ) === $line_cat
             && xval( $xpath, './/ram:RateApplicablePercent', $tg ) === $line_rate ) {
            $found = true;
            break;
        }
    }
    if ( ! $found ) {
        $errors[] = "BR-CO-25 ligne " . ($i+1) . " : catégorie=$line_cat taux=$line_rate% absent dans les groupes TVA header";
    }
}

// ── Summary ──────────────────────────────────────────────────────────────────

echo "\n=== Règles EN 16931 (BR-*) ===\n";
echo "Facture     : $inv_id\n";
echo "Vendeur     : $seller_name ($seller_country)\n";
echo "Acheteur    : $buyer_name ($buyer_country)\n";
echo "Totaux      : HT=$basis  TVA=$tax  TTC=$grand  Dû=$due\n";

echo "\n";
if ( empty( $errors ) ) {
    echo "✅ Toutes les règles BR vérifiées — aucune erreur\n";
} else {
    foreach ( $errors as $e ) {
        echo "❌ $e\n";
    }
}
if ( ! empty( $warnings ) ) {
    foreach ( $warnings as $w ) {
        echo "⚠️  $w\n";
    }
}

echo "\n";
exit( empty( $errors ) ? 0 : 1 );
