<?php
/**
 * Shared sample invoice data (shape of InvoiceData::from_order()) for the
 * standalone pipeline/XSD checks.
 *
 * @package Billigoo
 */

return array(
	'invoice_number'  => 'FA-2026-0001',
	'issue_date'      => '20260608',
	'issue_date_disp' => '08/06/2026',
	'currency'        => 'EUR',
	'buyer_reference' => '1042',
	'order_id'        => 1042,
	'seller'          => array(
		'name'     => 'Studio Defacto SARL',
		'siren'    => '912345678',
		'siret'    => '91234567800019',
		'vat'      => 'FR12912345678',
		'address'  => '10 rue des Lilas',
		'postcode' => '11100',
		'city'     => 'Narbonne',
		'country'  => 'FR',
	),
	'buyer'           => array(
		'name'     => 'ACME Pro SAS',
		'siret'    => '55203453400041',
		'siren'    => '552034534',
		'vat'      => 'FR40552034534',
		'address'  => '5 avenue de la République',
		'postcode' => '75011',
		'city'     => 'Paris',
		'country'  => 'FR',
	),
	'lines'           => array(
		array(
			'name'     => 'Prestation de conseil',
			'qty'      => 2.0,
			'unit_net' => 500.0,
			'net'      => 1000.0,
			'tax'      => 200.0,
			'rate'     => 20.0,
			'category' => 'S',
		),
		array(
			'name'     => 'Livraison',
			'qty'      => 1.0,
			'unit_net' => 15.0,
			'net'      => 15.0,
			'tax'      => 3.0,
			'rate'     => 20.0,
			'category' => 'S',
		),
	),
	'tax_groups'      => array(
		array(
			'category' => 'S',
			'rate'     => 20.0,
			'basis'    => 1015.0,
			'tax'      => 203.0,
		),
	),
	'payment_terms'   => 'Paiement à 30 jours.',
	'profile'         => 'BASIC',
	'totals'          => array(
		'line'  => 1015.0,
		'basis' => 1015.0,
		'tax'   => 203.0,
		'grand' => 1218.0,
		'due'   => 1218.0,
	),
);
