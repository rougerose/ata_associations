<?php
/**
 * Déclarations relatives à la base de données
 *
 * @plugin     ATA associations
 * @copyright  2020
 * @author     christophe le drean
 * @licence    GNU/GPL v3
 * @package    SPIP\Ata_associations\Pipelines
 */

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}


/**
 * Déclaration des alias de tables et filtres automatiques de champs
 *
 * @pipeline declarer_tables_interfaces
 * @param array $interfaces
 *     Déclarations d'interface pour le compilateur
 * @return array
 *     Déclarations d'interface pour le compilateur
 */
function ata_associations_declarer_tables_interfaces($interfaces) {

	$interfaces['table_des_tables']['associations'] = 'associations';
	$interfaces['table_des_tables']['associations_imports_sources'] = 'associations_imports_sources';
	$interfaces['table_des_tables']['associations_imports'] = 'associations_imports';

	return $interfaces;
}


/**
 * Déclaration des objets éditoriaux
 *
 * @pipeline declarer_tables_objets_sql
 * @param array $tables
 *     Description des tables
 * @return array
 *     Description complétée des tables
 */
function ata_associations_declarer_tables_objets_sql($tables) {

	$tables['spip_associations'] = array(
		'type' => 'association',
		'principale' => 'oui',
		'field'=> array(
			'id_association'     => 'bigint(21) NOT NULL',
			'id_rubrique'        => 'bigint(21) NOT NULL DEFAULT 0',
			'id_secteur'         => 'bigint(21) NOT NULL DEFAULT 0',
			'nom'                => 'text NOT NULL DEFAULT ""',
			'membre_fraap'       => 'tinyint(1) NOT NULL DEFAULT 0',
			'url_site'           => 'tinytext NOT NULL DEFAULT ""',
			'date'               => 'datetime NOT NULL DEFAULT "0000-00-00 00:00:00"',
			'statut'             => 'varchar(20)  DEFAULT "0" NOT NULL',
			'maj'                => 'TIMESTAMP'
		),
		'key' => array(
			'PRIMARY KEY'        => 'id_association',
			'KEY id_rubrique'    => 'id_rubrique',
			'KEY id_secteur'     => 'id_secteur',
			'KEY statut'         => 'statut',
		),
		'titre' => 'nom AS titre, "" AS lang',
		'date' => 'date',
		'champs_editables'  => array('nom', 'membre_fraap', 'url_site', 'id_rubrique', 'id_secteur'),
		'champs_versionnes' => array('membre_fraap', 'url_site', 'id_rubrique', 'id_secteur'),
		'rechercher_champs' => array('nom' => 5, 'url_site' => 5),
		'tables_jointures'  => array(),
		'statut_textes_instituer' => array(
			'prepa'    => 'texte_statut_en_cours_redaction',
			'prop'     => 'texte_statut_propose_evaluation',
			'publie'   => 'texte_statut_publie',
			'refuse'   => 'texte_statut_refuse',
			'poubelle' => 'texte_statut_poubelle',
		),
		'statut'=> array(
			array(
				'champ'     => 'statut',
				'publie'    => 'publie',
				'previsu'   => 'publie,prop,prepa',
				'post_date' => 'date',
				'exception' => array('statut','tout')
			)
		),
		'texte_changer_statut' => 'association:texte_changer_statut_association',
	);

	$tables['spip_associations_imports_sources'] = array(
		'type' => 'associations_imports_source',
		'principale' => 'oui',
		'table_objet_surnoms' => array('associationsimportssource'),
		'field'=> array(
			'id_associations_imports_source' => 'bigint(21) NOT NULL',
			'id_associations_import' => 'bigint(21) NOT NULL DEFAULT 0',
			'nom'                => 'text NOT NULL DEFAULT ""',
			'url_site'           => 'tinytext NOT NULL DEFAULT ""',
			'membre_fraap'       => 'tinyint(1) NOT NULL DEFAULT 0',
			'voie'               => 'tinytext NOT NULL DEFAULT ""',
			'complement'         => 'tinytext NOT NULL DEFAULT ""',
			'code_postal'        => 'varchar(40) NOT NULL DEFAULT ""',
			'ville'              => 'tinytext NOT NULL DEFAULT ""',
			'email'              => 'varchar(255) NOT NULL DEFAULT ""',
			'facebook'           => 'varchar(255) NOT NULL DEFAULT ""',
			'instagram'          => 'varchar(255) NOT NULL DEFAULT ""',
			'twitter'            => 'varchar(255) NOT NULL DEFAULT ""',
			'creation'           => 'text NOT NULL DEFAULT ""',
			'diffusion'          => 'text NOT NULL DEFAULT ""',
			'formation'          => 'text NOT NULL DEFAULT ""',
			'transmission'       => 'text NOT NULL DEFAULT ""',
			'residences'         => 'text NOT NULL DEFAULT ""',
			'statut'             => 'tinyint(1) NOT NULL DEFAULT 0',
			'publier'            => 'tinyint(1) NOT NULL DEFAULT 0',
			'lat' => 'double NULL NULL',
			'lon' => 'double NULL NULL',
			'maj'                => 'TIMESTAMP'
		),
		'key' => array(
			'PRIMARY KEY'        => 'id_associations_imports_source',
			'KEY statut'         => 'statut',
		),
		'titre' => 'nom AS titre, "" AS lang',
		 #'date' => '',
		'champs_editables'  => array(),
		'champs_versionnes' => array(),
		'rechercher_champs' => array(),
		'tables_jointures'  => array(),
	);

	$tables['spip_associations_imports'] = array(
		'type' => 'associations_import',
		'principale' => 'oui',
		'table_objet_surnoms' => array('associationsimport'),
		'field'=> array(
			'id_associations_import' => 'bigint(21) NOT NULL',
			'id_rubrique'        => 'bigint(21) NOT NULL DEFAULT 0',
			'statut'             => 'varchar(20)  DEFAULT "processing" NOT NULL',
			'total'              => 'bigint(21) NOT NULL DEFAULT 0',
			'encours'            => 'bigint(21) NOT NULL DEFAULT 0',
			'id_rubrique'        => 'bigint(21) NOT NULL DEFAULT 0',
			'date_start' => "datetime NOT NULL DEFAULT '0000-00-00 00:00:00'",
			'statut'             => 'varchar(20)  DEFAULT "0" NOT NULL',
			'maj'                => 'TIMESTAMP'
		),
		'key' => array(
			'PRIMARY KEY'        => 'id_associations_import',
			'KEY id_rubrique'    => 'id_rubrique',
			'KEY statut'         => 'statut',
		),
		'titre' => '"" AS titre, "" AS lang',
		 #'date' => '',
		'champs_editables'  => array('id_rubrique'),
		'champs_versionnes' => array('id_rubrique'),
		'rechercher_champs' => array(),
		'tables_jointures'  => array(),
		'statut_textes_instituer' => array(
			'processing'    => 'associations_import:texte_statut_processing',
			'pending'     => 'associations_import:texte_statut_pending',
			'end'   => 'associations_import:texte_statut_end',
		),
		'statut'=> array(
			array(
				'champ'     => 'statut',
				'publie'    => 'processing',
				'previsu'   => 'processing',
				'post_date' => 'date',
				'exception' => array('statut','tout')
			)
		),
		'texte_changer_statut' => 'associations_import:texte_changer_statut_associations_import',
	);
	return $tables;
}
