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
class DrupalServiceAPIContext extends DrupalContext
{

    // Store the raw response.
    protected $apiResponse = NULL;
    // All responses should be converted into a PHP array and stored here.
    protected $apiResponseArray = NULL;
    // The type of API response (json, xml, etc...)
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
     *   constructed with forward slashes '/' as the delimiters. Property
     *   names may contain regular expression matches.
     * @param bool $regex
     *   (optional) defaults to TRUE. Allow regular expression matching.
     *
     * @return mixed|null
     *   The value of the property or NULL if no property found or property
     *   contains no value.
     */
    private function getProperty($property_string, $regex = TRUE) {
        $value = NULL;
        $properties = explode('/', $property_string);
        $response = $this->apiResponseArray;
        while(($property = array_shift($properties)) !== NULL) {
            $property = ($regex) ? "^{$property}$" : preg_quote($property);
            $value = NULL;
            $keys = array_keys($response);
            foreach($keys as $key) {
                if (preg_match("/{$property}/", $key)) {
                    $response = $response[$key];
                    $value = $response;
                    break;
                }
            }
        } 
        // If property_string is empty then just return the entire array.
        if ($property_string == '' || !strlen($property_string)) {
            $value = $this->apiResponseArray;
        }

        return $value;
    }

    /**
     * Return the value of all properties matching a regular expression.
     *
     * @param array $property_array
     *   An exploded property string.
     * @param mixed $haystack
     *
     * @return array
     *   Each element contains the value of the property or NULL if property
     *   contains no value.
     */
    private function getAllProperty($property_array, $haystack) {
        $value = array();
        $property = '^' . array_shift($property_array) . '$';
        $keys = array_keys($haystack);
        foreach($keys as $key) {
            if (preg_match("/{$property}/", $key)) {
                if (count($property_array)) {
                  $value[] = array_pop($this->getAllProperty($property_array, $haystack[$key]));
                } else {
                  $value[] = $haystack[$key];
                }
            } 
        }

        return $value;
    }

    /**
     * Determine if a property exists or not.
     *
     * @param string $property_string
     *   A property name or path to the property. Path the property can be
     *   constructed with forward slashes '/' as the delimiters. Property
     *   names may contain regular expression matches.
     * @param bool $regex
     *   (optional) defaults to TRUE. Allow regular expression matching.
     *
     * @return bool
     *   TRUE if the property exists, FALSE if it does not.
     */
    private function propertyExists($property_string, $regex = TRUE) {
        $exists = FALSE;
        $properties = explode('/', $property_string);
        $response = $this->apiResponseArray;

        while($property = array_shift($properties)) {
            $exists = FALSE;
            $property = ($regex) ? "^{$property}$" : preg_quote($property);
            $keys = array_keys($response);
            foreach($keys as $key) {
                if (preg_match("/{$property}/", $key)) {
                    $response = $response[$key];
                    $exists = TRUE;
                    break;
                }
            }
        }

        return $exists;
    }

    public function objectToArray($obj) {
        if(is_object($obj)) $obj = (array) $obj;
        if(is_array($obj)) {
            $new = array();
            foreach($obj as $key => $val) {
                $new[$key] = $this->objectToArray($val);
            }
        }
        else $new = $obj;
        return $new;       
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
 
        $this->apiResponse = @file_get_contents($url);
        if (!strlen($this->apiResponse)) {
            throw new Exception("Could not open $path");
        }
        if ($format == 'json') {
            $this->apiResponseType = 'json';
            $this->apiResponseArray = $this->objectToArray(json_decode($this->apiResponse));
        }
    }

    /**
     * @Given /^I call "([^"]*)" as "([^"]*)" with "([^"]*)"$/
     *
     * @param string $path
     *   The relative url path to access.
     * @param string $format
     *   The format that the API should return the response in. Only 'json' is
     *   currently supported.
     * @param string $append
     *   Any string that should be appended to the GET request.
     */
    public function iCallAsWith($path, $format, $append)
    {
        $this->iCallAs($path, $format, $append);
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
        $property_type = ($property_type == 'integer' || $property_type == 'double') ? 'int' : $property_type;
        // Strings that are numbers should qualify as integers.
        if ($type == 'int' && ($property_type == 'string') && is_numeric($property_value)) {
            $type = 'string';
        }

        if ($type != $property_type) {
            throw new Exception("Wrong property type found for {$property_string}: {$property_type}");
        }
    }

    /**
     * @Then /^property "([^"]*)" all should be of type "([^"]*)"$/
     *
     * @param string $property_string
     *   A property name or path to the property. Path the property can be
     *   constructed with forward slashes '/' as the delimiters.
     * @param string $type
     *   The data type of the property. Can be 'int', 'string' or 'array'.
     */
    public function propertyAllShouldBeOfType($property_string, $type)
    {
        $property_value = $this->getAllProperty(explode('/', $property_string), $this->apiResponseArray);
        if (empty($property_value)) {
            throw new Exception("Missing property: {$property_string}");
        }

        foreach($property_value as $value) {
            $property_type = gettype($value);
            $property_type = ($property_type == 'integer' || $property_type == 'double') ? 'int' : $property_type;
            // Strings that are numbers should qualify as integers.
            if ($type == 'int' && ($property_type == 'string') && is_numeric($value)) {
                $type = 'string';
            }

            if ($type != $property_type) {
                throw new Exception("Wrong property type found for {$property_string}: {$property_type}");
            }
        }
    }

    /**
     * @Then /^property "([^"]*)" at least "([^"]*)" should be of type "([^"]*)"$/
     *
     * @param string $property_string
     *   A property name or path to the property. Path the property can be
     *   constructed with forward slashes '/' as the delimiters.
     * @param int $required
     *   The number of properties that should be of this type.
     * @param string $type
     *   The data type of the property. Can be 'int', 'string' or 'array'.
     */
    public function propertyAtLeastShouldBeOfType($property_string, $required, $type)
    {
        $amount = 0;
        $property_value = $this->getAllProperty(explode('/', $property_string), $this->apiResponseArray);
        if (empty($property_value)) {
            throw new Exception("Missing property: {$property_string}");
        }

        foreach($property_value as $value) {
            $property_type = gettype($value);
            $property_type = ($property_type == 'integer' || $property_type == 'double') ? 'int' : $property_type;
            // Strings that are numbers should qualify as integers.
            if ($type == 'int' && ($property_type == 'string') && is_numeric($value)) {
                $type = 'string';
            }
            if ($type == $property_type) {
                $amount = $amount + 1;
            }
        }

        if ($amount < $required) {
            throw new Exception("Wrong amount of property types found for {$property_string}: {$amount}, Wanted: {$required}");
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

    /**
     * @Then /^property "([^"]*)" should be recursive parameter "([^"]*)"$/
     */
    public function propertyShouldBeRecursiveParameter($property_string, $parameter_string)
    {
        $properties = $this->getProperty($property_string);
        $parameters = $this->getParameter($parameter_string);
        $properties_serial = serialize($properties);
        $parameters_serial = serialize($parameters);
        if ($properties_serial != $parameters_serial) {
            $this->printDebug($properties_serial);
            $this->printDebug($parameters_serial);
            throw new Exception("Recursive keys or values do not match");
        }
    }

}
