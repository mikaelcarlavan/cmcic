<?php
/* Copyright (C) 2012      Mikael Carlavan        <mcarlavan@qis-network.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
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
 *     	\file       htdocs/public/cmcic/payment.php
 *		\ingroup    cmcic
 *		\brief      File to offer a payment form for an invoice
 */

define("NOLOGIN",1);		// This means this output page does not require to be logged.
define("NOCSRFCHECK",1);	// We accept to go on this page from external web site.

$res=@include("../main.inc.php");					// For root directory
if (! $res) $res=@include("../../main.inc.php");	// For "custom" directory


dol_include_once("/cmcic/lib/cmcic.inc.php");
require_once(DOL_DOCUMENT_ROOT."/compta/facture/class/facture.class.php");
require_once(DOL_DOCUMENT_ROOT.'/compta/paiement/class/paiement.class.php');
require_once(DOL_DOCUMENT_ROOT."/commande/class/commande.class.php");
require_once(DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php');
require_once(DOL_DOCUMENT_ROOT."/core/lib/functions.lib.php");

require_once(DOL_DOCUMENT_ROOT."/core/lib/company.lib.php");
require_once(DOL_DOCUMENT_ROOT."/core/lib/security.lib.php");
require_once(DOL_DOCUMENT_ROOT."/core/lib/date.lib.php");
require_once(DOL_DOCUMENT_ROOT."/core/lib/functions2.lib.php");

require_once(DOL_DOCUMENT_ROOT."/compta/facture/class/facture.class.php");
require_once(DOL_DOCUMENT_ROOT."/commande/class/commande.class.php");

dol_include_once('/cmcic/class/cmcic.class.php');

// Security check
if (empty($conf->cmcic->enabled)) 
    accessforbidden('',1,1,1);

$langs->load("main");
$langs->load("other");
$langs->load("dict");
$langs->load("bills");
$langs->load("companies");
$langs->load("errors");

$langs->load("cmcic@cmcic");


$key    = GETPOST("key", 'alpha');

$error = false;
$message = false;


$cmcic = new CMCIC($db);
$result = $cmcic->fetch('', $key);

if ($result <= 0)
{
	$error = true;
	$message = $langs->trans('NoPaymentObject');
}

// Check module configuration
if (empty($conf->global->API_KEY))
{
	$error = true;
	$message = $langs->trans('ConfigurationError');
	dol_syslog('CMCIC: Configuration error : key is not defined');    
}

if (empty($conf->global->API_SHOP_ID))
{
	$error = true;
	$message = $langs->trans('ConfigurationError');
	dol_syslog('CMCIC: Configuration error : society ID is not defined');    
}

if (empty($conf->global->API_TPE_NUMBER))
{
	$error = true;
	$message = $langs->trans('ConfigurationError');
	dol_syslog('CMCIC: Configuration error : tpe number is not defined');    
}

if (empty($conf->global->API_BANK_SERVER))
{
	$error = true;
	$message = $langs->trans('ConfigurationError');
	dol_syslog('CMCIC: Configuration error : bank server is not defined');    
}

if (!$error)
{
	
	$isInvoice = ($cmcic->type == 'invoice' ? true : false);

	// Get societe info
	$societyName = $mysoc->name;
	$creditorName = $societyName;
	
	$currency = $conf->currency;
	
	// Define logo and logosmall
	$urlLogo = '';
	if (!empty($mysoc->logo_small) && is_readable($conf->mycompany->dir_output.'/logos/thumbs/'.$mysoc->logo_small))
	{
		$urlLogo = DOL_URL_ROOT.'/viewimage.php?modulepart=companylogo&amp;file='.urlencode('thumbs/'.$mysoc->logo_small);
	}
	elseif (! empty($mysoc->logo) && is_readable($conf->mycompany->dir_output.'/logos/'.$mysoc->logo))
	{
		$urlLogo = DOL_URL_ROOT.'/viewimage.php?modulepart=companylogo&amp;file='.urlencode($mysoc->logo);
	}

	// Prepare form
	$language = strtoupper($langs->getDefaultLang(true));

	
	$cmcicVersion = "3.0";
	$dateTransaction = dol_print_date(dol_now(), "%d/%m/%Y:%H:%M:%S");
	$refTransaction = dol_print_date(dol_now(), "%d%m%y%H%M%S");
	
	switch($conf->global->API_BANK_SERVER){
		case 'cm' :
			$bankName = 'Crédit Mutuel';
			$urlServer = ($conf->global->API_TEST) ? $conf->global->CM_URL_SERVER_TEST : $conf->global->CM_URL_SERVER;
		break;
	
		case 'obc' :
			$bankName = 'Neuflize OBC';
			$urlServer = ($conf->global->API_TEST) ? $conf->global->OBC_URL_SERVER_TEST : $conf->global->OBC_URL_SERVER;
		break;
	
		case 'cic' :
		default :
			$bankName = 'CIC';
			$urlServer = ($conf->global->API_TEST) ? $conf->global->CIC_URL_SERVER_TEST : $conf->global->CIC_URL_SERVER; 
		break;    
	}
	
	$item = ($isInvoice) ? new Facture($db) : new Commande($db);

	$result = $item->fetch($cmcic->fk_object);

	$alreadyPaid = 0;
	$creditnotes = 0;
	$deposits = 0;
	$totalObject = 0;
	$amountTransaction = 0;

	$needPayment = false;

	$result = $item->fetch_thirdparty($item->socid);
    
    if ($isInvoice)
    {
        $alreadyPaid = $item->getSommePaiement();
        $creditnotes = $item->getSumCreditNotesUsed();
        $deposits = $item->getSumDepositsUsed();         
    }

    $totalObject = $item->total_ttc;
       
    $alreadyPaid = empty($alreadyPaid) ? 0 : $alreadyPaid;
    $creditnotes = empty($creditnotes) ? 0 : $creditnotes;
    $deposits = empty($deposits) ? 0 : $deposits;
    
    $totalObject = empty($totalObject) ? 0 : $totalObject;
    
    $amountTransaction =  $totalObject - ($alreadyPaid + $creditnotes + $deposits);
    
    $needPayment = ($item->statut == 1) ? true : false;
    
    // Do nothing if payment is already completed
    if (price2num($amountTransaction, 'MT') == 0 || !$needPayment)
    {
        $error = true;
        $message = ($isInvoice ? $langs->trans('InvoicePaymentAlreadyDone') : $langs->trans('OrderPaymentAlreadyDone'));    
        dol_syslog('CMCIC: Payment already completed, form will not be displayed');
    }
}


if (!$error)
{
	   
	$customerEmail = $item->thirdparty->email;
	$customerName = $item->thirdparty->name;     
 	$customerId = $item->thirdparty->id;
 	$customerAddress = $item->thirdparty->address;
 	$customerZip = $item->thirdparty->zip;
 	$customerCity = $item->thirdparty->town;
 	$customerCountry = $item->thirdparty->country_code;
 	$customerPhone = $item->thirdparty->phone;
    
    //Clean data
    $refTransaction = dol_string_unaccent($refTransaction);
    $freeTag =  dol_string_unaccent($key);
    $amountTransactionNum = price2num($amountTransaction, 'MT');
    $amountCurrency = $amountTransactionNum .$currency;

	$url_ok = dol_buildpath('/cmcic/success.php', 2);//DOL_MAIN_URL_ROOT. '/public/cmcic/success.php';
	$url_ko = dol_buildpath('/cmcic/error.php', 2);//DOL_MAIN_URL_ROOT. '/public/cmcic/error.php';
	$url_ret = dol_buildpath('/cmcic/return.php', 2);//DOL_MAIN_URL_ROOT. '/public/cmcic/return.php';

    
    $oTpe = new CMCIC_Tpe($cmcicVersion, 
                            $conf->global->API_KEY, 
                            $conf->global->API_TPE_NUMBER, 
                            $urlServer, 
                            $conf->global->API_SHOP_ID, 
                            $url_ok, 
                            $url_ko, 
                            $language); 		

    // Data to certify
    $fields = sprintf(CMCIC_CGI1_FIELDS,     $oTpe->sNumero,
                                                 $dateTransaction,
                                                  $amountTransactionNum,
                                                  $currency,
                                                  $refTransaction,
                                                  $freeTag,
                                                  $oTpe->sVersion,
                                                  $oTpe->sLangue,
                                                  $oTpe->sCodeSociete, 
                                                  $customerEmail,
                                                  "", "", "", "", "", "", "", "", "", "");
    
    // MAC computation
    $oHmac = new CMCIC_Hmac($oTpe);
    $macToken = $oHmac->computeHmac($fields);
    	
    /*
     * View
     */
    $substit = array(
        '__OBJREF__' => $item->ref,
        '__SOCNAM__' => $societyName,
        '__SOCMAI__' => $conf->global->MAIN_INFO_SOCIETE_MAIL,
        '__CLINAM__' => $customerName,                
        '__AMOINV__' => price2num($amountTransaction, 'MT')
    );
     
     if ($isInvoice)
     {
        $welcomeTitle = $langs->transnoentities('InvoicePaymentFormWelcomeTitle');
        $welcomeText  = $langs->transnoentities('InvoicePaymentFormWelcomeText');      
        $descText = $langs->transnoentities('InvoicePaymentFormDescText');
     }
     else
     {
        $welcomeTitle = $langs->transnoentities('OrderPaymentFormWelcomeTitle'); 
        $welcomeText  = $langs->transnoentities('OrderPaymentFormWelcomeText');
        $descText = $langs->transnoentities('OrderPaymentFormDescText');
     } 
     
     $welcomeTitle = make_substitutions($welcomeTitle, $substit);
     $welcomeText = make_substitutions($welcomeText, $substit);
     $descText = make_substitutions($descText, $substit);
     
    require_once('tpl/payment.tpl.php');    
}else{
    
    /*
     * View
     */
     
    $substit = array(
        '__SOCNAM__' => $conf->global->MAIN_INFO_SOCIETE_NOM,
        '__SOCMAI__' => $conf->global->MAIN_INFO_SOCIETE_MAIL,
    );
    
    $welcomeTitle = make_substitutions($langs->transnoentities('InvoicePaymentFormWelcomeTitle'), $substit);     
    $message = make_substitutions($message, $substit);
    
    require_once('tpl/message.tpl.php');    
}

?>