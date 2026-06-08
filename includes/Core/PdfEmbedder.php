<?php
/**
 * Assembles the final Factur-X document (PDF/A-3 + embedded CII XML).
 *
 * @package Billigoo
 */

namespace Billigoo\Core;

use Atgp\FacturX\Writer;
use Atgp\FacturX\Utils\ProfileHandler;

defined( 'ABSPATH' ) || exit;

/**
 * Thin wrapper over atgp/factur-x Writer: merges the visual PDF and the CII XML
 * into a single Factur-X PDF, naming the attachment `factur-x.xml` and applying
 * the PDF/A-3 + XMP metadata. The XML is validated upstream by FacturxBuilder,
 * so XSD re-validation is disabled here to avoid duplicate work.
 */
final class PdfEmbedder {

	/**
	 * Embed the XML into the PDF and return the assembled Factur-X bytes.
	 *
	 * @param string $pdf_bytes Visual PDF content.
	 * @param string $xml       XSD-valid CII XML.
	 * @return string Factur-X PDF content.
	 *
	 * @throws \RuntimeException On embedding failure.
	 */
	public function embed( string $pdf_bytes, string $xml ): string {
		try {
			$writer = new Writer();
			return $writer->generate(
				$pdf_bytes,
				$xml,
				ProfileHandler::PROFILE_FACTURX_BASIC,
				false // XML already validated by FacturxBuilder.
			);
		} catch ( \Throwable $e ) {
			throw new \RuntimeException( 'Billigoo: failed to assemble Factur-X PDF: ' . $e->getMessage(), 0, $e );
		}
	}
}
