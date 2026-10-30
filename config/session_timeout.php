<?php
/**
 * Configurações Centralizadas de Timeout de Sessão
 * Garante consistência em toda aplicação
 */

// ========== TIMEOUTS DE SESSÃO ==========

// Tempo de inatividade máximo antes de expirar a sessão (em segundos)
// Padrão: 6 horas (21600s) - Suficiente para um dia de trabalho com pausas
// O keep-alive do JavaScript renova a cada 5 minutos quando usuário está ativo
const SESSION_TIMEOUT = 21600; // 6 horas

// Intervalo máximo entre renovações de activity (em segundos)
// Se não renovar em mais tempo que isso, a sessão expira
const SESSION_ABSOLUTE_TIMEOUT = 28800; // 8 horas (máximo absoluto)

// Tempo de inatividade para avisar o usuário (em segundos)
const SESSION_WARNING_TIME = 20 * 60; // 20 minutos

// Tempo máximo de inatividade do JavaScript antes de considerar usuário offline (ms)
const JS_INACTIVITY_TIMEOUT = 25 * 60 * 1000; // 25 minutos

// Intervalo de heartbeat do JavaScript (ms)
const JS_HEARTBEAT_INTERVAL = 5 * 60 * 1000; // 5 minutos

/**
 * Retorna o timeout configurado
 * @return int Timeout em segundos
 */
function getSessionTimeout() {
    return SESSION_TIMEOUT;
}

/**
 * Retorna o timeout absoluto configurado
 * @return int Timeout em segundos
 */
function getSessionAbsoluteTimeout() {
    return SESSION_ABSOLUTE_TIMEOUT;
}

/**
 * Valida tempo de session contra timeout
 * @param int $lastActivity Timestamp do último acesso
 * @return bool True se ainda está válido
 */
function isSessionValid($lastActivity) {
    $currentTime = time();
    $elapsed = $currentTime - $lastActivity;
    return $elapsed <= SESSION_TIMEOUT;
}

/**
 * Verifica se deve avisar o usuário sobre expiração
 * @param int $lastActivity Timestamp do último acesso
 * @return bool True se deve avisar
 */
function shouldWarnSessionExpiry($lastActivity) {
    $currentTime = time();
    $elapsed = $currentTime - $lastActivity;
    $warningThreshold = SESSION_TIMEOUT - SESSION_WARNING_TIME;
    return $elapsed > $warningThreshold;
}
?>
