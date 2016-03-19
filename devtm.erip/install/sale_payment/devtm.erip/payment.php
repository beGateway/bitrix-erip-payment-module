<?
use \Bitrix\Main\Localization\Loc;
Loc::loadMessages(__FILE__);

CModule::IncludeModule("sale");

$module_id = "devtm.erip";

$automatic = \Bitrix\Main\Config\Option::get($module_id, "set_automatic");


//автоматическое создание заказа в ЕРИП
if($automatic == "Y")
{
	//получение номера заказа
	$order_id = $GLOBALS["SALE_INPUT_PARAMS"]["ORDER"]["ID"];

	if($order_id <= 0) return;

	//получение статуса [ЕРИП Ожидание оплаты]
	$status = \Bitrix\Main\Config\Option::get($module_id, "order_status_code_erip");

	$arr_order = CSaleOrder::GetByID($order_id);

	$message_ok = "<p>".Loc::getMessage("DEVTM_ERIP_PAYMENT_OK_TEXT", array("#ORDER_ID#" => $order_id, "#PATH_TO_SERVICE#" => \Bitrix\Main\Config\Option::get($module_id, "path_to_service")))."</p>";

	CModule::IncludeModule($module_id);

	if($arr_order["STATUS_ID"] != $status)
	{
		//Вызов Handlers::setEripOrderAutomatic
		$result = Handlers::setEripOrderAutomatic($order_id, $status);
		if($result === true)
		{
			$GLOBALS["STOP_ERIP_HANDLER"] = true; //отмена запуска обработчика
			//Сохранение статуса заказа
			CSaleOrder::Update($order_id, array("STATUS_ID" => $status));
			echo $message_ok;
			unsent($GLOBALS["STOP_ERIP_HANDLER"]);
		}
		else
		{
			ShowError(Loc::getMessage("DEVTM_ERIP_ERROR_TEXT", array("#ERROR#" => $result)));
		}
	}
	else
		echo $message_ok;
}
//заказ создаёт менеджер
else
{
	echo "<p>".Loc::getMessage("DEVTM_ERIP_PAYMENT_OK_TEXT2")."</p>";
}


