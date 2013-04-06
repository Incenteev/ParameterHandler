# Managing your ignored parameters with Composer

This tool allows you to manage your ignored parameters when running a composer
install or update. It works when storing the parameters in a Yaml file under
a ``parameters`` key.

## Usage

Add the following in your root composer.json file:

```json
{
    "require": {
        "incenteev/composer-parameter-handler": "1.0.*"
    },
    "scripts": {
        "post-install-cmd": [
            "Incenteev\\ParameterHandler\\ScriptHandler::buildParameters"
        ],
        "post-update-cmd": [
            "Incenteev\\ParameterHandler\\ScriptHandler::buildParameters"
        ]
    },
    "extra": {
        "incenteev-parameters": {
            "file": "app/config/parameters.yml"
        }
    }
}
```

The ``app/config/parameters.yml`` will then be created or updated by the
composer script, to match the structure of the dist file ``app/config/parameters.yml.dist``
by asking you the missing parameters.

By default, the dist file is assumed to be in the same place than the parameters
file, suffixed by ``.dist``. This can be changed in the configuration:

```json
{
    "extra": {
        "incenteev-parameters": {
            "file": "app/config/parameters.yml",
            "dist-file": "some/other/folder/to/other/parameters/file/parameters.yml.dist"
        }
    }
}
```

The script handler will ask you interactively for parameters which are missing
in the parameters file, using the value of the dist file as default value.
All prompted values are parsed as inline Yaml, to allow you to define ``true``,
``false``, ``null`` or numbers easily.
If composer is run in a non-interactive mode, the values of the dist file
will be used for missing parameters.

Warning: This script removes outdated params from ``parameters.yml`` which are not in ``parameters.yml.dist``
If you need to keep outdated params you can use `keep-outdated` param in the configuration:
```json
{
    "extra": {
        "incenteev-parameters": {
            "keep-outdated": true,
        }
    }
}
```

## Using environment variables to set the parameters

For your prod environment, using an interactive prompt may not be possible
when deploying. In this case, you can rely on environment variables to provide
the parameters. This is achieved by providing a map between environment variables
and the parameters they should fill:

```json
{
    "extra": {
        "incenteev-parameters": {
            "env-map": {
                "my_first_param": "MY_FIRST_PARAM",
                "my_second_param": "MY_SECOND_PARAM"
            }
        }
    }
}
```

If an environment variable is set, its value will always replace the value
set in the existing parameters file.

As environment variables can only be strings, they are also parsed as inline
Yaml values to allows specifying ``null``, ``false``, ``true`` or numbers
easily.

Warning: This parameters handler will overwrite any comments or spaces into
your parameters.yml file so handle with care. So if you want to give format
and comments to your parameter's file you should do it on your dist version.
