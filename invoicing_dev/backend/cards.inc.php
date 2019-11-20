<?php


class Card
{
	/**
	 * Return Card Type based on the digits in the Card Number
	 *
	 * @param int $number
	 * @return string
	 */
	static function type($number)
	{
	  $number = preg_replace('/[^0-9]*/', '', $number);
	  settype($number, 'string');

	  // American Express
	  if (preg_match('/^3[47][0-9]{13}$/', $number) != 0) {
	    return "American Express";
	  }

	  // Discover
	  if (preg_match('/^6(?:011|5[0-9]{2})[0-9]{12}$/', $number) != 0) {
	    return "Discover Card";
	  }

	  // MasterCard
	  if (preg_match('/^(5[1-5]|2[2-7])[0-9]{14}$/', $number)) {
	    return "MasterCard";
	  }

	  // Visa
	  if (preg_match('/^4[0-9]{12}(?:[0-9]{3})?$/', $number) != 0) {
	    return "Visa";
	  }

	  // No match, return empty string
	  return NULL;
	}
}

?>
