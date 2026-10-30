<?php

namespace App\Auth;

final class AuthenticationPolicy
{
    public const JWT_SESSION_IDENTITY_CONFLICT = 'jwt_session_identity_conflict';

    /**
     * Resolve a precedência entre JWT e sessão sem duplicar validação.
     *
     * A decisão é determinística e só depende dos resultados já produzidos pelos
     * produtores autenticadores. Nenhuma regra de autorização, tenant ou banco é
     * aplicada nesta camada.
     */
    public static function decide(
        ?RequestAuthenticationResult $jwtResult = null,
        ?RequestAuthenticationResult $sessionResult = null,
        bool $hasBearerHeader = false
    ): RequestAuthenticationResult {
        $jwt = $jwtResult ?? new RequestAuthenticationResult(
            RequestAuthenticationResult::STATUS_NO_CREDENTIAL,
            RequestAuthenticationResult::SOURCE_NONE
        );

        $session = $sessionResult ?? new RequestAuthenticationResult(
            RequestAuthenticationResult::STATUS_NO_CREDENTIAL,
            RequestAuthenticationResult::SOURCE_NONE
        );

        if ($jwt->isAuthenticated() && $session->isAuthenticated()) {
            if ($jwt->getUserId() === $session->getUserId()) {
                return $jwt;
            }

            return new RequestAuthenticationResult(
                RequestAuthenticationResult::STATUS_AUTHENTICATION_ERROR,
                RequestAuthenticationResult::SOURCE_NONE,
                null,
                null,
                [],
                false,
                null,
                null,
                self::JWT_SESSION_IDENTITY_CONFLICT
            );
        }

        if ($hasBearerHeader) {
            if ($jwt->isAuthenticated()) {
                return $jwt;
            }

            if ($jwt->getStatus() !== RequestAuthenticationResult::STATUS_NO_CREDENTIAL) {
                return $jwt;
            }

            if ($session->isAuthenticated()) {
                return $session;
            }

            return $jwt;
        }

        if ($session->isAuthenticated()) {
            return $session;
        }

        if ($jwt->isAuthenticated()) {
            return $jwt;
        }

        if ($session->getStatus() !== RequestAuthenticationResult::STATUS_NO_CREDENTIAL) {
            return $session;
        }

        if ($jwt->getStatus() !== RequestAuthenticationResult::STATUS_NO_CREDENTIAL) {
            return $jwt;
        }

        return new RequestAuthenticationResult(
            RequestAuthenticationResult::STATUS_NO_CREDENTIAL,
            RequestAuthenticationResult::SOURCE_NONE
        );
    }
}
