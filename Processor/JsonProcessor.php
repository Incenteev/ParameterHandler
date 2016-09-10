<?php

namespace Incenteev\ParameterHandler\Processor;

class JsonProcessor extends AbstractProcessor implements ProcessorInterface
{
    /**
     * Persists an array to a file in JSON format.
     *
     * {@inheritdoc}
     */
    protected function writeFile($filename, array $configuration)
    {
        return
            false !== file_put_contents(
                $filename, $this->parser->dump($configuration)
            );
    }
}
