<?php

declare(strict_types=1);

namespace App\Domain\Entities;

class Client
{
    public function __construct(
        private ?string $id,
        private string $name,
        private string $email,
        private ?string $phone,
        private ?string $company,
        private ?string $createdAt = null,
        private ?string $updatedAt = null
    ) {
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function getCompany(): ?string
    {
        return $this->company;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?string
    {
        return $this->updatedAt;
    }

    /** @return array{id: ?string, name: string, email: string, phone: ?string, company: ?string, created_at: ?string, updated_at: ?string} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'company' => $this->company,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
