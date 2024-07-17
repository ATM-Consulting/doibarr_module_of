<?php
/**
 * Created by PhpStorm.
 * User: quentin
 * Date: 28/03/19
 * Time: 14:48
 */
require('config.php');

dol_include_once('/commande/class/commande.class.php');
dol_include_once('/comm/propal/class/propal.class.php');
dol_include_once('/of/class/ordre_fabrication_asset.class.php');
dol_include_once('/nomenclature/class/nomenclature.class.php');
dol_include_once('/fourn/class/fournisseur.class.php');
dol_include_once('/core/class/html.form.class.php');
dol_include_once('/of/lib/of.lib.php');

if(empty(getDolGlobalString('OF_DELIVERABILITY_REPORT_ORDER_DATE_EXTRAFIELD')) || empty(getDolGlobalInt('OF_DELIVERABILITY_REPORT_SUPPLIERORDER_DATE_EXTRAFIELD')) || empty(getDolGlobalInt('OF_DELIVERABILITY_REPORT_PROPAL_DATE_EXTRAFIELD'))) {
    accessforbidden($langs->trans('FillReportConf'));
}

$search_company=GETPOST('search_company', 'none');
$search_cmd=GETPOST('search_cmd', 'none');
$search_alert_line=GETPOST('search_alert_line', 'none');
$search_no_date=GETPOST('search_no_date', 'none');
$search_non_compliant=GETPOST('search_non_compliant', 'none');

$search_prod=GETPOST('search_prod', 'none');
$search_of=GETPOST('search_of', 'none');
$search_delivery_start=GETPOST("search_delivery_start", 'none');
$search_delivery_end=GETPOST("search_delivery_end", 'none');
$search_delivery_startday=GETPOST("search_delivery_startday","int");
$search_delivery_startmonth=GETPOST("search_delivery_startmonth","int");
$search_delivery_startyear=GETPOST("search_delivery_startyear","int");
$search_delivery_endday=GETPOST("search_delivery_endday","int");
$search_delivery_endmonth=GETPOST("search_delivery_endmonth","int");
$search_delivery_endyear=GETPOST("search_delivery_endyear","int");
$search_qty=GETPOST("search_qty", 'none');
$viewstatut=GETPOST('viewstatut', 'none');




$limit = GETPOST('limit', 'int') ? GETPOST('limit', 'int') : $conf->liste_limit;
$sortfield = GETPOST("sortfield", 'none');
$sortorder = GETPOST("sortorder", 'none');
$page = GETPOST("page", 'int');

if(empty($page) || $page == -1 || !empty($search_btn) || !empty($search_remove_btn) || (empty($toselect) && $massaction === '0')) {
    $page = 0;
}     // If $page is not defined, or '' or -1
$offset = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;
if(!$sortfield) $sortfield = 'date_livraison';
if(!$sortorder) $sortorder = 'DESC';


// Purge search criteria
if (GETPOST('button_removefilter_x', 'none') || GETPOST('button_removefilter.x', 'none') || GETPOST('button_removefilter', 'none')) // All tests are required to be compatible with all browsers
{
    $search_delivery_start=null;
    $search_delivery_end=null;
    $search_cmd='';
    $search_alert_line='';
    $search_no_date='';
    $search_non_compliant='';
    $search_company='';
    $search_prod='';
    $search_of='';
    $search_delivery_startday='';
    $search_delivery_startmonth='';
    $search_delivery_startyear='';
    $search_delivery_endday='';
    $search_delivery_endmonth='';
    $search_delivery_endyear='';
    $search_qty='';
    $viewstatut='';
}

$TLines = array();
$TLinesToDisplay = array();
$PDOdb = new TPDOdb;
$form = new Form($db);
$TProductId = array(); //On récupère tous les products id pour les associés à leur stock physique actuel car on va les décrementer au fur et à mesure que nous créons la liste
$TProductStock = array(); //On récupère tous les products id pour les associés à leur stock physique actuel car on va les décrementer au fur et à mesure que nous créons la liste

$langs->load('orders');
$langs->load('deliveries');
/*
 * On récupère toutes les lignes de commandes non livrées, ni annulées, et s'il y en a un, l'of lié pour pouvoir faire le traitement (lignes non filtrées)
 */
$sqlOrder = "SELECT DISTINCT cd.rowid as rowid,
            c.ref as ref,
            aol.fk_assetOf, aol.rowid as fk_assetOfLine,
            cde." . getDolGlobalString('OF_DELIVERABILITY_REPORT_ORDER_DATE_EXTRAFIELD')." as date_livraison,
             SUM(ed.qty) as qty_exped, 'commande' as element,
             prod.ref as ref_prod,
             s.nom as societe_nom,
             c.fk_statut as statut_elem,
             ao.date_besoin as of_date_besoin,
             ao.date_lancement as of_date_lancement,
             ao.temps_estime_fabrication as of_temps_estime_fabrication,
             cd.qty as line_qty,
             aol.qty as of_line_qty,
             aol.qty_used as of_line_qty_used,
             aol.qty_non_compliant as of_line_qty_non_compliant

             FROM " . MAIN_DB_PREFIX . "commandedet as cd";
$sqlOrder .= " LEFT JOIN " . MAIN_DB_PREFIX . "commande as c ON (cd.fk_commande = c.rowid)";
$sqlOrder .= " LEFT JOIN " . MAIN_DB_PREFIX . "commandedet_extrafields as cde ON (cde.fk_object = cd.rowid)";
$sqlOrder .= " LEFT JOIN " . MAIN_DB_PREFIX . "assetOf_line as aol ON (aol.fk_commandedet = cd.rowid)";
$sqlOrder .= " LEFT JOIN " . MAIN_DB_PREFIX . "assetOf as ao ON (aol.fk_assetOf = ao.rowid)";
$sqlOrder .= " LEFT JOIN " . MAIN_DB_PREFIX . "element_element as ee ON (ee.fk_source = c.rowid AND ee.sourcetype='commande' AND ee.targettype='shipping')";
$sqlOrder .= " LEFT JOIN " . MAIN_DB_PREFIX . "expedition as e ON (e.rowid = ee.fk_target)";
$sqlOrder .= " LEFT JOIN " . MAIN_DB_PREFIX . "product as prod ON (prod.rowid = cd.fk_product)";
$sqlOrder .= " LEFT JOIN " . MAIN_DB_PREFIX . "societe as s ON (s.rowid = c.fk_soc)";
$sqlOrder .= " LEFT JOIN " . MAIN_DB_PREFIX . "expeditiondet as ed ON (ed.fk_expedition = e.rowid AND ed.fk_origin_line = cd.rowid)";
$sqlOrderWhere = " WHERE c.fk_statut IN (" . Commande::STATUS_VALIDATED . "," . Commande::STATUS_SHIPMENTONPROCESS. ") AND prod.fk_product_type=0 AND prod.rowid IS NOT NULL ";
$sqlOrderGroup = " GROUP BY cd.rowid, aol.fk_assetOf, aol.rowid, cde." . getDolGlobalString('OF_DELIVERABILITY_REPORT_ORDER_DATE_EXTRAFIELD');

/*
 * On fait la même chose pour les propals ayant l'extrafield à oui
 */
$sqlPropal = "SELECT DISTINCT pd.rowid as rowid
            , p.ref as ref
            ,'' as fk_assetOf, '' as fk_assetOfLine
            , pde." . getDolGlobalString('OF_DELIVERABILITY_REPORT_PROPAL_DATE_EXTRAFIELD')." as date_livraison
            , NULL as qty_exped
            , 'propal' as element
            ,prod.ref as ref_prod
            ,s.nom as societe_nom
            ,p.fk_statut as statut_elem
            ,'' as of_date_besoin
            ,'' as of_date_lancement
            ,'' as of_temps_estime_fabrication
            ,pd.qty as line_qty
            ,'' as of_line_qty
            ,'' as of_line_qty_used
            ,'' as of_line_qty_non_compliant
        FROM " . MAIN_DB_PREFIX . "propaldet as pd";
$sqlPropal .= " LEFT JOIN " . MAIN_DB_PREFIX . "propal as p ON (pd.fk_propal = p.rowid)";
$sqlPropal .= " LEFT JOIN " . MAIN_DB_PREFIX . "propaldet_extrafields as pde ON (pde.fk_object = pd.rowid)";
$sqlPropal .= " LEFT JOIN " . MAIN_DB_PREFIX . "propal_extrafields as pe ON (pe.fk_object = p.rowid)";
$sqlPropal .= " LEFT JOIN " . MAIN_DB_PREFIX . "element_element as ee ON (ee.fk_source = p.rowid AND ee.sourcetype='propal' AND ee.targettype='commande')";
$sqlPropal .= " LEFT JOIN " . MAIN_DB_PREFIX . "product as prod ON (prod.rowid = pd.fk_product)";
$sqlPropal .= " LEFT JOIN " . MAIN_DB_PREFIX . "societe as s ON (s.rowid = p.fk_soc)";
$sqlPropalWhere = " WHERE p.fk_statut IN (".Propal::STATUS_VALIDATED.",".Propal::STATUS_SIGNED.")
                    AND prod.fk_product_type=0
                    AND prod.rowid IS NOT NULL
                    AND pe.of_check_prev = 1
                    AND ee.fk_target IS NULL ";
$sqlPropalGroup = " GROUP BY pd.rowid, pde." . getDolGlobalString('OF_DELIVERABILITY_REPORT_PROPAL_DATE_EXTRAFIELD');
$sqlOrderBy = " ORDER BY date_livraison, rowid";

$sql = $sqlOrder.$sqlOrderWhere.$sqlOrderGroup
    .' UNION '
    .$sqlPropal.$sqlPropalWhere.$sqlPropalGroup
    .$sqlOrderBy;

/*
 * TRAITEMENT GLOBAL
 */

$result = $db->query($sql);
if(!empty($result) && $db->num_rows($result) > 0) {
    while($obj = $db->fetch_object($result)) {
        if($obj->element == 'commande') $myLine = new OrderLine($db);
        else if($obj->element == 'propal') $myLine = new PropaleLigne($db);

        $nomenclature = new TNomenclature;
        if(!empty($TLines[$obj->rowid])) {
            $myLine = $TLines[$obj->rowid];
            $myLine->fk_assetOf[] = $obj->fk_assetOf;
            $myLine->fk_assetOfLine[] = $obj->fk_assetOfLine;
        }
        else {
            $myLine->fetch($obj->rowid);
            $myLine->fetch_optionals();
            if($obj->element == 'commande') $myLine->of_date_de_livraison = $myLine->array_options['options_' . getDolGlobalString('OF_DELIVERABILITY_REPORT_ORDER_DATE_EXTRAFIELD')];
            else if($obj->element == 'propal')$myLine->of_date_de_livraison = $myLine->array_options['options_' . getDolGlobalString('OF_DELIVERABILITY_REPORT_PROPAL_DATE_EXTRAFIELD')];
            $myLine->fk_assetOf = array($obj->fk_assetOf);//On récup l'OF
            $myLine->fk_assetOfLine = array($obj->fk_assetOfLine);//On récup la ligne associé
            $nomenclature->loadByObjectId($PDOdb, $obj->rowid, $obj->element, false, 0, $myLine->qty, 0);
            if(empty($nomenclature->rowid)) $nomenclature->loadByObjectId($PDOdb, $myLine->fk_product, 'product', false, 0, $myLine->qty, 0);
            if(!empty($nomenclature->rowid)) {
                $details_nomenclature = $nomenclature->getDetails(1);

                $myLine->nomenclature = $nomenclature; //On récup la nomenclature
                $myLine->details_nomenclature = $details_nomenclature; //On récup la nomenclature

                //On init aussi le tableau avec les produits liés à la nomenclature (récursivement =) )
                if(!empty($details_nomenclature)) _getProductIdFromNomen($TProductId, $details_nomenclature);
            }
            if(!empty($myLine->fk_product)) $TProductId[$myLine->fk_product] = $myLine->fk_product; // on init le produit avec tous les produits
            $myLine->qty_exped = $obj->qty_exped;
            $TLines[$myLine->id] = $myLine;
        }
    }
}

//Une fois que le traitement est fait on peut filtrer
if ($search_cmd){
    $sqlOrderWhere .= natural_search('c.ref', $search_cmd);
    $sqlPropalWhere .= natural_search('p.ref', $search_cmd);
}
if ($search_company){
     $sqlOrderWhere .= natural_search('s.nom', $search_company);
     $sqlPropalWhere .= natural_search('s.nom', $search_company);
}

if ($search_prod) {
    $sqlOrderWhere .= natural_search('prod.ref', $search_prod);
    $sqlPropalWhere .= natural_search('prod.ref', $search_prod);
}

if ($search_of){
    $sqlOrderWhere .= natural_search('ao.numero', $search_of);
    $sqlPropalWhere .= 'AND 0=1' ;
}
if ($search_qty != '') {
    $sqlOrderWhere.= natural_search("cd.qty", $search_qty, 1);
    $sqlPropalWhere.= natural_search("pd.qty", $search_qty, 1);
}
if (!empty($search_no_date)) {
    $sqlOrderWhere.= ' AND cde.' . getDolGlobalString('OF_DELIVERABILITY_REPORT_ORDER_DATE_EXTRAFIELD').' IS NULL';
    $sqlPropalWhere.= ' AND pde.' . getDolGlobalString('OF_DELIVERABILITY_REPORT_PROPAL_DATE_EXTRAFIELD').' IS NULL';
}
if (!empty($search_non_compliant)) {
    $sqlOrderWhere.= ' AND aol.qty_non_compliant > 0';
    $sqlPropalWhere .= ' AND 0=1' ;
}
//if ($viewstatut <> '')
//{
//    if ($viewstatut < 4 && $viewstatut > -3)
//    {
//        if ($viewstatut == 1 && !isModEnabled('expedition')) $sqlOrderWhere.= ' AND c.fk_statut IN (1,2)';	// If module expedition disabled, we include order with status 'sending in process' into 'validated'
//        else $sqlOrderWhere.= ' AND c.fk_statut = '.$viewstatut; // brouillon, validee, en cours, annulee
//    }
//    if ($viewstatut == 4)
//    {
//        $sqlOrderWhere.= ' AND c.facture = 1'; // invoice created
//    }
//    if ($viewstatut == -2)	// To process
//    {
//        //$sqlWhere.= ' AND c.fk_statut IN (1,2,3) AND c.facture = 0';
//        $sqlOrderWhere.= " AND ((c.fk_statut IN (1,2)) OR (c.fk_statut = 3 AND c.facture = 0))";    // If status is 2 and facture=1, it must be selected
//    }
//    if ($viewstatut == -3)	// To bill
//    {
//        //$sqlWhere.= ' AND c.fk_statut in (1,2,3)';
//        //$sqlWhere.= ' AND c.facture = 0'; // invoice not created
//        $sqlOrderWhere .= ' AND ((c.fk_statut IN (1,2)) OR (c.fk_statut = 3 AND c.facture = 0))'; // validated, in process or closed but not billed
//    }
//}
if(!empty($search_delivery_startday) && !empty($search_delivery_endday)){
    $sqlOrderWhere.= " AND  (cde." . getDolGlobalString('OF_DELIVERABILITY_REPORT_ORDER_DATE_EXTRAFIELD')." >= '".$search_delivery_startyear."-".$search_delivery_startmonth."-".$search_delivery_startday."'
                   AND cde." . getDolGlobalString('OF_DELIVERABILITY_REPORT_ORDER_DATE_EXTRAFIELD')." <= '".$search_delivery_endyear."-".$search_delivery_endmonth."-".$search_delivery_endday."')";
    $sqlPropalWhere.= " AND  (pde." . getDolGlobalString('OF_DELIVERABILITY_REPORT_PROPAL_DATE_EXTRAFIELD')." >= '".$search_delivery_startyear."-".$search_delivery_startmonth."-".$search_delivery_startday."'
                   AND pde." . getDolGlobalString('OF_DELIVERABILITY_REPORT_PROPAL_DATE_EXTRAFIELD')." <= '".$search_delivery_endyear."-".$search_delivery_endmonth."-".$search_delivery_endday."')";
} else if(!empty($search_delivery_startday)  && empty($search_delivery_endday) ){
    $sqlOrderWhere.= " AND  (cde." . getDolGlobalString('OF_DELIVERABILITY_REPORT_ORDER_DATE_EXTRAFIELD')." >= '".$search_delivery_startyear."-".$search_delivery_startmonth."-".$search_delivery_startday."')";
    $sqlPropalWhere.= " AND  (pde." . getDolGlobalString('OF_DELIVERABILITY_REPORT_PROPAL_DATE_EXTRAFIELD')." >= '".$search_delivery_startyear."-".$search_delivery_startmonth."-".$search_delivery_startday."')";
} else if(empty($search_delivery_startday)  && !empty($search_delivery_endday) ){
    $sqlOrderWhere.= "  AND (cde." . getDolGlobalString('OF_DELIVERABILITY_REPORT_ORDER_DATE_EXTRAFIELD')." <= '".$search_delivery_endyear."-".$search_delivery_endmonth."-".$search_delivery_endday."')";
    $sqlPropalWhere.= "  AND (pde." . getDolGlobalString('OF_DELIVERABILITY_REPORT_PROPAL_DATE_EXTRAFIELD')." <= '".$search_delivery_endyear."-".$search_delivery_endmonth."-".$search_delivery_endday."')";
}

$sqlOrder .= $sqlOrderWhere.$sqlOrderGroup; // Obliger de faire le travail 2x (1 pour avoir toutes les données et faire le traitement, et l'autre pour le filtrage et l'affichage car le traitement se fait ligne à ligne (qté décrémenté ligne par ligne))
$sqlPropal .= $sqlPropalWhere.$sqlPropalGroup; // Obliger de faire le travail 2x (1 pour avoir toutes les données et faire le traitement, et l'autre pour le filtrage et l'affichage car le traitement se fait ligne à ligne (qté décrémenté ligne par ligne))
$sql = $sqlOrder .' UNION '. $sqlPropal;
$result = $db->query($sql. $sqlOrderBy);

$nbtotalofrecords = count($TLines);

if(($page * $limit) > $nbtotalofrecords)    // if total resultset is smaller then paging size (filtering), goto and load page 0
{
    $page = 0;
    $offset = 0;
}
$sql .= $db->order($sortfield . ',rowid', $sortorder);
$sql .= $db->plimit($limit + 1, $offset);
$resql = $db->query($sql);
if(!empty($resql) && $db->num_rows($resql) > 0) {
    while($obj = $db->fetch_object($resql)) {
        $TLinesToDisplay[$obj->rowid] = $obj->rowid;
    }
}
$num = count($TLinesToDisplay);
/*
 * A présent on récupère le stock physique de chaque produit ainsi que le stock contenu dans chaque commande fourn
 */
$sql = "SELECT p.stock, p.rowid FROM " . MAIN_DB_PREFIX . "product as p WHERE rowid IN (" . implode(',', $TProductId) . ")";
$resql = $db->query($sql);

if(!empty($resql) && $db->num_rows($resql) > 0) {
    while($obj = $db->fetch_object($resql)) {
        $TProductStock[$obj->rowid] = array('stock' => $obj->stock);
    }
}

$sql = " SELECT cfd.qty, cfde." . getDolGlobalString('OF_DELIVERABILITY_REPORT_SUPPLIERORDER_DATE_EXTRAFIELD').", cfd.fk_product, cf.rowid FROM " . MAIN_DB_PREFIX . "commande_fournisseurdet as cfd";
$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "commande_fournisseurdet_extrafields as cfde ON (cfde.fk_object = cfd.rowid)";
$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "commande_fournisseur as cf ON (cf.rowid = cfd.fk_commande)";
$sql .= " WHERE cfd.fk_product IN (" . implode(',', $TProductId) . ") ";
$sql .= " AND cf.fk_statut NOT IN (" . CommandeFournisseur::STATUS_RECEIVED_COMPLETELY . ", " . CommandeFournisseur::STATUS_CANCELED . ", " . CommandeFournisseur::STATUS_CANCELED_AFTER_ORDER . ", " . CommandeFournisseur::STATUS_REFUSED . ")";
$sql .= " ORDER BY cfde." . getDolGlobalString('OF_DELIVERABILITY_REPORT_SUPPLIERORDER_DATE_EXTRAFIELD');

$resql = $db->query($sql);
if(!empty($resql) && $db->num_rows($resql) > 0) {
    while($obj = $db->fetch_object($resql)) {
//        $TProductStock[$obj->fk_product]['supplier_order']['total_from_supplier'] += $obj->qty;
        $TProductStock[$obj->fk_product]['supplier_order'][$obj->{getDolGlobalString('OF_DELIVERABILITY_REPORT_SUPPLIERORDER_DATE_EXTRAFIELD')}][$obj->rowid] += $obj->qty;
    }
}
//Recursively check if stock is enough
$TDetailStock = array();

foreach($TLines as $key => $line) {
    _getDetailStock($line, $TProductStock, $TDetailStock); //On met dans TDetailStock le détail du stock si on a assez ou pas
}

/*
 * VIEW
 */
llxHeader();

$param = '';
if ($limit > 0 && $limit != $conf->liste_limit) $param.='&limit='.urlencode($limit);
if ($search_cmd )             $param.='&search_cmd='.urlencode($search_cmd);
if ($search_alert_line )             $param.='&search_alert_line='.urlencode($search_alert_line);
if ($search_no_date )             $param.='&search_no_date='.urlencode($search_no_date);
if ($search_non_compliant )             $param.='&search_non_compliant='.urlencode($search_non_compliant);
if ($search_company)             $param.='&search_company='.urlencode($search_company);
if ($search_prod )             $param.='&search_prod='.urlencode($search_prod);
if ($search_of )             $param.='&search_of='.urlencode($search_of);
if ($search_qty != '')             $param.='&search_qty='.urlencode($search_qty);
if ($viewstatut != '')      $param.='&viewstatut='.urlencode($viewstatut);
if ($search_delivery_startday)   		$param.='&search_delivery_startday='.urlencode($search_delivery_startday);
if ($search_delivery_startmonth)   		$param.='&search_delivery_startmonth='.urlencode($search_delivery_startmonth);
if ($search_delivery_startyear)    		$param.='&search_delivery_startyear='.urlencode($search_delivery_startyear);
if ($search_delivery_endday)   		$param.='&search_delivery_endday='.urlencode($search_delivery_endday);
if ($search_delivery_endmonth)   		$param.='&search_delivery_endmonth='.urlencode($search_delivery_endmonth);
if ($search_delivery_endyear)    		$param.='&search_delivery_endyear='.urlencode($search_delivery_endyear);
if ($search_delivery_end)    		$param.='&search_delivery_end='.urlencode($search_delivery_end);
if ($search_delivery_start)    		$param.='&search_delivery_start='.urlencode($search_delivery_start);


print '<form method="POST" id="searchFormList" action="' . $_SERVER["PHP_SELF"] . '">';
print '<input type="hidden" name="token" value="' . $_SESSION['newtoken'] . '">';
print '<input type="hidden" name="formfilteraction" id="formfilteraction" value="list">';
print '<input type="hidden" name="action" value="list">';
print '<input type="hidden" name="sortfield" value="' . $sortfield . '">';
print '<input type="hidden" name="sortorder" value="' . $sortorder . '">';
print '<input type="hidden" name="page" value="' . $page . '">';
if (isset( $contextpage ))  print '<input type="hidden" name="contextpage" value="' .  $contextpage  . '">';
print '<input type="hidden" name="viewstatut" value="' . $viewstatut . '">';
if (isset( $socid )) print '<input type="hidden" name="socid" value="' . $socid . '">';

print_barre_liste($langs->trans('ShippablePrevReport'), $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, isset($massactionbutton) ? $massactionbutton :"", $num, $nbtotalofrecords, '', 0, isset($newcardbutton) ? $newcardbutton : "", '', $limit);

print '<div class="div-table-responsive">';
print '<table class="tagtable liste' . (isset($moreforfilter) ? " listwithfilterbefore" : "") . '">' . "\n";

print '<tr class="liste_titre_filter">';

print '<td class="liste_titre">';
print '<input class="flat" size="6" type="text" name="search_cmd" value="' . $search_cmd . '">';
print $form->textwithpicto('&nbsp;&nbsp;<input type="checkbox" name="search_alert_line"'.(!empty($search_alert_line)?'checked':'').' value="1">', $langs->trans('NotShippable'),1, 'warning');
print '</td>';
print '<td class="liste_titre">';
print '<input class="flat" size="6" type="text" name="search_company" value="' . $search_company . '">';
print '</td>';
print '<td class="liste_titre">';
print '<input class="flat" size="6" type="text" name="search_prod" value="' . $search_prod . '">';
print '</td>';
print '<td class="liste_titre">';
if(!empty($search_delivery_end)) {
    $search_delivery_end = str_replace('/', '-', $search_delivery_end);
    $search_delivery_end = strtotime($search_delivery_end);

} else $search_delivery_end=null;
if(!empty($search_delivery_start)) {
    $search_delivery_start = str_replace('/', '-', $search_delivery_start);
    $search_delivery_start = strtotime($search_delivery_start);
} else $search_delivery_start=null;
print $form->selectDate($search_delivery_start, 'search_delivery_start');
print $form->selectDate($search_delivery_end, 'search_delivery_end');
print $form->textwithpicto('&nbsp;&nbsp;<input type="checkbox" name="search_no_date"'.(!empty($search_no_date)?'checked':'').' value="1">', $langs->trans('LineWithoutDate'),1, 'warning');
print '</td>';
print '<td class="liste_titre maxwidthonsmartphone" align="right">';

print '</td>';
print '<td class="liste_titre">';
print '<input class="flat" size="6" type="text" name="search_of" value="' . $search_of . '">';
print '</td>';
print '<td  class="liste_titre"></td>';
print '<td  class="liste_titre"></td>';
print '<td  class="liste_titre"></td>';



print '<td class="liste_titre" align="right">';
print '<input class="flat" type="text" size="5" name="search_qty" value="' . dol_escape_htmltag($search_qty) . '">';
print '</td>';

print '<td  class="liste_titre"></td>';
print '<td  class="liste_titre" ></td>';
print '<td class="liste_titre" align="middle">';
print $form->textwithpicto('<input type="checkbox" name="search_non_compliant"'.(!empty($search_non_compliant)?'checked':'').' value="1">', $langs->trans('> 0'),1, 'warning');
print '</td>';

// Action column
print '<td class="liste_titre" align="middle">';
$searchpicto = $form->showFilterButtons();
print $searchpicto;
print '</td>';

print "</tr>\n";

// Fields title
print '<tr class="liste_titre">';
//TODO Faire fonctionner les print list field titre + tester les recherches
print_liste_field_titre($langs->trans('Ref'), $_SERVER["PHP_SELF"], 'ref', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('ThirdParty'), $_SERVER["PHP_SELF"], 'societe_nom', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('Product'), $_SERVER["PHP_SELF"], 'ref_prod', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('DeliveryDate'), $_SERVER["PHP_SELF"], "date_livraison", "", $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('Status'), $_SERVER["PHP_SELF"], 'statut_elem', '', $param, 'align="right"', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('OFAsset'), $_SERVER["PHP_SELF"], 'fk_assetOf', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('DateBesoin'), $_SERVER["PHP_SELF"], 'of_date_besoin', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('DateLaunch'), $_SERVER["PHP_SELF"], 'of_date_lancement', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('EstimatedMakeTime'), $_SERVER["PHP_SELF"], 'of_temps_estime_fabrication', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('Quantity'), $_SERVER["PHP_SELF"], 'line_qty', '', $param, 'align="right"', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('QtyToMake'), $_SERVER["PHP_SELF"], 'of_line_qty', '', $param, 'align="right"', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('ProduceQty'), $_SERVER["PHP_SELF"], 'of_line_qty_used', '', $param, 'align="right"', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('NonCompliant'), $_SERVER["PHP_SELF"], 'of_line_qty_non_compliant', '', $param, 'align="right"', $sortfield, $sortorder);
print_liste_field_titre('');
print '</tr>' . "\n";
/*
 * Lines
 */
foreach($TLinesToDisplay as $lineid) {
    if(!empty($search_alert_line) && ( !empty($TDetailStock[$lineid]['status']) || empty($TLines[$lineid]->of_date_de_livraison))) continue;
    if(!empty($TLines[$lineid])) {
        if($TLines[$lineid]->element == 'commandedet') {
            $parent = new Commande($db);
            $parent->fetch($TLines[$lineid]->fk_commande);
        }
        else if($TLines[$lineid]->element == 'propaldet'){
            $parent = new Propal($db);
            $parent->fetch($TLines[$lineid]->fk_propal);
        }

        $parent->fetch_thirdparty();
        $TLines[$lineid]->fetch_product();

        $icon = _getIconStatus($TDetailStock, $TLines, $lineid);

        $TAssetOF = array();
        $print_of='';
        if(!empty($TLines[$lineid]->fk_assetOf[0])) {
            foreach($TLines[$lineid]->fk_assetOf as $of_id) {
                $assetOF = new TAssetOF;
                $assetOF->load($PDOdb, $of_id);
                $TAssetOF[$of_id] = $assetOF;
                $print_of .= $assetOF->getNomUrl(1).'</br>';
            }
        }
        else $print_of = '';

        $TAssetOFLine = array();
        if(!empty($TLines[$lineid]->fk_assetOfLine)) {
            foreach($TLines[$lineid]->fk_assetOfLine as $fk_ofline) {
                $assetOFLine = new TAssetOFLine;
                $assetOFLine->load($PDOdb, $fk_ofline);
                $TAssetOFLine[$fk_ofline] = $assetOFLine;
            }
        }


        $stock_tooltip = '';
        if(!empty($TDetailStock[$lineid])) {
            _getPictoDetail($TDetailStock, $lineid, $stock_tooltip);
        }
    }

    print '<tr class="oddeven" >';

    print '<td nowrap>';
    print $parent->getNomUrl(1).'&nbsp;'.$icon;
    print '</td>';
    print '<td>';
    print $parent->thirdparty->getNomUrl(1);
    print '</td>';
    print '<td>';
    print $TLines[$lineid]->product->getNomUrl(1);
    print '</td>';
    print '<td>';
    if(!empty($TLines[$lineid]->of_date_de_livraison)) print date('d/m/Y', $TLines[$lineid]->of_date_de_livraison);
    else print  $langs->trans('WarningNoDate') . ' ' . img_picto($langs->trans('pictoNoDate'), 'warning');
    print '</td>';
    print '<td align="right">';
    print $parent->getLibStatut(2);
    print '</td>';
    print '<td>';
    print $print_of;
    print '</td>';
    print '<td>';
    if(!empty($TAssetOF)) {
        foreach($TAssetOF as $assetOf) {
            print !empty($assetOf->id) ? date('d/m/Y', $assetOf->date_besoin) : '';
            print '</br>';
        }
    }
    print '</td>';
    print '<td>';
    if(!empty($TAssetOF)) {
        foreach($TAssetOF as $assetOf) {
            if(!empty($assetOf->date_lancement)) print date( 'd/m/Y',$assetOf->date_lancement);
             else print '';


            print '</br>';
        }
    }
    print '</td>';
    print '<td>';
    if(!empty($TAssetOF)) {
        foreach($TAssetOF as $assetOf) {
            print $assetOf->temps_estime_fabrication.' '.$langs->trans('Hours');
            print '</br>';
        }
    }
    print '</td>';
    print '<td align="right">';

    print $form->textwithpicto($TLines[$lineid]->qty, $stock_tooltip);
    print '</td>';
    print '<td align="right">';
    if(!empty($TAssetOFLine)) {
        foreach($TAssetOFLine as $assetOFLine) {
            print $assetOFLine->qty;
            print '</br>';
        }
    }
    print '</td>';
    print '<td align="right">';
    if(!empty($TAssetOFLine)) {
        foreach($TAssetOFLine as $assetOFLine) {
            print $assetOFLine->qty_used;
            print '</br>';
        }
    }
    print '</td>';
    print '<td align="right">';
    if(!empty($TAssetOFLine)) {
        foreach($TAssetOFLine as $assetOFLine) {
    print $assetOFLine->qty_non_compliant;
    print '</br>';
}
}

    print '</td>';
    print '<td></td>';
    print '</tr>';
}

llxFooter();
