<?php
return [
    'async_job_processor_url' => getenv('ASYNC_JOB_PROCESSOR_URL') ?: 'http://localhost/api/v1/utils/process-async-jobs.php',
    'process_batch_size' => (int)(getenv('JOB_PROCESS_BATCH_SIZE') ?: 20),
    'max_pending_jobs' => (int)(getenv('JOB_QUEUE_MAX_PENDING') ?: 5000),
    'webhook_queue_dir' => getenv('WEBHOOK_QUEUE_DIR') ?: __DIR__ . '/../queue/webhooks',
];
