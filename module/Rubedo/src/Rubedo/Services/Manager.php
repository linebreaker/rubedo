<?php
/**
 * Rubedo -- ECM solution
 * Copyright (c) 2013, WebTales (http://www.webtales.fr/).
 * All rights reserved.
 * licensing@webtales.fr
 *
 * Open Source License
 * ------------------------------------------------------------------------------------------
 * Rubedo is licensed under the terms of the Open Source GPL 3.0 license. 
 *
 * @category   Rubedo
 * @package    Rubedo
 * @copyright  Copyright (c) 2012-2013 WebTales (http://www.webtales.fr)
 * @license    http://www.gnu.org/licenses/gpl.html Open Source GPL 3.0 license
 */
namespace Rubedo\Services;

use Rubedo\Interfaces\Services\IServicesManager, Rubedo\Interfaces\config;

/**
 * Service Manager Interface
 *
 * Proxy to actual services, offer a static getService and handle dependancy
 * injection
 *
 * @author jbourdin
 * @category Rubedo
 * @package Rubedo
 */
class Manager implements IServicesManager
{

    protected static $_serviceLocator;

    /**
     * array of current service parameters
     *
     * @var array
     */
    protected static $_servicesOptions;

    /**
     * Array of mock service
     */
    protected static $_mockServicesArray = array();

    /**
     * Reset the mockObject array for isolation purpose
     */
    public static function resetMocks()
    {
        self::$_mockServicesArray = array();
    }

    /**
     * Set a mock service for testing purpose
     *
     * @param string $serviceName
     *            Name of the service overridden
     * @param object $obj
     *            mock object substituted to the service
     */
    public static function setMockService($serviceName, $obj)
    {
        self::$_mockServicesArray[$serviceName] = $obj;
    }

    /**
     * Setter of services parameters, to init them from bootstrap
     *
     * @param array $options            
     */
    public static function setOptions($options)
    {
        if ('array' !== gettype($options)) {
            throw new \Rubedo\Exceptions\Server('Services parameters should be an array');
        }
        self::$_servicesOptions = $options;
    }

    /**
     * getter of services parameters, to init them from bootstrap
     *
     * @return array array of all the services
     */
    public static function getOptions()
    {
        return self::$_servicesOptions;
    }

    /**
     * Public static method to get an instance of the service given by its
     * name
     *
     * Return an instance of the manager containing the actual service object
     *
     * @param string $serviceName
     *            name of the service
     * @return static instance of the manager
     */
    public static function getService($serviceName)
    {
        return self::getServiceLocator()->get($serviceName);
        
        if (gettype($serviceName) !== 'string') {
            throw new \Rubedo\Exceptions\Server('getService only accept string argument');
        }
        
        if (isset(static::$_mockServicesArray[$serviceName])) {
            return static::$_mockServicesArray[$serviceName];
        }
        
        $serviceClassName = self::resolveName($serviceName);
        
        if (count(config::getConcerns($serviceName))) {
            return new Proxy($serviceClassName, $serviceName);
        } else {
            return new $serviceClassName();
        }
    }

    /**
     * Resolve the service name to the service class name for dependancy
     * injection
     *
     * @param string $serviceName
     *            name of the service
     * @return string class to instanciate
     */
    protected static function resolveName($serviceName)
    {
        $options = self::$_servicesOptions;
        
        if (isset($options[$serviceName]['class'])) {
            $className = $options[$serviceName]['class'];
        } else {
            throw new \Rubedo\Exceptions\Server('Classe name for ' . $serviceName . ' service should be defined in config file');
        }
        if (! $interfaceName = config::getInterface($serviceName)) {
            throw new \Rubedo\Exceptions\Server($serviceName . ' isn\'t declared in service interface config');
        }
        if (! in_array($interfaceName, class_implements($className))) {
            throw new \Rubedo\Exceptions\Server($className . ' don\'t implement ' . $interfaceName);
        }
        
        return $className;
    }

    /**
     *
     * @return the $_serviceLocator
     */
    public static function getServiceLocator()
    {
        return Manager::$_serviceLocator;
    }

    /**
     *
     * @param field_type $_serviceLocator            
     */
    public static function setServiceLocator($_serviceLocator)
    {
        Manager::$_serviceLocator = $_serviceLocator;
    }
}