<?php

namespace Drupal\lp_programs\Services;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\google_maps_services\Api\EndpointManagerInterface;
use Drupal\lp_programs\Entity\NearBySearchProgramInterface;
use Drupal\node\NodeInterface;

/**
 *
 */
class ProgramNearBySearchService {


  /**
   * @var \Drupal\google_maps_services\Api\EndpointManagerInterface
   */
  protected $googleEndpoint;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  use LoggerChannelTrait;

  /**
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * @param \Drupal\google_maps_services\Api\EndpointManagerInterface $google_endpoint
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EndpointManagerInterface $google_endpoint, ConfigFactoryInterface $config_factory) {
    $this->googleEndpoint = $google_endpoint;
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
  }

  /**
   * Test if we can update the poi of program (test the date).
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node program to test.
   *
   * @return bool
   */
  public function canUpdateProgram(NodeInterface $node) {
    if ($node->bundle() !== 'program' || !$node->hasField('field_date') || !$node->hasField('field_program_layout')) {
      return FALSE;
    }
    $field_program_status = $node->get('field_program_layout')->getValue();
    // Do not update program already delivred.
    if (!empty($field_program_status[0]['value']) && $field_program_status[0]['value'] === 'sold') {
      return FALSE;
    }
    if (empty($node->get('field_date')->getValue())) {
      return TRUE;
    }

    return TRUE;

    // Disable date condition.
//    $field_date = $node->get('field_date')->getValue();
//    $date_field = new DrupalDateTime($field_date[0]['value']);
//    $date_max = $this->getDateMaxPoi();
//    return $date_max->getTimestamp() >= $date_field->getTimestamp();
  }

  /**
   * Update program node entity POI. Doesn't save the node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node program which try to update.
   *
   * @return void
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function updateProgramEntity(NodeInterface $node) {

    $can_update = $this->canUpdateProgram($node);
    if (!$can_update) {
      return FALSE;
    }
    // Load config entities near by search.
    $config_entities = $this->entityTypeManager->getStorage('near_by_search_program')
      ->loadMultiple();

    $field_geol = $node->get('field_geol')->getValue();
    $location_origin = $field_geol[0]['lat'] . ',' . $field_geol[0]['lon'];


    $field_prog_near_by = $node->get('field_paragraph_multiple')->getValue();
    $para_ids = [];
    foreach ($field_prog_near_by as $old_para) {
      $para_ids[] = $old_para['target_id'];
    }

    $old_paragraphs = [];
    if (!empty($para_ids)) {
      $old_paragraphs = $this->entityTypeManager->getStorage('paragraph')
        ->loadMultiple($para_ids);
    }
    foreach ($config_entities as $config_entity) {
      $json = $this->getGooglePlace($location_origin, $config_entity);
      $this->getGoogleDirections($location_origin, $json);
      $paragraph = NULL;
      // Search inside paragraph by the id of config entities.
      foreach ($old_paragraphs as $old_para) {
        $field_type_near_by_search = $old_para->get('field_type_near_by_search')
          ->getValue();
        if ($field_type_near_by_search[0]['target_id'] == $config_entity->id()) {
          $paragraph = $old_para;
          $paragraph->set('field_json_data', json_encode($json));
          $paragraph->save();
          break;
        }
      }

      if (!empty($paragraph)) {
        continue;
      }
      // Create new paragraph.
      $paragraph = $this->createParagraph($config_entity->id(), json_encode($json));
      if (empty($paragraph)) {
        continue;
      }
      // Add new paragraph to node.
      $field_prog_near_by[] = [
        'target_id' => $paragraph->id(),
        'target_revision_id' => $paragraph->getRevisionId(),
      ];
    }
    $node->set('field_paragraph_multiple', $field_prog_near_by);

    $now = DrupalDateTime::createFromTimestamp(time());
    $node->set('field_date', $now->format('Y-m-d\TH:i:s'));
  }

  /**
   * Get google place near a location.
   *
   * @param $location_origin
   * @param \Drupal\lp_programs\Entity\NearBySearchProgramInterface $near_by_search_config
   *
   * @return array
   */
  private function getGooglePlace($location_origin, NearBySearchProgramInterface $near_by_search_config) {

    $json = [];
    $count_poi = 0;
    $settings = $this->configFactory->get('lp_programs.near_by_search.settings');
    $max_poi = $settings->get('max_poi');
    $params = [
      'language' => 'fr',
    ];
    $mode = $settings->get('mode') ?? 'radius';
    if ($mode == 'radius') {
      $params['radius'] = $settings->get('radius') ?? 2500;
    }

    if ($mode == 'rank_by') {
      $rank_by = $settings->get('rank_by') ?? 'distance';
      $params['rank_by'] = $rank_by;
      if ($rank_by == 'prominence') {
        $params['radius'] = $settings->get('radius') ?? 2500;
      }
    }
    /** @var  $near_by_search  \Drupal\lp_place_api\Api\Endpoint\PlaceNearBySearch */
    $near_by_search = $this->googleEndpoint->getEndpoint('placeNearBySearch');
    $lat_lon_duplicate_poi = $place_id = [];
    foreach ($near_by_search_config->get('google_type') as $google_type) {
      $params['type'] = $google_type;
      $pois = $near_by_search->getNearBySearch($location_origin, $params);
      $this->filterPoiResults($pois, $lat_lon_duplicate_poi, $place_id);
      $count_poi += count($pois['results']);
      $json[$google_type] = $pois['results'] ?? [];
      // Stop if max has been reach.
      if (!empty($max_poi) && $count_poi > $max_poi) {
        break;
      }
    }
    return $json;
  }

  /**
   * Remove duplicate POI and filter field.
   *
   * @param array $pois
   *
   * @return bool
   */
  private function filterPoiResults(array &$pois, array &$lat_lon_duplicate_poi, array &$place_id) {
    if (empty($pois['results'])) {
      return FALSE;
    }
    $settings = $this->configFactory->get('lp_programs.near_by_search.settings');
    $filter_field = $settings->get('filter_field') ? preg_split('/\r\n|\r|\n/', $settings->get('filter_field')) : [];
    foreach ($pois['results'] as $key_poi => &$result) {
      if (empty($result['geometry']['location']['lat']) || empty($result['geometry']['location']['lng'])) {
        unset($pois['results'][$key_poi]);
        continue;
      }
      // Verify we don't have already this POI by lat long.
      $key = $result['geometry']['location']['lat'] . '/' . $result['geometry']['location']['lng'];
      if (isset($lat_lon_duplicate_poi[$key])) {
        unset($pois['results'][$key_poi]);
        continue;
      }
      $lat_lon_duplicate_poi[$key] = TRUE;
      if (empty($result['place_id'])) {
        continue;
      }
      // Verify we don't have already this POI by place id.
      if (isset($place_id[$result['place_id']])) {
        unset($pois['results'][$key_poi]);
        continue;
      }
      $place_id[$result['place_id']] = TRUE;
      if (empty($settings->get('enable_filter_field')) || empty($filter_field)) {
        continue;
      }
      $this->filterField($result, $filter_field);
    }
    return TRUE;
  }

  /**
   * Filter key of field by another array of key to keep.
   *
   * @param array $results
   *   The array to filter.
   * @param array $filter_field
   *   The array of key to keep.
   *
   * @return void
   */
  private function filterField(array &$results, array $filter_field) {
    foreach ($results as $key => $value) {
      if (!in_array($key, $filter_field, TRUE)) {
        unset($results[$key]);
      }
    }
  }

  /**
   * Get direction of each POI.
   *
   * @param string $location_origin
   *   A string of origin format: 'lat,lng'.
   * @param array $json_place
   *   The array of data json to save.
   *
   * @return void
   */
  private function getGoogleDirections($location_origin, array &$json_place) {
    $settings = $this->configFactory->get('lp_programs.near_by_search.settings');
    $direction = $settings->get('directions') ?? [];
    $direction_params = [
      'language' => 'fr',
      'mode' => $direction['mode'] ?? 'walking',
      'region' => '.fr',
    ];
    if (!empty($direction['avoid'])) {
      $direction_params['avoid'] = implode('|', $direction['avoid']);
    }
    $direction_params['alternatives'] = $direction['alternatives'] ?? FALSE;
    $direction_service = $this->googleEndpoint->getEndpoint('directions');
    // Loop on each place to get direction.
    foreach ($json_place as $google_type => $all_place) {
      foreach ($all_place as $key => $place) {
        if (empty($place['geometry']['location'])) {
          continue;
        }
        // Get Directions of this place.
        $direction = $direction_service->getDirections($location_origin, $place['geometry']['location']['lat'] . ',' . $place['geometry']['location']['lng'], $direction_params);
        // Filter field of directions.
        $this->filterDirection($direction);
        $json_place[$google_type][$key]['direction'] = $direction;
      }
    }
  }

  /**
   * Filter field directions.
   *
   * @param array $direction
   *   Data of google directions.
   *
   * @return bool
   *   TRUE if field has been filter.
   */
  private function filterDirection(array &$direction) {

    $settings = $this->configFactory->get('lp_programs.near_by_search.settings');
    $direction_settings = $settings->get('directions') ?? [];
    if (empty($direction_settings['enable_filter_field']) || empty($direction_settings['directions'])) {
      return FALSE;
    }
    $filter_field = $direction_settings['directions']['field'] ? preg_split('/\r\n|\r|\n/', $direction_settings['directions']['field']) : [];
    if (empty($filter_field)) {
      return FALSE;
    }
    // Filter directions.
    $this->filterField($direction, $filter_field);

    $filter_field_route = $direction_settings['directions_route']['field'] ? preg_split('/\r\n|\r|\n/', $direction_settings['directions_route']['field']) : [];
    $filter_field_legs = $direction_settings['directions_route_legs']['field'] ? preg_split('/\r\n|\r|\n/', $direction_settings['directions_route_legs']['field']) : [];
    if (empty($filter_field_route) || !in_array('routes', $filter_field) || empty($direction['routes'])) {
      return FALSE;
    }
    // Filter routes.
    foreach ($direction['routes'] as &$routes) {
      $this->filterField($routes, $filter_field_route);
      if (empty($filter_field_legs) || !in_array('legs', $filter_field_route) || empty($routes['legs'])) {
        continue;
      }
      foreach ($routes['legs'] as &$legs) {
        // Filter legs
        $this->filterField($legs, $filter_field_legs);
      }
    }
    return TRUE;
  }

  /**
   * Create paragraph for the POI.
   *
   * @param string $config_entity_id
   *   The config id entities of near by search.
   * @param string $json
   *   The POI data json encoded.
   *
   * @return \Drupal\Core\Entity\EntityInterface|false
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function createParagraph($config_entity_id, $json) {
    $paragraph = $this->entityTypeManager->getStorage('paragraph')->create([
      'type' => 'near_by_search_program',
      'field_type_near_by_search' => ['target_id' => $config_entity_id],
      'field_json_data' => $json,
    ]);
    $saved = $paragraph->save();
    if ($saved !== SAVED_NEW && $saved !== SAVED_UPDATED) {
      return FALSE;
    }
    return $paragraph;
  }

  /**
   * Get the max date that we need to update program.
   *
   * @return \Drupal\Core\Datetime\DrupalDateTime
   *   The max date update.
   */
  public function getDateMaxPoi() {

    $day = $this->configFactory->get('lp_programs.near_by_search.settings')
        ->get('days') ?? 1;
    return new DrupalDateTime('now -' . $day . ' days');
  }

  /**
   * Return program needs to update POI (date not set or too old).
   *
   * @param boolean $load
   *   If true load the program entities.
   *
   * @return array|\Drupal\Core\Entity\EntityInterface[]|int
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getProgramToUpdate($load = TRUE) {
    $query = $this->entityTypeManager->getStorage('node')->getQuery();
    $query->condition('type', 'program');
    $query->condition('field_program_layout', 'sold', '<>');
    // Get date.
    $date_max = $this->getDateMaxPoi();
    // Build date empty or date too old.
    $orGroup = $query->orConditionGroup()
      ->notExists('field_date')
      ->condition('field_date', $date_max->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT), '<=');
    $query->condition($orGroup);

    $result = $query->execute();

    if (!empty($result) && $load === TRUE) {
      return $this->entityTypeManager->getStorage('node')
        ->loadMultiple($result);
    }
    return $result;
  }

  /**
   * @param \Drupal\node\NodeInterface $program
   *
   * @return array|false
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getJsonProgram(NodeInterface $program) {

    if (!$program->hasField('field_paragraph_multiple')) {
      $this->getLogger('lp_program.ProgramNearBySearchService')
        ->warning('The node program @nid has no field field_paragraph_multiple.', ['@nid' => $program->id()]);
      return FALSE;
    }
    $paragra_jsons = $program->get('field_paragraph_multiple')
      ->getValue();
    $target_id = [];
    foreach ($paragra_jsons as $paragra_json) {
      $target_id[] = $paragra_json['target_id'];
    }
    if (empty($target_id)) {
      return FALSE;
    }
    /** @var  $paragra_jsons \Drupal\paragraphs\Entity\Paragraph */
    $paragra_jsons = $this->entityTypeManager->getStorage('paragraph')
      ->loadMultiple($target_id);
    $result=[];
    foreach ($paragra_jsons as $paragra_json) {
      if (!$paragra_json->hasField('field_json_data')) {
        continue;
      }
      $field_json_data = $paragra_json->get('field_json_data')->getValue();
      if (empty($field_json_data[0]['value'])) {
        continue;
      }
      $result[] = json_decode($field_json_data[0]['value']);
    }
    return $result;
  }


}
