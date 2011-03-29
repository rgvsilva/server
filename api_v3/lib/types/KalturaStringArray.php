<?php
/**
 * An array of KalturaString
 * 
 * @package api
 * @subpackage objects
 */
class KalturaStringArray extends KalturaTypedArray
{
	public static function fromStringArray(array $strings)
	{
		$stringArray = new KalturaStringArray();
		foreach($strings as $string)
		{
			$stringObject = new KalturaString();
			$stringObject->value = $string;
			$stringArray[] = $stringObject;
		}
		return $stringArray;
	}
	
	public function __construct()
	{
		return parent::__construct("KalturaString");
	}
}
