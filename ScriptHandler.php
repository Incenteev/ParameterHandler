<?php

namespace Incenteev\ParameterHandler;

use Composer\Script\Event;
use Incenteev\ParameterHandler\Parser\JsonParser;
use Incenteev\ParameterHandler\Parser\YamlParser;
use Incenteev\ParameterHandler\Processor\JsonProcessor;
use Incenteev\ParameterHandler\Processor\YamlProcessor;

class ScriptHandler
{
    /**
     * Configuration type YAML.
     *
     * @var string
     */
    const CONFIGURATION_FORMAT_YAML = 'yaml';

    /**
     * Extension of configuration type YAML.
     *
     * @var string
     */
    const FILE_EXTENSION_YAML = 'yml';

    /**
     * Configuration type JSON.
     *
     * @var string
     */
    const CONFIGURATION_FORMAT_JSON = 'json';

    /**
     * Extension of configuration type JSON.
     *
     * @var string
     */
    const FILE_EXTENSION_JSON = 'json';

    /**
     * Collection of handable types.
     *
     * @var array
     */
    protected static $handable = array(
        self::CONFIGURATION_FORMAT_YAML,
        self::CONFIGURATION_FORMAT_JSON,
    );

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

            if (!array_key_exists('file', $config)) {
                throw new \InvalidArgumentException('The extra.incenteev-parameters.file setting is required to use this script handler.');
            }

            $type = self::retrieveConfigurationTypeByFile($config['file']);

            if (self::CONFIGURATION_FORMAT_YAML === $type) {
                $processor = new YamlProcessor($event->getIO(), new YamlParser());
            } elseif (self::FILE_EXTENSION_JSON === $type) {
                $processor = new JsonProcessor($event->getIO(), new JsonParser());
            } else {
                throw new \OutOfBoundsException(
                    sprintf(
                        'Configuration format in file "%s" can not be handled. Currently supported: "%s"',
                        $config['file'],
                        var_export(self::$handable, true)
                    )
                );
            }

            $processor->processFile($config);
        }
    }

    /**
     * Returns type of configuration by files extension.
     * Files with extension ".yml" will be resolved to type YAML, extension ".json" will be resolved to type JSON
     *
     * @param string $file File to parse extension from
     *
     * @return string Type of configuration, either self::CONFIGURATION_FORMAT_YAML or self::CONFIGURATION_FORMAT_JSON
     */
    private static function retrieveConfigurationTypeByFile($file)
    {
        $info      = new \SplFileInfo($file);
        $extension = strtolower($info->getExtension());

        switch ($extension) {
            case self::FILE_EXTENSION_YAML:
                $type = self::CONFIGURATION_FORMAT_YAML;
                break;
            case self::FILE_EXTENSION_JSON:
                $type = self::CONFIGURATION_FORMAT_JSON;
                break;
            default:
                $type = null;
                break;
        }

        return $type;
    }
}
