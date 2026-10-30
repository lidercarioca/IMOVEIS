<?php

namespace Tests\Unit;

use App\Repository\BannerRepository;
use App\Service\BannerService;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;

class BannerMigrationContractTest extends TestCase
{
    public function testBannerServiceAndRepositoryExist(): void
    {
        $this->assertTrue(class_exists(BannerService::class));
        $this->assertTrue(class_exists(BannerRepository::class));
        $this->assertTrue(method_exists(BannerService::class, 'listBanners'));
        $this->assertTrue(method_exists(BannerService::class, 'createBanner'));
        $this->assertTrue(method_exists(BannerService::class, 'updateBanner'));
        $this->assertTrue(method_exists(BannerService::class, 'deleteBanner'));
        $this->assertTrue(method_exists(BannerRepository::class, 'listActiveByTenant'));
        $this->assertTrue(method_exists(BannerRepository::class, 'createBanner'));
        $this->assertTrue(method_exists(BannerRepository::class, 'updateBanner'));
        $this->assertTrue(method_exists(BannerRepository::class, 'deleteBanner'));
    }

    public function testBannerEndpointsUseServiceInsteadOfRawSql(): void
    {
        $listEndpoint = file_get_contents(__DIR__ . '/../../api/v1/banners/getBanners.php');
        $createEndpoint = file_get_contents(__DIR__ . '/../../api/v1/banners/addBanner.php');
        $updateEndpoint = file_get_contents(__DIR__ . '/../../api/v1/banners/updateBanner.php');
        $deleteEndpoint = file_get_contents(__DIR__ . '/../../api/v1/banners/deleteBanner.php');
        $uploadEndpoint = file_get_contents(__DIR__ . '/../../api/v1/banners/uploadBannerImage.php');

        $this->assertStringContainsString('new \\App\\Service\\BannerService', $listEndpoint);
        $this->assertStringContainsString('new \\App\\Service\\BannerService', $createEndpoint);
        $this->assertStringContainsString('new \\App\\Service\\BannerService', $updateEndpoint);
        $this->assertStringContainsString('new \\App\\Service\\BannerService', $deleteEndpoint);
        $this->assertStringContainsString('new \\App\\Service\\BannerService', $uploadEndpoint);

        $this->assertStringNotContainsString('SELECT * FROM banners', $listEndpoint);
        $this->assertStringNotContainsString('INSERT INTO banners', $createEndpoint);
        $this->assertStringNotContainsString('UPDATE banners SET', $updateEndpoint);
        $this->assertStringNotContainsString('DELETE FROM banners', $deleteEndpoint);
    }

    public function testBannerServiceRejectsInvalidTenantAndDelegatesToRepository(): void
    {
        $repository = $this->getMockBuilder(BannerRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['listActiveByTenant'])
            ->getMock();

        $repository->expects($this->never())
            ->method('listActiveByTenant');

        $service = new BannerService($this->createMock(PDO::class), $repository);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Tenant inválido');

        $service->listBanners(0);
    }

    public function testBannerUploadSizeValidationRejectsInvalidFiles(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Tamanho do arquivo inválido');

        BannerService::validateUploadSize(['size' => 'abc']);
    }

    public function testBannerUploadSizeValidationRejectsOversizedFiles(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('O arquivo excede o tamanho máximo permitido');

        BannerService::validateUploadSize(['size' => BannerService::MAX_UPLOAD_SIZE_BYTES + 1]);
    }

    public function testBannerUploadSizeValidationAllowsFilesWithinLimit(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'banner_upload_');
        $this->assertNotFalse($tmp);

        file_put_contents($tmp, str_repeat('A', 512));

        try {
            BannerService::validateUploadSize([
                'size' => 512,
                'tmp_name' => $tmp,
            ]);
            $this->addToAssertionCount(1);
        } finally {
            @unlink($tmp);
        }
    }
}
