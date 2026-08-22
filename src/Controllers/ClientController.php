<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\ClientService;
use InvalidArgumentException;

class ClientController
{
    public function __construct(private readonly ClientService $service)
    {
    }

    /** @return list<array<string, mixed>> */
    public function index(): array
    {
        $clients = $this->service->findAll();

        return array_map(fn ($client) => $client->toArray(), $clients);
    }

    /** @return array<string, mixed> */
    public function show(string $id): array
    {
        $client = $this->service->findById($id);

        if (!$client) {
            http_response_code(404);
            return ['error' => 'Cliente não encontrado'];
        }

        return $client->toArray();
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function store(array $data): array
    {
        try {
            $client = $this->service->create($data);
            http_response_code(201);
            return $client->toArray();
        } catch (InvalidArgumentException $e) {
            http_response_code(400);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function update(string $id, array $data): array
    {
        try {
            $client = $this->service->update($id, $data);
            return $client->toArray();
        } catch (InvalidArgumentException $e) {
            http_response_code(400);
            return ['error' => $e->getMessage()];
        }
    }

    /** @return array<string, string> */
    public function destroy(string $id): array
    {
        try {
            $this->service->delete($id);
            return ['message' => 'Cliente removido'];
        } catch (InvalidArgumentException $e) {
            http_response_code(400);
            return ['error' => $e->getMessage()];
        }
    }
}
