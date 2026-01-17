<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\ProposalService;
use InvalidArgumentException;

class ProposalController
{
    public function __construct(private ProposalService $service)
    {
    }

    public function index(): array
    {
        $proposals = $this->service->findAll();

        return array_map(fn ($proposal) => $proposal->toArray(), $proposals);
    }

    public function show(string $id): array
    {
        $result = $this->service->findById($id);

        if (!$result) {
            http_response_code(404);
            return ['error' => 'Proposta não encontrada'];
        }

        return [
            'proposal' => $result['proposal']->toArray(),
            'items' => array_map(fn ($item) => $item->toArray(), $result['items']),
            'totals' => $result['totals'],
        ];
    }

    public function store(array $data): array
    {
        try {
            $proposal = $this->service->create($data);
            http_response_code(201);
            return $proposal->toArray();
        } catch (InvalidArgumentException $e) {
            http_response_code(400);
            return ['error' => $e->getMessage()];
        }
    }

    public function update(string $id, array $data): array
    {
        try {
            $proposal = $this->service->update($id, $data);
            return $proposal->toArray();
        } catch (InvalidArgumentException $e) {
            http_response_code(400);
            return ['error' => $e->getMessage()];
        }
    }

    public function destroy(string $id): array
    {
        try {
            $this->service->delete($id);
            return ['message' => 'Proposta removida'];
        } catch (InvalidArgumentException $e) {
            http_response_code(400);
            return ['error' => $e->getMessage()];
        }
    }

    public function send(string $id): array
    {
        try {
            $proposal = $this->service->send($id);
            return $proposal->toArray();
        } catch (InvalidArgumentException $e) {
            http_response_code(400);
            return ['error' => $e->getMessage()];
        }
    }

    public function approve(string $id): array
    {
        try {
            $contract = $this->service->approve($id);
            http_response_code(201);
            return $contract->toArray();
        } catch (InvalidArgumentException $e) {
            http_response_code(400);
            return ['error' => $e->getMessage()];
        }
    }

    public function reject(string $id): array
    {
        try {
            $proposal = $this->service->reject($id);
            return $proposal->toArray();
        } catch (InvalidArgumentException $e) {
            http_response_code(400);
            return ['error' => $e->getMessage()];
        }
    }

    public function revise(string $id): array
    {
        try {
            $proposal = $this->service->revise($id);
            http_response_code(201);
            return $proposal->toArray();
        } catch (InvalidArgumentException $e) {
            http_response_code(400);
            return ['error' => $e->getMessage()];
        }
    }

    public function addItem(string $id, array $data): array
    {
        try {
            $item = $this->service->addItem($id, $data);
            http_response_code(201);
            return $item->toArray();
        } catch (InvalidArgumentException $e) {
            http_response_code(400);
            return ['error' => $e->getMessage()];
        }
    }

    public function updateItem(string $id, string $itemId, array $data): array
    {
        try {
            $item = $this->service->updateItem($id, $itemId, $data);
            return $item->toArray();
        } catch (InvalidArgumentException $e) {
            http_response_code(400);
            return ['error' => $e->getMessage()];
        }
    }

    public function removeItem(string $id, string $itemId): array
    {
        try {
            $this->service->removeItem($id, $itemId);
            return ['message' => 'Item removido'];
        } catch (InvalidArgumentException $e) {
            http_response_code(400);
            return ['error' => $e->getMessage()];
        }
    }
}
