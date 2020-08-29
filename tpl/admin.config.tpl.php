<?php

/* Copyright (C) 2012      Mikael Carlavan        <contact@mika-carl.fr>
 *                                                http://www.mikael-carlavan.fr
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

/**	    \file       htdocs/cmcic/tpl/admin.config.tpl.php
 *		\ingroup    tos
 *		\brief      Admin setup view
 */
 
llxHeader('', $langs->trans('CMCICAdmin'));

echo ($message ? dol_htmloutput_mesg($message, '', ($error ? 'error' : 'ok'), 0) : '');

print_fiche_titre($langs->trans('CMCICAdmin'), $linkback, 'setup');

?>
<script type="text/javascript">
<!--
$(document).ready(function () {
        $("#generate_token").click(function() {
        	$.get( "<?php echo DOL_URL_ROOT; ?>/core/ajax/security.php", {
        		action: 'getrandompassword',
        		generic: true
			},
			function(token) {
				$("#security_token").val(token);
			});
        });
});
-->
</script>
<br />
<?php echo $langs->trans("CMCICDesc"); ?>
<br />
<form name="doliprintsetup" action="<?php echo $_SERVER["PHP_SELF"]; ?>" method="post">
<input type="hidden" name="action" value="update" />
<input type="hidden" name="token" value="<?php echo $_SESSION['newtoken']; ?>" />
<table class="noborder" width="100%">

<tr class="liste_titre">
<td><?php echo $langs->trans("Configuration"); ?></td>
<td><?php echo $langs->trans("Value"); ?></td>
<td><?php echo $langs->trans("Infos"); ?></td>
</tr>

<tr class="impair">
    <td class="fieldrequired"><?php echo $langs->trans("ApiTest"); ?></td>
    <td><?php echo $form->selectyesno("api_test", $api_test, 1); ?></td>
    <td><?php echo $form->textwithpicto('', $htmltooltips['ApiTest'], 1, 0); ?></td>    
</tr>

<tr class="pair">
    <td class="fieldrequired"><?php echo $langs->trans("ApiKey"); ?></td>
    <td><input size="32" type="text" name="api_key" value="<?php echo $api_key; ?>" /></td>
    <td><?php echo $form->textwithpicto('', $htmltooltips['ApiKey'], 1, 0); ?></td>    
</tr>

<tr class="impair">
    <td class="fieldrequired"><?php echo $langs->trans("ApiShopId"); ?></td>
    <td><input size="32" type="text" name="api_shop_id" value="<?php echo $api_shop_id; ?>"/></td>
    <td><?php echo $form->textwithpicto('', $htmltooltips['ApiShopId'], 1, 0); ?></td>    
</tr>

<tr class="pair">
    <td class="fieldrequired"><?php echo $langs->trans("ApiTpeNumber"); ?></td>
    <td><input size="32" type="text" name="api_tpe_number" value="<?php echo $api_tpe_number; ?>" /></td>
    <td><?php echo $form->textwithpicto('', $htmltooltips['ApiTpeNumber'], 1, 0); ?></td>    
</tr>

<tr class="impair">
    <td class="fieldrequired"><?php echo $langs->trans("ApiBankServer"); ?></td>
    <td><?php echo $form->selectarray("api_bank_server", array('cic'=>'CIC','cm'=>'Crédit Mutuel','obc'=>'OBC'), $api_bank_server); ?></td>
    <td><?php echo $form->textwithpicto('', $htmltooltips['ApiBankServer'], 1, 0); ?></td>    
</tr>

<tr class="liste_titre">
    <td><?php echo $langs->trans("UsageParameters"); ?></td>
    <td><?php echo $langs->trans("Value"); ?></td>
    <td><?php echo $langs->trans("Infos"); ?></td>   
</tr>

<tr class="impair">
    <td class="fieldrequired"><?php echo $langs->trans("SecurityToken"); ?></td>
    <td><input size="32" type="text" id="security_token" name="security_token" value="<?php echo $security_token; ?>" /> <?php echo img_picto($langs->trans('Generate'), 'refresh', 'id="generate_token" class="linkobject"'); ?></td>
    <td><?php echo $form->textwithpicto('', $htmltooltips['SecurityToken'], 1, 0); ?></td>    
</tr>

<tr class="pair">
    <td class="fieldrequired"><?php echo $langs->trans("DeliveryReceiptEmail"); ?></td>
    <td><?php echo $form->selectyesno("delivery_receipt_email", $delivery_receipt_email, 1); ?></td>
    <td><?php echo $form->textwithpicto('', $htmltooltips['DeliveryReceiptEmail'], 1, 0); ?></td>    
</tr>

<tr class="impair">
    <td class="fieldrequired"><?php echo $langs->trans("CcEmail"); ?></td>
    <td><?php echo $form->selectyesno("cc_email", $cc_email, 1); ?></td>
    <td><?php echo $form->textwithpicto('', $htmltooltips['CcEmail'], 1, 0); ?></td>    
</tr>

<tr class="pair">
    <td class="fieldrequired"><?php echo $langs->trans("CcEmails"); ?></td>
    <td><input size="32" type="text" id="cc_emails" name="cc_emails" value="<?php echo $cc_emails; ?>" /></td>
    <td><?php echo $form->textwithpicto('', $htmltooltips['CcEmails'], 1, 0); ?></td>    
</tr>

<tr class="liste_titre">
    <td><?php echo $langs->trans("IntegrationParameters"); ?></td>
    <td><?php echo $langs->trans("Value"); ?></td>
    <td><?php echo $langs->trans("Infos"); ?></td>   
</tr>

<tr class="impair">
    <td class="fieldrequired"><?php echo $langs->trans("UpdateInvoiceStatut"); ?></td>
    <td><?php echo $form->selectyesno("update_invoice_statut", $update_invoice_statut, 1); ?></td>
    <td><?php echo $form->textwithpicto('', $htmltooltips['UpdateInvoiceStatut'], 1, 0); ?></td>    
</tr>

<tr class="pair">
    <td class="fieldrequired"><?php echo $langs->trans("PaymentAutoSend"); ?></td>
    <td><?php echo $form->selectyesno("payment_auto_send", $payment_auto_send, 1); ?></td>
    <td><?php echo $form->textwithpicto('', $htmltooltips['PaymentAutoSend'], 1, 0); ?></td>    
</tr>

<tr class="impair">
    <td class="fieldrequired"><?php echo $langs->trans("BankAccountId"); ?></td>
    <td><?php $form->select_comptes($bank_account_id, 'bank_account_id', 0, '', 1); ?></td>
    <td><?php echo $form->textwithpicto('', $htmltooltips['BankAccountId'], 1, 0); ?></td>    
</tr>
</table>

<br />
<center>
<input type="submit" name="save" class="button" value="<?php echo $langs->trans("Save"); ?>" />
</center>

</form>

<?php llxFooter(''); ?>
