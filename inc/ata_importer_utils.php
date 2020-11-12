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
	return "<script language=\"JavaScript\" type=\"text/javascript\">window.setTimeout('location.href=\"$redirect\";',3000);</script>\n";
}

function ata_importer_afficher_progression($courant, $total) {
	static $etape = 1;
	if (unique($courant)) {

	}
}

/**
 * Supprimer les caractères accentués
 * https://www.php.net/manual/fr/function.mb-ereg-replace.php#123589
 *
 * @param  string $texte
 * @return string
 */
function ata_importer_supprimer_accents($texte){
	$transliterator = Transliterator::create("NFD; [:Nonspacing Mark:] Remove; NFC;");
	return $transliterator->transliterate($texte);
}


function ata_importer_update_meta($force = false) {
		$current = ((isset($GLOBALS['meta']['ata_import_processing']) AND $GLOBALS['meta']['ata_import_processing']) ? $GLOBALS['meta']['ata_import_processing'] : false);
		
		$new = false;

		if ($force OR (sql_countsel('spip_associations_imports', 'statut=' . sql_quote('processing')))) {
			$new = 'oui';
		}

		if ($new OR $new !== $current) {
			if ($new) {
				ecrire_meta('ata_import_processing', $new);
				// reprogrammer le cron
				include_spip('inc/genie');
				genie_queue_watch_dist();
			} else {
				effacer_meta('ata_import_processing');
			}
		}
		return $new;
}

function ata_importer_cadence() {
	// cadence maximum
	$cadence = array(60, 30);
	return $cadence;
}

function ata_importer_compter_imports($id_associations_import) {
	$total = sql_countsel("spip_associations_imports_sources", array(
		"id_associations_import=$id_associations_import",
		"statut=1"
	));
	if (!$stotal) {
		return;
	}

	// $statuts = sql_allfetsel('statut, count(statut) as nb', 'spip_associations_imports_sources', 'id_associations_import=1', 'statut');
	// $statuts = array_combine(array_map('reset', $statuts), array_map('end', $statuts));

	// $set = array(
	// 	'total' => $total,
		
	// );

}

function ata_importer_associations_init($redirect) {
	$timeout = ini_get('max_execution_time');
		// valeur conservatrice si on a pas reussi a lire le max_execution_time
	if (!$timeout) {
		$timeout = 30;
	} // parions sur une valeur tellement courante ...
	$max_time = time() + $timeout / 2;

	include_spip('inc/minipres');
	@ini_set('zlib.output_compression', '0'); // pour permettre l'affichage au fur et a mesure

	$titre = _T('Importation en cours');
	$balise_img = chercher_filtre('balise_img');
	$titre .= $balise_img(chemin_image('searching.gif'));
	echo(install_debut_html($titre));
	// script de rechargement auto sur timeout
	echo http_script("window.setTimeout('location.href=\"" . $redirect . "\";'," . ($timeout * 1000) . ')');
	echo "<div style='text-align: left'>\n";
	echo("</div>\n");

	$res = false;
	if (_request('step')) {
		$id_associations_import = _request('arg');
		$res = ata_importer_associations_processing($id_associations_import, $max_time);

		// $options = array(
		// 	'callback_progression' => 'dump_afficher_progres',
		// 	'max_time' => $max_time,
		// 	'no_erase_dest' => lister_tables_noerase(),
		// 	'where' => $status['where'] ? $status['where'] : array(),
		// );
		// $res = base_copier_tables($status_file, $status['tables'], '', 'dump', $options);
	} else {
		echo ata_importer_relance($redirect);
	}

	// if (!$res and $redirect) {
	// 	echo ata_importer_relance($redirect);
	// }
	echo(install_fin_html());
	
	if (@ob_get_contents()) {
		ob_end_flush();
	}
	flush();

	return $res;
}

function ata_importer_associations_processing($id_associations_import, $max_time) {
	$nb = 0;
	$relance = true;
	$boost = false;
	$f_relance = _DIR_TMP."ata_importer_relance.txt";
	$f_last = _DIR_TMP."ata_importer_last.txt";
	
	if (!$boost) {
		lire_fichier($f_relance, $nb);
	}

	if (!$nb = intval($nb)) {
		$relance = false;
		$periode = $max_time;
		$nb = 30;
		$now = time();
		if (!$boost) {
			lire_fichier($f_last, $last);
			if ($last = intval($last) and ($dt = $now - $last) > 0) {
        $c = min(2, $dt / $periode);
        $nb = intval(round($nb * $c, 0));
        //spip_log("Correction sur nb : $c ($dt au lieu de $periode) => $nb","ata_importer_associations." . _LOG_INFO_IMPORTANTE);
      }
		}
		ecrire_fichier($f_last, $now);
	}
}