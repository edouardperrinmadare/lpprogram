<?php

namespace Drupal\lp_programs\Plugin\QueueWorker;

use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\lp_programs\Services\ProgramNearBySearchService;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class NearBySearchQueueWorker.
 *
 * @QueueWorker(
 *  id = "near_by_search_queue_worker",
 *  title = @Translation("Update program POI near by searh."),
 *  cron = {"time" = 60}
 * )
 * @SuppressWarnings(PHPMD.CamelCaseParameterName)
 * @SuppressWarnings(PHPMD.CamelCaseVariableName)
 */
class NearBySearchQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * @var \Drupal\lp_programs\Services\ProgramNearBySearchService
   */
  protected $programNearBySearchService;

  use LoggerChannelTrait;

  /**
   * CoveaLinkQueueWorker constructor.
   *
   * {@inheritDoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ProgramNearBySearchService $programNearBySearchService) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->programNearBySearchService = $programNearBySearchService;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('lp_programs.near_by_search')
    );
  }

  /**
   * {@inheritDoc}
   */
  public function processItem($data) {

    if (!empty($data) && $data instanceof NodeInterface) {
      $this->programNearBySearchService->updateProgramEntity($data);
      $saved= $data->save();
      if ($saved !== SAVED_NEW && $saved !== SAVED_UPDATED) {
        $this->getLogger('lp_programs.NearBySearchQueueWorker')
          ->error(t('Error saving program'));
      }
    }
  }

}
