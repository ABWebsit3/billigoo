<?php
/**
 * Builds the Factur-X CII XML (BASIC profile, EN 16931 compliant) from an order.
 *
 * @package Billigoo
 */

namespace Billigoo\Core;

use Atgp\FacturX\XsdValidator;
use Atgp\FacturX\Utils\ProfileHandler;

defined( 'ABSPATH' ) || exit;

/**
 * Generates a Cross Industry Invoice (CII) XML document for the BASIC profile.
 *
 * The element ordering here is dictated by the Factur-X 1.08 BASIC XSD bundled
 * with atgp/factur-x; changing the sequence will fail schema validation.
 */
final class FacturxBuilder {

	private const NS = array(
		'rsm' => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
		'ram' => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
		'qdt' => 'urn:un:unece:uncefact:data:standard:QualifiedDataType:100',
		'udt' => 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100',
	);

	private const PROFILE_URN = 'urn:cen.eu:en16931:2017#compliant#urn:factur-x.eu:1p0:basic';

	private \DOMDocument $doc;

	/**
	 * Build and validate the CII XML from normalised invoice data.
	 *
	 * @param array<string,mixed> $data Output of InvoiceData::from_order().
	 * @return string XSD-valid CII XML.
	 *
	 * @throws \RuntimeException When the produced XML fails XSD validation.
	 */
	public function build( array $data ): string {
		$xml = $this->render( $data );

		$validator = new XsdValidator();
		try {
			$validator->validateWithException( $xml, ProfileHandler::PROFILE_FACTURX_BASIC );
		} catch ( \Throwable $e ) {
			throw new \RuntimeException( 'Billigoo: generated Factur-X XML is not schema-valid: ' . $e->getMessage(), 0, $e );
		}

		return $xml;
	}

	/**
	 * Render the normalised data into CII XML.
	 *
	 * @param array<string,mixed> $d Extracted data.
	 */
	private function render( array $d ): string {
		$this->doc                     = new \DOMDocument( '1.0', 'UTF-8' );
		$this->doc->formatOutput       = true;
		$this->doc->preserveWhiteSpace = false;

		$root = $this->el( 'rsm:CrossIndustryInvoice' );
		foreach ( self::NS as $prefix => $uri ) {
			$root->setAttributeNS( 'http://www.w3.org/2000/xmlns/', 'xmlns:' . $prefix, $uri );
		}
		$this->doc->appendChild( $root );

		$root->appendChild( $this->context() );
		$root->appendChild( $this->document( $d ) );
		$root->appendChild( $this->transaction( $d ) );

		return $this->doc->saveXML();
	}

	/**
	 * ExchangedDocumentContext (profile declaration).
	 */
	private function context(): \DOMElement {
		$ctx   = $this->el( 'rsm:ExchangedDocumentContext' );
		$param = $this->el( 'ram:GuidelineSpecifiedDocumentContextParameter' );
		$param->appendChild( $this->el( 'ram:ID', self::PROFILE_URN ) );
		$ctx->appendChild( $param );
		return $ctx;
	}

	/**
	 * ExchangedDocument (invoice header: number, type, date).
	 *
	 * @param array<string,mixed> $d Data.
	 */
	private function document( array $d ): \DOMElement {
		$doc = $this->el( 'rsm:ExchangedDocument' );
		$doc->appendChild( $this->el( 'ram:ID', $d['invoice_number'] ) );
		$doc->appendChild( $this->el( 'ram:TypeCode', '380' ) );

		$issue = $this->el( 'ram:IssueDateTime' );
		$dts   = $this->el( 'udt:DateTimeString', $d['issue_date'] );
		$dts->setAttribute( 'format', '102' );
		$issue->appendChild( $dts );
		$doc->appendChild( $issue );

		return $doc;
	}

	/**
	 * SupplyChainTradeTransaction (lines + header agreement/delivery/settlement).
	 *
	 * @param array<string,mixed> $d Data.
	 */
	private function transaction( array $d ): \DOMElement {
		$tx = $this->el( 'rsm:SupplyChainTradeTransaction' );

		$i = 0;
		foreach ( $d['lines'] as $line ) {
			$tx->appendChild( $this->line_item( ++$i, $line, $d['currency'] ) );
		}

		$tx->appendChild( $this->header_agreement( $d ) );
		$tx->appendChild( $this->el( 'ram:ApplicableHeaderTradeDelivery' ) ); // Required, may be empty in BASIC.
		$tx->appendChild( $this->header_settlement( $d ) );

		return $tx;
	}

	/**
	 * A single IncludedSupplyChainTradeLineItem.
	 *
	 * @param int                 $line_id  1-based line index.
	 * @param array<string,mixed> $line     Line data.
	 * @param string              $currency Currency code.
	 */
	private function line_item( int $line_id, array $line, string $currency ): \DOMElement {
		$item = $this->el( 'ram:IncludedSupplyChainTradeLineItem' );

		$assoc = $this->el( 'ram:AssociatedDocumentLineDocument' );
		$assoc->appendChild( $this->el( 'ram:LineID', (string) $line_id ) );
		$item->appendChild( $assoc );

		$product = $this->el( 'ram:SpecifiedTradeProduct' );
		$product->appendChild( $this->el( 'ram:Name', $line['name'] ) );
		$item->appendChild( $product );

		$agreement = $this->el( 'ram:SpecifiedLineTradeAgreement' );
		$price     = $this->el( 'ram:NetPriceProductTradePrice' );
		$price->appendChild( $this->el( 'ram:ChargeAmount', $this->amount( $line['unit_net'] ) ) );
		$agreement->appendChild( $price );
		$item->appendChild( $agreement );

		$delivery = $this->el( 'ram:SpecifiedLineTradeDelivery' );
		$billed   = $this->el( 'ram:BilledQuantity', $this->amount( $line['qty'] ) );
		$billed->setAttribute( 'unitCode', 'C62' ); // C62 = "one" (unit).
		$delivery->appendChild( $billed );
		$item->appendChild( $delivery );

		$settlement = $this->el( 'ram:SpecifiedLineTradeSettlement' );
		$tax        = $this->el( 'ram:ApplicableTradeTax' );
		$tax->appendChild( $this->el( 'ram:TypeCode', 'VAT' ) );
		$tax->appendChild( $this->el( 'ram:CategoryCode', $line['category'] ) );
		$tax->appendChild( $this->el( 'ram:RateApplicablePercent', $this->amount( $line['rate'] ) ) );
		$settlement->appendChild( $tax );
		$summation = $this->el( 'ram:SpecifiedTradeSettlementLineMonetarySummation' );
		$summation->appendChild( $this->el( 'ram:LineTotalAmount', $this->amount( $line['net'] ) ) );
		$settlement->appendChild( $summation );
		$item->appendChild( $settlement );

		return $item;
	}

	/**
	 * ApplicableHeaderTradeAgreement (buyer reference, seller, buyer).
	 *
	 * @param array<string,mixed> $d Data.
	 */
	private function header_agreement( array $d ): \DOMElement {
		$agreement = $this->el( 'ram:ApplicableHeaderTradeAgreement' );

		if ( '' !== (string) $d['buyer_reference'] ) {
			$agreement->appendChild( $this->el( 'ram:BuyerReference', (string) $d['buyer_reference'] ) );
		}

		$agreement->appendChild( $this->trade_party( 'ram:SellerTradeParty', $d['seller'] ) );
		$agreement->appendChild( $this->trade_party( 'ram:BuyerTradeParty', $d['buyer'] ) );

		return $agreement;
	}

	/**
	 * Build a Seller/Buyer TradeParty element.
	 *
	 * @param string               $tag   Qualified element name.
	 * @param array<string,string> $party Party data.
	 */
	private function trade_party( string $tag, array $party ): \DOMElement {
		$node = $this->el( $tag );
		$node->appendChild( $this->el( 'ram:Name', '' !== $party['name'] ? $party['name'] : '—' ) );

		if ( '' !== $party['siren'] ) {
			$org = $this->el( 'ram:SpecifiedLegalOrganization' );
			$id  = $this->el( 'ram:ID', $party['siren'] );
			$id->setAttribute( 'schemeID', '0002' ); // 0002 = SIRENE.
			$org->appendChild( $id );
			$node->appendChild( $org );
		}

		$address = $this->el( 'ram:PostalTradeAddress' );
		if ( '' !== $party['postcode'] ) {
			$address->appendChild( $this->el( 'ram:PostcodeCode', $party['postcode'] ) );
		}
		if ( '' !== $party['address'] ) {
			$address->appendChild( $this->el( 'ram:LineOne', $party['address'] ) );
		}
		if ( '' !== $party['city'] ) {
			$address->appendChild( $this->el( 'ram:CityName', $party['city'] ) );
		}
		$address->appendChild( $this->el( 'ram:CountryID', $party['country'] ) );
		$node->appendChild( $address );

		if ( '' !== $party['vat'] ) {
			$reg = $this->el( 'ram:SpecifiedTaxRegistration' );
			$id  = $this->el( 'ram:ID', $party['vat'] );
			$id->setAttribute( 'schemeID', 'VA' ); // VA = VAT number.
			$reg->appendChild( $id );
			$node->appendChild( $reg );
		}

		return $node;
	}

	/**
	 * ApplicableHeaderTradeSettlement (currency, tax breakdown, totals).
	 *
	 * @param array<string,mixed> $d Data.
	 */
	private function header_settlement( array $d ): \DOMElement {
		$settlement = $this->el( 'ram:ApplicableHeaderTradeSettlement' );
		$settlement->appendChild( $this->el( 'ram:InvoiceCurrencyCode', $d['currency'] ) );

		foreach ( $d['tax_groups'] as $group ) {
			$tax = $this->el( 'ram:ApplicableTradeTax' );
			$tax->appendChild( $this->el( 'ram:CalculatedAmount', $this->amount( $group['tax'] ) ) );
			$tax->appendChild( $this->el( 'ram:TypeCode', 'VAT' ) );
			$tax->appendChild( $this->el( 'ram:BasisAmount', $this->amount( $group['basis'] ) ) );
			$tax->appendChild( $this->el( 'ram:CategoryCode', $group['category'] ) );
			$tax->appendChild( $this->el( 'ram:RateApplicablePercent', $this->amount( $group['rate'] ) ) );
			$settlement->appendChild( $tax );
		}

		if ( '' !== $d['payment_terms'] ) {
			$terms = $this->el( 'ram:SpecifiedTradePaymentTerms' );
			$terms->appendChild( $this->el( 'ram:Description', $d['payment_terms'] ) );
			$settlement->appendChild( $terms );
		}

		$totals   = $d['totals'];
		$currency = $d['currency'];
		$sum      = $this->el( 'ram:SpecifiedTradeSettlementHeaderMonetarySummation' );
		$sum->appendChild( $this->el( 'ram:LineTotalAmount', $this->amount( $totals['line'] ) ) );
		$sum->appendChild( $this->el( 'ram:TaxBasisTotalAmount', $this->amount( $totals['basis'] ) ) );
		$tax_total = $this->el( 'ram:TaxTotalAmount', $this->amount( $totals['tax'] ) );
		$tax_total->setAttribute( 'currencyID', $currency );
		$sum->appendChild( $tax_total );
		$sum->appendChild( $this->el( 'ram:GrandTotalAmount', $this->amount( $totals['grand'] ) ) );
		$sum->appendChild( $this->el( 'ram:DuePayableAmount', $this->amount( $totals['due'] ) ) );
		$settlement->appendChild( $sum );

		return $settlement;
	}

	/**
	 * Create a namespaced element with optional (escaped) text content.
	 *
	 * @param string      $qualified Qualified name, e.g. "ram:ID".
	 * @param string|null $value     Text content.
	 */
	private function el( string $qualified, ?string $value = null ): \DOMElement {
		$prefix = explode( ':', $qualified )[0];
		$node   = $this->doc->createElementNS( self::NS[ $prefix ], $qualified );
		if ( null !== $value ) {
			$node->appendChild( $this->doc->createTextNode( $value ) );
		}
		return $node;
	}

	/**
	 * Format a monetary/numeric value with two decimals and a dot separator.
	 *
	 * @param float|int|string $value Raw value.
	 */
	private function amount( $value ): string {
		return number_format( (float) $value, 2, '.', '' );
	}
}
