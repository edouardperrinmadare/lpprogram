<?php

namespace Drupal\lp_slider_program;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;

/**
 * Defines a class to build a listing of Slider program entities.
 *
 * @ingroup lp_slider_program
 */
class SliderProgramListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('Slider program ID');
    $header['name'] = $this->t('Name');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var \Drupal\lp_slider_program\Entity\SliderProgram $entity */
    $row['id'] = $entity->id();
    $row['name'] = Link::createFromRoute(
      $entity->label(),
      'entity.slider_program.edit_form',
      ['slider_program' => $entity->id()]
    );
    return $row + parent::buildRow($entity);
  }

}
