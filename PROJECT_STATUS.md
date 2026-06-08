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
- [ ] Tester sur une **vraie install WP + WooCommerce**, **HPOS activé ET désactivé**
- [ ] Valider le Factur-X avec le **validateur officiel DGFiP / Kosit**
- [ ] Vérifier la conformité **PDF/A-3 via VeraPDF**
- [ ] Tests d'intégration : commande WC → génération → fichier ; transmission PA mockée ; export FEC

### Branchements services externes
- [ ] **Sandbox Pennylane** : credentials réels, valider import + statuts
- [ ] **Chorus Pro / PISTE** : compte technique, valider dépôt + compte-rendu
- [ ] **E-reporting** réel : aujourd'hui = JSON stocké + filtre `billigoo_ereporting_transmit` ;
      brancher PPF/PA quand l'endpoint existe
- [ ] Détection **B2G fiable** (annuaire SIRENE) — actuellement filtre/flag manuel `billigoo_is_public_buyer`
- [ ] (Optionnel Pro) Vérification existence SIRET via **API INSEE Sirene**

### Licence / distribution (projets séparés — hors plugin)
- [ ] Serveur de licences **Node/Supabase** + endpoint `POST /validate`
- [ ] Site **billigoo.fr** (vente, Stripe/LemonSqueezy, espace client)
- [ ] Endpoint **update-check** (mises à jour auto du plugin)

### Finitions plugin
- [ ] **i18n** : générer `languages/billigoo-fr_FR.po` + `.mo`
- [ ] **Script de build release** `bin/build-release.sh` (+ `.distignore`) :
      `composer install --no-dev -o` puis zip **avec vendor/** (WP ne lance pas composer !)
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

### 2026-06-08
- Implémentation complète Phases 2-4 + UI Stitch ; tous tests verts.
- Dépôt initialisé et poussé sur `github.com/ABWebsit3/billigoo` (authorship = ABWebsit3).
- Création de ce fichier de suivi.

<!-- Modèle d'entrée :
### AAAA-MM-JJ
- …ce qui a été fait…
- …décisions / points ouverts…
-->
