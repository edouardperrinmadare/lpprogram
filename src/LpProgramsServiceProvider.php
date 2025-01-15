<?php
namespace Drupal\lp_programs;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;


/**
 * Modifies the language manager service.
 */
class LpProgramsServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    $definitions = array_keys($container->getDefinitions());
    if (!in_array('sftp_client', $definitions)) {
      $container->removeDefinition('lp_programs.lot_manager');
    }
    if (!in_array('google_maps_services.api.endpoint_manager', $definitions)) {

      $container->removeDefinition('lp_programs.near_by_search');
    }
  }
}
