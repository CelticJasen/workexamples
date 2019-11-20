<?php

class Endicia
{
	//TODO: Logging of each request
	
	var $request_id = 0;
	var $FromName = "Bruce Hershenson";
	var $FromCompany = "eMoviePoster.com";
	var $ReturnAddress1 = "306 Washington Ave";
	var $FromCity = "West Plains";
	var $FromState = "MO";
	var $FromPostalCode = "65775";
	var $FromZIP4 = "";
	var $FromCountry = "US";
	var $FromPhone = "";
	var $FromEmail = "";
	
	function __construct($testing = true, $db)
	{
		$this->db = $db;
		
		if($testing == 2)
		{
			$this->RequesterID = "pspd";
			$this->AccountID = "1107206";
			$this->PassPhrase = "s59Pv2uIwk3f";
			$url = "https://LabelServer.Endicia.com";
			$wsdl = "https://LabelServer.Endicia.com/LabelService/EwsLabelService.asmx?WSDL";
		}
		elseif($testing)
		{
			$this->RequesterID = "test";
			$this->AccountID = "637620";
			$this->PassPhrase = "blah";
			$url = "https://www.envmgr.com/LabelService/EwsLabelService.asmx";
			$wsdl = "https://www.envmgr.com/LabelService/EwsLabelService.asmx?WSDL";
		}
		else
		{
			//Changed to new Commercial Plus account. AK 2016-06-17
			$this->RequesterID = "pspd";
			$this->AccountID = "1107206";
			$this->PassPhrase = "s59Pv2uIwk3f";
			$url = "https://LabelServer.Endicia.com";
			$wsdl = "https://LabelServer.Endicia.com/LabelService/EwsLabelService.asmx?WSDL";
			
			/*
			$this->RequesterID = "lemp";
			$this->AccountID = "934893";
			$this->PassPhrase = "4SO7155GVDs38gY8hm4reFb5e";
			$url = "https://LabelServer.Endicia.com";
			$wsdl = "https://LabelServer.Endicia.com/LabelService/EwsLabelService.asmx?WSDL";
			*/
		}
		
		$this->client = new SoapClient($wsdl, array(
			"trace" => true, //Allow tracing methods.
			"exceptions" => true, //Throw exceptions on error.
			"cache_wsdl" => "WSDL_CACHE_BOTH",
			"user_agent" => "eMoviePoster",
		));
	}
	
	
	function CalculatePostageRates($params)
	{
		if(!empty($params['ToPostalCode']) && $params['ToCountryCode'] != "US" && $params['ToCountryCode'] != "CA")
		{
			$params['ToPostalCode'] = "";
		}
		
		$PostageRatesRequest = array(
			"PostageRatesRequest" => array(
				"RequesterID" => $this->RequesterID,
				"RequestID" => $this->generate_request_id(),
				"CertifiedIntermediary" => array(
					"AccountID" => $this->AccountID,
					"PassPhrase" => $this->PassPhrase,
				),
				"MailClass" => (empty($params['ToCountryCode']) || $params['ToCountryCode'] == "US" ? "Domestic" : "International"), //Domestic or International
				//"DateAdvance" => 0, //Advance by 0 to 7 days
				"WeightOz" => defaults($params["WeightOz"], ""),
				//"MailpieceShape" => 
				"MailpieceDimensions" => array(
					"Length" => defaults($params['Length'], ""), 
					"Width" => defaults($params['Width'], ""), 
					"Height" => defaults($params['Height'], ""),
				),
				//"Machinable" => "TRUE", //Default is "TRUE"
				//"SpecialContents"
				//"LiveAnimalSurcharge"
				"DeliveryTimeDays" => "TRUE", //Whether or not to return delivery time.
				"Services" => array(
					//"CertifiedMail" => "OFF",
					//"COD" => "OFF",
					//"DeliveryConfirmation" => defaults($params['DeliveryConfirmation'], "ON"),
					//"ElectronicReturnReceipt" => defaults($params['ElectronicReturnReceipt'], "OFF"),
					"InsuredMail" => defaults($params['InsuredMail'], "OFF"), //Options are OFF, UspsOnline*, or ENDICIA
					//"RestrictedDelivery"
					//"ReturnReceipt"
					"SignatureConfirmation" => defaults($params['SignatureConfirmation'], "OFF"),
					//"AdultSignature"
					//"AdultSignatureRestrictedDelivery"
					"RegisteredMail" => defaults($params['RegisteredMail'], "OFF"),
					//"AMDelivery" => "OFF", //For Express
				),
				"FromPostalCode" => "65775",
				"ToPostalCode" => defaults($params['ToPostalCode'], ""),
				"ToCountryCode" => defaults($params['ToCountryCode'], "US"), //ISO 3166
				"CODAmount" => 0,
				"InsuredValue" => defaults($params['InsuredValue'], 0),
				"RegisteredMailValue" => defaults($params['RegisteredMailValue'], 0),
			)
		);
		
		if(empty($params['ToCountryCode']) || $params['ToCountryCode'] == "US")
			$PostageRatesRequest["PostageRatesRequest"]["Services"]["DeliveryConfirmation"] = "ON";
		
		$result = $this->client->CalculatePostageRates($PostageRatesRequest);
		
		
		//Mailer::mail("aaron@emovieposter.com", "Request", $this->client->__getLastRequestHeaders()."\r\n\r\n".$this->client->__getLastRequest()."\r\n\r\n".var_export($result, true));
		/*$this->db->query(assemble_insert_query3(array("request" => str_replace("\n", "\r\n", var_export($PostageRatesRequest, true)), 
			"response" => str_replace("\n", "\r\n", var_export($result, true))), 
			"invoicing.endicia_soap_requests", $this->db));*/
		
		if(empty($result->PostageRatesResponse->PostagePrice))
			throw new exception("Couldn't get postage rates because of an error.\r\n\r\n".print_r($result, true), 10000);
			
		//Stupid stupid SOAP servers don't like arrays with only one thing in them apparently. STUPID.
		if(!is_array($result->PostageRatesResponse->PostagePrice))
			$result->PostageRatesResponse->PostagePrice = array($result->PostageRatesResponse->PostagePrice);
		
		//Mailer::mail("steven@emovieposter.com, jasen@emovieposter.com", "endicia autoquote data", '$result=>' . print_r($result,true));
		
		return $result;
	}
	
	
	function GetPostageLabel($params)
	{
		$PostageLabelRequest = array(
			"LabelRequest" => array(
				"Test" => defaults($params['Test'], "YES"),
				"LabelType" => defaults($params['LabelType'], "Default"),
				"LabelSubtype" => defaults($params['LabelSubtype'], "None"),
				"LabelSize" => defaults($params['LabelSize'], "4x6"),
				"ImageFormat" => "PNG",
				"ImageResolution" => "300",
				"ImageRotation" => "None",
				"RequesterID" => $this->RequesterID,
				"AccountID" => $this->AccountID,
				"PassPhrase" => $this->PassPhrase,
				
				
				"RequestID" => $this->generate_request_id(),
				/*"CertifiedIntermediary" => array(
					
					
				),*/
				"MailClass" => defaults($params['MailClass'], "Priority"),
				//"DateAdvance" => 0, //Advance by 0 to 7 days
				"WeightOz" => defaults($params["WeightOz"], ""),
				//"MailpieceShape" => 
				"MailpieceDimensions" => array(
					"Length" => defaults($params['Length'], ""), 
					"Width" => defaults($params['Width'], ""), 
					"Height" => defaults($params['Height'], ""),
				),
				//"PackageTypeIndicator"
				//"Pricing"
				//"Machinable" => "TRUE", //Default is "TRUE"
				"POZipCode" => "65775",
				"IncludePostage" => "TRUE",
				//"PrintConsolidatorLabel"
				//"ReplyPostage"
				"ShowReturnAddress" => "TRUE",
				"Stealth" => defaults($params['Stealth'], "TRUE"),
				"ValidateAddress" => defaults($params['ValidateAddress'], "TRUE"),
				//"SpecialContents"
				//"LiveAnimalSurcharge"
				//"SignatureWaiver"
				//"NoWeekendDelivery"
				//"ServiceLevel"
				//"SundayHolidayDelivery"
				//"ShipDate"
				//"ShipTime"
				//"AutomationRate"
				//"SortType"
				//"EntryFacility"
				"Services" => array(
					"DeliveryConfirmation" => "ON",
					"SignatureConfirmation" => "OFF",
					//"CertifiedMail" => "OFF",
					//"RestrictedDelivery"
					//"ReturnReceipt"
					//"ElectronicReturnReceipt" => "OFF",
					//"HoldForPickup"
					//"OpenAndDistribute"
					//"COD" => "OFF",
					"InsuredMail" => defaults($params['InsuredMail'], "OFF"), //Options are OFF, UspsOnline*, or ENDICIA
					//"AdultSignature"
					//"AdultSignatureRestrictedDelivery"
					//"RegisteredMail" => "OFF",
					//"AMDelivery" => "OFF", //For Express
				),
				"CODAmount" => 0,
				"InsuredValue" => defaults($params['InsuredValue'], 0),
				"RegisteredMailValue" => 0,
				"CostCenter" => "",
				//"CostCenterAlphaNumeric"
				//"ReferenceID"
				//"ReferenceID2"
				//"ReferenceID3"
				//"ReferenceID4"
				//"PartnerCustomerID"
				"PartnerTransactionID" => "none",
				//"BpodClientDunsNumber"
				//"RubberStamp1" => "",
				//"RubberStamp2" => "",
				//"RubberStamp3" => "",
				"ResponseOptions" => array("PostagePrice" => "TRUE"),
				"FromName" => $this->FromName,
				"FromCompany" => $this->FromCompany,
				"ReturnAddress1" => $this->ReturnAddress1,
				//"ReturnAddress2"
				//"ReturnAddress3"
				//"ReturnAddress4"
				"FromCity" => $this->FromCity,
				"FromState" => $this->FromState,
				"FromPostalCode" => $this->FromPostalCode,
				"FromZIP" => $this->FromZIP4,
				"FromCountry" => $this->FromCountry,
				"FromPhone" => $this->FromPhone,
				"FromEmail" => $this->FromEmail,
				"ToName" => defaults($params['ToName'], ""),
				"ToCompany" => defaults($params['ToCompany'], ""),
				"ToAddress1" => defaults($params['ToAddress1'], ""),
				"ToAddress2" => defaults($params['ToAddress2'], ""),
				"ToAddress3" => defaults($params['ToAddress3'], ""),
				"ToAddress4" => defaults($params['ToAddress4'], ""),
				"ToCity" => defaults($params['ToCity'], ""),
				"ToState" => defaults($params['ToState'], ""),
				"ToPostalCode" => defaults($params['ToPostalCode'], ""),
				"ToZIP4" => defaults($params['ToZIP4'], ""),
				//"ToDeliveryPoint"
				"ToCountryCode" => defaults($params['ToCountryCode'], "US"), //ISO 3166
				"ToPhone" => defaults($params['ToPhone'], ""),
				"ToEMail" => defaults($params['ToEMail'], ""),
				"Value" => "",
				//A bunch of hold for pickup & open and distribute options go here
				//Customs form information goes here
			)
		);
		
		$result = $this->client->GetPostageLabel($PostageLabelRequest);
		
		return $result;
	}
	
	
	function GetEnvelope($params)
	{
		$PostageLabelRequest = array(
			"LabelRequest" => array(
				"Test" => defaults($params['Test'], "YES"),
				"LabelType" => defaults($params['LabelType'], "DestinationConfirm"),
				"LabelSubtype" => defaults($params['LabelSubtype'], "None"),
				"LabelSize" => defaults($params['LabelSize'], "EnvelopeSize10"),
				"ImageFormat" => "PNG",
				"ImageResolution" => "300",
				"ImageRotation" => "None",
				"RequesterID" => $this->RequesterID,
				"AccountID" => $this->AccountID,
				"PassPhrase" => $this->PassPhrase,
				
				
				"RequestID" => $this->generate_request_id(),
				/*"CertifiedIntermediary" => array(
					
					
				),*/
				"MailClass" => defaults($params['MailClass'], "First"),
				//"DateAdvance" => 0, //Advance by 0 to 7 days
				"WeightOz" => defaults($params["WeightOz"], ""),
				"MailpieceShape" => defaults($params['MailpieceShape'], "Letter"),
				/*"MailpieceDimensions" => array(
					"Length" => defaults($params['Length'], ""), 
					"Width" => defaults($params['Width'], ""), 
					"Height" => defaults($params['Height'], ""),
				),*/
				//"PackageTypeIndicator"
				//"Pricing"
				//"Machinable" => "TRUE", //Default is "TRUE"
				"POZipCode" => "65775",
				"IncludePostage" => "TRUE",
				//"PrintConsolidatorLabel"
				//"ReplyPostage"
				"ShowReturnAddress" => "TRUE",
				"Stealth" => defaults($params['Stealth'], "TRUE"),
				"ValidateAddress" => defaults($params['ValidateAddress'], "TRUE"),
				//"SpecialContents"
				//"LiveAnimalSurcharge"
				//"SignatureWaiver"
				//"NoWeekendDelivery"
				//"ServiceLevel"
				//"SundayHolidayDelivery"
				//"ShipDate"
				//"ShipTime"
				//"AutomationRate"
				//"SortType"
				//"EntryFacility"
				"Services" => array(
					//"DeliveryConfirmation" => "ON",
					"SignatureConfirmation" => "OFF",
					//"CertifiedMail" => "OFF",
					//"RestrictedDelivery"
					//"ReturnReceipt"
					//"ElectronicReturnReceipt" => "OFF",
					//"HoldForPickup"
					//"OpenAndDistribute"
					//"COD" => "OFF",
					"InsuredMail" => defaults($params['InsuredMail'], "OFF"), //Options are OFF, UspsOnline*, or ENDICIA
					//"AdultSignature"
					//"AdultSignatureRestrictedDelivery"
					//"RegisteredMail" => "OFF",
					//"AMDelivery" => "OFF", //For Express
				),
				"CODAmount" => 0,
				"InsuredValue" => defaults($params['InsuredValue'], 0),
				"RegisteredMailValue" => 0,
				"CostCenter" => "",
				//"CostCenterAlphaNumeric"
				//"ReferenceID"
				//"ReferenceID2"
				//"ReferenceID3"
				//"ReferenceID4"
				//"PartnerCustomerID"
				"PartnerTransactionID" => "none",
				//"BpodClientDunsNumber"
				//"RubberStamp1" => "",
				//"RubberStamp2" => "",
				//"RubberStamp3" => "",
				"ResponseOptions" => array("PostagePrice" => "TRUE"),
				"FromName" => $this->FromName,
				"FromCompany" => $this->FromCompany,
				"ReturnAddress1" => $this->ReturnAddress1,
				//"ReturnAddress2"
				//"ReturnAddress3"
				//"ReturnAddress4"
				"FromCity" => $this->FromCity,
				"FromState" => $this->FromState,
				"FromPostalCode" => $this->FromPostalCode,
				"FromZIP" => $this->FromZIP4,
				"FromCountry" => $this->FromCountry,
				"FromPhone" => $this->FromPhone,
				"FromEmail" => $this->FromEmail,
				"ToName" => defaults($params['ToName'], ""),
				"ToCompany" => defaults($params['ToCompany'], ""),
				"ToAddress1" => defaults($params['ToAddress1'], ""),
				"ToAddress2" => defaults($params['ToAddress2'], ""),
				"ToAddress3" => defaults($params['ToAddress3'], ""),
				"ToAddress4" => defaults($params['ToAddress4'], ""),
				"ToCity" => defaults($params['ToCity'], ""),
				"ToState" => defaults($params['ToState'], ""),
				"ToPostalCode" => defaults($params['ToPostalCode'], ""),
				"ToZIP4" => defaults($params['ToZIP4'], ""),
				//"ToDeliveryPoint"
				"ToCountryCode" => defaults($params['ToCountryCode'], "US"), //ISO 3166
				"ToPhone" => defaults($params['ToPhone'], ""),
				"ToEMail" => defaults($params['ToEMail'], ""),
				"Value" => "",
				//A bunch of hold for pickup & open and distribute options go here
				//Customs form information goes here
			)
		);
		
		$result = $this->client->GetPostageLabel($PostageLabelRequest);
		
		return $result;
	}
	
	
	
	function BuyPostage($RecreditAmount)
	{
		$RecreditRequest = array(
			"RecreditRequest" => array(
				"RequesterID" => $this->RequesterID,
				"RequestID" => $this->generate_request_id(),
				"CertifiedIntermediary" => array(
					"AccountID" => $this->AccountID,
					"PassPhrase" => $this->PassPhrase,
				),
				"RecreditAmount" => $RecreditAmount,
			)
		);
			
		$result = $this->client->BuyPostage($RecreditRequest);
		
		if(!isset($result->RecreditRequestResponse->Status) || $result->RecreditRequestResponse->Status != 0)
			throw new exception("Couldn't add postage due to an error. \r\n\r\n".print_r($result, true), 10040);
		
		return $result;
	}
	
	
	function ChangePassPhrase($NewPassPhrase)
	{
		$ChangePassPhraseRequest = array(
			"ChangePassPhraseRequest" => array(
				"RequesterID" => $this->RequesterID,
				"RequestID" => $this->generate_request_id(),
				"CertifiedIntermediary" => array(
					"AccountID" => $this->AccountID,
					"PassPhrase" => $this->PassPhrase,
				),
				"NewPassPhrase" => $NewPassPhrase,
			)
		);
			
		$result = $this->client->ChangePassPhrase($ChangePassPhraseRequest);
		
		if(!isset($result->ChangePassPhraseRequestResponse->Status) || $result->ChangePassPhraseRequestResponse->Status != 0)
			throw new exception("Couldn't change passphrase due to an error. \r\n\r\n".print_r($result, true), 10040);
		
		return $result;
	}
	
	
	function GetAccountStatus()
	{
		$AccountStatusRequest = array(
			"AccountStatusRequest" => array(
				"RequesterID" => $this->RequesterID,
				"RequestID" => $this->generate_request_id(),
				"CertifiedIntermediary" => array(
					"AccountID" => $this->AccountID,
					"PassPhrase" => $this->PassPhrase,
				),
			)
		);
		
		return $this->client->GetAccountStatus($AccountStatusRequest);
	}
	
	
	
	function generate_request_id()
	{
		return date("Ymd_His").".".++$this->request_id;
	}
	
	
	
	function test()
	{
		try
		{
			//var_dump($this->GetAccountStatus());
			var_dump($this->CalculatePostageRates(array()));
		}
		catch(exception $e)
		{
			echo $e->__toString()."\n\n";
		}
		
		var_dump($this->client->__getLastRequestHeaders());
		var_dump($this->client->__getLastRequest());
	}
	
	
	function prices_summary($PostageRatesResponse)
	{
		ob_start();
		
		foreach($PostageRatesResponse->PostageRatesResponse->PostagePrice as $quote)
		{
			printf("%s: %s\r\n", str_pad($quote->Postage->MailService, 40), str_pad(number_format($quote->Postage->TotalAmount, 2), 6, " ", STR_PAD_LEFT));
		}
		
		return ob_get_clean();
	}
	
	
	function format_amounts($PostageRatesResponse)
	{
		$prices = array();
		
		try
		{
			foreach($PostageRatesResponse->PostageRatesResponse->PostagePrice as $price)
			{					
				$prices[$price->MailClass] = $price->TotalAmount;
			}
		}
		catch(exception $e)
		{
			throw $e;
		}
		
		return $prices;
	}
	
	
	function generate_quotes_amounts($PostageRatesResponse, $insured, $db)
	{
		//Remember to add $3 for postage.
		//2016-06-02: We are removing the $1 discount for Registered.
		$r = $db->query("select usps_name, ship_service from invoicing.quotes_services_usps_map where insured = '".($insured ? 1 : 0)."';");
		
		$services = array();
		while(list($name, $id) = $r->fetch_row())
			$services[$name] = $id;
		
		$prices = array();
		
		try
		{
			foreach($PostageRatesResponse->PostageRatesResponse->PostagePrice as $price)
			{
				try
				{
			   		if(empty($services[$price->MailClass]) || $price->MailClass == "MediaMail") //We don't currently want to quote Media Mail automatically.
						continue;
				}
				catch(exception $e)
				{
					Mailer::mail("aaron@emovieposter.com", "you must let go of the illusion of control", $e->__toString()."\r\n\r\n".print_r($PostageRatesResponse, true));
				}
					
				$prices[$services[$price->MailClass]] = $price->TotalAmount + 3 + $price->Fees->InsuredMail;
			}
		}
		catch(exception $e)
		{
			email_error(var_export($PostageRatesResponse, true));
			throw $e;
		}
		
		return $prices;
	}
}


function defaults(&$value, $default)
{
	if(!isset($value))
		return $default;
	else
		return $value;
}


?>