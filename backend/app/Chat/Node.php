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

namespace App\Chat;

use App\App;

/**
 * Node service/model for CRUD operations on the mythicalpanel_nodes table.
 */
class Node
{
    /**
     * @var string The nodes table name
     */
    private static string $table = 'mythicalpanel_nodes';

    /**
     * Whitelist of allowed field names for SQL queries to prevent injection.
     */
    private static array $allowedFields = [
        'uuid',
        'name',
        'description',
        'location_id',
        'fqdn',
        'public',
        'scheme',
        'behind_proxy',
        'maintenance_mode',
        'memory',
        'memory_overallocate',
        'disk',
        'disk_overallocate',
        'upload_size',
        'daemon_token_id',
        'daemon_token',
        'daemonListen',
        'daemonSFTP',
        'daemonBase',
    ];

    /**
     * Validate required fields and types for node creation/update.
     */
    public static function validateNodeData(array $data, array $requiredFields = []): array
    {
        $errors = [];

        // Check required fields
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || trim((string) $data[$field]) === '') {
                $errors[] = "Missing required field: $field";
            }
        }

        // Type and format validations
        if (isset($data['uuid']) && !self::isValidUuid($data['uuid'])) {
            $errors[] = 'Invalid UUID format';
        }

        if (isset($data['name']) && (!is_string($data['name']) || strlen($data['name']) > 100)) {
            $errors[] = 'Name must be a string with maximum 100 characters';
        }

        if (isset($data['fqdn']) && !is_string($data['fqdn'])) {
            $errors[] = 'FQDN must be a string';
        }

        if (isset($data['location_id']) && (!is_numeric($data['location_id']) || (int) $data['location_id'] <= 0)) {
            $errors[] = 'Location ID must be a positive number';
        }

        if (isset($data['memory']) && (!is_numeric($data['memory']) || (int) $data['memory'] < 0)) {
            $errors[] = 'Memory must be a non-negative number';
        }

        if (isset($data['disk']) && (!is_numeric($data['disk']) || (int) $data['disk'] < 0)) {
            $errors[] = 'Disk space must be a non-negative number';
        }

        if (isset($data['daemonListen']) && (!is_numeric($data['daemonListen']) || (int) $data['daemonListen'] < 1)) {
            $errors[] = 'Daemon port must be a positive number';
        }

        if (isset($data['daemonSFTP']) && (!is_numeric($data['daemonSFTP']) || (int) $data['daemonSFTP'] < 1)) {
            $errors[] = 'Daemon SFTP port must be a positive number';
        }

        return $errors;
    }

    /**
     * Create a new node.
     *
     * @param array $data Associative array of node fields
     *
     * @return int|false The new node's ID or false on failure
     */
    public static function createNode(array $data): int|false
    {
        // Required fields for node creation
        $required = [
            'uuid',
            'name',
            'fqdn',
            'location_id',
        ];

        $columns = self::getColumns();
        $columns = array_map(fn ($c) => $c['Field'], $columns);
        $missing = array_diff($required, $columns);
        if (!empty($missing)) {
            $sanitizedData = self::sanitizeDataForLogging($data);
            App::getInstance(true)->getLogger()->error('Missing required fields: ' . implode(', ', $missing) . ' for node: ' . $data['name'] . ' with data: ' . json_encode($sanitizedData));

            return false;
        }

        foreach ($required as $field) {
            if (!isset($data[$field])) {
                $sanitizedData = self::sanitizeDataForLogging($data);
                App::getInstance(true)->getLogger()->error('Missing required field: ' . $field . ' for node: ' . $data['name'] . ' with data: ' . json_encode($sanitizedData));

                return false;
            }

            // Special validation for different field types
            if ($field === 'location_id') {
                if (!is_numeric($data[$field]) || (int) $data[$field] <= 0) {
                    $sanitizedData = self::sanitizeDataForLogging($data);
                    App::getInstance(true)->getLogger()->error('Invalid location_id: ' . $data[$field] . ' for node: ' . $data['name'] . ' with data: ' . json_encode($sanitizedData));

                    return false;
                }
            } else {
                // String fields validation
                if (!is_string($data[$field]) || trim($data[$field]) === '') {
                    $sanitizedData = self::sanitizeDataForLogging($data);
                    App::getInstance(true)->getLogger()->error('Missing required field: ' . $field . ' for node: ' . $data['name'] . ' with data: ' . json_encode($sanitizedData));

                    return false;
                }
            }
        }

        // UUID validation (basic)
        if (!preg_match('/^[a-f0-9\-]{36}$/i', $data['uuid'])) {
            $sanitizedData = self::sanitizeDataForLogging($data);
            App::getInstance(true)->getLogger()->error('Invalid UUID: ' . $data['uuid'] . ' for node: ' . $data['name'] . ' with data: ' . json_encode($sanitizedData));

            return false;
        }

        // Validate location_id exists
        if (!Location::getById($data['location_id'])) {
            $sanitizedData = self::sanitizeDataForLogging($data);
            App::getInstance(true)->getLogger()->error('Invalid location_id: ' . $data['location_id'] . ' for node: ' . $data['name'] . ' with data: ' . json_encode($sanitizedData));

            return false;
        }

        // Convert boolean values to integers for database compatibility
        $booleanFields = ['public', 'behind_proxy', 'maintenance_mode'];
        foreach ($booleanFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = $data[$field] ? 1 : 0;
            }
        }

        // Filter data to only include allowed fields
        $filteredData = array_intersect_key($data, array_flip(self::$allowedFields));

        $pdo = Database::getPdoConnection();
        $fields = array_keys($filteredData);
        $placeholders = array_map(fn ($f) => ':' . $f, $fields);
        $sql = 'INSERT INTO ' . self::$table . ' (`' . implode('`,`', $fields) . '`) VALUES (' . implode(',', $placeholders) . ')';
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute($filteredData)) {
            return (int) $pdo->lastInsertId();
        }

        $sanitizedData = self::sanitizeDataForLogging($data);
        App::getInstance(true)->getLogger()->error('Failed to create node: ' . $sql . ' for node: ' . $data['name'] . ' with data: ' . json_encode($sanitizedData) . ' and error: ' . json_encode($stmt->errorInfo()));

        return false;
    }

    /**
     * Fetch a node by ID.
     */
    public static function getNodeById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare('SELECT * FROM ' . self::$table . ' WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Fetch a node by UUID.
     */
    public static function getNodeByUuid(string $uuid): ?array
    {
        if (!preg_match('/^[a-f0-9\-]{36}$/i', $uuid)) {
            return null;
        }
        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare('SELECT * FROM ' . self::$table . ' WHERE uuid = :uuid LIMIT 1');
        $stmt->execute(['uuid' => $uuid]);

        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Fetch nodes by location ID.
     */
    public static function getNodesByLocationId(int $locationId): array
    {
        if ($locationId <= 0) {
            return [];
        }
        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare('SELECT * FROM ' . self::$table . ' WHERE location_id = :location_id ORDER BY name ASC');
        $stmt->execute(['location_id' => $locationId]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Fetch all nodes with optional filtering.
     */
    public static function getAllNodes(): array
    {
        $pdo = Database::getPdoConnection();
        $sql = 'SELECT * FROM ' . self::$table;

        $sql .= ' ORDER BY name ASC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Search nodes with pagination and filtering.
     */
    public static function searchNodes(
        int $page = 1,
        int $limit = 10,
        string $search = '',
        array $fields = [],
        string $sortBy = 'name',
        string $sortOrder = 'ASC',
        ?int $locationId = null,
    ): array {
        $pdo = Database::getPdoConnection();
        $offset = ($page - 1) * $limit;
        $params = [];

        $sql = 'SELECT n.*, l.name as location_name FROM ' . self::$table . ' n';
        $sql .= ' LEFT JOIN mythicalpanel_locations l ON n.location_id = l.id';
        $sql .= ' WHERE 1=1';

        if (!empty($search)) {
            $sql .= ' AND (n.name LIKE :search OR n.description LIKE :search OR n.fqdn LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        if ($locationId !== null) {
            $sql .= ' AND n.location_id = :location_id';
            $params['location_id'] = $locationId;
        }

        $sql .= ' ORDER BY n.' . $sortBy . ' ' . $sortOrder;
        $sql .= ' LIMIT :limit OFFSET :offset';

        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get total count of nodes with optional filtering.
     */
    public static function getNodesCount(
        string $search = '',
        ?int $locationId = null,
    ): int {
        $pdo = Database::getPdoConnection();
        $params = [];

        $sql = 'SELECT COUNT(*) FROM ' . self::$table . ' WHERE 1=1';

        if (!empty($search)) {
            $sql .= ' AND (name LIKE :search OR description LIKE :search OR fqdn LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        if ($locationId !== null) {
            $sql .= ' AND location_id = :location_id';
            $params['location_id'] = $locationId;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Update a node by UUID.
     */
    public static function updateNode(string $uuid, array $data): bool
    {
        if (!self::isValidUuid($uuid)) {
            App::getInstance(true)->getLogger()->error('Invalid UUID: ' . $uuid . ' for node: ' . $data['name'] . ' with data: ' . json_encode($data));

            return false;
        }

        // Validate location_id if provided
        if (isset($data['location_id']) && !Location::getById($data['location_id'])) {
            App::getInstance(true)->getLogger()->error('Invalid location_id: ' . $data['location_id'] . ' for node: ' . $data['name'] . ' with data: ' . json_encode($data));

            return false;
        }

        // Convert boolean values to integers for database compatibility
        $booleanFields = ['public', 'behind_proxy', 'maintenance_mode'];
        foreach ($booleanFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = $data[$field] ? 1 : 0;
            }
        }

        // Filter data to only include allowed fields
        $filteredData = array_intersect_key($data, array_flip(self::$allowedFields));

        $pdo = Database::getPdoConnection();
        $fields = array_keys($filteredData);
        $set = array_map(fn ($f) => "`$f` = :$f", $fields);
        $sql = 'UPDATE ' . self::$table . ' SET ' . implode(',', $set) . ' WHERE uuid = :uuid';

        $params = $filteredData;
        $params['uuid'] = $uuid;

        $stmt = $pdo->prepare($sql);

        return $stmt->execute($params);
    }

    /**
     * Update a node by ID.
     */
    public static function updateNodeById(int $id, array $data): bool
    {
        if ($id <= 0) {
            App::getInstance(true)->getLogger()->error('Invalid ID: ' . $id . ' for node: ' . $data['name'] . ' with data: ' . json_encode($data));

            return false;
        }

        // Validate location_id if provided
        if (isset($data['location_id']) && !Location::getById($data['location_id'])) {
            App::getInstance(true)->getLogger()->error('Invalid location_id: ' . $data['location_id'] . ' for node: ' . $data['name'] . ' with data: ' . json_encode($data));

            return false;
        }

        // Convert boolean values to integers for database compatibility
        $booleanFields = ['public', 'behind_proxy', 'maintenance_mode'];
        foreach ($booleanFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = $data[$field] ? 1 : 0;
            }
        }

        // Filter data to only include allowed fields
        $filteredData = array_intersect_key($data, array_flip(self::$allowedFields));

        $pdo = Database::getPdoConnection();
        $fields = array_keys($filteredData);
        $set = array_map(fn ($f) => "`$f` = :$f", $fields);
        $sql = 'UPDATE ' . self::$table . ' SET ' . implode(',', $set) . ' WHERE id = :id';

        $params = $filteredData;
        $params['id'] = $id;

        $stmt = $pdo->prepare($sql);

        return $stmt->execute($params);
    }

    /**
     * Hard delete a node by ID.
     */
    public static function hardDeleteNode(int $id): bool
    {
        if ($id <= 0) {
            App::getInstance(true)->getLogger()->error('Invalid ID: ' . $id . ' for node:  with data: ' . $id);

            return false;
        }
        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare('DELETE FROM ' . self::$table . ' WHERE id = :id');

        return $stmt->execute(['id' => $id]);
    }

    /**
     * Get table columns information.
     */
    public static function getColumns(): array
    {
        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare('DESCRIBE ' . self::$table);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Generate a cryptographically secure UUID for nodes.
     */
    public static function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0F | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3F | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Generate a daemon token ID (16 characters) using cryptographically secure random generation.
     */
    public static function generateDaemonTokenId(): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $tokenId = '';
        $randomBytes = random_bytes(16);
        for ($i = 0; $i < 16; ++$i) {
            $tokenId .= $chars[ord($randomBytes[$i]) % strlen($chars)];
        }

        return $tokenId;
    }

    /**
     * Generate a daemon token (64 characters) using cryptographically secure random generation.
     */
    public static function generateDaemonToken(): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $token = '';
        $randomBytes = random_bytes(64);
        for ($i = 0; $i < 64; ++$i) {
            $token .= $chars[ord($randomBytes[$i]) % strlen($chars)];
        }

        return $token;
    }

    /**
     * Validate UUID format.
     */
    public static function isValidUuid(string $uuid): bool
    {
        return (bool) preg_match('/^[a-f0-9\-]{36}$/i', $uuid);
    }

    /**
     * Get node with location information.
     */
    public static function getNodeWithLocation(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare('
            SELECT n.*, l.name as location_name, l.description as location_description 
            FROM ' . self::$table . ' n 
            LEFT JOIN mythicalpanel_locations l ON n.location_id = l.id 
            WHERE n.id = :id LIMIT 1
        ');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Get all nodes with location information.
     */
    public static function getAllNodesWithLocation(): array
    {
        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare('
            SELECT n.*, l.name as location_name, l.description as location_description 
            FROM ' . self::$table . ' n 
            LEFT JOIN mythicalpanel_locations l ON n.location_id = l.id 
            ORDER BY n.name ASC
        ');
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Sanitize data for logging by excluding sensitive fields.
     */
    private static function sanitizeDataForLogging(array $data): array
    {
        $sensitiveFields = [
            'daemon_token',
            'daemon_token_id',
        ];

        $sanitized = $data;
        foreach ($sensitiveFields as $field) {
            if (isset($sanitized[$field])) {
                $sanitized[$field] = '[REDACTED]';
            }
        }

        return $sanitized;
    }
}
