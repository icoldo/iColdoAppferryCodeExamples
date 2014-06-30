<?php

namespace BnpServiceDefinition\Dsl\Extension;

use BnpServiceDefinition\Dsl\Extension\Feature\FunctionProviderInterface;
use Zend\ServiceManager\Exception\ServiceNotCreatedException;
use Zend\ServiceManager\Exception\ServiceNotFoundException;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

class ServiceFunctionProvider implements
    FunctionProviderInterface,
    ServiceLocatorAwareInterface
{
    const SERVICE_KEY = 'BnpServiceDefinition\Dsl\Extension\ServiceFunctionProvider';

    /**
     * @var string
     */
    protected $serviceName;

    /**
     * @var ServiceLocatorInterface
     */
    protected $services;

    public function __construct($serviceName = null)
    {
        $this->serviceName = null === $serviceName ? static::SERVICE_KEY : null;
    }

    public function getName()
    {
        return 'plugin';
    }

    public function getEvaluator(array $context = array())
    {
        $self = $this;
        return function ($args, $service, $silent = false, $instance = null) use ($self) {
            if (! is_string($service)) {
                return $service;
            }

            return $self->getService($service, $silent, $instance);
        };
    }

    public function getCompiler()
    {
        return function ($service, $silent = false, $instance = null) {
            if (! is_string($service)) {
                return $service;
            }

            if ('false' === $silent) {
                $silent = false;
            }
            $silent = $silent ? 'true' : 'false';
            if (! $instance) {
                $instance = 'null';
            } elseif ('\\' !== substr($instance, 1, 1)) {
                $instance = $instance[0] . '\\' . substr($instance, 1);
            }

            return sprintf(
                '$this->services->get(\'%s\')->getService(%s, %s, %s)',
                $this->serviceName,
                $service,
                $silent,
                $instance
            );
        };
    }

    protected function getServiceFromLocator(ServiceLocatorInterface $services, $name, $silent = false, $instance = null)
    {
        $service = null;
        try {
            $service = $services->get($name);
        } catch (ServiceNotFoundException $e) {
            if (! $silent) {
                throw $e;
            }
        } catch (ServiceNotCreatedException $e) {
            if (! $silent) {
                throw $e;
            }
        }

        if (null !== $service && null !== $instance and ! is_object($service) || ! $service instanceof $instance) {
            throw new \RuntimeException();
        }

        return $service;
    }

    public function getService($name, $silent = false, $instance = null)
    {
        return $this->getServiceFromLocator($this->getServiceLocator(), $name, $silent, $instance);
    }

    /**
     * Set service locator
     *
     * @param ServiceLocatorInterface $serviceLocator
     */
    public function setServiceLocator(ServiceLocatorInterface $serviceLocator)
    {
        $this->services = $serviceLocator;
    }

    /**
     * Get service locator
     *
     * @return ServiceLocatorInterface
     */
    public function getServiceLocator()
    {
        return $this->services;
    }
}