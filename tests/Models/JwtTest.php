<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../config/secure_jwt.php';

class JwtTest extends TestCase
{
    public function testJwtEncodeDecode()
    {
        $payload = ['test' => 'data', 'exp' => time() + 3600];
        $secret = 'secret';

        $token = Firebase\JWT\JWT::encode($payload, $secret, 'HS256');
        $this->assertNotEmpty($token, 'Token deve ser gerado');

        $decoded = Firebase\JWT\JWT::decode($token, new Firebase\JWT\Key($secret, 'HS256'));
        $this->assertObjectHasProperty('test', $decoded);
        $this->assertSame('data', $decoded->test);
    }
}
