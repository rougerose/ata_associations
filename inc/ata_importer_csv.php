<?php

if (!defined('_ECRIRE_INC_VERSION')) {
  return;
}


function inc_ata_importer_csv($donnees, $id_rubrique, $publier = 0) {
	$res = '';
	$total = count($donnees);
	$now = date('Y-m-d H:i:s');

	if ($total) {
		include_spip('inc/ata_importer_utils');
		include_spip('action/editer_objet');
		$infos_import = array(
			'total' => $total,
			'encours' => 0,
			'id_rubrique' => $id_rubrique,
			'statut' => 'pending',
		);
		$id_associations_import = objet_inserer('associations_imports', null, $infos_import);

		if ($id_associations_import) {
			$associations = array();
			$i = 0;

			// Mots-clés Activités
			$mots = sql_allfetsel('id_mot, titre', 'spip_mots', 'id_groupe_racine=1');
			$mots_activites = array();

			// Normaliser les titres des mots pour comparaison avec les données d'import.
			foreach ($mots as $mot) {
				$titre = ata_importer_normaliser_activites($mot['titre']);
				$titre_court = ata_importer_activites_get_titre_court($titre);
				if (!$titre_court) {
					spip_log(
						"Mot-clé $titre ne possède pas de correspondance en titre court. Traitement du CSV interrompu.",
						'import_csv_debug.' . _LOG_INFO_IMPORTANTE
					);
					return $res = false;
				}
				$mots_activites[$mot['id_mot']] = $titre_court;
			}

			foreach ($donnees as $l => $asso) {
				if (empty($asso['nom'])) {
					spip_log(
						"Association ligne $l sans nom, ses données ont été ignorées",
						'import_csv_debug.' . _LOG_INFO_IMPORTANTE
					);
					continue;
				}

				$membre_fraap = '';
				$membre_fraap = trim(strtolower($asso['membre_fraap']));
				if ($membre_fraap) {
					if ($membre_fraap == 'oui') {
						$asso['membre_fraap'] = 1;
					}
					if ($membre_fraap == 'non') {
						$asso['membre_fraap'] = 0;
					}
				} else {
					$asso['membre_fraap'] = 0;
				}

				$association = array(
					'id_associations_import' => $id_associations_import,
					'nom' => $asso['nom'],
					'membre_fraap' => $asso['membre_fraap'],
					'voie' => $asso['adresse'],
					'complement' => $asso['adresse2'],
					'code_postal' => $asso['code_postal'],
					'ville' => $asso['ville'],
					'url_site' => $asso['site_internet'],
					'email' => $asso['email1'],
					'facebook' => $asso['facebook'],
					'twitter' => $asso['twitter'],
					'instagram' => $asso['instagram'],
					'statut' => 1,
					'publier' => $publier,
				);

				$activites = array(
					'creation' => $asso['activites_creation'],
					'diffusion' => $asso['activites_diffusion'],
					'formation' => $asso['activites_formation_ressources'],
					'transmission' => $asso['activites_transmission'],
					'residences' => $asso['residences']
				);

				foreach ($activites as $k => $activite) {
					if (empty($activite)) {
						$association[$k][] = '';
					} else {
						$activite = str_replace(array("\n", "\r", "\t"), ';', $activite);
						$activite = ata_importer_normaliser_activites($activite);
						$termes = explode(';', $activite);
						foreach ($termes as $terme) {
							$association[$k][] = ata_importer_activites_get_id(trim($terme), $mots_activites);
						}
					}
					$association[$k] = serialize($association[$k]);
				}

				$association = array_map('trim', $association);
				$associations[] = $association;
				$total--;
				$i++;

				if ($total == 0 or $i == 100) {
					$i = 0;
					$res = sql_insertq_multi('spip_associations_imports_sources', $associations);
					$associations = array();
				}
			}
		}

		if (
			$count = sql_countsel(
				'spip_associations_imports_sources',
				array('statut=1', 'id_associations_import=' . $id_associations_import)
			)
		) {
			sql_updateq(
				'spip_associations_imports',
				array('statut' => 'processing'),
				'id_associations_import=' . intval($id_associations_import)
			);
			$res = array($id_associations_import, $count);
		} else {
			$res = false;
		}
	}
	return $res;
}
