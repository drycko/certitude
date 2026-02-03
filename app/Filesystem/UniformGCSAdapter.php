<?php

namespace App\Filesystem;

use Google\Cloud\Storage\StorageClient;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\UnableToWriteFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToCheckExistence;

class UniformGCSAdapter implements FilesystemAdapter
{
    protected $bucket;
    protected $prefix;
    protected $storageClient;

    public function __construct(array $config)
    {
        $this->storageClient = new StorageClient([
            'projectId' => $config['project_id'],
            'keyFilePath' => $config['key_file'],
        ]);
        
        $this->bucket = $this->storageClient->bucket($config['bucket']);
        $this->prefix = $config['path_prefix'] ?? '';
    }

    /**
     * Get the StorageClient instance
     */
    public function getStorageClient()
    {
        return $this->storageClient;
    }

    /**
     * Get the bucket instance
     */
    public function getBucket()
    {
        return $this->bucket;
    }

    /**
     * Generate a temporary signed URL for private access
     */
    public function getTemporaryUrl(string $path, \DateTimeInterface $expiration): string
    {
        $object = $this->bucket->object($this->applyPathPrefix($path));
        return $object->signedUrl($expiration);
    }

    public function fileExists(string $path): bool
    {
        try {
            return $this->bucket->object($this->applyPathPrefix($path))->exists();
        } catch (\Exception $e) {
            return false;
        }
    }

    public function directoryExists(string $path): bool
    {
        $prefix = $this->applyPathPrefix($path);
        if ($prefix && !str_ends_with($prefix, '/')) {
            $prefix .= '/';
        }
        $objects = $this->bucket->objects(['prefix' => $prefix, 'maxResults' => 1]);
        foreach ($objects as $object) {
            return true;
        }
        return false;
    }

    private function applyPathPrefix($path): string
    {
        $path = ltrim($path, '/');
        return $this->prefix ? $this->prefix . '/' . $path : $path;
    }

    public function write(string $path, string $contents, Config $config): void
    {
        try {
            $this->bucket->upload($contents, [
                'name' => $this->applyPathPrefix($path),
                // No ACLs for uniform bucket-level access
            ]);
        } catch (\Exception $e) {
            throw UnableToWriteFile::atLocation($path, $e->getMessage());
        }
    }

    public function writeStream(string $path, $resource, Config $config): void
    {
        $this->write($path, stream_get_contents($resource), $config);
    }

    public function read(string $path): string
    {
        try {
            $object = $this->bucket->object($this->applyPathPrefix($path));
            return $object->downloadAsString();
        } catch (\Exception $e) {
            throw UnableToReadFile::fromLocation($path, $e->getMessage());
        }
    }

    public function readStream(string $path)
    {
        $content = $this->read($path);
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);
        return $stream;
    }

    public function delete(string $path): void
    {
        try {
            $this->bucket->object($this->applyPathPrefix($path))->delete();
        } catch (\Exception $e) {
            throw UnableToDeleteFile::atLocation($path, $e->getMessage());
        }
    }

    public function deleteDirectory(string $path): void
    {
        // Directories are automatic in GCS, but delete all objects with prefix
        $objects = $this->bucket->objects(['prefix' => $this->applyPathPrefix($path) . '/']);
        foreach ($objects as $object) {
            try {
                $object->delete();
            } catch (\Exception $e) {
                // Continue with other objects
            }
        }
    }

    public function createDirectory(string $path, Config $config): void
    {
        // Directories are created automatically in GCS
        // Upload an empty file to simulate directory creation if needed
        $this->write($path . '/.keep', '', $config);
    }

    public function setVisibility(string $path, string $visibility): void
    {
        // Not supported with uniform bucket-level access
        // Visibility is controlled at bucket level
    }

    public function visibility(string $path): FileAttributes
    {
        // For uniform buckets, visibility is determined by bucket settings
        // You might want to check bucket IAM policies here
        return new FileAttributes($path, null, null, null, 'public');
    }

    public function fileSize(string $path): FileAttributes
    {
        try {
            $object = $this->bucket->object($this->applyPathPrefix($path));
            $info = $object->info();
            return new FileAttributes($path, $info['size']);
        } catch (\Exception $e) {
            return new FileAttributes($path);
        }
    }

    public function mimeType(string $path): FileAttributes
    {
        try {
            $object = $this->bucket->object($this->applyPathPrefix($path));
            $info = $object->info();
            return new FileAttributes($path, null, null, $info['contentType']);
        } catch (\Exception $e) {
            return new FileAttributes($path);
        }
    }

    public function lastModified(string $path): FileAttributes
    {
        try {
            $object = $this->bucket->object($this->applyPathPrefix($path));
            $info = $object->info();
            return new FileAttributes($path, null, null, null, null, strtotime($info['updated']));
        } catch (\Exception $e) {
            return new FileAttributes($path);
        }
    }

    public function listContents(string $path, bool $deep): iterable
    {
        $prefix = $this->applyPathPrefix($path);
        if ($prefix && !str_ends_with($prefix, '/')) {
            $prefix .= '/';
        }

        $objects = $this->bucket->objects(['prefix' => $prefix]);
        
        foreach ($objects as $object) {
            $info = $object->info();
            yield new FileAttributes(
                $object->name(),
                $info['size'],
                null,
                $info['contentType'],
                null,
                strtotime($info['updated'])
            );
        }
    }

    public function move(string $source, string $destination, Config $config): void
    {
        $this->copy($source, $destination, $config);
        $this->delete($source);
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        try {
            $sourceObject = $this->bucket->object($this->applyPathPrefix($source));
            $sourceObject->copy($this->bucket, ['name' => $this->applyPathPrefix($destination)]);
        } catch (\Exception $e) {
            throw UnableToWriteFile::atLocation($destination, $e->getMessage());
        }
    }

    public function has(string $path): bool
    {
        try {
            return $this->bucket->object($this->applyPathPrefix($path))->exists();
        } catch (\Exception $e) {
            return false;
        }
    }
}