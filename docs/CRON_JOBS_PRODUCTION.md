# Configuração de Cron para Produção

Este documento reúne os scripts que devem ser agendados em produção para manter o sistema operando corretamente. Ele explica cada arquivo, sua função e exemplos de agendamento.

## 1. Scripts recomendados para cron

### 1.1 `cron/followup_cron.php`

- Finalidade: processa follow-ups automáticos e agenda novos follow-ups para cada tenant.
- Uso CLI: `php /caminho/para/htdocs/cron/followup_cron.php`
- Recomendação: executar regularmente, por exemplo a cada 5-15 minutos, dependendo do volume de follow-ups.
- Logs: `logs/followup_cron.log`

### 1.2 `bloquear_tenants_expirados.php`

- Finalidade: bloqueia tenants cujo plano já expirou.
- Uso CLI: `php /caminho/para/htdocs/bloquear_tenants_expirados.php`
- Recomendação: executar a cada 1 hora.
- Observação: este script pode ser executado via CLI e tem HTTP desabilitado por padrão.

### 1.3 `notificar_expiracao_tenants.php`

- Finalidade: notifica tenants que expirarão em 3 dias.
- Uso CLI: `php /caminho/para/htdocs/notificar_expiracao_tenants.php`
- Recomendação: executar uma vez por dia.
- Observação: este script só deve rodar via cron (HTTP desabilitado).

### 1.4 `scripts/cron/generateInactivityAlerts.php`

- Finalidade: gera alertas de leads inativos para tenants ativos.
- Uso CLI: `php /caminho/para/htdocs/scripts/cron/generateInactivityAlerts.php`
- Recomendação: executar uma vez por dia.
- Observação: depende do banco de dados e deve ser usado em ambientes com tenants ativos.

### 1.5 `api/v1/alertas/processAlerts.php`

- Finalidade: processa alertas inteligentes por meio do serviço interno de alertas.
- Uso CLI: `php /caminho/para/htdocs/api/v1/alertas/processAlerts.php api_key=SUACHAVE` ou via requisição HTTP com `ALERTS_CRON_KEY` definido.
- Recomendação: executar uma vez por dia, em horário de baixa carga.
- Observação: em produção, use a variável de ambiente `ALERTS_CRON_KEY` para autorizar a execução sem sessão.

### 1.6 `google/poll_unread.php` (opcional)

- Finalidade: faz polling de emails Gmail não lidos e cria notificações no painel.
- Uso CLI: `php /caminho/para/htdocs/google/poll_unread.php`
- Recomendação: executar a cada 3-5 minutos se não estiver usando Google Pub/Sub.
- Observação: utilize apenas se a integração de Gmail não estiver configurada para receber notificações via Pub/Sub.

## 2. Exemplos de agendamento (Linux)

Ajuste os caminhos conforme o ambiente de produção.

```cron
# Followups automáticos
*/10 * * * * /usr/bin/php /var/www/html/cron/followup_cron.php >> /var/log/app_followup_cron.log 2>&1

# Bloquear tenants expirados
author 0 * * * * /usr/bin/php /var/www/html/bloquear_tenants_expirados.php >> /var/log/app_block_expired_tenants.log 2>&1

# Notificar tenants próximos da expiração
0 9 * * * /usr/bin/php /var/www/html/notificar_expiracao_tenants.php >> /var/log/app_notify_expiration.log 2>&1

# Gerar alertas de leads inativos
0 3 * * * /usr/bin/php /var/www/html/scripts/cron/generateInactivityAlerts.php >> /var/log/app_inactivity_alerts.log 2>&1

# Processar alertas inteligentes
0 4 * * * /usr/bin/php /var/www/html/api/v1/alertas/processAlerts.php --api_key="SUA_CHAVE" >> /var/log/app_process_alerts.log 2>&1

# Gmail polling (opcional)
*/5 * * * * /usr/bin/php /var/www/html/google/poll_unread.php >> /var/log/app_gmail_poll.log 2>&1
```

> Substitua `/var/www/html` pelo diretório raiz do seu deploy.
> Substitua `/usr/bin/php` pelo caminho correto do PHP no servidor.

## 3. Notas para implantação

- Verifique se as permissões de arquivo e pasta permitem escrita nos logs e no diretório `logs/`.
- Confirme as variáveis de ambiente usadas pelo sistema, especialmente `ALERTS_CRON_KEY`, `TZ`, e configurações de banco de dados.
- Se usar Windows em vez de Linux, crie tarefas no Task Scheduler equivalentes aos comandos PHP acima.
- Não agende `report_cron.php` em produção; ele é apenas um relatório de execução.

## 4. Ordem de prioridade sugerida

1. `cron/followup_cron.php`
2. `bloquear_tenants_expirados.php`
3. `notificar_expiracao_tenants.php`
4. `scripts/cron/generateInactivityAlerts.php`
5. `api/v1/alertas/processAlerts.php`

## 5. Validação após deploy

- Teste cada script manualmente antes de ativar o cron.
- Confirme que os logs são gravados corretamente.
- Monitore as saídas de erro e ajuste os horários para evitar concorrência em horários de pico.
