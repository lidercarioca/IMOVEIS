<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Logging\FileLogger;
use App\Logging\LoggerFactory;
use App\Audit\AuditLogger;
use App\Observability\RequestTracker;

/**
 * LoggingTest - Testes para sistema de logging e auditoria (PHASE 11)
 *
 * Cobre:
 * - FileLogger: escrita em arquivo
 * - AuditLogger: registro de ações
 * - RequestTracker: rastreamento de requisições
 * - LoggerFactory: seleção de driver
 */
class LoggingTest extends TestCase
{
    private FileLogger $logger;
    private string $logDir;
    private AuditLogger $auditLogger;

    protected function setUp(): void
    {
        // Criar diretório de log temporário para testes
        $this->logDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'imoveis_logs_test_' . uniqid();
        $this->logger = new FileLogger($this->logDir);
        $this->auditLogger = new AuditLogger($this->logger);

        // Resetar RequestTracker
        RequestTracker::reset();
    }

    protected function tearDown(): void
    {
        // Limpar logs de teste
        RequestTracker::reset();

        if (is_dir($this->logDir)) {
            $files = @scandir($this->logDir);
            if ($files) {
                foreach ($files as $file) {
                    if ($file !== '.' && $file !== '..') {
                        @unlink($this->logDir . DIRECTORY_SEPARATOR . $file);
                    }
                }
            }
            @rmdir($this->logDir);
        }
    }

    // ================================================================
    // FileLogger Tests
    // ================================================================

    /**
     * Teste: FileLogger cria arquivo
     *
     * @test
     */
    public function testFileLoggerCreatesLogFile(): void
    {
        // Act: Fazer um log
        $this->logger->info('Test message');

        // Assert: Arquivo foi criado
        $files = scandir($this->logDir);
        $this->assertNotEmpty($files);

        // Verificar que há arquivo .log
        $hasLogFile = false;
        foreach ($files as $file) {
            if (str_ends_with($file, '.log')) {
                $hasLogFile = true;
                break;
            }
        }
        $this->assertTrue($hasLogFile);
    }

    /**
     * Teste: FileLogger escreve entrada JSON
     *
     * @test
     */
    public function testFileLoggerWritesJson(): void
    {
        // Act
        $this->logger->info('Test message', ['key' => 'value']);

        // Assert: Ler arquivo e validar JSON
        $files = array_diff(scandir($this->logDir), ['.', '..']);
        $this->assertNotEmpty($files);

        $logFile = reset($files);
        $content = file_get_contents($this->logDir . DIRECTORY_SEPARATOR . $logFile);

        $lines = explode("\n", trim($content));
        $this->assertGreaterThan(0, count($lines));

        // Primeira linha deve ser JSON válido
        $json = json_decode($lines[0], true);
        $this->assertIsArray($json);
        $this->assertEquals('INFO', $json['level']);
        $this->assertEquals('Test message', $json['message']);
    }

    /**
     * Teste: FileLogger suporta níveis de log
     *
     * @test
     */
    public function testFileLoggerLevels(): void
    {
        // Act: Log em diferentes níveis
        $this->logger->debug('Debug message');
        $this->logger->info('Info message');
        $this->logger->warning('Warning message');
        $this->logger->error('Error message');
        $this->logger->critical('Critical message');

        // Assert: Todos foram escritos
        $files = array_diff(scandir($this->logDir), ['.', '..']);
        $this->assertGreaterThanOrEqual(2, count($files)); // app.log + error.log

        // Ler conteúdo de todos os arquivos
        $allContent = '';
        foreach ($files as $file) {
            if (str_ends_with($file, '.log')) {
                $allContent .= file_get_contents($this->logDir . DIRECTORY_SEPARATOR . $file);
            }
        }

        // Verificar que todos os níveis foram escritos
        $this->assertStringContainsString('DEBUG', $allContent);
        $this->assertStringContainsString('INFO', $allContent);
        $this->assertStringContainsString('WARNING', $allContent);
        $this->assertStringContainsString('ERROR', $allContent);
        $this->assertStringContainsString('CRITICAL', $allContent);
    }

    /**
     * Teste: FileLogger com contexto
     *
     * @test
     */
    public function testFileLoggerContext(): void
    {
        // Act
        $context = [
            'user_id' => 123,
            'entity' => 'Property',
            'action' => 'create',
        ];
        $this->logger->info('Entity action', $context);

        // Assert
        $files = array_diff(scandir($this->logDir), ['.', '..']);
        $logFile = reset($files);
        $content = file_get_contents($this->logDir . DIRECTORY_SEPARATOR . $logFile);

        $json = json_decode(trim($content), true);
        $this->assertEquals($context, $json['context']);
    }

    // ================================================================
    // AuditLogger Tests
    // ================================================================

    /**
     * Teste: AuditLogger registra criação
     *
     * @test
     */
    public function testAuditLoggerCreate(): void
    {
        // Act
        $this->auditLogger->logCreate(
            'Property',
            123,
            ['title' => 'Casa Linda', 'price' => 500000],
            1
        );

        // Assert: Verificar que foi escrito
        $files = array_diff(scandir($this->logDir), ['.', '..']);
        $this->assertGreaterThan(0, count($files));
    }

    /**
     * Teste: AuditLogger registra atualização
     *
     * @test
     */
    public function testAuditLoggerUpdate(): void
    {
        // Act
        $this->auditLogger->logUpdate(
            'Property',
            123,
            ['price' => 500000],
            ['price' => 550000],
            1
        );

        // Assert
        $files = array_diff(scandir($this->logDir), ['.', '..']);
        $this->assertGreaterThan(0, count($files));
    }

    /**
     * Teste: AuditLogger registra exclusão
     *
     * @test
     */
    public function testAuditLoggerDelete(): void
    {
        // Act
        $this->auditLogger->logDelete(
            'Property',
            123,
            ['title' => 'Casa Antiga'],
            1
        );

        // Assert
        $files = array_diff(scandir($this->logDir), ['.', '..']);
        $this->assertGreaterThan(0, count($files));
    }

    /**
     * Teste: AuditLogger registra login
     *
     * @test
     */
    public function testAuditLoggerLogin(): void
    {
        // Act
        $this->auditLogger->logLogin(1, '192.168.1.1', ['method' => 'email']);

        // Assert
        $files = array_diff(scandir($this->logDir), ['.', '..']);
        $this->assertGreaterThan(0, count($files));
    }

    /**
     * Teste: AuditLogger registra login falho
     *
     * @test
     */
    public function testAuditLoggerFailedLogin(): void
    {
        // Act
        $this->auditLogger->logFailedLogin(
            'user@example.com',
            '192.168.1.1',
            'invalid_password'
        );

        // Assert
        $files = array_diff(scandir($this->logDir), ['.', '..']);
        $this->assertGreaterThan(0, count($files));
    }

    /**
     * Teste: AuditLogger não quebra fluxo em caso de erro
     *
     * @test
     */
    public function testAuditLoggerDoesNotBreakFlow(): void
    {
        // Act: Mesmo se houver erro, deveria retornar normalmente
        try {
            $this->auditLogger->logCreate('Entity', 'invalid_id', [], null);
            $result = true;
        } catch (\Exception $e) {
            $result = false;
        }

        // Assert: Não deve lançar exception
        $this->assertTrue($result);
    }

    /**
     * Teste: AuditLogger moderno registra uma única entrada estruturada por ação,
     * sem duplicação de evento para uma chamada explícita de update.
     */
    public function testAuditLoggerModernUpdateWritesSingleStructuredEntry(): void
    {
        $this->auditLogger->logUpdate('User', 42, ['name' => 'Old Name'], ['name' => 'New Name'], 9);

        $files = array_diff(scandir($this->logDir), ['.', '..']);
        $this->assertNotEmpty($files);

        $auditFile = null;
        foreach ($files as $file) {
            if (str_contains($file, 'audit-')) {
                $auditFile = $this->logDir . DIRECTORY_SEPARATOR . $file;
                break;
            }
        }

        $this->assertNotNull($auditFile);

        $content = trim((string) file_get_contents($auditFile));
        $this->assertNotSame('', $content);

        $lines = array_values(array_filter(array_map('trim', explode("\n", $content))));
        $this->assertCount(1, $lines);

        $entry = json_decode($lines[0], true);
        $this->assertIsArray($entry);
        $this->assertSame('update', $entry['action']);
        $this->assertSame('User', $entry['entity']);
        $this->assertSame(42, (int) $entry['entity_id']);
        $this->assertSame(9, (int) $entry['user_id']);
        $this->assertSame('Old Name', $entry['metadata']['before']['name']);
        $this->assertSame('New Name', $entry['metadata']['after']['name']);
    }

    // ================================================================
    // RequestTracker Tests
    // ================================================================

    /**
     * Teste: RequestTracker usa REMOTE_ADDR corretamente e ignora headers de cliente
     *
     * @test
     */
    public function testRequestTrackerUsesRemoteAddrAndIgnoresClientHeaders(): void
    {
        $originalRemoteAddr = $_SERVER['REMOTE_ADDR'] ?? null;
        $originalCf = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? null;
        $originalXff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null;

        $_SERVER['REMOTE_ADDR'] = '203.0.113.10';
        $_SERVER['HTTP_CF_CONNECTING_IP'] = '198.51.100.50';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.99, 10.0.0.5';

        try {
            $this->assertSame('203.0.113.10', RequestTracker::resolveClientIp());
            $_SERVER['REMOTE_ADDR'] = '2001:db8::1';
            $this->assertSame('2001:db8::1', RequestTracker::resolveClientIp());
        } finally {
            if ($originalRemoteAddr === null) {
                unset($_SERVER['REMOTE_ADDR']);
            } else {
                $_SERVER['REMOTE_ADDR'] = $originalRemoteAddr;
            }

            if ($originalCf === null) {
                unset($_SERVER['HTTP_CF_CONNECTING_IP']);
            } else {
                $_SERVER['HTTP_CF_CONNECTING_IP'] = $originalCf;
            }

            if ($originalXff === null) {
                unset($_SERVER['HTTP_X_FORWARDED_FOR']);
            } else {
                $_SERVER['HTTP_X_FORWARDED_FOR'] = $originalXff;
            }
        }
    }

    /**
     * Teste: RequestTracker retorna null quando o IP estiver ausente ou inválido
     *
     * @test
     */
    public function testRequestTrackerReturnsNullForMissingOrInvalidIp(): void
    {
        $originalRemoteAddr = $_SERVER['REMOTE_ADDR'] ?? null;

        unset($_SERVER['REMOTE_ADDR']);
        $this->assertNull(RequestTracker::resolveClientIp());

        $_SERVER['REMOTE_ADDR'] = 'not-an-ip';
        $this->assertNull(RequestTracker::resolveClientIp());

        if ($originalRemoteAddr === null) {
            unset($_SERVER['REMOTE_ADDR']);
        } else {
            $_SERVER['REMOTE_ADDR'] = $originalRemoteAddr;
        }
    }

    /**
     * Teste: RequestTracker gera request_id
     *
     * @test
     */
    public function testRequestTrackerGeneratesRequestId(): void
    {
        // Act
        $requestId = RequestTracker::start();

        // Assert
        $this->assertNotNull($requestId);
        $this->assertIsString($requestId);
        $this->assertGreaterThan(0, strlen($requestId));
    }

    /**
     * Teste: RequestTracker propaga request_id
     *
     * @test
     */
    public function testRequestTrackerPropagate(): void
    {
        // Act
        $originalId = RequestTracker::start();
        $retrievedId = RequestTracker::getId();

        // Assert
        $this->assertEquals($originalId, $retrievedId);

        // Verificar propagação para $_SERVER
        $this->assertEquals($originalId, $_SERVER['REQUEST_ID']);
        $this->assertEquals($originalId, $_SERVER['HTTP_X_REQUEST_ID']);
    }

    /**
     * Teste: RequestTracker aceita request_id customizado
     *
     * @test
     */
    public function testRequestTrackerCustomId(): void
    {
        // Act
        $customId = 'custom-request-id-123';
        $returnedId = RequestTracker::start($customId);

        // Assert
        $this->assertEquals($customId, $returnedId);
        $this->assertEquals($customId, RequestTracker::getId());
    }

    /**
     * Teste: RequestTracker armazena metadata
     *
     * @test
     */
    public function testRequestTrackerMetadata(): void
    {
        // Arrange
        RequestTracker::start();

        // Act
        RequestTracker::addMetadata('user_id', 42);
        RequestTracker::addMetadata('tenant_id', 1);

        $metadata = RequestTracker::getMetadata();

        // Assert
        $this->assertEquals(42, RequestTracker::getMetadata('user_id'));
        $this->assertArrayHasKey('user_id', $metadata);
        $this->assertArrayHasKey('tenant_id', $metadata);
    }

    /**
     * Teste: RequestTracker calcula duração
     *
     * @test
     */
    public function testRequestTrackerDuration(): void
    {
        // Arrange
        RequestTracker::start();
        usleep(100000); // 100ms

        // Act
        $duration = RequestTracker::getDuration();

        // Assert
        $this->assertGreaterThan(50, $duration); // Pelo menos 50ms
        $this->assertLessThan(500, $duration); // Menos que 500ms
    }

    /**
     * Teste: RequestTracker retorna resumo completo
     *
     * @test
     */
    public function testRequestTrackerSummary(): void
    {
        // Arrange
        RequestTracker::start();
        RequestTracker::addMetadata('user_id', 42);

        // Act
        $summary = RequestTracker::getSummary();

        // Assert
        $this->assertIsArray($summary);
        $this->assertArrayHasKey('request_id', $summary);
        $this->assertArrayHasKey('duration_ms', $summary);
        $this->assertArrayHasKey('method', $summary);
        $this->assertArrayHasKey('path', $summary);
        $this->assertArrayHasKey('ip', $summary);
        $this->assertArrayHasKey('user_agent', $summary);
        $this->assertArrayHasKey('metadata', $summary);
    }

    /**
     * Teste: RequestTracker reset limpa tudo
     *
     * @test
     */
    public function testRequestTrackerReset(): void
    {
        // Arrange
        RequestTracker::start('test-id');
        RequestTracker::addMetadata('key', 'value');
        $idBefore = RequestTracker::getId();

        // Act
        RequestTracker::reset();

        // Assert
        $this->assertNotNull($idBefore);
        $this->assertNull(RequestTracker::getId());
    }

    // ================================================================
    // LoggerFactory Tests
    // ================================================================

    /**
     * Teste: LoggerFactory retorna singleton
     *
     * @test
     */
    public function testLoggerFactoryReturnsSingleton(): void
    {
        // Resetar factory
        LoggerFactory::reset();

        // Act
        $logger1 = LoggerFactory::make();
        $logger2 = LoggerFactory::make();

        // Assert
        $this->assertSame($logger1, $logger2);
    }

    /**
     * Teste: LoggerFactory retorna LoggerInterface
     *
     * @test
     */
    public function testLoggerFactoryReturnsInterface(): void
    {
        // Resetar factory
        LoggerFactory::reset();

        // Act
        $logger = LoggerFactory::make();

        // Assert
        $this->assertInstanceOf(\App\Logging\LoggerInterface::class, $logger);
    }

    /**
     * Teste: LoggerFactory permite injeção customizada
     *
     * @test
     */
    public function testLoggerFactoryCustomInstance(): void
    {
        // Arrange
        $customLogger = new FileLogger($this->logDir);
        LoggerFactory::setInstance($customLogger);

        // Act
        $logger = LoggerFactory::make();

        // Assert
        $this->assertSame($customLogger, $logger);
    }

    // ================================================================
    // Integration Tests
    // ================================================================

    /**
     * Teste: Logger + Audit + RequestTracker juntos
     *
     * @test
     */
    public function testLoggingStackIntegration(): void
    {
        // Arrange
        $requestId = RequestTracker::start();
        RequestTracker::addMetadata('user_id', 1);

        // Act
        $this->logger->info('Property creation started', [
            'request_id' => $requestId,
        ]);

        $this->auditLogger->logCreate('Property', 123, ['title' => 'Casa'], 1);

        $this->logger->info('Property creation completed', [
            'request_id' => $requestId,
        ]);

        // Assert
        $files = array_diff(scandir($this->logDir), ['.', '..']);
        $this->assertGreaterThanOrEqual(2, count($files)); // app.log + audit.log

        RequestTracker::reset();
    }

    /**
     * Teste: Logging não quebra fluxo em caso de erro no diretório
     *
     * @test
     */
    public function testLoggingFailureDoesNotBreakFlow(): void
    {
        // Arrange: Criar logger com diretório inválido
        $invalidDir = '/invalid/path/that/does/not/exist/imoveis_logs';
        $logger = new FileLogger($invalidDir);

        // Act: Tentar fazer log (pode falhar silenciosamente)
        try {
            $logger->info('Test message');
            $result = true;
        } catch (\Exception $e) {
            $result = false;
        }

        // Assert: Não deve lançar exception
        $this->assertTrue($result);
    }
}
