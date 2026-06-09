<?php
/**
 * Generates languages/billigoo.pot by scanning all PHP files for WP i18n calls,
 * then creates billigoo-fr_FR.po (identity — source strings are already French)
 * and compiles billigoo-fr_FR.mo.
 *
 * Usage: php bin/generate-pot.php
 */

$plugin_root = dirname( __DIR__ );
$lang_dir    = $plugin_root . '/languages';
$pot_file    = $lang_dir . '/billigoo.pot';
$po_file     = $lang_dir . '/billigoo-fr_FR.po';
$mo_file     = $lang_dir . '/billigoo-fr_FR.mo';
$domain      = 'billigoo';

if ( ! is_dir( $lang_dir ) ) {
    mkdir( $lang_dir, 0755, true );
}

// ── 1. Scan PHP files ─────────────────────────────────────────────────────────
$dirs = [ 'includes', 'admin', 'woocommerce', 'templates', 'public' ];
$php_files = [];
foreach ( $dirs as $d ) {
    $path = $plugin_root . '/' . $d;
    if ( ! is_dir( $path ) ) {
        continue;
    }
    $it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $path ) );
    foreach ( $it as $f ) {
        if ( $f->isFile() && 'php' === $f->getExtension() ) {
            $php_files[] = $f->getPathname();
        }
    }
}
$php_files[] = $plugin_root . '/billigoo.php';

// Patterns: __(), _e(), esc_html__(), esc_attr__(), esc_html_e(), esc_attr_e()
// _x(), _ex() — with context
// _n(), _nx() — plural
$patterns = [
    // Simple: __('string', 'domain') or _e('string', 'domain')
    'simple'   => '/(?:__|_e|esc_html__|esc_attr__|esc_html_e|esc_attr_e)\s*\(\s*([\'"])((?:[^\\\1]|\\.)*?)\1\s*,\s*[\'"]' . preg_quote( $domain, '/' ) . '[\'"]\s*\)/s',
    // Context: _x('string', 'context', 'domain')
    'context'  => '/_x\s*\(\s*([\'"])((?:[^\\\1]|\\.)*?)\1\s*,\s*([\'"])((?:[^\\\3]|\\.)*?)\3\s*,\s*[\'"]' . preg_quote( $domain, '/' ) . '[\'"]\s*\)/s',
    // Plural: _n('singular', 'plural', $n, 'domain')
    'plural'   => '/_n\s*\(\s*([\'"])((?:[^\\\1]|\\.)*?)\1\s*,\s*([\'"])((?:[^\\\3]|\\.)*?)\3\s*,\s*[^\'"]+?,\s*[\'"]' . preg_quote( $domain, '/' ) . '[\'"]\s*\)/s',
];

$entries = []; // key => [singular, plural|null, context|null, locations[]]

foreach ( $php_files as $file ) {
    $src      = file_get_contents( $file );
    $rel_path = ltrim( str_replace( $plugin_root, '', $file ), '/\\' );
    $rel_path = str_replace( '\\', '/', $rel_path );

    // Simple
    preg_match_all( $patterns['simple'], $src, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE );
    foreach ( $matches as $m ) {
        $str = stripslashes( $m[2][0] );
        $key = 'S:' . $str;
        $entries[ $key ]['singular'] = $str;
        $entries[ $key ]['plural']   = null;
        $entries[ $key ]['context']  = null;
        $line = substr_count( substr( $src, 0, $m[0][1] ), "\n" ) + 1;
        $entries[ $key ]['locations'][] = $rel_path . ':' . $line;
    }

    // Context
    preg_match_all( $patterns['context'], $src, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE );
    foreach ( $matches as $m ) {
        $str = stripslashes( $m[2][0] );
        $ctx = stripslashes( $m[4][0] );
        $key = 'C:' . $ctx . ':' . $str;
        $entries[ $key ]['singular'] = $str;
        $entries[ $key ]['plural']   = null;
        $entries[ $key ]['context']  = $ctx;
        $line = substr_count( substr( $src, 0, $m[0][1] ), "\n" ) + 1;
        $entries[ $key ]['locations'][] = $rel_path . ':' . $line;
    }

    // Plural
    preg_match_all( $patterns['plural'], $src, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE );
    foreach ( $matches as $m ) {
        $singular = stripslashes( $m[2][0] );
        $plural   = stripslashes( $m[4][0] );
        $key      = 'P:' . $singular;
        $entries[ $key ]['singular'] = $singular;
        $entries[ $key ]['plural']   = $plural;
        $entries[ $key ]['context']  = null;
        $line = substr_count( substr( $src, 0, $m[0][1] ), "\n" ) + 1;
        $entries[ $key ]['locations'][] = $rel_path . ':' . $line;
    }
}

// Deduplicate locations
foreach ( $entries as &$e ) {
    $e['locations'] = array_unique( $e['locations'] );
}
unset( $e );

echo 'Strings trouvees : ' . count( $entries ) . PHP_EOL;

// ── 2. Write .pot ─────────────────────────────────────────────────────────────
function pot_escape( string $str ): string {
    return str_replace( [ '\\', '"', "\n", "\r", "\t" ], [ '\\\\', '\\"', '\\n', '\\r', '\\t' ], $str );
}

$now  = gmdate( 'Y-m-d H:iO' );
$pot  = <<<POT
# Billigoo — Plugin WooCommerce Factur-X
# Copyright (C) 2026 Billigoo
# This file is distributed under the GPL-2.0-or-later license.
#
msgid ""
msgstr ""
"Project-Id-Version: Billigoo 1.0.0\\n"
"Report-Msgid-Bugs-To: https://billigoo.fr\\n"
"POT-Creation-Date: {$now}\\n"
"MIME-Version: 1.0\\n"
"Content-Type: text/plain; charset=UTF-8\\n"
"Content-Transfer-Encoding: 8bit\\n"
"X-Generator: Billigoo generate-pot.php\\n"
"X-Domain: billigoo\\n"


POT;

foreach ( $entries as $entry ) {
    $pot .= '#: ' . implode( "\n#: ", array_slice( $entry['locations'], 0, 5 ) ) . "\n";
    if ( null !== $entry['context'] ) {
        $pot .= 'msgctxt "' . pot_escape( $entry['context'] ) . '"' . "\n";
    }
    $pot .= 'msgid "' . pot_escape( $entry['singular'] ) . '"' . "\n";
    if ( null !== $entry['plural'] ) {
        $pot .= 'msgid_plural "' . pot_escape( $entry['plural'] ) . '"' . "\n";
        $pot .= 'msgstr[0] ""' . "\n";
        $pot .= 'msgstr[1] ""' . "\n";
    } else {
        $pot .= 'msgstr ""' . "\n";
    }
    $pot .= "\n";
}

file_put_contents( $pot_file, $pot );
echo 'POT ecrit    : languages/billigoo.pot' . PHP_EOL;

// ── 3. Write fr_FR.po (identity — source already in French) ──────────────────
$po = $pot;
$po = str_replace(
    '"POT-Creation-Date: ' . $now . '\n"',
    '"PO-Revision-Date: ' . $now . '\n"',
    $po
);
$po = str_replace( 'X-Generator: Billigoo generate-pot.php', 'Language: fr_FR', $po );
$po = str_replace( '"X-Domain: billigoo\\n"', '"Plural-Forms: nplurals=2; plural=(n > 1);\\n"' . "\n" . '"X-Domain: billigoo\\n"', $po );

// For identity translation: fill msgstr with msgid value
$po = preg_replace_callback(
    '/msgid ("(?:[^"\\\\]|\\\\.)*")\nmsgstr ""\n/s',
    fn( $m ) => 'msgid ' . $m[1] . "\nmsgstr " . $m[1] . "\n",
    $po
);
// Plural identity
$po = preg_replace_callback(
    '/msgid_plural ("(?:[^"\\\\]|\\\\.)*")\nmsgstr\[0\] ""\nmsgstr\[1\] ""\n/s',
    fn( $m ) => 'msgid_plural ' . $m[1] . "\nmsgstr[0] " . $m[1] . "\nmsgstr[1] " . $m[1] . "\n",
    $po
);

file_put_contents( $po_file, $po );
echo 'PO ecrit     : languages/billigoo-fr_FR.po' . PHP_EOL;

// ── 4. Compile .mo ────────────────────────────────────────────────────────────
// Parse po entries
$mo_entries = [];
$lines = explode( "\n", $po );
$i = 0;
$count = count( $lines );
while ( $i < $count ) {
    $line = $lines[ $i ];
    if ( str_starts_with( $line, 'msgctxt ' ) ) {
        $i++;
        continue;
    }
    if ( str_starts_with( $line, 'msgid "' ) ) {
        // Read multi-line msgid
        $msgid = '';
        $val   = substr( $line, 7, -1 ); // strip msgid " and trailing "
        $msgid .= stripslashes( str_replace( [ '\n', '\t', '\\"' ], [ "\n", "\t", '"' ], $val ) );
        $i++;
        while ( isset( $lines[ $i ] ) && str_starts_with( $lines[ $i ], '"' ) ) {
            $val    = substr( $lines[ $i ], 1, -1 );
            $msgid .= stripslashes( str_replace( [ '\n', '\t', '\\"' ], [ "\n", "\t", '"' ], $val ) );
            $i++;
        }
        // Now read msgstr
        if ( isset( $lines[ $i ] ) && str_starts_with( $lines[ $i ], 'msgstr "' ) ) {
            $msgstr = '';
            $val    = substr( $lines[ $i ], 8, -1 );
            $msgstr .= stripslashes( str_replace( [ '\n', '\t', '\\"' ], [ "\n", "\t", '"' ], $val ) );
            $i++;
            while ( isset( $lines[ $i ] ) && str_starts_with( $lines[ $i ], '"' ) ) {
                $val     = substr( $lines[ $i ], 1, -1 );
                $msgstr .= stripslashes( str_replace( [ '\n', '\t', '\\"' ], [ "\n", "\t", '"' ], $val ) );
                $i++;
            }
            if ( '' !== $msgid && '' !== $msgstr ) {
                $mo_entries[ $msgid ] = $msgstr;
            }
        }
        continue;
    }
    $i++;
}

// Build .mo binary (GNU MO format, little-endian)
$originals   = array_keys( $mo_entries );
$translations = array_values( $mo_entries );
sort( $originals ); // MO requires sorted originals
$n = count( $originals );
$translations_sorted = [];
foreach ( $originals as $k ) {
    $translations_sorted[] = $mo_entries[ $k ];
}

$magic   = 0x950412de; // LE magic
$rev     = 0;
$o_off   = 28;               // orig table starts right after header
$t_off   = $o_off + $n * 8;  // trans table
$strings_off = $t_off + $n * 8;

$orig_table  = '';
$trans_table = '';
$orig_pool   = '';
$trans_pool  = '';
$cur_orig_offset  = $strings_off;
$cur_trans_offset = $strings_off; // will be adjusted after orig_pool

foreach ( $originals as $idx => $orig ) {
    $len           = strlen( $orig );
    $orig_table   .= pack( 'VV', $len, $cur_orig_offset );
    $orig_pool    .= $orig . "\0";
    $cur_orig_offset += $len + 1;
}

$trans_start = $cur_orig_offset;
$cur_trans_offset = $trans_start;
foreach ( $translations_sorted as $trans ) {
    $len            = strlen( $trans );
    $trans_table   .= pack( 'VV', $len, $cur_trans_offset );
    $trans_pool    .= $trans . "\0";
    $cur_trans_offset += $len + 1;
}

// Adjust trans table offsets (we computed them as if starting at strings_off, fix)
$trans_table_fixed = '';
$cur = $trans_start;
foreach ( $translations_sorted as $trans ) {
    $trans_table_fixed .= pack( 'VV', strlen( $trans ), $cur );
    $cur += strlen( $trans ) + 1;
}

$mo = pack( 'V', $magic )          // magic
    . pack( 'V', $rev )             // revision
    . pack( 'V', $n )               // num strings
    . pack( 'V', $o_off )           // orig table offset
    . pack( 'V', $t_off )           // trans table offset
    . pack( 'V', 0 )                // hash table size (unused)
    . pack( 'V', 0 )                // hash table offset (unused)
    . $orig_table
    . $trans_table_fixed
    . $orig_pool
    . $trans_pool;

file_put_contents( $mo_file, $mo );
echo 'MO compile   : languages/billigoo-fr_FR.mo (' . $n . ' entrees)' . PHP_EOL;
echo PHP_EOL;
echo 'Done.' . PHP_EOL;
