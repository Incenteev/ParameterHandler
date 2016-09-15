<?php

namespace Incenteev\ParameterHandler\Processor;

use Symfony\Component\Yaml\Yaml;

class YamlProcessor extends AbstractProcessor
{
    /**
     * Persists an array to a file in YAML format.
     *
     * {@inheritdoc}
     */
    protected function writeFile($filename, array $configuration)
    {
        return
            false !== file_put_contents(
                $filename, "# This file is auto-generated during the composer install\n".Yaml::dump($configuration, 99)
            );
    }
}
