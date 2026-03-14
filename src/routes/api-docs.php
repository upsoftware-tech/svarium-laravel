<?php

use Illuminate\Support\Facades\Route;
use Upsoftware\Svarium\Http\Controllers\ApiDocsController;

if (! (bool) config('upsoftware.api.docs.enabled', true)) {
    return;
}

$docsPath = trim((string) config('upsoftware.api.docs.path', 'api/docs'), '/');
$specPath = trim((string) config('upsoftware.api.docs.spec_path', 'api/openapi.json'), '/');

if ($docsPath === '' || $specPath === '') {
    return;
}

$middleware = array_values(array_filter(array_map(
    static fn (mixed $item): string => trim((string) $item),
    (array) config('upsoftware.api.docs.middleware', [])
), static fn (string $item): bool => $item !== ''));

if (! (bool) config('upsoftware.api.docs.public', true)) {
    $middleware[] = 'auth';
}

$middleware = array_values(array_unique($middleware));

Route::middleware($middleware)->group(function () use ($docsPath, $specPath): void {
    Route::get($docsPath, [ApiDocsController::class, 'docs'])->name('svarium.api.docs');
    Route::get($specPath, [ApiDocsController::class, 'spec'])->name('svarium.api.docs.spec');
});

