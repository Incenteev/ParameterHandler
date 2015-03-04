<?php


namespace Incenteev\ParameterHandler;

use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Yaml;

/**
 * FileLoader
 *
 * @author Robert Schönthal <robert.schoenthal@gmail.com>
 */
class FileHandler
{
    const AUTO_GENERATE_MESSAGE = "# This file is auto-generated during the composer install\n";

    /**
     * @var Parser
     */
    private $yamlParser;

    public function __construct(Parser $parser)
    {
        $this->yamlParser = $parser;
    }

    public function read($file, $type = 'yaml')
    {
        if (!is_readable($file)) {
            throw new \InvalidArgumentException(sprintf('unreadable config file "%s"', $file));
        }

        switch ($type) {
            case 'dotenv' :
                return $this->loadEnvFile($file);
            case 'yaml' :
            default :
                return $this->loadYamlFile($file);
        }
    }

    public function write($data, $file, $type = 'yaml')
    {
        if (!is_dir($dir = dirname($file))) {
            mkdir($dir, 0755, true);
        }

        switch ($type) {
            case 'dotenv' :
                $this->writeEnvFile($data, $file);
                break;
            case 'yaml' :
            default :
                $this->writeYamlFile($data, $file);
                break;
        }
    }

    private function loadEnvFile($file)
    {
        $vars = [];

        $autodetect = ini_get('auto_detect_line_endings');
        ini_set('auto_detect_line_endings', '1');
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        ini_set('auto_detect_line_endings', $autodetect);

        foreach ($lines as $line) {
            // Disregard comments
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            // Only use non-empty lines that look like setters
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $vars[$key] = $value;
            }
        }

        return $vars;
    }

    private function loadYamlFile($file)
    {
        return $this->yamlParser->parse(file_get_contents($file));
    }

    private function writeEnvFile($data, $file)
    {
        $h = fopen($file, 'w');
        fwrite($h, self::AUTO_GENERATE_MESSAGE);

        foreach ($data as $name => $value) {
            fwrite($h, sprintf("%s=%s\n", $name, $value));
        }

        fclose($h);
    }

    private function writeYamlFile($data, $file)
    {
        file_put_contents($file, self::AUTO_GENERATE_MESSAGE.Yaml::dump($data, 99));
    }
}
