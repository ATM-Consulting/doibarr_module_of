<?php

define('INC_FROM_CRON_SCRIPT', true);
set_time_limit(0);
require('../config.php');
require('../lib/asset.lib.php');
require('../class/asset.class.php');
require('../class/ordre_fabrication_asset.class.php');

//Interface qui renvoie les emprunts de ressources d'un utilisateur
$PDOdb=new TPDOdb;

$get = __get('get','emprunt');

traite_get($PDOdb, $get);

function traite_get(&$PDOdb, $case) {	
	switch (strtolower($case)) {
        case 'autocomplete':
            __out(_autocomplete($PDOdb,GETPOST('fieldcode'),GETPOST('term'),GETPOST('fk_product')));
            break;
        case 'autocomplete-serial':
            __out(_autocompleteSerial($PDOdb,GETPOST('lot_number')));
            break;
		case 'addofproduct':
			__out(_addofproduct($PDOdb,GETPOST('id_assetOf'),GETPOST('fk_product'),GETPOST('type')));

			break;
		case 'deletelineof':
			__out(_deletelineof($PDOdb,GETPOST('idLine'),GETPOST('type')), 'json');
			break;
		case 'addlines':
			__out(_addlines($PDOdb,GETPOST('idLine'),GETPOST('qty')),GETPOST('type'));
			break;
		case 'addofworkstation':
			__out(_addofworkstation($PDOdb,GETPOST('id_assetOf'),GETPOST('fk_asset_workstation')));
			break;	
		case 'deleteofworkstation':	
			__out(_deleteofworkstation($PDOdb,GETPOST('id_assetOf'), GETPOST('fk_asset_workstation_of') ));
			break;
		case 'measuringunits':
			__out(_measuringUnits(GETPOST('type'), GETPOST('name')), 'json');
			break;
		case 'getofchildid':
			$Tid = array();
			$assetOf=new TAssetOF;
			$assetOf->load($PDOdb, __get('id',0,'integer'));
			
			$assetOf->getListeOFEnfants($PDOdb, $Tid);
			
			__out($Tid);
			break;
	}
}

function _addofworkstation(&$PDOdb, $id_assetOf, $fk_asset_workstation, $nb_hour=0) {	
	$of=new TAssetOF;
	$of->load($PDOdb, $id_assetOf);
	
	$k = $of->addChild($PDOdb, 'TAssetWorkstationOF');
	
	$of->TAssetWorkstationOF[$k]->fk_asset_workstation = $fk_asset_workstation;
	$of->TAssetWorkstationOF[$k]->nb_hour = $nb_hour;
	$of->save($PDOdb);
}

function _deleteofworkstation(&$PDOdb, $id_assetOf, $fk_asset_workstation_of) 
{
	$of=new TAssetOF;
	$of->load($PDOdb, $id_assetOf);
	$of->removeChild('TAssetWorkstationOF', $fk_asset_workstation_of);
	$of->save($PDOdb);	
}

function _autocompleteSerial(&$PDOdb, $lot='') {
        
    $sql = 'SELECT DISTINCT(a.serial_number) ';
    $sql .= 'FROM '.MAIN_DB_PREFIX.'asset as a WHERE 1 ';
    
    if (!empty($lot)) $sql .= ' AND lot_number LIKE '.$PDOdb->quote($lot.'%').' ';
    
    $sql .= 'ORDER BY a.serial_number';
//      print $sql;
    $PDOdb->Execute($sql);
    while ($PDOdb->Get_line()) 
    {
        $TResult[] = $PDOdb->Get_field('serial_number');
    }
    
    $PDOdb->close();
    return $TResult;
    
}
//Autocomplete sur les différents champs d'une ressource
function _autocomplete(&$PDOdb,$fieldcode,$value,$fk_product=0,$lot_number=0, $table='assetlot')
{
	$value = trim($value);
	
	$sql = 'SELECT DISTINCT(al.'.$fieldcode.') ';
	$sql .= 'FROM '.MAIN_DB_PREFIX.$table.' as al ';
	
	if($fk_product)
	{
		$sql .= 'LEFT JOIN '.MAIN_DB_PREFIX.'asset as a ON (a.'.$fieldcode.' = al.'.$fieldcode.') ';
		$sql .= 'LEFT JOIN '.MAIN_DB_PREFIX.'product as p ON (p.rowid = a.fk_product) ';
	}
	
	if (!empty($value)) $sql .= 'WHERE al.'.$fieldcode.' LIKE '.$PDOdb->quote($value.'%').' ';
	
	if (!empty($value) && $fk_product) $sql .= 'AND p.rowid = '.(int) $fk_product.' ';
	elseif ($fk_product) $sql .= 'WHERE p.rowid = '.(int) $fk_product.' ';
	
	$sql .= 'ORDER BY al.'.$fieldcode;
//		print $sql;
	$PDOdb->Execute($sql);
	while ($PDOdb->Get_line()) 
	{
		$TResult[] = $PDOdb->Get_field($fieldcode);
	}
	
	$PDOdb->close();
	return $TResult;
}

function _addofproduct(&$PDOdb,$id_assetOf,$fk_product,$type,$qty=1, $lot_number = '')
{	
	global $db;
	
	$TassetOF = new TAssetOF;
	$TassetOF->load($PDOdb, $id_assetOf);
	$TassetOF->addLine($PDOdb, $fk_product, $type,$qty,0, $lot_number);
	$TassetOF->save($PDOdb);
	
	// Pour ajouter directement les stations de travail, attachées au produit grâce à l'onglet "station de travail" disponible dans la fiche produit
	if($type == "TO_MAKE") {
		$sql = "SELECT fk_asset_workstation, nb_hour";
		$sql.= " FROM ".MAIN_DB_PREFIX."asset_workstation_product";
		$sql.= " WHERE fk_product = ".$fk_product;
		$resql = $db->query($sql);
		
		if($resql) {
			
			while($res = $db->fetch_object($resql)) {
				
				_addofworkstation($PDOdb, $id_assetOf, $res->fk_asset_workstation, $res->nb_hour);

			}
			
		}
		
	}

}

function _deletelineof(&$PDOdb,$idLine,$type){
	$TAssetOFLine = new TAssetOFLine;
	$TAssetOFLine->load($PDOdb, $idLine);	
	
	//Permet de supprimer le/les OF enfant(s)
	$TAssetOF = new TAssetOF;
	$TAssetOF->load($PDOdb, $TAssetOFLine->fk_assetOf);
	$id_of_deleted = $TAssetOF->deleteOFEnfant($PDOdb, $TAssetOFLine->fk_product);
	
	$TAssetOFLine->delete($PDOdb);
	
	return $id_of_deleted;
}

function _addlines(&$PDOdb,$idLine,$qty){
	global $db, $conf;
	
	dol_include_once('product/class/product.class.php');
	
	//On met à jour la 1ère ligne des TO_MAKE
	$TAssetOFLine = new TAssetOFLine;
	//$PDOdb->debug = true;
	$TAssetOFLine->load($PDOdb, $idLine);
	$TAssetOFLine->qty = $_REQUEST['qty'];
	$TAssetOFLine->save($PDOdb);

	//On charge l'OF pour pouvoir parcourir ses lignes et mettre à jour les quantités
	$TAssetOF = new TAssetOF;
	$TAssetOF->load($PDOdb, $TAssetOFLine->fk_assetOf);
	
	//Id des lignes modifiés
	$TIdLineModified = array($TAssetOFLine->fk_assetOf);
	//Id des nouveaux OF créés
	$TNewIdAssetOF = array();
	
 	_updateNeeded($TAssetOF, $PDOdb, $db, $conf, $TAssetOFLine->fk_product, $_REQUEST['qty'], $TIdLineModified, $TNewIdAssetOF);
	
	return array($TIdLineModified, $TNewIdAssetOF);
}

function _updateToMake($TAssetOFChildId = array(), &$PDOdb, &$db, &$conf, $fk_product, $qty, &$TIdLineModified, &$TNewIdAssetOF)
{
	if (empty($TAssetOFChildId)){
		return false;
	}

	foreach ($TAssetOFChildId as $idOF)
	{
		$TAssetOF = new TAssetOF;
		$TAssetOF->load($PDOdb, $idOF);
		
		foreach ($TAssetOF->TAssetOFLine as $line) 
		{
			//Si le produit TO_MAKE de cette OF correspond au notre, on maj sa qté ainsi que ces needed et on stop le traitement pcq pas besoin d'aller plus loin
			if ($line->type == 'TO_MAKE' && $line->fk_product == $fk_product)
			{
				$TIdLineModified[] = $TAssetOF->rowid;
				$line->qty = $qty;
				$line->save($PDOdb);
				
				_updateNeeded($TAssetOF, $PDOdb, $db, $conf, $line->fk_product, $line->qty, $TIdLineModified, $TNewIdAssetOF);
				
                return true; // on a trouvé la ligne consernée
			}
		}
		
	}
    
    return false;
}

function _measuringUnits($type, $name)
{
	global $db;
	
	require_once DOL_DOCUMENT_ROOT.'/product/class/html.formproduct.class.php';
	require_once DOL_DOCUMENT_ROOT.'/core/lib/product.lib.php';
	
	$html=new FormProduct($db);
	
	if($type == 'unit') return array(' unité(s)');
	else return array($html->load_measuring_units($name, $type, 0));
}

function _updateNeeded($TAssetOF, &$PDOdb, &$db, &$conf, $fk_product, $qty, &$TIdLineModified, &$TNewIdAssetOF)
{
	$prod = new Product($db);
	$prod->fetch($fk_product);
	$TComposition = $prod->getChildsArbo($prod->id);
	
	if (empty($TComposition)) return;
	
	$TAssetOFChildId = array();
	$TAssetOF->getListeOFEnfants($PDOdb, $TAssetOFChildId, $TAssetOF->rowid, false); //Récupération des OF enfants direct - les sous-enfants ne sont pas récupérés
	
	//Boucle sur les lignes de l'OF courant
	foreach ($TAssetOF->TAssetOFLine as $line) 
	{
		// On ne modifie les quantités que des produits NEEDED qui sont des sous produits du produit TO_MAKE
		if($line->type == 'NEEDED' && !empty($TComposition[$line->fk_product][1])) 
		{
			$line->qty = $line->qty_needed = $line->qty_used = $qty * $TComposition[$line->fk_product][1];
			$line->save($PDOdb);

			//_updateToMake : si un OF enfant existe pour ce produit NEEDED alors on met à jour les qté de celui-ci
	        if(!_updateToMake($TAssetOFChildId, $PDOdb, $db, $conf, $line->fk_product, $line->qty, $TIdLineModified, $TNewIdAssetOF)) {
				//Si on entre là, c'est que la création d'un OF doit être efféctué, uniquement si la conf nous le permet
				
				//TODO attention la création de l'OF ne prend pas en compte la quantité encore en stock
  				
  				if (!empty($conf->global->CREATE_CHILDREN_OF)) 
  				{
                	$TCompositionSubProd = $TAssetOF->getProductComposition($PDOdb,$line->fk_product, $line->qty);
					
					if ((!empty($conf->global->CREATE_CHILDREN_OF_COMPOSANT) && !empty($TCompositionSubProd)) || empty($conf->global->CREATE_CHILDREN_OF_COMPOSANT)) {
						$k = $TAssetOF->createOFifneeded($PDOdb,$line->fk_product, $line->qty);
						$TAssetOF->save($PDOdb);

						if ($k !== null) $TNewIdAssetOF[] = $TAssetOF->TAssetOF[$k]->rowid;
					}
				}
				
//				var_dump($line->fk_product, $line->qty);

	        }
			
		}
	}
}
