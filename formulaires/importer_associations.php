<?php

if (!defined('_ECRIRE_INC_VERSION')) {
  return;
}

include_spip('inc/saisies');
include_spip('inc/ata_importer_utils');

function formulaires_importer_associations_saisies($ids) {
	$saisies = array();
	$imports = array();
	if (is_array($ids) and count($ids)) {
		foreach ($ids as $id) {
			// Récupérer les imports à traiter
			$row = sql_allfetsel(
				'total, encours, date_start',
				'spip_associations_imports',
				'id_associations_import=' . intval($id)
			);
			$nb_restant = $row[0]['total'] - $row[0]['encours'];
			$imports[$id] = singulier_ou_pluriel(
				$nb_restant,
				'association:info_1_association',
				'association:info_nb_associations'
			);
			$imports[$id] .= ' à importer (sur un total de ' . $row[0]['total'] . ').';
			$imports[$id] .= '<br><small>CSV enregistré le ' . affdate($row[0]['date_start'], 'd-m-Y H:i:s');
			$imports[$id] .= '</small>';
		}
		$saisies = array(
			array(
				'saisie' => 'radio',
				'options' => array(
					'nom' => 'associations_imports',
					'label' => 'Sélectionner les données à importer',
					'obligatoire' => 'oui',
					'datas' => $imports
				)
			)
		);
	}

	return $saisies;
}

function formulaires_importer_associations_charger($ids) {
	$contexte = array();
	return $contexte;
}


function formulaires_importer_associations_verifier($ids) {
	$erreurs = array();
	return $erreurs;
}


function formulaires_importer_associations_traiter($ids) {
	refuser_traiter_formulaire_ajax();
	$retours = array();
	$id_associations_import = _request('associations_imports');

	$timeout = time() + 30;
	$nb = 60;

	$ata_importer_associations = charger_fonction('ata_importer_associations', 'inc/');
	$res = $ata_importer_associations($id_associations_import, $nb, $timeout);

	//$res = array('message_ok' => 'ok', 'nb' => '12');

	if ($res['message_ok']) {
		$retours['message_ok'] = $res['message_ok'];
	} elseif ($res['message_erreur']) {
		$retours['message_erreur'] = $res['message_erreur'];
	}
	$retours['editable'] = true;
	return $retours;
}
