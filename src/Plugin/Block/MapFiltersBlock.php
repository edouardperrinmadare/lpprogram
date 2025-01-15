<?php

namespace Drupal\lp_programs\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a block for filter programs map.
 *
 * @Block(
 *   id = "lp_programs_map_filters_block",
 *   admin_label = @Translation("Program Map Filters Block"),
 *   category = @Translation("LP Programs"),
 * )
 */
class MapFiltersBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#theme' => 'lp_programs_map_filters_block',
    ];
  }

}
