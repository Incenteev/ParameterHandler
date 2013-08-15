<?php

namespace Incenteev\ParameterHandler;

use Composer\IO\IOInterface;
use Composer\Script\Event;
use Symfony\Component\Yaml\Inline;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Yaml;

class ScriptHandler
{
    public static function buildParameters(Event $event)
    {
        $extras = $event->getComposer()->getPackage()->getExtra();

        if (!isset($extras['incenteev-parameters'])) {
            throw new \InvalidArgumentException('The parameter handler needs to be configured through the extra.incenteev-parameters setting.');
        }

        $configs = $extras['incenteev-parameters'];

        if (!is_array($configs)) {
            throw new \InvalidArgumentException('The extra.incenteev-parameters setting must be an array or a configuration object.');
        }

        if (array_keys($configs) !== range(0, count($configs) - 1)) {
            $configs = array($configs);
        }

        foreach ($configs as $config) {
            if (!is_array($config)) {
                throw new \InvalidArgumentException('The extra.incenteev-parameters setting must be an array of configuration objects.');
            }

            self::processFile($config, $event->getIO());
        }
    }

    private static function processFile(array $config, IOInterface $io)
    {
        $config = self::processConfig($config);

        $realFile = $config['file'];
        $parameterKey = $config['parameter-key'];

        $exists = is_file($realFile);

        $yamlParser = new Parser();

        $action = $exists ? 'Updating' : 'Creating';
        $io->write(sprintf('<info>%s the "%s" file</info>', $action, $realFile));

        // Find the expected params
        $expectedValues = $yamlParser->parse(file_get_contents($config['dist-file']));
        if (!isset($expectedValues[$parameterKey])) {
            throw new \InvalidArgumentException('The dist file seems invalid.');
        }
        $expectedParams = (array) $expectedValues[$parameterKey];

        // find the actual params
        $actualValues = array($parameterKey => array());
        if ($exists) {
            $existingValues = $yamlParser->parse(file_get_contents($realFile));
            if (!is_array($existingValues)) {
                throw new \InvalidArgumentException(sprintf('The existing "%s" file does not contain an array', $realFile));
            }
            $actualValues = array_merge($actualValues, $existingValues);
        }

        $actualValues[$parameterKey] = self::processParams($config, $io, $expectedParams, (array) $actualValues[$parameterKey]);

        // Preserve other top-level keys than `$parameterKey` in the file
        foreach ($expectedValues as $key => $setting) {
            if (!array_key_exists($key, $actualValues)) {
                $actualValues[$key] = $setting;
            }
        }

        if (!is_dir($dir = dirname($realFile))) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($realFile, "# This file is auto-generated during the composer install\n" . Yaml::dump($actualValues, 99));
    }

    private static function processConfig(array $config)
    {
        if (empty($config['file'])) {
            throw new \InvalidArgumentException('The extra.incenteev-parameters.file setting is required to use this script handler.');
        }

        if (empty($config['dist-file'])) {
            $config['dist-file'] = $config['file'].'.dist';
        }

        if (!is_file($config['dist-file'])) {
            throw new \InvalidArgumentException(sprintf('The dist file "%s" does not exist. Check your dist-file config or create it.', $config['dist-file']));
        }

        if (empty($config['parameter-key'])) {
            $config['parameter-key'] = 'parameters';
        }

        return $config;
    }

    private static function processParams(array $config, IOInterface $io, $expectedParams, $actualParams)
    {
        // Grab values for parameters that were renamed
        $renameMap = empty($config['rename-map']) ? array() : (array) $config['rename-map'];
        $actualParams = array_replace($actualParams, self::processRenamedValues($renameMap, $actualParams));

        $keepOutdatedParams = false;
        if (isset($config['keep-outdated'])) {
            $keepOutdatedParams = (boolean) $config['keep-outdated'];
        }

        if (!$keepOutdatedParams) {
            // Remove the outdated params
            foreach ($actualParams as $key => $value) {
                if (!array_key_exists($key, $expectedParams)) {
                    unset($actualParams[$key]);
                }
            }
        }

        $envMap = empty($config['env-map']) ? array() : (array) $config['env-map'];

        // Add the params coming from the environment values
        $actualParams = array_replace($actualParams, self::getEnvValues($envMap));

        return self::getParams($io, $expectedParams, $actualParams);
    }

    private static function getEnvValues(array $envMap)
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

    private static function processRenamedValues(array $renameMap, array $actualParams)
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

    private static function getParams(IOInterface $io, array $expectedParams, array $actualParams)
    {
        // Simply use the expectedParams value as default for the missing params.
        if (!$io->isInteractive()) {
            return array_replace($expectedParams, $actualParams);
        }

        $isStarted = false;

        foreach ($expectedParams as $key => $message) {
            if (array_key_exists($key, $actualParams)) {
                continue;
            }

            if (!$isStarted) {
                $isStarted = true;
                $io->write('<comment>Some parameters are missing. Please provide them.</comment>');
            }

            $default = Inline::dump($message);
            $value = $io->ask(sprintf('<question>%s</question> (<comment>%s</comment>): ', $key, $default), $default);

            $actualParams[$key] = Inline::parse($value);
        }

        return $actualParams;
    }
}
