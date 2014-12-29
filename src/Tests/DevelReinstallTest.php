<?php

/**
 * @file
 * Contains \Drupal\devel\Tests\DevelReinstallTest.
 */

namespace Drupal\devel\Tests;

use Drupal\simpletest\WebTestBase;

/**
 * Tests reinstall modules.
 *
 * @group devel
 */
class DevelReinstallTest extends WebTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('devel');

  /**
   * The profile to install as a basis for testing.
   *
   * @var string
   */
  protected $profile = 'minimal';

  /**
   * Set up test.
   */
  protected function setUp() {
    parent::setUp();

    $web_user = $this->drupalCreateUser(array('administer site configuration'));
    $this->drupalLogin($web_user);
  }

  /**
   * Reinstall modules.
   */
  public function testDevelReinstallModules() {
    // Minimal profile enables only dblog, block and node.
    $modules = array('dblog', 'block');

    // Sort modules as DevelReinstall->buildForm do.
    // Needed for compare correctly the message.
    sort($modules);

    $this->drupalGet('devel/reinstall');

    // Prepare field data in an associative array
    $edit = array();
    foreach ($modules as $module) {
      $edit["list[$module]"] = TRUE;
    }

    $this->drupalPostForm('devel/reinstall', $edit, t('Reinstall'));
    $this->assertText(t('Uninstalled and installed: @names.', array('@names' => implode(', ', $modules))));
  }

}
