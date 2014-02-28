<?php
namespace TYPO3\Solrgeo\Controller;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2014 Phuong Doan <phuong.doan@dkd.de>, dkd Internet Service GmbH
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 *
 * @package solrgeo
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 *
 */
class FrontendGeoSearchController extends SolrController {

	/**
	 * @var \TYPO3\Solrgeo\Configuration\GeoSearchConfiguration
	 */
	protected $geoSearchObject = null;

	/**
	 * @var string
	 */
	protected $geolocation = '-1';

	/**
	 * @var boolean
	 */
	protected $searchHasResults = false;

	public function initializeGeoSearchConfiguration() {
		$this->createGeoSearchObject();
	}

	/**
	 * @param \TYPO3\Solrgeo\Configuration\GeoSearchConfiguration $geoSearchObject
	 */
	public function setGeoSearchObject($geoSearchObject) {
		$this->geoSearchObject = $geoSearchObject;
	}

	/**
	 * @return \TYPO3\Solrgeo\Configuration\GeoSearchConfiguration
	 */
	public function getGeoSearchObject() {
		return $this->geoSearchObject;
	}

	/**
	 * Gets the geolocation
	 *
	 * @return string the latitude and longitude as string, comma separated
	 */
	public function getGeolocation() {
		return $this->geolocation;
	}

	/**
	 * Sets the geolocation from given search keyword
	 *
	 * @return string the latitude and longitude as string, comma separated
	 */
	public function setGeolocation($keyword) {
		$geocoder = $this->helper->getGeoCoder();
		$this->geolocation = $geocoder->getGeolocationFromKeyword($keyword);
	}

	/**
	 * @param boolean $hasResults
	 */
	public function setSearchHasResults($hasResults) {
		$this->searchHasResults = $hasResults;
	}

	/**
	 * @return bool
	 */
	public function getSearchHasResults() {
		return $this->searchHasResults;
	}

	/**
	 * Sets the configured filter type, sort direction and distance for query / facets
	 *
	 * @return void
	 */
	protected function createGeoSearchObject() {
		$this->geoSearchObject = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\Solrgeo\\Configuration\\GeoSearchConfiguration');
		$configuration = $this->helper->getConfiguration('tx_solrgeo');
		$searchConf = $configuration['search.'];

		// query configuration
		if(!empty($searchConf['query.']['filter.']['d'])){
			$this->geoSearchObject->setDistance($searchConf['query.']['filter.']['d']);
		}

		if(!empty($searchConf['query.']['filter.']['type'])){
			$this->geoSearchObject->setFilterType($searchConf['query.']['filter.']['type']);
		}

		if(!empty($searchConf['query.']['sort.']['direction'])) {
			$this->geoSearchObject->setSortDirection(strtolower($searchConf['query.']['sort.']['direction']));
		}

		if(!empty($searchConf['faceting.']['distance']) && $searchConf['faceting.']['distance'] == '1') {
			$this->geoSearchObject->setDistanceFilterEnable(true);
		}

		// fact.city configuration
		if(!empty($searchConf['faceting.']['city']) && $searchConf['faceting.']['city'] == '1') {
			$this->geoSearchObject->setCityFacetEnable(true);
		}
		if(!empty($searchConf['faceting.']['city.']['sort.']['direction'])) {
			$this->geoSearchObject->setFacetCitySortDirection(strtolower($searchConf['faceting.']['city.']['sort.']['direction']));
		}

		if(!empty($searchConf['faceting.']['city.']['sort.']['type'])) {
			$this->geoSearchObject->setFacetCitySortType(strtolower($searchConf['faceting.']['city.']['sort.']['type']));
		}

		// fact.country configuration
		if(!empty($searchConf['faceting.']['country']) && $searchConf['faceting.']['country'] == '1') {
			$this->geoSearchObject->setCountryFacetEnable(true);
		}
		if(!empty($searchConf['faceting.']['country.']['sort.']['direction'])) {
			$this->geoSearchObject->setFacetCountrySortDirection(strtolower($searchConf['faceting.']['country.']['sort.']['direction']));
		}

		if(!empty($searchConf['faceting.']['country.']['sort.']['type'])) {
			$this->geoSearchObject->setFacetCountrySortType(strtolower($searchConf['faceting.']['country.']['sort.']['type']));
		}

		if(!empty($searchConf['faceting.']['distance.']['ranges.'])) {
			$this->geoSearchObject->setConfiguredRanges($searchConf['faceting.']['distance.']['ranges.']);
		}

		// Zoom for google maps
		if(!empty($searchConf['maps.']['zoom.']['city'])) {
			$this->geoSearchObject->setCityZoom($searchConf['maps.']['zoom.']['city']);
		}

		if(!empty($searchConf['maps.']['zoom.']['country'])) {
			$this->geoSearchObject->setCountryZoom($searchConf['maps.']['zoom.']['country']);
		}
	}


	/**
	 * Search by the given keyword
	 *
	 * @param string The search keyword
	 * @param string Distance
	 * @param string Range interval
	 * @return array Array contains the results
	 */
	public function searchByKeyword($keyword, $distance = '', $range = '') {
		$resultDocuments = array();
		if($keyword == '') {
			$resultDocuments[] = $this->getErrorResult('error_emptyQuery');
		}
		else if ($this->solrAvailable) {
			$geolocation = $this->getGeolocation();
			if($geolocation == "-1") {
				$resultDocuments[] = $this->getErrorResult('searchFailed');
			}
			else {
				$resultDocuments = $this->processGeosearch($keyword, $geolocation, $distance, $range);
			}
		}
		else {
			$resultDocuments[] = $this->getErrorResult('searchUnavailable');
		}

		return $resultDocuments;
	}


	/**
	 * Process the geo search
	 *
	 * @param string The search keyword
	 * @param string Data for geo location
	 * @param string Distance
	 * @param string Range interval
	 * @return array Array contains the results
	 */
	private function processGeosearch($keyword, $geolocation, $distance = '', $range = '') {
		$resultDocuments = array();
		$query = $this->getDefaultQuery();
		$limit = 10;
		if (!empty($this->conf['search.']['results.']['resultsPerPage'])) {
			$limit = $this->conf['search.']['results.']['resultsPerPage'];
		}
		$query->setResultsPerPage($limit);
		$query->setHighlighting();

		$query = $this->modifyQuery($query, $keyword, $geolocation, $distance, $range);
		$this->query = $query;

		$this->search->search($this->query, 0, NULL);
		$solrResults = $this->search->getResultDocuments();

		if(!empty($solrResults)) {
			$this->setSearchHasResults(true);
			foreach ($solrResults as $result) {
				$fields   = $result->getFieldNames();
				$document = array();
				if( !$this->geoSearchObject->isSearchByCountry() ||
					($this->geoSearchObject->isSearchByCountry() && in_array(self::COUNTRY_FIELD, $fields))) {
					foreach ($fields as $field) {
						$fieldValue       = $result->getField($field);
						$document[$field] = $fieldValue["value"];
					}
					$resultDocuments[] = $document;
				}
			}
		}
		else {
			$this->setSearchHasResults(false);
			$additionInformation = '"'.$keyword.'"';
			if($range != '') {
				$additionInformation .= $GLOBALS['TSFE']->sL(
						'LLL:EXT:solrgeo/Resources/Private/Language/locallang_search.xml:tx_solrgeo.within').$range.' km';
			}
			$additionInformation .= '.';
			$resultDocuments[] = $this->getErrorResult('no_results_nothing_found',$additionInformation);
		}

		return $resultDocuments;
	}


	/**
	 * Modifies the Query depends on keyword, range or distance
	 *
	 * @param \Tx_solr_Query $query
	 * @param string $keyword
	 * @param string $geolocation
	 * @param string $distance
	 * @param string $range
	 * @return \Tx_solr_Query
	 */
	public function modifyQuery(\Tx_solr_Query $query, $keyword, $geolocation, $distance, $range) {
		if(\TYPO3\Solrgeo\Utility\String::startsWith($keyword, 'country,')) {
			$this->geoSearchObject->setSearchByCountry(true);
			$keyword = str_replace('country,','',$keyword);
			$query->setQueryField(self::COUNTRY_FIELD, 1.0);
			$query->setKeywords($keyword);
		}
		else if($range != '') {
			$tmp = explode('-',$range);
			$lowerLimit = $tmp[0];
			if($lowerLimit != '0' && !\TYPO3\Solrgeo\Utility\String::contains($lowerLimit,'.')){
				$lowerLimit = bcadd($lowerLimit, '0.001', 3);
			}
			$upperLimit = $tmp[1];
			$query->addFilter('{!frange l='.$lowerLimit.' u='.$upperLimit.'}geodist()');
			$query->addQueryParameter('sfield', self::GEO_LOCATION_FIELD);
			$query->addQueryParameter('pt', $geolocation);
		}
		else {
			if($distance == '') {
				$distance = $this->geoSearchObject->getDistance();
			}

			$query->addFilter('{!'.$this->geoSearchObject->getFilterType().' pt='.$geolocation.' sfield='.self::GEO_LOCATION_FIELD.' d='.$distance.'}');
			$query->addQueryParameter('sort', 'geodist('.self::GEO_LOCATION_FIELD.','.$geolocation.') '.$this->geoSearchObject->getDirection());
		}
		return $query;
	}


	/**
	 * Sets the error.
	 *
	 * @param string The error key defined in /Resources/Private/Language/locallang_search.xml
	 * @param string Additional error information
	 * @return array Array contains the error
	 */
	private function getErrorResult($error_key, $additionalErrorInfo = "") {
		$document = array();
		$document['title'] = $GLOBALS['TSFE']->sL(
				'LLL:EXT:solrgeo/Resources/Private/Language/locallang_search.xml:tx_solrgeo.'.$error_key).$additionalErrorInfo;
		$document['content'] = "";
		return $document;
	}

	/**
	 * @param string The search keyword
	 * @return array
	 */
	public function getFacetGrouping($keyword, $facetType, $language) {
		$resultDocuments = array();
		if(($facetType == self::CITY_FIELD && $this->geoSearchObject->isCityFacetEnable()) ||
		   ($facetType == self::COUNTRY_FIELD && $this->geoSearchObject->isCountryFacetEnable())
			&& $keyword != '') {
			$geolocation = $this->getGeolocation();
			if($geolocation != '-1') {
				$resultDocuments = $this->processFacet($geolocation, $facetType, $language);
			}

		}
		return $resultDocuments;
	}

	/**
	 * @param string Latitude and longitude of the search keyword
	 */
	private function processFacet($geolocation, $facetType, $language) {
		$resultDocuments = array();
		if(($facetType == self::CITY_FIELD && $this->geoSearchObject->isCityFacetEnable()) ||
			($facetType == self::COUNTRY_FIELD && $this->geoSearchObject->isCountryFacetEnable())) {
			$query = $this->getDefaultQuery();
			$query->setFieldList(array($facetType));
			$query->setGrouping(true);
			$query->addGroupField($facetType);

			// Facet.City
			if($facetType == self::CITY_FIELD) {
				if($this->geoSearchObject->getFacetCitySortType() == 'distance') {
					// Sort by distance
					$query->addQueryParameter('sort',
						'geodist('.self::GEO_LOCATION_FIELD.','.$geolocation.') '.$this->geoSearchObject->getFacetCitySortDirection());
				}
				else {
					// Sort by city
					$query->addQueryParameter('sort',$facetType.' '.$this->geoSearchObject->getFacetCitySortDirection());
				}
			}

			// Facet.Country
			else {
				if($this->geoSearchObject->getFacetCountrySortType() == 'distance') {
					// Sort by distance
					$query->addQueryParameter('sort',
						'geodist('.self::GEO_LOCATION_FIELD.','.$geolocation.') '.$this->geoSearchObject->getFacetCountrySortDirection());
				}
				else {
					// Sort by country
					$query->addQueryParameter('sort',$facetType.' '.$this->geoSearchObject->getFacetCountrySortDirection());
				}
			}

			$this->query = $query;
			$this->search->search($this->query, 0, NULL);
			$response = $this->search->getResponse();
			$resultDocuments = $this->getGroupedResults($response, $facetType, $language);
		}
		return $resultDocuments;
	}

	/**
	 * Add the the grouped value
	 *
	 * @param \Apache_Solr_Response
	 */
	private function getGroupedResults(\Apache_Solr_Response $response, $facetType, $language) {
		$resultDocuments = array();
		$groupKey = ($facetType == self::CITY_FIELD) ? 'city' : 'country';
		foreach ($response->grouped as $groupCollectionKey => $groupCollection) {
			if($groupCollectionKey == $facetType && isset($groupCollection->groups)) {
				foreach($groupCollection->groups as $group) {
					$doclist = $group->doclist;
					$docs = $doclist->docs;
					if(!empty($docs)){
						$groupedValue = $docs[0]->$facetType;
						if($groupedValue != '') {
							$result = array();
							$result['numFound'] = $doclist->numFound;
							$result[$groupKey] = $groupedValue;
							$result['url'] = $this->helper->getLinkUrl(true).
									(($facetType == self::CITY_FIELD) ? $groupedValue : $groupKey.",".$groupedValue) .
									'&L='.$language;
							$resultDocuments[] = $result;
						}
					}
				}
			}
		}

		// Because numFound is not a Solr document field we have to sort it manuelly
		if(($facetType == self::CITY_FIELD && $this->geoSearchObject->getFacetCitySortType() == 'numfound') ||
		   ($facetType == self::COUNTRY_FIELD && $this->geoSearchObject->getFacetCountrySortType() == 'numfound')) {
			$numfound = array();
			foreach ($resultDocuments as $key => $row) {
				$numfound[$key] = $row['numFound'];
			}

			if($facetType == self::CITY_FIELD) {
				array_multisort($numfound,(($this->geoSearchObject->getFacetCitySortDirection() == 'asc') ? SORT_ASC : SORT_DESC), $resultDocuments);
			}
			else {
				array_multisort($numfound,(($this->geoSearchObject->getFacetCountrySortDirection() == 'asc') ? SORT_ASC : SORT_DESC), $resultDocuments);
			}
		}

		return $resultDocuments;
	}

	/**
	 * Adds address and geolocation information for drawing google maps in frontend
	 *
	 * @param array $resultDocuments
	 */
	public function prepareSolrDocumentsForGoogleMaps(array $resultDocuments) {
		$googleMapsLocations = array();
		if(!empty($resultDocuments)) {
			foreach($resultDocuments as $resultDocument) {
				$tmp = array();
				if($resultDocument['address_textS'] != '') {
					$tmp[] = $resultDocument['address_textS'].", ".$resultDocument['city_textS'];
				}
				else {
					$tmp[] = $resultDocument['city_textS'];
				}
				$latLong = explode(',', $resultDocument['geo_location']);
				$tmp[] = $latLong[0];
				$tmp[] = $latLong[1];
				if(!in_array($tmp, $googleMapsLocations)) {
					$googleMapsLocations[] = $tmp;
				}
			}
		}
		return $googleMapsLocations;
	}

	/**
	 * Gets the geolocation for the keyword and saves it into an array
	 *
	 * @param string $keyword
	 * @return array contains latitude and longitude
	 */
	public function getGeolocationAsArray($keyword) {
		$geolocationArray = array();
		if($keyword != '') {
			$this->setGeolocation($keyword);
			$geolocation = $this->getGeolocation();
			if($geolocation != '-1') {
				$geolocationArray = explode(',', $geolocation);
			}
		}
		return $geolocationArray;
	}

}
?>