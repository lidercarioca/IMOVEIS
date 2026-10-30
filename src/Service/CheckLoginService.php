<?php

namespace App\Service;

use App\Infrastructure\User\UserRepository;
use App\Repository\AgendamentoRepository;
use InvalidArgumentException;
use PDO;
use RuntimeException;

class CheckLoginService
{
    private PDO $pdo;
    private UserRepository $userRepository;
    private AgendamentoRepository $agendamentoRepository;

    public function __construct(PDO $pdo, ?AgendamentoRepository $agendamentoRepository = null, ?UserRepository $userRepository = null)
    {
        $this->pdo = $pdo;
        $this->agendamentoRepository = $agendamentoRepository ?? new AgendamentoRepository($pdo);
        $this->userRepository = $userRepository ?? new UserRepository($pdo);
    }

    public function getLoginStatus(int $userId, int $tenantId, bool $isAdmin): array
    {
        $this->validateUserAndTenant($userId, $tenantId);

        $user = $this->userRepository->findById($userId, $tenantId);
        if ($user === null) {
            throw new RuntimeException('Usuário não pertence ao tenant atual');
        }

        return [
            'logado' => true,
            'usuario' => $user,
            'isAdmin' => $isAdmin,
            'agendamentos_do_usuario' => $this->agendamentoRepository->countForUser($userId, $tenantId),
            'total_agendamentos' => $isAdmin ? $this->agendamentoRepository->countForTenant($tenantId) : 0,
        ];
    }

    private function validateUserAndTenant(int $userId, int $tenantId): void
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('Usuário inválido');
        }

        if ($tenantId <= 0) {
            throw new InvalidArgumentException('Contexto de tenant inválido');
        }
    }
}
