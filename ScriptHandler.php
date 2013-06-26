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

        $filesToProcess = self::getFiles($extras['incenteev-parameters']);

        foreach ($filesToProcess as $files) {

            self::processFiles($files, $event);
        }
    }

    /**
     * Reads file array and processes each file accordingly
     *
     * @static
     * @param array $files
     * @param \Composer\Script\Event $event
     * @throws \InvalidArgumentException
     */
    private static function processFiles(array $files, Event $event)
    {
        $extras = $event->getComposer()->getPackage()->getExtra();

        $realFile = $files['file'];

        if (empty($files['dist-file'])) {
            $distFile = $realFile . '.dist';
        } else {
            $distFile = $files['dist-file'];
        }

        $keepOutdatedParams = false;
        if (isset($extras['incenteev-parameters']['keep-outdated'])) {
            $keepOutdatedParams = (boolean) $extras['incenteev-parameters']['keep-outdated'];
        }

        if (!is_file($distFile)) {
            throw new \InvalidArgumentException(sprintf(
                'The dist file "%s" does not exist. Check your dist-file config or create it.',
                $distFile
            ));
        }

        $exists = is_file($realFile);

        $yamlParser = new Parser();
        $io = $event->getIO();

        $action = $exists ? 'Updating' : 'Creating';
        $io->write(sprintf('<info>%s the "%s" file.</info>', $action, $realFile));

        // Find the expected params
        $expectedValues = $yamlParser->parse(file_get_contents($distFile));
        if (!isset($expectedValues['parameters'])) {
            throw new \InvalidArgumentException('The dist file seems invalid.');
        }
        $expectedParams = (array) $expectedValues['parameters'];

        // find the actual params
        $actualValues = array('parameters' => array());
        if ($exists) {
            $existingValues = $yamlParser->parse(file_get_contents($realFile));
            if (!is_array($existingValues)) {
                throw new \InvalidArgumentException(sprintf(
                    'The existing "%s" file does not contain an array',
                    $realFile
                ));
            }
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

        // Add the params coming from the environment values
        $actualParams = array_replace($actualParams, self::getEnvValues($envMap));

        $actualParams = self::getParams($io, $expectedParams, $actualParams);

        file_put_contents(
            $realFile,
          "# This file is auto-generated during the composer install\n" . Yaml::dump(
              array('parameters' => $actualParams)
          )
        );
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

            $default = Inline::dump($message);
            $value = $io->ask(sprintf('<question>%s</question> (<comment>%s</comment>):', $key, $default), $default);

            $actualParams[$key] = Inline::parse($value);
        }

        return $actualParams;
    }

    /**
     * Allows for single or multiple files to be processed.
     *
     * @author Micah Breedlove <druid628@gmail.com>
     * @static
     * @param array $incenteevParameters
     * @return array
     */
    private static function getFiles(array $incenteevParameters)
    {
        $files = array();

        // Single File
        if (is_string($incenteevParameters['file'])) {

            $files['file']['file'] = $incenteevParameters['file'];
            if (isset($incenteevParameters['dist-file']) && is_string($incenteevParameters['dist-file'])) {
                $files['file']['dist-file'] = $incenteevParameters['dist-file'];
            }

            return $files;
        }

        // Multi File
        foreach (array_keys($incenteevParameters['file']) as $file) {
            $isDistFile = (false !== strpos($file, "-dist"));
            $filename = $isDistFile ? substr($file, 0, strpos($file, "-dist")) : $file;

            if (!isset($files[$filename])) {
                $files[$filename] = array();
            }

            if ($isDistFile) {
                $files[$filename]['dist-file'] = $incenteevParameters['file'][$file];
            } else {
                $files[$filename]['file'] = $incenteevParameters['file'][$file];
            }
        }

        return $files;
    }
}
