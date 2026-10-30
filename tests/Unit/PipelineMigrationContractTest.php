<?php

namespace Tests\Unit;

use App\Repository\PipelineRepository;
use App\Service\PipelineService;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;

class PipelineMigrationContractTest extends TestCase
{
    public function testPipelineServiceAndRepositoryExist(): void
    {
        $this->assertTrue(class_exists(PipelineService::class));
        $this->assertTrue(class_exists(PipelineRepository::class));
    }

    public function testPipelineServicePreservesCoreContracts(): void
    {
        $this->assertTrue(method_exists(PipelineService::class, 'createItem'));
        $this->assertTrue(method_exists(PipelineService::class, 'getOptions'));
        $this->assertTrue(method_exists(PipelineService::class, 'getMetrics'));
    }

    public function testPipelineEndpointsUseServiceInsteadOfRawSql(): void
    {
        $createItem = file_get_contents(__DIR__ . '/../../api/v1/pipeline/createItem.php');
        $getOptions = file_get_contents(__DIR__ . '/../../api/v1/pipeline/getOptions.php');
        $getMetrics = file_get_contents(__DIR__ . '/../../api/v1/pipeline/getMetrics.php');

        $this->assertStringContainsString('new \\App\\Service\\PipelineService', $createItem);
        $this->assertStringContainsString('new \\App\\Service\\PipelineService', $getOptions);
        $this->assertStringContainsString('new \\App\\Service\\PipelineService', $getMetrics);
        $this->assertStringNotContainsString('SELECT property_id FROM leads', $createItem);
        $this->assertStringNotContainsString('SELECT id, title, neighborhood, type, price FROM properties', $getOptions);
        $this->assertStringNotContainsString('SELECT \n            sp.pipeline_stage_id', $getMetrics);
        $this->assertStringNotContainsString('tenant_id = 1', strtolower($createItem));
        $this->assertStringNotContainsString('tenant_id = 1', strtolower($getOptions));
        $this->assertStringNotContainsString('tenant_id = 1', strtolower($getMetrics));
    }

    public function testCreateItemRejectsInvalidTenantWithoutFallback(): void
    {
        $repository = $this->getMockBuilder(PipelineRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findLeadPropertyIdByTenant', 'findStageByTenant', 'createItem'])
            ->getMock();

        $repository->expects($this->never())->method('createItem');

        $service = new PipelineService($this->createMock(PDO::class), $repository);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Tenant inválido');

        $service->createItem(0, 12, [
            'pipeline_stage_id' => 3,
            'lead_id' => 44,
            'value' => '150000',
        ]);
    }

    public function testRemainingPipelineHandlersUseServiceAndRequireTenantIsolation(): void
    {
        $this->assertTrue(method_exists(PipelineService::class, 'getItems'));
        $this->assertTrue(method_exists(PipelineService::class, 'getStages'));
        $this->assertTrue(method_exists(PipelineService::class, 'updateItem'));
        $this->assertTrue(method_exists(PipelineService::class, 'moveItem'));
        $this->assertTrue(method_exists(PipelineService::class, 'deleteItem'));

        $this->assertTrue(method_exists(PipelineRepository::class, 'listItemsByTenant'));
        $this->assertTrue(method_exists(PipelineRepository::class, 'listStagesByTenant'));
        $this->assertTrue(method_exists(PipelineRepository::class, 'updateItemByIdAndTenant'));
        $this->assertTrue(method_exists(PipelineRepository::class, 'moveItemByIdAndTenant'));
        $this->assertTrue(method_exists(PipelineRepository::class, 'deleteItemByIdAndTenant'));

        $getItems = file_get_contents(__DIR__ . '/../../api/v1/pipeline/getItems.php');
        $getStages = file_get_contents(__DIR__ . '/../../api/v1/pipeline/getStages.php');
        $updateItem = file_get_contents(__DIR__ . '/../../api/v1/pipeline/updateItem.php');
        $moveItem = file_get_contents(__DIR__ . '/../../api/v1/pipeline/moveItem.php');
        $deleteItem = file_get_contents(__DIR__ . '/../../api/v1/pipeline/deleteItem.php');

        $this->assertStringContainsString('new \\App\\Service\\PipelineService', $getItems);
        $this->assertStringContainsString('new \\App\\Service\\PipelineService', $getStages);
        $this->assertStringContainsString('new \\App\\Service\\PipelineService', $updateItem);
        $this->assertStringContainsString('new \\App\\Service\\PipelineService', $moveItem);
        $this->assertStringContainsString('new \\App\\Service\\PipelineService', $deleteItem);

        $this->assertStringNotContainsString('tenant_id = 1', strtolower($getItems));
        $this->assertStringNotContainsString('tenant_id = 1', strtolower($getStages));
        $this->assertStringNotContainsString('tenant_id = 1', strtolower($updateItem));
        $this->assertStringNotContainsString('tenant_id = 1', strtolower($moveItem));
        $this->assertStringNotContainsString('tenant_id = 1', strtolower($deleteItem));
    }
}
