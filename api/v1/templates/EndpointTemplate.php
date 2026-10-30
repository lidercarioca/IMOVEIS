<?php

/**
 * API v1 Endpoint Template
 *
 * Padrão de endpoint para /api/v1:
 *   1. bootstrap.php
 *   2. AuthMiddleware
 *   3. TenantMiddleware
 *   4. Service Layer
 *   5. Response JSON
 *
 * Uso:
 *   require_once __DIR__ . '/../../app/bootstrap.php';
 *
 *   try {
 *       api_v1_require_auth();
 *       api_v1_resolve_tenant();
 *
 *       $service = new \App\Service\ExampleService();
 *       $result = $service->execute();
 *
 *       \App\Http\Response::success($result);
 *   } catch (\Throwable $e) {
 *       \App\Http\Response::serverError('Erro ao processar requisição');
 *   }
 */

// Este arquivo é apenas um template de referência para novos endpoints /api/v1.
// Não deve ser usado como endpoint real.
