<?php
namespace App\Middleware;

/**
 * MiddlewareInterface - Contrato para middlewares
 *
 * Middlewares processam requisições e respostas.
 */
interface MiddlewareInterface
{
    /**
     * Processar middleware
     *
     * @param array $request Dados da requisição
     * @param callable $next Próximo middleware
     * @return array Resposta
     */
    public function handle(array $request, callable $next): array;
}
