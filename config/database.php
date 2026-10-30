<?php
// Em produção, desativa a exibição de erros
if (getenv('ENVIRONMENT') !== 'development') {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
} else {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

// Carrega as configurações de segurança
require_once __DIR__ . '/security.php';

// Carrega as variáveis de ambiente
require_once __DIR__ . '/env_loader.php';

// Criar instância global do PDO para compatibilidade com código legado
$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$password = getenv('DB_PASS') ?: '';
$dbname = getenv('DB_NAME');

if ($host === false || $user === false || $dbname === false) {
    throw new RuntimeException('Configuração do banco ausente. Defina DB_HOST, DB_USER e DB_NAME no arquivo .env.');
}

try {
    // Conecta explicitamente à instância MariaDB 12.3 em TCP
    $dsn = "mysql:host={$host};port=3306;dbname={$dbname};charset=utf8mb4";

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_STRINGIFY_FETCHES => false, // Garante que números não sejam convertidos para strings
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
    ];

    $pdo = new PDO($dsn, $user, $password, $options);

    // Carrega helper de charset
    require_once dirname(__DIR__) . '/app/helpers/charset_helper.php';

    // Garante que a comunicação com MySQL seja em UTF-8
    ensureUTF8Charset($pdo);
} catch (Exception $e) {
    throw $e;
}

/**
 * Classe responsável pela conexão com o banco de dados
 * e operações básicas de CRUD
 */
class Database
{
    private $host;
    private $user;
    private $password;
    private $dbname;
    private $options;

    public function __construct()
    {
        $this->host = getenv('DB_HOST');
        $this->user = getenv('DB_USER');
        $this->password = getenv('DB_PASS') ?: '';
        $this->dbname = getenv('DB_NAME');

        if ($this->host === false || $this->user === false || $this->dbname === false) {
            throw new RuntimeException('Configuração do banco ausente. Defina DB_HOST, DB_USER e DB_NAME no arquivo .env.');
        }

        $this->options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
        ];

        // Desativa logs de depuração em produção
        if (getenv('ENVIRONMENT') === 'production') {
            error_reporting(0);
            ini_set('display_errors', 0);
        }
    }

    public function connect()
    {
        try {
            $dsn = "mysql:host={$this->host};port=3306;dbname={$this->dbname};charset=utf8mb4";

            $pdo = new PDO($dsn, $this->user, $this->password, $this->options);

            return $pdo;
        } catch (Exception $e) {
            throw $e;
        }
    }
}

// Compatibilidade: função global getConnection() usada por endpoints antigos
if (!function_exists('getConnection')) {
    function getConnection()
    {
        global $pdo;
        if (isset($pdo) && $pdo instanceof PDO) {
            return $pdo;
        }
        $db = new Database();
        return $db->connect();
    }
}
