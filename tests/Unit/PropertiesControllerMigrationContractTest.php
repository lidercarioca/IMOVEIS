<?php

namespace Tests\Unit;

use App\Service\PropertyService;
use App\Repository\PropertyRepository;
use PHPUnit\Framework\TestCase;

class PropertiesControllerMigrationContractTest extends TestCase
{
    public function testControllerDoesNotBuildSqlFragmentsForPropertyUpdates(): void
    {
        $controllerSource = file_get_contents(__DIR__ . '/../../app/controllers/PropertiesController.php');

        $this->assertStringContainsString('new \\App\\Service\\PropertyService', $controllerSource);
        $this->assertStringNotContainsString('UPDATE properties SET', $controllerSource);
        $this->assertStringNotContainsString("implode(', ', \$updateFields)", $controllerSource);
        $this->assertStringNotContainsString('title = :title', $controllerSource);
        $this->assertStringNotContainsString('price = :price', $controllerSource);
        $this->assertStringNotContainsString('status = :status', $controllerSource);
    }

    public function testPropertyServiceAndRepositoryExposePatchUpdateContract(): void
    {
        $this->assertTrue(method_exists(PropertyService::class, 'updatePropertyPatch'));
        $this->assertTrue(method_exists(PropertyRepository::class, 'updatePropertyPatch'));
    }
}
