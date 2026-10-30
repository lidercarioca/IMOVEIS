/**
 * Testes para normalizeApiResponse()
 * Executar em browser console ou via teste automatizado
 *
 * Uso em browser console:
 * 1. Abrir DevTools (F12)
 * 2. Carregar api-helper.js (já carregado se página usa)
 * 3. Copiar e colar este arquivo no console
 * 4. Executar: runApiNormalizerTests()
 */

function runApiNormalizerTests() {
    console.log('🧪 Iniciando testes de normalização da API...\n');

    let passed = 0;
    let failed = 0;

    function test(name, fn) {
        try {
            fn();
            console.log(`✅ ${name}`);
            passed++;
        } catch (error) {
            console.error(`❌ ${name}`);
            console.error(`   Erro: ${error.message}`);
            failed++;
        }
    }

    function assert(condition, message) {
        if (!condition) {
            throw new Error(message);
        }
    }

    function assertEqual(actual, expected, message) {
        if (JSON.stringify(actual) !== JSON.stringify(expected)) {
            throw new Error(
                `${message}\nEsperado: ${JSON.stringify(expected)}\nRecebido: ${JSON.stringify(actual)}`
            );
        }
    }

    // ======================================
    // Testes: isLegacyResponse()
    // ======================================

    console.log('📌 isLegacyResponse()\n');

    test('Array puro é legacy', () => {
        assert(isLegacyResponse([]), 'Array vazio deve ser legacy');
        assert(isLegacyResponse([1, 2, 3]), 'Array com items deve ser legacy');
    });

    test('Object {success, data} é legacy', () => {
        assert(isLegacyResponse({ success: true, data: [] }), '{success, data} deve ser legacy');
        assert(
            isLegacyResponse({ success: false, data: null }),
            '{success: false, data} deve ser legacy'
        );
    });

    test('Object {error} é legacy', () => {
        assert(isLegacyResponse({ error: 'Falha' }), '{error} deve ser legacy');
    });

    test('ApiResponse novo NÃO é legacy', () => {
        assert(
            !isLegacyResponse({
                success: true,
                message: null,
                data: [],
                errors: [],
                meta: {},
            }),
            'ApiResponse novo não deve ser legacy'
        );
    });

    test('Primitivos não são legacy', () => {
        assert(!isLegacyResponse('string'), 'String não deve ser legacy');
        assert(!isLegacyResponse(123), 'Number não deve ser legacy');
        assert(!isLegacyResponse(null), 'Null não deve ser legacy');
    });

    // ======================================
    // Testes: isStandardApiResponse()
    // ======================================

    console.log('\n📌 isStandardApiResponse()\n');

    test('ApiResponse novo é padrão', () => {
        assert(
            isStandardApiResponse({
                success: true,
                message: null,
                data: [],
                errors: [],
                meta: {},
            }),
            'ApiResponse completo deve ser padrão'
        );
    });

    test('Objeto com campos parciais não é padrão', () => {
        assert(
            !isStandardApiResponse({ success: true, data: [] }),
            'Objeto sem message/errors/meta não deve ser padrão'
        );
    });

    test('Array não é padrão', () => {
        assert(!isStandardApiResponse([]), 'Array não deve ser padrão');
    });

    test('Primitivos não são padrão', () => {
        assert(!isStandardApiResponse('string'), 'String não deve ser padrão');
    });

    // ======================================
    // Testes: normalizeApiResponse()
    // ======================================

    console.log('\n📌 normalizeApiResponse()\n');

    test('Array puro → estrutura normalizada', () => {
        const result = normalizeApiResponse([1, 2, 3], 200);
        assert(result.success === true, 'success deve ser true');
        assert(result.message === null, 'message deve ser null');
        assert(JSON.stringify(result.data) === JSON.stringify([1, 2, 3]), 'data deve manter array');
        assert(Array.isArray(result.errors), 'errors deve ser array');
        assert(result.meta.legacy === true, 'meta.legacy deve ser true');
        assert(result.meta.originalFormat === 'array', 'meta.originalFormat deve ser "array"');
    });

    test('{success: true, data: [...]} → estrutura normalizada', () => {
        const input = { success: true, data: ['item1', 'item2'] };
        const result = normalizeApiResponse(input, 200);
        assert(result.success === true, 'success deve ser true');
        assert(result.data[0] === 'item1', 'data deve preservar items');
        assert(result.meta.legacy === true, 'deve marcar como legacy');
        assert(
            result.meta.originalFormat === 'success-data',
            'originalFormat deve ser "success-data"'
        );
    });

    test('{success: false, data: null} → estrutura normalizada com erro', () => {
        const input = { success: false, data: null, error: 'Erro' };
        const result = normalizeApiResponse(input, 200);
        assert(result.success === false, 'success deve ser false');
        assert(result.message === 'Erro', 'message deve capturar error');
        assert(result.data === null, 'data deve ser null');
    });

    test('{error: "Falha"} → estrutura normalizada', () => {
        const result = normalizeApiResponse({ error: 'Falha ao processar' }, 400);
        assert(result.success === false, 'success deve ser false');
        assert(result.message === 'Falha ao processar', 'message deve capturar error');
        assert(result.data === null, 'data deve ser null');
        assert(result.meta.originalFormat === 'error', 'originalFormat deve ser "error"');
    });

    test('Objeto plano → envolvido em estrutura', () => {
        const input = { status: 'ok', count: 5 };
        const result = normalizeApiResponse(input, 200);
        assert(result.success === true, 'success deve ser true (HTTP 200)');
        assert(
            JSON.stringify(result.data) === JSON.stringify(input),
            'data deve ser objeto original'
        );
        assert(
            result.meta.originalFormat === 'plain-object',
            'originalFormat deve ser "plain-object"'
        );
    });

    test('ApiResponse novo → retornado como está (passthrough)', () => {
        const input = {
            success: true,
            message: null,
            data: ['item'],
            errors: [],
            meta: { custom: 'value' },
        };
        const result = normalizeApiResponse(input, 200);
        assertEqual(result, input, 'Deve retornar objeto idêntico');
    });

    test('String primitiva → envolvida', () => {
        const result = normalizeApiResponse('resposta simples', 200);
        assert(result.success === true, 'success deve ser true');
        assert(result.data === 'resposta simples', 'data deve manter string');
        assert(result.meta.originalFormat === 'primitive', 'originalFormat deve ser "primitive"');
    });

    test('Number primitivo → envolvido', () => {
        const result = normalizeApiResponse(42, 200);
        assert(result.success === true, 'success deve ser true');
        assert(result.data === 42, 'data deve manter number');
        assert(result.meta.originalFormat === 'primitive', 'originalFormat deve ser "primitive"');
    });

    test('Null primitivo → envolvido', () => {
        const result = normalizeApiResponse(null, 200);
        assert(result.success === true, 'success deve ser true (HTTP 200)');
        assert(result.data === null, 'data deve ser null');
        assert(result.meta.originalFormat === 'primitive', 'originalFormat deve ser "primitive"');
    });

    test('HTTP status afeta sucesso de primitivos', () => {
        const resultError = normalizeApiResponse(null, 500);
        assert(resultError.success === false, 'success deve ser false para HTTP 500');

        const resultSuccess = normalizeApiResponse(null, 200);
        assert(resultSuccess.success === true, 'success deve ser true para HTTP 200');
    });

    // ======================================
    // Testes: Estrutura sempre presente
    // ======================================

    console.log('\n📌 Validação de estrutura\n');

    test('Resultado sempre tem todos os campos', () => {
        const testesCasos = [
            [],
            { success: true, data: [] },
            { error: 'Falha' },
            'string',
            42,
            null,
            { custom: 'object' },
        ];

        testesCasos.forEach((caso) => {
            const result = normalizeApiResponse(caso, 200);
            assert(
                result.success !== undefined,
                `success deve existir para ${JSON.stringify(caso)}`
            );
            assert(
                result.message !== undefined,
                `message deve existir para ${JSON.stringify(caso)}`
            );
            assert(result.data !== undefined, `data deve existir para ${JSON.stringify(caso)}`);
            assert(
                Array.isArray(result.errors),
                `errors deve ser array para ${JSON.stringify(caso)}`
            );
            assert(
                typeof result.meta === 'object',
                `meta deve ser object para ${JSON.stringify(caso)}`
            );
        });
    });

    test('Meta sempre tem legacy e originalFormat', () => {
        const result = normalizeApiResponse([1, 2, 3], 200);
        assert(result.meta.legacy !== undefined, 'meta.legacy deve existir');
        assert(result.meta.originalFormat !== undefined, 'meta.originalFormat deve existir');
    });

    // ======================================
    // Resumo
    // ======================================

    console.log(`\n${'='.repeat(50)}`);
    console.log(`📊 Testes Concluídos`);
    console.log(`${'='.repeat(50)}`);
    console.log(`✅ Passou: ${passed}`);
    console.log(`❌ Falhou: ${failed}`);
    console.log(`📈 Total: ${passed + failed}`);
    console.log(`${'='.repeat(50)}\n`);

    if (failed === 0) {
        console.log('🎉 Todos os testes passaram!');
    } else {
        console.log(`⚠️  ${failed} teste(s) falharam.`);
    }

    return { passed, failed, total: passed + failed };
}

// Exporta para uso global
window.runApiNormalizerTests = runApiNormalizerTests;

// Auto-executa se em contexto de teste
if (typeof process !== 'undefined' && process.env && process.env.NODE_ENV === 'test') {
    runApiNormalizerTests();
}
