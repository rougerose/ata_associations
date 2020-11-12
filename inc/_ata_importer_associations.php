<?php

if (!defined("_ECRIRE_INC_VERSION")) {
  return;
}

include_spip('inc/ata_importer_utils');
include_spip('action/editer_objet');
include_spip('action/editer_liens');
include_spip('action/editer_gis');
include_spip('action/editer_rezosocio');
include_spip('inc/distant');
include_spip('inc/modifier'); // collecter_requests()

function inc_ata_importer_associations($donnees_csv) {

  if (count($donnees_csv)) {
    $verifier = charger_fonction('verifier', 'inc');
    
    $cpt = 0;
    
    // Mémoriser une fois les mots-clés Activités, plutôt que de faire une requête sql
    // à chaque itération d'association
    $mots = sql_allfetsel('id_mot, titre', 'spip_mots','id_groupe_racine=1');
    
    // Mots-clés Activités dans un tableau de la forme id_mot => titre
    // Au passage, les titres sont débarassés de leurs accents, en minuscules et sans "œ".
    $mots_activites = array();
    
    foreach ($mots as $mot) {
      $titre = strtolower(ata_importer_supprimer_accents($mot['titre']));
      $titre = str_replace('œ', 'oe', $titre);
      $mots_activites[$mot['id_mot']] = $titre;
    }

    foreach($donnees_csv as $association) {
      $cpt = $cpt + 1;

      $association = array_map('trim', $association);
      
      //* Nom de l'association
      $champs_asso = array('nom' => $association['nom']);
      $nom = $champs_asso['nom'];

      // Rechercher si l'association n'existe pas déjà dans la base
      unset($ids_nom, $ids_code);
      $ids_nom = sql_allfetsel('id_association', 'spip_associations', 'nom=' . sql_quote($association['nom']));
      if (count($ids_nom)) {
        $from = 'spip_adresses AS L2 INNER JOIN spip_adresses_liens AS L1 ON (L1.id_adresse = L2.id_adresse)';
        $where = array(
			    sql_in('L1.id_objet', $ids_nom),
			    'L1.objet=' . sql_quote('association'),
			    'L2.code_postal=' . sql_quote($association['code_postal'])
        );
        $ids_code = sql_allfetsel('L2.id_adresse', $from, $where);
      }

      if (empty($champs_asso['nom'])) {
        spip_log("Association ligne $cpt du fichier CSV : Aucun nom, les données n'ont pas été importées.", 'ata_import_csv.' . _LOG_INFO_IMPORTANTE);
        continue;
      } elseif (!empty($ids_code) and count($ids_code) > 0) {
        spip_log("Association $nom (ligne $cpt du fichier CSV) déjà présente : les données n'ont pas été importées.", 'ata_import_csv.' . _LOG_INFO_IMPORTANTE);
        continue;
      }

      //* Site internet
      $erreur_url = $verifier(
        $association['site_internet'],
        'url', 
        array(
          'mode' => 'protocole_seul',
          'type_protocole' => 'web'
        )
      );
      if ($erreur_url) {
        $association['site_internet'] = substr_replace($association['site_internet'], 'http://', 0, 0);
      }
      $champs_asso['url_site'] = $association['site_internet'];

      // membre_fraap
      $membre_fraap = strtolower($association['membre_fraap']);
      if ($membre_fraap == '' or $membre_fraap == 'non') {
        $champs_asso['membre_fraap'] = 0;
      } else {
        $champs_asso['membre_fraap'] = 1;
      }        

      $id_association = 0;
      $id_association = objet_inserer('association', null, $champs_asso);

      if (intval($id_association)) {
        //* Adresse
        // adresse, adresse2, code_postal, ville
        // TODO Ajouter Département et Région
        $champs_adresse = array(
          'titre' => $champs_asso['nom'],
          'voie' => $association['adresse'] ? $association['adresse'] : '',
          'complement' => $association['adresse2'] ? $association['adresse2']  : '',
          'code_postal' => $association['code_postal'] ? $association['code_postal'] : '',
          'ville' => $association['ville'] ? $association['ville'] :  ''
        );
        $id_adresse = 0;
        $id_adresse = ata_importer_inserer_adresse($id_association, $champs_adresse);

        //* Géolocalisation
        if (intval($id_adresse)) {
          $champs_adresse_gis = $champs_adresse;
          $champs_adresse_gis['pays'] = 'France';

          $id_gis = ata_importer_inserer_gis($id_association, $champs_adresse_gis);

          if (intval($id_gis) == 0) {
            spip_log("Association $nom, id $id_association : géolocalisation impossible", 'ata_import_csv.' . _LOG_INFO_IMPORTANTE);
          }
        }

        //* Email
        if (!empty($association['email_1'])) {
          $champs_email = array(
            'titre' => $champs_asso['nom'],
            'email' => $association['email_1']
          );
          $id_email = ata_importer_inserer_email($id_association, $champs_email);
          
          if (intval($id_email) == 0) {
            spip_log("Association $nom, id $id_association : l'adresse email n'a pas été importée.", 'ata_import_csv.' . _LOG_INFO_IMPORTANTE);
          }
        }
      }

      //* Réseaux sociaux
      if (!empty($association['facebook']) or !empty($association['twitter'] or !empty($association['instagram']))) {
        $champs_rezos = array(
          'titre' => $champs_asso['nom'],
          'facebook' => $association['facebook'],
          'twitter' => $association['twitter'],
          'instagram' => $association['instagram']
        );
        ata_importer_inserer_rezos($id_association, $champs_rezos);
      }

      //* Activités
      $champs_activites = array(
        'creation' => explode(PHP_EOL, $association['activites_creation']),
        'diffusion' => explode(PHP_EOL,$association['activites_diffusion']),
        'formation' => explode(PHP_EOL, $association['activites_formation_ressources']),
        'transmission' => explode(PHP_EOL, $association['activites_transmission']),
        'residences' => explode(PHP_EOL, $association['residences'])
      );
      
      if (!empty($champs_activites['creation']) or !empty($champs_activites['diffusion']) or !empty($champs_activites['formation']) or !empty($champs_activites['transmission']) or !empty($champs_activites['residences'])) {
        ata_importer_inserer_activites($id_association, $champs_activites, $mots_activites);
      }
    }
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
  return '';
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
  }
  return $id_email;
}

function ata_importer_inserer_rezos($id_association, $champs) {
  $rezos = array(
    'facebook' => $champs['facebook'],
    'twitter' => $champs['twitter'],
    'instagram' => $champs['instagram']
  );

  foreach($rezos as $type_rezo => $rezo) {
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
      $nom = $row['titre'];
      spip_log("Association $nom, id $id_association : le compte $type_rezo n'a pas été enregistré.", 'ata_import_csv.' . _LOG_INFO_IMPORTANTE);
    }
  }
}

function ata_importer_inserer_activites($id_association, $champs_activites, $mots_activites) {
  $ids = array();

  foreach($champs_activites as $activites) {
    foreach($activites as $activite) {
      if (!empty($activite)) {
        $index = '';
        $titre = strtolower(ata_importer_supprimer_accents($activite));
        $titre = str_replace('œ', 'oe', $titre);
        $index = array_search($titre, $mots_activites);

        if ($index) {
          $ids[] = $index;
        }
      }
    }
  }

  if (count($ids) > 0) {
    objet_associer(
      array('mot' => $ids),
      array('association' => $id_association)
    );
  }
}


function ata_importer_inserer_gis($id_association, $champs_adresse_gis) {
  $adresse = '';
  $adresse = $champs_adresse_gis['voie'] ? $champs_adresse_gis['voie'] . ' ' : '';
  $adresse .= $champs_adresse_gis['complement'] ? $champs_adresse_gis['complement'] . ' ' : '';
  $adresse .= $champs_adresse_gis['code_postal'] ? $champs_adresse_gis['code_postal'] . ' ' : '';
  $adresse .= $champs_adresse_gis['ville'] ? $champs_adresse_gis['ville'] . ' ' : '';
  $adresse .= 'France';
  
  $query = '';
  $query = $adresse;
  $query_langue = 'fr';

	set_request("mode","search");
	set_request("q", $query);
	set_request("accept-language", $query_langue);
  set_request("format","json");
  
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

    if ($place['properties']['osm_key'] === 'place' and preg_match('/town|city|village|suburb|hamlet/', $place['properties']['osm_value'])) {
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
	$arguments = collecter_requests(array('format', 'q', 'limit', 'addressdetails', 'accept-language', 'lat', 'lon'), array());

	$geocoder = defined('_GIS_GEOCODER') ? _GIS_GEOCODER : 'photon';
	
	if ($geocoder == 'photon') {
		unset($arguments['format']);
		unset($arguments['addressdetails']);
	}

	if (!empty($arguments) && in_array($geocoder, array('photon','nominatim'))) {
		header('Content-Type: application/json; charset=UTF-8');
		if ($geocoder == 'photon') {
			if (isset($arguments['accept-language'])) {
				// ne garder que les deux premiers caractères du code de langue, car les variantes spipiennes comme fr_fem posent problème
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