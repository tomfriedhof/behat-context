<?php
/**
 * @files
 * Generic behat functionality that should span all sites.
 */

use Behat\Behat\Context\ClosuredContextInterface,
    Behat\Behat\Context\TranslatedContextInterface,
    Behat\Behat\Context\BehatContext,
    Behat\Behat\Exception\PendingException;
use Behat\Gherkin\Node\PyStringNode,
    Behat\Gherkin\Node\TableNode;

class BaseContext extends BehatContext {

    // Parameters contained in the YML file.
    protected $parameters = NULL;
    // Override behats normal step success output message with this instead.
    public $override_text = NULL;
    // Database access.
    protected $db = NULL;

    /**
     * Initializes context.
     * Every scenario gets it's own context object.
     *
     * @param array $parameters context parameters (set them up through behat.yml)
     */
    public function __construct(array $parameters)
    {
        // Fix any literal 'name' keys. In the YML config, any parameter on
        // the top level that contains a subvalue of 'name' will be interpreted
        // as the parameters actual name and get assigned as the key.
        foreach($parameters as $key => $parameter) {
            if (is_array($parameter) && isset($parameter['_name'])) {
                $parameters[$key]['name'] = $parameters[$key]['_name'];
                unset($parameters[$key]['_name']);                
            }
        }
        $this->parameters = $parameters;

        $this->ssh['ip'] = $this->getParameter('ssh/ip');
        $this->ssh['user'] = $this->getParameter('ssh/user');
        $this->ssh['key'] = $this->getParameter('ssh/key');
        if (!$this->ssh['key']) {
            if (file_exists($_SERVER['HOME'] . '/.vagrant.d/insecure_private_key')) {
                $this->ssh['key'] = $_SERVER['HOME'] . '/.vagrant.d/insecure_private_key';
            }           
        }

        $this->mysql['ip'] = $this->getParameter('mysql/ip');
        $this->mysql['port'] = $this->getParameter('mysql/port');
        $this->mysql['name'] = $this->getParameter('mysql/name');
        $this->mysql['password'] = $this->getParameter('mysql/password');
        $this->mysql['database'] = $this->getParameter('mysql/database');

        // Connect to the database if credentials exist.
        if ($this->mysql['database']) {
            $this->mysqliConnect();
        }
    }

    /**
     * Return the value of a parameter in the YML config file.
     *
     * @param string $parameter_string
     *   Property name or path to parameter as located in the YML config
     *   file beneath the 'parameters' value.
     *
     * @return mixed|NULL
     *   The value of the parameter or NULL if no property found.
     */
    protected function getParameter($parameter_string) {
        $parameters = explode('/', $parameter_string);
        $config = $this->parameters;

        while($parameter = array_shift($parameters)) {
            if (isset($config["$parameter"])) {
                $config = $config["$parameter"];
            } else {
                $config = NULL;
            }
        }

        return $config;
    }

    /**
     * Connect to the drupal database.
     *
     * @return resource
     *   The database resource.
     */
    protected function mysqliConnect() {
        if (isset($this->ssh['ip'])) {
            @shell_exec("ssh -i {$this->ssh['key']} -f -L 3307:127.0.0.1:3306 {$this->ssh['user']}@{$this->ssh['ip']} sleep 60 > /dev/null 2>&1");
            $this->db = mysqli_connect('127.0.0.1', $this->mysql['name'], $this->mysql['password'], $this->mysql['database'], 3307);
        } else {
            $this->db = mysqli_connect($this->mysql['ip'], $this->mysql['name'], $this->mysql['password'], $this->mysql['database'], $this->mysql['port']);
        }

        if (!$this->db || $this->db->connect_error || mysqli_connect_error()) {
            throw new Exception('Connect Error (' . mysqli_connect_errno() . ') ' . mysqli_connect_error());
        }

        return $this->db;
    }

}
