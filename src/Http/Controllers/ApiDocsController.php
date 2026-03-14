<?php

namespace Upsoftware\Svarium\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Upsoftware\Svarium\Services\Api\OpenApiGenerator;

class ApiDocsController extends Controller
{
    public function docs(Request $request, OpenApiGenerator $generator): Response
    {
        if (! (bool) config('upsoftware.api.docs.enabled', true)) {
            abort(404);
        }

        $refresh = $this->toBool($request->query('refresh', false));
        if ($refresh || (bool) config('upsoftware.api.docs.auto_generate', true)) {
            $generator->generateAndStore();
        }

        $title = trim((string) config('upsoftware.api.docs.title', ''));
        if ($title === '') {
            $title = trim((string) config('app.name', 'Svarium')).' API';
        }

        $specPath = '/'.trim((string) config('upsoftware.api.docs.spec_path', 'api/openapi.json'), '/');
        $specUrl = url($specPath);

        $html = <<<'HTML'
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{TITLE}}</title>
  <style>
    body { margin: 0; padding: 0; }
  </style>
</head>
<body>
  <redoc spec-url="{{SPEC_URL}}"></redoc>
  <script src="https://cdn.redoc.ly/redoc/latest/bundles/redoc.standalone.js"></script>
</body>
</html>
HTML;

        $html = strtr($html, [
            '{{TITLE}}' => e($title),
            '{{SPEC_URL}}' => e($specUrl),
        ]);

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }

    public function spec(Request $request, OpenApiGenerator $generator): Response
    {
        if (! (bool) config('upsoftware.api.docs.enabled', true)) {
            abort(404);
        }

        $refresh = $this->toBool($request->query('refresh', false));
        $path = $generator->resolvedStoragePath();

        if ($refresh || (bool) config('upsoftware.api.docs.auto_generate', true) || ! is_file($path)) {
            $path = $generator->generateAndStore();
        }

        if (! is_file($path)) {
            throw new RuntimeException('OpenAPI specification file not found.');
        }

        $content = file_get_contents($path);
        if (! is_string($content) || trim($content) === '') {
            throw new RuntimeException('OpenAPI specification is empty.');
        }

        return response($content, 200, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    protected function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (bool) $value;
        }

        $normalized = strtolower(trim((string) $value));

        if (in_array($normalized, ['1', 'true', 'on', 'yes'], true)) {
            return true;
        }

        if (in_array($normalized, ['0', 'false', 'off', 'no'], true)) {
            return false;
        }

        return $normalized !== '';
    }
}

