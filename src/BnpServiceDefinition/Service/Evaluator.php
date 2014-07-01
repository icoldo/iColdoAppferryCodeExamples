<?php

namespace BnpServiceDefinition\Service;

use BnpServiceDefinition\Definition\DefinitionRepository;
use BnpServiceDefinition\Definition\MethodDefinition;
use BnpServiceDefinition\Dsl\Language;

class Evaluator
{
    /**
     * @var Language
     */
    protected $language;

    /**
     * @var ReferenceResolver
     */
    protected $referenceResolver;

    public function __construct(Language $language, ReferenceResolver $resolver)
    {
        $this->language = $language;
        $this->referenceResolver = $resolver;
    }

    public function evaluate($definitionName, DefinitionRepository $repository)
    {
        $definition = $repository->getServiceDefinition($definitionName);
        $className = $this->evaluateArgument($definition->getClass());

        if (! class_exists($className, true)) {
            throw new \RuntimeException(sprintf(
                '%s definition resolved to the class %s, which does no exit',
                $definitionName,
                $className
            ));
        }

        $reflection = new \ReflectionClass($className);
        $service = $reflection->newInstanceArgs($this->evaluateArguments($definition->getArgs()));

        $context = array('service' => $service);
        foreach (array_values($definition->getMethodCalls()) as $i => $methodCall) {
            /** @var $methodCall MethodDefinition */
            if (null !== $methodCall->getCondition()) {
                foreach ($methodCall->getCondition() as $condition) {
                    if ('true' !== $this->evaluateArgument($condition, $context)) {
                        continue 2;
                    }
                }
            }

            $method = $this->evaluateArgument($methodCall->getName(), $context);
            if (! is_string($method)) {
                throw new \RuntimeException(sprintf(
                    'A method call can only be a string, %s provided, as %d method call for the %s service definition',
                    gettype($method),
                    $i + 1,
                    $definitionName
                ));
            } elseif (! method_exists($service, $method)) {
                throw new \RuntimeException(sprintf(
                    'Requested method "%s::%s" (index %d) does not exists or is not visible for %s service definition',
                    get_class($service),
                    $method,
                    $i + 1,
                    $definitionName
                ));
            }

            call_user_func_array(
                array($service, $method),
                $this->evaluateArguments($methodCall->getParams(), $context)
            );
        }

        return $service;
    }

    protected function evaluateArgument($argument, array $context = array())
    {
        return $this->language->evaluate($this->referenceResolver->resolveReference($argument), $context);
    }

    protected function evaluateArguments(array $args, array $context = array())
    {
        $language = $this->language;
        return array_map(
            function ($arg) use ($language, $context) {
                return $language->evaluate($arg, $context);
            },
            $this->referenceResolver->resolveReferences($args)
        );
    }
}