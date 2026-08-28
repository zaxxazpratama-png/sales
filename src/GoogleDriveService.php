<?php
namespace App;

use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;

class GoogleDriveService
{
    private Drive  $service;
    private string $folderId;

    public function __construct()
    {
        Config::load();

        $credentialsPath = Config::get('google_credentials');
        $this->folderId  = Config::get('google_drive_folder_id');

        if (!file_exists($credentialsPath)) {
            throw new \RuntimeException("Google credentials file tidak ditemukan: {$credentialsPath}");
        }

        $client = new Client();
        $client->setApplicationName('Sales Order Form - PT. TIN');
        $client->setScopes([Drive::DRIVE_FILE]);
        $client->setAuthConfig($credentialsPath);

        $this->service = new Drive($client);
    }

    /**
     * Upload file ke Google Drive
     * 
     * @param  string $filePath    Path file di server (tmp upload)
     * @param  string $fileName    Nama file di Drive
     * @param  string $mimeType    MIME type file
     * @return string              URL file di Google Drive (webViewLink)
     */
    public function upload(string $filePath, string $fileName, string $mimeType = 'image/jpeg'): string
    {
        $fileMetadata = new DriveFile([
            'name'    => $fileName,
            'parents' => [$this->folderId],
        ]);

        $content = file_get_contents($filePath);

        $file = $this->service->files->create($fileMetadata, [
            'data'           => $content,
            'mimeType'       => $mimeType,
            'uploadType'     => 'multipart',
            'fields'         => 'id,webViewLink,webContentLink',
        ]);

        // Set permission: anyone with link can view
        $permission = new Drive\Permission([
            'type' => 'anyone',
            'role' => 'reader',
        ]);
        $this->service->permissions->create($file->id, $permission);

        return $file->webViewLink ?? '';
    }

    /**
     * Deteksi MIME type dari file upload
     */
    public static function detectMimeType(array $file): string
    {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $map = [
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'pdf'  => 'application/pdf',
        ];
        return $map[$ext] ?? 'application/octet-stream';
    }
}
