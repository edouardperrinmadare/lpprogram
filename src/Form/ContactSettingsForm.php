<?php

namespace Drupal\lp_programs\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 */
class ContactSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'lp_programs_contact_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'lp_programs.contact_settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('lp_programs.contact_settings');
    $form['mail'] = [
      '#title' => t('Mail settings'),
      '#type' => 'details',
      '#open' => TRUE,
      '#tree' => TRUE,
    ];
    $mail_settings = $config->get('mail');
    $form['mail']['to_default_mail'] = [
      '#title' => t('Destinataire par default'),
      '#description' => t("Si coché le mail sera envoyé au mail par default du site."),
      '#type' => 'checkbox',
      '#default_value' => $mail_settings['to_default_mail'] ?? TRUE,
    ];
    $form['mail']['to'] = [
      '#title' => t('Destinataire'),
      '#description' => t('Destinataire des candidatures, un mail par ligne.'),
      '#type' => 'textarea',
      '#default_value' => $mail_settings['to'] ?? '',
      '#states' => [
        'visible' => [
          ':input[name="mail[to_default_mail]"]' => ['unchecked' => TRUE],
        ],
      ],
    ];

    $form['mail']['from_default_mail'] = [
      '#title' => t('Expéditeur par default'),
      '#description' => t("Si coché le mail sera envoyé avec le mail par default du site."),
      '#type' => 'checkbox',
      '#default_value' => $mail_settings['from_default_mail'] ?? TRUE,
    ];
    $form['mail']['from'] = [
      '#title' => t('Expéditeur'),
      '#description' => t('Expéditeur des candidatures, un mail par ligne.'),
      '#type' => 'textfield',
      '#default_value' => $mail_settings['from'] ?? '',
      '#states' => [
        'visible' => [
          ':input[name="mail[from_default_mail]"]' => ['unchecked' => TRUE],
        ],
      ],
    ];
    $form['mail']['from_name'] = [
      '#title' => t('Expéditeur nom'),
      '#description' => t('Nom de l\'expéditeur des candidatures, un mail par ligne.'),
      '#type' => 'textfield',
      '#default_value' => $mail_settings['from_name'] ?? '',
      '#states' => [
        'visible' => [
          ':input[name="mail[from_default_mail]"]' => ['unchecked' => TRUE],
        ],
      ],
    ];

    $form['mail']['body'] = [
      '#title' => t('Text du mail'),
      '#description' => t('Corps du mail de candidature.'),
      '#type' => 'textarea',
      '#default_value' => $mail_settings['body'] ?? '',
    ];
    $form['mail']['token'] = [
      '#theme' => 'token_tree_link',
      '#token_types' => ['node', 'lp_programs'],
      '#show_restricted' => TRUE,
      '#global_types' => TRUE,
      '#weight' => 90,
    ];
    $form['modal'] = [
      '#title' => t('Modal settings'),
      '#type' => 'details',
      '#open' => TRUE,
      '#tree' => TRUE,
    ];
    $modal_settings = $config->get('modal');

    $form['modal']['title_confirm'] = [
      '#title' => t('Titre mail envoyé'),
      '#description' => t('Titre de la modal affichée après que le mail se soit envoyé.'),
      '#type' => 'textarea',
      '#default_value' => $modal_settings['title_confirm'] ?? '',
    ];
    $form['modal']['text_confirm'] = [
      '#title' => t('Text mail envoyé'),
      '#description' => t('Text affiché dans la modal après que le mail se soit envoyé.'),
      '#type' => 'textarea',
      '#default_value' => $modal_settings['text_confirm'] ?? '',
    ];

    $form['modal']['title_error'] = [
      '#title' => t('Titre mail erreur'),
      '#description' => t('Titre de la modal affichée après que le mail se soit envoyé.'),
      '#type' => 'textarea',
      '#default_value' => $modal_settings['title_error'] ?? '',
    ];
    $form['modal']['text_error'] = [
      '#title' => t('Text mail erreur'),
      '#description' => t("Text affiché dans la modal après qu'il y ait eu une erreur lors de l'envois de l'email."),
      '#type' => 'textarea',
      '#default_value' => $modal_settings['text_error'] ?? '',
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Retrieve the configuration
    $this->configFactory->getEditable('lp_programs.contact_settings')
      ->set('mail', $form_state->getValue('mail'))
      ->set('modal', $form_state->getValue('modal'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
