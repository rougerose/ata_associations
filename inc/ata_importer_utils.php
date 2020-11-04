<?php

if (!defined("_ECRIRE_INC_VERSION")) {
  return;
}

function ata_importer_meta_name($etape) {
	return $meta = "ata_importer_{$etape}_". abs($GLOBALS['visiteur_session']['id_auteur']);
}

function ata_importer_init($status_file, $fichier) {
	$status_file = _DIR_TMP . basename($status_file) . '.txt';
	$status['etape'] = 'init';
	$status['fichier'] = $fichier;

	if (!ecrire_fichier($status_file, serialize($status))) {
		return _T('ata_associations:erreur_probleme_ecrire_fichier', array('fichier' => $status_file));
	}

	return true;
}

function ata_importer_relance($redirect) {
  // si Javascript est dispo, anticiper le Time-out
	return "<script language=\"JavaScript\" type=\"text/javascript\">window.setTimeout('location.href=\"$redirect\";',300);</script>\n";
}

function ata_importer_afficher_progression($courant, $total) {
	static $etape = 1;
	if (unique($courant)) {

	}
}


/*include_spip('inc/flock');
include_spip('inc/invalideur');

if(!function_exists('deplacer_fichier_upload')){
		include_spip('inc/documents');
	}


function ata_gerer_fichier($fichier, $supprimer = false) {
  $fichier_infos = '';

  if ($supprimer) {
    ata_supprimer_fichier();
  } else {
    $fichier_infos = ata_copier_fichier($fichier);
  }

  return $fichier_infos;
}

function ata_copier_fichier($fichier) {

	$repertoire = sous_repertoire(_DIR_IMPORTS_ATA, 'imports_associations/');
	purger_repertoire($repertoire, $options = array('atime', time()));

	$infos = array();
	$hash = substr(md5(time()), 0, 5);
	$chemin = $fichier['tmp_name'];
	$nom = basename($fichier['name']);
	$extension = strtolower(pathinfo($nom, PATHINFO_EXTENSION));
	$extension_old = $extension;
	$extension = corriger_extension($extension);
	$nom = str_replace(".$extension_old", ".$extension", $nom);

	if ($fichier['error'] == 0 and $fichier_tmp = tempnam($repertoire, $hash.'_')) {
		$copie = $fichier_tmp.".$extension";

		if (deplacer_fichier_upload($chemin, $copie, false)) {
			$infos['name'] = basename($copie);
			$infos['tmp_name'] = $copie;
			$infos['extension'] = $extension;
			$infos['size'] = $fichier['size'];
			$infos['type'] = $fichier['type'];

			supprimer_fichier($fichier_tmp, true);
		}
  }
  
	return $infos;
}

function ata_supprimer_fichier() {
	
	$repertoire = sous_repertoire(_DIR_IMPORTS_ASSOCIATIONS.'imports_associations/');

	// Si on entre bien dans le répertoire
	if ($ressource_repertoire = opendir($repertoire)) {
		$fichiers = array();

		// On commence par supprimer les plus vieux
		while ($fichier = readdir($ressource_repertoire)) {
			if (!in_array($fichier, array('.', '..', '.ok'))) {
				$chemin_fichier = $repertoire.$fichier;

				if (is_file($chemin_fichier)) {
					supprimer_fichier($chemin_fichier);
				}
			}
		}
	}
}
*/