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


function ata_associations_taches_generales_cron($taches_generales) {
	if (isset($GLOBALS['meta']['ata_import_processing']) and ($GLOBALS['meta']['ata_import_processing']==='oui')){
		
		include_spip('inc/ata_importer_utils');
		list($periode,$nb) = ata_importer_cadence();
		$taches_generales['ata_importer_test'] = max(60, $periode-15);
	}
	return $taches_generales;
}