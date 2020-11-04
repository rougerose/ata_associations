<?php
/**
 * Utilisations de pipelines par ATA Associations
 *
 * @plugin     ATA Associations
 * @copyright  2020
 * @author     christophe le drean
 * @licence    GNU/GPL v3
 * @package    SPIP\Ata_associations\Pipelines
 */

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}





/**
 * Optimiser la base de données
 *
 * Supprime les objets à la poubelle.
 *
 * @pipeline optimiser_base_disparus
 * @param  array $flux Données du pipeline
 * @return array       Données du pipeline
 */
function ata_associations_optimiser_base_disparus($flux) {

	sql_delete('spip_associations', "statut='poubelle' AND maj < " . $flux['args']['date']);

	return $flux;
}
