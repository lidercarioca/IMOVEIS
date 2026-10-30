<?php

namespace App\Auth;

final class AuthenticatedIdentityAdapter
{
    /**
     * Adapta um RequestAuthenticationResult para AuthenticatedIdentity
     * e materializa o AuthContext para compatibilidade legada.
     *
     * Não realiza nenhuma autenticação/validação adicional — apenas usa
     * os dados presentes em RequestAuthenticationResult.
     */
    public static function adapt(RequestAuthenticationResult $result): ?AuthenticatedIdentity
    {
        if (!$result->isAuthenticated()) {
            return null;
        }

        $identity = AuthenticatedIdentity::fromRequestAuthenticationResult($result);
        if ($identity === null) {
            return null;
        }

        AuthContext::getInstance()->materializeIdentity($identity);

        return $identity;
    }
}
