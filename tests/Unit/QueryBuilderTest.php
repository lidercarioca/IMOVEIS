<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Database\QueryBuilder;

/**
 * QueryBuilderTest - Testa a construção de SQL com QueryBuilder
 *
 * Objetivo:
 * - Validar que QueryBuilder gera SQL legível e correto
 * - Verificar que SQL é idêntico ao anterior (compatibilidade)
 * - Testar fluent interface e encadeamento
 */
class QueryBuilderTest extends TestCase
{
    /**
     * Testa seleção básica de colunas
     */
    public function testSelectColumns()
    {
        $qb = new QueryBuilder();
        $sql = $qb->select(['p.*', 'pi.image_url'])
                  ->from('properties p')
                  ->getSql();

        $this->assertStringContainsString('SELECT p.*, pi.image_url', $sql);
        $this->assertStringContainsString('FROM properties p', $sql);
    }

    /**
     * Testa LEFT JOIN
     */
    public function testLeftJoin()
    {
        $qb = new QueryBuilder();
        $sql = $qb->select(['p.*', 'pi.image_url'])
                  ->from('properties p')
                  ->leftJoin('property_images pi', 'p.id = pi.property_id')
                  ->getSql();

        $this->assertStringContainsString('LEFT JOIN property_images pi ON p.id = pi.property_id', $sql);
    }

    /**
     * Testa WHERE clause
     */
    public function testWhereClause()
    {
        $qb = new QueryBuilder();
        $sql = $qb->select(['*'])
                  ->from('properties')
                  ->where('tenant_id = ?', [1])
                  ->getSql();

        $this->assertStringContainsString('WHERE tenant_id = ?', $sql);
        $this->assertEquals([1], $qb->getParams());
    }

    /**
     * Testa múltiplas condições AND
     */
    public function testMultipleAndWhere()
    {
        $qb = new QueryBuilder();
        $sql = $qb->select(['*'])
                  ->from('properties')
                  ->where('tenant_id = ?', [1])
                  ->andWhere('status = ?', ['active'])
                  ->getSql();

        $this->assertStringContainsString('WHERE tenant_id = ? AND status = ?', $sql);
        $this->assertEquals([1, 'active'], $qb->getParams());
    }

    /**
     * Testa ORDER BY
     */
    public function testOrderBy()
    {
        $qb = new QueryBuilder();
        $sql = $qb->select(['*'])
                  ->from('properties')
                  ->orderBy('id', 'DESC')
                  ->getSql();

        $this->assertStringContainsString('ORDER BY id DESC', $sql);
    }

    /**
     * Testa SQL completo (query que PropertyRepository usa)
     *
     * Este teste valida que o QueryBuilder gera SQL idêntico ao anterior
     */
    public function testCompletePropertyQuery()
    {
        $qb = new QueryBuilder();
        $sql = $qb->select(['p.*', 'pi.image_url'])
                  ->from('properties p')
                  ->leftJoin('property_images pi', 'p.id = pi.property_id')
                  ->orderBy('p.id', 'DESC')
                  ->getSql();

        // Esperado (normalizado):
        // SELECT p.*, pi.image_url FROM properties p LEFT JOIN property_images pi ON p.id = pi.property_id ORDER BY p.id DESC

        $this->assertStringContainsString('SELECT p.*, pi.image_url', $sql);
        $this->assertStringContainsString('FROM properties p', $sql);
        $this->assertStringContainsString('LEFT JOIN property_images pi ON p.id = pi.property_id', $sql);
        $this->assertStringContainsString('ORDER BY p.id DESC', $sql);
        
        // Verificar ordem (SELECT antes de FROM antes de WHERE antes de ORDER BY)
        $this->assertLessThan(
            strpos($sql, 'FROM'),
            strpos($sql, 'SELECT'),
            'SELECT deve vir antes de FROM'
        );
        $this->assertLessThan(
            strpos($sql, 'ORDER BY'),
            strpos($sql, 'FROM'),
            'FROM deve vir antes de ORDER BY'
        );
    }

    /**
     * Testa fluent interface (method chaining)
     */
    public function testFluentInterface()
    {
        $qb = new QueryBuilder();
        $result = $qb->select(['id', 'name'])
                     ->from('properties');

        // Deve retornar self para encadeamento
        $this->assertInstanceOf(QueryBuilder::class, $result);
    }

    /**
     * Testa __toString()
     */
    public function testToString()
    {
        $qb = new QueryBuilder();
        $qb->select(['*'])->from('properties');

        $string = (string)$qb;
        $this->assertIsString($string);
        $this->assertStringContainsString('SELECT', $string);
        $this->assertStringContainsString('FROM', $string);
    }

    /**
     * Testa ORDER BY com direção padrão
     */
    public function testOrderByDefaultDirection()
    {
        $qb = new QueryBuilder();
        $sql = $qb->select(['*'])
                  ->from('properties')
                  ->orderBy('id')
                  ->getSql();

        $this->assertStringContainsString('ORDER BY id ASC', $sql);
    }

    /**
     * Testa ORDER BY com direção inválida (deve usar ASC como fallback)
     */
    public function testOrderByInvalidDirection()
    {
        $qb = new QueryBuilder();
        $sql = $qb->select(['*'])
                  ->from('properties')
                  ->orderBy('id', 'INVALID')
                  ->getSql();

        $this->assertStringContainsString('ORDER BY id ASC', $sql);
    }

    /**
     * Testa reset() - método obrigatório para reutilização segura
     *
     * Valida que reset() limpa completamente o estado da instância
     * sem efeitos colaterais entre chamadas
     */
    public function testReset()
    {
        $qb = new QueryBuilder();
        
        // Primeira query
        $qb->select(['id', 'name'])
           ->from('users')
           ->where('active = ?', [1])
           ->orderBy('name', 'ASC');
        
        $sql1 = $qb->getSql();
        $params1 = $qb->getParams();
        
        // Validar primeira query
        $this->assertStringContainsString('SELECT id, name', $sql1);
        $this->assertStringContainsString('FROM users', $sql1);
        $this->assertStringContainsString('WHERE active = ?', $sql1);
        $this->assertStringContainsString('ORDER BY name ASC', $sql1);
        $this->assertEquals([1], $params1);
        
        // Reset - limpar estado
        $qb->reset();
        
        // Segunda query na mesma instância
        $qb->select(['id', 'email'])
           ->from('properties')
           ->where('tenant_id = ?', [5])
           ->andWhere('status = ?', ['active'])
           ->orderBy('id', 'DESC');
        
        $sql2 = $qb->getSql();
        $params2 = $qb->getParams();
        
        // Validar segunda query (deve ser completamente diferente)
        $this->assertStringContainsString('SELECT id, email', $sql2);
        $this->assertStringContainsString('FROM properties', $sql2);
        $this->assertStringContainsString('WHERE tenant_id = ? AND status = ?', $sql2);
        $this->assertStringContainsString('ORDER BY id DESC', $sql2);
        $this->assertEquals([5, 'active'], $params2);
        
        // Validar que não há contaminação entre queries
        $this->assertStringNotContainsString('users', $sql2, 'Primeira query não deve aparecer em sql2');
        $this->assertStringNotContainsString('FROM users', $sql2);
        
        // Reset novamente para estado limpo
        $qb->reset();
        
        // Validar que após reset está vazio
        $this->assertEquals('', $qb->getSql(), 'SQL deve estar vazio após reset');
        $this->assertEquals([], $qb->getParams(), 'Params deve estar vazio após reset');
    }

    /**
     * Testa reset() retorna self para method chaining
     */
    public function testResetReturnsSelf()
    {
        $qb = new QueryBuilder();
        $qb->select(['*'])->from('test');
        
        $result = $qb->reset();
        
        // Deve retornar self para permitir chaining após reset
        $this->assertInstanceOf(QueryBuilder::class, $result);
        $this->assertSame($qb, $result);
        
        // E deve permitir encadeamento após reset
        $result->select(['id'])->from('properties');
        $this->assertStringContainsString('SELECT id', $qb->getSql());
    }
}

