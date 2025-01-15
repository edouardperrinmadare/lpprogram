<?php

use Drupal\views\ViewExecutable;

/**
 * Implements hook_views_pre_view().
 */
function lp_programs_views_pre_view(ViewExecutable $view, $display_id, array &$args) {
  if ($view->id() == 'programmes_madare' && ($display_id == 'page_1' || $display_id == 'attachment_1')) {
    _lp_programs_views_map($view);
}
}

/**
 * Internal function to rebuild map program.
 *
 * @param \Drupal\views\ViewExecutable $view
 *
 * @return void
 */
function _lp_programs_views_map(ViewExecutable $view) {
  $view->element['#attached']['library'][] = 'lp_programs/lp_programs.search';


  // Fetch current exposed filter values.
  $exposedFilters = $view->getExposedInput();

  // Determine position based on filters or taxonomy.
  $position = _lp_programs_get_position($view); 
  $place = _lp_programs_get_place($view);

  $distance = 200 ;

if (!empty($position)) {
      // If position exists, it's prioritized and replaces the place value.
      // Assuming 'lat' and 'lon' can be directly used or you adjust based on your setup.
      if (!empty($view->args)) {
        $locality = _lp_programs_get_locality($view, ['alias' => $view->args[0]]);
      }
      $distance = 200;
      $exposedFilters['field_address_administrative_area'] = $locality->getName();
  } 

  if (!empty($place)) {
    if (empty($view->args)) {
      $locality = _lp_programs_get_locality($view, ['alias' => strtolower($place)]);
    }

    if ($locality ) {
      $distance = 300;
      $exposedFilters['field_address_administrative_area'] = $locality->getName();
    } else {
      $distance = 100;
      unset($exposedFilters['field_address_administrative_area']);
    }

  } 

  // Handle distance adjustments.
  if ($distance !== null) {
      if ($distance > 300) {
          // Limit max distance to 300 km if over.
          $distance = 300;
      } elseif (strtolower(trim($exposedFilters['field_geol']['source_configuration']['origin_address'])) === "france") {
          // Special case for France.
          $distance = 570; // Maximum distance from the border.
      }

      // Apply adjusted distance to the view's exposed filters.
      $exposedFilters['field_geol']['value'] = $distance;
      $view->setExposedInput($exposedFilters);

  }

  // Further logic and adjustments can be applied as needed.
}

/**
 * @param \Drupal\views\ViewExecutable $view
 *
 * @return false|string
 */
function _lp_programs_get_position(ViewExecutable $view) {
  // Get exposed filter value.
  $exposedFilter = $view->getExposedInput();

  // Get location position from exposed filter.

  // Get search alias and position if not available in query.
  if (!empty($view->args)) {

    $locality = _lp_programs_get_locality($view, ['alias' => $view->args[0]]);

    if ($locality) {

      $field_localisation = $locality->field_localisation;
      return $field_localisation->lat . ',' . $field_localisation->lon;
    }
  }
  return FALSE;
}



/**
 * @param \Drupal\views\ViewExecutable $view
 *
 * @return false|mixed
 */
function _lp_programs_get_place(ViewExecutable $view) {
  // Get location name (City || Region) from query.
  $query = \Drupal::request()->query;
  if (!empty($query->get('place'))) {
    return $query->get('place');
  }

  // Get search alias and place if not available in query.
  if (!empty($view->args)) {

    $locality = _lp_programs_get_locality($view, ['alias' => $view->args[0]]);

    if ($locality) {
      $place = $locality->getName();
      // Add place to js settings to add value to the field.
      $view->element['#attached']['drupalSettings']['lp_programs_search']['place'] = $place;

      // Add term render to template var.
      $view_builder = \Drupal::entityTypeManager()->getViewBuilder('taxonomy_term');
      $build = $view_builder->view($locality);
      $view->localisation_text = $build;

      return $place;
    }
  }

  return FALSE;
}

/**
 * @param \Drupal\views\ViewExecutable $view
 *
 * @return false|mixed
 * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
 * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
 */
function _lp_programs_get_locality(ViewExecutable $view, array $options) {
  if (!empty($options['alias'])) {
    $localisationAlias = $options['alias'];
  } else {
    $localisationAlias = $view->args[0];
  }
  $view->element['#attached']['drupalSettings']['lp_programs_search']['place'] = $localisationAlias;
  $term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties([
    'vid' => 'locality',
    'status' => 1,
    'field_search_alias' => $localisationAlias,
    'parent' => 0, 
  ]);
  if (count($term) == 1) {
    /** @var \Drupal\taxonomy\Entity\Term $term */
    return reset($term);
  }

  return FALSE;
}
