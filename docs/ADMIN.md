# Back-office admin

## Vue d’ensemble admin

Le back-office est développé en PHP natif, sans framework.
Le dossier `admin/` contient à la fois :

- les points d’entrée HTTP du back-office ;
- la garde d’accès et la logique de rôles ;
- des écrans de gestion métier ;
- un layout admin partagé ;
- des sous-modules spécialisés par domaine.

Architecture générale :

- `admin/_auth.php` : garde d’accès centrale, rôles, timeout de session, capacités.
- `admin/_guard.php` : wrapper simple qui charge `_auth.php`.
- `admin/_layout_header.php` / `admin/_layout_footer.php` : structure visuelle commune.
- `admin/_flash.php` : messages flash pour confirmations / erreurs.
- sous-dossiers métier : `orders/`, `products/`, `customers/`, `partners/`, `revenue/`, `audit/`, `exports/`, `reviews/`, `product_reviews/`.

Le back-office s’appuie sur :

- `app/config/database.php` pour PDO ;
- `app/models/*` pour les accès aux données ;
- `app/services/AdminAuditService.php` pour la traçabilité ;
- quelques services historiques dans `includes/`, notamment notifications.

## Logique générale du dossier `admin/`

- Chaque page admin est un fichier PHP autonome jouant le rôle de contrôleur + vue.
- La majorité des pages chargent `admin/_auth.php` dès le début.
- Les rôles et permissions sont appliqués page par page.
- Les formulaires sensibles vérifient le jeton CSRF.
- Les pages font souvent leurs requêtes SQL directement ou via un modèle.
- Le rendu HTML, la logique métier et parfois des requêtes SQL sont regroupés dans le même fichier.

## Pages principales admin

### `admin/index.php`

Dashboard principal.

- point d’entrée central du back-office ;
- adapte l’affichage selon le rôle `owner` ou `partner` ;
- expose des KPI, files prioritaires, commandes à traiter, stock faible, modération en attente ;
- s’appuie sur `AdminStats.php` et des requêtes directes.

### `admin/orders/`

Gestion opérationnelle des commandes.

- `admin/orders/index.php` : listing, filtres, KPI, recherche, période, actions de masse.
- `admin/orders/show.php` : détail d’une commande, historique, notes, notifications, changement de statut.

Flux couverts :

- consultation des commandes ;
- progression du statut ;
- ajout de notes ;
- consultation des notifications liées ;
- audit des changements.

### `admin/products/`

Gestion catalogue et inventaire.

- `admin/products/index.php` : listing produits, recherche, filtres, édition rapide de prix/stock, activation, publication des produits en attente.
- `admin/products/create.php` : création produit.
- `admin/products/edit.php` : édition produit.
- `admin/products/delete.php` : suppression produit.
- `admin/products/publish.php` : publication owner-only.
- `admin/products/stock.php` : ajustement manuel du stock avec journalisation.
- `admin/products/stock_index.php` : vue dédiée au stock.

### `admin/customers/`

Gestion des clients.

- `admin/customers/index.php` : liste clients, recherche, filtres.
- `admin/customers/show.php` : détail d’un client.

Module réservé au `owner`.

### `admin/partners/`

Gestion des comptes partenaires.

- `admin/partners/index.php` : création de partenaire, réinitialisation de mot de passe, activation/désactivation, listing.

Ce module manipule directement les utilisateurs avec rôle `partner`.

### `admin/revenue/`

Suivi ventes et chiffre d’affaires réel.

- `admin/revenue/index.php` : KPIs de CA réel, panier moyen, taux de livraison / annulation, période analysée, tendances mensuelles, top produits, liste des commandes sur la période.

Module réservé au `owner`.

### `admin/audit/`

Journal d’activité admin.

- `admin/audit/index.php` : affichage des logs d’audit, filtres par action, recherche, lecture de métadonnées.

Module réservé au `owner`.

### `admin/settings.php`

Paramètres globaux du site.

Permet notamment de gérer :

- nom boutique ;
- emails de contact et notification ;
- notifications owner ;
- WhatsApp public via `shop_whatsapp_number` et lien `wa.me` ;
- livraison gratuite ;
- taux de taxe ;
- maintenance.

Le stockage repose sur la table `settings` via `includes/Settings.php`.
Le projet utilise uniquement WhatsApp simple via lien `wa.me`.

## Autres modules trouvés

- `admin/pages.php`, `admin/page_edit.php` : gestion CMS et pages éditoriales.
- `admin/reviews/index.php` : modération avis site.
- `admin/product_reviews/index.php` : modération avis produits.
- `admin/categories.php` et actions `category_add/edit/delete/toggle.php` : gestion catégories.
- `admin/coupons.php` et actions `coupon_add/edit/delete.php` : gestion coupons.
- `admin/shipping_zones.php` : gestion des zones/frais de livraison.
- `admin/system_health.php` : état système.
- `admin/login.php`, `admin/logout.php`, `admin/403.php` : auth et erreurs d’accès.
- `admin/dashboard.php` : ancienne entrée owner-only.
- `admin/exports/` : actuellement redirection vers `admin/index.php`, pas un module autonome exploitable dans l’état actuel.
- `admin/backups.php` : redirection vers `admin/index.php`, pas de gestion de sauvegarde active dans ce fichier.

## Rôles et permissions

## Rôles

### `owner`

Rôle admin complet.

- provient du rôle DB `admin` mappé vers `owner` dans `admin/_auth.php` ;
- dispose d’un wildcard `*` dans `admin_role_capabilities()` ;
- accède à tous les modules visibles dans le code.

### `partner`

Rôle opérationnel restreint.

- provient du rôle DB `partner` ;
- accès limité aux fonctionnalités terrain / opérationnelles ;
- pas d’accès aux modules owner-only.

## Permissions visibles dans le code

Capacités explicitement déclarées pour `partner` dans `admin/_auth.php` :

- `orders.view`
- `orders.update_status`
- `orders.note`
- `products.create`
- `products.stock.adjust`
- `products.view`

Capacités appelées dans le code pour certains écrans sensibles :

- `settings.manage`
- `users.manage`
- `exports.view`
- `orders.notifications`
- `orders.cancel`

En pratique, comme `owner` possède `*`, ces capacités lui sont ouvertes même si elles ne sont pas listées individuellement.

## Pages sensibles réservées

Owner-only dans le code :

- `admin/revenue/index.php`
- `admin/audit/index.php`
- `admin/customers/index.php`
- `admin/customers/show.php`
- `admin/reviews/index.php`
- `admin/product_reviews/index.php`
- `admin/pages.php`
- `admin/page_edit.php`
- `admin/categories.php` et actions associées
- `admin/coupons.php` et actions associées
- `admin/products/edit.php`
- `admin/products/delete.php`
- `admin/products/publish.php`
- `admin/shipping_zones.php`
- `admin/dashboard.php`

Owner ou partner :

- `admin/index.php`
- `admin/orders/index.php`
- `admin/orders/show.php`
- `admin/products/stock.php`
- `admin/products/stock_index.php`

À noter :

- `admin/products/index.php` est accessible aux deux rôles, mais plusieurs actions POST ne s’exécutent que si l’utilisateur est `owner`.
- `admin/settings.php` et `admin/partners/index.php` utilisent des capacités, mais en pratique restent réservés au `owner`.

## Flux métiers

## Gestion commandes

Le flux principal visible dans le code est :

- `nouveau`
- `confirme`
- `en_preparation`
- `en_livraison`
- `livre`

Cas particulier :

- `annulee` est gérée comme statut terminal.

Le code normalise aussi plusieurs alias legacy ou anglais :

- `pending` vers `nouveau`
- `confirmed` vers `confirme`
- `processing` / `prepared` vers `en_preparation`
- `shipped` / `delivering` vers `en_livraison`
- `delivered` vers `livre`
- `cancelled` vers `annulee`

Le détail de commande permet :

- visualisation des lignes de commande ;
- historique de statuts ;
- notes internes si la table existe ;
- état des jobs de notification si la table existe ;
- audit des changements.

## Gestion produits

Le module produits couvre :

- création ;
- édition ;
- listing admin ;
- filtres par statut ;
- mise à jour rapide du prix ;
- mise à jour rapide du stock ;
- activation / passage brouillon ;
- publication de produits `pending` vers `published`.

Le code supporte plusieurs colonnes optionnelles selon le schéma :

- `status`
- `is_featured`
- `featured_rank`
- `is_active`
- `low_stock_threshold`

## Gestion stock

Le stock est manipulé via :

- `admin/products/index.php` pour des mises à jour rapides owner-only ;
- `admin/products/stock.php` pour les ajustements détaillés.

Sur `admin/products/stock.php`, le flux inclut :

- calcul ancien stock / nouveau stock ;
- mise à jour transactionnelle ;
- insertion dans `stock_movements` si la table existe ;
- audit `inventory_adjusted`.

Raisons d’ajustement vues dans le code :

- `manual_adjust`
- `restock`
- `correction`

## Suivi ventes / chiffre d’affaires

Le module `admin/revenue/index.php` calcule notamment :

- CA du jour ;
- CA de la semaine ;
- CA du mois ;
- CA de l’année ;
- CA sur période sélectionnée ;
- nombre de commandes livrées ;
- nombre de commandes annulées ;
- taux de livraison ;
- taux d’annulation ;
- panier moyen ;
- tendances mensuelles ;
- top produits.

Le CA réel est basé uniquement sur les commandes livrées (`livre`, `livree`).
Les commandes non livrées ne doivent jamais être affichées comme chiffre d’affaires dans l’admin.
Les requêtes utilisent directement `orders` et `order_items`.

## Journal d’audit

Le journal d’audit repose sur `admin_audit_logs` et `AdminAuditService`.

Actions visibles dans le code ou mappées dans l’interface :

- changement de statut commande ;
- ajout de note commande ;
- changement de statuts en lot ;
- ajustement de stock ;
- création / modification produit ;
- changement de statut produit ;
- création partenaire ;
- réinitialisation mot de passe partenaire ;
- activation / désactivation partenaire ;
- modification paramètres ;
- gestion zones de livraison.

## Fichiers à refactoriser plus tard

Les fichiers admin les plus lourds repérés dans le projet :

- `admin/revenue/index.php`
- `admin/orders/show.php`
- `admin/orders/index.php`
- `admin/products/index.php`
- `admin/products/edit.php`
- `admin/index.php`
- `admin/products/create.php`
- `admin/product_reviews/index.php`
- `admin/audit/index.php`
- `admin/reviews/index.php`
- `admin/customers/show.php`

Raison principale :

- gros mélange de rendu HTML, logique métier, permissions, SQL et traitements POST dans le même fichier.

## Zones sensibles

## Permissions

- Toute modification de `admin/_auth.php` impacte l’ensemble du back-office.
- Le mapping DB `admin` -> `owner` et `partner` -> `partner` est central.
- Certaines pages s’appuient sur `requireRole()`, d’autres sur `requireAdminCapability()`.
- Il faut vérifier chaque écran avant d’élargir les droits d’un partenaire.

## Statuts commandes

- Les statuts sont utilisés par le dashboard, la liste commandes, le détail commande, le module revenue et l’audit.
- Le code contient une normalisation d’alias legacy ; toute modification doit préserver cette compatibilité.
- Les transitions autorisées sont contrôlées implicitement dans les écrans admin et dans le modèle.

## Stock

- Le stock est un point critique car il touche l’inventaire, la disponibilité produit et les journaux de mouvements.
- `admin/products/stock.php` met à jour la quantité et peut alimenter `stock_movements`.
- Une mauvaise modification peut casser les alertes de stock faible et la traçabilité.

## Paiements

- Le dashboard et les commandes surveillent `payment_status`, notamment `pending`.
- Paiement : paiement a la livraison uniquement (COD).
- Même si le module paiement n’est pas centralisé dans un seul fichier admin, plusieurs vues supposent l’existence et la cohérence de ces colonnes.
- Toute évolution doit être alignée avec `orders`, le checkout public et les écrans admin de suivi.

## Revenue queries

- `admin/revenue/index.php` contient des requêtes d’agrégation importantes et sensibles aux statuts métier.
- Le calcul du CA dépend de la définition d’une commande “livrée”.
- Les colonnes legacy de `order_items` sont prises en charge dynamiquement ; un changement de schéma peut fausser les résultats.

## Audit logs

- L’audit est utile pour l’exploitation et le support.
- Les logs dépendent de `AdminAuditService` et de la table `admin_audit_logs`.
- Beaucoup d’actions admin importantes supposent que l’audit reste disponible, même si certaines pages tolèrent l’absence de table par compatibilité.

## Remarques de passation

- Le back-office fonctionne comme une application PHP par écrans, pas comme un module MVC structuré.
- Les garde-fous existent, mais plusieurs pages restent fortement couplées au schéma SQL réel.
- Avant toute évolution sur les modules commandes, stock, revenu ou partenaires, vérifier à la fois :
  - les rôles dans `admin/_auth.php`
  - les tables SQL attendues
  - les appels à `AdminAuditService`
  - les dépendances avec les notifications
