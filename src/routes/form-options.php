<?php

use Illuminate\Support\Facades\Route;
use Upsoftware\Svarium\Http\Controllers\FormModelOptionsController;

$path = trim((string) config('upsoftware.form.select_options.path', 'svarium/form/options/model'), '/');
if ($path === '') {
    return;
}

$middleware = array_values(array_filter(array_map(
    static fn (mixed $item): string => trim((string) $item),
    (array) config('upsoftware.form.select_options.middleware', ['auth'])
), static fn (string $item): bool => $item !== ''));

Route::middleware($middleware)
    ->get($path, FormModelOptionsController::class)
    ->name('svarium.form.select-options.model');

