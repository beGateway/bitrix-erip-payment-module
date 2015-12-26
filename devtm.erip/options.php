<?

use Bitrix\Main\Localization\Loc;

if( ! $USER->isAdmin() || ! \Bitrix\Main\Loader::includeModule("sale") ) return ;

Loc::loadMessages(__FILE__);

global $APPLICATION;

$module_id = "devtm.erip";

$all_options = array(
					"address_for_send" => array(Loc::getMessage("DEVTM_ERIP_DOMAIN_API_DESC"), "text"),
					"shop_id" => array(Loc::getMessage("DEVTM_ERIP_SHOP_ID_DESC"), "text"),
					"shop_key" => array(Loc::getMessage("DEVTM_ERIP_SHOP_KEY_DESC"), "text"),
					"notification_url" => array(Loc::getMessage("DEVTM_ERIP_NOTIFICATION_URL_DESC"), "text"),
					"service_number" => array(Loc::getMessage("DEVTM_ERIP_SERVICE_NUMBER_DESC"), "text"),
					"company_name" => array(Loc::getMessage("DEVTM_ERIP_COMPANY_NAME_DESC"), "text"),
					"sale_name" => array(Loc::getMessage("DEVTM_ERIP_SALE_NAME_DESC"), "text"),
					"path_to_service" => array(Loc::getMessage("DEVTM_ERIP_PATH_TO_SERVICE_DESC"), "text"),
					"payment_description" => array(Loc::getMessage("DEVTM_ERIP_PAYMENT_OPT_DESC"), "textarea"),
					"service_info" => array(Loc::getMessage("DEVTM_ERIP_FOR_PAYER_DESC"), "text"),
					"receipt" => array(Loc::getMessage("DEVTM_ERIP_RECEIPT_PAYER_DESC"), "text"),
				);
$tabs = array(
			array(
				"DIV" => "edit1",
				"TAB" => Loc::getMessage("DEVTM_ERIP_TAB_NAME"),
				"ICON" => "erip-icon",
				"TITLE" => Loc::getMessage("DEVTM_ERIP_TAB_DESC")
			),
		);
		
$o_tab = new CAdminTabControl("EripTabControl", $tabs);

if( $REQUEST_METHOD == "POST" && strlen( $save . $reset ) > 0 && check_bitrix_sessid() )
{
	if( strlen($reset) > 0 )
	{
		foreach( $all_options as $name => $desc )
		{
			\Bitrix\Main\Config\Option::delete( $module_id, $name );
		}
	}
	else
	{
		foreach( $all_options as $name => $desc )
		{
			if( isset( $_REQUEST[$name] ) )
				\Bitrix\Main\Config\Option::set( $module_id, $name, $_REQUEST[$name] );
		}
	}
	
	LocalRedirect($APPLICATION->GetCurPage()."?mid=".urlencode($module_id)."&lang=".urlencode(LANGUAGE_ID)."&".$o_tab->ActiveTabParam());
}

$o_tab->Begin();
?>

<form method="post" action="<?echo $APPLICATION->GetCurPage()?>?mid=<?=urlencode($module_id)?>&amp;lang=<?echo LANGUAGE_ID?>">
<?
$o_tab->BeginNextTab();
foreach( $all_options as $name => $desc ):
	$cur_opt_val = htmlspecialcharsbx(Bitrix\Main\Config\Option::get( $module_id, $name ));
	$name = htmlspecialcharsbx($name);
?>
	<tr>
		<td width="40%">
			<label for="<?echo $name?>"><?echo $desc[0]?>:</label>
		</td>
<?
	if($desc[1] == "text"):
?>
		<td width="60%">
			<input type="text" id="<?echo $name?>" value="<?echo $cur_opt_val?>" name="<?echo $name?>">
		</td>
<?
	elseif($desc[1] == "textarea"):
?>
		<td width="60%">
			<textarea cols="60" rows="15" name="<?echo $name?>" id="<?echo $name?>"><?if(strlen($cur_opt_val) > 0) echo $cur_opt_val; else echo Loc::getMessage("DEVTM_ERIP_PAYMENT_DESC")?></textarea>
		</td>
	<?endif;?>
	</tr>
<?endforeach?>
<?$o_tab->Buttons();?>
	<input type="submit" name="save" value="<?= Loc::getMessage("DEVTM_ERIP_SAVE_BTN_NAME")?>" title="<?= Loc::getMessage("DEVTM_ERIP_SAVE_BTN_NAME")?>" class="adm-btn-save">
	<input type="submit" name="reset" title="<?= Loc::getMessage("DEVTM_ERIP_RESET_BTN_NAME")?>" OnClick="return confirm('<?echo AddSlashes(Loc::getMessage("DEVTM_ERIP_RESTORE_WARNING"))?>')" value="<?= Loc::getMessage("DEVTM_ERIP_RESET_BTN_NAME")?>">
	<?=bitrix_sessid_post();?>
<?$o_tab->End();?>
</form>