<?php

namespace Tests\Integration;

use App\Repository\ReportRepository;
use App\Repository\SecurityMonitoringRepository;
use App\Repository\SiteVisitRepository;
use App\Repository\UtmRepository;
use App\Service\SiteVisitService;
use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/config/test_database.php';

class RealMysqlMigrationValidationTest extends TestCase
{
    private string $dbName;
    private string $dbHost;
    private string $dbPort;
    private string $dbUser;
    private string $dbPass;

    private PDO $pdo;

    protected function setUp(): void
    {
        assertTestDatabaseIsIsolated();
        $config = getTestDatabaseConfig();

        $this->dbName = $config['name'];
        $this->dbHost = $config['host'];
        $this->dbPort = $config['port'];
        $this->dbUser = $config['user'];
        $this->dbPass = $config['pass'];

        $root = new PDO(
            sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $this->dbHost, $this->dbPort),
            $this->dbUser,
            $this->dbPass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );

        $root->exec('DROP DATABASE IF EXISTS `' . $this->dbName . '`');
        $root->exec('CREATE DATABASE `' . $this->dbName . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

        $this->pdo = new PDO(
            sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $this->dbHost, $this->dbPort, $this->dbName),
            $this->dbUser,
            $this->dbPass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        $this->pdo->exec('CREATE TABLE users (
            id INT NOT NULL AUTO_INCREMENT,
            tenant_id INT NOT NULL,
            username VARCHAR(100) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

        $this->pdo->exec('CREATE TABLE properties (
            id INT NOT NULL AUTO_INCREMENT,
            tenant_id INT NOT NULL,
            assigned_user_id INT NULL,
            type VARCHAR(40) NOT NULL,
            status VARCHAR(40) NOT NULL,
            price DECIMAL(12,2) NOT NULL DEFAULT 0,
            neighborhood VARCHAR(120) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

        $this->pdo->exec('CREATE TABLE leads (
            id INT NOT NULL AUTO_INCREMENT,
            tenant_id INT NOT NULL,
            property_id INT NULL,
            assigned_user_id INT NULL,
            utm_tracking_id INT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

        $this->pdo->exec('CREATE TABLE utm_campaigns (
            id INT NOT NULL AUTO_INCREMENT,
            tenant_id INT NOT NULL,
            campaign_name VARCHAR(180) NOT NULL,
            source VARCHAR(100) NULL,
            medium VARCHAR(100) NULL,
            content VARCHAR(255) NULL,
            utm_link VARCHAR(255) NULL,
            description VARCHAR(255) NULL,
            created_by INT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            deleted_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY idx_tenant_campaign (tenant_id, campaign_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

        $this->pdo->exec('CREATE TABLE utm_tracking (
            id INT NOT NULL AUTO_INCREMENT,
            tenant_id INT NOT NULL,
            campaign_id INT NULL,
            utm_source VARCHAR(100) NULL,
            utm_medium VARCHAR(100) NULL,
            utm_campaign VARCHAR(180) NULL,
            utm_content VARCHAR(255) NULL,
            utm_term VARCHAR(180) NULL,
            clicked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            user_ip VARCHAR(45) NOT NULL,
            user_agent VARCHAR(255) NULL,
            referrer VARCHAR(255) NULL,
            destination_url VARCHAR(255) NULL,
            PRIMARY KEY (id),
            KEY idx_tenant_click (tenant_id, clicked_at),
            KEY idx_campaign (campaign_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

        $this->pdo->exec('CREATE TABLE site_visits (
            id INT NOT NULL AUTO_INCREMENT,
            tenant_id INT NOT NULL,
            visitor_ip VARCHAR(45) NULL,
            visitor_ua VARCHAR(255) NULL,
            page_url VARCHAR(255) NOT NULL DEFAULT "/",
            referrer VARCHAR(255) NULL,
            session_id VARCHAR(120) NOT NULL,
            visited_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_session (session_id, tenant_id, visited_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

        $this->pdo->exec('CREATE TABLE security_anomalies (
            id INT NOT NULL AUTO_INCREMENT,
            tenant_id INT NOT NULL,
            anomaly_type VARCHAR(100) NOT NULL,
            severity VARCHAR(20) NOT NULL DEFAULT "MEDIUM",
            username VARCHAR(255) NULL,
            user_id INT NULL,
            ip_address VARCHAR(45) NULL,
            detected_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            resolved_at DATETIME NULL,
            resolved INT NOT NULL DEFAULT 0,
            resolved_by INT NULL,
            details TEXT NULL,
            PRIMARY KEY (id),
            KEY idx_tenant_detected (tenant_id, detected_at),
            KEY idx_resolved (tenant_id, resolved)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

        $this->pdo->exec('CREATE TABLE suspicious_ips (
            id INT NOT NULL AUTO_INCREMENT,
            tenant_id INT NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            user_id INT NULL,
            username VARCHAR(255) NULL,
            attempt_count INT NOT NULL DEFAULT 1,
            last_attempt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ip_tenant (tenant_id, ip_address)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

        $this->pdo->exec('CREATE TABLE blocked_ips (
            id INT NOT NULL AUTO_INCREMENT,
            tenant_id INT NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            reason VARCHAR(255) NULL,
            blocked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            blocked_until DATETIME NULL,
            blocked_by_user_id INT NULL,
            permanent TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY uq_blocked_ip_tenant (tenant_id, ip_address)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

        $this->pdo->exec('CREATE TABLE alerts_history (
            id INT NOT NULL AUTO_INCREMENT,
            tenant_id INT NOT NULL,
            alert_type VARCHAR(100) NOT NULL,
            message TEXT NOT NULL,
            severity VARCHAR(20) NOT NULL DEFAULT "MEDIUM",
            status VARCHAR(30) NOT NULL DEFAULT "open",
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            deleted_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY idx_tenant_alerts (tenant_id, severity, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo)) {
            $this->pdo->exec('DROP DATABASE IF EXISTS `' . $this->dbName . '`');
        }
    }

    public function testUtmCampaignsAndTrackingAreTenantScoped(): void
    {
        $repo = new UtmRepository($this->pdo);

        $campaignA = $repo->createCampaign(7, 'Spring Promo', 'newsletter', 'email', 'welcome', 'Promo 7', 11);
        $campaignB = $repo->createCampaign(9, 'Spring Promo', 'newsletter', 'email', 'welcome', 'Promo 9', 22);

        $this->assertCount(1, $repo->listCampaigns(7));
        $this->assertCount(1, $repo->listCampaigns(9));

        $repo->insertTracking(7, (int) $campaignA['id'], ['utm_source' => 'newsletter', 'utm_medium' => 'email', 'utm_campaign' => 'Spring Promo'], '10.0.0.1', 'ua-1', 'https://example.com');
        $repo->insertTracking(9, (int) $campaignB['id'], ['utm_source' => 'newsletter', 'utm_medium' => 'email', 'utm_campaign' => 'Spring Promo'], '10.0.0.2', 'ua-2', 'https://example.com');
        $repo->insertTracking(7, (int) $campaignA['id'], ['utm_source' => 'social', 'utm_medium' => 'facebook', 'utm_campaign' => 'Spring Promo'], '10.0.0.3', 'ua-3', 'https://example.com');

        $summary7 = $repo->getAnalytics(7, 30);
        $summary9 = $repo->getAnalytics(9, 30);

        $this->assertSame(2, $summary7['summary']['total_clicks']);
        $this->assertSame(1, $summary9['summary']['total_clicks']);
        $this->assertSame(['newsletter' => 1, 'social' => 1], $summary7['by_source']);
        $this->assertSame(['newsletter' => 1], $summary9['by_source']);
    }

    public function testSiteVisitDeduplicationHonorsTenantBoundary(): void
    {
        $service = new SiteVisitService($this->pdo, new SiteVisitRepository($this->pdo));

        $this->assertTrue($service->trackVisit([
            'tenant_id' => 7,
            'session_id' => 'sess-tenant-7',
            'visitor_ip' => '127.0.0.1',
            'visitor_ua' => 'ua-7',
            'page_url' => '/imoveis',
            'referrer' => 'https://example.com',
        ]));

        $this->assertTrue($service->trackVisit([
            'tenant_id' => 9,
            'session_id' => 'sess-tenant-7',
            'visitor_ip' => '127.0.0.2',
            'visitor_ua' => 'ua-9',
            'page_url' => '/imoveis',
            'referrer' => 'https://example.com',
        ]));

        $this->assertFalse($service->trackVisit([
            'tenant_id' => 7,
            'session_id' => 'sess-tenant-7',
            'visitor_ip' => '127.0.0.12',
            'visitor_ua' => 'ua-7-repeat',
            'page_url' => '/imoveis/novo',
            'referrer' => 'https://example.com',
        ]));

        $this->assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM site_visits')->fetchColumn());
    }

    public function testSecurityMonitoringOverviewAndTenantIsolationWork(): void
    {
        $repo = new SecurityMonitoringRepository($this->pdo);

        $this->pdo->exec('INSERT INTO security_anomalies (tenant_id, anomaly_type, severity, username, ip_address, detected_at, resolved, resolved_by, details) VALUES (7, "login_failed", "HIGH", "alice", "10.0.0.5", NOW(), 0, NULL, "tenant 7")');
        $this->pdo->exec('INSERT INTO security_anomalies (tenant_id, anomaly_type, severity, username, ip_address, detected_at, resolved, resolved_by, details) VALUES (9, "login_failed", "CRITICAL", "bob", "10.0.0.9", NOW(), 0, NULL, "tenant 9")');

        $this->pdo->exec('INSERT INTO suspicious_ips (tenant_id, ip_address, user_id, username, attempt_count, last_attempt) VALUES (7, "10.0.0.5", 1, "alice", 3, NOW())');
        $this->pdo->exec('INSERT INTO suspicious_ips (tenant_id, ip_address, user_id, username, attempt_count, last_attempt) VALUES (9, "10.0.0.9", 2, "bob", 2, NOW())');

        $this->pdo->exec('INSERT INTO blocked_ips (tenant_id, ip_address, reason, blocked_at, blocked_until, blocked_by_user_id, permanent) VALUES (7, "10.0.0.22", "tenant 7 block", NOW(), NULL, 1, 1)');
        $this->pdo->exec('INSERT INTO blocked_ips (tenant_id, ip_address, reason, blocked_at, blocked_until, blocked_by_user_id, permanent) VALUES (9, "10.0.0.99", "tenant 9 block", NOW(), NULL, 2, 1)');

        $this->pdo->exec('INSERT INTO alerts_history (tenant_id, alert_type, message, severity, status, created_at, deleted_at) VALUES (7, "login", "alert 7", "CRITICAL", "open", NOW(), NULL)');
        $this->pdo->exec('INSERT INTO alerts_history (tenant_id, alert_type, message, severity, status, created_at, deleted_at) VALUES (9, "login", "alert 9", "CRITICAL", "open", NOW(), NULL)');

        $tenant7 = $repo->getOverview(7);
        $tenant9 = $repo->getOverview(9);

        $this->assertSame(1, $tenant7['anomalies_count']);
        $this->assertSame(1, $tenant9['anomalies_count']);
        $this->assertSame(1, $tenant7['suspicious_ips_count']);
        $this->assertSame(1, $tenant9['suspicious_ips_count']);
        $this->assertSame(1, $tenant7['blocked_ips_count']);
        $this->assertSame(1, $tenant9['blocked_ips_count']);
        $this->assertSame(1, $tenant7['critical_alerts_count']);
        $this->assertSame(1, $tenant9['critical_alerts_count']);
    }

    public function testReportRepositoryReturnsPortfolioValuesOnlyForRequestedTenant(): void
    {
        $repo = new ReportRepository($this->pdo);

        $this->pdo->exec('INSERT INTO properties (tenant_id, assigned_user_id, type, status, price, neighborhood, created_at, updated_at) VALUES (7, 11, "apartment", "active", 250000, "Centro", NOW(), NOW())');
        $this->pdo->exec('INSERT INTO properties (tenant_id, assigned_user_id, type, status, price, neighborhood, created_at, updated_at) VALUES (7, 11, "house", "vendido", 400000, "Bela Vista", NOW(), NOW())');
        $this->pdo->exec('INSERT INTO properties (tenant_id, assigned_user_id, type, status, price, neighborhood, created_at, updated_at) VALUES (9, 22, "apartment", "active", 900000, "Centro", NOW(), NOW())');

        $portfolio7 = $repo->getPortfolioStats(7);
        $portfolio9 = $repo->getPortfolioStats(9);

        $this->assertSame(2, $portfolio7['portfolio']['total_properties']);
        $this->assertSame(650000.0, round((float) $portfolio7['portfolio']['total_value'], 1));
        $this->assertSame(1, $portfolio9['portfolio']['total_properties']);
        $this->assertSame(900000.0, round((float) $portfolio9['portfolio']['total_value'], 1));
    }
}
