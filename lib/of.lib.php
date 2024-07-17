<?php

	function ofPrepareHead(&$asset,$type='assetOF') {
		global $user, $conf, $langs;

		$head=array();

		switch ($type) {

			case 'assetOF':
				$head= array(
				    array(dol_buildpath('/of/fiche_of.php?id='.$asset->getId(),1), $langs->trans('Card'),'fiche'),
                    array(dol_buildpath('/of/document.php?id='.$asset->getId(),1), $langs->trans('Documents'),'document'),
                );

				break;
		}

		$h = count($head);
		complete_head_from_modules($conf, $langs, $asset, $head, $h, 'of');

		return $head;

	}
	function ofAdminPrepareHead()
	{
	    global $langs, $conf;
	    $langs->load("of@of");
	    $h = 0;
	    $head = array();
	    $head[$h][0] = dol_buildpath("/of/admin/of_setup.php", 1);
	    $head[$h][1] = $langs->trans("Parameters");
	    $head[$h][2] = 'settings';
	    $h++;
	    $head[$h][0] = dol_buildpath("/of/admin/of_models.php", 1);
	    $head[$h][1] = $langs->trans("Models");
	    $head[$h][2] = 'models';
	    $h++;
	    $head[$h][0] = dol_buildpath("/of/admin/of_about.php", 1);
	    $head[$h][1] = $langs->trans("About");
	    $head[$h][2] = 'about';
	    $h++;

	    return $head;
	}

	function visu_checkbox_user(&$PDOdb, &$form, $group, $TUsers, $name, $status)
	{
		$include = array();
		$res = '';
		$sql = 'SELECT u.lastname, u.firstname, uu.fk_user, u.statut
		  FROM '.MAIN_DB_PREFIX.'usergroup_user uu INNER JOIN '.MAIN_DB_PREFIX.'user u ON (uu.fk_user = u.rowid)
		  WHERE uu.fk_usergroup = '.(int) $group;
		$PDOdb->Execute($sql);

		//Cette input doit être présent que si je suis en brouillon, si l'OF est lancé la présence de cette input va réinitialiser à vide les associations précédentes
		if ($status == 'DRAFT' && $form->type_aff == 'edit') {
		    $res = '<input checked="checked" style="display:none;" type="checkbox" name="'.$name.'" value="0" />';
        }

		while ($obj = $PDOdb->Get_line())
		{
				$label = $obj->lastname.' '.$obj->firstname;
				if($obj->statut == 0) {
					$label='<span style="text-decoration : line-through;">'.$label.'</span>';

					if(!in_array($obj->fk_user, $TUsers)) continue;

				}

				if ($status == 'DRAFT' || (in_array($obj->fk_user, $TUsers))) {
			    $res .= '<p style="margin:4px 0">'
			    		.$form->checkbox1($label, $name, $obj->fk_user, (in_array($obj->fk_user, $TUsers) ? true : false), ($status == 'DRAFT' ? 'style="vertical-align:text-bottom;"' : 'disabled="disabled" style="vertical-align:text-bottom;"'), '', '', 'case_after', array('no'=>'', 'yes'=>img_picto('', 'tick.png'))).'</p>';
            }
		}

		return $res;
	}

	/*Mode opératoire*/
	function visu_checkbox_task(&$PDOdb, &$form, $fk_workstation, $TTasks, $name, $status)
	{
		$include = array();
		$res = '';
		$sql = 'SELECT rowid, libelle FROM '.MAIN_DB_PREFIX.'asset_workstation_task WHERE fk_workstation = '.(int) $fk_workstation;
		$PDOdb->Execute($sql);

		//Cette input doit être présent que si je suis en brouillon, si l'OF est lancé la présence de cette input va réinitialiser à vide les associations précédentes
		if ($status == 'DRAFT' && $form->type_aff != 'edit') $res = '<input checked="checked" style="display:none;" type="checkbox" name="'.$name.'" value="0" />';
		while ($obj = $PDOdb->Get_line())
		{
			if ($status == 'DRAFT' && $form->type_aff == 'edit') {
				$res .= $form->checkbox1('', $name, $obj->rowid, (in_array($obj->rowid, $TTasks)), ($status == 'DRAFT' ? 'style="vertical-align:text-bottom;"' : 'disabled="disabled" style="vertical-align:text-bottom;"'));
			}

			if($status == 'DRAFT' || in_array($obj->rowid, $TTasks)) {
				$res.=$obj->libelle;
			}

			if(in_array($obj->rowid, $TTasks)) {
				$res.=img_picto('', 'tick.png');
			}

			$res.='<br />';

		}

		return $res;

	}

	function visu_project_task(&$db, $fk_project_task, $mode, $name)
	{
		global $langs;
		if (!$fk_project_task) return ' - ';

		require_once DOL_DOCUMENT_ROOT.'/projet/class/task.class.php';
		require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';

		$projectTask = new Task($db);
		$projectTask->fetch($fk_project_task);

		$link = $projectTask->getNomUrl(1,'withproject');
		//		$link = '<a href="'.DOL_URL_ROOT.'/projet/tasks/task.php?id='.$fk_project_task.'">'.img_picto('', 'object_projecttask.png').$projectTask->ref.'</a>';

		if ($projectTask->progress == 0) $imgStatus = img_picto($langs->trans('OFWaiting'), 'statut0.png');
		elseif ($projectTask->progress < 100) $imgStatus = img_picto($langs->trans('OFInProgress'), 'statut3.png');
		else $imgStatus = img_picto($langs->trans('OFFinish'), 'statut4.png');

		if ($mode == 'edit')
		{
			$formother = new FormOther($db);
			return $link.' - '.dol_print_date($projectTask->date_start).' - '.$formother->select_percent($projectTask->progress, $name).' '.$imgStatus;
		}
		else {
			return $link.' - '.dol_print_date($projectTask->date_start).' - '.$projectTask->progress.' % '.$imgStatus;
		}

	}

	/**
	 *  Override
	 * 	Return a combo box with list of units
	 *  For the moment, units labels are defined in measuring_units_string
	 *
	 *  @param	string		$name                Name of HTML field
	 *  @param  string		$measuring_style     Unit to show: weight, size, surface, volume
	 *  @param  string		$default             Force unit
	 * 	@param	int			$adddefault			Add empty unit called "Default"
	 * 	@return	void
	 */
	function custom_load_measuring_units($name='measuring_units', $measuring_style='', $default='0', $adddefault=0)
	{
		global $langs,$conf,$mysoc;
		$langs->load("other");

		$return='';

		$measuring_units=array();
		if ($measuring_style == 'weight') $measuring_units=array(-6=>1,-3=>1,0=>1,3=>1,99=>1);
		else if ($measuring_style == 'size') $measuring_units=array(-3=>1,-2=>1,-1=>1,0=>1,98=>1,99=>1);
        else if ($measuring_style == 'surface') $measuring_units=array(-6=>1,-4=>1,-2=>1,0=>1,98=>1,99=>1);
		else if ($measuring_style == 'volume') $measuring_units=array(-9=>1,-6=>1,-3=>1,0=>1,88=>1,89=>1,97=>1,99=>1,/* 98=>1 */);  // Liter is not used as already available with dm3
		else if ($measuring_style == 'unit') $measuring_units=array(0=>0);

		$return.= '<select class="flat" name="'.$name.'">';
		if ($adddefault) $return.= '<option value="0">'.$langs->trans("Default").'</option>';

		foreach ($measuring_units as $key => $value)
		{
			$return.= '<option value="'.$key.'"';
			if ($key == $default)
			{
				$return.= ' selected="selected"';
			}
			//$return.= '>'.$value.'</option>';
			if ($measuring_style == 'unit') $return.= '>'.$langs->trans('unit_s_').'</option>';
			else $return.= '>'.measuring_units_string($key,$measuring_style).'</option>';
		}
		$return.= '</select>';

		return $return;
	}

	/**
	 *	Override de la fonction classique de la class FormProject
	 *  Show a combo list with projects qualified for a third party
	 *
	 *	@param	int		$socid      	Id third party (-1=all, 0=only projects not linked to a third party, id=projects not linked or linked to third party id)
	 *	@param  int		$selected   	Id project preselected
	 *	@param  string	$htmlname   	Nom de la zone html
	 *	@param	int		$maxlength		Maximum length of label
	 *	@param	int		$option_only	Option only
	 *	@param	int		$show_empty		Add an empty line
	 *	@return string         		    select or options if OK, void if KO
	 */
	function custom_select_projects($socid=-1, $selected='', $htmlname='projectid', $type_aff = 'view', $maxlength=25, $option_only=0, $show_empty=1)
	{
		global $user,$conf,$langs,$db;

		require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';

		$out='';

		if ($type_aff == 'view')
		{
			if ($selected > 0)
			{
				$project = new Project($db);
				$project->fetch($selected);

				//return dol_trunc($project->ref,18).' - '.dol_trunc($project->title,$maxlength);

				$out .= $project->getNomUrl(1).' '.$project->getLibStatut(3);
				$projectTitle = dol_trunc($project->title,$maxlength);
				if(!empty($projectTitle) && !ctype_space($projectTitle)){
					$out .= ' - '.dol_trunc($project->title,$maxlength);
				}

				return $out;
			}
			else
			{
				return $out;
			}
		}

		if(DOL_VERSION>=6) {
			dol_include_once('/core/class/html.formprojet.class.php');
			$formProject=new FormProjets($db);
			return $formProject->select_projects($socid,$selected, $htmlname,32,0,1,0,0,0,0,'',1);
		}

		$hideunselectables = false;
		if (getDolGlobalInt('PROJECT_HIDE_UNSELECTABLES')) $hideunselectables = true;

		$projectsListId = false;
		if (!$user->hasRight('projet', 'all', 'lire'))
		{
			$projectstatic=new Project($db);
			$projectsListId = $projectstatic->getProjectsAuthorizedForUser($user,0,1);
		}

		// Search all projects
		$sql = 'SELECT p.rowid, p.ref, p.title, p.fk_soc, p.fk_statut, p.public';
		$sql.= ' FROM '.MAIN_DB_PREFIX .'projet as p';
		$sql.= " WHERE p.entity IN (".getEntity('project',1).")";
		if ($projectsListId !== false) $sql.= " AND p.rowid IN (".$projectsListId.")";
		if ($socid == 0) $sql.= " AND (p.fk_soc=0 OR p.fk_soc IS NULL)";
		if ($socid > 0)  $sql.= " AND (p.fk_soc=".$socid." OR p.fk_soc IS NULL)";
		$sql.= " ORDER BY p.ref ASC";


		$resql=$db->query($sql);
		if ($resql)
		{
			if (empty($option_only)) {
				$out.= '<select class="flat" name="'.$htmlname.'">';
			}
			if (!empty($show_empty)) {
				$out.= '<option value="0">&nbsp;</option>';
			}
			$num = $db->num_rows($resql);
			$i = 0;
			if ($num)
			{
				while ($i < $num)
				{
					$obj = $db->fetch_object($resql);
					// If we ask to filter on a company and user has no permission to see all companies and project is linked to another company, we hide project.
					if ($socid > 0 && (empty($obj->fk_soc) || $obj->fk_soc == $socid) && ! $user->hasRight('societe', 'lire'))
					{
						// Do nothing
					}
					else
					{
						$labeltoshow=dol_trunc($obj->ref,18);
						//if ($obj->public) $labeltoshow.=' ('.$langs->trans("SharedProject").')';
						//else $labeltoshow.=' ('.$langs->trans("Private").')';
						if (!empty($selected) && $selected == $obj->rowid /*&& $obj->fk_statut > 0*/)
						{
							$out.= '<option value="'.$obj->rowid.'" selected="selected">'.$labeltoshow.' - '.dol_trunc($obj->title,$maxlength).'</option>';
						}
						else
						{
							$disabled=0;
							$labeltoshow.=' '.dol_trunc($obj->title,$maxlength);
							if (! $obj->fk_statut > 0)
							{
								$disabled=1;
								$labeltoshow.=' - '.$langs->trans("Draft");
							}
							if ($socid > 0 && (! empty($obj->fk_soc) && $obj->fk_soc != $socid))
							{
								$disabled=1;
								$labeltoshow.=' - '.$langs->trans("LinkedToAnotherCompany");
							}

							if ($hideunselectables && $disabled)
							{
								$resultat='';
							}
							else
							{
								$resultat='<option value="'.$obj->rowid.'"';
								if ($disabled) $resultat.=' disabled="disabled"';
								//if ($obj->public) $labeltoshow.=' ('.$langs->trans("Public").')';
								//else $labeltoshow.=' ('.$langs->trans("Private").')';
								$resultat.='>';
								$resultat.=$labeltoshow;
								$resultat.='</option>';
							}
							$out.= $resultat;
						}
					}
					$i++;
				}
			}
			if (empty($option_only)) {
				$out.= '</select>';
			}

			if(isModEnabled('cliacropose')) { // TODO c'est naze, à refaire en utilisant la vraie autocompletion dispo depuis dolibarr 3.8 pour utiliser l'auto complete projets de doli si active (j'avais rajouté un script ajax/projects.php pour acropose)

				// Autocomplétion
				if(isset($selected)) {

					$p = new Project($db);
					$p->fetch($selected);
					$selected_value = $p->ref;

				}
				if(empty($htmlname))$htmlname='fk_project';
				$out = ajax_autocompleter($selected, $htmlname, DOL_URL_ROOT.'/projet/ajax/projects.php', $urloption, 1);
				$out .= '<input type="text" size="20" name="search_'.$htmlname.'" id="search_'.$htmlname.'" value="'.$selected_value.'"'.$placeholder.' />';

			}

			$db->free($resql);

			return $out;
		}
		else
		{
			dol_print_error($db);
			return '';
		}
	}


function _getArrayNomenclature(&$PDOdb, $TAssetOFLine=false, $fk_product=false)
{
	global $conf;

	dol_include_once("/of/class/ordre_fabrication_asset.class.php");

	$TRes = array();

	if (!isModEnabled('nomenclature')) return $TRes;

	include_once DOL_DOCUMENT_ROOT.'/custom/nomenclature/class/nomenclature.class.php';

	$fk_product = $TAssetOFLine->fk_product ? $TAssetOFLine->fk_product : $fk_product;

	$TNomen = TNomenclature::get($PDOdb, $fk_product);
	foreach ($TNomen as $TNomenclature)
	{
		$TRes[$TNomenclature->getId()] = !empty($TNomenclature->title) ? $TNomenclature->title : '(sans titre)';
	}

	return $TRes;
}

function _calcQtyOfProductInOf(&$db, &$conf, &$product)
{
	dol_include_once("/of/class/ordre_fabrication_asset.class.php");

	return TAssetOf::qtyFromOF($product->id);

}

function get_next_value_PDOdb(TPDOdb $db,$mask,$table,$field,$where='',$objsoc='',$date='',$mode='next', $bentityon=true, $objuser=null, $forceentity=null)
{
    global $conf,$user;

    if (! is_object($objsoc)) $valueforccc=$objsoc;
    else if ($table == "commande_fournisseur" || $table == "facture_fourn" ) $valueforccc=$objsoc->code_fournisseur;
    else $valueforccc=$objsoc->code_client;

    $sharetable = $table;
    if ($table == 'facture' || $table == 'invoice') $sharetable = 'invoicenumber'; // for getEntity function

    // Clean parameters
    if ($date == '') $date=dol_now();	// We use local year and month of PHP server to search numbers
    // but we should use local year and month of user

    // For debugging
    //dol_syslog("mask=".$mask, LOG_DEBUG);
    //include_once(DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php');
    //$mask='FA{yy}{mm}-{0000@99}';
    //$date=dol_mktime(12, 0, 0, 1, 1, 1900);
    //$date=dol_stringtotime('20130101');

    $hasglobalcounter=false;
    // Extract value for mask counter, mask raz and mask offset
    if (preg_match('/\{(0+)([@\+][0-9\-\+\=]+)?([@\+][0-9\-\+\=]+)?\}/i',$mask,$reg))
    {
        $masktri=$reg[1].(! empty($reg[2])?$reg[2]:'').(! empty($reg[3])?$reg[3]:'');
        $maskcounter=$reg[1];
        $hasglobalcounter=true;
    }
    else
    {
        // setting some defaults so the rest of the code won't fail if there is a third party counter
        $masktri='00000';
        $maskcounter='00000';
    }

    $maskraz=-1;
    $maskoffset=0;
    $resetEveryMonth=false;
    if (dol_strlen($maskcounter) < 3 && !getDolGlobalInt('MAIN_COUNTER_WITH_LESS_3_DIGITS')) return 'ErrorCounterMustHaveMoreThan3Digits';

    // Extract value for third party mask counter
    if (preg_match('/\{(c+)(0*)\}/i',$mask,$regClientRef))
    {
        $maskrefclient=$regClientRef[1].$regClientRef[2];
        $maskrefclient_maskclientcode=$regClientRef[1];
        $maskrefclient_maskcounter=$regClientRef[2];
        $maskrefclient_maskoffset=0; //default value of maskrefclient_counter offset
        $maskrefclient_clientcode=substr($valueforccc,0,dol_strlen($maskrefclient_maskclientcode));//get n first characters of client code where n is length in mask
        $maskrefclient_clientcode=str_pad($maskrefclient_clientcode,dol_strlen($maskrefclient_maskclientcode),"#",STR_PAD_RIGHT);//padding maskrefclient_clientcode for having exactly n characters in maskrefclient_clientcode
        $maskrefclient_clientcode=dol_string_nospecial($maskrefclient_clientcode);//sanitize maskrefclient_clientcode for sql insert and sql select like
        if (dol_strlen($maskrefclient_maskcounter) > 0 && dol_strlen($maskrefclient_maskcounter) < 3) return 'ErrorCounterMustHaveMoreThan3Digits';
    }
    else $maskrefclient='';

    // fail if there is neither a global nor a third party counter
    if (! $hasglobalcounter && ($maskrefclient_maskcounter == ''))
    {
        return 'ErrorBadMask';
    }

    // Extract value for third party type
    if (preg_match('/\{(t+)\}/i',$mask,$regType))
    {
        $masktype=$regType[1];
        $masktype_value=substr(preg_replace('/^TE_/','',$objsoc->typent_code),0,dol_strlen($regType[1]));// get n first characters of thirdpaty typent_code (where n is length in mask)
        $masktype_value=str_pad($masktype_value,dol_strlen($regType[1]),"#",STR_PAD_RIGHT);				 // we fill on right with # to have same number of char than into mask
    }
    else
    {
        $masktype='';
        $masktype_value='';
    }

    // Extract value for user
    if (preg_match('/\{(u+)\}/i',$mask,$regType))
    {
        $lastname = 'XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX';
        if (is_object($objuser)) $lastname = $objuser->lastname;

        $maskuser=$regType[1];
        $maskuser_value=substr($lastname,0,dol_strlen($regType[1]));// get n first characters of user firstname (where n is length in mask)
        $maskuser_value=str_pad($maskuser_value,dol_strlen($regType[1]),"#",STR_PAD_RIGHT);				 // we fill on right with # to have same number of char than into mask
    }
    else
    {
        $maskuser='';
        $maskuser_value='';
    }

    // Personalized field {XXX-1} à {XXX-9}
    $maskperso=array();
    $maskpersonew=array();
    $tmpmask=$mask;
    while (preg_match('/\{([A-Z]+)\-([1-9])\}/',$tmpmask,$regKey))
    {
        $maskperso[$regKey[1]]='{'.$regKey[1].'-'.$regKey[2].'}';
        $maskpersonew[$regKey[1]]=str_pad('', $regKey[2], '_', STR_PAD_RIGHT);
        $tmpmask=preg_replace('/\{'.$regKey[1].'\-'.$regKey[2].'\}/i', $maskpersonew[$regKey[1]], $tmpmask);
    }

    if (strstr($mask,'user_extra_'))
    {
        $start = "{user_extra_";
        $end = "\}";
        $extra= get_string_between($mask, "user_extra_", "}");
        if(!empty($user->array_options['options_'.$extra])){
            $mask =  preg_replace('#('.$start.')(.*?)('.$end.')#si', $user->array_options['options_'.$extra], $mask);
        }
    }
    $maskwithonlyymcode=$mask;
    $maskwithonlyymcode=preg_replace('/\{(0+)([@\+][0-9\-\+\=]+)?([@\+][0-9\-\+\=]+)?\}/i',$maskcounter,$maskwithonlyymcode);
    $maskwithonlyymcode=preg_replace('/\{dd\}/i','dd',$maskwithonlyymcode);
    $maskwithonlyymcode=preg_replace('/\{(c+)(0*)\}/i',$maskrefclient,$maskwithonlyymcode);
    $maskwithonlyymcode=preg_replace('/\{(t+)\}/i',$masktype_value,$maskwithonlyymcode);
    $maskwithonlyymcode=preg_replace('/\{(u+)\}/i',$maskuser_value,$maskwithonlyymcode);
    foreach($maskperso as $key => $val)
    {
        $maskwithonlyymcode=preg_replace('/'.preg_quote($val,'/').'/i', $maskpersonew[$key], $maskwithonlyymcode);
    }
    $maskwithnocode=$maskwithonlyymcode;
    $maskwithnocode=preg_replace('/\{yyyy\}/i','yyyy',$maskwithnocode);
    $maskwithnocode=preg_replace('/\{yy\}/i','yy',$maskwithnocode);
    $maskwithnocode=preg_replace('/\{y\}/i','y',$maskwithnocode);
    $maskwithnocode=preg_replace('/\{mm\}/i','mm',$maskwithnocode);
    // Now maskwithnocode = 0000ddmmyyyyccc for example
    // and maskcounter    = 0000 for example
    //print "maskwithonlyymcode=".$maskwithonlyymcode." maskwithnocode=".$maskwithnocode."\n<br>";
    //var_dump($reg);

    // If an offset is asked
    if (! empty($reg[2]) && preg_match('/^\+/',$reg[2])) $maskoffset=preg_replace('/^\+/','',$reg[2]);
    if (! empty($reg[3]) && preg_match('/^\+/',$reg[3])) $maskoffset=preg_replace('/^\+/','',$reg[3]);

    // Define $sqlwhere
    $sqlwhere='';
    $yearoffset=0;	// Use year of current $date by default
    $yearoffsettype=false;		// false: no reset, 0,-,=,+: reset at offset SOCIETE_FISCAL_MONTH_START, x=reset at offset x

    // If a restore to zero after a month is asked we check if there is already a value for this year.
    if (! empty($reg[2]) && preg_match('/^@/',$reg[2]))	$yearoffsettype = preg_replace('/^@/','',$reg[2]);
    if (! empty($reg[3]) && preg_match('/^@/',$reg[3]))	$yearoffsettype = preg_replace('/^@/','',$reg[3]);

    //print "yearoffset=".$yearoffset." yearoffsettype=".$yearoffsettype;
    if (is_numeric($yearoffsettype) && $yearoffsettype >= 1)
        $maskraz=$yearoffsettype; // For backward compatibility
    else if ($yearoffsettype === '0' || (! empty($yearoffsettype) && ! is_numeric($yearoffsettype) && getDolGlobalInt('SOCIETE_FISCAL_MONTH_START') > 1))
        $maskraz = getDolGlobalInt('SOCIETE_FISCAL_MONTH_START');
    //print "maskraz=".$maskraz;	// -1=no reset

    if ($maskraz > 0) {   // A reset is required
        if ($maskraz == 99) {
            $maskraz = date('m', $date);
            $resetEveryMonth = true;
        }
        if ($maskraz > 12) return 'ErrorBadMaskBadRazMonth';

        // Define posy, posm and reg
        if ($maskraz > 1)	// if reset is not first month, we need month and year into mask
        {
            if (preg_match('/^(.*)\{(y+)\}\{(m+)\}/i',$maskwithonlyymcode,$reg)) { $posy=2; $posm=3; }
            elseif (preg_match('/^(.*)\{(m+)\}\{(y+)\}/i',$maskwithonlyymcode,$reg)) { $posy=3; $posm=2; }
            else return 'ErrorCantUseRazInStartedYearIfNoYearMonthInMask';

            if (dol_strlen($reg[$posy]) < 2) return 'ErrorCantUseRazWithYearOnOneDigit';
        }
        else // if reset is for a specific month in year, we need year
        {
            if (preg_match('/^(.*)\{(m+)\}\{(y+)\}/i',$maskwithonlyymcode,$reg)) { $posy=3; $posm=2; }
            else if (preg_match('/^(.*)\{(y+)\}\{(m+)\}/i',$maskwithonlyymcode,$reg)) { $posy=2; $posm=3; }
            else if (preg_match('/^(.*)\{(y+)\}/i',$maskwithonlyymcode,$reg)) { $posy=2; $posm=0; }
            else return 'ErrorCantUseRazIfNoYearInMask';
        }
        // Define length
        $yearlen = $posy?dol_strlen($reg[$posy]):0;
        $monthlen = $posm?dol_strlen($reg[$posm]):0;
        // Define pos
        $yearpos = (dol_strlen($reg[1])+1);
        $monthpos = ($yearpos+$yearlen);
        if ($posy == 3 && $posm == 2) {		// if month is before year
            $monthpos = (dol_strlen($reg[1])+1);
            $yearpos = ($monthpos+$monthlen);
        }
        //print "xxx ".$maskwithonlyymcode." maskraz=".$maskraz." posy=".$posy." yearlen=".$yearlen." yearpos=".$yearpos." posm=".$posm." monthlen=".$monthlen." monthpos=".$monthpos." yearoffsettype=".$yearoffsettype." resetEveryMonth=".$resetEveryMonth."\n";

        // Define $yearcomp and $monthcomp (that will be use in the select where to search max number)
        $monthcomp=$maskraz;
        $yearcomp=0;

        if (! empty($yearoffsettype) && ! is_numeric($yearoffsettype) && $yearoffsettype != '=')	// $yearoffsettype is - or +
        {
            $currentyear=date("Y", $date);
            $fiscaldate=dol_mktime('0','0','0',$maskraz,'1',$currentyear);
            $newyeardate=dol_mktime('0','0','0','1','1',$currentyear);
            $nextnewyeardate=dol_mktime('0','0','0','1','1',$currentyear+1);
            //echo 'currentyear='.$currentyear.' date='.dol_print_date($date, 'day').' fiscaldate='.dol_print_date($fiscaldate, 'day').'<br>';

            // If after or equal of current fiscal date
            if ($date >= $fiscaldate)
            {
                // If before of next new year date
                if ($date < $nextnewyeardate && $yearoffsettype == '+') $yearoffset=1;
            }
            // If after or equal of current new year date
            else if ($date >= $newyeardate && $yearoffsettype == '-') $yearoffset=-1;
        }
        // For backward compatibility
        else if (date("m",$date) < $maskraz && empty($resetEveryMonth)) { $yearoffset=-1; }	// If current month lower that month of return to zero, year is previous year

        if ($yearlen == 4) $yearcomp=sprintf("%04d",date("Y",$date)+$yearoffset);
        elseif ($yearlen == 2) $yearcomp=sprintf("%02d",date("y",$date)+$yearoffset);
        elseif ($yearlen == 1) $yearcomp=substr(date("y",$date),2,1)+$yearoffset;
        if ($monthcomp > 1 && empty($resetEveryMonth))	// Test with month is useless if monthcomp = 0 or 1 (0 is same as 1) (regis: $monthcomp can't equal 0)
        {
            if ($yearlen == 4) $yearcomp1=sprintf("%04d",date("Y",$date)+$yearoffset+1);
            elseif ($yearlen == 2) $yearcomp1=sprintf("%02d",date("y",$date)+$yearoffset+1);

            $sqlwhere.="(";
            $sqlwhere.=" (SUBSTRING(".$field.", ".$yearpos.", ".$yearlen.") = '".$yearcomp."'";
            $sqlwhere.=" AND SUBSTRING(".$field.", ".$monthpos.", ".$monthlen.") >= '".str_pad($monthcomp, $monthlen, '0', STR_PAD_LEFT)."')";
            $sqlwhere.=" OR";
            $sqlwhere.=" (SUBSTRING(".$field.", ".$yearpos.", ".$yearlen.") = '".$yearcomp1."'";
            $sqlwhere.=" AND SUBSTRING(".$field.", ".$monthpos.", ".$monthlen.") < '".str_pad($monthcomp, $monthlen, '0', STR_PAD_LEFT)."') ";
            $sqlwhere.=')';
        }
        else if ($resetEveryMonth)
        {
            $sqlwhere.="(SUBSTRING(".$field.", ".$yearpos.", ".$yearlen.") = '".$yearcomp."'";
            $sqlwhere.=" AND SUBSTRING(".$field.", ".$monthpos.", ".$monthlen.") = '".str_pad($monthcomp, $monthlen, '0', STR_PAD_LEFT)."')";
        }
        else   // reset is done on january
        {
            $sqlwhere.='(SUBSTRING('.$field.', '.$yearpos.', '.$yearlen.") = '".$yearcomp."')";
        }
    }
    //print "sqlwhere=".$sqlwhere." yearcomp=".$yearcomp."<br>\n";	// sqlwhere and yearcomp defined only if we ask a reset
    //print "masktri=".$masktri." maskcounter=".$maskcounter." maskraz=".$maskraz." maskoffset=".$maskoffset."<br>\n";

    // Define $sqlstring
    if (function_exists('mb_strrpos'))
    {
        $posnumstart=mb_strrpos($maskwithnocode,$maskcounter,0,'UTF-8');
    }
    else
    {
        $posnumstart=strrpos($maskwithnocode,$maskcounter);
    }	// Pos of counter in final string (from 0 to ...)
    if ($posnumstart < 0) return 'ErrorBadMaskFailedToLocatePosOfSequence';
    $sqlstring='SUBSTRING('.$field.', '.($posnumstart+1).', '.dol_strlen($maskcounter).')';

    // Define $maskLike
    $maskLike = dol_string_nospecial($mask);
    $maskLike = str_replace("%","_",$maskLike);
    // Replace protected special codes with matching number of _ as wild card caracter
    $maskLike = preg_replace('/\{yyyy\}/i','____',$maskLike);
    $maskLike = preg_replace('/\{yy\}/i','__',$maskLike);
    $maskLike = preg_replace('/\{y\}/i','_',$maskLike);
    $maskLike = preg_replace('/\{mm\}/i','__',$maskLike);
    $maskLike = preg_replace('/\{dd\}/i','__',$maskLike);
    $maskLike = str_replace(dol_string_nospecial('{'.$masktri.'}'),str_pad("",dol_strlen($maskcounter),"_"),$maskLike);
    if ($maskrefclient) $maskLike = str_replace(dol_string_nospecial('{'.$maskrefclient.'}'),str_pad("",dol_strlen($maskrefclient),"_"),$maskLike);
    if ($masktype) $maskLike = str_replace(dol_string_nospecial('{'.$masktype.'}'),$masktype_value,$maskLike);
    if ($maskuser) $maskLike = str_replace(dol_string_nospecial('{'.$maskuser.'}'),$maskuser_value,$maskLike);
    foreach($maskperso as $key => $val)
    {
        $maskLike = str_replace(dol_string_nospecial($maskperso[$key]),$maskpersonew[$key],$maskLike);
    }

    // Get counter in database
    $counter=0;
    $sql = "SELECT MAX(".$sqlstring.") as val";
    $sql.= " FROM ".MAIN_DB_PREFIX.$table;
    $sql.= " WHERE ".$field." LIKE '".$maskLike."'";
    $sql.= " AND ".$field." NOT LIKE '(PROV%)'";
    if ($bentityon) // only if entity enable
        $sql.= " AND entity IN (".getEntity($sharetable).")";
    else if (! empty($forceentity))
        $sql.= " AND entity IN (".$forceentity.")";
    if ($where) $sql.=$where;
    if ($sqlwhere) $sql.=' AND '.$sqlwhere;

    //print $sql.'<br>';
    dol_syslog("functions2::get_next_value mode=".$mode."", LOG_DEBUG);
    $resql=$db->Execute($sql);
    if ($resql)
    {
        $obj = $db->Get_line();
        $counter = $obj->val;
    }
    else dol_print_error($db);

    // Check if we must force counter to maskoffset
    if (empty($counter)) $counter=$maskoffset;
    else if (preg_match('/[^0-9]/i',$counter))
    {
        $counter=0;
        dol_syslog("Error, the last counter found is '".$counter."' so is not a numeric value. We will restart to 1.", LOG_ERR);
    }
    else if ($counter < $maskoffset && !getDolGlobalInt('MAIN_NUMBERING_OFFSET_ONLY_FOR_FIRST')) $counter=$maskoffset;

    if ($mode == 'last')	// We found value for counter = last counter value. Now need to get corresponding ref of invoice.
    {
        $counterpadded=str_pad($counter,dol_strlen($maskcounter),"0",STR_PAD_LEFT);

        // Define $maskLike
        $maskLike = dol_string_nospecial($mask);
        $maskLike = str_replace("%","_",$maskLike);
        // Replace protected special codes with matching number of _ as wild card caracter
        $maskLike = preg_replace('/\{yyyy\}/i','____',$maskLike);
        $maskLike = preg_replace('/\{yy\}/i','__',$maskLike);
        $maskLike = preg_replace('/\{y\}/i','_',$maskLike);
        $maskLike = preg_replace('/\{mm\}/i','__',$maskLike);
        $maskLike = preg_replace('/\{dd\}/i','__',$maskLike);
        $maskLike = str_replace(dol_string_nospecial('{'.$masktri.'}'),$counterpadded,$maskLike);
        if ($maskrefclient) $maskLike = str_replace(dol_string_nospecial('{'.$maskrefclient.'}'),str_pad("",dol_strlen($maskrefclient),"_"),$maskLike);
        if ($masktype) $maskLike = str_replace(dol_string_nospecial('{'.$masktype.'}'),$masktype_value,$maskLike);
        if ($maskuser) $maskLike = str_replace(dol_string_nospecial('{'.$maskuser.'}'),$maskuser_value,$maskLike);

        $ref='';
        $sql = "SELECT ".$field." as ref";
        $sql.= " FROM ".MAIN_DB_PREFIX.$table;
        $sql.= " WHERE ".$field." LIKE '".$maskLike."'";
        $sql.= " AND ".$field." NOT LIKE '%PROV%'";
        if ($bentityon) // only if entity enable
            $sql.= " AND entity IN (".getEntity($sharetable).")";
        else if (! empty($forceentity))
            $sql.= " AND entity IN (".$forceentity.")";
        if ($where) $sql.=$where;
        if ($sqlwhere) $sql.=' AND '.$sqlwhere;

        dol_syslog("functions2::get_next_value mode=".$mode."", LOG_DEBUG);
        $resql=$db->query($sql);
        if ($resql)
        {
            $obj = $db->fetch_object($resql);
            if ($obj) $ref = $obj->ref;
        }
        else dol_print_error($db);

        $numFinal=$ref;
    }
    else if ($mode == 'next')
    {
        $counter++;

        // If value for $counter has a length higher than $maskcounter chars
        if ($counter >= pow(10, dol_strlen($maskcounter)))
        {
            $counter='ErrorMaxNumberReachForThisMask';
        }

        if (! empty($maskrefclient_maskcounter))
        {
            //print "maskrefclient_maskcounter=".$maskrefclient_maskcounter." maskwithnocode=".$maskwithnocode." maskrefclient=".$maskrefclient."\n<br>";

            // Define $sqlstring
            $maskrefclient_posnumstart=strpos($maskwithnocode,$maskrefclient_maskcounter,strpos($maskwithnocode,$maskrefclient));	// Pos of counter in final string (from 0 to ...)
            if ($maskrefclient_posnumstart <= 0) return 'ErrorBadMask';
            $maskrefclient_sqlstring='SUBSTRING('.$field.', '.($maskrefclient_posnumstart+1).', '.dol_strlen($maskrefclient_maskcounter).')';
            //print "x".$sqlstring;

            // Define $maskrefclient_maskLike
            $maskrefclient_maskLike = dol_string_nospecial($mask);
            $maskrefclient_maskLike = str_replace("%","_",$maskrefclient_maskLike);
            // Replace protected special codes with matching number of _ as wild card caracter
            $maskrefclient_maskLike = str_replace(dol_string_nospecial('{yyyy}'),'____',$maskrefclient_maskLike);
            $maskrefclient_maskLike = str_replace(dol_string_nospecial('{yy}'),'__',$maskrefclient_maskLike);
            $maskrefclient_maskLike = str_replace(dol_string_nospecial('{y}'),'_',$maskrefclient_maskLike);
            $maskrefclient_maskLike = str_replace(dol_string_nospecial('{mm}'),'__',$maskrefclient_maskLike);
            $maskrefclient_maskLike = str_replace(dol_string_nospecial('{dd}'),'__',$maskrefclient_maskLike);
            $maskrefclient_maskLike = str_replace(dol_string_nospecial('{'.$masktri.'}'),str_pad("",dol_strlen($maskcounter),"_"),$maskrefclient_maskLike);
            $maskrefclient_maskLike = str_replace(dol_string_nospecial('{'.$maskrefclient.'}'),$maskrefclient_clientcode.str_pad("",dol_strlen($maskrefclient_maskcounter),"_"),$maskrefclient_maskLike);

            // Get counter in database
            $maskrefclient_counter=0;
            $maskrefclient_sql = "SELECT MAX(".$maskrefclient_sqlstring.") as val";
            $maskrefclient_sql.= " FROM ".MAIN_DB_PREFIX.$table;
            //$sql.= " WHERE ".$field." not like '(%'";
            $maskrefclient_sql.= " WHERE ".$field." LIKE '".$maskrefclient_maskLike."'";
            if ($bentityon) // only if entity enable
                $maskrefclient_sql.= " AND entity IN (".getEntity($sharetable).")";
            else if (! empty($forceentity))
                $sql.= " AND entity IN (".$forceentity.")";
            if ($where) $maskrefclient_sql.=$where; //use the same optional where as general mask
            if ($sqlwhere) $maskrefclient_sql.=' AND '.$sqlwhere; //use the same sqlwhere as general mask
            $maskrefclient_sql.=' AND (SUBSTRING('.$field.', '.(strpos($maskwithnocode,$maskrefclient)+1).', '.dol_strlen($maskrefclient_maskclientcode).")='".$maskrefclient_clientcode."')";

            dol_syslog("functions2::get_next_value maskrefclient", LOG_DEBUG);
            $maskrefclient_resql=$db->query($maskrefclient_sql);
            if ($maskrefclient_resql)
            {
                $maskrefclient_obj = $db->fetch_object($maskrefclient_resql);
                $maskrefclient_counter = $maskrefclient_obj->val;
            }
            else dol_print_error($db);

            if (empty($maskrefclient_counter) || preg_match('/[^0-9]/i',$maskrefclient_counter)) $maskrefclient_counter=$maskrefclient_maskoffset;
            $maskrefclient_counter++;
        }

        // Build numFinal
        $numFinal = $mask;

        // We replace special codes except refclient
        if (! empty($yearoffsettype) && ! is_numeric($yearoffsettype) && $yearoffsettype != '=')	// yearoffsettype is - or +, so we don't want current year
        {
            $numFinal = preg_replace('/\{yyyy\}/i',date("Y",$date)+$yearoffset, $numFinal);
            $numFinal = preg_replace('/\{yy\}/i',  date("y",$date)+$yearoffset, $numFinal);
            $numFinal = preg_replace('/\{y\}/i',   substr(date("y",$date),1,1)+$yearoffset, $numFinal);
        }
        else	// we want yyyy to be current year
        {
            $numFinal = preg_replace('/\{yyyy\}/i',date("Y",$date), $numFinal);
            $numFinal = preg_replace('/\{yy\}/i',  date("y",$date), $numFinal);
            $numFinal = preg_replace('/\{y\}/i',   substr(date("y",$date),1,1), $numFinal);
        }
        $numFinal = preg_replace('/\{mm\}/i',  date("m",$date), $numFinal);
        $numFinal = preg_replace('/\{dd\}/i',  date("d",$date), $numFinal);

        // Now we replace the counter
        $maskbefore='{'.$masktri.'}';
        $maskafter=str_pad($counter,dol_strlen($maskcounter),"0",STR_PAD_LEFT);
        //print 'x'.$maskbefore.'-'.$maskafter.'y';
        $numFinal = str_replace($maskbefore,$maskafter,$numFinal);

        // Now we replace the refclient
        if ($maskrefclient)
        {
            //print "maskrefclient=".$maskrefclient." maskwithonlyymcode=".$maskwithonlyymcode." maskwithnocode=".$maskwithnocode."\n<br>";
            $maskrefclient_maskbefore='{'.$maskrefclient.'}';
            $maskrefclient_maskafter=$maskrefclient_clientcode.str_pad($maskrefclient_counter,dol_strlen($maskrefclient_maskcounter),"0",STR_PAD_LEFT);
            $numFinal = str_replace($maskrefclient_maskbefore,$maskrefclient_maskafter,$numFinal);
        }

        // Now we replace the type
        if ($masktype)
        {
            $masktype_maskbefore='{'.$masktype.'}';
            $masktype_maskafter=$masktype_value;
            $numFinal = str_replace($masktype_maskbefore,$masktype_maskafter,$numFinal);
        }

        // Now we replace the user
        if ($maskuser)
        {
            $maskuser_maskbefore='{'.$maskuser.'}';
            $maskuser_maskafter=$maskuser_value;
            $numFinal = str_replace($maskuser_maskbefore,$maskuser_maskafter,$numFinal);
        }
    }

    dol_syslog("functions2::get_next_value return ".$numFinal,LOG_DEBUG);
    return $numFinal;
}

function of_banner(TAssetOF $object) {
    global $langs, $conf, $db;
    $PDOdb = new TPDOdb;
    $soc = new Societe($db);
    $proj = new Project($db);
    print '<div class="OFMaster" assetOf_id="'.$object->getId().'">

			<table width="100%" class="border">

				<tr><td width="20%">'.$langs->transnoentitiesnoconv('NumberOf').'</td><td>'.$object->getNumero($PDOdb).'</td></tr>
				<tr rel="ordre">
					<td>'.$langs->transnoentities('Ordre').'</td>
					<td>'.$langs->trans($object->ordre).'</td>
				</tr>';
    if(!empty($object->fk_soc)){
        $soc->fetch($object->fk_soc);
        print '<tr rel="customer">
					<td>' . $langs->transnoentities('Customer') . '</td>
					<td>' . $soc->getNomUrl(1) . '</td>
				</tr>';
    }
    if(!empty($object->fk_project)) {
        $proj->fetch($object->fk_project);
        print '<tr rel="customer">
					<td>' . $langs->transnoentities('Project') . '</td>
					<td>' . $proj->getNomUrl(1) . '</td>
				</tr>';
    }
	print '</table>
			</div></br>	'

;



}

function _getProductIdFromNomen(&$TProductId, $details_nomenclature)
{
    foreach($details_nomenclature as $detail){
        if(!empty($detail['childs'])) _getProductIdFromNomen($TProductId, $detail['childs']);
        $TProductId[$detail['fk_product']]=$detail['fk_product'];
    }
}

function _getDetailStock(&$line, &$TProductStock, &$TDetails)
{
    global $conf;
    if(empty($line->of_date_de_livraison))return -3;
    $qtyToDestock = $line->qty;

/*
 * 1st step on verif si stock physique is enough
 */
    if(isset($TProductStock[$line->fk_product]['stock'])){
        $TDetails[$line->id]['stock_reel'] = $TProductStock[$line->fk_product]['stock'];

        if($qtyToDestock < $TProductStock[$line->fk_product]['stock']){
            $TProductStock[$line->fk_product]['stock']-= $qtyToDestock;
            $qtyToDestock=0;
        }
        else{
            $qtyToDestock -= $TProductStock[$line->fk_product]['stock'];
            $TProductStock[$line->fk_product]['stock']= 0;
        }

    }


/*
 * 2nd step on verif si on peut compenser le manque avec les prochaines cmd fourn
 */
    if($qtyToDestock > 0 && !empty($TProductStock[$line->fk_product]['supplier_order'])){
        foreach($TProductStock[$line->fk_product]['supplier_order'] as $date => $stock_by_order) {
            if($qtyToDestock <= 0) break; // La quantité totale est trouvée

            if(!empty($date) && !empty($line->of_date_de_livraison)){
                $tms_fourn = strtotime($date);
                if($tms_fourn < $line->of_date_de_livraison) {
                    foreach($stock_by_order as $fk_order => $stock) {
                        $TDetails[$line->id]['supplier_order'][$fk_order] += $TProductStock[$line->fk_product]['supplier_order'][$date][$fk_order];

                        if($qtyToDestock < $TProductStock[$line->fk_product]['supplier_order'][$date][$fk_order]){
                            $TProductStock[$line->fk_product]['supplier_order'][$date][$fk_order] -= $qtyToDestock;
                            $qtyToDestock=0;
                        }
                        else{
                            $qtyToDestock -= $TProductStock[$line->fk_product]['supplier_order'][$date][$fk_order];
                            $TProductStock[$line->fk_product]['supplier_order'][$date][$fk_order] = 0;
                        }
                    }
                }
            }
        }
    }
/*
 * 3rd step : Si on a toujours pas de quoi fournir le client, on vérifie si on a de quoi créer les produits et que le délai est ok
 */
    if($qtyToDestock > 0 && !empty($line->details_nomenclature)){
        $isNomenOK = 0;
        foreach($line->details_nomenclature as $detail){

            $isNomenOK = _getDetailFromNomenclature($detail, $TProductStock, $TDetails[$line->id], $line->of_date_de_livraison, $qtyToDestock);
            if($isNomenOK < 0) break;
        }
    }
    if($qtyToDestock<=0 || $isNomenOK > 0)$TDetails[$line->id]['status'] = 1;
}


function _getDetailFromNomenclature($details_nomenclature, &$TProductStock, &$TDetails, $date_de_livraison, $qtyToDestock){

    $qtyToDestock = $details_nomenclature['qty'] * $qtyToDestock;
    /*
     * 1st step on verif si stock physique is enough
     */
    if(!empty($TProductStock[$details_nomenclature['fk_product']]['stock'])){
        $TDetails['childs'][$details_nomenclature['fk_product']]['stock_reel'] = $qtyToDestock .'/'.$TProductStock[$details_nomenclature['fk_product']]['stock'];

        if($qtyToDestock < $TProductStock[$details_nomenclature['fk_product']]['stock']){
            $TProductStock[$details_nomenclature['fk_product']]['stock']-= $qtyToDestock;
            $qtyToDestock=0;
        }
        else{
            $qtyToDestock -= $TProductStock[$details_nomenclature['fk_product']]['stock'];
            $TProductStock[$details_nomenclature['fk_product']]['stock']= 0;
        }

    }


    /*
     * 2nd step on verif si on peut compenser le manque avec les prochaines cmd fourn
     */
    if($qtyToDestock > 0 && !empty($TProductStock[$details_nomenclature['fk_product']]['supplier_order'])){
        foreach($TProductStock[$details_nomenclature['fk_product']]['supplier_order'] as $date => $stock_by_order) {
            if($qtyToDestock <= 0) break; // La quantité totale est trouvée

            if(!empty($date) && !empty($date_de_livraison)){
                $qtyToDisplay = $qtyToDestock;
                $tms_fourn = strtotime($date);
                if($tms_fourn < $date_de_livraison) {
                    foreach($stock_by_order as $fk_order => $stock) {
                        $TDetails['childs'][$details_nomenclature['fk_product']]['supplier_order'][$fk_order] += $TProductStock[$details_nomenclature['fk_product']]['supplier_order'][$date][$fk_order];

                        if($qtyToDestock < $TProductStock[$details_nomenclature['fk_product']]['supplier_order'][$date][$fk_order]){
                            $TProductStock[$details_nomenclature['fk_product']]['supplier_order'][$date][$fk_order] -= $qtyToDestock;
                            $qtyToDestock=0;
                        }
                        else{
                            $qtyToDestock -= $TProductStock[$details_nomenclature['fk_product']]['supplier_order'][$date][$fk_order];
                            $TProductStock[$details_nomenclature['fk_product']]['supplier_order'][$date][$fk_order] = 0;
                        }
                    }
                    $TDetails['childs'][$details_nomenclature['fk_product']]['supplier_order'][$fk_order] = $qtyToDisplay .'/'.$TDetails['childs'][$details_nomenclature['fk_product']]['supplier_order'][$fk_order];
                }
            }
        }
    }
    /*
     * 3rd step : Si on a toujours pas de quoi fournir le client, on vérifie si on a de quoi créer les produits et que le délai est ok
     */
    if($qtyToDestock > 0 && !empty($details_nomenclature['childs'])){
        $isNomenOK = 0;
        foreach($details_nomenclature['childs'] as $detail){
            $isNomenOK = _getDetailFromNomenclature($detail, $TProductStock, $TDetails['childs'][$details_nomenclature['fk_product']], $date_de_livraison, $qtyToDestock);
            if($isNomenOK < 0) break;
        }
    }
    if($qtyToDestock<=0 || $isNomenOK > 0){
        $TDetails['childs'][$details_nomenclature['fk_product']]['status'] = 1;
        return 1;
    }
    return -1;

}

function _getPictoDetail($TDetailStock, $lineid, &$stock_tooltip, $level = 1) {
    global $langs, $db;
    $nbsp = '';
    for($i = 1; $i < $level; $i++) $nbsp .= '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
    $is_null = 1;
    if(!empty($TDetailStock[$lineid])) {
        foreach($TDetailStock[$lineid] as $type => $detail) {

            if($type == 'stock_reel') {
                $stock_tooltip .= $nbsp . $langs->trans('PhysicalStock') . ' : ' . $detail . '</br>';
                $is_null = 0;
            }

            if($type == 'supplier_order') {

                $stock_tooltip .= $nbsp . $langs->trans('SupplierOrder') . ' : </br>';
                foreach($detail as $fk_supplier_order => $stock) {
                    $fourncmd = new CommandeFournisseur($db);
                    $fourncmd->fetch($fk_supplier_order);
                    $stock_tooltip .= '&nbsp;&nbsp;&nbsp;&nbsp;' .$nbsp . $fourncmd->getNomUrl(1) . ' ==> ' . $stock . '</br>';
                }
                $is_null = 0;
            }

            if($type == 'childs') {
                $stock_tooltip .= $nbsp . $langs->trans('Nomenclature') . ' : </br>';
                foreach($detail as $fk_product => $TDetails) {
                    $prod = new Product($db);
                    $prod->fetch($fk_product);
                    $stock_tooltip .= '&nbsp;&nbsp;&nbsp;&nbsp;' . $nbsp . $prod->getNomUrl(1) . ' : </br>';
                    _getPictoDetail($detail, $fk_product, $stock_tooltip, $level + 1);
                }
                $is_null = 0;
            }
        }
    }
    if(!empty($is_null)) $stock_tooltip .= $nbsp.'Pas de stock';
}

function _getIconStatus($TDetailStock, $TLines, $lineid) {
	global $conf;
	$style = ' border-radius: 50%;
	    width: 20px;
        height: 20px;
        display: inline-block;';
    if(!empty($TDetailStock[$lineid]['status'])) $style .= 'background:#8DDE8D;';
    else if(empty($TLines[$lineid]->of_date_de_livraison)) $style .= 'background:#dedb8d;';
    else $style .= 'background:#de8d8d;';

    $icon = '<div class="shippable_status" style="'.$style.'"></div>';

    return $icon;
}

/**
 * @param OrderLine $line
 */
function getOFForLine($line)
{
	global $conf, $db;

	$TOF = array();

	$sql = "SELECT DISTINCT ofe.rowid";

	$sql.=" FROM ".MAIN_DB_PREFIX."assetOf as ofe
			LEFT JOIN ".MAIN_DB_PREFIX."assetOf_line ofel ON (ofel.fk_assetOf=ofe.rowid AND ofel.type = 'TO_MAKE')
			LEFT JOIN ".MAIN_DB_PREFIX."product p ON (p.rowid = ofel.fk_product)
			LEFT JOIN ".MAIN_DB_PREFIX."societe s ON (s.rowid = ofe.fk_soc)";

	if(getDolGlobalInt('OF_MANAGE_ORDER_LINK_BY_LINE')) $sql.=" LEFT JOIN ".MAIN_DB_PREFIX."commandedet cd ON (cd.rowid=ofel.fk_commandedet) ";

	$sql.="  WHERE ofe.entity=".$conf->entity;

	if(getDolGlobalInt('OF_MANAGE_ORDER_LINK_BY_LINE')) {

			$sql.=" AND ofel.fk_commandedet = ".$line->id." AND ofe.fk_assetOf_parent = 0 ";

	}
	else $sql.=" AND ofe.fk_commande=".$line->fk_commande." AND ofe.fk_assetOf_parent = 0 AND ofel.fk_product = ".$line->fk_product;

	$sql.=" GROUP BY ofe.rowid ";

	$resql = $db->query($sql);
	if ($resql)
	{
		if ($db->num_rows($resql))
		{
			if(!class_exists('TPDOdb')) { // fix fatal error
				if(!defined('INC_FROM_DOLIBARR')){ define('INC_FROM_DOLIBARR', 1); } // Normalement si on est là sans cette class c'est vraiment qu'il ne s'agit
				require_once __DIR__ . "/../config.php";
			}

			$pdo = new TPDOdb;
			dol_include_once('/of/class/ordre_fabrication_asset.class.php');

			while ($obj = $db->fetch_object($resql))
			{
				$of = new TAssetOF;
				$res = $of->load($pdo, $obj->rowid);
				if ($res)
				{
					$TOF[] = $of->getNomUrl();
				}

			}

		}
	}

	return $TOF;
}
