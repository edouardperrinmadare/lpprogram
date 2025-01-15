<?php

namespace Drupal\lp_programs\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\feeds\Event\EntityEvent;
use Drupal\feeds\Event\FeedsEvents;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class EntityTypeSubscriber.
 *
 * @package Drupal\custom_events\EventSubscriber
 */
class FeedsEventsSubscriber implements EventSubscriberInterface {

  use LoggerChannelTrait;

  protected $entityTypeManager;

  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    // Reference the current_user service
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   *
   * @return array
   *   The event names to listen for, and the methods that should be executed.
   */
  public static function getSubscribedEvents() {
    return [
      FeedsEvents::PROCESS_ENTITY_POSTSAVE => 'entityPostSave',
    ];
  }

  /**
   * Event subscriber callback of feed entity post save.
   *
   * @param \Drupal\feeds\Event\EntityEvent $feed_event
   * @param $event_name
   *
   * @return void
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function entityPostSave(EntityEvent $feed_event, $event_name) {
    $lot = $feed_event->getEntity();
    $item = $feed_event->getItem();
    $item = $item->toArray();
    if ($lot instanceof Node) {
      $program = $this->getProgramLot($lot, $item);
      if ($program instanceof Node) {
        $this->attachLotProgram($lot, $program, $item);
        $this->calculatePriceProgram($program, $item);
      }
    }
  }

  /**
   * @param \Drupal\node\NodeInterface $lot
   * @param array $item
   *
   * @return \Drupal\node\Entity\Node|false
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function getProgramLot(NodeInterface $lot, array $item) {
    if ($lot->bundle() !== 'lot') {
      return FALSE;
    }
    // Node lot is not saved.
    if (empty($lot->id())) {
      return FALSE;
    }
    if (empty($item['program_id']) || empty($item['lot_type'])) {
      $this->getLogger('lp_programs.entityPostSave')
        ->error("Program id  or lot type is empty for the lot @id @title", [
          '@id' => $lot->id(),
          '@title' => $lot->getTitle(),
        ]);
      return FALSE;
    }
    $program = $this->entityTypeManager->getStorage('node')
      ->loadByProperties([
        'type' => 'program',
        'field_program_external_id' => $item['program_id'],
      ]);
    if (empty($program)) {
      $this->getLogger('lp_programs.entityPresave')
        ->error("Can't find program with external id @id", ['@id' => $item['program_id']]);
      return FALSE;
    }
    /** @var  $program \Drupal\node\Entity\Node */
    $program = reset($program);
    return $program;
  }

  /**
   * Calculate min and max price of program.
   *
   * @param \Drupal\node\NodeInterface $program
   * @param array $item
   *
   * @return bool
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function calculatePriceProgram(NodeInterface $program, array $item) {

    $type_lot = $program->get('field_type_lot')->getValue();
    if (empty($type_lot[0]['value']) || empty($item['field_prix_und_0_value']) || empty($item['lot_type'])) {
      return FALSE;
    }
    // Not same lot type.
    if ($item['lot_type'] != $type_lot[0]['value']) {
      return FALSE;
    }
    $min = $program->get('field_program_min_price')->getValue();
    $max = $program->get('field_program_max_price')->getValue();
    $save = FALSE;
    // Set max.
    if (empty($max) ||$item['field_prix_und_0_value'] > $max[0]['value'] ) {
      $save = TRUE;
      $program->set('field_program_max_price', $item['field_prix_und_0_value']);
    }
    // Set min.
    if ( empty($min)|| $item['field_prix_und_0_value'] < $min[0]['value'] || $min[0]['value'] == 0) {
      $save = TRUE;
      $program->set('field_program_min_price', $item['field_prix_und_0_value']);
    }
    if ($save == TRUE) {
      $program->save();
    }
    return TRUE;
  }

  /**
   * Attach node lot on programme.
   *
   * @param \Drupal\node\NodeInterface $lot
   * @param array $item
   *
   * @return bool
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function attachLotProgram(NodeInterface $lot, NodeInterface $program, array $item) {

    $field_name = 'field_program_lots';
    if ($item['lot_type'] == 'manual') {
      $field_name = 'field_manual_lots';
    }
    $field_program = $program->get($field_name)->getValue();
    $field_program[] = ['target_id' => $lot->id()];
    $program->set($field_name, $field_program);
    $program->save();
    return TRUE;
  }

}
