<?php

namespace Core\Phalcon;

use Phalcon\Config as PhalconConfig;
use Phalcon\Di;

class Config
{
    protected static $config;
    protected static $preloadConfig = [];

    public static function clearCache()
    {
        $environment = static::environment();
        is_file($cacheFile = storagePath('cache/config-' . $environment . '.php')) and unlink($cacheFile);
        Cache::delete('db_configs_' . $environment);
    }

    public static function environment()
    {
        return static::get('environment');
    }

    /**
     * @param $key          string
     * @param $defaultValue mixed
     * @return mixed
     */
    public static function get($key = null, $defaultValue = null)
    {
        return $key === null ? static::$config : fnGet(static::$config, $key, $defaultValue, '.');
    }

    protected static function loadDb(PhalconConfig $config)
    {
        $dbConfig = new PhalconConfig(Model\Config::all());
        $config->merge($dbConfig);
        static::$config = $config->toArray();
    }

    protected static function loadFiles($files)
    {
        $settings = [];
        foreach ($files as $file) {
            // @codeCoverageIgnoreStart
            if (!is_file($file)) {
                continue;
            }
            // @codeCoverageIgnoreEnd
            $key = pathinfo($file, PATHINFO_FILENAME);
            $value = include $file;
            $settings[$key] = is_array($value) ? $value : [];
        }
        return $settings;
    }

    public static function register(Di $di)
    {
        $environment = isset($_SERVER['APP_ENV']) ? $_SERVER['APP_ENV'] : 'production';
        // @codeCoverageIgnoreStart
        if (is_file($cacheFile = storagePath('cache/config-' . $environment . '.php'))) {
            static::$config = include $cacheFile;
            Config::get('app.cache_config') or static::clearCache();
            return;
        }
        // @codeCoverageIgnoreEnd

        // Load preload files (for unit testing)
        $config = new PhalconConfig(static::$preloadConfig);

        // Load default configs
        $defaultFiles = glob($_SERVER['APP_CONFIG_PATH'] . '/*.php');
        $config->merge(new PhalconConfig(static::loadFiles($defaultFiles)));

        // Load override configs
        $overrideDirs = glob($_SERVER['APP_CONFIG_PATH'] . '/override-*/');
        foreach ($overrideDirs as $overrideDir) {
            $overrideFiles = glob($overrideDir . '*.php');
            $overrideSettings = static::loadFiles($overrideFiles);
            $overrideConfig = new PhalconConfig($overrideSettings);
            $config->merge($overrideConfig);
        }

        // Load environment configs
        $environmentFiles = glob($_SERVER['APP_CONFIG_PATH'] . '/' . $environment . '/*.php');
        $environmentSettings = static::loadFiles($environmentFiles);
        $environmentSettings['environment'] = $environment;
        $environmentConfig = new PhalconConfig($environmentSettings);
        $config->merge($environmentConfig);

        $di->remove('config');
        $di->setShared('config', $config);
        static::$config = $config->toArray();
        Config::get('database.default') and static::loadDb($config);
        // @codeCoverageIgnoreStart
        if (Config::get('app.cache_config')) {
            is_dir($cacheDir = dirname($cacheFile)) or mkdir($cacheDir, 0777, true);
            fileSaveArray($cacheFile, static::$config, function ($content) {
                $replacement = <<<'EOF'
$_SERVER['APP_ROOT_PATH'] . '
EOF;
                return str_replace("'{$_SERVER['APP_ROOT_PATH']}", $replacement, $content);
            });
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * @param string $key
     * @param mixed  $value
     * @return mixed
     */
    public static function set($key, $value)
    {
        array_set(static::$config, $key, $value, '.');
        return $value;
    }

    public static function runningUnitTest()
    {
        return static::environment() == 'testing';
    }
}
