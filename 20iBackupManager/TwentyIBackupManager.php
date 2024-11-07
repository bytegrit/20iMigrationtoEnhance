<?php

require 'vendor/autoload.php';
require_once '20i_client.php';

/**
 * Class TwentyIBackupManager
 */
class TwentyIBackupManager
{
    private $hostClient;
    private $ftpHost = 'yourftpserver.com';
    private $ftpUser = 'yourftpuser';
    private $ftpPass = 'yourftppass';
    private $ftpDir = '/'; // Set the directory on FTP where you want to upload the files
    private $sites;
    private $excludedSites = [];

    // 20i account details
    const API_BASE = 'https://api.20i.com';
    const BEARER_TOKEN = 'yourbearertoken';
    const BACKUP_DIR = __DIR__ . '/backups';
    const PROCESSED_FILE = __DIR__ . '/processed_sites.txt';

    // Backup config
    const DELETE_BACKUPS_OLDER_THAN = '1 month';

    private $whatToBackup = [
        'files' => true,
        'databases' => true,
    ];

    public function __construct()
    {
        $this->hostClient = new \TwentyI\Stack\MyREST(self::BEARER_TOKEN);
        $this->sites = $this->getSites();
    }

    protected function getSites()
    {
        if (is_null($this->sites)) {
            // Fetch all sites from the API
            $allSites = $this->hostClient->getWithFields($this->endpoint('package'));
          
            // Load the site IDs from the site.txt file
            $siteFile = __DIR__ . '/site.txt';
            if (!file_exists($siteFile)) {
                echo "❌  Site list file not found: $siteFile\n";
                return [];
            }
            $siteIds = file($siteFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            


            // Filter the sites to include only those listed in site.txt
            $this->sites = array_filter($allSites, function ($site) use ($siteIds) {
                return in_array($site->id, $siteIds);
            });
            
            

            // If any of the site IDs from site.txt are not found in the API response, log a message
            foreach ($siteIds as $siteId) {
                if (!in_array($siteId, array_column($this->sites, 'id'))) {
                    echo "❌  Site not found in API response with ID: $siteId\n";
                }
            }
        }

        return $this->sites;
    }

    protected function endpoint($path)
    {
        return sprintf("%s/%s", self::API_BASE, $path);
    }

    protected function startBackup($siteId)
    {
        $logPrefix = 'BACKUP';
        $site = $this->getSiteById($siteId);

        if (!$site) {
            echo "[$logPrefix] ❌  Site not found with ID: $siteId\n";
            return false;
        }

        $result = $this->hostClient->postWithFields($this->endpoint("package/" . $site->id . "/web/websiteBackup"), $this->whatToBackup);

        if (isset($result->result)) {
            echo "[$logPrefix] ⚡  Backup scheduled for {$site->name}\n";
            return true;
        } else {
            echo "[$logPrefix] ❌  Failed to schedule backup for {$site->name}\n";
            return false;
        }
    }

    protected function getSiteById($siteId)
    {
        foreach ($this->sites as $site) {
            if ($site->id === $siteId) {
                return $site;
            }
        }
        return null;
    }

    protected function downloadBackup($siteId)
    {
        $logPrefix = 'DOWNLOAD';

        if (!is_dir(self::BACKUP_DIR)) {
            echo "[$logPrefix] ❌  Backup directory not created, making...\n";
            mkdir(self::BACKUP_DIR);
        }

        $site = $this->getSiteById($siteId);

        if (!$site) {
            echo "[$logPrefix] ❌  Site not found with ID: $siteId\n";
            return false;
        }

        $backup = $this->hostClient->getWithFields($this->endpoint('package/' . $site->id . '/web/websiteBackup'));

        if (isset($backup, $backup->download_link)) {
            $backupDate = date("d-m-Y-H-i-s", strtotime($backup->created_at));
            $filename = $site->name . '_' . $backupDate . '.zip';
            $filepath = self::BACKUP_DIR . '/' . $filename;

            if (!is_file($filepath)) {
                echo "[$logPrefix] ☁️️  Found a remote backup for " . $site->name . " created at " . $backupDate . ", downloading\n";
                file_put_contents($filepath, fopen($backup->download_link, 'r'));
                $this->markSiteAsProcessed($siteId);
                return $filepath;
            } else {
                echo "[$logPrefix] 📁  Found a local backup for {$site->name}, skipping download\n";
                return $filepath;
            }
        }

        return false;
    }

    protected function markSiteAsProcessed($siteId)
    {
        $processed = file_exists(self::PROCESSED_FILE) ? file(self::PROCESSED_FILE, FILE_IGNORE_NEW_LINES) : [];
        if (!in_array($siteId, $processed)) {
            file_put_contents(self::PROCESSED_FILE, $siteId . PHP_EOL, FILE_APPEND);
        }
    }

    protected function transferBackup($filepath)
    {
        $logPrefix = 'TRANSFER';
        $filename = basename($filepath);

        // Establish FTP connection
        $ftpConn = ftp_connect($this->ftpHost);
        $loginResult = ftp_login($ftpConn, $this->ftpUser, $this->ftpPass);

        if (!$ftpConn || !$loginResult) {
            echo "[$logPrefix] ❌  FTP connection or login failed.\n";
            return false;
        }

        ftp_pasv($ftpConn, true); // Enable passive mode

        echo "[$logPrefix] ⚡  Uploading backup " . $filename . "\n";

        if (ftp_put($ftpConn, $this->ftpDir . '/' . $filename, $filepath, FTP_BINARY)) {
            echo "[$logPrefix] ✅  Uploaded backup " . $filename . " to FTP server, deleting local copy\n";
            unlink($filepath);
        } else {
            echo "[$logPrefix] ❌  Failed to upload backup " . $filename . " to FTP server\n";
        }

        // Close the FTP connection
        ftp_close($ftpConn);
    }

   public function boot()
{
    $args = getopt("a:");

    switch ($args['a']) {
        case 'download':
            $sites = $this->getSites();
            foreach ($sites as $site) {
                if (!in_array($site->id, $this->getProcessedSites())) {
                    $filepath = $this->downloadBackup($site->id);
                    if ($filepath) {
                        $this->transferBackup($filepath);
                    }
                }
            }
            break;

        case 'backup':
            $sites = $this->getSites();
            foreach ($sites as $site) {
                if (!in_array($site->id, $this->getProcessedSites())) {
                    $this->startBackup($site->id);
                }
            }
            break;

        default:
            echo 'Invalid action requested: ' . $args['a'] . "\n";
            break;
    }
}


    protected function getProcessedSites()
    {
        return file_exists(self::PROCESSED_FILE) ? file(self::PROCESSED_FILE, FILE_IGNORE_NEW_LINES) : [];
    }
}

echo "---------------------------------------------------------------------------------------\n";
echo '20i CLI Backup Manager' . "\n";
echo "---------------------------------------------------------------------------------------\n";

$manager = new TwentyIBackupManager;
$manager->boot();
