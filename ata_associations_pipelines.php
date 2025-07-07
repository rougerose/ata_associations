<?php

/**
 * Utilisations de pipelines par ATA associations
 *
 * @plugin     ATA associations
 * @copyright  2020
 * @author     christophe le drean
 * @licence    GNU/GPL v3
 * @package    SPIP\Ata_associations\Pipelines
 */

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

/**
 * Ajouter les objets sur les vues des parents directs
 *
 * @pipeline affiche_enfants
 * @param  array $flux Données du pipeline
 * @return array       Données du pipeline
 **/
function ata_associations_affiche_enfants($flux) {
	if ($e = trouver_objet_exec($flux['args']['exec']) and $e['edition'] == false) {
		$id_objet = $flux['args']['id_objet'];

		if ($e['type'] == 'rubrique') {
			$flux['data'] .= recuperer_fond(
				'prive/objets/liste/associations',
				[
					'titre' => _T('association:titre_associations_rubrique'),
					'id_rubrique' => $id_objet,
				]
			);

			if (autoriser('creerassociationdans', 'rubrique', $id_objet)) {
				include_spip('inc/presentation');
				$flux['data'] .= icone_verticale(
					_T('association:icone_creer_association'),
					generer_url_ecrire('association_edit', "id_rubrique=$id_objet"),
					'association-24.png',
					'new',
					'right'
				) . "<br class='nettoyeur' />";
			}
		}
	}
	return $flux;
}

/**
 * Afficher le nombre d'éléments dans les parents
 *
 * @pipeline boite_infos
 * @param  array $flux Données du pipeline
 * @return array       Données du pipeline
 **/
function ata_associations_boite_infos($flux) {
	if (isset($flux['args']['type']) and isset($flux['args']['id']) and $id = intval($flux['args']['id'])) {
		$texte = '';
		if ($flux['args']['type'] == 'rubrique' and $nb = sql_countsel(
			'spip_associations',
			["statut='publie'", 'id_rubrique=' . $id]
		)) {
			$texte .= '<div>' . singulier_ou_pluriel(
				$nb,
				'association:info_1_association',
				'association:info_nb_associations'
			) . "</div>\n";
		}
		if ($flux['args']['type'] == 'rubrique' and $nb = sql_countsel(
			'spip_associations_imports',
			["statut='publie'", 'id_rubrique=' . $id]
		)) {
			$texte .= '<div>' . singulier_ou_pluriel(
				$nb,
				'associations_import:info_1_associations_import',
				'associations_import:info_nb_associations_imports'
			) . "</div>\n";
		}
		if ($texte and $p = strpos($flux['data'], '<!--nb_elements-->')) {
			$flux['data'] = substr_replace($flux['data'], $texte, $p, 0);
		}
	}
	return $flux;
}

/**
 * Compter les enfants d'un objet
 *
 * @pipeline objets_compte_enfants
 * @param  array $flux Données du pipeline
 * @return array       Données du pipeline
 **/
function ata_associations_objet_compte_enfants($flux) {
	if ($flux['args']['objet'] == 'rubrique' and $id_rubrique = intval($flux['args']['id_objet'])) {
		// juste les publiés ?
		if (array_key_exists('statut', $flux['args']) and ($flux['args']['statut'] == 'publie')) {
			$flux['data']['associations'] = sql_countsel(
				'spip_associations',
				'id_rubrique= ' . intval($id_rubrique) . " AND (statut = 'publie')"
			);
		} else {
			$flux['data']['associations'] = sql_countsel(
				'spip_associations',
				'id_rubrique= ' . intval($id_rubrique) . " AND (statut <> 'poubelle')"
			);
		}
	}

	return $flux;
}

/**
 * Optimiser la base de données
 *
 * Supprime les objets à la poubelle.
 * Supprime les objets à la poubelle.
 * Supprime les objets à la poubelle.
 *
 * @pipeline optimiser_base_disparus
 * @param  array $flux Données du pipeline
 * @return array       Données du pipeline
 */
function ata_associations_optimiser_base_disparus($flux) {

	sql_delete('spip_associations', "statut='poubelle' AND maj < " . $flux['args']['date']);

	sql_delete('spip_associations_imports_sources', "statut='poubelle' AND maj < " . $flux['args']['date']);

	sql_delete('spip_associations_imports', "statut='poubelle' AND maj < " . $flux['args']['date']);

	return $flux;
}

/**
 * Synchroniser la valeur de id secteur
 *
 * @pipeline trig_propager_les_secteurs
 * @param  string $flux Données du pipeline
 * @return string       Données du pipeline
 **/
function ata_associations_trig_propager_les_secteurs($flux) {

	// synchroniser spip_associations
	$r = sql_select(
		'A.id_association AS id, R.id_secteur AS secteur',
		'spip_associations AS A, spip_rubriques AS R',
		'A.id_rubrique = R.id_rubrique AND A.id_secteur <> R.id_secteur'
	);
	while ($row = sql_fetch($r)) {
		sql_update('spip_associations', ['id_secteur' => $row['secteur']], 'id_association=' . $row['id']);
	}

	return $flux;
}

/**
	TODO:
	- Utiliser le pipeline pour forcer un type travail uniquement ? Reste à faire...
 */
// function ata_associations_types_coordonnees($liste) {
// 	$types_adresses = $liste['adresse'];
// 	if (!$types_adresses or !is_array($types_adresses)) {
// 		$types_adresses = [];
// 	}
// 	// on définit les couples types + chaînes de langue à ajouter
// 	$types_adresses_asso = [
// 		'work' => 'Travail',
// 	];
// 	// on les rajoute à la liste des types des adresses
// 	$liste['adresse'] = array_merge($types_adresses, $types_adresses_asso);
// 	return $liste;
// }

/**
 * Réduire la liste des réseaux sociaux à (facebook, twitter, instagram)
 * qui seront les seuls pris en compte dans les exports json des associations.
 *
 * Utilise le pipeline du plugin rezosocios
 *
 * @param  array $flux
 * @return array
 */
function ata_associations_rezosocios_liste($flux) {
	$rezos = [
		'facebook' => $flux['facebook'],
		'twitter' => $flux['twitter'],
		'instagram' => $flux['instagram'],
	];
	$flux = $rezos;

	return $flux;
}
