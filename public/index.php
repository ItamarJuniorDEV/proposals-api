<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Controllers\ClientController;
use App\Controllers\ContractController;
use App\Controllers\ProposalController;
use App\Http\Routes\Router;
use App\Http\Security\BearerTokenGuard;
use App\Infrastructure\Database\Connection;
use App\Infrastructure\Persistence\ClientRepository;
use App\Infrastructure\Persistence\ContractRepository;
use App\Infrastructure\Persistence\ProposalItemRepository;
use App\Infrastructure\Persistence\ProposalRepository;
use App\Services\ClientService;
use App\Services\ContractService;
use App\Services\ProposalService;

const MAX_JSON_BODY_BYTES = 1048576;

$envPath = __DIR__ . '/../.env';

if (is_file($envPath)) {
    $env = parse_ini_file($envPath, false, INI_SCANNER_TYPED) ?: [];

    foreach ($env as $key => $value) {
        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
        }
    }
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

if ($method === 'GET' && $path === '/') {
    echo json_encode(['status' => 'ok', 'api' => 'proposals'], JSON_UNESCAPED_UNICODE);
    exit;
}

$configuredToken = $_ENV['API_TOKEN'] ?? '';
$guard = new BearerTokenGuard(is_string($configuredToken) ? $configuredToken : '');

if (!$guard->isConfigured()) {
    error_log('security.api_token_not_configured');
    http_response_code(503);
    echo json_encode(['error' => 'Serviço indisponível'], JSON_UNESCAPED_UNICODE);
    exit;
}

$authorizationHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;
$authorizationHeader = is_string($authorizationHeader) ? $authorizationHeader : null;

if (!$guard->allows($authorizationHeader)) {
    error_log('security.auth_failed');
    http_response_code(401);
    header('WWW-Authenticate: Bearer');
    echo json_encode(['error' => 'Não autorizado'], JSON_UNESCAPED_UNICODE);
    exit;
}

/** @return array<string, mixed> */
function readJsonBody(): array
{
    $contentLength = $_SERVER['CONTENT_LENGTH'] ?? null;

    if (is_string($contentLength) && ctype_digit($contentLength) && (int) $contentLength > MAX_JSON_BODY_BYTES) {
        http_response_code(413);
        echo json_encode(['error' => 'Payload muito grande'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stream = fopen('php://input', 'rb');

    if ($stream === false) {
        throw new RuntimeException('Não foi possível ler o corpo da requisição');
    }

    $raw = stream_get_contents($stream, MAX_JSON_BODY_BYTES + 1);
    fclose($stream);

    if ($raw === false) {
        throw new RuntimeException('Não foi possível ler o corpo da requisição');
    }

    if (strlen($raw) > MAX_JSON_BODY_BYTES) {
        http_response_code(413);
        echo json_encode(['error' => 'Payload muito grande'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (trim($raw) === '') {
        return [];
    }

    $data = json_decode($raw, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'JSON inválido'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['error' => 'JSON deve ser um objeto'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    foreach (array_keys($data) as $key) {
        if (!is_string($key)) {
            http_response_code(400);
            echo json_encode(['error' => 'JSON deve ser um objeto'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    /** @var array<string, mixed> $data */
    return $data;
}

$pdo = Connection::getInstance();

$clientRepository = new ClientRepository($pdo);
$proposalRepository = new ProposalRepository($pdo);
$itemRepository = new ProposalItemRepository($pdo);
$contractRepository = new ContractRepository($pdo);

$clientService = new ClientService($clientRepository);

$proposalService = new ProposalService(
    $proposalRepository,
    $itemRepository,
    $clientRepository,
    $contractRepository,
    $pdo
);

$contractService = new ContractService(
    $contractRepository,
    $proposalRepository,
    $itemRepository
);

$clientController = new ClientController($clientService);
$proposalController = new ProposalController($proposalService);
$contractController = new ContractController($contractService);

$router = new Router();

$router->get('/clients', fn () => $clientController->index());
$router->get('/clients/{id:uuid}', fn ($id) => $clientController->show($id));
$router->post('/clients', fn () => $clientController->store(readJsonBody()));
$router->put('/clients/{id:uuid}', fn ($id) => $clientController->update($id, readJsonBody()));
$router->delete('/clients/{id:uuid}', fn ($id) => $clientController->destroy($id));

$router->get('/proposals', fn () => $proposalController->index());
$router->get('/proposals/{id:uuid}', fn ($id) => $proposalController->show($id));
$router->post('/proposals', fn () => $proposalController->store(readJsonBody()));
$router->put('/proposals/{id:uuid}', fn ($id) => $proposalController->update($id, readJsonBody()));
$router->delete('/proposals/{id:uuid}', fn ($id) => $proposalController->destroy($id));
$router->post('/proposals/{id:uuid}/send', fn ($id) => $proposalController->send($id));
$router->post('/proposals/{id:uuid}/approve', fn ($id) => $proposalController->approve($id));
$router->post('/proposals/{id:uuid}/reject', fn ($id) => $proposalController->reject($id));
$router->post('/proposals/{id:uuid}/revise', fn ($id) => $proposalController->revise($id));

$router->post('/proposals/{id:uuid}/items', fn ($id) => $proposalController->addItem($id, readJsonBody()));
$router->put('/proposals/{id:uuid}/items/{itemId:uuid}', fn ($id, $itemId) => $proposalController->updateItem($id, $itemId, readJsonBody()));
$router->delete('/proposals/{id:uuid}/items/{itemId:uuid}', fn ($id, $itemId) => $proposalController->removeItem($id, $itemId));

$router->get('/contracts', fn () => $contractController->index());
$router->get('/contracts/{id:uuid}', fn ($id) => $contractController->show($id));

try {
    $response = $router->resolve($method, $path);

    if (!is_array($response)) {
        $response = ['data' => $response];
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
} catch (\Throwable $e) {
    error_log('request.failed exception='.$e::class);
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno'], JSON_UNESCAPED_UNICODE);
}
