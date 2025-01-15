<?php

namespace Drupal\lp_programs\Services;

/**
 * Moodle service interface.
 * @SuppressWarnings(PHPMD.CamelCaseParameterName)
 * @SuppressWarnings(PHPMD.CamelCaseVariableName)
 */
interface ProgramLotServiceInterface{

  /**
   * Return the node bundle name.
   *
   * @return string
   */
  public function getNodeBundle();

  /**
   * Return the field name where external id is stored.
   *
   * @return string
   */
  public function getFieldNameExternalId();

  /**
   * Return mapping between xml and drupal field.
   *
   * @return array
   */
  public function getMapping();
}
