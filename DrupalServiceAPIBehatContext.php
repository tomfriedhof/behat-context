<?php

use Behat\Behat\Context\ClosuredContextInterface,
    Behat\Behat\Context\TranslatedContextInterface,
    Behat\Behat\Context\BehatContext,
    Behat\Behat\Exception\PendingException;
use Behat\Gherkin\Node\PyStringNode,
    Behat\Gherkin\Node\TableNode;

require_once('DrupalContext.php');

/**
 * Generic Drupal Servie API Behat functionality.
 */
class DrupalServiceAPIBehatContext extends DrupalContext
{

    protected $apiResponse = NULL;
    protected $apiResponseArray = NULL;
    protected $apiResponseType = NULL;

    /**
     * Initializes context.
     * Every scenario gets it's own context object.
     *
     * @param array $parameters context parameters (set them up through behat.yml)
     */
    public function __construct(array $parameters)
    {
        parent::__construct($parameters);
    }

    /**
     * Return the value of a property.
     *
     * @param string $property_string
     *   A property name or path to the property. Path the property can be
     *   constructed with forward slashes '/' as the delimiters.
     *
     * @return mixed|NULL
     *   The value of the property or NULL if no property found.
     */
    private function getPropertyJson($property_string) {
        $properties = explode('/', $property_string);
        $response = $this->apiResponseArray;

        while($property = array_shift($properties)) {
            if (isset($response->$property)) {
                $response = $response->$property;
            } else {
                $response = NULL;
            }
        }

        return $response;
    }

    /**
     * Determine if a property exists or not.
     *
     * @param string $property_string
     *   A property name or path to the property. Path the property can be
     *   constructed with forward slashes '/' as the delimiters. Property
     *   names may contain regular expression matches.
     *
     * @return bool|null
     *   TRUE if the property exists, FALSE if it does not, NULL on error.
     */
    private function propertyExistsJson($property_string) {
        $exists = NULL;
        $properties = explode('/', $property_string);
        $response = $this->apiResponseArray;

        while($property = array_shift($properties)) {
            $exists = FALSE;
            if (property_exists($response, $property)) {
                $exists = TRUE;
            }
            if (isset($response->$property)) {
                $response = $response->$property;
            }
        }

        return $exists;
    }

    /**
     * Determine if a property exists or not.
     *
     * @param string $property_string
     *   A property name or path to the property. Path the property can be
     *   constructed with forward slashes '/' as the delimiters.
     *
     * @return bool|null
     *   TRUE if the property exists, FALSE if it does not, NULL on error.
     */
    private function propertyMatchExistsJson($property_string) {
        $exists = NULL;
        $properties = explode('/', $property_string);
        $response = $this->apiResponseArray;

        while($property = array_shift($properties)) {
            $exists = FALSE;
            if (preg_match("/{$property}/", $response)) {
                $exists = TRUE;   
            }
            $keys = array_keys(get_object_vars($response));
            foreach($keys as $key) {
                if (preg_match("/{$property}/", $key)) {
                    $exists = TRUE;
                    $response = $response->$key;
                    break;
                }
            }
        }

        return $exists;
    }

    /**
     * Manages which property getter should be invoked depending on what type
     * of data was returned from the api request.
     *
     * @param string $property_string
     *   A property name or path to the property. Path the property can be
     *   constructed with forward slashes '/' as the delimiters.
     *
     * @return mixed|NULL
     *   Returns the value of the property or NULL if response type handler
     *   could not be found or if the property itself could not be located.
     */
    private function getProperty($property_string) {
        switch($this->apiResponseType) {
            case 'json':
                $value = $this->getPropertyJson($property_string);
                break;
        }

        return $value;
    }

    /**
     * Manages which property exist() should be invoked depending on what type
     * of data was returned from the api request.
     *
     * @param string $property_string
     *   A property name or path to the property. Path the property can be
     *   constructed with forward slashes '/' as the delimiters.
     *
     * @return bool|null
     *   Returns TRUE if the property exists, FALSE if it does not, NULL on
     *   error.
     */
    private function propertyExists($property_string) {
        $exists = NULL;

        switch($this->apiResponseType) {
            case 'json':
                $exists = $this->propertyExistsJson($property_string);
                break;
        }

        return $exists;
    }

    /**
     * Manages which property exist() should be invoked depending on what type
     * of data was returned from the api request.
     *
     * @param string $property_string
     *   A property name or path to the property. Path the property can be
     *   constructed with forward slashes '/' as the delimiters. Property
     *   names may contain regular expression matches.
     *
     * @return bool|null
     *   Returns TRUE if the property exists, FALSE if it does not, NULL on
     *   error.
     */
    private function propertyMatchExists($property_string) {
        $exists = NULL;

        switch($this->apiResponseType) {
            case 'json':
                $exists = $this->propertyMatchExistsJson($property_string);
                break;
        }

        return $exists;
    }

    /**
     * @Given /^I call "([^"]*)" as "([^"]*)"$/
     *
     * @param string $path
     *   The relative url path to access.
     * @param string $format
     *   The format that the API should return the response in. Only 'json' is
     *   currently supported.
     * @param string $append
     *   Any string that should be appended to the GET request.
     */
    public function iCallAs($path, $format, $append = '')
    {
        // @todo probably want to use CURL so we can examine response headers.
        $url = $this->parameters['base_url'] . $path . ".{$format}{$append}";
 
        $this->apiResponse = file_get_contents($url);
        if (!strlen($this->apiResponse)) {
            throw new Exception("Could not open $path");
        }
        if ($format == 'json') {
            $this->apiResponseType = 'json';
            $this->apiResponseArray = json_decode($this->apiResponse);
        }
    }

    /**
     * @Given /^I call parameter "([^"]*)" as "([^"]*)"$/
     *
     * @param string $parameter_string
     *   Property name or path to parameter as located in the YML config
     *   file beneath the 'parameters' value.
     * @param string $format
     *   The format that the API should return the response in. Only 'json' is
     *   currently supported.
     */
    public function iCallParameterAs($parameter_string, $format)
    {
        $parameter_value = $this->getParameter($parameter_string);
        if ($parameter_value === NULL) {
            throw new Exception("Missing config parameter: {$parameter_string}");
        }
        $this->iCallAs($parameter_value, $format);
    }

    /**
     * @Then /^property "([^"]*)" should exist$/
     *
     * @param string $property_string
     *   A property name or path to the property. Path the property can be
     *   constructed with forward slashes '/' as the delimiters.
     */
    public function propertyShouldExist($property_string)
    {
        if (!$this->propertyExists($property_string)) {
            throw new Exception("Property {$property_string} does not exist");
        }
    }

    /**
     * @Then /^property match "([^"]*)" should exist$/
     *
     * @param string $property_string
     *   A property name or path to the property. Path the property can be
     *   constructed with forward slashes '/' as the delimiters. Property
     *   names may contain regular expression matches.
     */
    public function propertyMatchShouldExist($property_string)
    {
        if (!$this->propertyMatchExists($property_string)) {
            throw new Exception("Property {$property_string} does not exist");
        }
    }

    /**
     * @Then /^property "([^"]*)" should be "([^"]*)"$/
     *
     * @param string $property_string
     *   A property name or path to the property. Path the property can be
     *   constructed with forward slashes '/' as the delimiters.
     * @param mixed $value
     *   The value the property must equal.
     */
    public function propertyShouldBe($property_string, $value)
    {
        $property_value = $this->getProperty($property_string);
        if ($property_value === NULL) {
            throw new Exception("Missing property: {$property_string}");
        }
        if ($property_value != $value) {
            throw new Exception("Wrong value found for {$property_string}: {$property_value}");
        }
    }

    /**
     * @Then /^property "([^"]*)" should be parameter "([^"]*)"$/
     *
     * @param string $property_string
     *   A property name or path to the property. Path the property can be
     *   constructed with forward slashes '/' as the delimiters.
     *
     * @param string $config
     *   Property name or path to property as located in the YML config
     *   file beneath the 'parameters' value.
     */
    public function propertyShouldBeParameter($property_string, $parameter_string)
    {
        // Get value from config file.
        $parameter_value = $this->getParameter($parameter_string);
        if ($parameter_value === NULL) {
            throw new Exception("Missing config parameter: {$parameter_string}");
        }
        $property_value = $this->getProperty($property_string);
        if ($property_value === NULL) {
            throw new Exception("Missing api property: {$property_string}");
        }
        if ($property_value != $parameter_value) {
            throw new Exception("Wrong value found for {$property_string}: {$property_value}, wanted: {$parameter_value}");
        }
        $this->override_text = "property \"{$property_string}\" should be \"{$parameter_value}\"";
    }

    /**
     * @Then /^property "([^"]*)" should contain "([^"]*)"$/
     *
     * @param string $property_string
     *   A property name or path to the property. Path the property can be
     *   constructed with forward slashes '/' as the delimiters.
     * @param mixed $value
     *   The value the property must contain.
     */
    public function propertyShouldContain($property_string, $value)
    {
        $property_value = $this->getProperty($property_string);
        if ($property_value === NULL) {
            throw new Exception("Missing property: {$property_string}");
        }
        if (!strstr($property_value, $value)) {
            throw new Exception("Missing value ({$value}) inside {$property_string}: {$property_value}");
        }
    }

    /**
     * @Then /^property "([^"]*)" should be of type "([^"]*)"$/
     *
     * @param string $property_string
     *   A property name or path to the property. Path the property can be
     *   constructed with forward slashes '/' as the delimiters.
     * @param string $type
     *   The data type of the property. Can be 'int', 'string' or 'array'.
     */
    public function propertyShouldBeOfType($property_string, $type)
    {
        $property_value = $this->getProperty($property_string);
        if ($property_value === NULL) {
            throw new Exception("Missing property: {$property_string}");
        }
        $property_type = gettype($property_value);
        // Properties that are objects should qualify as arrays.
        if ($type == 'array') {
            $type = 'object';
        }
        // Strings that are numbers should qualify as integers.
        if ($type == 'int' && is_numeric($property_value)) {
            $type = 'string';
        }

        if ($type != $property_type) {
            throw new Exception("Wrong property type found for {$property_string}: {$property_type}");
        }
    }

    /**
     * @Then /^property "([^"]*)" should have "([^"]*)" children$/
     *
     * @param string $property_string
     *   A property name or path to the property. Path the property can be
     *   constructed with forward slashes '/' as the delimiters.
     * @param int $number
     *   Number of array elements the property should have.
     */
    public function propertyShouldHaveChildren($property_string, $number)
    {
        $property_value = $this->getProperty($property_string);
        $property_count = count((array) $property_value);
        if ($property_count != $number) {
            throw new Exception("Wrong number of elements found for {$property_string}: {$property_count}");
        }
    }    

    /**
     * @Then /^property "([^"]*)" should have at least "([^"]*)" children$/
     *
     * @param string $property_string
     *   A property name or path to the property. Path the property can be
     *   constructed with forward slashes '/' as the delimiters.
     * @param int $number
     *   Number of array elements the property should have at least.
     */
    public function propertyShouldHaveAtLeastChildren($property_string, $number)
    {
        $property_value = $this->getProperty($property_string);
        $property_count = count((array) $property_value);
        if ($property_count < $number) {
            throw new Exception("Wrong number of elements found for {$property_string}: {$property_count}");
        }
    }

    /**
     * @Then /^property "([^"]*)" should have less than "([^"]*)" children$/
     *
     * @param string $property_string
     *   A property name or path to the property. Path the property can be
     *   constructed with forward slashes '/' as the delimiters.
     * @param int $number
     *   Number of array elements the property should have less than.
     */
    public function propertyShouldHaveLessThanChildren($property_string, $number)
    {
        $property_value = $this->getProperty($property_string);
        $property_count = count((array) $property_value);
        if ($property_count >= $number) {
            throw new Exception("Wrong number of elements found for {$property_string}: {$property_count}");
        }
    }

}
