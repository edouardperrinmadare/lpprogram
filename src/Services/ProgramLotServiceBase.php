<?php

namespace Drupal\lp_programs\Services;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Locale\CountryManager;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\field\FieldConfigInterface;
use Drupal\node\NodeInterface;

/**
 * Manage common service method for program and lot.
 *
 * @SuppressWarnings(PHPMD.CamelCaseParameterName)
 * @SuppressWarnings(PHPMD.CamelCaseVariableName)
 */
abstract class ProgramLotServiceBase implements ProgramLotServiceInterface {

  /**
   * An instance of entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   *
   */
  use LoggerChannelTrait;

  /**
   * {@inheritDoc}
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * @param array $data_lot
   *
   * @return array
   */
  public function buildDataMapping(array $data_lot) {
    $data_mapped = [];
    $lot_mapping = $this->getMapping();
    foreach ($data_lot as $data_key => $data_value) {
      if (empty($lot_mapping[$data_key])) {
        continue;
      }
      if (is_array($lot_mapping[$data_key])) {
        $data_mapped[key($lot_mapping[$data_key])][current($lot_mapping[$data_key])] = $data_value;
        continue;
      }
      $data_mapped[$lot_mapping[$data_key]] = $data_value;
    }
    return $data_mapped;
  }

  /**
   * @param array $id_external
   * @param $field_name
   * @param bool $load_node
   *
   * @return array|\Drupal\Core\Entity\EntityInterface[]|int
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getEntityByExternalId(array $id_external, $load_node = TRUE) {
    $query = $this->entityTypeManager->getStorage('node')->getQuery();
    $query->condition('type', $this->getNodeBundle());
    $query->condition($this->getFieldNameExternalId(), $id_external, 'IN');
    $node_ids = $query->execute();
    if ($load_node && !empty($node_ids)) {
      return $this->entityTypeManager->getStorage('node')
        ->loadMultiple($node_ids);
    }
    return $node_ids;
  }

  public function createEntity() {
    $status = 1;

    if ($this->getNodeBundle() == 'program') {
      $status = 0;
    }

    return $this->entityTypeManager->getStorage('node')->create([
      'type' => $this->getNodeBundle(),
      'status' => $status,
    ]);
  }

  /**
   * Clean referenced entities.
   *
   * Set field type to entity_reference_revisions and entity_reference to clean
   * per entity field type.
   * Set entity_reference_revisions to empty and field_names fill with the field
   * name to clean.
   *
   * @param \Drupal\node\NodeInterface $entity
   *   The entity to reset field entity ref.
   * @param array|string[] $field_types
   *   An array of field type to clean ie:
   *   'entity_reference_revisions','entity_reference'.
   * @param array $field_names_clean
   *   An array of field name to clean.
   *
   * @return bool
   */
  public function resetReferencedEntities(NodeInterface $entity, array $field_types = [
    'entity_reference_revisions',
    'entity_reference',
  ], array                                              $field_names_clean = []) {

    $node_fields = $entity->getFieldDefinitions();
    foreach ($node_fields as $field_name => $field_def) {
      if (!$field_def instanceof FieldConfigInterface) {
        continue;
      }
      $field_type = $field_def->get('field_type');
      if (in_array($field_type, $field_types, TRUE) || in_array($field_name, $field_names_clean, TRUE)) {
        $ref_entities = $entity->$field_name->referencedEntities();
        foreach ($ref_entities as $ref_entity) {
          if ($ref_entity instanceof EntityInterface) {

            $ref_entity->delete();
          }
        }
      }
    }
    $entity->set($field_name, NULL);
    return TRUE;
  }

  /**
   * @param \Drupal\node\NodeInterface $entity
   * @param $field_name
   * @param array $target_ids
   *
   * @return bool
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function removeReferencedEntities(NodeInterface $entity, $field_name, array $target_ids = []) {

    if (!$entity->hasField($field_name)) {
      return FALSE;
    }
    $field_ref = $entity->get($field_name)->getValue();
    $ref_entities = $entity->$field_name->referencedEntities();
    foreach ($ref_entities as $ref_entity) {
      if (!empty($target_ids) && (!$ref_entity instanceof EntityInterface || !in_array($ref_entity->id(), $target_ids, TRUE))) {
        continue;
      }
      foreach ($field_ref as $key_ref => $ref) {
        if ($ref['target_id'] === $ref_entity->id()) {
          unset($field_ref[$key_ref]);
        }
      }
      $ref_entity->delete();
    }
    $entity->set($field_name, $field_ref);
    return TRUE;
  }

  /**
   * Save image on field entity.
   *
   * @param \Drupal\node\NodeInterface $entity
   *   The entity to set the field.
   * @param string $field_name_image
   *   The name of field to set value.
   * @param string $external_url
   * @param string $alt
   * @param string $title
   *
   * @return false|void
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function saveFile(NodeInterface $entity, $field_destination, $external_url, $alt = '', $title = '') {
    $node_fields = $entity->getFieldDefinitions();

    // Get directory path.
    foreach ($node_fields as $field_name => $field_def) {
      if ($field_name !== $field_destination) {
        continue;
      }
      $settings = $field_def->get('settings');
      $directory = $settings['file_directory'];
      $directory = $this->token->replace($directory, [$entity->getEntityTypeId() => $entity], [
        'clear' => TRUE,
        'sanitize' => TRUE,
      ]);
      // Get uri scheme for directory.
      $field_storage = $field_def->getFieldStorageDefinition();
      $settings_storage = $field_storage->get('settings');
      $uri_scheme = $settings_storage['uri_scheme'] ?? 'public';
      $directory = $uri_scheme . '://' . $directory;
    }
    if (empty($directory)) {
      $this->getLogger('lne_moodle.webinar')
        ->error('Field @field_name directory is empty', ['@field_name' => $field_destination]);
    }
    $file = system_retrieve_file($external_url, $directory . '/' . basename($external_url), TRUE);

    if (empty($file)) {

      $this->getLogger('lne_moodle.webinar')
        ->error("Error on saving file entity source: @path, destination: @dest", [
          '@path' => $external_url,
          '@dest' => $directory,
        ]);
      return FALSE;
    }
    $fileInfo = [
      'target_id' => $file->id(),
      'alt' => $alt,
      'title' => $title,
    ];
    // Add file on the entity.
    $entity->set($field_destination, $fileInfo);
  }

  /**
   * Create a paragraph.
   *
   * @param string $type_paragraph
   *   The paragraph type name.
   * @param array $field_data
   *
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function createParagraph($type_paragraph, array $field_data) {
    $paragraph_speaker = $this->entityTypeManager->getStorage('paragraph')
      ->create([
        'type' => $type_paragraph,
      ]);
    foreach ($field_data as $field_name => $field_value) {
      $paragraph_speaker->set($field_name, $field_value);
    }
    $paragraph_speaker->save();

    return [
      'target_id' => $paragraph_speaker->id(),
      'target_revision_id' => $paragraph_speaker->getRevisionId(),
    ];
  }

  /**
   * Clean field value.
   *
   * @param \Drupal\node\NodeInterface $entity
   *   The entity to clean field value.
   */
  public function resetFields(NodeInterface $entity) {
    // Clean all fields of entity.
    $node_article_fields = $entity->getFieldDefinitions();
    foreach ($node_article_fields as $field_name => $field_def) {
      if ($field_name === 'title' || $field_name === 'body' || strpos($field_name, 'field_') === 0) {
        $entity->set($field_name, NULL);
      }
    }
  }

  /**
   * Set field value on entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to set field values.
   * @param array $data_mapped
   *   The data mapped by field drupal => value.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function setFieldOnEntity(ContentEntityInterface $entity, array $data_mapped) {
    $countries = CountryManager::getStandardList();
    $country_array = [];
    foreach ($countries as $code => $country) {
      $country_array[$code] = $country->__toString();
    }
    $field_def = $entity->getFieldDefinitions();
    foreach ($data_mapped as $field_name => $value) {

      if (empty($field_def[$field_name]) || !$entity->hasField($field_name)) {
        continue;
      }
      switch ($field_def[$field_name]->getType()) {
        case'string':
          $entity->set($field_name, $value);
          break;

        case 'address':
          $field_adress = $entity->get($field_name)->getValue();
          foreach ($value as $key_adress => $adress_value) {
            // Transform into country code.
            if ($key_adress === 'country_code') {
              $adress_value = array_search($adress_value, $country_array);
            }
            $field_adress[0][$key_adress] = $adress_value;
          }

          $entity->set($field_name, $field_adress[0]);
          break;

        case 'entity_reference':
          $this->getOrCreateTaxonomyTerm($entity, $field_def, $field_name, $value);
          break;

        case 'text_with_summary':
          $value['format'] = 'full_html';
          $entity->set($field_name, $value);
          break;
        case 'text_long':
          $entity->set($field_name, [
            'value' => $value,
            'format' => 'full_html',
          ]);
          break;

        case 'image':
          $this->saveFile($entity, $field_name, $value);
          break;

        case 'date':
          $entity->set($field_name, date('Y-m-d', $value));
          break;

        case 'datetime':
          $entity->set($field_name, date('Y-m-d\TH:i:s', $value));
          break;

        case 'link':
          $entity->set($field_name, ['uri' => $value]);
          break;
        case 'integer':
          $entity->set($field_name, (int) $value);
          break;
        case 'float':
          $entity->set($field_name, (float) $value);
          break;
        case 'boolean':
          $entity->set($field_name, (int) $value);
          break;

        default:
          $this->getLogger('lp_programs.ProgramLot')
            ->error("Error the field type @type is not mapped", [
              '@type' => $field_def[$field_name]->getType(),
            ]);
          break;
      }
    }
  }

  /**
   * Get or creat term and set on entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   * @param array $field_def
   * @param $field_name
   * @param $value
   *
   * @return bool
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function getOrCreateTaxonomyTerm(ContentEntityInterface $entity, array $field_def, $field_name, $value) {

    $field_storage = $field_def[$field_name]->getFieldStorageDefinition();
    $field_storage_settings = $field_storage->getSettings();
    // Set only taxonomy term.
    if (empty($field_storage_settings['target_type']) || $field_storage_settings['target_type'] !== 'taxonomy_term') {
      $this->getLogger('lp_programs.ProgramLot')
        ->error("Error the field type @type is not mapped", [
          '@type' => $field_def[$field_name]->getType(),
        ]);
      return FALSE;
    }
    // Get vocabulary id.
    $field_settings = $field_def[$field_name]->getSettings();
    $target_bundle = $field_settings["handler_settings"]["target_bundles"];
    // Load taxonomy terms.
    $terms = $this->entityTypeManager->getStorage('taxonomy_term')
      ->loadByProperties(['vid' => $target_bundle, 'name' => $value]);
    // Create term if not found.
    if (empty($terms)) {
      $terms = [];
      $terms[] = $this->entityTypeManager->getStorage('taxonomy_term')
        ->create([
          'vid' => reset($target_bundle),
          'name' => $value,
        ]);
    }
    // Set terms on entity.
    foreach ($terms as $term) {
      $entity->set($field_name, [
        'target_id' => $term->id(),
      ]);
    }
    return TRUE;
  }


}
