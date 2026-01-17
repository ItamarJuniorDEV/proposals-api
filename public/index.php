<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Controllers\ClientController;
use App\Controllers\ContractController;
use App\Controllers\ProposalController;
use App\Http\Routes\Router;
use App\Infrastructure\Database\Connection;
use App\Infrastructure\Persistence\ClientRepository;
use App\Infrastructure\Persistence\ContractRepository;
use App\Infrastructure\Persistence\ProposalItemRepository;
use App\Infrastructure\Persistence\ProposalRepository;
use App\Services\ClientService;
use App\Services\ContractService;
use App\Services\ProposalService;

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

function readJsonBody(): array
{
    $raw = file_get_contents('php://input');

    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $data = json_decode($raw, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'JSON inválido'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    return is_array($data) ? $data : [];
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

$router->get('/', fn () => ['status' => 'ok', 'api' => 'proposals']);

$router->get('/clients', fn () => $clientController->index());
$router->get('/clients/{id}', fn ($id) => $clientController->show($id));
$router->post('/clients', fn () => $clientController->store(readJsonBody()));
$router->put('/clients/{id}', fn ($id) => $clientController->update($id, readJsonBody()));
$router->delete('/clients/{id}', fn ($id) => $clientController->destroy($id));

$router->get('/proposals', fn () => $proposalController->index());
$router->get('/proposals/{id}', fn ($id) => $proposalController->show($id));
$router->post('/proposals', fn () => $proposalController->store(readJsonBody()));
$router->put('/proposals/{id}', fn ($id) => $proposalController->update($id, readJsonBody()));
$router->delete('/proposals/{id}', fn ($id) => $proposalController->destroy($id));
$router->post('/proposals/{id}/send', fn ($id) => $proposalController->send($id));
$router->post('/proposals/{id}/approve', fn ($id) => $proposalController->approve($id));
$router->post('/proposals/{id}/reject', fn ($id) => $proposalController->reject($id));
$router->post('/proposals/{id}/revise', fn ($id) => $proposalController->revise($id));

$router->post('/proposals/{id}/items', fn ($id) => $proposalController->addItem($id, readJsonBody()));
$router->put('/proposals/{id}/items/{itemId}', fn ($id, $itemId) => $proposalController->updateItem($id, $itemId, readJsonBody()));
$router->delete('/proposals/{id}/items/{itemId}', fn ($id, $itemId) => $proposalController->removeItem($id, $itemId));

$router->get('/contracts', fn () => $contractController->index());
$router->get('/contracts/{id}', fn ($id) => $contractController->show($id));

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';

try {
    $response = $router->resolve($method, $path);

    if (!is_array($response)) {
        $response = ['data' => $response];
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno'], JSON_UNESCAPED_UNICODE);
}
