<?php

if (!class_exists('TObjetStd'))
{
	define('INC_FROM_DOLIBARR', true);
	require_once __DIR__ . '/../config.php';
}

class TAssetOF extends TObjetStd{
/*
 * Ordre de fabrication d'équipement
 * */
	var $element = 'of';

    /** @var TAssetOFLine[] */
    public $TAssetOFLine = array();
    /** @var TAssetWorkstationOF[] */
    public $TAssetWorkstationOF = array();
    /** @var TAssetOF[] */
    public $TAssetOF = array();

 	static $TOrdre=array(
			'ASAP'=>'ASAP'
			,'TODAY'=>'ForToday'
			,'TOMORROW'=> 'ForTomorrow'
			,'WEEK'=>'ForWeek'
			,'MONTH'=>'ForMonth'

		);

 	static $TStatus=array(
			'DRAFT'=>'Draft'
            ,'NEEDOFFER'=>'WaitingSupplierPrice'
            ,'ONORDER'=>'WaitingProductsOrdered'
            ,'VALID'=>'ValidForProduction'
            ,'OPEN'=>'InProduction'
			,'CLOSE'=>'Done'
		);

 	public $entity;
 	public $fk_user;
 	public $fk_assetOf_parent;
 	public $fk_soc;
 	public $fk_commande;
 	public $fk_project;
 	public $rank;
 	public $temps_estime_fabrication;
 	public $temps_reel_fabrication;
 	public $mo_cost;
 	public $mo_estimated_cost;
 	public $compo_cost;
 	public $compo_estimated_cost;
 	public $total_cost;
 	public $total_estimated_cost;
 	public $ordre;
 	public $numero;
 	public $status;
 	public $date_besoin;
 	public $date_lancement;
 	public $date_start;
 	public $date_end;
 	public $note;

 	public $PDOdb;

	function fetchObjectLinked($sourceid = null, $sourcetype = '', $targetid = null, $targettype = '', $clause = 'OR', $alsosametype = 1, $orderby = 'sourcetype', $loadalsoobjects = 1)
    {
        $this->linkedObjectsIds=array();
        $this->linkedObjects=array();

        // TODO usurper via un objet Generic l'appel standard
        // ...

        return 1;
    }

	function __construct() {
	    global $conf;

	    $this->PDOdb = new TPDOdb;

		$this->set_table(MAIN_DB_PREFIX.'assetOf');

		$this->add_champs('entity,fk_user,fk_assetOf_parent,fk_soc,fk_commande,fk_project,rank',array('type'=>'integer','index'=>true));
		$this->add_champs('entity,temps_estime_fabrication,temps_reel_fabrication,mo_cost,mo_estimated_cost,compo_cost,compo_estimated_cost,total_cost,total_estimated_cost','type=float;');
		$this->add_champs('ordre,numero,status','type=chaine;');
		$this->add_champs('date_besoin,date_lancement,date_start,date_end',array('type'=>'date'));
		$this->add_champs('note','type=text;');
		$this->add_champs('modelpdf',array('type' => 'string'));
		$this->_init_vars();
		$this->start();

		$this->TWorkstation=array();
		$this->status='DRAFT';

		$this->setChild('TAssetOFLine','fk_assetOf');
		$this->setChild('TAssetWorkstationOF','fk_assetOf');
		$this->setChild('TAssetOF','fk_assetOf_parent');

		$this->date_besoin = time();
		$this->date_lancement = $this->date_start = $this->date_end = 0;

		//Tableau d'erreurs
		$this->errors = array();

		$this->current_cost_for_to_make = 0; // montant utilisé pour les entrées de stock

		$this->entity = $conf->entity;
	}

    /**
     * @param DoliDB $db        Object db connector
     * @param int $fk_product   id product to test
     * @return array
     */
	public static function productInOf($db, $fk_product)
    {
        $TOfId = array();
        $sql = 'SELECT DISTINCT o.rowid FROM '.MAIN_DB_PREFIX.'assetOf o';
        $sql.= ' INNER JOIN '.MAIN_DB_PREFIX.'assetOf_line ol ON (o.rowid = ol.fk_assetOf)';
        $sql.= ' WHERE ol.fk_product = '.intval($fk_product);

        $resql = $db->query($sql);
        if ($resql)
        {
            while ($obj = $db->fetch_object($resql))
            {
                $TOfId[$obj->rowid] = $obj->rowid;
            }
        }
        else
        {
            dol_print_error($db);
        }

        return $TOfId;
    }


	function set_current_cost_for_to_make($compo_planned_cost= false) {

		$this->set_temps_fabrication(true);
		$this->set_fourniture_cost();

		$this->total_cost = (empty($this->compo_cost) && $compo_planned_cost? $this->compo_planned_cost : $this->compo_cost) + ( empty($this->mo_cost) && $compo_planned_cost ? $this->mo_estimated_cost : $this->mo_cost);

		$qty = 0;

		foreach($this->TAssetOFLine as &$line) {
            if($line->type=='TO_MAKE') {
            	$qty+=empty( $line->qty_used ) ? $line->qty : $line->qty_used;
            }
        }

        if($qty>0) $this->current_cost_for_to_make = $this->total_cost / $qty;
    //    var_dump($qty, $this->current_cost_for_to_make , $this->total_cost);
        foreach($this->TAssetOFLine as &$line) {
        	$line->current_cost_for_to_make = $this->current_cost_for_to_make;
        }

	}

	/**
	 * Function Load. Load an object with id
	 *
	 * @param TPDOdb	$PDOdb			Object PDO database
	 * @param int		$id			Contain rowid of object
	 * @param bool	$loadChild	true = load childs; false = Only load object
	 *
	 * @return bool	            true = OK; false = KO
	 */
	function load(&$PDOdb, $id, $loadChild = true) {
		global $conf, $langs, $db;

		$res = parent::load($PDOdb,$id,true);

		$this->ref = $this->numero; //for dolibarr compatibility

		$this->set_current_cost_for_to_make();

	        foreach($this->TAssetOFLine as &$line) {
        		 $line->of_numero = $this->numero;
        	}

	        foreach($this->TAssetWorkstationOF as &$ws) {
        	    $ws->of_status = $this->status;
	            $ws->of_fk_project = $this->fk_project;
        	}

        if(getDolGlobalInt('OF_DISPLAY_PRODUCT_CATEGORIES')) {
            include_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';
            $langs->load('categories');
            foreach($this->TAssetOFLine as $k => &$TAssetOFLine) {
                $cat = new Categorie($db);
                $TCateg = $cat->containing($TAssetOFLine->fk_product, 'product');
                if(! empty($TCateg)) {
                    usort($TCateg, function($a, $b) { return strcmp($a->label, $b->label); });
                    $TAssetOFLine->categLabel = '';
                    foreach($TCateg as $categ) {
                        $color = $categ->color ? ' style="background: #'.$categ->color.';"' : ' style="background: #aaa"';
                        $TAssetOFLine->categLabel .= '<span class="noborderoncategories" '.$color.'>'.$categ->getNomUrl(1).'</span><br>';
                    }
                    $TAssetOFLine->categ = $TCateg;
                }
            }

			if (isset($this->TAssetOFLine)){

				usort($this->TAssetOFLine, function($a, $b) {
						if(property_exists($a,'categ') && is_array($a->categ) && property_exists($b,'categ') && is_array($b->categ) ){
							if ( $a->categ[0]->label == $b->categ[0]->label ){
								return strcmp($a->product->ref, $b->product->ref);
							}

						}else{
							return
								property_exists($a,'categ') && is_array($a->categ) && property_exists($b,'categ') && is_array($b->categ)
									?  strcmp($a->categ[0]->label, $b->categ[0]->label)
									: "";
						}


				});
			}

        }

		usort($this->TAssetWorkstationOF, array($this,'sortWorkStationByRank'));


		return $res;
	}

    /**
     * Function to get virtual stock for product
     * @param int    $fk_product
     * @param string $type
     * @return int
     */
    public static function getQtyForProduct($fk_product, $type='NEEDED') {
        global $db, $conf;
        $TStatut = '"CLOSE"';
        if(getDolGlobalInt('OF_USE_DESTOCKAGE_PARTIEL')) $TStatut .= ',"OPEN"';
        if(!getDolGlobalInt('OF_DRAFT_IN_VIRTUAL_STOCK')) $TStatut .= ',"DRAFT"';
        $sql = 'SELECT SUM(aol.qty';
        if(getDolGlobalInt('OF_MANAGE_NON_COMPLIANT')) $sql .= '+aol.qty_non_compliant';
        $sql .= '  ) as qty
                FROM '.MAIN_DB_PREFIX.'assetOf_line aol
                INNER JOIN '.MAIN_DB_PREFIX.'assetOf ao ON (aol.fk_assetOf = ao.rowid)
                WHERE aol.fk_product='.$fk_product.' AND ao.status NOT IN ('.$TStatut.') AND aol.type="'.$type.'"';
        $resql = $db->query($sql);
        if(!empty($resql)) {
            $obj = $db->fetch_object($resql);
            return $obj->qty;
        }
        return 0;
    }

	function loadByProductCategory(&$db, $categ, $fk_soc, $status) {

		//On récupère l'of ayant des produits ayant pour catégorie la même catégorie et étant brouillon
        $sql = "SELECT of.rowid FROM ".MAIN_DB_PREFIX."assetOf of
                LEFT JOIN ".MAIN_DB_PREFIX."assetOf_line as ofline ON (of.rowid = ofline.fk_assetOf AND ofline.type='TO_MAKE')
                LEFT JOIN ".MAIN_DB_PREFIX."categorie_product cat ON (ofline.fk_product = cat.fk_product)
                WHERE cat.fk_categorie = $categ->id
                AND of.fk_soc = $fk_soc
                AND of.status = '$status'";

        $db->Execute($sql);
        $TOfIds = $db->Get_All();
        $TOfs = array();
        foreach($TOfIds as $fk_of){
            $TOfs[$fk_of->rowid]++;
        }

        foreach($TOfs as $fk_of => $nb_line){
            $this->load($db, $fk_of);
            $countLineToMake = 0;
            foreach($this->TAssetOFLine as $line){
                if($line->type == 'TO_MAKE')$countLineToMake++;
            }

            if($nb_line == $countLineToMake)return $this->id;//Si tous les tomake sont dans la même catégorie, on a trouvé un of compatible
        }

        return -1;
	}

	function sortWorkStationByRank(&$a,&$b) {

		if($a->rang < $b->rang) {
			return -1;
		}
		else if($a->rang > $b->rang) {
                        return 1;
                }
		else return 0;

	}

	function checkWharehouseOnLines()
	{
		foreach ($this->TAssetOFLine as &$ofLine)
		{
			if (empty($ofLine->fk_entrepot) || $ofLine->fk_entrepot < 0)
			{
				return false;
			}
		}

		return true;
	}

	public function getListChildrenOf(&$TOf)
    {

	if (!empty($this->TAssetOF)) {

		$TOf = array_merge($TOf, $this->TAssetOF);

		foreach ($this->TAssetOF as $childOf) $childOf->getListChildrenOf($TOf);

	}

    }

    /**
     * @param TPDOdb $PDOdb
     * @param float  $qty
     * @param float  $qty_used
     * @param float  $qty_non_compliant
     * @param int    $idLine
     * @param bool   $addToMake
     * @param bool   $force_empty
     * @return bool
     */
    public function updateNomenclatureToMakeQty(&$PDOdb, $qty, $qty_used, $qty_non_compliant, $idLine, $addToMake = false, $force_empty = false) {
        if(!empty($this->TAssetOFLine)) {
            /**
             * @var TAssetOFLine $assetOFLine
             */
            //On vide tous les needed et les workstations
            foreach($this->TAssetOFLine as $assetOFLine) {
                if($assetOFLine->type !== 'TO_MAKE') {
                    $assetOFLine->qty = 0;
                    $assetOFLine->qty_needed = 0;
                    $assetOFLine->qty_used = 0;
                    $assetOFLine->saveQty($PDOdb);
                    $TChildParam = $assetOFLine->getToMakeChildrenOFIdAndLineID($assetOFLine);
                    if(! empty($TChildParam)) {
                        $childOF = new TAssetOF;
                        $childOF->load($PDOdb, $TChildParam['fk_OF']);
                        $childOF->updateNomenclatureToMakeQty($PDOdb, 0, 0, 0, $TChildParam['idLine'], false, true);
                    }
                }
            }
            if(! empty($this->TAssetWorkstationOF)) {
                foreach($this->TAssetWorkstationOF as $assetOFWorkstation) {
                    $assetOFWorkstation->nb_hour = 0;
                    $assetOFWorkstation->nb_hour_real = 0;
                    $assetOFWorkstation->nb_hour_prepare = 0;
                    $assetOFWorkstation->save($PDOdb);
                }
            }


            //Calcul
            foreach($this->TAssetOFLine as $assetOFLine) {
                if($assetOFLine->type === 'TO_MAKE') { // On recalcul pour tous les "à fabriquer"
                    if($idLine === $assetOFLine->getId()) {

                        if($addToMake) {
                            $qty += $assetOFLine->qty;
                            $qty_used += $assetOFLine->qty_used;
                            $qty_non_compliant += $assetOFLine->qty_non_compliant;
                        }
                        if(! empty($qty) || ($force_empty && $this->status == 'DRAFT')) $assetOFLine->qty = $qty;
                        if(! empty($qty_used) || $force_empty) $assetOFLine->qty_used = $qty_used;
                        if(! empty($qty_non_compliant) || $force_empty) $assetOFLine->qty_non_compliant = $qty_non_compliant;
                        $assetOFLine->saveQty($PDOdb);
                    }
                    //On récupère les nomenclatures associés
                    $TNomen = new TNomenclature;
                    $TNomen->load($PDOdb, $assetOFLine->fk_nomenclature);
                    //Needed
                    if(! empty($TNomen->TNomenclatureDet)) {
                        foreach($TNomen->TNomenclatureDet as $nomenDet) { // Pour chaque composant on récupère la ligne d'of associée
                            $lineId = $assetOFLine->getNeededIDByProduct($nomenDet->fk_product);
                            if(! empty($lineId)) {
                                $neededLine = new TAssetOFLine;
                                $neededLine->load($PDOdb, $lineId);

                                $neededLine->qty += $assetOFLine->qty * $nomenDet->qty;
                                $neededLine->qty_needed += $assetOFLine->qty * $nomenDet->qty;
                               	if ($this->status == 'DRAFT') $neededLine->qty_used += $assetOFLine->qty * $nomenDet->qty;
								else $neededLine->qty_used += ($assetOFLine->qty_used + $assetOFLine->qty_non_compliant) * $nomenDet->qty;

                                $neededLine->saveQty($PDOdb);

                                // OF Enfant
                                $TChildParam = $assetOFLine->getToMakeChildrenOFIdAndLineID($neededLine);
                                if(! empty($TChildParam)) {
                                    $childOF = new TAssetOF;
                                    $childOF->load($PDOdb, $TChildParam['fk_OF']);
                                    $qtyCompliant = $assetOFLine->qty_used * $nomenDet->qty;
                                    $qtyNonCompliant = $assetOFLine->qty_non_compliant * $nomenDet->qty;
                                    $childOF->updateNomenclatureToMakeQty($PDOdb, $assetOFLine->qty * $nomenDet->qty, $qtyCompliant, $qtyNonCompliant, $TChildParam['idLine'], true);
                                }
                            }
                        }
                    }
                    //Workstation
                    if(! empty($TNomen->TNomenclatureWorkstation)) {
                        foreach($TNomen->TNomenclatureWorkstation as $nomenWorkstation) {
                            $lineId = $assetOFLine->getWorkstationOfIDByWorkstation($nomenWorkstation->fk_workstation);
                            if(! empty($lineId)) {
                                $workstationLine = new TAssetWorkstationOF;
                                $workstationLine->load($PDOdb, $lineId);

                                $workstationLine->nb_hour += $assetOFLine->qty * $nomenWorkstation->nb_hour;
                                $workstationLine->nb_hour_prepare += $assetOFLine->qty * $nomenWorkstation->nb_hour;
                                $workstationLine->nb_hour_real += ($assetOFLine->qty_used + $assetOFLine->qty_non_compliant) * $nomenWorkstation->nb_hour;

                                $workstationLine->save($PDOdb);
                            }
                        }
                    }
                }
            }
        }
        return true;
    }


	function validate(&$PDOdb) {

		global $conf,$langs;

		$error = 0;
		/** @var TAssetOF[] $TOf */
		$TOf = array();

//		$TIdOfEnfant = array();
//		if($conf->global->ASSET_CHILD_OF_STATUS_FOLLOW_PARENT_STATUS) $this->getListeOFEnfants($PDOdb, $TIdOfEnfant, $this->getId()); // TODO virer cet appel pour utiliser l'attribut ->TAssetOF en récursion puis retirer un peu plus bas le "->withChild" à false
//		krsort($TIdOfEnfant);
//
//		foreach ($TIdOfEnfant as $i => $id_of)
//		{
//			$TOf[$i] = new TAssetOF;
//			$TOf[$i]->load($PDOdb, $id_of);
//		}

//		if($conf->global->ASSET_CHILD_OF_STATUS_FOLLOW_PARENT_STATUS) $TOf = $this->TAssetOF;
		if(getDolGlobalInt('ASSET_CHILD_OF_STATUS_FOLLOW_PARENT_STATUS')) $this->getListChildrenOf($TOf);

		$TOf[] = &$this;
		if (getDolGlobalInt('OF_CHECK_IF_WAREHOUSE_ON_OF_LINE'))
		{
			// Check si un fk_entrepot est saisie sur chaque ligne de l'OF courrant et sur les OFs enfants
			foreach ($TOf as &$of)
			{
				if (!$of->checkWharehouseOnLines() && getDolGlobalInt('ASSET_MANUAL_WAREHOUSE'))
				{
					$error++;
					$this->errors[] = $langs->trans('ofError_fk_entrepot_missing', $of->numero);
				}
			}
		}

		if (!$error)
		{//$PDOdb->debug =true;
			foreach ($TOf as &$of)
			{
				//var_dump($of->id, $of->status );
				// On valide pas un of qui est déjà validé ou supérieur
				if($of->getId() <= 0 || $of->status != 'DRAFT') continue;

				$of->status = 'VALID';

				if($this->getId() == $of->getId()) { // Ca c'est juste pour l'of sur lequel on se trouve.
					if(!empty($_REQUEST['TAssetOFLine']))
					{
						foreach($_REQUEST['TAssetOFLine'] as $k => $row)
						{
							$of->TAssetOFLine[$k]->set_values($row);
						}
					}
				}

				$of->createOfAndCommandesFourn($PDOdb);
				$of->unsetChildDeleted = true;

				// On met déjà à jour tous les OFs enfant (même si récursion) un à un, donc je ne veux pas qu'il enregistre les enfants (->TAssetOf) ça sert à rien
				$TAssetOF_tmp = $of->TAssetOF;
                $of->TAssetOF= array();

				foreach ($of->TAssetOFLine as $k => &$ofLine)
				{
					if($ofLine->type == 'NEEDED') {

						$TAllow_modify = array();

						if(getDolGlobalInt('ASSET_DEFINED_WORKSTATION_BY_NEEDED') && getDolGlobalInt('OF_USE_APPRO_DELAY_FOR_TASK_DELAY')) {
							$nb = $ofLine->getNbDayForReapro(); // si besoin de stock
                            // TODO à voir si je conserve le délai de réappro au niveau de l'objet $ofLine dans un attribut nb_days_before_reapro
//var_dump($nb);
							foreach($ofLine->TWorkstation as &$ws) {
								foreach($of->TAssetWorkstationOF as &$wsof) {

									if(getDolGlobalInt('ASSET_USE_PROJECT_TASK') && $wsof->fk_project_task <= 0 && $of->fk_project>0) {
										// a priori la tâche devrait exister, donc on test
										$wsof->save($PDOdb);
									}

									// TODO vérifier le test sur : $wsof->nb_days_before_beginning<=0
									if($ws->id == $wsof->fk_asset_workstation/* && $wsof->fk_project_task>0 */&& ($wsof->nb_days_before_beginning<=0 || !empty($TAllow_modify[$wsof->fk_asset_workstation] ))) {
										if($ws->type == 'STT') {
											$wsof->nb_hour_real = $wsof->nb_hour = $nb * 7; //TODO debug
										}
										else if($wsof->nb_days_before_beginning < $nb) {
										    if (!getDolGlobalInt('OF_USE_APPRO_DELAY_FOR_TASK_DELAY_DISABLE_DELAY_BEFORE_START'))	$wsof->nb_days_before_beginning = $nb;
										    else $wsof->nb_days_before_reapro = $nb;
										}
										$TAllow_modify[$wsof->fk_asset_workstation] = true;

									}
								}
							}

						}
					}
				}

				$of->save($PDOdb);
                $of->TAssetOF = $TAssetOF_tmp;
			}

            if(getDolGlobalInt('ASSET_TASK_HIERARCHIQUE_BY_RANK'))
            {
                $fk_task_last = $this->applyHirarchyOnTask(); // création des liens parent/enfant entre les tâches
                $this->calculTaskDates(); // calcul des dates pour respecter l'enchainement des dates
            }


      if(getDolGlobalInt('OF_FORCE_GENERATE_PDF_ON_VALID')) {
				// cette conf ne fonctionne qu'avec les models compatibles standard Dolibarr
				// elle fait planter les génération en TBS odt car fonctionne avec un vieux sytème tout pérave
				$this->generateDocument('', $langs);
			}

			return 1;
		}

		return -1;
	}

    /**
     * @param  int|null    $fk_task_prev
     * @return int|null     null if nothing, >0 to get the last task id
     */
	public function applyHirarchyOnTask($fk_task_prev = null, $recursivity = true)
    {
        global $db, $user;

        // TODO faire la gestion des erreurs
        /** @var TAssetWorkstationOf $workstationOf */

		if(getDolGlobalInt('ASSET_TASK_HIERARCHIQUE_BY_RANK_REVERT'))
		{
			$TAssetWorkstationOFReverse = $this->TAssetWorkstationOF;
		}
		else{
			$TAssetWorkstationOFReverse = array_reverse($this->TAssetWorkstationOF);
		}

        foreach ($TAssetWorkstationOFReverse as $workstationOf)
        {
            if (!isset($workstationOf->projectTask) && !empty($workstationOf->fk_project_task))
            {
                $workstationOf->projectTask = new Task($db);
                $workstationOf->projectTask->fetch($workstationOf->fk_project_task);
            }

            if (empty($workstationOf->projectTask->id))
            {
                continue; // Pas de liaison avec une tâche, probablement dû au fait qu'il n'ait pas de liaison avec un workstation
            }

            if ($fk_task_prev !== null)
            {
                $workstationOf->projectTask->fk_task_parent = $fk_task_prev;
                $workstationOf->projectTask->update($user);
            }

            $fk_task_prev = $workstationOf->projectTask->id;
        }

        if ($recursivity && !empty($this->TAssetOF))
        {
            foreach ($this->TAssetOF as $childOf)
            {
                $childOf->applyHirarchyOnTask($fk_task_prev);
            }
        }

        return $fk_task_prev;
    }

    // TODO faire la gestion des erreurs
    public function calculTaskDates()
    {
        global $db, $user;

        $last_date_end = null;

        if (!empty($this->TAssetOF))
        {
            $TAssetOfReverse = array_reverse($this->TAssetOF);
            foreach ($TAssetOfReverse as $assetOf)
            {
                $last_date_end = $assetOf->calculTaskDates();
            }
        }

//        $TAssetWorkstationOFReverse = array_reverse($this->TAssetWorkstationOF);
        foreach ($this->TAssetWorkstationOF as $workstationOf)
        {
            if (!isset($workstationOf->ws) && !empty($workstationOf->fk_asset_workstation))
            {
                $workstationOf->ws = new TAssetWorkstationOF();
                $workstationOf->ws->load($this->PDOdb, $workstationOf->fk_asset_workstation);
            }
            if (!isset($workstationOf->projectTask) && !empty($workstationOf->fk_project_task))
            {
                $workstationOf->projectTask = new Task($db);
                $workstationOf->projectTask->fetch($workstationOf->fk_project_task);
            }

            if (!empty($workstationOf->ws->id) && !empty($workstationOf->projectTask->id))
            {
                $TDate = $workstationOf->calculTaskDates($this->PDOdb, $this, $workstationOf->ws, $workstationOf->projectTask, $last_date_end);
                $workstationOf->projectTask->date_start = $TDate['date_start'];
                $workstationOf->projectTask->date_end = $TDate['date_end'];

                $last_date_end = $TDate['date_end'];
                $workstationOf->projectTask->update($user);
            }
        }

        return $last_date_end;
    }

	function create_new_project() {

		global $db, $user;

		dol_include_once('/projet/class/project.class.php');

		// On crée un projet
		$project = new Project($db);

		$project->ref = TAssetWorkstationOF::get_next_ref_project();

		// On récupère le fk_commande associé
		if($_REQUEST['action'] === 'createOFCommande') $fk_commande = $_REQUEST['fk_commande'];
		else $fk_commande = $this->fk_commande;

		if(!empty($fk_commande)) {

			dol_include_once('/commande/class/commande.class.php');
			$commande = new Commande($db);
			$commande->fetch($fk_commande);

			// On nomme le projet avec la ref de la commande d'origine
			if(!empty($commande->ref)) $project->title.= 'Commande client '.$commande->ref;
			$this->fk_project = $project->create($user);

			// On associe la commande au projet
			$project->update_element('commande', $fk_commande);

		}

	}

	function set_fourniture_cost($force_pmp=false) {

		$this->compo_cost = 0;
		$this->compo_estimated_cost = 0;
		$this->compo_planned_cost = 0;

		foreach($this->TAssetOFLine as &$line) {
			//TODO il manque ici les coefficients de frais généraux. A récupérer depuis la nomenclature lors de la création de l'OF

			if($line->type == 'NEEDED') {

				if(empty($line->pmp) || $force_pmp) $line->load_product($force_pmp);

				$line->compo_cost = $line->pmp;
				$line->compo_estimated_cost= $line->pmp; //TODO affiner
				$line->compo_planned_cost= $line->pmp; //TODO affiner

				$this->compo_cost+= $line->qty_used * $line->compo_cost;
				$this->compo_estimated_cost+= $line->qty_needed * $line->compo_estimated_cost;
				$this->compo_planned_cost+= $line->qty * $line->compo_planned_cost;
			}

		}

	}

	static function addStockMouvementDolibarr($fk_product,$qty,$description, $fk_entrepot,$price = 0)
	{
		global $db, $user,$conf;

		require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
		require_once DOL_DOCUMENT_ROOT.'/product/stock/class/mouvementstock.class.php';

		$mouvS = new MouvementStock($db);

		$conf->global->PRODUIT_SOUSPRODUITS = false; // Dans le cas asset il ne faut pas de destocke recurssif
		if(getDolGlobalInt('OF_DONT_UPDATE_PMP_ON_CLOSE')) $price = 0;
		if($fk_entrepot > 0 && !empty($qty))
		{
				if($qty > 0) {
					$result=$mouvS->reception($user, $fk_product, $fk_entrepot, $qty, $price, $description);
				} else {
					$result=$mouvS->livraison($user,$fk_product, $fk_entrepot, -$qty, $price, $description);

				}
		}
	}

	function set_temps_fabrication($justPrice=false) {
		global $db, $user, $conf;
        dol_include_once('/projet/class/task.class.php');

		$this->temps_estime_fabrication=0;
		$this->temps_reel_fabrication=0;
		$this->mo_cost = $this->mo_estimated_cost = 0;

		$night = $this->isNight();

		foreach($this->TAssetWorkstationOF as &$ws) {

			$this->temps_estime_fabrication+=$ws->nb_hour;
			$this->temps_reel_fabrication+=$ws->nb_hour_real;

			if ($night) $thm = $ws->ws->thm_night;
			else $thm = $ws->ws->thm;

			$ws->thm = $thm + $ws->ws->thm_machine;
			$ws->mo_cost = $ws->nb_hour_real * $ws->thm ;
			$ws->mo_estimated_cost= $ws->nb_hour * $ws->thm;

			$this->mo_cost+= $ws->mo_cost;
			$this->mo_estimated_cost+= $ws->mo_estimated_cost;

            if(!$justPrice && $ws->fk_project_task>0) {

               $task = new Task($db);
               $task->fetch($ws->fk_project_task);

	       		if($task->date_start<$this->date_lancement) {
                   $task->date_start = $this->date_lancement;
		   		   $task->update($user);
               }

            }

		}

	}

	function isNight()
	{
		global $conf;

		$night = false;
		if (getDolGlobalString('WORKSTATION_TRANCHE_HORAIRE_THM_NUIT'))
		{
			// Cas simple
			$tranche = explode('-', getDolGlobalString('WORKSTATION_TRANCHE_HORAIRE_THM_NUIT'));
			$heure_de_saisie = date('Hi');

			if ($heure_de_saisie >= $tranche[0] || $heure_de_saisie <= $tranche[1]) $night = true;
		}

		return $night;
	}

	function getNomUrl($picto=0) {
		global $langs;

		$label = $langs->trans('titleOfToolTip', $this->numero)
            .'<br>'
            .$langs->trans($this->getLibStatus())
            .'<br>';

		if(!empty($this->date_besoin)) $label .= date('d/m/Y', $this->date_besoin);

		$str =  '<a class="classfortooltip" title="'.dol_escape_htmltag($label, 1).'" href="'.dol_buildpath('/of/fiche_of.php?id='.$this->getId().'"', 2).'>'
                .($picto ? img_picto('','object_list.png','',0).' ' : '')
                .$this->numero
                .'</a>';

		return $str;
	}

	/**
	 * Renvoi un tableau contenant les ID des asset associé à l'OF (TO_MAKE ou NEEDED)
	 *
	 * @param type $PDOdb
	 * @param type $type
	 * @return array of id asset
	 */
	function getTAssetId(&$PDOdb, $type='TO_MAKE')
	{
		$sql = 'SELECT ee.fk_target as fk_asset FROM '.MAIN_DB_PREFIX.'element_element ee';
		$sql.= ' INNER JOIN '.MAIN_DB_PREFIX.'assetOf_line aol ON (ee.fk_source = aol.rowid)';
		$sql.= ' WHERE ee.targettype = \'TAsset\'';
		$sql.= ' AND ee.sourcetype = \'TAssetOFLine\'';
		$sql.= ' AND aol.fk_assetOf = '.$this->getId();
		$sql.= ' AND aol.type = \''.$type.'\'';

		$PDOdb->Execute($sql);
		$TAssetId = $PDOdb->Get_All();

		if (!empty($TAssetId)) return $TAssetId;
		else return array();
	}

    /**
     * Permet de mettre à jour l'attribut date_lancement, que l'objet soit chargé ou non
     * S'il est chargé, alors l'update en BDD est faite aussi
     * @param $PDOdb
     * @param $time
     * @return bool
     */
	public function updateDateLancement($PDOdb, $time)
    {
        $this->date_lancement = $time;

        if ($this->getId() <= 0) return false;

        return $PDOdb->dbupdate(
            $this->get_table()
            , array('date_lancement' => date('Y-m-d', $time), 'rowid' => $this->getId())
            , array('rowid')
        );
    }

	/**
	 * Permet de mettre à jour l'attribut date_besoin
	 * S'il est chargé, alors l'update en BDD est faite aussi
	 * @param $PDOdb
	 * @param $time
	 * @return bool
	 */
	public function updateDateBesoin($PDOdb, $time)
	{
		$this->date_besoin = $time;

		if ($this->getId() <= 0) return false;

		return $PDOdb->dbupdate(
			$this->get_table()
			, array('date_besoin' => date('Y-m-d', $time), 'rowid' => $this->getId())
			, array('rowid')
		);
	}

    /**
     * Permet de calculer la date de lancement de l'OF lors de sa validation
     * Attention, les délai de réapprovisionnement sont prisent en compte
     * @param     $PDOdb
     * @param int $time
     */
	function setDelaiLancement(&$PDOdb, $time = 0) {

	    global $conf;

		if((empty($this->date_lancement) && $this->status != 'DRAFT')
		 || ( $this->date_lancement < $time ))
		 {

			$nb_day_prod = 0;
			$nb_day_service = 0;

			foreach ($this->TAssetOFLine as $k => &$ofLine)
			{
				//Methode 1, le MAX(appro) + SUM(service)
				if($ofLine->type == 'NEEDED') {
                    /**
                     * TODO utiliser l'attribut nb_days_before_reapro au lieu de @see TAssetOFLine::getNbDayForReapro()
                     * @see TAssetOF::validate
                     */
					$nb = $ofLine->getNbDayForReapro(); // si besoin de stock

					if($ofLine->product->type == 1) {
						$nb_day_service+=$nb;
					}
					else {
					    // On garde uniquement le temps de réapprovisionnement le plus grand
						if($nb_day_prod<$nb)$nb_day_prod = $nb;
					}

				}
			}

			$date_lancement = strtotime('midnight');
			$delai = $nb_day_prod + $nb_day_service;

            if ($delai > 0)
            {
                if (getDolGlobalString('OF_LANCEMENT_SKIP_DAYS_OF_WEEK'))
                {
                    $i=0;
                    $TDayToSkip = explode(',', getDolGlobalString('OF_LANCEMENT_SKIP_DAYS_OF_WEEK'));
                    while ($delai > 0)
                    {
                        if (getDolGlobalInt('OF_LANCEMENT_SKIP_DAYS_OF_WEEK_LIMIT_LOOP') && getDolGlobalInt('OF_LANCEMENT_SKIP_DAYS_OF_WEEK_LIMIT_LOOP') < $i) break;

                        $date_lancement = strtotime('+1 day', $date_lancement);

                        if (in_array(date('w', $date_lancement), $TDayToSkip) === true) continue;
                        else $delai--;

                        $i++;
                    }
                }
                else
                {
                    $date_lancement = strtotime('+'.$delai.' day', $date_lancement);
                }
            }

            $this->date_lancement = $date_lancement;

			if( $this->date_lancement < $time ) $this->date_lancement = $time;

			$time_child = $this->getMaxDelaiLancementForChild($PDOdb);

			if( $this->date_lancement < $time_child ) $this->date_lancement = $time_child;

			$this->updateDateLancement($PDOdb, $this->date_lancement);

		}

		$this->setDelaiLancementForParent($PDOdb);

	}

	function getMaxDelaiLancementForChild(&$PDOdb) {

	    $PDOdb->Execute("SELECT max(date_lancement) as date_lancement FROM ".$this->get_table()." WHERE fk_assetOf_parent=".$this->getId());
	    if($obj = $PDOdb->Get_line()) {
	        return strtotime($obj->date_lancement);

	    }

	    return -1;
	}

	function setDelaiLancementForParent(&$PDOdb) {
//var_dump($this->fk_assetOf_parent);exit;
//		return false;
		if($this->fk_assetOf_parent>0) {

			$of=new TAssetOF;
			if($of->load($PDOdb, $this->fk_assetOf_parent)>0) {
				$of->setDelaiLancement($PDOdb, $this->date_lancement);
			}


		}


	}

	function save(&$PDOdb) {

		global $user,$langs,$conf, $db;
	//	var_dump( $this->status, debug_backtrace());

        $onUpdate = 0;

		$this->set_temps_fabrication();
		$this->set_fourniture_cost();
		$this->total_cost = $this->compo_cost + $this->mo_cost;
		$this->total_estimated_cost = $this->compo_estimated_cost + $this->mo_estimated_cost;

		if(getDolGlobalInt('USE_LOT_IN_OF'))
		{
			$this->setLotWithParent($PDOdb);
		}

		//Sécurité sur la maj de l'objet, si on supprime les lignes d'un OF en mode edit, lors de l'enregistrement les infos sont ré-insert avec un fk_product à 0
		foreach ($this->TAssetOFLine as $k => &$ofLine)
		{
			if (!$ofLine->fk_product)
			{
				unset($this->TAssetOFLine[$k]);
			}
		}

		$this->destockOrStockPartialQty($PDOdb, $this);

		if($this->fk_project == 0) {
			if(getDolGlobalInt('ASSET_AUTO_CREATE_PROJECT_ON_OF')) $this->create_new_project();
			elseif(!empty($this->fk_commande)) {
				require_once DOL_DOCUMENT_ROOT . '/commande/class/commande.class.php';
				$commande = new Commande($db);
				$commande->fetch($this->fk_commande);
				if(!empty($commande->fk_project)) $this->fk_project = $commande->fk_project;
			}
		}

		foreach($this->TAssetOF as &$of) $of->fk_project = $this->fk_project;

        if(!empty($this->id)) {
            $this->setDelaiLancement($PDOdb);
            $onUpdate = 1; //pour éviter de faire 2x le traitement
        }

        if(getDolGlobalInt('OF_RANK_PRIOR_BY_LAUNCHING_DATE')){

            if(!empty($this->date_lancement)) {
                if(!empty($this->rank)) $this->ajustRank();
                else $this->getNextRank();
            } else {
                setEventMessage($langs->trans('MissingLaunchingDateForRank'), 'warnings');
            }
        }

		if(!empty($this->TAssetOFLine)){
			foreach($this->TAssetOFLine as &$AssetOFLine)
			{
				//si lors de l'enregistrement, il y a des non conformes, on ajoute les postes de travail et on crée les taches si conf activé.
				if(!empty(getDolGlobalString('OF_WORKSTATION_NON_COMPLIANT')) && !empty($AssetOFLine->qty_non_compliant) && !empty($AssetOFLine->fk_assetOf)) { //Pour chaque of non conforme
					if($this->status == 'OPEN' || $this->status == 'CLOSE') {
						$TFKWorkstationToAdd = explode(',', getDolGlobalString('OF_WORKSTATION_NON_COMPLIANT'));

						if(!empty($TFKWorkstationToAdd)){
							foreach($TFKWorkstationToAdd as $key => $fk_workstation) {
								foreach($this->TAssetWorkstationOF as $TAssetWorkstationOF) { //Pour éviter de créer des workstation en double
									if($fk_workstation == $TAssetWorkstationOF->fk_asset_workstation) unset($TFKWorkstationToAdd[$key]);
								}
							}
						}

						if(!empty($TFKWorkstationToAdd)){
							foreach($TFKWorkstationToAdd as $fk_workstation) {
								$res  = $this->addofworkstation($PDOdb, $fk_workstation, 0, 0, 0, 0, '', 0);

								if(!empty(getDolGlobalString('ASSET_USE_PROJECT_TASK'))) {
									require_once DOL_DOCUMENT_ROOT . '/projet/class/task.class.php';
									require_once DOL_DOCUMENT_ROOT . '/core/modules/project/task/' . getDolGlobalString('PROJECT_TASK_ADDON') . '.php';

									$lastInsert = count($this->TAssetWorkstationOF);
									$this->TAssetWorkstationOF[$lastInsert - 1]->fk_assetOf = $AssetOFLine->fk_assetOf;
									$action = '';

									if(getDolGlobalInt('ASSET_CUMULATE_PROJECT_TASK')){
										$taskstatic = new Task($db);
										$TTask = $taskstatic->getTasksArray(null, null, $this->fk_project);

										if(!empty($TTask)) {
											foreach($TTask as $task) {
												$task->fetch_optionals();
												if(!empty($task->array_options['options_fk_workstation']) && $this->TAssetWorkstationOF[$lastInsert - 1]->fk_asset_workstation == $task->array_options['options_fk_workstation']){
													$action = 'updateTask';
													$this->TAssetWorkstationOF[$lastInsert - 1]->fk_project_task=$task->id;
													$this->from_create=1;
												}
											}
										}
									}

									if($action == 'updateTask') $this->TAssetWorkstationOF[$lastInsert - 1]->updateTask($PDOdb, $db, $conf, $user, $this);
									else $this->TAssetWorkstationOF[$lastInsert - 1]->createTask($PDOdb, $db, $conf, $user, $this);
								}
							}
						}
						if(!empty($this->TChildObjetStd)){
							foreach($this->TChildObjetStd as $key => $TChildObjetStd) { // Sinon boucle infini car AssetOfline est l'enfant d'of et j'ai besoin de save les enfants pour les assetofworkstation
								if($TChildObjetStd['class'] == get_class($AssetOFLine)) unset($this->TChildObjetStd[$key]);
							}
						}
					}
				}
			}
		}

		parent::save($PDOdb);

        if(!$onUpdate) $this->setDelaiLancement($PDOdb);

        $this->getNumero($PDOdb, true);


		// Appel des triggers
		include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
		$interface = new Interfaces($db);
		$result = $interface->run_triggers('ASSET_OF_SAVE',$this,$user,$langs,$conf);
		if ($result < 0)
		{
			$this->errors[] = $interface->errors;
		}
	}

    function ajustRank() {

	    $old_rank = $this->getOldRank();

        if($old_rank > $this->rank || empty($old_rank))$this->setRank($old_rank,$this->rank,1);
        else $this->setRank($this->rank,$old_rank,-1);

        if($this->isRankTooHigh()) $this->getNextRank(); // On le replace là où il faut

    }

    function isRankTooHigh(){
        global $db;
        $launchingDate = date('Y-m-d', $this->date_lancement);
        $sql = "SELECT rowid FROM $this->table WHERE date_lancement='$launchingDate' AND rank=".($this->rank-1);

        if(!empty($this->id)) $sql .= " AND rowid!=$this->id";

        $resql = $db->query($sql);
        if(!empty($resql) && $db->num_rows($resql)>0 || $this->rank==1) return false;// Le premier est le seul à ne pas pouvoir avoir de précédent

        return true;
    }

    function getOldRank(){
	    global $db;

        $sql = "SELECT  rank FROM $this->table WHERE rowid=$this->rowid";
        $resql = $db->query($sql);

        if(!empty($resql)){
            $obj = $db->fetch_object($resql);
            return $obj->rank;
        }

        return 0;
    }

   /* function rankDirection(){
        global $db;

        if(!empty($this->id)) {
            $sql = "SELECT rank FROM $this->table WHERE rowid = $this->id";
            $resql = $db->query($sql);
            if(!empty($resql)) {
                $db->fetch_object();
            }
        }

	    return 1;
    }*/

    /*function fillMissingRank() {
        global $db;

        $launchingDate = date('Y-m-d', $this->date_lancement);

        $sql = "SELECT
             CONCAT(z.expected, IF(z.got-1>z.expected, CONCAT(' thru ',z.got-1), '')) AS missing
            FROM (
                 SELECT
                  @rownum:=@rownum+1 AS expected,
                  IF(@rownum=rank, 0, @rownum:=rank) AS got
                 FROM
                  (SELECT @rownum:=0) AS a
                  JOIN $this->table
                  WHERE date_lancement='$launchingDate'";
        $sql .= " ORDER BY rank
             ) AS z
            WHERE z.got!=0;"; // On récupère tous les manquants (soit un nb s'il est seul sinon de tel val à tel val)
        //si ça peut aider à comprendre la requête : https://stackoverflow.com/questions/4340793/how-to-find-gaps-in-sequential-numbering-in-mysql/29736658#29736658
        $resql = $db->query($sql);
        if(!empty($resql) && $db->num_rows($resql)>0) {
            $obj = $db->fetch_object($resql);

            if(strpos($obj->missing, ' thru ') === false){
                $sqlUpdate="UPDATE $this->table SET rank=rank-1 WHERE rank>$obj->missing AND date_lancement='$launchingDate'";
                $db->query($sqlUpdate);
            }else {
                $limits = explode(' thru ', $obj->missing);
                $sqlUpdate="UPDATE $this->table SET rank=rank-".($limits[1]-$limits[0]+1)." WHERE rank>$limits[0] AND date_lancement='$launchingDate'";
                $db->query($sqlUpdate);
            }
            $this->fillMissingRank();
        }
    }*/

    function setRank($high_rank, $low_rank, $value) {
        global $db;
        $launchingDate = date('Y-m-d', $this->date_lancement);

        if(!empty($high_rank)) $sqlEmptyOldRank = "AND rank <= $high_rank";
        else $sqlEmptyOldRank = '';

        $sql = "SELECT rowid, rank FROM $this->table WHERE date_lancement='$launchingDate' AND rank>=$low_rank $sqlEmptyOldRank";

        if(!empty($this->id)) $sql .= " AND rowid!=$this->id";

        $resql = $db->query($sql);

        if(!empty($resql)) {
            while($obj = $db->fetch_object($resql)) {
                $sqlUpdate = "UPDATE $this->table SET rank=" . ($obj->rank + $value) . " WHERE rowid=$obj->rowid";

                $db->query($sqlUpdate);
            }
        }
    }

    /*function hasSameRank(){
        global $db;
        $launchingDate = date('Y-m-d', $this->date_lancement);

        //On vérifie qu'il n'y a pas d'autres rangs de même niveau
        $sql = "SELECT rowid FROM $this->table WHERE date_lancement='$launchingDate' AND rank=$this->rank";
        if(!empty($this->id))$sql .= " AND rowid!=$this->id";

        $resql = $db->query($sql);

        if(!empty($resql) && $db->num_rows($resql) > 0)return true;
        return false;
    }*/

	function getNextRank(){
	    global $db;

        $launchingDate = date('Y-m-d', $this->date_lancement);
        $sql = "SELECT MAX(rank) as max_rank FROM $this->table WHERE date_lancement='$launchingDate'";
        if(!empty($this->id))$sql .= " AND rowid!=$this->id";

        $resql = $db->query($sql);
        $this->rank = 1;

        if(!empty($resql)){
            $obj = $db->fetch_object($resql);
            $this->rank = $obj->max_rank +1;
        }

    }

    function getNumero(&$PDOdb, $save=false) {
        global $db, $conf;

        if(empty($this->numero)) {
            dol_include_once('of/lib/of.lib.php');

            $mask = getDolGlobalString('OF_MASK','OF{00000}');
            $numero = get_next_value_PDOdb($PDOdb,$mask,'assetOf','numero');

            if($save) {
                $this->numero = $numero;

                $wc = $this->withChild;
                $this->withChild=false;
                parent::save($PDOdb);
                $this->withChild=$wc;

            }

        }
        else{
            $numero = $this->numero;
        }

        return $numero;

    }

    function setStatus(TPDOdb &$PDOdb, $status) {

    	$PDOdb->dbupdate($this->get_table(),array('rowid'=>$this->getId(),'status'=>$status ),array('rowid'));

    }

	function setLotWithParent(&$PDOdb)
	{
		if (count($this->TAssetOFLine) && $this->fk_assetOf_parent)
		{
			$ofParent = new TAssetOF;
			$ofParent->load($PDOdb, $this->fk_assetOf_parent);

			foreach($ofParent->TAssetOFLine as &$ofLigneParent)
			{
				foreach($this->TAssetOFLine as &$ofLigne)
				{
					if($ofLigne->fk_product == $ofLigneParent->fk_product)
					{
						if (empty($this->update_parent))
						{
							$ofLigne->lot_number = $ofLigneParent->lot_number;
							$ofLigne->save($PDOdb);
						}
						else
						{
							$ofLigneParent->lot_number = $ofLigne->lot_number;
							$ofLigneParent->save($PDOdb);
						}
					}
				}
			}
		}
	}

	//Associe les équipements à l'OF
	function setEquipement(&$PDOdb)
	{
		//pre($this->TAssetOFLine,true);exit;
		foreach($this->TAssetOFLine as $TAssetOFLine)
		{
			$TAssetOFLine->setAsset($PDOdb,$this,true);
		}

		return true;
	}

	function delLine(&$PDOdb,$iline)
	{
		global $user,$langs,$conf,$db;

		$this->TAssetOFLine[$iline]->to_delete=true;

		// Appel des triggers
		include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
		$interface = new Interfaces($db);
		$result = $interface->run_triggers('ASSET_OF_DEL_LINE',$this,$user,$langs,$conf);
		if ($result < 0)
		{
			$this->errors[] = $interface->errors;
		}
	}

    /**
     * Ajout d'un produit TO_MAKE à l'OF
     * @param TPDOdb $PDOdb
     * @param int    $fk_product
     * @param int    $quantite_to_make
     * @param int    $fk_assetOf_line_parent
     * @param int    $fk_nomenclature
     * @return bool
     */
	function addProductComposition(&$PDOdb, $fk_product, $quantite_to_make=1, $fk_assetOf_line_parent=0, $fk_nomenclature=0)
	{
		global $conf;

		$Tab = $this->getProductComposition($PDOdb,$fk_product, $quantite_to_make, $fk_nomenclature, $fk_assetOf_line_parent);

		foreach($Tab as $fk_product => $TProd)
		{
		    foreach ($TProd as $prod)
		    {
		    	$idLine = $this->addLine($PDOdb, $prod->fk_product, 'NEEDED', $prod->qty,$fk_assetOf_line_parent, '', 0, 0, $prod->note_private , $prod->workstations);

				if (getDolGlobalInt('CREATE_CHILDREN_OF'))
				{
					$TabSubProd = $this->getProductComposition($PDOdb,$prod->fk_product, $prod->qty);

					if ((getDolGlobalInt('CREATE_CHILDREN_OF_COMPOSANT') && !empty($TabSubProd)) || !getDolGlobalInt('CREATE_CHILDREN_OF_COMPOSANT'))
					{
                        $this->createOFifneeded($PDOdb, $prod->fk_product, $prod->qty, $idLine);
					}
				}
			}
		}

		return true;
	}

	//Retourne les produits NEEDED de l'OF concernant le produit $id_produit
	function getProductComposition(&$PDOdb,$id_product, $quantite_to_make, $fk_nomenclature=0, $fk_assetOf_line_parent=0)
	{
		global $db,$conf;

		$Tab=array();

		if (isModEnabled('nomenclature'))
		{
			dol_include_once('/nomenclature/class/nomenclature.class.php');
			if ($fk_nomenclature)
			{
				$TNomen = new TNomenclature;
				$TNomen->load($PDOdb, $fk_nomenclature);
			}
			else
			{
				$TabNomen = TNomenclature::get($PDOdb, $id_product);
				if (!empty($Tab[0])); $TNomen = $TabNomen[0];
			}


			if (!empty($TNomen))
			{

				$TRes = $TNomen->getDetails($quantite_to_make);
				$this->getProductComposition_arrayMerge($PDOdb, $Tab, $TRes, 1, true, $fk_assetOf_line_parent);
			}


		}
		else
		{
			include_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';

			$product = new Product($db);
			$product->fetch($id_product);
			$TRes = $product->getChildsArbo($product->id);
			// var_dump($TRes);
			$this->getProductComposition_arrayMerge($PDOdb,$Tab, $TRes, $quantite_to_make);
		}
		/*if($id_product==2) {

			var_dump($Tab);exit;
		}*/
		return $Tab;
	}

	private function getProductComposition_arrayMerge(&$PDOdb,&$Tab, $TRes, $qty_parent=1)
	{
		global $conf;
		//TODO AA c'est de la merde à refaire
		foreach($TRes as $row)
		{
			$prod = new stdClass;
			$prod->fk_product = $row[0];
			$prod->qty = $row[1] * $qty_parent;
		        $prod->note_private = isset($row['note_private']) ? $row['note_private'] : '';
		        $prod->workstations = isset($row['workstations']) ? $row['workstations'] : '';

			if (getDolGlobalInt('ASSETOF_NOT_CONCAT_QTY_FOR_NEEDED'))
			{
				$Tab[$prod->fk_product][]=$prod;
			}
			else
			{
				if(isset($Tab[$prod->fk_product]))
				{
					$Tab[$prod->fk_product][0]->qty += $prod->qty;
				}
				else
				{
					$Tab[$prod->fk_product][]=$prod;
				}
			}

		}

	}

	static function qtyFromOF($fk_product, $include_draft = true) {
		global $db, $conf;

		$qty_to_make = $qty_needed = 0;
		$sql = 'SELECT (SELECT SUM( CASE WHEN aol.qty_used>0 THEN aol.qty_used ELSE aol.qty END ) - SUM(aol.qty_stock)
			        	FROM  '.MAIN_DB_PREFIX.'assetOf_line aol
			        	INNER JOIN '.MAIN_DB_PREFIX.'assetOf ao ON (aol.fk_assetOf = ao.rowid)
			        	AND aol.fk_product = '.$fk_product.'
			        	AND aol.type = "TO_MAKE"
			        	AND ao.status IN ('. ($include_draft ? '"DRAFT",':'').' "VALID", "OPEN", "ONORDER", "NEEDOFFER")) AS qty_to_make
			        ,(SELECT '.( getDolGlobalInt('OF_USE_DESTOCKAGE_PARTIEL') ? 'SUM(aol.qty_needed) - SUM(aol.qty_used)' : ' (SUM(aol.qty_needed) - SUM(aol.qty_used)) + SUM(aol.qty_used) - SUM(aol.qty_stock)' ).'
			        	FROM '.MAIN_DB_PREFIX.'assetOf_line aol
						INNER JOIN '.MAIN_DB_PREFIX.'assetOf ao ON (aol.fk_assetOf = ao.rowid)
						WHERE aol.fk_product = '.$fk_product.'
						AND aol.type = "NEEDED"
						AND aol.qty_used <= aol.qty_needed
						AND ao.status IN ('. ($include_draft ? '"DRAFT",':'').'"VALID", "OPEN", "ONORDER", "NEEDOFFER")) AS qty_needed';

		$resql = $db->query($sql);
		if($resql === false) {
			var_dump($db);
		}
		if ($row = $db->fetch_object($resql))
		{
			$qty_to_make = is_null($row->qty_to_make) ? 0 : $row->qty_to_make;
			$qty_needed = is_null($row->qty_needed) ? 0 : $row->qty_needed;
		}

		return array($qty_to_make, $qty_needed);

	}

	/*
	 * Crée une OF si produit composé pas en stock
	 */
	function createOFifneeded(&$PDOdb,$fk_product, $qty_needed, $fk_assetOfLine_parent = 0) {
		global $conf,$db;

		if (getDolGlobalInt('CREATE_CHILDREN_OF_PREVENT_OF_CREATION_FOR_SERVICES') || getDolGlobalInt('CREATE_CHILDREN_OF_PREVENT_OF_CREATION_FOR_PRODUCTS_RAWMATERIAL')) {
			$product = new Product($db);
			$product->fetch($fk_product);
			if (getDolGlobalInt('CREATE_CHILDREN_OF_PREVENT_OF_CREATION_FOR_SERVICES') && $product->type == Product::TYPE_SERVICE) {
				return null;
			}
			if (getDolGlobalInt('CREATE_CHILDREN_OF_PREVENT_OF_CREATION_FOR_PRODUCTS_RAWMATERIAL') && $product->finished == 0) {
				return null;
			}
		}

		$reste = TAssetOF::getProductStock($fk_product,0,true, getDolGlobalInt('CREATE_CHILDREN_OF_ON_VIRTUAL_STOCK'))-$qty_needed;


		if($reste>=0) {
			return null;
		}
		else {
			$k=$this->addChild($PDOdb,'TAssetOF');
			$this->TAssetOF[$k]->status = 'DRAFT';
			$this->TAssetOF[$k]->fk_project = $this->fk_project;
			$this->TAssetOF[$k]->fk_soc = $this->fk_soc;
			$this->TAssetOF[$k]->fk_commande = $this->fk_commande;
			$this->TAssetOF[$k]->date_besoin = (!empty($this->date_besoin)) ? $this->date_besoin : dol_now();
			$this->TAssetOF[$k]->addLine($PDOdb, $fk_product, 'TO_MAKE', abs($qty_needed), $fk_assetOfLine_parent);

			return $k;
		}
	}

	static function getProductNeededQty($fk_product, $include_draft_of=true, $include_of_from_order = false, $date='', $type='NEEDED') {

		global $db,$conf;

		$sql = "SELECT SUM( IF(qty_needed>0,qty_needed - qty_stock, qty-qty_stock) ) as qty
				FROM ".MAIN_DB_PREFIX."assetOf_line l
					LEFT JOIN ".MAIN_DB_PREFIX."assetOf of ON(l.fk_assetOf = of.rowid)
			WHERE of.entity=".$conf->entity." AND l.fk_product=".$fk_product."
			AND type='".$type."' AND of.status IN (".($include_draft_of ? "'DRAFT',": '')."'VALID','OPEN')
			";
		if(!empty($date))$sql.=" AND of.date_besoin<='".$date."'";
		if(!$include_of_from_order) $sql.=" AND of.fk_commande = 0 ";

		$res = $db->query($sql);

		$obj = $db->fetch_object($res);

		return (float)$obj->qty;


	}

	/*
	 * retourne le stock restant du produit
	 */
	static function getProductStock($fk_product, $fk_warehouse=0, $include_draft_of=true, $use_virtual=false) {
	//TODO finish ! or not
		global $db;

		if($fk_product<=0) return 0;

		dol_include_once('/product/class/product.class.php');

		$product = new Product($db);
		$product->fetch($fk_product);

		if($product->id<=0 || $product->type>0) {
			 return 0;
		}

		$product->load_stock(); // TODO cache

		if($use_virtual) {
			$stock = $product->stock_theorique;
		}
		else {
			if($fk_warehouse>0)$stock = $product->stock_warehouse[$fk_warehouse]->real;
			else $stock =$product->stock_reel;
		}

		// MAIN_MAX_DECIMALS_STOCK
		return price2num($stock, 'MS');
	}

    /**
     * Add TO_MAKE line
     *
     * Ajoute une ligne de produit à l'OF et les lignes dépendantes à la volée (créé les ofs enfant par extension)
	 *
	 * @return id line
	 */
	function addLine(&$PDOdb, $fk_product, $type, $quantite=1,$fk_assetOf_line_parent=0, $lot_number='',$fk_nomenclature=0,$fk_commandedet=0, $note_private = '', $workstations='')
	{
		global $user,$langs,$conf,$db;
        $found = false;
        if(count($this->TAssetOFLine) > 1 && getDolGlobalInt('OF_REGROUP_LINE')) {
            foreach($this->TAssetOFLine as &$assetOFLine) {

                if($assetOFLine->fk_product == $fk_product
                    && ($fk_nomenclature == $assetOFLine->fk_nomenclature || $type == 'NEEDED')
                    && $assetOFLine->type == $type) {
                    $found = true;
                    $TAssetOFLine = $assetOFLine;
                    $TAssetOFLine->qty_needed += $quantite;
                    $TAssetOFLine->qty += (getDolGlobalInt('ASSET_ADD_NEEDED_QTY_ZERO') && $type === 'NEEDED') ? 0 : $quantite;
                    $TAssetOFLine->qty_used += (getDolGlobalInt('ASSET_ADD_NEEDED_QTY_ZERO') && $type === 'NEEDED' || $type === 'TO_MAKE') ? 0 : $quantite;
                    break;
                }
            }
        }
        if(!$found) {


            $k = $this->addChild($PDOdb, 'TAssetOFLine');

            $TAssetOFLine = &$this->TAssetOFLine[$k];

            $TAssetOFLine->fk_assetOf_line_parent = $fk_assetOf_line_parent;
			$TAssetOFLine->fk_assetOf = $this->getId();
            $TAssetOFLine->fk_product = $fk_product;
            $TAssetOFLine->fk_asset = 0; //TODO remove ?
            $TAssetOFLine->type = $type;
            $TAssetOFLine->qty_needed = $quantite;
            $TAssetOFLine->qty = (getDolGlobalInt('ASSET_ADD_NEEDED_QTY_ZERO') && $type === 'NEEDED') ? 0 : $quantite;
            $TAssetOFLine->qty_used = (getDolGlobalInt('ASSET_ADD_NEEDED_QTY_ZERO') && $type === 'NEEDED' || $type === 'TO_MAKE') ? 0 : $quantite;
            $TAssetOFLine->note_private = $note_private;

            $TAssetOFLine->fk_commandedet = $fk_commandedet;

            $TAssetOFLine->fk_product_fournisseur_price = -2;

            if(isModEnabled('nomenclature') && ! $fk_nomenclature) {
                dol_include_once('/nomenclature/class/nomenclature.class.php');

                $TNomen = array();

                if($fk_commandedet > 0) {
                    $TNomen = TNomenclature::get($PDOdb, $fk_commandedet, false, 'commande');
                }

                if(empty($TNomen)) $TNomen = TNomenclature::get($PDOdb, $fk_product);
                if(count($TNomen) == 1) {
                    $TAssetOFLine->fk_nomenclature = $TNomen[0]->getId();
                    $fk_nomenclature = $TAssetOFLine->fk_nomenclature;
                }
            }
            else {
                $TAssetOFLine->fk_nomenclature = $fk_nomenclature;
            }

            $TAssetOFLine->lot_number = $lot_number;

            $TAssetOFLine->initConditionnement($PDOdb);

            if($fk_nomenclature > 0) {
                $TAssetOFLine->nomenclature_valide = true;
            }

            if($type == 'NEEDED') {
                if($TAssetOFLine->fk_product > 0) $TAssetOFLine->load_product();

                if(! empty($workstations)) {

                    $TAssetOFLine->set_workstations($PDOdb, explode(',', $workstations));
                }
            }
        }

        if(getDolGlobalInt('ASSET_MANUAL_WAREHOUSE')) {
            if($TAssetOFLine->fk_product > 0 && empty($TAssetOFLine->product)) $TAssetOFLine->load_product();
            if(! empty($TAssetOFLine->product->fk_default_warehouse)) $TAssetOFLine->fk_entrepot = $TAssetOFLine->product->fk_default_warehouse;
        }

		$idAssetOFLine = $TAssetOFLine->save($PDOdb);

		// Appel des triggers
		include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
		$interface = new Interfaces($db);
		$result = $interface->run_triggers('ASSET_OF_ADD_LINE',$TAssetOFLine,$user,$langs,$conf);
		if ($result < 0)
		{
			$this->errors[] = $interface->errors;
		}

		if($type=='TO_MAKE' && ( $fk_nomenclature>0 || !isModEnabled('nomenclature') ))
		{
			$this->addWorkstation($PDOdb, $fk_product,$fk_nomenclature,$quantite, $found);
			$this->addProductComposition($PDOdb,$fk_product, $quantite,$idAssetOFLine,$fk_nomenclature);
			$this->set_current_cost_for_to_make();
		}

        if(getDolGlobalInt('OF_KEEP_PRODUCT_DOCUMENTS') && !empty($fk_product) && !$found) {
            $prod = new Product($db);
            $prod->fetch($fk_product);

            if(isModEnabled('product')) $product_dir = $conf->product->multidir_output[$prod->entity] . '/' . get_exdir(0, 0, 0, 0, $prod, 'product') . dol_sanitizeFileName($prod->ref);
            else if(isModEnabled('service')) $product_dir = $conf->service->multidir_output[$prod->entity] . '/' . get_exdir(0, 0, 0, 0, $prod, 'product') . dol_sanitizeFileName($prod->ref);

            $this->copyAllFiles($product_dir);
        }

		return $idAssetOFLine;
	}

	function copyAllFiles($dir) {
        global $conf;
        $PDOdb = new TPDOdb;
        $upload_dir = $conf->of->multidir_output[$this->entity] . '/' . get_exdir(0, 0, 0, 0, $this, 'tassetof') . dol_sanitizeFileName($this->getNumero($PDOdb));
        $TFiles = dol_dir_list($dir);
        if(!empty($TFiles)) {
            foreach($TFiles as $file) {
                if(!is_dir($upload_dir)) dol_mkdir($upload_dir);
                dol_copy($file['fullname'], $upload_dir . '/' . $file['name']);
            }
        }
    }

    /**
     * @param        $PDOdb
     * @param        $fk_asset_workstation
     * @param int    $nb_hour
     * @param int    $nb_hour_prepare
     * @param int    $nb_hour_manufacture
     * @param int    $rang
     * @param string $private_note
     * @param int    $nb_days_before_beginning
     * @param int    $nb_days_before_reapro
     * @return bool|int|string
     * @deprecated
     * @see addWorkstation
     */
	function addofworkstation(&$PDOdb, $fk_asset_workstation, $nb_hour=0, $nb_hour_prepare=0,$nb_hour_manufacture=0,$rang=0,$private_note = '',$nb_days_before_beginning=0, $nb_days_before_reapro = 0)
    {
        return $this->addTAssetWorkstationOF($PDOdb, $fk_asset_workstation, $nb_hour, $nb_hour_prepare,$nb_hour_manufacture,$rang,$private_note,$nb_days_before_beginning, $nb_days_before_reapro);
    }


    function addTAssetWorkstationOF(&$PDOdb, $fk_asset_workstation, $nb_hour=0, $nb_hour_prepare=0,$nb_hour_manufacture=0,$rang=0,$private_note = '',$nb_days_before_beginning=0, $nb_days_before_reapro = 0)
    {
		global $conf;

		if(empty($nb_hour))$nb_hour = $nb_hour_prepare + $nb_hour_manufacture;

		$coef = 1;
		if (getDolGlobalInt('OF_COEF_WS')) $coef = getDolGlobalInt('OF_COEF_WS');

		$k=false;
		if(getDolGlobalInt('OF_CONCAT_WS_ON_ADD') && method_exists($this, 'searchChild')) $k = $this->searchChild('TAssetWorkstationOF',$fk_asset_workstation,'fk_asset_workstation');
		if($k===false) $k = $this->addChild($PDOdb, 'TAssetWorkstationOF');

		$this->TAssetWorkstationOF[$k]->fk_asset_workstation = $fk_asset_workstation;
		$this->TAssetWorkstationOF[$k]->nb_hour_prepare += $nb_hour_prepare* $coef;
		$this->TAssetWorkstationOF[$k]->nb_hour_manufacture += $nb_hour_manufacture* $coef;
		$this->TAssetWorkstationOF[$k]->nb_hour += $nb_hour * $coef;
		$this->TAssetWorkstationOF[$k]->nb_days_before_beginning = $nb_days_before_beginning;
		$this->TAssetWorkstationOF[$k]->nb_days_before_reapro = $nb_days_before_reapro;

		$this->TAssetWorkstationOF[$k]->rang = $rang;

		$this->TAssetWorkstationOF[$k]->nb_hour_real = 0;
    	$this->TAssetWorkstationOF[$k]->note_private = $private_note;

		return $k;
	}

	function updateLines(&$PDOdb,$TQty)
	{
		foreach($this->TAssetOFLine as $TAssetOFLine)
		{
			$TAssetOFLine->qty_used = $TQty[$TAssetOFLine->getId()];
			$TAssetOFLine->save($PDOdb);
		}
	}

    /**
     * Fonction qui permet de mettre à jour les postes de travail liés à un produit
     * pour la création d'un OF depuis une fiche produit
     *
     * @param TPDOdb $PDOdb
     * @param int    $fk_product
     * @param int    $fk_nomenclature
     * @param int    $qty_needed
     * @param bool   $found
     */
    function addWorkStation(&$PDOdb, $fk_product, $fk_nomenclature = 0, $qty_needed = 1, $found = false) {
        global $conf;

        if( isModEnabled('workstationatm')) {
            if( isModEnabled('nomenclature') )  {

                if($fk_nomenclature > 0) {
                    dol_include_once('/nomenclature/class/nomenclature.class.php');

                    $n = new TNomenclature;
                    if($n->load($PDOdb, $fk_nomenclature, true)) {
                        //on check si les workstations étaient pas déjà là

                        foreach($n->TNomenclatureWorkstation as &$nws) {
                            $foundWk = false;
                            if($found) {
                                foreach($this->TAssetWorkstationOF as &$wkOF) {
                                    if($wkOF->fk_asset_workstation == $nws->fk_workstation) {
                                        $wkOF->nb_hour += $nws->nb_hour_prepare + $nws->nb_hour_manufacture * ($qty_needed / $n->qty_reference);
                                        $wkOF->nb_hour_prepare += $nws->nb_hour_prepare;
                                        $wkOF->save($PDOdb);
                                        $foundWk = true;
                                    }
                                }
                            }
                            if(! $foundWk) {
                                if(($nws->nb_hour_manufacture > 0 || $nws->nb_hour_prepare > 0) || getDolGlobalInt('ASSET_AUTHORIZE_ADD_WORKSTATION_TIME_0_ON_OF')) {

                                    $k = $this->addTAssetWorkstationOF($PDOdb, $nws->fk_workstation, $nws->nb_hour_prepare + $nws->nb_hour_manufacture * ($qty_needed / $n->qty_reference), $nws->nb_hour_prepare, $nws->nb_hour_manufacture * ($qty_needed / $n->qty_reference), $nws->rang, $nws->note_private, $nws->nb_days_before_beginning, $nws->nb_days_before_reapro);

                                    $this->TAssetWorkstationOF[$k]->ws = $nws->workstation;
                                }
                            }
                        }
                    }
                }
            }
            else {
                $sql = "SELECT fk_workstation as fk_asset_workstation, nb_hour";
                $sql .= " FROM ".MAIN_DB_PREFIX."workstation_product";
                $sql .= " WHERE fk_product = ".$fk_product;
                $PDOdb->Execute($sql);

                while($res = $PDOdb->Get_line()) {

                    $k = $this->addofworkstation($PDOdb, $nws->fk_workstation, $res->nb_hour);

                    $this->TAssetWorkstationOF[$k]->ws = $ws;
                }
            }
        }
    }

    function launchOF(&$PDOdb)
    {
        global $conf;

        $qtyIsValid = $this->checkQtyAsset($PDOdb, $conf);

        if ($qtyIsValid)
        {
            $this->status = 'OPEN';
            $this->setEquipement($PDOdb);
            $this->save($PDOdb);

            return true;
        }

		$this->error = 'ofAllQtyIsNotEnough';
        return false;
    }

	//Finalise un OF => incrémention/décrémentation du stock
	function closeOF(&$PDOdb)
	{
	    global $langs, $conf, $db, $user;

	    dol_include_once('/projet/class/task.class.php');
		dol_include_once('/product/class/product.class.php');

		$TIDOFToValidate = array($this->rowid);
		if(getDolGlobalInt('ASSET_CHILD_OF_STATUS_FOLLOW_PARENT_STATUS')) $this->getListeOFEnfants($PDOdb, $TIDOFToValidate, $this->rowid);
		krsort($TIDOFToValidate);

		foreach ($TIDOFToValidate as $id_of)
		{
			$of = new TAssetOF;
			$of->load($PDOdb, $id_of);
			$of->date_end = time();

			if(getDolGlobalInt('OF_FORCE_SET_FOURNITURE_COST_PMP_ON_CLOSE')){
				$of->set_fourniture_cost(true);
			}

			// On passe pas un of en prod s'il l'est déjà ou s'il n'est pas au statut validé
			if($of->rowid <= 0 || $of->status != 'OPEN') continue;

			foreach($of->TAssetOFLine as &$AssetOFLine)
			{
				if($AssetOFLine->type == 'NEEDED') {

					$qty_needed = !empty($AssetOFLine->qty_needed) ? $AssetOFLine->qty_needed : $AssetOFLine->qty;
					if($AssetOFLine->qty_used == 0) $AssetOFLine->qty_used = $qty_needed;

				}
				else if($AssetOFLine->type == 'TO_MAKE')
				{
					if($AssetOFLine->qty_used == 0) $AssetOFLine->qty_used = $AssetOFLine->qty;
				}

			}

			$of->set_current_cost_for_to_make(true);

			$of->status = 'CLOSE';

		    if (!getDolGlobalInt('OF_ALLOW_FINISH_OF_WITH_UNRECEIVE_ORDER') && !$of->checkCommandeFournisseur($PDOdb))
	        {
                setEventMessage($langs->trans('OFAssetCmdFournNotFinish'), 'errors');
                return false;
	        }

	     	foreach($of->TAssetOFLine as &$AssetOFLine)
			{
				if($AssetOFLine->type == 'NEEDED') {
					$AssetOFLine->destockQtyUsedAsset($PDOdb);
				}
			}

			foreach($of->TAssetOFLine as &$AssetOFLine)
			{
				if($AssetOFLine->type == 'TO_MAKE')
				{
					$AssetOFLine->stockQtyToMakeAsset($PDOdb, $of);
				}

			}

			foreach($of->TAssetWorkstationOF as &$wsof) {

				if($wsof->fk_project_task > 0) {

					$t=new Task($db);
					$t->fetch($wsof->fk_project_task);
					if($t->progress<100) {
						$t->progress = 100;
						$t->update($user);
					}

				}

				if($wsof->nb_hour_real == 0 && !getDolGlobalInt('OF_REAL_HOUR_CAN_BE_EMPTY')) {
					$wsof->nb_hour_real = $wsof->nb_hour;
				}

			}

			$of->save($PDOdb);

		}

        return true;
	}


	/*
	 * Permet le lancer l'OF pour la production et de faire le destockage des NEEDED
	 */
	function openOF(&$PDOdb)
	{
		global $db, $user, $conf, $langs;

		include_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
		dol_include_once("fourn/class/fournisseur.product.class.php");
		dol_include_once("fourn/class/fournisseur.commande.class.php");

		$TIDOFToValidate = array($this->rowid);

		if(getDolGlobalInt('ASSET_CHILD_OF_STATUS_FOLLOW_PARENT_STATUS')) $this->getListeOFEnfants($PDOdb, $TIDOFToValidate, $this->rowid);
		krsort($TIDOFToValidate);

		foreach ($TIDOFToValidate as $id_of) {

			$of = new TAssetOF;
			$of->load($PDOdb, $id_of);
			$of->date_start = time();
			// On passe pas un of en prod s'il l'est déjà ou s'il n'est pas au statut validé
			if($of->rowid <= 0 || $of->status != 'VALID') continue;

	        if($of->launchOF($PDOdb))
	        {
				if (getDolGlobalInt('OF_USE_DESTOCKAGE_PARTIEL')) {


					foreach($of->TAssetOFLine as &$AssetOFLine)
					{
						if($AssetOFLine->type == 'TO_MAKE')
						{
							 $AssetOFLine->stockQtyToMakeAsset($PDOdb, $of);
						}
						else
						{
							$AssetOFLine->destockQtyUsedAsset($PDOdb);
						}
					}

				}
				else {
					foreach($of->TAssetOFLine as &$AssetOFLine)
					{

						if($AssetOFLine->type=='NEEDED') {
							if(getDolGlobalInt('OF_IF_NEEDED_QTY_EMPTY_ON_LAUNCH_PUSH_NEEDED')) {
	                                                        $AssetOFLine->qty = empty($AssetOFLine->qty) ? $AssetOFLine->qty_needed : $AssetOFLine->qty;
                                                        	$AssetOFLine->qty_used = empty($AssetOFLine->qty_used) ? $AssetOFLine->qty : $AssetOFLine->qty_used;
                                                	}
							$AssetOFLine->destockQtyUsedAsset($PDOdb);
						}
					}
				}
	        }

			if (!empty($of->error)) $this->error = $langs->trans($of->error, $of->numero);
			if (!empty($of->errors)) $this->errors = $of->errors;
		}
	}


	function destockOrStockPartialQty(&$PDOdb, &$of)
	{
		global $conf;

		if ($of->status == 'OPEN' || $of->status == 'CLOSE')
		{
			foreach($of->TAssetOFLine as &$AssetOFLine)
	        {
	            if ($AssetOFLine->type == 'NEEDED') $AssetOFLine->destockQtyUsedAsset($PDOdb);
				else $AssetOFLine->stockQtyToMakeAsset($PDOdb, $of, 1);
			}

			return true;
		}

		return false;
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

	private function addCommandeFourn(&$PDOdb,$ofLigne, $resultatSQL) {

		global $db, $user;
		dol_include_once("fourn/class/fournisseur.commande.class.php");

		// On cherche s'il existe une commande pour ce fournisseur
		$sql = "SELECT rowid";
		$sql.= " FROM ".MAIN_DB_PREFIX."commande_fournisseur";
		$sql.= " WHERE fk_soc = ".$resultatSQL->fk_soc;
		$sql.= " AND fk_statut = 0"; //uniquement brouillon
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
			$com->addline($desc, $resultatSQL->price/$resultatSQL->quantity, $ofLigne->qty, $resultatSQL->tva_tx, 0, 0, $resultatSQL->fk_product, $resultatSQL->rowid);

		}

		//Création association element_element entre la commande fournisseur et l'OF
		$this->addElementElement($PDOdb,$com,$ofLigne);
	}

	function delete(&$PDOdb)
	{
		global $user,$langs,$conf,$db;

		// Appel des triggers
		include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
		$interface = new Interfaces($db);
		$result = $interface->run_triggers('ASSET_OF_DELETE',$this,$user,$langs,$conf);
		if ($result < 0)
		{
			$this->errors[] = $interface->errors;
		}

		parent::delete($PDOdb);

		$this->delElementElement($PDOdb);
	}

	function addElementElement(&$PDOdb,&$commandeFourn,&$ofLigne){

		$TIdCommandeFourn = $this->getElementElement($PDOdb);

		if(!in_array($commandeFourn->id, $TIdCommandeFourn)){
			$sql = "REPLACE INTO ".MAIN_DB_PREFIX."element_element (fk_source,fk_target,sourcetype,targettype)
					VALUES (".$ofLigne->fk_assetOf.",".$commandeFourn->id.",'ordre_fabrication','order_supplier')";

			$PDOdb->Execute($sql);
		}

	}

	function delElementElement(&$PDOdb){

		$PDOdb->Execute("DELETE FROM ".MAIN_DB_PREFIX."element_element
						 WHERE sourcetype = 'ordre_fabrication'
						 	AND targettype = 'order_supplier'
						 	AND fk_source = ".$this->getId());
	}

	function getElementElement(&$PDOdb){

		$TIdCommandeFourn = array();

		$sql = "SELECT fk_target
				FROM ".MAIN_DB_PREFIX."element_element
				WHERE fk_source = ".$this->getId()."
					AND sourcetype = 'ordre_fabrication'
					AND targettype = 'order_supplier'";

		$PDOdb->Execute($sql);

		while($PDOdb->Get_line()){
			$TIdCommandeFourn[] = $PDOdb->Get_field('fk_target');
		}

		return $TIdCommandeFourn;

	}

	function createOfAndCommandesFourn(&$PDOdb) {
		global $db, $user, $conf;

		dol_include_once("fourn/class/fournisseur.commande.class.php");

		$TabOF = array();
		$TabOF[] = $this->rowid;
		$this->getListeOFEnfants($PDOdb, $TabOF);
		krsort($TabOF);

		// Boucle pour chaque OF de l'arbre
		foreach($TabOF as $idOf){

			// On charge l'OF
			$assetOF = new TAssetOF;
			$assetOF->load($PDOdb, $idOf);

			// Boucle pour chaque produit de l'OF
			foreach($assetOF->TAssetOFLine as $ofLigne) {
				//pre($ofLigne,true);
				// On cherche le produit "TO_MAKE"
				if($ofLigne->type == "TO_MAKE") {

					//pre($ofLigne,true); exit;

					if($ofLigne->fk_product_fournisseur_price > 0) { // Fournisseur externe

						// On récupère la ligne prix fournisseur correspondante
						$sql = "SELECT rowid, fk_soc, fk_product, price, quantity, ref_fourn, tva_tx";
						if (isModEnabled('assetatm')) $sql.= ', compose_fourni';
						$sql.= " FROM ".MAIN_DB_PREFIX."product_fournisseur_price";
						$sql.= " WHERE rowid = ".$ofLigne->fk_product_fournisseur_price;
						$resql = $db->query($sql);

						$res = $db->fetch_object($resql);

						// Si fabrication interne
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

								$this->addCommandeFourn($PDOdb,$ofLigne, $res);

							}
							else { // Suffisemment de stock, donc destockage :
								$assetOF->openOF($PDOdb);
							}
						}
						elseif(!$res->compose_fourni) { //Commande Fournisseur

							$this->addCommandeFourn($PDOdb,$ofLigne, $res);

							// On récupère les OF enfants pour les supprimer
							$TabIdEnfantsDirects = $assetOF->getEnfantsDirects();

							foreach($TabIdEnfantsDirects as $idOF) {

								$assetOF->removeChild("TAssetOF", $idOF);
							}

							//Suppression des lignes NEEDED puisque inutiles
							$assetOF->delLineNeeded($PDOdb);
							$assetOF->unsetChildDeleted = true;

							$assetOF->save($PDOdb);

							// On casse la boucle
							break;

						}

					}
					else { // Fournisseur interne (Bourguignon) [PH - 14/04/15 - FIXME c'est pas que pour bourguignon ?]

						if($ofLigne->fk_product_fournisseur_price == -1) { // Sortie de stock, kill OF enfants

							$TabIdEnfantsDirects = $assetOF->getEnfantsDirects();

							foreach($TabIdEnfantsDirects as $idOF) {

								$assetOF->removeChild("TAssetOF", $idOF);
							}

							$assetOF->save($PDOdb);

							// On casse la boucle
							break;

						}
						elseif($ofLigne->fk_product_fournisseur_price == -2){ // Fabrication interne
							//[PH] FIXME - pourquoi on destock maintenant ? On valide tt juste l'OF
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
								//$assetOF->openOF($PDOdb);
								$assetOF->status = 'VALID';
							}

						}

					}

				}

			}

		}

	}

	function delLineNeeded(&$PDOdb){

		foreach($this->TAssetOFLine as $k=>$ofLigne){

			if($ofLigne->type == "NEEDED"){
				$this->delLine($PDOdb, $k);
			}
		}

	}

	static function ordre($ordre='ASAP'){


		return TAssetOF::$TOrdre[$ordre];
	}


	function getListeOFEnfants(&$PDOdb, &$Tid, $id_parent=null, $recursive = true) {

		if(is_null($id_parent))$id_parent = $this->getId();

		$sql = "SELECT rowid";
		$sql.= " FROM ".MAIN_DB_PREFIX."assetOf";
		$sql.= " WHERE fk_assetOf_parent = ".$id_parent;
		$sql.= " ORDER BY rowid";

		$Tab = $PDOdb->ExecuteAsArray($sql);
		foreach($Tab as $row) {
			$Tid[] = $row->rowid;
			if ($recursive) $this->getListeOFEnfants($PDOdb, $Tid, $row->rowid);
		}

	}

	public function getListeOfParents(&$PDOdb, $type = 'id', $loadChild = false, $recursive = true)
    {
        $TRes = array();

        if (!empty($this->fk_assetOf_parent))
        {
            $of = new TAssetOF;
            $of->load($PDOdb, $this->fk_assetOf_parent, $loadChild);
            if ($type == 'object') $TRes[] = $of;
            else $TRes[] = $of->getId();

            if ($recursive)
            {
                $result = $of->getListeOfParents($PDOdb, $recursive);
                $TRes = array_merge($TRes, $result);
            }
        }


        return $TRes;
    }

	public function addAssetLink(&$asset, $id_line) {


		foreach($this->TAssetOFLine as $k=>&$ofLigne){

			if($ofLigne->getId() == $id_line){
				$ofLigne->addAssetLink($asset);

				break;
			}
		}


	}

	function getOFEnfantWithProductToMake(&$PDOdb, &$res, $fk_product, $level=0, $recursive = true)
	{
		global $db;

		$tab = array();

		$sql = "SELECT a.rowid, al.rowid as fk_line";
		$sql.= " FROM ".MAIN_DB_PREFIX."assetOf a";
		$sql.= " INNER JOIN ".MAIN_DB_PREFIX."assetOf_line al ON (a.rowid = al.fk_assetOf AND al.type = 'TO_MAKE' AND al.fk_product = ".(int) $fk_product.")";
		$sql.= " WHERE fk_assetOf_parent = ".$this->getId();

		$tab = $PDOdb->ExecuteAsArray($sql);

		foreach ($tab as $val)
		{
			if ($recursive)
			{
				$TAssetOF = new TAssetOF;
				$TAssetOF->load($PDOdb, $val->rowid);
				foreach ($TAssetOF->TAssetOFLine as $line)
				{
					$TAssetOF->getOFEnfantWithProductToMake($PDOdb, $res, $line->fk_product, $level+1);
				}
			}

			$res[] = array('id_assetOf' => $val->rowid, 'level' => $level, 'id_assetOfLine'=>$val->fk_line);
		}

	}

	public function updateToMakeLineQty(&$PDOdb, $idLine,$qty_new, $coef = 0) {

		$res = false;

		$nb_to_make = 0;


//		return $this->TAssetOFLine;
		if(!empty($this->TAssetOFLine) && empty($coef)) {

			foreach ($this->TAssetOFLine as &$line) {
				if($line->type === 'TO_MAKE' && $idLine === $line->getId() && $line->qty>0) {
					$coef = $qty_new / $line->qty;

					$res = true;

					$nb_to_make++;

				}
			}
		}
		else if(!empty($coef)) {
			$nb_to_make = 1;
			$res = true;
		}
	//var_dump($coef);
		if($res && $nb_to_make == 1) { // On applique le coef que s'il y a 1 seul produit à fabriquer

			if(!empty($this->TAssetOFLine)) {

				foreach ($this->TAssetOFLine as &$line) {
			//	var_dump('$line', $line->qty);
					$line->qty*=$coef;
					$line->qty_needed*=$coef;
			//	var_dump('$line>', $line->qty);

					$line->saveQty($PDOdb);

					$TOF=array();
					$this->getOFEnfantWithProductToMake($PDOdb, $TOF, $line->fk_product,0, false);
					if(!empty($TOF)) {

						foreach($TOF as &$data) {

							$of = new TAssetOF;
							if($of->load($PDOdb, $data['id_assetOf'])) {
		//						var_dump('OFCHILD', $of->getId());
								if(!$of->updateToMakeLineQty($PDOdb, 0, 0, $coef)) $res = false;

							}

						}

					}

				}

			}

			if(!empty($this->TAssetWorkstationOF)) {

				foreach ($this->TAssetWorkstationOF as &$ws) {

					$ws->nb_hour*=$coef;
					$ws->nb_hour_prepare*=$coef;

					$ws->save($PDOdb);
				}

			}

		}

		return $res;

	}
	public function updateUsedNonCompliantLineQty(&$PDOdb, $idLine,$qty_used, $qty_non_compliant, $coef = 0, $coef_compliant = 0, $coef_non_compliant = 0) {

		$res = false;
        if(empty($qty_non_compliant))$qty_non_compliant = 0;
		$nb_to_make = 0;

		if(!empty($this->TAssetOFLine) && empty($coef) && (!empty($qty_non_compliant) || !empty($qty_used))) {


			foreach ($this->TAssetOFLine as &$line) {


				if($line->type === 'TO_MAKE' && $idLine === $line->getId() && $line->qty>0) {


					$coef = ($qty_used + $qty_non_compliant) / $line->qty;
					$coef_non_compliant =  $qty_non_compliant / $line->qty;
					$coef_compliant = $qty_used / $line->qty;

					$res = true;

					$nb_to_make++;

				}
			}
		}
		else if(!empty($coef)) {
			$nb_to_make = 1;
            $child = true;
			$res = true;
		}
		if($res && $nb_to_make == 1) { // On applique le coef que s'il y a 1 seul produit à fabriquer

			if(!empty($this->TAssetOFLine)) {

				foreach ($this->TAssetOFLine as &$line) {
					if($line->type === 'NEEDED') {

						//	var_dump('$line', $line->qty);
						$line->qty_used = $line->qty_needed * $coef;

//						$line->qty_used *= $coef;

						$line->saveQty($PDOdb);

						$TOF = array();
						$this->getOFEnfantWithProductToMake($PDOdb, $TOF, $line->fk_product, 0, false);
						if (!empty($TOF)) {

							foreach ($TOF as &$data) {

								$of = new TAssetOF;
								if ($of->load($PDOdb, $data['id_assetOf'])) {
									//						var_dump('OFCHILD', $of->getId());
									if (!$of->updateUsedNonCompliantLineQty($PDOdb, 0, 0, 0, $coef, $coef_compliant, $coef_non_compliant)) $res = false;

								}

							}

						}
					} else if(!empty($line->qty)) {
                        if(!empty($child)) {
                            $line->qty_used = $line->qty * $coef_compliant;
                            $line->qty_non_compliant = $line->qty * $coef_non_compliant;
                        } else {
                            $line->qty_used = $qty_used;
                            $line->qty_non_compliant = $qty_non_compliant;
                        }
						$line->saveQty($PDOdb);
					}

				}

			}

			if(!empty($this->TAssetWorkstationOF)) {

				foreach ($this->TAssetWorkstationOF as &$ws) {

					$ws->nb_hour*=$coef;
					$ws->nb_hour_prepare*=$coef;

					$ws->save($PDOdb);
				}

			}

		}
		return $res;

	}

	function getLineProductToMake() {

		if(!empty($this->TAssetOFLine)) {
			foreach ($this->TAssetOFLine as &$line) {
				if($line->type === 'TO_MAKE') return $line;
			}
		}

		return 0;

	}

    function getLinesProductToMake() {
        $TLine = array();
        if(!empty($this->TAssetOFLine)) {
            foreach ($this->TAssetOFLine as &$line) {
                if($line->type === 'TO_MAKE') $TLine[]= $line;
            }
        }

        return $TLine;

    }

	/*
	 * Permet de supprimer le/les OF enfants
	 * return 0 si aucun OF
	 * return array id_assetOf si un ou +sieurs OF
	 */
	function deleteOFEnfant(&$PDOdb, $fk_product)
	{
		$res = $tab = array();
		$this->getOFEnfantWithProductToMake($PDOdb, $tab, $fk_product);

		if (count($tab) <= 0) return 0;

		foreach ($tab as $row)
		{
			if ($row['level'] == 0)
			{
				$TAssetOF = new TAssetOF;
				$TAssetOF->load($PDOdb, $row['id_assetOf']);
				$TAssetOF->delete($PDOdb);
			}
			$res[] = $row['id_assetOf'];
		}

		return $res;
	}

	function getLibStatus($to_translate=false) {
		return self::status($this->status,$to_translate);
	}

	static function status($status='DRAFT', $to_translate=false){
		global $langs;

		if (!$to_translate) return TAssetOF::$TStatus[$status];
		else return  $langs->trans(TAssetOF::$TStatus[$status]);
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

	function getLastId(&$PDOdb){
		$PDOdb->Execute('SELECT rowid FROM '.MAIN_DB_PREFIX.'assetOf ORDER BY rowid DESC LIMIT 1');
		$PDOdb->Get_line();

		return $PDOdb->Get_field('rowid');
	}

	/**
	 * Retourne un tableau contenant les identifaints des OF créés à partir de la commande dont le rowid est égal à $id_command
	 * @param int $id_command
	 * @return array $TID_OF_command
	 */
	static function getTID_OF_command($id_command) {

		global $db;
		$TID_OF_command = array();

		$sql = "SELECT rowid";
		$sql.= " FROM ".MAIN_DB_PREFIX."assetOf of";
		$sql.= " WHERE fk_commande = ".$id_command;
		$resql = $db->query($sql);

		while($res = $db->fetch_object($resql)) {
			$TID_OF_command[] = $res->rowid;
		}

		return $TID_OF_command;
	}

	function checkLotIsFill()
	{
		global $langs,$db;

		$fill = true;
		foreach ($this->TAssetOFLine as $OFLine)
		{
			if (empty($OFLine->lot_number))
			{
				$product = new Product($db);
				$product->fetch($OFLine->fk_product);
				$this->errors[] = $langs->trans('OFAssetLotEmpty', $product->label, $product->getNomUrl());
				$fill = false;
			}
		}

		return $fill;
	}

	function checkCommandeFournisseur(&$PDOdb)
	{
		global $db;

		$res = true;
		$Tid = $this->getElementElement($PDOdb);

		foreach ($Tid as $id)
		{
			$cmdf = new CommandeFournisseur($db);
			$cmdf->fetch($id);

			//4 = livraison partielle # 5 = livraison total #8 = facturé
			if (!in_array($cmdf->statut, array(4,5,8)))
			{
				$res = false;
				break;
			}
		}

		return $res;
	}

	/*
	 * Fonction qui vérifie si la quantité des équipement est suffisante pour lancer la production
	 * Alimente $this->errors
	 * return true if OK else false NULL
	 */
	function checkQtyAsset(&$PDOdb, &$conf)
	{
		global $db;

        if(!getDolGlobalInt('USE_LOT_IN_OF')) return true;

		$qtyIsValid = true;
		foreach($this->TAssetOFLine as $TAssetOFLine)
		{
			$qtyIsValid &= $TAssetOFLine->setAsset($PDOdb,$this, false);
			if (!empty($TAssetOFLine->error)) $this->errors[] = $TAssetOFLine->error;
		}

		return $qtyIsValid;
	}

	function getControlPDF(&$PDOdb) {

		return QualityControl::getControlPDF($this->id, $this->element);

	}

    /**
     * @param TPDOdb $PDOdb
     * @return bool|int|string
     */
	public function getMaxDateEndOnChildrenOf($PDOdb)
    {
        global $db;

        $date_start_search = false;

        // Récuperation de la date le plus éloigné dans le temps sur les OF enfants
        $sql = 'SELECT MAX(t.datee) as datee FROM '.MAIN_DB_PREFIX.'projet_task t';
        $sql.= ' INNER JOIN '.MAIN_DB_PREFIX.'projet_task_extrafields te ON (te.fk_object = t.rowid)';
        $sql.= ' INNER JOIN '.MAIN_DB_PREFIX.'asset_workstation_of awo ON (awo.fk_project_task = t.rowid AND awo.fk_asset_workstation = te.fk_workstation)';
        $sql.= ' INNER JOIN '.MAIN_DB_PREFIX.'assetOf o ON (o.rowid = awo.fk_assetOf)';
        $sql.= ' WHERE o.fk_assetOf_parent = '.$this->getId();
        $sql.= ' AND o.status NOT IN ("OPEN", "CLOSE")';

        $resql = $db->query($sql);
        if ($resql)
        {
            $arr = $db->fetch_array($resql);
            if ($arr['datee'] !== null)
            {
                $date_start_search = $db->jdate($arr['datee']);
            }
        }
        else
        {
            $this->error = $db->lasterror();
        }

        return $date_start_search;
    }


	/**
	 *  Create a document onto disk according to template module.
	 *
	 * 	@param	    string		$modele			Force model to use ('' to not force)
	 * 	@param		Translate	$outputlangs	Object langs to use for output
	 *  @param      int			$hidedetails    Hide details of lines
	 *  @param      int			$hidedesc       Hide description
	 *  @param      int			$hideref        Hide ref
	 *  @param   null|array  $moreparams     Array to provide more information
	 * 	@return     int         				0 if KO, 1 if OK
	 */
	public function generateDocument($modele, $outputlangs, $hidedetails = 0, $hidedesc = 0, $hideref = 0, $moreparams = null)
	{
		global $conf,$langs;

		$outputlangs->load("of@of");

		if (! dol_strlen($modele)) {

			$modele = 'templateOF.odt';

			if ($this->modelpdf) {
				$modele = $this->modelpdf;
			} elseif (getDolGlobalString('OF_ADDON_PDF')) {
				$modele = getDolGlobalString('OF_ADDON_PDF');
			}
		}

		$modelpath = "core/modules/of/doc/";

		return $this->commonGenerateDocument($modelpath, $modele, $outputlangs, $hidedetails, $hidedesc, $hideref, $moreparams);
	}


	/**
	 * copier de dolibarr car le TObject ne le gère pas
	 * Common function for all objects extending CommonObject for generating documents
	 *
	 * @param 	string 		$modelspath 	Relative folder where generators are placed
	 * @param 	string 		$modele 		Generator to use. Caller must set it to obj->modelpdf or GETPOST('modelpdf', 'none') for example.
	 * @param 	Translate 	$outputlangs 	Output language to use
	 * @param 	int 		$hidedetails 	1 to hide details. 0 by default
	 * @param 	int 		$hidedesc 		1 to hide product description. 0 by default
	 * @param 	int 		$hideref 		1 to hide product reference. 0 by default
	 * @param   null|array  $moreparams     Array to provide more information
	 * @return 	int 						>0 if OK, <0 if KO
	 * @see	addFileIntoDatabaseIndex()
	 */
	protected function commonGenerateDocument($modelspath, $modele, $outputlangs, $hidedetails, $hidedesc, $hideref, $moreparams = null)
	{
		global $conf, $langs, $user, $db;

		$srctemplatepath='';

		// Increase limit for PDF build
		$err=error_reporting();
		error_reporting(0);
		@set_time_limit(120);
		error_reporting($err);

		// If selected model is a filename template (then $modele="modelname" or "modelname:filename")
		$tmp=explode(':', $modele, 2);
		if (! empty($tmp[1]))
		{
			$modele=$tmp[0];
			$srctemplatepath=$tmp[1];
		}

		// Search template files
		$file=''; $classname=''; $filefound=0;
		$dirmodels=array('/');
		if (is_array($conf->modules_parts['models'])) $dirmodels=array_merge($dirmodels, $conf->modules_parts['models']);
		foreach($dirmodels as $reldir)
		{
			foreach(array('doc','pdf') as $prefix)
			{
				$file = $prefix."_".$modele.".modules.php";
				// On verifie l'emplacement du modele
				$file=dol_buildpath($reldir.$modelspath.$file, 0);
				if (file_exists($file))
				{
					$filefound=1;
					$classname=$prefix.'_'.$modele;
					break;
				}
			}
			if ($filefound) break;
		}

		// If generator was found
		if ($filefound)
		{
			global $db;  // Required to solve a conception default in commonstickergenerator.class.php making an include of code using $db

			require_once $file;

			$obj = new $classname($db);

			// If generator is ODT, we must have srctemplatepath defined, if not we set it.
			if ($obj->type == 'odt' && empty($srctemplatepath))
			{
				$varfortemplatedir=$obj->scandir;
				if ($varfortemplatedir && getDolGlobalString($varfortemplatedir))
				{
					$dirtoscan= getDolGlobalString($varfortemplatedir);

					$listoffiles=array();

					// Now we add first model found in directories scanned
					$listofdir=explode(',', $dirtoscan);
					foreach($listofdir as $key => $tmpdir)
					{
						$tmpdir=trim($tmpdir);
						$tmpdir=preg_replace('/DOL_DATA_ROOT/', DOL_DATA_ROOT, $tmpdir);
						if (! $tmpdir) { unset($listofdir[$key]); continue; }
						if (is_dir($tmpdir))
						{
							$tmpfiles=dol_dir_list($tmpdir, 'files', 0, '\.od(s|t)$', '', 'name', SORT_ASC, 0);
							if (count($tmpfiles)) $listoffiles=array_merge($listoffiles, $tmpfiles);
						}
					}

					if (count($listoffiles))
					{
						foreach($listoffiles as $record)
						{
							$srctemplatepath=$record['fullname'];
							break;
						}
					}
				}

				if (empty($srctemplatepath))
				{
					$this->error='ErrorGenerationAskedForOdtTemplateWithSrcFileNotDefined';
					return -1;
				}
			}

			if ($obj->type == 'odt' && ! empty($srctemplatepath))
			{
				if (! dol_is_file($srctemplatepath))
				{
					$this->error='ErrorGenerationAskedForOdtTemplateWithSrcFileNotFound';
					return -1;
				}
			}

			// We save charset_output to restore it because write_file can change it if needed for
			// output format that does not support UTF8.
			$sav_charset_output=$outputlangs->charset_output;

			$resultwritefile = $obj->write_file($this, $outputlangs, $srctemplatepath, $hidedetails, $hidedesc, $hideref, $moreparams);

			// After call of write_file $obj->result['fullpath'] is set with generated file. It will be used to update the ECM database index.

			if ($resultwritefile > 0)
			{
				$outputlangs->charset_output=$sav_charset_output;

				// We delete old preview
				require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
				dol_delete_preview($this);

				// Success in building document. We build meta file.
				dol_meta_create($this);

				return 1;
			}
			else
			{
				$outputlangs->charset_output=$sav_charset_output;
				dol_print_error($db, "Error generating document for ".__CLASS__.". Error: ".$obj->error, $obj->errors);
				return -1;
			}
		}
		else
		{
			$this->error=$langs->trans("Error")." ".$langs->trans("ErrorFileDoesNotExists", $file);
			dol_print_error('', $this->error);
			return -1;
		}
	}
}

class TAssetOFLine extends TObjetStd{
/*
 * Ligne d'Ordre de fabrication d'équipement
 * */

	function __construct() {
	    global $conf;

		$this->set_table(MAIN_DB_PREFIX.'assetOf_line');

    	$this->TChamps = array();
		$this->add_champs('entity,fk_assetOf,fk_product,fk_product_fournisseur_price,fk_entrepot,fk_nomenclature,nomenclature_valide,fk_commandedet',array('type'=>'integer','index'=>true));
		$this->add_champs('qty_needed,qty,qty_used,qty_stock,conditionnement,conditionnement_unit,pmp,qty_non_compliant',array('type'=>'float'));
		$this->add_champs('type,lot_number,measuring_units',array('type'=>'string'));
	    $this->add_champs('note_private',array('type'=>'text'));

		//clé étrangère
		parent::add_champs('fk_assetOf_line_parent',array('type'=>'integer','index'=>true));

		$this->TType=array('NEEDED','TO_MAKE');

		$this->errors = array();
		$this->TFournisseurPrice=array();
		$this->TWorkstation=array();
		$this->_init_vars();
	    $this->start();
		$this->setChild('TAssetOFLine','fk_assetOf_line_parent');

		$this->product = null;
		$this->entity = $conf->entity;
	}

	//$qty_to_re_stock est normalement tjr positif
	//TODO remove useless
	function reStockAsset(&$PDOdb, $qty_to_re_stock)
	{
		global $conf;

		if ($qty_to_re_stock == 0) return false;

		$TAsset = $this->getAssetLinked($PDOdb);
        foreach($TAsset as $asset)
        {
        	//Si la contenance max de mon équipement peut récupérer la qty restante
        	if (($asset->contenance_value - $asset->contenancereel_value) < $qty_to_re_stock){
				$qty_to_re_stock = 0;
			}

			$labelMvt = 'Suppression Ordre de Fabrication';
			if($this->type == 'TO_MAKE') $labelMvt = 'Suppression Ordre de Fabrication';

         	$asset->save($PDOdb,$user
	            ,$labelMvt.' n°'.$OF->numero.' - Equipement : '.$asset->serial_number
	            ,$qty_to_re_stock, false, $this->fk_product, false, $fk_entrepot);
        }
	}

	function stockProduct($qty_to_stock) {

		return $this->destockProduct(-$qty_to_stock) ;
	}

	function destockProduct($qty_to_destock) {
		global $conf,$langs;

		$sens = ($qty_to_destock>0) ? -1 : 1;
		$qty_to_destock_rest =  abs($qty_to_destock);
//TODO translate

		$labelMvt = $langs->trans('UseByOF', $this->of_numero);
		if($this->type == 'TO_MAKE') $sens == 1 ? $labelMvt = $langs->trans('CreateByOF', $this->of_numero) : $labelMvt = $langs->trans('DeletedByOF', $this->of_numero);

		if($this->type == 'TO_MAKE') $fk_entrepot = getDolGlobalInt('ASSET_MANUAL_WAREHOUSE') ? $this->fk_entrepot : getDolGlobalInt('ASSET_DEFAULT_WAREHOUSE_ID_TO_MAKE');
		else $fk_entrepot = getDolGlobalInt('ASSET_MANUAL_WAREHOUSE') ? $this->fk_entrepot : getDolGlobalInt('ASSET_DEFAULT_WAREHOUSE_ID_TO_MAKE');

		$price = 0;

		if($this->type=='TO_MAKE') {
			$PDOdb = new TPDOdb();
			$price = $this->current_cost_for_to_make;
			$this->pmp = $this->current_cost_for_to_make;

			$this->setPMP($PDOdb, $this->pmp);
			if($this->fk_assetOf_line_parent>0) {
				$line = new TAssetOFLine();
				if($line->load($PDOdb, $this->fk_assetOf_line_parent)) {
					$line->setPMP($PDOdb, $this->pmp); // passage par une fonction hors save à cause des intrications
				}
			}

		}

		TAssetOF::addStockMouvementDolibarr($this->fk_product, $sens * $qty_to_destock_rest, $labelMvt,$fk_entrepot, $price);

		$this->update_qty_stock($sens * $qty_to_destock_rest);

	}

	/*
	 * définit le PMP
	 *
	 */
	function setPMP(&$PDOdb, $pmp) {
		$this->pmp = (double) $pmp;

//		if($this->fk_product == 14714){	setEventMessage($this->id.','.$this->pmp,'warning');}
		$PDOdb->Execute("UPDATE ".$this->get_table()." SET pmp = ".$this->pmp." WHERE rowid = ".$this->getId());
	}

	function stockAsset(&$PDOdb, $qty_to_stock, $add_only_qty_to_contenancereel=false) {

		global $conf, $langs, $user;

        if($qty_to_stock==0) return false; // on attend une qty ! A noter que cela peut-être négatif en cas de sous conso il faut restocker un bout

        // TODO reste un souci de stockage d'un TO_MAKE sur OF Terminé (si j'en fabrique 50 puis qu'on modifie la quantité par 60 avec une contenance max de 50 par équipement, le surplus fini actuellement dans le 1er équipement et laisse finalement le 2nd vide)
        $mouvement = 'restockage';
		if ($qty_to_stock < 0) $mouvement = 'destockage'; // Fix un problème de restockage en cas de sous conso d'un NEEDED

        $sens = ($qty_to_stock>0) ? 1 : -1;
		$qty_to_stock_rest =  abs($qty_to_stock);

		if($this->type == 'TO_MAKE') $fk_entrepot = getDolGlobalInt('ASSET_MANUAL_WAREHOUSE') ? $this->fk_entrepot : getDolGlobalInt('ASSET_DEFAULT_WAREHOUSE_ID_TO_MAKE');
		else $fk_entrepot = getDolGlobalInt('ASSET_MANUAL_WAREHOUSE') ? $this->fk_entrepot : getDolGlobalInt('ASSET_DEFAULT_WAREHOUSE_ID_TO_MAKE');

		//echo $sens." x ".$qty_to_destock_rest.'<br>';

		$labelMvt = $langs->trans('UseByOF', $this->of_numero);
		if($this->type == 'TO_MAKE') $sens == 1 ? $labelMvt = $langs->trans('CreateByOF', $this->of_numero) : $labelMvt = $langs->trans('DeletedByOF', $this->of_numero);

		if(!getDolGlobalInt('USE_LOT_IN_OF') || empty($conf->{ ATM_ASSET_NAME }->enabled))
        {
        	$this->stockProduct($sens * $qty_to_stock_rest);
        }
		else
		{
			$TAsset = $this->getAssetLinked($PDOdb);

            if(empty($TAsset)) {
				$asset=new TAsset;

				//On a pas d'asset lié à la ligne, cependant si on a un lot il faut quand même destocker un équipement de ce lot
				if($this->lot_number){
					$assetOf = new TAssetOF;
					$assetOf->load($PDOdb, $this->fk_assetOf);
					$res = $this->setAsset($PDOdb, $assetOf);
					//var_dump($res);
					if(!$res) {
						setEventMessages( 'ERR.'.__METHOD__.' > setAsset ' .$this->lot_number, $assetOf->errors ,'errors');
					}
					else {
						$this->update_qty_stock($sens * $qty_to_stock_rest);
					}
				}
				else{ //Sinon effectivement on destocke juste le produit sans les équipements
					$this->stockProduct( $sens * $qty_to_stock_rest);
				}

            }
            else{

				$nb_asset = count($TAsset); $i=0;
                foreach($TAsset as $asset)
                {
					$qty_asset_to_stock=0;
					if($mouvement == 'destockage')  {
						if(!getDolGlobalInt('ASSET_NEGATIVE_DESTOCK') && $asset->contenancereel_value - $qty_to_stock_rest<0) {
							$qty_asset_to_stock=$asset->contenancereel_value;

							if($i+1 == $nb_asset) {
								setEventMessage($langs->trans('InssuficienteAssetContenanceToUsedInOF', $asset->serial_number),'errors');
							}
						}
						else if($i+1 == $nb_asset && getDolGlobalInt('ASSET_NEGATIVE_DESTOCK')) {
							$qty_asset_to_stock = $qty_to_stock_rest;
						}
						else {
							if($nb_asset-$i > 0) $qty_asset_to_stock = $qty_to_stock_rest/($nb_asset-$i);
							else $qty_asset_to_stock = $qty_to_stock_rest;
						}
					}
					else {

						if($qty_to_stock_rest>$asset->contenance_value - $asset->contenancereel_value) {
							$qty_asset_to_stock = $asset->contenance_value - $asset->contenancereel_value;
							if($i+1 == $nb_asset) {
								setEventMessage($langs->trans('InssuficienteAssetContenanceToAddFromOF', $asset->serial_number),'errors');
							}
						}
						else {
							$qty_asset_to_stock = $qty_to_stock_rest;
						}

					}

					//echo $sens." x ".$qty_asset_to_destock.'<br>';
					$this->update_qty_stock($sens * $qty_asset_to_stock);

					$asset->save($PDOdb,$user
							,$labelMvt.' n°'.$this->of_numero.' - '.$langs->trans('Asset').' : '.$asset->serial_number
							,$sens * $qty_asset_to_stock, false, $this->fk_product, false, $fk_entrepot, $add_only_qty_to_contenancereel);

					$qty_to_stock_rest-= $qty_asset_to_stock;

					$i++;

					if($qty_to_stock_rest<=0)break;


                }

            }

		}

        return $this->save($PDOdb);

	}

	/**
	 * @param 	$qty_to_destock		if < 0 = restockage, if > 0 = destockage
	 */
    function destockAsset(&$PDOdb, $qty_to_destock, $add_only_qty_to_contenancereel=false)
    {

		return $this->stockAsset($PDOdb, -$qty_to_destock, $add_only_qty_to_contenancereel);

    }

	// Met à jour la ##### de quantité stock, si tu comprends pas demande à PH
	function update_qty_stock($qty) {

		if($this->type=='TO_MAKE') $this->qty_stock += $qty; // Je destock la quantité prise unitairement dans l'équipement
		else $this->qty_stock -= $qty; // Je destock la quantité prise unitairement dans l'équipement

	}

	/*
	 * Donne le délai en jour avant réapprovisionnement
	 */
	function getNbDayForReapro() {
		global $db, $user, $conf;

		if(isModEnabled('supplierorderfromorder') && $this->type=='NEEDED') {

			$stock_needed = TAssetOF::getProductStock($this->fk_product,0,true, getDolGlobalInt('CREATE_CHILDREN_OF_ON_VIRTUAL_STOCK'));

			if($stock_needed > 0) return 0;

			if(dol_include_once('/supplierorderfromorder/class/sofo.class.php')){

				$qty = $this->qty_needed>0 ? $this->qty_needed : 1;
				$nb = TSOFO::getMinAvailability($this->fk_product, $qty,true);
//		var_dump($nb, $this->qty_needed);exit;
				return $nb;
			}

		}

		return 0;
	}

	//Affecte les équipements à la ligne de l'OF
	function setAsset(&$PDOdb,&$AssetOf, $forReal = false)
	{

		global $db, $user, $conf, $langs;

        if(!getDolGlobalInt('USE_LOT_IN_OF') || empty($this->lot_number)) return true;

        dol_include_once('/' . ATM_ASSET_NAME . '/class/asset.class.php');

		$completeSql = '';
		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX . ATM_ASSET_NAME;

		$is_cumulate = TAsset_type::getIsCumulate($PDOdb, $this->fk_product);
		$is_perishable = TAsset_type::getIsPerishable($PDOdb, $this->fk_product);
		$is_unit = TAsset_type::getIsUnit($PDOdb, $this->fk_product);

		//si on cherche à déstocker 5 * 0.10 Kg alors on ne cherche pas un équipement avec + de 5Kg en stock mais bien + de 0.50Kg
		list($qty,$qty_stock) = $this->convertQty();

		//echo $this->qty;exit;

		/** If type of equipment is cumulable then we destock 1 or more equipment until we have the necessary quantity */
		if ($is_cumulate || $is_unit) // si traitement unitaire c'est pareil
		{
			$sql.= ' WHERE status != "USED" ';

			if(!getDolGlobalInt('ASSET_NEGATIVE_DESTOCK') && !$is_unit) $sql.= ' AND contenancereel_value > 0 ';

			if ($is_perishable) $completeSql = ' AND DATE_FORMAT(dluo, "%Y-%m-%d") >= DATE_FORMAT(NOW(), "%Y-%m-%d") ORDER BY dluo ASC, date_cre ASC, contenancereel_value ASC';
			else $completeSql = ' ORDER BY date_cre ASC, contenancereel_value ASC';
		}
		else
		{
			$sql.= ' WHERE status != "USED" ';

			if(!getDolGlobalInt('ASSET_NEGATIVE_DESTOCK') && !$is_unit)$sql.= ' AND contenancereel_value >= '.($qty - $qty_sotck).' ';// - la quantité déjà utilisé

			if ($is_perishable) $completeSql = ' AND DATE_FORMAT(dluo, "%Y-%m-%d") >= DATE_FORMAT(NOW(), "%Y-%m-%d") ORDER BY dluo ASC, contenancereel_value ASC, date_cre ASC LIMIT 1';
			else $completeSql = ' ORDER BY contenancereel_value ASC, date_cre ASC LIMIT 1';
		}

		$sql.= ' AND fk_product = '.$this->fk_product;

		if (getDolGlobalInt('USE_LOT_IN_OF'))
		{
			if (getDolGlobalInt('OF_CONCAT_MULTIPLE_LOT') && $this->type == 'NEEDED')
			{
				$TAsset = $this->getAssetLinked($PDOdb);

				if (!empty($TAsset))
				{
					$tempTab = array();
					foreach ($TAsset as $asset)
					{
						$tempTab[] = $asset->lot_number;
					}

					$sql.= ' AND lot_number in ("'.implode('","', $tempTab) .'")';
				}

			}
			else $sql .= ' AND lot_number = "'.$this->lot_number.'"';
		}

		$sql.= $completeSql;

		//echo $sql.'<br>';

		$Tab = $PDOdb->ExecuteAsArray($sql);

        $no_error = true;

		if($this->type == 'NEEDED' && ($AssetOf->status == 'OPEN' || !$forReal )  ) // TODO remove condition status
		{
			if ($qty_stock == $qty) return true; //qty_stock = qté déjà utilisé et qty = qté de besoin, donc si egal alors pas besoin de chercher d'autre équipement

			$nbAssetFound = count($Tab);
			$mvmt_stock_already_done = $nbAssetFound > 0 ? true : false;
			$qty_needed = $qty - $qty_stock; // - la quantité déjà utilisé

            if ($nbAssetFound == 0 && !getDolGlobalInt('ASSET_NEGATIVE_DESTOCK')) {
                $AssetOf->errors[] = $langs->trans('ofQtyLotIsNotEnough', $this->lot_number, $this->getId(), $this->product->label);
                $no_error = false;
            }
            else
            {
            	//On fait un 1er tour pour vérifier la qté
        		$qtyIsEnough = $this->checkAddAssetLink($PDOdb, $Tab, $qty_needed, $forReal, false);

				if (!$qtyIsEnough && !getDolGlobalInt('ASSET_NEGATIVE_DESTOCK')) $AssetOf->errors[] = $langs->trans('ofQtyLotIsNotEnough', $this->lot_number, $this->getId(), $this->product->label);
				else $this->checkAddAssetLink($PDOdb, $Tab, $qty_needed, $forReal);

				$no_error = $qtyIsEnough;
            }

		}

        //TODO on créé un équipement si non trouver, voir pour réintégrer ce comportement sur paramétrage
		/*
		$this->fk_asset = $idAsset;
		$this->save($PDOdb, $conf);
*/
        if(!$no_error && !getDolGlobalInt('ASSET_NEGATIVE_DESTOCK')) return false;
        else return true;
	}

	/*
	 *  Converty les quantités en fonction du conditionnement produit
	 */
	function convertQty(){

		$conditionnement = $this->conditionnement;

		//TODO : mettre tous sur la même unité de mesure
		$qty_stock = $this->qty_stock;
		$qty = empty( $this->qty ) ?  $this->qty_needed :  $this->qty;

		return array($qty, $qty_stock);
	}

	/*
	 *
	 * Return true si la quantité d'équipement est suffisante
	 */
	function checkAddAssetLink(&$PDOdb, $Tab, $qty_needed, $forReal, $addLink=true)
	{
		global $conf;

		if(empty($conf->{ ATM_ASSET_NAME }->enabled)) return false;


		$break = false;

		foreach($Tab as $row)
	   	{
    	 	$asset=new TAsset;
         	$asset->load($PDOdb, $row->rowid);

			//Contient la liste des asset
			$TAsset = $this->getAssetLinked($PDOdb);

	         // Si j'ai assez de contenu dans mon équipement
	         if ($asset->contenancereel_value - $qty_needed >= 0)
	         {
	             $qty_to_destock = $qty_needed;
	             $break = true;
	         }
	         else
	         {
	             // sinon si cumulable
	             $qty_to_destock = $asset->contenancereel_value;
	             $qty_needed -= $asset->contenancereel_value;
	         }

		     if($forReal && $addLink) {
		         $this->addAssetLink($asset);

		         if (getDolGlobalInt('ASSET_USE_DEFAULT_WAREHOUSE')) $fk_entrepot = getDolGlobalInt('ASSET_DEFAULT_WAREHOUSE_ID_NEEDED');
		         else $fk_entrepot = $asset->fk_entrepot;

				 //il faut aussi destocker la contenance
				 //$asset->contenancereel_value -= $qty_to_destock;

		         $asset->status = 'indisponible';
		         //On affiche aussi l'ID de l'équipement dans la description pcq le serial_number peut être vide
		         //$asset->save($PDOdb,$user,'Utilisation via Ordre de Fabrication n°'.$AssetOf->numero.' - Equipement : '.$asset->getId().' - '.$asset->serial_number, -$qty_to_destock, false, 0, false, $fk_entrepot);
		         $asset->save($PDOdb,$user);
		     }

	         if ($break) break;
		}

		return $break;
	}


    function getAssetLinkedLinks(&$PDOdb, $r='<br />', $sep='<br />') {
        global $conf;
        if(empty($conf->{ ATM_ASSET_NAME }->enabled)) return '';

        $TAsset = $this->getAssetLinked($PDOdb);

        foreach($TAsset as &$asset) {

            $r.=$asset->getNomUrl(true,true).$sep;
        }

        return $r;
    }
    function getAssetLinked(&$PDOdb, $only_ids=false) {
        global $conf;

        if(!empty($conf->{ ATM_ASSET_NAME }->enabled)) {
	        $Tab = $TId = array();

	        $TId = TAsset::get_element_element($this->getId(), 'TAssetOFLine', 'TAsset');
	        if ($only_ids) return $TId;
			//pre($TId,true);

	        foreach($TId as $id) {
	            $asset = new TAsset;
	            if($asset->load($PDOdb, $id)) {
	                $Tab[] = $asset;
	            }
	        }

	        if (getDolGlobalInt('OF_REORDER_LINKED_ASSET_BY_DLU'))
			{
				usort($Tab, array($this, 'reoderLinkedAssetsByDlu'));
			}

			return $Tab;
		}

        return array();
    }

	function reoderLinkedAssetsByDlu(&$a,&$b) {

		if($a->dluo < $b->dluo) {
			return -1;
		}
		else if($a->dluo > $b->dluo) {
			return 1;
		}
		else return 0;

	}

    function addAssetLink(&$asset)
    {
    	global $conf;
    	if(!empty($conf->{ ATM_ASSET_NAME }->enabled)) {
        	TAsset::set_element_element($this->getId(), 'TAssetOFLine', $asset->getId(), 'TAsset');
        }
    }

	function initConditionnement(&$PDOdb)
	{
		global $conf;

		if(!empty($conf->{ ATM_ASSET_NAME }->enabled)) {
			dol_include_once('/' . ATM_ASSET_NAME . '/class/asset.class.php');
	        	$assetType = new TAsset_type;
	        	$assetType->load_by_fk_product($PDOdb, $this->fk_product);
		        $this->conditionnement = $assetType->getDefaultContenance($this->fk_product);
		        $this->conditionnement_unit = $assetType->contenance_units;
		        $this->measuring_units = $assetType->measuring_units;

		}
		else{
			$this->measuring_units = 'unit';
			$this->gestion_stock = 'UNIT';
			$this->conditionnement = 1;
		}
	}

    function libUnite()
    {
        dol_include_once('/core/lib/product.lib.php');
        return measuring_units_string($this->conditionnement_unit, $this->measuring_units);
    }

	//Utilise l'équipement affecté à la ligne de l'OF
	function makeAsset(&$PDOdb, &$AssetOf, $fk_product, $qty_to_make, $idAsset = 0, $lot_number = '' ,$fk_entrepot = 0)
	{
	   	global $user,$conf;

	   	//INFO : si on utilise pas les lots on a pas besoin de créer des équipements => on gère uniquement des mvt de stock
	   	if(empty($conf->{ ATM_ASSET_NAME }->enabled) || !getDolGlobalInt('USE_LOT_IN_OF')) return true;

	   	if(!dol_include_once('/' . ATM_ASSET_NAME . '/class/asset.class.php')) return true;

        $assetType = new TAsset_type;
        if($assetType->load_by_fk_product($PDOdb, $fk_product))
        {
        	/* On fabrique de la contenance et non pas une quantité de produit au sens strict
        	 * Si on fabrique un produit au sens strict, le type d'équipement de ce produit aura une contenance max à 1, donc ça marche pareil
        	 * En revanche, on a un sac de sable à moitier vide, s'il est réutilisable on va le remplir puis en créer si besoin
        	 *
			 * A penser :	   [1] [2] [3]
			 * Périsable :		1	0	1
			 * Réutilisable :	0	1	1
			 *
			 * [1] => on crée de nouveaux équipements
			 * [2] => on réutilise
			 * [3] => si la qté de l'équipement courant = 0 alors on réutilise
			 *
			 * Dans le process de la validation de la production, les TO_MAKE doivent pré-séléctionner les équipements possibles à la réutilisation
			 * Quand on "Termine" l'OF il faudra prendre la liste des équipements liés pour les remplir puis en créer si nécessaire
			 */

			// évite de créer des assets en double, et permet de créer éventuellement un surplus d'équipement si nécessaire sur un update de la quantité produite d'un TO_MAKE
			$TAssetLinked = $this->getAssetLinked($PDOdb);
			$qty_stockage_dispo = 0;
			foreach ($TAssetLinked as &$assetLinked)
			{
				$qty_stockage_dispo += $assetLinked->contenance_value - $assetLinked->contenancereel_value;
			}

            $contenance_max = $assetType->contenance_value;
            $nb_asset_to_create = ceil(($qty_to_make - $qty_stockage_dispo) / $contenance_max);

			//Qté restante a fabriquer
            $qty_to_make_rest = $qty_to_make;
            for($i=0; $i<$nb_asset_to_create; $i++)
            {
                $TAsset = new TAsset;
                $TAsset->fk_soc = $AssetOf->fk_soc;
                $TAsset->fk_societe_localisation = getDolGlobalString('ASSET_DEFAULT_LOCATION');
                $TAsset->fk_product = $fk_product;

		$TAsset->fk_asset_type = $assetType->getId();
                $TAsset->load_asset_type($PDOdb);

		if (empty($TAsset->dluo)){
                	if(getDolGlobalInt('ASSET_DEFAULT_DLUO')) $TAsset->dluo = strtotime(date('Y-m-d').' +' . getDolGlobalString('ASSET_DEFAULT_DLUO').' days');
                	else $TAsset->dluo = strtotime(date('Y-m-d'));
		}

				//pre($assetType,true);exit;

                if($qty_to_make_rest>$TAsset->contenance_value) {
                    $qty_to_make_asset = $TAsset->contenance_value;
                }
                else {
                    $qty_to_make_asset = $qty_to_make_rest;
                }

                $qty_to_make_rest-=$qty_to_make_asset;

//                $TAsset->contenancereel_value = $qty_to_make_asset;
				// Je force la contenance à 0, car l'appel à la méthode load_asset_type() un peu plus haut init la valeur, de plus cet attribut sera update par stockAsset()
				$TAsset->contenancereel_value = 0;
                $TAsset->lot_number = $lot_number;

                if (getDolGlobalInt('ASSET_USE_DEFAULT_WAREHOUSE') && empty($fk_entrepot)) $fk_entrepot = getDolGlobalInt('ASSET_DEFAULT_WAREHOUSE_ID_TO_MAKE');

				if(!$fk_entrepot) exit('ASSET_USE_DEFAULT_WAREHOUSE non définis dans la configuration du module. ERR.L.2244');

                $TAsset->fk_entrepot = $fk_entrepot;

                $TAsset->save($PDOdb,'','',0,false,0,true,$fk_entrepot); //Save une première fois pour avoir le serial_number + 2ème save pour mvt de stock

                $this->addAssetLink($TAsset);

            }

            return true;
        }
        else{
            return false;
        }

	}

	function getWorkstationsPDF(&$db) //TODO AA c'est quoi ce nom de fonction de merde ?!
	{

		$r='';

        foreach($this->TWorkstation as &$w) {
            if(!empty($r)) $r.=', ';
            $r.=$w->name;
        }

		return $r;
	}

	function load(&$PDOdb, $id, $loadChild = true) {
		$res = parent::load($PDOdb, $id);
		$this->load_workstations($PDOdb);

		$this->loadFournisseurPrice($PDOdb);

		$this->load_product();

		return $res;
	}

	function load_product($force_pmp = false) {
		global $db,$conf;

		if($this->fk_product>0) {
			dol_include_once('/product/class/product.class.php');

			$this->product = new Product($db);
           	$this->product->fetch($this->fk_product);

           	if($force_pmp || empty($this->pmp)) {
				if(isModEnabled('nomenclature')) {
					dol_include_once('/nomenclature/class/nomenclature.class.php');
					$nd = new TNomenclatureDet();
					$nd->fk_product = $this->fk_product;
					$PDOdb=new TPDOdb();
					if(getDolGlobalString('NOMENCLATURE_COST_TYPE') == 'pmp'){
						//sélectionne le pmp si renseigné
						$this->pmp = $nd->getPMPPrice();
						if(empty($this->pmp) || $this->pmp == 0) $this->pmp = $nd->getSupplierPrice($PDOdb, $this->qty>0 ? $this->qty : 1, true,true,false,true);
					} elseif(getDolGlobalString('NOMENCLATURE_COST_TYPE') == 'costprice') {
						// sélectionne le prix de revient sur la fiche produit si renseigné, sinon sélectionne le PMP si renseigné, sinon sélectionne le meilleur prix fournisseur
						$this->pmp = $nd->getCostPrice();
						if(empty($this->pmp)) $this->pmp = $nd->getPMPPrice();
						if(empty($this->pmp) || $this->pmp == 0) $this->pmp = $nd->getSupplierPrice($PDOdb, $this->qty>0 ? $this->qty : 1, true,true,false,true);
					} else {
						$this->pmp = $nd->getSupplierPrice($PDOdb, $this->qty>0 ? $this->qty : 1, true,true,false,true);
					}
				}
				else {
					$this->product = new Product($db);
					$this->product->fetch($this->fk_product);

					$pmp = (double) $this->product->pmp; //TODO set parameters to select prefered rank
					if(empty($pmp) && !empty($this->product->cost_price)) {
						$pmp = (double) $this->product->cost_price;
					}
					if(empty($pmp)) {
						dol_include_once('/fourn/class/fournisseur.product.class.php');
						$fournProd = new ProductFournisseur($db);
						$fournProd->find_min_price_product_fournisseur($this->fk_product, $this->qty>0 ? $this->qty : 1);
						$pmp = (double) $fournProd->fourn_unitprice;
					}

					$this->pmp = $pmp;

				}
			}
		}

	}
	function delete(&$PDOdb)
	{
		global $user,$langs,$conf,$db;

		// Appel des triggers
		include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
		$interface = new Interfaces($db);
		$result = $interface->run_triggers('ASSET_LINE_OF_DELETE',$this,$user,$langs,$conf);
		if ($result < 0)
		{
			$this->errors[] = $interface->errors;
		}

		//La fonction destockAsset en utilisant les lot ne permet pas de remettre la contenancereel_value dans chaque asset utilisés
		($this->type == 'NEEDED') ? $this->stockAsset($PDOdb, $this->qty_stock) : $this->destockAsset($PDOdb, $this->qty_stock);

	    // TODO dbdelete()
        $sql = 'DELETE FROM '.MAIN_DB_PREFIX.'element_element WHERE fk_source = '.(int) $this->rowid.' AND sourcetype = "tassetofline" AND targettype = "tassetworkstation"';
        $PDOdb->Execute($sql);
        $sql = 'DELETE FROM '.MAIN_DB_PREFIX.'element_element WHERE fk_source = '.(int) $this->rowid.' AND sourcetype = "TAssetOFLine" AND targettype = "TAsset"';
        $PDOdb->Execute($sql);

		/* Evite le restockage en double
         * Si mon OF a 1 TO_MAKE qui a besoin de 2 NEEDED
         * le fait de delete l'OF on va donc delete chacun de ses enfants TAssetOFLine
         * seulement un objet TAssetOFLine de type TO_MAKE a aussi des enfants TAssetOFLine
         */
 		$this->TAssetOFLine = array();  //le delete de l'enfant est déjà fait par la liaison parent/enfant OF

		parent::delete($PDOdb);
	}

	function set_workstations(&$PDOdb, $TWorkstations)
	{

		if (empty($TWorkstations)) return false;

		if(empty($this->id)) {
			$this->id = $this->save($PDOdb);
		}

		$sql = 'DELETE FROM '.MAIN_DB_PREFIX.'element_element WHERE fk_source = '.(int) $this->rowid.' AND sourcetype = "tassetofline" AND targettype = "tassetworkstation"';
		$PDOdb->Execute($sql);

        //TODO fonction add_element()
		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'element_element (';
		$sql.= 'fk_source, sourcetype, fk_target, targettype';
		$sql.= ') VALUES ';

		$save = false;
		foreach ($TWorkstations as $id_workstation)
		{
			if ($id_workstation <= 0) continue;

			$this->TWorkstation[] = $id_workstation;
			$save = true;

			$sql.= '(';
			$sql.= (int) $this->id.',';
			$sql.= $PDOdb->quote('tassetofline').',';
			$sql.= (int) $id_workstation.',';
			$sql.= $PDOdb->quote('tassetworkstation');
			$sql.= '),';
		}

		if ($save)
		{
			$sql = rtrim($sql, ',');

			$PDOdb->Execute($sql);

		}
	}

	function load_workstations(&$PDOdb)
	{

        $this->TWorkstation=array();

		$sql= 'SELECT fk_target FROM '.MAIN_DB_PREFIX.'element_element';
		$sql.= ' WHERE fk_source = '.(int) $this->rowid;
		$sql.= ' AND sourcetype = "tassetofline" AND targettype = "tassetworkstation"';

		$Tab = $PDOdb->ExecuteAsArray($sql);
		foreach($Tab as $row) {
		    $w=new TWorkstation;
            $w->load($PDOdb, $row->fk_target);

		    $this->TWorkstation[] = $w;
        }

		return $this->TWorkstation;
	}

    // TODO refaire, c'est codé par la mère michèle et je pense que son chat lui a bouffé la cervelle
	function visu_checkbox_workstation(&$db, &$of, &$form, $name)
	{
		$TWSid= array();
		foreach($this->TWorkstation as &$ws) {
		    if(is_object($ws)) $TWSid[] = $ws->getId();
            else  $TWSid[] =(int)$ws;
        }

		$sql = 'SELECT name AS libelle, rowid
		  FROM '.MAIN_DB_PREFIX.'workstation WHERE rowid
		IN (SELECT fk_asset_workstation FROM '.MAIN_DB_PREFIX.'asset_workstation_of WHERE fk_assetOf = '.(int) $of->rowid.')';
		$resql = $db->query($sql);

		$res = '<input checked="checked" style="display:none;" type="checkbox" name="'.$name.'" value="0" />';
		while ($r = $db->fetch_object($resql))
		{

			$res .= '<p style="margin:4px 0">'
    			.$form->checkbox1($r->libelle, $name, $r->rowid
    			, (in_array($r->rowid, $TWSid) ? true : false), 'style="vertical-align:text-bottom;"', '', '', 'case_before'
    			, array('no'=>'', 'yes'=>img_picto('', 'tick.png')) )
    			. '</p>';
		}

		return $res;
	}

	function loadFournisseurPrice(&$PDOdb) {
	    global $conf;

		$sql = "SELECT  pfp.rowid,  pfp.fk_soc,  pfp.price,  pfp.quantity,s.nom as 'name'";
        if (isModEnabled('assetatm')) $sql.= ', pfp.compose_fourni';
		$sql.= " FROM ".MAIN_DB_PREFIX."product_fournisseur_price pfp LEFT JOIN ".MAIN_DB_PREFIX."societe s ON (pfp.fk_soc=s.rowid)
		WHERE fk_product = ".(int)$this->fk_product;

		$PDOdb->Execute($sql);

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
			,$PDOdb->Get_All()
		);

	}

	function saveQty(TPDOdb &$PDOdb) {

		$PDOdb->dbupdate($this->get_table(), array( 'qty'=>$this->qty, 'qty_needed'=>$this->qty_needed,'qty_non_compliant'=>$this->qty_non_compliant, 'qty_used'=>$this->qty_used, 'rowid'=>$this->getId()),array('rowid'));


	}

	function save(&$PDOdb)
	{
		global $user,$langs,$conf,$db;

		if($this->conditionnement==0 && $this->fk_product>0) { //TOCHECK A priori inutile
            $this->initConditionnement($PDOdb);
        }

		// Appel des triggers
		include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
		$interface = new Interfaces($db);
		$result = $interface->run_triggers('ASSET_LINE_OF_SAVE',$this,$user,$langs,$conf);
		if ($result < 0)
		{
			$this->errors[] = $interface->errors;
		}

		$this->TAssetOFLine=array(); // on ne doit pas intéragir avec la ligne enfant de celle-ci (problème d'intrications récurssives)

		return parent::save($PDOdb);
	}

	function getLibelleEntrepot(&$PDOdb, $withStock=true)
	{
		$res = false;

		if (!$this->fk_entrepot) return 'Aucun entrepôt séléctionné';

		(float)DOL_VERSION > 6 ? $field_label_entrepot='ref' : $field_label_entrepot='label';

		$sql = 'SELECT e.'.$field_label_entrepot.', "" AS reel FROM '.MAIN_DB_PREFIX.'entrepot e WHERE rowid = '.(int) $this->fk_entrepot;
		if ($withStock)
		{
			$sql = 'SELECT e.'.$field_label_entrepot.', ps.reel FROM '.MAIN_DB_PREFIX.'entrepot e
					LEFT JOIN '.MAIN_DB_PREFIX.'product_stock ps ON (ps.fk_entrepot = e.rowid AND ps.fk_product = '.(int) $this->fk_product.')
					WHERE e.rowid = '.(int) $this->fk_entrepot.'';
		}
		else
		{
			$sql = 'SELECT e.'.$field_label_entrepot.' FROM '.MAIN_DB_PREFIX.'entrepot e WHERE rowid = '.(int) $this->fk_entrepot;
		}

		$PDOdb->Execute($sql);
		while ($PDOdb->Get_line())
		{
			$res = $PDOdb->Get_field($field_label_entrepot);
			if ($withStock) $res .= ' (Stock: '.$PDOdb->Get_field('reel').')';
		}

		if ($res) return $res;
		else return 'Aucun entrepôt séléctionné';
	}

	function set_values($row)
	{
		parent::set_values($row);
	}

	//Fonction pour le destockage partiel
	function destockQtyUsedAsset(&$PDOdb)
	{
    	// qty_used (saisie via l'interface) - qty_stock (ce qui a déjà été fabriqué)
		$qty_to_destock = $this->qty_used - $this->qty_stock;

		$this->destockAsset($PDOdb, $qty_to_destock); // Tant qu'on utilise l'attribut qty_used via le formulaire on as pas besoin de passer le 4eme param à false
	}

	function stockQtyToMakeAsset(&$PDOdb, &$of, $fromCloseOf=0)
	{
		global $conf,$langs;
		$qty_make = $this->qty_used - $this->qty_stock;
		if (empty($this->lot_number)) $this->lot_number = $of->ref;

		$res = $this->makeAsset($PDOdb, $of, $this->fk_product, $qty_make, 0, $this->lot_number, $this->fk_entrepot);
		//TODO si pas d'équipement défini, pas de mouvement de stock ! à corriger
		if ($res) $this->stockAsset($PDOdb, $qty_make, true, false); // On stock les nouveaux équipements
		else {
			// Si on utilise les assets mais qu'on tombe ici, alors il faut vérifier si les produits ont bien un type d'équipement de renseigné sur la fiche
			$this->stockProduct($qty_make);
			setEventMessage($langs->trans('ImpossibleToCreateAsset'), 'errors');
		}

	}


    /**
     * @param TAssetOFLine $neededLine
     * @return array
     */
    public function getToMakeChildrenOFIdAndLineID($neededLine) {
        global $db;
        $sql = 'SELECT fk_assetOf, rowid FROM '.MAIN_DB_PREFIX.'assetOf_line WHERE fk_product = '.$neededLine->fk_product.' AND fk_assetOf IN (SELECT rowid FROM '.MAIN_DB_PREFIX.'assetOf WHERE fk_assetOf_parent = '.$neededLine->fk_assetOf.') AND type = "TO_MAKE"';
        $resql = $db->query($sql);
        if($resql && $db->num_rows($resql) > 0) {
            $obj = $db->fetch_object($resql);
            return array('idLine' => $obj->rowid, 'fk_OF' => $obj->fk_assetOf);
        }
        return array();
    }
    /**
     * @param int $fk_product
     * @return int
     */
    public function getNeededIDByProduct($fk_product) {
        global $db;
        $sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'assetOf_line WHERE fk_product = '.$fk_product.' AND fk_assetOf = '.$this->fk_assetOf.' AND type = "NEEDED"';
        $resql = $db->query($sql);
        if($resql && $db->num_rows($resql) > 0) {
            $obj = $db->fetch_object($resql);
            return $obj->rowid;
        }
        return 0;
    }

        /**
     * @param int $fk_product
     * @return int
     */
    public function getWorkstationOfIDByWorkstation($fk_workstation) {
        global $db;
        $sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'asset_workstation_of WHERE fk_asset_workstation = '.$fk_workstation.' AND fk_assetOf = '.$this->fk_assetOf;
        $resql = $db->query($sql);
        if($resql && $db->num_rows($resql) > 0) {
            $obj = $db->fetch_object($resql);
            return $obj->rowid;
        }
        return 0;
    }
}

/*
 * Link to product
 */
class TAssetWorkstationProduct extends TObjetStd {

	function __construct() {
		$this->set_table(MAIN_DB_PREFIX.'asset_workstation_product');
	    	$this->TChamps = array();
		$this->add_champs('fk_product, fk_asset_workstation','type=entier;index;');
		$this->add_champs('nb_hour_prepare,nb_hour_manufacture,nb_hour,rang','type=float;'); // nombre d'heure associé au poste de charge et au produit

		$this->_init_vars();
		$this->start();

		$this->nb_hour=0;
		$this->rang=0;
	}

}

/*
 * Link to OF
 */
class TAssetWorkstationOF extends TObjetStd{

	function __construct() {
		$this->set_table(MAIN_DB_PREFIX.'asset_workstation_of');
    	$this->add_champs('fk_assetOf, fk_asset_workstation, fk_project_task',array('type'=>'integer', 'index'=>true) );
		$this->add_champs('nb_hour,nb_hour_real,nb_hour_prepare,rang,thm,nb_days_before_beginning,nb_days_before_reapro',array('type'=>'float')); // nombre d'heure associé au poste de charge sur un OF
		$this->add_champs('note_private',array('type'=>'text'));

		// J'ai rajouté nb_hour_prepare dans cette table parce que quand on veut afficher le nombre d'heures de préparation pour un poste de travail sur l'odt of,
		// celui ci peut être différent ligne par ligne si on a plusieurs fois un même poste de travail sur une nomenclature.
		// as you wish buddy ! AA
		$this->_init_vars();
	    	$this->start();

		$this->ws = new TAssetWorkstation; // TODO replace by TWorkstation
		$this->users = array();
		$this->tasks = array();
	}

	function load(&$PDOdb, $id, $loadChild = true)
	{
		parent::load($PDOdb,$id);
		$this->users = $this->get_users($PDOdb);
		$this->tasks = $this->get_tasks($PDOdb);

		if($this->fk_asset_workstation >0)
		{
			$this->ws->load($PDOdb, $this->fk_asset_workstation);

		}
	}

	function createTask(&$PDOdb, &$db, &$conf, &$user, TAssetOF &$OF)
	{
		//l'ajout de poste de travail à un OF en ajax n'initialise pas le $user
		if (!$user->id)	$user->id = GETPOST('user_id', 'none');

		$ws = new TAssetWorkstation;
		$ws->load($PDOdb, $this->fk_asset_workstation);

		if($ws->id<=0 || $OF->fk_project<=0) return false;

		$class_mod = getDolGlobalString('PROJECT_TASK_ADDON', 'mod_task_simple');
		$modTask = new $class_mod;

		$projectTask = new Task($db);
		$projectTask->fk_project = $OF->fk_project;
		$projectTask->ref = $modTask->getNextValue(0, $projectTask);
		$projectTask->label = $ws->libelle;
		$projectTask->description = $this->note_private;

        $projectTask->fk_task_parent = 0;

		$projectTask->planned_workload = $this->nb_hour*3600;

		$line_product_to_make = $OF->getLineProductToMake();

		if(getDolGlobalInt('OF_SHOW_LINE_ORDER_EXTRAFIELD_COPY_TO_TASK')) {

		    if(!empty($line_product_to_make) && $line_product_to_make->fk_commandedet>0) {

    		    dol_include_once('/commande/class/commande.class.php');

    		    $line = new OrderLine($db);
    		    $line->fetch_optionals($line_product_to_make->fk_commandedet);

    		    $projectTask->array_options = $line->array_options;

		    }

		}


       	$projectTask->array_options['options_grid_use']=1;
       	$projectTask->array_options['options_fk_workstation']=$ws->getId();
		$projectTask->array_options['options_fk_of']=$this->fk_assetOf;


		$projectTask->date_c=dol_now();

		$p = new Product($db);

		if(!empty($line_product_to_make) && ($p->fetch($line_product_to_make->fk_product) > 0)) {
			$projectTask->array_options['options_fk_product']=$p->id;
		}

        // Si cette conf est disable alors oui, on calcul les dates, autrement non car ce sera fait au niveau de la méthode 'validate' de l'objet OF
        if(!getDolGlobalInt('ASSET_TASK_HIERARCHIQUE_BY_RANK'))
        {
            // TODO vérifier que le calcul des dates soit cohérent
            $TDate = $this->calculTaskDates($PDOdb, $OF, $ws, $projectTask);
            $projectTask->date_start = $TDate['date_start'];
            $projectTask->date_end = $TDate['date_end'];
        }

        $res = $projectTask->create($user);

        if($res<0) {
            var_dump($projectTask->error, $projectTask);

            exit('ErrorCreateTaskWS') ;
        }
        else{
            $projectTask->add_object_linked('tassetof',$this->fk_assetOf);
            $this->fk_project_task = $projectTask->id;
            $this->projectTask = $projectTask;
            $this->ws = $ws;
        }

		$this->updateAssociation($PDOdb, $db, $projectTask);
	}

	function updateTask(&$PDOdb, &$db, &$conf, &$user, &$OF, $date_start_search = null, $TExcludeTaskId = array())
	{
		if (!$user->id)	$user->id = GETPOST('user_id', 'none');

		global $conf;

		$projectTask = new Task($db);
		$projectTask->fetch($this->fk_project_task);
		$projectTask->fk_project = $OF->fk_project;
		$projectTask->description = $this->note_private;

        if(getDolGlobalInt('ASSET_CUMULATE_PROJECT_TASK') && !empty($OF->from_create)) {
            $projectTask->planned_workload += $this->nb_hour * 3600;
            $projectTask->add_object_linked('tassetof',$this->fk_assetOf);
        } // On cumul le temps dans la tache
        else if($projectTask->planned_workload <= 0) $projectTask->planned_workload = $this->nb_hour * 3600;

        // FIXME je crois que cette partie ne sert à rien
        if(!isModEnabled('gantt')) {
            if(getDolGlobalInt('ASSET_CUMULATE_PROJECT_TASK') && !empty($OF->from_create)) {

                //On prend la date la plus petite
                if($projectTask->date_start > $OF->date_lancement)
                {
                    $delta = (int) $this->nb_days_before_beginning + (int) $this->nb_days_before_reapro;
                    $projectTask->date_start = strtotime(' +' .$delta. ' days', $OF->date_lancement);
                }

                //On prend la date la plus grande
                if($projectTask->date_end < $OF->date_besoin) $projectTask->date_end = $OF->date_besoin;

                if($projectTask->date_end < $projectTask->date_start) $projectTask->date_end = $projectTask->date_start;
            }
            else {
                $delta = (int) $this->nb_days_before_beginning + (int) $this->nb_days_before_reapro;
                $projectTask->date_start = strtotime(' +' .$delta. ' days', $OF->date_lancement);
                $projectTask->date_end = $OF->date_besoin;
                if($projectTask->date_end < $projectTask->date_start) $projectTask->date_end = $projectTask->date_start;
            }
        }


        $ws = new TAssetWorkstation;
        $ws->load($PDOdb, $this->fk_asset_workstation);
        $TDate = $this->calculTaskDates($PDOdb, $OF, $ws, $projectTask, $date_start_search, $TExcludeTaskId);
        $projectTask->date_start = $TDate['date_start'];
        $projectTask->date_end = $TDate['date_end'];

        $projectTask->update($user);

		$this->updateAssociation($PDOdb, $db, $projectTask);
	}

	public function getMaxDateEndOnChildrenTask($projectTask)
    {
        global $db;

        $result = null;

        $sql = 'SELECT MAX(t.datee) as datee FROM '.MAIN_DB_PREFIX.'projet_task t';
        $sql.= ' WHERE fk_task_parent = '.$projectTask->id;

        $resql = $db->query($sql);
        if ($resql)
        {
            if (($arr = $db->fetch_array($resql)))
            {
                $result = $db->jdate($arr['datee']);
            }
        }
        else
        {
            $this->error = $db->lasterror();
            $this->errors[] = $this->error;
        }

        return $result;
    }

    /**
     * @param      $PDOdb
     * @param TAssetOf     $OF
     * @param TWorkstation     $ws
     * @param      $projectTask
     * @param null $date_start_search
     * @param array $TExcludeTaskId
     * @return array
     */
	public function calculTaskDates($PDOdb, $OF, $ws, $projectTask, $date_start_search = null, $TExcludeTaskId = array())
    {
        global $conf;

        $res_date_start = $res_date_end = null; // TODO il faut prendre en compte ici, un décalage pour la date de début si il y a un lien de parenté entre les tâches

        if ($date_start_search === null)
        {
            $date_start_search = $this->getMaxDateEndOnChildrenTask($projectTask);
            if (empty($date_start_search))
            {
                if (!empty($OF->date_lancement))
                {
                    $date_start_search = $OF->date_lancement; // Ici la date de lancement doit déjà prendre en compte le temps de réapro
                }
                else
                {
                    $delta = (int) $this->nb_days_before_reapro; // non prise en compte de $this->nb_days_before_beginning car il est naturellement ajouté plus bas
                    $date_start_search = strtotime(' +'.$delta.' days', $OF->date_besoin); // TODO complètement incohérent de ce baser sur la date du besoin
                }
            }
            else
            {
                // Je force le passage du timestamp à minuit car sinon il y a de forte chance que ça engendre des décalages
                $date_start_search = strtotime('midnight', $date_start_search);
                if ($OF->date_lancement != $date_start_search) $OF->updateDateLancement($PDOdb, $date_start_search);
            }
        }
        else
        {
            $date_start_search = strtotime('+1 day', $date_start_search);
        }

        $date_start_search = strtotime('midnight', $date_start_search);

        if (isModEnabled('workstationatm'))
        {
            // TODO mériterait un peu d'otpimisation en passant en param le timestamp de la date de fin de la tâche parente directement plutôt que de le fetcher ici
//            if ($projectTask->fk_task_parent > 0)
//            {
//                $parentTask = new Task($projectTask->db);
//                if ($parentTask->fetch($projectTask->fk_task_parent) > 0)
//                {
//                    $date_end = strtotime(date('Y-m-d 00:00:00', $parentTask->date_end).' +1 day');
//                    if ($date_start_search < $date_end) $date_start_search = $date_end;
//                }
//            }

            // Délai avant démarrage dans une variable pour décrément
            if ($this->nb_days_before_beginning > 0)
            {
                $date_start_search = strtotime('+'.$this->nb_days_before_beginning.' days', $date_start_search);
            }

            $i = 0;
            $nb_hour_left = $this->nb_hour;
            $date_current_search = $date_start_search;


            if (!empty($projectTask->id)) $TExcludeTaskId[] = $projectTask->id;

            // TODO cette boucle ainsi que l'init des variables juste au dessus doit être dans une méthode à part entière car devra être appelé lors de l'update des tâches
            while ('Olaf is incredible')
            {
                // @INFO Les postes de travail de type STT sont planifiés correctement avec la capacité de définie
                $TCapacityInfoByDate = $ws->getCapacityLeftRange($PDOdb, $date_current_search, $date_current_search, false, $TExcludeTaskId);

                if (!empty($TCapacityInfoByDate))
                {
                    reset($TCapacityInfoByDate);
                    $key = key($TCapacityInfoByDate);
                    $TCapacityInfo = $TCapacityInfoByDate[$key];

                    $capacity = $TCapacityInfo['nb_hour_capacity'];
                    $capacityLeft = $TCapacityInfo['capacityLeft'];
                    $nb_ressource = $TCapacityInfo['nb_ressource'];

                    if ($capacityLeft !== 'NA' && $capacityLeft > 0 && $nb_ressource > 0)
                    {
                            // set date début car il se peut que nous sommes un jour non dispo pour le poste de travail
                            if ($res_date_start === null) $res_date_start = $date_current_search;

                            do {
                                if ($capacityLeft >= $capacity && $nb_hour_left >= $capacity)
                                {
                                    $nb_hour_left-= $capacity;
                                    $capacityLeft-= $capacity;
                                }
                                // $capacityLeft > 0 donc on utilise le reste
                                else
                                {
                                    $nb_hour_left-= $capacityLeft;
                                    $capacityLeft = 0;
                                }

                                $nb_ressource-= 1; // Chaque boucle utilise le temps d'une ressource

                                // Si c'est du non parallélisable, alors il faut stopper immédiatement, autrement on continue tant qu'il y a de la dispo temps & ressource ou on s'arrête si le nombre d'heure de travail passe à 0 ou inférieur (ce qui veut dire que nous avons suffisamment de dispo)
                            } while ($TCapacityInfo['is_parallele'] && $capacityLeft > 0 && $nb_ressource > 0 && $nb_hour_left > 0);
                    }
                }

                if ($nb_hour_left <= 0) break; // pas besoin de chercher de la dispo les jours suivants
                else $date_current_search = strtotime('+1 day', $date_current_search);

                $i++;
                if (getDolGlobalInt('OF_MAX_EXECUTION_SEARCH_PLANIF') && $i > getDolGlobalInt('OF_MAX_EXECUTION_SEARCH_PLANIF')){
					break; // sécurité, permet de plafonner la planification sur x jours
				}elseif (!getDolGlobalInt('OF_MAX_EXECUTION_SEARCH_PLANIF') && $i > 60){
					break; // sécurité, si pas de configuration plafone sur 60 jours
				}
            }

            // TODO voir si on met pas 23:59:59 (quand la demi journée sera gérée, pour le moment je met par défaut à 12:00:00)
            $res_date_end = $date_current_search + 12 * 3600; // Calage à midi pour que prod planning (Gantt) affiche correctement la prise en compte du dernier jour
        }
        else
        {
            $res_date_start = $date_start_search;
            $res_date_end = $projectTask->date_start + $this->nb_hour * 3600;
        }

        // INFO ceci devrait être dans la méthode validate() de l'objet OF juste après le save(), mais comme on calcul correctement les dates ici je suis obligé de faire ça là
		// TODO je ne prends pas en compte si on tombe sur un jour non travaillé (exemple : un dimanche)
		$date_besoin_for_children = strtotime('-1 day', $OF->date_lancement);
		foreach ($OF->TAssetOF as $childOf)
		{
			$res = $childOf->updateDateBesoin($PDOdb, $date_besoin_for_children);
		}

        $res_date_end = strtotime(date('Y-m-d 23:59:59', $res_date_end));

        return array('date_start' => $res_date_start, 'date_end' => $res_date_end);
    }

	function updateAssociation(&$PDOdb, &$db, &$projectTask)
	{
		// Association des utilisateurs aux tâches du projet
		$TUsers = $this->get_users($PDOdb);
		if(!empty($TUsers)) {
			dol_include_once('/projet/class/project.class.php');
			foreach ($TUsers as $id_user_associated_on_task) {
				$p = new Project($db);
				if($p->fetch($projectTask->fk_project) > 0) {
					$p->add_contact($id_user_associated_on_task, 160, 'internal');
					$projectTask->add_contact($id_user_associated_on_task, 180, 'internal');
				}

			}
		}
	}

	function deleteTask(&$db, &$conf, &$user)
	{
		require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';

		if (!$user->id)	$user->id = GETPOST('user_id', 'none');

		$projectTask = new Task($db);
		$projectTask->fetch($this->fk_project_task);
		$projectTask->delete($user);

		$this->fk_project_task = 0;
	}

	function manageProjectTask(&$PDOdb, $date_start_search = null, $force = false, $TExcludeTaskId = array())
	{
		global $db,$conf,$user;
		$of=new TAssetOF;
		$of->load($PDOdb, $this->fk_assetOf, false);

		if (!$force && $of->status !== 'VALID') return false; // of non valide on ne créé par les tâches

		require_once DOL_DOCUMENT_ROOT.'/projet/class/task.class.php';
		require_once DOL_DOCUMENT_ROOT.'/core/modules/project/task/' . getDolGlobalString('PROJECT_TASK_ADDON').'.php';

		$action = '';

		if ($of->fk_project > 0 && $this->fk_project_task == 0){

		    $action = 'createTask';

            if(getDolGlobalInt('ASSET_CUMULATE_PROJECT_TASK')){

                $taskstatic = new Task($db);
                $TTask = $taskstatic->getTasksArray(null, null, $of->fk_project);
                if(!empty($TTask)) {
                    foreach($TTask as $task) {
                        $task->fetch_optionals();
                        if(!empty($task->array_options['options_fk_workstation']) && $this->fk_asset_workstation == $task->array_options['options_fk_workstation']){
                            $action = 'updateTask';
                            $this->fk_project_task=$task->id;
                            $of->from_create=1;
                        }

                    }
                }
            }
        }
		elseif ($of->fk_project > 0 && $this->fk_project_task > 0) $action = 'updateTask';
		elseif ($of->fk_project == 0 && $this->fk_project_task > 0) $action = 'deleteTask';

		switch ($action)
		{
			case 'createTask':
				$this->createTask($PDOdb, $db, $conf, $user, $of, null, $TExcludeTaskId);
				break;
			case 'updateTask':
				$this->updateTask($PDOdb, $db, $conf, $user, $of, $date_start_search, $TExcludeTaskId);
				break;
			case 'deleteTask':
                if(!getDolGlobalInt('ASSET_CUMULATE_PROJECT_TASK') || getDolGlobalInt('ASSET_CUMULATE_PROJECT_TASK') && $this->isLastLink())$this->deleteTask($db, $conf, $user);
				break;
			default:
				break;
		}
	}

	function setTHM() {

		if(empty($this->nb_hour_real)) {
			$this->thm = 0;
			return $this->thm;
		}

		if(!empty($this->ws->thm) || !empty($this->ws->thm_machine)) $this->thm = $this->ws->thm + $this->ws->thm_machine;

		if(empty($this->db)) {
			global $db,$conf;
		}
		else{
			$db = &$this->db;
		}
        if(getDolGlobalInt('ASSET_CUMULATE_PROJECT_TASK')){
            $sql = "SELECT (SUM(tt.thm * tt.task_duration) / SUM(tt.task_duration)) as thm
			FROM " . MAIN_DB_PREFIX . "projet_task_time tt
			LEFT JOIN " . MAIN_DB_PREFIX . "projet_task_extrafields tex ON (tex.fk_object = tt.fk_task)
			LEFT JOIN " . MAIN_DB_PREFIX . "element_element ee  ON (ee.fk_target=tt.fk_task AND ee.targettype='project_task' AND ee.sourcetype='tassetof')
			WHERE ee.fk_source = " . $this->fk_assetOf . " AND tex.fk_workstation=" . $this->fk_asset_workstation . " AND tt.thm>0";

        }else {
            $sql = "SELECT (SUM(tt.thm * tt.task_duration) / SUM(tt.task_duration)) as thm
			FROM " . MAIN_DB_PREFIX . "projet_task_time tt
				LEFT JOIN " . MAIN_DB_PREFIX . "projet_task_extrafields tex ON (tex.fk_object = tt.fk_task)
			WHERE tex.fk_of = " . $this->fk_assetOf . " AND tex.fk_workstation=" . $this->fk_asset_workstation . " AND tt.thm>0";
        }
		$res = $db->query($sql);
		if($obj = $db->fetch_object($res)) {
			if($obj->thm>0)	$this->thm = (float)$obj->thm;
		}
		//var_dump($sql,$this->thm);
		return $this->thm;

	}

	function save(&$PDOdb)
	{
	 	global $conf;

		$this->setTHM();

        if (getDolGlobalInt('ASSET_USE_PROJECT_TASK'))
		{
			$this->manageProjectTask($PDOdb); // TODO lors de la mise à jour d'une date de livraison sur une commande fournisseur, il faudrait penser à faire appel au save des TAssetWorkstationOF pour mettre à jour toutes les dates des tâches ( attention, il faudra les exclure eux même dans la recherche de capacité )
		}
		parent::save($PDOdb);
	}

	static function get_next_ref_project() {

		global $conf;

		// Récupération de la référence suivante en fonction du masque (std dolibarr)
	    $defaultref='';
	    $modele = getDolGlobalString('PROJECT_ADDON','mod_project_simple');
	    // Search template files
	    $file=''; $classname=''; $filefound=0;
	    $dirmodels=array_merge(array('/'),(array) $conf->modules_parts['models']);
	    foreach($dirmodels as $reldir)
	    {
	    	$file=dol_buildpath($reldir."core/modules/project/".$modele.'.php',0);
	    	if (file_exists($file))
	    	{
	    		$filefound=1;
	    		$classname = $modele;
	    		break;
	    	}
	    }
	    if ($filefound)
	    {
		    $result=dol_include_once($reldir."core/modules/project/".$modele.'.php');
		    $modProject = new $classname;

		    $defaultref = $modProject->getNextValue($thirdparty ?? 0,$object ?? null);
	    }

		return $defaultref;

	}

	function set_values($Tab)
	{
		global $db,$user;

		if ($this->fk_project_task)
		{
			require_once DOL_DOCUMENT_ROOT.'/projet/class/task.class.php';

			$projectTask = new Task($db);
			$projectTask->fetch($this->fk_project_task);

			if (isset($Tab['nb_hour']))
			{
				$projectTask->planned_workload = Tools::string2num($Tab['nb_hour'])*3600;
			}

			if (isset($Tab['progress']))
			{
				$projectTask->progress = $Tab['progress'];
				unset($Tab['progress']);
			}

			$projectTask->update($user);
		}

		parent::set_values($Tab);
	}

	function delete(&$PDOdb)
	{
		global $db,$user,$conf;

		$sql = 'DELETE FROM '.MAIN_DB_PREFIX.'element_element WHERE fk_source = '.(int) $this->rowid.' AND sourcetype = "tassetworkstationof" AND (targettype = "user" OR targettype = "task")';
		$PDOdb->Execute($sql);

		if( getDolGlobalInt('ASSET_CUMULATE_PROJECT_TASK') && !empty($this->fk_assetOf)) {
            $sql = 'DELETE FROM ' . MAIN_DB_PREFIX . 'element_element WHERE fk_source = ' . (int)$this->fk_assetOf . ' AND sourcetype = "tassetof" AND (targettype = "task")';
            $PDOdb->Execute($sql);
        }
		if ($this->fk_project_task > 0)
		{
			require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
			require_once DOL_DOCUMENT_ROOT.'/projet/class/task.class.php';
			require_once DOL_DOCUMENT_ROOT.'/core/modules/project/task/' . getDolGlobalString('PROJECT_TASK_ADDON').'.php';

			if (!$user->id) $user->id = GETPOST('user_id', 'none');

			$projectTask = new Task($db);
			if($projectTask->fetch($this->fk_project_task) > 0) {
				// Suppression des occurences qui définissent cette tâches en tant que parente
				$db->query('UPDATE '.MAIN_DB_PREFIX.'projet_task SET fk_task_parent = 0 WHERE fk_task_parent = '.$projectTask->id);
                if(!getDolGlobalInt('ASSET_CUMULATE_PROJECT_TASK') || getDolGlobalInt('ASSET_CUMULATE_PROJECT_TASK') && $this->isLastLink())$projectTask->delete($user);
			}
		}

		parent::delete($PDOdb);
	}

	function set_users(&$PDOdb, $Tusers)
	{
		if (empty($Tusers)) return false;

		$this->users = array();

		$sql = 'DELETE FROM '.MAIN_DB_PREFIX.'element_element WHERE fk_source = '.(int) $this->rowid.' AND sourcetype = "tassetworkstationof" AND targettype = "user"';
		$PDOdb->Execute($sql);

		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'element_element (';
		$sql.= 'fk_source, sourcetype, fk_target, targettype';
		$sql.= ') VALUES ';

		$save = false;
		foreach ($Tusers as $id_user)
		{
			if ($id_user <= 0) continue;

			$this->users[] = $id_user;
			$save = true;

			$sql.= '(';
			$sql.= (int) $this->rowid.',';
			$sql.= $PDOdb->quote('tassetworkstationof').',';
			$sql.= (int) $id_user.',';
			$sql.= $PDOdb->quote('user');
			$sql.= '),';
		}

		if ($save)
		{
			$sql = rtrim($sql, ',');
			$PDOdb->Execute($sql);
		}
	}

	function get_users(&$PDOdb)
	{
		$res = array();

		$sql = 'SELECT fk_target FROM '.MAIN_DB_PREFIX.'element_element';
		$sql.= ' WHERE fk_source = '.(int) $this->rowid;
		$sql.= ' AND sourcetype = "tassetworkstationof" AND targettype = "user"';

		$PDOdb->Execute($sql);
		while ($PDOdb->Get_line()) $res[] = $PDOdb->Get_field('fk_target');

		return $res;
	}

	function getUsersPDF(&$PDOdb)
	{
		$res = '';
		if(count($this->users) <= 0) return '-';

		$sql = 'SELECT lastname, firstname FROM '.MAIN_DB_PREFIX.'user WHERE rowid IN ('.implode(',', $this->users).')';
		$PDOdb->Execute($sql);

		while ($PDOdb->Get_line())
		{
			$res .= $PDOdb->Get_Field('lastname').' '.$PDOdb->Get_field('Firstname').', ';
		}

		$res = rtrim($res, ', ');

		return $res;
	}

	//Mode opératoire et non tâche projet
	function set_tasks($PDOdb, $Ttask)
	{
		if (empty($Ttask)) return false;

		$this->tasks = array();

		$sql = 'DELETE FROM '.MAIN_DB_PREFIX.'element_element WHERE fk_source = '.(int) $this->rowid.' AND sourcetype = "tassetworkstationof" AND targettype = "task"';
		$PDOdb->Execute($sql);

		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'element_element (';
		$sql.= 'fk_source, sourcetype, fk_target, targettype';
		$sql.= ') VALUES ';

		$save = false;
		foreach ($Ttask as $id_task)
		{
			if ($id_task <= 0) continue;

			$this->tasks[] = $id_task;
			$save = true;

			$sql.= '(';
			$sql.= (int) $this->rowid.',';
			$sql.= $PDOdb->quote('tassetworkstationof').',';
			$sql.= (int) $id_task.',';
			$sql.= $PDOdb->quote('task');
			$sql.= '),';
		}

		if ($save)
		{
			$sql = rtrim($sql, ',');
			$PDOdb->Execute($sql);
		}
	}

	function get_tasks(&$PDOdb)
	{
		$res = array();

		$sql= 'SELECT fk_target FROM '.MAIN_DB_PREFIX.'element_element';
		$sql.= ' WHERE fk_source = '.(int) $this->rowid;
		$sql.= ' AND sourcetype = "tassetworkstationof" AND targettype = "task"';

		$PDOdb->Execute($sql);
		while ($PDOdb->Get_line()) $res[] = $PDOdb->Get_field('fk_target');

		return $res;
	}


	function getTasksPDF(&$PDOdb)
	{
		$res = '';
		if(count($this->tasks) <= 0) return '-';

		$sql = 'SELECT libelle FROM '.MAIN_DB_PREFIX.'asset_workstation_task WHERE rowid IN ('.implode(',', $this->tasks).')';
		$PDOdb->Execute($sql);

		while ($PDOdb->Get_line())
		{
			$res .= $PDOdb->Get_Field('libelle').', ';
		}

		$res = rtrim($res, ', ');

		return $res;
	}

	function set_project_task(&$PDOdb, $progress)
	{
		global $db;

		require_once DOL_DOCUMENT_ROOT.'/projet/class/task.class.php';

		$projectTask = new Task($db);
		$projectTask->fetch($this->fk_project_task);

		$projectTask->progress = $progress;
		$projectTask->update($db);
	}

	function isLastLink(){
	    global $db;

	    $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."asset_workstation_of WHERE fk_project_task=".$this->fk_project_task;
	    $resql = $db->query($sql);
	    $rows = $db->num_rows($resql);

	    if($rows > 1) return false;

	    return true;
    }

}



dol_include_once('/workstationatm/class/workstation.class.php');

if (class_exists('TWorkstation')) {
	class TAssetWorkstation extends TWorkstation {
	//TODO remove it and use workstation object
		function __construct() {
            global $conf;

	    	parent::__construct();
		    $this->start();
		    $this->entity = $conf->entity;
		}

		function load(&$PDOdb, $id, $loadChild = true)
		{
			parent::load($PDOdb, $id);
			$this->libelle = $this->name;
		}

		function save(&$PDOdb) {
			global $conf;

			$this->name = $this->libelle;

			parent::save($PDOdb);
		}

	}
}

class TAssetWorkstationTask extends TObjetStd
{
	function __construct()
	{
		$this->set_table(MAIN_DB_PREFIX.'asset_workstation_task');
		$this->TChamps = array();
		$this->add_champs('fk_workstation',array('type'=>'integer','index'=>true));
		$this->add_champs('libelle',array('type'=>'string'));
		$this->add_champs('description',array('type'=>'text'));

	    $this->start();
	}


}



