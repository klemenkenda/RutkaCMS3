<?php

declare(strict_types=1);

use App\Database;
use App\FormDataService;
use App\LegacyConfigParser;
use App\Env;

require_once dirname(__DIR__) . '/src/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

$origin = Env::get('API_ALLOWED_ORIGIN', '*');
header('Access-Control-Allow-Origin: ' . $origin);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$baseDir = dirname(__DIR__);
$configPath = Env::get('LEGACY_CONFIG_PATH', 'config/legacy-config.example.txt');
$configAbsolute = str_starts_with($configPath, '/') || preg_match('/^[A-Za-z]:\\\\/', $configPath) === 1
    ? $configPath
    : $baseDir . '/' . $configPath;

try {
    $parser = new LegacyConfigParser();
    $parsedConfig = $parser->parseFile($configAbsolute);

    $db = new Database();
    $service = new FormDataService($db->pdo(), $parsedConfig);

    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($path === '/health' && $method === 'GET') {
        jsonResponse(200, ['status' => 'ok']);
    }

    if ($path === '/api/forms' && $method === 'GET') {
        jsonResponse(200, ['forms' => $service->listForms()]);
    }

    if (preg_match('#^/api/forms/([A-Za-z_][A-Za-z0-9_]*)$#', $path, $m) === 1 && $method === 'GET') {
        jsonResponse(200, $service->getFormSchema($m[1]));
    }

    if (preg_match('#^/api/forms/([A-Za-z_][A-Za-z0-9_]*)/options/([A-Za-z_][A-Za-z0-9_]*)$#', $path, $m) === 1 && $method === 'GET') {
        jsonResponse(200, ['options' => $service->getFieldOptions($m[1], $m[2])]);
    }

    if (preg_match('#^/api/forms/([A-Za-z_][A-Za-z0-9_]*)/upload/([A-Za-z_][A-Za-z0-9_]*)$#', $path, $m) === 1 && $method === 'POST') {
        $file = $_FILES['file'] ?? null;
        if (!is_array($file)) {
            jsonResponse(400, ['error' => 'Missing upload payload: file']);
        }

        $result = $service->uploadFieldFile($m[1], $m[2], $file, $baseDir);
        jsonResponse(200, $result);
    }

    if (preg_match('#^/api/forms/([A-Za-z_][A-Za-z0-9_]*)/entries$#', $path, $m) === 1) {
        $form = $m[1];

        if ($method === 'GET') {
            $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 50;
            $offset = isset($_GET['offset']) ? (int) $_GET['offset'] : 0;
            jsonResponse(200, ['data' => $service->listEntries($form, $limit, $offset)]);
        }

        if ($method === 'POST') {
            $payload = jsonBody();
            jsonResponse(201, $service->createEntry($form, $payload));
        }
    }

    if (preg_match('#^/api/forms/([A-Za-z_][A-Za-z0-9_]*)/entries/(\d+)$#', $path, $m) === 1) {
        $form = $m[1];
        $id = (int) $m[2];

        if ($method === 'PUT') {
            $payload = jsonBody();
            jsonResponse(200, $service->updateEntry($form, $id, $payload));
        }

        if ($method === 'DELETE') {
            jsonResponse(200, $service->deleteEntry($form, $id));
        }
    }

    jsonResponse(404, ['error' => 'Not found']);
} catch (Throwable $e) {
    jsonResponse(500, [
        'error' => 'Server error',
        'message' => Env::get('APP_DEBUG', 'false') === 'true' ? $e->getMessage() : 'Internal error',
    ]);
}

/**
 * @return array<string, mixed>
 */
function jsonBody(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        throw new InvalidArgumentException('Invalid JSON payload');
    }

    return $data;
}

/**
 * @param array<string, mixed> $payload
 */
function jsonResponse(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
