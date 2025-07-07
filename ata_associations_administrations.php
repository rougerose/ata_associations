<?php

/**
 * Fichier gérant l'installation et désinstallation du plugin ATA associations
 *
 * @plugin     ATA associations
 * @copyright  2020
 * @author     christophe le drean
 * @licence    GNU/GPL v3
 * @package    SPIP\Ata_associations\Installation
 */

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}
include_spip('base/importer_spip_mots');
include_spip('base/importer_spip_groupes_mots');
/**
 * Fonction d'installation et de mise à jour du plugin ATA associations.
 *
 * @param string $nom_meta_base_version
 *     Nom de la meta informant de la version du schéma de données du plugin installé dans SPIP
 * @param string $version_cible
 *     Version du schéma de données dans ce plugin (déclaré dans paquet.xml)
 **/
function ata_associations_upgrade($nom_meta_base_version, $version_cible) {
	$maj = [];

	$maj['create'] = [
		['maj_tables', ['spip_associations', 'spip_associations_imports_sources', 'spip_associations_imports']],
		['ata_associations_configurer_dependances'],
		['importer_spip_mots'],
		['importer_spip_groupes_mots'],
	];

	$maj['1.0.3'] = [['ata_associations_peupler_type_adresse']];

	include_spip('base/upgrade');
	maj_plugin($nom_meta_base_version, $version_cible, $maj);
}

/**
 * Fonction de désinstallation du plugin ATA associations.
 *
 * @param string $nom_meta_base_version
 *     Nom de la meta informant de la version du schéma de données du plugin installé dans SPIP
 **/
function ata_associations_vider_tables($nom_meta_base_version) {

	sql_drop_table('spip_associations');
	sql_drop_table('spip_associations_imports_sources');
	sql_drop_table('spip_associations_imports');

	# Nettoyer les liens courants (le génie optimiser_base_disparus se chargera de nettoyer toutes les tables de liens)
	sql_delete('spip_documents_liens', sql_in('objet', ['association']));
	sql_delete('spip_mots_liens', sql_in('objet', ['association']));
	sql_delete('spip_auteurs_liens', sql_in('objet', ['association']));
	// Tables supplémentaires
	sql_delete('spip_adresses_liens', sql_in('objet', ['association']));
	sql_delete('spip_emails_liens', sql_in('objet', ['association']));
	sql_delete('spip_rezosocios_liens', sql_in('objet', ['association']));
	sql_delete('spip_gis_liens', sql_in('objet', ['association']));
	sql_delete('spip_territoires_liens', sql_in('objet', ['association']));
	# Nettoyer les versionnages et forums
	sql_delete('spip_versions', sql_in('objet', ['association', 'associations_imports_source', 'associations_import']));
	sql_delete(
		'spip_versions_fragments',
		sql_in('objet', ['association', 'associations_imports_source', 'associations_import'])
	);
	sql_delete('spip_forum', sql_in('objet', ['association', 'associations_imports_source', 'associations_import']));

	effacer_meta($nom_meta_base_version);
}

function ata_associations_configurer_dependances() {
	include_spip('inc/config');
	// GIS
	$gis_conf = lire_config('gis', []);
	$gis_conf_associations = [
		// Plus ou moins le centre de la France
		'lat' => '46.4947387',
		'lon' => '2.6028326',
		'zoom' => '6',
		'geocoder' => 'on',
		'adresse' => 'on',
		'layer_defaut' => 'cartodb_positron',
		'plugins_desactives' => ['KML.js', 'GPX.js', 'TOPOJSON.js', 'Control.FullScreen.js', 'Control.MiniMap.js'],
		'gis_objets' => ['spip_associations'],
	];
	$gis_conf = array_merge($gis_conf, $gis_conf_associations);
	ecrire_config('gis', $gis_conf);

	// Coordonnées
	$coord_conf = lire_config('coordonnees', []);
	$coord_conf['objets'] = array_merge($coord_conf['objets'], ['spip_associations']);
	ecrire_config('coordonnees', $coord_conf);

	// Réseaux sociaux
	$rezos_conf = lire_config('rezosocios', []);
	if (!empty($rezos_conf)) {
		$rezos_conf['rezosocios_objets'] = array_merge($rezos_conf['rezosocios_objets'], ['spip_associations']);
	} else {
		$rezos_conf['rezosocios_objets'] = ['spip_associations'];
	}
	ecrire_config('rezosocios', $rezos_conf);

	// Territoires
	$territoires_conf = lire_config('territoires', []);
	if (empty($territoires_conf)) {
		$territoires_conf['association_objets'] = ['spip_associations'];
	} else {
		$territoires_conf['association_objets'] = array_merge(
			$territoires_conf['association_objets'],
			['spip_associations']
		);
	}
	ecrire_config('territoires', $territoires_conf);
}

/**
 * Mise à jour 1.0.3
 * Ajouter à toutes les adresses existantes (spip_adresses_liens) le type = work
 */
function ata_associations_peupler_type_adresse() {
	$adresses = sql_allfetsel('id_adresse', 'spip_adresses_liens');

	if (count($adresses) > 0) {
		foreach ($adresses as $adresse) {
			$data[] = $adresse['id_adresse'];
		}
		sql_updateq('spip_adresses_liens', ['type' => 'work'], sql_in('id_adresse', $data));
	}
}
