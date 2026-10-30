<?php

declare(strict_types=1);

if (!function_exists('getAppDatabaseProfile')) {
    /**
     * Retorna a configuração atual da aplicação para comparação de segurança.
     * Não expõe segredos; apenas host, usuário e nome do banco da aplicação.
     */
    function getAppDatabaseProfile(): array
    {
        return [
            'host' => (string) (getenv('DB_HOST') ?: '127.0.0.1'),
            'user' => (string) (getenv('DB_USER') ?: 'root'),
            'name' => (string) (getenv('DB_NAME') ?: 'sys_imoveis'),
        ];
    }
}

if (!function_exists('getTestDatabaseConfig')) {
    /**
     * Lê as variáveis de ambiente de integração MySQL para testes.
     * Não reutiliza a conexão global do app.
     */
    function getTestDatabaseConfig(): array
    {
        $config = [
            'host' => (string) (getenv('TEST_DB_HOST') ?: ''),
            'port' => (string) (getenv('TEST_DB_PORT') ?: '3306'),
            'user' => (string) (getenv('TEST_DB_USER') ?: ''),
            'pass' => (string) (getenv('TEST_DB_PASS') ?: ''),
            'name' => (string) (getenv('TEST_DB_NAME') ?: ''),
            'isolated' => strtolower((string) (getenv('TEST_DB_ISOLATED') ?: '')),
        ];

        foreach (['host', 'user', 'name'] as $field) {
            if ($config[$field] === '') {
                throw new RuntimeException(
                    'MySQL integration config is incomplete. Define TEST_DB_HOST, TEST_DB_USER, TEST_DB_NAME and TEST_DB_PASS before running integration tests.'
                );
            }
        }

        if (!in_array($config['isolated'], ['true', '1', 'yes', 'on'], true)) {
            throw new RuntimeException(
                'MySQL integration tests require TEST_DB_ISOLATED=true to confirm a dedicated test schema before any connection attempt.'
            );
        }

        return $config;
    }
}

if (!function_exists('assertTestDatabaseIsIsolated')) {
    /**
     * Garante que o banco de testes não reutiliza o schema da aplicação.
     * A declaração TEST_DB_ISOLATED=true é obrigatória e não substitui validação de host/schema.
     * A confirmação explícita é um requisito de configuração, não uma prova técnica autônoma.
     */
    function assertTestDatabaseIsIsolated(): void
    {
        $app = getAppDatabaseProfile();
        $test = getTestDatabaseConfig();

        $appName = strtolower(trim($app['name']));
        $testName = strtolower(trim($test['name']));
        $appHost = strtolower(trim($app['host']));
        $testHost = strtolower(trim($test['host']));

        if ($appHost === $testHost && $appName === $testName) {
            throw new RuntimeException(
                'TEST_DB_HOST/TEST_DB_NAME must not point to the application database. Configure a dedicated test schema before any MySQL integration execution.'
            );
        }

        $reservedDbs = ['sys_imoveis', 'rr_imoveis', 'mysql', 'information_schema', 'performance_schema', 'phpmyadmin', 'test'];
        if (in_array($testName, $reservedDbs, true)) {
            throw new RuntimeException(
                'TEST_DB_NAME must be set to a dedicated test schema, not a shared system database.'
            );
        }

        if ($testName === $appName) {
            throw new RuntimeException(
                'TEST_DB_NAME cannot match the application database name even when the host differs; a dedicated test schema is required.'
            );
        }

        if ($testHost === $appHost && $testName !== $appName) {
            throw new RuntimeException(
                'TEST_DB_ISOLATED=true is required and host/schema must still be checked; same host with a different schema is not sufficient evidence of isolation.'
            );
        }
    }
}

if (!function_exists('getTestDatabasePdo')) {
    /**
     * Cria uma conexão separada apenas para testes e sem reutilizar o $pdo global da app.
     */
    function getTestDatabasePdo(): PDO
    {
        assertTestDatabaseIsIsolated();

        $config = getTestDatabaseConfig();
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $config['host'],
            $config['port'],
            $config['name']
        );

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
        ];

        return new PDO($dsn, $config['user'], $config['pass'], $options);
    }
}
