# Structure cible progressive

## Objectif

Ce document décrit une évolution progressive de l'arborescence du projet pour la rendre plus lisible et plus proche d'une séparation claire entre logique applicative, interfaces d'administration, points d'entrée publics, base de données et scripts techniques.

L'objectif n'est pas de casser l'existant, mais de préparer une migration compatible, incrémentale et réaliste pour un projet PHP natif e-commerce.

## Structure actuelle par grands blocs

### `app/`

Bloc applicatif principal. Le dossier contient déjà une base cohérente avec :

- `config/`
- `core/`
- `helpers/`
- `logs/`
- `models/`
- `services/`

Il sert de socle pour centraliser la configuration, les abstractions métier et les services réutilisables.

### `admin/`

Bloc d'administration mixte, avec coexistence de fichiers racine et de sous-dossiers métier.

Sous-dossiers observés :

- `audit/`
- `customers/`
- `exports/`
- `orders/`
- `partials/`
- `partners/`
- `products/`
- `product_reviews/`
- `revenue/`
- `reviews/`

Fichiers racine encore présents et probablement encore utilisés comme points d'entrée admin :

- `dashboard.php`
- `categories.php`
- `coupons.php`
- `settings.php`
- `pages.php`
- `shipping_zones.php`
- `system_health.php`
- `login.php`
- `logout.php`
- `index.php`
- plusieurs actions directes de type `category_*` et `coupon_*`

Ce bloc est déjà partiellement modularisé, mais pas encore homogène.

### `pages/`

Bloc des pages publiques côté storefront, avec fichiers PHP directement accessibles ou inclus via les routes publiques.

On y trouve notamment :

- catalogue et produit : `catalogue.php`, `produit.php`, `recherche.php`
- tunnel client : `panier.php`, `commande.php`, `commande_submit.php`, `order_success.php`
- compte client : `connexion.php`, `inscription.php`, `mon-compte.php`, `mes-commandes.php`
- contenus institutionnels : `mentions-legales.php`, `politique-confidentialite.php`, `conditions-generales-vente.php`
- suivi et paiement : `suivi-commande.php`, `payment_return.php`

### `public/api/`

Bloc des endpoints publics AJAX/API.

Fichiers observés :

- authentification et compte : `login.php`, `logout.php`, `register.php`, `account_delete.php`
- commandes : `my_orders.php`, `order_lookup.php`, `order_track.php`
- contenus interactifs : `contact_submit.php`, `newsletter_subscribe.php`
- avis : `product_reviews_create.php`, `reviews_create.php`
- utilitaire : `_common.php`

Sous-bloc présent :

- `public/api/admin/`

### `assets/`

Bloc statique frontend avec séparation classique :

- `css/`
- `images/`
- `js/`

La structure est simple et fonctionnelle, mais pourra évoluer vers une organisation plus page-level ou module-level sans casser les URLs existantes au départ.

### `database/`

Bloc base de données déjà bien identifié :

- `schema.sql`
- plusieurs patchs incrémentaux `patch_*.sql`

Ce dossier correspond déjà bien à la cible "database".

### `docs/`

Bloc documentation projet.

Documents observés :

- `README.md`
- `ARCHITECTURE.md`
- `ADMIN.md`
- `REFACTOR_PLAN.md`
- `FILES_TO_REVIEW.md`

Ce dossier est le bon endroit pour piloter la migration structurelle avant tout déplacement réel.

### `cron/`

Bloc des traitements planifiés.

Fichier observé :

- `worker_notifications.php`

### `scripts/`

Bloc des scripts techniques et d'outillage.

Fichiers observés :

- création / bootstrap : `create-admin.php`, `create-partner.php`
- maintenance / migration : `cleanup-product-images.php`, `migrate-products-multi-images.php`
- backup : `backup_database_example.sh`, `backup_uploads_example.sh`

### `uploads/`

Bloc des fichiers utilisateurs ou médias dynamiques.

Ce dossier doit être considéré comme sensible car il dépend du stockage réel, des URLs publiques, des permissions filesystem et parfois de références en base.

### `includes/`

Bloc de composants mutualisés et services historiques accessibles globalement.

Fichiers observés :

- layout : `header.php`, `footer.php`
- contexte : `_header_context.php`
- maintenance : `maintenance.php`
- services : `Mailer.php`, `NotificationService.php`
- utilitaires / config métier : `Logger.php`, `Settings.php`

### `lib/`

Bloc de bibliothèques métiers ciblées.

Fichier observé :

- `NotificationQueue.php`

### Fichiers racine

La racine contient encore plusieurs points d'entrée et fichiers structurants :

- bootstrap / routing : `index.php`, `page.php`, `config.php`
- SEO / exposition publique : `robots.txt`, `sitemap.php`
- fonctionnalités publiques directes : `suivi_commande.php`
- base de données historique : `database/malishop.sql`
- configuration d'environnement : `.env.example`, `.env.production.example`
- serveur / déploiement : `.htaccess`, `php.ini.production.example`
- documentation : `README.md`

La racine est donc encore active et ne doit pas être nettoyée trop tôt.

## Structure cible progressive

Structure cible réaliste proposée pour ce projet PHP natif :

```text
sitehamala/
├── app/
│   ├── config/
│   ├── core/
│   ├── helpers/
│   ├── models/
│   └── services/
├── admin/
│   ├── dashboard/
│   ├── orders/
│   ├── products/
│   ├── customers/
│   ├── partners/
│   ├── settings/
│   ├── coupons/
│   ├── categories/
│   └── audit/
├── pages/
├── public/
│   └── api/
├── assets/
├── database/
├── docs/
├── scripts/
├── cron/
└── uploads/
```

## Lecture de la cible

### `app/`

Ce dossier devient le centre de gravité du code réutilisable :

- `config/` : configuration applicative et mapping d'environnement
- `core/` : bootstrap, base classes, sécurité, utilitaires transverses
- `helpers/` : fonctions d'assistance non métier
- `models/` : accès aux données et objets métier
- `services/` : orchestration métier et notifications

Le sous-dossier `logs/` actuellement présent peut rester temporairement en place tant qu'aucune stratégie claire de stockage/runtime n'est arrêtée.

### `admin/`

Ce dossier évolue vers une organisation fonctionnelle par module métier, sans supprimer les anciennes entrées immédiatement :

- `dashboard/` : vue d'ensemble, statistiques, synthèses
- `orders/` : gestion des commandes
- `products/` : catalogue, stock, featured, workflow produit
- `customers/` : comptes clients
- `partners/` : partenaires / vendeurs / affiliés selon le métier réel
- `settings/` : réglages fonctionnels et techniques
- `coupons/` : promotions et coupons
- `categories/` : taxonomie catalogue
- `audit/` : logs, revue, contrôle, santé système

Des sous-blocs existants comme `reviews/`, `product_reviews/`, `exports/` ou `revenue/` peuvent rester temporairement en l'état puis être rattachés plus tard à un module cible clair.

### `pages/`

Ce dossier reste le bloc des pages publiques. À court terme, il n'a pas besoin d'être transformé en framework ou router complexe. L'amélioration attendue est surtout documentaire, puis éventuellement une meilleure séparation entre :

- pages vitrines / CMS
- pages catalogue
- pages compte client
- pages checkout

### `public/api/`

Ce dossier reste dédié aux endpoints appelés côté public ou en AJAX.

La cible n'est pas de multiplier les couches, mais de mieux distinguer :

- endpoints storefront
- endpoints compte client
- endpoints checkout
- endpoints d'integration si un besoin futur apparait
- endpoints admin exposés publiquement si nécessaire

### `assets/`

Ce dossier reste l'emplacement public des ressources front. L'évolution souhaitable est une structuration progressive par contexte ou par page tout en gardant la compatibilité des URLs.

### `database/`

Ce dossier reste la référence unique pour :

- schéma de base
- patchs SQL
- seeds
- éventuellement documentation de migration plus tard

### `docs/`

Ce dossier pilote la transformation et doit contenir les conventions avant les mouvements réels.

### `scripts/`

Ce dossier centralise les scripts d'outillage, d'initialisation, de migration et de maintenance.

### `cron/`

Ce dossier reste dédié aux workers et traitements planifiés.

### `uploads/`

Ce dossier reste séparé et stable car il porte des contraintes runtime et opérationnelles différentes du code source.

## Plan futur de modularisation admin

L'objectif n'est pas de refondre immédiatement `admin/`, mais de préparer une uniformisation progressive à partir des modules déjà bien structurés.

### Modules déjà structurés

Les sous-dossiers suivants constituent déjà une base cohérente et peuvent servir de référence de nommage et d'organisation :

- `orders/`
- `products/`
- `customers/`
- `partners/`
- `revenue/`
- `audit/`

Ces modules sont déjà organisés par responsabilité métier et ne doivent pas être "replats".

### Candidats futurs à modulariser

Les groupes suivants sont de bons candidats pour une future uniformisation en sous-dossiers dédiés :

- `categories`
- `coupons`
- `cms`
- `settings`
- `shipping_zones`

Structure cible théorique :

```text
admin/
  categories/
  coupons/
  settings/
  cms/
```

### Détail par groupe

#### `categories`

Ce groupe pourrait devenir un module dédié avec un futur regroupement logique de :

- `categories.php`
- `category_add.php`
- `category_edit.php`
- `category_delete.php`
- `category_toggle.php`

Ce groupe est un bon candidat à modulariser plus tard.

Niveau de risque :

- moyen

Ce groupe aurait besoin d'un wrapper legacy si déplacé, car les URLs historiques et plusieurs liens internes pointent encore vers les fichiers plats actuels.

#### `coupons`

Ce groupe pourrait devenir un module dédié avec un futur regroupement logique de :

- `coupons.php`
- `coupon_add.php`
- `coupon_edit.php`
- `coupon_delete.php`

Ce groupe est lui aussi un bon candidat à modulariser plus tard.

Niveau de risque :

- moyen

Un wrapper legacy restera nécessaire pour préserver les anciennes URLs admin.

#### `cms`

Ce groupe pourrait devenir un module dédié avec un futur regroupement logique de :

- `pages.php`
- `page_edit.php`

Ce groupe peut être modularisé plus tard, mais il reste un peu plus sensible qu'un simple listing admin car il touche indirectement les pages publiques et la gestion des slugs.

Niveau de risque :

- moyen

Un wrapper legacy sera nécessaire pour conserver la compatibilité des anciennes entrées admin.

#### `shipping_zones`

Ce groupe pourrait être rattaché plus tard à un bloc `settings/` ou à un module technique voisin.

Il correspond aujourd'hui principalement à :

- `shipping_zones.php`

Ce groupe peut être modularisé plus tard, mais seulement après stabilisation des autres blocs plus simples.

Niveau de risque :

- moyen à élevé

Un wrapper legacy sera nécessaire si l'URL historique est déplacée.

#### `settings`

Ce groupe correspond aujourd'hui principalement à :

- `settings.php`

Il pourrait devenir un module dédié plus tard, mais il doit rester stable plus longtemps car il concentre des réglages fonctionnels et techniques plus sensibles.

Niveau de risque :

- élevé

Un wrapper legacy sera nécessaire si une migration physique est faite plus tard.

### Ce qui devrait probablement rester plat

Les fichiers transverses et points d'entrée admin globaux ne doivent pas être déplacés maintenant, notamment :

- `index.php`
- `login.php`
- `logout.php`
- `_auth.php`
- `_layout_header.php`
- `_layout_footer.php`
- `_flash.php`
- `_guard.php`
- `403.php`

Plus largement, les autres fichiers transverses d'authentification, de layout, de garde ou de bootstrap admin doivent rester en place tant que les modules métier n'ont pas été uniformisés avec compatibilité stricte.

### Ordre recommandé de migration future

Ordre conseillé si une migration réelle est faite un jour :

1. `categories`
2. `coupons`
3. `cms`
4. `shipping_zones`
5. `settings`

Cet ordre permet de commencer par les groupes les plus naturellement modulaires avant de traiter les écrans plus sensibles côté exploitation.

### Règle de compatibilité

Tout déplacement futur d'un écran admin doit garder un wrapper legacy à l'ancienne URL.

Exemples de compatibilité acceptables :

- ancien fichier conservé en place, qui fait un `require` vers le nouveau fichier
- ancien point d'entrée laissé en façade le temps de migrer progressivement les liens internes

La règle importante est qu'aucune URL admin historique ne doit disparaître brutalement pendant la phase de transition.

## Ce qu'il ne faut pas déplacer maintenant

Les éléments suivants ne doivent pas être déplacés à ce stade :

- `index.php` racine
- `config.php` racine
- les pages publiques dans `pages/`
- les fichiers `admin/*.php` encore utilisés comme entrées directes
- les fichiers liés aux routes publiques
- les fichiers liés aux callbacks paiement
- `uploads/`

Concrètement, cela inclut notamment :

- les points d'entrée publics racine comme `page.php` et `suivi_commande.php`
- les pages checkout / retour paiement comme `pages/commande.php`, `pages/commande_submit.php`, `pages/payment_return.php`, `pages/order_success.php`
- les endpoints publics/API utilisés par le front ou des intégrations
- les fichiers historiques d'administration encore adressables directement par URL

## Plan de migration safe

### Phase 1 : documentation et conventions

- documenter la structure actuelle
- définir les blocs cibles
- définir une convention de nommage pour les futurs sous-dossiers admin
- définir les règles de compatibilité avant tout déplacement

### Phase 2 : assets page-level

- organiser progressivement les assets par contexte fonctionnel ou par page
- éviter toute rupture d'URL publique
- introduire au besoin une convention interne avant une vraie réorganisation physique

### Phase 3 : admin modules existants

- consolider d'abord la documentation des modules déjà présents dans `admin/`
- rapprocher logiquement les écrans racine des sous-dossiers métier existants
- identifier les futurs regroupements stables avant tout déplacement réel

### Phase 4 : pages admin isolées vers sous-dossiers, seulement avec redirects/compatibilité

- déplacer uniquement les pages admin bien isolées
- conserver une ancienne entrée compatible pour chaque URL historique
- tester les liens internes, boutons, formulaires et retours de navigation

### Phase 5 : nettoyage racine très tardif

- ne toucher à la racine qu'une fois les points d'entrée historiques stabilisés
- ne déplacer les fichiers racine que lorsque tous les appels, liens, includes et routes ont été cartographiés et validés

## Règle de compatibilité

Tout déplacement futur doit conserver une ancienne entrée compatible.

Trois stratégies sûres sont acceptables :

- ancien fichier conservé en place, qui fait un `require` vers le nouveau fichier
- redirect contrôlé quand le déplacement concerne une URL publique et que le comportement HTTP est maîtrisé
- mise à jour exhaustive des liens, includes, routes et appels, suivie de tests de non-régression

La règle importante est simple : aucun déplacement ne doit être considéré comme terminé tant que l'ancien point d'entrée n'est pas couvert par un mécanisme de compatibilité ou par une validation exhaustive testée.

## Conclusion

La structure actuelle est déjà partiellement alignée avec la cible côté `app/`, `database/`, `public/api/`, `scripts/` et `cron/`.

Le principal enjeu n'est donc pas une refonte brutale, mais une normalisation progressive de `admin/`, une meilleure lisibilité documentaire, puis des migrations encadrées avec compatibilité stricte.
