<?php
class Tracking
{
	
	static function url($tracking_number, $fedex = false)
	{
		if(strtolower(substr($tracking_number, 0, 1)) == "f")
		{
			$fedex = true;
			$tracking_number = substr($tracking_number, 1);
		}

		if($fedex)
		{
			$url = "https://www.fedex.com/apps/fedextrack/?action=track&trackingnumber=$tracking_number&cntry_code=us";
		}
		elseif(strtolower(substr($tracking_number, 0, 2)) == "1z")
		{
			$url = "http://wwwapps.ups.com/etracking/tracking.cgi?tracknum=".$tracking_number;
		}
		else
		{
			$url = "http://trkcnfrm1.smi.usps.com/PTSInternetWeb/InterLabelInquiry.do".
				"?strOrigTrackNum=".$tracking_number;
		}
		
		return array($url, $tracking_number);
	}
	
	
	static function contents($tracking_row, $db)
	{
		if(empty($tracking_row['pull_sheet_id']) && empty($tracking_row['package_id']))
		{
			if(empty($tracking_row['invoice_num']))
				throw new exception("empty invoice number", 10000);
			else
			{
				$r = $db->query("select 1 from tbl_tracking_numbers where invoice_num = '".$db->escape_string($tracking_row['invoice_num'])."'");
				
				if($r->num_rows > 1)
					throw new exception("unidentifiable packages: invoice number ".$tracking_row['invoice_num'], 10000);
				else
					return $db->query("select * from invoicing.sales where invoice_number = '".$db->escape_string($tracking_row['invoice_num'])."'");
			}
		}
		elseif(empty($tracking_row['package_id']))
		{
			return $db->query("select * from invoicing.sales ".
				"join pull_sheet_id_map on sales_id = autonumber ".
				"where pull_sheet_id = '".$db->escape_string($tracking_row['pull_sheet_id'])."' ".
				"group by sales.autonumber ");
		}
		else
			return $db->query("select * from invoicing.sales where package_id = '".$db->escape_string($tracking_row['package_id'])."'");
	}
	
	
	static function contents2($tracking_row, $db)
	{
		if(empty($tracking_row['pull_sheet_id']) && empty($tracking_row['package_id']))
		{
			if(empty($tracking_row['invoice_num']))
				throw new exception("empty invoice number", 10000);
			else
			{
				$r = $db->query("select 1 from tbl_tracking_numbers where invoice_num = '".$db->escape_string($tracking_row['invoice_num'])."'");
				
				if($r->num_rows > 1)
				{
					throw new exception("unidentifiable packages: invoice number ".$tracking_row['invoice_num'], 10000);
				}
				else
				{
					$r = $db->query("select sum(if(price > 0, price, 0)), sum(snst_amount is null), sum(price > 0) ".
						"from invoicing.sales ".
						"join listing_system.new_specialnotes_shiptype on snst_id = combine_type ".
						"where invoice_number = '".$db->escape_string($tracking_row['invoice_num'])."'");
				}
			}
		}
		elseif(empty($tracking_row['package_id']))
		{
			$r = $db->query("select sum(if(price > 0, price, 0)), sum(snst_amount is null), sum(price > 0) ".
						"from ".
						"(select price, snst_amount from invoicing.sales ".
						"join pull_sheet_id_map on sales_id = autonumber ".
						"join listing_system.new_specialnotes_shiptype on snst_id = combine_type ".
						"where pull_sheet_id = '".$db->escape_string($tracking_row['pull_sheet_id'])."' ".
						"group by autonumber) as t1");
		}
		else
		{
			$r = $db->query("select sum(if(price > 0, price, 0)), sum(snst_amount is null), sum(price > 0) ".
						"from invoicing.sales ".
						"join listing_system.new_specialnotes_shiptype on snst_id = combine_type ".
						"where package_id = '".$db->escape_string($tracking_row['package_id'])."'");
		}
		
		return $r->fetch_row();
	}
}

?>