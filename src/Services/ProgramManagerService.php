<?php

namespace Drupal\lp_programs\Services;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\node\NodeInterface;

/**
 * Manage feature for program.
 *
 * @SuppressWarnings(PHPMD.CamelCaseParameterName)
 * @SuppressWarnings(PHPMD.CamelCaseVariableName)
 */
class ProgramManagerService extends ProgramLotServiceBase
{

  /** @var */
  protected $database;

  /**
   *
   */
  use LoggerChannelTrait;

  /**
   * {@inheritDoc}
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, Connection $database)
  {
    parent::__construct($entity_type_manager);
    $this->database = $database;
  }

  /**
   * The machine name of bundle node representing a program.
   */
  private const NODE_BUNDLE = 'program';

  /**
   * The field name where external id is stored.
   */
  private const FIELD_NAME_EXTERNAL_ID = 'field_program_external_id';

  /**
   * {@inheritDoc}
   */
  public function getNodeBundle()
  {
    return self::NODE_BUNDLE;
  }

  /**
   * {@inheritDoc}
   */
  public function getFieldNameExternalId()
  {
    return self::FIELD_NAME_EXTERNAL_ID;
  }

  /**
   * {@inheritDoc}
   */
  public function getMapping()
  {
    return [
      'NUMERO' => 'field_program_external_id',
      'NOM' => 'title',
      'ADRESSE_POSTALE' => ['field_address' => 'address_line1'],
      'CP' => ['field_address' => 'postal_code'],
      'VILLE' => ['field_address' => 'locality'],
      'PAYS' => ['field_address' => 'country_code'],
      'DESCRIPTIF_LONG' => 'field_program_presentation',
      //   'DESCRIPTIF_COURT' => ['field_program_presentation' => 'summary'],
      // 'ADRESSE_WEB' => '',
      //  'PERSPECTIVE' => '',
      //  'URL_PLAQUETTE' => '',
      //   'URL_ERNT' => '',
      'TYPE_BIEN' => 'field_program_types',
      //      'NB_LOTS' => '',
      'DATE_LIVRAISON' => 'field_program_delivery',
    ];
  }

  /**
   * Get all "lot" given array of program ids.
   *
   * @param array $nids_program
   * @param bool $load_lot
   *
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getLotProgram(array $node_program, $load_lot = TRUE)
  {
    /** @var  $node_program NodeInterface[] */
    $result_lot = [];
    foreach ($node_program as $node) {
      if (!$node instanceof NodeInterface) {
        continue;
      }
      if (!$node->hasField('field_program_lots')) {
        continue;
      }
      // Collect lots ids.
      $lot_ref_field = $node->get('field_program_lots')->getValue();
      foreach ($lot_ref_field as $ref_field) {
        $result_lot[$node->id()][] = $ref_field['target_id'];
      }
    }
    if (!$load_lot || empty($result_lot)) {
      return $result_lot;
    }
    foreach ($result_lot as $nid => $lots_ids) {
      $result_lot[$nid] = $this->entityTypeManager->getStorage('node')
        ->loadMultiple($lots_ids);
    }
    return $result_lot;
  }

  /**
   * Load program by lot id.
   *
   * @param \Drupal\node\NodeInterface $node_lot
   *
   * @return \Drupal\Core\Entity\EntityInterface[]|false
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getProgramByLot(NodeInterface $node_lot)
  {
    // query import lot
    $query = $this->entityTypeManager->getStorage('node')->getQuery();
    
    $query->condition('field_program_lots', $node_lot->id());
    $node_parent_ids = $query->execute();
    if (!empty($node_parent_ids)) {
      return $this->entityTypeManager->getStorage('node')
        ->loadMultiple($node_parent_ids);
    }

    // query manual lot
    $query_manual = $this->entityTypeManager->getStorage('node')->getQuery();

    $query_manual->condition('field_manual_lots', $node_lot->id());
    $node_parent_ids = $query_manual->execute();
    if (!empty($node_parent_ids)) {
      return $this->entityTypeManager->getStorage('node')
        ->loadMultiple($node_parent_ids);
    }
    
    return FALSE;
  }

  /**
   * Get Program by adress.
   *
   * @param array $address
   * @param array $field_conditions
   *   Other field condition format (['field'=>['value'=>,'operator'=>]]).
   *
   * @return array|int
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getProgramByAddress(array $address, array $field_conditions = [])
  {
    $query = $this->entityTypeManager->getStorage('node')->getQuery();

    $query->condition('type', 'program');
    // Insert address component inside condition.
    foreach ($address as $component => $value) {
      if (empty($value)) {
        continue;
      }
      $query->condition('field_address.' . $component, $value);
    }
    foreach ($field_conditions as $field => $value) {
      if (empty($value['value'])) {
        continue;
      }
      $value['operator'] = $value['operator'] ?? '=';
      $query->condition($field, $value['value'], $value['operator']);
    }

    return $query->execute();
  }

  /**
   * @param \Drupal\node\NodeInterface $program
   *
   * @return NodeInterface[]|false
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getLotsDisplayProgram(NodeInterface $program)
  {
    if (!$program->hasField('field_type_lot') || !$program->hasField('field_program_lots') || !$program->hasField('field_manual_lots')) {
      return FALSE;
    }
    if ($program->get('field_type_lot')->isEmpty()) {
      return FALSE;
    }
    if ($program->get('field_type_lot')
      ->getString() === 'import' && $program->get('field_program_lots')
      ->isEmpty()
    ) {
      return FALSE;
    }
    if ($program->get('field_type_lot')
      ->getString() === 'manual' && $program->get('field_manual_lots')
      ->isEmpty()
    ) {
      return FALSE;
    }

    if ($program->get('field_type_lot')->getString() === 'import') {
      $field_name_lot = 'field_program_lots';
    }
    if ($program->get('field_type_lot')->getString() === 'manual') {
      $field_name_lot = 'field_manual_lots';
    }
    $ids = [];
    $field_lot = $program->get($field_name_lot)->getValue();
    foreach ($field_lot as $lot) {
      if ($lot['target_id'] != 1) {
        $ids[] = $lot['target_id'];
      }
    }

    if (empty($ids)) {
      return [];
    }

    /** @var  $lots NodeInterface[] */
    return $this->entityTypeManager->getStorage('node')->loadMultiple($ids);
  }

  /**
   * Update min max surface program by the lots set inside.
   *
   * @param \Drupal\node\NodeInterface $program
   * @param $save
   *
   * @return void
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function updateSurfaceProgram(NodeInterface $program, $save = FALSE)
  {

    $third = $this->getThirdPartySettingsNode();
    $enable = FALSE;
    foreach ($third['synch_field_lot'] as $enable) {
      if ($enable === 'surface') {
        $enable = TRUE;
        break;
      }
    }
    if ($enable == FALSE) {
      return FALSE;
    }
    $this->compareFieldMinMaxProgram($program, 'field_program_min_surface', 'field_program_max_surface', 'field_lot_area', FALSE);
    if ($save == TRUE) {
      $program->save();
    }
  }

  public function getThirdPartySettingsNode()
  {

    $node_type = $this->entityTypeManager->getStorage('node_type')
      ->load('program');
    if (!empty($node_type)) {
      return $node_type->getThirdPartySetting('lp_programs', 'form');
    }
    return [];
  }

  /**
   * Update min max price program by the lots set inside.
   *
   * @param \Drupal\node\NodeInterface $program
   * @param $save
   *
   * @return false|void
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function updatePriceProgram(NodeInterface $program, $save = FALSE)
  {
    $third = $this->getThirdPartySettingsNode();
    $enable = FALSE;
    foreach ($third['synch_field_lot'] as $enable) {
      if ($enable === 'price') {
        $enable = TRUE;
        break;
      }
    }
    if ($enable == FALSE) {
      return FALSE;
    }

    $is_price_ht = $program->get('field_program_notaxprice')->getString();
    if ($is_price_ht) {
      $field_lot_price = 'field_lot_price_ht';
    } else {
      $field_lot_price = 'field_lot_price';
    }
    $this->compareFieldMinMaxProgram($program, 'field_program_min_price', 'field_program_max_price', $field_lot_price, $is_price_ht);
    if ($save == TRUE) {
      $program->save();
    }
  }

  /**
   * Set min and max inside program field compare to one field value of lot.
   *
   * @param \Drupal\node\NodeInterface $program
   * @param $fiel_min_program
   * @param $field_max_program
   * @param $field_lot
   *
   * @return bool
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function compareFieldMinMaxProgram(NodeInterface $program, $fiel_min_program, $field_max_program, $field_lot, $is_price_ht)
  {
    if (!$program->hasField($fiel_min_program) || !$program->hasField($field_max_program)) {
      return FALSE;
    }

    $min = 0;
    $max = 0;
    $lots = $this->getLotsDisplayProgram($program);

    // If not lots set price fields to null
    if (empty($lots)) {
      if ($program->get($field_max_program)->getString() == '' && $program->get($fiel_min_program)->getString() == '') {
        return FALSE;
      }
      $program->set($field_max_program, NULL);
      $program->set($fiel_min_program, NULL);
      return TRUE;
    }

    $change = FALSE;
    foreach ($lots as $lot) {
      if (!$lot->hasField($field_lot)) {
        continue;
      }

      // Price ht not available.
      if ($lot->get($field_lot)->isEmpty() && $is_price_ht) {
        $program->set('field_program_notaxprice', 0);
        $field_lot = 'field_lot_price';
        \Drupal::messenger()->addWarning(t('Les lots de ce programme ne disposent pas de prix HT. <br>Veuillez vérifier le fichier XML des données des lots.'));
      }

      if ($lot->get($field_lot)->isEmpty()) {
        continue;
      }

      $lot_price = $lot->get($field_lot)->getString();

      // Set min.
      if (empty($min) || $lot_price < $min || $min == 0) {
        $min = $lot_price;
        $program->set($fiel_min_program, $lot_price);
        $change = TRUE;
      }

      // Set max.
      if (empty($max) || $lot_price > $max) {
        $max = $lot_price;
        $program->set($field_max_program, $lot_price);
        $change = TRUE;
      }
    }

    switch ($field_lot) {
      case 'field_lot_area':
        if ($min == $max) {
          $program->set($field_max_program, NULL);
        }
        break;
    }

    return $change;
  }
}
