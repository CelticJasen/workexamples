<?php
if (!empty($_GET['type'])) {
	switch($_GET['type']) {
		case "package_type":
			echo file_get_contents('./autocomplete_data/package_type.json');
			break;
		case "package_location":
			echo file_get_contents('./autocomplete_data/package_location.json');
			break;
		case "consignor_money":
			echo file_get_contents('./autocomplete_data/consignor_money.json');
			break;
		case "after_refund_action":
			echo file_get_contents('./autocomplete_data/after_refund_action.json');
			break;
  }
}
?>