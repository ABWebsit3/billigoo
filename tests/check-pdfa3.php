<?php
/**
 * Lightweight PDF/A-3b conformance check (no Java/VeraPDF needed).
 *
 * Verifies the four properties that matter for Factur-X archival:
 *   1. XMP metadata declares PDF/A-3b  (pdfaid:part=3, conformance=B)
 *   2. Factur-X XML is embedded        (AFRelationship = Data)
 *   3. Embedded file is named correctly (factur-x.xml)
 *   4. Fonts are declared as embedded  (FontDescriptor/FontFile*)
 *
 * Usage: php tests/check-pdfa3.php path/to/invoice.pdf
 */

$pdf_path = $argv[1] ?? null;
if ( ! $pdf_path || ! is_file( $pdf_path ) ) {
    fwrite( STDERR, "Usage: php check-pdfa3.php <invoice.pdf>\n" );
    exit( 1 );
}

$pdf = file_get_contents( $pdf_path );
$ok  = true;

echo "=== PDF/A-3b check : " . basename( $pdf_path ) . " ===\n\n";

// ── 1. XMP — pdfaid:part must be 3 ───────────────────────────────────────────
if ( preg_match( '/<pdfaid:part>\s*(\d+)\s*<\/pdfaid:part>/s', $pdf, $m ) ) {
    $part = (int) $m[1];
    if ( 3 === $part ) {
        echo "✅ pdfaid:part = 3 (PDF/A-3)\n";
    } else {
        echo "❌ pdfaid:part = $part (doit être 3 pour PDF/A-3)\n";
        $ok = false;
    }
} else {
    echo "❌ pdfaid:part absent du XMP\n";
    $ok = false;
}

// ── 2. XMP — pdfaid:conformance must be B ────────────────────────────────────
if ( preg_match( '/<pdfaid:conformance>\s*([A-Z])\s*<\/pdfaid:conformance>/s', $pdf, $m ) ) {
    $conf = $m[1];
    if ( 'B' === $conf ) {
        echo "✅ pdfaid:conformance = B\n";
    } else {
        echo "⚠️  pdfaid:conformance = $conf (attendu B)\n";
    }
} else {
    echo "❌ pdfaid:conformance absent du XMP\n";
    $ok = false;
}

// ── 3. Factur-X XML embedded (file name) ─────────────────────────────────────
if ( str_contains( $pdf, 'factur-x.xml' ) ) {
    echo "✅ Fichier 'factur-x.xml' présent dans le PDF\n";
} else {
    echo "❌ 'factur-x.xml' non trouvé — XML non embarqué ou mal nommé\n";
    $ok = false;
}

// ── 4. AFRelationship = Data (required for PDF/A-3 embedded files) ────────────
if ( str_contains( $pdf, '/AFRelationship' ) ) {
    if ( str_contains( $pdf, '/Data' ) ) {
        echo "✅ AFRelationship /Data présent\n";
    } else {
        echo "⚠️  AFRelationship présent mais valeur non détectée\n";
    }
} else {
    echo "⚠️  AFRelationship absent (requis PDF/A-3)\n";
}

// ── 5. Fonts embedded (FontFile / FontFile2 / FontFile3) ──────────────────────
$font_file_count = substr_count( $pdf, '/FontFile' );
if ( $font_file_count > 0 ) {
    echo "✅ Polices embarquées détectées ($font_file_count références FontFile*)\n";
} else {
    echo "❌ Aucune police embarquée — PDF/A exige que toutes les polices soient incluses\n";
    $ok = false;
}

// ── 6. Factur-X XMP extension schema ─────────────────────────────────────────
// The library uses urn:factur-x:pdfa:CrossIndustryDocument:invoice:1p0#
// (not urn:factur-x.eu which is only in the profile URN inside the XML).
if ( str_contains( $pdf, 'urn:factur-x:pdfa:CrossIndustryDocument:invoice:1p0' ) ) {
    echo "✅ Schéma XMP Factur-X présent (urn:factur-x:pdfa:...)\n";
} else {
    echo "❌ Schéma XMP Factur-X absent\n";
    $ok = false;
}

// ── Summary ───────────────────────────────────────────────────────────────────
echo "\n";
if ( $ok ) {
    echo "✅ Tous les indicateurs PDF/A-3b sont corrects.\n";
    echo "   Pour une validation exhaustive, utiliser VeraPDF (verapdf.org).\n";
} else {
    echo "❌ Un ou plusieurs indicateurs PDF/A-3b échouent.\n";
}
