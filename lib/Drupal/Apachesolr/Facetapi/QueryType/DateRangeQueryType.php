<?php

/**
 * @file
 * Contains Drupal_Apachesolr_Facetapi_QueryType_DateRangeQueryType.
 */

/**
 * Date range query type plugin for the Apache Solr Search Integration adapter.
 */
class Drupal_Apachesolr_Facetapi_QueryType_DateRangeQueryType extends FacetapiQueryType implements FacetapiQueryTypeInterface {

  /**
   * Implements FacetapiQueryTypeInterface::getType().
   */
  static public function getType() {
    return 'date_range';
  }

  /**
   * Implements FacetapiQueryTypeInterface::execute().
   *
   * @see http://searchhub.org/dev/2012/02/23/date-math-now-and-filter-queries/
   */
  public function execute($query) {
    $active = $this->getActiveItems();
    $field = $this->facet['field'];
    
    $settings = $this->adapter->getFacetSettings($this->facet, facetapi_realm_load('block'));
    $ranges = (isset($settings->settings['ranges']) ? $settings->settings['ranges'] : date_facets_default_ranges());
    foreach (array_merge($ranges, date_facets_default_ranges()) as $range) {
      $start_info = $range['date_range_start_op'] . $range['date_range_start_amount'] . '|' . $range['date_range_start_unit'];
      if ($range['date_range_start_op'] == 'NOW') {
        $start_info = NULL;
      }
      $end_info = $range['date_range_end_op'] . $range['date_range_end_amount'] . '|' . $range['date_range_end_unit'];
      if ($range['date_range_end_op'] == 'NOW') {
        $end_info = NULL;
      }
      list($start, $end) = $this->generateRange($start_info, $end_info);
      $query->addParam('facet.query', $this->facet['field'] . ":[$start TO $end]");
    }

    if (!empty($active)) {
      $range = $ranges[key($active)];
      $start_info = $range['date_range_start_op'] . $range['date_range_start_amount'] . '|' . $range['date_range_start_unit'];
      if ($range['date_range_start_op'] == 'NOW') {
        $start_info = NULL;
      }
      $end_info = $range['date_range_end_op'] . $range['date_range_end_amount'] . '|' . $range['date_range_end_unit'];
      if ($range['date_range_end_op'] == 'NOW') {
        $end_info = NULL;
      }
      list($start, $end) = $this->generateRange($start_info, $end_info);
      $query->addParam('fq', $this->facet['field'] . ":[$start TO $end]");
    }
  }

  /**
   * Return the divisor for the given unit.
   */
  protected function unitDivisor($unit) {
    switch ($unit) {
      case "HOUR":
        return "HOUR";
      case "DAY":
        return "DAY";
      case "MONTH":
      case "YEAR":
        return "MONTH";
    }
  }

  /**
   * Generate a Solr version of a lower and upper date range.
   */
  protected function generateRange($lower = NULL, $upper = NULL) {
    if (empty($lower)) {
      $lower = "NOW/DAY";
    }
    else {
      list($lower_amount, $lower_unit) = explode('|', $lower);
      $s = (((int) abs($lower_amount) > 1) ? 'S' : '');
      $lower = "NOW/" . $this->unitDivisor($lower_unit) . $lower_amount . $lower_unit . $s;
    }
    if (empty($upper)) {
      $upper = "NOW/DAY+1DAY";
    }
    else {
      list($upper_amount, $upper_unit) = explode('|', $upper);
      $s = (((int) abs($upper_amount) > 1) ? 'S' : '');
      $upper = "NOW/" . $this->unitDivisor($upper_unit) . $upper_amount . $upper_unit . $s;
    }
    return array($lower, $upper);
  }

  /**
   * Implements FacetapiQueryTypeInterface::build().
   *
   * Unlike normal facets, we provide a static list of options.
   */
  public function build() {
    $settings = $this->adapter->getFacetSettings($this->facet, facetapi_realm_load('block'));
    $ranges = (isset($settings->settings['ranges']) && !empty($settings->settings['ranges']) ? $settings->settings['ranges'] : date_facets_default_ranges());
    $build = date_facets_get_ranges($ranges);
    if ($response = apachesolr_static_response_cache($this->adapter->getSearcher())) {
      $facet_global_settings = $this->adapter->getFacet($this->facet)->getSettings();
      $values = (array) $response->facet_counts->facet_queries;
      // Add result counts from the facet queries added in execute().
      foreach ($ranges as $range) {
        $start_info = $range['date_range_start_op'] . $range['date_range_start_amount'] . '|' . $range['date_range_start_unit'];
        if ($range['date_range_start_op'] == 'NOW') {
          $start_info = NULL;
        }
        $end_info = $range['date_range_end_op'] . $range['date_range_end_amount'] . '|' . $range['date_range_end_unit'];
        if ($range['date_range_end_op'] == 'NOW') {
          $end_info = NULL;
        }
        list($start, $end) = $this->generateRange($start_info, $end_info);
        $build[$range['machine_name']]['#count'] = $values[($this->facet['field'] . ":[$start TO $end]")];
      }
      // Unset options with fewer results than the minimum count setting.
      foreach ($build as $name => $item) {
        if ($item['#count'] < $facet_global_settings->settings['facet_mincount']) {
          unset($build[$name]);
        }
      }
    }
    return $build;
  }
}
