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
 * \file    class/actions_of.class.php
 * \ingroup of
 * \brief   This file is an example hook overload class file
 *          Put some comments here
 */

/**
 * Class Actionsof
 */
require_once __DIR__ . '/../backport/v19/core/class/commonhookactions.class.php';
class Actionsof extends \of\RetroCompatCommonHookActions
{
	/**
	 * @var array Hook results. Propagated to $hookmanager->resArray for later reuse
	 */
	public $results = array();

	/**
	 * @var string String displayed by executeHook() immediately after return
	 */
	public $resprints;

	/**
	 * @var array Errors
	 */
	public $errors = array();

	/**
	 * Constructor
	 */
	public function __construct()
	{
	}

	/**
	 * Overloading the doActions function : replacing the parent's function with the one below
	 *
	 * @param   array()         $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    &$object        The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          &$action        Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */

	function addSearchEntry($parameters, &$object, &$action, $hookmanager) {

		global $langs, $db, $conf, $user;

		if($parameters['currentcontext'] === 'searchform' && DOL_VERSION>=6) {
				$search_boxvalue = &$parameters['search_boxvalue'];
				$langs->load('of@of');
				$this->results['searchintoof']= array(
						'img'=>'object_list'
						, 'label'=>$langs->trans("SearchIntoOf", $search_boxvalue)
						, 'text'=>img_picto('','object_list').' '.$langs->trans("SearchIntoOf", $search_boxvalue)
						, 'url'=>dol_buildpath('/of/liste_of.php',1)
                        , 'position' => getDolGlobalInt('OF_POSITION_SEARCH_ENTRY',50)
				);

				return 0;

		}

	}

	function doActions($parameters, &$object, &$action, $hookmanager)
	{
		global $langs, $db, $conf, $user;

		// Constante PRODUIT_SOUSPRODUITS passée à 0 pour ne pas déstocker les sous produits lors de la validation de l'expédition
		/*
		 * if(in_array('expeditioncard',explode(':',$parameters['context'])) && $action === "confirm_valid") {
		 * $conf->global->PRODUIT_SOUSPRODUITS = 0;
		 * }
		 */
		// --> Maintenant Géré grâce à la constante INDEPENDANT_SUBPRODUCT_STOCK que j'ai rajoutée sur notre Dolibarr
		if ($parameters['currentcontext'] === 'ordersuppliercard') {

			if (GETPOST('action', 'none') === 'confirm_commande' && GETPOST('confirm', 'none') === 'yes') {

				$time_livraison = property_exists($object, 'delivery_date') ? $object->delivery_date : $object->date_livraison;

				$sql = "SELECT fk_source as 'fk_of'
						FROM " . MAIN_DB_PREFIX . "element_element
						WHERE sourcetype='ordre_fabrication' AND fk_target=" . $object->id . " AND targettype='order_supplier' ";

				$res = $db->query($sql);

				if($res)
				{
					define('INC_FROM_DOLIBARR', true);

					dol_include_once("/of/config.php");
					dol_include_once("/of/class/ordre_fabrication_asset.class.php");

					if ($obj = $db->fetch_object($res)) {
						// of lié à la commande
						$PDOdb = new TPDOdb();

						$OF = new TAssetOF();
						$OF->load($PDOdb, $obj->fk_of);

						$OF->date_lancement = $time_livraison;
						$OF->save($PDOdb);
					} else {
						// pas d'of liés directement
						$TProduct = $TProd = array ();
						foreach ($object->lines as &$l) {
							if ($l->product_type == 0) {
								if (empty($l->fk_product)) continue;

								$TProduct[] = $l->fk_product;

								if (! isset($TProd[$l->fk_product])) $TProd[$l->fk_product] = 0;
								$TProd[$l->fk_product] += $l->qty;
							}
						}

						if (! empty($TProduct))
						{
							$sql = "SELECT DISTINCT of.date_besoin, of.rowid as 'fk_of'
									FROM " . MAIN_DB_PREFIX . "assetOf_line ofl
									LEFT JOIN " . MAIN_DB_PREFIX . "assetOf of ON (of.rowid = ofl.fk_assetOf)
									WHERE ofl.fk_product IN (" . implode(',', $TProduct) . ")
									AND of.status='ONORDER'
									ORDER BY of.date_besoin ASC";

							$res = $db->query($sql);

							if ($res)
							{
								$PDOdb = new TPDOdb();

								while ($obj = $db->fetch_object($res))
								{
									$OF = new TAssetOF();
									$OF->load($PDOdb, $obj->fk_of);
									$to_save = false;
									foreach ($OF->TAssetOFLine as &$line) {

										if (isset($TProd[$line->fk_product]) && $TProd[$line->fk_product] > 0) {
											$TProd[$line->fk_product] -= ($line->qty_needed > 0 ? $line->qty_needed : $line->qty);

											if ($OF->date_lancement < $time_livraison) {
												$OF->date_lancement = $time_livraison;
												$to_save = true;
											}
										}
									}

									if ($to_save) {
										// print 'OF '.$OF->getId().'$time_livraison'.$time_livraison;
										$OF->save($PDOdb);
									}
								}
							} else {
								setEventMessage($db->lasterror, 'errors');
							} // if ($res) { } else { }
						} // if (! empty($TProduct))
					} // if ($obj = $db->fetch_object($res)) { } else { }
				} // if ($res)
			} // if (GETPOST('action', 'none') === 'confirm_commande' && GETPOST('confirm', 'none') === 'yes')
		} elseif($parameters['currentcontext'] === 'stocktransfercard'){


			//si l'origine du transfert de stock est un of et que l'entrepôt de destination est vite, alors on affiche une erreur

			if($action == 'add' && !empty(GETPOST('TAssetOFLine', 'array')) ){
                if(! empty(GETPOST('cancel', 'alphanohtml'))) {
                        header('Location: '.dol_buildpath('/of/fiche_of.php', 1).'?id='.GETPOST('id_of','int'));
                        exit;
                }
				if(GETPOST('fk_warehouse_destination', 'int') <= 0){
					setEventMessage('WarehouseTargetEmpty', 'errors');
					$action = 'create';
				}
			}

		}

	return 0;

	}

    function formObjectOptions($parameters, &$object, &$action, $hookmanager)
    {
      	global $langs,$db,$conf;

	}

    function formEditProductOptions($parameters, &$object, &$action, $hookmanager)
    {
    	/*ini_set('dysplay_errors','On');
		error_reporting(E_ALL);*/
    	global $db,$langs;

        /*$this->results=array('myreturn'=>$myvalue);
        $this->resprints='';
 */
        return 0;
    }

	function formAddObjectLine ($parameters, &$object, &$action, $hookmanager) {



		return 0;
	}

	function printObjectLine ($parameters, &$object, &$action, $hookmanager){

		global $db;


		return 0;
	}

	/**
	 * PDF Evolution Columns
	 * @param array              $parameters     Hook metadatas (context, etc...)
	 * @param CommonDocGenerator $pdfDoc
	 * @param string             $action         Current action (if set). Generally create or edit or null
	 * @param HookManager        $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function defineColumnField($parameters, &$pdfDoc, &$action, $hookmanager)
	{
		global $conf, $user, $langs;
		if (!getDolGlobalInt('OF_USE_REFLINENUMBER')) return 0;

		// Translations
		$langs->loadLangs(array("of@of"));

		$TContext = explode(':',$parameters['context']);

		/*
		 * For example
		 $this->cols['theColKey'] = array(
			 'rank' => $rank, // int : use for ordering columns
			 'width' => 20, // the column width in mm
			 'title' => array(
				 'textkey' => 'yourLangKey', // if there is no label, yourLangKey will be translated to replace label
				 'label' => ' ', // the final label : used fore final generated text
				 'align' => 'L', // text alignement :  R,C,L
				 'padding' => array(0.5,0.5,0.5,0.5), // Like css 0 => top , 1 => right, 2 => bottom, 3 => left
			 ),
			 'content' => array(
				 'align' => 'L', // text alignement :  R,C,L
				 'padding' => array(0.5,0.5,0.5,0.5), // Like css 0 => top , 1 => right, 2 => bottom, 3 => left
			 ),
		 );
 		*/

		$def = array(
			'rank' => 55,
			'width' => 20, // in mm
			'status' => false,
			'title' => array(
				'label' => $langs->transnoentities('RefLineNumber')
			),
			'content' => array(
				'align' => 'C', // text alignement :  R,C,L
			),
			'border-left' => true, // add left line separator
		);

		$objectDocCompatible =array('commande', 'facture', 'shipping', 'propal');
		if (in_array($parameters['object']->element, $objectDocCompatible)){
			$def['status'] = true;

			if(getDolGlobalInt('OF_REF_LINE_NUMBER_BEFORE_DESC')){
				$pdfDoc->cols['desc']['border-left'] = true; // add left line separator
			}
		}

		$pdfDoc->insertNewColumnDef('RefLineNumber', $def, 'desc',!getDolGlobalInt('OF_REF_LINE_NUMBER_BEFORE_DESC'));
		return 0;
	}

	/**
	 * Overloading the printPDFline function
	 *
	 * @param   array              $parameters  Hook metadatas (context, etc...)
	 * @param   CommonDocGenerator $pdfDoc      The object to process
	 * @param   string             $action      Current action (if set). Generally create or edit or null
	 * @param   HookManager        $hookmanager Hook manager propagated to allow calling another hook
	 * @return  int  < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function printPDFLine($parameters, &$pdfDoc, &$action, $hookmanager)
	{
		global $conf, $user, $langs;
		if (!getDolGlobalInt('OF_USE_REFLINENUMBER')) return 0;
		$pdf =& $parameters['pdf'];
		$i = $parameters['i'];
		$outputlangs = $parameters['outputlangs'];

		$returnVal = 0;

		/** @var $object CommonObject */
		$object = $parameters['object'];

		if ($pdfDoc->getColumnStatus('RefLineNumber'))
		{
			$line = $object->lines[$i];
			$reflinenumber = null;
			if (!empty($line)
				&& is_object($line)
				&& is_callable(array($line, 'fetch_optionals'), true)
				&& $line->fetch_optionals() > 0
				&& !empty($line->array_options['options_reflinenumber']))
			{
				$reflinenumber = $line->array_options['options_reflinenumber'];
			} else {
				$reflinenumber = '';
			}
			if(!empty($reflinenumber)){
				$pdfDoc->printStdColumnContent($pdf, $parameters['curY'], 'RefLineNumber', $reflinenumber);
				$parameters['nexY'] = max($pdf->GetY(),$parameters['nexY']);
				$returnVal =  1;
			}
		}
		return $returnVal;
	}

	function formCreateThirdpartyOptions($parameters, &$object, &$action, $hookmanager){


	}

	function formEditThirdpartyOptions ($parameters, &$object, &$action, $hookmanager){
		global $db;

	}

	function addMoreLine($parameters, &$object, &$action, $hookmanager)
	{
		global $db,$conf,$langs;

		if (getDolGlobalInt('OF_SHOW_QTY_THEORIQUE_MOINS_OF'))
		{
			$langs->load(ATM_ASSET_NAME . '@' . ATM_ASSET_NAME);
			define('INC_FROM_DOLIBARR', true);
			dol_include_once('/of/config.php');
			dol_include_once('/product/class/product.class.php');

			$product = new Product($db);
			$fk_product = GETPOST('id', 'int');
			$ref_product = GETPOST('ref', 'none');
			$f = $product->fetch($fk_product, $ref_product);

			if ($f > 0)
			{
				$product->load_stock();
				list($qty_to_make, $qty_needed) = $this->_calcQtyOfProductInOf($db, $conf, $product);
				$qty = $product->stock_theorique + $qty_to_make - $qty_needed;

				print '<tr>';
				print '<td>'.$langs->trans('ofLabelQtyTheoriqueMoinsOf').'</td>';
				print '<td>'.$langs->trans('ofResultQty', $qty, $qty_to_make, $qty_needed).'</td>';
				print '</tr>';
			}
		}
	}

	function printCommonFooter($parameters, &$object, &$action, $hookmanager){
	    global $conf;

        if ($parameters['currentcontext'] === 'tasklist' && (float) DOL_VERSION >= 9 && getDolGlobalInt('ASSET_CUMULATE_PROJECT_TASK')) {
            ?>

            <script type="text/javascript">
                $(document).ready(function(){
                    $('#search_options_fk_of').remove(); //Remove search
                    $('th[data-titlekey="fk_of"] a').contents().unwrap(); // remove order by

                    $('td[data-key="fk_of"]').each(function(){

                        let fkTask=$(this).parent('tr').data('rowid');
                        let url = ''+'<?php echo dol_buildpath('/of/script/interface.php',2)?>';
                        let td_of = $(this);
                        $.ajax({
                            url:url
                            ,data:{
                                get:'getLinkedOf'
                                ,fk_task:fkTask
                            }
                        }).done(function(result) {
                            var TOfs = jQuery.parseJSON(result);

                            if(!jQuery.isEmptyObject(TOfs)) {

                                var html = '';

                                $.each( TOfs, function( i, Of ){
                                    html += Of.ref + "</br>";
                                });

                                td_of.html(html);
                            }
                        });
                    });
                });
            </script>

            <?php
        } elseif($parameters['currentcontext'] === 'stocktransfercard'){

			//on ajoute le tableau qui liste les produits nécessaires si ce transfert de stock est créé à partir d'un of d'origine

			global $db, $langs;

			if (!defined('INC_FROM_DOLIBARR')) define('INC_FROM_DOLIBARR', 1);
			dol_include_once('of/config.php');
			dol_include_once('/of/class/ordre_fabrication_asset.class.php');

			$id_of = GETPOST('id_of', 'int');
			$TAssetOFLine_saved = GETPOST('TAssetOFLine', 'array');

			if($id_of > 0) {

				$PDOdb = new TPDOdb;
				$of = new TAssetOF($db);
				$res = $of->load($PDOdb, $id_of);

				$formProduct = new FormProduct($db);
				$form = new TFormCore($db);

				if ($res) {
					print '<div class="div-table-responsive">';
					print '<table class="liste"  id="productlist">';
					print '<tr class="liste_titre">';
					print '<th class = "center">'.$langs->trans('Product').'</th>';
					print '<th class = "center">'.$langs->trans('VirtualStock').'</th>';
					print '<th class = "center">'.$langs->trans('RealStock').'</th>';
					print '<th class = "center">'.$langs->trans('Qty').'</th>';
					print '<th class = "center">'.$langs->trans('Warehouse').'</th>';
					print '</tr>';

					if(!empty($of->TAssetOFLine)) {

						foreach ($of->TAssetOFLine as $k => $line) {

							if ($line->type == "TO_MAKE") continue;        //si c'est le produit de l'OF à créer on n'en tient pas compte pour le transfert de stock

							$product = new Product($db);
							$product->fetch($line->fk_product);
							$stock_theo = TAssetOF::getProductStock($product->id, 0, true, true);

							print '<tr>';
							print '<input type="hidden" name = "id_of" value = "' . $id_of . '"/>';
							print '<td class = "center">' . $product->getNomUrl(1) . ' - '.$product->label.'</td>';
							print '<td class = "center">' . $stock_theo . '</td>';
							print '<td class = "center">' . $product->stock_reel . '</td>';
							print '<td class = "center" id="assetOFLine_qty">' . $form->texte('', 'TAssetOFLine[' . $line->fk_product . '][qty]', !empty($TAssetOFLine_saved[$line->fk_product]['qty']) ? $TAssetOFLine_saved[$line->fk_product]['qty'] : $line->qty, 5, 50) . '</td>';
							print '<td class = "center" id="assetOFLine_warehouse">' . $formProduct->selectWarehouses(!empty($TAssetOFLine_saved[$line->fk_product]['fk_warehouse_source']) ? $TAssetOFLine_saved[$line->fk_product]['fk_warehouse_source'] : $line->fk_entrepot, 'TAssetOFLine[' . $line->fk_product . '][fk_warehouse_source]', '', 0, 0, $line->fk_product) . '</td>';
							print '</tr>';

						}
					}

					print '</table>';
					print '</div>';


				}

				?>
				<script type="text/javascript">

					$("#productlist").insertAfter("div .tabBarWithBottom");
					$("#field_fk_warehouse_source").hide();

				</script>

				<?php
			}

		}
		return 0;
    }

	private function _calcQtyOfProductInOf(&$db, &$conf, &$product)
	{
		dol_include_once('/of/lib/of.lib.php');
		return _calcQtyOfProductInOf($db, $conf, $product);
	}

    /**
     * Overloading the loadvirtualstock function
     *
     * @param array()         $parameters     Hook metadatas (context, etc...)
     * @param CommonObject $object      The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
     * @param string       $action      Current action (if set). Generally create or edit or null
     * @param HookManager  $hookmanager Hook manager propagated to allow calling another hook
     * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
     */
    public function loadvirtualstock(&$parameters, &$object, &$action, $hookmanager) {
        dol_include_once('/of/class/ordre_fabrication_asset.class.php');
        $qtyNeeded = TAssetOF::getQtyForProduct($parameters['id']);
        $object->stock_theorique -= $qtyNeeded;
        $qtyToMake = TAssetOF::getQtyForProduct($parameters['id'], 'TO_MAKE');
        $object->stock_theorique += $qtyToMake;
    }

	/*
		 * Overloading the addMoreActionsButtons function
		 *
		 * @param   array()         $parameters     Hook metadatas (context, etc...)
		 * @param   CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
		 * @param   string          $action         Current action (if set). Generally create or edit or null
		 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
		 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
		 */
	public function addMoreActionsButtons(&$parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $langs, $db;

		$TContext = explode(':',$parameters['context']);

		if(in_array('ordercard',$TContext) && getDolGlobalInt('OF_DISPLAY_OF_ON_COMMANDLINES'))
		{
			dol_include_once('/of/lib/of.lib.php');

			$jsonObjectData =array();
			foreach($object->lines as $i => $line)
			{
				$jsonObjectData[$line->id] = new stdClass();
				$jsonObjectData[$line->id]->id = $line->id;
				$TOf = getOFForLine($line);
				$jsonObjectData[$line->id]->TOf = implode('<br>', $TOf);
			}

			?>
			<script type="application/javascript">
				$( document ).ready(function() {

					var jsonObjectData = <?php print json_encode($jsonObjectData) ; ?> ;

					// ADD NEW COLS
					$("#tablelines tr").each(function( index ) {

						$colSpanBase = 1; // nombre de colonnes ajoutées

						if($( this ).hasClass( "liste_titre" ))
						{
							// PARTIE TITRE
							$('<td align="center" class="linecolof"><?php print $langs->transnoentities('OF'); ?></td>').insertBefore($( this ).find("td.linecoldescription"));
						}
						else if($( this ).data( "product_type" ) == "9"){
							$( this ).find("td[colspan]:first").attr('colspan',    parseInt($( this ).find("td[colspan]:first").attr('colspan')) + 1  );
						}
						else
						{
							// PARTIE LIGNE
							var nobottom = '';
							if($( this ).hasClass( "liste_titre_create" ) || $( this ).attr("data-element") == "extrafield" ){
								nobottom = ' nobottom ';
							}

							// New columns
							$('<td align="center" class="linecolof' + nobottom + '"></td>').insertBefore($( this ).find("td.linecoldescription"));


							if($( this ).hasClass( "liste_titre_create" )){
								$( this ).find("td.linecoledit").attr('colspan',    parseInt($( this ).find("td.linecoledit").attr('colspan')) + $colSpanBase  );
							}

						}
					});

					// Affichage des données
					$.each(jsonObjectData, function(i, item) {
						$("#row-" + jsonObjectData[i].id + " .linecolof:first").html(jsonObjectData[i].TOf);
					});

				});
			</script>
			<?php
		}

    if(in_array('stockproductcard', $TContext)) {
        dol_include_once('/of/class/ordre_fabrication_asset.class.php');
        $langs->load('of@of');

        $qtyNeeded = TAssetOF::getQtyForProduct($object->id);
        $qtyToMake = TAssetOF::getQtyForProduct($object->id, 'TO_MAKE');
        $textOfVirtualStock = $langs->trans('VirtualStockOf', floatval($qtyNeeded), floatval($qtyToMake));
        ?>
        <script type="application/javascript">
            $(document).ready(function () {
                let tooltip = $('td:contains("<?php echo $langs->trans('VirtualStock');?>")').next('td').find('.classfortooltip');
                tooltip.attr('title', tooltip.attr('title')+'<br/><?php echo $textOfVirtualStock;?>');
            });
            </script>
        <?php
    }

		if (getDolGlobalInt('OF_USE_REFLINENUMBER')
			&& (
				in_array('ordercard', $TContext)
				|| in_array('invoicecard', $TContext)
				|| in_array('propalcard', $TContext)
				|| in_array('expeditioncard', $TContext)
			)
		)
		{
			dol_include_once('/of/lib/of.lib.php');
			if (isModEnabled('subtotal') && !class_exists('TSubtotal'))
				dol_include_once('/subtotal/class/subtotal.class.php');

				$jsonObjectData = array(
					'conf' => array(
						'OF_REF_LINE_NUMBER_BEFORE_DESC' => getDolGlobalInt('OF_REF_LINE_NUMBER_BEFORE_DESC')
					),
					'lines' => array_map(
						function ($l) {
							return array(
								'id' => $l->id,
								'reflinenumber' => isset($l->array_options['options_reflinenumber']) ? $l->array_options['options_reflinenumber'] : 0,
								'isModSubtotalLine' => class_exists('TSubtotal') ?  TSubtotal::isModSubtotalLine($l) : 0,
								'isTitle' => class_exists('TSubtotal') ? TSubtotal::isTitle($l) : 0 ,
								'isSubtotal' => class_exists('TSubtotal') ? TSubtotal::isSubtotal($l) : 0,
								'isFreeText' => class_exists('TSubtotal') ? TSubtotal::isFreeText($l) : 0,
							);
						},
						$object->lines
					),
					'trans' => array(
						'RefLineNumber' => $langs->trans('RefLineNumber')
					),
				);



			?>
			<script type="application/javascript">
				$(function() {
					let jsonObjectData = <?php echo json_encode($jsonObjectData) ; ?>;

					// ADD NEW COLS
					$("#tablelines tr").each(function() {
						$colSpanBase = 1; // nombre de colonnes ajoutées
						if($( this ).hasClass("liste_titre")) {
							// PARTIE TITRE
							let colToAdd = $('<td align="center" class="colreflinenumber">' + jsonObjectData.trans['RefLineNumber'] + '</td>');
							let colTargeted = $( this ).find("td.linecoldescription");
							if(jsonObjectData.conf.OF_REF_LINE_NUMBER_BEFORE_DESC){ colToAdd.insertBefore(colTargeted);
							}else{ colToAdd.insertAfter(colTargeted); }
						} else if($( this ).data("product_type") === 9) {
							$( this ).find("td[colspan]:first").attr('colspan', parseInt($( this ).find("td[colspan]:first").attr('colspan')) + 1);
						} else {
							// PARTIE LIGNE
							let nobottom = '';
							if($( this ).hasClass( "liste_titre_create" ) || $( this ).attr("data-element") == "extrafield" ){
								nobottom = ' nobottom ';
							}

							// New columns
							let colToAdd = $('<td align="center" class="colreflinenumber' + nobottom + '"></td>');
							let colTargeted = $( this ).find("td.linecoldescription");
							if(jsonObjectData.conf.OF_REF_LINE_NUMBER_BEFORE_DESC){
								colToAdd.insertBefore(colTargeted);
							}else{
								colToAdd.insertAfter(colTargeted);
							}

							if($( this ).hasClass("liste_titre_create")){
								$( this ).find("td.linecoledit").attr('colspan', parseInt($( this ).find("td.linecoledit").attr('colspan')) + $colSpanBase);
							}

						}
					});

					// Affichage des données
					$.each(jsonObjectData.lines, function(i, item) {
						$("#row-" + item.id + " .colreflinenumber:first").html(item.reflinenumber);
					});

				});
			</script>
			<?php
		}
	}
}
