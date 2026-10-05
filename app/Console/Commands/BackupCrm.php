<?php

namespace App\Console\Commands;

use App\Services\Backup\GoogleDriveBackupUploader;
use Illuminate\Console\Command;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;
use Symfony\Component\Process\ExecutableFinder;

class BackupCrm extends Command
{
    protected $signature =
        'crm:backup-daily';


    protected $description =
        'Back up the live CRM database and code to Google Drive';


    private $lockHandle;


    public function handle(
        GoogleDriveBackupUploader $uploader
    ): int {

        if (
            !config(
                'crm_backup.enabled',
                false
            )
        ) {
            $this->warn(
                'CRM backup is disabled.'
            );

            return self::SUCCESS;
        }


        if (!$this->acquireLock()) {

            $this->warn(
                'Another CRM backup is already running.'
            );

            return self::SUCCESS;
        }


        $timestamp =
            now(
                config(
                    'crm_backup.timezone',
                    'Asia/Kolkata'
                )
            )->format(
                'Y-m-d_H-i-s'
            );


        $tempRoot =
            rtrim(
                (string)
                config(
                    'crm_backup.temp_dir'
                ),
                '/'
            );


        $workDir =
            $tempRoot
            . '/'
            . $timestamp;


        $dbFile =
            $workDir
            . '/accretion_crm_database_'
            . $timestamp
            . '.sql';


        $codeFile =
            $workDir
            . '/accretion_crm_code_'
            . $timestamp
            . '.zip';


        $completed =
            false;


        try {

            $this->makeDirectory(
                $workDir
            );


            $this->info(
                'CRM backup started: '
                . $timestamp
            );


            /*
             * DATABASE
             */

            $this->info(
                'Creating database dump...'
            );


            $this->createDatabaseDump(
                $dbFile
            );


            $this->assertValidFile(
                $dbFile,
                'Database SQL'
            );


            $this->info(
                'Database dump created: '
                . $this->humanSize(
                    filesize($dbFile)
                )
            );


            /*
             * CODE
             */

            $this->info(
                'Creating live CRM code ZIP...'
            );


            $this->createCodeZip(
                $codeFile
            );


            $this->assertValidFile(
                $codeFile,
                'CRM code ZIP'
            );


            $this->info(
                'CRM code ZIP created: '
                . $this->humanSize(
                    filesize($codeFile)
                )
            );


            $dbFolderId =
                trim(
                    (string)
                    config(
                        'services.google_drive_backup.db_folder_id'
                    )
                );


            $codeFolderId =
                trim(
                    (string)
                    config(
                        'services.google_drive_backup.code_folder_id'
                    )
                );


            if ($dbFolderId === '') {
                throw new RuntimeException(
                    'GOOGLE_DRIVE_BACKUP_DB_FOLDER_ID is missing.'
                );
            }


            if ($codeFolderId === '') {
                throw new RuntimeException(
                    'GOOGLE_DRIVE_BACKUP_CODE_FOLDER_ID is missing.'
                );
            }


            /*
             * DATABASE → GOOGLE DRIVE
             */

            $this->info(
                'Uploading database backup to Google Drive...'
            );


            $remoteDb =
                $uploader
                    ->uploadAndVerify(
                        $dbFile,
                        basename($dbFile),
                        $dbFolderId,
                        'application/sql'
                    );


            $this->info(
                'Database upload verified. File ID: '
                . $remoteDb['id']
            );


            /*
             * CODE → GOOGLE DRIVE
             */

            $this->info(
                'Uploading code backup to Google Drive...'
            );


            $remoteCode =
                $uploader
                    ->uploadAndVerify(
                        $codeFile,
                        basename($codeFile),
                        $codeFolderId,
                        'application/zip'
                    );


            $this->info(
                'Code upload verified. File ID: '
                . $remoteCode['id']
            );


            /*
             * Only now is cleanup permitted.
             */

            $completed =
                true;


            $this->info(
                'CRM BACKUP SUCCESSFUL.'
            );


            return self::SUCCESS;

        } catch (\Throwable $e) {

            report(
                $e
            );


            $this->error(
                'CRM BACKUP FAILED: '
                . $e->getMessage()
            );


            $this->error(
                'Temporary backup retained at: '
                . $workDir
            );


            return self::FAILURE;

        } finally {

            if (
                $completed
                && is_dir($workDir)
            ) {

                $this->deleteDirectory(
                    $workDir
                );


                $this->info(
                    'Temporary local backup deleted.'
                );
            }


            $this->releaseLock();
        }
    }


    private function createDatabaseDump(
        string $target
    ): void {

        $connectionName =
            config(
                'database.default'
            );


        $connection =
            config(
                'database.connections.'
                . $connectionName,
                []
            );


        $driver =
            $connection['driver']
            ?? $connectionName;


        $host =
            (string)
            ($connection['host']
            ?? '127.0.0.1');


        $port =
            (string)
            ($connection['port']
            ?? (
                $driver === 'pgsql'
                    ? '5432'
                    : '3306'
            ));


        $database =
            (string)
            ($connection['database']
            ?? '');


        $username =
            (string)
            ($connection['username']
            ?? '');


        $password =
            (string)
            ($connection['password']
            ?? '');


        if (
            $database === ''
            || $username === ''
        ) {
            throw new RuntimeException(
                'Database configuration is incomplete.'
            );
        }


        if (
            in_array(
                $driver,
                [
                    'mysql',
                    'mariadb',
                ],
                true
            )
        ) {

           $mysqlDumpBinary =
    trim(
        (string)
        config(
            'crm_backup.mysqldump_binary',
            'mysqldump'
        )
    );


if ($mysqlDumpBinary === '') {
    $mysqlDumpBinary =
        'mysqldump';
}


$process =
    new Process(
        [
            $mysqlDumpBinary,
            '--host=' . $host,
            '--port=' . $port,
            '--user=' . $username,
            '--single-transaction',
            '--quick',
            '--routines',
            '--triggers',
            '--events',
            '--hex-blob',
            '--no-tablespaces',
            '--default-character-set=utf8mb4',
            '--result-file=' . $target,
            $database,
        ],
        base_path(),
        [
            'MYSQL_PWD' =>
                $password,
        ],
        null,
        3600
    );

        } elseif (
            in_array(
                $driver,
                [
                    'pgsql',
                    'postgres',
                    'postgresql',
                ],
                true
            )
        ) {

        $pgDumpBinary =
            $this->resolvePgDumpBinary();


        $this->line(
            'Using pg_dump: '
            . $pgDumpBinary
        );


        if ($pgDumpBinary === '') {
            $pgDumpBinary =
                'pg_dump';
        }


        $process =
            new Process(
                [
                    $pgDumpBinary,
                    '--host=' . $host,
                    '--port=' . $port,
                    '--username=' . $username,
                    '--format=plain',
                    '--no-owner',
                    '--no-privileges',
                    '--file=' . $target,
                    $database,
                ],
                base_path(),
                [
                    'PGPASSWORD' =>
                        $password,
                ],
                null,
                3600
            );

        } else {

            throw new RuntimeException(
                'Unsupported database driver: '
                . $driver
            );
        }


        $process->run();


        if (
            !$process->isSuccessful()
        ) {
            throw new RuntimeException(
                'Database dump failed: '
                . trim(
                    $process->getErrorOutput()
                    ?: $process->getOutput()
                )
            );
        }
    }


    private function createCodeZip(
        string $target
    ): void {

        $root =
            realpath(
                base_path()
            );


        if (!$root) {
            throw new RuntimeException(
                'Unable to resolve CRM application path.'
            );
        }


        $zip =
            new ZipArchive();


        $result =
            $zip->open(
                $target,
                ZipArchive::CREATE
                | ZipArchive::OVERWRITE
            );


        if ($result !== true) {
            throw new RuntimeException(
                'Unable to create CRM code ZIP.'
            );
        }


        $includeEnv =
            filter_var(
                config(
                    'crm_backup.include_env',
                    false
                ),
                FILTER_VALIDATE_BOOLEAN
            );


        /*
         * Code backup only.
         *
         * Persistent customer/storage files should be
         * backed up separately if required.
         */
        $excludedPrefixes = [

            '.git/',

            'node_modules/',

            'storage/app/',

            'storage/framework/',

            'storage/logs/',

            'bootstrap/cache/',
        ];


        $iterator =
            new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(
                    $root,
                    \FilesystemIterator::SKIP_DOTS
                ),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );


        foreach (
            $iterator as $file
        ) {

            if (
                !$file->isFile()
                || $file->isLink()
            ) {
                continue;
            }


            $fullPath =
                $file->getPathname();


            $relative =
                ltrim(
                    str_replace(
                        '\\',
                        '/',
                        substr(
                            $fullPath,
                            strlen($root)
                        )
                    ),
                    '/'
                );


            if ($relative === '') {
                continue;
            }


            if (
                !$includeEnv
                && $relative === '.env'
            ) {
                continue;
            }


            $excluded =
                false;


            foreach (
                $excludedPrefixes as $prefix
            ) {

                if (
                    str_starts_with(
                        $relative,
                        $prefix
                    )
                ) {

                    $excluded =
                        true;

                    break;
                }
            }


            if ($excluded) {
                continue;
            }


            if (
                !$zip->addFile(
                    $fullPath,
                    $relative
                )
            ) {

                $zip->close();

                throw new RuntimeException(
                    'Unable to add file to ZIP: '
                    . $relative
                );
            }
        }


        if (!$zip->close()) {
            throw new RuntimeException(
                'Unable to finalize CRM code ZIP.'
            );
        }
    }


    private function assertValidFile(
        string $path,
        string $label
    ): void {

        clearstatcache(
            true,
            $path
        );


        if (
            !is_file($path)
            || filesize($path) <= 0
        ) {

            throw new RuntimeException(
                $label
                . ' was not created or is empty.'
            );
        }


        @chmod(
            $path,
            0600
        );
    }


    private function makeDirectory(
        string $path
    ): void {

        if (
            !is_dir($path)
            && !mkdir(
                $path,
                0700,
                true
            )
            && !is_dir($path)
        ) {

            throw new RuntimeException(
                'Unable to create backup directory: '
                . $path
            );
        }


        @chmod(
            $path,
            0700
        );
    }


    private function deleteDirectory(
        string $path
    ): void {

        if (!is_dir($path)) {
            return;
        }


        $iterator =
            new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(
                    $path,
                    \FilesystemIterator::SKIP_DOTS
                ),
                \RecursiveIteratorIterator::CHILD_FIRST
            );


        foreach (
            $iterator as $item
        ) {

            if (
                $item->isDir()
                && !$item->isLink()
            ) {

                @rmdir(
                    $item->getPathname()
                );

            } else {

                @unlink(
                    $item->getPathname()
                );
            }
        }


        @rmdir(
            $path
        );
    }


    private function humanSize(
        int $bytes
    ): string {

        $units = [
            'B',
            'KB',
            'MB',
            'GB',
            'TB',
        ];


        $value =
            max(
                0,
                $bytes
            );


        $index =
            0;


        while (
            $value >= 1024
            && $index
                < count($units) - 1
        ) {

            $value /=
                1024;

            $index++;
        }


        return
            number_format(
                $value,
                2
            )
            . ' '
            . $units[$index];
    }


    private function acquireLock(): bool
    {
        $lockFile =
            storage_path(
                'framework/crm-backup.lock'
            );


        $this->lockHandle =
            fopen(
                $lockFile,
                'c'
            );


        if (!$this->lockHandle) {
            throw new RuntimeException(
                'Unable to create CRM backup lock.'
            );
        }


        return
            flock(
                $this->lockHandle,
                LOCK_EX
                | LOCK_NB
            );
    }


    private function releaseLock(): void
    {
        if (
            is_resource(
                $this->lockHandle
            )
        ) {

            flock(
                $this->lockHandle,
                LOCK_UN
            );


            fclose(
                $this->lockHandle
            );
        }
    }


    private function resolvePgDumpBinary(): string
{
    /*
     * --------------------------------------------------
     * 1. Explicit .env override
     * --------------------------------------------------
     */

    $configured =
        trim(
            (string)
            config(
                'crm_backup.pg_dump_binary',
                ''
            )
        );


    if ($configured !== '') {

        /*
         * Protect against someone putting extra
         * quotation marks into the value.
         */
        $configured =
            trim(
                $configured,
                "\"'"
            );


        if (
            is_file(
                $configured
            )
        ) {
            return
                $configured;
        }


        /*
         * It may simply be an executable name
         * such as "pg_dump".
         */
        $finder =
            new ExecutableFinder();


        $found =
            $finder
                ->find(
                    $configured
                );


        if ($found) {
            return
                $found;
        }
    }


    /*
     * --------------------------------------------------
     * 2. Search normal operating-system PATH
     * --------------------------------------------------
     */

    $finder =
        new ExecutableFinder();


    $found =
        $finder
            ->find(
                'pg_dump'
            );


    if ($found) {
        return
            $found;
    }


    /*
     * --------------------------------------------------
     * 3. Windows common PostgreSQL locations
     * --------------------------------------------------
     */

    if (
        PHP_OS_FAMILY === 'Windows'
    ) {

        $patterns = [

            'C:/Program Files/PostgreSQL/*/bin/pg_dump.exe',

            'C:/Program Files (x86)/PostgreSQL/*/bin/pg_dump.exe',

            'C:/xampp/pgsql/bin/pg_dump.exe',

            'C:/xampp/postgresql/bin/pg_dump.exe',
        ];


        $matches = [];


        foreach (
            $patterns as $pattern
        ) {

            $foundFiles =
                glob(
                    $pattern
                );


            if (
                is_array(
                    $foundFiles
                )
            ) {

                $matches =
                    array_merge(
                        $matches,
                        $foundFiles
                    );
            }
        }


        /*
         * Prefer the newest installed PostgreSQL
         * version if several versions exist.
         */
        usort(
            $matches,
            static function (
                string $a,
                string $b
            ): int {

                return
                    strnatcasecmp(
                        $b,
                        $a
                    );
            }
        );


        foreach (
            $matches as $match
        ) {

            if (
                is_file(
                    $match
                )
            ) {
                return
                    $match;
            }
        }
    }


    /*
     * --------------------------------------------------
     * 4. Linux / Docker common paths
     * --------------------------------------------------
     */

    $linuxPaths = [

        '/usr/bin/pg_dump',

        '/usr/local/bin/pg_dump',
    ];


    foreach (
        $linuxPaths as $path
    ) {

        if (
            is_file(
                $path
            )
        ) {
            return
                $path;
        }
    }


    throw new RuntimeException(
        'pg_dump executable was not found. '
        . 'Install PostgreSQL command-line tools '
        . 'or set CRM_BACKUP_PG_DUMP_BINARY '
        . 'to the exact pg_dump executable path.'
    );
}

private function resolveMysqlDumpBinary(): string
{
    $configured =
        trim(
            (string)
            config(
                'crm_backup.mysqldump_binary',
                ''
            )
        );


    if ($configured !== '') {

        $configured =
            trim(
                $configured,
                "\"'"
            );


        if (
            is_file(
                $configured
            )
        ) {
            return
                $configured;
        }


        $finder =
            new ExecutableFinder();


        $found =
            $finder
                ->find(
                    $configured
                );


        if ($found) {
            return
                $found;
        }
    }


    $finder =
        new ExecutableFinder();


    $found =
        $finder
            ->find(
                'mysqldump'
            );


    if ($found) {
        return
            $found;
    }


    $paths = [

        'C:/xampp/mysql/bin/mysqldump.exe',

        '/usr/bin/mysqldump',

        '/usr/local/bin/mysqldump',
    ];


    foreach (
        $paths as $path
    ) {

        if (
            is_file(
                $path
            )
        ) {
            return
                $path;
        }
    }


    throw new RuntimeException(
        'mysqldump executable was not found.'
    );
}

}