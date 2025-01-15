<?php

namespace Drupal\lp_programs\Services;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Utility\Token;
use Drupal\node\NodeInterface;
use Drupal\sftp_client\SftpClient;

/**
 * Manage feature for lot.
 *
 * @SuppressWarnings(PHPMD.CamelCaseParameterName)
 * @SuppressWarnings(PHPMD.CamelCaseVariableName)
 */
class LotManagerService extends ProgramLotServiceBase {

  /**
   * The machine name of bundle node representing a webinar.
   */
  private const NODE_BUNDLE = 'lot';

  /**
   * The field name where external id is stored.
   */
  private const FIELD_NAME_EXTERNAL_ID = 'field_program_external_id';

  /**
   * @var \Drupal\sftp_client\SftpClient
   */
  protected $sftpClient;

  /**
   *
   */
  use LoggerChannelTrait;

  /**
   * {@inheritDoc}
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory, Token $token, SftpClient $sftp_client) {
    parent::__construct($entity_type_manager);
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->token = $token;
    $this->sftpClient = $sftp_client;
  }

  public function clearMappedFields(NodeInterface $node) {
    $mapping = $this->getMapping();
    foreach ($mapping as $xmlField => $drupalField) {
        if ($node->hasField($drupalField)) {
            $node->set($drupalField, NULL);
        }
    }
}

  /**
   * {@inheritDoc}
   */
  public function getNodeBundle() {
    return self::NODE_BUNDLE;
  }

  /**
   * {@inheritDoc}
   */
  public function getFieldNameExternalId() {
    return self::FIELD_NAME_EXTERNAL_ID;
  }

  public function getSettings() {
    return $this->configFactory->get('lp_programs.lot_settings');
  }

  /**
   * {@inheritDoc}
   */
  public function getMapping() {
    return [
      'NUM_LOT' => 'field_program_external_id',
      'TYPE_LOT' => 'field_lot_type',
      'TYPE_LOT_COMPLEMENT' => 'field_lot_type_complement',
      'NATURE_LOT' => 'field_lot_nature',
      'ETAGE' => 'field_lot_floor',
      'PRIX_LOT' => 'field_lot_price',
      'PRIX_LOT_HT' => 'field_lot_price_ht',
      'PLAN_LOT' => 'field_lot_map_url',
      'SURFACE_HABITABLE_LOT' => 'field_lot_home_area',
      'SURFACE_TERRAIN' => 'field_lot_area',
      'SURFACE_TERRASSE' => 'field_lot_terrace_area',
      'SURFACE_TERRASSE_TOITURE' => 'field_lot_rooftop_area',
      'SURFACE_JARDIN' => 'field_lot_garden_area',
      'SURFACE_BALCON' => 'field_lot_balcony_area',
      'SURFACE_GARAGE' => 'field_lot_garage_area',
      'DISPOSITIF' => 'field_lot_device',
      'NB_PARKING' => 'field_lot_parking_spaces',
    ];
  }

  /**
   * Find lot by external id inside array of node lot.
   *
   * @param NodeInterface[] $nodes_lots
   *   Array of node lots to search.
   * @param string $num_lot
   *   The external id of lot to search.
   *
   * @return \Drupal\node\NodeInterface|false
   *   The node lot found or false if not found.
   */
  public function findLotFieldValue(array $nodes_lots, $num_lot) {
    foreach ($nodes_lots as $node_lot) {
      if (!$node_lot instanceof NodeInterface) {
        continue;
      }
      if ($node_lot->hasField($this->getFieldNameExternalId()) && $node_lot->get($this->getFieldNameExternalId())
          ->getValue()) {
        $id_external = $node_lot->get($this->getFieldNameExternalId())
          ->getValue();
        if (!empty($id_external[0]['value']) && $num_lot == $id_external[0]['value']) {
          return $node_lot;
        }
      }
    }
    return FALSE;
  }

  /**
   * Load xml into simple xml object.
   *
   * @param array $files_path
   *   Array of files path of xml.
   *
   * @return array
   *   Array of simple xml object.
   */
  public function openXmlFilePath(array $files_path) {
    $result = [];
    foreach ($files_path as $mtime => $path) {
      $result[] = [
        'path' => $path,
        'xml' => simplexml_load_file($path),
        'mtime' => $mtime,
      ];
    }
    return $result;
  }

  /**
   * Get files on sftp.
   *
   * @return array|void
   */
/**
 * Get XML files from a local directory and place them in the destination directory.
 *
 * @return array
 */
public function getXmlSftpFiles() {
  try {
      // Define the local directory path where XML files are sourced.
      $sourcePath = DRUPAL_ROOT . '/lots';
      // Define the destination directory path dynamically from settings.
      $config = $this->getSettings();
      $destinationPath = $this->token->replace($config->get('sftp.destination') ?? 'sites/default/files/public');

      // Check if the source directory exists.
      if (!is_dir($sourcePath)) {
          throw new \Exception('The specified source directory does not exist.');
      }
      
      // Scan the source directory for XML files.
      $xmlFiles = glob($sourcePath . '/*.XML');

      $files = [];
      
      foreach ($xmlFiles as $filePath) {
          $fileName = basename($filePath);
          $destinationFilePath = $destinationPath . '/' . $fileName;
          if (!file_exists($destinationFilePath) || (filemtime($filePath) > filemtime($destinationFilePath))) {
              // Copy the file to the destination directory if it does not exist
              // or if it is newer than the one in the destination.
              copy($filePath, $destinationFilePath);
          }
          $time = filemtime($destinationFilePath);
          $files[$time] = $destinationFilePath;
      }
      
      // Sort files by modification time.
      ksort($files);
      
      return $files;
  } catch (\Exception $e) {
      $this->getLogger('lp_programs')->error($e->getMessage());
  }
}

  /**
   * Extract all program from xml.
   *
   * @param \SimpleXMLElement $file
   */
  public function extractProgramXmlToArray(\SimpleXMLElement $file) {
    $res = [];
    foreach ($file as $key => $value) {
      if ($key !== 'PROGRAMME') {
        continue;
      }
      $res[] = $value;
    }
    return $res;
  }

  /**
   * Transform the array of simple xml element to array.
   *
   * @param \SimpleXMLElement[] $programs
   *
   * @return array
   */
  public function transformProgramXmlToArray(array $programs) {
    $res = [];
    foreach ($programs as $key => $program) {
      if (!$program instanceof \SimpleXMLElement) {
        continue;
      }
      $this->transformXmlElementToArray($res, $program, $key);
    }
    return $res;
  }

  /**
   * Transform Simple xml element to array.
   *
   * @param array $res
   * @param \SimpleXMLElement $element
   * @param $key
   */
  private function transformXmlElementToArray(array &$res, \SimpleXMLElement $element, $key) {
    foreach ($element->children() as $key_child => $child) {
      $sub_child = $child->children();
      // Call recurcevely if it's simple xml element.
      if (!empty($sub_child)) {
        $res[$key][$child->getName()][] = [];
        $last_key = array_key_last($res[$key][$child->getName()]);
        $this->transformXmlElementToArray($res[$key][$child->getName()], $child, $last_key);
        continue;
      }
      $res[$key][$child->getName()] = (string) $child;
    }
  }

}
