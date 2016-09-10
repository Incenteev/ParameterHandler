<?php

namespace Incenteev\ParameterHandler\Processor;

interface ProcessorInterface
{
    /**
     * Processes a configuration.
     *
     * @param array $config Configuration collection.
     *
     * @see https://github.com/Incenteev/ParameterHandler/blob/master/README.md
     */
    public function processFile(array $config);
}
