<?php

namespace App\Service;

use PDO;
use App\Infrastructure\Agendamento\AgendamentoRepository;
use App\Infrastructure\User\UserRepository;

require_once dirname(dirname(__DIR__)) . '/app/utils/NotificationManager.php';
require_once dirname(dirname(__DIR__)) . '/app/utils/EmailNotificationService.php';

/**
 * AgendamentoService - camada de orquestração do domínio Agendamentos
 *
 * Fase A: estrutura mínima, sem SQL no endpoint, apenas delegando
 * para o repository e preservando o contrato de resposta legado.
 */
class AgendamentoService
{
    private PDO $pdo;
    private AgendamentoRepository $repository;

    public function __construct(PDO $pdo, ?AgendamentoRepository $repository = null)
    {
        $this->pdo = $pdo;
        $this->repository = $repository ?? new AgendamentoRepository($pdo);
    }

    public function list(array $options = []): array
    {
        $rows = $this->repository->list($options);

        return array_map(function (array $row): array {
            return $this->formatRow($row);
        }, $rows);
    }

    public function get(int $id, int $tenantId): ?array
    {
        $row = $this->repository->findById($id, $tenantId);

        return $row !== null ? $this->formatRow($row) : null;
    }

    public function isAdmin(int $userId, int $tenantId): bool
    {
        $role = $this->resolveRole($userId, $tenantId);

        return $role === 'admin' || $role === 'administrator';
    }

    public function resolveRole(int $userId, int $tenantId): ?string
    {
        $userRepository = new UserRepository($this->pdo);
        $role = $userRepository->getRole($userId, $tenantId);

        return $role !== null ? strtolower(trim($role)) : null;
    }

    public function listUpcoming(int $tenantId, ?int $userId = null, bool $isAdmin = false): array
    {
        return $this->repository->findUpcoming($tenantId, $userId, $isAdmin);
    }

    public function getTodayScheduleSummary(int $tenantId, ?int $userId = null, bool $isAdmin = false): array
    {
        $todayAgendamentos = $this->repository->findTodaySchedule($tenantId, $userId, $isAdmin);

        foreach ($todayAgendamentos as &$agendamento) {
            $agendamento['contact_name'] = 'Não informado';
            $agendamento['contact_phone'] = 'Não informado';

            if (!empty($agendamento['leads_id'])) {
                $lead = $this->repository->findLeadNameEmail((int) $agendamento['leads_id'], $tenantId);
                if ($lead) {
                    $agendamento['contact_name'] = $lead['name'] ?? 'Não informado';
                    $agendamento['contact_phone'] = $lead['phone'] ?? 'Não informado';
                }
            }
        }
        unset($agendamento);

        $totalToday = count($todayAgendamentos);
        $completed = count(array_filter($todayAgendamentos, static fn($a) => ($a['status'] ?? '') === 'completed'));
        $pending = count(array_filter($todayAgendamentos, static fn($a) => ($a['status'] ?? '') === 'pending'));
        $cancelled = count(array_filter($todayAgendamentos, static fn($a) => ($a['status'] ?? '') === 'cancelled'));
        $attendanceRate = $totalToday > 0 ? round(($completed / $totalToday) * 100) : 0;

        $pendingDays = $this->repository->findPendingByDays($tenantId, $userId, $isAdmin, 7);
        $attendance = $this->repository->findAttendance30Days($tenantId, $userId, $isAdmin);

        return [
            'success' => true,
            'today' => [
                'agendamentos' => array_map(function (array $a): array {
                    return [
                        'id' => (int)($a['id'] ?? 0),
                        'property_id' => (int)($a['property_id'] ?? 0),
                        'property_title' => $a['property_title'] ?? null,
                        'price' => (float)($a['price'] ?? 0),
                        'neighborhood' => $a['neighborhood'] ?? null,
                        'contact_name' => $a['contact_name'] ?? 'Não informado',
                        'contact_phone' => $a['contact_phone'] ?? 'Não informado',
                        'appointment_date' => $a['data_agendamento'] ?? null,
                        'time' => !empty($a['data_agendamento']) ? date('H:i', strtotime($a['data_agendamento'])) : '',
                        'status' => $a['status'] ?? null,
                        'status_label' => $this->getStatusLabel($a['status'] ?? null),
                        'agent_name' => $a['agent_name'] ?? 'Não atribuído',
                        'notes' => $a['descricao'] ?? '',
                    ];
                }, $todayAgendamentos),
                'statistics' => [
                    'total' => $totalToday,
                    'completed' => $completed,
                    'pending' => $pending,
                    'cancelled' => $cancelled,
                    'attendance_rate' => $attendanceRate,
                ]
            ],
            'pending' => [
                'by_days' => array_map(static function (array $p): array {
                    $days = (int)($p['dias_para'] ?? 0);
                    return [
                        'days' => $days,
                        'total' => (int)($p['total'] ?? 0),
                        'label' => $days == 1 ? 'Amanhã' : 'Em ' . $days . ' dias',
                    ];
                }, $pendingDays),
            ],
            'attendance_30_days' => [
                'attended' => (int)($attendance['attended'] ?? 0),
                'cancelled' => (int)($attendance['cancelled'] ?? 0),
                'total' => (int)($attendance['total'] ?? 0),
                'rate' => ((int)($attendance['total'] ?? 0)) > 0 ? round(((int)($attendance['attended'] ?? 0) / (int)($attendance['total'] ?? 1)) * 100) : 0,
            ],
        ];
    }

    public function getUpcomingSummary(int $tenantId, ?int $userId = null, bool $isAdmin = false): array
    {
        $agendamentos = $this->listUpcoming($tenantId, $userId, $isAdmin);

        $hoje = [];
        $amanha = [];
        $dataHoje = date('Y-m-d');
        $dataAmanha = date('Y-m-d', strtotime('+1 day'));

        foreach ($agendamentos as $agendamento) {
            $dataAgendamento = substr($agendamento['data_agendamento'], 0, 10);

            if ($dataAgendamento === $dataHoje) {
                $hoje[] = $agendamento;
            } elseif ($dataAgendamento === $dataAmanha) {
                $amanha[] = $agendamento;
            }
        }

        return [
            'data' => $agendamentos,
            'hoje' => $hoje,
            'amanha' => $amanha,
            'total' => count($agendamentos),
        ];
    }

    private function getStatusLabel(?string $status): string
    {
        $labels = [
            'pending' => 'Pendente',
            'completed' => 'Realizado',
            'cancelled' => 'Cancelado',
            'no_show' => 'Não compareceu',
            'confirmado' => 'Confirmado',
            'pendente' => 'Pendente',
            'realizado' => 'Realizado',
            'cancelado' => 'Cancelado',
        ];

        return $labels[$status ?? ''] ?? 'Desconhecido';
    }

    public function getLeadsWithProperties(int $tenantId, ?int $userId = null, bool $isAdmin = false): array
    {
        return $this->repository->findLeadsWithProperties($tenantId, $userId, $isAdmin);
    }

    public function create(array $data, int $tenantId, int $userId): ?array
    {
        $propertyId = isset($data['property_id']) ? (int) $data['property_id'] : 0;
        if ($propertyId <= 0 || !$this->repository->propertyBelongsToTenant($propertyId, $tenantId)) {
            throw new \RuntimeException('Propriedade não encontrada ou sem permissão');
        }

        $leadId = isset($data['leads_id']) && $data['leads_id'] !== '' ? (int) $data['leads_id'] : null;
        if ($leadId !== null && !$this->repository->leadBelongsToTenant($leadId, $tenantId)) {
            throw new \RuntimeException('Lead não encontrado ou sem permissão');
        }

        $insertData = [
            'tenant_id' => $tenantId,
            'property_id' => $propertyId,
            'leads_id' => $leadId,
            'phone' => $data['telefone'] ?? null,
            'data_agendamento' => $data['data_agendamento'],
            'user_id' => $userId,
            'descricao' => $data['descricao'] ?? null,
        ];

        $createdId = $this->repository->create($insertData);
        if ($createdId <= 0) {
            return null;
        }

        $agendamento = $this->repository->findById($createdId, $tenantId);
        if ($agendamento === null) {
            return null;
        }

        try {
            $leadName = null;
            $leadEmail = null;
            $leadId = $insertData['leads_id'];

            if ($leadId) {
                $lead = $this->repository->findLeadNameEmail((int)$leadId, $insertData['tenant_id']);
                if ($lead) {
                    $leadName = $lead['name'];
                    $leadEmail = $lead['email'];
                }
            }

            if (!$leadName) {
                $leadName = $insertData['phone'] ?? 'Cliente Externo';
            }

            $notificationManager = new \NotificationManager($this->pdo, $tenantId);
            $notificationManager->notifyNewAgendamento(
                $agendamento['property_title'] ?? 'Imóvel',
                $insertData['data_agendamento'],
                $agendamento['user_name'] ?? 'Usuário'
            );

            $emailService = new \EmailNotificationService($this->pdo, $tenantId);
            $emailService->notifyNewAgendamento(
                $agendamento['property_title'] ?? 'Imóvel',
                $insertData['data_agendamento'],
                $leadName,
                $leadEmail ?? 'nao-informado@email.com'
            );
        } catch (\Exception $e) {
            error_log('Erro ao enviar notificação de agendamento: ' . $e->getMessage());
        }

        return $this->formatRow($agendamento);
    }

    public function update(int $id, int $tenantId, array $data): bool
    {
        $result = $this->repository->update($id, $tenantId, $data);
        if (!$result || empty($data['status'])) {
            return $result;
        }

        try {
            $agendamento = $this->repository->findById($id, $tenantId);
            if ($agendamento) {
                $notificationManager = new \NotificationManager($this->pdo, $tenantId);
                $statusTexto = [
                    'confirmado' => 'Confirmado',
                    'cancelado' => 'Cancelado',
                    'realizado' => 'Realizado'
                ];

                $statusLabel = $statusTexto[strtolower(trim((string)$data['status']))] ?? ucfirst((string)$data['status']);

                $notificationManager->createNotification(
                    'agendamento',
                    'Agendamento ' . $statusLabel,
                    'O agendamento para "' . ($agendamento['property_title'] ?? 'Imovel') . '" foi marcado como ' . $statusLabel,
                    'painel.php?tab=agendamentos',
                    null
                );
            }
        } catch (\Exception $e) {
            error_log('Erro ao criar notificação de alteração de status de agendamento: ' . $e->getMessage());
        }

        return true;
    }

    public function updateDate(int $id, string $dataAgendamento, int $tenantId): bool
    {
        $result = $this->repository->update($id, $tenantId, ['data_agendamento' => $dataAgendamento]);
        if (!$result) {
            return false;
        }

        try {
            $agendamento = $this->repository->findById($id, $tenantId);
            if ($agendamento) {
                $notificationManager = new \NotificationManager($this->pdo, $tenantId);
                $notificationManager->createNotification(
                    'agendamento',
                    'Agendamento Alterado',
                    'O agendamento para "' . ($agendamento['property_title'] ?? 'Imóvel') . '" foi alterado para ' . $dataAgendamento . ' por ' . ($agendamento['user_name'] ?? 'Usuário'),
                    'painel.php?tab=agendamentos',
                    null
                );
            }
        } catch (\Exception $e) {
            error_log('Erro ao enviar notificação de alteração de agendamento: ' . $e->getMessage());
        }

        return true;
    }

    public function delete(int $id, int $tenantId, ?int $userId = null, bool $isAdmin = false): array
    {
        $agendamento = $this->repository->findOwnerById($id, $tenantId);

        if ($agendamento === null) {
            return [
                'success' => false,
                'status' => 404,
                'message' => 'Agendamento não encontrado',
            ];
        }

        if (!$isAdmin && (int)$agendamento['user_id'] !== (int)$userId) {
            return [
                'success' => false,
                'status' => 403,
                'message' => 'Sem permissão para deletar este agendamento',
            ];
        }

        $result = $this->repository->softDelete($id, $tenantId);

        if (!$result) {
            return [
                'success' => false,
                'status' => 400,
                'message' => 'Erro ao deletar agendamento',
            ];
        }

        return [
            'success' => true,
            'status' => 200,
            'message' => 'Agendamento deletado com sucesso',
        ];
    }

    private function formatRow(array $row): array
    {
        $dataAgendamento = new \DateTime($row['data_agendamento']);
        $hoje = new \DateTime();
        $intervalo = $hoje->diff($dataAgendamento);

        $row['dias_faltam'] = $intervalo->invert ? -$intervalo->days : $intervalo->days;
        $row['data_formatada'] = $dataAgendamento->format('d/m/Y H:i');

        return $row;
    }
}
