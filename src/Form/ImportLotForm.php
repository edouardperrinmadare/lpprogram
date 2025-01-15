<?php

namespace Drupal\lp_programs\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\lp_programs\Services\LotManagerService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form import cotisations.
 */
class ImportLotForm extends FormBase {


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
    $path_files = $this->lotManagerService->getXmlSftpFiles();
    $files_xml = $this->lotManagerService->openXmlFilePath($path_files);

    // Create the operations array for the batch.
    $files_storage=  $options = [];
    foreach ($files_xml as $file_xml) {
      $files_storage[$file_xml['path']]=$file_xml;
      $options[$file_xml['path']] = (string) $file_xml['xml']->ENTETE->PROMOTEUR . (string) $file_xml['xml']->ENTETE->DATE;
    }
    $form_state->setStorage([
      'files' => $path_files,
      'files_xml' => $files_storage,
    ]);
    $form['files'] = [
      '#type' => 'radios',
      '#title' => t('XML file to import'),
      '#options' => $options,
      '#required' => TRUE,
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
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $storage = $form_state->getStorage();
    if (empty($storage['files_xml'])) {
      $form_state->setError($form, t('Sftp return no files'));
      return FALSE;
    }
    $file_path = $form_state->getValue('files');
    if (empty($storage['files_xml'][$file_path])) {
      $form_state->setError($form, t('Sftp return no files'));
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $storage = $form_state->getStorage();
    // Create the operations array for the batch.
    $operations = [];
    $file_path = $form_state->getValue('files');
    if (!empty($storage['files_xml'][$file_path])) {
      $program_array = $this->lotManagerService->extractProgramXmlToArray($storage['files_xml'][$file_path]['xml']);
      $program_array = $this->lotManagerService->transformProgramXmlToArray($program_array);
    }
    // Clean storage inside form state or we get exception of simple xml
    // serialization.
    $form_state->setStorage([]);
    if (!empty($program_array)) {
      $operations[] = [
        '\Drupal\lp_programs\Batch\BatchLotImport::importLot',
        [
          $program_array,
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
