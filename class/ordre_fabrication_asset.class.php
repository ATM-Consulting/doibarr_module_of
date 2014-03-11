<?php

class TAssetOF extends TObjetStd{
/*
 * Ordre de fabrication d'équipement
 * */
 	static $TOrdre=array(
			'ASAP'=>'Au plut tôt'
			,'TODAY'=>'Dans la journée'
			,'TOMORROW'=> 'Demain'
			,'WEEK'=>'Dans la semaine'
			,'MONTH'=>'Dans le mois'
			
		);
 
	static $TStatus=array(
			'DRAFT'=>'Brouillon'
			,'VALID'=>'Valide pour production'
			,'OPEN'=>'Lancé la production'
			,'CLOSE'=>'Terminé'
		);
		
	
	
	function __construct() {
		$this->set_table(MAIN_DB_PREFIX.'assetOf');
    	$this->TChamps = array(); 	  
		$this->add_champs('entity,fk_user,fk_assetOf_parent','type=entier;index;');
		$this->add_champs('entity,temps_estime_fabrication,temps_reel_fabrication','type=float;');
		$this->add_champs('ordre,numero,status','type=chaine;');
		$this->add_champs('date_besoin,date_lancement','type=date;');
		$this->add_champs('note','type=text;');
		
		$this->start();
		
		$this->workstation=null;
		$this->status='DRAFT';
		
		$this->setChild('TAssetOFLine','fk_assetOf');
		$this->setChild('TAssetWorkstationOF','fk_assetOf');
		$this->setChild('TAssetOF','fk_assetOf_parent');
		
	}
	
	function load(&$db, $id) {
		global $conf;
		
		$res = parent::load($db,$id);
		
		
		return $res;
	}
	
	function set_temps_fabrication() {
		$this->temps_estime_fabrication=0;
		$this->temps_reel_fabrication=0;	
			
		foreach($this->TAssetWorkstationOF as $row) {
			
			$this->temps_estime_fabrication+=$row->nb_hour;
			$this->temps_reel_fabrication+=$row->nb_hour_real;
			
			
		}
		
	}
	
	function save(&$db) {
		
		$this->set_temps_fabrication();
		
		
		parent::save($db);
		
		if($this->numero=='') {
			$this->numero='OF'.str_pad( $this->getId() , 5, '0', STR_PAD_LEFT);
			$wc = $this->withChild;
			$this->withChild=false;
			parent::save($db);
			$this->withChild=$wc;
		}
	}
	
	//Associe les équipements à l'OF
	function setEquipement(&$ATMdb){
		
		foreach($this->TAssetOFLine as $TAssetOFLine){
			
			$TAssetOFLine->setAsset($ATMdb);	
		}
		
		return true;
	}
	
	function delLine(&$ATMdb,$iline){
		
		$this->TAssetOFLine[$iline]->to_delete=true;
		
	}
	
	//Ajout d'un produit TO_MAKE à l'OF
	function addProductComposition(&$ATMdb, $fk_product, $quantite_to_make=1, $fk_assetOf_line_parent=0){
		
		$Tab = $this->getProductComposition($ATMdb,$fk_product, $quantite_to_make);
		/*echo "<pre>";
		print_r($Tab);
		echo "</pre>";*/
		
		foreach($Tab as $prod) {
			
			$this->addLine($ATMdb, $prod->fk_product, 'NEEDED', $prod->qty * $quantite_to_make,$fk_assetOf_line_parent);
			
		}
		
		return true;
	}
	
	//Retourne les produits NEEDED de l'OF concernant le produit $id_produit
	function getProductComposition(&$ATMdb,$id_product, $quantite_to_make){
		include_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
		global $db;	
		
		$Tab=array();
		$product = new Product($db);
		$product->fetch($id_product);
		$TRes = $product->getChildsArbo($product->id);
		
		$this->getProductComposition_arrayMerge($ATMdb,$Tab, $TRes, $quantite_to_make);
		
		return $Tab;
	}
	
	private function getProductComposition_arrayMerge(&$ATMdb,&$Tab, $TRes, $qty_parent=1, $createOF=true) {
		
		foreach($TRes as $row) {
			
			$prod = new stdClass;
			$prod->fk_product = $row[0];
			$prod->qty = $row[1];
			
			if(isset($Tab[$prod->fk_product])) {
				$Tab[$prod->fk_product]->qty += $prod->qty * $qty_parent;
			}
			else {
				$Tab[$prod->fk_product]=$prod;	
			}
			
			if(!empty($row['childs'])) {
				
				if($createOF) {
					$this->createOFifneeded($ATMdb, $prod->fk_product, $prod->qty * $qty_parent);
				}
				else {
					$this->getProductComposition_arrayMerge($Tab, $row['childs'], $prod->qty * $qty_parent);	
				}
			}
		}
		
	} 
	
	/*
	 * Crée une OF si produit composé pas en stock
	 */
	function createOFifneeded(&$ATMdb,$fk_product, $qty_needed) {
		
		$reste = $this->getProductStock($fk_product)-$qty_needed;
		
		if($reste>0) {
			null;
		}
		else {
			
			$k=$this->addChild($ATMdb,'TAssetOF');
			$this->TAssetOF[$k]->status = "DRAFT";
			$this->TAssetOF[$k]->date_besoin = dol_now();
			$this->TAssetOF[$k]->addLine($ATMdb, $fk_product, 'TO_MAKE', abs($qty_needed));
			
		}
		
	}
	/*
	 * retourne le stock restant du produit
	 */
	function getProductStock($fk_product) {
		global $db;
		include_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
		
		$product = new Product($db);
		$product->fetch($fk_product);
		$product->load_stock();
		
		return $product->stock_reel;
		
	}
	
	/*function createCommandeFournisseur($type='externe'){
		global $db,$conf,$user;
		include_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.class.php';
		
		$id_fourn = $this->getFournisseur();
		
		$cmdFour = new CommandeFournisseur($db);
		$cmdFour->ref_supplier = "";
       	$cmdFour->note_private = "";
        $cmdFour->note_public = "";
        $cmdFour->socid;
		
		return $id_cmd_four;
	}
	
	function getFournisseur(){
		global $db;
		
		return 1;
	}*/
	
	
	
	//Ajoute une ligne de produit à l'OF
	function addLine(&$ATMdb, $fk_product, $type, $quantite=1,$fk_assetOf_line_parent=0){
		global $user;
		
		$k = $this->addChild($ATMdb, 'TAssetOFLine');
		
		$TAssetOFLine = &$this->TAssetOFLine[$k];
		$TAssetOFLine->fk_assetOf_line_parent = $fk_assetOf_line_parent;
		$TAssetOFLine->entity = $user->entity;
		$TAssetOFLine->fk_product = $fk_product;
		$TAssetOFLine->fk_asset = 0;
		$TAssetOFLine->type = $type;
		$TAssetOFLine->qty_needed = $quantite;
		$TAssetOFLine->qty = $quantite;
		$TAssetOFLine->qty_used = $quantite;
		
		$idAssetOFLine = $TAssetOFLine->save($ATMdb);
		
		if($type=='TO_MAKE') {
			$this->addProductComposition($ATMdb,$fk_product, $quantite,$idAssetOFLine);
		}
	}
	
	function updateLines(&$ATMdb,$TQty){
		
		foreach($this->TAssetOFLine as $TAssetOFLine){
			$TAssetOFLine->qty_used = $TQty[$TAssetOFLine->getId()];
			$TAssetOFLine->save($ATMdb);
		}
	}
	
	//Finalise un OF => incrémention/décrémentation du stock
	function closeOF(&$ATMdb){
		include_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
		
		foreach($this->TAssetOFLine as $AssetOFLine){
			$asset = new TAsset;
			
			if($AssetOFLine->type == "TO_MAKE"){
				$AssetOFLine->makeAsset($ATMdb,$AssetOFLine->fk_product,$AssetOFLine->qty_used);
			}
		}
	}
	
	function openOF(&$ATMdb){
		global $db, $user;
		include_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
		dol_include_once("fourn/class/fournisseur.product.class.php");
		dol_include_once("fourn/class/fournisseur.commande.class.php");
		
		foreach($this->TAssetOFLine as $AssetOFLine){
			$asset = new TAsset;

			if($AssetOFLine->type == "NEEDED"){
				//TODO v2 : sélection d'un équipement à associé et décrémenter son stock
				$asset->addStockMouvementDolibarr($AssetOFLine->fk_product,-$AssetOFLine->qty_used,'Utilisation via Ordre de Fabrication');
			}

		}
	}
	
	private function getEnfantsDirects() {
		
		global $db;
		
		$TabIdEnfants = array();
		
		$sql = "SELECT rowid";
		$sql.= " FROM ".MAIN_DB_PREFIX."assetOf";
		$sql.= " WHERE fk_assetOf_parent = ".$this->rowid;
		
		$resql = $db->query($sql);
		
		while($res = $db->fetch_object($resql)) {
			$TabIdEnfants[] = $res->rowid;
		}
		
		return $TabIdEnfants;
		
	}
	
	private function addCommandeFourn($ofLigne, $resultatSQL) {

		global $db, $user;
		dol_include_once("fourn/class/fournisseur.commande.class.php");		
		
		// On cherche s'il existe une commande pour ce fournisseur
		$sql = "SELECT rowid";
		$sql.= " FROM ".MAIN_DB_PREFIX."commande_fournisseur";
		$sql.= " WHERE fk_soc = ".$resultatSQL->fk_soc;
		$sql.= " ORDER BY rowid DESC";
		$sql.= " LIMIT 1";
		$resql = $db->query($sql);
		
		$res = $db->fetch_object($resql);

		if($res) { // Il existe une commande, on la charge
			$com = new CommandeFournisseur($db);
			$com->fetch($res->rowid);
		} else { // Il n'existe aucune commande pour ce fournisseur donc on en crée une nouvelle
			$com = new CommandeFournisseur($db);
			$com->socid = $resultatSQL->fk_soc;
			$com->create($user);
		}
		
		// On cherche si ce produit existe déjà dans la commande, si oui, : "updateline"
		foreach($com->lines as $line) {
			if($line->fk_product == $resultatSQL->fk_product) {
				$com->updateline($line->id, $line->desc, $line->subprice, $line->qty+$ofLigne->qty, $line->remise_percent, $line->tva_tx);
				$done = true;
				break;
			}
		}
		
		if(!$done) {
			
			// Si le produit n'existe pas déjà dans la commande, on l'ajoute à cette commande
			$com->addline($desc, $resultatSQL->price/$resultatSQL->quantity, $ofLigne->qty, $txtva, 0, 0, $resultatSQL->fk_product, $resultatSQL->rowid);
			
		}		
	}
	
	function createOfAndCommandesFourn(&$ATMdb) {
		global $db, $user;
		
		dol_include_once("fourn/class/fournisseur.commande.class.php");
		
		$TabOF = array();
		$TabOF[] = $this->rowid;
		$this->getListeOFEnfants($ATMdb, $TabOF);
		
		// Boucle pour chaque OF de l'arbre
		foreach($TabOF as $idOf){
			
			// On charge l'OF
			$assetOF = new TAssetOF;
			$assetOF->load($ATMdb, $idOf);
			
			// Boucle pour chaque produit de l'OF
			foreach($assetOF->TAssetOFLine as $ofLigne) {
				
				// On cherche le produit "TO_MAKE"
				if($ofLigne->type == "TO_MAKE") {
					
					if($ofLigne->fk_product_fournisseur_price > 0) { // Fournisseur externe
					
						// On récupère la ligne prix fournisseur correspondante
						$sql = "SELECT rowid, fk_soc, fk_product, price, compose_fourni, quantity, ref_fourn";
						$sql.= " FROM ".MAIN_DB_PREFIX."product_fournisseur_price";
						$sql.= " WHERE rowid = ".$ofLigne->fk_product_fournisseur_price;
						$resql = $db->query($sql);

						$res = $db->fetch_object($resql);
						
						// Si composé fourni
						if($res->compose_fourni) {
						
							// On charge le produit "TO_MAKE"
							$prod = new Product($db);
							$prod->fetch($ofLigne->fk_product);
							$prod->load_stock();
							
							$stockProd = 0;
							
							// On récupère son stock
							foreach($prod->stock_warehouse as $stock) {
								$stockProd += $stock->real;
							}
							
							// S'il y a suffisemment de stock, on destocke
							// Sinon, commande fournisseur :
							if($stockProd < $ofLigne->qty_needed) {
								
								$this->addCommandeFourn($ofLigne, $res);

							} else { // Suffisemment de stock, donc destockage :
								$assetOF->openOF($ATMdb);
							}
						} else { //Composé non fourni
						
							$this->addCommandeFourn($ofLigne, $res);

							// On récupère les OF enfants pour les supprimer
							$TabIdEnfantsDirects = $assetOF->getEnfantsDirects();
							
							foreach($TabIdEnfantsDirects as $idOF) {
							
								$assetOF->removeChild("TAssetOF", $idOF);
							}

							$assetOF->save($ATMdb);
							
							// On casse la boucle
							break;
							
						}

					} else { // Fournisseur interne (Bourguignon)
					
						if($ofLigne->fk_product_fournisseur_price == -1) { // Composé non fourni, kill OF enfants
							
							$TabIdEnfantsDirects = $assetOF->getEnfantsDirects();
							
							foreach($TabIdEnfantsDirects as $idOF) {
							
								$assetOF->removeChild("TAssetOF", $idOF);
							}

							$assetOF->save($ATMdb);
							
							// On casse la boucle
							break;
							
						} elseif($ofLigne->fk_product_fournisseur_price == -2){ // Composé fourni
							$prod = new Product($db);
							$prod->fetch($ofLigne->fk_product);
							$prod->load_stock();
							
							$stockProd = 0;
							
							// On récupère son stock
							foreach($prod->stock_warehouse as $stock) {
								$stockProd += $stock->real;
							}	
							
							// S'il y a sufisemment de stock, on destocke
							if($stockProd >= $ofLigne->qty_needed) {
								$assetOF->openOF($ATMdb);
							}
													
						}
						
					}
					
				}

			}
			
		}

	}
	
	static function ordre($ordre='ASAP'){
		
		
		return TAssetOF::$TOrdre[$ordre];
	}
	
	
	function getListeOFEnfants(&$ATMdb, &$Tid, $id_parent=null) {
			
		if(is_null($id_parent))$id_parent = $this->getId();
		
		$sql = "SELECT rowid";
		$sql.= " FROM ".MAIN_DB_PREFIX."assetOf";
		$sql.= " WHERE fk_assetOf_parent = ".$id_parent;
		
		$Tab = $ATMdb->ExecuteAsArray($sql);
		foreach($Tab as $row) {
			$Tid[] = $row->rowid;
			$this->getListeOFEnfants($ATMdb, $Tid, $row->rowid);
		}
				
	}

	static function status($status='DRAFT'){
		
			
		return  TAssetOF::$TStatus[$status];
	}
	
	function getCanBeParent(&$PDOdb) {
		
		$sql="SELECT rowid, numero FROM ".MAIN_DB_PREFIX."assetOf 
		WHERE rowid NOT IN (".$this->getId().") AND status='DRAFT'";
		$Tab = $PDOdb->ExecuteAsArray($sql);
		$TCombo=array();
		foreach($Tab as $row) {
			$TCombo[$row->rowid] = $row->numero;
		}
		
		return $TCombo;
		
	}

}

class TAssetOFLine extends TObjetStd{
/*
 * Ligne d'Ordre de fabrication d'équipement 
 * */
	
	function __construct() {
		$this->set_table(MAIN_DB_PREFIX.'assetOf_line');
    	$this->TChamps = array(); 	  
		$this->add_champs('entity,fk_assetOf,fk_product,fk_asset,fk_product_fournisseur_price','type=entier;index;');
		$this->add_champs('qty_needed,qty,qty_used','type=float;');
		$this->add_champs('type','type=chaine;');
		
		//clé étrangère
		parent::add_champs('fk_assetOf_line_parent','type=entier;index;');
		
		$this->TType=array('NEEDED','TO_MAKE');
		
		$this->TFournisseurPrice=array();
		
	    $this->start();
		
		$this->setChild('TAssetOFLine','fk_assetOf_line_parent');
	}
	
	//Affecte l'équipement à la ligne de l'OF
	function setAsset(&$ATMdb){
		global $db, $user;	
		include_once 'asset.class.php';
		
		$asset = new TAsset;
		
		$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."asset WHERE contenance_reel >= ".$this->qty." ORDER BY contenance_reel ASC LIMIT 1";
		$ATMdb->Execute($sql);
		if($ATMdb->Get_line()){
			$idAsset = $ATMdb->Get_field('rowid');
			$asset->load($ATMdb, $idAsset);
			$asset->status = 'indisponible';
		}
		else{
			$asset = $this->makeAsset($ATMdb, $this->fk_product, $this->qty);
		}
				
		$asset->save($ATMdb);
		
		$this->fk_asset = $idAsset;
		$this->save($ATMdb);
		
		return true;
	}
	
	//Utilise l'équipement affecté à la ligne de l'OF
	function makeAsset(&$ATMdb,$fk_product,$qty){
		global $user,$conf;
		include_once 'asset.class.php';
		
		$TAsset = new TAsset;
		$TAsset->fk_soc = '';
		$TAsset->fk_product = $fk_product;
		$TAsset->entity = $user->entity;
		
		/*echo '<pre>';
		print_r($TAsset);
		echo '</pre>';*/
		
		/*
		 * Empêche l'ajout en stock des sous-produit d'un produit composé
		 */
		$varconf = $conf->global->PRODUIT_SOUSPRODUITS;
		$conf->global->PRODUIT_SOUSPRODUITS = NULL;
		$TAsset->save($ATMdb,$user,'Création via Ordre de Fabrication',$qty);
		$conf->global->PRODUIT_SOUSPRODUITS = $varconf;
	}
	
	function load(&$ATMdb, $id) {
		
		parent::load($ATMdb, $id);
		
		$this->loadFournisseurPrice($ATMdb);
		
	}
	
	function loadFournisseurPrice(&$ATMdb) {
		$sql = "SELECT  pfp.rowid,  pfp.fk_soc,  pfp.price,  pfp.quantity, pfp.compose_fourni,s.nom as 'name'
		FROM ".MAIN_DB_PREFIX."product_fournisseur_price pfp LEFT JOIN ".MAIN_DB_PREFIX."societe s ON (pfp.fk_soc=s.rowid)
		WHERE fk_product = ".(int)$this->fk_product;
		
		$ATMdb->Execute($sql);
		
		$interne=new stdClass;
		$interne->rowid=-1;
		$interne->fk_soc=-1;
		$interne->price=0;
		$interne->compose_fourni=0;
		$interne->name='Interne';
		
		$interne2=new stdClass;
		$interne2->rowid=-2;
		$interne2->fk_soc=-1;
		$interne2->price=0;
		$interne2->compose_fourni=1;
		$interne2->name='Interne';
		
		$this->TFournisseurPrice = array_merge(
			array($interne, $interne2)
			,$ATMdb->Get_All()
		);
		
		
	}
	
}
/*
 * Link to product
 */
class TAssetWorkstationProduct extends TObjetStd{
	
	function __construct() {
		$this->set_table(MAIN_DB_PREFIX.'asset_workstation_product');
    	$this->TChamps = array(); 	  
		$this->add_champs('fk_product, fk_asset_workstation','type=entier;index;');
		$this->add_champs('nb_hour','type=float;'); // nombre d'heure associé au poste de charge et au produit
		
	    $this->start();
	}
	
	
	static function getWorstations(&$ATMdb) {
		$TWorkstation=array();
		$sql = "SELECT rowid, libelle FROM ".MAIN_DB_PREFIX."asset_workstation WHERE entity=".$conf->entity;
		$ATMdb->Execute($sql);
		while($ATMdb->Get_line()){
			$TWorkstation[$ATMdb->Get_field('rowid')]=$ATMdb->Get_field('libelle');
		}
		return $TWorkstation;
	}
	
}

/*
 * Link to OF
 */
class TAssetWorkstationOF extends TObjetStd{
	
	function __construct() {
		$this->set_table(MAIN_DB_PREFIX.'asset_workstation_of');
    	$this->TChamps = array(); 	  
		$this->add_champs('fk_assetOf, fk_asset_workstation','type=entier;index;');
		$this->add_champs('nb_hour,nb_hour_real','type=float;'); // nombre d'heure associé au poste de charge sur un OF
		
	    $this->start();
		
		$this->ws = new TAssetWorkstation;
		
	}
	
	function load(&$ATMdb, $id) {
		
		parent::load($ATMdb,$id);
		
		if($this->fk_asset_workstation >0){
			$this->ws->load($ATMdb, $this->fk_asset_workstation);			
			
		}
		
	}
	
}



class TAssetWorkstation extends TObjetStd{
/*
 * Atelier de fabrication d'équipement
 * */
	
	function __construct() {
		$this->set_table(MAIN_DB_PREFIX.'asset_workstation');
    	$this->TChamps = array(); 	  
		$this->add_champs('entity,fk_usergroup','type=entier;index;');
		$this->add_champs('libelle','type=chaine;');
		$this->add_champs('nb_hour_max','type=float;'); // charge maximale du poste de travail
		
	    $this->start();
	}
	
	function save(&$ATMdb) {
		global $conf;
		
		$this->entity = $conf->entity;
		
		parent::save($ATMdb);
		
		
	}
	
	static function getWorstations(&$ATMdb) {
		global $conf;
		
		$TWorkstation=array();
		$sql = "SELECT rowid, libelle FROM ".MAIN_DB_PREFIX."asset_workstation WHERE entity=".$conf->entity;
		
		$ATMdb->Execute($sql);
		while($ATMdb->Get_line()){
			$TWorkstation[$ATMdb->Get_field('rowid')]=$ATMdb->Get_field('libelle');
		}
		
		
		return $TWorkstation;
	}
	
}
