<?php

/**
 * @file CdlImportPlugin.php
 *
 * Copyright (c) 2022 Cast Irron Coding
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class CdlImportPlugin
 * @ingroup plugins_importexport_sample
 *
 * @brief Imports data for plugins
 */

/**
 * @psalm-suppress UndefinedFunction
 */
import('classes.plugins.ImportExportPlugin');

/**
 * @psalm-suppress UndefinedClass
 */
class CdlImportPlugin extends ImportExportPlugin
{
    const PLUGIN_DISPLAY_NAME = 'CDL Import Plugin';
    const PLUGIN_DESCRIPTION = 'JS plugin for importing data, CC license URLs to start';

    /**
     * Called as a plugin is registered to the registry
     * @param $category String Name of category plugin was registered to
     * @return boolean True if plugin initialized successfully; if false,
     *    the plugin will not be registered.
     */
    function register($category, $path)
    {
        $this->registerAutoload();

        return parent::register($category, $path);
    }

    /**
     * Get the name of this plugin. The name must be unique within
     * its category.
     * @return String name of plugin
     */
    function getName(): string
    {
        return __CLASS__;
    }

    /**
     * @return string
     */
    function getDisplayName(): string
    {
        return self::PLUGIN_DISPLAY_NAME;
    }

    /**
     * @return string
     */
    function getDescription(): string
    {
        return self::PLUGIN_DESCRIPTION;
    }

    /**
     * Execute import/export tasks using the command-line interface.
     * @param $scriptName string
     * @param $args array Parameters to the plugin
     */
    function executeCLI(string $scriptName, array $args): void
    {
        $command = array_shift($args);
        switch ($command) {
            case 'cc':
                $importerClass = \CdlImportPlugin\Commands\Cc::class;
                break;
            default:
                $this->help();
                exit;
        }
        $this->executeImporterCommand($importerClass, $args);
    }

    /**
     * @param $importerClass string
     * @param $args array
     */
    function executeImporterCommand(string $importerClass, array $args): void
    {
        (new $importerClass($args))->execute($args);
    }

    /**
     * Display the command-line usage information
     */
    function help(): string
    {
        echo "PUT SOME HELPFUL INFO HERE.\n";
    }

    /**
     *
     */
    function registerAutoload(): void
    {
        spl_autoload_register(
            function ($class) {
                $namespace = 'CdlImportPlugin\\';
                if (strpos($class, $namespace) === 0) {
                    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($namespace))) . '.php';
                    if (file_exists($file)) {
                        include $file;
                    }
                }
            }
        );
    }
}
