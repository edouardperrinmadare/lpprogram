<?php

namespace Drupal\lp_programs\Services;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Site\Settings;
use Elasticsearch\ClientBuilder;
use Exception;

/**
 * ProximitySearch program service.
 *
 * Call example:
 * - Instantiate service > $searchProgram =
 * Drupal::service('lp_programs.proximity_search')
 * - Search one distance > $searchProgram->search($position, '100km', NULL);
 * - Search incremental on multiple distances with a limit >
 * $searchProgram->search($position, ['20km', '50km', '100km', '300km'], 20);
 */
class ProgramProximitySearch {

  /**
   * Elastic Search client.
   */
  private $client;

  /**
   * Elastic Search configuration.
   *
   * @var array
   */
  private $esConfig;

  /**
   * The site settings.
   *
   * @var \Drupal\Core\Site\Settings
   */
  protected $settings;

  /**
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a ProximitySearch object.
   *
   * @param \Drupal\Core\Site\Settings $settings
   *   The site settings.
   *
   * @throws \Exception
   */
  public function __construct(Settings $settings, ConfigFactoryInterface $config_factory) {
    $this->settings = $settings;
    $this->configFactory = $config_factory;

    $this->setESConfiguration();
    if (empty($this->esConfig['host'])) {
      throw new Exception('Elastic Search host setting not found.');
    }

    $this->buildClient();
  }

  /**
   * Set Elastic Search Configurations.
   */
  private function setESConfiguration() {
    $this->esConfig = [
      'host' => $this->configFactory->get('elasticsearch_connector.cluster.localhost')
        ->get('url'),
      'username' => $this->configFactory->get('elasticsearch_connector.cluster.localhost')
        ->get('options.username'),
      'password' => $this->configFactory->get('elasticsearch_connector.cluster.localhost')
        ->get('options.password'),
      'index' => $this->settings->get('elasticsearch_connector.index'),
    ];
  }

  /**
   * Build Client url.
   */
  private function buildClientUrl() {
    $url = parse_url($this->esConfig['host']);

    $host = $this->esConfig['host'];

    if (!empty($this->esConfig['username']) && !empty($this->esConfig['password'])) {
      $host = $url['scheme'] . '://' . $this->esConfig['username'] . ':' . $this->esConfig['password'] . '@' . $url['host'] . ':' . $url['port'];
    }

    return [$host];
  }

  /**
   * Build Elastic Search client.
   */
  private function buildClient() {
    $hosts = $this->buildClientUrl();

    $this->client = ClientBuilder::create()
      ->setHosts($hosts)
      ->build();
  }

  /**
   * Search one distance or search incremental on multiple distances.
   *
   * Filter by default:  Program Status != delivered and not empty.
   *
   * @param string $position Example: 43.604652,1.444209
   * @param array|string $distance Example: 50km or ['20km', '50km', '100km',
   *   '300km']
   * @param null|int $limit Example: 20
   *
   * @return array programs nid.
   */
  public function search($position, $distance, $limit = NULL, $params = []) {
    $results = [];
    if (empty($position) || empty($distance)) {
      return $results;
    }
    $params['index'] = $this->esConfig['index'];
    $params['body']['size'] = $params['body']['size'] ?? '300';
    $params['body']['fields'] = $params['body']['fields'] ?? ['nid'];
    $params['body']['_source'] = $params['body']['_source'] ?? FALSE;
    $params['body']['query']['bool']['must'] = $params['body']['query']['bool']['must'] ?? ["exists" => ["field" => "field_program_layout"]];
    //$params['body']['query']['bool']['must_not'] = $params['body']['query']['bool']['must_not'] ?? ["term" => ["field_program_layout" => "sold"]];
    $params['body']['query']['bool']['filter'] = [
      'geo_distance' => [
        'distance' => "",
        "localisation" => $position,
      ],
    ];
  
    // Search one distance.

    if (!is_array($distance)) {
      $params['body']['query']['bool']['filter']['geo_distance']['distance'] = $distance;

      // Strictly limit result returned by ES.
      if ($limit) {
        $params['body']['size'] = $limit;
      }

      $results = $this->client->search($params);

      return $results['hits']['hits'];
    }
    if (empty($limit)) {
      return $results;
    }
    // Search incremental on multiple distances with a limit.
    foreach ($distance as $value) {
      $params['body']['query']['bool']['filter']['geo_distance']['distance'] = $value;
      $results = $this->client->search($params);

      $count = count($results['hits']['hits']);
      if ($count >= $limit) {
        break;
      }
    }
    return $results['hits']['hits'];
  }

  /**
   * Search on Address field.
   *
   * Filter by City || Region.
   *
   * @param array $address
   *
   * @return array programs nid.
   */
  public function searchByAddress($address) {
    $results = [];

    if (empty($address)) {
      return $results;
    }

    // Prepare ES query.
    $params['index'] = $this->esConfig['index'];
    $params['body']['size'] = $params['body']['size'] ?? '300';
    $params['body']['fields'] = $params['body']['fields'] ?? ['nid'];
    $params['body']['_source'] = $params['body']['_source'] ?? FALSE;
    $params['body']['query']['bool']['must'] = $params['body']['query']['bool']['must'] ?? ["exists" => ["field" => "field_program_layout"]];
    //$params['body']['query']['bool']['must_not'] = $params['body']['query']['bool']['must_not'] ?? ["term" => ["field_program_layout" => "sold"]];

    // Address query filter.
    if (!empty($address['ville'])) {
      $params['body']['query']['bool']['filter'] = ["term" => ["locality" => strtolower($address['ville'])]];
    }
    if (!empty($address['region'])) {
      $params['body']['query']['bool']['filter'] = ["term" => ["administrative_area" => $address['region']]];
    }

    // Search.
    $results = $this->client->search($params);
    
    return $results['hits']['hits'];
  }

}
