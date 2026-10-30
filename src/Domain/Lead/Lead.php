<?php

namespace App\Domain\Lead;

class Lead
{
    public ?int $id;
    public int $tenantId;
    public ?int $propertyId;
    public ?int $assignedUserId;
    public string $name;
    public string $email;
    public string $phone;
    public string $message;
    public string $status;
    public string $source;
    public string $createdAt;
    public string $updatedAt;

    public function __construct(array $data)
    {
        $this->id = isset($data['id']) ? (int)$data['id'] : null;
        $this->tenantId = (int)$data['tenant_id'];
        $this->propertyId = isset($data['property_id']) ? ($data['property_id'] !== null ? (int)$data['property_id'] : null) : null;
        $this->assignedUserId = isset($data['assigned_user_id']) ? ($data['assigned_user_id'] !== null ? (int)$data['assigned_user_id'] : null) : null;
        $this->name = trim((string)($data['name'] ?? ''));
        $this->email = trim((string)($data['email'] ?? ''));
        $this->phone = trim((string)($data['phone'] ?? ''));
        $this->message = trim((string)($data['message'] ?? ''));
        $this->status = trim((string)($data['status'] ?? 'new'));
        $this->source = trim((string)($data['source'] ?? 'site'));
        $this->createdAt = $data['created_at'] ?? date('Y-m-d H:i:s');
        $this->updatedAt = $data['updated_at'] ?? $this->createdAt;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenantId,
            'property_id' => $this->propertyId,
            'assigned_user_id' => $this->assignedUserId,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'message' => $this->message,
            'status' => $this->status,
            'source' => $this->source,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self($data);
    }
}
