<?php
/*
 * Script créant et vérifiant que les champs requis s'ajoutent bien
 *
 */
    if(!defined('INC_FROM_DOLIBARR')) {
        define('INC_FROM_CRON_SCRIPT', true);
        require('../config.php');
        $ATMdb=new TPDOdb;
        $ATMdb->debug=true;
    }
    else{
        $ATMdb=new TPDOdb;

    }

	global $db;

	if (isModEnabled('workstation') ){
		dol_include_once('/workstationatm/class/workstation.class.php');
	}
    require_once __DIR__.'/../class/ordre_fabrication_asset.class.php';

    $o=new TAssetOF;
	$o->init_db_by_vars($ATMdb);

	$o=new TAssetOFLine;
	$o->init_db_by_vars($ATMdb);
	//if (class_exists('TWorkstation')) {
	if (isModEnabled('workstationatm')) {
		$o=new TAssetWorkstation;
		$o->init_db_by_vars($ATMdb);
	}
	else {
		exit($langs->trans("moduleWorkstationNeeded").' : <a href="https://github.com/ATM-Consulting/dolibarr_module_workstation" target="_blank">'.$langs->trans('DownloadModule').'</a>');
	}

	$o=new TAssetWorkstationOF;
	$o->init_db_by_vars($ATMdb);

	$o=new TAssetWorkstationProduct;
	$o->init_db_by_vars($ATMdb);

	$o=new TAssetWorkstationTask;
	$o->init_db_by_vars($ATMdb);

	dol_include_once('/of/class/of_amount.class.php');

	$o=new AssetOFAmounts($db);
	$o->init_db_by_vars();
