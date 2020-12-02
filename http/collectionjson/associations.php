<?php

if (!defined('_ECRIRE_INC_VERSION')) {
  return;
}


include_spip('base/abstract_sql');
include_spip('base/objets');

/**
 * API REST GET sur une collection associations
 * @param  request $requete objet contenant la requete HTTP
 * @param  response $reponse objet qui contiendra la réponse, format json
 * @return response
 */
function http_collectionjson_associations_get_collection($requete, $reponse) {
	$collection = $requete->attributes->get('collection');
	$offset = $requete->query->get('offset');
	$limit = $requete->query->get('limit');

	// parametres possibles
	$parametres = array(
		'id_association' => $requete->query->get('id_association'),
		'id_mot' => $requete->query->get('id_mot'),
		'id_adresse' => $requete->query->get('id_adresse')
	);

	$parametres = ata_associations_collectionjson_traiter_parametres($parametres);

	$cle = id_table_objet($collection);
	$table = table_objet_sql($collection);
	$objet = objet_type($collection);

	$contexte = array(
		'table' => $table,
		'id_table_objet' => $cle,
		'parametres' => $parametres,
		'offset' => isset($offset) ? $offset : 0,
		'pagination' => isset($limit) ? $limit : 10
	);

	// récupérer le contenu
	$lignes = ata_associations_recuperer_contenu_collection($contexte);
	$nb_objets = array_pop($lignes);
	$lignes = $lignes[0];

	if ($lignes) {
		$objets = array();
		$pagination = $contexte['pagination'];
		$offset = $contexte['offset'];

		foreach ($lignes as $champ) {
			$objets[] = collectionjson_get_objet($objet, $champ[$cle], $champ);
		}

		// pagination
		$links = array();
		$url = self('&');

		if ($offset > 0) {
			$offset_precedent = max(0, $offset-$pagination);
			$links[] = array(
				'rel' => 'prev',
				'prompt' => _T('public:page_precedente'),
				'href' => url_absolue(parametre_url($url, 'offset', $offset_precedent, '&')),
			);
		}

		if (($offset + $pagination) < $nb_objets) {
			$offset_suivant = $offset + $pagination;
			$links[] = array(
				'rel' => 'next',
				'prompt' => _T('public:page_suivante'),
				'href' => url_absolue(parametre_url($url, 'offset', $offset_suivant, '&')),
			);
		}

		$json = array(
			'collection' => array(
				'version' => '1.0',
				'href' => url_absolue(parse_url(self('&'), PHP_URL_PATH)),
				'links' => $links,
				'items' => $objets,
			),
		);

		// pipelines
		$json = pipeline(
			'collectionjson_get_collection',
			array(
				'args' => array(
					'collection' => $collection,
					'contexte' => $contexte,
				),
				'data' => $json,
			)
		);

		$json = pipeline(
			'http_collectionjson_get_collection_contenu',
			array(
				'args' => array(
					'requete' => $requete,
					'reponse' => $reponse,
				),
				'data' => $json,
			)
		);

		$json = json_encode($json);
		$reponse->setStatusCode(200);
		$reponse->setCharset('utf-8');
		$reponse->headers->set('Content-Type', 'application/json');
		$reponse->setContent($json);
	} else {
		$fonction_erreur = charger_fonction('erreur', 'http/collectionjson/');
		$response = $fonction_erreur(404, $requete, $reponse);
	}

	return $reponse;
}


/**
 * API REST GET sur une ressource association
 * @param  request $requete objet contenant la requete HTTP
 * @param  response $reponse objet qui contiendra la réponse, format json
 * @return response
 */
function http_collectionjson_associations_get_ressource($requete, $reponse) {
	$format = $requete->attributes->get('format');
	$collection = $requete->attributes->get('collection');
	$ressource = $requete->attributes->get('ressource');

	// C'est une ressource, aucune prise en compte de parametres éventuels
	$parametres = array(
		'id_association' => null,
		'id_mot' => null,
		'id_adresse' => null
	);

	$cle = id_table_objet($collection);
	$table = table_objet_sql($collection);
	$id_objet = intval($ressource);
	$objet = objet_type($collection);

	$select = array($cle, 'nom', 'url_site');
	$from = $table.' AS l1';
	$from .= ' INNER JOIN spip_gis_liens AS l2 ON (l2.id_objet = l1.id_association AND l2.objet = "association")';
	$where = array('l1.statut='.sql_quote('publie'));
	$where[] = "$cle=$id_objet";

	$champs = sql_fetsel($select, $from, $where);

	if ($champs) {
		$item = collectionjson_get_objet($objet, $id_objet, $champs);

		$json = array(
			'collection' => array(
				'version' => '1.0',
				'href' => url_absolue(self('&')),
				'items' => array(
					$item,
				)
			),
		);

		// On passe le json dans un pipeline
		$json = pipeline(
			'http_collectionjson_get_ressource_contenu',
			array(
				'args' => array(
					'requete' => $requete,
					'reponse' => $reponse,
				),
				'data' => $json,
			)
		);

		$json = json_encode($json);
		$reponse->setStatusCode(200);
		$reponse->setCharset('utf-8');
		$reponse->headers->set('Content-Type', 'application/json');
		$reponse->setContent($json);
	} else {
		$fonction_erreur = charger_fonction('erreur', "http/$format/");
		$reponse = $fonction_erreur(404, $requete, $reponse);
	}

	return $reponse;
}

/**
 * Traiter les parametres ajoutés au GET
 * @param  array $parametres
 * @return array
 */
function ata_associations_collectionjson_traiter_parametres($parametres) {
	$parametres_post_traitement = array();

	if (is_array($parametres)) {
		foreach ($parametres as $cle => $parametre) {
			if ($parametre and is_string($parametre)) {
				$parametre = explode(',', $parametre);
			}
			$parametres_post_traitement[$cle] = $parametre;
		}
	}

	return $parametres_post_traitement;
}


/**
 * [ata_associations_recuperer_contenu_collection description]
 * @param  array  $contexte
 * 	- table
 * 	- id_table_objet
 * 	- parametres : id_association, id_mots, id_adresse
 * 	- offset
 * 	- pagination

 * @return array|integer
 * 	Un tableau contenant tous les résultats de la requete
 * 	ou le nombre total des résultats.
 */
function ata_associations_recuperer_contenu_collection($contexte) {
	$select = array();
	$where = array();
	$having = array();
	$groupby = array();
	$orderby = array();
	$limit = '';
	$having = array();

	$cle = $contexte['id_table_objet'];
	$table = $contexte['table'];

	$limit = $contexte['offset'].','.$contexte['pagination'];
	$from[] = $table.' AS l1';
	$orderby[] = 'l1.nom';

	$id_association = $contexte['parametres']['id_association'];
	$id_mot = $contexte['parametres']['id_mot'];
	$id_adresse = $contexte['parametres']['id_adresse'];

	$cpt = 0;
	$requetes = array();
	$resultats = array();

	// TODO pourquoi aucun critère de recherche ?
	if (is_null($id_association) and is_null($id_mot) and is_null($id_adresse)) {
		$select = array($cle, 'nom');
		$from = $table.' AS l1';
		$from .= ' INNER JOIN spip_gis_liens AS l2 ON (l2.id_objet = l1.id_association AND l2.objet = "association")';
		$where = array('l1.statut='.sql_quote('publie'));
		$cpt = sql_countsel($from);
		$resultats = sql_allfetsel($select, $from, '', '', $orderby, $limit, '');
	} else {
		$select = array($cle);

		if ($id_association) {
			$from = $table.' AS l1';
			$from .= ' INNER JOIN spip_gis_liens AS l2';
			$from .= ' ON (l2.id_objet = l1.id_association AND l2.objet = "association")';
			$where = array('l1.statut='.sql_quote('publie'));
			$where[] = sql_in('l1.id_association', $id_association);
			$requetes[] = sql_select($select, $from, $where, '', $orderby, $limit, '');
		}

		if ($id_adresse) {
			$where = '';
			if (count($id_adresse) > 1) {
				$in = sql_in('id_adresse', $id_adresse);
				$villes = sql_allfetsel('ville', 'spip_adresses', $in);
				foreach ($villes as $i => $ville) {
					if ($i > 0) {
						$where .= ' OR ';
					}
					$where .= 'l3.ville LIKE '.sql_quote('%'.$ville['ville'].'%');
				}
			} else {
				$ville = sql_getfetsel('ville', 'spip_adresses', "id_adresse=$id_adresse[0]");
				$where = 'l3.ville LIKE '.sql_quote("%$ville%");
			}
			$where .= ' and l1.statut='.sql_quote('publie');
			$from = $table.' AS l1';
			$from .= ' INNER JOIN spip_adresses_liens AS l2';
			$from .= ' ON (l2.objet="association" AND l2.id_objet=l1.id_association)';
			$from .= ' INNER JOIN spip_adresses AS l3';
			$from .= ' ON (l3.id_adresse=l2.id_adresse)';
			$from .= ' INNER JOIN spip_gis_liens AS l4';
			$from .= ' ON (l4.id_objet = l1.id_association AND l4.objet = "association")';
			$requetes[] = sql_select($select, $from, $where, '', $orderby, $limit, '');
		}

		if ($id_mot) {
			$where_assos = ata_associations_prepare_mots($id_mot);
			$from = $table.' AS l1';

			if ($where_assos) {
				$from .= ' INNER JOIN spip_gis_liens AS l4';
				$from .= ' ON (l4.id_objet = l1.id_association AND l4.objet = "association")';
				$where = array('l1.statut='.sql_quote('publie'));
				$where[] = $where_assos;
			} else {
				$where = array('l1.id_association=0');
			}

			$requetes[] = sql_select($select, $from, $where, '', '', $limit, '');
		}

		$in = array();
		$wh = array();

		foreach ($requetes as $requete) {
			while ($id = sql_fetch($requete)) {
				$in[] = $id['id_association'];
			}
			if ($in) {
				$wh[] = sql_in('id_association', $in);
				$in = array();
			}
		}
	}

	if ($wh) {
		$select = array($cle, 'nom');
		$from = $table;
		$cpt = sql_countsel($from, $wh);
		$resultats = sql_allfetsel($select, $from, $wh);
	}

	return $lignes = array($resultats, $cpt);
}

/**
 * Requête de sélection des associations selon une série de mots-clés.
 * Il s'agit d'extraire les associations qui possède un mot-clé ET un autre.
 *
 * Code repris du plugin critere_mots.
 *
 * @param  array $mots  Les mots-clés de la requête
 * @param  string $table Uniquement la table associations.
 * @return array|string
 */
function ata_associations_prepare_mots($mots, $table = 'associations') {
	$table = table_objet($table);
	$objet_delatable = objet_type($table);
	$id_table = id_table_objet($table);
	$where = array();

	foreach ($mots as $mot) {
		$id_mot = $mot;
		$where[] = 'id_mot='.sql_quote($id_mot).' and objet='.sql_quote($objet_delatable);
	}

	$having = ' HAVING SUM(1) >= '.ceil(1 * count($where));
	$in = array();
	$s = sql_query('SELECT id_objet as i FROM spip_mots_liens WHERE '
		. join(' OR ', $where)
		. ' GROUP BY id_objet,objet'
		. $having);
	while ($t = sql_fetch($s)) {
		$in[] = $t['i'];
	}

	if ($in) {
		// Attention, on utilise ici l1, en réf à la table association dans la requête principale.
		$wh = sql_in("l1.$id_table", $in);
	} else {
		$wh = '0';
	}
	return $wh;
}
