# Fichiers à vérifier plus tard

## Objet du document

Ce document liste des fichiers et dossiers qui semblent hérités, temporaires, ambigus ou à clarifier dans ce projet PHP natif e-commerce.

Principe de prudence :

- aucun élément ci-dessous n’est marqué comme inutile avec certitude ;
- la présence ou l’absence de références directes dans le code ne suffit pas toujours à conclure ;
- certains fichiers existent pour compatibilité, exploitation locale, migration ou maintenance manuelle.

## Synthèse rapide

| Chemin exact | Rôle probable | Pourquoi vérifier | Risque si suppression sans analyse | Recommandation |
|---|---|---|---|---|
| `_edge_tmp/` | fichiers temporaires Microsoft Edge / Crashpad | ressemble à un dépôt temporaire local, hors logique applicative | faible pour l’app, mais possible perte d’artefacts de debug local | documenter puis archiver ou nettoyer localement après confirmation |
| `pages/about.php` | alias / redirection legacy vers la page À propos | doublon apparent avec `pages/apropos.php` | casser un ancien lien entrant ou un bookmark | garder |
| `suivi_commande.php` | point d’entrée legacy de suivi | redirige vers `pages/suivi.php` | casser d’anciens liens publics | garder |
| `admin/dashboard.php` | ancienne entrée admin | redirige vers `admin/index.php` | casser un ancien lien admin ou un favori | garder |
| `admin/backups.php` | ancienne entrée backup | redirige vers `admin/index.php` | casser un ancien lien interne | documenter |
| `admin/exports/` | module d’export partiellement inactif / partiellement utilisé | `index.php` redirige, mais `orders_export.php` est appelé | suppression partielle dangereuse | vérifier plus tard |
| `assets/css/pages/admin-products.css` | socle CSS admin partagé legacy | nom orienté produits, mais utilisé largement dans l’admin | casser l’affichage de nombreuses pages admin si renommé sans audit | documenter et renommer plus tard seulement avec mise à jour complète des références |
| `pages/_cms.php` | helper CMS partagé par plusieurs pages publiques | nom de fichier “privé”, mais réellement utilisé | casser les pages CMS hybrides | garder |
| `scripts/migrate-products-multi-images.php` | script de migration ponctuelle | sert à faire évoluer le schéma / backfill images | perte d’un outil de maintenance utile en reprise | archiver plus tard si migration confirmée terminée |
| `scripts/cleanup-product-images.php` | script de maintenance DB/images | peut modifier les chemins images en base | suppression d’un outil de nettoyage contrôlé | documenter |
| `scripts/backup_database_example.sh` | exemple de sauvegarde Linux | fichier d’exemple, pas forcément utilisé en prod | faible, mais utile comme procédure | garder et documenter |
| `scripts/backup_uploads_example.sh` | exemple de sauvegarde Linux | même logique | faible, mais utile comme procédure | garder et documenter |
| `cron/worker_notifications.php` | worker réel de notifications | composant d’exploitation important | casser la file de notifications email | garder |

## Détail par élément

### `_edge_tmp/`

- Chemin exact : `_edge_tmp/`
- Rôle probable : fichiers temporaires créés localement par Edge / Crashpad, pas par l’application.
- Indices : présence de `Crashpad`, `reports`, `.tmp`, `Variations`, `.dmp`.
- Pourquoi vérifier :
  - ne correspond à aucune structure métier du projet ;
  - non référencé dans le code ;
  - ressemble à un dépôt technique laissé dans la racine du workspace.
- Risque si on le supprime sans analyse :
  - faible pour le site ;
  - possible perte d’artefacts utiles pour diagnostiquer un crash navigateur local.
- Recommandation : documenter puis archiver ou nettoyer localement après validation humaine.

### SQL de seed et schéma

- Chemins exacts :
  - `database/schema.sql`
  - `database/patch_*.sql`
- Rôle probable :
  - `schema.sql` : schéma de base ;
  - `patch_*.sql` : migrations incrémentales ;
- Pourquoi vérifier :
  - il faut distinguer ce qui sert à reconstruire une base propre de ce qui sert aux migrations incrémentales ;
  - certains écrans admin renvoient explicitement vers des patchs précis.
- Risque si on le supprime sans analyse :
  - perte de la capacité de réinstaller ou réparer un environnement ;
  - incohérences entre code PHP et structure SQL attendue.
- Recommandation : garder et documenter plus clairement le flux d’installation/migration.

### `pages/about.php` et `pages/apropos.php`

- Chemins exacts :
  - `pages/about.php`
  - `pages/apropos.php`
- Rôle probable :
  - `pages/about.php` est un alias legacy ;
  - `pages/apropos.php` est la page publique principale.
- Pourquoi vérifier :
  - apparent doublon fonctionnel ;
  - `pages/about.php` redirige vers `pages/apropos.php`.
- Risque si on le supprime sans analyse :
  - cassure de liens externes, bookmarks ou URLs historiques.
- Recommandation : garder, car le doublon semble volontaire pour compatibilité.

### `suivi_commande.php` et `pages/suivi.php`

- Chemins exacts :
  - `suivi_commande.php`
  - `pages/suivi.php`
  - `pages/suivi-commande.php`
- Rôle probable :
  - `suivi_commande.php` est un point d’entrée legacy qui redirige ;
  - `pages/suivi.php` semble être l’entrée courante ;
  - `pages/suivi-commande.php` mérite une comparaison fonctionnelle ultérieure.
- Pourquoi vérifier :
  - noms proches pouvant masquer plusieurs générations de la même fonctionnalité ;
  - racine + `pages/` + variante avec tiret.
- Risque si on le supprime sans analyse :
  - cassure de liens publics anciens ;
  - perte de compatibilité SEO ou support client.
- Recommandation : garder pour l’instant et vérifier plus tard la matrice exacte des URLs.

### `page.php` et `pages/_cms.php`

- Chemins exacts :
  - `page.php`
  - `pages/_cms.php`
- Rôle probable :
  - `page.php` : route générique CMS par slug ;
  - `pages/_cms.php` : helper partagé pour charger/sanitizer certaines pages CMS connues.
- Pourquoi vérifier :
  - il y a deux mécanismes CMS dans le projet : route générique et pages publiques “hybrides” alimentées par CMS ;
  - `pages/_cms.php` a un nom pouvant faire croire à un fichier interne non utilisé, alors qu’il est chargé par plusieurs pages légales et éditoriales.
- Risque si on le supprime sans analyse :
  - casse de pages légales / FAQ / à propos ;
  - casse du rendu CMS.
- Recommandation : garder et mieux documenter la différence entre CMS générique et CMS par clé.

### `admin/dashboard.php` et `admin/index.php`

- Chemins exacts :
  - `admin/dashboard.php`
  - `admin/index.php`
- Rôle probable :
  - `admin/index.php` : dashboard réel ;
  - `admin/dashboard.php` : ancienne URL redirigée.
- Pourquoi vérifier :
  - doublon apparent côté admin ;
  - `admin/dashboard.php` ne contient plus de logique propre.
- Risque si on le supprime sans analyse :
  - cassure de liens admin historiques.
- Recommandation : garder comme alias tant que les URLs legacy n’ont pas été auditées.

### `admin/backups.php`

- Chemin exact : `admin/backups.php`
- Rôle probable : ancienne entrée de module backups, aujourd’hui redirigée vers `admin/index.php`.
- Pourquoi vérifier :
  - le nom suggère une fonctionnalité qui n’est plus présente dans ce fichier ;
  - peut induire en erreur un repreneur du projet.
- Risque si on le supprime sans analyse :
  - cassure d’un lien interne, d’un signet admin ou d’une ancienne navigation.
- Recommandation : documenter ; à archiver plus tard si aucun usage réel n’est confirmé.

### `admin/exports/`

- Chemins exacts :
  - `admin/exports/index.php`
  - `admin/exports/orders.php`
  - `admin/exports/orders_export.php`
  - `admin/exports/products.php`
  - `admin/exports/customers.php`
- Rôle probable :
  - zone d’export admin incomplète ou partiellement neutralisée ;
  - `orders_export.php` est bien encore appelé depuis `admin/orders/index.php`.
- Pourquoi vérifier :
  - `admin/exports/index.php` redirige ;
  - certains écrans du dossier ressemblent à des entrées secondaires ;
  - il faut distinguer les écrans inactifs des endpoints réellement utilisés.
- Risque si on le supprime sans analyse :
  - perte des exports CSV commandes ;
  - casse de liens ou de fonctionnalités cachées.
- Recommandation : vérifier plus tard, fichier par fichier.

### `assets/css/pages/admin-products.css`

- Chemin exact : `assets/css/pages/admin-products.css`
- Rôle probable : fichier CSS admin partagé legacy, utilisé comme socle commun par une large partie du back-office.
- Pourquoi vérifier :
  - malgré son nom orienté “products”, il est actuellement utilisé par environ 30 pages admin ;
  - il sert de base de styles partagés pour plusieurs écrans admin, pas seulement pour les produits ;
  - son nom est donc trompeur pour un repreneur du projet.
- Risque si on le renomme sans analyse :
  - casse visuelle potentielle sur de nombreuses pages admin ;
  - risque élevé d’oublier des références déclarées via `$page_css`.
- Recommandation :
  - ne pas le renommer à court terme ;
  - un renommage futur vers `admin-shared.css` est envisageable ;
  - ne faire ce renommage qu’après inventaire et mise à jour de toutes les références `$page_css`.

### Modules admin proches ou à clarifier

- Chemins exacts :
  - `admin/reviews/index.php`
  - `admin/product_reviews/index.php`
  - `admin/categories.php` + `admin/category_add.php` / `edit.php` / `delete.php` / `toggle.php`
  - `admin/coupons.php` + `admin/coupon_add.php` / `edit.php` / `delete.php`
- Rôle probable :
  - modules actifs, mais avec structures différentes selon les domaines.
- Pourquoi vérifier :
  - certains utilisent une page liste + actions séparées ;
  - d’autres regroupent plus de logique dans un seul écran ;
  - utile pour un futur nettoyage d’architecture.
- Risque si on le supprime sans analyse :
  - casser des workflows admin actifs.
- Recommandation : documenter et harmoniser plus tard, pas de suppression.

### Scripts de maintenance et de migration

- Chemins exacts :
  - `scripts/migrate-products-multi-images.php`
  - `scripts/cleanup-product-images.php`
  - `scripts/create-admin.php`
  - `scripts/create-partner.php`
- Rôle probable :
  - outils CLI / localhost pour setup, migration, nettoyage ou bootstrap des comptes.
- Pourquoi vérifier :
  - certains sont explicitement ponctuels ;
  - certains peuvent être exécutés aussi via le web local ;
  - ils touchent directement la base de données.
- Risque si on le supprime sans analyse :
  - perte d’outils utiles pour maintenance ou reprise d’environnement ;
  - difficulté à reproduire certains changements historiques.
- Recommandation :
  - `migrate-products-multi-images.php` : archiver plus tard si la migration est confirmée terminée partout ;
  - `cleanup-product-images.php` : garder et documenter ;
  - `create-admin.php` / `create-partner.php` : garder.

### Scripts de backup d’exemple

- Chemins exacts :
  - `scripts/backup_database_example.sh`
  - `scripts/backup_uploads_example.sh`
- Rôle probable : exemples de sauvegarde pour environnement Linux.
- Pourquoi vérifier :
  - le suffixe `example` suggère qu’ils ne sont pas branchés directement ;
  - README les mentionne dans une logique de procédure.
- Risque si on le supprime sans analyse :
  - perte de documentation opérationnelle implicite ;
  - perte d’un point de départ pour industrialiser les sauvegardes.
- Recommandation : garder et documenter.

### Worker cron

- Chemin exact : `cron/worker_notifications.php`
- Rôle probable : worker réel de consommation de file de notifications.
- Pourquoi vérifier :
  - composant d’exploitation important ;
  - dépend de fichiers runtime, d’un lock, d’un heartbeat et de services de notification.
- Risque si on le supprime sans analyse :
  - arrêt des notifications email ;
  - perte de visibilité sur le traitement différé.
- Recommandation : garder.

### Fichiers “maintenance” et logs potentiels à surveiller

- Chemins exacts :
  - `includes/maintenance.php`
  - `app/logs/` si présent localement hors suivi Git
- Rôle probable :
  - maintenance mode côté site ;
  - logs techniques d’exploitation.
- Pourquoi vérifier :
  - impacts forts possibles si branchés dans le flux de bootstrap ;
  - les logs ne sont pas listés dans le dépôt, mais le code en crée.
- Risque si on le supprime sans analyse :
  - perte d’informations de diagnostic ;
  - casse possible du mode maintenance selon l’intégration réelle.
- Recommandation : documenter plus tard.

## Points de vigilance pour un futur tri

- Ne pas supprimer un alias d’URL tant que les redirections legacy n’ont pas été cartographiées.
- Ne pas supprimer un script de migration tant qu’on n’a pas confirmé que tous les environnements sont alignés.
- Ne pas supprimer un dump SQL isolé tant que sa valeur de restauration ou de comparaison n’a pas été validée.
- Pour le dossier `_edge_tmp/`, commencer par vérifier :
  - présence dans `.gitignore`
  - date et origine de création
  - éventuelles consignes d’équipe
  - absence d’usage hors code applicatif
