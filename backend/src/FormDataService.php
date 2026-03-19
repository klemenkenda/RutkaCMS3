<?php

declare(strict_types=1);

namespace App;

use PDO;

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
}
