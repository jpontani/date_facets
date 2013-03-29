<?php

/**
 * @file
 * Contains Drupal_Search_Facetapi_QueryType_DateRangeQueryType.
 */

/**
 * Date range query type plugin for the core Search adapter.
 */
class Drupal_Search_Facetapi_QueryType_DateRangeQueryType extends FacetapiQueryType implements FacetapiQueryTypeInterface {

  /**
   * Implements FacetapiQueryTypeInterface::getType().
   */
  static public function getType() {
    return 'date_range';
  }

  /**
   * Implements FacetapiQueryTypeInterface::execute().
   */
  public function execute($query) {
    $active = $this->getActiveItems();

    if (!empty($active)) {

      $facet_query = $this->adapter->getFacetQueryExtender();
      $query_info = $this->adapter->getQueryInfo($this->facet);
      $tables_joined = array();
      
      $settings = $this->adapter->getFacetSettings($this->facet, facetapi_realm_load('block'));
      $ranges = (isset($settings->settings['ranges']) ? $settings->settings['ranges'] : array());

      if (empty($ranges)) {
        // Check the first value since only one is allowed.
        // @todo Make the start time less dynamic to make use of the query cache.
        // Leverage the same technique as Solr where the times are reounded.
        switch (key($active)) {
          case 'past_hour':
            $start = REQUEST_TIME - (60 * 60);
            break;

          case 'past_24_hours':
            $start = REQUEST_TIME - (60 * 60 * 24);
            break;

          case 'past_week':
            $start = REQUEST_TIME - (60 * 60 * 24 * 7);
            break;

          case 'past_month':
            $start = REQUEST_TIME - (60 * 60 * 24 * 30);
            break;

          case 'past_year':
            $start = REQUEST_TIME - (60 * 60 * 24 * 365);
            break;

          default:
            return;
        }
        // @todo Make the end time less dynamic to make use of the query cache.
        // Leverage the same technique as Solr where the times are reounded.
        $end = REQUEST_TIME;
      }
      else {
        $range = $ranges[key($active)];
        foreach (array('start', 'end') as $item) {
          $$item = REQUEST_TIME;
          $unit = $range['date_range_' . $item . '_unit'];
          $amount = (int) $range['date_range_' . $item . '_amount'];
          switch ($unit) {
            case 'HOUR':
              $unit = (int) DATE_RANGE_UNIT_HOUR;
              break;
            case 'DAY':
              $unit = (int) DATE_RANGE_UNIT_DAY;
              break;
            case 'MONTH':
              $unit = (int) DATE_RANGE_UNIT_MONTH;
              break;
            case 'YEAR':
              $unit = (int) DATE_RANGE_UNIT_YEAR;
              break;
          }
          switch ($range['date_range_' . $item . '_op']) {
            case '-':
              $$item -= ($amount * $unit);
              break;
            case '+':
              $$item += ($amount * $unit);
              break;
          }
        }
      }

      // Iterate over the facet's fields and adds SQL clauses.
      foreach ($query_info['fields'] as $field_info) {

        // Adds join to the facet query.
        $facet_query->addFacetJoin($query_info, $field_info['table_alias']);

        // Adds adds join to search query, makes sure it is only added once.
        if (isset($query_info['joins'][$field_info['table_alias']])) {
          if (!isset($tables_joined[$field_info['table_alias']])) {
            $tables_joined[$field_info['table_alias']] = TRUE;
            $join_info = $query_info['joins'][$field_info['table_alias']];
            $query->join($join_info['table'], $join_info['alias'], $join_info['condition']);
          }
        }

        // Adds field conditions to the facet and search query.
        $field = $field_info['table_alias'] . '.' . $this->facet['field'];
        $query->condition($field, $start, '>=');
        $query->condition($field, $end, '<');
        $facet_query->condition($field, $start, '>=');
        $facet_query->condition($field, $end, '<');
      }
    }
  }

  /**
   * Implements FacetapiQueryTypeInterface::build().
   *
   * Unlike normal facets, we provide a static list of options.
   */
  public function build() {
    $settings = $this->adapter->getFacetSettings($this->facet, facetapi_realm_load('block'));
    $ranges = (isset($settings->settings['ranges']) ? $settings->settings['ranges'] : array());
    return date_facets_get_ranges($ranges);
  }
}
