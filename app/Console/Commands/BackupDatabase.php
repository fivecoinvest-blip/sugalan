<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class BackupDatabase extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'backup:database 
                            {--type=full : Backup type (full, schema, data)}
                            {--compress : Compress backup file}
                            {--retention=30 : Days to retain backups}';

    /**
     * The console command description.
     */
    protected $description = 'Create a database backup';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🗄️  Starting database backup...');
        
        $type = $this->option('type');
        $compress = $this->option('compress');
        $retention = (int) $this->option('retention');

        try {
            // Create backup
            $filename = $this->createBackup($type, $compress);
            
            if ($filename) {
                $this->info("✓ Backup created: {$filename}");
                
                // Clean old backups
                $this->cleanOldBackups($retention);
                
                $this->info('✓ Database backup completed successfully!');
                return 0;
            } else {
                $this->error('✗ Backup creation failed');
                return 1;
            }
        } catch (\Exception $e) {
            $this->error('✗ Backup failed: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Create database backup
     */
    protected function createBackup(string $type, bool $compress): ?string
    {
        $connection = config('database.default');
        $config = config("database.connections.{$connection}");
        
        $timestamp = Carbon::now()->format('Y-m-d_His');
        $filename = "backup_{$type}_{$timestamp}";
        
        if ($config['driver'] === 'sqlite') {
            return $this->backupSqlite($filename, $compress);
        } elseif ($config['driver'] === 'mysql') {
            return $this->backupMysql($filename, $type, $compress, $config);
        } else {
            $this->error("Unsupported database driver: {$config['driver']}");
            return null;
        }
    }

    /**
     * Backup SQLite database
     */
    protected function backupSqlite(string $filename, bool $compress): string
    {
        $database = config('database.connections.sqlite.database');
        
        // Handle relative paths
        if (!str_starts_with($database, '/')) {
            $database = base_path($database);
        }
        
        if (!file_exists($database)) {
            throw new \Exception("SQLite database file not found: {$database}");
        }

        $backupPath = storage_path('backups');
        
        if (!is_dir($backupPath)) {
            mkdir($backupPath, 0755, true);
        }

        $destination = "{$backupPath}/{$filename}.sqlite";

        // Copy database file
        copy($database, $destination);

        // Compress if requested
        if ($compress) {
            $compressed = $this->compressFile($destination);
            unlink($destination);
            return basename($compressed);
        }

        return basename($destination);
    }

    /**
     * Backup MySQL database
     */
    protected function backupMysql(string $filename, string $type, bool $compress, array $config): ?string
    {
        $backupPath = storage_path('backups');
        
        if (!is_dir($backupPath)) {
            mkdir($backupPath, 0755, true);
        }

        $destination = "{$backupPath}/{$filename}.sql";

        // Build mysqldump command
        $command = sprintf(
            'mysqldump --user=%s --password=%s --host=%s --port=%s',
            escapeshellarg($config['username']),
            escapeshellarg($config['password']),
            escapeshellarg($config['host']),
            escapeshellarg($config['port'] ?? 3306)
        );

        // Add options based on type
        switch ($type) {
            case 'schema':
                $command .= ' --no-data';
                break;
            case 'data':
                $command .= ' --no-create-info';
                break;
            default:
                // Full backup (default)
                $command .= ' --routines --triggers';
                break;
        }

        $command .= sprintf(' %s > %s', 
            escapeshellarg($config['database']),
            escapeshellarg($destination)
        );

        // Execute backup
        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            $this->error("mysqldump failed with return code: {$returnCode}");
            return null;
        }

        // Compress if requested
        if ($compress) {
            $compressed = $this->compressFile($destination);
            unlink($destination);
            return basename($compressed);
        }

        return basename($destination);
    }

    /**
     * Compress file using gzip
     */
    protected function compressFile(string $file): string
    {
        $compressed = $file . '.gz';
        
        $fp = gzopen($compressed, 'w9');
        $content = file_get_contents($file);
        gzwrite($fp, $content);
        gzclose($fp);

        return $compressed;
    }

    /**
     * Clean old backups based on retention policy
     */
    protected function cleanOldBackups(int $retentionDays): void
    {
        $backupPath = storage_path('backups');
        
        if (!is_dir($backupPath)) {
            return;
        }

        $cutoffDate = Carbon::now()->subDays($retentionDays);
        $files = glob($backupPath . '/backup_*');
        $deletedCount = 0;

        foreach ($files as $file) {
            $fileTime = Carbon::createFromTimestamp(filemtime($file));
            
            if ($fileTime->lt($cutoffDate)) {
                unlink($file);
                $deletedCount++;
            }
        }

        if ($deletedCount > 0) {
            $this->info("✓ Cleaned {$deletedCount} old backup(s)");
        }
    }
}
