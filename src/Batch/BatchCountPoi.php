<?php

namespace Drupal\lp_programs\Batch;


/**
 * Class BatchService.
 */
class BatchCountPoi {

  /**
   * @param $context
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public static function countPoi(&$context) {
    $entity_type_manager = \Drupal::entityTypeManager();
    /** @var  $near_by_search_service \Drupal\lp_programs\Services\ProgramNearBySearchService */
    $near_by_search_service = \Drupal::service('lp_programs.near_by_search');
    $storage = $entity_type_manager->getStorage('node');
    if (!isset($context['sandbox']['progress'])) {
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['current_id'] = 0;
      $context['sandbox']['max'] = $storage->getQuery()
        ->condition('type', 'program')
        ->count()->execute();
      $context['results']['poi'] = [];
    }

    // For this example, we decide that we can safely process
    // 5 nodes at a time without a timeout.
    $limit = 50;

    // With each pass through the callback, retrieve the next group of nids.
    $query = $storage->getQuery()
      ->condition('type', 'program')
      ->condition('nid', $context['sandbox']['current_id'], '>')
      ->sort('nid')
      ->pager($limit);

    $result = $query->execute();
    $programs = [];
    if (!empty($result)) {
      /** @var  $programs  \Drupal\node\Entity\Node[] */
      $programs = $storage->loadMultiple($result);
    }
    foreach ($programs as $node_program) {
      $json_fields = $near_by_search_service->getJsonProgram($node_program);
      $context['sandbox']['current_id'] = $node_program->id();
      $context['sandbox']['progress']++;
      if (empty($json_fields)) {
        continue;
      }
      foreach ($json_fields as $json) {
        foreach ($json as $google_type => $poi) {
          $context['results']['poi'][$google_type] += count($poi);
        }
      }
      $context['message'] = t('count poi of @id: @title', [
        '@id' => $node_program->id(),
        '@title' => $node_program->getTitle(),
      ]);
    }
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
  public static function countPoiFinished($success, array $results, array $operations) {
    $messenger = \Drupal::messenger();
    if ($success) {
      // Here we could do something meaningful with the results.
      // We just display the number of nodes we processed...
      $total=0;
      foreach ($results['poi'] as $google_type => $count) {
        $messenger->addMessage(t('@count @google poi.', [
          '@count' => $count,
          '@google' => $google_type,
        ]));
        $total+=$count;
      }
      $messenger->addMessage(t('@count total poi.', [
        '@count' => $total,
      ]));
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
