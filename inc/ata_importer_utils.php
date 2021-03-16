<?php

if (!defined('_ECRIRE_INC_VERSION')) {
  return;
}

function ata_importer_activites_get_id($terme, $mots_activites) {
	$callback = function ($val) use ($terme) {
		return (strpos($terme, $val) !== false ? true : false);
	};
	$ids = array_keys(array_filter($mots_activites, $callback));
	if (count($ids)) {
		return reset($ids);
	} else {
		return '';
	}
}

function ata_importer_activites_get_titre_court($titre_long) {
	$titres_courts = array(
		'creation', // => 'Création',
		'production', // => "Aide à la production technique d'oeuvres",
		'financement', // => "Aide au financement d'oeuvres",
		'ateliers', // => "Mise à disposition d'ateliers",
		'ressourcerie', // => 'Matériauthèque-ressourcerie',
		'diffusion', // => 'Diffusion',
		'edition', // => 'Edition',
		'portes', // => 'Portes ouvertes',
		'vente', // => 'Espace dédié à la vente',
		'organisation', // => "Organisation d'événemts",
		'parcours', // => "Parcours d'art",
		'intervention', // => "Intervention ds l'espace public",
		'education', // => 'Education artistique et culturelle',
		'mediation', // => "Médiation - visites d'exposition",
		'commissariat', // => "Commissariat d'exposition",
		'artotheque', // => "Artothèque-collection d'oeuvres",
		'organisme', // => 'Organisme de formation',
		'bibliotheque', // => 'Centre de doc-bibliothèque',
		'information', // => "Espace d'information personnalisée",
		'admin', // => 'Aide admin et à la proflisation',
		'residence', // => 'Résidence',
	);
	$callback = function ($val) use ($titre_long) {
		return (strpos($titre_long, $val) !== false ? true : false);
	};
	$titre_court = array_filter($titres_courts, $callback);
	if (count($titre_court)) {
		return $titre_court = reset($titre_court);
	} else {
		return false;
	}
}

function ata_importer_normaliser_activites($texte) {
	$texte = strtolower(ata_importer_supprimer_accents($texte));
	$supprimer = array('œ', '-', ',', "d'", "l'");
	$remplacer = array('oe', ' ', ' ', '', '');
	$texte = str_replace($supprimer, $remplacer, $texte);
	$texte = preg_replace('/\s+/', ' ', $texte);
	return $texte;
}

/**
 * Supprimer les caractères accentués
 * https://www.php.net/manual/fr/function.mb-ereg-replace.php#123589
 *
 * @param  string $texte
 * @return string
 */
function ata_importer_supprimer_accents($texte) {
	$transliterator = Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC;');
	return $transliterator->transliterate($texte);
}
