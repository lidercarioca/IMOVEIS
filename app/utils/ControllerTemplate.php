<?php

namespace App\Utils;

/**
 * ControllerTemplate - Template gerador para novos Controllers
 * 
 * Método de conveniência para padronizar criação de Controllers
 * 
 * Exemplo de uso:
 * $template = ControllerTemplate::generate('Property', [
 *     'getById' => ['GET', 'Obter um imóvel por ID'],
 *     'list' => ['GET', 'Listar todos os imóveis'],
 *     'create' => ['POST', 'Criar novo imóvel'],
 *     'update' => ['PUT', 'Atualizar imóvel'],
 *     'delete' => ['DELETE', 'Deletar imóvel']
 * ]);
 * 
 * echo $template; // Imprime código do controller
 */
class ControllerTemplate {
    
    /**
     * Gerar template de Controller
     * 
     * @param string $name Nome do controller (ex: Property)
     * @param array $methods Métodos desejados [method => [HTTP_METHOD, descrição]]
     * @return string Código PHP gerado
     */
    public static function generate(string $name, array $methods): string {
        $template = <<<'PHP'
<?php

namespace App\Controllers;

use App\Utils\ResponseFormatter;
use App\Utils\Validator;
use App\Utils\Logger;
use App\Services\{SERVICE_NAME};

/**
 * {CLASS_NAME}Controller - Controlador para {LOWER_NAME}
 * 
 * Endpoints:
{METHODS_DOCS}
 */
class {CLASS_NAME}Controller extends BaseController {
    
    private {SERVICE_NAME} $service;
    
    public function __construct({SERVICE_NAME} $service) {
        parent::__construct();
        $this->service = $service;
    }

{METHODS_CODE}
}
?>
PHP;

        // Substituir placeholders
        $serviceName = $name . 'Service';
        $className = $name;
        $lowerName = strtolower($name);
        
        // Gerar documentação dos métodos
        $methodDocs = '';
        foreach ($methods as $method => $info) {
            $httpMethod = $info[0];
            $description = $info[1];
            $methodDocs .= "\n * - {$httpMethod} /{$lowerName} - {$description}";
        }
        
        // Gerar código dos métodos
        $methodsCode = '';
        foreach ($methods as $method => $info) {
            $httpMethod = $info[0];
            $description = $info[1];
            $methodsCode .= self::generateMethod($method, $httpMethod, $description);
        }
        
        // Substituir placeholders
        $template = str_replace('{SERVICE_NAME}', $serviceName, $template);
        $template = str_replace('{CLASS_NAME}', $className, $template);
        $template = str_replace('{LOWER_NAME}', $lowerName, $template);
        $template = str_replace('{METHODS_DOCS}', $methodDocs, $template);
        $template = str_replace('{METHODS_CODE}', $methodsCode, $template);
        
        return $template;
    }
    
    /**
     * Gerar método individual
     */
    private static function generateMethod(string $name, string $httpMethod, string $description): string {
        $template = <<<'PHP'
    
    /**
     * {DESCRIPTION}
     * {HTTP_METHOD}
     */
    public function {METHOD_NAME}(): string {
        if (!$this->requireAuth()) {
            return ResponseFormatter::unauthorized();
        }
        
        try {
            // TODO: Implementar validação e lógica
            
            Logger::audit('{ACTION}', $this->userId, 'resource', ['details' => 'N/A']);
            
            return ResponseFormatter::success([], '{DESCRIPTION}');
            
        } catch (\InvalidArgumentException $e) {
            return ResponseFormatter::unprocessable($e->getMessage());
        } catch (\Exception $e) {
            Logger::error('Erro em {METHOD_NAME}', ['error' => $e->getMessage()]);
            return ResponseFormatter::serverError();
        }
    }
PHP;

        $template = str_replace('{DESCRIPTION}', $description, $template);
        $template = str_replace('{HTTP_METHOD}', $httpMethod, $template);
        $template = str_replace('{METHOD_NAME}', $name, $template);
        $template = str_replace('{ACTION}', $name, $template);
        
        return $template;
    }
    
    /**
     * Listar métodos CRUD comuns
     */
    public static function crudMethods(string $name): array {
        $lower = strtolower($name);
        
        return [
            'list' => ['GET', "Listar todos os {$lower}"],
            'getById' => ['GET', "Obter um {$lower} por ID"],
            'create' => ['POST', "Criar novo {$lower}"],
            'update' => ['PUT', "Atualizar {$lower}"],
            'delete' => ['DELETE', "Deletar {$lower}"]
        ];
    }
    
    /**
     * Gerar template CRUD padrão
     */
    public static function generateCrud(string $name): string {
        return self::generate($name, self::crudMethods($name));
    }
}
