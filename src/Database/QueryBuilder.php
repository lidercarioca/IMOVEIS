<?php
namespace App\Database;

/**
 * QueryBuilder - Construtor de SQL queries leve (sem frameworks externos)
 *
 * Responsável por:
 * - Abstrair construção manual de SQL
 * - Manter SQL legível e reutilizável
 * - Facilitar debugging e logs de queries
 * - Evitar repetição de padrões comuns
 *
 * Design:
 * - Fluent interface (method chaining)
 * - Totalmente agnóstico sobre regras de negócio
 * - Não executa queries (aquele é papel do Repository)
 * - Suporta prepared statements
 *
 * Exemplo:
 *   $query = (new QueryBuilder())
 *       ->select(['p.*', 'pi.image_url'])
 *       ->from('properties p')
 *       ->leftJoin('property_images pi', 'p.id = pi.property_id')
 *       ->where('p.tenant_id = ?', [$tenantId])
 *       ->andWhere('p.status IN (?, ?)', ['active', 'sold'])
 *       ->orderBy('p.id', 'DESC');
 *
 *   $sql = $query->getSql();
 *   $params = $query->getParams();
 */
class QueryBuilder
{
    private array $select = [];
    private string $from = '';
    private array $joins = [];
    private array $where = [];
    private array $params = [];
    private string $orderBy = '';
    private ?int $limit = null;

    /**
     * Define as colunas a serem selecionadas
     *
     * @param array $columns Array de colunas (ex: ['p.*', 'pi.image_url'])
     * @return self
     */
    public function select(array $columns): self
    {
        $this->select = $columns;
        return $this;
    }

    /**
     * Define a tabela principal FROM
     *
     * @param string $table Nome da tabela com alias (ex: 'properties p')
     * @return self
     */
    public function from(string $table): self
    {
        $this->from = $table;
        return $this;
    }

    /**
     * Adiciona um LEFT JOIN
     *
     * @param string $table Tabela a dar join (ex: 'property_images pi')
     * @param string $on Condição do join (ex: 'p.id = pi.property_id')
     * @return self
     */
    public function leftJoin(string $table, string $on): self
    {
        $this->joins[] = "LEFT JOIN {$table} ON {$on}";
        return $this;
    }

    /**
     * Adiciona um INNER JOIN
     *
     * @param string $table Tabela a dar join
     * @param string $on Condição do join
     * @return self
     */
    public function innerJoin(string $table, string $on): self
    {
        $this->joins[] = "INNER JOIN {$table} ON {$on}";
        return $this;
    }

    /**
     * Adiciona a primeira condição WHERE (ou raplica se já existe)
     *
     * IMPORTANTE: Se houver múltiplas condições, use andWhere()
     *
     * @param string $condition Condição SQL (ex: 'p.tenant_id = ?')
     * @param array $params Parâmetros para prepared statement
     * @return self
     */
    public function where(string $condition, array $params = []): self
    {
        // Se já há WHERE conditions, isso replace (não é comum em QueryBuilder,
        // mas deixamos como fallback). Geralmente use andWhere().
        $this->where = [$condition];
        $this->params = array_merge($this->params, $params);
        return $this;
    }

    /**
     * Adiciona uma condição AND WHERE
     *
     * @param string $condition Condição SQL (ex: 'p.status = ?')
     * @param array $params Parâmetros para prepared statement
     * @return self
     */
    public function andWhere(string $condition, array $params = []): self
    {
        $this->where[] = $condition;
        $this->params = array_merge($this->params, $params);
        return $this;
    }

    /**
     * Adiciona uma condição OR WHERE
     *
     * @param string $condition Condição SQL
     * @param array $params Parâmetros para prepared statement
     * @return self
     */
    public function orWhere(string $condition, array $params = []): self
    {
        if (empty($this->where)) {
            $this->where[] = $condition;
        } else {
            // Envolvemos em parênteses para manter precedência
            $this->where[count($this->where) - 1] .= " OR ({$condition})";
        }
        $this->params = array_merge($this->params, $params);
        return $this;
    }

    /**
     * Define ORDER BY
     *
     * @param string $column Coluna a ordenar (ex: 'p.id')
     * @param string $direction Direção (ASC ou DESC)
     * @return self
     */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $direction = strtoupper($direction);
        if (!in_array($direction, ['ASC', 'DESC'])) {
            $direction = 'ASC';
        }
        $this->orderBy = "{$column} {$direction}";
        return $this;
    }

    /**
     * Define um limite de resultados
     *
     * @param int $limit Número máximo de linhas retornadas
     * @return self
     */
    public function limit(int $limit): self
    {
        if ($limit > 0) {
            $this->limit = $limit;
        }
        return $this;
    }

    /**
     * Retorna o SQL completo montado
     *
     * @return string SQL pronto para prepared statement
     */
    public function getSql(): string
    {
        $sql = '';

        // SELECT
        if (!empty($this->select)) {
            $sql .= 'SELECT ' . implode(', ', $this->select) . ' ';
        }

        // FROM
        if ($this->from) {
            $sql .= 'FROM ' . $this->from . ' ';
        }

        // JOINs
        if (!empty($this->joins)) {
            $sql .= implode(' ', $this->joins) . ' ';
        }

        // WHERE
        if (!empty($this->where)) {
            $sql .= 'WHERE ' . implode(' AND ', $this->where) . ' ';
        }

        // ORDER BY
        if ($this->orderBy) {
            $sql .= 'ORDER BY ' . $this->orderBy . ' ';
        }

        // LIMIT
        if ($this->limit !== null) {
            $sql .= 'LIMIT ' . $this->limit;
        }

        return trim($sql);
    }

    /**
     * Retorna os parâmetros na ordem correta para prepared statement
     *
     * @return array Parâmetros para execute()
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * Reseta o QueryBuilder para reutilização segura
     *
     * Remove todos os states anteriores, permitindo reutilizar
     * a instância sem efeitos colaterais entre chamadas.
     *
     * @return self
     */
    public function reset(): self
    {
        $this->select = [];
        $this->from = '';
        $this->joins = [];
        $this->where = [];
        $this->params = [];
        $this->orderBy = '';
        return $this;
    }

    /**
     * Retorna o SQL para debug/logging
     *
     * @return string Representação em string
     */
    public function __toString(): string
    {
        return $this->getSql();
    }
}
