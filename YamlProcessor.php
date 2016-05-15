<?php

namespace Incenteev\ParameterHandler;

use Symfony\Component\Yaml\Inline;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Yaml;

class YamlProcessor implements FileProcessorInterface
{
    private $yamlParser;

    public function __construct()
    {
        $this->yamlParser = new Parser();
    }

    public function dump($file, $values)
    {
        file_put_contents($file, "# This file is auto-generated during the composer install\n" . Yaml::dump($values, 99));
    }

    public function parse($file)
    {
        return $this->yamlParser->parse(file_get_contents($file));
    }

    public function supports($extension)
    {
        return $extension === 'yml' || $extension === 'yaml';
    }
}
