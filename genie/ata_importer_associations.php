<?php

if (!defined("_ECRIRE_INC_VERSION")) {
  return;
}

function genie_ata_importer_associations($t) {
  // Adapté de mailshot/genie/mailshot_bulksend
  spip_log("ata_importer_associations:meta_processing:" . $GLOBALS['meta']['ata_import_processing'],"ata_importer_associations." . _LOG_INFO_IMPORTANTE);

  if (sql_countsel('spip_associations_imports', 'statut=' . sql_quote('processing'))) {
    // Sécurité pour que le cron se relance
		// sera effacee dans ata_importer_associations_traiter_sources si l'envoi est fini
    $GLOBALS['meta']['ata_import_processing'] = 'oui';
    include_spip('ata_importer_utils');
    $boost = false;

    $nb = 0;
    $f_relance = _DIR_TMP."ata_importer_relance.txt";
    $f_last = _DIR_TMP."ata_importer_last.txt";
    $relance = true;

    if (!$boost) {
      lire_fichier($f_relance, $nb);
    }

    if (!$nb = intval($nb)) {
      $relance = false;
      list($periode, $nb) = ata_importer_cadence();

      $now = time();
      if (!$boost) {
        lire_fichier($f_last, $last);

        if ($last = intval($last) and ($dt = $now - $last) > 0) {
          $c = min(2, $dt / $periode);
          $nb = intval(round($nb * $c, 0));
          spip_log("Correction sur nb : $c ($dt au lieu de $periode) => $nb","ata_importer_associations." . _LOG_INFO_IMPORTANTE);
        }
      }
      ecrire_fichier($f_last, $now);
    }

    include_spip('inc/ata_importer_associations');
    $restant = ata_importer_associations($nb);

    if ($restant > 0 and !$boost) {
      ecrire_fichier($f_relance, $restant);
      $boost = true;
    } elseif ($relance) {
      @unlink($f_relance);
      list($periode, $nb) = ata_importer_cadence();
      $now = time();
      lire_fichier($f_last, $last);
      if ($last = intval($last) and ($dt = $now - $last) > $periode) {
        $boost = true;
      }
    }

    if ($boost) {
      return -($t - $periode);
    }
  }
  else {
    if (!function_exists('ata_importer_update_meta')) {
      include_spip('inc/ata_importer_utils');
    }
    ata_importer_update_meta();
  }
  // 0 si rien à faire
  // 1 si traitement terminé
  // négatif si le traitement doit se poursuivre
  return 0;
}