<?php
/* <one line to give the program's name and a brief idea of what it does.>
 * Copyright (C) 2015 ATM Consulting <support@atm-consulting.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * 	\file		core/triggers/interface_99_modMyodule_oftrigger.class.php
 * 	\ingroup	of
 * 	\brief		Sample trigger
 * 	\remarks	You can create other triggers by copying this one
 * 				- File name should be either:
 * 					interface_99_modMymodule_Mytrigger.class.php
 * 					interface_99_all_Mytrigger.class.php
 * 				- The file must stay in core/triggers
 * 				- The class name must be InterfaceMytrigger
 * 				- The constructor method must be named InterfaceMytrigger
 * 				- The name property name must be Mytrigger
 */

/**
 * Trigger class
 */
class Interfaceoftrigger
{

    private $db;

    /**
     * Constructor
     *
     * 	@param		DoliDB		$db		Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;

        $this->name = preg_replace('/^Interface/i', '', get_class($this));
        $this->family = "demo";
        $this->description = "Triggers of this module are empty functions."
            . "They have no effect."
            . "They are provided for tutorial purpose only.";
        // 'development', 'experimental', 'dolibarr' or version
        $this->version = 'development';
        $this->picto = 'of@of';
    }

    /**
     * Trigger name
     *
     * 	@return		string	Name of trigger file
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Trigger description
     *
     * 	@return		string	Description of trigger file
     */
    public function getDesc()
    {
        return $this->description;
    }

    /**
     * Trigger version
     *
     * 	@return		string	Version of trigger file
     */
    public function getVersion()
    {
        global $langs;
        $langs->load("admin");

        if ($this->version == 'development') {
            return $langs->trans("Development");
        } elseif ($this->version == 'experimental')

                return $langs->trans("Experimental");
        elseif ($this->version == 'dolibarr') return DOL_VERSION;
        elseif ($this->version) return $this->version;
        else {
            return $langs->trans("Unknown");
        }
    }

    /**
     * Function called when a Dolibarrr business event is done.
     * All functions "run_trigger" are triggered if file
     * is inside directory core/triggers
     *
     * 	@param		string		$action		Event action code
     * 	@param		Object		$object		Object
     * 	@param		User		$user		Object user
     * 	@param		Translate	$langs		Object langs
     * 	@param		conf		$conf		Object conf
     * 	@return		int						<0 if KO, 0 if no triggered ran, >0 if OK
     */
    public function run_trigger($action, $object, $user, $langs, $conf)
    {
        if ($action === 'PRODUCT_DELETE')
        {
            if (!defined('INC_FROM_DOLIBARR')) define('INC_FROM_DOLIBARR', 1);
            dol_include_once('/of/config.php');
            dol_include_once('/of/class/ordre_fabrication_asset.class.php');
            $TOfId = TAssetOF::productInOf($this->db, $object->id);
            if (!empty($TOfId))
            {
                $this->error = 'OF_product_in_of';
                return -1;
            }
        }
    	elseif($action === 'RELATED_ADD_LINK' && $object->type_related_object == 'ordre_fabrication') {

    		global $conf;

    		if(getDolGlobalInt('OF_FOLLOW_SUPPLIER_ORDER_STATUS')) {

	    		define('INC_FROM_DOLIBARR',true);
	    		dol_include_once('/of/config.php');
	    		dol_include_once('/of/class/ordre_fabrication_asset.class.php');
	    		$PDOdb=new TPDOdb;

	    		$of = new TAssetOF;
	    		if($of->load($PDOdb, $object->id_related_object) && $of->status!='CLOSE') {
	    			$of->setStatus($PDOdb, 'ONORDER');

	    		}

    		}
    	}
    	else if($action === 'ORDER_VALIDATE') {

        	global $conf, $db;

			if(getDolGlobalInt('CREATE_OF_ON_ORDER_VALIDATE')) {
				define('INC_FROM_DOLIBARR',true);

				dol_include_once('/product/class/product.class.php');

				dol_include_once('/of/config.php');
				dol_include_once('/of/class/ordre_fabrication_asset.class.php');
				$PDOdb=new TPDOdb;

				foreach($object->lines as $line) {

					// Uniquement si c'est un produit
					if(!empty($line->fk_product) && $line->fk_product_type == 0) {

						// On charge le produit pour vérifier son stock
						$prod = new Product($db);
						$prod->fetch($line->fk_product);
						$prod->load_stock();

						if($prod->stock_reel < $line->qty) {

							$assetOF = new TAssetOF;
							$assetOF->fk_commande = $object->id;
							$assetOF->fk_soc = $object->socid;
							$assetOF->date_besoin = property_exists($object, "delivery_date") ? $object->delivery_date : $object->date_livraison;
							$assetOF->addLine($PDOdb, $line->fk_product, 'TO_MAKE', $line->qty,0, '',0,$line->id);
							$assetOF->save($PDOdb);

						}

					}

				}
			}


        }
		elseif($action === 'ORDER_CANCEL') {

				if(getDolGlobalInt('DELETE_OF_ON_ORDER_CANCEL')) {
					define('INC_FROM_DOLIBARR',true);
					dol_include_once('/of/config.php');
					dol_include_once('/of/class/ordre_fabrication_asset.class.php');
					$PDOdb=new TPDOdb;

					// On récupère les identifiants des of créés à partir de cette commande
					$TID_OF_command = TAssetOF::getTID_OF_command($_REQUEST['id']);

					foreach($TID_OF_command as $id_of) {

						$asset = new TAssetOF;
						$asset->load($PDOdb, $id_of);

						if($asset->status == "DRAFT" || $asset->status == "VALID")
							$asset->delete($PDOdb);

					}

				}

		}
		else if($action === 'TASK_MODIFY') {
		    if(isModEnabled('workstationatm') && isModEnabled('of') ) {

		        if( getDolGlobalInt('ASSET_CUMULATE_PROJECT_TASK') ) {
                    if (!isset($conf->tassetof))$conf->tassetof = new \stdClass(); // for warning
		            $conf->tassetof->enabled = 1; // pour fetchobjectlinked
                    $object->fetchObjectLinked(0,'tassetof',$object->id,$object->element,'OR',1,'sourcetype',0);
                }

		        if(getDolGlobalInt('OF_CLOSE_OF_ON_CLOSE_ALL_TASK')
                    && ((getDolGlobalInt('ASSET_CUMULATE_PROJECT_TASK') && !empty($object->linkedObjectsIds['tassetof'])) || !empty($object->array_options['options_fk_of']))
                    && $object->progress==100) {

                    if(getDolGlobalInt('ASSET_CUMULATE_PROJECT_TASK')) {
                        foreach($object->linkedObjectsIds['tassetof'] as $fk_of) $this->closeOfIfTaskDone($fk_of, $object);
                    } else {
                        $this->closeOfIfTaskDone($object->array_options['options_fk_of'], $object);
                    }
                }

		    }
		}
		elseif($action === 'TASK_DELETE')
		{
		    global $db;

		    if(isModEnabled('workstationatm') && isModEnabled('of'))
		    {
 		        $sql = "UPDATE ".MAIN_DB_PREFIX."asset_workstation_of SET fk_project_task = 0 WHERE fk_project_task = " . $object->id;
 		        $res = $db->query($sql);
 		        if (!$res) setEventMessage('Erreur de mise à jour du poste de travail lié', 'errors');
                $object->deleteObjectLinked();
		    }


		}
		elseif($action==='TASK_TIMESPENT_CREATE') {
			if(isModEnabled('workstationatm')) {
				define('INC_FROM_DOLIBARR',true);
		    	dol_include_once('/of/config.php');
				dol_include_once('/of/class/ordre_fabrication_asset.class.php');
				$PDOdb=new TPDOdb;

				$PDOdb->Execute("SELECT rowid
						FROM ".MAIN_DB_PREFIX."asset_workstation_of
						WHERE fk_project_task=".$object->id);
				if($obj = $PDOdb->Get_line()) {

					$wsof=new TAssetWorkstationOF;
					$wsof->load($PDOdb, $obj->rowid);
					$wsof->nb_hour_real = ($object->duration_effective + $object->timespent_duration) / 3600;

					// Parce que Dolibarr mets le THM à jour après la création de la tâche :/
					$sql = "UPDATE ".MAIN_DB_PREFIX."projet_task_time";
		            $sql.= " SET thm = (SELECT thm FROM ".MAIN_DB_PREFIX."user WHERE rowid = ".$object->timespent_fk_user.")";	// set average hour rate of user
		            $sql.= " WHERE rowid = ".$object->timespent_id;
					$object->db->query($sql);

					$wsof->db = &$object->db;
					$wsof->save($PDOdb);

				}


			}


		}
		elseif($action === 'ORDERSUPPLIER_ADD_LIVRAISON' || $action==='ORDER_SUPPLIER_RECEIVE') {
			global $db, $conf;

			/**  @var CommandeFournisseur $object */

			if(isModEnabled('of')) {
				define('INC_FROM_DOLIBARR',true);
		    	dol_include_once('/of/config.php');
				dol_include_once('/of/class/ordre_fabrication_asset.class.php');

				$PDOdb=new TPDOdb();

				$resql =$db->query('SELECT fk_statut FROM '.MAIN_DB_PREFIX.'commande_fournisseur WHERE rowid = '.(int)GETPOST('id', 'none') );
				$res = $db->fetch_object($resql);

				if($res->fk_statut == 5) { // La livraison est totale
					//On cherche l'OF lié
					$resql = $db->query("SELECT fk_source
											FROM ".MAIN_DB_PREFIX."element_element
											WHERE fk_target = ".(int)GETPOST('id', 'none')."
												AND sourcetype = 'ordre_fabrication'
												AND targettype = 'order_supplier'");

					// Creation d'un résumé des quantités des produits liées à la commande fournisseur
					$TOrderLinesSummary = array();
					if(!empty($object->lines)){
						foreach ($object->lines as $line){
							if(!empty($line->fk_product)){
								if(!isset($TOrderLinesSummary[$line->fk_product] )){
									// utilisation d'un object au cas où plus tard if faudrait rajouter des choses
									$TOrderLinesSummary[$line->fk_product] = new stdClass();
									$TOrderLinesSummary[$line->fk_product]->qty = 0;
									$TOrderLinesSummary[$line->fk_product]->fk_product = $line->fk_product;
								}

								$TOrderLinesSummary[$line->fk_product]->qty+= $line->qty;
							}
						}
					}


					while($res = $db->fetch_object($resql)) {

						$id_of = (int)$res->fk_source;
						if($id_of > 0) {
							$of = new TAssetOF;
							$of->load($PDOdb, $id_of);

							if(getDolGlobalInt('ASSET_DEFINED_WORKSTATION_BY_NEEDED') && getDolGlobalInt('OF_USE_APPRO_DELAY_FOR_TASK_DELAY')) {
								foreach($of->TAssetOFLine as &$ofLine) {
									foreach($ofLine->TWorkstation as &$ws) {
										foreach($of->TAssetWorkstationOF as &$wsof) {

										    if($ws->id == $wsof->fk_asset_workstation && $wsof->fk_project_task>0) {

										        if ($wsof->nb_days_before_beginning>0) {

    												$wsof->nb_days_before_beginning = 0;
    												$wsof->save($PDOdb);
    										    }

    										    if(getDolGlobalInt('OF_CLOSE_TASK_LINKED_TO_PRODUCT_LINKED_TO_SUPPLIER_ORDER')) {

    										        dol_include_once('/projet/class/task.class.php');

    										        foreach($object->lines as &$line) {

    										            if($line->fk_product == $ofLine->fk_product && ($wsof->type == 'STT' || !getDolGlobalInt('OF_CLOSE_TASK_LINKED_TO_PRODUCT_LINKED_TO_SUPPLIER_ORDER_NEED_STT')) ) {

    										                $projectTask = new Task($db);
    										                $projectTask->fetch($wsof->fk_project_task);
    										                $projectTask->progress = 100;
    										                $projectTask->update($user);

    										            }

    										        }

    										    }

											}


										}
									}
								}

							}


							$TidSupplierOrder = $of->getElementElement($PDOdb);

							// Vérification des autres commandes
							foreach($TidSupplierOrder as $fk_supplierorder) {
								if($fk_supplierorder == (int)GETPOST('id', 'none') ) continue;

								$resql2 =$db->query('SELECT fk_statut FROM '.MAIN_DB_PREFIX.'commande_fournisseur WHERE rowid = '.$fk_supplierorder );
                                				$res2 = $db->fetch_object($resql2);
				                                if($res2->fk_statut != 5) return 0; // toutes les commandes ne sont pas reçu on arrête là
							}

						//	var_dump($of->getId(), $of->status, $conf->global->OF_FOLLOW_SUPPLIER_ORDER_STATUS);
							if($of->status != 'CLOSE') {
								if(getDolGlobalInt('OF_FOLLOW_SUPPLIER_ORDER_STATUS')) {

									foreach($of->TAssetWorkstationOF as &$wsof) {
										if($wsof->fk_project_task>0 && $wsof->nb_days_before_beginning>0) {
											$wsof->nb_days_before_beginning = 0;
										}
									}

									// Le statut de l'OF passe au statut "Terminé" lors de la réception complète de la commande fournisseur associée
									// et cela quelque soit le statut de l'OF à ce moment là (sauf si l'OF possède d'autres produits à créer qui ne dépendent pas d'une commande fournisseur).
									// verification si la commande fournisseur fourni l'ensemble des produits restant à produire
									$TOrderLinesSummaryClone = $TOrderLinesSummary;
									$setStatusTo = 'CLOSE';

									if(!empty($of->TAssetOFLine)){
										foreach ($of->TAssetOFLine as $assetOFLine){
											// Normalement on n'est pas censé avoir plusieurs lignes d'OF pour un même fk_product mais au cas où on va partir de l'hypothèse que oui.

											$stillToBeProduced = $assetOFLine->needed_qty - $assetOFLine->qty; // le restant à produire

											if($assetOFLine->type == 'TO_MAKE' // seulement les lignes de type a Produire
												&& !empty($assetOFLine->fk_product) // au cas ou
												&& $stillToBeProduced > 0 // seulement les lignes qui restent à produire
											)
											{


												if(!isset($TOrderLinesSummaryClone[$assetOFLine->fk_product])){
													// Vu que q'un produit reste à produire mais que je ne le trouve pas dans la commande fournisseur alors
													// l'OF c'est que possède d'autres produits à créer qui ne dépendent pas de cette commande fournisseur
													$setStatusTo = 'OPEN';
													break;
												}
												else{
													// déduction du restant à produire sur la commande fournisseur
													$TOrderLinesSummaryClone[$assetOFLine->fk_product]->qty-= $stillToBeProduced;

													if($TOrderLinesSummaryClone[$assetOFLine->fk_product]->qty <= 0){
														// Apres reception de la commande il restera encore des produits à produire donc
														// l'OF possède d'autres produits à créer qui ne dépendent pas de cette commande fournisseur
														$setStatusTo = 'OPEN';
														break;
													}
												}
											}


										}
									}

									$of->setStatus($PDOdb, $setStatusTo);

								}
								else{
									$of->closeOF($PDOdb);//TODO étrange de fermer l'OF systématiquement, rajouter sur option je pense
									setEventMessage($langs->trans('OFAttachedClosedAutomatically', '<a href="'.dol_buildpath('/of/fiche_of.php?id='.$id_of, 2).'">'.$of->numero.'</a>'));
								}
							}

						}
					}

					//exit;
				}
			}

		}
		elseif ($action == 'ORDER_SUPPLIER_SUBMIT')
		{
		    $this->_maj_task_date($object);
		}
		elseif ($action == 'ORDER_SUPPLIER_MODIFY')
		{
		    $this->_maj_task_date($object);
		}  elseif ($action === 'STOCKTRANSFER_CREATE')
		{

			//lors de la création d'un transfert de stock, lorsque celui ci est créé depuis un OF, on ajoute automatiquement les lignes
			global $db;

			$TAssetOFLine = GETPOST('TAssetOFLine', 'array');
            $originOFId = GETPOST('id_of', 'int');

			if(!empty($TAssetOFLine)) {

				foreach ($TAssetOFLine as $id_product => $TValues) {

					$prod = new Product($db);
					$res = $prod->fetch($id_product);

					if($res  && $TValues['qty']  > 0) {
						$line = new StockTransferLine($db);
						$line->fk_stocktransfer = $object->id;
						$line->qty = $TValues['qty'];
						$line->fk_warehouse_source = $TValues['fk_warehouse_source'];
						$line->fk_warehouse_destination = GETPOST('fk_warehouse_destination', 'int');
						$line->fk_product = $id_product;
						$line->pmp = $prod->pmp;

						$line->rang = count($object->lines) + 1;
						$line->create($user);

						$object->fetchLines();
					}

				}
			}

            if (! empty($originOFId)) {

                $PDOdb = new TPDOdb;
                $of = new TAssetOF;

                $res = $of->load($PDOdb, $originOFId);
                if ($res) {
                    $urlOF = $of->getNomUrl();

                    $object->fetch_optionals();
                    $object->array_options['options_linked_of'] = $urlOF;
                    $object->array_options['options_fk_of'] = $originOFId;
                    $object->insertExtraFields();
                }
            }
		}
        elseif ($action == 'ASSET_OF_DELETE')
        {
            if (isModEnabled('stocktransfer')) {

                dol_include_once('/product/stock/stocktransfer/class/stocktransfer.class.php');
                global $db;

                $stockTransfer = new StockTransfer($db);

                $sql = 'SELECT se.fk_object stocktransfer FROM '.MAIN_DB_PREFIX.'stocktransfer_stocktransfer_extrafields se ';
                $sql.= 'WHERE se.fk_of = '.$object->id;
                $res = $db->query($sql);

                if ($res) {
                    while ($obj = $db->fetch_object($res)) {
                        $resFetch = $stockTransfer->fetch($obj->stocktransfer);
                        if ($resFetch > 0) {
                            $stockTransfer->fetch_optionals();
                            $stockTransfer->array_options['options_linked_of'] = '';
                            $stockTransfer->array_options['options_fk_of'] = '';

                            $stockTransfer->insertExtrafields();
                        }
                    }
                }
            }
        }

        return 0;
    }

    private function _maj_task_date(&$object)
    {
        global $db, $conf;

        dol_include_once('/projet/class/task.class.php');
		$deliveryDate = property_exists($object, 'delivery_date') ? $object->delivery_date : $object->date_livraison;
        if(isModEnabled('of') && !empty($deliveryDate)) {
            define('INC_FROM_DOLIBARR',true);
            dol_include_once('/of/config.php');
            dol_include_once('/of/class/ordre_fabrication_asset.class.php');

            $PDOdb=new TPDOdb();

            $resql = $db->query("SELECT fk_source
									FROM ".MAIN_DB_PREFIX."element_element
									WHERE fk_target = ".$object->id."
									AND sourcetype = 'ordre_fabrication'
									AND targettype = 'order_supplier'");

            while($res = $db->fetch_object($resql)) {

                $id_of = (int)$res->fk_source;
                if($id_of > 0) {
                    $of = new TAssetOF;
                    $of->load($PDOdb, $id_of);

                    if(getDolGlobalInt('ASSET_DEFINED_WORKSTATION_BY_NEEDED') && getDolGlobalInt('OF_USE_APPRO_DELAY_FOR_TASK_DELAY')) {

                        $TOfParent = $of->getListeOfParents($PDOdb, 'object', true);

                        $TExcludeTaskIdCurrentOf = $TExcludeTaskId = array();
                        foreach($of->TAssetWorkstationOF as &$tmpWsof) $TExcludeTaskIdCurrentOf[$tmpWsof->fk_project_task] = $tmpWsof->fk_project_task;

                        foreach ($TOfParent as $tmpOf)
                        {
                            foreach($tmpOf->TAssetWorkstationOF as &$tmpWsof)
                            {
                                $TExcludeTaskId[$tmpWsof->fk_project_task] = $tmpWsof->fk_project_task;
                            }
                        }

                        // Concat arrays with preserving keys
                        $TExcludeTaskId = $TExcludeTaskId + $TExcludeTaskIdCurrentOf;
                        foreach($of->TAssetOFLine as &$ofLine)
                        {
                            foreach($ofLine->TWorkstation as &$ws)
                            {
                                foreach($of->TAssetWorkstationOF as &$wsof)
                                {
                                    if($ws->id == $wsof->fk_asset_workstation && $wsof->fk_project_task > 0)
                                    {
                                        foreach($object->lines as &$line)
                                        {
                                            if($line->fk_product == $ofLine->fk_product)
                                            {
                                                $date = dol_now();
												$dateObj =  property_exists($object, 'delivery_date') ? $object->delivery_date : $object->date_livraison;
                                                if (!empty($dateObj)) $date = $dateObj;

                                                $wsof->manageProjectTask($PDOdb, $date, true, $TExcludeTaskId);
                                                unset($TExcludeTaskId[$wsof->fk_project_task], $TExcludeTaskIdCurrentOf[$wsof->fk_project_task]);
                                            }

                                        }
                                        break;
                                    }
//                                    else
//                                    {
                                        // TODO il faudrait recalculer aussi les dates des autres tâches non concerné par la commande fournisseur car il est possible qu'il y ai un impact
                                        // Attention ceci doit ce faire en dehors des boucles ici, car il est nécessaire d'impacter toutes les tâches concernées
//                                    }

                                }
                            }
                        }

                        if (!empty($TOfParent))
                        {
                            // si la commande fournisseur impact un seul poste de travail, il faut maintenant purger le tableau pour ne pas exclure les autres pour les OF parents
                            if (!empty($TExcludeTaskIdCurrentOf))
                            {
                                foreach ($TExcludeTaskIdCurrentOf as $fk_project_task)
                                {
                                    unset($TExcludeTaskId[$fk_project_task]);
                                }
                                unset($TExcludeTaskIdCurrentOf);
                            }

                            foreach ($TOfParent as $of)
                            {
                                foreach ($of->TAssetWorkstationOF as &$wsof)
                                {
                                    $wsof->manageProjectTask($PDOdb, null, true, $TExcludeTaskId);
                                    unset($TExcludeTaskId[$wsof->fk_project_task]);
                                }
                            }
                        }


                    }

                }
            }
        }
    }

    private function closeOfIfTaskDone ($fk_of, $task) {
        global $conf, $db;

        $sql = "SELECT count(*) as nb
                            FROM " . MAIN_DB_PREFIX . "projet_task t";

        if(!getDolGlobalInt('ASSET_CUMULATE_PROJECT_TASK')) {
            $sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "projet_task_extrafields tex ON (tex.fk_object=t.rowid)
                            WHERE tex.fk_of=" . $fk_of . " AND (t.progress<100 OR t.progress IS NULL)";
        }
        else {
            $sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "element_element as ee ON (ee.fk_target=t.rowid AND ee.targettype='project_task' AND ee.sourcetype='tassetof')
                            WHERE ee.fk_source = " .$fk_of  . " AND (t.progress<100 OR t.progress IS NULL) AND t.rowid !=".$task->id;
        }

        $res = $db->query($sql);
        if($res === false) {

            $this->error = $db->lasterr;
            return -1;
        }

        $row = $db->fetch_object($res);

        if($row->nb == 0) {

            define('INC_FROM_DOLIBARR', true);
            dol_include_once('/of/config.php');
            dol_include_once('/of/class/ordre_fabrication_asset.class.php');
            $PDOdb = new TPDOdb;

            $assetOf = new TAssetOF();
            $assetOf->load($PDOdb, $fk_of);
            $assetOf->closeOF($PDOdb);
        }
    }
}
