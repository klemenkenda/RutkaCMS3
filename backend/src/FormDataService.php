<?php

declare(strict_types=1);

namespace App;

use PDO;
use RuntimeException;

final class FormDataService
{
    private PDO $pdo;

    /** @var array<string, array<string, mixed>> */
    private array $forms;

    /**
     * @param array<string, mixed> $parsedConfig
     */
    public function __construct(PDO $pdo, array $parsedConfig)
    {
        $this->pdo = $pdo;
        $this->forms = $parsedConfig['forms'] ?? [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listForms(): array
    {
        $result = [];
        foreach ($this->forms as $slug => $form) {
            $result[] = [
                'slug' => $slug,
                'name' => $form['meta']['name'] ?? $slug,
                'listorder' => $form['meta']['listorder'] ?? 'id DESC',
                'formtype' => $form['meta']['formtype'] ?? 'import',
                'fieldCount' => count($form['fields'] ?? []),
            ];
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function getFormSchema(string $form): array
    {
        $definition = $this->getFormDefinition($form);

        $fields = [];
        foreach (($definition['fields'] ?? []) as $fieldKey => $fieldDef) {
            $fields[] = [
                'key' => $fieldKey,
                'name' => $fieldDef['name'] ?? $fieldKey,
                'type' => $fieldDef['type'] ?? 'text_field',
                'comment' => $fieldDef['comment'] ?? null,
                'start' => $fieldDef['start'] ?? null,
                'dir' => $fieldDef['dir'] ?? null,
                'data' => $fieldDef['data'] ?? null,
            ];
        }

        return [
            'slug' => $form,
            'name' => $definition['meta']['name'] ?? $form,
            'meta' => $definition['meta'] ?? [],
            'fields' => $fields,
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function getFieldOptions(string $form, string $field): array
    {
        $fieldDef = $this->getFieldDefinition($form, $field);
        $dataSql = trim((string) ($fieldDef['data'] ?? ''));

        if ($dataSql === '') {
            return [];
        }

        // Allow only simple read-only SELECT queries from config.
        if (preg_match('/^\s*select\s+/i', $dataSql) !== 1 || str_contains($dataSql, ';')) {
            throw new \InvalidArgumentException('Unsupported options SQL for field: ' . $field);
        }

        if (preg_match('/\b(insert|update|delete|drop|alter|create|truncate)\b/i', $dataSql) === 1) {
            throw new \InvalidArgumentException('Unsafe options SQL for field: ' . $field);
        }

        $stmt = $this->pdo->query($dataSql);
        $rows = $stmt->fetchAll(PDO::FETCH_NUM);
        $options = [];

        foreach ($rows as $row) {
            if (!isset($row[0])) {
                continue;
            }

            $options[] = [
                'value' => (string) $row[0],
                'label' => isset($row[1]) ? (string) $row[1] : (string) $row[0],
            ];
        }

        return $options;
    }

    /**
     * @param array<string, mixed> $file
     * @return array{filename: string, relativePath: string}
     */
    public function uploadFieldFile(string $form, string $field, array $file, string $projectBaseDir): array
    {
        $fieldDef = $this->getFieldDefinition($form, $field);
        $type = strtolower((string) ($fieldDef['type'] ?? ''));

        if (!in_array($type, ['upload_image', 'filename_upload'], true)) {
            throw new \InvalidArgumentException('Field is not a file upload type: ' . $field);
        }

        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload failed with code: ' . $error);
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new RuntimeException('Missing uploaded file payload.');
        }

        $originalName = (string) ($file['name'] ?? 'file');
        $safeName = $this->sanitizeFilename($originalName);
        $finalName = $this->buildUniqueFilename($safeName);

        if ($type === 'upload_image') {
            $ext = strtolower(pathinfo($finalName, PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (!in_array($ext, $allowed, true)) {
                throw new \InvalidArgumentException('Only image formats are allowed for field: ' . $field);
            }
        }

        $configuredDir = (string) ($fieldDef['dir'] ?? 'uploads/' . $form);
        $targetDir = $this->resolveUploadDir($projectBaseDir, $configuredDir);

        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new RuntimeException('Could not create upload directory.');
        }

        $targetPath = rtrim($targetDir, '/\\') . DIRECTORY_SEPARATOR . $finalName;
        if (!move_uploaded_file($tmpName, $targetPath)) {
            throw new RuntimeException('Could not store uploaded file.');
        }

        $relativePath = $this->buildRelativePath($configuredDir, $finalName);

        return [
            'filename' => $finalName,
            'relativePath' => $relativePath,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listEntries(string $form, int $limit = 50, int $offset = 0): array
    {
        $definition = $this->getFormDefinition($form);
        $table = $this->assertIdentifier($form);

        $orderBy = $this->buildOrderBy(
            (string) ($definition['meta']['listorder'] ?? 'id DESC'),
            array_keys($definition['fields'] ?? [])
        );

        $sql = sprintf(
            'SELECT * FROM `%s` ORDER BY %s LIMIT :limit OFFSET :offset',
            $table,
            $orderBy
        );

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', max(1, min(200, $limit)), PDO::PARAM_INT);
        $stmt->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createEntry(string $form, array $payload): array
    {
        $definition = $this->getFormDefinition($form);
        $table = $this->assertIdentifier($form);

        $allowedFields = array_keys($definition['fields'] ?? []);
        $columns = [];
        $values = [];

        foreach ($allowedFields as $field) {
            if (!array_key_exists($field, $payload)) {
                continue;
            }

            $columns[] = $this->assertIdentifier($field);
            $values[$field] = $payload[$field];
        }

        if ($columns === []) {
            throw new \InvalidArgumentException('Payload does not contain any valid fields for form: ' . $form);
        }

        $columnSql = implode(', ', array_map(static fn (string $c): string => '`' . $c . '`', $columns));
        $placeholders = implode(', ', array_map(static fn (string $c): string => ':' . $c, array_keys($values)));

        $sql = sprintf('INSERT INTO `%s` (%s) VALUES (%s)', $table, $columnSql, $placeholders);
        $stmt = $this->pdo->prepare($sql);

        foreach ($values as $field => $value) {
            $stmt->bindValue(':' . $field, $value);
        }

        $stmt->execute();

        return [
            'id' => (int) $this->pdo->lastInsertId(),
            'created' => true,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function updateEntry(string $form, int $id, array $payload): array
    {
        $definition = $this->getFormDefinition($form);
        $table = $this->assertIdentifier($form);

        $allowed = array_keys($definition['fields'] ?? []);
        $setClauses = [];
        $values = [];

        foreach ($allowed as $field) {
            if (!array_key_exists($field, $payload)) {
                continue;
            }

            $column = $this->assertIdentifier($field);
            $setClauses[] = sprintf('`%s` = :%s', $column, $column);
            $values[$column] = $payload[$field];
        }

        if ($setClauses === []) {
            throw new \InvalidArgumentException('Payload does not contain any valid fields for form: ' . $form);
        }

        $sql = sprintf('UPDATE `%s` SET %s WHERE id = :id', $table, implode(', ', $setClauses));
        $stmt = $this->pdo->prepare($sql);

        foreach ($values as $column => $value) {
            $stmt->bindValue(':' . $column, $value);
        }
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        $stmt->execute();

        return [
            'updated' => $stmt->rowCount() > 0,
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function deleteEntry(string $form, int $id): array
    {
        $table = $this->assertIdentifier($form);

        $sql = sprintf('DELETE FROM `%s` WHERE id = :id', $table);
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'deleted' => $stmt->rowCount() > 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getFormDefinition(string $form): array
    {
        if (!isset($this->forms[$form])) {
            throw new \InvalidArgumentException('Unknown form: ' . $form);
        }

        return $this->forms[$form];
    }

    /**
     * @return array<string, mixed>
     */
    private function getFieldDefinition(string $form, string $field): array
    {
        $definition = $this->getFormDefinition($form);
        $fields = $definition['fields'] ?? [];

        if (!isset($fields[$field])) {
            throw new \InvalidArgumentException('Unknown field for form: ' . $form . '.' . $field);
        }

        return $fields[$field];
    }

    /**
     * Restrict SQL identifiers to avoid injection through table/column names.
     */
    private function assertIdentifier(string $identifier): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier) !== 1) {
            throw new \InvalidArgumentException('Invalid SQL identifier: ' . $identifier);
        }

        return $identifier;
    }

    /**
     * Build ORDER BY safely from legacy config like "ne_date DESC".
     *
     * @param array<int, string> $knownColumns
     */
    private function buildOrderBy(string $raw, array $knownColumns): string
    {
        $known = array_fill_keys($knownColumns, true);
        $known['id'] = true;

        $chunks = array_map('trim', explode(',', $raw));
        $parts = [];

        foreach ($chunks as $chunk) {
            if ($chunk === '') {
                continue;
            }

            if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)(?:\s+(ASC|DESC))?$/i', $chunk, $match) !== 1) {
                continue;
            }

            $column = $match[1];
            if (!isset($known[$column])) {
                continue;
            }

            $direction = strtoupper($match[2] ?? 'ASC');
            if ($direction !== 'ASC' && $direction !== 'DESC') {
                $direction = 'ASC';
            }

            $parts[] = sprintf('`%s` %s', $column, $direction);
        }

        if ($parts === []) {
            return '`id` DESC';
        }

        return implode(', ', $parts);
    }

    private function sanitizeFilename(string $name): string
    {
        $base = preg_replace('/[^A-Za-z0-9._-]+/', '_', $name) ?? 'file';
        $base = trim($base, '._-');

        if ($base === '') {
            return 'file.bin';
        }

        return $base;
    }

    private function buildUniqueFilename(string $name): string
    {
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        $root = pathinfo($name, PATHINFO_FILENAME);
        $suffix = date('Ymd_His') . '_' . bin2hex(random_bytes(3));

        if ($ext === '') {
            return $root . '_' . $suffix;
        }

        return $root . '_' . $suffix . '.' . $ext;
    }

    private function resolveUploadDir(string $projectBaseDir, string $configuredDir): string
    {
        $normalized = str_replace('\\', '/', trim($configuredDir));
        $segments = [];

        foreach (explode('/', $normalized) as $segment) {
            $segment = trim($segment);
            if ($segment === '' || $segment === '.' || $segment === '..') {
                continue;
            }

            $segments[] = preg_replace('/[^A-Za-z0-9._-]+/', '_', $segment) ?? 'dir';
        }

        $suffix = $segments === [] ? 'uploads' : implode(DIRECTORY_SEPARATOR, $segments);

        return rtrim($projectBaseDir, '/\\') . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . $suffix;
    }

    private function buildRelativePath(string $configuredDir, string $filename): string
    {
        $normalized = str_replace('\\', '/', trim($configuredDir));
        $segments = [];

        foreach (explode('/', $normalized) as $segment) {
            $segment = trim($segment);
            if ($segment === '' || $segment === '.' || $segment === '..') {
                continue;
            }
            $segments[] = $segment;
        }

        $prefix = $segments === [] ? '' : implode('/', $segments) . '/';
        return $prefix . $filename;
    }
}
