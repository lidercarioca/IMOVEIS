Directory para armazenar schemas de validação de feeds (XSD / JSON Schema).

Coloque aqui os arquivos de schema fornecidos pelos portais, usando o nome do portal como prefixo.
Exemplos:
- `schemas/olx.xsd`  — XSD para validação do feed XML da OLX
- `schemas/zap.xsd`  — XSD para validação do feed XML do ZAP

Como usar o script de validação:

CLI:
```
php scripts/validate_feed.php portal=olx format=xml limit=500
```

Web:
```
/scripts/validate_feed.php?portal=olx&format=xml&limit=500
```

Se existir um arquivo XSD correspondente (`schemas/{portal}.xsd`) e o formato for `xml`, o script tentará validar o XML gerado contra o XSD.
Caso contrário, o script executará validações básicas (campos obrigatórios, price numérico, URLs absolutas, imagens obrigatórias quando configurado).

Coloque os schemas neste diretório e rode o script para verificar o feed antes de tentar o envio ao portal.
