#!/usr/bin/env php
<?php

namespace adt;

// Require Zend classes for argument validation
require_once __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'Zend' . DIRECTORY_SEPARATOR . 'Console' . DIRECTORY_SEPARATOR . 'Exception' . DIRECTORY_SEPARATOR . 'ExceptionInterface.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'Zend' . DIRECTORY_SEPARATOR . 'Console' . DIRECTORY_SEPARATOR . 'Exception' . DIRECTORY_SEPARATOR . 'BadMethodCallException.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'Zend' . DIRECTORY_SEPARATOR . 'Console' . DIRECTORY_SEPARATOR . 'Exception' . DIRECTORY_SEPARATOR . 'InvalidArgumentException.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'Zend' . DIRECTORY_SEPARATOR . 'Console' . DIRECTORY_SEPARATOR . 'Exception' . DIRECTORY_SEPARATOR . 'RuntimeException.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'Zend' . DIRECTORY_SEPARATOR . 'Console' . DIRECTORY_SEPARATOR . 'Getopt.php';

require_once 'System.php';
var_dump(class_exists('System', false));

class ADT {

    /************************************************************************************************
     * CLASS FIELDS
     ***********************************************************************************************/

    /**
     * Command line options
     *
     * @var \Zend\Console\Getopt
     */
    protected $_options = null;
    /**
     * Default options
     *
     * @var array
     */
    protected $_defaultOptions = array(
        // TODO set up reasonable defaults
        'help' => null,
        'wpconfig' => './wp-config.php',
        'host' => 'DB_HOST',
        'name' => 'DB_NAME',
        'password' => 'DB_PASSWD',
        'user' => 'DB_USER',
        'verbose' => 0,
        'import' => null,
        'dump' => true,
        'localize' => null,
        'remotize' => null,
    );

    /************************************************************************************************
     * PUBLIC METHODS
     ***********************************************************************************************/

    /**
     * Constructor
     *
     * @return void
     */
    public function __construct() {
        // Parse & validate the command line options
        try {
            $this -> _options = new \Zend\Console\Getopt( array(
                // TODO parse ini file before getting cli options
                'help|?' => 'Display help message',
                'wpconfig|w-s' => 'Path to wp_config.php (default: ./wp_config.php)',
                'host|h-s' => 'Database host URI',
                'name|n-s' => 'Database name',
                'password|p-s' => 'Database password',
                'user|u-s' => 'Database user',
                'verbose|v-i' => 'Output verbose progress information',
                'import|i-' => 'Import an sql file into the database',
                'dump|d-' => 'Dump a database into an sql file',
                'localize|l-' => 'Localize a database dump',
                'remotize|r-' => 'Remotize a database dump',
            ));
            // Override default options with given options from cli
            $options = $this -> _defaultOptions;
            foreach ($this->_options->getOptions() as $option) {
                $options[$option] = $this -> _options -> getOption($option);
            }
            // In case of errors: Die with a usage message
        } catch(\Zend\Console\Exception\ExceptionInterface $e) {
            $this -> _usage($e -> getMessage());
        }
        // TODO run appropriate methods
    }

    /************************************************************************************************
     * PRIVATE METHODS
     ***********************************************************************************************/

    /**
     * Die with a usage message
     *
     * @param string $message               Message
     * @return void
     */
    protected function _usage($message = '') {
        die("\n" . trim($message . "\n\n" . $this -> _options -> getUsageMessage()) . "\n\n");
    }

}

new ADT();
?>