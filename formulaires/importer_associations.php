<?php

if (!defined("_ECRIRE_INC_VERSION")) {
	return;
}

include_spip('inc/cvtupload');
include_spip('inc/saisies');
include_spip('inc/ata_importer_utils');


function formulaires_importer_associations_saisies(){
	static $saisies;
	
	if (!$saisies == null) {
		return $saisies;
	}
	
	$saisies = array(
		array(
			'saisie' => 'hidden',
			'options' => array(
				'nom' => 'progress',
				'defaut' => 'off'
			)
		),
		array(
			'saisie' =>'fichiers',
			'options' => array(
				'nom' => 'csv',
				'label' => _T('ata_associations:label_importer_fichier'),
				'nb_fichiers' => 1,
				'obligatoire' => 'oui'
			), 
			'verifier' => array(
				'type' => 'fichiers',
				'options' => array(
					'mime' => 'specifique',
					'mime_specifique' => array('text/csv')
				)
			)
		),
		array(
			'saisie' => 'radio',
			'options' => array(
				'nom' => 'publier',
				'label' => _T('ata_associations:label_importer_publier'),
				'defaut' => '0',
				'data' => array('1' => 'Oui', '0' => 'Non')
			)
		)
	);
	return $saisies;
}


function formulaires_importer_associations_charger(){
	$contexte = array(
		'mes_saisies' => formulaires_importer_associations_saisies()
	);
	return $contexte;
}


function formulaires_importer_associations_fichiers(){
	return array_keys(saisies_lister_avec_type(formulaires_importer_associations_saisies(), 'fichiers'));
}


function formulaires_importer_associations_verifier(){
	$erreurs = array();

	// Vérifier les autres saisies (de type fichiers)
	$saisies = formulaires_importer_associations_saisies();
	$erreurs_par_fichier = array(); 
	$saisies_verifier = saisies_verifier($saisies, true, $erreurs_par_fichier);
	foreach ($saisies_verifier as $champ => $erreur) { 
		// nettoyer $_FILES des fichiers problématiques
		cvtupload_nettoyer_files_selon_erreurs($champ, $erreurs_par_fichier[$champ]);
	}

	// fusionner avec nos précedentes erreurs
	$erreurs = array_merge($erreurs, $saisies_verifier);
	return $erreurs;
}


function formulaires_importer_associations_traiter() {
	$retours = array();
	
	$fichiers = _request('_fichiers');
	$status_file = ata_importer_meta_name(0);
	$import_init = ata_importer_init($status_file, $fichiers);

	if ($import_init === true) {
		spip_log('Importation depuis le formulaire', 'ata_import_debug.' . _LOG_INFO_IMPORTANTE);
		
		// Test doublons
		// $association = array('nom' => 'Astérismes', 'code_postal' => '29120'); 
		// $ids_nom = sql_allfetsel('id_association', 'spip_associations', 'nom=' . sql_quote($association['nom']));
		// if (count($ids_nom)) {
		// 	$from = 'spip_adresses AS L2 INNER JOIN spip_adresses_liens AS L1 ON (L1.id_adresse = L2.id_adresse)';
		// 	$where = array(
		// 		sql_in('L1.id_objet', $ids_nom),
		// 		'L1.objet=' . sql_quote('association'),
		// 		'L2.code_postal=' . sql_quote($association['code_postal'])
		// 	);
		// 	$ids_code = sql_allfetsel('L2.id_adresse', $from, $where);
		// }
		// if (isset($ids_code) and count($ids_code)) {
		// 	$r = "ok";
		// }
		// Fin Test doublons

		// Test Import classique
		$importer_csv = charger_fonction('importer_csv', 'inc');
		$donnees = $importer_csv($fichiers['csv'][0]['tmp_name'], true);

		$importer_associations = charger_fonction('ata_importer_associations', 'inc');
		$importer_associations($donnees);
		// Fin Test Import classique

		// $id_job = job_queue_add('ata_importer_csv', 'Importer fichier csv associations', $arguments = array($donnees), $file = 'genie/ata_importer_csv', true);

		// // Executer immediatement si possible
		// if ($id_job) {
		// 	include_spip('inc/queue');
		// 	queue_schedule(array($id_job));
		// } else {
		// 	spip_log("Erreur insertion Import CSV dans la file des travaux", 'ata_import_debug.' . _LOG_INFO_IMPORTANTE);
		// }
		

		
		// TEST 
		/*
		$retours['editable'] = true;
		$importer_csv = charger_fonction('importer_csv', 'inc');
		$donnees = $importer_csv($fichiers['csv'][0]['tmp_name'], true);
		
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
		

		$status['lignes_importees'] = 0;
		$status['lignes_restantes'] = 0;
		$status['total'] = count($donnees);
		$status['lignes_restantes'] = $status['total'];
		$insertions = array_chunk($donnees, 10);

		foreach ($insertions as $cle_chunk => $chunk) {
      foreach ($chunk as $cle_asso => $asso) {
				$champs = array('nom' => $asso['nom'], 'code_postal' => $asso['code_postal']);
				$champs_activites = array(
          'creation' => explode(PHP_EOL, $asso['activites_creation']),
          'diffusion' => explode(PHP_EOL,$asso['activites_diffusion']),
          'formation' => explode(PHP_EOL, $asso['activites_formation_ressources']),
          'transmission' => explode(PHP_EOL, $asso['activites_transmission']),
          'residences' => explode(PHP_EOL, $asso['residences'])
				);

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

				// if (sql_getfetsel('nom', 'spip_associations', array('nom='.sql_quote(trim($data['nom'])))))
        //$compteur_insertions++;
        //$status['lignes_importees'] = $compteur_insertions;
        //$status['lignes_restantes'] = $status['total'] - $status['lignes_importees'];
        // if ($max_time and time() > $max_time) {
        //   $log = 'importer_donnees, fin de boucle asso : Timeout. ';
        //   $log .= 'Lignes importées = ' . $status['lignes_importees'] . '. ';
        //   $log .= 'Lignes restantes = ' . $status['lignes_restantes'] . '. ';
        //   spip_log($log, 'ata_imata_import_debug.' ._LOG_INFO_IMPORTANTE);
        //   break;
        // }
      }
      // if ($max_time and time() > $max_time) {
      //   $log = 'importer_donnees, fin de boucle chunk : Timeout. ';
      //   $log .= 'Lignes importées = ' . $status['lignes_importees'] . '. ';
      //   $log .= 'Lignes restantes = ' . $status['lignes_restantes'] . '. ';
      //   spip_log($log, 'ata_import_debug.' ._LOG_INFO_IMPORTANTE);
      //   break;
      // }
    }

    // $log = 'importer_donnees, fin de script. ';
    // $log .= 'Lignes importées = ' . $status['lignes_importees'] . '. ';
    // $log .= 'Lignes restantes = ' . $status['lignes_restantes'] . '. ';
		// spip_log($log, 'ata_import_debug.' ._LOG_INFO_IMPORTANTE);
		*/
		// TEST FIN
		
		
		$retours['message_ok'] = "Importation des données en cours.";
		$retours['redirect'] = $redirect;
	} else {
		$retours['message_erreur'] = $import_init;
	}

	// $progress =  _request('progress');
	// ecrire_config('ata_importer', array('fichier' => $fichiers['csv'][0]));
	// var_dump($_FILES);
	// var_dump($fichiers);
	// refuser_traiter_formulaire_ajax();
	// $redirect = self();
	// $retours['redirect'] = generer_action_auteur('importer_associations', 'redirect='. urlencode($redirect));
	// $retours['message_ok'] = "Patienter";
	
	return $retours;
}