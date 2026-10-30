<?php

namespace App\Http;

class Response
{
    public static function json($payload, int $statusCode = 200, array $headers = []): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');

        foreach ($headers as $name => $value) {
            header(sprintf('%s: %s', $name, $value));
        }

        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    public static function success($data = null, string $message = 'Success', int $statusCode = 200, bool $includeMessage = true, array $extras = []): void
    {
        $payload = [
            'success' => true,
            'data' => $data,
        ];

        if ($includeMessage) {
            $payload['message'] = $message;
        }

        if (!empty($extras)) {
            $payload = array_merge($payload, $extras);
        }

        self::json($payload, $statusCode);
    }

    public static function error(string $message = 'Error', int $statusCode = 400, $errors = null): void
    {
        $payload = [
            'success' => false,
            'message' => $message,
            'error' => $message,
            'data' => null,
        ];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        self::json($payload, $statusCode);
    }

    public static function unauthorized(string $message = 'Unauthorized'): void
    {
        self::error($message, 401);
    }

    public static function forbidden(string $message = 'Forbidden'): void
    {
        self::error($message, 403);
    }

    public static function serverError(string $message = 'Internal Server Error'): void
    {
        self::error($message, 500);
    }
}
