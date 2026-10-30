<?php
namespace App\Audit;

use App\Logging\LoggerInterface;

/**
 * AuditLogger - Rastreamento de ações do sistema
 *
 * Responsável por registrar todas as ações importantes:
 * - Criação/edição/exclusão de entidades
 * - Ações de usuário (login, logout)
 * - Alterações em dados sensíveis
 *
 * Estrutura padrão:
 * - user_id: usuário que realizou ação
 * - action: tipo de ação (create, update, delete, view, export)
 * - entity: tipo de entidade (Property, User, Contract)
 * - entity_id: ID da entidade afetada
 * - timestamp: quando foi feito
 * - metadata: dados adicionais (valores antes/depois)
 */
class AuditLogger
{
    // Tipos de ações
    public const ACTION_CREATE = 'create';
    public const ACTION_UPDATE = 'update';
    public const ACTION_DELETE = 'delete';
    public const ACTION_VIEW = 'view';
    public const ACTION_EXPORT = 'export';
    public const ACTION_LOGIN = 'login';
    public const ACTION_LOGOUT = 'logout';
    public const ACTION_FAILED_LOGIN = 'failed_login';

    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Registrar criação de entidade
     *
     * @param string $entity
     * @param mixed $entityId
     * @param array $data Dados criados
     * @param int|null $userId
     * @return void
     */
    public function logCreate(
        string $entity,
        $entityId,
        array $data = [],
        ?int $userId = null
    ): void {
        $this->log(
            self::ACTION_CREATE,
            $entity,
            $entityId,
            ['created_data' => $data],
            $userId
        );
    }

    /**
     * Registrar atualização de entidade
     *
     * @param string $entity
     * @param mixed $entityId
     * @param array $before Dados antes
     * @param array $after Dados depois
     * @param int|null $userId
     * @return void
     */
    public function logUpdate(
        string $entity,
        $entityId,
        array $before = [],
        array $after = [],
        ?int $userId = null
    ): void {
        $this->log(
            self::ACTION_UPDATE,
            $entity,
            $entityId,
            [
                'before' => $before,
                'after' => $after,
            ],
            $userId
        );
    }

    /**
     * Registrar exclusão de entidade
     *
     * @param string $entity
     * @param mixed $entityId
     * @param array $deletedData Dados que foram deletados
     * @param int|null $userId
     * @return void
     */
    public function logDelete(
        string $entity,
        $entityId,
        array $deletedData = [],
        ?int $userId = null
    ): void {
        $this->log(
            self::ACTION_DELETE,
            $entity,
            $entityId,
            ['deleted_data' => $deletedData],
            $userId
        );
    }

    /**
     * Registrar visualização de entidade
     *
     * @param string $entity
     * @param mixed $entityId
     * @param int|null $userId
     * @return void
     */
    public function logView(
        string $entity,
        $entityId,
        ?int $userId = null
    ): void {
        $this->log(self::ACTION_VIEW, $entity, $entityId, [], $userId);
    }

    /**
     * Registrar export de dados
     *
     * @param string $entity
     * @param int $count Quantidade de registros
     * @param array $filters Filtros aplicados
     * @param int|null $userId
     * @return void
     */
    public function logExport(
        string $entity,
        int $count,
        array $filters = [],
        ?int $userId = null
    ): void {
        $this->log(
            self::ACTION_EXPORT,
            $entity,
            null,
            [
                'count' => $count,
                'filters' => $filters,
            ],
            $userId
        );
    }

    /**
     * Registrar login bem-sucedido
     *
     * @param int $userId
     * @param string $ipAddress
     * @param array $additionalInfo
     * @return void
     */
    public function logLogin(
        int $userId,
        string $ipAddress = '',
        array $additionalInfo = []
    ): void {
        $this->log(
            self::ACTION_LOGIN,
            'User',
            $userId,
            array_merge(['ip' => $ipAddress], $additionalInfo),
            $userId
        );
    }

    /**
     * Registrar logout
     *
     * @param int $userId
     * @param string $ipAddress
     * @return void
     */
    public function logLogout(
        int $userId,
        string $ipAddress = ''
    ): void {
        $this->log(
            self::ACTION_LOGOUT,
            'User',
            $userId,
            ['ip' => $ipAddress],
            $userId
        );
    }

    /**
     * Registrar tentativa de login falhada
     *
     * @param string $email
     * @param string $ipAddress
     * @param string $reason
     * @return void
     */
    public function logFailedLogin(
        string $email,
        string $ipAddress = '',
        string $reason = 'invalid_credentials'
    ): void {
        $this->log(
            self::ACTION_FAILED_LOGIN,
            'User',
            null,
            [
                'email' => $email,
                'ip' => $ipAddress,
                'reason' => $reason,
            ],
            null
        );
    }

    /**
     * Log genérico de auditoria
     *
     * @param string $action
     * @param string $entity
     * @param mixed $entityId
     * @param array $metadata
     * @param int|null $userId
     * @return void
     */
    public function log(
        string $action,
        string $entity,
        $entityId,
        array $metadata = [],
        ?int $userId = null
    ): void {
        try {
            // Se FileLogger tem método audit(), usar ele
            if (method_exists($this->logger, 'audit')) {
                $this->logger->audit($action, $entity, $entityId, $metadata, $userId);
            } else {
                // Fallback: usar info log
                $context = [
                    'action' => $action,
                    'entity' => $entity,
                    'entity_id' => $entityId,
                    'user_id' => $userId,
                    'metadata' => $metadata,
                ];
                $this->logger->info("Audit: {$action} on {$entity}#{$entityId}", $context);
            }
        } catch (\Exception $e) {
            // Auditoria não deve quebrar o fluxo
            error_log("Audit log error: " . $e->getMessage());
        }
    }
}
