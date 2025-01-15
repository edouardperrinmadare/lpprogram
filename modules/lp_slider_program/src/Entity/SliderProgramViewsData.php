<?php

namespace Drupal\lp_slider_program\Entity;

use Drupal\views\EntityViewsData;

/**
 * Provides Views data for Slider program entities.
 */
class SliderProgramViewsData extends EntityViewsData {

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    // Additional information for Views integration, such as table joins, can be
    // put here.
    return $data;
  }

}
