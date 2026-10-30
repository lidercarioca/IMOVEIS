<?php
namespace App\Domain\DTO;

/**
 * PropertyDTO - Data Transfer Object para Propriedades
 *
 * Responsabilidades:
 * - Encapsular dados de propriedade com type safety
 * - Garantir estrutura consistente
 * - Facilitar conversão para/de array
 * - Habilitar testes unitários e validação de tipo
 *
 * IMPORTANTE: Este é um DTO interno, não exposto pela API.
 * Conversões para array mantêm compatibilidade com JSON legado.
 */
class PropertyDTO
{
    // Campos obrigatórios
    public int $id;
    public string $title;
    public string $description;
    public string $location;
    public string $type;
    public string $transactionType;
    public float $price;
    public int $bedrooms;
    public int $bathrooms;
    public float $area;
    public int $yearBuilt;
    public string $status;

    // Campos opcionais (nullable)
    public ?string $neighborhood;
    public ?string $city;
    public ?string $state;
    public ?string $zip;
    public ?string $garage;
    public ?float $condominium;
    public ?float $iptu;
    public ?int $suites;
    public ?int $assigned_user_id;

    // Arrays
    public array $images;
    public array $features;

    /**
     * Construtor privado - use fromArray() para criar instâncias
     */
    private function __construct() {}

    /**
     * Factory method: criar DTO a partir de array (normalmente da DB)
     *
     * @param array $data Dados da propriedade
     * @return self Nova instância do DTO
     * @throws \InvalidArgumentException Se dados obrigatórios estão faltando
     */
    public static function fromArray(array $data): self
    {
        $dto = new self();

        // Campos obrigatórios (com fallback seguro)
        $dto->id = (int)($data['id'] ?? 0);
        if ($dto->id <= 0) {
            throw new \InvalidArgumentException("ID inválido: {$dto->id}");
        }
        
        $dto->title = (string)($data['title'] ?? 'Propriedade sem título');
        $dto->description = (string)($data['description'] ?? '');
        $dto->location = (string)($data['location'] ?? '');
        $dto->type = (string)($data['type'] ?? 'Não especificado');
        $dto->transactionType = (string)($data['transactionType'] ?? 'Não especificado');
        $dto->price = (float)($data['price'] ?? 0);
        $dto->bedrooms = (int)($data['bedrooms'] ?? 0);
        $dto->bathrooms = (int)($data['bathrooms'] ?? 0);
        $dto->area = (float)($data['area'] ?? 0);
        $dto->yearBuilt = (int)($data['yearBuilt'] ?? 0);
        $dto->status = (string)($data['status'] ?? 'Ativo');

        // Campos opcionais
        $dto->neighborhood = isset($data['neighborhood']) ? (string)$data['neighborhood'] : null;
        $dto->city = isset($data['city']) ? (string)$data['city'] : null;
        $dto->state = isset($data['state']) ? (string)$data['state'] : null;
        $dto->zip = isset($data['zip']) ? (string)$data['zip'] : null;
        $dto->garage = isset($data['garage']) ? (string)$data['garage'] : null;
        $dto->condominium = isset($data['condominium']) ? (float)$data['condominium'] : null;
        $dto->iptu = isset($data['iptu']) ? (float)$data['iptu'] : null;
        $dto->suites = isset($data['suites']) ? (int)$data['suites'] : null;
        $dto->assigned_user_id = isset($data['assigned_user_id']) ? (int)$data['assigned_user_id'] : null;

        // Arrays
        $dto->images = is_array($data['images'] ?? null) ? $data['images'] : [];
        $dto->features = is_array($data['features'] ?? null) ? $data['features'] : [];

        return $dto;
    }

    /**
     * Converter DTO para array (compatível com JSON legado)
     *
     * @return array Representação em array do DTO
     */
    public function toArray(): array
    {
        return [
            'id'              => $this->id,
            'title'           => $this->title,
            'description'     => $this->description,
            'location'        => $this->location,
            'neighborhood'    => $this->neighborhood,
            'city'            => $this->city,
            'state'           => $this->state,
            'zip'             => $this->zip,
            'price'           => $this->price,
            'type'            => $this->type,
            'transactionType' => $this->transactionType,
            'images'          => $this->images,
            'features'        => $this->features,
            'bedrooms'        => $this->bedrooms,
            'bathrooms'       => $this->bathrooms,
            'garage'          => $this->garage,
            'area'            => $this->area,
            'yearBuilt'       => $this->yearBuilt,
            'status'          => $this->status,
            'condominium'     => $this->condominium,
            'iptu'            => $this->iptu,
            'suites'          => $this->suites,
            'assigned_user_id' => $this->assigned_user_id,
        ];
    }
}
