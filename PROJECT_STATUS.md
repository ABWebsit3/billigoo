# Billigoo — Suivi du projet

> Fichier de suivi **vivant** : à mettre à jour à chaque session (cocher les cases,
> compléter le journal en bas). Objectif : pouvoir reprendre le travail sur
> n'importe quelle machine sans rien perdre.
>
> Dernière mise à jour : **2026-06-08**

---

## 1. Vue d'ensemble

Plugin WooCommerce de facturation électronique **Factur-X** (PDF/A-3 + CII XML,
conformité réforme française 2026-2027). Spec complète : `BILLIGOO_PLAN.md`.

**État global : les 4 phases du plan sont implémentées + l'UI design (Stitch).**
Tout est linté, l'autoloader est régénéré, les tests passent. Ce qui reste est
surtout : tests sur une vraie install, branchements aux services externes
(sandbox PA, serveur de licence), et finitions (i18n, build de release).

---

## 2. Reprendre sur un autre PC (à faire en premier)

```bash
git clone git@github.com:ABWebsit3/billigoo.git
cd billigoo
composer install            # ⚠️ INDISPENSABLE : vendor/ est gitignoré
```

- **PHP** utilisé en dev local : `/Applications/XAMPP/xamppfiles/bin/php` (adapter selon la machine).
- **Auth Git push** : ce dépôt utilise une *deploy key* SSH dédiée sur la machine d'origine
  (`~/.ssh/billigoo_deploy_ed25519`, référencée via `git config core.sshCommand`).
  Sur un nouveau PC il faut **recréer ce moyen d'accès** : soit une nouvelle deploy key
  (Settings → Deploy keys, *Allow write access*), soit un Personal Access Token en HTTPS.
- **Identité commits** : `git config user.name "ABWebsit3"` /
  `git config user.email "ABWebsit3@users.noreply.github.com"`.

### Lancer les vérifications
```bash
PHP=/Applications/XAMPP/xamppfiles/bin/php
$PHP vendor/bin/phpunit                       # tests unitaires (sans WP)
$PHP tests/crypto-check.php                    # round-trip chiffrement
$PHP tests/fec-check.php                        # écritures FEC équilibrées
$PHP tests/facturx-xsd-check.php                # XML CII valide XSD
$PHP tests/facturx-pipeline-check.php           # pipeline PDF+XML complet
```

### Changer de plan (test) — voir aussi §6
mu-plugin `wp-content/mu-plugins/billigoo-dev-plan.php` :
```php
<?php add_filter( 'billigoo_license_plan', fn() => 'starter' ); // starter|pro|agence
```

---

## 3. Fait ✅

### Phase 1 — Core MVP
- [x] Champs B2B au checkout (SIRET/TVA/raison sociale, validation Luhn AJAX) — classique + Blocks
- [x] Numérotation séquentielle légale (verrou `GET_LOCK`, préfixe, reset annuel)
- [x] Génération Factur-X **BASIC** (mPDF PDF/A + atgp/factur-x, XSD validé)
- [x] Déclenchement auto sur statut commande + idempotence
- [x] Stockage protégé (`uploads/billigoo`, .htaccess deny) + endpoints de téléchargement sécurisés
- [x] Meta box commande + pièce jointe email

### UI design (Stitch) — WP-native
- [x] Menu top-level Billigoo (Dashboard / Factures / Réglages + wizard caché)
- [x] CSS design-system `public/assets/css/admin.css` + JS slide-over
- [x] Dashboard (métriques réelles, conformité, activité récente)
- [x] Liste factures (carte, filtre statut, slide-over détail)
- [x] Checkout B2B stylé + notice email brandée "Votre facture est prête"

### Phase 2 — Transmission PA
- [x] `includes/Pa/` : interface + AbstractConnector (HTTP, normalisation statuts)
- [x] Connecteurs : Pennylane, Chorus Pro (PISTE OAuth2, B2G), Manual
- [x] `PaRouter` (routage B2B/B2G) + `TransmissionManager` (auto + manuel + cron polling)
- [x] Statuts PA sur la commande + `StatusBadge` partout (liste, slide-over, dashboard, meta box)

### Phase 3 — Avancé
- [x] Export **FEC** (pipe/BOM, écritures équilibrées) + **CSV** + endpoint sécurisé
- [x] Génération **en lot** (action groupée WooCommerce + Action Scheduler, fallback synchrone)
- [x] **E-reporting** B2C (agrégation par taux, cron mensuel/trimestriel, historique)
- [x] **API REST** `billigoo/v1` (capability + rate-limit, Application Passwords)
- [x] **Webhooks** signés HMAC via dispatcheur `Events` + journal

### Phase 4 — UX & templates
- [x] Template PDF **Premium** + résolution template/logo/couleur dans PdfBuilder
- [x] **Wizard 5 étapes** (identité → facturation → PA + test → template → récap)
- [x] Réglages en **7 onglets** + onglet **Licence**

### Sécurité & cycle de vie
- [x] `Crypto` AES-256 + secrets chiffrés (`enc:`) + `Settings::sanitize()` tab-aware
- [x] `License` (plans Starter/Pro/Agence, déverrouillé par défaut + filtre)
- [x] Crons programmés/nettoyés (Activator/Deactivator), `uninstall.php`
- [x] Tests : PHPUnit (Crypto, FEC, statuts PA, SIRET) + scripts standalone — tous verts

---

## 4. Reste à faire 🔧

### Validation & tests réels (priorité haute)
- [x] Tester sur une **vraie install WP + WooCommerce**, **HPOS activé ET désactivé** — ✅ 2026-06-08
- [x] Valider le Factur-X : XSD BASIC 1.08 + règles EN 16931 BR-* — ✅ 2026-06-08
- [x] Vérifier la conformité **PDF/A-3b** (pdfaid:part=3, XML embarqué, polices, XMP schema) — ✅ 2026-06-08
- [ ] Tests d'intégration : transmission PA mockée ; export FEC

### Branchements services externes
- [ ] **Sandbox Pennylane** : credentials réels, valider import + statuts
- [~] **Chorus Pro / PISTE** : sandbox partiellement testé — token OK, `deposer/flux` accessible (400),
      mais l'hébergeur (agence-abweb.fr) bloque les requêtes sortantes vers `*.piste.gouv.fr` (403 WAF).
      Credentials sandbox valides : client_id `841b0807-...`, env sandbox.
      **Action** : demander à l'hébergeur d'autoriser les sorties vers `sandbox-oauth.piste.gouv.fr` et `sandbox-api.piste.gouv.fr` (port 443).
- [ ] **E-reporting** réel : aujourd'hui = JSON stocké + filtre `billigoo_ereporting_transmit` ;
      brancher PPF/PA quand l'endpoint existe
- [ ] Détection **B2G fiable** (annuaire SIRENE) — actuellement filtre/flag manuel `billigoo_is_public_buyer`
- [ ] (Optionnel Pro) Vérification existence SIRET via **API INSEE Sirene**

### Licence / distribution (projets séparés — hors plugin)
- [ ] Serveur de licences **Node/Supabase** + endpoint `POST /validate`
- [ ] Site **billigoo.fr** (vente, Stripe/LemonSqueezy, espace client)
- [ ] Endpoint **update-check** (mises à jour auto du plugin)

### Finitions plugin
- [x] **i18n** : `languages/billigoo.pot` (279 strings) + `billigoo-fr_FR.po` + `.mo` — 2026-06-08
- [x] **Script de build release** `bin/build-release.ps1` — produit `billigoo-{version}.zip` (7.2 MB) en 1 commande — 2026-06-08
- [ ] Connecteurs PA roadmap : **Tiime, Yooz, Qonto, Sage** (actuellement fallback Manual)
- [ ] readme.txt : compléter le changelog + screenshots pour wordpress.org

### Roadmap fonctionnelle (post-lancement)
- [ ] Support **UBL 2.1** complet (v1.3)
- [ ] Gestion des **avoirs / credit notes** Factur-X (v1.4)
- [ ] **Multi-devises** (v1.5)
- [ ] Dashboard analytics avancé / prévisions trésorerie (v2.0)

---

## 5. Limites connues (non testables dans l'env. de dev actuel)
- Aucune install WP+WooCommerce locale → la logique WP/WC n'est validée que par lint + tests unitaires sans WP.
- Appels réseau PA / PISTE / e-reporting / licence : code écrit selon les API documentées, **non exécuté** (pas de sandbox/credentials).

---

## 6. Repères techniques utiles
- Hook pivot : `billigoo_invoice_generated($order,$result)` (transmission PA + events).
- Events → webhooks : tout passe par l'action `billigoo_event`.
- Secrets : stockés chiffrés ; lecture via `Settings::secret($key)`.
- Réglages tab-aware : champ caché `_billigoo_tab` ; un champ secret vide = valeur conservée.
- FEC testable sans WC : `FecExporter::rows_for_data($data, $opts)` (fonction pure).
- Plans & features : `includes/License.php` (`has_feature()`), filtre `billigoo_license_plan`.

---

## 7. Journal des sessions (ajouter en haut)

### 2026-06-09 (session 3)
- Diagnostic et debug complet Chorus Pro / PISTE sandbox.
- Token PISTE obtenu (body auth, scope=openid) avec client `841b0807-...`.
- Endpoint `deposer/flux` autorisé (400 = payload test incorrect, pas 403).
- Bloquant hébergeur : requêtes sortantes vers `piste.gouv.fr` bloquées par WAF → à débloquer côté hébergeur.
- Fix : `manage_woocommerce` → `manage_options` sur toutes les pages admin (compatibilité WP sans WC bien initialisé).
- Fix : `AbstractConnector` — `sslverify=false` pour domaines `*.gouv.fr` (IGC/A non dans bundle Mozilla).
- Fix : `ChorusProConnector` — revert Basic Auth → body auth (méthode correcte confirmée par debug).
- Outils debug ajoutés : `tests/debug-piste.php`, `tests/mu-debug-piste.php`, `tests/mu-plugin-pa-mock.php`.

### 2026-06-08 (session 2)
- Tests sur install WP réelle (billigoo.agence-abweb.fr) — toutes validations passées.
- Fix : taux TVA lu depuis WC (`get_rate_percent`) au lieu de back-calculé → 20.00% exact.
- Fix : wizard activation redirect (transient consommé avant vérification AJAX/réseau).
- Scripts de test ajoutés : `tests/validate-facturx.php`, `tests/check-pdfa3.php`, `tests/extract-xml.php`.
- Vendor/ nettoyé (172MB .git parasites + fonts inutiles → 20MB).

### 2026-06-08 (session 1)
- Implémentation complète Phases 2-4 + UI Stitch ; tous tests verts.
- Dépôt initialisé et poussé sur `github.com/ABWebsit3/billigoo` (authorship = ABWebsit3).
- Création de ce fichier de suivi.

<!-- Modèle d'entrée :
### AAAA-MM-JJ
- …ce qui a été fait…
- …décisions / points ouverts…
-->
