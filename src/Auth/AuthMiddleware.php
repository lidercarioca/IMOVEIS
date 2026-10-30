<?php
namespace App\Auth;

use App\Middleware\AuthMiddleware as BaseAuthMiddleware;

/**
 * AuthMiddleware - Wrapper de compatibilidade para o middleware de autenticação.
 *
 * Este arquivo existe para cumprir a estrutura de pastas esperada
 * em /src/Auth sem duplicar a lógica existente.
 */
class AuthMiddleware extends BaseAuthMiddleware
{
}
