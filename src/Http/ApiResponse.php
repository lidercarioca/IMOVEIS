<?php

namespace App\Http;

/**
 * ApiResponse - Classe para padronizar respostas JSON da API
 * 
 * Implementa um padrão enterprise de resposta JSON com:
 * - Status success/error clara
 * - Estrutura consistente
 * - Headers corretos
 * - Compatibilidade com terminação segura
 * 
 * Uso básico:
 * 
 *   ApiResponse::success(['items' => [...]], 'Dados carregados');
 *   ApiResponse::error('Erro interno', 500);
 *   ApiResponse::validation(['campo' => 'Campo obrigatório']);
 * 
 * @package App\Http
 * @since 2026-05-13
 */
class ApiResponse
{
    /**
     * Estrutura de resposta bem-sucedida
     * 
     * @param mixed $data Dados a serem retornados
     * @param string|null $message Mensagem opcional
     * @param array $meta Metadados adicionais (paginação, etc)
     * @param int $status Código HTTP (padrão: 200)
     * @return void
     */
    public static function success($data = null, ?string $message = null, array $meta = [], int $status = 200): void
    {
        $payload = [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'errors' => [],
            'meta' => $meta,
        ];

        self::send($payload, $status);
    }

    /**
     * Estrutura de resposta de erro genérico
     * 
     * @param string $message Descrição do erro
     * @param int $status Código HTTP (padrão: 500)
     * @param array $errors Detalhes de erros (se houver)
     * @param mixed $data Dados adicionais (se houver)
     * @return void
     */
    public static function error(string $message, int $status = 500, array $errors = [], $data = null): void
    {
        $payload = [
            'success' => false,
            'message' => $message,
            'data' => $data,
            'errors' => $errors,
            'meta' => [],
        ];

        self::send($payload, $status);
    }

    /**
     * Estrutura de resposta de erro de validação
     * 
     * @param array $errors Erros de validação por campo
     * @param string $message Mensagem personalizada
     * @param int $status Código HTTP (padrão: 422 Unprocessable Entity)
     * @return void
     */
    public static function validation(array $errors, ?string $message = "Erro de validação", int $status = 422): void
    {
        $payload = [
            'success' => false,
            'message' => $message,
            'data' => null,
            'errors' => $errors,
            'meta' => [],
        ];

        self::send($payload, $status);
    }

    /**
     * Resposta de recurso não encontrado (404)
     * 
     * @param string $message Mensagem personalizada
     * @return void
     */
    public static function notFound(?string $message = "Recurso não encontrado"): void
    {
        self::error($message, 404);
    }

    /**
     * Resposta de não autenticado (401)
     * 
     * @param string $message Mensagem personalizada
     * @return void
     */
    public static function unauthorized(?string $message = "Não autenticado"): void
    {
        self::error($message, 401);
    }

    /**
     * Resposta de acesso negado (403)
     * 
     * @param string $message Mensagem personalizada
     * @return void
     */
    public static function forbidden(?string $message = "Acesso negado"): void
    {
        self::error($message, 403);
    }

    /**
     * Envia a resposta JSON com headers corretos
     * 
     * Privado: usar métodos públicos como success(), error(), etc
     * 
     * @param array $payload Dados a enviar
     * @param int $status Código HTTP
     * @return void
     */
    private static function send(array $payload, int $status): void
    {
        // Headers padrão
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($status);

        // Enviar JSON com flags padrão
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

        // Encerrar execução
        exit;
    }
}
