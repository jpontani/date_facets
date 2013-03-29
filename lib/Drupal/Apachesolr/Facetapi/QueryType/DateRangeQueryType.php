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
    $ranges = (isset($settings->settings['ranges']) ? $settings->settings['ranges'] : array());

    if (empty($ranges)) {
      if (!empty($active)) {
        // Check the first value since only one is allowed.
        switch (key($active)) {
          case 'past_hour':
            list($start, $end) = $this->generateRange("-1|HOUR");
            break;

          case 'past_24_hours':
            list($start, $end) = $this->generateRange("-1|DAY");
            break;

          case 'past_week':
            list($start, $end) = $this->generateRange("-7|DAY", TRUE);
            break;

          case 'past_month':
            list($start, $end) = $this->generateRange("-1|MONTH");
            break;

          case 'past_year':
            list($start, $end) = $this->generateRange("-1|YEAR");
            break;

          default:
            return;
        }
      }
    }
    else {
      $range = $ranges[key($active)];
    }
    $query->addParam('fq', $this->facet['field'] . ":[$start TO $end]");
  }

  /**
   * Return the divisor for the given unit.
   */
  protected function unitDivisor($unit, $plural = FALSE) {
    $s = '';
    if ($plural) {
      $s = 'S';
    }
    switch ($unit) {
      case "HOUR":
        return "HOUR" . $s;
      case "DAY":
        return "DAY" . $s;
      case "MONTH":
      case "YEAR":
        return "MONTH" . $s;
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
      $plural = ((int) abs($lower_amount) > 1);
      $lower = "NOW/" . $this->unitDivisor($lower_unit, $plural) . $lower_amount;
    }
    if (empty($upper)) {
      $upper = "NOW/DAY+1DAY";
    }
    else {
      list($upper_amount, $upper_unit) = explode('|', $upper);
      $plural = ((int) abs($upper_amount) > 1);
      $upper = "NOW/" . $this->unitDivisor($upper_unit, $plural) . $upper_amount;
    }
    return ($lower, $upper);
  }

  /**
   * Implements FacetapiQueryTypeInterface::build().
   *
   * Unlike normal facets, we provide a static list of options.
   */
  public function build() {
    return date_facets_get_ranges();
  }
}
