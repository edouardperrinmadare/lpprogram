<?php
/**
 * @file
 * Contains \Drupal\lp_programs\Form\SearchProgramForm.
 */

namespace Drupal\lp_programs\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SearchProgramForm extends FormBase {

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Class constructor.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'search_program_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    /** @var  $terms \Drupal\taxonomy\Entity\Term[] */
    $terms = $this->entityTypeManager->getStorage('taxonomy_term')
      ->loadTree('lot_type');
    $options = [];
    foreach ($terms as $term) {

      $options[$term->tid] = $term->name;
    }

    $form['#attributes'] = ['class' => ['hbspt-form']];

    $form['location'] = [
      '#type' => 'textfield',
      '#title' => t('Où voulez vous vivre ?'),
      '#required' => FALSE,
      '#placeholder' => t('Recherchez une ville ou une région'),
    ];

    $form['area'] = [
      '#type' => 'select',
      '#title' => t('Nombre de pièces'),
      '#options' => $options,
      '#empty_option' => t('Tout'),
    ];

    $form['position'] = [
      '#type' => 'hidden',
      '#title' => t('Où voulez vous vivre ?'),
      '#required' => FALSE,
    ];

    $form['actions'] = [
      '#type' => 'actions',
      '#attributes' => ['class' => ['hs_submit']],
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Je recherche'),
      '#button_type' => 'primary',
      '#attributes' => ['class' => ['hs-button']],
    ];

    $form['#attached']['library'][] = 'lp_programs/lp_programs.gmap';
    $form['#attached']['library'][] = 'lp_programs/lp_programs.searchform';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_data = [
      'place' => $form_state->getValue('location'),
      'field_geol[source_configuration][origin_address]' => $form_state->getValue('position'),
      'sort_bef_combine' => 'proximite_ASC',
    ];
    if (!empty($form_state->getValue('area'))) {
      $form_data['field_program_types_target_id['.$form_state->getValue('area').']'] = $form_state->getValue('area');
    }

    $form_state->setRedirect('view.programmes_madare.page_1', $form_data);
  }

}
