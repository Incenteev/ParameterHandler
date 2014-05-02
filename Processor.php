<?php

namespace Incenteev\ParameterHandler;

use Composer\IO\IOInterface;
use Symfony\Component\Yaml\Inline;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Yaml;

class Processor
{
    private $io;
    private $isStarted;

    public function __construct(IOInterface $io)
    {
        $this->io        = $io;
        $this->isStarted = false;
    }

    public function processFile(array $config)
    {
        $config = $this->processConfig($config);

        $realFile     = $config['file'];
        $parameterKey = $config['parameter-key'];

        $exists = is_file($realFile);

        $yamlParser = new Parser();

        $action = $exists ? 'Updating' : 'Creating';
        $this->io->write(sprintf('<info>%s the "%s" file</info>', $action, $realFile));

        // Find the expected params
        $expectedValues = $yamlParser->parse(file_get_contents($config['dist-file']));
        if (!isset($expectedValues[$parameterKey])) {
            throw new \InvalidArgumentException('The dist file seems invalid.');
        }
        $expectedParams = (array) $expectedValues[$parameterKey];

        // find the actual params
        $actualValues = array_merge(
            // Preserve other top-level keys than `$parameterKey` in the file
            $expectedValues,
            array($parameterKey => array())
        );
        if ($exists) {
            $existingValues = $yamlParser->parse(file_get_contents($realFile));
            if ($existingValues === null) {
                $existingValues = array();
            }
            if (!is_array($existingValues)) {
                throw new \InvalidArgumentException(sprintf('The existing "%s" file does not contain an array', $realFile));
            }
            $actualValues = array_merge($actualValues, $existingValues);
        }

        $actualValues[$parameterKey] = $this->processParams($config, $expectedParams, (array) $actualValues[$parameterKey]);

        if (!is_dir($dir = dirname($realFile))) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($realFile, "# This file is auto-generated during the composer install\n" . Yaml::dump($actualValues, 99));
    }

    private function processConfig(array $config)
    {
        if (empty($config['file'])) {
            throw new \InvalidArgumentException('The extra.incenteev-parameters.file setting is required to use this script handler.');
        }

        if (empty($config['dist-file'])) {
            $config['dist-file'] = $config['file'] . '.dist';
        }

        if (!is_file($config['dist-file'])) {
            throw new \InvalidArgumentException(sprintf('The dist file "%s" does not exist. Check your dist-file config or create it.', $config['dist-file']));
        }

        if (empty($config['parameter-key'])) {
            $config['parameter-key'] = 'parameters';
        }

        return $config;
    }

    private function processParams(array $config, array $expectedParams, array $actualParams)
    {
        // Grab values for parameters that were renamed
        $renameMap    = empty($config['rename-map']) ? array() : (array) $config['rename-map'];
        $actualParams = array_replace($actualParams, $this->processRenamedValues($renameMap, $actualParams));

        $keepOutdatedParams = false;
        if (isset($config['keep-outdated'])) {
            $keepOutdatedParams = (boolean) $config['keep-outdated'];
        }

        if (!$keepOutdatedParams) {
            $actualParams = array_intersect_key($actualParams, $expectedParams);
        }

        $envMap = empty($config['env-map']) ? array() : (array) $config['env-map'];

        // Add the params coming from the environment values
        $actualParams = array_replace($actualParams, $this->getEnvValues($envMap));

        return $this->getParams($expectedParams, $actualParams);
    }

    private function getEnvValues(array $envMap)
    {
        $params = array();
        foreach ($envMap as $param => $env) {
            $value = getenv($env);
            if ($value) {
                $params[$param] = Inline::parse($value);
            }
        }

        return $params;
    }

    private function processRenamedValues(array $renameMap, array $actualParams)
    {
        foreach ($renameMap as $param => $oldParam) {
            if (array_key_exists($param, $actualParams)) {
                continue;
            }

            if (!array_key_exists($oldParam, $actualParams)) {
                continue;
            }

            $actualParams[$param] = $actualParams[$oldParam];
        }

        return $actualParams;
    }

    private function getParams(array $expectedParams, array $actualParams)
    {
        // Simply use the expectedParams value as default for the missing params.
        if (!$this->io->isInteractive()) {
            return array_replace_recursive($expectedParams, $actualParams);
        }

        $actualParams = $this->provideParams($expectedParams, $actualParams);

        return $actualParams;
    }

    /**
     * @param array  $expectedParams
     * @param array  $actualParams
     * @param string $parentKeys
     *
     * @return array
     */
    private function provideParams(array $expectedParams, array $actualParams, $parentKeys = '')
    {
        foreach ($expectedParams as $paramKey => $paramValue) {
            if (array_key_exists($paramKey, $actualParams) && !is_array($paramValue)) {
                continue;
            }

            if (is_array($paramValue)) {
                if (!array_key_exists($paramKey, $actualParams)) {
                    $actualParams[$paramKey] = array();
                }

                $actualParams[$paramKey] = $this->provideParams($paramValue, $actualParams[$paramKey], $this->getParametersPath($parentKeys, $paramKey));
            } else {
                if (!$this->isStarted) {
                    $this->isStarted = true;
                    $this->io->write('<comment>Some parameters are missing. Please provide them.</comment>');
                }

                $default                 = Inline::dump($paramValue);
                $parametersPath          = $this->getParametersPath($parentKeys, $paramKey);
                $value                   = $this->io->ask(
                    sprintf('<question>%s</question> (<comment>%s</comment>): ', $parametersPath, $default),
                    $default
                );
                $actualParams[$paramKey] = Inline::parse($value);
            }

        }

        return $actualParams;
    }

    /**
     * @param $parentKeys
     * @param $key
     *
     * @return string
     */
    private function getParametersPath($parentKeys, $key)
    {
        return $parentKeys ? $parentKeys . '.' . $key : $key;
    }
}
