<?php
try {
  mysqli_report(MYSQLI_REPORT_ERROR);
  $db = new mysqli("localhost", "office", "never eat shredded wheat", "invoicing");
  require_once ("includes.inc.php");
  require_once ("error_exception.inc.php");
  require_once ("config.php");

  $output = Array("package_prices"=> Array());

  //TODO: Quote registered for $700 or over.
  //Only add $2 for registered packages. NOT ANYMORE 2016-06-02
  //For domestic, either insured or not. Not both!
  //For foreign, quote both insured and non-insured
  //Check with endicia on the Endicia insurance bug.
  //Disappearing button bug.

  //2016-06-02: Removed $1 discount from Registered.
  if (!empty($_POST['get_rates'])) {
    try {
      require_once ("endicia.inc.php");

      $r = mysql_query3("select coalesce(ship_zip, Zip), coalesce(ship_country, Country), " . "pounds*16+ounces as weight, length, width, height " . "from quotes_packages join quotes using(quote_id) " . "left join customers on (customer_email = customers.email) " . "left join aa_customers on (customer_email = aa_customers.email) " . "where package_id = '$_POST[package_id]'");

      $quote = array();

      list($quote['ToPostalCode'], $quote['ToCountryCode'], $quote['WeightOz'], $quote['Length'], $quote['Width'], $quote['Height']) = mysql_fetch_row($r);

      if ($quote['ToCountryCode'] == "US")
        $quote['ToPostalCode'] = substr($quote['ToPostalCode'], 0, 5);
      elseif ($quote['ToCountryCode'] == "CA")
        $quote['ToPostalCode'] = str_replace(" ", "", $quote['ToPostalCode']);

      if (empty($quote['WeightOz']))
        throw new exception("Cannot quote: No weight given", 10040);

      $e = new Endicia(false, $db);
      
      //Mailer::mail("steven@emovieposter.com, jasen@emovieposter.com", "data", '$quote=>' . print_r($quote,true). "\n\nPOST=>" . print_r($_POST,true));

      $uninsured_prices = $e->generate_quotes_amounts($e->CalculatePostageRates($quote), false, $db);

      //If we chose insured, get insured prices.
      if (!empty($_POST['insured_value'])) {
        $quote['InsuredMail'] = "Endicia";
        $quote['InsuredValue'] = $_POST['insured_value'];
        $quote['PackageValue'] = $_POST['insured_value'];

        $insured_prices = $e->generate_quotes_amounts($e->CalculatePostageRates($quote), true, $db);

        /*$body = var_export($e->client->__getLastRequest(), true)."\n\n\n";
         $body .= var_export($e->client->__getLastResponse(), true);

         Mailer::mail("aaron@emovieposter.com", "data", $body);*/

        if ($quote['ToCountryCode'] == "US") {
          try {
            //Quote registered.
            $quote['RegisteredMail'] = "ON";
            $quote['RegisteredMailValue'] = $quote['InsuredValue'];

            $quote['InsuredMail'] = "OFF";
            $quote['InsuredValue'] = 0;

            $prices2 = $e->generate_quotes_amounts($e->CalculatePostageRates($quote), true, $db);

            if (isset($prices2[2])) {
              //Whatever we got for Priority (insured), set that as the Registered Mail price.
              $insured_prices[18] = $prices2[2];
              //2016-06-02: Removed $1 discount from Registered per Thom
            }
          } catch(exception $e) {
            email_error("Couldn't get registered.\r\n" . $e->__toString());
          }
        }
      } else
        $insured_prices = array();

      /*
       Clear out values we don't want
       */
      $clear_values = array(
        1,
        2,
        3,
        4,
        5
      );

      foreach ($clear_values as $ship_service) {
        if (empty($insured_prices[$ship_service]))
          $insured_prices[$ship_service] = "";

        if (empty($uninsured_prices[$ship_service]))
          $uninsured_prices[$ship_service] = "";
      }

      /*
       Populate price_data_list with $prices and $uninsured_prices.

       Foreign will get uninsured and insured prices.

       Domestic will get either uninsured or insured prices.
       */
      $price_data_list = array();

      if (empty($insured_prices)) {
        $prices = $uninsured_prices;
      } else {
        /*
         For international, quote both insured and uninsured.
         */
        if ($quote['ToCountryCode'] != "US") {
          $prices = $uninsured_prices;

          foreach ($insured_prices as $k=>$v) {
            if (!empty($v))
              $prices[$k] = $v;
          }
        }
        /*
         For domestic values between $1 and $50,
         change _uninsured_ prices to display as insured prices,
         for the following services only: Priority Mail, Priority Mail Express.

         This is because USPS automatically gives $50 insurance for
         domestic Priority, and $100 insurance for domestic Priority Mail Express.
         */
        elseif ($_POST['insured_value'] <= 50) {
          $prices = $insured_prices;

          unset($prices[2], $prices[4]);
          $prices[1] = $uninsured_prices[1];
          $prices[3] = $uninsured_prices[3];
        }
        /*
         For domestic values between $51 and $100,
         display the Express uninsured price as insured.

         Since USPS gives $100 insurance for domestic Priority Mail Express,
         we don't need to quote insurance.

         But since USPS only gives $50 insurance for regular domestic Priority,
         we must quote insurance.
         */
        elseif ($_POST['insured_value'] > 50 and $_POST['insured_value'] <= 100) {
          $prices = $insured_prices;

          unset($prices[4]);
          $prices[3] = $uninsured_prices[3];
        }
        /*
         For over $100, use insured prices.
         */
        else {
          $prices = $insured_prices;
        }
      }

      /*
       2014-08-14: Thom requested Express be removed. I asked Phil,
       who asked Angie, who said she doesn't want it because
       "it's a bunch of extra crap and junk".

       Phillip said to remove it. AK

       When are we gonna... I dunno, actually think about what
       the customer wants instead of what the employees want?
       */
      if ($quote['ToCountryCode'] == "US") {
        unset($prices[3], $prices[4]);
      }

      foreach ($prices as $ship_service=>$cost) {
        //the two below requests are from the email "quote program adjustment ideas"

        /*
         * First, is there a way to set a maximum dollar amount to
         * allow first class international prices to generate?
         * We do not offer first class for qualifying weight and
         * dimension packages that are $300 or more. I figure it would
         * be easier for me as on occasion, I forget to just delete
         * the first class prices for these $300+ packages. If making
         * the maximum amount that a first class package be $299 when
         * I generate shipping rates, that would be great! If not,
         * then oh well...was just an idea.
         * john
         * SS 2019-03-05
         */
        if ($quote['ToCountryCode'] != "US" && $ship_service == '5' && floatval($quote['PackageValue']) >= 300) {
          $cost = "";
        }

        /*
         * Second, can we set the minimum amount for USPS Registered Mail
         * at $700? We do not offer the registered mail option unless a
         * domestic package has a value of at least $700. I've always just
         * deleted the price for this field for domestic packages $699 or less,
         * and its taken me 3 1/2 years to think "can't we just set the minimum
         * amount to $700 when generating quotes?" Again, if this can't be done,
         * then I'll just deal with it and continue what I've been doing.
         * john
         * JJ 2019-03-05
         */

        if ($quote['ToCountryCode'] == "US" && $ship_service == '18' && floatval($quote['PackageValue']) < 700) {
          $cost = "";
        }

        $price_data_list[] = array(
          "ship_service"=>$ship_service,
          "cost"=>$cost,
          "free_item"=>0,
          "who"=>$_POST['who'],
          "package_id"=>$_POST['package_id'],
          "alternate_address"=>0,
        );
      }
      unset($amount);
    } catch(exception $e) {
      throw new exception("It broke.\r\n\r\n" . $e->__toString(), 10040);
    }
  } else {
    $price_data_list = json_decode($_POST['price_data'], true);
  }

  foreach ($price_data_list as $price_data) {
    if (trim($price_data['cost']) == "") {
      mysql_query3(sprintf("delete from invoicing.quotes_amounts " . "where package_id = '%s' and ship_service = '%s' and free_item = '%s' and alternate_address = '%s'", mysql_real_escape_string($price_data['package_id']), mysql_real_escape_string($price_data['ship_service']), mysql_real_escape_string($price_data['free_item']), mysql_real_escape_string($price_data['alternate_address'])));

      mysql_query3(sprintf("update invoicing.quotes_packages set `who` = '%s' where package_id = '%s'", mysql_real_escape_string($price_data['who']), mysql_real_escape_string($price_data['package_id'])));
    } else {
      //Needs at least package_id, ship_service, free_item, alternate_address, who
      mysql_query3(assemble_insert_query2($price_data, "invoicing.quotes_amounts", true));
    }

    $r = mysql_query3(sprintf("select * from invoicing.quotes_amounts " . "where package_id = '%s' and ship_service = '%s' and free_item = '%s' and alternate_address = '%s'", mysql_real_escape_string($price_data['package_id']), mysql_real_escape_string($price_data['ship_service']), mysql_real_escape_string($price_data['free_item']), mysql_real_escape_string($price_data['alternate_address'])));

    if (mysql_num_rows($r))
      $output['package_prices'][] = mysql_fetch_assoc($r);
  }

  echo json_encode($output);
} catch(Exception $e) {
  if ($e->getCode() == 10040) {
    echo json_encode(Array("message"=>$e->getMessage()));
    email_error($e->__toString());
  } else {
    echo json_encode(Array("error"=>"There was an error. I will inform the admin of this problem."));
    email_error($e->__toString());
  }
}
