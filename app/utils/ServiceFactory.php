<?php

namespace App\Utils;

use PDO;
use App\Services\UserService;
use App\Services\FinanceService;
use App\Services\AppointmentService;
use App\Models\User;
use App\Models\Transaction;
use App\Models\Appointment;

/**
 * ServiceFactory - Injeção de Dependências
 * 
 * Centraliza a criação de Services com suas dependências.
 * Facilita testes (mock services) e mudanças de implementação.
 */
class ServiceFactory
{

    private static ?PDO $pdo = null;
    private static array $services = [];

    /**
     * Inicializa o factory com a conexão PDO
     * 
     * IMPORTANTE: Deve ser chamado uma vez na bootstrap do app
     * 
     * Uso:
     * ServiceFactory::init($pdo);
     * 
     * @param PDO $pdo Conexão com o banco de dados
     */
    public static function init(PDO $pdo): void
    {
        self::$pdo = $pdo;
    }

    /**
     * Retorna a instância de PDO
     * 
     * @return PDO
     * @throws \RuntimeException Se factory não foi inicializado
     */
    public static function getPDO(): PDO
    {
        if (self::$pdo === null) {
            throw new \RuntimeException('ServiceFactory deve ser inicializado com init($pdo)');
        }
        return self::$pdo;
    }

    /**
     * Retorna UserService (singleton)
     * 
     * @return UserService
     */
    public static function getUserService(): UserService
    {
        if (!isset(self::$services['user'])) {
            $model = new User(self::getPDO());
            self::$services['user'] = new UserService($model);
        }
        return self::$services['user'];
    }

    // PropertyService factory removed. Use \App\Service\PropertyService directly.

    /**
     * Retorna FinanceService (singleton)
     * 
     * @return FinanceService
     */
    public static function getFinanceService(): FinanceService
    {
        if (!isset(self::$services['finance'])) {
            self::$services['finance'] = new FinanceService(self::getPDO());
        }
        return self::$services['finance'];
    }

    /**
     * Retorna AppointmentService (singleton)
     * 
     * @return AppointmentService
     */
    public static function getAppointmentService(): AppointmentService
    {
        if (!isset(self::$services['appointment'])) {
            $model = new Appointment(self::getPDO());
            self::$services['appointment'] = new AppointmentService($model);
        }
        return self::$services['appointment'];
    }

    /**
     * Registra um service customizado
     * 
     * Útil para testes (mock services) ou extensões
     * 
     * @param string $name Nome do service
     * @param object $service Instância do service
     */
    public static function register(string $name, object $service): void
    {
        self::$services[$name] = $service;
    }

    /**
     * Obtém um service registrado dinamicamente
     * 
     * @param string $name Nome do service
     * @return object|null
     */
    public static function get(string $name): ?object
    {
        return self::$services[$name] ?? null;
    }

    /**
     * Limpa cache de services (útil para testes)
     */
    public static function flush(): void
    {
        self::$services = [];
    }

    /**
     * Retorna lista de services registrados
     * 
     * @return array
     */
    public static function getRegistered(): array
    {
        return array_keys(self::$services);
    }
}
