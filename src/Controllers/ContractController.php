<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\ContractService;

class ContractController
{
    public function __construct(private readonly ContractService $service)
    {
    }

    /** @return list<array<string, mixed>> */
    public function index(): array
    {
        $contracts = $this->service->findAll();

        return array_map(fn ($contract) => $contract->toArray(), $contracts);
    }

    /** @return array<string, mixed> */
    public function show(string $id): array
    {
        $result = $this->service->findById($id);

        if (!$result) {
            http_response_code(404);
            return ['error' => 'Contrato não encontrado'];
        }

        return [
            'contract' => $result['contract']->toArray(),
            'proposal' => $result['proposal']->toArray(),
            'items' => array_map(fn ($item) => $item->toArray(), $result['items']),
        ];
    }
}
