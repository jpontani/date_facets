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
  
  protected function generateRange($range = array()) {
    if (empty($range)) {
      $start = REQUEST_TIME;
      $end = REQUEST_TIME + DATE_RANGE_UNIT_DAY;
    }
    else {
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
    return array($start, $end);
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
      $ranges = (isset($settings->settings['ranges']) ? $settings->settings['ranges'] : date_facets_default_ranges());
      $range = $ranges[key($active)];
      list($start, $end) = $this->generateRange($range);

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
   */
  public function build() {
    $realm = facetapi_realm_load('block');
    $settings = $this->adapter->getFacetSettings($this->facet, $realm);
    $ranges = (isset($settings->settings['ranges']) ? $settings->settings['ranges'] : date_facets_default_ranges());
    $build = date_facets_get_ranges($ranges);
    if ($this->adapter->searchExecuted()) {
      $facet_global_settings = $this->adapter->getFacet($this->facet)->getSettings();
      // Iterate over each date range to get a count of results for that item.
      foreach ($ranges as $range) {
        list($start, $end) = $this->generateRange($range);
        $facet_query = clone $this->adapter->getFacetQueryExtender();
        $query_info = $this->adapter->getQueryInfo($this->facet);
        $facet_query->addFacetField($query_info);
        foreach ($query_info['fields'] as $field_info) {
          $facet_query->addFacetJoin($query_info, $field_info['table_alias']);
          $field = $field_info['table_alias'] . '.' . $this->facet['field'];
          $facet_query->condition($field, $start, '>=');
          $facet_query->condition($field, $end, '<');
        }
        // Executes query, iterates over results.
        $result = $facet_query->execute()->fetchAll();
        // If the result is 0, and the mincount is set to more than 0, remove
        // the facet option.
        if (empty($result)
          && $facet_global_settings->settings['facet_mincount'] > 0) {
          unset($build[$range['machine_name']]);
        }
        // Add the facet option counts to the build array.
        foreach ($result as $record) {
          $build[$range['machine_name']]['#count'] = $record->count;
        }
      }
    }
    return $build;
  }
}
