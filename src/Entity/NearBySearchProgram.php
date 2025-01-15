<?php

namespace Drupal\lp_programs\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines the Near by search program entity.
 *
 * @ConfigEntityType(
 *   id = "near_by_search_program",
 *   label = @Translation("Near by search program"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\lp_programs\NearBySearchProgramListBuilder",
 *     "form" = {
 *       "add" = "Drupal\lp_programs\Form\NearBySearchProgramForm",
 *       "edit" = "Drupal\lp_programs\Form\NearBySearchProgramForm",
 *       "delete" = "Drupal\lp_programs\Form\NearBySearchProgramDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\lp_programs\NearBySearchProgramHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "near_by_search_program",
 *   admin_permission = "administer site configuration",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *
 *   config_export = {
 *     "id",
 *     "label",
 *     "google_type",
 *     "uuid",
 *   },
 *   links = {
 *     "canonical" = "/admin/structure/near_by_search_program/{near_by_search_program}",
 *     "add-form" = "/admin/structure/near_by_search_program/add",
 *     "edit-form" = "/admin/structure/near_by_search_program/{near_by_search_program}/edit",
 *     "delete-form" = "/admin/structure/near_by_search_program/{near_by_search_program}/delete",
 *     "collection" = "/admin/structure/near_by_search_program"
 *   }
 * )
 */
class NearBySearchProgram extends ConfigEntityBase implements NearBySearchProgramInterface {

  /**
   * The Near by search program ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The Near by search program label.
   *
   * @var string
   */
  protected $label;

  public function getId(){
    return $this->id;
  }

  public function getLabel(){
    return $this->label;
  }

  protected $google_type;
  public function getGoogleType(){
    return $this->google_type;
  }

}
