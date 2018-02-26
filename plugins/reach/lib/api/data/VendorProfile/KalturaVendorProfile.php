<?php
/**
 * @package plugins.reach
 * @subpackage api.objects
 */
class KalturaVendorProfile extends KalturaObject implements IRelatedFilterable
{
    const MAX_DICTIONARY_LENGTH = 1000;
	/**
	 * @var int
	 * @readonly
	 * @filter eq,in,order
	 */
	public $id;

	/**
	 * @var int
	 * @readonly
	 */
	public $partnerId;

	/**
	 * @var time
	 * @readonly
	 * @filter gte,lte,order
	 */
	public $createdAt;

	/**
	 * @var time
	 * @readonly
	 * @filter gte,lte,order
	 */
	public $updatedAt;

	/**
	 * @var KalturaVendorProfileStatus
	 * @readonly
	 * @filter eq,in
	 */
	public $status;

	/**
	 * @var KalturaVendorProfileType
	 * @filter eq,in
	 */
	public $profileType;

	/**
	 * @var KalturaCatalogItemLanguage
	 */
	public $defaultSourceLanguage;

	/**
	 * @var KalturaVendorCatalogItemOutputFormat
	 */
	public $defaultOutputFormat;

	/**
	 * @var KalturaNullableBoolean
	 */
	public $enableMachineModeration;

	/**
	 * @var KalturaNullableBoolean
	 */
	public $enableHumanModeration;

	/**
	 * @var KalturaNullableBoolean
	 */
	public $autoDisplayMachineCaptionsOnPlayer;

	/**
	 * @var KalturaNullableBoolean
	 */
	public $autoDisplayHumanCaptionsOnPlayer;

	/**
	 * @var KalturaNullableBoolean
	 */
	public $enableMetadataExtraction;

	/**
	 * @var KalturaNullableBoolean
	 */
	public $enableSpeakerChangeIndication;

	/**
	 * @var KalturaNullableBoolean
	 */
	public $enableAudioTags;

	/**
	 * @var KalturaNullableBoolean
	 */
	public $enableProfanityRemoval;

	/**
	 * @var int
	 */
	public $maxCharactersPerCaptionLine;

	/**
	 * @var KalturaVendorProfileRulesArray
	 */
	public $rules;

	/**
	 * @var KalturaBaseVendorCredit
	 * @requiresPermission update
	 */
	public $credit;

	/**
	 * @var int
	 * @readonly
	 */
	public $usedCredit;

	/**
	 * @var KalturaDictionaryArray
	 */
	public $dictionaries;

	private static $map_between_objects = array
	(
		'id',
		'partnerId',
		'createdAt',
		'updatedAt',
		'status',
		'profileType' => 'type',
		'defaultSourceLanguage',
		'defaultOutputFormat',
		'enableMachineModeration',
		'enableHumanModeration',
		'autoDisplayMachineCaptionsOnPlayer',
		'autoDisplayHumanCaptionsOnPlayer',
		'enableMetadataExtraction',
		'enableSpeakerChangeIndication',
		'enableAudioTags',
		'enableProfanityRemoval',
		'maxCharactersPerCaptionLine',
		'rules' => 'rulesArray',
		'credit',
		'usedCredit',
		'dictionaries' => 'dictionariesArray',
	);

	/* (non-PHPdoc)
	 * @see KalturaCuePoint::getMapBetweenObjects()
	 */
	public function getMapBetweenObjects()
	{
		return array_merge(parent::getMapBetweenObjects(), self::$map_between_objects);
	}

	/* (non-PHPdoc)
 	 * @see KalturaObject::toInsertableObject()
 	 */
	public function toInsertableObject($object_to_fill = null, $props_to_skip = array())
	{
		if (is_null($object_to_fill))
			$object_to_fill = new VendorProfile();

		return parent::toInsertableObject($object_to_fill, $props_to_skip);
	}

	public function validateForInsert($propertiesToSkip = array())
	{
		$this->validate();

		$languages = array();
		foreach($this->dictionaries as $dictionary)
		{
			/* @var KalturaDictionary $dictionary */
			if (in_array($dictionary->language, $languages))
				throw new KalturaAPIException(KalturaReachErrors::DICTIONARY_LANGUAGE_DUPLICATION, $dictionary->language);

			if (!$this->validateDictionaryLength($dictionary->data))
				throw new KalturaAPIException(KalturaReachErrors::MAX_DICTIONARY_LENGTH_EXCEEDED , $dictionary->language, self::MAX_DICTIONARY_LENGTH);

			$languages[] = $dictionary->language;
		}

		return parent::validateForInsert($propertiesToSkip);
	}

	public function validateForUpdate($sourceObject, $propertiesToSkip = array())
	{
		$this->validate($sourceObject);

		//validate we are not inserting or adding duplicates dictionaries
		$languages = array();
		foreach($this->dictionaries as $dictionary)
		{
			/* @var KalturaDictionary $dictionary */
			if (in_array($dictionary->language, $languages))
				throw new KalturaAPIException(KalturaReachErrors::DICTIONARY_LANGUAGE_DUPLICATION, $dictionary->language);

				if (!$this->validateDictionaryLength($dictionary->data))
					throw new KalturaAPIException(KalturaReachErrors::MAX_DICTIONARY_LENGTH_EXCEEDED , $dictionary->language, self::MAX_DICTIONARY_LENGTH);
			$languages[] = $dictionary->language;
		}

		/* @var VendorProfile $sourceObject */
		foreach($sourceObject->getDictionariesArray() as $dictionary)
		{
			/* @var kDictionary $dictionary */
			if (in_array($dictionary->getLanguage(), $languages))
				throw new KalturaAPIException(KalturaReachErrors::DICTIONARY_LANGUAGE_DUPLICATION, $dictionary->getLanguage());

			if (!$this->validateDictionaryLength($dictionary->getData()))
				throw new KalturaAPIException(KalturaReachErrors::MAX_DICTIONARY_LENGTH_EXCEEDED , $dictionary->getLanguage(), self::MAX_DICTIONARY_LENGTH);

			$languages[] = $dictionary->getLanguage();
		}

		return parent::validateForUpdate($sourceObject, $propertiesToSkip);
	}

	private function validateDictionaryLength($data){
		return strlen($data) <= self::MAX_DICTIONARY_LENGTH ? true : false;
	}

	private function validate(VendorProfile $sourceObject = null)
	{
		if (!$sourceObject) //Source object will be null on insert
		{
			$this->validatePropertyNotNull("profileType");
			$this->validatePropertyNotNull("credit");
		}

		return;
	}

	public function getExtraFilters()
	{
		return array();
	}

	public function getFilterDocs()
	{
		return array();
	}


	/* (non-PHPdoc)
	 * @see KalturaObject::fromObject()
	 */
	public function doFromObject($dbObject, KalturaDetachedResponseProfile $responseProfile = null)
	{
		/* @var $dbObject VendorProfile */
		parent::doFromObject($dbObject, $responseProfile);

		if($this->shouldGet('credit', $responseProfile) && !is_null($dbObject->getCredit())) 
		{
			$this->credit = KalturaBaseVendorCredit::getInstance($dbObject->getCredit(),$responseProfile);
		}
	}
}