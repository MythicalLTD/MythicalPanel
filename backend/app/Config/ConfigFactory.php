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

namespace App\Config;

class ConfigFactory
{
    private \PDO $db;
    private array $cache = [];

    private string $table_name = 'mythicalpanel_settings';

    public function __construct(\PDO $db)
    {
        try {
            $this->db = $db;
        } catch (\Exception $e) {
            throw new \Exception('Failed to connect to the MYSQL Server! ', $e->getMessage());
        }
    }

    /**
     * Get a setting from the database.
     *
     * @param string $name The name of the setting
     * @param mixed $fallback The fallback value if the setting is not found
     *
     * @return string|null The value of the setting
     */
    public function getSetting(string $name, ?string $fallback): ?string
    {
        // Check if the setting is in the cache
        if (isset($this->cache[$name])) {
            return $this->cache[$name];
        }
        $stmt = $this->db->prepare("SELECT * FROM {$this->table_name} WHERE name = :name LIMIT 1");
        $stmt->execute(['name' => $name]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($result) {
            // Store the result in the cache
            $this->cache[$name] = $result['value'];

            return $result['value'];
        }

        return $fallback ?? null;
    }

    public function getSettings(array $columns = []): array
    {
        $query = "SELECT name, value FROM {$this->table_name}";
        if (!empty($columns)) {
            $placeholders = array_fill(0, count($columns), '?');
            $query .= ' WHERE name IN (' . implode(',', $placeholders) . ')';
        }
        $query .= ' ORDER BY name ASC';
        $stmt = $this->db->prepare($query);
        if (!empty($columns)) {
            $stmt->execute($columns);
        } else {
            $stmt->execute();
        }
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $settings = [];
        foreach ($results as $result) {
            $settings[$result['name']] = $result['value'];
            $this->cache[$result['name']] = $result['value'];
        }

        return $settings;
    }

    /**
     * Set a setting in the database.
     *
     * @param string $name The name of the setting
     * @param string $value The value of the setting
     *
     * @throws \Exception If the setting already exists
     *
     * @return bool True if the setting was set successfully
     */
    public function setSetting(string $name, string $value): bool
    {
        $stmt = $this->db->prepare("INSERT INTO {$this->table_name} (name, value, date) VALUES (:name, :value, NOW()) ON DUPLICATE KEY UPDATE value = :value, date = NOW()");
        $result = $stmt->execute(['name' => $name, 'value' => $value]);
        if ($result) {
            // Update the cache
            $this->cache[$name] = $value;
        }

        return $result;
    }

    /**
     * ⚠️ DANGER ZONE - HANDLE WITH EXTREME CAUTION ⚠️.
     *
     * This function is used to dump all settings from the database.
     *
     * WARNING: This function will return ALL settings from the database in their decrypted form.
     * This includes potentially sensitive information like API keys, tokens, and credentials.
     *
     * Only use this function for debugging purposes in a secure environment.
     * Never expose this data publicly or log it to files that could be accessed by others.
     *
     * The settings are returned as a simple key-value array with no encryption.
     * Be extremely careful with how you handle and store this data.
     *
     * @return array All settings from database in plain text
     */
    public function dumpSettings(): array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table_name} ORDER BY name ASC");
        $stmt->execute();
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $settings = [];
        foreach ($results as $result) {
            $settings[$result['name']] = $result['value'];
        }

        return $settings;
    }

    public static function getConfigurableSettings(): array
    {
        $ref = new \ReflectionClass(ConfigInterface::class);

        return $ref->getConstants();
    }
}
