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

        if (empty($extras['incenteev-parameters']['file'])) {
            throw new \InvalidArgumentException('The extra.incenteev-parameters.file setting is required to use this script handler.');
        }

        $realFile = $extras['incenteev-parameters']['file'];

        if (empty($extras['incenteev-parameters']['dist-file'])) {
            $distFile = $realFile.'.dist';
        } else {
            $distFile = $extras['incenteev-parameters']['dist-file'];
        }

        $keepOutdatedParams = false;
        if (isset($extras['incenteev-parameters']['keep-outdated'])) {
            $keepOutdatedParams = (boolean)$extras['incenteev-parameters']['keep-outdated'];
        }

        if (!is_file($distFile)) {
            throw new \InvalidArgumentException(sprintf('The dist file "%s" does not exist. Check your dist-file config or create it.', $distFile));
        }

        $exists = is_file($realFile);

        $yamlParser = new Parser();
        $io = $event->getIO();

        $action = $exists ? 'Updating' : 'Creating';
        $io->write(sprintf('<info>%s the "%s" file.</info>', $action, $realFile));

        // Find the expected params
        $parametersDistFiles = self::getVendorsParametersDistFiles();
        $expectedValues = array();
        foreach ($parametersDistFiles as $singleParameterFile) {
            $expectedValues = array_merge($expectedValues, self::loadParameterFile($singleParameterFile));
        }
        $parametersDistGlobal = $yamlParser->parse(file_get_contents($distFile));
        $expectedValues = array('parameters'=> array_merge($expectedValues['parameters'], $parametersDistGlobal['parameters']));

        if (!isset($expectedValues['parameters'])) {
            throw new \InvalidArgumentException('The dist file seems invalid.');
        }
        $expectedParams = (array) $expectedValues['parameters'];

        // find the actual params
        $actualValues = array('parameters' => array());

        if ($exists) {
            $existingValues = self::loadParameterFile($realFile);
            $actualValues = array_merge($actualValues, $existingValues);
        }
        $actualParams = (array) $actualValues['parameters'];

        if (!$keepOutdatedParams) {
            // Remove the outdated params
            foreach ($actualParams as $key => $value) {
                if (!array_key_exists($key, $expectedParams)) {
                    unset($actualParams[$key]);
                }
            }
        }

        $envMap = empty($extras['incenteev-parameters']['env-map']) ? array() : (array) $extras['incenteev-parameters']['env-map'];
        $YmlDepth = isset($extras['incenteev-parameters']['yml-depth']) ? $extras['incenteev-parameters']['yml-depth'] : 3;

        // Add the params coming from the environment values
        $actualParams = array_replace($actualParams, self::getEnvValues($envMap));

        $actualParams = self::getParams($io, $expectedParams, $actualParams);

        file_put_contents($realFile, "# This file is auto-generated during the composer install\n" . Yaml::dump(array('parameters' => $actualParams), $YmlDepth));
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

            if (is_array($message)) {
                $actualParams[$key] = self::askForArray($io, $key, $message);
            } else {
                $actualParams[$key] = self::askForInline($io, $key, $message);
            }
        }

        return $actualParams;
    }

    private static function askForArray(IOInterface $io, $key, $message)
    {
        $params = array();

        foreach ($message as $simpleMessage) {
            if (is_string($simpleMessage)) {

                return self::askForInline($io, $key, $message);
            }

            $default   = current($simpleMessage);
            $insideKey = key($simpleMessage);
            if (is_array($default)) {
                $params[$insideKey] = self::askForArray($io, $key, $default);
            } else {
                $value = $io->ask(sprintf('<question>%s</question> (<comment>%s</comment>):', $key, $default), $default);
                $params[$insideKey] = Inline::parse($value);
            }
        }

        return $params;
    }

    private static function askForInline(IOInterface $io, $key, $message)
    {
        $default = Inline::dump($message);
        $value = $io->ask(sprintf('<question>%s</question> (<comment>%s</comment>):', $key, $default), $default);

        return Inline::parse($value);
    }

    private static function getVendorsParametersDistFiles()
    {
        $configFiles = array();

        foreach (self::getVendorsPaths() as $key => $vendor)
        {
            $parametersDistFile = current($vendor) . '/' . str_replace('\\', '/', $key) . '/Resources/config/app/parameters.yml.dist';
            if (is_file($parametersDistFile)) {
                $configFiles[] = $parametersDistFile;
            }
        }

        return $configFiles;
    }

    private static function getVendorsPaths()
    {
        $vendorDir = dirname(dirname(dirname(dirname(dirname(__FILE__)))));

        return include($vendorDir . '/composer/autoload_namespaces.php');
    }

    private static function loadParameterFile($realFile)
    {
        $yamlParser = new Parser();

        $existingValues = $yamlParser->parse(file_get_contents($realFile));
        if (!is_array($existingValues)) {
            throw new \InvalidArgumentException(sprintf('The existing "%s" file does not contain an array', $realFile));
        }

        return $existingValues;

    }
}
