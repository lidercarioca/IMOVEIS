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
$host = getenv('DB_HOST') ?: 'localhost';
$user = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASS') ?: '';
$dbname = getenv('DB_NAME') ?: 'rr_imoveis';

try {
    $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_STRINGIFY_FETCHES => false, // Garante que números não sejam convertidos para strings
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
    ];
    
    $pdo = new PDO($dsn, $user, $password, $options);
    error_log("Conexão PDO global estabelecida com sucesso");
    
} catch (Exception $e) {
    error_log("Erro de conexão com o banco de dados: " . $e->getMessage());
    throw $e;
}

/**
 * Classe responsável pela conexão com o banco de dados
 * e operações básicas de CRUD
 */
class Database {
    private $host;
    private $user;
    private $password;
    private $dbname;
    private $options;

    public function __construct() {
        $this->host = getenv('DB_HOST') ?: 'localhost';
        $this->user = getenv('DB_USER') ?: 'root';
        $this->password = getenv('DB_PASS') ?: '';
        $this->dbname = getenv('DB_NAME') ?: 'rr_imoveis';

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

    public function connect() {
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset=utf8mb4";
            
            error_log("Tentando conectar ao MySQL com: host={$this->host}, user={$this->user}, dbname={$this->dbname}");
            $pdo = new PDO($dsn, $this->user, $this->password, $this->options);
            error_log("Conexão PDO estabelecida com sucesso");
            
            return $pdo;
        } catch (Exception $e) {
            error_log("Erro de conexão com o banco de dados: " . $e->getMessage());
            throw $e;
        }
    }
}
