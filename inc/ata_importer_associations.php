<?php

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

include_spip('inc/ata_importer_utils');
include_spip('action/editer_objet');
include_spip('action/editer_liens');
include_spip('action/editer_gis');
include_spip('action/editer_rezosocio');
include_spip('inc/distant');
include_spip('inc/modifier'); // collecter_requests()

function inc_ata_importer_associations($id_associations_import = 0, $id_rubrique = '', $nb_max = 50, $maxtime = 30) {
	$nb_restant = $nb_max;
	$res = array();

	if (!$id_associations_import) {
		spip_log('Identifiant id_associations_import = 0', 'ata_importer_csv.' . _LOG_INFO_IMPORTANTE);
		return array('message_erreur' => 'Aucune données à importer, veuillez recommencer.', 'nb' => $nb_max);
	}

	$associations = sql_allfetsel(
		'*',
		'spip_associations_imports_sources',
		array('id_associations_import=' . intval($id_associations_import), 'statut=1'),
		'',
		'',
		"0, $nb_max"
	);

	if (count($associations)) {
		foreach ($associations as $asso) {
			// Eviter un timeout
			if (time() >= $maxtime) {
				$res['message_ok'] = "Le lot a été importé en partie : sur les $nb_max à importer, ";
				$res['message_ok'] .= "$nb_restant restent encore à traiter.";
				$res['nb'] = $nb_restant;
				return $res;
			}
			// rubrique liée à l'association
			$asso['id_rubrique'] = $id_rubrique;
			$id_association = ata_importer_inserer_association($asso);
			sql_updateq(
				'spip_associations_imports_sources',
				array('statut' => 0),
				'id_associations_imports_source=' . intval($asso['id_associations_imports_source'])
			);
			sql_update(
				'spip_associations_imports',
				array('encours' => 'encours+1'),
				'id_associations_import=' . intval($id_associations_import)
			);
			$nb_restant--;
		}
	}

	// Reste-t-il des associations à importer ?
	$nb_restant = sql_countsel(
		'spip_associations_imports_sources',
		array('id_associations_import=' . intval($id_associations_import), 'statut=1')
	);

	if ($nb_restant == 0) {
		// On a terminé, mettre à jour le compteur
		sql_updateq(
			'spip_associations_imports',
			array('statut' => 'end'),
			'id_associations_import=' . intval($id_associations_import)
		);
		$res['message_ok'] = 'Import terminé.';
		$res['nb'] = $nb_restant;
		return $res;
	}

	if ($nb_restant > 0 or time() >= $maxtime) {
		$res['message_ok'] = "La totalité du lot a été importé. $nb_restant associations restent à importer.";
		$res['nb'] = $nb_restant;
		return $res;
	}
}


function ata_importer_inserer_association($asso) {
	// Chercher un éventuel doublon
	$ids_nom = sql_allfetsel('id_association', 'spip_associations', 'nom=' . sql_quote($asso['nom']));
	if (count($ids_nom)) {
		$from = 'spip_adresses AS L2 INNER JOIN spip_adresses_liens AS L1 ON (L1.id_adresse = L2.id_adresse)';
		$where = array(
			sql_in('L1.id_objet', $ids_nom),
			'L1.objet=' . sql_quote('association'),
			'L2.code_postal=' . sql_quote($asso['code_postal'])
		);
		$ids_code = sql_allfetsel('L2.id_adresse', $from, $where);

		if (count($ids_code)) {
			spip_log(
				'Association ' . $asso['nom'] . " déjà présente : les données n'ont pas été importées.",
				'ata_import_csv_doublons.' . _LOG_INFO_IMPORTANTE
			);
			// Doublon = Abandon
			return 0;
		}
	}

	$verifier = charger_fonction('verifier', 'inc');
	$erreur_url = $verifier($asso['url_site'], 'url', array('mode' => 'protocole_seul', 'type_protocole' => 'web'));
	if ($erreur_url) {
		$asso['url_site'] = substr_replace($asso['url_site'], 'http://', 0, 0);
	}
	$champs_asso = array(
		'nom' => $asso['nom'],
		'url_site' => $asso['url_site'],
		'membre_fraap' => $asso['membre_fraap'],
	);

	$id_association = objet_inserer('association', $asso['id_rubrique'], $champs_asso);

	if (intval($id_association) == 0) {
		return 0;
	} else {
		//* Adresse
		// adresse, adresse2, code_postal, ville
		$champs_adresse = array(
			'titre' => $champs_asso['nom'],
			'voie' => $asso['voie'] ? $asso['voie'] : '',
			'complement' => $asso['complement'] ? $asso['complement']  : '',
			'code_postal' => $asso['code_postal'] ? $asso['code_postal'] : '',
			'ville' => $asso['ville'] ? $asso['ville'] :  ''
		);

		$id_adresse = 0;
		$id_adresse = ata_importer_inserer_adresse($id_association, $champs_adresse);

		//* Territoires
		if (intval($id_adresse) and $champs_adresse['code_postal']) {
			$champs_territoires = array(
				'pays' => _COORDONNEES_PAYS_DEFAUT, // FR
				'code_postal' => $champs_adresse['code_postal']
			);
			ata_importer_inserer_territoires($id_association, $champs_territoires);
		}

		//* Géolocalisation
		// Utiliser les coordonnées déjà fournies
		if ($asso['lat'] && $asso['lon']) {
			$id_gis = gis_inserer();
			$gis = array(
				'titre' => $champs_adresse['titre'],
				'lat' => $asso['lat'],
				'lon' => $asso['lon'],
				'zoom' => lire_config('gis/zoom')
			);

			if (intval($id_gis)) {
				gis_modifier($id_gis, $gis);
				objet_associer(
					array('gis' => $id_gis),
					array('association' => $id_association)
				);
			}
		} else {
			// Sinon obtenir les coordonnées par le geocoder
			if (intval($id_adresse)) {
				$champs_adresse_gis = $champs_adresse;
				$champs_adresse_gis['pays'] = 'France';

				$id_gis = ata_importer_inserer_gis($id_association, $champs_adresse_gis);
			}
		}

		//* Email
		if (!empty($asso['email'])) {
			$champs_email = array(
				'titre' => $champs_asso['nom'],
				'email' => $asso['email']
			);
			$id_email = ata_importer_inserer_email($id_association, $champs_email);
		}

		//* Réseaux sociaux
		if (!empty($asso['facebook']) or !empty($asso['twitter'] or !empty($asso['instagram']))) {
			$champs_rezos = array(
				'titre' => $champs_asso['nom'],
				'facebook' => $asso['facebook'],
				'twitter' => $asso['twitter'],
				'instagram' => $asso['instagram']
			);
			ata_importer_inserer_rezos($id_association, $champs_rezos);
		}

		//* Activités
		$champs_activites = array(
			'creation' => unserialize($asso['creation']),
			'diffusion' => unserialize($asso['diffusion']),
			'formation' => unserialize($asso['formation']),
			'transmission' => unserialize($asso['transmission']),
			'residences' => unserialize($asso['residences'])
		);

		if ($count = array_sum($champs_activites['creation'])
			+ array_sum($champs_activites['diffusion'])
			+ array_sum($champs_activites['formation'])
			+ array_sum($champs_activites['transmission'])
			+ array_sum($champs_activites['residences'] > 0)
		) {
			ata_importer_inserer_activites($id_association, $champs_activites);
		}

		//* Publier ?
		if ($asso['publier']) {
			$publier = objet_modifier('association', $id_association, array('statut' => 'publie'));
		}
		return $id_association;
	}
}


function ata_importer_inserer_adresse($id_association, $champs) {
	$champs['pays'] = _COORDONNEES_PAYS_DEFAUT; // FR

	$id_adresse = objet_inserer('adresse', null, $champs);

	if (intval($id_adresse)) {
		objet_associer(
			array('adresse' => $id_adresse),
			array('association' => $id_association),
			array('type' => _COORDONNEES_TYPE_DEFAUT) // work
		);
		return $id_adresse;
	}
	spip_log("id $id_association", 'ata_import_csv_adresse.' . _LOG_INFO_IMPORTANTE);
	return '';
}

function ata_importer_inserer_territoires($id_association, $champs) {
	$iso_territoire = $champs['pays'] . '-' . substr($champs['code_postal'], 0, 2);
	$departement = sql_allfetsel(
		'id_territoire, iso_parent',
		'spip_territoires',
		'iso_territoire=' . sql_quote($iso_territoire)
	);
	if ($departement[0]['iso_parent']) {
		$id_region = sql_getfetsel(
			'id_territoire',
			'spip_territoires',
			'iso_territoire=' . sql_quote($departement[0]['iso_parent'])
		);
		if (intval($id_region)) {
			objet_associer(
				array('territoire' => array($departement[0]['id_territoire'], $id_region)),
				array('association' => $id_association)
			);
		} else {
			spip_log("id $id_association", 'ata_import_csv_territoires.' . _LOG_INFO_IMPORTANTE);
		}
	}
}

function ata_importer_inserer_email($id_association, $champs) {
	$id_email = '';
	$id_email = objet_inserer('email', null, $champs);

	if (intval($id_email)) {
		objet_associer(
			array('email' => $id_email),
			array('association' => $id_association),
			array('type' => _COORDONNEES_TYPE_DEFAUT)
		);
	} else {
		spip_log("id $id_association", 'ata_import_csv_email.' . _LOG_INFO_IMPORTANTE);
	}
	return $id_email;
}

function ata_importer_inserer_rezos($id_association, $champs) {
	$rezos = array(
		'facebook' => $champs['facebook'],
		'twitter' => $champs['twitter'],
		'instagram' => $champs['instagram']
	);

	foreach ($rezos as $type_rezo => $rezo) {
		$id_rezo = '';
		$nom_rezo = rezosocios_nom($type_rezo);
		if (!empty($rezo)) {
			$row = array(
				'titre' => $champs['titre'],
				'nom_compte' => $rezo,
				'type_rezo' => $type_rezo
			);
			$id_rezo = rezosocio_inserer();
		}

		if (intval($id_rezo)) {
			rezosocio_modifier($id_rezo, $row);
			objet_associer(
				array('rezosocio' => $id_rezo),
				array('association' => $id_association)
			);
		}

		if (intval($id_rezo) == 0 and !empty($rezo)) {
			spip_log("id $id_association, type rézo : $type_rezo", 'ata_import_csv_rezos.' . _LOG_INFO_IMPORTANTE);
		}
	}
}

function ata_importer_inserer_activites($id_association, $champs_activites) {
	foreach ($champs_activites as $activites) {
		foreach ($activites as $activite) {
			if ($activite) {
				objet_associer(
					array('mot' => $activite),
					array('association' => $id_association)
				);
			}
		}
	}
}


function ata_importer_inserer_gis($id_association, $champs_adresse_gis) {
	$adresse = '';
	$adresse = $champs_adresse_gis['voie'] ? $champs_adresse_gis['voie'] . ' ' : '';
	// $adresse .= $champs_adresse_gis['complement'] ? $champs_adresse_gis['complement'] . ' ' : '';
	$adresse .= $champs_adresse_gis['code_postal'] ? $champs_adresse_gis['code_postal'] . ' ' : '';
	$adresse .= $champs_adresse_gis['ville'] ? $champs_adresse_gis['ville'] . ' ' : '';
	$adresse .= 'France';

	$query = '';
	$query = $adresse;
	$query_langue = 'fr';

	set_request('mode', 'search');
	set_request('q', $query);
	set_request('accept-language', $query_langue);
	set_request('format', 'json');

	$requete = ata_importer_geocoder_rechercher();

	$gis = '';

	if (is_array($requete) and count($requete['features'][0])) {
		$place = $requete['features'][0];
		$gis = array();
		$street_components = array();

		if ($place['properties']['country']) {
			$gis['pays'] = $place['properties']['country'];
		}

		if ($place['properties']['countrycode']) {
			$gis['code_pays'] = $place['properties']['countrycode'];
		}

		if ($place['properties']['state']) {
			$gis['region'] = $place['properties']['state']; //
		}

		if ($place['properties']['osm_key'] === 'place'
			and preg_match('/town|city|village|suburb|hamlet/', $place['properties']['osm_value'])
		) {
			$gis['ville'] = $place['properties']['name'];
		} elseif ($place['properties']['village']) {
			$gis['ville'] = $place['properties']['village'];
		} elseif ($place['properties']['town']) {
			$gis['ville'] = $place['properties']['town'];
		} elseif ($place['properties']['city']) {
			$gis['ville'] = $place['properties']['city'];
		}

		if ($place['properties']['postcode']) {
			$gis['code_postal'] = $place['properties']['postcode'];
		}

		if ($place['properties']['street']) {
			$street_components[] = $place['properties']['street'];
		} elseif ($place['properties']['road']) {
			$street_components[] = $place['properties']['road'];
		} elseif ($place['properties']['pedestrian']) {
			$street_components[] = $place['properties']['pedestrian'];
		}

		if ($place['properties']['housenumber']) {
			array_unshift($street_components, $place['properties']['housenumber']);
		}

		if (count($street_components) > 0) {
			$gis['adresse'] = implode(' ', $street_components);
		}

		$gis['lon'] = $place['geometry']['coordinates'][0];
		$gis['lat'] = $place['geometry']['coordinates'][1];
		$gis['zoom'] = lire_config('gis/zoom');
		$gis['titre'] = $champs_adresse_gis['titre'];
	}

	$id_gis = 0;

	if ($gis['lon'] and $gis['lat']) {
		$id_gis = gis_inserer();

		if (intval($id_gis)) {
			gis_modifier($id_gis, $gis);
			objet_associer(
				array('gis' => $id_gis),
				array('association' => $id_association)
			);
		}
	} else {
		spip_log("id $id_association, requete : $query", 'ata_import_csv_geoloc.' . _LOG_INFO_IMPORTANTE);
	}
	return $id_gis;
}


// Fonction adaptée de action_gis_geocoder_rechercher()
// et de https://contrib.spip.net/Astuces-GIS#Utiliser-le-geocoder-depuis-PHP
function ata_importer_geocoder_rechercher() {
	$mode = _request('mode');
	if (!$mode || !in_array($mode, array('search', 'reverse'))) {
		return;
	}

	/* On filtre les arguments à renvoyer à Nomatim (liste blanche) */
	$arguments = collecter_requests(
		array('format', 'q', 'limit', 'addressdetails', 'accept-language', 'lat', 'lon'),
		array()
	);

	$geocoder = defined('_GIS_GEOCODER') ? _GIS_GEOCODER : 'photon';

	if ($geocoder == 'photon') {
		unset($arguments['format']);
		unset($arguments['addressdetails']);
	}

	if (!empty($arguments) && in_array($geocoder, array('photon','nominatim'))) {
		header('Content-Type: application/json; charset=UTF-8');
		if ($geocoder == 'photon') {
			if (isset($arguments['accept-language'])) {
				// ne garder que les deux premiers caractères du code de langue,
				// car les variantes spipiennes comme fr_fem posent problème
				$arguments['lang'] = substr($arguments['accept-language'], 0, 2);
				unset($arguments['accept-language']);
			}
			if ($mode == 'search') {
				$mode = 'api/';
			} else {
				$mode = 'reverse';
			}
			$url = 'http://photon.komoot.io/';
		} else {
			$url = 'http://nominatim.openstreetmap.org/';
		}

		$url = defined('_GIS_GEOCODER_URL') ? _GIS_GEOCODER_URL : $url;
		$data = recuperer_page("{$url}{$mode}?" . http_build_query($arguments));
		$data = json_decode($data, true);
		return $data;
	}
}
