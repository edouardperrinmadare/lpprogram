<?php

namespace Drupal\lp_programs\Commands;

use Drush\Commands\DrushCommands;

/**
 * A Drush commandfile.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 *
 * See these files for an example of injecting Drupal services:
 *   - http://cgit.drupalcode.org/devel/tree/src/Commands/DevelCommands.php
 *   - http://cgit.drupalcode.org/devel/tree/drush.services.yml
 */
class LpProgramsImportLotCommands extends DrushCommands {

  /**
   * Import lot in a batch.
   *
   * @command lp_programs:lot-import
   */
  public function lp_programs_lot_import() {
    $this->logger()->success(dt('lp_programs_lot_import'));

    $lot_settins = \Drupal::configFactory()->get('lp_programs.lot_settings')->get('cron');
    $mode = $lot_settins['import_mode'];
    /** @var  $lp_program  \Drupal\lp_programs\Services\LotManagerService */
    $lp_program = \Drupal::service('lp_programs.lot_manager');
    $path_files = $lp_program->getXmlSftpFiles();
    $files_xml = $lp_program->openXmlFilePath($path_files);
    $timestamp_recent = 0;
    $file_to_import = [];
    // Get the xml files on FTP.
    foreach ($files_xml as $file_xml) {
      // Include all files.
      if ($mode === 'all_files') {
        $file_to_import[] = $file_xml['path'];
        continue;
      }
      // Include only the more recent one.
      if ($mode === 'date_file') {
        // Take date inside xml file.
        $date = (string) $file_xml['xml']->ENTETE->DATE;
        $timezone = new \DateTimeZone('Europe/Paris');
        $date_time = \DateTime::createFromFormat('Y-m-d H/i/s +', $date, $timezone);

        // Take modification date.
        $timestamp = $file_xml['mtime'];
        if ($date_time instanceof \DateTime) {
          $timestamp = $date_time->getTimestamp() ?? $file_xml['mtime'];
        }
        if ($timestamp > $timestamp_recent) {
          // Reset array files.
          $file_to_import = [];
          $timestamp_recent = $timestamp;
          $file_to_import[] = $file_xml;
        }
      }
    }

    // Create the operations array for the batch.
    $operations = [];

    foreach ($file_to_import as $local_path) {
      // Get data inside xml and add it to queue.
      $program_array = $lp_program->extractProgramXmlToArray($local_path['xml']);
      $program_array = $lp_program->transformProgramXmlToArray($program_array);

      if (!empty($program_array)) {
        $operations[] = [
          '\Drupal\lp_programs\Batch\BatchLotImport::importLot',
          [
            $program_array,
          ],
        ];

        // Create the batch.
        $batch = [
          'title' => t('Import programs'),
          'operations' => $operations,
          'finished' => '\Drupal\lp_programs\Batch\BatchLotImport::importLotFinished',
        ];
        // Add batch operations as new batch sets.
        batch_set($batch);
        // Process the batch sets.
        drush_backend_batch_process();

        $this->logger()->notice("Batch operations end.");

        // Log some information.
        \Drupal::logger('lp_programs')->info('Les lots ont été importés avec succès.');
      }
    }

  }
}
