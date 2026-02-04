<?php

namespace App\Services\Tenant;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileStorageService
{
    protected string $disk;

    public function __construct()
    {
        $this->disk = config('filesystems.default', 'local');
    }

    /**
     * Upload a file to storage
     */
    public function upload(UploadedFile $file, string $directory = 'documents'): array
    {
        $originalName = $file->getClientOriginalName();
        $filename = $this->generateUniqueFilename($file);
        $currentTenant = current_tenant();
        // encode tenant ID in path to segregate files
        $tenantId = $currentTenant->id;
        $path = 'tenants/tenant_' . $tenantId . '/' . $directory . '/' . $filename;

        // Store the file
        $storedPath = Storage::disk($this->disk)->putFileAs($path, $file, $filename);

        return [
            'filename' => $filename,
            'original_filename' => $originalName,
            'file_path' => $storedPath,
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
        ];
    }

    /**
     * Delete a file from storage
     */
    public function delete(string $filePath): bool
    {
        return Storage::disk($this->disk)->delete($filePath);
    }

    /**
     * Get URL for a file
     */
    public function getUrl(string $filePath): string
    {
        if ($this->disk === 'gcs') {
            return Storage::disk($this->disk)->url($filePath);
        }

        // For local development, create a secure route
        return route('documents.download', ['path' => base64_encode($filePath)]);
    }

    /**
     * Check if file exists
     * @param string $filePath
     * @return bool
     */
    public function exists(string $filePath): bool
    {
        return Storage::disk($this->disk)->exists($filePath);
    }

    /**
     * Get file contents
     * @param string $filePath
     * @return string
     * App\Services\FileStorageService::get(): Return value must be of type string, null returned? 
     */
    public function get(string $filePath): string
    {   
        return Storage::disk($this->disk)->get($filePath) ?? '';
    }

    /**
     * Generate unique filename
     */
    private function generateUniqueFilename(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();
        $timestamp = now()->format('Y-m-d_H-i-s');
        $random = Str::random(8);
        
        return $timestamp . '_' . $random . '.' . $extension;
    }

    /**
     * Get allowed file types
     */
    public function getAllowedFileTypes(): array
    {
        return [
            'application/pdf',
            'image/jpeg',
            'image/jpg',
            'image/png',
            'application/msword', // .doc
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // .docx
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/zip',
            'application/x-zip-compressed',
            'application/x-zip',
            'application/octet-stream', // Some servers use this for ZIP
            'multipart/x-zip',
        ];
    }

    /**
     * Get allowed file extensions
     */
    public function getAllowedFileExtensions(): array
    {
        return [
            'pdf',
            'jpg',
            'jpeg',
            'png',
            'doc',
            'docx',
            'xls',
            'xlsx',
            'zip',
        ];
    }

    /**
     * Validate file type (PDF and JPG only)
     */
    public function validateFileType(UploadedFile $file): bool
    {
        $allowedMimeTypes = $this->getAllowedFileTypes();

        $allowedExtensions = $this->getAllowedFileExtensions();
        
        return in_array($file->getMimeType(), $allowedMimeTypes) && 
               in_array(strtolower($file->getClientOriginalExtension()), $allowedExtensions);
    }

    /**
     * Get maximum file size in bytes
     */
    public function getMaxFileSize(): int
    {
        return 15 * 1024 * 1024; // 15MB
    }
}