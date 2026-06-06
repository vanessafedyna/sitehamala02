# Architecture du projet

## Vue d'ensemble

Ce projet est une application e-commerce en PHP natif, sans framework.
Le rendu HTML, la logique métier, l'accès aux données et une partie des contrôles HTTP sont répartis dans des fichiers PHP organisés par zone fonctionnelle.

L'application repose principalement sur :

- des points d'entrée PHP publics ;
- un back-office admin séparé dans `admin/` ;
- une couche légère de bootstrap dans `app/` ;
- des endpoints JSON dans `public/api/` ;
- des includes partagés pour le layout et certains services historiques.

## Structure générale

- `app/` : noyau applicatif minimal, configuration, sécurité, modèles, services, helpers.
- `admin/` : back-office et modules de gestion.
- `pages/` : pages publiques du site e-commerce.
- `public/api/` : endpoints JSON appelés par le front et quelques actions de compte.
- `assets/` : CSS, JS, images statiques.
- `database/` : schéma SQL et patchs de migration.
- `includes/` : layout partagé et services historiques encore utilisés.
- `uploads/` : fichiers envoyés par les utilisateurs ou l’admin.
- `scripts/` : scripts utilitaires manuels pour setup, seed et maintenance.
- `cron/` : tâches planifiées.
- racine du projet : quelques points d’entrée majeurs et la configuration legacy.

## Rôle des dossiers principaux

### `app/`

Couche centrale du projet.

- `app/bootstrap.php` : bootstrap principal chargé par la majorité des entrées PHP.
- `app/config/` : chargement d’environnement et connexion base de données.
- `app/core/` : session, CSRF, auth, headers de sécurité.
- `app/models/` : accès aux tables principales.
- `app/services/` : services métier ou techniques.
- `app/helpers/` : fonctions de support pour pages publiques et utilitaires globaux.

### `admin/`

Back-office sans framework, structuré par écrans et sous-modules.

- authentification et garde d’accès à la racine du dossier ;
- sous-dossiers métier : `orders/`, `products/`, `customers/`, `reviews/`, `product_reviews/`, `exports/`, `partners/`, `revenue/`, `audit/` ;
- layout admin partagé via `_layout_header.php`, `_layout_footer.php`, `partials/`.

### `pages/`

Pages publiques du site :

- catalogue ;
- fiche produit ;
- panier et commande ;
- connexion / inscription / compte ;
- pages éditoriales et légales ;
- suivi de commande ;
- CMS et pages dynamiques.

### `public/api/`

Endpoints JSON pour le front public et certaines actions AJAX.

- `_common.php` centralise le bootstrap API ;
- endpoints de login, inscription, contact, suivi de commande, avis, newsletter, compte, etc. ;
- contient aussi `public/api/admin/` pour quelques actions admin ciblées.

### `assets/`

Ressources statiques front et admin.

- `assets/css/` : styles globaux et styles par page ;
- `assets/js/` : scripts globaux et scripts par page ;
- `assets/images/` : branding, placeholders, visuels.

### `database/`

Source SQL du projet.

- `schema.sql` : base de structure principale ;
- `patch_*.sql` : migrations incrémentales ;

### `includes/`

Composants et services historiques partagés.

- `header.php` et `footer.php` : layout public ;
- `_header_context.php` : contexte d’en-tête ;
- services transverses : `Logger.php`, `Settings.php`, `Mailer.php`, `NotificationService.php`.

### `uploads/`

Stockage des médias uploadés.

- `uploads/products/` : images produits ;
- `uploads/categories/` : images catégories.

Ces chemins sont sensibles car utilisés par le site et potentiellement par la base de données.

### `scripts/`

Scripts CLI de maintenance ou d’initialisation.

- création d’admin ou partenaire ;
- migration / nettoyage d’images ;
- exemples de sauvegarde.

### `cron/`

Tâches planifiées.

- `cron/worker_notifications.php` : worker de notifications.

## Points d’entrée importants du site public

### Racine

- `index.php` : page d’accueil publique.
- `page.php` : affichage des pages CMS par slug.
- `sitemap.php` : génération sitemap.
- `suivi_commande.php` : point d’accès historique vers le suivi.
- `config.php` : configuration legacy encore consommée par le rendu public.

### Pages publiques majeures dans `pages/`

- `pages/catalogue.php` : listing catalogue.
- `pages/produit.php` : fiche produit.
- `pages/panier.php` : panier.
- `pages/commande.php` : checkout.
- `pages/commande_submit.php` : traitement de soumission de commande.
- `pages/order_success.php` : confirmation commande.
- `pages/suivi.php` et `pages/suivi-commande.php` : suivi de commande.
- `pages/connexion.php`, `pages/inscription.php` : auth client.
- `pages/mon-compte.php`, `pages/mes-commandes.php`, `pages/modifier-profil.php`, `pages/changer-mot-de-passe.php`, `pages/supprimer-compte.php` : espace client.
- `pages/contact.php`, `pages/faq.php`, `pages/apropos.php` : contenu public.
- `pages/mentions-legales.php`, `pages/politique-confidentialite.php`, `pages/conditions-generales-vente.php`, `pages/livraison.php`, `pages/retours.php` : pages légales et commerciales.

### Endpoints publics JSON

- `public/api/login.php`, `public/api/logout.php`, `public/api/register.php`
- `public/api/contact_submit.php`
- `public/api/order_lookup.php`, `public/api/order_track.php`
- `public/api/reviews_create.php`, `public/api/product_reviews_create.php`
- `public/api/newsletter_subscribe.php`
- `public/api/my_orders.php`, `public/api/account_delete.php`

## Points d’entrée importants du back-office admin

- `admin/login.php` : authentification admin / partner.
- `admin/index.php` : dashboard principal.
- `admin/logout.php` : fermeture de session.
- `admin/dashboard.php` : autre entrée de dashboard selon usage historique.
- `admin/settings.php` : paramètres globaux.
- `admin/system_health.php` : état système.
- `admin/backups.php` : sauvegardes.
- `admin/pages.php`, `admin/page_edit.php` : gestion CMS.
- `admin/orders/index.php`, `admin/orders/show.php` : gestion commandes.
- `admin/products/index.php`, `create.php`, `edit.php`, `delete.php`, `publish.php`, `stock.php`, `stock_index.php` : catalogue et stock.
- `admin/categories.php` et actions associées : gestion catégories.
- `admin/coupons.php` et actions associées : gestion coupons.
- `admin/customers/index.php`, `admin/customers/show.php` : clients.
- `admin/reviews/index.php`, `admin/product_reviews/index.php` : modération avis.
- `admin/revenue/index.php` : reporting revenu.
- `admin/exports/` : exports produits, commandes, clients.
- `admin/audit/index.php` : audit et journalisation admin.
- `admin/partners/index.php` : espace partenaires.

## Fichiers de configuration importants

- `app/bootstrap.php` : bootstrap principal de l’application.
- `app/config/app.php` : charge l’environnement et le mode debug.
- `app/config/env.php` : parsing des variables d’environnement.
- `app/config/database.php` : singleton PDO et politique de connexion.
- `config.php` : configuration legacy encore utilisée par le front public.
- `.env` : configuration locale effective.
- `.env.example` et `.env.production.example` : exemples d’environnement.
- `.htaccess` : comportement Apache et réécritures éventuelles.
- `php.ini.production.example` : base de configuration PHP pour prod.

## Fichiers liés à la sécurité

### Session

- `app/core/session.php` : nom de session, cookies sécurisés, `SameSite`, `HttpOnly`, mode strict.

### CSRF

- `app/core/csrf.php` : génération et validation de jeton CSRF.
- `public/api/_common.php` : validation CSRF côté endpoints JSON via `api_require_csrf()`.

### Headers HTTP

- `app/core/security_headers.php` : headers globaux, CSP, HSTS, `X-Frame-Options`, `Referrer-Policy`, etc.

### Authentification

- `app/core/auth.php` : auth front, auth admin, login/logout, garde d’accès.
- `admin/_auth.php` : contrôle d’accès admin, rôles `owner` / `partner`, timeout de session, capacités.
- `admin/_guard.php` : wrapper de garde admin.
- `admin/login.php` : login admin avec contrôle CSRF et limitation des tentatives.

### Journalisation et garde-fous

- `includes/Logger.php` : log applicatif.
- `app/services/AdminAuditService.php` : audit admin.
- `public/api/_common.php` : rate limiting API avec fallback session.

## Modèles, services et helpers principaux

### Modèles

- `app/models/ProductModel.php` : logique d’accès catalogue / produits.
- `app/models/OrderModel.php` : logique d’accès commandes.
- `app/models/CustomerModel.php` : clients.
- `app/models/CategoryModel.php` : catégories.
- `app/models/CouponModel.php` : coupons.
- `app/models/CmsPageModel.php` : pages CMS.

### Services

- `app/services/ProductImageService.php` : gestion images produits.
- `app/services/CategoryImageService.php` : gestion images catégories.
- `app/services/SkuService.php` : génération / règles SKU.
- `app/services/PasswordResetOtpService.php` : OTP de réinitialisation.
- `app/services/CmsSanitizer.php` : sanitation du contenu CMS.
- `app/services/AdminAuditService.php` : audit back-office.
- `includes/NotificationService.php`, `includes/Mailer.php` : notifications et envois.
- `app/helpers/public_contact.php` : generation du contact WhatsApp public via lien `wa.me`.
- Paiement : paiement a la livraison uniquement (COD).

### Helpers

- `app/helpers/functions.php` : helpers globaux, URLs, JSON, logs, introspection DB, rate limit session.
- `app/helpers/home_page.php` : contexte page d’accueil.
- `app/helpers/product_page.php` : fiche produit.
- `app/helpers/contact_page.php` : page contact.
- `app/helpers/public_contact.php` : traitement contact public.
- `app/helpers/seo.php` : SEO.
- `app/helpers/checkout_submit.php` : support du flux checkout.
- `app/helpers/suivi_page.php` : suivi de commande.

## Zones à ne pas modifier sans précaution

- `app/bootstrap.php` : point de chargement global ; un changement impacte public, admin et API.
- `app/core/session.php`, `app/core/csrf.php`, `app/core/auth.php`, `app/core/security_headers.php` : impact direct sécurité et authentification.
- `app/config/database.php` : toute erreur coupe le site et l’admin.
- `config.php` : encore consommé par le front historique ; risque de casser les URLs et assets.
- `includes/header.php`, `includes/footer.php`, `includes/_header_context.php` : impact global sur le rendu public.
- `public/api/_common.php` : impact global sur tous les endpoints JSON.
- `admin/_auth.php` : impact global sur tout le back-office.
- `uploads/` et services d’images : attention aux chemins physiques, URLs stockées et permissions.
- `database/schema.sql` et `database/patch_*.sql` : toute modification doit être versionnée et cohérente avec le code PHP existant.

## Gros fichiers à refactoriser plus tard

Ces fichiers sont volumineux et mélangent souvent rendu, logique métier et requêtes SQL. Ils sont de bons candidats pour une refactorisation ultérieure, sans action dans cette étape.

- `admin/revenue/index.php`
- `app/models/OrderModel.php`
- `admin/orders/show.php`
- `admin/orders/index.php`
- `admin/products/index.php`
- `admin/products/edit.php`
- `admin/index.php`
- `admin/products/create.php`
- `app/models/ProductModel.php`
- `pages/produit.php`
- `pages/catalogue.php`

## Remarques de reprise

- Le projet mélange une couche `app/` plus structurée et des fichiers historiques dans `includes/` et à la racine.
- La base de données est interrogée directement via PDO, sans ORM.
- Le routage est basé sur les fichiers PHP eux-mêmes ; il n’y a pas de routeur applicatif central de framework.
- Avant toute évolution sensible, vérifier les dépendances croisées entre front public, `public/api/` et back-office admin.
