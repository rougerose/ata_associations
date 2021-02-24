<?php
#
# Ces fichiers sont a placer dans le repertoire base/ de votre plugin
#
/**
 * Gestion de l'importation de `spip_mots`
**/

/**
 * Fonction d'import de la table `spip_mots`
 * à utiliser dans le fichier d'administration du plugin
 *
 *     ```
 *     include_spip('base/importer_spip_mots');
 *     $maj['create'][] = array('importer_spip_mots');
 *     ```
 *
**/
function importer_spip_mots() {
	######## VERIFIEZ LE NOM DE LA TABLE D'INSERTION ###########
	$table = 'spip_mots';

	// nom_du_champ_source => nom_du_champ_destination
	// mettre vide la destination ou supprimer la ligne permet de ne pas importer la colonne.
	$correspondances = array(
		'id_mot' => 'id_mot',
		'titre' => 'titre',
		'descriptif' => 'descriptif',
		'texte' => 'texte',
		'id_groupe' => 'id_groupe',
		'type' => 'type',
		'maj' => 'maj',
		'id_groupe_racine' => 'id_groupe_racine',
	);

	// transposer les donnees dans la nouvelle structure
	$inserts = array();
	list($cles, $valeurs) = donnees_spip_mots();
	// on remet les noms des cles dans le tableau de valeur
	// en s'assurant de leur correspondance au passage
	if (is_array($valeurs)) {
		foreach ($valeurs as $v) {
			$i = array();
			foreach ($v as $k => $valeur) {
				$cle = $cles[$k];
				if (isset($correspondances[$cle]) and $correspondances[$cle]) {
					$i[ $correspondances[$cle] ] = $valeur;
				}
			}
			$inserts[] = $i;
		}
		unset($valeurs);

		// inserer les donnees en base.
		$nb_inseres = 0;
		// ne pas reimporter ceux deja la (en cas de timeout)
		$nb_deja_la = sql_countsel($table);
		$inserts = array_slice($inserts, $nb_deja_la);
		$nb_a_inserer = count($inserts);
		// on decoupe en petit bout (pour reprise sur timeout)
		$inserts = array_chunk($inserts, 100);
		foreach ($inserts as $i) {
			sql_insertq_multi($table, $i);
			$nb_inseres += count($i);
			// serie_alter() relancera la fonction jusqu'a ce que l'on sorte sans timeout.
			if (time() >= _TIME_OUT) {
				// on ecrit un gentil message pour suivre l'avancement.
				echo "<br />Insertion dans $table relanc&eacute;e : ";
				echo "<br />- $nb_deja_la &eacute;taient d&eacute;j&agrave; l&agrave;";
				echo "<br />- $nb_inseres ont &eacute;t&eacute; ins&eacute;r&eacute;s.";
				$a_faire = $nb_a_inserer - $nb_inseres;
				echo "<br />- $a_faire &agrave; faire.";
				return;
			}
		}
	}
}


/**
 * Donnees de la table spip_mots
**/
function donnees_spip_mots() {

	$cles = array('id_mot', 'titre', 'descriptif', 'texte', 'id_groupe', 'type', 'maj', 'id_groupe_racine');

	$valeurs = array(
		array('1', 'Création', '', '', '2', 'Création', '2020-10-30 07:10:02', '1'),
		array('2', 'Aide à la production technique d\'œuvres', '', '', '2', 'Création', '2020-10-30 07:10:19', '1'),
		array('3', 'Mise à disposition d\'ateliers ou d\'outils', '', '', '2', 'Création', '2020-11-05 16:44:31', '1'),
		array('4', 'Aide au financement d\'œuvres', '', '', '2', 'Création', '2020-11-05 16:45:01', '1'),
		array('5', 'Matériauthèque, ressourcerie', '', '', '2', 'Création', '2020-11-05 16:45:35', '1'),
		array('6', 'Diffusion', '(expositions, projections, etc.)', '', '3', 'Diffusion', '2020-11-05 16:47:47', '1'),
		array('7', 'Édition', '(catalogue, DVD, application, mooc, livre d\'artiste, revue, publication, multiples, etc.)', '', '3', 'Diffusion', '2020-11-05 16:48:03', '1'),
		array('8', 'Espace dédié à la vente', '', '', '3', 'Diffusion', '2020-11-05 16:48:19', '1'),
		array('9', 'Organisation d\'événements', '(festival, salon, débat, conférence, etc.)', '', '3', 'Diffusion', '2020-11-05 16:48:49', '1'),
		array('10', 'Portes ouvertes', '', '', '3', 'Diffusion', '2020-11-05 16:49:10', '1'),
		array('11', 'Parcours d\'art', '', '', '3', 'Diffusion', '2020-11-05 16:49:20', '1'),
		array('12', 'Intervention dans l\'espace public', '', '', '3', 'Diffusion', '2020-11-05 16:49:33', '1'),
		array('13', 'Résidence', '(de recherche, de création, avec ou sans diffusion publique, avec ou sans rencontre avec les publics)', '', '4', 'Résidences', '2020-11-12 16:41:23', '1'),
		array('14', 'Éducation artistique et culturelle', '(ateliers, cours de pratiques)', '', '5', 'Transmission', '2020-11-05 16:51:11', '1'),
		array('15', 'Médiation, visite d\'exposition', '', '', '5', 'Transmission', '2020-11-05 16:51:29', '1'),
		array('16', 'Commissariat d\'exposition', '', '', '5', 'Transmission', '2020-11-05 16:51:44', '1'),
		array('17', 'Artothèque, collection d\'œuvres', '', '', '5', 'Transmission', '2020-11-05 16:52:06', '1'),
		array('18', 'Organisme de formation professionnelle', '', '', '6', 'Formation et ressources', '2020-11-05 16:53:03', '1'),
		array('19', 'Centre de documentation, bibliothèque', '', '', '6', 'Formation et ressources', '2020-11-05 16:53:17', '1'),
		array('20', 'Espace d\'information personnalisée pour les artistes-auteurs', '', '', '6', 'Formation et ressources', '2020-11-05 16:53:38', '1'),
		array('21', 'Aide administrative et à la professionnalisation', '', '', '6', 'Formation et ressources', '2020-11-05 16:53:59', '1'),
	);

	return array($cles, $valeurs);
}
