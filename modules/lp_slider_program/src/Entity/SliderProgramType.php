<?php

namespace Drupal\lp_slider_program\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;

/**
 * Defines the Slider program type entity.
 *
 * @ConfigEntityType(
 *   id = "slider_program_type",
 *   label = @Translation("Slider program type"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\lp_slider_program\SliderProgramTypeListBuilder",
 *     "form" = {
 *       "add" = "Drupal\lp_slider_program\Form\SliderProgramTypeForm",
 *       "edit" = "Drupal\lp_slider_program\Form\SliderProgramTypeForm",
 *       "delete" = "Drupal\lp_slider_program\Form\SliderProgramTypeDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\lp_slider_program\SliderProgramTypeHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "slider_program_type",
 *   admin_permission = "administer site configuration",
 *   bundle_of = "slider_program",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *   },
 *   links = {
 *     "canonical" = "/admin/structure/slider_program_type/{slider_program_type}",
 *     "add-form" = "/admin/structure/slider_program_type/add",
 *     "edit-form" = "/admin/structure/slider_program_type/{slider_program_type}/edit",
 *     "delete-form" = "/admin/structure/slider_program_type/{slider_program_type}/delete",
 *     "collection" = "/admin/structure/slider_program_type"
 *   }
 * )
 */
class SliderProgramType extends ConfigEntityBundleBase implements SliderProgramTypeInterface {

  /**
   * The Slider program type ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The Slider program type label.
   *
   * @var string
   */
  protected $label;

}
