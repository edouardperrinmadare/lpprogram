<?php

namespace Drupal\lp_programs\Batch;

use Drupal\node\Entity\Node;

/**
 * Class BatchService.
 */
class BatchDeleteDuplicateLot {

  /**
   * @param $context
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public static function deleteDuplicatedLots($limit, &$context) {
    // Get all programs.
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'program');
    $program_nids = $query->execute();

    // Lots nids referenced in programs.
    $lot_nids = [];
    foreach ($program_nids as $nid) {
      $program = Node::load($nid);

      if ($program->field_program_lots->isEmpty()) {
        continue;
      }

      $lot_referenced_nids = array_column($program->field_program_lots->getValue(), 'target_id');

      if ($lot_referenced_nids[0] == 1) {
        continue;
      }

      $lot_nids = array_merge($lot_nids, $lot_referenced_nids);
    }

    // Set batch init progress.
    if (!isset($context['sandbox']['progress'])) {
      // Count duplicated lots within the limit.
      $query = \Drupal::entityQuery('node')
        ->condition('type', 'lot')
        ->condition('nid', $lot_nids, 'NOT IN')
        ->range(0, $limit);
      $lots_duplicated_nids_count = $query->count()->execute();

      // Add batch info.
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['current_id'] = 0;
      $context['sandbox']['max'] = $lots_duplicated_nids_count;
    }

    // 50 nodes at a time without a timeout.
    $delete_count_per_batch = 50;

    // Check number of lot that remains to delete.
    $remains_to_delete = $context['sandbox']['max'] - $context['sandbox']['progress'];
    if ($remains_to_delete < $delete_count_per_batch) {
      $delete_count_per_batch = $remains_to_delete;
    }

    // Get duplicated lots within the limit.
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'lot')
      ->condition('nid', $lot_nids, 'NOT IN')
      ->range(0, $delete_count_per_batch);
    $lots_duplicated_nids = $query->execute();
    $last_id = array_values(array_slice($lots_duplicated_nids, -1))[0];

    // Delete lot.
    $storage_handler = \Drupal::entityTypeManager()->getStorage("node");
    $nodes = $storage_handler->loadMultiple($lots_duplicated_nids);
    $storage_handler->delete($nodes);

    // Store some results for post-processing in the 'finished' callback.
    // The contents of 'results' will be available as $results in the
    // 'finished' function.
    $context['results'] = array_merge($context['results'], $lots_duplicated_nids);

    // Batch information update.
    $context['sandbox']['current_id'] = $last_id;
    $context['sandbox']['progress'] += count($lots_duplicated_nids);
    $context['message'] = t('@count lots deleted, @remain_count lots remain to delete.', [
      '@count' => $context['sandbox']['progress'],
      '@remain_count' => $context['sandbox']['max'] - $context['sandbox']['progress'],
    ]);

    // Inform the batch engine that we are not finished,
    // and provide an estimation of the completion level we reached.
    if ($context['sandbox']['progress'] != $context['sandbox']['max']) {
      $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
    }
  }

  /**
   * Batch Finished callback.
   *
   * @param bool $success
   *   Success of the operation.
   * @param array $results
   *   Array of results for post processing.
   * @param array $operations
   *   Array of operations.
   */
  public static function deleteDuplicatedLotFinished($success, array $results, array $operations) {
    $messenger = \Drupal::messenger();
    if ($success) {
      $messenger->addMessage(t('@count total lots deleted.', ['@count' => count($results)]));
      \Drupal::logger('lp_programs')->notice('@count total lots deleted.', ['@count' => count($results)]);
    }
    else {
      // An error occurred.
      // $operations contains the operations that remained unprocessed.
      $error_operation = reset($operations);
      $messenger->addMessage(
        t('An error occurred while processing @operation with arguments : @args',
          [
            '@operation' => $error_operation[0],
            '@args' => print_r($error_operation[0], TRUE),
          ]
        )
      );
    }
  }
}
