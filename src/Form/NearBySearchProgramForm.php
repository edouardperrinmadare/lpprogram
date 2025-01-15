<?php

namespace Drupal\lp_programs\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class NearBySearchProgramForm.
 */
class NearBySearchProgramForm extends EntityForm {

  public static function getGooglType() {
    return [
      'accounting',
      'airport',
      'amusement_park',
      'aquarium',
      'art_gallery',
      'atm',
      'bakery',
      'bank',
      'bar',
      'beauty_salon',
      'bicycle_store',
      'book_store',
      'bowling_alley',
      'bus_station',
      'cafe',
      'campground',
      'car_dealer',
      'car_rental',
      'car_repair',
      'car_wash',
      'casino',
      'cemetery',
      'church',
      'city_hall',
      'clothing_store',
      'convenience_store',
      'courthouse',
      'dentist',
      'department_store',
      'doctor',
      'drugstore',
      'electrician',
      'electronics_store',
      'embassy',
      'fire_station',
      'florist',
      'funeral_home',
      'furniture_store',
      'gas_station',
      'gym',
      'hair_care',
      'hardware_store',
      'hindu_temple',
      'home_goods_store',
      'hospital',
      'insurance_agency',
      'jewelry_store',
      'laundry',
      'lawyer',
      'library',
      'light_rail_station',
      'liquor_store',
      'local_government_office',
      'locksmith',
      'lodging',
      'meal_delivery',
      'meal_takeaway',
      'mosque',
      'movie_rental',
      'movie_theater',
      'moving_company',
      'museum',
      'night_club',
      'painter',
      'park',
      'parking',
      'pet_store',
      'pharmacy',
      'physiotherapist',
      'plumber',
      'police',
      'post_office',
      'primary_school',
      'real_estate_agency',
      'restaurant',
      'roofing_contractor',
      'rv_park',
      'school',
      'secondary_school',
      'shoe_store',
      'shopping_mall',
      'spa',
      'stadium',
      'storage',
      'store',
      'subway_station',
      'supermarket',
      'synagogue',
      'taxi_stand',
      'tourist_attraction',
      'train_station',
      'transit_station',
      'travel_agency',
      'university',
      'veterinary_care',
      'zoo',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public
  function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $near_by_search_program = $this->entity;
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $near_by_search_program->label(),
      '#description' => $this->t("Label for the Near by search program."),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $near_by_search_program->id(),
      '#machine_name' => [
        'exists' => '\Drupal\lp_programs\Entity\NearBySearchProgram::load',
      ],
      '#disabled' => !$near_by_search_program->isNew(),
    ];
    $google_type = $this::getGooglType();
    $form['google_type'] = [
      '#type' => 'select',
      '#size' => 30,
      '#title' => $this->t('Label'),
      '#multiple' => TRUE,
      '#options' => array_combine($google_type, $google_type),
      '#default_value' => $near_by_search_program->getGoogleType(),
      '#description' => $this->t("Label for the Near by search program."),
      '#required' => TRUE,
    ];
    /* You will need additional form elements for your custom properties. */

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public
  function save(array $form, FormStateInterface $form_state) {
    $near_by_search_program = $this->entity;
    $status = $near_by_search_program->save();

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()
          ->addMessage($this->t('Created the %label Near by search program.', [
            '%label' => $near_by_search_program->label(),
          ]));
        break;

      default:
        $this->messenger()
          ->addMessage($this->t('Saved the %label Near by search program.', [
            '%label' => $near_by_search_program->label(),
          ]));
    }
    $form_state->setRedirectUrl($near_by_search_program->toUrl('collection'));
  }

}
