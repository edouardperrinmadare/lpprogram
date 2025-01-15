<?php

namespace Drupal\lp_programs\Plugin\QueueWorker;

use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\lp_programs\Batch\BatchLotImport;
use Drupal\lp_programs\Services\LotManagerService;
use Drupal\lp_programs\Services\ProgramManagerService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class LotXmlQueueWorker.
 *
 * @QueueWorker(
 *  id = "lot_xml_queue_worker",
 *  title = @Translation("Update lot xml of program."),
 *  cron = {"time" = 340}
 * )
 * @SuppressWarnings(PHPMD.CamelCaseParameterName)
 * @SuppressWarnings(PHPMD.CamelCaseVariableName)
 */
class LotXmlQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  protected $programService;

  protected $lotService;


  use LoggerChannelTrait;

  /**
   * CoveaLinkQueueWorker constructor.
   *
   * {@inheritDoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ProgramManagerService $program_manager_service, LotManagerService $lot_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->programService = $program_manager_service;
    $this->lotService = $lot_manager;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('lp_programs.program_manager'),
      $container->get('lp_programs.lot_manager'),
    );
  }

  /**
   * {@inheritDoc}
   */
  public function processItem($data) {
    if (!empty($data)) {
      if (empty($data['NUMERO'])) {
        return FALSE;
      }
      $node_program_lot = $this->programService->getEntityByExternalId([$data['NUMERO']]);
      if (!empty($node_program_lot)) {
        $node_program_lot = reset($node_program_lot);
      } else {
        return FALSE;
      }
  
      $lot_saved = BatchLotImport::saveLot($node_program_lot, $this->programService, $this->lotService, $data);
      $node_program_lot->set('field_program_lots', $lot_saved);
      $saved = $node_program_lot->save();
    }
  }

}
