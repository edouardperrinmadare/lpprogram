<?php

namespace Drupal\lp_slider_program\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class SliderProgramTypeForm.
 */
class SliderProgramTypeForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $slider_program_type = $this->entity;
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $slider_program_type->label(),
      '#description' => $this->t("Label for the Slider program type."),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $slider_program_type->id(),
      '#machine_name' => [
        'exists' => '\Drupal\lp_slider_program\Entity\SliderProgramType::load',
      ],
      '#disabled' => !$slider_program_type->isNew(),
    ];

    /* You will need additional form elements for your custom properties. */

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $slider_program_type = $this->entity;
    $status = $slider_program_type->save();

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addMessage($this->t('Created the %label Slider program type.', [
          '%label' => $slider_program_type->label(),
        ]));
        break;

      default:
        $this->messenger()->addMessage($this->t('Saved the %label Slider program type.', [
          '%label' => $slider_program_type->label(),
        ]));
    }
    $form_state->setRedirectUrl($slider_program_type->toUrl('collection'));
  }

}
