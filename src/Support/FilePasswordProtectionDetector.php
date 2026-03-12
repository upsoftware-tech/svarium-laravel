<?php

namespace Upsoftware\Svarium\Support;

use Illuminate\Http\UploadedFile;
use ZipArchive;

class FilePasswordProtectionDetector
{
    public function isPasswordProtected(UploadedFile $file): bool
    {
        $path = $this->resolvePath($file);
        if ($path === '') {
            return false;
        }

        $extension = $this->resolveExtension($file);

        return match ($extension) {
            'xlsx', 'xlsm', 'xltx', 'xltm' => $this->isOpenXmlSpreadsheetPasswordProtected($path),
            'ods' => $this->isOdsPasswordProtected($path),
            'pdf' => $this->isPdfPasswordProtected($path),
            default => false,
        };
    }

    protected function resolvePath(UploadedFile $file): string
    {
        $realPath = $file->getRealPath();
        if (is_string($realPath) && $realPath !== '') {
            return $realPath;
        }

        $pathname = $file->getPathname();
        if (is_string($pathname) && $pathname !== '') {
            return $pathname;
        }

        return '';
    }

    protected function resolveExtension(UploadedFile $file): string
    {
        $extension = strtolower(trim((string) $file->getClientOriginalExtension()));
        if ($extension !== '') {
            return $extension;
        }

        $name = strtolower(trim((string) $file->getClientOriginalName()));

        return strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
    }

    protected function isOpenXmlSpreadsheetPasswordProtected(string $path): bool
    {
        if (! class_exists(ZipArchive::class)) {
            return false;
        }

        $zip = new ZipArchive();
        $openResult = $zip->open($path);

        if ($openResult === true) {
            $hasEncryptedPackage = $zip->locateName('EncryptedPackage', ZipArchive::FL_NODIR) !== false;
            $hasEncryptionInfo = $zip->locateName('EncryptionInfo', ZipArchive::FL_NODIR) !== false;
            $zip->close();

            return $hasEncryptedPackage || $hasEncryptionInfo;
        }

        // Zaszyfrowany plik Office najczęściej nie otwiera się jako ZIP i ma nagłówek OLE.
        return $this->hasOleHeader($path);
    }

    protected function isOdsPasswordProtected(string $path): bool
    {
        if (! class_exists(ZipArchive::class)) {
            return false;
        }

        $zip = new ZipArchive();
        $openResult = $zip->open($path);
        if ($openResult !== true) {
            return false;
        }

        $manifest = $zip->getFromName('META-INF/manifest.xml');
        $zip->close();

        if (! is_string($manifest) || $manifest === '') {
            return false;
        }

        return stripos($manifest, 'manifest:encryption-data') !== false;
    }

    protected function isPdfPasswordProtected(string $path): bool
    {
        $handle = @fopen($path, 'rb');
        if (! is_resource($handle)) {
            return false;
        }

        $chunk = @fread($handle, 1024 * 1024);
        @fclose($handle);

        if (! is_string($chunk) || $chunk === '') {
            return false;
        }

        return strpos($chunk, '/Encrypt') !== false;
    }

    protected function hasOleHeader(string $path): bool
    {
        $handle = @fopen($path, 'rb');
        if (! is_resource($handle)) {
            return false;
        }

        $header = @fread($handle, 8);
        @fclose($handle);

        if (! is_string($header) || strlen($header) < 8) {
            return false;
        }

        return $header === hex2bin('D0CF11E0A1B11AE1');
    }
}
