
20i Backup Manager

This project provides a PHP-based CLI tool to automate backups for 20i-hosted sites and upload them to an FTP server. The tool supports options to initiate backups or download and transfer existing backups.

Requirements

- PHP 7.4 or above
- Composer for dependency management
- FTP server access for backup storage
- 20i API credentials for managing sites and backups

Setup

1. Clone the Repository

   git clone https://your-repo-url.git
   cd your-repo-directory

2. Install Dependencies

   Use Composer to install the required packages:

   composer install

3. Configure FTP and API Settings

   Open the `TwentyIBackupManager` class and update the following properties with your details:

   private $ftpHost = 'yourftpserver.com';
   private $ftpUser = 'yourftpuser';
   private $ftpPass = 'yourftppass';

   Also, set the 20i API Bearer Token in the `BEARER_TOKEN` constant:

   const BEARER_TOKEN = 'yourbearertoken';

4. Create Site List File

   Add the IDs of sites you want to backup in a file named `site.txt` located in the project’s root directory. Each line should contain one site ID:

   site_id_1
   site_id_2
   site_id_3

5. Create Necessary Directories

   Ensure the `backups` directory exists to store downloaded backup files:

   mkdir backups

6. Processed Sites File

   A file named `processed_sites.txt` will automatically be created to track the sites that have already been backed up. You can manually clear this file if you need to re-run backups for previously processed sites.

Usage

This CLI tool supports two main actions: scheduling backups and downloading existing backups. Use the following commands to operate the tool:

php TwentyIBackupManager.php -a backup

- backup: Schedules a new backup for each site listed in `site.txt` that hasn't been backed up yet.

php TwentyIBackupManager.php -a download

- download: Downloads the backup of each site listed in `site.txt`, then transfers it to the configured FTP server.

FTP Transfer

The tool automatically uploads each backup file to the specified FTP server and deletes the local copy upon successful transfer.

Customization Options

- Backup Retention: Change the retention period by modifying the `DELETE_BACKUPS_OLDER_THAN` constant:

  const DELETE_BACKUPS_OLDER_THAN = '1 month';

- Backup Configuration: You can select what to back up (files, databases, or both) by adjusting the `whatToBackup` property.

20i Server Folder

To avoid blocking issues, upload the entire `20iBackupManager` folder to any hosting environment before initiating backups. This helps to prevent connection restrictions that may otherwise interfere with backup operations.

Logging and Error Handling

- The tool outputs messages to indicate the success or failure of each action.
- If a backup or FTP upload fails, the relevant error will be displayed.

License

This project is licensed under the MIT License.
