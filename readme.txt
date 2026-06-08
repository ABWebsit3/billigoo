=== Billigoo ===
Contributors: billigoo
Tags: woocommerce, factur-x, facturation, invoice, france
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
WC requires at least: 7.0
WC tested up to: 9.9
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Facturation électronique Factur-X (PDF/A-3 + XML CII, EN 16931) conforme à la réforme française 2026 pour WooCommerce.

== Description ==

Billigoo génère automatiquement, pour chaque commande WooCommerce, une facture
électronique au format Factur-X (PDF lisible + XML structuré embarqué), conforme
au profil BASIC de la norme EN 16931.

Fonctionnalités (MVP) :

* Capture des données B2B au checkout (raison sociale, SIRET, TVA) — checkout classique et blocs.
* Validation du SIRET (algorithme de Luhn) en direct.
* Numérotation séquentielle légale, continue et sans trou (verrou base de données).
* Génération Factur-X BASIC (PDF/A via mPDF + XML CII validé contre le XSD officiel).
* Déclenchement automatique selon le statut de commande (processing / completed).
* Liste des factures dans l'admin + pièce jointe PDF aux emails client.

== Installation ==

1. Installer le plugin dans `wp-content/plugins/billigoo`.
2. Exécuter `composer install` dans le dossier du plugin (dépendances mPDF + atgp/factur-x).
3. Activer le plugin (WooCommerce doit être actif).
4. Renseigner l'identité du vendeur dans WooCommerce → Billigoo · Réglages.

== Changelog ==

= 1.0.0 =
* Version initiale (MVP Factur-X BASIC).
