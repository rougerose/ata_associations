<?php

if (!defined("_ECRIRE_INC_VERSION")) {
  return;
}

include_spip('inc/ata_importer_utils');
include_spip('action/editer_objet');
include_spip('action/editer_liens');
include_spip('action/editer_gis');
include_spip('action/editer_rezosocio');
include_spip('inc/filtres');
include_spip('inc/distant');
include_spip('inc/modifier'); // collecter_requests()

function inc_ata_importer_associations($status_file, $redirect = '') {
  spip_log('inc… Entrée dans la fonction', 'ata_import_debug.' ._LOG_INFO_IMPORTANTE);

  // script adapté de inc_sauvegarder_dist
  $status_file = _DIR_TMP . basename($status_file) . '.txt';

  if (lire_fichier($status_file, $status)) {
    $status = unserialize($status);
    
    $timeout = ini_get('max_execution_time');
    if (!$timeout) {
      $timeout = 300;
    }
    $max_time = time() + $timeout / 2;
    
    include_spip('inc/minipres');
    @ini_set('zlib.output_compression', '0'); // pour permettre l'affichage au fur et a mesure

    $titre = _T('ata_associations:info_import_en_cours') . ' (X lignes) ';
    $balise_img = chercher_filtre('balise_img');
    $titre .= $balise_img(chemin_image('searching.gif'));
    
    echo(install_debut_html($titre));
    
    // script de rechargemespip_log('inc_ata_importer_associations', 'ata_import_debug.' ._LOG_INFO_IMPORTANTE);nt auto sur timeout
    echo http_script("window.setTimeout('location.href=\"" . $redirect . "\";'," . ($timeout * 1000) . ')');
    echo "<div style='text-align: left'>\n";
    
    // au premier coup on ne fait rien sauf afficher l'ecran de sauvegarde
    $res = false;
    if (_request('step')) {
      spip_log('inc... : chargement CSV, étape ' . _request('step'), 'ata_import_debug.' ._LOG_INFO_IMPORTANTE);
      $importer_csv = charger_fonction('importer_csv', 'inc');
		  $donnees = $importer_csv($status['fichier']['csv'][0]['tmp_name'], true);
      
      $options = array(
        'callback_progression' => 'ata_importer_afficher_progression',
        'max_time' => $max_time
      );
      $res = ata_importer_donnees_associations($donnees, $status_file, $options);
    }

    echo("</div>\n");

    if (!$res and $redirect) {
      spip_log('inc... : Relance via js', 'ata_import_debug.' ._LOG_INFO_IMPORTANTE);
      echo ata_importer_relance($redirect);
    }
    echo(install_fin_html());
    if (@ob_get_contents()) {
      ob_end_flush();
    }
    flush();

    return $res;
  }
}


function ata_importer_donnees_associations($donnees, $status_file, $options) {
  spip_log('importer_donnees : entrée dans la fonction', 'ata_import_debug.' ._LOG_INFO_IMPORTANTE);
  
  $callback_progression = isset($options['callback_progression']) ? $options['callback_progression'] : '';
  $max_time = isset($options['max_time']) ? $options['max_time'] : 0;

  lire_fichier($status_file, $status);
  $status = unserialize($status);

  if (!isset($status['lignes_importees'])) {
    $status['lignes_importees'] = 0;
  }

  if (!isset($status['lignes_restantes'])) {
    $status['lignes_restantes'] = 0;
  }

  if (!isset($status['lignes_total'])) {
    $status['lignes_total'] = count($donnees);
  }

  if ($status['lignes_total'] == 0) {
    spip_log("Aucune données à importer", 'ata_import_debug.' . _LOG_INFO_IMPORTANTE);
    // Pas de données. Echec et fin.
    return true;
  }

  $status['lignes_restantes'] = $status['lignes_total'] - $status['lignes_importees'];

  // Etape
  $status['etape'] = _request('step');
  
  $log = 'importer_donnees, avant boucle : ';
  $log .= 'Lignes importées = ' . $status['lignes_importees'] . '. ';
  $log .= 'Lignes restantes = ' . $status['lignes_restantes'] . '. ';
  $log .= 'Lignes totales = ' . $status['lignes_total'] . '. ';

  spip_log($log, 'ata_import_debug.' ._LOG_INFO_IMPORTANTE);

  if ($status['lignes_importees'] >= 0 and $status['lignes_restantes'] > 0) {

    if ($status['lignes_importees'] > 0) {
      $offset = $status['lignes_importees'] - 1;
      $donnees = array_slice($donnees, $offset);
      spip_log('Importation des données à partir de la ligne ' . $offset, 'ata_import_debug.' . _LOG_INFO_IMPORTANTE);
    }
    
    if ($callback_progression) {
      // TODO détailler la progression
      //$callback_progression();
    }

    $insertions = array_chunk($donnees, 50);
    $compteur_insertions = 0;
    
    $verifier = charger_fonction('verifier', 'inc');

    // Mémoriser une fois les mots-clés Activités, plutôt que de faire une requête sql
    // à chaque itération d'association
    $mots_cles_sql = sql_allfetsel('id_mot, titre', 'spip_mots','id_groupe_racine=1');
    
    // Mots-clés Activités dans un tableau de la forme id_mot => titre
    // Au passage, les titres sont débarassés de leurs accents, en minuscules et sans "œ".
    $mots_cles_activites = array();
    
    foreach ($mots_cles_sql as  $mot) {
      $titre = strtolower(ata_importer_supprimer_accents($mot['titre']));
			$titre = str_replace('œ', 'oe', $titre);
			$mots_cles_activites[$mot['id_mot']] = $titre;
    }


    /*
    // TEST
    foreach ($insertions as $cle_chunk => $chunk) {
      foreach ($chunk as $cle_asso => $asso) {
        $asso_champs = array('nom' => $asso['nom']);
        $compteur_insertions++;
        $status['lignes_importees'] = $compteur_insertions;
        $status['lignes_restantes'] = $status['lignes_total'] - $status['lignes_importees'];
        
        if ($max_time and time() > $max_time) {
          $log = 'importer_donnees, fin de boucle asso : Timeout. ';
          $log .= 'Lignes importées = ' . $status['lignes_importees'] . '. ';
          $log .= 'Lignes restantes = ' . $status['lignes_restantes'] . '. ';
          spip_log($log, 'ata_import_debug.' ._LOG_INFO_IMPORTANTE);
          break;
        }
      }
      if ($max_time and time() > $max_time) {
        $log = 'importer_donnees, fin de boucle chunk : Timeout. ';
        $log .= 'Lignes importées = ' . $status['lignes_importees'] . '. ';
        $log .= 'Lignes restantes = ' . $status['lignes_restantes'] . '. ';
        spip_log($log, 'ata_import_debug.' ._LOG_INFO_IMPORTANTE);
        break;
      }
    }

    $log = 'importer_donnees, fin de script. ';
    $log .= 'Lignes importées = ' . $status['lignes_importees'] . '. ';
    $log .= 'Lignes restantes = ' . $status['lignes_restantes'] . '. ';
    spip_log($log, 'ata_import_debug.' ._LOG_INFO_IMPORTANTE);
    // FIN TEST
    */

    
    foreach ($insertions as $chunk) {
      foreach ($chunk as $association) {
        // Init des tableaux et des variables
        $champs_asso = $champs_adresse = $champs_email = $champs_rezos = $champs_activites = $champs_gis_partiels = $champs_gis = array();
        $id_association = $id_adresse = $id_gis = $id_email = '';

        $compteur_log = $compteur_insertions + 1;
        
        $association = array_map('trim', $association);
        
        // nom
        $champs_asso['nom'] = $association['nom'];

        // Pas de nom, le traitement de la ligne du tableau est arrêtée ici.
        if (empty($champs_asso['nom'])) {
          spip_log("Association sans nom à la ligne $compteur_log du fichier CSV", 'ata_import_csv.' . _LOG_INFO_IMPORTANTE);
          break;
        }

        // site_internet
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

        // adresse, adresse2, code_postal, ville
        // TODO Ajouter Département et Région
        $champs_adresse = array(
          'titre' => $champs_asso['nom'],
          'voie' => $association['adresse'],
          'complement' => $association['adresse2'],
          'code_postal' => $association['code_postal'],
          'ville' => $association['ville']
        );
        
        // adresse2
        $champs_adresse['complement'] = $association['adresse2'];

        // code_postal
        $champs_adresse['code_postal'] = $association['code_postal'];

        // ville
        $champs_adresse['ville'] = $association['ville'];
        
        // email_1
        $champs_email = array(
          'titre' => $champs_asso['nom'],
          'email' => $association['email_1']
        );

        // facebook, twitter et instagram
        $champs_rezos = array(
          'titre' => $champs_asso['nom'],
          'facebook' => $association['facebook'],
          'instagram' => $association['instagram'],
          'twitter' => $association['twitter']
        );

        // Activités
        // activites_carto
        // activites_creation
        // activites_diffusion
        // activites_formation_ressources
        // activites_transmission
        // residences

        $champs_activites = array(
          'creation' => explode(PHP_EOL, $association['activites_creation']),
          'diffusion' => explode(PHP_EOL,$association['activites_diffusion']),
          'formation' => explode(PHP_EOL, $association['activites_formation_ressources']),
          'transmission' => explode(PHP_EOL, $association['activites_transmission']),
          'residences' => explode(PHP_EOL, $association['residences'])
				);

        // Vérifier si l'association n'est pas en doublon
        // TODO Corriger la recherche du code postal qui est dans la table adresses.
        // if (sql_countsel('spip_associations', array('nom=' . sql_quote(trim($champs['nom'])), 'code_postal=' . sql_quote($champs['code_postal'])))) {
        //   spip_log("Association déjà enregistrée sur le site et présente à la ligne $compteur_log du fichier CSV", 'ata_import_csv.' . _LOG_INFO_IMPORTANTE);
				// 	break;
				// }

        // Insertion des données
        $id_association = objet_inserer('association', null, $champs_asso);

        
        if (intval($id_association)) {
          
          // * Créer l'adresse
          $id_adresse = ata_importer_adresse($id_association, $champs_adresse);
          
          // * Créer le point de géolocalisation
          if (intval($id_adresse)) {
            $champs_gis_partiels = array(
              'titre' => $champs_adresse['titre'],
              'voie' => $champs_adresse['voie'],
              'complement' => $champs_adresse['complement'],
              'code_postal' => $champs_adresse['code_postal'],
              'ville' => $champs_adresse['ville'],
              'pays' => $champs_adresse['pays']
            );
          
            $champs_gis = ata_importer_gis($champs_gis_partiels);

            // Vérifier que les données minimum d'un point (latitude et longitude)
            // sont bien présentes.
            if ($champs_gis['lat'] and $gis_champs['lon']) {
              $id_gis = gis_inserer();

              if (intval($id_gis)) {
                gis_modifier($id_gis, $champs_gis);

                // Associer id_gis et id_association
                objet_associer(
                  array('gis' => $id_gis),
                  array('association' => $id_association)
                );
              }
            }
          } else {
            spip_log("Erreur association #$id_association : l'adresse et la géolocalisation n'ont pas pu être enregistrées.", 'ata_import_csv.' . _LOG_INFO_IMPORTANTE);
          }

          // * Créer l'email 
          if (!in_array(null, $champs_email)) {
            $id_email = ata_importer_email($id_association, $champs_email);

            if (intval($id_email) == 0) {
              spip_log("Erreur association #$id_association : l'email n'a pas pu être enregistré.", 'ata_import_csv.' . _LOG_INFO_IMPORTANTE);
            }
          }

          // * Créer les réseaux sociaux
          if (!empty($champs_rezos['facebook'])
            and !empty($champs_rezos['twitter'])
            and !empty($champs_rezos['instagram'])
          ) {
            ata_importer_rezos($id_association, $champs_rezos);
          }

          // * Activités

          if (!empty($champs_activites['creation'])
          and !empty($champs_activites['diffusion'])
          and !empty($champs_activites['formation'])
          and !empty($champs_activites['transmission'])
          and !empty($champs_activites['residences'])
          ) {
            ata_importer_activites($id_association, $champs_activites, $mots_cles_activites);
          }

        } else {
          spip_log("Erreur : l'association présente à la ligne $compteur_log du fichier CSV n'a pas pu être enregistrée.", 'ata_import_csv.' . _LOG_INFO_IMPORTANTE);
        }

        // TODO: Tester un résultat qui indique que tous les enregistrements pour une asso sont faits.
        $compteur_insertions++;
        $status['lignes_importees'] = $compteur_insertions;
        $status['lignes_restantes'] = $status['lignes_total'] - $status['lignes_importees'];

        if ($max_time and time() > $max_time) {
          $log = 'importer_donnees, fin de boucle asso : Timeout. ';
          $log .= 'Lignes importées = ' . $status['lignes_importees'] . '. ';
          $log .= 'Lignes restantes = ' . $status['lignes_restantes'] . '. ';
          spip_log($log, 'ata_import_debug.' ._LOG_INFO_IMPORTANTE);
          break;
        }
      }
      
      if ($max_time and time() > $max_time) {
        $log = 'importer_donnees, fin de boucle chunk : Timeout. ';
        $log .= 'Lignes importées = ' . $status['lignes_importees'] . '. ';
        $log .= 'Lignes restantes = ' . $status['lignes_restantes'] . '. ';
        spip_log($log, 'ata_import_debug.' ._LOG_INFO_IMPORTANTE);
        break;
      }
    }
    // Todo: ajouter progression ?
    $log = 'importer_donnees, fin des boucles. ';
    $log .= 'Lignes importées = ' . $status['lignes_importees'] . '. ';
    $log .= 'Lignes restantes = ' . $status['lignes_restantes'] . '. ';
    spip_log($log, 'ata_import_debug.' ._LOG_INFO_IMPORTANTE);

    ecrire_fichier($status_file, serialize($status));
    if ($max_time and time() > $max_time and $status['lignes_restantes'] > 0) {
      $log = "importer_donnees, fin des boucle : Timeout + return false. Lignes restantes = ";
      $log .= $status['lignes_restantes'];
      spip_log($log, 'ata_import_debug.' ._LOG_INFO_IMPORTANTE);
      // l'import n'est pas terminé, mais le temps imparti est écoulé.
      return false;
    }
  }
  spip_log('importer_donnees, fin du script : Return true', 'ata_import_debug.' ._LOG_INFO_IMPORTANTE);
  // import terminé
  return true;
}

function ata_importer_adresse($id_association, $champs_adresse) {
  $set['pays'] = _COORDONNEES_PAYS_DEFAUT; // FR

  $id_adresse = objet_inserer('adresse', null, $champs_adresse);

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

function ata_importer_email($id_association, $champs_email) {
  $id_email = objet_inserer('email', null, $champs_email);

  if (intval($id_email)) {
    objet_associer(
      array('email' => $id_email),
      array('association' => $id_association),
      array('type' => _COORDONNEES_TYPE_DEFAUT) // work
    );

    return $id_email;
  } 
  return '';
}

function ata_importer_rezos($id_association, $champs_rezos) {
  $rezos = array(
    'facebook' => $champs_rezos['facebook'],
    'instagram' => $champs_rezos['instagram'],
    'twitter' => $champs_rezos['twitter']
  );

  foreach ($rezos as $type_rezo => $rezo) {
    $nom_rezo = rezosocios_nom($type_rezo);

    if ($rezo) {
      $champs = array(
        'titre' => $set['titre'],
        'nom_compte' => $rezo,
        'type_rezo' => $type_rezo
      );
      
      $id_rezo = rezosocio_inserer();

      if (intval($id_rezo)) {
        rezosocio_modifier($id_rezo, $champs);
        objet_associer(
          array('rezosocio' => $id_rezo),
          array('association' => $id_association)
        );
      } else {
        spip_log("Erreur association #$id_association : le compte $type_rezo n'a pas pu être enregistré.", 'ata_import_csv.' . _LOG_INFO_IMPORTANTE);
      }
    }
  }
}

function ata_importer_activites($id_association, $champs_activites, $mots_cles_activites) {
  $ids = array();

  foreach($champs_activites as $activites) {
    foreach($activites as $activite) {
      $titre = strtolower(ata_importer_supprimer_accents($activite));
      $titre = str_replace('œ', 'oe', $titre);
      $i = array_search($titre, $mots_cles_activites);
      if ($i) {
        $ids[] = $i;
      }
    }
  }

  if (count($ids)) {
    objet_associer(
      array('mot' => $ids),
      array('association', $id_association)
    );
  }
}

function ata_importer_gis($champs_gis) {
	// Ecrire la requête de géo-localisation
	$query = '';

  $query_pays = 'France';
  $query = $champs_gis['voie'] ? $champs_gis['voie'] . ' ' : '';
  $query .= $champs_gis['complement'] ? $champs_gis['complement'] . ' ' : '';
  $query .= $champs_gis['code_postal'] ? $champs_gis['code_postal'] . ' ' : '';
  $query .= $champs_gis['ville'] ? $champs_gis['ville'] . ' ' : '';
	$query .= $query_pays;

	$query_langue = 'fr';

	set_request("mode","search");
	set_request("q", $query);
	set_request("accept-language", $query_langue);
	set_request("format","json");

	// $arguments = collecter_requests(array('json_callback', 'format', 'q', 'limit', 'addressdetails', 'accept-language', 'lat', 'lon'), array());

  // Envoi de la requête
	$requete = ata_importer_geocoder_rechercher();

  $gis = null;

	if (is_array($requete) and count($requete['features'])) {
		foreach ($requete['features'] as $key => $feature) {
			// On recherche dans la réponse le premier élément
			// dont les données sont town, city, village ou
			// suburb
			//
			// suburb : pour les arrondissements

			if ($feature['properties']['osm_key'] === 'place'
				and preg_match(
					'/town|city|village|suburb/',
					$feature['properties']['osm_value']
				))
			{
				$req_pays = $feature['properties']['country'];
				$req_region = $feature['properties']['state'];

				// Vérifier qu'il ne s'agit pas d'un arrondissement,
				// le nom de la ville est sur une clé différente.
				if ($feature['properties']['osm_value'] == 'suburb') {
					$req_ville = $feature['properties']['city'];
				} else {
					$req_ville = $feature['properties']['name'];
				}

				$req_code_postal = $feature['properties']['postcode'];
				$req_longitude = $feature['geometry']['coordinates'][0];
				$req_latitude = $feature['geometry']['coordinates'][1];

				$config_zoom = lire_config('gis/zoom');

				// Données du point GIS à enregistrer en base
				$gis = array(
					'titre' => $champs_gis['titre'],
					'lat' => $req_latitude,
					'lon' => $req_longitude,
					'zoom' => $config_zoom,
					'adresse' => $champs_gis['voie'],
					'pays' => $req_pays,
					'region' => $req_region,
					'ville' => $req_ville,
					'code_postal' => $req_code_postal
				);

				// Arrêt de la boucle
				break;
			}
		}
	}
	return $gis;
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
			$url = 'http://photon.komoot.de/';
		} else {
			$url = 'http://nominatim.openstreetmap.org/';
		}

		$url = defined('_GIS_GEOCODER_URL') ? _GIS_GEOCODER_URL : $url;
		$data = recuperer_page("{$url}{$mode}?" . http_build_query($arguments));
    $data = json_decode($data, true);
    return $data;
  }
}