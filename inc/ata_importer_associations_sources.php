<?php

if (!defined("_ECRIRE_INC_VERSION")) {
  return;
}

function inc_ata_importer_associations_sources($donnees, $publier = 0) {
  $total = count($donnees);
  $now = date('Y-m-d H:i:s');
  
  if ($total) {
    include_spip('action/editer_objet');
    $infos_import = array(
      'total' => $total,
      'encours' => 0,
      'date_start' => $now,
      'statut' => 'pending',
    );
    $id_associations_import = objet_inserer('associations_imports', null, $infos_import);
    
    if ($id_associations_import) {
      $associations = array();
      $i = 0;

      foreach($donnees as $asso) {
        $association = array();
        $association['id_associations_import'] = $id_associations_import;
        if (empty($asso['nom'])) {
          continue;
        }
        $association['nom'] = $asso['nom'];
        $membre_fraap = '';
        $membre_fraap = strtolower($asso['membre_fraap']);
        if ($membre_fraap == '' or $membre_fraap == 'non') {
          $association['membre_fraap'] = 0;
        } else {
          $association['membre_fraap'] = 1;
        } 
        $association['voie'] = $asso['adresse'];
        $association['complement'] = $asso['adresse2'];
        $association['code_postal'] = $asso['code_postal'];
        $association['ville'] = $asso['ville'];
        $association['url_site'] = $asso['site_internet'];
        $association['email'] = $asso['email_1'];
        $association['facebook'] = $asso['facebook'];
        $association['twitter'] = $asso['twitter'];
        $association['instagram'] = $asso['instagram'];
        $association['creation'] = str_replace(PHP_EOL, ';', $asso['activites_creation']);
        $association['diffusion'] = str_replace(PHP_EOL, ';', $asso['activites_diffusion']);
        $association['formation'] = str_replace(PHP_EOL, ';', $asso['activites_formation_ressources']);
        $association['transmission'] = str_replace(PHP_EOL, ';', $asso['activites_transmission']);
        $association['residences'] = str_replace(PHP_EOL, ';', $asso['residences']);
        $association['statut'] = 1;
        $association['publier'] = $publier;
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
    if (sql_countsel('spip_associations_imports_sources', array('statut=1', 'id_associations_import=' . $id_associations_import))) {
      // Informer le cron des imports à faire.
      //sql_updateq('spip_associations_imports', array('statut' => 'processing'), 'id_associations_import=' . intval($id_associations_import));
      include_spip('inc/ata_importer_utils');
      //ata_importer_update_meta($infos_import['statut'] == 'processing');
    }
  }
}

function ata_importer_associations_traiter_sources($nb_max = 5, $offset = 0) {
  $nb_restant = $nb_max;
  
  $now = $_SERVER['REQUEST_TIME'];
  
  if (!$now) {
    $now = time();
  }

  define('_ATA_IMPORTER_MAX_TIME', $now + 25); // 25 secondes max
  
  $offset = intval($offset);

  // Mémoriser une fois les mots-clés Activités, 
  // plutôt que de faire une requête sql à chaque itération d'association
    $mots = sql_allfetsel('id_mot, titre', 'spip_mots','id_groupe_racine=1');
    
  // Mots-clés Activités dans un tableau de la forme id_mot => titre
  // Au passage, les titres sont débarassés de leurs accents, 
  // en minuscules et sans "œ".
  $mots_activites = array();
  include_spip('inc/ata_importer_utils');
  
  foreach ($mots as $mot) {
    $titre = strtolower(ata_importer_supprimer_accents($mot['titre']));
    $titre = str_replace('œ', 'oe', $titre);
    $mots_activites[$mot['id_mot']] = $titre;
  }

  $imports = sql_allfetsel('*', 'spip_associations_imports', 'statut=' . sql_quote('processing', '', 'id_associations_import', '0,2'));

  foreach($imports as $import) {
    spip_log("Traiter_sources #".$import['id_associations_import']." ".$import['encours']."/".$import['total']." (max $nb_max)","ata_traiter_sources." . _LOG_INFO_IMPORTANTE);

    if (time() > _ATA_IMPORTER_MAX_TIME) {
      return $nb_restant;
    }

    // Récupérer les N prochaines assos à traiter
    $assos = sql_allfetsel('*', 'spip_associations_imports_sources', 'id_associations_import=' . intval($import['id_associations_import']) . ' AND statut=1', '', '', "$offset, $nb_max");

    if (count($assos)) {
      foreach($assos as $asso) {
        if (time() > _ATA_IMPORTER_MAX_TIME) {
          return $nb_restant;
        }
        $erreur = ata_importer_associations_inserer($asso, $mots_activites);
        $done = false;

      }
    }
  }

  return $nb_max;
}


function ata_importer_associations_inserer($asso, $mots_activites) {
  
  // Chercher un éventuel doublon
  $ids_nom = sql_allfetsel('id_association', 'spip_associations', 'nom=' . sql_quote($asso['nom']));
  if (count($ids_nom)) {
    $from = 'spip_adresses AS L2 INNER JOIN spip_adresses_liens AS L1 ON (L1.id_adresse = L2.id_adresse)';
    $where = array(
      sql_in('L1.id_objet', $ids_nom),
      'L1.objet=' . sql_quote('association'),
      'L2.code_postal=' . sql_quote($asso['code_postal'])
    );
    $ids_code = sql_allfetsel('L2.id_adresse', $from, $where);

    if (count($ids_code)) {
      spip_log("Association " . $asso['nom'] . " déjà présente : les données n'ont pas été importées.", 'ata_import_csv.' . _LOG_INFO_IMPORTANTE);
      // Abandon
      return '';
    }
  }

  $verifier = charger_fonction('verifier', 'inc');
  $erreur_url = $verifier(
    $asso['url_site'], 
    'url', 
    array(
      'mode' => 'protocole_seul',
      'type_protocole' => 'web'
    )
  );
  if ($erreur_url) {
    $asso['url_site'] = substr_replace($asso['url_site'], 'http://', 0, 0);
  }

  $champs_asso = array(
    'nom' => $asso['nom'],
    'url_site' => $asso['url_site'],
    'membre_fraap' => $asso['membre_fraap']
  );
  $id_association = objet_inserer('association', null, $champs_asso);

  if ($id_association) {
    return '';
  } else {
    return false;
  }
}