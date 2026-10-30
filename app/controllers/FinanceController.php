<?php
/**
 * FinanceController - Controller de Finanças (Exemplo refatorado)
 * 
 * Usa Services para lógica de negócio
 * Usa ResponseFormatter para respostas padronizadas
 * Responsável apenas por receber requisição e retornar resposta
 */

namespace App\Controllers;

use App\Services\FinanceService;
use App\Models\Transaction;
use App\Utils\ResponseFormatter;
use App\Utils\Validator;
use App\Utils\Logger;
use PDO;

class FinanceController extends BaseController {
    protected FinanceService $service;
    
    public function __construct(PDO $pdo) {
        parent::__construct();
        
        $transaction = new Transaction($pdo);
        $this->service = new FinanceService($transaction);
        
        // Inicializar logger
        Logger::init(__DIR__ . '/../../logs');
    }
    
    /**
     * Obter resumo do mês
     */
    public function getSummary(): string {
        try {
            if (!$this->requireAuth()) {
                return ResponseFormatter::unauthorized('Usuário não autenticado');
            }
            
            $mes = $_GET['mes'] ?? date('Y-m');
            
            // Validar formato do mês
            if (!Validator::month($mes)) {
                return ResponseFormatter::unprocessable('Formato de mês inválido', [
                    'mes' => 'Use formato YYYY-MM'
                ]);
            }
            
            $summary = $this->service->getMonthlySummary($mes);
            
            $this->logOperation('get_finance_summary', null, "Mês: {$mes}");
            
            return ResponseFormatter::success($summary, 'Resumo financeiro obtido com sucesso');
        } catch (\Exception $e) {
            Logger::error('Erro ao obter resumo financeiro', [
                'user_id' => $this->userId,
                'error' => $e->getMessage()
            ]);
            
            return ResponseFormatter::serverError('Erro ao obter resumo financeiro');
        }
    }
    
    /**
     * Listar transações
     */
    public function list(): string {
        try {
            if (!$this->requireAuth()) {
                return ResponseFormatter::unauthorized('Usuário não autenticado');
            }
            
            $mes = $_GET['mes'] ?? date('Y-m');
            $page = (int)($_GET['page'] ?? 1);
            $limit = (int)($_GET['limit'] ?? 20);
            
            // Validar paginação
            if ($page < 1 || $limit < 1 || $limit > 100) {
                return ResponseFormatter::unprocessable('Parâmetros de paginação inválidos');
            }
            
            $filters = [
                'tipo' => $_GET['tipo'] ?? null,
                'categoria' => $_GET['categoria'] ?? null,
                'status' => $_GET['status'] ?? null
            ];
            
            $result = $this->service->listTransactions($mes, $filters, $page, $limit);
            
            $this->logOperation('list_transactions', null, "Página: {$page}");
            
            return ResponseFormatter::paginated(
                $result['data'],
                $result['pagination']['page'],
                $result['pagination']['limit'],
                $result['pagination']['total'],
                'Transações listadas com sucesso'
            );
        } catch (\Exception $e) {
            Logger::error('Erro ao listar transações', [
                'user_id' => $this->userId,
                'error' => $e->getMessage()
            ]);
            
            return ResponseFormatter::serverError('Erro ao listar transações');
        }
    }
    
    /**
     * Criar transação
     */
    public function create(): string {
        try {
            if (!$this->requireAuth()) {
                return ResponseFormatter::unauthorized('Usuário não autenticado');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data) {
                return ResponseFormatter::unprocessable('Dados inválidos ou JSON malformado');
            }
            
            // Validar campos obrigatórios
            $errors = Validator::requiredFields($data, [
                'tipo',
                'descricao',
                'valor',
                'data_transacao'
            ]);
            
            if (!empty($errors)) {
                return ResponseFormatter::unprocessable('Campos obrigatórios faltando', $errors);
            }
            
            // Validar tipo
            if (!Validator::inArray($data['tipo'], ['receita', 'despesa', 'comissao'])) {
                return ResponseFormatter::unprocessable('Tipo de transação inválido', [
                    'tipo' => 'Deve ser: receita, despesa ou comissao'
                ]);
            }
            
            // Validar valor
            if (!Validator::float($data['valor']) || (float)$data['valor'] <= 0) {
                return ResponseFormatter::unprocessable('Valor inválido', [
                    'valor' => 'Deve ser um número positivo'
                ]);
            }
            
            // Validar data
            if (!Validator::date($data['data_transacao'])) {
                return ResponseFormatter::unprocessable('Data inválida', [
                    'data_transacao' => 'Use formato YYYY-MM-DD'
                ]);
            }
            
            // Adicionar user_id
            $data['user_id'] = $this->userId;
            
            $id = $this->service->createTransaction($data);
            
            Logger::audit('create_transaction', $this->userId, 'transaction', [
                'id' => $id,
                'tipo' => $data['tipo'],
                'valor' => $data['valor']
            ]);
            
            return ResponseFormatter::success(
                ['id' => $id],
                'Transação criada com sucesso',
                ResponseFormatter::CREATED
            );
        } catch (\Exception $e) {
            Logger::error('Erro ao criar transação', [
                'user_id' => $this->userId,
                'error' => $e->getMessage()
            ]);
            
            return ResponseFormatter::serverError('Erro ao criar transação');
        }
    }
    
    /**
     * Atualizar transação
     */
    public function update(?int $id = null): string {
        try {
            if (!$this->requireAuth()) {
                return ResponseFormatter::unauthorized('Usuário não autenticado');
            }
            
            $id = $id ?? (int)($_GET['id'] ?? $_POST['id'] ?? 0);
            
            if (!$id || !Validator::integer($id)) {
                return ResponseFormatter::unprocessable('ID de transação inválido');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data) {
                return ResponseFormatter::unprocessable('Dados inválidos ou JSON malformado');
            }
            
            // Validar tipo se fornecido
            if (isset($data['tipo']) && !Validator::inArray($data['tipo'], ['receita', 'despesa', 'comissao'])) {
                return ResponseFormatter::unprocessable('Tipo de transação inválido');
            }
            
            // Validar valor se fornecido
            if (isset($data['valor']) && (!Validator::float($data['valor']) || (float)$data['valor'] <= 0)) {
                return ResponseFormatter::unprocessable('Valor inválido');
            }
            
            $this->service->update($id, $data);
            
            Logger::audit('update_transaction', $this->userId, 'transaction', [
                'id' => $id
            ]);
            
            return ResponseFormatter::success([], 'Transação atualizada com sucesso');
        } catch (\Exception $e) {
            Logger::error('Erro ao atualizar transação', [
                'user_id' => $this->userId,
                'transaction_id' => $id ?? null,
                'error' => $e->getMessage()
            ]);
            
            return ResponseFormatter::serverError('Erro ao atualizar transação');
        }
    }
    
    /**
     * Deletar transação
     */
    public function delete(?int $id = null): string {
        try {
            if (!$this->requireAuth()) {
                return ResponseFormatter::unauthorized('Usuário não autenticado');
            }
            
            $id = $id ?? (int)($_GET['id'] ?? $_POST['id'] ?? 0);
            
            if (!$id || !Validator::integer($id)) {
                return ResponseFormatter::unprocessable('ID de transação inválido');
            }
            
            $this->service->delete($id);
            
            Logger::audit('delete_transaction', $this->userId, 'transaction', [
                'id' => $id
            ]);
            
            return ResponseFormatter::success([], 'Transação deletada com sucesso');
        } catch (\Exception $e) {
            Logger::error('Erro ao deletar transação', [
                'user_id' => $this->userId,
                'transaction_id' => $id ?? null,
                'error' => $e->getMessage()
            ]);
            
            return ResponseFormatter::serverError('Erro ao deletar transação');
        }
    }
}
