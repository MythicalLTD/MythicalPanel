<?php

/*
 * This file is part of App.
 * Please view the LICENSE file that was distributed with this source code.
 *
 * # MythicalSystems License v2.0
 *
 * ## Copyright (c) 2021–2025 MythicalSystems and Cassian Gherman
 *
 * Breaking any of the following rules will result in a permanent ban from the MythicalSystems community and all of its services.
 */

namespace App\Plugins;

use App\Chat\Database;

/**
 * @deprecated Please use PluginHelper or PluginManager instead!
 */
class PluginDB extends Database
{
    public const PLUGIN_TABLE = 'mythicalpanel_addons';

    /**
     * Get all the plugins.
     *
     * @deprecated Please use PluginManager::getLoadedPlugins() instead
     */
    public static function getPlugins(): array
    {
        try {
            $conn = self::getPdoConnection();
            $stmt = $conn->prepare('SELECT * FROM ' . self::PLUGIN_TABLE);
            $stmt->execute();

            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            self::db_Error('Failed to get plugins from database: ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Register a new plugin in the database.
     *
     * @param string $name The unique name/identifier of the plugin
     * @param string $displayName The display name of the plugin
     *
     * @return bool True if registration successful, false if already exists
     *
     * @deprecated Use PluginHelper::getPluginConfig() instead
     */
    public static function registerPlugin(string $name, string $displayName): bool
    {

        try {
            $conn = Database::getPdoConnection();

            // Check if plugin already exists
            if (self::isPluginRegistered($name)) {
                return false;
            }

            $stmt = $conn->prepare('
                INSERT INTO mythicalpanel_addons 
                (name) 
                VALUES (:name)
            ');

            $stmt->bindParam(':name', $name, \PDO::PARAM_STR);
            $result = $stmt->execute();

            if (!$result) {
                self::db_Error('Failed to execute plugin registration query');

                return false;
            }

            return true;

        } catch (\Exception $e) {
            self::db_Error('Failed to register plugin: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Check if a plugin is registered.
     *
     * @param string $name The name of the plugin
     *
     * @return bool True if plugin exists and isn't deleted
     *
     * @deprecated Use PluginHelper::getPluginConfig() instead
     */
    public static function isPluginRegistered(string $name): bool
    {
        try {
            $conn = Database::getPdoConnection();

            $stmt = $conn->prepare("
                SELECT id 
                FROM mythicalpanel_addons 
                WHERE name = :name 
                AND deleted = 'false'
                LIMIT 1
            ");

            $stmt->execute([':name' => $name]);

            return (bool) $stmt->fetch(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            self::db_Error('Failed to check if plugin is registered: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Enable or disable a plugin.
     *
     * @param string $name The name of the plugin
     * @param bool $enabled Whether to enable or disable the plugin
     *
     * @deprecated Use PluginHelper::getPluginConfig() instead
     */
    public static function setPluginEnabled(string $name, bool $enabled): void
    {
        try {
            $conn = Database::getPdoConnection();

            $stmt = $conn->prepare('
                UPDATE mythicalpanel_addons 
                SET enabled = :enabled,
                    date = CURRENT_TIMESTAMP
                WHERE name = :name
            ');

            $stmt->execute([
                ':name' => $name,
                ':enabled' => $enabled ? 'true' : 'false',
            ]);
        } catch (\Exception $e) {
            self::db_Error('Failed to set plugin enabled: ' . $e->getMessage());
        }
    }

    /**
     * Check if a plugin is enabled.
     *
     * @param string $name The name of the plugin
     *
     * @return bool True if plugin is enabled
     *
     * @deprecated Use PluginHelper::getPluginConfig() instead
     */
    public static function isPluginEnabled(string $name): bool
    {
        try {
            $conn = Database::getPdoConnection();

            $stmt = $conn->prepare("
                SELECT enabled 
                FROM mythicalpanel_addons 
                WHERE name = :name 
                AND deleted = 'false'
                LIMIT 1
            ");

            $stmt->execute([':name' => $name]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            return $result && $result['enabled'] === 'true';
        } catch (\Exception $e) {
            self::db_Error('Failed to check if plugin is enabled: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Delete a plugin.
     *
     * @param string $name The name of the plugin
     *
     * @deprecated Use PluginHelper::getPluginConfig() instead
     */
    public static function deletePlugin(string $name): void
    {
        try {
            $conn = Database::getPdoConnection();

            $stmt = $conn->prepare("
                UPDATE mythicalpanel_addons 
                SET deleted = 'true',
                    date = CURRENT_TIMESTAMP
                WHERE name = :name
            ");

            $stmt->execute([':name' => $name]);
        } catch (\Exception $e) {
            self::db_Error('Failed to delete plugin: ' . $e->getMessage());
        }
    }

    /**
     * Get plugin information.
     *
     * @param string $name The name of the plugin
     *
     * @return array|null Plugin information or null if not found
     *
     * @deprecated Use PluginHelper::getPluginConfig() instead
     */
    public static function getPluginInfo(string $name): ?array
    {
        try {
            $conn = Database::getPdoConnection();

            $stmt = $conn->prepare("
                SELECT name, enabled, locked 
                FROM mythicalpanel_addons 
                WHERE name = :name 
                AND deleted = 'false'
                LIMIT 1
            ");

            $stmt->execute([':name' => $name]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            return $result ?: null;
        } catch (\Exception $e) {
            self::db_Error('Failed to get plugin info: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * List all registered plugins.
     *
     * @param bool $includeDisabled Whether to include disabled plugins
     *
     * @return array List of plugins
     *
     * @deprecated Use PluginHelper::getPluginConfig() instead
     */
    public static function listPlugins(bool $includeDisabled = false): array
    {
        try {
            $conn = Database::getPdoConnection();

            $sql = "
                SELECT name, enabled, locked 
                FROM mythicalpanel_addons 
                WHERE deleted = 'false'
            ";

            if (!$includeDisabled) {
                $sql .= " AND enabled = 'true'";
            }

            $stmt = $conn->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            self::db_Error('Failed to list plugins: ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Convert an ID to a name.
     *
     * @param int $id The ID to convert
     *
     * @return string The name
     *
     * @deprecated Use PluginHelper::getPluginConfig() instead
     */
    public static function convertIdToName(int $id): string
    {
        try {
            $conn = Database::getPdoConnection();
            $stmt = $conn->prepare('SELECT name FROM mythicalpanel_addons WHERE id = :id');
            $stmt->execute([':id' => $id]);

            return $stmt->fetch(\PDO::FETCH_ASSOC)['name'];
        } catch (\Exception $e) {
            self::db_Error('Failed to convert ID to name: ' . $e->getMessage());

            return '';
        }
    }
}
