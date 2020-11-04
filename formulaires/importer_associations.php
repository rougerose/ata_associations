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
		spip_log('Importation depuis le formulaire', 'ata_import.' . _LOG_INFO_IMPORTANTE);
		$redirect = generer_action_auteur('importer_associations', $status_file);
		
		/*
		// TEST
		$retours['editable'] = true;
		$importer_csv = charger_fonction('importer_csv', 'inc');
		$donnees = $importer_csv($fichiers['csv'][0]['tmp_name'], true);
		$status['lignes_importees'] = 0;
		$status['lignes_restantes'] = 0;
		$status['total'] = count($donnees);
		$status['lignes_restantes'] = $status['total'];
		$insertions = array_chunk($donnees, 10);

		foreach ($insertions as $cle_chunk => $chunk) {
      foreach ($chunk as $cle_asso => $asso) {
        $asso_champs = array('nom' => $asso['nom']);
        $compteur_insertions++;
        $status['lignes_importees'] = $compteur_insertions;
        $status['lignes_restantes'] = $status['total'] - $status['lignes_importees'];
        // if ($max_time and time() > $max_time) {
        //   $log = 'importer_donnees, fin de boucle asso : Timeout. ';
        //   $log .= 'Lignes importées = ' . $status['lignes_importees'] . '. ';
        //   $log .= 'Lignes restantes = ' . $status['lignes_restantes'] . '. ';
        //   spip_log($log, 'ata_import.' ._LOG_INFO_IMPORTANTE);
        //   break;
        // }
      }
      // if ($max_time and time() > $max_time) {
      //   $log = 'importer_donnees, fin de boucle chunk : Timeout. ';
      //   $log .= 'Lignes importées = ' . $status['lignes_importees'] . '. ';
      //   $log .= 'Lignes restantes = ' . $status['lignes_restantes'] . '. ';
      //   spip_log($log, 'ata_import.' ._LOG_INFO_IMPORTANTE);
      //   break;
      // }
    }

    // $log = 'importer_donnees, fin de script. ';
    // $log .= 'Lignes importées = ' . $status['lignes_importees'] . '. ';
    // $log .= 'Lignes restantes = ' . $status['lignes_restantes'] . '. ';
    // spip_log($log, 'ata_import.' ._LOG_INFO_IMPORTANTE);
		
		// TEST FIN
		*/
		
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