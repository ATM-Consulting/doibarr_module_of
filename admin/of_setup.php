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
 * 	\file		admin/of.php
 * 	\ingroup	of
 * 	\brief		This file is an example module setup page
 * 				Put some comments here
 */
// Dolibarr environment

require '../config.php';
// Libraries
require_once DOL_DOCUMENT_ROOT . "/core/lib/admin.lib.php";
require_once '../lib/of.lib.php';
dol_include_once('abricot/includes/lib/admin.lib.php');


// Translations
$langs->load('admin');
$langs->load("of@of");

// Access control
if (! $user->admin) {
    accessforbidden();
}

// Parameters
$action = GETPOST('action', 'none');
$newToken = function_exists('newToken')?newToken():$_SESSION['newtoken'];
/**
 * @param $visibility
 * @return boolean 0=error; 1=success
 */
function set_reflinenumber_extrafield_visibility($visibility) {
	global $db;
	dol_include_once('/core/class/extrafields.class.php');
	$sql = 'UPDATE ' . MAIN_DB_PREFIX . 'extrafields SET list = ' . intval($visibility)
		. ' WHERE name = "reflinenumber" AND elementtype IN ("commandedet", "propaldet", "facturedet") AND entity IN (' . getEntity('extrafields') . ')';
	$resql = $db->query($sql);
	return boolval($resql);
}

function handle_ajax_query() {
    $code = GETPOST('code', 'none');
    $val = GETPOST('val', 'none');

    if ($code == 'OF_USE_REFLINENUMBER' && set_reflinenumber_extrafield_visibility(intval($val))) {
        return 'success';
    } else {
        return 'failure';
    }
}

// CMMCM: Dolibarr ne permet pas d’ajouter un hook sur dolibarr_set_const()
if ($action=='ajax') { echo handle_ajax_query(); exit; }

/*
 * Actions
 */
if (preg_match('/set_(.*)/',$action,$reg))
{
	$code=$reg[1];
	$val = GETPOST($code, 'none');
	if(is_array($val))$val= implode(',', $val);

	if (dolibarr_set_const($db, $code, $val, 'chaine', 0, '', $conf->entity) > 0)
	{
		header("Location: ".$_SERVER["PHP_SELF"]);
		exit;
	}
	else
	{
		dol_print_error($db);
	}
}

if (preg_match('/del_(.*)/',$action,$reg))
{
	$code=$reg[1];
	if (dolibarr_del_const($db, $code, 0) > 0)
	{
		Header("Location: ".$_SERVER["PHP_SELF"]);
		exit;
	}
	else
	{
		dol_print_error($db);
	}
}
	if($action=='save') {

		$error=0;

		if(isset($_REQUEST['TOF']))
		{
			foreach($_REQUEST['TOF'] as $name=>$param) {

				dolibarr_set_const($db, $name, $param, 'chaine', 0, '', $conf->entity);

			}
		}

		if (!$error) setEventMessage($langs->trans("SetupSaved"));
	}
/*
 * View
 */
$page_name = "ofSetup";
llxHeader('', $langs->trans($page_name));

// Subheader
$linkback = '<a href="' . DOL_URL_ROOT . '/admin/modules.php">'
    . $langs->trans("BackToModuleList") . '</a>';
print_fiche_titre($langs->trans($page_name), $linkback);

// Configuration header
$head = ofAdminPrepareHead();
dol_fiche_head(
    $head,
    'settings',
    $langs->trans("Module104161Name"),
    1,
    "of@of"
);
$PDOdb = new TPDOdb;
$formCore=new TFormcore;
// Setup page goes here
$form=new Form($db);

// Check abricot version
if(!function_exists('setup_print_title') || !function_exists('isAbricotMinVersion') || isAbricotMinVersion('3.1.0') < 0 ){
	print '<div class="error" >'.$langs->trans('AbricotNeedUpdate').' : <a href="http://wiki.atm-consulting.fr/index.php/Accueil#Abricot" target="_blank"><i class="fa fa-info"></i> Wiki</a></div>';
	exit;
}



print '<table class="noborder" width="100%">';



// **************************
// CONFIGURATION NUMEROTATION
// **************************
setup_print_title('OptionForNumberingTemplate');


// MASQUE DE NUMÉROTATION
dol_include_once('/of/class/ordre_fabrication_asset.class.php');
$assetOf=new TAssetOF();

$actualRefConf = getDolGlobalString('OF_MASK', 'OF{00000}');
$tooltip=$langs->trans("GenericMaskCodes");

$newtNumberOf = $langs->trans('ActualOFREfConf').' '. $actualRefConf . ' ' . $langs->trans('NextOfRef') . ' : '.$assetOf->getNumero($PDOdb,false).' ';

$attr = array(
	'placeholder' => 'OF{00000}'
);
setup_print_input_form_part('OF_MASK', false, $newtNumberOf, $attr, 'input', $tooltip);


// *****************************************
// CONFIGURATION EN LIEN AVEC LES OF ENFANTS
// *****************************************
setup_print_title('ParamLinkedToOFChildren');

$ajaxConstantOnOffInput = array(
	'alert' => array(
		'del' => array(
			'content'=>$langs->transnoentities('AssetOFConfirmChangeState')
				."<ul><li>".$langs->transnoentities('CreateAssetChildrenOFWithComposant')."</li>"
				."<li>".$langs->transnoentities('CreateAssetChildrenOF')."</li>"
				."<li>".$langs->transnoentities('DeleteAssetOFOnOrderCancel')."</li></ul>",
			'title'=>$langs->transnoentities('AssetOFConfirmChangeStateTitle')
		)
	),
	'del' => array(
		'CREATE_CHILDREN_OF_COMPOSANT',
		'CREATE_OF_ON_ORDER_VALIDATE',
		'DELETE_OF_ON_ORDER_CANCEL'
	)
);

setup_print_on_off('CREATE_CHILDREN_OF', $langs->trans("CreateAssetChildrenOF"), '', 'CreateAssetChildrenOFHelp', 300, false, $ajaxConstantOnOffInput);
setup_print_on_off('CREATE_CHILDREN_OF_ON_VIRTUAL_STOCK', $langs->trans("CreateAssetChildrenOFOnVirtualStock"));
setup_print_on_off('OF_MINIMAL_VIEW_CHILD_OF', $langs->trans("MinimalViewForChilOF"));

$ajaxConstantOnOffInput = array(
	'alert' => array(
		'set' => array(
			'content'=>$langs->transnoentities('CreateAssetChildrenOFWithComposantConfirmChangeStateContent')
				."<ul><li>".$langs->transnoentities('CreateAssetChildrenOF')."</li></ul>",
			'title'=>$langs->transnoentities('CreateAssetChildrenOFWithComposantConfirmChangeState')
		)
	),
	'set' => array('CREATE_CHILDREN_OF' => 1)
);
setup_print_on_off('CREATE_CHILDREN_OF_COMPOSANT', $langs->trans("CreateAssetChildrenOFWithComposant"), '', 'CREATE_CHILDREN_OF_COMPOSANT_HELP', 300, false, $ajaxConstantOnOffInput);
setup_print_on_off('ASSET_CHILD_OF_STATUS_FOLLOW_PARENT_STATUS', $langs->trans("AssetChildOfStatusFollowParentStatus"));


// ********************
// CONFIGURATION STOCKS
// ********************
setup_print_title('ParamLinkedToOFStocks');

setup_print_on_off('ASSET_ADD_NEEDED_QTY_ZERO', $langs->trans("AssetAddNeededQtyZero"));
setup_print_on_off('ASSET_NEGATIVE_DESTOCK', $langs->trans("AssetNegativeDestock"));
setup_print_on_off('OF_CHECK_IF_WAREHOUSE_ON_OF_LINE');
setup_print_on_off('OF_USE_DESTOCKAGE_PARTIEL', $langs->trans("AssetUseDestockagePartiel"));
setup_print_on_off('OF_DRAFT_IN_VIRTUAL_STOCK');

// Deprecated
setup_print_on_off('OF_SHOW_QTY_THEORIQUE_MOINS_OF', '<em>'.$langs->trans("OfShowQtytheorique").'</em>');


// ********************
// CONFIGURATION PRINTS
// ********************
setup_print_title('ParamLinkedToOFPrints');

setup_print_on_off('OF_PRINT_IN_PDF', false, 'OF_PRINT_IN_PDF_NEED');

$ajaxConstantOnOffInput = array(
	'alert' => array(
		'set' => array(
			'content'=>$langs->transnoentities('ConfirmChangeStateContentOptionActivationImpact')
				."<br/>+ ".$langs->transnoentities('OF_PRINT_IN_PDF'),
			'title'=>$langs->transnoentities('AssetConcatPDF')
		)
	),
	'set' => array('OF_PRINT_IN_PDF' => 1)
);
setup_print_on_off('ASSET_CONCAT_PDF', $langs->trans("AssetConcatPDF"), '', 'ASSET_CONCAT_PDF_HELP', 300, false, $ajaxConstantOnOffInput);


// ************************
// CONFIGURATION TAG PRINTS
// ************************
setup_print_title('ParamLinkedToOFTagsPrints');

$input = $formCore->number("", "OF_NB_TICKET_PER_PAGE",getDolGlobalInt('OF_NB_TICKET_PER_PAGE', 0),10,1,-1);
setup_print_input_form_part('OF_NB_TICKET_PER_PAGE', $langs->trans("OfNbTicketrPerPage"), '', array(), $input, 'OF_NB_TICKET_PER_PAGE_HELP');

$tooltip=$langs->trans("DEFAULT_ETIQUETTES_HELP");
$liste = array(1 => 'etiquette.html', 2 => 'etiquette_custom.html');
$input = $form::selectarray('DEFAULT_ETIQUETTES', $liste, getDolGlobalInt('DEFAULT_ETIQUETTES', 0));
setup_print_input_form_part('DEFAULT_ETIQUETTES', $langs->trans('CHOOSE_CUSTOM_LABEL'), '', array(), $input, $tooltip);

$input = $formCore->texte('', 'ABRICOT_WKHTMLTOPDF_CMD', (getDolGlobalString('ABRICOT_WKHTMLTOPDF_CMD','')), 80,255,' placeholder="wkhtmltopdf" ');
setup_print_input_form_part('ABRICOT_WKHTMLTOPDF_CMD', false, 'ABRICOT_WKHTMLTOPDF_CMD_DESC', array(), $input);


print '<tbody class="default-etiquette-sub-conf" data-target="2" style="display: '.(getDolGlobalInt('DEFAULT_ETIQUETTES') && (getDolGlobalInt('DEFAULT_ETIQUETTES') != 2) ?'none':'').'" >';
if(getDolGlobalInt('DEFAULT_ETIQUETTES') == 2){

	$attrNumb = array('maxlength' => '10', 'type' => 'number', 'step' => '1', 'min' => 0);
	$attrPercent = array('maxlength' => '10', 'type' => 'number', 'step' => '0.01', 'min' => 0, 'max' => 100);

	setup_print_input_form_part('DEFINE_MARGIN_TOP', false, '', $attrNumb);
	setup_print_input_form_part('DEFINE_MARGIN_TOP_CELL', false, '', $attrNumb);
	setup_print_input_form_part('DEFINE_MARGIN_LEFT', false, '', $attrNumb);
	setup_print_input_form_part('DEFINE_MARGIN_RIGHT', false, '', $attrNumb);
	setup_print_input_form_part('DEFINE_WIDTH_DIV', false, '', $attrPercent);
	setup_print_input_form_part('DEFINE_HEIGHT_DIV', false, '', $attrNumb);
}
print '</tbody>';
?><script>(function() {
		$( "#DEFAULT_ETIQUETTES" ).change(function() {
			$('.default-etiquette-sub-conf').hide();
			$('.default-etiquette-sub-conf[data-target="' + $(this).val() + '"]').show();
		});
	})();
</script><?php


// ********************
// CONFIGURATION ORDERS
// ********************
setup_print_title('ParamLinkedToOrders');

setup_print_on_off('OF_SHOW_ORDER_LINE_PRICE');
setup_print_on_off('OF_SHOW_LINE_ORDER_EXTRAFIELD');
$tooltip=$langs->trans("OF_SHOW_LINE_ORDER_EXTRAFIELD_JUST_THEM_HELP");
$attr = array(
	'size' => '80',
	'maxlength' => '255'
);
setup_print_input_form_part('OF_SHOW_LINE_ORDER_EXTRAFIELD_JUST_THEM', false, '', $attr, 'input', $tooltip);

setup_print_on_off('OF_SHOW_LINE_ORDER_EXTRAFIELD_COPY_TO_TASK');
setup_print_on_off('OF_HANDLE_ORDER_LINE_DESC');


// ******************
// CONFIGURATION GPAO
// ******************
setup_print_title('ParamLinkedToOFGPAO');

setup_print_on_off('OF_RANK_PRIOR_BY_LAUNCHING_DATE');
setup_print_on_off('OF_MANAGE_NON_COMPLIANT');

if(isModEnabled('workstationatm')){
	$input = $form->multiselectarray('OF_WORKSTATION_NON_COMPLIANT', TWorkstation::getWorstations($PDOdb), getDolGlobalString('OF_WORKSTATION_NON_COMPLIANT') ? explode(',',getDolGlobalString('OF_WORKSTATION_NON_COMPLIANT') ) : '',0, 0, '', 0, 300);
	setup_print_input_form_part('OF_WORKSTATION_NON_COMPLIANT', false, '', array(), $input);
}
setup_print_on_off('OF_REGROUP_LINE');


// ********************
// CONFIGURATION DIVERS
// ********************
setup_print_title('ParamLinkedToOFOthers');







	print '<tr '.$bc[$var].'>';
	print '<td>'.$langs->trans("OF_COEF_MINI_TU_1").'</td>';
	print '<td align="center" width="20">&nbsp;</td>';
	print '<td align="right" width="300">';
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="token" value="'.$newToken.'">';
	print '<input type="hidden" name="action" value="set_OF_COEF_MINI_TU_1">';
	print $formCore->texte('', 'OF_COEF_MINI_TU_1', getDolGlobalString('OF_COEF_MINI_TU_1',''), 10, 10);
	print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
	print '</form>';
	print '</td></tr>';

    setup_print_on_off('OF_MANAGE_ORDER_LINK_BY_LINE', $langs->trans('OF_MANAGE_ORDER_LINK_BY_LINE') , $langs->trans('OF_MANAGE_ORDER_LINK_BY_LINEDETAIL'));

    setup_print_on_off('OF_DISPLAY_OF_ON_COMMANDLINES');
    setup_print_on_off('OF_DISPLAY_PRODUCT_CATEGORIES');

    // T1107 : l’extrafield numéro de ligne de référence sur commandedet doit être rendu invisible si on désactive la conf (d’où le <script>)
    setup_print_on_off('OF_USE_REFLINENUMBER', $langs->trans('OF_USE_REFLINENUMBER'), $langs->trans('OF_USE_REFLINENUMBER_help'));
    setup_print_on_off('OF_REF_LINE_NUMBER_BEFORE_DESC');
    ?><script>
    (function() {
        let setRefLineNumberExtrafieldVisibility = function(visibility) {
            $.get('of_setup.php', { action: 'ajax', code: 'OF_USE_REFLINENUMBER', val: visibility }).done((data) => {
                if (data === 'failure') $.jnotify(data, 'error', true);
            });
        };
        $(function() {
            $("#set_OF_USE_REFLINENUMBER").click(() => setRefLineNumberExtrafieldVisibility(1));
            $("#del_OF_USE_REFLINENUMBER").click(() => setRefLineNumberExtrafieldVisibility(0));
        });
    })();
    </script><?php


print '</table>';

print '<table class="noborder" width="100%">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("ParametersWorkstation").'</td>'."\n";
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="100">'.$langs->trans("Value").'</td>'."\n";

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("UseProjectTask").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="300">';
print ajax_constantonoff('ASSET_USE_PROJECT_TASK');
print '</td></tr>';

$attr = array(
	'type'=>'number',
	'min' => 0,
	'max' => 1000,
	'placeholder' => 60
);
setup_print_input_form_part('OF_MAX_EXECUTION_SEARCH_PLANIF', $langs->trans('OF_MAX_EXECUTION_SEARCH_PLANIF'), '', $attr, 'input', $langs->trans('OF_MAX_EXECUTION_SEARCH_PLANIF_HELP'));


$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("CumulateProjectTask",$langs->transnoentitiesnoconv("UseProjectTask")).'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="300">';
print ajax_constantonoff('ASSET_CUMULATE_PROJECT_TASK');
print '</td></tr>';


$ajaxConstantOnOffInput = array(
	'alert' => array(
		'del' => array(
			'content'=>$langs->transnoentities('AssetOFConfirmChangeState')
				."<br/>- ".$langs->transnoentities('ASSET_TASK_HIERARCHIQUE_BY_RANK_REVERT'),
			'title'=>$langs->transnoentities('UseProjectTaskHierarchique')
		)
	),
	'del' => array('ASSET_TASK_HIERARCHIQUE_BY_RANK' => 1)
);

setup_print_on_off('ASSET_TASK_HIERARCHIQUE_BY_RANK', $langs->trans("UseProjectTaskHierarchique"), '', false, 300, false, $ajaxConstantOnOffInput);

$ajaxConstantOnOffInput = array(
	'alert' => array(
		'set' => array(
			'content'=>$langs->transnoentities('ConfirmChangeStateContentOptionActivationImpact')
				."<br/>+ ".$langs->transnoentities('UseProjectTaskHierarchique'),
			'title'=>$langs->transnoentities('ASSET_TASK_HIERARCHIQUE_BY_RANK_REVERT')
		)
	),
	'set' => array('ASSET_TASK_HIERARCHIQUE_BY_RANK' => 1)
);
setup_print_on_off('ASSET_TASK_HIERARCHIQUE_BY_RANK_REVERT', false, '', false, 300, false, $ajaxConstantOnOffInput);

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("OF_CONCAT_WS_ON_ADD").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="300">';
print ajax_constantonoff('OF_CONCAT_WS_ON_ADD');
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("AssetDefinedUserByWorkstation").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="300">';
print ajax_constantonoff('ASSET_DEFINED_USER_BY_WORKSTATION');
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("AssetDefinedTaskByWorkstation").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="300">';
print ajax_constantonoff('ASSET_DEFINED_OPERATION_BY_WORKSTATION');
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("AssetUseWorkstationByNeededInOF").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="300">';
print ajax_constantonoff('ASSET_DEFINED_WORKSTATION_BY_NEEDED');
print '</td></tr>';


$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("OF_USE_APPRO_DELAY_FOR_TASK_DELAY").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="300">';
print ajax_constantonoff('OF_USE_APPRO_DELAY_FOR_TASK_DELAY');
print '</td></tr>';


$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("set_OF_COEF_WS").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="right" width="300" style="white-space:nowrap;">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$newToken.'">';
print '<input type="hidden" name="action" value="set_OF_COEF_WS">';
print $formCore->texte('', 'OF_COEF_WS', (getDolGlobalString('OF_COEF_WS','')), 5,255);
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td></tr>';



$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("OF_SHOW_WS_IN_LIST").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="300">';
print ajax_constantonoff('OF_SHOW_WS_IN_LIST');
print '</td></tr>';

setup_print_on_off('OF_REAL_HOUR_CAN_BE_EMPTY', $langs->trans('OF_REAL_HOUR_CAN_BE_EMPTY'));

print '</table>';



print '<table class="noborder" width="100%">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("ParametersWorkflow").'</td>'."\n";
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="100">'.$langs->trans("Value").'</td>'."\n";

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("CreteAssetOFOnOrderValidation").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="300">';
print ajax_constantonoff('CREATE_OF_ON_ORDER_VALIDATE', array('set' => array('CREATE_CHILDREN_OF' => 1)));
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("PreventChildrenOfCreationForServices").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="300">';
print ajax_constantonoff('CREATE_CHILDREN_OF_PREVENT_OF_CREATION_FOR_SERVICES');
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("PreventChildrenOfCreationForRawMaterialProduct").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="300">';
print ajax_constantonoff('CREATE_CHILDREN_OF_PREVENT_OF_CREATION_FOR_PRODUCTS_RAWMATERIAL');
print '</td></tr>';


$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("DeleteAssetOFOnOrderCancel").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="300">';
print ajax_constantonoff('DELETE_OF_ON_ORDER_CANCEL', array('set' => array('CREATE_CHILDREN_OF' => 1)));
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("AssetAutoCreateProjectOnOF").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="300">';
print ajax_constantonoff('ASSET_AUTO_CREATE_PROJECT_ON_OF');
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("OF_ALLOW_FINISH_OF_WITH_UNRECEIVE_ORDER").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="300">';
print ajax_constantonoff('OF_ALLOW_FINISH_OF_WITH_UNRECEIVE_ORDER');
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("OF_FOLLOW_SUPPLIER_ORDER_STATUS").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="300">';
print ajax_constantonoff('OF_FOLLOW_SUPPLIER_ORDER_STATUS');
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans('OF_CLOSE_TASK_LINKED_TO_PRODUCT_LINKED_TO_SUPPLIER_ORDER').'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="300">';
print ajax_constantonoff('OF_CLOSE_TASK_LINKED_TO_PRODUCT_LINKED_TO_SUPPLIER_ORDER');
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$form->textwithtooltip($langs->trans('OF_CLOSE_TASK_LINKED_TO_PRODUCT_LINKED_TO_SUPPLIER_ORDER_NEED_STT'), $langs->trans('NEED_CONF_OF_CLOSE_TASK_LINKED_TO_PRODUCT_LINKED_TO_SUPPLIER_ORDER'),2,1,img_help(1,'')).'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="300">';
print ajax_constantonoff('OF_CLOSE_TASK_LINKED_TO_PRODUCT_LINKED_TO_SUPPLIER_ORDER_NEED_STT');
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans('OF_CLOSE_OF_ON_CLOSE_ALL_TASK').'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="300">';
print ajax_constantonoff('OF_CLOSE_OF_ON_CLOSE_ALL_TASK');
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans('OF_KEEP_ORDER_DOCUMENTS').'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="300">';
print ajax_constantonoff('OF_KEEP_ORDER_DOCUMENTS');
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans('OF_KEEP_PRODUCT_DOCUMENTS').'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="300">';
print ajax_constantonoff('OF_KEEP_PRODUCT_DOCUMENTS');
print '</td></tr>';

setup_print_on_off('OF_SHOW_ORDER_DOCUMENTS');
setup_print_on_off('OF_SHOW_PRODUCT_DOCUMENTS');
setup_print_on_off('OF_DONT_UPDATE_PMP_ON_CLOSE');

print "</table>";

print '<table class="noborder" width="100%">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("ParametersReport").'</td>'."\n";
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="100">'.$langs->trans("Value").'</td>'."\n";

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("OF_DELIVERABILITY_REPORT_SUPPLIERORDER_DATE_EXTRAFIELD").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="right" width="400">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$newToken.'">';
print '<input type="hidden" name="action" value="set_OF_DELIVERABILITY_REPORT_SUPPLIERORDER_DATE_EXTRAFIELD">';
$liste = _getDateExtrafields('commande_fournisseurdet');
print $form::selectarray('OF_DELIVERABILITY_REPORT_SUPPLIERORDER_DATE_EXTRAFIELD', $liste, getDolGlobalString('OF_DELIVERABILITY_REPORT_SUPPLIERORDER_DATE_EXTRAFIELD',''),1);
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("OF_DELIVERABILITY_REPORT_ORDER_DATE_EXTRAFIELD").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="right" width="400">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$newToken.'">';
print '<input type="hidden" name="action" value="set_OF_DELIVERABILITY_REPORT_ORDER_DATE_EXTRAFIELD">';
$liste = _getDateExtrafields('commandedet');
print $form::selectarray('OF_DELIVERABILITY_REPORT_ORDER_DATE_EXTRAFIELD', $liste, getDolGlobalString('OF_DELIVERABILITY_REPORT_ORDER_DATE_EXTRAFIELD',''),1);
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("OF_DELIVERABILITY_REPORT_PROPAL_DATE_EXTRAFIELD").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="right" width="400">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$newToken.'">';
print '<input type="hidden" name="action" value="set_OF_DELIVERABILITY_REPORT_PROPAL_DATE_EXTRAFIELD">';
$liste = _getDateExtrafields('propaldet');
print $form::selectarray('OF_DELIVERABILITY_REPORT_PROPAL_DATE_EXTRAFIELD', $liste, getDolGlobalString('OF_DELIVERABILITY_REPORT_PROPAL_DATE_EXTRAFIELD',''),1);
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td></tr>';

print "</table>";



	$form=new TFormCore;

	showParameters($form, $newToken);

/**
 * @param $form
 * @param $newToken
 * @return void
 * @throws Exception
 */
function showParameters(&$form, $newToken = "") {
	global $db,$conf,$langs;
	dol_include_once('/product/class/html.formproduct.class.php');

	$formProduct = new FormProduct($db);

	?><form action="<?php echo $_SERVER['PHP_SELF'] ?>" name="load-of" method="POST" enctype="multipart/form-data">
		<input type="hidden" name="action" value="save" />
		<input type="hidden" name="token" value="<?php echo $newToken; ?>" />
		<table width="100%" class="noborder">
			<tr class="liste_titre">
				<td colspan="2"><?php echo $langs->trans('ParametersWarehouse') ?></td>
			</tr>

			<tr class="pair">
				<td><?php echo $langs->trans('UseManualWarehouse') ?></td><td><?php echo ajax_constantonoff('ASSET_MANUAL_WAREHOUSE'); ?></td>
			</tr>

			<tr id="USE_DEFAULT_WAREHOUSE" class="impair">
				<td><?php echo $langs->trans('UseDefinedWarehouse') ?></td><td><?php echo ajax_constantonoff('ASSET_USE_DEFAULT_WAREHOUSE', array('showhide' => array('#WAREHOUSE_TO_MAKE', '#WAREHOUSE_NEEDED'), 'hide' => array('#WAREHOUSE_TO_MAKE', '#WAREHOUSE_NEEDED'))); ?></td>
			</tr>

			<tr class="pair" id="WAREHOUSE_TO_MAKE" class="pair" <?php if (!getDolGlobalInt('ASSET_USE_DEFAULT_WAREHOUSE')) echo "style='display:none;'" ?>>
				<td><?php echo $langs->trans('DefaultWarehouseIdToMake') ?></td><td><?php echo $formProduct->selectWarehouses(getDolGlobalInt('ASSET_DEFAULT_WAREHOUSE_ID_TO_MAKE'),'TOF[ASSET_DEFAULT_WAREHOUSE_ID_TO_MAKE]'); ?></td>
			</tr>

			<tr class="impair" id="WAREHOUSE_NEEDED" <?php if (!getDolGlobalInt('ASSET_USE_DEFAULT_WAREHOUSE')) echo "style='display:none;'" ?>>
				<td><?php echo $langs->trans('DefaultWarehouseIdNeeded') ?></td><td><?php echo $formProduct->selectWarehouses(getDolGlobalInt('ASSET_DEFAULT_WAREHOUSE_ID_NEEDED'),'TOF[ASSET_DEFAULT_WAREHOUSE_ID_NEEDED]'); ?></td>
			</tr>

		</table>

		<script type="text/javascript">
			$(function() {
				$('#set_ASSET_MANUAL_WAREHOUSE').click(function() {
					if ($('#del_ASSET_USE_DEFAULT_WAREHOUSE').css('display') != 'none') {
						$('#del_ASSET_USE_DEFAULT_WAREHOUSE').click();
					}
				});

				$('#set_ASSET_USE_DEFAULT_WAREHOUSE').click(function() {
					if ($('#del_ASSET_MANUAL_WAREHOUSE').css('display') != 'none') {
						$('#del_ASSET_MANUAL_WAREHOUSE').click();
					}
				});
			});
		</script>

		<p align="right">
			<input class="button" type="submit" name="bt_save" value="<?php echo $langs->trans('Save') ?>" />
		</p>

	</form>
	<p align="center" style="background: #fff;">

	   <a href="http://www.atm-consulting.fr/" target="_blank"><img src="../img/ATM_logo_petit.jpg" /></a>
	</p>

	<br /><br />
	<?php
}

dol_fiche_end(1);

llxFooter();

$db->close();

function _getDateExtrafields($elementtype){
    global $db;
    dol_include_once('/core/class/extrafields.class.php');
    $extra = new ExtraFields($db);
    $extra->fetch_name_optionals_label($elementtype);
    if(!empty($extra->attributes[$elementtype]['label'])) return $extra->attributes[$elementtype]['label'];
    else return array();
}
