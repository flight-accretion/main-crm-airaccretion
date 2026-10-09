<?php

return [

    'enabled' =>
        env(
            'CRM_BACKUP_ENABLED',
            false
        ),

    'time' =>
        env(
            'CRM_BACKUP_TIME',
            '02:30'
        ),

    'timezone' =>
        env(
            'CRM_BACKUP_TIMEZONE',
            'Asia/Kolkata'
        ),

    'temp_dir' =>
        env('CRM_BACKUP_TEMP_DIR')
        ?: storage_path(
            'app/private/crm-backups'
        ),

    'include_env' =>
        env(
            'CRM_BACKUP_INCLUDE_ENV',
            false
        ),

    'delete_local_after_upload' =>
        env(
            'CRM_BACKUP_DELETE_LOCAL_AFTER_UPLOAD',
            true
        ),

    /*
     * Optional executable override.
     *
     * Normally leave these blank and let
     * BackupCrm auto-detect them.
     */
    'pg_dump_binary' =>
        env(
            'CRM_BACKUP_PG_DUMP_BINARY'
        ),

    'mysqldump_binary' =>
        env(
            'CRM_BACKUP_MYSQLDUMP_BINARY'
        ),

];
