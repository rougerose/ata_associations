<?php

if (!defined('_ECRIRE_INC_VERSION')) {
  return;
}

include_spip('inc/cvtupload');
include_spip('inc/saisies');
include_spip('inc/ata_importer_utils');


function formulaires_importer_csv_saisies() {
	static $saisies;

	if (!$saisies == null) {
		return $saisies;
	}

	$rubriques = sql_allfetsel('id_rubrique, titre', 'spip_rubriques', '', '', 'titre ASC');
	$t_rubriques = array();
	foreach ($rubriques as $rubrique) {
		$t_rubriques[$rubrique['id_rubrique']] = $rubrique['titre'];
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
			'saisie' => 'selection',
			'options' => array(
				'nom' => 'id_rubrique',
				'obligatoire' => 'oui',
				'label' => _T('ata_associations:label_importer_rubrique'),
				'datas' => $t_rubriques
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


function formulaires_importer_csv_charger() {
	$contexte = array(
		'mes_saisies' => formulaires_importer_csv_saisies()
	);
	return $contexte;
}


function formulaires_importer_csv_fichiers() {
	return array_keys(saisies_lister_avec_type(formulaires_importer_csv_saisies(), 'fichiers'));
}


function formulaires_importer_csv_verifier() {
	$erreurs = array();

	// Vérifier les autres saisies (de type fichiers)
	$saisies = formulaires_importer_csv_saisies();
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


function formulaires_importer_csv_traiter() {
  refuser_traiter_formulaire_ajax();
	$retours = array();
	$fichiers = _request('_fichiers');
	$publier = _request('publier');
	$id_rubrique = _request('id_rubrique');
	$options = array('head' => true);
	$importer_csv = charger_fonction('importer_csv', 'inc');
	$donnees = $importer_csv($fichiers['csv'][0]['tmp_name'], $options);

	$ata_importer_csv = charger_fonction('ata_importer_csv', 'inc');
	$res = $ata_importer_csv($donnees, $id_rubrique, $publier);

	// Test
	//$res = array(1, 1);

	if (is_array($res)) {
		$retours['message_ok'] = 'Traitement du fichier terminé. ';
		$retours['message_ok'] .= singulier_ou_pluriel(
			$res[1],
			'association:info_1_association',
			'association:info_nb_associations'
		) . ' à importer.';
		// $retours['redirect'] = $redirect;
		// $retours['editable'] = false;
	} else {
		$retours['message_erreur'] = 'Erreur lors du traitement du fichier CSV';
	}
	return $retours;
}
