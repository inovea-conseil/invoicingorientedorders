<?php
/* Copyright (C) 2024 SuperAdmin
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
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    invoicingorientedorders/class/actions_invoicingorientedorders.class.php
 * \ingroup invoicingorientedorders
 * \brief   Example hook overload.
 *
 * Put detailed description here.
 */

/**
 * Class ActionsInvoicingorientedorders
 */
class ActionsInvoicingorientedorders
{
	/**
	 * @var DoliDB Database handler.
	 */
	public $db;

	/**
	 * @var string Error code (or message)
	 */
	public $error = '';

	/**
	 * @var array Errors
	 */
	public $errors = array();


	/**
	 * @var array Hook results. Propagated to $hookmanager->resArray for later reuse
	 */
	public $results = array();

	/**
	 * @var string String displayed by executeHook() immediately after return
	 */
	public $resprints;

	/**
	 * @var int		Priority of hook (50 is used if value is not defined)
	 */
	public $priority;


	/**
	 * Constructor
	 *
	 *  @param		DoliDB		$db      Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}



	public function printOriginObjectLine($parameters, &$object, &$action, $hookmanager)
	{
		global $restrictlist, $selectedLines,$langs,$db;
		$langs->load("invoicingorientedorders@invoicingorientedorders");
		require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
		$lineorder = &$parameters['line'];
		$qtyfactured = 0;
		$label ="";
		$productAlready = array();
		$skip = 1;
		dol_syslog('invoicingorientedorders init skip: '.$skip, LOG_DEBUG);
		foreach ($object->lines as $line ) {
            $lineIsSousTotal = is_null($line->fk_product) && ($line->product_type == 9) && (isset($line->array_options['options_soustotal_type']) && ($line->array_options['options_soustotal_type'] > 0));
			if (in_array($line->fk_product,$productAlready) && !$lineIsSousTotal) {
				$skip = 0;
				dol_syslog('invoicingorientedorders skip: '.$skip.' line already in array or is SousTotal', LOG_DEBUG);
				break;
			}
			$productAlready[] = $line->fk_product;
		}
		if ($object->element == "commande") {
			if ( !isset($object->linkedObjects['facture']) || empty($object->linkedObjects['facture'])) {
				return 0;
			}
			foreach ($object->linkedObjects['facture'] as $invoice ) {
				if ($invoice->type != 0 ) {
					$skip = 0;
					dol_syslog('invoicingorientedorders skip: '.$skip.' not standard invoice', LOG_DEBUG);
					break;
				}
				if (dolibarr_get_const($db, "INVOICINGORIENTEDORDERS_COUNTDRAFTS") || $invoice->status != $invoice::STATUS_DRAFT) {
					foreach ($invoice->lines as $line) {
						if ($lineorder->fk_product == $line->fk_product) {
							$qtyfactured += $line->qty;
							break;
						}
					}
				}
				if(dolibarr_get_const($db, "INVOICINGORIENTEDORDERS_BLOCKIFDRAFTS") &&  $invoice->status == $invoice::STATUS_DRAFT) {
					$skip = 2;
					dol_syslog('invoicingorientedorders skip: '.$skip, LOG_DEBUG);
				}
			}
			if ($skip) {
				$label .= ($lineorder->fk_product_type == 0 ? img_object($langs->trans(''), 'product') : img_object($langs->trans(''), 'service') ). " " .  $lineorder->ref . " - " . (!empty($lineorder->label) ? $lineorder->label: $lineorder->libelle );
				echo '<tr> 	<td class="linecolref"> ' . $label . $lineorder->label . ' </td>
							<td class="linecoldescription"> ' . $lineorder->desc . ' </td>
							<td class="linecolvat right"> ' . vatrate($lineorder->tva_tx, true) . ' </td>
							<td class="linecoluht right"> ' . price($lineorder->subprice) . ' </td>';
				if (isModEnabled("multicurrency")) {
					print '<td class="linecoluht_currency right">  ' . price($lineorder->multicurrency_subprice) . ' </td>';
				}
				print '<td class="linecolqty right"> ' . $qtyfactured ."/". $lineorder->qty . ' </td>';
				if (!empty($conf->global->PRODUCT_USE_UNITS)) {
					print '<td class="linecoluseunit left">'.$lineorder->getLabelOfUnit('long').'</td>';
				}

				echo '
						<td class="linecoldiscount right"> ' . vatrate($lineorder->remise_percent,true) . ' </td>
						<td class="linecolht right"> ' . price($lineorder->total_ht) . ' </td>
						<td class="center"> <input id="cb' . $lineorder->id . '" class="flat checkforselect" type="checkbox" name="toselect[]" value="' . $lineorder->id . '" checked="checked" > </td>
					</tr> ';
			}
			?>

			<script>
				$(document).ready(function() {
					// Créer un élément input hidden et l'ajouter au formulaire
					$("#formtocreate").append('<input type="hidden" name="<?= $lineorder->fk_product?>" value="<?= $qtyfactured?>">');
					if ($('#formtocreate input[name="balance"]').length === 0) {
						const bouton = $('<input type="submit"  class="button button-save "value="<?=$langs->trans("CREATE_BALANCE_INVOICE")?>" name="balance">');

						if (<?= $skip?> !== 1) {
							bouton.prop( "disabled", true );
						} else {
							bouton.prop( "disabled", false );
						}

						$('#formtocreate input[name="save"]').before(bouton);


						bouton.on('click', function() {
							$("#formtocreate").append('<input type="hidden" name="balance" value="balance">');
						});
					}
				});
			</script>
		<?php
			return $skip;
		}
		return 0;
	}

	function createFrom($parameters, &$object, $action, $hookmanager) {
		global $user,$db,$mysoc;
		require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
		$object->fetch_lines();
		$fk_parent_line = null;

		$lines = $object->lines;


		if (!empty(GETPOST('balance') )) {
			foreach ( $lines as $line) {
				$qtySolde = GETPOST($line->fk_product, 'alpha');
				if ( !empty($qtySolde) ) {
					$qty = $line->qty - $qtySolde  ;
				//	$line->pa_ht = $line->subprice;
					if ($qty > 0 ) {
						$object->updateline($line->id, $line->desc, $line->subprice, $qty, $line->remise_percent, $line->date_start, $line->date_end, $line->tva_tx, $line->localtax1_tx, $line->localtax2_tx, 'HT', $line->info_bits, $line->product_type, $line->fk_parent_line, 0, $line->fk_fournprice, $line->pa_ht, $line->label, $line->special_code, $line->array_options, $line->situation_percent, $line->fk_unit, $line->multicurrency_subprice);
					} else {
						$object->deleteline($line->rowid);
					}
				}
			}
		}
		return 1;
	}

	/**
	 * Execute action
	 *
	 * @param	array			$parameters		Array of parameters
	 * @param	CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param	string			$action      	'add', 'update', 'view'
	 * @return	int         					<0 if KO,
	 *                           				=0 if OK but we want to process standard actions too,
	 *                            				>0 if OK and we want to replace standard actions.
	 */
	public function getNomUrl($parameters, &$object, &$action)
	{
		global $db, $langs, $conf, $user;
		$this->resprints = '';
		return 0;
	}

	/**
	 * Overloading the doActions function : replacing the parent's function with the one below
	 *
	 * @param   array           $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          $action         Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function doActions($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user, $langs;

		$error = 0; // Error counter

		/* print_r($parameters); print_r($object); echo "action: " . $action; */
		if (in_array($parameters['currentcontext'], array('somecontext1', 'somecontext2'))) {	    // do something only for the context 'somecontext1' or 'somecontext2'
			// Do what you want here...
			// You can for example call global vars like $fieldstosearchall to overwrite them, or update database depending on $action and $_POST values.
		}

		if (!$error) {
			$this->results = array('myreturn' => 999);
			$this->resprints = 'A text to show';
			return 0; // or return 1 to replace standard code
		} else {
			$this->errors[] = 'Error message';
			return -1;
		}
	}


	/**
	 * Overloading the doMassActions function : replacing the parent's function with the one below
	 *
	 * @param   array           $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          $action         Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function doMassActions($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user, $langs;

		$error = 0; // Error counter

		/* print_r($parameters); print_r($object); echo "action: " . $action; */
		if (in_array($parameters['currentcontext'], array('somecontext1', 'somecontext2'))) {		// do something only for the context 'somecontext1' or 'somecontext2'
			foreach ($parameters['toselect'] as $objectid) {
				// Do action on each object id
			}
		}

		if (!$error) {
			$this->results = array('myreturn' => 999);
			$this->resprints = 'A text to show';
			return 0; // or return 1 to replace standard code
		} else {
			$this->errors[] = 'Error message';
			return -1;
		}
	}


	/**
	 * Overloading the addMoreMassActions function : replacing the parent's function with the one below
	 *
	 * @param   array           $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          $action         Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function addMoreMassActions($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user, $langs;

		$error = 0; // Error counter
		$disabled = 1;

		/* print_r($parameters); print_r($object); echo "action: " . $action; */
		if (in_array($parameters['currentcontext'], array('somecontext1', 'somecontext2'))) {		// do something only for the context 'somecontext1' or 'somecontext2'
			$this->resprints = '<option value="0"'.($disabled ? ' disabled="disabled"' : '').'>'.$langs->trans("InvoicingorientedordersMassAction").'</option>';
		}

		if (!$error) {
			return 0; // or return 1 to replace standard code
		} else {
			$this->errors[] = 'Error message';
			return -1;
		}
	}



	/**
	 * Execute action
	 *
	 * @param	array	$parameters     Array of parameters
	 * @param   Object	$object		   	Object output on PDF
	 * @param   string	$action     	'add', 'update', 'view'
	 * @return  int 		        	<0 if KO,
	 *                          		=0 if OK but we want to process standard actions too,
	 *  	                            >0 if OK and we want to replace standard actions.
	 */
	public function beforePDFCreation($parameters, &$object, &$action)
	{
		global $conf, $user, $langs;
		global $hookmanager;

		$outputlangs = $langs;

		$ret = 0; $deltemp = array();
		dol_syslog(get_class($this).'::executeHooks action='.$action);

		/* print_r($parameters); print_r($object); echo "action: " . $action; */
		if (in_array($parameters['currentcontext'], array('somecontext1', 'somecontext2'))) {		// do something only for the context 'somecontext1' or 'somecontext2'
		}

		return $ret;
	}

	/**
	 * Execute action
	 *
	 * @param	array	$parameters     Array of parameters
	 * @param   Object	$pdfhandler     PDF builder handler
	 * @param   string	$action         'add', 'update', 'view'
	 * @return  int 		            <0 if KO,
	 *                                  =0 if OK but we want to process standard actions too,
	 *                                  >0 if OK and we want to replace standard actions.
	 */
	public function afterPDFCreation($parameters, &$pdfhandler, &$action)
	{
		global $conf, $user, $langs;
		global $hookmanager;

		$outputlangs = $langs;

		$ret = 0; $deltemp = array();
		dol_syslog(get_class($this).'::executeHooks action='.$action);

		/* print_r($parameters); print_r($object); echo "action: " . $action; */
		if (in_array($parameters['currentcontext'], array('somecontext1', 'somecontext2'))) {
			// do something only for the context 'somecontext1' or 'somecontext2'
		}

		return $ret;
	}



	/**
	 * Overloading the loadDataForCustomReports function : returns data to complete the customreport tool
	 *
	 * @param   array           $parameters     Hook metadatas (context, etc...)
	 * @param   string          $action         Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function loadDataForCustomReports($parameters, &$action, $hookmanager)
	{
		global $conf, $user, $langs;

		$langs->load("invoicingorientedorders@invoicingorientedorders");

		$this->results = array();

		$head = array();
		$h = 0;

		if ($parameters['tabfamily'] == 'invoicingorientedorders') {
			$head[$h][0] = dol_buildpath('/module/index.php', 1);
			$head[$h][1] = $langs->trans("Home");
			$head[$h][2] = 'home';
			$h++;

			$this->results['title'] = $langs->trans("Invoicingorientedorders");
			$this->results['picto'] = 'invoicingorientedorders@invoicingorientedorders';
		}

		$head[$h][0] = 'customreports.php?objecttype='.$parameters['objecttype'].(empty($parameters['tabfamily']) ? '' : '&tabfamily='.$parameters['tabfamily']);
		$head[$h][1] = $langs->trans("CustomReports");
		$head[$h][2] = 'customreports';

		$this->results['head'] = $head;

		return 1;
	}



	/**
	 * Overloading the restrictedArea function : check permission on an object
	 *
	 * @param   array           $parameters     Hook metadatas (context, etc...)
	 * @param   string          $action         Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int 		      			  	<0 if KO,
	 *                          				=0 if OK but we want to process standard actions too,
	 *  	                            		>0 if OK and we want to replace standard actions.
	 */
	public function restrictedArea($parameters, &$action, $hookmanager)
	{
		global $user;

		if ($parameters['features'] == 'myobject') {
			if ($user->rights->invoicingorientedorders->myobject->read) {
				$this->results['result'] = 1;
				return 1;
			} else {
				$this->results['result'] = 0;
				return 1;
			}
		}

		return 0;
	}

	/**
	 * Execute action completeTabsHead
	 *
	 * @param   array           $parameters     Array of parameters
	 * @param   CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          $action         'add', 'update', 'view'
	 * @param   Hookmanager     $hookmanager    hookmanager
	 * @return  int                             <0 if KO,
	 *                                          =0 if OK but we want to process standard actions too,
	 *                                          >0 if OK and we want to replace standard actions.
	 */
	public function completeTabsHead(&$parameters, &$object, &$action, $hookmanager)
	{
		global $langs, $conf, $user;

		if (!isset($parameters['object']->element)) {
			return 0;
		}
		if ($parameters['mode'] == 'remove') {
			// utilisé si on veut faire disparaitre des onglets.
			return 0;
		} elseif ($parameters['mode'] == 'add') {
			$langs->load('invoicingorientedorders@invoicingorientedorders');
			// utilisé si on veut ajouter des onglets.
			$counter = count($parameters['head']);
			$element = $parameters['object']->element;
			$id = $parameters['object']->id;
			// verifier le type d'onglet comme member_stats où ça ne doit pas apparaitre
			// if (in_array($element, ['societe', 'member', 'contrat', 'fichinter', 'project', 'propal', 'commande', 'facture', 'order_supplier', 'invoice_supplier'])) {
			if (in_array($element, ['context1', 'context2'])) {
				$datacount = 0;

				$parameters['head'][$counter][0] = dol_buildpath('/invoicingorientedorders/invoicingorientedorders_tab.php', 1) . '?id=' . $id . '&amp;module='.$element;
				$parameters['head'][$counter][1] = $langs->trans('InvoicingorientedordersTab');
				if ($datacount > 0) {
					$parameters['head'][$counter][1] .= '<span class="badge marginleftonlyshort">' . $datacount . '</span>';
				}
				$parameters['head'][$counter][2] = 'invoicingorientedordersemails';
				$counter++;
			}
			if ($counter > 0 && (int) DOL_VERSION < 14) {
				$this->results = $parameters['head'];
				// return 1 to replace standard code
				return 1;
			} else {
				// en V14 et + $parameters['head'] est modifiable par référence
				return 0;
			}
		}
	}

	/* Add here any other hooked methods... */
}
