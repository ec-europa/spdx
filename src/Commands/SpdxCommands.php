<?php

namespace Drupal\spdx\Commands;

use Composer\Autoload\ClassLoader;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Database\Database;
use Drush\Commands\DrushCommands;
use EasyRdf\Graph;
use EasyRdf\GraphStore;
use ReflectionClass;

/**
 * Class SpdxCommands
 */
class SpdxCommands extends DrushCommands {

  /**
   * Imports the SPDX licenses into the default SPARQL database.
   *
   * @param string $graph_uri
   *   The graph to put the licenses in.
   * @param array $options
   *   An array of options.
   *
   * @command spdx:import
   * @option clean Whether to clean the graph before importing the graphs.
   * @usage spdx:import "http://example.com/licenses"
   *   Imports the licenses into the "http://example.com/licenses" graph.
   * @usage spdx:import "http://example.com/licenses" --clean
   *   Imports the licenses into the graph but also cleans the graph first.
   *
   * @throws \ReflectionException
   *   Thrown if the class does not exist.
   */
  public function importLicenses($graph_uri, $options = ['clean' => FALSE]) {
    if (!UrlHelper::isValid($graph_uri, ['absolute' => TRUE])) {
      throw new \InvalidArgumentException('Graph URI is not a valid URL');
    }

    $reflextion = new ReflectionClass(ClassLoader::class);

    // Dirname with 2 as the second parameter, will return the full path
    // including the ./vendor/ subdir
    $directory = dirname($reflextion->getFilename(), 2);
    $dir_parts = [$directory, 'spdx', 'license-list-data', 'rdfxml'];
    $spdx_source = implode(DIRECTORY_SEPARATOR, $dir_parts) . DIRECTORY_SEPARATOR;

    /** @var \Drupal\Driver\Database\joinup_sparql\Connection $connection */
    $connection = \Drupal::service('sparql_endpoint');
    $connection_options = $connection->getConnectionOptions();
    $connect_string = "http://{$connection_options['host']}:{$connection_options['port']}/sparql-graph-crud";

    if ($options['clean']) {
      $connection->query("CLEAR GRAPH <{$graph_uri}>;");
      $this->logger()->success(dt('Graph has been cleaned.'));
    }

    $rdf_files = glob($spdx_source . '*.rdf');
    if (empty($rdf_files)) {
      throw new \LogicException(dt('No files were detected in the %directory directory. Are the dependencies installed?', [
        '%directory' => $spdx_source,
      ]));
    }

    foreach (glob($spdx_source . '*.rdf') as $rdf_file_path) {
      $graph = new Graph($graph_uri);
      $graph->parseFile($rdf_file_path);
      // There is a file that includes all the licenses and we don't need to
      // re-import them.
      // @see https://github.com/spdx/license-list-data/issues/48.
      if (count($graph->toRdfPhp()) === 1) {
        continue;
      }
      $graph_store = new GraphStore($connect_string);
      $graph_store->insert($graph);

      $arguments = ['%file' => $rdf_file_path];
      $this->logger()->success(dt('Imported file %file.', $arguments));
    }
  }
}