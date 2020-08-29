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

require_once(DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php');
require_once(DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php');
require_once(DOL_DOCUMENT_ROOT."/core/lib/functions.lib.php");
require_once(DOL_DOCUMENT_ROOT."/compta/facture/class/facture.class.php");
require_once(DOL_DOCUMENT_ROOT."/commande/class/commande.class.php");

dol_include_once('/cmcic/class/cmcic.class.php');
/**
 *      \class      InterfaceCMCICWorkflow
 *      \brief      Class of triggers for cmcic module
 */
class InterfaceCMCICWorkflow
{
    var $db;

    /**
     *   Constructor
     *   @param      DB      Database handler
     */
    function InterfaceCMCICWorkflow($DB)
    {
        $this->db = $DB;

        $this->name = preg_replace('/^Interface/i','',get_class($this));
        $this->family = "cmcic";
        $this->description = "Triggers of this module allows to manage cmcic workflow";
        $this->version = 'dolibarr';            // 'development', 'experimental', 'dolibarr' or version
        $this->picto = 'cmcic@cmcic';
    }


    /**
     *   \brief      Renvoi nom du lot de triggers
     *   \return     string      Nom du lot de triggers
     */
    function getName()
    {
        return $this->name;
    }

    /**
     *   \brief      Renvoi descriptif du lot de triggers
     *   \return     string      Descriptif du lot de triggers
     */
    function getDesc()
    {
        return $this->description;
    }

    /**
     *   \brief      Renvoi version du lot de triggers
     *   \return     string      Version du lot de triggers
     */
    function getVersion()
    {
        global $langs;
        $langs->load("admin");

        if ($this->version == 'development') return $langs->trans("Development");
        elseif ($this->version == 'experimental') return $langs->trans("Experimental");
        elseif ($this->version == 'dolibarr') return DOL_VERSION;
        elseif ($this->version) return $this->version;
        else return $langs->trans("Unknown");
    }

    /**
     *      \brief      Fonction appelee lors du declenchement d'un evenement Dolibarr.
     *                  D'autres fonctions run_trigger peuvent etre presentes dans includes/triggers
     *      \param      action      Code de l'evenement
     *      \param      object      Objet concerne
     *      \param      user        Objet user
     *      \param      lang        Objet lang
     *      \param      conf        Objet conf
     *      \return     int         <0 if fatal error, 0 si nothing done, >0 if ok
     */
	function run_trigger($action, $object, $user, $langs, $conf)
    {
	    $triggered = ($action == 'BILL_SENTBYMAIL' || $action == 'BILL_VALIDATE' || $action == 'ORDER_SENTBYMAIL' || $action == 'ORDER_VALIDATE') ? true : false;
        $invoice = ($action == 'BILL_SENTBYMAIL' || $action == 'BILL_VALIDATE') ? true : false;  
         
        if ($triggered)
        {
            $langs->load("cmcic@cmcic");
        	dol_syslog("CMCIC: Trigger '".$this->name."' for action '".$action."' launched by ".__FILE__." ref=".$object->ref);

            $ref = $object->ref;
            
            
            $item = ($invoice) ? new Facture($this->db) : new Commande($this->db);
            
            $result = $item->fetch($object->id);
            if ($result < 0)
            {
                dol_syslog('CMCIC: Invoice/order with specified reference does not exist, email containing payment link has not been sent');
            	return $result;
            }
            else
            {
            	$result = $item->fetch_thirdparty();
            } 

	        $alreadyPaid = 0;
			$creditnotes = 0;
			$deposits = 0; 
            $totalObject = 0;
                
            if ($invoice)/* No partial payment for orders */
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

            $needPayment = ($item->statut == 1) ? true : false; //Validated but need to be paid
            
            // Do nothing if payment already completed
            if (price2num($amountTransaction, 'MT') == 0 || !$needPayment){
                dol_syslog('CMCIC: Payment already done, email containing payment link has not been sent');
                return 0;
            }
                        
            // Do nothing if payment is not CB
            // Get CB id
            $cbId = dol_getIdFromCode($this->db, 'CB','c_paiement');
            
                               
            if ($item->mode_reglement_id != $cbId){
                dol_syslog('CMCIC: Invoice/order payment mode is not CB, can not send payment link email');
                return 0;                
            }

            
            // Create URL
            $token = $conf->global->SECURITY_TOKEN ? $conf->global->SECURITY_TOKEN : '';
            $now = dol_now();
            $token = dol_hash($token.$ref.$now, 3); // MD5
            
            $cmcic = new CMCIC($this->db);
            $result = $cmcic->fetch('', $token);
            if ($result == 0)
            {
				$cmcic->key = $token;
				$cmcic->type = $invoice ? 'invoice' : 'order';
				$cmcic->fk_object = $item->id;
				$cmcic->create($user);
            }
            
            $paymentLink = dol_buildpath('/cmcic/payment.php', 2).'?key='.$token;
            
			$extrafields = $item->array_options;
			$extrafields['options_payment_link'] = $paymentLink;
			$item->array_options = $extrafields;
			$result = $item->insertExtraFields();
			
			// Update extrafields
			if ($action == 'BILL_VALIDATE' || $action == 'ORDER_VALIDATE')
			{
				return 1;
			}            
			
            // Return if autosend is desactivated
			if (empty($conf->global->PAYMENT_AUTO_SEND))
			{
				return 1;
			} 
			           
            $substit = array(
                '__OBJREF__' => $ref,
                '__PAYURL__' => $paymentLink,
                '__SOCNAM__' => $conf->global->MAIN_INFO_SOCIETE_NOM,
                '__SOCMAI__' => $conf->global->MAIN_INFO_SOCIETE_MAIL,
                '__CLINAM__' => $item->client->name,                
                '__AMOOBJ__' => price2num($amountTransaction, 'MT')
            );
            
			if (trim($_POST['sendto']))
			{
				// Recipient is provided into free text
				$sendto = trim($_POST['sendto']);
				$sendtoid = 0;
			}
		    else 
		    {
				$sendtoid = $object->sendtoid;
			
				if ($sendtoid){
					$sendto = $item->thirdparty->contact_get_property($sendtoid, 'email');
				}else{
					$sendto = $item->thirdparty->email;
				}  		    
		    }                      

            $from = $conf->global->MAIN_INFO_SOCIETE_MAIL;
            
            $message = ($invoice) ? $langs->transnoentities('InvoicePaymentEmailBody') : $langs->transnoentities('OrderPaymentEmailBody');
            $subject = ($invoice) ? $langs->transnoentities('InvoicePaymentEmailSubject') : $langs->transnoentities('OrderPaymentEmailSubject');
            
            $subject = make_substitutions($subject, $substit);           
            $message = make_substitutions($message, $substit);
            
            $message = str_replace('\n',"<br />", $message);
            
            $deliveryreceipt = $conf->global->DELIVERY_RECEIPT_EMAIL;
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
                    dol_syslog('CMCIC: Email containing payment link has been correctly sent');
                }else{
                    dol_syslog('CMCIC: Error sending email containing payment link');
                }
                return $result;
            }
            else
            {
                dol_syslog('CMCIC: Error in creating email containing payment link');
                return $result;
            }
     
        }

		return 0;
    }

}
?>
