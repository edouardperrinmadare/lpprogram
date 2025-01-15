<?php

namespace Drupal\lp_programs\Batch;

use Drupal\lp_programs\Services\LotManagerService;
use Drupal\lp_programs\Services\ProgramManagerService;
use Drupal\node\NodeInterface;

/**
 * Class BatchService.
 */
class BatchLotImport {

  /**
   * Batch operation callback to import lot.
   *
   * @param array $lot_to_import
   *   Array of program to import (data prviding from webservice).
   * @param $context
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public static function importLot(array $lot_to_import, &$context) {
    /** @var  $lot_manager \Drupal\lp_programs\Services\LotManagerService */
    $lot_manager = \Drupal::service('lp_programs.lot_manager');
    /** @var  $program_manager \Drupal\lp_programs\Services\ProgramManagerService */
    $program_manager = \Drupal::service('lp_programs.program_manager');
    $lot_settings = \Drupal::config('lp_programs.lot_settings');
    $settings_batch = $lot_settings->get('batch');
    // Init value.
    if (empty($context['sandbox'])) {
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['max'] = count($lot_to_import);
      \Drupal::logger('lp_programs.BatchLotImport')
        ->info(t('Starting batch import lot manual: @count lots to import', [
          '@count' => $context['sandbox']['max'],
        ]));
    }
    if (empty($lot_to_import)) {
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['max'] = 0;
      $context['finished'] = 1;
      \Drupal::logger('lp_programs.BatchLotImport')
        ->error(t('No data inside xml.'));
    }
    $remains = $context['sandbox']['max'] - $context['sandbox']['progress'];
    $context['message'] = t(':count remainder program to import', [':count' => $remains]);
    $limit = $settings_batch['limit'] ?? 50;
    \Drupal::logger('lp_programs.BatchLotImport')
      ->info($context['message']);
    if ($remains < $limit) {
      $limit = $remains;
    }
    // Get ids for this batch.
    $ids = [];
    for ($i = 0; $i < $limit; $i++) {
      $indice = $context['sandbox']['progress'] + $i;
      if (empty($lot_to_import[$indice]['NUMERO'])) {
        \Drupal::logger('lp_programs.BatchLotImport')
          ->error(t('data has no key "NUMERO", indice: @ind, progress: @prog, limit: @limit', [
            '@ind' => $indice,
            '@limit' => $limit,
            '@prog' => $context['sandbox']['progress'],
          ]));
        continue;
      }
      $ids[] = $lot_to_import[$indice]['NUMERO'];
    }
    // Get node programs by external ids.
    $node_programs = $program_manager->getEntityByExternalId($ids);
    // Loop batch to create or update node.
    $init_progress = $context['sandbox']['progress'];
    for ($i = 0; $i < $limit; $i++) {
      $indice = $init_progress + $i;
      if (empty($lot_to_import[$indice]['NUMERO'])) {
        \Drupal::logger('lp_programs.BatchLotImport')
          ->error(t('data has no key "NUMERO", indice: @ind @max @re, progress: @prog, limit: @limit', [
            '@ind' => $indice,
            '@max' => $context['sandbox']['max'],
            '@re' => $remains,
            '@limit' => $limit,
            '@prog' => $context['sandbox']['progress'],
          ]));
        continue;
      }
      // Search for field id external inside node list.
      $node_program_lot = self::getNodeProgram($node_programs, $program_manager, $lot_to_import[$indice]);

      // Create node if not found inside node liste.
      // Modified Process : node not automatically create if not exist
      /*if ($node_program_lot === FALSE) {
        $node_program_lot = $program_manager->createEntity();
        self::updateProgram($node_program_lot, $program_manager, $lot_to_import[$indice]);
      }*/
      if (!$node_program_lot instanceof NodeInterface) {
        \Drupal::logger('lp_programs.BatchLotImport')
          ->error(t("can't get or create node program. ".$lot_to_import[$indice]['NUMERO']));
        continue;
      }
      $lot_saved = self::saveLot($node_program_lot, $program_manager, $lot_manager, $lot_to_import[$indice]);
      $node_program_lot->set('field_program_lots', $lot_saved);
      $saved = $node_program_lot->save();
      if ($saved == SAVED_NEW) {

        $context['results']['created'][] = $node_program_lot->id();
      }
      if ($saved == SAVED_UPDATED) {
        $context['results']['updated'][] = $node_program_lot->id();
      }
      $context['sandbox']['progress']++;
      $context['message'] = t('Node :title has been updated.', [':title' => $node_program_lot->getTitle()]);
    }
    if ($context['sandbox']['progress'] != $context['sandbox']['max']) {
      $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
    }
  }

  /**
   * @param \Drupal\node\NodeInterface $node_program_lot
   * @param \Drupal\lp_programs\Services\ProgramManagerService $program_manager
   * @param \Drupal\lp_programs\Services\LotManagerService $lot_manager
   * @param array $data
   *
   * @return array|bool
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public static function saveLot(NodeInterface $node_program_lot, ProgramManagerService $program_manager, LotManagerService $lot_manager, array $data) {
    // Lot is empty/
    if (empty($data['LOT'])) {
      $program_manager->resetReferencedEntities($node_program_lot, [], ['field_program_lots']);
      return TRUE;
    }
    /** @var  $lots NodeInterface[] */
    $lots = $program_manager->getLotProgram([$node_program_lot]);

    if (!empty($lots[$node_program_lot->id()])) {
      self::removeLot($data, $node_program_lot, $lots[$node_program_lot->id()], $lot_manager);
    } else {
      $lots[$node_program_lot->id()] = [];
    }

    $lot_save = [];
    // Loop on data lot.
    foreach ($data['LOT'] as $data_lot) {
      // Get node lot to update
      $node_lot_update = $lot_manager->findLotFieldValue($lots[$node_program_lot->id()], $data_lot['NUM_LOT']);

      if (!$node_lot_update instanceof NodeInterface) {
          $node_lot_update = $lot_manager->createEntity();
      } else {
          // Effacer tous les champs mappés avant la mise à jour
          $lot_manager->clearMappedFields($node_lot_update);
      }
      // Rebuild surfaces.
      if (!empty($data_lot['SURFACES'])) {
        $data_lot['SURFACES'] = reset($data_lot['SURFACES']);
        foreach ($data_lot['SURFACES'] as $surface_key => $surface_value) {
          $data_lot[$surface_key] = $surface_value;
        }
      }
      // Rebuild lot type.
      if (!empty($data_lot['TYPE_LOT'])) {
        $type_list = explode(' ', $data_lot['TYPE_LOT']);
        if (count($type_list) == 2) {
          $type_lot = $type_list[0];
          $type_lot_complement = $type_list[1];
          $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties([
            'status' => 1,
            'vid' => 'lot_type_complement',
            'name' => $type_lot_complement,
          ]);
          if (!empty($terms)) {
            $data_lot['TYPE_LOT_COMPLEMENT'] = $type_lot_complement;
            $data_lot['TYPE_LOT'] = $type_lot;
          }
        }
      }
      // Rebuild lot dispositif.
      if (!empty($data_lot['DISPOSITIF'])) {
        $search = ['LMNP amortissement', 'LMNP Censi-bouvard'];
        $replace = ['LMNP', 'LMNP'];
        $subject = $data_lot['DISPOSITIF'];
        $data_lot['DISPOSITIF'] = str_ireplace($search, $replace, $subject);
      }

      // Build data for field drupal.
      $data_mapped = $lot_manager->buildDataMapping($data_lot);
      $node_lot_update->setTitle(t('Lot @num', ['@num' => $data_lot['NUM_LOT']]));
      // Set data lot on node.
      $lot_manager->setFieldOnEntity($node_lot_update, $data_mapped);

      $saved = $node_lot_update->save();
      if ($saved == SAVED_NEW || $saved == SAVED_UPDATED) {
        $lot_save[] = ['target_id' => $node_lot_update->id()];
      }
    }
    return $lot_save;

  }

  /**
   * Remove all lot not inside data.
   *
   * @param array $data
   * @param \Drupal\node\NodeInterface $node_program_lot
   * @param array $lots
   * @param \Drupal\lp_programs\Services\LotManagerService $lot_manager
   *
   * @return array
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private static function removeLot(array $data, NodeInterface $node_program_lot, array $lots, LotManagerService $lot_manager) {
    $num_lots_id = [];
    foreach ($data['LOT'] as $lot) {
      $num_lots_id[$lot['NUM_LOT']] = $lot;
    }
    $lot_ids_remove = [];
    foreach ($lots as $node_lot) {
      if ($node_lot->hasField($lot_manager->getFieldNameExternalId()) && $node_lot->get($lot_manager->getFieldNameExternalId())
          ->getValue()) {
        $id_external = $node_lot->get($lot_manager->getFieldNameExternalId())
          ->getValue();
        if (empty($id_external[0]['value']) || empty($num_lots_id[$id_external[0]['value']])) {
          $lot_ids_remove[] = $id_external[0]['value'];
        }
      }
    }
    // Delete lot references.
    if (!empty($lot_ids_remove)) {
      $lot_manager->removeReferencedEntities($node_program_lot, 'field_program_lots', $lot_ids_remove);
    }
    return $lot_ids_remove;
  }

  /**
   * @param \Drupal\node\NodeInterface $node_program_lot
   * @param \Drupal\lp_programs\Services\ProgramManagerService $program_manager
   * @param array $data
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private static function updateProgram(NodeInterface $node_program_lot, ProgramManagerService $program_manager, array $data) {
    $data_mapped = $program_manager->buildDataMapping($data);
    // Set data lot on node.
    $program_manager->setFieldOnEntity($node_program_lot, $data_mapped);
  }

  /**
   * @param array $node_programs
   * @param \Drupal\lp_programs\Services\ProgramManagerService $program_manager
   * @param $data
   *
   * @return false|mixed
   */
  private static function getNodeProgram(array $node_programs, ProgramManagerService $program_manager, $data) {
    $node_program_lot = FALSE;
    foreach ($node_programs as $program) {
      if ($program->hasField($program_manager->getFieldNameExternalId()) && $program->get($program_manager->getFieldNameExternalId())) {
        $value = $program->get($program_manager->getFieldNameExternalId())
          ->getValue();
        // Node already created.
        if ($value[0]['value'] == $data['NUMERO']) {
          $node_program_lot = $program;
          break;
        }
      }
    }
    return $node_program_lot;
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
  public static function importProgramFinished($success, array $results, array $operations) {
    $messenger = \Drupal::messenger();
    if ($success) {
      // Here we could do something meaningful with the results.
      // We just display the number of nodes we processed...
      $messenger->addMessage(t('@count program created.', ['@count' => !empty($results['created']) ? count($results['created']) : 0]));
      $messenger->addMessage(t('@count program updated.', ['@count' => !empty($results['updated']) ? count($results['updated']) : 0]));
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
