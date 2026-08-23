<?php

declare(strict_types=1);

namespace Tests\Integration\Persistence;

use App\Domain\Entities\Client;
use App\Domain\Entities\Contract;
use App\Domain\Enums\ProposalStatus;
use App\Infrastructure\Persistence\ClientRepository;
use App\Infrastructure\Persistence\ContractRepository;
use App\Infrastructure\Persistence\ProposalItemRepository;
use App\Infrastructure\Persistence\ProposalRepository;
use App\Services\ProposalService;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

final class PersistenceIntegrationTest extends TestCase
{
    private static PDO $pdo;

    private ClientRepository $clientRepository;
    private ContractRepository $contractRepository;
    private ProposalItemRepository $itemRepository;
    private ProposalRepository $proposalRepository;
    private ProposalService $proposalService;

    public static function setUpBeforeClass(): void
    {
        $host = self::requiredEnv('DB_HOST');
        $port = self::requiredEnv('DB_PORT');
        $database = self::requiredEnv('DB_NAME');
        $user = self::requiredEnv('DB_USER');
        $password = self::requiredEnv('DB_PASSWORD');

        self::$pdo = new PDO(
            "pgsql:host={$host};port={$port};dbname={$database}",
            $user,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );

        $migrations = glob(__DIR__.'/../../../database/migrations/*.sql');

        if ($migrations === false || $migrations === []) {
            self::fail('Migrations não encontradas');
        }

        sort($migrations);

        foreach ($migrations as $migration) {
            $sql = file_get_contents($migration);

            if ($sql === false) {
                self::fail("Não foi possível ler {$migration}");
            }

            self::$pdo->exec($sql);
        }
    }

    protected function setUp(): void
    {
        self::$pdo->exec('TRUNCATE TABLE contracts, proposal_items, proposals, clients CASCADE');

        $this->clientRepository = new ClientRepository(self::$pdo);
        $this->proposalRepository = new ProposalRepository(self::$pdo);
        $this->itemRepository = new ProposalItemRepository(self::$pdo);
        $this->contractRepository = new ContractRepository(self::$pdo);
        $this->proposalService = new ProposalService(
            $this->proposalRepository,
            $this->itemRepository,
            $this->clientRepository,
            $this->contractRepository,
            self::$pdo
        );
    }

    public function testProposalLifecyclePersistsApprovalAndContract(): void
    {
        $proposalId = $this->createSentProposal();

        $contract = $this->proposalService->approve($proposalId);
        $storedProposal = $this->proposalRepository->findById($proposalId);
        $storedContract = $this->contractRepository->findByProposalId($proposalId);

        $this->assertNotNull($storedProposal);
        $this->assertSame(ProposalStatus::Approved, $storedProposal->getStatus());
        $this->assertNotNull($storedContract);
        $this->assertSame($contract->getId(), $storedContract->getId());
        $this->assertSame(180.00, $storedContract->getTotalAmount());
    }

    public function testApprovalRollsBackStatusWhenContractInsertConflicts(): void
    {
        $proposalId = $this->createSentProposal();

        $this->contractRepository->create(new Contract(
            id: null,
            proposalId: $proposalId,
            totalAmount: 180.00
        ));

        try {
            $this->proposalService->approve($proposalId);
            $this->fail('Aprovação deveria falhar por contrato duplicado');
        } catch (PDOException) {
            $storedProposal = $this->proposalRepository->findById($proposalId);
            $this->assertNotNull($storedProposal);
            $this->assertSame(ProposalStatus::Sent, $storedProposal->getStatus());

            $count = self::$pdo
                ->query("SELECT COUNT(*) FROM contracts WHERE proposal_id = '{$proposalId}'")
                ->fetchColumn();

            $this->assertSame(1, (int) $count);
        }
    }

    private function createSentProposal(): string
    {
        $client = $this->clientRepository->create(new Client(
            id: null,
            name: 'Cliente Integração',
            email: 'integracao@example.com',
            phone: null,
            company: 'Empresa Teste'
        ));

        $clientId = $client->getId();
        $this->assertNotNull($clientId);

        $proposal = $this->proposalService->create([
            'client_id' => $clientId,
            'discount_percent' => 10,
            'valid_until' => '2030-12-31',
        ]);

        $proposalId = $proposal->getId();
        $this->assertNotNull($proposalId);

        $this->proposalService->addItem($proposalId, [
            'description' => 'Serviço de integração',
            'quantity' => 2,
            'unit_price' => 100,
        ]);

        $sent = $this->proposalService->send($proposalId);
        $this->assertSame(ProposalStatus::Sent, $sent->getStatus());

        return $proposalId;
    }

    private static function requiredEnv(string $key): string
    {
        $value = getenv($key);

        if ($value === false || trim($value) === '') {
            self::fail("Variável de ambiente ausente: {$key}");
        }

        return trim($value);
    }
}
