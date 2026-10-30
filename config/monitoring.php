<?php
return [
    'enabled' => filter_var(getenv('MONITORING_ENABLED') ?: 'true', FILTER_VALIDATE_BOOLEAN),
    'health_check_secret' => getenv('HEALTH_CHECK_SECRET') ?: '',
    'require_secret' => getenv('ENVIRONMENT') !== 'development',
    'job_metrics_limit' => (int)(getenv('JOB_METRICS_LIMIT') ?: 10),
    'async_job_processor_url' => getenv('ASYNC_JOB_PROCESSOR_URL') ?: 'http://localhost/api/v1/utils/process-async-jobs.php',
];
