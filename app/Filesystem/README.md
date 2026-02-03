# UniformGCSAdapter - Google Cloud Storage Adapter for Laravel

A custom Flysystem adapter for Google Cloud Storage that supports **uniform bucket-level access** (no object-level ACLs).

## Why This Adapter?

This adapter was created because existing GCS adapters (like `superbalist/flysystem-google-storage`) are outdated and fail with modern GCS buckets that use uniform bucket-level access. Google recommends uniform bucket-level access for better security and IAM integration, but many Flysystem adapters still try to set object-level ACLs, causing errors.

## Features

- ✅ Flysystem 3.x compatible
- ✅ Supports uniform bucket-level access (no ACL errors)
- ✅ Uses official `google/cloud-storage` package
- ✅ All standard Flysystem operations: read, write, delete, list, copy, move
- ✅ Path prefix support for multi-tenant storage
- ✅ Clean, maintainable code

## Installation

### 1. Copy the Adapter

Copy `UniformGCSAdapter.php` to your Laravel project:
```
app/Filesystem/UniformGCSAdapter.php
```

### 2. Install Google Cloud Storage SDK

```bash
composer require google/cloud-storage
```

### 3. Register the Driver

In `app/Providers/AppServiceProvider.php`, add to the `boot()` method:

```php
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Filesystem;
use App\Filesystem\UniformGCSAdapter;

public function boot(): void
{
    // Register GCS custom driver
    Storage::extend('gcs', function ($app, $config) {
        $adapter = new UniformGCSAdapter($config);
        return new Filesystem($adapter, $config);
    });
}
```

### 4. Configure Filesystem

In `config/filesystems.php`, add a GCS disk:

```php
'disks' => [
    // ... other disks

    'gcs' => [
        'driver' => 'gcs',
        'project_id' => env('GCS_PROJECT_ID'),
        'key_file' => base_path(env('GCS_KEY_FILE')),
        'bucket' => env('GCS_BUCKET'),
        'path_prefix' => env('GCS_PATH_PREFIX', ''), // optional
    ],
],
```

### 5. Environment Variables

Add to your `.env` file:

```env
GCS_PROJECT_ID=your-project-id
GCS_KEY_FILE=path/to/service-account-key.json
GCS_BUCKET=your-bucket-name
GCS_PATH_PREFIX=optional/prefix
```

## Google Cloud Setup

### 1. Create a Service Account

1. Go to Google Cloud Console → IAM & Admin → Service Accounts
2. Create a new service account
3. Grant it **Storage Object Admin** role (or appropriate permissions)
4. Create and download a JSON key file

### 2. Configure Your Bucket

**Important:** Your bucket must have uniform bucket-level access enabled:

1. Go to Cloud Storage → Browser → Select your bucket
2. Click **Permissions** tab
3. Switch to **Uniform** access control
4. Configure IAM policies at the bucket level (not object level)

## Usage

### Basic Operations

```php
use Illuminate\Support\Facades\Storage;

// Write a file
Storage::disk('gcs')->put('path/to/file.txt', 'content');

// Read a file
$content = Storage::disk('gcs')->get('path/to/file.txt');

// Check if file exists
if (Storage::disk('gcs')->exists('path/to/file.txt')) {
    // File exists
}

// Delete a file
Storage::disk('gcs')->delete('path/to/file.txt');

// List files
$files = Storage::disk('gcs')->files('path/to/directory');

// Copy a file
Storage::disk('gcs')->copy('source.txt', 'destination.txt');

// Move a file
Storage::disk('gcs')->move('old-path.txt', 'new-path.txt');
```

### Upload Files

```php
// Upload from request
$request->file('document')->storeAs('documents', 'filename.pdf', 'gcs');

// Upload with public URL generation
$path = Storage::disk('gcs')->putFile('uploads', $file);
$url = Storage::disk('gcs')->url($path);
```

### Multi-Tenant Usage

Use path prefixes for tenant isolation:

```php
// In your tenant-aware code
$tenantPrefix = "tenant_{$tenantId}";
config(['filesystems.disks.gcs.path_prefix' => $tenantPrefix]);

// Now all operations are scoped to this tenant
Storage::disk('gcs')->put('invoice.pdf', $content);
// Stored at: gs://bucket/tenant_123/invoice.pdf
```

## Important Notes

### Visibility

The `setVisibility()` method is **not supported** with uniform bucket-level access. Visibility is controlled at the bucket level via IAM policies:

- **Public access:** Configure bucket IAM to allow `allUsers` or `allAuthenticatedUsers`
- **Private access:** Use service account authentication (default)

### Permissions

Required GCS IAM permissions for the service account:
- `storage.objects.create`
- `storage.objects.delete`
- `storage.objects.get`
- `storage.objects.list`

Or simply grant: **Storage Object Admin** role

### Performance

For large files, consider:
- Using GCS lifecycle rules for automatic cleanup
- Implementing CDN caching for frequently accessed files
- Using signed URLs for temporary access

## Troubleshooting

### "Permission denied" errors

- Verify service account has proper IAM roles
- Check bucket permissions
- Ensure `keyFilePath` points to valid JSON key file

### "ACL not supported" errors

- Confirm your bucket uses **uniform** bucket-level access
- This adapter intentionally skips ACL operations

### Path issues

- Paths should not start with `/`
- Use forward slashes `/` not backslashes `\`
- The adapter handles path prefix automatically

## Future Package

This adapter may be published as a standalone Composer package. Until then, copy it as-is into your projects.

## License

Free to use and modify for your projects.
