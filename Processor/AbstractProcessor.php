<?php

namespace Incenteev\ParameterHandler\Processor;

use Composer\IO\IOInterface;
use Incenteev\ParameterHandler\Parser\JsonParser;
use Symfony\Component\Yaml\Parser as YamlParser;
use Symfony\Component\Yaml\Inline;

abstract class AbstractProcessor
{
    /**
     * IO Interface of composer used for displaying messages.
     *
     * @var IOInterface
     */
    protected $io;

    /**
     * Parser.
     *
     * @var IOInterface
     */
    protected $parser;

    /**
     * Constructor.
     *
     * @param IOInterface           $io     Composer IO Interface
     * @param YamlParser|JsonParser $parser Instance of parser to parse configuration
     */
    public function __construct(IOInterface $io, $parser)
    {
        $this->parser = $parser;
        $this->io     = $io;
    }

    /**
     * {@inheritdoc}
     *
     * @throws \InvalidArgumentException|\RuntimeException
     */
    public function processFile(array $config)
    {
        $config = $this->processConfig($config);

        $realFile     = $config['file'];
        $parameterKey = $config['parameter-key'];

        $exists = is_file($realFile);

        $action = $exists ? 'Updating' : 'Creating';
        $this->io->write(sprintf('<info>%s the "%s" file</info>', $action, $realFile));

        // Find the expected params
        $expectedValues = $this->parser->parse(file_get_contents($config['dist-file']));

        if (!isset($expectedValues[$parameterKey])) {
            throw new \InvalidArgumentException(sprintf('The top-level key %s is missing.', $parameterKey));
        }
        $expectedParams = (array) $expectedValues[$parameterKey];

        // find the actual params
        $actualValues = array_merge(
        // Preserve other top-level keys than `$parameterKey` in the file
            $expectedValues,
            [$parameterKey => []]
        );
        if ($exists) {
            $existingValues = $this->parser->parse(file_get_contents($realFile));
            if ($existingValues === null) {
                $existingValues = [];
            }
            if (!is_array($existingValues)) {
                throw new \InvalidArgumentException(sprintf('The existing "%s" file does not contain an array', $realFile));
            }
            $actualValues = array_merge($actualValues, $existingValues);
        }

        $actualValues[$parameterKey] = $this->processParams($config, $expectedParams, (array) $actualValues[$parameterKey]);

        if (!is_dir($dir = dirname($realFile)) && (!@mkdir($dir, 0755, true) && !is_dir($dir))) {
            throw new \RuntimeException(
                sprintf('Error while creating directory "%s". Check path and permissions.', $dir)
            );
        }

        $this->writeFile($realFile, $actualValues);
    }

    /**
     * @param array $config
     *
     * @return array
     *
     * @throws \InvalidArgumentException
     */
    protected function processConfig(array $config)
    {
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

    protected function processParams(array $config, array $expectedParams, array $actualParams)
    {
        // Grab values for parameters that were renamed
        $renameMap    = empty($config['rename-map']) ? [] : (array) $config['rename-map'];
        $actualParams = array_replace($actualParams, $this->processRenamedValues($renameMap, $actualParams));

        $keepOutdatedParams = false;
        if (isset($config['keep-outdated'])) {
            $keepOutdatedParams = (boolean) $config['keep-outdated'];
        }

        if (!$keepOutdatedParams) {
            $actualParams = array_intersect_key($actualParams, $expectedParams);
        }

        $envMap = empty($config['env-map']) ? [] : (array) $config['env-map'];

        // Add the params coming from the environment values
        $actualParams = array_replace($actualParams, $this->getEnvValues($envMap));

        return $this->getParams($expectedParams, $actualParams);
    }

    /**
     * Parses environments variables by map and resolves correct types.
     * As environment variables can only be strings, they are also parsed to allow specifying null, false,
     * true or numbers easily.
     *
     * @param array $envMap Map used to map data from environment variable name to parameter name.
     *
     * @return array
     */
    protected function getEnvValues(array $envMap)
    {
        $params = [];
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
            return array_replace($expectedParams, $actualParams);
        }

        $isStarted = false;

        foreach ($expectedParams as $key => $value) {
            if (array_key_exists($key, $actualParams)) {
                continue;
            }

            if (!$isStarted) {
                $isStarted = true;
                $this->io->write('<comment>Some parameters are missing. Please provide them.</comment>');
            }

            $default = Inline::dump($value);

            $value = $this->io->ask(sprintf('<question>%s</question> (<comment>%s</comment>): ', $key, $default), $default);

            $actualParams[$key] = Inline::parse($value);
        }

        return $actualParams;
    }

    /**
     * Persists configuration.
     *
     * @param string $file          Filename to persist configuration to.
     * @param array  $configuration Configuration to persist as an array.
     *
     * @return bool TRUE after successful persisting the file, otherwise FALSE
     */
    abstract protected function writeFile($file, array $configuration);
}