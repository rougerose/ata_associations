<?php

/**
 * Options au chargement du plugin ATA Associations
 *
 * @plugin     ATA Associations
 * @copyright  2020
 * @author     christophe le drean
 * @licence    GNU/GPL v3
 * @package    SPIP\Ata_associations\Options
 */

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

// Répertoire des fichiers importés
if (!defined('_DIR_IMPORTS_ATA')) {
	define('_DIR_IMPORTS_ATA', _DIR_VAR);
}

// Type de coordonnées par défaut
// 2025-07-07 : la constante n'est plus utilisée
// if (!defined('_COORDONNEES_TYPE_DEFAUT')) {
// 	define('_COORDONNEES_TYPE_DEFAUT', 'work');
// }

// Pays par défaut (en principe défini par Coordonnees)
if (!defined('_COORDONNEES_PAYS_DEFAUT')) {
	define('_COORDONNEES_PAYS_DEFAUT', 'FR');
}
