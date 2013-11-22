<?php

namespace ActiveLAMP\BehatContext\Context;
use Behat\Behat\Context\ClosuredContextInterface,
    Behat\Behat\Context\TranslatedContextInterface,
    Behat\Behat\Context\BehatContext,
    Behat\Behat\Exception\PendingException;
use Behat\Gherkin\Node\PyStringNode,
    Behat\Gherkin\Node\TableNode;
use Behat\MinkExtension\Context\RawMinkContext;

use Behat\MinkExtension\Context\MinkContext;
use ActiveLAMP\BehatContext\Context\ActiveLampContext;

/**
 * Generic Drupal Behat functionality.
 */
class DrupalContext extends ActiveLampContext
{

    protected $mink = NULL;

    /**
     * Initializes context.
     * Every scenario gets it's own context object.
     *
     * @param array $parameters context parameters (set them up through behat.yml)
     */
    public function __construct(array $parameters)
    {
        parent::__construct($parameters);
        $this->useContext('drupal_mink', new MinkContext($parameters));
        $this->mink = $this->getMainContext()->getSubcontext('drupal_mink');
    }

    /**
     * Use mink's session management when accessing drupal.
     *
     * Session management is required to authenticate users and remember
     * cookies.
     *
     * @param string $url
     *   Fully formed, or relative, URL to visit.
     *
     * @return string
     *   The result of the page visit.
     */
    public function iCall($url) {
        $this->printDebug("Calling: {$url}");
    	$session = $this->mink->getSession()->visit($url);
    	$content = $this->mink->getSession()->getPage()->getContent();

        return $content;
    }

    /**
     * @Given /^I am logged into drupal$/
     */
    public function iAmLoggedIntoDrupal()
    {
    	$base_url = $this->getParameter('base_url');
    	$name = $this->getParameter('drupal/name');
    	$password = $this->getParameter('drupal/password');

        $this->iCall($base_url . '/user/login');
        $this->mink->fillField('edit-name', $name);
        $this->mink->fillField('edit-pass', $password);
        $this->mink->pressButton('Log in');
    }

    /**
     * @Given /^I am logged out of drupal$/
     */
    public function iAmLoggedOutOfDrupal()
    {
        $base_url = $this->getParameter('base_url');

        $this->iCall($base_url . '/user/logout');
    }

}
