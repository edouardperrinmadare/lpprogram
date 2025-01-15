<?php

namespace Drupal\lp_programs\Commands;

use Drupal\Core\Logger\LoggerChannelTrait;
use Drush\Commands\DrushCommands;

/**
 * Class LpProgramsDrushCommand.
 *
 * @package Drupal\lp_programs\Commands
 * @SuppressWarnings(PHPMD.CamelCaseParameterName)
 * @SuppressWarnings(PHPMD.CamelCaseVariableName)
 */
class LpProgramsDrushCommand extends DrushCommands {

  use LoggerChannelTrait;

  /**
   * Count POI imported.
   *
   * @command count_poi:imported
   * @aliases cpoii
   * @usage count_poi:imported
   *    Count POI imported in all programs.
   */
  public function pusblishPrecom() {
    // Create the operations array for the batch.
    $operations = [];

    $this->getLogger('lp_programs.drush_command')
      ->info('Start batch count POI.');
    $operations[] = [
      '\Drupal\lp_programs\Batch\BatchCountPoi::countPoi',
      [],
    ];
    // 4. Create the batch.
    $batch = [
      'title' => t('Count POI'),
      'operations' => $operations,
      'finished' => '\Drupal\lp_programs\Batch\BatchCountPoi::countPoiFinished',
    ];

    batch_set($batch);
    drush_backend_batch_process();
  }

  /**
   * Delete duplicate Lot entities.
   *
   * @param string $limit
   *   Max number of lots to delete.
   *
   * @command lp_programs:delete-duplicated-lots
   * @usage lp_programs:delete-duplicated-lots 1000
   *    Delete duplicated lots.
   */
  public function deleteDuplicatedLots($limit = 100) {
    // Create the operations array for the batch.
    $operations = [];

    $this->getLogger('lp_programs.drush_command')
      ->info('Start batch to delete duplicated lots.');
    $operations[] = [
      '\Drupal\lp_programs\Batch\BatchDeleteDuplicateLot::deleteDuplicatedLots',
      [
        $limit
      ],
    ];
    // Create the batch.
    $batch = [
      'title' => t('Delete duplicated lot'),
      'operations' => $operations,
      'finished' => '\Drupal\lp_programs\Batch\BatchCountPoi::deleteDuplicatedLotFinished',
    ];

    batch_set($batch);
    drush_backend_batch_process();
  }

}
