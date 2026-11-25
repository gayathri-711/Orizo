<?php
declare(strict_types=1);

return [
    'private_root' => dirname(__DIR__) . '/storage/private',
    'public_cms_root' => dirname(__DIR__) . '/public/uploads/cms',
    'max_inquiry_bytes' => ((int) env_val('MAX_INQUIRY_UPLOAD_MB', 10)) * 1024 * 1024,
    'max_project_bytes' => ((int) env_val('MAX_PROJECT_UPLOAD_MB', 25)) * 1024 * 1024,
    'max_cms_bytes' => ((int) env_val('MAX_CMS_UPLOAD_MB', 5)) * 1024 * 1024,
    'allowed_private_mimes' => [
        'pdf'  => ['application/pdf'],
        'png'  => ['image/png'],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'webp' => ['image/webp'],
        'doc'  => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'xls'  => ['application/vnd.ms-excel'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        'ppt'  => ['application/vnd.ms-powerpoint'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation'],
        'txt'  => ['text/plain'],
        'csv'  => ['text/csv', 'text/plain', 'application/csv'],
        'zip'  => ['application/zip', 'application/x-zip-compressed'],
    ],
    'allowed_cms_mimes' => [
        'png'  => ['image/png'],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'webp' => ['image/webp'],
    ],
];
