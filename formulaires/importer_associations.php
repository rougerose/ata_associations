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

	// Test Import classique
		$publier = _request('publier');
		// $importer_csv = charger_fonction('importer_csv', 'inc');
		// $donnees = $importer_csv($fichiers['csv'][0]['tmp_name'], true);

		// $ata_importer_csv = charger_fonction('ata_importer_csv', 'inc');
		// $res = $ata_importer_csv($donnees, $publier);
		$res = 1;
		// Fin Test Import classique

		if (intval($res) > 0) {
			//$redirect = parametre_url(parametre_url(self(), 'vue', 'progression_importer'), 'id_associations_import', $res['id_associations_import']);
			// $retours['redirect'] = $redirect;
			include_spip('inc/actions');
			$redirect = generer_action_auteur('importer_associations', intval($res));
			//$message = $res['message_ok'] . " Veuillez <a href='$redirect'>rafraîchir la page</a> pour importer les données des associations.";
			$retours['message_ok'] = 'Importation en cours.';
			$retours['redirect'] = $redirect;
			$retours['editable'] = false;
		} else {
			$retours['message_erreur'] = $res['message_erreur'];
		}

	// if ($import_init === true) {
	// 	// spip_log('Importation depuis le formulaire', 'ata_import_debug.' . _LOG_INFO_IMPORTANTE);
		
		
		
		
		
	// } else {
	// 	$retours['message_erreur'] = $import_init;
	// }
	return $retours;
}