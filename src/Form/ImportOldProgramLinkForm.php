<?php

namespace Drupal\lp_programs\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\lp_programs\Services\LotManagerService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form import cotisations.
 */
class ImportOldProgramLinkForm extends FormBase {


  protected $lotManagerService;

  /**
   * Constructs a \Drupal\system\ConfigFormBase object.
   *
   * @param \Drupal\Core\State\StateInterface $state
   */
  public function __construct(LotManagerService $lot_manager_service) {
    $this->lotManagerService = $lot_manager_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('lp_programs.lot_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'lp_programs.import_lot';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = [
      '#attributes' => ['enctype' => 'multipart/form-data'],
    ];

    $form['file_upload_details'] = [
      '#markup' => t('<b>The File</b>'),
    ];

    $validators = [
      'file_validate_extensions' => ['csv'],
    ];
    $form['csv_file'] = [
      '#type' => 'managed_file',
      '#name' => 'csv_program_link',
      '#title' => t('File'),
      '#size' => 20,
      '#description' => t('CSV format only'),
      '#upload_validators' => $validators,
      '#upload_location' => 'private://import/program-link',
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $file = $form_state->getValue('csv_file');
    $complete_form = $form_state->getCompleteForm();
    if (!empty($complete_form["csv_file"]["file_" . $file[0]]["filename"]["#file"])) {
      $file_uri = \Drupal::service('file_system')
        ->realpath($complete_form["csv_file"]["file_" . $file[0]]["filename"]["#file"]->getFileUri());
    }
    // Display success message.;
    // Clean storage inside form state or we get exception of simple xml
    // serialization.
    $form_state->setStorage([]);
    if (!empty($file_uri)) {
      $operations[] = [
        '\Drupal\lp_programs\Batch\BatchProgramLinkImport::importProgramLink',
        [
          $file_uri,
        ],
      ];

      // 4. Create the batch.
      $batch = [
        'title' => t('Import programs'),
        'operations' => $operations,
        'finished' => '\Drupal\lp_programs\Batch\BatchLotImport::importLotFinished',
      ];
      batch_set($batch);
    }
  }

}
