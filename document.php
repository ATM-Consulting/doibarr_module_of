<?php

/**
 *       \file       htdocs/product/document.php
 *       \ingroup    product
 *       \brief      Page des documents joints sur les produits
 */

require('config.php');

dol_include_once('/of/class/ordre_fabrication_asset.class.php');
dol_include_once('/of/lib/of.lib.php');
dol_include_once('/projet/class/project.class.php');
dol_include_once('/commande/class/commande.class.php');
dol_include_once('/product/class/product.class.php');
require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/images.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formfile.class.php';

// Load translation files required by the page
$langs->loadLangs(array('other', 'products'));

$id = GETPOST('id', 'int');
$ref = GETPOST('ref', 'none');
$action = GETPOST('action', 'none');
$confirm = GETPOST('confirm', 'none');

// Security check
$fieldvalue = (!empty($id) ? $id : (!empty($ref) ? $ref : ''));
$fieldtype = (!empty($ref) ? 'ref' : 'rowid');

// Initialize technical object to manage hooks of page. Note that conf->hooks_modules contains array of hook context
$hookmanager->initHooks(array('ofdocuments'));

// Get parameters
$sortfield = GETPOST("sortfield", 'none');
$sortorder = GETPOST("sortorder", 'none');
$page = GETPOST("page", 'int');
if(empty($page) || $page == -1) {
    $page = 0;
}     // If $page is not defined, or '' or -1
$offset = $conf->liste_limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;
if(!$sortorder) $sortorder = "ASC";
if(!$sortfield) $sortfield = "position_name";

$object = new TAssetOF;
$PDOdb = new TPDOdb;
if($id > 0 || !empty($ref)) {
    $result = $object->load($PDOdb, $id);

    $upload_dir = $conf->of->multidir_output[$object->entity] . '/' . get_exdir(0, 0, 0, 0, $object, 'tassetof') . dol_sanitizeFileName($object->ref);
}
$modulepart = 'of';
$permissiontoadd = $user->hasRight('of','of','write');

/*
 * Actions
 */

$parameters = array('id' => $id);
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action);    // Note that $action and $object may have been modified by some hooks
if($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');

if(empty($reshook)) {
    // Action submit/delete file/link
    include_once DOL_DOCUMENT_ROOT . '/core/actions_linkedfiles.inc.php';
}

/*
 *	View
 */

$form = new Form($db);

$title = $langs->trans('OFAsset');
$helpurl = '';
$shortlabel = dol_trunc($object->ref, 16);

llxHeader('', $title, $helpurl);

if($object->id) {
    print dol_get_fiche_head(ofPrepareHead($object, 'assetOF'), 'document', $langs->trans('OFAsset'), -1);

    of_banner($object);

    $parameters = array();
    $reshook = $hookmanager->executeHooks('formObjectOptions', $parameters, $object, $action);    // Note that $action and $object may have been modified by hook
    print $hookmanager->resPrint;
    if($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');

    // Build file list
    $filearray = dol_dir_list($upload_dir, "files", 0, '', '(\.meta|_preview.*\.png)$', $sortfield, (strtolower($sortorder) == 'desc' ? SORT_DESC : SORT_ASC), 1);

    $totalsize = 0;
    foreach($filearray as $key => $file) {
        $totalsize += $file['size'];
    }

    print '<div class="fichecenter">';

    print '<div class="underbanner clearboth"></div>';
    print '<table class="border tableforfield" width="100%">';

    print '<tr><td class="titlefield">' . $langs->trans("NbOfAttachedFiles") . '</td><td colspan="3">' . count($filearray) . '</td></tr>';
    print '<tr><td>' . $langs->trans("TotalSizeOfAttachedFiles") . '</td><td colspan="3">' . dol_print_size($totalsize, 1, 1) . '</td></tr>';
    print '</table>';

    print '</div>';
    print '<div style="clear:both"></div>';

    dol_fiche_end();

    $permission = $user->hasRight('of','of','write');
    $permtoedit = $user->hasRight('of','of','write');
    $param = '&id=' . $object->id;
    include_once DOL_DOCUMENT_ROOT . '/core/tpl/document_actions_post_headers.tpl.php';

    $formfile = new FormFile($db);

    //Fichiers joints des commandes associées
    if(getDolGlobalInt('OF_SHOW_ORDER_DOCUMENTS')) {
        $TCommandes = array();
        if(getDolGlobalInt('OF_MANAGE_ORDER_LINK_BY_LINE')) {
            $displayOrders = '';
            $TLine_to_make = $object->getLinesProductToMake();

            foreach($TLine_to_make as $line) {
                if(!empty($line->fk_commandedet)) {
                    $commande = new Commande($db);
                    $orderLine = new OrderLine($db);
                    $orderLine->fetch($line->fk_commandedet);
                    $commande->fetch($orderLine->fk_commande);
                    $TCommandes[$orderLine->fk_commande] = $commande;
                }
            }
            if(!empty($TCommandes)) {
                foreach($TCommandes as $commande) {
                    $upload_dir = $conf->commande->dir_output . "/" . dol_sanitizeFileName($commande->ref);
                    $formfile->list_of_documents(
                        dol_dir_list($upload_dir, "files", 0, '', '(\.meta|_preview.*\.png)$', $sortfield, (strtolower($sortorder) == 'desc' ? SORT_DESC : SORT_ASC), 1),
                        $commande,
                        'commande',
                        '&id='.$object->id,
                        0,
                        $relativepathwithnofile,        // relative path with no file. For example "0/1"
                        0,
                        0,
                        '',
                        0,
                        '',
                        '',
                        0,
                        0,
                        $upload_dir,
                        $sortfield,
                        $sortorder,
                        $disablemove
                    );
                }
            }
        }
        else if(!empty($object->fk_commande)) {
            $commande = new Commande($db);
            $commande->fetch($object->fk_commande);
            $upload_dir = $conf->commande->dir_output . "/" . dol_sanitizeFileName($commande->ref);
            $formfile->list_of_documents(
                dol_dir_list($upload_dir, "files", 0, '', '(\.meta|_preview.*\.png)$', $sortfield, (strtolower($sortorder) == 'desc' ? SORT_DESC : SORT_ASC), 1),
                $commande,
                'commande',
                '&id='.$object->id,
                0,
                $relativepathwithnofile,        // relative path with no file. For example "0/1"
                0,
                0,
                '',
                0,
                $langs->transnoentities('OrderLinkedFiles', $commande->getNomUrl(1)).' '.$commande->ref_client,
                '',
                0,
                0,
                $upload_dir,
                $sortfield,
                $sortorder,
                $disablemove
            );
        }
    }

    //Fichiers joints des produits associés
    if(getDolGlobalInt('OF_SHOW_PRODUCT_DOCUMENTS') && !empty($object->TAssetOFLine)) {
        foreach($object->TAssetOFLine as $line) {
            if(!empty($line->fk_product)) {
                $product = new Product($db);
                $product->fetch($line->fk_product);
		if((float)DOL_VERSION >= 13) {
			if(isModEnabled('product')) $upload_dir = $conf->product->multidir_output[$product->entity] . '/' . get_exdir(0, 0, 0, 1, $product, 'product');
			else if(isModEnabled('service')) $upload_dir = $conf->service->multidir_output[$product->entity] . '/' . get_exdir(0, 0, 0, 1, $product, 'product');
		} else {
	                if(isModEnabled('product')) $upload_dir = $conf->product->multidir_output[$product->entity] . '/' . get_exdir(0, 0, 0, 0, $product, 'product') . dol_sanitizeFileName($product->ref);
	                else if(isModEnabled('service')) $upload_dir = $conf->service->multidir_output[$product->entity] . '/' . get_exdir(0, 0, 0, 0, $product, 'product') . dol_sanitizeFileName($product->ref);
		}

                $formfile->list_of_documents(
                    dol_dir_list($upload_dir, "files", 0, '', '(\.meta|_preview.*\.png)$', $sortfield, (strtolower($sortorder) == 'desc' ? SORT_DESC : SORT_ASC), 1),
                    $product,
                    'product',
                    '&id='.$object->id,
                    0,
                    $relativepathwithnofile,        // relative path with no file. For example "0/1"
                    0,
                    0,
                    '',
                    0,
                    $langs->transnoentities('ProductLinkedFiles', $product->getNomUrl(1)).' '.(getDolGlobalInt('OF_PRINT_LABEL_AND_DESC_PRODUCT_ON_LINKED_OBJECT_FILES') ? $product->label.' '.$product->description : ''),
                    '',
                    0,
                    0,
                    $upload_dir,
                    $sortfield,
                    $sortorder,
                    $disablemove
                );
            }
        }
    }
}
else {
    print $langs->trans("ErrorUnknown");
}

// End of page
llxFooter();
$db->close();
