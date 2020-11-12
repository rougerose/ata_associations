<?php

if (!defined("_ECRIRE_INC_VERSION")) {
  return;
}


function inc_ata_importer_csv($donnees, $publier = 0) {
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
        if (empty($asso['nom'])) {
          continue;
        }
        
        $membre_fraap = '';
        $membre_fraap = strtolower($asso['membre_fraap']);
        if ($membre_fraap == '' or $membre_fraap == 'non') {
          $asso['membre_fraap'] = 0;
        } else {
          $asso['membre_fraap'] = 1;
        } 
        
        $association = array(
          'id_associations_import' => $id_associations_import,
          'nom' => $asso['nom'],
          'voie' => $asso['adresse'],
          'complement' => $asso['adresse2'],
          'code_postal' => $asso['code_postal'],
          'ville' => $asso['ville'],
          'url_site' => $asso['site_internet'],
          'email' => $asso['email_1'],
          'facebook' => $asso['facebook'],
          'twitter' => $asso['twitter'],
          'instagram' => $asso['instagram'],
          'statut' => 1,
          'publier' => $publier,
        );
        
        $association['creation'] = str_replace(PHP_EOL, ';', $asso['activites_creation']);
        $association['diffusion'] = str_replace(PHP_EOL, ';', $asso['activites_diffusion']);
        $association['formation'] = str_replace(PHP_EOL, ';', $asso['activites_formation_ressources']);
        $association['transmission'] = str_replace(PHP_EOL, ';', $asso['activites_transmission']);
        $association['residences'] = str_replace(PHP_EOL, ';', $asso['residences']);

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
    if ($count = sql_countsel('spip_associations_imports_sources', array('statut=1', 'id_associations_import=' . $id_associations_import))) {
      sql_updateq('spip_associations_imports', array('statut' => 'processing'), 'id_associations_import=' . intval($id_associations_import));
      return $res = $id_associations_import;
      // Informer le cron des imports à faire.
      // sql_updateq('spip_associations_imports', array('statut' => 'processing'), 'id_associations_import=' . intval($id_associations_import));
      // include_spip('inc/ata_importer_utils');
      // ata_importer_update_meta($infos_import['statut'] == 'processing');
    } else {
      return $res = false;
    }
  }
}