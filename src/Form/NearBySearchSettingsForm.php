<?php

namespace Drupal\lp_programs\Form;

use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 */
class NearBySearchSettingsForm extends ConfigFormBase {

  protected $entityTypeManager;

  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($config_factory);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'lp_programs_near_by_search_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'lp_programs.near_by_search.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('lp_programs.near_by_search.settings');
    $config_entities = $this->entityTypeManager->getStorage('near_by_search_program')
      ->loadMultiple();
    $count = 0;
    foreach ($config_entities as $config_entity) {
      $count += count($config_entity->get('google_type'));
    }

    $form['info'] = [
      '#markup' => t('Currently %count google type set', [
        '%count' => $count,
      ]),
    ];

    $form['enable_cron'] = [
      '#title' => t('Enable cron task'),
      '#description' => t('Enable cron task to crawl POI.'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('enable_cron') ?? TRUE,
    ];

    $form['days'] = [
      '#title' => t('Days'),
      '#description' => t('Number of days to wait for update'),
      '#type' => 'number',
      '#states' => [
        'visible' => [
          'input[name="enable_cron"]' => ['checked' => TRUE],
        ],
        'required' => [
          'input[name="enable_cron"]' => ['checked' => TRUE],
        ],
      ],
      '#default_value' => $config->get('days') ?? 7,
    ];
    $form['enable_presave'] = [
      '#title' => t('Program saved'),
      '#description' => t('Get POI of program after is saved.'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('enable_presave') ?? TRUE,
    ];
    $form['enable_filter_field'] = [
      '#title' => t('Filter field'),
      '#description' => t('Check this if you want to reduce data imported inside JSON field of program.'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('enable_filter_field') ?? TRUE,
    ];
    $url = Url::fromUri('https://developers.google.com/maps/documentation/places/web-service/details#Place');
    $link = Link::fromTextAndUrl(t('Google place field'), $url);
    $link = $link->toRenderable();
    // If you need some attributes.
    $form['filter_field'] = [
      '#title' => t('Field enable'),
      '#description' => t('Insert all field you need to keep in database. Set one field per line. You can find name of fields on %link', ['%link' => render($link)]),
      '#type' => 'textarea',
      '#states' => [
        'visible' => [
          'input[name="enable_filter_field"]' => ['checked' => TRUE],
        ],
        'required' => [
          'input[name="enable_filter_field"]' => ['checked' => TRUE],
        ],
      ],
      '#default_value' => $config->get('filter_field') ?? '',
    ];
    $form['max_poi'] = [
      '#title' => t('Maximum of POI'),
      '#description' => t('Set the max number of POI per config entities. Leave empty for no limit.'),
      '#type' => 'textfield',
      '#default_value' => $config->get('max_poi') ?? '',
    ];
    $form['mode'] = [
      '#title' => t('Mode'),
      '#description' => t('Mode to get POI'),
      '#type' => 'select',
      '#required' => TRUE,
      '#options' => ['radius' => t('Radius'), 'rank_by' => t('Rank by')],
      '#default_value' => $config->get('mode') ?? 'radius',
    ];
    $form['rank_by'] = [
      '#title' => t('Rank by'),
      '#description' => t('Distance radius to crawl the POI'),
      '#type' => 'select',
      '#options' => [
        'prominence' => t('Prominence'),
        'distance' => t('Distance'),
      ],
      '#default_value' => $config->get('rank_by') ?? 'distance',
      '#states' => [
        'visible' => [
          'select[name="mode"]' => ['value' => 'rank_by'],
        ],
        'required' => [
          'select[name="mode"]' => ['value' => 'rank_by'],
        ],
      ],
    ];
    $form['radius'] = [
      '#title' => t('Radius'),
      '#description' => t('Distance radius to crawl the POI'),
      '#type' => 'number',
      '#default_value' => $config->get('radius') ?? 5000,
      '#states' => [
        'visible' => [
          [
            'select[name="rank_by"]' => ['value' => 'prominence'],
          ],
          [
            'select[name="mode"]' => ['value' => 'radius'],
          ],
        ],
        'required' => [
          [
            'select[name="rank_by"]' => ['value' => 'prominence'],
          ],
          [
            'select[name="mode"]' => ['value' => 'radius'],
          ],
        ],
      ],
    ];

    $direction = $config->get('directions') ?? [];
    $form['directions'] = [
      '#title' => t('Directions'),
      '#type' => 'details',
      '#open' => TRUE,
      '#tree' => TRUE,
    ];
    $form['directions']['alternatives'] = [
      '#title' => t('Alternatives'),
      '#description' => t('If set to true, specifies that the Directions service may provide more than one route alternative in the response.'),
      '#type' => 'checkbox',
      '#default_value' => $direction['alternatives'] ?? FALSE,
    ];
    $form['directions']['mode'] = [
      '#title' => t('Mode'),
      '#description' => t('Mode to get direction'),
      '#type' => 'select',
      '#required' => TRUE,
      '#options' => [
        'driving' => t('Driving'),
        'walking' => t('Walking'),
        'bicycling' => t('Bicycling'),
        'transit' => t('Transit'),
      ],
      '#default_value' => $direction['mode'] ?? 'walking',
    ];

    $form['directions']['avoid'] = [
      '#title' => t('Avoid'),
      '#description' => t('Indicates that the calculated route(s) should avoid the indicated features.'),
      '#type' => 'select',
      '#multiple' => TRUE,
      '#options' => [
        'tolls' => t('Tolls'),
        'highways' => t('Highways'),
        'ferries' => t('Ferries'),
        'indoor' => t('Indoor'),
      ],
      '#default_value' => $direction['avoid'] ?? [],
    ];
    $form['directions']['enable_filter_field'] = [
      '#title' => t('Filter field'),
      '#description' => t('Check this if you want to reduce data imported inside JSON field of program.'),
      '#type' => 'checkbox',
      '#default_value' => $direction['enable_filter_field'] ?? TRUE,
    ];
    $form['directions']['tabs_filter'] = [
      '#type' => 'horizontal_tabs',
      '#default_tab' => 'edit-rendez-vous-details',
      '#states' => [
        'visible' => [
          'input[name="directions[enable_filter_field]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $url = Url::fromUri('https://developers.google.com/maps/documentation/directions/get-directions#DirectionsResponse');
    $link = Link::fromTextAndUrl(t('Google directions field'), $url);
    $link = $link->toRenderable();
    $this->generateFilterField(   $form['directions'], 'directions', $this->t('Directions'), $link);
    $url = Url::fromUri('https://developers.google.com/maps/documentation/directions/get-directions#DirectionsRoute');
    $link = Link::fromTextAndUrl(t('Google directions route field'), $url);
    $link = $link->toRenderable();
    $this->generateFilterField(   $form['directions'], 'directions_route', $this->t('Directions route'), $link);
    $url = Url::fromUri('https://developers.google.com/maps/documentation/directions/get-directions#DirectionsLeg');
    $link = Link::fromTextAndUrl(t('Google directions route legs field'), $url);
    $link = $link->toRenderable();
    $this->generateFilterField(   $form['directions'], 'directions_route_legs', $this->t('Directions route legs'), $link);
    return parent::buildForm($form, $form_state);
  }

  private function generateFilterField(&$element, $element_name, $label_detail, array $link_help) {

    $config = $this->config('lp_programs.near_by_search.settings');
    $direction = $config->get('directions') ?? [];
    $element[$element_name] = [
      '#type' => 'details',
      '#title' => $label_detail,
      '#group' => 'tabs_filter',
      '#states' => [
        'visible' => [
          'input[name="directions[enable_filter_field]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $element[$element_name]['field']= [
      '#title' => t('Field enable'),
      '#description' => t('Insert all field you need to keep in database. Set one field per line. You can find name of fields on %link', ['%link' => render($link_help)]),
      '#type' => 'textarea',
      '#states' => [
        'visible' => [
          'input[name="directions[enable_filter_field]"]' => ['checked' => TRUE],
        ],
      ],
      '#default_value' => $direction[$element_name]['field'] ?? '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Retrieve the configuration
    $this->configFactory->getEditable('lp_programs.near_by_search.settings')
      ->set('radius', $form_state->getValue('radius'))
      ->set('enable_cron', $form_state->getValue('enable_cron'))
      ->set('days', $form_state->getValue('days'))
      ->set('enable_presave', $form_state->getValue('enable_presave'))
      ->set('enable_filter_field', $form_state->getValue('enable_filter_field'))
      ->set('filter_field', $form_state->getValue('filter_field'))
      ->set('max_poi', $form_state->getValue('max_poi'))
      ->set('mode', $form_state->getValue('mode'))
      ->set('rank_by', $form_state->getValue('rank_by'))
      ->set('directions', $form_state->getValue('directions'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
