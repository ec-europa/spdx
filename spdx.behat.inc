<?php

/**
 * @file
 * Contains \SpdxSubContext.
 */

declare(strict_types = 1);

use Behat\Gherkin\Node\TableNode;
use Drupal\DrupalExtension\Context\DrupalSubContextBase;
use Drupal\rdf_entity\Entity\Rdf;


/**
 * Behat step definitions for the spdx module.
 */
class SpdxSubContext extends DrupalSubContextBase {

  /**
   * Test licences.
   *
   * @var \Drupal\rdf_entity\Entity\Rdf[]
   */
  protected $licences = [];

  /**
   * Creates a number of SPDX licence records.
   *
   * Table format:
   * @codingStandardsIgnoreStart
   * | label          |
   * | Licence name 1 |
   * @codingStandardsIgnoreEnd
   *
   * @param \Behat\Gherkin\Node\TableNode $data
   *   The collection data.
   *
   * @throws \Exception
   *   Thrown when a column name is incorrect.
   *
   * @Given (the following )SPDX licences:
   */
  public function givenSpdxLicences(TableNode $data) {
    foreach ($data->getColumnsHash() as $licence_data) {
      $licence_data += ['rid' => 'spdx_licence'];
      $licence = Rdf::create($licence_data);
      $licence->save();
      $this->licences[$licence->id()] = $licence;
    }
  }

  /**
   * Navigates to the canonical page of the SPDX licence.
   *
   * @param string $licence
   *   The SPDX licence label.
   *
   * @Given I visit the :licence SPDX licence
   * @Given I go to the( homepage of the) :licence SPDX licence
   */
  public function visitSpdxLicence(string $licence): void {
    $result = \Drupal::entityQuery('rdf_entity')
      ->condition('label', $licence)
      ->condition('rid', 'spdx_licence')
      ->range(0, 1)
      ->execute();

    if (empty($result)) {
      $message = "The SPDX licence with label '$licence' was not found.";
      throw new \InvalidArgumentException($message);
    }
    $licence = Rdf::load(reset($result));
    $this->visitPath($licence->toUrl()->toString());
  }

  /**
   * Removes any created SPDX licences.
   *
   * @AfterScenario
   */
  public function cleanSpdxLicences() {
    foreach ($this->licences as $licence) {
      $licence->delete();
    }
  }
}
