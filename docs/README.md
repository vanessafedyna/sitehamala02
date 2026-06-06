# Documentation technique

## Objet

Ce fichier sert de point d'entree pour la documentation technique du projet. Il aide un developpeur a savoir quel document ouvrir selon le sujet traite, sans devoir parcourir tout le dossier `docs/` au hasard.

## Index des documents

### [ARCHITECTURE.md](./ARCHITECTURE.md)

Role :
Ce document presente la structure globale du projet PHP natif e-commerce, les grands dossiers, les points d'entree HTTP et les couches applicatives principales.

Quand le consulter :
A lire en premier quand on decouvre le projet, quand on cherche ou se trouve une responsabilite technique, ou avant d'intervenir sur une zone peu connue.

Pour qui :
Utile surtout pour un nouveau developpeur, un intervenant ponctuel, ou toute personne qui doit se reperer rapidement dans le codebase.

### [ADMIN.md](./ADMIN.md)

Role :
Ce document decrit le back-office admin, ses ecrans majeurs, la logique des roles et les principaux flux de gestion comme commandes, produits, clients et moderation.

Quand le consulter :
A ouvrir avant toute modification dans `admin/`, avant d'ajuster des permissions, ou pour comprendre les dependances fonctionnelles d'un ecran de gestion.

Pour qui :
Utile pour les developpeurs back-office, les personnes en charge de correctifs admin, et toute passation sur les workflows internes.

### [FILES_TO_REVIEW.md](./FILES_TO_REVIEW.md)

Role :
Ce document recense des fichiers ou dossiers ambigus, legacy, temporaires ou a clarifier avant suppression, nettoyage ou reorganisation.

Quand le consulter :
A verifier avant de supprimer un fichier, nettoyer la racine du projet, archiver un script, ou conclure trop vite qu'un element n'est plus utile.

Pour qui :
Utile pour les developpeurs, les mainteneurs techniques et toute personne chargee d'un nettoyage prudent du depot ou de l'environnement local.

### [REFACTOR_PLAN.md](./REFACTOR_PLAN.md)

Role :
Ce document formalise un plan de refactorisation future pour les gros fichiers PHP, avec un angle lisibilite, passation et reduction du risque.

Quand le consulter :
A lire avant de lancer un refactor, avant d'extraire des partials ou services, ou pour prioriser les gros fichiers a traiter plus tard.

Pour qui :
Utile pour un developpeur amene a restructurer le code, pour une passation entre developpeurs, et pour preparer un chantier de maintenance progressive.

## Ordre recommande de lecture pour un nouveau developpeur

1. Lire [ARCHITECTURE.md](./ARCHITECTURE.md) pour comprendre la carte generale du projet.
2. Lire [ADMIN.md](./ADMIN.md) si une intervention touche le back-office ou les flux de gestion.
3. Lire [FILES_TO_REVIEW.md](./FILES_TO_REVIEW.md) avant tout nettoyage, archivage ou suppression d'elements.
4. Lire [REFACTOR_PLAN.md](./REFACTOR_PLAN.md) avant toute initiative de refactorisation ou d'extraction structurelle.

## Regles de prudence avant modification

- Ne pas modifier les routes sans verifier les liens existants, les redirections, les formulaires et les URLs deja utilisees.
- Ne pas deplacer les fichiers sans verifier tous les `include`, `require`, `require_once` et chemins relatifs dependants.
- Ne pas toucher aux statuts commandes sans verifier les flux admin, client et paiement.
- Ne pas supprimer un fichier sans faire une recherche de references dans tout le projet.
- Toujours lancer un PHP lint apres modification pour detecter rapidement une erreur de syntaxe.

## Usage conseille

En pratique, ce `README` doit rester court et orienter vers les bons documents. Les details techniques doivent continuer de vivre dans les fichiers specialises, afin de garder un point d'entree simple pour les futurs developpeurs.
