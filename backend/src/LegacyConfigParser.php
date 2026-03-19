<?php

declare(strict_types=1);

namespace App;

final class LegacyConfigParser
{
    /**
     * @return array<string, mixed>
     */
    public function parseFile(string $filePath): array
    {
        if (!is_file($filePath)) {
            throw new \RuntimeException('Legacy config file not found: ' . $filePath);
        }

        $raw = file_get_contents($filePath);
        if ($raw === false) {
            throw new \RuntimeException('Could not read config file: ' . $filePath);
        }

        return $this->parseString($raw);
    }

    /**
     * @return array<string, mixed>
     */
    public function parseString(string $raw): array
    {
        $state = [
            'config' => [],
            'forms' => [],
        ];

        $currentSectionType = null;
        $currentSectionName = null;
        $currentPart = null;
        $currentField = null;

        $lines = preg_split('/\r\n|\n|\r/', $raw) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, '<?PHP')) {
                continue;
            }

            if ($line === 'END_CONFIG') {
                break;
            }

            if (preg_match('/^\[(type|form):\s*([^\]]+)\]$/i', $line, $match) === 1) {
                $currentSectionType = strtolower(trim($match[1]));
                $currentSectionName = trim($match[2]);
                $currentPart = null;
                $currentField = null;

                if ($currentSectionType === 'form') {
                    if (!isset($state['forms'][$currentSectionName])) {
                        $state['forms'][$currentSectionName] = [
                            'name' => $currentSectionName,
                            'meta' => [],
                            'parts' => [],
                            'fields' => [],
                        ];
                    }
                }
                continue;
            }

            if (preg_match('/^<(part|button):\s*([^>]+)>$/i', $line, $match) === 1) {
                $currentPart = trim($match[2]);
                $currentField = null;

                if ($currentSectionType === 'type' && $currentSectionName === 'config') {
                    $state['config'][$currentPart] ??= [];
                }

                if ($currentSectionType === 'form' && $currentSectionName !== null) {
                    $state['forms'][$currentSectionName]['parts'][$currentPart] ??= [];
                }
                continue;
            }

            if (preg_match('/^<field:\s*([^>]+)>$/i', $line, $match) === 1) {
                $currentField = trim($match[1]);
                if ($currentSectionType === 'form' && $currentSectionName !== null) {
                    $state['forms'][$currentSectionName]['fields'][$currentField] ??= [
                        'key' => $currentField,
                    ];
                }
                continue;
            }

            if (preg_match('/^([A-Za-z0-9_]+)\s*=\s*(.*)$/', $line, $match) === 1) {
                $key = trim($match[1]);
                $value = trim($match[2]);

                if ($currentSectionType === 'type' && $currentSectionName === 'config' && $currentPart !== null) {
                    $state['config'][$currentPart][$key] = $value;
                    continue;
                }

                if ($currentSectionType === 'form' && $currentSectionName !== null) {
                    if ($currentField !== null) {
                        $state['forms'][$currentSectionName]['fields'][$currentField][$key] = $value;
                    } elseif ($currentPart !== null) {
                        $state['forms'][$currentSectionName]['parts'][$currentPart][$key] = $value;

                        // Mirror the default part into top-level metadata for easier API access.
                        if ($currentPart === 'main') {
                            $state['forms'][$currentSectionName]['meta'][$key] = $value;
                        }
                    } else {
                        $state['forms'][$currentSectionName]['meta'][$key] = $value;
                    }
                }
            }
        }

        return $state;
    }
}
