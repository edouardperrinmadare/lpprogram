<?php

namespace Drupal\lp_slider_program\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining Slider program entities.
 *
 * @ingroup lp_slider_program
 */
interface SliderProgramInterface extends ContentEntityInterface, EntityChangedInterface, EntityPublishedInterface, EntityOwnerInterface {

  /**
   * Add get/set methods for your configuration properties here.
   */

  /**
   * Gets the Slider program name.
   *
   * @return string
   *   Name of the Slider program.
   */
  public function getName();

  /**
   * Sets the Slider program name.
   *
   * @param string $name
   *   The Slider program name.
   *
   * @return \Drupal\lp_slider_program\Entity\SliderProgramInterface
   *   The called Slider program entity.
   */
  public function setName($name);

  /**
   * Gets the Slider program creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Slider program.
   */
  public function getCreatedTime();

  /**
   * Sets the Slider program creation timestamp.
   *
   * @param int $timestamp
   *   The Slider program creation timestamp.
   *
   * @return \Drupal\lp_slider_program\Entity\SliderProgramInterface
   *   The called Slider program entity.
   */
  public function setCreatedTime($timestamp);

}
