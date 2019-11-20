<?php


class ConsignorDump
{
	function __construct(mysqli $db)
	{
		require_once("/webroot/includes/PHPExcel/PHPExcel.php");
		require_once("/webroot/includes/PHPExcel/PHPExcel/Cell/AdvancedValueBinder.php");
		PHPExcel_Cell::setValueBinder( new PHPExcel_Cell_AdvancedValueBinder() );
		$this->db = $db;
	}
	
	
	function create_xls($data, $columns, $filename)
	{
		/*
			Method: create_xls
			
			$data - Numeric array of rows (associative arrays)
			
			$columns - Specifies columns to output, column order, and column labels.
				Associative array with entries in the format of Column => Label.
		*/
		
		$cacheMethod = PHPExcel_CachedObjectStorageFactory::cache_in_memory_gzip;
		PHPExcel_Settings::setCacheStorageMethod($cacheMethod);

		
		$objPHPExcel = new PHPExcel();

		// Set document properties
		$objPHPExcel->getProperties()->setCreator("eMoviePoster.com")
									 ->setTitle("Consignor History");
									 //->setSubject("Office 2007 XLSX Test Document")
									 //->setDescription("Test document for Office 2007 XLSX, generated using PHP classes.")
									 //->setKeywords("office 2007 openxml php")
									 //->setCategory("Test result file");
		
		
		// Create a first sheet
		$objPHPExcel->setActiveSheetIndex(0);
		$letter = "A";
		foreach($columns as $column => $label)
		{
			$objPHPExcel->getActiveSheet()->setCellValue($letter."1", $label);
			$objPHPExcel->getActiveSheet()->getColumnDimension($letter)->setAutoSize(true);
			$letter++;
		}
		
		
		
		/*
		$objPHPExcel->getActiveSheet()->getColumnDimension('E')->setOutlineLevel(1)
		                                                       ->setVisible(false)
		                                                       ->setCollapsed(true);*/

		$objPHPExcel->getActiveSheet()->freezePane('A2');
		$objPHPExcel->getActiveSheet()->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 1);
		
		
		// Add data
		$i = 2;
		foreach($data as $row)
		{
			$letter = "A";
			foreach($columns as $column => $label)
			{
				 
				switch($column)
				{
					case "Date":
						$objPHPExcel->getActiveSheet()->setCellValue($letter.$i, $row[$column]);
						$objPHPExcel->getActiveSheet()->getStyle($letter.$i)
							->getNumberFormat()
							->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_DATE_YYYYMMDD2);
						break;
					
					case "eBay_Item_Num":
						$objPHPExcel->getActiveSheet()->setCellValueExplicit(
						    $letter.$i,
						    $row[$column],
						    PHPExcel_Cell_DataType::TYPE_STRING
						);
						break;
						
					case "Price":
					case "Price After Commission":
						$objPHPExcel->getActiveSheet()->setCellValue($letter.$i, "$".$row[$column]);
						break;
						
					default:
						$objPHPExcel->getActiveSheet()->setCellValue($letter.$i, $row[$column]);
						break;
				}
				
				$letter++;
			}
			
			$i++;
		}
		
		$objPHPExcel->getActiveSheet()->setCellValue("D".$i, "=SUM(D2:D".($i-1).")");
		$objPHPExcel->getActiveSheet()->setCellValue("E".$i, "=SUM(E2:E".($i-1).")");

		// Set active sheet index to the first sheet, so Excel opens this as the first sheet
		$objPHPExcel->setActiveSheetIndex(0);

		$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
		$objWriter->save("/webroot/invoicing/download/$filename");
		
		return "/webroot/invoicing/download/$filename";
	}
	
	
	function dump($consignor, $filename)
	{
		$data = $this->fetch($consignor);
		
		return $this->create_xls($data, array(
			"Date" => "Date",
			"eBay_Item_Num" => "Item Number",
			"Lot_Number" => "Title",
			"Price" => "Price",
			"Price After Commission" => "Price After Commission"
		), $filename);
	}
	
	
	function fetch($consignor, $filter = true)
	{
		require_once("/webroot/accounting/backend/payouts.inc.php");
		$used = array();
		$data = array();
		
		$r = $this->db->query("select CommissionRate ".
			"from listing_system.tbl_consignorlist ".
			"where ConsignorName = '".$this->db->escape_string($consignor)."'");
			
		list($rate) = $r->fetch_row();
		$rates = Payouts::parse_commission_rate($rate);
		
		
		
		$r = $this->db->query("select * ".
			"from listing_system.tbl_Current_Consignments ".
			"where Consignee = '".$this->db->escape_string($consignor)."' ".
			"order by `Date` desc, Lot_Number, Title");
		
		$count = $gross = $net = 0;
		
		while($row = $r->fetch_assoc())
		{
			if(in_array($row['eBay_Item_Num'], $used))
				continue;
			
			if(empty($row['Lot_Number']))
				$row['Lot_Number'] = $row['Title']." ".$row['additional info']." ".$row['Type'];
				
			
			if(!empty($row['commission']))
			{
				$row['Price After Commission'] = bcsub($row['Price'], $row['commission'], 2);
			}
			else
			{
				list($commission, $price_minus_commission, $rate_id) = Payouts::item_amount($row['Price'], $rates);
				$row['Price After Commission'] = number_format(round($price_minus_commission, 2), 2);
			}
			
			$count++;
			
			$used[] = $row['eBay_Item_Num'];
			$data[] = $row;
		}
		
		return $data;
	}
}


if(!empty($_REQUEST['ConsignorName']))
{
	set_time_limit(5*60);
	header("Content-Type: application/vnd.ms-excel");

	set_error_handler("exception_error_handler", E_ALL);
	
	mysqli_report(MYSQLI_REPORT_STRICT | MYSQLI_REPORT_ERROR);
	
	$db = new mysqli("localhost", "root", "k8!05Er-05", "invoicing");
	$db->set_charset("utf8");
	
	$dump = new ConsignorDump($db);
	
	$filename = $_REQUEST['ConsignorName']."_".date("Ymd_His").".xls";
	
	header("Content-Disposition: attachment; filename=$filename");
	
	$filename = $dump->dump($_REQUEST['ConsignorName'], $filename);
	
	echo file_get_contents($filename);
	
	unlink($filename);
}

?>