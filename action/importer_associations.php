<?php

if (!defined("_ECRIRE_INC_VERSION")) {
  return;
}

include_spip('inc/ata_importer_utils');

function action_importer_associations($args = null) {
  if (is_null($args)) {
		$securiser_action = charger_fonction ('securiser_action', 'inc');
		$args = $securiser_action();
	}
	$id_associations_import = $args;
	$redirect = parametre_url(generer_action_auteur('importer_associations', $args), 'step', intval(_request('step') + 1), '&');

	$res = false;

	// Charger fonction d'importation
	if ($importer_associations = ata_importer_associations_init($redirect)) {
		include_spip('inc/headers');
		echo redirige_formulaire(generer_url_ecrire('configurer_ata_associations', 'status=ok', '', true, true));
	}

	echo(str_repeat("<br />\r\n", 256));
	while (@ob_get_level()) {
		@ob_flush();
		@flush();
		@ob_end_flush();
	}



	// include_spip('inc/headers');
	// $r = generer_url_ecrire('configurer_ata_associations');
	// echo redirige_formulaire($r);


	// $status_file = $args;
	// $step = intval(_request('step') + 1);
	// $redirect = parametre_url(generer_action_auteur('importer_associations', $status_file), 'step', $step, '&');

	// $stepLog = $step - 1;
	// spip_log('Action importer, étape ' . $stepLog, 'ata_import_debug.' . _LOG_INFO_IMPORTANTE);

	// $importer_associations = charger_fonction('ata_importer_associations', 'inc');
	
	// if($importer_associations($status_file, $redirect)) {
	// 	// ata_importer_end($status_file, 'fini');
	// 	include_spip('inc/headers');
	// 	$r = generer_url_ecrire('configurer_ata_associations');
	// 	echo redirige_formulaire($r);
	// }

	// forcer l'envoi du buffer par tous les moyens !
	// echo(str_repeat("<br />\r\n", 256));
	// while (@ob_get_level()) {
	// 	@ob_flush();
	// 	@flush();
	// 	@ob_end_flush();
	// }
}



