<?php

if (!defined("_ECRIRE_INC_VERSION")) {
  return;
}

include_spip('inc/ata_importer_utils');
include_spip('action/editer_objet');

function inc_ata_importer_associations($status_file, $redirect = '') {
  spip_log('inc… Entrée dans la fonction', 'ata_import_debug.' ._LOG_INFO_IMPORTANTE);

  // script adapté de inc_sauvegarder_dist
  $status_file = _DIR_TMP . basename($status_file) . '.txt';

  if (lire_fichier($status_file, $status)) {
    $status = unserialize($status);
    
    $timeout = ini_get('max_execution_time');
    if (!$timeout) {
      $timeout = 15;
    }
    $max_time = time() + $timeout / 2;
    
    include_spip('inc/minipres');
    @ini_set('zlib.output_compression', '0'); // pour permettre l'affichage au fur et a mesure

    $titre = _T('ata_associations:info_import_en_cours') . ' (X lignes) ';
    $balise_img = chercher_filtre('balise_img');
    $titre .= $balise_img(chemin_image('searching.gif'));
    
    echo(install_debut_html($titre));
    
    // script de rechargemespip_log('inc_ata_importer_associations', 'ata_import_debug.' ._LOG_INFO_IMPORTANTE);nt auto sur timeout
    echo http_script("window.setTimeout('location.href=\"" . $redirect . "\";'," . ($timeout * 1000) . ')');
    echo "<div style='text-align: left'>\n";
    
    // au premier coup on ne fait rien sauf afficher l'ecran de sauvegarde
    $res = false;
    if (_request('step')) {
      spip_log('inc... : chargement CSV, étape ' . _request('step'), 'ata_import_debug.' ._LOG_INFO_IMPORTANTE);
      $importer_csv = charger_fonction('importer_csv', 'inc');
		  $donnees = $importer_csv($status['fichier']['csv'][0]['tmp_name'], true);
      
      $options = array(
        'callback_progression' => 'ata_importer_afficher_progression',
        'max_time' => $max_time
      );
      $res = ata_importer_donnees_associations($donnees, $status_file, $options);
    }

    echo("</div>\n");

    if (!$res and $redirect) {
      spip_log('inc... : Relance via js', 'ata_import_debug.' ._LOG_INFO_IMPORTANTE);
      echo ata_importer_relance($redirect);
    }
    echo(install_fin_html());
    if (@ob_get_contents()) {
      ob_end_flush();
    }
    flush();

    return $res;
  }
}


function ata_importer_donnees_associations($donnees, $status_file, $options) {
  spip_log('importer_donnees : entrée dans la fonction', 'ata_import_debug.' ._LOG_INFO_IMPORTANTE);
  
  $callback_progression = isset($options['callback_progression']) ? $options['callback_progression'] : '';
  $max_time = isset($options['max_time']) ? $options['max_time'] : 0;

  lire_fichier($status_file, $status);
  $status = unserialize($status);

  if (!isset($status['lignes_importees'])) {
    $status['lignes_importees'] = 0;
  }

  if (!isset($status['lignes_restantes'])) {
    $status['lignes_restantes'] = 0;
  }

  if (!isset($status['lignes_total'])) {
    $status['lignes_total'] = count($donnees);
  }

  if ($status['lignes_total'] == 0) {
    spip_log("Aucune données à importer", 'ata_import_debug.' . _LOG_INFO_IMPORTANTE);
    // Pas de données. Echec et fin.
    return true;
  }

  $status['lignes_restantes'] = $status['lignes_total'] - $status['lignes_importees'];

  // Etape
  $status['etape'] = _request('step');
  
  $log = 'importer_donnees, avant boucle : ';
  $log .= 'Lignes importées = ' . $status['lignes_importees'] . '. ';
  $log .= 'Lignes restantes = ' . $status['lignes_restantes'] . '. ';
  $log .= 'Lignes totales = ' . $status['lignes_total'] . '. ';

  spip_log($log, 'ata_import_debug.' ._LOG_INFO_IMPORTANTE);

  if ($status['lignes_importees'] >= 0 and $status['lignes_restantes'] > 0) {

    if ($status['lignes_importees'] > 0) {
      $offset = $status['lignes_importees'] - 1;
      $donnees = array_slice($donnees, $offset);
      spip_log('Réduction du tableau des données à importer, à partir de ' . $offset, 'ata_import_debug.' . _LOG_INFO_IMPORTANTE);
    }
    
    if ($callback_progression) {
      // TODO détailler la progression
      //$callback_progression();
    }

    $insertions = array_chunk($donnees, 50);
    $compteur_insertions = 0;
    
    $verifier = charger_fonction('verifier', 'inc');

    /*
    // TEST
    foreach ($insertions as $cle_chunk => $chunk) {
      foreach ($chunk as $cle_asso => $asso) {
        $asso_champs = array('nom' => $asso['nom']);
        $compteur_insertions++;
        $status['lignes_importees'] = $compteur_insertions;
        $status['lignes_restantes'] = $status['lignes_total'] - $status['lignes_importees'];
        
        if ($max_time and time() > $max_time) {
          $log = 'importer_donnees, fin de boucle asso : Timeout. ';
          $log .= 'Lignes importées = ' . $status['lignes_importees'] . '. ';
          $log .= 'Lignes restantes = ' . $status['lignes_restantes'] . '. ';
          spip_log($log, 'ata_import_debug.' ._LOG_INFO_IMPORTANTE);
          break;
        }
      }
      if ($max_time and time() > $max_time) {
        $log = 'importer_donnees, fin de boucle chunk : Timeout. ';
        $log .= 'Lignes importées = ' . $status['lignes_importees'] . '. ';
        $log .= 'Lignes restantes = ' . $status['lignes_restantes'] . '. ';
        spip_log($log, 'ata_import_debug.' ._LOG_INFO_IMPORTANTE);
        break;
      }
    }

    $log = 'importer_donnees, fin de script. ';
    $log .= 'Lignes importées = ' . $status['lignes_importees'] . '. ';
    $log .= 'Lignes restantes = ' . $status['lignes_restantes'] . '. ';
    spip_log($log, 'ata_import_debug.' ._LOG_INFO_IMPORTANTE);
    // FIN TEST
    */

    
    foreach ($insertions as $chunk) {
      foreach ($chunk as $association) {
        $association = array_map('trim', $association);
        
        // Init des tableaux
        $champs_asso = array();
        $champs_adresse = array();
        
        // nom
        $champs_asso['nom'] = $association['nom'];

        // Pas de nom, le traitement de la ligne du tableau est arrêtée ici.
        if (empty($champs_asso['nom'])) {
          spip_log("Association sans nom à la ligne " . $compteur_insertions + 1 . "du fichier CSV", 'ata_import_csv.' . _LOG_INFO_IMPORTANTE);
          break;
        }

        // site_internet
        $erreur_url = $verifier($association['site_internet'], 'url', array('mode' => 'protocole_seul'));
        if ($erreur_url) {
          $association['site_internet'] = substr_replace($association['site_internet'], 'http://', 0);
        }
        $champs_asso['site_internet'] = $association['site_internet'];

        // membre_fraap
        $membre_fraap = strtolower($association['membre_fraap']);
        if ($membre_fraap == '' or $membre_fraap == 'non') {
          $champs_asso['membre_fraap'] = 0;
        } else {
          $champs_asso['membre_fraap'] = 1;
        }

        // adresse
        $champs_adresse['titre'] = $champs_asso['nom'];
        $champs_adresse['voie'] = $association['adresse'];

        // adresse2
        $champs_adresse['complement'] = $association['adresse2'];

        // code_postal
        $champs_adresse['code_postal'] = $association['code_postal'];

        // ville
        $champs_adresse['ville'] = $association['ville'];
        

        // email_1

        // facebook

        // twitter

        // instagram

        

        // activites_carto

        // activites_creation

        // activites_diffusion

        // activites_formation_ressources

        // activites_transmission

        // Insertion des données
        //$id_association = objet_inserer('association', null, $champs_asso);

        // TODO: Tester un résultat qui indique que tous les enregistrements pour une asso sont faits.
        $compteur_insertions++;
        $status['lignes_importees'] = $compteur_insertions;
        $status['lignes_restantes'] = $status['lignes_total'] - $status['lignes_importees'];

        if ($max_time and time() > $max_time) {
          $log = 'importer_donnees, fin de boucle asso : Timeout. ';
          $log .= 'Lignes importées = ' . $status['lignes_importees'] . '. ';
          $log .= 'Lignes restantes = ' . $status['lignes_restantes'] . '. ';
          spip_log($log, 'ata_import_debug.' ._LOG_INFO_IMPORTANTE);
          break;
        }
      }
      
      if ($max_time and time() > $max_time) {
        $log = 'importer_donnees, fin de boucle chunk : Timeout. ';
        $log .= 'Lignes importées = ' . $status['lignes_importees'] . '. ';
        $log .= 'Lignes restantes = ' . $status['lignes_restantes'] . '. ';
        spip_log($log, 'ata_import_debug.' ._LOG_INFO_IMPORTANTE);
        break;
      }
    }
    // Todo: ajouter progression ?
    $log = 'importer_donnees, fin des boucles. ';
    $log .= 'Lignes importées = ' . $status['lignes_importees'] . '. ';
    $log .= 'Lignes restantes = ' . $status['lignes_restantes'] . '. ';
    spip_log($log, 'ata_import_debug.' ._LOG_INFO_IMPORTANTE);

    ecrire_fichier($status_file, serialize($status));
    if ($max_time and time() > $max_time and $status['lignes_restantes'] > 0) {
      spip_log('importer_donnees, fin boucles : Timeout + return false. Lignes restantes = ' . $status['lignes_restantes'], 'ata_import_debug.' ._LOG_INFO_IMPORTANTE);
      // l'import n'est pas terminé, mais le temps imparti est écoulé.
      return false;
    }
  }
  spip_log('importer_donnees, fin du script : Return true', 'ata_import_debug.' ._LOG_INFO_IMPORTANTE);
  // import terminé
  return true;
}

