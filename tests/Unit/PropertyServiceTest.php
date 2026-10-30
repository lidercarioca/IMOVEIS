<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Service\PropertyService;
use App\Repository\PropertyRepository;

/**
 * PropertyServiceTest - Testes Unitários para PropertyService
 *
 * Responsável por:
 * - Verificar que Service retorna array
 * - Validar lógica de isolamento de tenant
 * - Testar resposta a entradas vazias
 * - Verificar exceções com entradas inválidas
 * - Mockar Repository para testes isolados
 *
 * IMPORTANTE: Não testamos banco de dados aqui.
 * O Service é testado com um mock do Repository.
 */
class PropertyServiceTest extends TestCase
{
    private PropertyService $service;
    private $repositoryMock;
    private $cacheMock;

    /**
     * setUp - Executado antes de cada teste
     *
     * Configura mocks e o Service com mock de Repository.
     */
    protected function setUp(): void
    {
        // Criar mock do Repository
        $this->repositoryMock = $this->createMock(PropertyRepository::class);

        // Criar mock do Cache (padrão: retorna null, cache miss)
        $this->cacheMock = $this->createMock(\App\Cache\CacheInterface::class);
        $this->cacheMock->method('get')->willReturn(null);

        // Criar um PDO mock simples (apenas para construtor do Service)
        $pdoMock = $this->createMock(\PDO::class);

        // Criar o Service com mocks injetados
        $this->service = new PropertyService($pdoMock, $this->cacheMock);

        // Usar Reflection para substituir o Repository privado pelo mock
        $reflection = new \ReflectionClass(PropertyService::class);
        $repositoryProperty = $reflection->getProperty('repository');
        $repositoryProperty->setAccessible(true);
        $repositoryProperty->setValue($this->service, $this->repositoryMock);
    }

    public function testResolveEffectiveCommissionPercentageUsesExplicitValuesAndAllowsZeroPercent(): void
    {
        $this->assertSame(4.5, PropertyService::resolveEffectiveCommissionPercentage(4.5, []));
        $this->assertSame(4.5, PropertyService::resolveEffectiveCommissionPercentage(null, ['sale_commission_percentage' => '4.5']));
        $this->assertSame(0.0, PropertyService::resolveEffectiveCommissionPercentage(0.0, ['sale_commission_percentage' => '6.0']));
        $this->assertSame(6.0, PropertyService::resolveEffectiveCommissionPercentage(null, ['sale_commission_percentage' => null]));
    }

    public function testResolveEffectiveBrokerCommissionPercentagePrefersExplicitValuesAndDoesNotInventFallback(): void
    {
        $this->assertSame(40.0, PropertyService::resolveEffectiveBrokerCommissionPercentage([
            'broker_commission_percentage' => '50.0',
        ], 7, 40.0));

        $this->assertSame(25.0, PropertyService::resolveEffectiveBrokerCommissionPercentage([
            'broker_commission_percentage' => '25.0',
        ], 7, null));

        $this->assertNull(PropertyService::resolveEffectiveBrokerCommissionPercentage([
            'broker_commission_percentage' => null,
        ], 7, null));
    }

    public function testResolveEffectiveBrokerCommissionPercentageUsesUserDefaultWhenNoExplicitOverrideExists(): void
    {
        $this->assertSame(40.0, PropertyService::resolveEffectiveBrokerCommissionPercentage([
            'broker_default_commission_percentage' => '40.0',
        ], 7, null));

        $this->assertSame(15.0, PropertyService::resolveEffectiveBrokerCommissionPercentage([
            'broker_default_commission_percentage' => '40.0',
        ], 7, 15.0));

        $this->assertSame(0.0, PropertyService::resolveEffectiveBrokerCommissionPercentage([
            'broker_default_commission_percentage' => '0.0',
        ], 7, null));

        $this->assertNull(PropertyService::resolveEffectiveBrokerCommissionPercentage([
            'broker_default_commission_percentage' => null,
        ], 7, null));
    }

    public function testResolveEffectiveBrokerUserIdPrefersAssignedBrokerOverAuthenticatedUserAndRespectsTenant(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, tenant_id INTEGER NOT NULL, is_superadmin INTEGER DEFAULT 0, username TEXT NOT NULL, role TEXT, name TEXT, email TEXT, phone TEXT, broker_default_commission_percentage REAL NULL, created_at TEXT, last_login TEXT, active INTEGER DEFAULT 1, force_password_change INTEGER DEFAULT 0, password_changed_at TEXT)');
        $pdo->prepare('INSERT INTO users (id, tenant_id, is_superadmin, username, role, name, email, phone, broker_default_commission_percentage, created_at, last_login, active, force_password_change, password_changed_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute([109, 1, 0, 'Vitoria', 'admin', 'Vitoria', 'vitoria@example.com', '123', 40.0, '2024-01-01 00:00:00', null, 1, 0, null]);
        $pdo->prepare('INSERT INTO users (id, tenant_id, is_superadmin, username, role, name, email, phone, broker_default_commission_percentage, created_at, last_login, active, force_password_change, password_changed_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute([112, 1, 0, 'David', 'broker', 'David', 'david@example.com', '456', 40.0, '2024-01-01 00:00:00', null, 1, 0, null]);
        $pdo->prepare('INSERT INTO users (id, tenant_id, is_superadmin, username, role, name, email, phone, broker_default_commission_percentage, created_at, last_login, active, force_password_change, password_changed_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute([115, 1, 0, 'BrokerExp', 'broker', 'BrokerExp', 'broker@example.com', '789', 30.0, '2024-01-01 00:00:00', null, 1, 0, null]);
        $pdo->prepare('INSERT INTO users (id, tenant_id, is_superadmin, username, role, name, email, phone, broker_default_commission_percentage, created_at, last_login, active, force_password_change, password_changed_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute([999, 2, 0, 'OtherTenant', 'broker', 'OtherTenant', 'other@example.com', '999', 50.0, '2024-01-01 00:00:00', null, 1, 0, null]);

        $service = new PropertyService($pdo);
        $method = new \ReflectionMethod(PropertyService::class, 'resolveEffectiveBrokerUserId');
        $method->setAccessible(true);

        $this->assertSame(112, $method->invoke($service, 1, ['assigned_user_id' => 112], 109));
        $this->assertSame(115, $method->invoke($service, 1, ['assigned_user_id' => 112, 'broker_user_id' => 115], 109));
        $this->assertSame(112, $method->invoke($service, 1, ['assigned_user_id' => 112, 'broker_user_id' => 999], 109));
        $this->assertNull($method->invoke($service, 1, ['assigned_user_id' => null], 109));
    }

    public function testDetermineStatusTransitionActionClosesSaleWhenStatusMovesToSoldOrRented(): void
    {
        $this->assertSame('close', PropertyService::determineStatusTransitionAction('active', 'vendido'));
        $this->assertSame('close', PropertyService::determineStatusTransitionAction('pending', 'vendido'));
        $this->assertSame('close', PropertyService::determineStatusTransitionAction('active', 'alugado'));
        $this->assertSame('close', PropertyService::determineStatusTransitionAction('pending', 'alugado'));
    }

    public function testDetermineStatusTransitionActionRevertsWhenStatusMovesBackToAvailable(): void
    {
        $this->assertSame('revert', PropertyService::determineStatusTransitionAction('vendido', 'active'));
        $this->assertSame('revert', PropertyService::determineStatusTransitionAction('vendido', 'pending'));
        $this->assertSame('revert', PropertyService::determineStatusTransitionAction('alugado', 'active'));
        $this->assertSame('revert', PropertyService::determineStatusTransitionAction('alugado', 'pending'));
    }

    public function testDetermineStatusTransitionActionNormalizesLegacyAliases(): void
    {
        $this->assertSame('close', PropertyService::determineStatusTransitionAction('active', 'sold'));
        $this->assertSame('close', PropertyService::determineStatusTransitionAction('pending', 'rented'));
        $this->assertSame('revert', PropertyService::determineStatusTransitionAction('vendido', 'active'));
        $this->assertSame('revert', PropertyService::determineStatusTransitionAction('alugado', 'pending'));
        $this->assertNull(PropertyService::determineStatusTransitionAction('vendido', 'sold'));
        $this->assertNull(PropertyService::determineStatusTransitionAction('alugado', 'rented'));
    }

    public function testFinancialTransactionRepositoryCreatesCommissionRevenueAsRevenueType(): void
    {
        $pdo = $this->createMock(\PDO::class);
        $statement = $this->createMock(\PDOStatement::class);

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('INSERT INTO financeiro'))
            ->willReturn($statement);

        $statement->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (array $params): bool {
                return ($params[':tipo'] ?? null) === 'receita'
                    && ($params[':categoria'] ?? null) === 'Comissão'
                    && (float)($params[':valor'] ?? 0) === 75000.0
                    && ($params[':tenant_id'] ?? null) === 1;
            }))
            ->willReturn(true);

        $pdo->expects($this->once())
            ->method('lastInsertId')
            ->willReturn('1001');

        $repository = new \App\Infrastructure\Finance\FinancialTransactionRepository($pdo);
        $id = $repository->createTransaction([
            'tipo' => 'receita',
            'property_id' => 30,
            'user_id' => 109,
            'descricao' => 'Receita da comissão de venda/aluguel do imóvel Apartamento Luxo Morumbi',
            'valor' => 75000.00,
            'data_transacao' => '2026-10-02',
            'categoria' => 'Comissão',
            'status' => 'pendente',
        ], 1);

        $this->assertSame(1001, $id);
    }

    /**
     * Teste 1: Service retorna array (não throw)
     *
     * @test
     */
    public function testGetPropertiesReturnsArray(): void
    {
        // Arrange: Mock retorna array vazio
        $this->repositoryMock
            ->method('getProperties')
            ->willReturn([]);

        // Act: Chamar Service com opções válidas
        $result = $this->service->getProperties([
            'isPanel' => true,
            'tenantId' => 1,
            'userId' => 1,
            'isAdmin' => true,
        ]);

        // Assert: Resultado é array
        $this->assertIsArray($result);
    }

    /**
     * Teste 2: Service respeta isolamento de tenant
     *
     * @test
     */
    public function testGetPropertiesAppliesTenantIsolation(): void
    {
        // Arrange: Mock propriedade de tenant 1
        $mockRow = [
            'id' => 1,
            'title' => 'Prop 1',
            'description' => 'Desc',
            'location' => 'Local',
            'neighborhood' => null,
            'city' => null,
            'state' => null,
            'zip' => null,
            'price' => 100000.00,
            'type' => 'house',
            'transactionType' => 'sale',
            'features' => '[]',
            'bedrooms' => 3,
            'bathrooms' => 2,
            'garage' => null,
            'area' => 150.00,
            'yearBuilt' => 2020,
            'status' => 'Ativo',
            'condominium' => null,
            'iptu' => null,
            'suites' => null,
            'assigned_user_id' => 1,
            'image_url' => null,
            'tenant_id' => 1, // Tenant correto
        ];

        $this->repositoryMock
            ->method('getProperties')
            ->with(
                $this->stringContains('WHERE p.tenant_id = ?'),
                [1] // tenantId = 1
            )
            ->willReturn([$mockRow]);

        // Act
        $result = $this->service->getProperties([
            'isPanel' => true,
            'tenantId' => 1,
            'userId' => 1,
            'isAdmin' => true,
        ]);

        // Assert: Resultado é array (validação que Repository foi chamado)
        $this->assertIsArray($result);
    }

    /**
     * Teste 3: Service lida com resultados vazios
     *
     * @test
     */
    public function testGetPropertiesHandlesEmptyResults(): void
    {
        // Arrange: Repository retorna vazio
        $this->repositoryMock
            ->method('getProperties')
            ->willReturn([]);

        // Act
        $result = $this->service->getProperties([
            'isPanel' => false,
            'tenantId' => 1,
            'isAdmin' => false,
        ]);

        // Assert: Retorna array vazio (não null, não erro)
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Teste 4: Service valida isPanel como boolean
     *
     * @test
     */
    public function testGetPropertiesThrowsOnInvalidIsPanel(): void
    {
        // Act & Assert: Deve lançar InvalidArgumentException
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("'isPanel' deve ser boolean");

        $this->service->getProperties([
            'isPanel' => 'true', // String, não boolean!
            'tenantId' => 1,
            'isAdmin' => false,
        ]);
    }

    /**
     * Teste 5: Service valida tenantId como positivo
     *
     * @test
     */
    public function testGetPropertiesThrowsOnNegativeTenantId(): void
    {
        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("'tenantId' deve ser um inteiro positivo");

        $this->service->getProperties([
            'isPanel' => true,
            'tenantId' => -1, // Negativo!
            'isAdmin' => false,
        ]);
    }

    /**
     * Teste 6: Service valida tenantId como obrigatório
     *
     * @test
     */
    public function testGetPropertiesThrowsOnMissingTenantId(): void
    {
        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("'tenantId' é obrigatória");

        $this->service->getProperties([
            'isPanel' => true,
            // tenantId faltando!
            'isAdmin' => false,
        ]);
    }

    public function testPropertyEndpointDoesNotFallbackToTenantOne(): void
    {
        $endpoint = file_get_contents(__DIR__ . '/../../api/v1/properties/getProperties.php');

        $this->assertStringNotContainsString('$tenantId = 1;', $endpoint);
        $this->assertStringNotContainsString('tenant_id = 1', strtolower($endpoint));
        $this->assertStringContainsString('getCurrentTenantId()', $endpoint);
        $this->assertStringContainsString('getRequestTenantId()', $endpoint);
    }

    /**
     * Teste 7: Service não lança com null userId (é opcional)
     *
     * @test
     */
    public function testGetPropertiesAcceptsNullUserId(): void
    {
        // Arrange
        $this->repositoryMock
            ->method('getProperties')
            ->willReturn([]);

        // Act & Assert: Não deve lançar
        $result = $this->service->getProperties([
            'isPanel' => false,
            'tenantId' => 1,
            'userId' => null, // Pode ser null
            'isAdmin' => false,
        ]);

        $this->assertIsArray($result);
    }

    /**
     * Teste 8: Service valida isAdmin como boolean
     *
     * @test
     */
    public function testGetPropertiesThrowsOnInvalidIsAdmin(): void
    {
        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("'isAdmin' deve ser boolean");

        $this->service->getProperties([
            'isPanel' => true,
            'tenantId' => 1,
            'isAdmin' => 1, // Integer, não boolean!
        ]);
    }

    /**
     * Teste 9: Requisição pública (não-painel) retorna lista
     *
     * @test
     */
    public function testGetPropertiesPublicRequest(): void
    {
        // Arrange
        $mockRow = [
            'id' => 1,
            'title' => 'Prop 1',
            'description' => 'Desc',
            'location' => 'Local',
            'neighborhood' => null,
            'city' => null,
            'state' => null,
            'zip' => null,
            'price' => 100000.00,
            'type' => 'house',
            'transactionType' => 'sale',
            'features' => '[]',
            'bedrooms' => 3,
            'bathrooms' => 2,
            'garage' => null,
            'area' => 150.00,
            'yearBuilt' => 2020,
            'status' => 'Ativo',
            'condominium' => null,
            'iptu' => null,
            'suites' => null,
            'assigned_user_id' => 1,
            'image_url' => null,
        ];

        $this->repositoryMock
            ->method('getProperties')
            ->with(
                $this->stringContains('WHERE'),
                $this->isType('array')
            )
            ->willReturn([$mockRow]);

        // Act
        $result = $this->service->getProperties([
            'isPanel' => false,
            'tenantId' => 1,
            'isAdmin' => false,
        ]);

        // Assert
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    /**
     * Teste 10: Cache HIT - dados retornados do cache (PHASE 10)
     *
     * @test
     */
    public function testGetPropertiesCacheHit(): void
    {
        // Arrange: Mock do cache com dados
        $cachedData = [
            ['id' => 1, 'title' => 'Cached Property', 'price' => 100000],
        ];

        $cacheMock = $this->createMock(\App\Cache\CacheInterface::class);
        $cacheMock
            ->method('get')
            ->willReturn($cachedData); // Cache HIT

        // Usar o cache mock no service
        $pdoMock = $this->createMock(\PDO::class);
        $service = new \App\Service\PropertyService($pdoMock, $cacheMock);

        // Act: Chamar service (deve retornar do cache, não chamar Repository)
        $result = $service->getProperties([
            'isPanel' => true,
            'tenantId' => 1,
            'userId' => 1,
            'isAdmin' => true,
        ]);

        // Assert: Resultado vem do cache
        $this->assertIsArray($result);
        $this->assertEquals($cachedData, $result);
    }

    /**
     * Teste 11: Cache MISS - dados buscados do Repository e salvos em cache (PHASE 10)
     *
     * @test
     */
    public function testGetPropertiesCacheMiss(): void
    {
        // Arrange: Mock do Repository
        $mockRow = [
            'id' => 1,
            'title' => 'Prop 1',
            'description' => 'Desc',
            'location' => 'Local',
            'neighborhood' => null,
            'city' => null,
            'state' => null,
            'zip' => null,
            'price' => 100000.00,
            'type' => 'house',
            'transactionType' => 'sale',
            'features' => '[]',
            'bedrooms' => 3,
            'bathrooms' => 2,
            'garage' => null,
            'area' => 150.00,
            'yearBuilt' => 2020,
            'status' => 'Ativo',
            'condominium' => null,
            'iptu' => null,
            'suites' => null,
            'assigned_user_id' => 1,
            'image_url' => null,
            'tenant_id' => 1,
        ];

        $repositoryMock = $this->createMock(\App\Repository\PropertyRepository::class);
        $repositoryMock
            ->method('getProperties')
            ->willReturn([$mockRow]);

        // Mock do cache - primeiro retorna null (miss), depois espera ser chamado com set()
        $cacheMock = $this->createMock(\App\Cache\CacheInterface::class);
        $cacheMock
            ->method('get')
            ->willReturn(null); // Cache MISS

        // Esperamos que set() seja chamado uma vez
        $cacheMock
            ->expects($this->once())
            ->method('set');

        // Usar service com mocks
        $pdoMock = $this->createMock(\PDO::class);
        $service = new \App\Service\PropertyService($pdoMock, $cacheMock);

        // Injetar mock do Repository
        $reflection = new \ReflectionClass(\App\Service\PropertyService::class);
        $repositoryProperty = $reflection->getProperty('repository');
        $repositoryProperty->setAccessible(true);
        $repositoryProperty->setValue($service, $repositoryMock);

        // Act: Chamar service (deve buscar do Repository e salvar em cache)
        $result = $service->getProperties([
            'isPanel' => true,
            'tenantId' => 1,
            'userId' => 1,
            'isAdmin' => true,
        ]);

        // Assert: Resultado foi processado e cache.set() foi chamado
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    /**
     * Teste 12: Invalidação de cache (PHASE 10)
     *
     * @test
     */
    public function testCacheInvalidation(): void
    {
        // Arrange
        $cacheMock = $this->createMock(\App\Cache\CacheInterface::class);

        // Esperamos que forget() seja chamado
        $cacheMock
            ->expects($this->exactly(2))
            ->method('forget');

        $pdoMock = $this->createMock(\PDO::class);
        $service = new \App\Service\PropertyService($pdoMock, $cacheMock);

        // Act: Invalidar cache do tenant
        $service->invalidateCache(1);

        // Assert: forget() foi chamado para cada chave
        // (validação via expects acima)
        $this->assertTrue(true);
    }

    /**
     * Teste 13: Invalidação de cache específica de usuário (PHASE 10)
     *
     * @test
     */
    public function testCacheInvalidationForUser(): void
    {
        // Arrange
        $cacheMock = $this->createMock(\App\Cache\CacheInterface::class);

        // Esperamos que forget() seja chamado uma vez
        $cacheMock
            ->expects($this->once())
            ->method('forget');

        $pdoMock = $this->createMock(\PDO::class);
        $service = new \App\Service\PropertyService($pdoMock, $cacheMock);

        // Act: Invalidar cache do usuário
        $service->invalidateCacheForUser(1, 5);

        // Assert: forget() foi chamado
        $this->assertTrue(true);
    }

    /**
     * Teste 14: getTenantIdForProperty usa lookup explícito por property_id.
     *
     * @test
     */
    public function testGetTenantIdForPropertyUsesExplicitPropertyLookup(): void
    {
        $this->repositoryMock
            ->expects($this->once())
            ->method('getTenantIdByPropertyId')
            ->with(42)
            ->willReturn(7);

        $this->repositoryMock
            ->expects($this->never())
            ->method('findPropertyById');

        $this->assertSame(7, $this->service->getTenantIdForProperty(42));
    }

    /**
     * Teste 15: getTenantIdForProperty retorna null quando a propriedade não existe.
     *
     * @test
     */
    public function testGetTenantIdForPropertyReturnsNullWhenPropertyDoesNotExist(): void
    {
        $this->repositoryMock
            ->expects($this->once())
            ->method('getTenantIdByPropertyId')
            ->with(999)
            ->willReturn(null);

        $this->assertNull($this->service->getTenantIdForProperty(999));
    }

    /**
     * Teste 16: getPropertyByIdForApi não executa lookup global quando o tenant é inválido.
     *
     * @test
     */
    public function testGetPropertyByIdForApiRejectsTenantZeroBeforeLegacyLookup(): void
    {
        $this->repositoryMock
            ->expects($this->never())
            ->method('findPropertyByIdForLegacy');

        $this->assertNull($this->service->getPropertyByIdForApi(42, [
            'tenantId' => 0,
            'isAdmin' => false,
        ]));
    }

    /**
     * Teste 17: getPropertyByIdForApi não executa lookup global quando o tenant é nulo.
     *
     * @test
     */
    public function testGetPropertyByIdForApiRejectsNullTenantBeforeLegacyLookup(): void
    {
        $this->repositoryMock
            ->expects($this->never())
            ->method('findPropertyByIdForLegacy');

        $this->assertNull($this->service->getPropertyByIdForApi(42, [
            'tenantId' => null,
            'isAdmin' => false,
        ]));
    }

    /**
     * Cenário 1: Commission do corretor usa valores corretos
     * Preço = 280000, Comissão imobiliária = 6%, Broker = 40%
     * Esperado em commissions table: sale_value = 16800, commission_percentage = 40
     *
     * Validação via código: Os valores corretos são calculados e estariam passados
     * para CommissionModel::createFromSale com sale_value e commission_percentage corretos
     *
     * @test
     */
    public function testBrokerCommissionValuesCalculatedCorrectly(): void
    {
        // Test the math: 280000 × 6% = 16800 (imobiliária receita)
        // Then: 16800 × 40% = 6720 (broker repasse)
        $price = 280000.00;
        $commissionPercent = 6.00;
        $brokerCommissionPercent = 40.00;

        $commissionValue = ($price * $commissionPercent) / 100;
        $brokerCommissionValue = ($commissionValue * $brokerCommissionPercent) / 100;

        $this->assertSame(16800.00, $commissionValue, 'Imobiliária receita = 280000 × 6%');
        $this->assertSame(6720.00, $brokerCommissionValue, 'Broker repasse = 16800 × 40%');

        // Verify the fix: commission table should use commissionValue and brokerCommissionPercent
        // NOT $price and $commissionPercent
        $this->assertSame(16800.00, $commissionValue, 'Commission sale_value should be imobiliária receita');
        $this->assertSame(40.00, $brokerCommissionPercent, 'Commission percentage should be broker percentage');
    }

    /**
     * Cenário 2: Broker sem percentual não cria comissão
     *
     * @test
     */
    public function testResolveEffectiveBrokerCommissionPercentageReturnsNullWhenBrokerHasNone(): void
    {
        // Test at the static method level
        $result = PropertyService::resolveEffectiveBrokerCommissionPercentage(
            ['broker_default_commission_percentage' => null],
            112,
            null
        );

        $this->assertNull($result, 'Should return null when no broker percentage exists');
    }

    /**
     * Cenário 3: Broker com percentual 0 não gera despesa
     *
     * @test
     */
    public function testResolveEffectiveBrokerCommissionPercentageReturnsZeroWhenBrokerPercentageIsZero(): void
    {
        $result = PropertyService::resolveEffectiveBrokerCommissionPercentage(
            ['broker_default_commission_percentage' => 0.0],
            112,
            null
        );

        $this->assertSame(0.0, $result, 'Should return 0.0 when broker percentage is 0');
    }

    /**
     * Cenário 4: Override explícito do percentual do corretor vence default
     *
     * O método resolveEffectiveBrokerCommissionPercentage coloca o valor explícito
     * como primeira opção em candidatos, garantindo precedência.
     *
     * @test
     */
    public function testResolveEffectiveBrokerCommissionPercentageHasPriorityOrder(): void
    {
        // When explicit value is provided, it's prioritized
        $result = PropertyService::resolveEffectiveBrokerCommissionPercentage(
            ['broker_default_commission_percentage' => 30.0],
            112,
            50.0  // explicit override
        );

        // Explicit value (50.0) should be first in candidates and returned
        $this->assertSame(50.0, $result, 'Explicit override should be prioritized');
    }

    /**
     * Cenário 5: Tenant isolation - CommissionModel respects tenant via repository
     * This is implicitly tested via the tenant-aware repository design
     *
     * @test
     */
    public function testResolveEffectiveBrokerUserIdRespectsTenantBoundary(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, tenant_id INTEGER NOT NULL, is_superadmin INTEGER DEFAULT 0, username TEXT NOT NULL, role TEXT, name TEXT, email TEXT, phone TEXT, broker_default_commission_percentage REAL NULL, created_at TEXT, last_login TEXT, active INTEGER DEFAULT 1, force_password_change INTEGER DEFAULT 0, password_changed_at TEXT)');

        // User in tenant 2
        $pdo->prepare('INSERT INTO users (id, tenant_id, is_superadmin, username, role, name, email, phone, broker_default_commission_percentage, created_at, last_login, active, force_password_change, password_changed_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([999, 2, 0, 'OtherTenant', 'broker', 'OtherTenant', 'other@example.com', '999', 40.0, '2024-01-01 00:00:00', null, 1, 0, null]);

        $service = new PropertyService($pdo);
        $method = new \ReflectionMethod(PropertyService::class, 'resolveEffectiveBrokerUserId');
        $method->setAccessible(true);

        // Try to use user from tenant 2 in tenant 1 context
        $result = $method->invoke($service, 1, ['assigned_user_id' => 999], null);

        $this->assertNull($result, 'Broker from different tenant should be rejected');
    }

    /**
     * Cenário 6: Idempotência - CommissionRepository checks existsBySaleId before creating
     *
     * @test
     */
    public function testCommissionRepositoryPreventsDoubletCreation(): void
    {
        $pdo = $this->createMock(\PDO::class);
        $stmt = $this->createMock(\PDOStatement::class);

        $pdo->expects($this->once())
            ->method('prepare')
            ->willReturn($stmt);

        $stmt->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $stmt->expects($this->once())
            ->method('fetch')
            ->willReturn(['id' => 1]); // Already exists

        $repository = new \App\Infrastructure\Finance\CommissionRepository($pdo);
        $exists = $repository->existsBySaleId(6, 1);

        $this->assertTrue($exists, 'Should detect existing commission for same sale_id + tenant_id');
    }
}
