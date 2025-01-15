<?php

namespace Drupal\lp_programs\Batch;

use Drupal\lp_programs\Services\LotManagerService;
use Drupal\lp_programs\Services\ProgramManagerService;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Class BatchService.
 */
class BatchProgramLinkImport {

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
  public static function importProgramLink($file_path, &$context) {
    $lot_settings = \Drupal::config('lp_programs.lot_settings');
    $settings_batch = $lot_settings->get('batch');
    // Init value.
    $file = new \SplFileObject($file_path, 'r');
    if (empty($context['sandbox'])) {
      $file->seek(PHP_INT_MAX);
      $context['sandbox']['progress'] = 1;
      $context['sandbox']['max'] = $file->key();
    }
    $file->seek($context['sandbox']['progress']);
    $remains = $context['sandbox']['max'] - $context['sandbox']['progress'];
    $context['message'] = t(':count remainder program to import', [':count' => $remains]);
    $limit = 100;
    if ($remains < $limit) {
      $limit = $remains;
    }
    // Get ids for this batch.
    for ($i = 0; $i < $limit; $i++) {
      $line = $file->fgetcsv();
      $context['sandbox']['progress']++;
      $file->next();
      if (empty($line[0]) || empty($line[1])) {
        \Drupal::messenger()
          ->addError(t('Line empty, limit @limit,progress @progress,max @max, line @line', [
            '@limit' => $limit,
            '@progress' => $context['sandbox']['progress'],
            '@max' => $context['sandbox']['max'],
            '@line' => $file->key(),
          ]));
        continue;
      }
      /** @var  $program NodeInterface */
      $program = \Drupal::entityTypeManager()->getStorage('node')
        ->loadByProperties([
          'type' => 'program',
          'field_program_external_id' => $line[0],
        ]);
      /** @var  $program_parent NodeInterface */
      $program_parent = \Drupal::entityTypeManager()->getStorage('node')
        ->loadByProperties([
          'type' => 'program',
          'field_program_external_id' => $line[1],
        ]);
      if (empty($program) || empty($program_parent)) {
        \Drupal::messenger()
          ->addError(t('No program @id or @id_parent', [
            '@id' => $line[0],
            '@id_parent' => $line[1],
          ]));
        continue;
      }
      $program = reset($program);
      $program_parent = reset($program_parent);
      $field = $program_parent->get('field_program_related')->getValue();
      $paragraph_slider = NULL;
      if (!empty($field[0]['target_id'])) {
        $paragraph_slider = Paragraph::load($field[0]['target_id']);
        $field_program = $paragraph_slider->get('field_program')->getValue();
        $field_program[] = ['target_id' => $program->id()];
        $paragraph_slider->set('field_program', $field_program);
        $paragraph_slider->save();
      }
      if (empty($paragraph_slider)) {
        $paragraph_slider = self::createParagraphSlider($program->id());
        if (empty($paragraph_slider)) {
          continue;
        }
        // Set the paragraph on program parent.
        $field = $program_parent->get('field_program_related')->getValue();
        $field[] = [
          'target_id' => $paragraph_slider->id(),
          'target_revision_id' => $paragraph_slider->getRevisionId(),
        ];
        $program_parent->set('field_program_related', $field);
        $program_parent->save();
      }

      $context['message'] = t('Program :title has been updated.', [':title' => $program_parent->getTitle()]);
    }

    if ($context['sandbox']['progress'] != $context['sandbox']['max']) {
      $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
    }
  }

  private static function createParagraphSlider($program_id) {
    $paragraph = Paragraph::create([
      'type' => 'manual_program_slider',
      'field_program' => ['target_id' => $program_id],
    ]);
    $saved = $paragraph->save();
    if ($saved !== SAVED_NEW && $saved !== SAVED_UPDATED) {
      return FALSE;
    }
    return $paragraph;
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
  private static function saveLot(NodeInterface $node_program_lot, ProgramManagerService $program_manager, LotManagerService $lot_manager, array $data) {
    // Lot is empty/
    if (empty($data['LOT'])) {
      $program_manager->resetReferencedEntities($node_program_lot, [], ['field_program_lots']);
      return TRUE;
    }
    /** @var  $lots NodeInterface[] */
    $lots = $program_manager->getLotProgram([$node_program_lot]);

    if (!empty($lots[$node_program_lot->id()])) {
      self::removeLot($data, $node_program_lot, $lots[$node_program_lot->id()], $lot_manager);
    }

    $lot_save = [];
    // Loop on data lot.
    foreach ($data['LOT'] as $data_lot) {
      // Get node lot to update
      $node_lot_update = $lot_manager->findLotFieldValue($lots, $data_lot['NUM_LOT']);
      // Create lot if not exist.
      if (!$node_lot_update instanceof NodeInterface) {
        $node_lot_update = $lot_manager->createEntity();
      }
      // Rebuild surfaces.
      if (!empty($data_lot['SURFACES'])) {
        $data_lot['SURFACES'] = reset($data_lot['SURFACES']);
        foreach ($data_lot['SURFACES'] as $surface_key => $surface_value) {
          $data_lot[$surface_key] = $surface_value;
        }
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
