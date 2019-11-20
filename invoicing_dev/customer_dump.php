<?php


class Customer_Dump
{
	function __construct($db)
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
									 ->setTitle("Customer History");
									 //->setSubject("Office 2007 XLSX Test Document")
									 //->setDescription("Test document for Office 2007 XLSX, generated using PHP classes.")
									 //->setKeywords("office 2007 openxml php")
									 //->setCategory("Test result file");
		
		
		// Create a first sheet
		$objPHPExcel->setActiveSheetIndex(0);
		$objPHPExcel->getActiveSheet()->setTitle("Purchases");
		$letter = "A";
		foreach($columns as $column => $label)
		{
			$objPHPExcel->getActiveSheet()->setCellValue($letter."3", $label);
			$objPHPExcel->getActiveSheet()->getColumnDimension($letter)->setAutoSize(true);
			$letter++;
		}
		
		/*
		$objPHPExcel->getActiveSheet()->getColumnDimension('E')->setOutlineLevel(1)
		                                                       ->setVisible(false)
		                                                       ->setCollapsed(true);*/

		$objPHPExcel->getActiveSheet()->freezePane('Z4');
		$objPHPExcel->getActiveSheet()->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 1);
		
		
		// Add data
		$i = 4;
		foreach($data as $row)
		{
			$letter = "A";
			foreach($columns as $column => $label)
			{
				 
				switch($column)
				{
					case "date":
						$objPHPExcel->getActiveSheet()->setCellValue($letter.$i, $row[$column]);
						$objPHPExcel->getActiveSheet()->getStyle($letter.$i)
							->getNumberFormat()
							->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_DATE_YYYYMMDD2);
						break;
					
					case "ebay_item_number":
						$objPHPExcel->getActiveSheet()->setCellValueExplicit(
						    $letter.$i,
						    $row[$column],
						    PHPExcel_Cell_DataType::TYPE_STRING
						);
						break;
						
					case "price":
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
		
		$objPHPExcel->getActiveSheet()
		    ->getStyle('D2')
		    ->getNumberFormat()
		    ->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
		$objPHPExcel->getActiveSheet()->setCellValue("D"."1", "Price Total");
		$objPHPExcel->getActiveSheet()->setCellValue("D"."2", "=SUM(D4:D" . $i . ")");

		//TODO add a second worksheet to the document with the shipping information
		$customer_id = $_REQUEST['customer_id'];
		$columns2 = array(
			"shipping_method" => "Shipper",
			"shipping_date" => "Ship Date",
			"shipping_charged" => "Ship Cost",);
		
		$shipqueryresults = $this->db->query("SELECT shipping_method, COALESCE(cc_date_processed, date_of_invoice) AS shipping_date,shipping_charged ".
			"FROM invoicing.invoices ".
			"WHERE customer_id = '" . $customer_id . "' ".
        	"AND `status` = 'shipped' ".
        	"AND shipping_charged > 0 ".
        	"ORDER BY 'shipping_date'");
		
		// Create a second sheet
		$objPHPExcel->createSheet(1);
        $objPHPExcel->setActiveSheetIndex(1);
        $objPHPExcel->getActiveSheet()->setTitle("Shipping");
        $letter = 'A';
		foreach($columns2 as $column => $label)
		{
			$objPHPExcel->getActiveSheet()->setCellValue($letter."3", $label);
			$objPHPExcel->getActiveSheet()->getColumnDimension($letter)->setAutoSize(true);
			$letter++;
		}
		
		$objPHPExcel->getActiveSheet()->freezePane('Z4');
		$objPHPExcel->getActiveSheet()->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 1);
		
		// Add data
		$i = 4;
		foreach($shipqueryresults as $row)
		{
			$letter = "A";
			foreach($columns2 as $column => $label)
			{
				 
				switch($column)
				{
					case "shipping_date":
						$objPHPExcel->getActiveSheet()->setCellValue($letter.$i, $row[$column]);
						$objPHPExcel->getActiveSheet()->getStyle($letter.$i)
							->getNumberFormat()
							->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_DATE_YYYYMMDD2);
						break;
					
					case "shipping_method":
						$objPHPExcel->getActiveSheet()->setCellValueExplicit(
						    $letter.$i,
						    $row[$column],
						    PHPExcel_Cell_DataType::TYPE_STRING
						);
						break;
						
					case "shipping_charged":
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
		
		$objPHPExcel->getActiveSheet()
		    ->getStyle('C2')
		    ->getNumberFormat()
		    ->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
		$objPHPExcel->getActiveSheet()->setCellValue("C"."1", "Shipping Cost Total");
		$objPHPExcel->getActiveSheet()->setCellValue("C"."2", "=SUM(C4:C" . $i . ")");
		
		// Set active sheet index to the first sheet, so Excel opens this as the first sheet
		$objPHPExcel->setActiveSheetIndex(0);

		$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
		$objWriter->save("/webroot/invoicing/download/$filename");
		
		return "/webroot/invoicing/download/$filename";
	}
	
	
	function dump($email, $filename)
	{
		$data = $this->fetch($email);
		
		return $this->create_xls($data, array(
			"date" => "Date",
			"ebay_item_number" => "Item Number",
			"ebay_title" => "Title",
			"price" => "Price",
			"condition" => "Condition",
		), $filename);
	}
	
	
	function fetch($email, $filter = true)
	{
		$data = array();
		
		$r = $this->db->query("select * from invoicing.sales ".
			"where ebay_email = '".$this->db->escape_string($email)."' ".
			"and invoice_printed != 0 ".($filter ? "and ebay_item_number regexp '[0-9]+' and price > 0 " : " ").
			"order by `date` desc");
		
		while($row = $r->fetch_assoc())
		{
			/*if($date = strtotime($row['date']))
				$row['date'] = date("m/d/Y", $date);*/
			
			if($item = AuctionHistory::getItemByItemNumber($this->db, $row['ebay_item_number']))
			{
				$row['condition'] = $item->getCondition();
			}
			else
			{
				$row['condition'] = "";
			}
			
			$data[] = $row;
		}
		
		return $data;
	}
}


if(!empty($_REQUEST['email']))
{
	header("Content-Type: application/vnd.ms-excel");

	set_error_handler("exception_error_handler", E_ALL);
	
	mysqli_report(MYSQLI_REPORT_STRICT | MYSQLI_REPORT_ERROR);
	
	$db = new mysqli("localhost", "root", "k8!05Er-05", "invoicing");
	$db->set_charset("utf8");
	
	$dump = new Customer_Dump($db);
	
	$filename = $_REQUEST['email']."_".date("Ymd_His").".xls";
	
	header("Content-Disposition: attachment; filename=$filename");
	
	$filename = $dump->dump($_REQUEST['email'], $filename);
	
	echo file_get_contents($filename);
	
	unlink($filename);
}

?>