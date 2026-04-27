<?php

namespace App\Support;

use Illuminate\Filesystem\Filesystem;

/**
 * Overrides the default Filesystem to handle Windows file-lock errors
 * on rename() when compiling Blade views (XAMPP + antivirus / opcache).
 */
class WindowsFilesystem extends Filesystem
{
    /**
     * Write the contents of a file, replacing it atomically.
     * Retries the rename up to 5 times with a short sleep to
     * wait for any file lock to be released.
     */
    public function replace($path, $content, $mode = null)
    {
        // If the path already exists and is a symlink, get the real path...
        $path = realpath($path) ?: $path;

        $tempPath = tempnam(dirname($path), basename($path));

        // Fix permissions if needed
        if ($mode !== null) {
            chmod($tempPath, $mode);
        } else {
            @chmod($tempPath, 0777 & ~umask());
        }

        // Retry file_put_contents in case of temporary file locks
        $attempts = 0;
        $maxAttempts = 5;
        $writeSuccess = false;

        while ($attempts < $maxAttempts) {
            try {
                file_put_contents($tempPath, $content);
                $writeSuccess = true;
                break;
            } catch (\Throwable $e) {
                $attempts++;
                if ($attempts >= $maxAttempts) {
                    throw $e;
                }
                usleep(50000); // 50ms wait between retries
            }
        }

        if (!$writeSuccess) {
            throw new \RuntimeException("Failed to write to temp file {$tempPath}");
        }

        $attempts = 0;

        while ($attempts < $maxAttempts) {
            try {
                @rename($tempPath, $path);
                if (file_exists($tempPath)) {
                    throw new \RuntimeException("rename failed for {$path}");
                }
                return;
            } catch (\Throwable $e) {
                $attempts++;
                if ($attempts >= $maxAttempts) {
                    // Final fallback: copy + delete instead of atomic rename
                    @copy($tempPath, $path);
                    @unlink($tempPath);
                    return;
                }
                usleep(50000); // 50ms wait between retries
            }
        }
    }
}
