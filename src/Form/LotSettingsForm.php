<?php

namespace Drupal\lp_programs\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 */
class LotSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'lp_programs_lot_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'lp_programs.lot_settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('lp_programs.lot_settings');
    $cron_config = $config->get('cron') ?? [];
    $form['cron'] = [
      '#title' => t('Cron settings'),
      '#type' => 'details',
      '#open' => TRUE,
      '#tree' => TRUE,
    ];
    $form['cron']['enable_cron'] = [
      '#title' => t('Enable cron task'),
      '#description' => t('Enable cron task to import lot.'),
      '#type' => 'checkbox',
      '#default_value' => $cron_config['enable_cron'] ?? TRUE,
    ];
    $form['cron']['import_mode'] = [
      '#title' => t('Import mode'),
      '#options' => [
        'date_file' => t('Last file modified'),
        'all_files' => t('Add all xml files found inside queue'),
      ],
      '#states' => [
        'visible' => [
          'input[name="cron[enable_cron]"]' => ['checked' => TRUE],
        ],
        'required' => [
          'input[name="cron[enable_cron]"]' => ['checked' => TRUE],
        ],
      ],
      '#description' => t('Choose how to select files to import'),
      '#type' => 'select',
      '#default_value' => $cron_config['import_mode'] ?? 'date_file',
    ];

    $form['cron']['hours'] = [
      '#title' => t('Hours recurrence'),
      '#description' => t('Number of hours to wait for update'),
      '#type' => 'number',
      '#states' => [
        'visible' => [
          'input[name="cron[enable_cron]"]' => ['checked' => TRUE],
        ],
        'required' => [
          'input[name="cron[enable_cron]"]' => ['checked' => TRUE],
        ],
      ],
      '#default_value' => $cron_config['hours'] ?? 7,
    ];
    $form['sftp'] = [
      '#title' => t('SFTP settings'),
      '#type' => 'details',
      '#open' => TRUE,
      '#tree' => TRUE,
    ];
    $sftp_config = $config->get('sftp') ?? [];
    $form['sftp']['path'] = [
      '#type' => 'textfield',
      '#title' => t('Files path'),
      '#default_value' => $sftp_config['path'] ?? '',
      '#description' => $this->t('Sftp file path'),
    ];
    $form['sftp']['destination'] = [
      '#type' => 'textfield',
      '#title' => t('Files path destination'),
      '#default_value' => $sftp_config['destination'] ?? '',
      '#description' => $this->t('File path destination'),
    ];
    $batch_config = $config->get('batch') ?? [];
    $form['batch'] = [
      '#title' => t('Batch manual settings'),
      '#type' => 'details',
      '#open' => TRUE,
      '#tree' => TRUE,
    ];
    $form['batch']['limit'] = [
      '#type' => 'number',
      '#min' => 1,
      '#title' => t('Batch limit'),
      '#default_value' => $batch_config['limit'] ?? 100,
      '#description' => $this->t('How many program to process per batch'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Retrieve the configuration
    $this->configFactory->getEditable('lp_programs.lot_settings')
      ->set('cron', $form_state->getValue('cron'))
      ->set('sftp', $form_state->getValue('sftp'))
      ->set('batch', $form_state->getValue('batch'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
