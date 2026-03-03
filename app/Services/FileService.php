<?php
/**
 * The Battle 3x3 — Service Layer
 * FileService
 *
 * Handles file uploads (logos, player photos) and deletions.
 * Reads UPLOADS_PATH, MAX_FILE_SIZE, ALLOWED_IMAGE_TYPES from config/app.php constants.
 */

class FileService
{
    public function __construct(private PDO $pdo) {}

    /**
     * Process an uploaded file and move it to the uploads directory.
     *
     * @param  array  $file    Entry from $_FILES
     * @param  string $subDir  Sub-directory within UPLOADS_PATH (e.g. "logos", "photos")
     * @return array{success: bool, filename?: string, error?: string}
     */
    public function handle(array $file, string $subDir): array
    {
        if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => 'No file selected or upload error.'];
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ALLOWED_IMAGE_TYPES)) {
            return ['success' => false, 'error' => 'Invalid file type. Allowed: ' . implode(', ', ALLOWED_IMAGE_TYPES)];
        }

        if ($file['size'] > MAX_FILE_SIZE) {
            return ['success' => false, 'error' => 'File exceeds ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB limit.'];
        }

        $dir = UPLOADS_PATH . '/' . $subDir;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = uniqid('', true) . '.' . $ext;
        if (move_uploaded_file($file['tmp_name'], $dir . '/' . $filename)) {
            return ['success' => true, 'filename' => $subDir . '/' . $filename];
        }

        return ['success' => false, 'error' => 'Failed to save the file.'];
    }

    /**
     * Delete a previously uploaded file.
     *
     * @param string|null $filename  Relative path within UPLOADS_PATH.
     */
    public function delete(?string $filename): void
    {
        if (!$filename) {
            return;
        }
        $path = UPLOADS_PATH . '/' . $filename;
        if (file_exists($path)) {
            unlink($path);
        }
    }
}
