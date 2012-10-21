<?php

namespace LogSafe\ParameterHandler;

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

        if (empty($extras['logsafe-parameters']['file'])) {
            throw new \InvalidArgumentException('The extra.logsafe-parameters.file setting is required to use this script handler.');
        }

        $realFile = $extras['logsafe-parameters']['file'];

        if (empty($extras['logsafe-parameters']['dist-file'])) {
            $distFile = $realFile.'.dist';
        } else {
            $distFile = $extras['logsafe-parameters']['dist-file'];
        }

        if (!is_file($distFile)) {
            throw new \InvalidArgumentException(sprintf('The dist file "%s" does not exist. Check your dist-file config or create it.', $distFile));
        }

        $exists = is_file($realFile);

        $yamlParser = new Parser();
        $io = $event->getIO();

        if ($exists) {
            $io->write(sprintf('<info>Updating the "%s" file.</info>', $realFile));
        } else {
            $io->write(sprintf('<info>Creating the "%s" file.</info>', $realFile));
        }

        // Find the expected params
        $expectedValues = $yamlParser->parse(file_get_contents($distFile));
        if (!isset($expectedValues['parameters'])) {
            throw new \InvalidArgumentException('The dist file seems invalid.');
        }
        $expectedParams = (array) $expectedValues['parameters'];

        // find the actual params
        $actualValues = array('parameters' => array());
        if ($exists) {
            $actualValues = array_merge($actualValues, $yamlParser->parse(file_get_contents($realFile)));
        }
        $actualParams = (array) $actualValues['parameters'];

        // Remove the outdated params
        foreach ($actualParams as $key => $value) {
            if (!array_key_exists($key, $expectedParams)) {
                unset($actualParams[$key]);
            }
        }

        $envMap = empty($extras['logsafe-parameters']['env-map']) ? array() : (array) $extras['logsafe-parameters']['env-map'];

        // Add the params coming from the environment values
        $actualParams = array_replace($actualParams, self::getEnvValues($envMap));

        $actualParams = self::getParams($io, $expectedParams, $actualParams);

        file_put_contents($realFile, "# This file is auto-generated during the composer install\n" . Yaml::dump(array('parameters' => $actualParams)));
    }

    private static function getEnvValues(array $envMap)
    {
        $params = array();
        foreach ($envMap as $env => $param) {
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

            $default = Inline::dump($message);
            $value = $io->ask(sprintf('<question>%s</question> (<comment>%s</comment>):', $key, $default), $default);

            $actualParams[$key] = Inline::parse($value);
        }

        return $actualParams;
    }
}
