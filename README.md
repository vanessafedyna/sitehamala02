# SORA Collection

Application e-commerce developpee en PHP/MySQL, avec front HTML/CSS/JavaScript et un back-office admin.

## Stack technique

- PHP (sans framework)
- MySQL / MariaDB
- HTML, CSS, JavaScript
- Apache (en local via XAMPP ou equivalent)

## Fonctionnalites principales

- Catalogue produits (recherche, filtres, fiches produit)
- Panier et checkout (paiement a la livraison)
- Espace client (connexion, inscription, suivi de commandes)
- Back-office admin (produits, commandes, clients, pages CMS, settings, exports)
- Notifications (email et WhatsApp simple via lien `wa.me`)
- Worker de traitement de file de notifications

## Structure du projet

```text
sitehamala/
|- app/                # bootstrap, config, core, helpers, models, services
|- admin/              # back-office
|- assets/             # CSS, JS, images
|- cron/               # workers/cron
|- database/           # schema + patches SQL
|- includes/           # templates/layout + services utilitaires
|- pages/              # pages publiques
|- public/api/         # endpoints API JSON
|- uploads/            # fichiers uploades (produits/categories)
|- config.php          # config globale (base_url, constantes site)
|- index.php           # page d'accueil
`- database/malishop.sql # dump SQL complet (etat de reference)
```

## Installation locale

Prerequis:

- PHP 8.x avec extensions PDO/MySQL
- MySQL/MariaDB
- Apache (ou stack locale equivalente)

Etapes:

1. Placer le projet dans le dossier web de votre environnement local.
2. Creer une base MySQL (ex: `malishop`).
3. Importer la base:
   - Option recommandee: importer `database/malishop.sql` (dump complet).
   - Option maintenance: utiliser `database/schema.sql` puis appliquer les patches necessaires dans `database/`.
4. Creer un fichier `.env` a la racine (voir section Configuration).
5. Demarrer Apache + MySQL.

## Configuration environnement (.env)

Le projet charge automatiquement `.env` (via `app/config/env.php`).

Variables principales:

```env
APP_BASE_URL=/sitehamala/
APP_ENV=dev

DB_HOST=127.0.0.1
DB_NAME=malishop
DB_USER=root
DB_PASS=
```

Notes:

- `APP_BASE_URL` doit correspondre au chemin web reel du projet.
- Si `APP_BASE_URL` est vide, le projet tente une auto-detection.
- `DB_USER=root` ci-dessus est un exemple local/XAMPP uniquement. En production, utiliser un compte MySQL dedie a l'application.
- Le projet utilise uniquement WhatsApp simple via lien `wa.me`, base sur le parametre `shop_whatsapp_number`.

## Checklist production

- Ne pas deployer le `.env` local: creer un `.env` serveur non versionne a partir de `.env.production.example`.
- Definir `APP_ENV=prod` et `APP_DEBUG=false`.
- Definir `APP_PUBLIC_URL` avec le vrai domaine HTTPS de production.
- Remplacer tous les placeholders `DB_*` et `SMTP_*` par des credentials production dedies.
- Ne pas utiliser `root` pour `DB_USER` en production: preferer un compte applicatif restreint a la base du site.
- Verifier que `.env` et les variantes `.env.*` restent ignores par Git, sauf les fichiers `*.example`.
- Verifier les permissions d'ecriture sur `uploads/` et `app/logs/`.

## Runtime PHP production

Le projet force deja certains points au runtime:

- `config.php` masque `display_errors` quand `APP_DEBUG=false`.
- `app/core/session.php` force `session.use_strict_mode`, `session.use_only_cookies`, `session.use_trans_sid=0`, `session.cookie_httponly` et `session.cookie_samesite=Lax`.
- En production HTTPS, `app/core/session.php` force aussi `session.cookie_secure=1`.
- `app/core/security_headers.php` envoie deja `X-Content-Type-Options`, `Referrer-Policy`, `X-Frame-Options`, `Permissions-Policy`, une CSP progressive et HSTS en HTTPS hors local.

Ce que le serveur PHP doit encore garantir:

- `display_errors=Off`, `display_startup_errors=Off`, `log_errors=On`
- `expose_php=Off`
- OPcache actif en production
- des limites runtime coherentes (`memory_limit`, `max_execution_time`, `upload_max_filesize`, `post_max_size`)
- un `session.save_path` ecrivable, hors webroot

Template recommande:

- Voir `php.ini.production.example`

Commandes de validation:

```powershell
php -i | Select-String "Loaded Configuration File"
php -i | Select-String "display_errors|display_startup_errors|log_errors|expose_php"
php -i | Select-String "memory_limit|max_execution_time|post_max_size|upload_max_filesize"
php -i | Select-String "session.use_strict_mode|session.use_only_cookies|session.use_trans_sid|session.cookie_httponly|session.cookie_samesite|session.cookie_secure|session.save_path"
php -i | Select-String "opcache.enable|opcache.enable_cli|opcache.memory_consumption|opcache.validate_timestamps"
```

Verification OPcache:

- `php -i | Select-String opcache.enable` doit montrer `On` sur le runtime web de production.
- Si vous avez une page de diagnostic serveur, verifier aussi `opcache_get_status()` ou la section OPcache de `phpinfo()`.
- En PHP-FPM/Apache, redemarrer le service PHP apres modification du `php.ini` puis recontroler sur le runtime web, pas seulement en CLI.

## Base de donnees

Sources disponibles:

- `database/malishop.sql` : dump complet (pratique pour une reprise rapide)
- `database/schema.sql` : schema de base
- `database/patch_*.sql` : evolutions incrementales

Compte MySQL de production recommande:

```sql
CREATE USER 'sitehamala_app'@'127.0.0.1' IDENTIFIED BY 'change_me_to_a_long_random_password';
GRANT SELECT, INSERT, UPDATE, DELETE ON `your_production_database`.* TO 'sitehamala_app'@'127.0.0.1';
FLUSH PRIVILEGES;
```

Notes least privilege:

- Les permissions minimales attendues par l'application au runtime sont `SELECT`, `INSERT`, `UPDATE`, `DELETE`.
- Les creations ou alterations de schema se font via les scripts SQL du dossier `database/` ou via des scripts de maintenance, pas via le runtime web normal.
- Aucun besoin identifie pour `CREATE TEMPORARY TABLES` ou `LOCK TABLES` dans le flux applicatif standard.

## Backup & Recovery

Perimetre minimal a couvrir en production:

- Base MySQL/MariaDB: donnees transactionnelles et structure runtime.
- `uploads/`: fichiers produits/categories references par la base.
- `app/logs/app.log`: utile pour diagnostic post-incident recent, mais secondaire par rapport a la base et aux uploads.

Constat actuel:

- Le repo contient des sources SQL de reference (`database/malishop.sql`, `database/schema.sql`, `database/patch_*.sql`) mais pas de strategie de backup production.
- Aucun script de backup/restore dedie n'est present aujourd'hui.
- Les fichiers critiques a sauvegarder cote applicatif sont surtout `uploads/`; les logs peuvent etre captures en retention courte selon l'espace disponible.

Strategie minimale recommandee:

- Backup DB quotidien par dump logique compresse.
- Backup quotidien de `uploads/` sous forme d'archive ou de synchronisation versionnee.
- Retention cible: `7 daily + 4 weekly + 12 monthly` si l'espace le permet. Minimum absolu: `7 daily + 4 weekly`.
- Copier chaque backup vers un stockage hors serveur (objet storage, autre VM, NAS distant, bucket chiffre, etc.).
- Chiffrer au repos si le stockage de destination n'est pas deja chiffre.
- Verifier regulierement qu'au moins un backup DB et un backup uploads recents existent bien hors serveur.

Scripts d'exemple:

- `scripts/backup_database_example.sh`
- `scripts/backup_uploads_example.sh`

Ces scripts sont des exemples manuels/scheduler-safe:

- aucun secret hardcode
- sortie hors du repo par defaut
- aucune suppression automatique

Exemple de rotation:

- `daily/` conserve 7 versions
- chaque dimanche, recopier le backup du jour vers `weekly/` et conserver 4 versions
- le 1er du mois, recopier le backup du jour vers `monthly/` et conserver 12 versions

Copie hors serveur:

- La copie offsite doit etre un second job distinct du backup local.
- Eviter de considerer le meme disque ou le meme serveur comme une vraie sauvegarde.
- Un test minimal consiste a verifier chaque jour la presence d'un dump DB et d'une archive `uploads/` sur le stockage distant.

Restore readiness:

Restauration base de donnees:

```sh
gzip -dc /restore/path/your_production_database_YYYY-MM-DD_HHMMSS.sql.gz | mysql -h 127.0.0.1 -u restore_user -p your_restore_database
```

Restauration uploads:

```sh
mkdir -p /var/www/sitehamala
tar -xzf /restore/path/sitehamala_uploads_YYYY-MM-DD_HHMMSS.tar.gz -C /var/www/sitehamala
```

Checklist de test de restauration:

1. Restaurer la base dans une base de test isolee, jamais par-dessus la prod.
2. Restaurer les `uploads/` dans un chemin de test isole.
3. Pointer un environnement de test vers cette base et ces fichiers restaures.
4. Verifier le login admin, le catalogue, une fiche produit avec image, et une commande existante.
5. Verifier que les comptes de service (DB, SMTP, cron) sont reconfigures pour le test afin d'eviter tout effet externe.
6. Mesurer le temps de restauration reel et noter les etapes manuelles.
7. Corriger la documentation si une etape a ete ambigue ou oubliee.

Rythme recommande de test:

- test de restauration DB au moins mensuel
- test complet DB + uploads au moins trimestriel

Exemples cron Linux:

```cron
15 2 * * * /bin/sh /var/www/sitehamala/scripts/backup_database_example.sh
45 2 * * * /bin/sh /var/www/sitehamala/scripts/backup_uploads_example.sh
15 3 * * * /usr/local/bin/rsync -az --delete /var/backups/sitehamala/ backup@backup-host:/srv/backups/sitehamala/
```

Attention:

- La rotation/suppression doit etre implemente avec prudence et testee a part.
- Ne pas restaurer un dump ou des uploads directement sur la production sans fenetre de maintenance et rollback planifie.
- Les scripts d'exemple supposent un scheduler externe et des credentials fournis par l'environnement.

## Lancement en local

Exemple URL locale:

- `http://localhost/sitehamala/`

Back-office admin:

- `http://localhost/sitehamala/admin/login.php`

## Admin, worker et cron

Admin:

- Interface dans `admin/`
- Login via `admin/login.php`

Worker notifications:

- Fichier: `cron/worker_notifications.php`
- Exemple (Windows/XAMPP):
  - `C:\xampp\xampp-renew\php\php.exe C:\xampp\xampp-renew\htdocs\sitehamala\cron\worker_notifications.php`
- Options supportees:
  - `--dry-run`
  - `--limit=<n>`

## Fiabilite cron et supervision

Jobs critiques identifies:

- `cron/worker_notifications.php` : consomme la file de notifications. En V1, les emails sont traites et le contact WhatsApp reste limite au lien `wa.me`.

Etat actuel:

- Le worker notifications utilise deja un verrou logique avec `locked_at` dans `notification_jobs`, ce qui limite les doubles traitements au niveau queue.
- En revanche, sans supervision externe, un arret du scheduler ou un worker qui ne tourne plus peut rester discret.

Garde-fous legerement ajoutes:

- lock fichier non bloquant pour eviter deux instances simultanees du meme script
- heartbeat JSON de derniere execution dans `app/logs/cron/`
- fallback automatique vers le repertoire temporaire systeme si `app/logs/cron/` n'est pas ecrivable

Fichiers de heartbeat attendus:

- `app/logs/cron/worker_notifications.heartbeat.json`

Planification cron recommandee:

```cron
*/5 * * * * /usr/bin/php /var/www/sitehamala/cron/worker_notifications.php >> /var/log/sitehamala/worker_notifications.log 2>&1
```

Points de parametrage:

- `worker_notifications.php` toutes les 5 minutes est un minimum raisonnable pour limiter la latence de file.
- Conserver les redirections stdout/stderr vers des logs distincts simplifie le diagnostic post-incident.

Exemple systemd simple:

```ini
[Unit]
Description=SiteHamala notification worker cron

[Service]
Type=oneshot
WorkingDirectory=/var/www/sitehamala
ExecStart=/usr/bin/php /var/www/sitehamala/cron/worker_notifications.php
```

```ini
[Unit]
Description=Run SiteHamala notification worker every 5 minutes

[Timer]
OnCalendar=*:0/5
Persistent=true

[Install]
WantedBy=timers.target
```

Exemple Supervisor simple:

```ini
[program:sitehamala-worker-notifications]
command=/usr/bin/php /var/www/sitehamala/cron/worker_notifications.php
directory=/var/www/sitehamala
autostart=true
autorestart=true
startsecs=0
stdout_logfile=/var/log/sitehamala/worker_notifications.log
stderr_logfile=/var/log/sitehamala/worker_notifications.err.log
```

Note supervision:

- Pour ce projet, `systemd timer` ou cron + Healthchecks/Dead Man's Snitch sont preferables a un daemon maison.
- Le worker actuel est pense comme job periodique et non comme boucle infinie residente.

Checklist monitoring minimale:

- Verifier que le scheduler declenche bien chaque job a la frequence attendue.
- Surveiller l'age du fichier `worker_notifications.heartbeat.json`; alerter s'il depasse 15 minutes.
- Surveiller la presence de `status=fatal` ou `status=skipped_locked` repetes dans les heartbeat.
- Surveiller la croissance de `notification_jobs` en `pending` ou `failed`.
- Surveiller les erreurs `worker_fatal_error` et `worker_job_failed` dans `app/logs/app.log`.
- Verifier qu'un log stdout/stderr recent existe pour chaque job critique.

Requetes SQL utiles de controle:

```sql
SELECT status, COUNT(*) AS total
FROM notification_jobs
GROUP BY status;

SELECT COUNT(*) AS overdue_pending
FROM notification_jobs
WHERE status IN ('pending', 'failed')
  AND (next_retry_at IS NULL OR next_retry_at <= NOW());

SELECT id, order_id, type, attempts, max_attempts, last_error, next_retry_at, locked_at
FROM notification_jobs
WHERE status IN ('pending', 'failed')
ORDER BY updated_at ASC
LIMIT 20;
```

Alertes minimales recommandees:

- alerte si aucun heartbeat `worker_notifications` depuis 15 minutes
- alerte si `overdue_pending > 0` de facon persistante sur plusieurs checks
- alerte si le nombre de jobs `failed` augmente rapidement
- alerte si `locked_at` reste ancien de facon anormale ou si `skipped_locked` devient frequent

## Securite et deploiement (base)

Avant production:

- Configurer un vrai `.env` (pas de secrets en dur dans le repo)
- Mettre des credentials MySQL dedies (pas `root`)
- Conserver le mode debug desactive (`APP_DEBUG=false`, `DEBUG_MODE=false`)
- Activer HTTPS en production
- Verifier les settings SMTP et le numero `shop_whatsapp_number` dans l'admin. Le projet utilise uniquement WhatsApp simple via lien `wa.me`.
- Verifier les permissions ecriture sur `uploads/` et `app/logs/`

Headers de securite:

- Les headers globaux sont envoyes au bootstrap (`app/core/security_headers.php`), avec CSP progressive.
- HSTS est active uniquement en HTTPS hors environnement local.

## Notes de maintenance

- Le header public utilise un contexte dedie: `includes/_header_context.php`.
- Les endpoints API sont dans `public/api/`.
- Les exports CSV admin sont dans `admin/exports/`.
- Les logs applicatifs sont ecrits dans `app/logs/`.
- Pour les evolutions DB, preferer des patches SQL incrementaux dans `database/`.
