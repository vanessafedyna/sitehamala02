# Plan de refactorisation future

## Objet

Ce document decrit un plan de refactorisation theorique pour les gros fichiers PHP du projet e-commerce. Il ne propose aucune modification immediate du code en production.

Principes de ce plan :
- approche conservative et production-safe
- aucun renommage, aucun deplacement, aucun changement de route ou d'include a ce stade
- aucun nouveau partial ou service n'est cree maintenant
- l'objectif est uniquement d'ameliorer la lisibilite future pour une passation entre developpeurs

## Strategie generale

Avant tout refactor reel, il faudra conserver l'ordre suivant :
1. isoler la lecture du fichier et cartographier ses responsabilites
2. extraire ensuite les blocs purement presentationnels
3. separer le traitement POST et les validations
4. deplacer enfin les requetes et helpers metier vers des couches dediees
5. verifier a chaque etape les flux admin, la protection CSRF, les roles, les messages flash et les formats de donnees

Invariants a respecter sur tous les ecrans :
- ne pas casser les controles d'acces `owner` / `partner`
- ne pas casser la protection CSRF ni les redirections post-action
- ne pas changer les noms de champs `GET` / `POST` utilises par les formulaires
- ne pas modifier le format des statuts, des montants FCFA, ni les structures de tableau attendues par les vues
- ne pas casser la compatibilite schema legacy deja geree dans le code

---

## `admin/revenue/index.php`

Taille observee :
- environ 1960 lignes

Responsabilites actuellement melangees :
- controle d'acces admin
- helpers de timezone, formatage et resolution de periode
- normalisation de statuts
- construction de plusieurs requetes SQL analytiques
- calcul de KPI
- chargement de listes de commandes
- chargement des tops produits
- preparation de la tendance mensuelle
- rendu HTML complet
- gros bloc CSS inline
- plusieurs blocs JS inline pour animations, sticky toolbar et graphiques

Separations envisageables plus tard :
- requetes KPI
- requetes de detail des commandes
- requetes top produits
- requetes de tendance mensuelle
- resolution des filtres de periode
- composants d'affichage des cartes KPI
- composant tableau des commandes
- composant top produits
- composant graphique / tendance
- JS d'animation et JS de graphique
- CSS specifique a la page

Proposition de decoupage futur theorique :
- `_period_filters.php`
- `_kpi_cards.php`
- `_orders_table.php`
- `_top_products.php`
- `_trend_chart.php`
- `_empty_state.php`
- `_revenue_page_styles.php`
- `_revenue_page_scripts.php`

Risque :
- eleve

Gains en lisibilite :
- reduction nette de la charge cognitive sur un ecran critique
- separation claire entre donnees, filtres, widgets et scripts
- maintenance plus simple des requetes analytiques sans relire toute la page

Ce qu'il ne faut surtout pas casser :
- logique des presets `today/week/month/year/custom`
- timezone effective utilisee pour les agrégations
- correspondance des statuts livres / annules dans les KPI
- donnees attendues par le graphique et les compteurs animes
- comportement des filtres et leur persistence dans l'URL

---

## `app/models/OrderModel.php`

Taille observee :
- environ 1546 lignes

Responsabilites actuellement melangees :
- lecture admin des commandes
- comptage admin
- lecture detail d'une commande et de ses items
- recherche par order number, utilisateur ou telephone
- creation complete de commande depuis le panier
- decrement / restock de stock
- validation des transitions de statut
- ecriture d'historique de statut
- compatibilite schema legacy
- introspection schema SQL et caches de colonnes
- normalisation des lignes `orders`

Separations envisageables plus tard :
- requetes de lecture admin
- requetes de lecture front / detail
- logique transactionnelle de creation de commande
- logique transactionnelle de changement de statut
- gestion d'historique de statut
- gestion de stock liee aux commandes
- helpers de normalisation de statut
- helpers d'introspection schema

Proposition de decoupage futur theorique :
- `OrderReadRepository.php`
- `OrderWriteRepository.php`
- `OrderStatusService.php`
- `OrderCheckoutService.php`
- `OrderStockService.php`
- `OrderHistoryRepository.php`
- `OrderSchemaAdapter.php`
- `OrderRowNormalizer.php`

Risque :
- eleve

Gains en lisibilite :
- frontieres plus nettes entre lecture, ecriture et logique metier
- comprehension bien meilleure du cycle de vie d'une commande
- reduction du risque de regression lors d'une evolution localisee

Ce qu'il ne faut surtout pas casser :
- transaction de creation de commande
- decrementation et restock de stock
- compatibilite entre schemas `orders` / `order_items` heterogenes
- validation des transitions de statut
- historique `order_status_history`
- valeurs initiales et mapping de statut vers la base

---

## `admin/orders/show.php`

Taille observee :
- environ 1306 lignes

Responsabilites actuellement melangees :
- controle d'acces
- helpers de statut et formatage montant
- detection de tables optionnelles
- chargement detail commande
- chargement historique
- chargement notes admin
- chargement notifications
- fallback manuel pour les items
- traitement POST pour notes et changement de statut
- journalisation / audit / notification
- rendu HTML detail
- CSS inline

Separations envisageables plus tard :
- helpers de statut mutualisables
- lecture des notes
- lecture des notifications
- bloc traitement POST note
- bloc traitement POST statut
- vue resume commande
- vue timeline / historique
- vue articles commande
- vue notes internes
- vue notifications techniques
- CSS de page

Proposition de decoupage futur theorique :
- `_order_summary.php`
- `_status_panel.php`
- `_items_table.php`
- `_history_timeline.php`
- `_internal_notes.php`
- `_notification_jobs.php`
- `_customer_block.php`
- `_order_show_styles.php`

Risque :
- eleve

Gains en lisibilite :
- ecran detail plus simple a parcourir pour un nouveau developpeur
- responsabilites mieux distinguees entre lecture, actions admin et rendu
- facilite de tester mentalement chaque bloc independamment

Ce qu'il ne faut surtout pas casser :
- changements de statut autorises selon le workflow
- insertion de notes admin et affichage immediat apres redirect
- compatibilite quand `order_notes` ou `notification_jobs` n'existent pas
- chargement robuste des items pour schema legacy
- journalisation et notifications declenchees par les actions admin

---

## `admin/orders/index.php`

Taille observee :
- environ 1136 lignes

Responsabilites actuellement melangees :
- controle d'acces
- helpers de date, statut, labels et badges
- regles de transition rapide
- traitement POST des actions bulk
- traitement POST des mises a jour rapides
- calcul KPI
- chargement liste paginee des commandes
- filtres par statut, recherche et dates
- rendu desktop et mobile
- export link building
- CSS inline
- JS inline pour bulk selection et reveal UI

Separations envisageables plus tard :
- parsing / resolution des filtres
- service de bulk actions
- calcul des KPI
- construction de la pagination et des query strings
- tableau desktop
- cartes mobile
- barre de filtres
- zone d'actions bulk
- styles inline
- scripts inline

Proposition de decoupage futur theorique :
- `_filters.php`
- `_kpi_cards.php`
- `_bulk_actions.php`
- `_table.php`
- `_mobile_cards.php`
- `_pagination.php`
- `_orders_index_styles.php`
- `_orders_index_scripts.php`

Risque :
- moyen a eleve

Gains en lisibilite :
- separation plus nette entre actions admin et rendu de liste
- lecture beaucoup plus rapide des zones critiques
- meilleure base pour de futures evolutions des filtres ou exports

Ce qu'il ne faut surtout pas casser :
- filtres `status`, `q`, `date_from`, `date_to`, `preset`, `page`
- actions bulk sur les IDs de commande
- messages flash et redirects
- coherence entre badges, labels et transitions autorisees
- export CSV/Excel base sur les query params courants

---

## `admin/products/edit.php`

Taille observee :
- environ 1062 lignes

Responsabilites actuellement melangees :
- controle d'acces owner
- chargement produit
- detection de colonnes optionnelles
- chargement categories marketing
- normalisation des variantes
- normalisation de categorie
- validation formulaire complete
- upload / traitement d'images
- mise a jour produit
- mise a jour categories relationnelles
- eventuel traitement featured
- audit admin
- rendu du gros formulaire
- CSS inline
- JS inline pour variantes et animations

Separations envisageables plus tard :
- hydratation initiale des valeurs de formulaire
- validation des champs
- normalisation des variantes
- traitement images
- persistence du produit
- persistence des categories associees
- panneau informations produit
- panneau media
- panneau variantes
- panneau attributs marketing
- JS variantes
- CSS de page

Proposition de decoupage futur theorique :
- `_product_form_core.php`
- `_product_form_media.php`
- `_product_form_variants.php`
- `_product_form_marketing.php`
- `_product_form_flags.php`
- `_product_form_actions.php`
- `_product_edit_styles.php`
- `_product_form_scripts.php`

Risque :
- eleve

Gains en lisibilite :
- meilleur parcours du formulaire par domaine fonctionnel
- facilite pour un nouveau developpeur de localiser validations et persistence
- reduction des aller-retours entre HTML, PHP et logique image

Ce qu'il ne faut surtout pas casser :
- validation SKU et unicite
- comportement des colonnes optionnelles (`is_featured`, multi-images, etc.)
- mapping des champs `POST` existants
- ordre et presence des images
- relation produit/categories
- messages d'erreur et valeurs preservees apres echec

---

## `admin/products/index.php`

Taille observee :
- environ 1061 lignes

Responsabilites actuellement melangees :
- controle d'acces
- detection de colonnes `featured` et `status`
- traitement POST edition rapide prix / stock / activation
- traitement POST publication en lot
- construction des filtres
- listing pagine des produits
- rendu toolbar, tableau, inline edit et pagination
- CSS inline
- JS inline pour reveal et edition inline

Separations envisageables plus tard :
- traitement POST des actions rapides
- traitement POST des actions bulk
- resolution des filtres
- lecture de la liste admin
- toolbar / filtres
- tableau principal
- modalite inline edit
- pagination
- CSS inline
- JS inline

Proposition de decoupage futur theorique :
- `_filters.php`
- `_products_table.php`
- `_inline_price_form.php`
- `_inline_stock_form.php`
- `_bulk_publish_bar.php`
- `_pagination.php`
- `_products_index_styles.php`
- `_products_index_scripts.php`

Risque :
- moyen

Gains en lisibilite :
- meilleure separation entre listing et actions de maintenance
- lisibilite accrue des cas owner-only
- reduction du bruit front dans un fichier orienté gestion

Ce qu'il ne faut surtout pas casser :
- quick update prix / stock
- toggle actif / brouillon
- publication bulk des produits `pending`
- filtre `status` quand la colonne existe
- pagination et conservation des query params

---

## `admin/products/create.php`

Taille observee :
- environ 941 lignes

Responsabilites actuellement melangees :
- controle d'acces
- initialisation du formulaire
- chargement categories
- detection multi-images
- generation SKU
- validation formulaire
- gestion des roles partner / owner
- upload images
- creation produit
- association categories
- rendu formulaire complet
- CSS inline
- JS inline variantes / reveal

Separations envisageables plus tard :
- valeurs par defaut du formulaire
- validation des champs
- generation SKU
- traitement images
- creation produit
- liaison categories
- sections visuelles du formulaire
- scripts variantes
- styles de page

Proposition de decoupage futur theorique :
- `_product_form_core.php`
- `_product_form_media.php`
- `_product_form_variants.php`
- `_product_form_marketing.php`
- `_product_form_submit.php`
- `_product_create_styles.php`
- `_product_form_scripts.php`

Risque :
- moyen a eleve

Gains en lisibilite :
- rapprochement avec une structure reusable entre creation et edition
- meilleure comprehension des differences owner / partner
- support de maintenance plus simple sur la validation produit

Ce qu'il ne faut surtout pas casser :
- image obligatoire numero 1
- regles partner sur le prix
- generation et validation SKU
- structure des variantes envoyees par le formulaire
- upload et enregistrement des images dans l'ordre attendu

---

## `admin/index.php`

Taille observee :
- environ 976 lignes

Responsabilites actuellement melangees :
- bootstrap admin et chargement utilisateur
- calcul de priorites owner
- chargement de multiples listes "to do"
- branchement conditionnel owner vs partner
- closures d'etat visuel pour cartes
- rendu dashboard complet
- CSS inline
- JS inline pour reveal et compteurs animes

Separations envisageables plus tard :
- requetes KPI dashboard
- requetes listes prioritaires
- mapping de l'etat visuel des cartes
- hero / intro dashboard
- cartes priorites
- listes de suivi
- scripts d'animation
- styles dashboard

Proposition de decoupage futur theorique :
- `_hero.php`
- `_priority_cards.php`
- `_owner_todo_lists.php`
- `_partner_recent_products.php`
- `_dashboard_kpis.php`
- `_dashboard_styles.php`
- `_dashboard_scripts.php`

Risque :
- moyen

Gains en lisibilite :
- dashboard plus facile a transmettre a un nouveau mainteneur
- separation claire entre donnees de pilotage et habillage visuel
- meilleure base pour faire evoluer owner et partner separement

Ce qu'il ne faut surtout pas casser :
- differences d'affichage selon le role
- compteurs prioritaires et seuils visuels
- chargement des listes critiques
- animations non bloquantes du dashboard

---

## `app/models/ProductModel.php`

Taille observee :
- environ 914 lignes

Responsabilites actuellement melangees :
- CRUD admin
- lecture catalogue front
- filtres admin et front
- recherche
- compatibilite de colonnes prix / images / status
- suppression avec nettoyages lies
- introspection schema `products`
- normalisation de lignes produit

Separations envisageables plus tard :
- repository admin
- repository front catalogue
- suppression transactionnelle
- filtres / query builder
- helpers de prix
- helpers de gestion image legacy et multi-images
- normaliseur de ligne produit
- introspection schema

Proposition de decoupage futur theorique :
- `ProductAdminRepository.php`
- `ProductCatalogRepository.php`
- `ProductDeleteService.php`
- `ProductFilterBuilder.php`
- `ProductImageNormalizer.php`
- `ProductPriceNormalizer.php`
- `ProductSchemaAdapter.php`

Risque :
- moyen a eleve

Gains en lisibilite :
- distinction plus claire entre besoins back-office et besoins catalogue
- logique de compatibilite schema plus facile a isoler
- maintenance plus simple des filtres et tris

Ce qu'il ne faut surtout pas casser :
- compatibilite des colonnes image legacy / nouvelles
- fallback quand certaines colonnes n'existent pas
- filtres `status`, `category`, `category_slug`, `stock_filter`
- comportements front qui supposent une cle `image_path`
- suppression transactionnelle des dependances produit

---

## Priorisation conseillee

Ordre de travail recommande pour un futur refactor reel :
1. `admin/orders/index.php`
2. `admin/products/index.php`
3. `admin/index.php`
4. `admin/products/create.php`
5. `admin/products/edit.php`
6. `admin/orders/show.php`
7. `app/models/ProductModel.php`
8. `admin/revenue/index.php`
9. `app/models/OrderModel.php`

Rationale :
- commencer par les ecrans admin de liste donne des gains de lisibilite rapides avec un risque encore maitrise
- traiter ensuite les formulaires produit, qui partagent une structure voisine
- garder les gros modeles transactionnels et les analytics lourds pour la fin, car ils concentrent le plus de risque metier

## Notes de passation

Pour un autre developpeur, la lecture du code sera plus sure si chaque futur refactor respecte ces garde-fous :
- faire des extractions progressives fichier par fichier
- figer les comportements visibles avant de deplacer des blocs
- comparer les structures `$_POST`, `$_GET` et les messages flash avant/apres
- tester les workflows critiques : commandes, statuts, produits, images, dashboard, analytics
- conserver un plan de rollback simple tant que les grosses pages restent tres couplees
