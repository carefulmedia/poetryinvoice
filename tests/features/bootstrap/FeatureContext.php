<?php

use Drupal\DrupalExtension\Context\RawDrupalContext;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;
use Behat\Behat\Tester\Exception\PendingException;

/**
 * Defines application features from the specific context.
 */
class FeatureContext extends RawDrupalContext {

  /**
   * Initializes context.
   *
   * Every scenario gets its own context instance.
   * You can also pass arbitrary arguments to the
   * context constructor through behat.yml.
   */
  public function __construct() {
  }
  
  /**
   * @Given Sticky elements are removed from page
   */
  public function stickyElementsAreRemovedFromPage() {
    $script = "jQuery('#toolbar-administration, header.region-sticky, .sticky-shadow').hide();";
    $this->getSession()->getDriver()->executeScript($script);
  }

}
