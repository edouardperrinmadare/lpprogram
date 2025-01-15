<?php

namespace Drupal\lp_programs\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\lp_programs\Services\LotManagerService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form import cotisations.
 */
class CountPoiForm extends FormBase {
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'lp_programs.count_poi';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Count POI'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $operations[] = [
      '\Drupal\lp_programs\Batch\BatchCountPoi::countPoi',
      [],
    ];
    // 4. Create the batch.
    $batch = [
      'title' => t('Count POI'),
      'operations' => $operations,
      'finished' => '\Drupal\lp_programs\Batch\BatchCountPoi::countPoiFinished',
    ];

    batch_set($batch);
    }


}
