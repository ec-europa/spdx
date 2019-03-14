<?php

declare(strict_types = 1);

namespace Drupal\spdx\Commands;

use Composer\Autoload\ClassLoader;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Database\Database;
use Drupal\Driver\Database\sparql\Connection;
use Drupal\rdf_entity\RdfEntityGraphStoreTrait;
use Drush\Commands\DrushCommands;
use Drush\Exceptions\UserAbortException;
use EasyRdf\Graph;
use EasyRdf\GraphStore;
use ReflectionClass;

/**
 * Class SpdxCommands
 */
class SpdxCommands extends DrushCommands {

  use RdfEntityGraphStoreTrait;

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
   * @throws \Drush\Exceptions\UserAbortException
   *   Thrown if the user cancels the command.
   */
  public function importLicenses(string $graph_uri, array $options = ['clean' => FALSE]): void {
    if (!UrlHelper::isValid($graph_uri, ['absolute' => TRUE])) {
      throw new \InvalidArgumentException('Graph URI is not a valid URL');
    }

    $message = $options['clean'] ?
      'This will clean the "%graph" graph and re import all licenses. Are you sure you want to proceed?' :
      'This will import all license data into the "%graph" graph. Are you sure you want to continue?';

    if (!$this->io()->confirm(dt($message, ['%graph' => $graph_uri]))) {
      throw new UserAbortException();
    }

    $vendor_dir = $this->getConfig()->get('drush.vendor-dir');
    $dir_parts = [$vendor_dir, 'spdx', 'license-list-data', 'rdfxml'];
    $spdx_source = implode(DIRECTORY_SEPARATOR, $dir_parts) . DIRECTORY_SEPARATOR;

    $graph_store = $this->createGraphStore();
    if ($options['clean']) {
      $client = Connection::open(Database::getConnectionInfo('sparql_default')['default']);
      $client->clear($graph_uri);
      $this->logger()->success(dt('Graph has been cleaned.'));
    }

    $rdf_files = glob($spdx_source . '*.rdf');
    if (empty($rdf_files)) {
      throw new \LogicException(dt('No files were detected in the %directory directory. Are the dependencies installed?', [
        '%directory' => $spdx_source,
      ]));
    }

    $count = 0;
    foreach ($rdf_files as $rdf_file_path) {
      $graph = new Graph($graph_uri);
      $graph->parseFile($rdf_file_path);
      // There is a file that includes all the licenses and we don't need to
      // re-import them.
      // @see https://github.com/spdx/license-list-data/issues/48.
      $licenses = $graph->allOfType('http://spdx.org/rdf/terms#License');
      if (count($licenses) !== 1) {
        continue;
      }
      $graph_store->insert($graph);
      $count++;
      $this->logger()->success(dt('Imported file %file.', [
        '%file' => $rdf_file_path
      ]));
    }

    $this->logger()->success(dt('Finished importing %count licenses.', [
      '%count' => $count,
    ]));
  }
}
