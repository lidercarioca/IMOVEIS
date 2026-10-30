<?php

namespace Tests\Unit;

use App\Service\CompanySettingsService;
use PHPUnit\Framework\TestCase;

class CompanySettingsMigrationContractTest extends TestCase
{
    public function testCompanySettingsServiceExists(): void
    {
        $this->assertTrue(class_exists(CompanySettingsService::class));
        $this->assertTrue(method_exists(CompanySettingsService::class, 'getContactInfoForTenant'));
        $this->assertTrue(method_exists(CompanySettingsService::class, 'upsertLogoPath'));
    }

    public function testSettingsEndpointsUseServiceRatherThanRawSql(): void
    {
        $getCompanySettings = file_get_contents(__DIR__ . '/../../api/v1/settings/getCompanySettings.php');
        $saveCompanySettings = file_get_contents(__DIR__ . '/../../api/v1/settings/saveCompanySettings.php');
        $getPublicCompanySettings = file_get_contents(__DIR__ . '/../../api/v1/settings/getPublicCompanySettings.php');

        $this->assertTrue(
            str_contains($getCompanySettings, 'new CompanySettingsService') || str_contains($getCompanySettings, 'new \\App\\Service\\CompanySettingsService')
        );
        $this->assertTrue(
            str_contains($saveCompanySettings, 'new CompanySettingsService') || str_contains($saveCompanySettings, 'new \\App\\Service\\CompanySettingsService')
        );
        $this->assertTrue(
            str_contains($getPublicCompanySettings, 'new CompanySettingsService') || str_contains($getPublicCompanySettings, 'new \\App\\Service\\CompanySettingsService')
        );

        $this->assertStringNotContainsString('SELECT * FROM company_settings', strtolower($getCompanySettings));
        $this->assertStringNotContainsString('SELECT * FROM company_settings', strtolower($saveCompanySettings));
        $this->assertStringNotContainsString('SELECT * FROM company_settings', strtolower($getPublicCompanySettings));
        $this->assertStringNotContainsString('UPDATE company_settings', strtolower($saveCompanySettings));
        $this->assertStringNotContainsString('INSERT INTO company_settings', strtolower($saveCompanySettings));
    }

    public function testSettingsEndpointsDoNotFallbackToTenantOne(): void
    {
        $getCompanySettings = file_get_contents(__DIR__ . '/../../api/v1/settings/getCompanySettings.php');
        $getPublicCompanySettings = file_get_contents(__DIR__ . '/../../api/v1/settings/getPublicCompanySettings.php');

        $this->assertStringNotContainsString('tenant_id = 1', strtolower($getCompanySettings));
        $this->assertStringNotContainsString('tenant_id = 1', strtolower($getPublicCompanySettings));
        $this->assertStringNotContainsString('fallback tenant_id=1', strtolower($getCompanySettings));
        $this->assertStringNotContainsString('fallback tenant_id=1', strtolower($getPublicCompanySettings));
    }
}
