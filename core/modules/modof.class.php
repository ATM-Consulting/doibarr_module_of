<?php
/* Copyright (C) 2003      Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2012 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012 Regis Houssin        <regis.houssin@capnetworks.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * 	\defgroup   of     Module of
 *  \brief      Example of a module descriptor.
 *				Such a file must be copied into htdocs/of/core/modules directory.
 *  \file       htdocs/of/core/modules/modof.class.php
 *  \ingroup    of
 *  \brief      Description and activation file for module of
 */
include_once DOL_DOCUMENT_ROOT .'/core/modules/DolibarrModules.class.php';


/**
 *  Description and activation class for module of
 */
class modof extends DolibarrModules
{
	/**
	 *   Constructor. Define names, constants, directories, boxes, permissions
	 *
	 *   @param      DoliDB		$db      Database handler
	 */
	function __construct($db)
	{
        global $langs,$conf;

        $this->db = $db;

		// Id for module (must be unique).
		// Use here a free id (See in Home -> System information -> Dolibarr for list of used modules id).
		$this->editor_name = 'ATM Consulting';
		$this->editor_url = 'https://www.atm-consulting.fr';
		$this->numero = 104161; // 104000 to 104999 for ATM CONSULTING
		// Key text used to identify module (for permissions, menus, etc...)
		$this->rights_class = 'of';

		// Family can be 'crm','financial','hr','projects','products','ecm','technic','other'
		// It is used to group modules in module setup page
		$this->family = "ATM Consulting - GPAO";
		// Module label (no space allowed), used if translation string 'ModuleXXXName' not found (where XXX is value of numeric property 'numero' of module)
		$this->name = preg_replace('/^mod/i','',get_class($this));
		// Module description, used if translation string 'ModuleXXXDesc' not found (where XXX is value of numeric property 'numero' of module)
		$this->description = "Ordres de fabrication: management of manufacturing orders";
		// Possible values for version are: 'development', 'experimental', 'dolibarr' or version
		$this->version = '2.15.0';
		// Key used in llx_const table to save module status enabled/disabled (where MYMODULE is value of property name of module in uppercase)
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		// Where to store the module in setup page (0=common,1=interface,2=others,3=very specific)
		$this->special = 0;
		// Name of image file used for this module.
		// If file is in theme/yourtheme/img directory under name object_pictovalue.png, use this->picto='pictovalue'
		// If file is in module/img directory under name object_pictovalue.png, use this->picto='pictovalue@module'
		$this->picto='of@of';

		// Defined all module parts (triggers, login, substitutions, menus, css, etc...)
		// for default path (eg: /of/core/xxxxx) (0=disable, 1=enable)
		// for specific path of parts (eg: /of/core/modules/barcode)
		// for specific css file (eg: /of/css/of.css.php)
		//$this->module_parts = array(
		//                        	'triggers' => 0,                                 	// Set this to 1 if module has its own trigger directory (core/triggers)
		//							'login' => 0,                                    	// Set this to 1 if module has its own login method directory (core/login)
		//							'substitutions' => 0,                            	// Set this to 1 if module has its own substitution function file (core/substitutions)
		//							'menus' => 0,                                    	// Set this to 1 if module has its own menus handler directory (core/menus)
		//							'theme' => 0,                                    	// Set this to 1 if module has its own theme directory (theme)
		//                        	'tpl' => 0,                                      	// Set this to 1 if module overwrite template dir (core/tpl)
		//							'barcode' => 0,                                  	// Set this to 1 if module has its own barcode directory (core/modules/barcode)
		//							'models' => 0,                                   	// Set this to 1 if module has its own models directory (core/modules/xxx)
		//							'css' => array('/of/css/of.css.php'),	// Set this to relative path of css file if module has its own css file
	 	//							'js' => array('/of/js/of.js'),          // Set this to relative path of js file if module must load a js on all pages
		//							'hooks' => array('hookcontext1','hookcontext2')  	// Set here all hooks context managed by module
		//							'dir' => array('output' => 'othermodulename'),      // To force the default directories names
		//							'workflow' => array('WORKFLOW_MODULE1_YOURACTIONTYPE_MODULE2'=>array('enabled'=>'isModEnabled('module1') && isModEnabled('module2')', 'picto'=>'yourpicto@of')) // Set here all workflow context managed by module
		//                        );
		$this->module_parts = array(
			'triggers' => 1,
			'hooks'=>array(
				'ordersuppliercard',
				'productstock',
				'searchform',
				'tasklist',
				'ordercard',
				'propalcard',
				'invoicecard',
				'expeditioncard',
				'orderlist',
				'propallist',
				'invoicelist',
				'pdfgeneration',
				'productdao',
				'stockproductcard',
				'stocktransfercard'
			),
			'models' => 1,
			'dir' => array('output' => 'of')
		);

		// Data directories to create when module is enabled.
		// Example: this->dirs = array("/of/temp");
		$this->dirs = array('/of/template');

		// Config pages. Put here list of php page, stored into of/admin directory, to use to setup module.
		$this->config_page_url = array("of_setup.php@of");

		// Dependencies
		$this->hidden = false;			// A condition to hide module
		$this->depends = array();		// List of modules id that must be enabled if this module is enabled
		$this->requiredby = array();	// List of modules id to disable if this one is disabled
		$this->conflictwith = array();	// List of modules id this module is in conflict with
		$this->phpmin = array(7,0);					// Minimum version of PHP required by module
		$this->need_dolibarr_version = array(15,0);	// Minimum version of Dolibarr required by module
		$this->langfiles = array("of@of");

		// Constants
		// List of particular constants to add when module is enabled (key, 'chaine', value, desc, visible, 'current' or 'allentities', deleteonunactive)
		// Example: $this->const=array(0=>array('MYMODULE_MYNEWCONST1','chaine','myvalue','This is a constant to add',1),
		//                             1=>array('MYMODULE_MYNEWCONST2','chaine','myvalue','This is another constant to add',0, 'current', 1)
		// );
		$this->const = array(
			array('TEMPLATE_OF','chaine','templateOF.odt',"Template à utiliser",1)
			//Pour afficher la sélection d'un équipement dans une liste déroulante lors de l'ajout d'une ligne de commande
			,array('ASSET_MANUAL_WAREHOUSE', 'chaine',0,'Ventiller manuellement les entrepôts sur l\'OF',1)
			,array('ASSET_USE_DEFAULT_WAREHOUSE', 'chaine',0,'Précise si le comportement du destockage/stockage doit prendre l\'entrepôt de la configuration ou celui défini sur chaque équipement',1)
			,array('ASSET_DEFAULT_WAREHOUSE_ID_TO_MAKE',0,'Identifiant de l\'entrepôt pour gérer le stock via les OF (produits à fabriquer)',1)
			,array('ASSET_DEFAULT_WAREHOUSE_ID_NEEDED',0,'Identifiant de l\'entrepôt pour gérer le stock via les OF (produits nécessaires)',1)
			,array('USE_LOT_IN_OF', 'chaine', 0,'Utiliser la gestion des lots dans OF',1)
			,array('ASSET_USE_PROJECT_TASK', 'chaine', 0,'Chaque poste de travail associé à un OF créera une tâche au projet associé',1)
			,array('ASSET_DEFINED_USER_BY_WORKSTATION', 'chaine', 0,'Permettre l\'association d\'un ou plusieurs utilisateurs d\'être assigné à un poste de travail sur un OF',1)
			,array('ASSET_DEFINED_OPERATION_BY_WORKSTATION', 'chaine', 0,'Permet de définir un protocole opératoire pour chaque poste de travail',1)
			,array('ASSET_DEFINED_WORKSTATION_BY_NEEDED', 'chaine', 0,'Permet de ventiler les produits de composition par poste de travail',1)
		);


		// Array to add new pages in new tabs
		// Example: $this->tabs = array('objecttype:+tabname1:Title1:mylangfile@of:$user->hasRight('of', 'read'):/of/mynewtab1.php?id=__ID__',  	// To add a new tab identified by code tabname1
        //                              'objecttype:+tabname2:Title2:mylangfile@of:$user->hasRight('othermodule', 'read'):/of/mynewtab2.php?id=__ID__',  	// To add another new tab identified by code tabname2
        //                              'objecttype:-tabname:NU:conditiontoremove');                                                     						// To remove an existing tab identified by code tabname
		// where objecttype can be
		// 'categories_x'	  to add a tab in category view (replace 'x' by type of category (0=product, 1=supplier, 2=customer, 3=member)
		// 'contact'          to add a tab in contact view
		// 'contract'         to add a tab in contract view
		// 'group'            to add a tab in group view
		// 'intervention'     to add a tab in intervention view
		// 'invoice'          to add a tab in customer invoice view
		// 'invoice_supplier' to add a tab in supplier invoice view
		// 'member'           to add a tab in fundation member view
		// 'opensurveypoll'	  to add a tab in opensurvey poll view
		// 'order'            to add a tab in customer order view
		// 'order_supplier'   to add a tab in supplier order view
		// 'payment'		  to add a tab in payment view
		// 'payment_supplier' to add a tab in supplier payment view
		// 'product'          to add a tab in product view
		// 'propal'           to add a tab in propal view
		// 'project'          to add a tab in project view
		// 'stock'            to add a tab in stock view
		// 'thirdparty'       to add a tab in third party view
		// 'user'             to add a tab in user view
        $this->tabs = array(
			'product:+tabOF2:OF:of@of:$user->hasRight("of","of","lire"):/of/liste_of.php?fk_product=__ID__'
			,'order:+tabOF3:OF:of@of:$user->hasRight("of","of","lire"):/of/liste_of.php?fk_commande=__ID__'

		);

        // Dictionaries
	    if (! isModEnabled('of'))
        {
        	$conf->of=new stdClass();
        	$conf->of->enabled=0;
        }
		$this->dictionaries=array();
        /* Example:
        if (! isModEnabled('of')) $conf->of->enabled=0;	// This is to avoid warnings
        $this->dictionaries=array(
            'langs'=>'mylangfile@of',
            'tabname'=>array(MAIN_DB_PREFIX."table1",MAIN_DB_PREFIX."table2",MAIN_DB_PREFIX."table3"),		// List of tables we want to see into dictonnary editor
            'tablib'=>array("Table1","Table2","Table3"),													// Label of tables
            'tabsql'=>array('SELECT f.rowid as rowid, f.code, f.label, f.active FROM '.MAIN_DB_PREFIX.'table1 as f','SELECT f.rowid as rowid, f.code, f.label, f.active FROM '.MAIN_DB_PREFIX.'table2 as f','SELECT f.rowid as rowid, f.code, f.label, f.active FROM '.MAIN_DB_PREFIX.'table3 as f'),	// Request to select fields
            'tabsqlsort'=>array("label ASC","label ASC","label ASC"),																					// Sort order
            'tabfield'=>array("code,label","code,label","code,label"),																					// List of fields (result of select to show dictionary)
            'tabfieldvalue'=>array("code,label","code,label","code,label"),																				// List of fields (list of fields to edit a record)
            'tabfieldinsert'=>array("code,label","code,label","code,label"),																			// List of fields (list of fields for insert)
            'tabrowid'=>array("rowid","rowid","rowid"),																									// Name of columns with primary key (try to always name it 'rowid')
            'tabcond'=>array(isModEnabled('of'),isModEnabled('of'),isModEnabled('of'))												// Condition to show each dictionary
        );
        */

        // Boxes
		// Add here list of php file(s) stored in core/boxes that contains class to show a box.
        $this->boxes = array();			// List of boxes
		// Example:
		//$this->boxes=array(array(0=>array('file'=>'myboxa.php','note'=>'','enabledbydefaulton'=>'Home'),1=>array('file'=>'myboxb.php','note'=>''),2=>array('file'=>'myboxc.php','note'=>'')););

		// Permissions
		$this->rights = array();		// Permission array used by this module
		$r=0;

		$this->rights = array();		// Permission array used by this module
		$r=0;

		$r++;
		$this->rights[$r][0] = $this->numero+$r;
		$this->rights[$r][1] = 'Lire les Ordres de fabrication';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'of';
		$this->rights[$r][5] = 'lire';

		$r++;
		$this->rights[$r][0] = $this->numero+$r;
		$this->rights[$r][1] = 'Créer des Ordres de fabrication';
		$this->rights[$r][3] =0;
		$this->rights[$r][4] = 'of';
		$this->rights[$r][5] = 'write';


		$r++;
		$this->rights[$r][0] = $this->numero+$r;
		$this->rights[$r][1] = 'Générer les documents';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'read';

		$r++;
		$this->rights[$r][0] = $this->numero+$r;
		$this->rights[$r][1] = 'Voir le coût d\'un OF';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'of';
		$this->rights[$r][5] = 'price';

		$r++;
		$this->rights[$r][0] = $this->numero+$r;
		$this->rights[$r][1] = 'Voir le temps prévus sur un OF';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'of';
		$this->rights[$r][5] = 'show_ws_time';

		$r++;
		$this->rights[$r][0] = $this->numero+$r;
		$this->rights[$r][1] = 'Autoriser la suppression d\'un OF à l\'état "Terminé"';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'of';
		$this->rights[$r][5] = 'allow_delete_of_finish';


		// Main menu entries
		$this->menu = array();			// List of menus to add
		$r=0;

		$langs->load('of@of'); // load lang file to translate menu

		// Main menu entries
		$this->menus = array();			// List of menus to add
		$r=0;


		$this->menu[$r]=array('fk_menu'=>0,			// Put 0 if this is a top menu
				'type'=>'top',			// This is a Top menu entry
				'titre'=>'GPAO',
				'mainmenu'=>'of',
				'leftmenu'=>'',		// Use 1 if you also want to add left menu entries using this descriptor. Use 0 if left menu entries are defined in a file pre.inc.php (old school).
				'url'=>'/of/liste_of.php',
				'langs'=>'of@of',
				'position'=>100,
				'enabled'=>'$user->hasRight("of","of","lire")',			// Define condition to show or hide menu entry. Use 'isModEnabled('mymodule')' if entry must be visible if module is enabled.
				'perms'=>'$user->hasRight("of","of","lire")',			// Use 'perms'=>'$user->hasRight('mymodule', 'level1', 'level2')' if you want your menu with a permission rules
				'target'=>'',
				'user'=>2);				// 0=Menu for internal users, 1=external users, 2=both
		$r++;



		/***/


		$this->menu[$r]=array(	'fk_menu'=>'fk_mainmenu=of',			// Put 0 if this is a top menu
					'type'=>'left',			// This is a Top menu entry
					'titre'=>'AssetProductionOrder',
					'mainmenu'=>'of',
					'leftmenu'=>'assetOFlist',
					'url'=>'/of/liste_of.php',
					'position'=>300,
					'enabled'=>'',			// Define condition to show or hide menu entry. Use 'isModEnabled('mymodule')' if entry must be visible if module is enabled.
					'perms'=>'$user->hasRight("of","of","lire")',			// Use 'perms'=>'$user->hasRight('mymodule', 'level1', 'level2')' if you want your menu with a permission rules
					'target'=>'',
					'user'=>2);				// 0=Menu for internal users, 1=external users, 2=both
		$r++;

        $this->menu[$r]=array(  'fk_menu'=>'fk_mainmenu=of,fk_leftmenu=assetOFlist',         // Put 0 if this is a top menu
                    'type'=>'left',         // This is a Top menu entry
                    'titre'=>'AssetProductionOrderDraft',
                    'mainmenu'=>'of',
                    'leftmenu'=>'',
                    'url'=>'/of/liste_of.php?search_status_of=DRAFT',
                    'position'=>310+$r,
                    'enabled'=>'',           // Define condition to show or hide menu entry. Use 'isModEnabled('mymodule')' if entry must be visible if module is enabled.
                    'perms'=>'$user->hasRight("of","of","lire")',          // Use 'perms'=>'$user->hasRight('mymodule', 'level1', 'level2')' if you want your menu with a permission rules
                    'target'=>'',
                    'user'=>2);             // 0=Menu for internal users, 1=external users, 2=both
        $r++;

        $this->menu[$r]=array(  'fk_menu'=>'fk_mainmenu=of,fk_leftmenu=assetOFlist',         // Put 0 if this is a top menu
                    'type'=>'left',         // This is a Top menu entry
                    'titre'=>'AssetProductionOrderNEEDOFFER',
                    'mainmenu'=>'of',
                    'leftmenu'=>'',
                    'url'=>'/of/liste_of.php?search_status_of=NEEDOFFER',
                    'position'=>310+$r,
                    'enabled'=>'',            // Define condition to show or hide menu entry. Use 'isModEnabled('mymodule')' if entry must be visible if module is enabled.
                    'perms'=>'$user->hasRight("of","of","lire")',          // Use 'perms'=>'$user->hasRight('mymodule', 'level1', 'level2')' if you want your menu with a permission rules
                    'target'=>'',
                    'user'=>2);             // 0=Menu for internal users, 1=external users, 2=both
        $r++;

        $this->menu[$r]=array(  'fk_menu'=>'fk_mainmenu=of,fk_leftmenu=assetOFlist',         // Put 0 if this is a top menu
                    'type'=>'left',         // This is a Top menu entry
                    'titre'=>'AssetProductionOrderONORDER',
                    'mainmenu'=>'of',
                    'leftmenu'=>'AssetProdSOrder',
                    'url'=>'/of/liste_of.php?search_status_of=ONORDER',
                    'position'=>310+$r,
                    'enabled'=>'',            // Define condition to show or hide menu entry. Use 'isModEnabled('mymodule')' if entry must be visible if module is enabled.
                    'perms'=>'$user->hasRight("of","of","lire")',          // Use 'perms'=>'$user->hasRight('mymodule', 'level1', 'level2')' if you want your menu with a permission rules
                    'target'=>'',
                    'user'=>2);             // 0=Menu for internal users, 1=external users, 2=both
        $r++;


        $this->menu[$r]=array(  'fk_menu'=>'fk_mainmenu=of,fk_leftmenu=AssetProdSOrder',         // Put 0 if this is a top menu
        		'type'=>'left',         // This is a Top menu entry
        		'titre'=>'AssetProductionSupplierOrder',
        		'mainmenu'=>'of',
        		'leftmenu'=>'AssetProductionOrderONORDER',
        		'url'=>'/of/liste_of.php?mode=supplier_order',
        		'position'=>310+$r,
        		'enabled'=>'',            // Define condition to show or hide menu entry. Use 'isModEnabled('mymodule')' if entry must be visible if module is enabled.
        		'perms'=>'$user->hasRight("of","of","lire")',          // Use 'perms'=>'$user->hasRight('mymodule', 'level1', 'level2')' if you want your menu with a permission rules
        		'target'=>'',
        		'user'=>2);             // 0=Menu for internal users, 1=external users, 2=both
        $r++;


        $this->menu[$r]=array(  'fk_menu'=>'fk_mainmenu=of,fk_leftmenu=assetOFlist',         // Put 0 if this is a top menu
                    'type'=>'left',         // This is a Top menu entry
                    'titre'=>'AssetProductionOrderVALID',
                    'mainmenu'=>'of',
                    'leftmenu'=>'',
                    'url'=>'/of/liste_of.php?search_status_of=VALID',
                    'position'=>310+$r,
                    'enabled'=>'',            // Define condition to show or hide menu entry. Use 'isModEnabled('mymodule')' if entry must be visible if module is enabled.
                    'perms'=>'$user->hasRight("of","of","lire")',          // Use 'perms'=>'$user->hasRight('mymodule', 'level1', 'level2')' if you want your menu with a permission rules
                    'target'=>'',
                    'user'=>2);             // 0=Menu for internal users, 1=external users, 2=both
        $r++;

        $this->menu[$r]=array(  'fk_menu'=>'fk_mainmenu=of,fk_leftmenu=assetOFlist',         // Put 0 if this is a top menu
                    'type'=>'left',         // This is a Top menu entry
                    'titre'=>'AssetProductionOrderOPEN',
                    'mainmenu'=>'of',
                    'leftmenu'=>'',
                    'url'=>'/of/liste_of.php?search_status_of=OPEN',
                    'position'=>310+$r,
                    'enabled'=>'',            // Define condition to show or hide menu entry. Use 'isModEnabled('mymodule')' if entry must be visible if module is enabled.
                    'perms'=>'$user->hasRight("of","of","lire")',          // Use 'perms'=>'$user->hasRight('mymodule', 'level1', 'level2')' if you want your menu with a permission rules
                    'target'=>'',
                    'user'=>2);             // 0=Menu for internal users, 1=external users, 2=both
        $r++;
        $this->menu[$r]=array(  'fk_menu'=>'fk_mainmenu=of,fk_leftmenu=assetOFlist',         // Put 0 if this is a top menu
                    'type'=>'left',         // This is a Top menu entry
                    'titre'=>'AssetProductionOrderCLOSE',
                    'mainmenu'=>'of',
                    'leftmenu'=>'',
                    'url'=>'/of/liste_of.php?search_status_of=CLOSE',
                    'position'=>310+$r,
                    'enabled'=>'',            // Define condition to show or hide menu entry. Use 'isModEnabled('mymodule')' if entry must be visible if module is enabled.
                    'perms'=>'$user->hasRight("of","of","lire")',          // Use 'perms'=>'$user->hasRight('mymodule', 'level1', 'level2')' if you want your menu with a permission rules
                    'target'=>'',
                    'user'=>2);             // 0=Menu for internal users, 1=external users, 2=both
        $r++;
        $this->menu[$r]=array(  'fk_menu'=>'fk_mainmenu=of,fk_leftmenu=assetOFlist',         // Put 0 if this is a top menu
                    'type'=>'left',         // This is a Top menu entry
                    'titre'=>'AssetProductionOrderNONCOMPLIANT',
                    'mainmenu'=>'of',
                    'leftmenu'=>'',
                    'url'=>'/of/liste_of.php?mode=non_compliant',
                    'position'=>310+$r,
                    'enabled'=>'getDolGlobalInt("OF_MANAGE_NON_COMPLIANT")',            // Define condition to show or hide menu entry. Use 'isModEnabled('mymodule')' if entry must be visible if module is enabled.
                    'perms'=>'$user->hasRight("of","of","lire")',          // Use 'perms'=>'$user->hasRight('mymodule', 'level1', 'level2')' if you want your menu with a permission rules
                    'target'=>'',
                    'user'=>2);             // 0=Menu for internal users, 1=external users, 2=both
        $r++;

		$this->menu[$r]=array(	'fk_menu'=>'fk_mainmenu=of,fk_leftmenu=assetOFlist',			// Put 0 if this is a top menu
					'type'=>'left',			// This is a Top menu entry
					'titre'=>'AssetNewProductionOrder',
					'mainmenu'=>'of',
					'leftmenu'=>'',
					'url'=>'/of/fiche_of.php?action=new',
					'position'=>310+$r,
					'enabled'=>'',			// Define condition to show or hide menu entry. Use 'isModEnabled('mymodule')' if entry must be visible if module is enabled.
					'perms'=>'$user->hasRight("of","of","lire")',			// Use 'perms'=>'$user->hasRight('mymodule', 'level1', 'level2')' if you want your menu with a permission rules
					'target'=>'',
					'user'=>2);				// 0=Menu for internal users, 1=external users, 2=both
		$r++;

        $this->menu[$r]=array(	'fk_menu'=>'fk_mainmenu=of,fk_leftmenu=assetOFlist',			// Put 0 if this is a top menu
                                  'type'=>'left',			// This is a Top menu entry
                                  'titre'=>'ShippablePrevReport',
                                  'mainmenu'=>'of',
                                  'leftmenu'=>'',
                                  'url'=>'/of/shipable_prev.php',
                                  'position'=>310+$r,
                                  'enabled'=>'',			// Define condition to show or hide menu entry. Use 'isModEnabled('mymodule')' if entry must be visible if module is enabled.
                                  'perms'=>'$user->hasRight("of","of","lire")',			// Use 'perms'=>'$user->hasRight('mymodule', 'level1', 'level2')' if you want your menu with a permission rules
                                  'target'=>'',
                                  'user'=>2);				// 0=Menu for internal users, 1=external users, 2=both
        $r++;


		// Add here entries to declare new menus
		//
		// Example to declare a new Top Menu entry and its Left menu entry:
		// $this->menu[$r]=array(	'fk_menu'=>0,			                // Put 0 if this is a top menu
		//							'type'=>'top',			                // This is a Top menu entry
		//							'titre'=>'of top menu',
		//							'mainmenu'=>'of',
		//							'leftmenu'=>'of',
		//							'url'=>'/of/pagetop.php',
		//							'langs'=>'mylangfile@of',	        // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
		//							'position'=>100,
		//							'enabled'=>'isModEnabled('of')',	// Define condition to show or hide menu entry. Use 'isModEnabled('of')' if entry must be visible if module is enabled.
		//							'perms'=>'1',			                // Use 'perms'=>'$user->hasRight('of', 'level1', 'level2')' if you want your menu with a permission rules
		//							'target'=>'',
		//							'user'=>2);				                // 0=Menu for internal users, 1=external users, 2=both
		// $r++;
		//
		// Example to declare a Left Menu entry into an existing Top menu entry:
		// $this->menu[$r]=array(	'fk_menu'=>'fk_mainmenu=xxx',		    // Use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
		//							'type'=>'left',			                // This is a Left menu entry
		//							'titre'=>'of left menu',
		//							'mainmenu'=>'xxx',
		//							'leftmenu'=>'of',
		//							'url'=>'/of/pagelevel2.php',
		//							'langs'=>'mylangfile@of',	        // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
		//							'position'=>100,
		//							'enabled'=>'isModEnabled('of')',  // Define condition to show or hide menu entry. Use 'isModEnabled('of')' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
		//							'perms'=>'1',			                // Use 'perms'=>'$user->hasRight('of', 'level1', 'level2')' if you want your menu with a permission rules
		//							'target'=>'',
		//							'user'=>2);				                // 0=Menu for internal users, 1=external users, 2=both
		// $r++;


		// Exports
		$r=1;

		// Example:
		// $this->export_code[$r]=$this->rights_class.'_'.$r;
		// $this->export_label[$r]='CustomersInvoicesAndInvoiceLines';	// Translation key (used only if key ExportDataset_xxx_z not found)
        // $this->export_enabled[$r]='1';                               // Condition to show export in list (ie: '$user->id==3'). Set to 1 to always show when module is enabled.
		// $this->export_permission[$r]=array(array("facture","facture","export"));
		// $this->export_fields_array[$r]=array('s.rowid'=>"IdCompany",'s.nom'=>'CompanyName','s.address'=>'Address','s.zip'=>'Zip','s.town'=>'Town','s.fk_pays'=>'Country','s.phone'=>'Phone','s.siren'=>'ProfId1','s.siret'=>'ProfId2','s.ape'=>'ProfId3','s.idprof4'=>'ProfId4','s.code_compta'=>'CustomerAccountancyCode','s.code_compta_fournisseur'=>'SupplierAccountancyCode','f.rowid'=>"InvoiceId",'f.facnumber'=>"InvoiceRef",'f.datec'=>"InvoiceDateCreation",'f.datef'=>"DateInvoice",'f.total'=>"TotalHT",'f.total_ttc'=>"TotalTTC",'f.tva'=>"TotalVAT",'f.paye'=>"InvoicePaid",'f.fk_statut'=>'InvoiceStatus','f.note'=>"InvoiceNote",'fd.rowid'=>'LineId','fd.description'=>"LineDescription",'fd.price'=>"LineUnitPrice",'fd.tva_tx'=>"LineVATRate",'fd.qty'=>"LineQty",'fd.total_ht'=>"LineTotalHT",'fd.total_tva'=>"LineTotalTVA",'fd.total_ttc'=>"LineTotalTTC",'fd.date_start'=>"DateStart",'fd.date_end'=>"DateEnd",'fd.fk_product'=>'ProductId','p.ref'=>'ProductRef');
		// $this->export_entities_array[$r]=array('s.rowid'=>"company",'s.nom'=>'company','s.address'=>'company','s.zip'=>'company','s.town'=>'company','s.fk_pays'=>'company','s.phone'=>'company','s.siren'=>'company','s.siret'=>'company','s.ape'=>'company','s.idprof4'=>'company','s.code_compta'=>'company','s.code_compta_fournisseur'=>'company','f.rowid'=>"invoice",'f.facnumber'=>"invoice",'f.datec'=>"invoice",'f.datef'=>"invoice",'f.total'=>"invoice",'f.total_ttc'=>"invoice",'f.tva'=>"invoice",'f.paye'=>"invoice",'f.fk_statut'=>'invoice','f.note'=>"invoice",'fd.rowid'=>'invoice_line','fd.description'=>"invoice_line",'fd.price'=>"invoice_line",'fd.total_ht'=>"invoice_line",'fd.total_tva'=>"invoice_line",'fd.total_ttc'=>"invoice_line",'fd.tva_tx'=>"invoice_line",'fd.qty'=>"invoice_line",'fd.date_start'=>"invoice_line",'fd.date_end'=>"invoice_line",'fd.fk_product'=>'product','p.ref'=>'product');
		// $this->export_sql_start[$r]='SELECT DISTINCT ';
		// $this->export_sql_end[$r]  =' FROM ('.MAIN_DB_PREFIX.'facture as f, '.MAIN_DB_PREFIX.'facturedet as fd, '.MAIN_DB_PREFIX.'societe as s)';
		// $this->export_sql_end[$r] .=' LEFT JOIN '.MAIN_DB_PREFIX.'product as p on (fd.fk_product = p.rowid)';
		// $this->export_sql_end[$r] .=' WHERE f.fk_soc = s.rowid AND f.rowid = fd.fk_facture';
		// $this->export_sql_order[$r] .=' ORDER BY s.nom';
		// $r++;
	}

	/**
	 *		Function called when module is enabled.
	 *		The init function add constants, boxes, permissions and menus (defined in constructor) into Dolibarr database.
	 *		It also creates data directories
	 *
     *      @param      string	$options    Options when enabling module ('', 'noboxes')
	 *      @return     int             	1 if OK, 0 if KO
	 */
	function init($options='')
	{
		global $user;

		$sql = array();

		if(!defined('INC_FROM_DOLIBARR')) define('INC_FROM_DOLIBARR',true);

		dol_include_once('/of/config.php');
		dol_include_once('/of/script/create-maj-base.php');

		$result=$this->_load_tables('/of/sql/');

		dol_include_once('/core/class/extrafields.class.php');
        $extrafields=new ExtraFields($this->db);
        $res = $extrafields->addExtraField('fk_of', 'Ordre de Fabrication', 'sellist', 0, '', 'projet_task',0,0,'',serialize(array('options'=>array('assetOf:numero:rowid'=>null))));
        $res = $extrafields->addExtraField('fk_product', 'Produit à fabriquer', 'sellist', 0, '', 'projet_task',0,0,'',serialize(array('options'=>array('product:label:rowid'=>null))));
        $res = $extrafields->addExtraField('of_check_prev', 'A prendre en compte pour le prévisionnel de production', 'boolean', 0, '', 'propal',0,0,'','');
        $res = $extrafields->addExtraField('fk_of', 'ID de l\'OF lié', 'int', 0, 10, 'stocktransfer_stocktransfer', 0, 0, '', unserialize('a:1:{s:7:"options";a:1:{s:0:"";N;}}'), 0, '', 0, '');
        $res = $extrafields->addExtraField('linked_of', 'OF lié', 'html', 0, 2000, 'stocktransfer_stocktransfer', 0, 0, '', unserialize('a:1:{s:7:"options";a:1:{s:0:"";N;}}'), 0, '', 5, '');

		foreach (array('commandedet', 'propaldet', 'facturedet', 'expeditiondet') as $elementtype) {
			$res = $extrafields->addExtraField(
				'reflinenumber',
				'RefLineNumber',
				'varchar',
				100,
				'128',
				$elementtype,
				0,
				0,
				'',
				array('options'=>array(''=>null)),
				0,
				'',
				'0',
				'',
				'',
				'',
				'',
				0
			);
		}

		// template
		$src=dol_buildpath('/of/exempleTemplate/templateOF.odt');
		$dirodt=DOL_DATA_ROOT.'/of/template/';
		$dest=$dirodt.'/templateOF.odt';

		if (file_exists($src) && ! file_exists($dest))
		{
			require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
			dol_mkdir($dirodt);
			$result=dol_copy($src,$dest,0,0);
			if ($result < 0)
			{
				global $langs;
				$langs->load("errors");
				$this->error=$langs->trans('ErrorFailToCopyFile',$src,$dest);
				return 0;
			}
		}

		$TCron = array(array(
				'label' => 'Stockage encours OFs',
				'jobtype' => 'method',
				'frequency' => 1,
				'unitfrequency' => 86400,
				'status' => 1,
				'module_name' => 'of',
				'classesname' => 'of/class/of_amount.class.php',
				'objectname' => 'AssetOFAmounts',
				'methodename' => 'stockCurrentAmount',
				'params' => '',
				'datestart' => time(),
				'datenextrun' => time(),
		));

		dol_include_once('/cron/class/cronjob.class.php');

		foreach($TCron as $cronvalue) {

			$req = "
				SELECT rowid
				FROM " . MAIN_DB_PREFIX . "cronjob
				WHERE classesname = '" . $cronvalue['classesname'] . "'
				AND module_name = '" . $cronvalue['module_name'] . "'
				AND objectname = '" . $cronvalue['objectname'] . "'
				AND methodename = '" . $cronvalue['methodename'] . "'
			";

			$res = $this->db->query($req);
			$job = $this->db->fetch_object($res);

			if (empty($job->rowid)) {
				$cronTask = new Cronjob($this->db);
				foreach ($cronvalue as $key => $value) {
					$cronTask->{$key} = $value;
				}

				$res = $cronTask->create($user);
				if($res<=0) {
					var_dump($res,$cronTask);
					exit;
				}
			}

		}

		$this->transformExtraFkOfIntoElementElement();

		return $this->_init($sql, $options);
	}

	/**
	 *		Function called when module is disabled.
	 *      Remove from database constants, boxes and permissions from Dolibarr database.
	 *		Data directories are not deleted
	 *
     *      @param      string	$options    Options when enabling module ('', 'noboxes')
	 *      @return     int             	1 if OK, 0 if KO
	 */
	function remove($options='')
	{
		$sql = array();

		return $this->_remove($sql, $options);
	}


    function transformExtraFkOfIntoElementElement() {
        global $db;
        dol_include_once('/projet/class/task.class.php');
        //Check si on a pas déjà fait appel à cette fonction
        $sqlCheck = "SELECT * FROM " . MAIN_DB_PREFIX . "element_element WHERE sourcetype='tassetof'";
        $resqlCheck = $db->query($sqlCheck);

        if(!empty($resqlCheck) && $db->num_rows($resqlCheck) == 0) {

            //On ajoute les objets liés
            $sql = "SELECT t.rowid, tex.fk_of FROM " . MAIN_DB_PREFIX . "projet_task t
            LEFT JOIN " . MAIN_DB_PREFIX . "projet_task_extrafields tex ON (tex.fk_object=t.rowid)
            LEFT JOIN " . MAIN_DB_PREFIX . "element_element ee  ON (ee.fk_target=t.rowid AND ee.targettype='project_task' AND ee.sourcetype='tassetof')
            WHERE tex.fk_of IS NOT NULL ";

            $resql = $db->query($sql);
            if(!empty($resql) && $db->num_rows($resql) > 0) {
                while($obj = $db->fetch_object($resql)) {
                    $t = new Task($db);
                    $t->fetch($obj->rowid);
                    if(!empty($obj->fk_of)) $t->add_object_linked('tassetof', $obj->fk_of);
                }
            }
        }
    }

}
