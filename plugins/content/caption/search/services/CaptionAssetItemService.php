<?php

/**
 * Search caption asset items
 *
 * @service captionAssetItem
 * @package plugins.captionSearch
 * @subpackage api.services
 */
class CaptionAssetItemService extends KalturaBaseService
{

	const SIZE_OF_ENTRIES_CHUNK = 150;
	const MAX_NUMBER_OF_ENTRIES = 1000;
	
	public function initService($serviceId, $serviceName, $actionName)
	{
		$ks = kCurrentContext::$ks_object ? kCurrentContext::$ks_object : null;
		
		if (($actionName == 'search') &&
		  (!$ks || (!$ks->isAdmin() && !$ks->verifyPrivileges(ks::PRIVILEGE_LIST, ks::PRIVILEGE_WILDCARD))))
		{
			KalturaCriterion::enableTag(KalturaCriterion::TAG_WIDGET_SESSION);
			entryPeer::setUserContentOnly(true);
		}

		parent::initService($serviceId, $serviceName, $actionName);
		
		if($actionName != 'parse')
		{
			$this->applyPartnerFilterForClass('asset');
			$this->applyPartnerFilterForClass('CaptionAssetItem');
		}
		
		if(!CaptionSearchPlugin::isAllowedPartner($this->getPartnerId()))
			throw new KalturaAPIException(KalturaErrors::FEATURE_FORBIDDEN, CaptionSearchPlugin::PLUGIN_NAME);
	}
	
    /**
     * Parse content of caption asset and index it
     *
     * @action parse
     * @param string $captionAssetId
     * @throws KalturaCaptionErrors::CAPTION_ASSET_ID_NOT_FOUND
     */
    function parseAction($captionAssetId)
    {
		//do nothing
    }
	
	/**
	 * Search caption asset items by filter, pager and free text
	 *
	 * @action search
	 * @param KalturaBaseEntryFilter $entryFilter
	 * @param KalturaCaptionAssetItemFilter $captionAssetItemFilter
	 * @param KalturaFilterPager $captionAssetItemPager
	 * @return KalturaCaptionAssetItemListResponse
	 */
	function searchAction(KalturaBaseEntryFilter $entryFilter = null, KalturaCaptionAssetItemFilter $captionAssetItemFilter = null, KalturaFilterPager $captionAssetItemPager = null)
	{
		if (!$captionAssetItemPager)
		{
			$captionAssetItemPager = new KalturaFilterPager();
		}

		if (!$captionAssetItemFilter)
		{
			$captionAssetItemFilter = new KalturaCaptionAssetItemFilter();
		}

		$captionAssetItemFilter->validatePropertyNotNull(array("contentLike", "contentMultiLikeOr", "contentMultiLikeAnd"));

		$captionAssetItemCoreFilter = new CaptionAssetItemFilter();
		$captionAssetItemFilter->toObject($captionAssetItemCoreFilter);

		$captionItemQueryToFilter = new ESearchCaptionQueryFromFilter();

		$filterOnEntryIds = false;
		if($entryFilter || kEntitlementUtils::getEntitlementEnforcement())
		{
			$entryCoreFilter = new entryFilter();
			if($entryFilter)
			{
				$entryFilter->toObject($entryCoreFilter);
			}
			$entryCoreFilter->setPartnerSearchScope($this->getPartnerId());
			$this->addEntryAdvancedSearchFilter($captionAssetItemFilter, $entryCoreFilter);

			$entryCriteria = KalturaCriteria::create(entryPeer::OM_CLASS);
			$entryCoreFilter->attachToCriteria($entryCriteria);
			$entryCriteria->applyFilters();

			$entryIds = $entryCriteria->getFetchedIds();
			if(!$entryIds || !count($entryIds))
			{
				$entryIds = array('NOT_EXIST');
			}

			$captionAssetItemCoreFilter->setEntryIdIn($entryIds);
			$filterOnEntryIds = true;
			if($entryCoreFilter->get('_eq_id'))
			{
				$captionItemQueryToFilter->setEntryIdEqual();
			}
		}

		$captionAssetItemCorePager = new kFilterPager();
		$captionAssetItemPager->toObject($captionAssetItemCorePager);
		list($captionAssetItems, $objectsCount) = $captionItemQueryToFilter->retrieveElasticQueryCaptions($captionAssetItemCoreFilter, $captionAssetItemCorePager, $filterOnEntryIds);

		$list = KalturaCaptionAssetItemArray::fromDbArray($captionAssetItems, $this->getResponseProfile());
		$response = new KalturaCaptionAssetItemListResponse();
		$response->objects = $list;
		$response->totalCount = $objectsCount;
		return $response;
	}
	
	private function addEntryAdvancedSearchFilter(KalturaCaptionAssetItemFilter $captionAssetItemFilter, entryFilter $entryCoreFilter)
	{
		//create advanced filter on entry caption
		$entryCaptionAdvancedSearch = new EntryCaptionAssetSearchFilter();
		$entryCaptionAdvancedSearch->setContentLike($captionAssetItemFilter->contentLike);
		$entryCaptionAdvancedSearch->setContentMultiLikeAnd($captionAssetItemFilter->contentMultiLikeAnd);
		$entryCaptionAdvancedSearch->setContentMultiLikeOr($captionAssetItemFilter->contentMultiLikeOr);
		$inputAdvancedSearch = $entryCoreFilter->getAdvancedSearch();
		if(!is_null($inputAdvancedSearch))
		{
			$advancedSearchOp = new AdvancedSearchFilterOperator();
			$advancedSearchOp->setType(AdvancedSearchFilterOperator::SEARCH_AND);
			$advancedSearchOp->setItems(array ($inputAdvancedSearch, $entryCaptionAdvancedSearch));
			$entryCoreFilter->setAdvancedSearch($advancedSearchOp);
		}
		else
		{
			$entryCoreFilter->setAdvancedSearch($entryCaptionAdvancedSearch);
		}
	}
	
	
	/**
	 * Search caption asset items by filter, pager and free text
	 *
	 * @action searchEntries
	 * @param KalturaBaseEntryFilter $entryFilter
	 * @param KalturaCaptionAssetItemFilter $captionAssetItemFilter
	 * @param KalturaFilterPager $captionAssetItemPager
	 * @return KalturaBaseEntryListResponse
	 */
	public function searchEntriesAction (KalturaBaseEntryFilter $entryFilter = null, KalturaCaptionAssetItemFilter $captionAssetItemFilter = null, KalturaFilterPager $captionAssetItemPager = null)
	{
		if (!$captionAssetItemPager)
		{
			$captionAssetItemPager = new KalturaFilterPager();
		}
		if (!$captionAssetItemFilter)
		{
			$captionAssetItemFilter = new KalturaCaptionAssetItemFilter();
		}

		$captionAssetItemFilter->validatePropertyNotNull(array("contentLike", "contentMultiLikeOr", "contentMultiLikeAnd"));

		$captionAssetItemCoreFilter = new CaptionAssetItemFilter();
		$captionAssetItemFilter->toObject($captionAssetItemCoreFilter);

		$entryIdChunks = array(NULL);
		$shouldSortCaptionFiltering = false;

		if($entryFilter || kEntitlementUtils::getEntitlementEnforcement())
		{
			$entryCoreFilter = new entryFilter();
			if($entryFilter)
			{
				$entryFilter->toObject($entryCoreFilter);
			}
			$entryCoreFilter->setPartnerSearchScope($this->getPartnerId());
			$this->addEntryAdvancedSearchFilter($captionAssetItemFilter, $entryCoreFilter);

			$entryCriteria = KalturaCriteria::create(entryPeer::OM_CLASS);
			$entryCoreFilter->attachToCriteria($entryCriteria);
			$entryCriteria->setLimit(self::MAX_NUMBER_OF_ENTRIES);

			$entryCriteria->applyFilters();

			$entryIds = $entryCriteria->getFetchedIds();
			if(!$entryIds || !count($entryIds))
			{
				$entryIds = array('NOT_EXIST');
			}

			$entryIdChunks = array_chunk($entryIds , self::SIZE_OF_ENTRIES_CHUNK);
			$shouldSortCaptionFiltering = $entryFilter->orderBy ? true : false;
		}

		$entries = array();
		$counter = 0;

		$captionAssetItemCorePager = new kPager();
		$captionAssetItemPager->toObject($captionAssetItemCorePager);

		$captionItemQueryToFilter = new ESearchCaptionQueryFromFilter();

		foreach ($entryIdChunks as $chunk)
		{
			$currCoreFilter = clone ($captionAssetItemCoreFilter);
			$currCorePager = clone ($captionAssetItemCorePager);
			if ($chunk)
			{
				$currCoreFilter->setEntryIdIn($chunk);
				$currCorePager->setPageSize(sizeof($chunk));
				$currCorePager->setPageIndex(1);
			}

			list ($currEntries, $count) = $captionItemQueryToFilter->retrieveElasticQueryEntryIds($currCoreFilter, $currCorePager);
			//sorting this chunk according to results of first sphinx query
			if ($shouldSortCaptionFiltering)
			{
				$currEntries = array_intersect($entryIds, $currEntries);
			}
			$entries = array_merge ($entries, $currEntries);
			$counter += $count;
		}

		$inputPageSize = $captionAssetItemPager->pageSize;
		$inputPageIndex = $captionAssetItemPager->pageIndex;

		//page index & size validation - no negative values & size not too big
		$pageSize = max(min($inputPageSize, baseObjectFilter::getMaxInValues()), 0);
		$pageIndex = max($captionAssetItemPager::MIN_PAGE_INDEX, $inputPageIndex) - 1;

		$firstIndex = $pageSize * $pageIndex ;
		$entries = array_slice($entries , $firstIndex , $pageSize);

		$dbList = entryPeer::retrieveByPKs($entries);

		if ($shouldSortCaptionFiltering)
		{
			//results ids mapping
			$entriesMapping = array();
			foreach($dbList as $item)
			{
				$entriesMapping[$item->getId()] = $item;
			}

			$dbList = array();
			foreach($entries as $entryId)
			{
				if (isset($entriesMapping[$entryId]))
				{
					$dbList[] = $entriesMapping[$entryId];
				}
			}
		}
		$list = KalturaBaseEntryArray::fromDbArray($dbList, $this->getResponseProfile());
		$response = new KalturaBaseEntryListResponse();
		$response->objects = $list;
		$response->totalCount = $counter;

		return $response;
	}


	/**
	 * List caption asset items by filter and pager
	 *
	 * @action list
	 * @param string $captionAssetId
	 * @param KalturaCaptionAssetItemFilter $captionAssetItemFilter
	 * @param KalturaFilterPager $captionAssetItemPager
	 * @return KalturaCaptionAssetItemListResponse
	 */
	function listAction($captionAssetId, KalturaCaptionAssetItemFilter $captionAssetItemFilter = null, KalturaFilterPager $captionAssetItemPager = null)
	{

		if (!$captionAssetItemPager)
			$captionAssetItemPager = new KalturaFilterPager();

		if (!$captionAssetItemFilter)
			$captionAssetItemFilter = new KalturaCaptionAssetItemFilter();

		$captionAssetItemCoreFilter = new CaptionAssetItemFilter();
		$captionAssetItemFilter->toObject($captionAssetItemCoreFilter);
	        $captionAssetItemFilter->idEqual = $captionAssetId;

        	$captionAsset = assetPeer::retrieveById($captionAssetId);
	        $entryId = $captionAsset->getEntryId();
	        $entryFilter = new KalturaBaseEntryFilter();
	        $entryFilter->idEqual = $entryId;

        	$response = CaptionAssetItemService::searchAction( $entryFilter , $captionAssetItemFilter , $captionAssetItemPager );
	        return $response;
	}

}
