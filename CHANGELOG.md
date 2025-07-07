# Changelog

## 1.0.11 - 2025-07-07

### Added

-   ajout ecs et rector

### Fixed

-   Type d'adresse : Compte tenu des changements survenus dans le plugin Coordonnées, les développements déjà en place qui visaient à forcer le type Work pour chaque enregistrement de coordonnées n'était plus fonctionnel : de nombreuses adresses étaient sans type, Spip affichait pour ces assos des adresses qui n'étaient pas la leur. L'API permet en principe de modifier la liste des types mais ça ne semble pas fonctionner en l'état. On utilise donc le fonctionnement par défaut du plugin (choix du type au moment de la saisie). La version (1.0.3) de la base est modifiée pour ajouter une fonction de mise à jour de toutes les données d'adresse pour leur ajouter le type attendu.
