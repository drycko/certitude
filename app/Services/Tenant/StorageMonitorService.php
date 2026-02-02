<?php

namespace App\Services\Tenant;

use Exception;
use Illuminate\Support\Facades\Log;
use Google\Cloud\Storage\StorageClient;
use Illuminate\Support\Facades\Cache;

class StorageMonitorService
{
    protected array $config;
    
    public function __construct()
    {
        $this->config = [
            'cache_ttl' => 300, // 5 minutes cache
            'gcs_project_id' => config('filesystems.disks.gcs.project_id'),
            'gcs_bucket' => config('filesystems.disks.gcs.bucket'),
            'gcs_key_file' => config('filesystems.disks.gcs.key_file'),
        ];
    }

    /**
     * Get comprehensive storage information for all available storage types
     */
    public function getAllStorageInfo(): array
    {
        $allInfo = [
            'local' => $this->getLocalStorageInfo(),
            'cpanel' => $this->getCPanelStorageInfo(),
            'google_cloud' => $this->getGoogleCloudStorageInfo(),
            'summary' => $this->getStorageSummary(),
        ];

        $allInfo['dashboard_cards'] = $this->formatDashboardCards($allInfo);
        $allInfo['alerts'] = $this->formatAlerts($allInfo);
        $allInfo['charts_data'] = $this->formatChartsData($allInfo);
        $allInfo['recommendations'] = $allInfo['summary']['recommendations'] ?? [];

        return $allInfo;
    }

    /**
     * Get local filesystem storage information
     */
    public function getLocalStorageInfo(): array
    {
        $storageInfo = [
            'type' => 'local',
            'method' => 'system_disk',
            'total' => null,
            'used' => null,
            'free' => null,
            'percent' => null,
            'status' => 'unknown',
            'warnings' => []
        ];

        try {
            $storagePath = storage_path();
            $freeBytes = disk_free_space($storagePath);
            $totalBytes = disk_total_space($storagePath);

            if ($freeBytes !== false && $totalBytes !== false) {
                $usedBytes = $totalBytes - $freeBytes;
                $usedPercent = ($usedBytes / $totalBytes) * 100;

                $storageInfo = array_merge($storageInfo, [
                    'total' => $totalBytes,
                    'used' => $usedBytes,
                    'free' => $freeBytes,
                    'percent' => $usedPercent,
                    'status' => $this->getStorageStatus($usedPercent),
                    'path' => $storagePath
                ]);

                if ($usedPercent > 85) {
                    $storageInfo['warnings'][] = 'Local storage critically low';
                } elseif ($usedPercent > 70) {
                    $storageInfo['warnings'][] = 'Local storage getting low';
                }
            }
        } catch (Exception $e) {
            $storageInfo['error'] = $e->getMessage();
            Log::warning('Failed to get local storage info: ' . $e->getMessage());
        }

        return $storageInfo;
    }

    /**
     * Get cPanel-specific storage information (for shared hosting)
     */
    public function getCPanelStorageInfo(): array
    {
        return Cache::remember('cpanel_storage_info', $this->config['cache_ttl'], function () {
            $storageInfo = [
                'type' => 'cpanel',
                'method' => 'unknown',
                'total' => null,
                'used' => null,
                'free' => null,
                'percent' => null,
                'status' => 'unknown',
                'warnings' => []
            ];

            // Method 1: Try cPanel API if credentials are available
            $cPanelInfo = $this->getCPanelApiInfo();
            if ($cPanelInfo) {
                return array_merge($storageInfo, $cPanelInfo);
            }

            // Method 2: Try quota command
            $quotaInfo = $this->getQuotaInfo();
            if ($quotaInfo) {
                return array_merge($storageInfo, $quotaInfo);
            }

            // Method 3: Directory scanning with estimation
            $dirScanInfo = $this->getDirectoryScanInfo();
            if ($dirScanInfo) {
                return array_merge($storageInfo, $dirScanInfo);
            }

            return $storageInfo;
        });
    }

    /**
     * Get Google Cloud Storage information
     */
    public function getGoogleCloudStorageInfo(): array
    {
        return Cache::remember('gcs_storage_info', $this->config['cache_ttl'], function () {
            $storageInfo = [
                'type' => 'google_cloud',
                'method' => 'api',
                'total' => null,
                'used' => null,
                'free' => null,
                'percent' => null,
                'status' => 'unknown',
                'warnings' => [],
                'bucket_info' => []
            ];

            if (!$this->config['gcs_project_id'] || !$this->config['gcs_bucket']) {
                $storageInfo['error'] = 'Google Cloud Storage not configured';
                return $storageInfo;
            }

            try {
                $storage = new StorageClient([
                    'projectId' => $this->config['gcs_project_id'],
                    'keyFilePath' => $this->config['gcs_key_file']
                ]);

                $bucket = $storage->bucket($this->config['gcs_bucket']);
                
                // Get bucket info
                $bucketInfo = $bucket->info();
                
                // Calculate used space by iterating objects
                $usedBytes = 0;
                $objectCount = 0;
                
                $objects = $bucket->objects(['maxResults' => 1000]); // Limit for performance
                foreach ($objects as $object) {
                    $usedBytes += $object->info()['size'] ?? 0;
                    $objectCount++;
                }

                // Estimate total quota (GCS doesn't have hard limits by default)
                // Using project quotas or reasonable estimates
                $estimatedQuota = $this->getGCSQuotaEstimate();
                $usedPercent = $estimatedQuota > 0 ? ($usedBytes / $estimatedQuota) * 100 : 0;

                $storageInfo = array_merge($storageInfo, [
                    'total' => $estimatedQuota,
                    'used' => $usedBytes,
                    'free' => $estimatedQuota - $usedBytes,
                    'percent' => $usedPercent,
                    'status' => $this->getStorageStatus($usedPercent),
                    'bucket_info' => [
                        'name' => $this->config['gcs_bucket'],
                        'location' => $bucketInfo['location'] ?? 'unknown',
                        'storage_class' => $bucketInfo['storageClass'] ?? 'unknown',
                        'object_count' => $objectCount,
                        'created' => $bucketInfo['timeCreated'] ?? null
                    ]
                ]);

                // Add warnings based on usage
                if ($usedPercent > 80) {
                    $storageInfo['warnings'][] = 'Google Cloud Storage usage high';
                }

                // Check for billing limits
                if ($usedBytes > (50 * 1024 * 1024 * 1024)) { // 50GB
                    $storageInfo['warnings'][] = 'Approaching typical billing threshold';
                }

            } catch (Exception $e) {
                $storageInfo['error'] = 'GCS connection failed: ' . $e->getMessage();
                Log::warning('Failed to get Google Cloud Storage info: ' . $e->getMessage());
            }

            return $storageInfo;
        });
    }

    /**
     * Get storage summary across all types
     */
    public function getStorageSummary(): array
    {
        $local = $this->getLocalStorageInfo();
        $cpanel = $this->getCPanelStorageInfo();
        $gcs = $this->getGoogleCloudStorageInfo();

        $totalUsed = ($local['used'] ?? 0) + ($gcs['used'] ?? 0);
        $totalQuota = ($local['total'] ?? 0) + ($gcs['total'] ?? 0);
        
        $allWarnings = array_merge(
            $local['warnings'] ?? [],
            $cpanel['warnings'] ?? [],
            $gcs['warnings'] ?? []
        );

        return [
            'total_used' => $totalUsed,
            'total_quota' => $totalQuota,
            'overall_percent' => $totalQuota > 0 ? ($totalUsed / $totalQuota) * 100 : 0,
            'primary_storage' => $this->determinePrimaryStorage($local, $cpanel, $gcs),
            'warnings' => $allWarnings,
            'recommendations' => $this->getStorageRecommendations($local, $cpanel, $gcs)
        ];
    }

    /**
     * Check if storage is critically low across all systems
     */
    public function isCriticallyLow(): bool
    {
        $summary = $this->getStorageSummary();
        return $summary['overall_percent'] > 90 || count($summary['warnings']) > 2;
    }

    /**
     * Get formatted storage status for display
     */
    public function getFormattedStatus(): array
    {
        $allInfo = $this->getAllStorageInfo();
        
        return [
            'dashboard_cards' => $this->formatDashboardCards($allInfo),
            'alerts' => $this->formatAlerts($allInfo),
            'charts_data' => $this->formatChartsData($allInfo),
            'recommendations' => $allInfo['summary']['recommendations']
        ];
    }

    // Protected helper methods

    protected function getCPanelApiInfo(): ?array
    {
        // Attempt to use cPanel API if credentials are available
        $cPanelHost = env('CPANEL_HOST');
        $cPanelUser = env('CPANEL_USER');
        $cPanelPass = env('CPANEL_PASS');

        if (!$cPanelHost || !$cPanelUser || !$cPanelPass) {
            return null;
        }

        try {
            // cPanel UAPI call for disk usage
            $url = "https://{$cPanelHost}:2083/execute/DiskUsage/get_disk_usage";
            
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => 'Authorization: Basic ' . base64_encode("{$cPanelUser}:{$cPanelPass}")
                ]
            ]);

            $response = @file_get_contents($url, false, $context);
            
            if ($response) {
                $data = json_decode($response, true);
                
                if (isset($data['data']['disk_usage'])) {
                    $diskUsage = $data['data']['disk_usage'];
                    $usedBytes = ($diskUsage['used_bytes'] ?? 0);
                    $totalBytes = ($diskUsage['limit_bytes'] ?? 0);
                    
                    if ($totalBytes > 0) {
                        return [
                            'method' => 'cpanel_api',
                            'total' => $totalBytes,
                            'used' => $usedBytes,
                            'free' => $totalBytes - $usedBytes,
                            'percent' => ($usedBytes / $totalBytes) * 100,
                            'status' => $this->getStorageStatus(($usedBytes / $totalBytes) * 100)
                        ];
                    }
                }
            }
        } catch (Exception $e) {
            Log::debug('cPanel API check failed: ' . $e->getMessage());
        }

        return null;
    }

    protected function getQuotaInfo(): ?array
    {
        if (!function_exists('exec') || in_array('exec', explode(',', ini_get('disable_functions')))) {
            return null;
        }

        try {
            $output = [];
            @exec('quota -u ' . get_current_user() . ' 2>/dev/null', $output);
            
            foreach ($output as $line) {
                if (preg_match('/\s+(\d+)\s+(\d+)\s+(\d+)/', $line, $matches)) {
                    $usedKB = (int)$matches[1];
                    $quotaKB = (int)$matches[2];
                    
                    if ($quotaKB > 0) {
                        $usedBytes = $usedKB * 1024;
                        $totalBytes = $quotaKB * 1024;
                        
                        return [
                            'method' => 'quota_command',
                            'total' => $totalBytes,
                            'used' => $usedBytes,
                            'free' => $totalBytes - $usedBytes,
                            'percent' => ($usedBytes / $totalBytes) * 100,
                            'status' => $this->getStorageStatus(($usedBytes / $totalBytes) * 100)
                        ];
                    }
                }
            }
        } catch (Exception $e) {
            Log::debug('Quota command failed: ' . $e->getMessage());
        }

        return null;
    }

    protected function getDirectoryScanInfo(): ?array
    {
        try {
            $appPath = base_path();
            $usedBytes = $this->calculateDirectorySize($appPath);
            
            if ($usedBytes === false) {
                return null;
            }

            // Estimate quota based on common cPanel plans
            $estimatedQuota = $this->estimateCPanelQuota($appPath);
            
            $usedPercent = ($usedBytes / $estimatedQuota) * 100;
            
            return [
                'method' => 'directory_scan',
                'total' => $estimatedQuota,
                'used' => $usedBytes,
                'free' => $estimatedQuota - $usedBytes,
                'percent' => $usedPercent,
                'status' => $this->getStorageStatus($usedPercent),
                'scan_path' => $appPath
            ];
            
        } catch (Exception $e) {
            Log::debug('Directory scan failed: ' . $e->getMessage());
        }

        return null;
    }

    protected function calculateDirectorySize(string $directory): int|false
    {
        $maxExecutionTime = 30; // Limit to 30 seconds
        $startTime = time();
        $size = 0;

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (time() - $startTime > $maxExecutionTime) {
                    break; // Prevent timeout
                }

                if ($file->isFile()) {
                    $size += $file->getSize();
                }
            }

            return $size;
        } catch (Exception $e) {
            return false;
        }
    }

    protected function estimateCPanelQuota(string $path): int
    {
        // Default quotas for common cPanel plans
        $defaultQuotas = [
            'basic' => 15 * 1024 * 1024 * 1024,      // 15GB
            'premium' => 50 * 1024 * 1024 * 1024,    // 50GB
            'business' => 100 * 1024 * 1024 * 1024,  // 100GB
        ];

        // Try to detect from path patterns
        if (preg_match('/\/(\d+)GB?\//', $path, $matches)) {
            return (int)$matches[1] * 1024 * 1024 * 1024;
        }

        // Check for plan indicators in hostname or path
        $hostname = gethostname();
        if (stripos($hostname, 'business') !== false) {
            return $defaultQuotas['business'];
        } elseif (stripos($hostname, 'premium') !== false) {
            return $defaultQuotas['premium'];
        }

        // Default to business plan (Dole got a new 100gb plan)
        return $defaultQuotas['business'];
    }

    protected function getGCSQuotaEstimate(): int
    {
        // GCS doesn't have hard quotas by default, so we estimate based on:
        // 1. Project quotas (if available)
        // 2. Billing limits
        // 3. Reasonable defaults

        $defaultLimit = 1024 * 1024 * 1024 * 1024; // 1TB default
        
        // Check for configured limits
        if ($configuredLimit = config('storage.gcs_limit_bytes')) {
            return $configuredLimit;
        }

        return $defaultLimit;
    }

    protected function getStorageStatus(float $usedPercent): string
    {
        if ($usedPercent > 90) return 'critical';
        if ($usedPercent > 85) return 'warning';
        if ($usedPercent > 70) return 'caution';
        return 'healthy';
    }

    protected function determinePrimaryStorage(array $local, array $cpanel, array $gcs): string
    {
        // Determine which storage system is primary based on configuration and usage
        if (!empty($gcs['used']) && $gcs['used'] > ($local['used'] ?? 0)) {
            return 'google_cloud';
        }
        
        if ($cpanel['method'] !== 'unknown') {
            return 'cpanel';
        }
        
        return 'local';
    }

    protected function getStorageRecommendations(array $local, array $cpanel, array $gcs): array
    {
        $recommendations = [];

        // Check local storage
        if (($local['percent'] ?? 0) > 85) {
            $recommendations[] = [
                'type' => 'critical',
                'message' => 'Local storage critically low - consider cleanup or migration to cloud',
                'action' => 'cleanup_local'
            ];
        }

        // Check cPanel storage
        if (($cpanel['percent'] ?? 0) > 80) {
            $recommendations[] = [
                'type' => 'warning',
                'message' => 'cPanel quota almost reached - consider upgrading plan or using cloud storage',
                'action' => 'migrate_to_cloud'
            ];
        }

        // Check Google Cloud costs
        if (($gcs['used'] ?? 0) > (100 * 1024 * 1024 * 1024)) { // 100GB
            $recommendations[] = [
                'type' => 'info',
                'message' => 'Google Cloud usage is high - monitor billing costs',
                'action' => 'review_billing'
            ];
        }

        // General recommendations
        if (empty($recommendations)) {
            $recommendations[] = [
                'type' => 'success',
                'message' => 'Storage levels are healthy across all systems',
                'action' => 'continue_monitoring'
            ];
        }

        return $recommendations;
    }

    protected function formatDashboardCards(array $allInfo): array
    {
        $cards = [];

        foreach (['local', 'cpanel', 'google_cloud'] as $type) {
            $info = $allInfo[$type];
            
            if ($info['total']) {
                $cards[] = [
                    'title' => ucfirst(str_replace('_', ' ', $type)) . ' Storage',
                    'used' => $this->formatBytes($info['used']),
                    'total' => $this->formatBytes($info['total']),
                    'percent' => round($info['percent'], 1),
                    'status' => $info['status'],
                    'method' => $info['method']
                ];
            }
        }

        return $cards;
    }

    protected function formatAlerts(array $allInfo): array
    {
        $alerts = [];

        foreach (['local', 'cpanel', 'google_cloud'] as $type) {
            $warnings = $allInfo[$type]['warnings'] ?? [];
            foreach ($warnings as $warning) {
                $alerts[] = [
                    'type' => $type,
                    'level' => $allInfo[$type]['status'],
                    'message' => $warning
                ];
            }
        }

        return $alerts;
    }

    protected function formatChartsData(array $allInfo): array
    {
        $chartData = [];

        foreach (['local', 'cpanel', 'google_cloud'] as $type) {
            $info = $allInfo[$type];
            
            if ($info['total']) {
                $chartData[] = [
                    'name' => ucfirst(str_replace('_', ' ', $type)),
                    'used' => $info['used'],
                    'free' => $info['free'],
                    'percent' => $info['percent']
                ];
            }
        }

        return $chartData;
    }

    protected function formatBytes(int $size): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = $size > 0 ? floor(log($size, 1024)) : 0;
        $power = min($power, count($units) - 1);
        
        return number_format($size / pow(1024, $power), 2) . ' ' . $units[$power];
    }
}