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
 *     	\file       htdocs/public/cmcic/confirm.php
 *		\ingroup    cmcic
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

dol_include_once('/cmcic/class/cmcic.class.php');
dol_syslog('CMCIC: confirmation page has been called'); 
// Security check
if (empty($conf->cmcic->enabled)) 
    accessforbidden('',1,1,1);
    
$langs->setDefaultLang('fr_FR');
    
$langs->load("main");
$langs->load("other");
$langs->load("dict");
$langs->load("bills");
$langs->load("companies");
$langs->load("cmcic@cmcic");

$error = false;
dol_syslog('CMCIC: Check configuration'); 

// Check module configuration
if (empty($conf->global->API_KEY))
{
	$error = true;
	dol_syslog('CMCIC: Configuration error : key is not defined');    
}

if (empty($conf->global->API_SHOP_ID))
{
	$error = true;
	dol_syslog('CMCIC: Configuration error : society ID is not defined');    
}

if (empty($conf->global->API_TPE_NUMBER))
{
	$error = true;
	dol_syslog('CMCIC: Configuration error : tpe number is not defined');    
}

if (empty($conf->global->API_BANK_SERVER))
{
	$error = true;
	dol_syslog('CMCIC: Configuration error : bank server is not defined');    
}  
    
if ($error)
{
    exit;
}

	
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

$language = strtoupper($langs->getDefaultLang(true));
$cmcicVersion = "3.0";

$vars = getMethode();

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
                         
$oHmac = new CMCIC_Hmac($oTpe);

// Message Authentication
$fields = sprintf(CMCIC_CGI2_FIELDS, $oTpe->sNumero,
					  $vars["date"],
				          $vars['montant'],
				          $vars['reference'],
				          $vars['texte-libre'],
				          $oTpe->sVersion,
				          $vars['code-retour'],
    					  $vars['cvx'],
    					  $vars['vld'],
    					  $vars['brand'],
    					  $vars['status3ds'],
    					  $vars['numauto'],
    					  $vars['motifrefus'],
    					  $vars['originecb'],
    					  $vars['bincb'],
    					  $vars['hpancb'],
    					  $vars['ipclient'],
    					  $vars['originetr'],
    					  $vars['veres'],
    					  $vars['pares']);

$success = false;
if ($oHmac->computeHmac($fields) == strtolower($vars['MAC']))
{
	switch($vars['code-retour']) 
	{
        case "payetest":
		case "paiement":
            $success = true;
		break;
			
	}

	$receipt = CMCIC_CGI2_MACOK;

}
else
{
	// your code if the HMAC doesn't match
	$receipt = CMCIC_CGI2_MACNOTOK.$fields;
}

// Get invoice data
$key = $vars['texte-libre'];
$cmcic = new CMCIC($db);
$result = $cmcic->fetch('', $key);

if ($result <= 0)
{
	$error = true;
	dol_syslog('CMCIC: Invoice/order with specified reference does not exist, confirmation payment email has not been sent');
	exit;
}


$isInvoice = $cmcic->type == 'invoice' ? true : false;
$item = ($isInvoice) ? new Facture($db) : new Commande($db);
$result = $item->fetch($cmcic->fk_object);
if ($result < 0)
{
    $error = true;
    dol_syslog('CMCIC: Invoice/order with specified reference does not exist, confirmation payment email has not been sent');
}
	
$item->fetch_thirdparty();
$referenceDolibarr = $item->ref;

$dateTransaction = $vars['date'];
$referenceTransaction = $vars['reference'];
$referenceAutorisation = $vars['numauto'];

$amountTransaction = $vars['montant'];
$bankBin = $vars['bincb'];

$clientBankName = ''; 
$clientName = $item->thirdparty->name;

$substit = array(
	'__OBJREF__' => $referenceDolibarr,
	'__SOCNAM__' => $conf->global->MAIN_INFO_SOCIETE_NOM,
	'__SOCMAI__' => $conf->global->MAIN_INFO_SOCIETE_MAIL,
	'__CLINAM__' => $clientName,                
	'__AMOOBJ__' => $amountTransaction,
);
            
// Update DB
if ($success)
{
    // If order, first convert it into invoice, then mark is as paid
    
    if (!$isInvoice)
    { 
        $item->fetch_lines();
        
        // Create invoice
        $invoice = new Facture($db);
        $result = $invoice->createFromOrder($item);
        
        $item = new Facture($db);
        $item->fetch($invoice->id);
        $item->fetch_thirdparty();                  
    }
    
		  
    // Set transaction reference 
    $item->setValueFrom('ref_int', $referenceTransaction);
    $id = $item->id; 
    
    
    $db->begin();
    $currency = $conf->currency;
    
    $amount = str_replace($currency, '', $amountTransaction);//Remove currency
    // Creation of payment line
    $payment = new Paiement($db);
    $payment->datepaye     = dol_now();
    $payment->amounts      = array($id => price2num($amount)); 
    $payment->amount      = $amount;      
    $payment->paiementid   = dol_getIdFromCode($db, 'CB', 'c_paiement');
    $payment->num_paiement = $referenceAutorisation;
    $payment->note         = '';

    $paymentId = $payment->create($user, $conf->global->UPDATE_INVOICE_STATUT);

    if ($paymentId < 0)
    {
        dol_syslog('CMCIC: Payment has not been created in the database');
    }

	if (!empty($conf->global->BANK_ACCOUNT_ID))
	{
		$payment->addPaymentToBank($user, 'payment', '(CustomerInvoicePayment)', $conf->global->BANK_ACCOUNT_ID, $clientName, $clientBankName);      
	} 
                
    $db->commit(); 
    
    $subject = ($isInvoice ? $langs->transnoentities('InvoiceSuccessPaymentEmailSubject') : $langs->transnoentities('OrderSuccessPaymentEmailSubject'));         
    $message = ($isInvoice ? $langs->transnoentities('InvoiceSuccessPaymentEmailBody') : $langs->transnoentities('OrderSuccessPaymentEmailBody'));
    
    $subject = make_substitutions($subject, $substit);           
    $message = make_substitutions($message, $substit);        
          
}else{
    
    $grounds = urldecode($vars['motifrefus']);
    $message = '';
    
    switch(strtolower($grounds)){
        case 'Appel Phonie' :
        case 'Filtrage' :
        case 'Interdit' :
        case 'Refus' :
        default :
            $message = $langs->transnoentities('ErrorPaymentUnauthorizedEmail');
        break;
    }
    
    $subject = ($isInvoice ? $langs->transnoentities('InvoiceErrorPaymentEmailSubject') : $langs->transnoentities('OrderErrorPaymentEmailSubject'));         
    $message .= ($isInvoice ? $langs->transnoentities('InvoiceErrorPaymentEmailBody') : $langs->transnoentities('OrderErrorPaymentEmailBody'));    
    
    $subject = make_substitutions($subject, $substit);           
    $message = make_substitutions($message, $substit);  
}

if (!$error)
{
    //Get data for email  
	$sendto = $item->thirdparty->email;
  

    $from = $conf->global->MAIN_INFO_SOCIETE_MAIL;
             
	$message = str_replace('\n',"<br />", $message);
	
	$deliveryreceipt = 0;//$conf->global->DELIVERY_RECEIPT_EMAIL;
	$addr_cc = ($conf->global->CC_EMAIL ? $conf->global->MAIN_INFO_SOCIETE_MAIL: "");

	if (!empty($conf->global->CC_EMAILS)){
		$addr_cc.= (empty($addr_cc) ? $conf->global->CC_EMAILS : ','.$conf->global->CC_EMAILS);
	}

	$mail = new CMailFile($subject, $sendto, $from, $message, array(), array(), array(), $addr_cc, "", $deliveryreceipt, 1);
	$result = $mail->error;
            
    if (!$result)
    {
        $result = $mail->sendfile();
        if ($result){
            dol_syslog('CMCIC: Confirmation payment email has been correctly sent');
        }else{
            dol_syslog('CMCIC: Error sending confirmation payment email');
        }
    }
    else
    {
        dol_syslog('CMCIC: Error in creating confirmation payment email');
    }     
}

                        
printf (CMCIC_CGI2_RECEIPT, $receipt);

$db->close();
?>