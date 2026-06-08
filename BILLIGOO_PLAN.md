# BILLIGOO — Plugin WooCommerce Factur-X
## Plan de développement complet pour Claude Code

---

## Vue d'ensemble

**Nom du plugin :** Billigoo  
**Slug WordPress :** `billigoo`  
**Version initiale :** 1.0.0  
**Objectif :** Plugin WooCommerce de facturation électronique conforme à la réforme française 2026-2027.  
Génère des factures au format Factur-X (PDF/A-3 + XML CII EN 16931), les transmet automatiquement aux Plateformes Agréées (PA), et gère l'e-reporting B2C.

**Modèle économique :**
| Plan | Prix | Équivalent mensuel | Cible |
|------|------|--------------------|-------|
| Starter | Gratuit | 0 €/mois | Boutiques B2B < 50 factures/mois |
| Pro | 69 €/an | 5,75 €/mois | Boutiques avec transmission PA |
| Agence | 149 €/an | 12,42 €/mois | Agences / multi-sites (5 domaines) |
| Add-on Template | 19 € one-shot | — | Templates PDF premium |
| Service Setup | 79 € one-shot | — | Session visio configuration |

**Positionnement concurrentiel :**
- e-facturX : 0 à 99 €/mois (348–1 188 €/an) — trop cher pour les TPE
- FactureXPress : early access 25 € — pas encore distribué
- Billigoo : 69 €/an → **5,75 €/mois**, flat, sans compteur de factures

---

## Conformité réglementaire française

### Calendrier obligatoire
- **1er sept. 2026 :** Réception obligatoire pour TOUTES les entreprises assujetties à la TVA
- **1er sept. 2026 :** Émission obligatoire grandes entreprises + ETI
- **1er sept. 2027 :** Émission obligatoire TPE/PME

### Formats acceptés (à implémenter)
1. **Factur-X** (priorité 1) — format hybride PDF/A-3 + XML CII — standard franco-allemand
2. **CII** (Cross Industry Invoice) — XML pur, profil EN 16931
3. **UBL 2.1** — format européen alternatif (priorité 2)

### Profils Factur-X à supporter
- `MINIMUM` — données identité + montant total (B2C simplifié)
- `BASIC WL` — sans lignes de détail
- `BASIC` — avec lignes de détail (recommandé TPE)
- `EN 16931` — profil complet conforme norme européenne (Pro/Agence)

### Données obligatoires par facture (EN 16931)
- Numéro de facture (séquentiel, sans trou, inaltérable)
- Date d'émission
- SIREN/SIRET vendeur + acheteur
- Numéro TVA intracommunautaire
- Adresses complètes vendeur + acheteur
- Désignation, quantité, prix unitaire HT, taux TVA par ligne
- Total HT, total TVA par taux, total TTC
- Conditions de paiement (délai, mode)
- Devise (EUR par défaut)

### E-reporting (obligation 2027 pour TPE)
- Transmission des données de transaction B2C à l'administration
- Transmission des données de paiement
- Périodicité : mensuelle ou trimestrielle selon volume

---

## Architecture du plugin

### Arborescence des fichiers

```
billigoo/
├── billigoo.php                    # Fichier principal, headers WordPress
├── readme.txt                      # Readme WordPress.org
├── uninstall.php                   # Nettoyage BDD à désinstallation
│
├── includes/
│   ├── class-billigoo.php          # Classe principale, init hooks
│   ├── class-billigoo-activator.php
│   ├── class-billigoo-deactivator.php
│   ├── class-billigoo-license.php  # Gestion licences Pro/Agence
│   │
│   ├── core/
│   │   ├── class-invoice-generator.php   # Orchestrateur génération
│   │   ├── class-facturx-builder.php     # Construction XML CII
│   │   ├── class-pdf-builder.php         # Génération PDF/A-3
│   │   ├── class-pdf-embedder.php        # Fusion PDF + XML (embed)
│   │   ├── class-invoice-number.php      # Numérotation séquentielle
│   │   └── class-invoice-validator.php   # Validation avant envoi
│   │
│   ├── pa/                               # Plateformes Agréées
│   │   ├── class-pa-router.php           # Routeur vers PA configurée
│   │   ├── class-pa-pennylane.php        # Connecteur API Pennylane
│   │   ├── class-pa-chorus-pro.php       # Connecteur Chorus Pro (B2G)
│   │   ├── class-pa-tiime.php            # Connecteur Tiime
│   │   └── interface-pa-connector.php    # Interface commune PA
│   │
│   ├── ereporting/
│   │   ├── class-ereporting-manager.php  # Gestion e-reporting B2C
│   │   └── class-ereporting-scheduler.php # Cron mensuel/trimestriel
│   │
│   ├── export/
│   │   ├── class-export-csv.php          # Export CSV factures
│   │   ├── class-export-fec.php          # Export FEC (expert-comptable)
│   │   └── class-export-batch.php        # Génération rétroactive en lot
│   │
│   └── api/
│       ├── class-rest-api.php            # Endpoints REST WP (plan Agence)
│       └── class-webhooks.php            # Webhooks sur statut facture
│
├── admin/
│   ├── class-billigoo-admin.php          # Init pages admin
│   │
│   ├── pages/
│   │   ├── page-dashboard.php            # Dashboard principal
│   │   ├── page-invoices.php             # Liste des factures
│   │   ├── page-settings.php             # Paramètres généraux
│   │   ├── page-license.php              # Activation licence
│   │   └── page-wizard.php              # Assistant configuration (5 étapes)
│   │
│   ├── partials/
│   │   ├── invoice-detail.php            # Vue détail facture
│   │   ├── status-badge.php              # Badge statut (conforme/rejeté/…)
│   │   └── settings-tabs.php             # Navigation onglets settings
│   │
│   └── assets/
│       ├── css/admin.css
│       └── js/admin.js
│
├── woocommerce/
│   ├── class-wc-integration.php          # Hooks WooCommerce
│   ├── class-checkout-fields.php         # Champs B2B au checkout
│   ├── class-order-meta.php              # Métadonnées SIRET/TVA commande
│   └── class-email-attachment.php        # Attache PDF aux emails WC
│
├── public/
│   ├── class-billigoo-public.php
│   └── assets/
│       ├── css/public.css
│       └── js/public.js
│
├── templates/
│   ├── invoice-default/                  # Template PDF standard
│   │   ├── template.php
│   │   └── style.css
│   └── invoice-premium/                  # Template PDF premium (add-on)
│       ├── template.php
│       └── style.css
│
├── vendor/                               # Dépendances Composer
│   └── atgp/
│       └── factur-x/                     # Lib PHP Factur-X (MIT)
│
└── languages/
    ├── billigoo-fr_FR.po
    └── billigoo-fr_FR.mo
```

---

## Phase 1 — Core MVP (semaines 1–4)

### 1.1 Setup initial WordPress

**Fichier `billigoo.php` :**
```php
/**
 * Plugin Name: Billigoo
 * Plugin URI: https://billigoo.fr
 * Description: Facturation électronique Factur-X conforme 2026 pour WooCommerce
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * WC requires at least: 7.0
 * WC tested up to: 9.x
 * Author: [Ton nom]
 * License: GPL-2.0-or-later
 * Text Domain: billigoo
 */
```

**Dépendances Composer :**
```json
{
  "require": {
    "atgp/factur-x": "^1.0",
    "setasign/fpdi": "^2.3"
  }
}
```

**Hooks d'initialisation obligatoires :**
- `woocommerce_loaded` — vérification WC actif
- `before_woocommerce_init` — déclaration compatibilité HPOS
- `woocommerce_blocks_loaded` — compatibilité Checkout Blocks

### 1.2 Compatibilité HPOS (obligatoire WC ≥ 8.2)

```php
// Dans class-billigoo.php
add_action('before_woocommerce_init', function() {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables', __FILE__, true
        );
    }
});
```

### 1.3 Champs B2B au checkout

**Champs à ajouter (fichier `class-checkout-fields.php`) :**

| Champ | ID | Validation | Obligatoire |
|-------|----|------------|-------------|
| SIRET | `billigoo_siret` | 14 chiffres, checksum Luhn | Si client pro coché |
| N° TVA intracom | `billigoo_vat_number` | Format EU (FR + 11 chiffres) | Optionnel |
| Raison sociale | `billigoo_company_name` | Texte libre | Si client pro coché |
| Adresse de facturation pro | Champs WC natifs | — | Réutiliser WC |

**Logique d'affichage :**
- Ajouter une case "Je commande en tant que professionnel" au checkout
- Si cochée → afficher les champs SIRET + TVA + raison sociale
- Validation AJAX inline (vérification format SIRET avant submit)
- Compatibilité Checkout Blocks WooCommerce (via `IntegrationInterface`)
- Sauvegarder en métadonnées de commande + métadonnées client

**Validation SIRET (algo) :**
- 14 chiffres exacts
- Algorithme de Luhn (clé de contrôle)
- Optionnel : appel API INSEE Sirene pour vérification existence (plan Pro)

### 1.4 Numérotation séquentielle légale

**Règles impératives (droit fiscal français) :**
- Numérotation continue, sans trou, sans possibilité de modification
- Préfixe personnalisable (ex: `FA-2026-`)
- Réinitialisation annuelle optionnelle (ex: `FA-2026-0001`, `FA-2027-0001`)
- Stockage du dernier numéro en option WordPress (atomique, avec verrou DB)
- Jamais basé sur l'ID de commande WooCommerce (gaps possibles)

**Implémentation :**
```php
// Verrou pour éviter doublons en concurrence
$lock = get_transient('billigoo_invoice_lock');
if ($lock) { /* retry après 100ms */ }
set_transient('billigoo_invoice_lock', true, 5);
$number = get_option('billigoo_last_invoice_number', 0) + 1;
update_option('billigoo_last_invoice_number', $number);
delete_transient('billigoo_invoice_lock');
```

### 1.5 Génération Factur-X

**Librairie :** `atgp/factur-x` (PHP, licence MIT, maintenue)

**Profil par défaut :** `BASIC` (couvre 95% des cas TPE)  
**Profil Pro :** `EN 16931` complet

**Données mappées depuis WooCommerce :**

```
WC Order → Factur-X
─────────────────────────────────────────
order->get_id()                → BuyerReference
get_option('billigoo_siret')   → SellerTradeParty.ID (SIREN)
order_meta['billigoo_siret']   → BuyerTradeParty.ID (SIRET acheteur)
order->get_billing_company()   → BuyerTradeParty.Name
order->get_date_created()      → IssueDateTime
order->get_items()             → IncludedSupplyChainTradeLineItem[]
item->get_total()              → LineTotalAmount
item->get_tax_class()          → ApplicableTradeTax.RateApplicablePercent
order->get_total_tax()         → TaxTotalAmount
order->get_total()             → GrandTotalAmount
order->get_payment_method()    → SpecifiedTradePaymentTerms
```

**Génération PDF/A-3 :**
- Utiliser `mPDF` ou `TCPDF` pour rendu PDF depuis template PHP
- Convertir en PDF/A-3b (conformité archivage long terme)
- Embarquer le XML CII via `atgp/factur-x` (méthode `embed()`)
- Le fichier XML embarqué doit être nommé `factur-x.xml`
- Métadonnées XMP obligatoires dans le PDF (profil, version)

**Stockage :**
- Dossier protégé : `wp-content/uploads/billigoo/{year}/{month}/FA-2026-0001.pdf`
- Protéger avec `.htaccess` (accès direct bloqué)
- Accès via endpoint WP sécurisé avec vérification nonce + capacité

### 1.6 Déclenchement automatique

**Hooks WooCommerce :**
```php
// Génération à la validation commande
add_action('woocommerce_order_status_processing', [$this, 'generate_invoice']);
add_action('woocommerce_order_status_completed', [$this, 'generate_invoice']);

// Option dans settings : choisir le statut déclencheur
// Valeurs possibles : processing, completed, on-hold
```

**Idempotence :** Ne pas régénérer si une facture existe déjà pour cette commande (vérifier meta `billigoo_invoice_id`).

---

## Phase 2 — Transmission PA (semaines 5–7)

### 2.1 Interface commune PA

```php
interface BilligooPA_Connector {
    public function connect(array $credentials): bool;
    public function send_invoice(string $pdf_path, string $xml_path, array $metadata): array;
    public function get_status(string $remote_id): string;
    public function receive_invoice(string $from_siret): array;
}
```

**Statuts retournés (normaliser) :**
- `pending` — en attente de traitement PA
- `sent` — transmis à la PA
- `acknowledged` — réceptionné par PA destinataire
- `accepted` — accepté par l'acheteur
- `rejected` — rejeté (avec motif)
- `paid` — paiement confirmé (si PA le supporte)

### 2.2 Connecteur Pennylane

**API Pennylane :** REST + OAuth2  
**Documentation :** `https://pennylane.com/fr/api-documentation`

```php
class BilligooPA_Pennylane implements BilligooPA_Connector {
    const API_BASE = 'https://app.pennylane.com/api/v2/';
    
    public function send_invoice($pdf_path, $xml_path, $metadata): array {
        // POST /customer_invoices/import
        // Multipart : fichier PDF + champs metadata
        // Retourne : { id, status, created_at }
    }
    
    public function get_status($remote_id): string {
        // GET /customer_invoices/{id}
        // Retourne le statut courant
    }
}
```

### 2.3 Connecteur Chorus Pro (B2G)

**Usage :** Uniquement si l'acheteur est une entité publique (SIRET commençant par certains codes APE)  
**API :** PISTE (API Chorus Pro) — authentification OAuth2 ClientCredentials  
**Endpoint principal :** `https://piste.gouv.fr/api-oauth/v1/`

```php
// Détecter si acheteur = entité publique
// Via annuaire SIRENE ou flag manuel au checkout
// Si oui → router vers Chorus Pro
// Sinon → router vers PA privée configurée
```

### 2.4 Dashboard statuts

**Vue liste factures (`page-invoices.php`) :**

| Colonne | Contenu |
|---------|---------|
| N° facture | Lien cliquable → détail |
| Commande | Lien vers commande WC |
| Client | Raison sociale + SIRET |
| Montant TTC | Formaté avec devise |
| Date | Date de création |
| Format | Badge (Factur-X / UBL / CII) |
| Statut PA | Badge coloré (pending/sent/accepted/rejected) |
| Actions | Télécharger PDF / Re-transmettre / Voir XML |

**Filtres :**
- Par statut PA
- Par période (date picker)
- Par client (recherche libre)
- Par montant (min/max)

**Stats mensuelles (widget dashboard) :**
- Nombre de factures ce mois
- Montant total TTC facturé
- Taux d'acceptation PA (%)
- Factures en attente / rejetées (alertes)

---

## Phase 3 — Fonctionnalités avancées (semaines 8–10)

### 3.1 E-reporting B2C (plan Pro)

**Obligation :** Transmettre à l'administration les données de transactions B2C et les paiements  
**Périodicité :** Mensuelle (< 2M€ CA) ou trimestrielle

**Implémentation :**
```php
class BilligooEreporting_Manager {
    // Collecte toutes les commandes B2C de la période
    // Génère le fichier de données de transaction
    // Transmet via API PPF ou PA configurée
    // Stocke accusé de réception
}

// Cron WordPress
add_action('billigoo_monthly_ereporting', [$manager, 'send_monthly_report']);
wp_schedule_event(strtotime('first day of next month'), 'monthly', 'billigoo_monthly_ereporting');
```

**Données à transmettre par transaction B2C :**
- Date de transaction
- Montant HT par taux de TVA
- Montant TVA par taux
- Devise
- Type de transaction (vente, avoir)
- Mode de paiement

### 3.2 Export FEC (Fichier des Écritures Comptables)

**Format :** CSV avec séparateur `|`, encodage UTF-8 BOM  
**Colonnes obligatoires (Article A47 A-1 CGI) :**
```
JournalCode|JournalLib|EcritureNum|EcritureDate|CompteNum|CompteLib|
CompAuxNum|CompAuxLib|PieceRef|PieceDate|EcritureLib|Debit|Credit|
EcritureLet|DateLet|ValidDate|Montantdevise|Idevise
```

**Génération :**
- Chaque facture → 2 lignes minimum (compte client + compte TVA)
- Comptes PCG français par défaut (411000 clients, 445711 TVA collectée…)
- Paramétrable dans les settings (mapping compte personnalisable)

### 3.3 Génération rétroactive en lot (plan Agence)

**Fonctionnement :**
- Sélection de commandes dans la liste WooCommerce (checkbox)
- Action groupée "Générer Billigoo Factur-X"
- Traitement en arrière-plan via WP Queue (Action Scheduler, déjà inclus dans WC)
- Barre de progression dans l'admin (AJAX polling)
- Rapport final : X générées, X erreurs (avec détail)

**Limites :** Maximum 500 factures par lot pour éviter timeout

### 3.4 API REST WordPress (plan Agence)

**Namespace :** `billigoo/v1`

| Endpoint | Méthode | Description |
|----------|---------|-------------|
| `/invoices` | GET | Liste paginée des factures |
| `/invoices/{id}` | GET | Détail + statut PA |
| `/invoices/{id}/pdf` | GET | Téléchargement PDF (base64) |
| `/invoices/{id}/xml` | GET | Téléchargement XML Factur-X |
| `/invoices/{id}/transmit` | POST | Re-transmettre vers PA |
| `/invoices/generate` | POST | Générer pour une commande WC |
| `/stats` | GET | Statistiques période |

**Authentification :** Application Passwords WordPress (natif WP 5.6+)

### 3.5 Webhooks (plan Agence)

**Events déclencheurs :**
- `invoice.generated` — facture créée
- `invoice.sent` — transmise à la PA
- `invoice.accepted` — acceptée
- `invoice.rejected` — rejetée
- `ereporting.sent` — e-reporting transmis

**Format payload :**
```json
{
  "event": "invoice.accepted",
  "invoice_id": "FA-2026-0042",
  "order_id": 1234,
  "timestamp": "2026-09-15T10:30:00Z",
  "data": { "pa_reference": "PA-REF-XYZ", "amount": 1440.00 }
}
```

### 3.6 Multi-sites (plan Agence)

**Système de licence :**
- Clé de licence générée à l'achat (format UUID v4)
- Activation via appel à API Billigoo (serveur de licences)
- Limite : 5 domaines par licence Agence
- Désactivation possible depuis le dashboard client

**Serveur de licences (à développer séparément) :**
- Node.js + Supabase (dans ton stack existant)
- Table `licenses` : clé, plan, domaines_activés[], created_at, expires_at
- Endpoint `POST /validate` appelé à chaque activation de site

---

## Phase 4 — UX et Templates (semaines 11–12)

### 4.1 Wizard de configuration (5 étapes)

**Déclenché à l'activation du plugin ou depuis le menu.**

```
Étape 1 — Identité de votre entreprise
  - Raison sociale
  - SIREN (avec validation)
  - SIRET siège social
  - Numéro TVA intracommunautaire
  - Adresse complète

Étape 2 — Paramètres de facturation
  - Préfixe numéro de facture (ex: FA-)
  - Réinitialisation annuelle (oui/non)
  - Prochain numéro de départ
  - Statut commande déclencheur (processing / completed)
  - Profil Factur-X (BASIC par défaut, EN 16931 pour Pro)

Étape 3 — Plateforme Agréée (plan Pro)
  - Choix PA : Pennylane / Tiime / Chorus Pro / Autre
  - Saisie credentials API (clé API ou OAuth)
  - Test de connexion (bouton "Tester")
  - Mode B2G (Chorus Pro) activable séparément

Étape 4 — Template PDF
  - Choix template (Standard / Premium si add-on acheté)
  - Upload logo
  - Couleur principale (color picker)
  - Mentions légales personnalisées
  - Aperçu en temps réel (iframe)

Étape 5 — Récapitulatif et activation
  - Résumé de la configuration
  - Statut conformité (checklist verte)
  - Bouton "Terminer et activer"
  - Lien vers documentation
```

### 4.2 Templates PDF

**Template Standard (inclus) :**
- En-tête : logo + coordonnées vendeur
- Tableau lignes : description, qté, PU HT, TVA%, montant HT
- Pied de page : totaux (HT, TVA par taux, TTC), conditions paiement
- Mention "Facture électronique conforme Factur-X — Profil [BASIC/EN 16931]"
- Mention numéro TVA intracommunautaire
- QR Code (optionnel) : URL vérification si PA le fournit

**Template Premium (add-on 19 €) :**
- Layout moderne avec bandeau couleur personnalisable
- Disposition deux colonnes
- Espace signature numérique
- 3 variantes de mise en page (classique, moderne, minimaliste)

---

## Pages admin — Spécifications UI

### Dashboard principal (`WooCommerce > Billigoo`)

```
┌─────────────────────────────────────────────────────────┐
│  BILLIGOO                            [Pro] [Docs] [Help] │
├─────────────────────────────────────────────────────────┤
│  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌────────┐  │
│  │ 247      │  │ 31 420 € │  │ 98,3 %   │  │  4     │  │
│  │ Factures │  │ CA factu.│  │ Acceptées│  │ Rejet. │  │
│  │ ce mois  │  │ ce mois  │  │ par PA   │  │ ⚠️     │  │
│  └──────────┘  └──────────┘  └──────────┘  └────────┘  │
│                                                         │
│  Statut conformité                                      │
│  ✅ Génération Factur-X active                          │
│  ✅ Connexion PA Pennylane OK                           │
│  ⚠️  E-reporting : prochain envoi le 01/10/2026         │
│  ✅ Numérotation légale : FA-2026- (dernière: 0247)     │
│                                                         │
│  Activité récente                                       │
│  [Liste 10 dernières factures avec statuts]             │
│                                                         │
│  [Voir toutes les factures →]                           │
└─────────────────────────────────────────────────────────┘
```

### Liste des factures (`WooCommerce > Billigoo > Factures`)

- Table WordPress native (`WP_List_Table`)
- Colonnes : N°, Commande, Client, Montant, Date, Format, Statut PA, Actions
- Actions par ligne : Télécharger PDF, Télécharger XML, Re-transmettre, Voir détail
- Actions groupées : Exporter CSV, Exporter FEC, Générer lot, Re-transmettre lot
- Filtres en haut : période, statut, client
- Pagination : 25 par page par défaut

### Page paramètres (onglets)

```
Onglet 1 — Général
  Identité entreprise (SIREN, SIRET, TVA, adresse)
  Paramètres numérotation
  Statut déclencheur

Onglet 2 — Facturation
  Profil Factur-X
  Devise (EUR par défaut)
  Mentions légales
  Conditions de paiement par défaut
  Template PDF actif

Onglet 3 — Plateforme Agréée (Pro)
  Choix PA + credentials
  Bouton test connexion
  Mode B2G (Chorus Pro)
  Retry automatique (0/1/3 tentatives)

Onglet 4 — E-reporting (Pro)
  Activation e-reporting B2C
  Périodicité (mensuelle/trimestrielle)
  Historique des envois

Onglet 5 — Export
  Export CSV (filtre période)
  Export FEC (filtre période)
  Mapping comptes PCG (Agence)

Onglet 6 — API & Webhooks (Agence)
  Endpoints REST actifs
  Clés API (générer/révoquer)
  Configuration webhooks (URL + secret)
  Log des appels API

Onglet 7 — Licence
  Clé de licence active
  Plan actuel + date expiration
  Domaines activés (Agence)
  Bouton renouvellement
```

### Intégration page commande WooCommerce

Dans la page de détail d'une commande WC, ajouter une **meta box "Billigoo"** :

```
┌─────────────────────────────────┐
│  BILLIGOO                       │
│  Facture : FA-2026-0042         │
│  Statut PA : ✅ Acceptée        │
│  PA : Pennylane (réf: PL-1234)  │
│  Générée le : 15/09/2026 10:31  │
│                                 │
│  [📄 PDF] [📋 XML] [↑ Retransm]│
└─────────────────────────────────┘
```

### Intégration email client WooCommerce

- Attacher le PDF Factur-X à l'email "Commande traitée" (statut processing)
- Option dans settings : activer/désactiver l'envoi par email
- Mention dans le corps de l'email : "Votre facture électronique conforme est jointe"

---

## Checkout — Champs B2B

**Affichage dans le checkout WooCommerce :**

```
[ ] Je commande en tant que professionnel

▼ Si coché, afficher :

Raison sociale *           [________________________]
SIRET *                    [__ __ ____ ___ ___ __]
                           ✅ SIRET valide
Numéro TVA intracommunautaire  [FR__ __________]
                               (optionnel)
```

**Compatibilité :**
- Checkout classique (shortcode `[woocommerce_checkout]`)
- Checkout Blocks (via `IntegrationInterface` WooCommerce Blocks)
- Page Mon Compte : afficher les champs dans "Détails de facturation"

---

## Sécurité

- Tous les nonces WordPress sur les formulaires admin
- Vérification capacité `manage_woocommerce` sur toutes les pages admin
- Credentials PA chiffrés en base (AES-256 via `openssl_encrypt`)
- Fichiers PDF protégés (dossier hors webroot ou `.htaccess` deny)
- Accès PDF via endpoint WP avec vérification session client (espace client)
- Sanitization de toutes les entrées (`sanitize_text_field`, `absint`, etc.)
- Escape de toutes les sorties (`esc_html`, `esc_attr`, `wp_kses`)
- Rate limiting sur l'endpoint REST (100 req/min par IP)

---

## Tests

### Tests unitaires (PHPUnit)
- `InvoiceNumberTest` — numérotation séquentielle, concurrence, verrou
- `FacturXBuilderTest` — validation XML CII vs schéma XSD officiel
- `SiretValidatorTest` — algorithme Luhn, cas limites
- `PARouterTest` — mock des connecteurs PA

### Tests d'intégration
- Commande WC → génération facture → fichier créé
- Transmission PA mockée → statut mis à jour
- Export FEC → format conforme

### Validation Factur-X
- Utiliser le validateur officiel DGFiP (ou Kosit Validator)
- URL sandbox PA (Pennylane propose un environnement de test)
- Vérifier conformité PDF/A-3 (outil VeraPDF)

---

## Déploiement et distribution

### WordPress.org (plan Starter)
- Soumettre le plugin sur wordpress.org/plugins
- Version Starter 100% gratuite, sans clé de licence
- Le plan Pro/Agence se débride via clé activée depuis billigoo.fr

### Site billigoo.fr
- Page de vente avec tableau comparatif plans
- Paiement via Stripe (ou LemonSqueezy pour éviter la TVA EU)
- Envoi clé de licence par email post-achat
- Espace client : voir ses licences, domaines activés, renouvellement

### Mises à jour
- Héberger le `update-check` sur billigoo.fr (endpoint JSON)
- Plugin interroge l'endpoint pour les mises à jour (pattern WordPress standard)
- Mises à jour gratuites pendant 1 an incluses dans la licence

---

## Roadmap post-lancement

| Version | Fonctionnalité |
|---------|---------------|
| 1.1 | Connecteur Tiime PA |
| 1.2 | Connecteur Yooz PA |
| 1.3 | Support UBL 2.1 complet |
| 1.4 | Gestion des avoirs (credit notes) Factur-X |
| 1.5 | Mode multi-devises (EUR + GBP, USD pour clients export) |
| 2.0 | Dashboard analytics avancé + prévisions trésorerie |

---

## Notes de développement importantes

1. **Ne pas utiliser** `WC_Order->get_id()` comme base de numérotation — les IDs WC ont des gaps
2. **Toujours tester** avec `WC_Order` issu de HPOS (`wc_get_order()`) et non `get_post()`
3. **La lib `atgp/factur-x`** nécessite PHP 8.0+ et l'extension `ext-dom`
4. **Le PDF/A-3** requiert que toutes les polices soient embarquées dans le PDF
5. **Chorus Pro** n'est pas une PA au sens B2B privé — bien distinguer B2G et B2B dans le routeur
6. **L'e-reporting** B2C est distinct de la facturation électronique B2B — deux flux séparés
7. **Tester sur WooCommerce HPOS activé ET désactivé** pour la compatibilité maximale
