<?php

namespace Upsoftware\Svarium\Console\Commands;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Upsoftware\Svarium\Console\Commands\Concerns\InteractsWithTenantTenancy;

class MakeTenantSeederCommand extends CoreCommand
{
    use InteractsWithTenantTenancy;

    protected $signature = 'svarium:make.tenant.seeder
        {name : Seeder class name (example: DemoTenantSeeder or Billing/InvoiceSeeder)}
        {--path= : Override tenant seeders path}
        {--namespace= : Override tenant seeders namespace}';

    protected $description = 'Create a tenant seeder in the configured tenant seeders directory';
    protected $descriptionKey = 'make.tenant.seeder';

    public function handle(): int
    {
        $inputName = trim((string) $this->argument('name'));

        if ($inputName === '') {
            $this->error('Seeder name cannot be empty.');
            return self::FAILURE;
        }

        $segments = preg_split('/[\\\\\/]+/', $inputName) ?: [];
        $segments = array_values(array_filter(array_map('trim', $segments)));

        if ($segments === []) {
            $this->error('Seeder name cannot be empty.');
            return self::FAILURE;
        }

        $class = Str::studly((string) array_pop($segments));

        if (! Str::endsWith($class, 'Seeder')) {
            $class .= 'Seeder';
        }

        $subNamespaceSegments = array_map(fn (string $segment) => Str::studly($segment), $segments);

        $pathOption = $this->option('path');
        $namespaceOption = $this->option('namespace');

        $basePath = $this->tenantSeedersPath(is_string($pathOption) ? $pathOption : null);
        $baseNamespace = $this->tenantSeederNamespace(is_string($namespaceOption) ? $namespaceOption : null);

        $directory = $basePath;
        if ($subNamespaceSegments !== []) {
            $directory .= DIRECTORY_SEPARATOR.implode(DIRECTORY_SEPARATOR, $subNamespaceSegments);
        }

        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true, true);
        }

        $file = $directory.DIRECTORY_SEPARATOR.$class.'.php';

        if (File::exists($file)) {
            $this->error("Seeder [{$class}] already exists: {$file}");
            return self::FAILURE;
        }

        $namespace = $baseNamespace;
        if ($subNamespaceSegments !== []) {
            $namespace .= '\\'.implode('\\', $subNamespaceSegments);
        }

        File::put($file, $this->renderSeeder($namespace, $class));

        $this->info("Tenant seeder {$class} created.");
        $this->line("Path: {$file}");

        return self::SUCCESS;
    }

    protected function renderSeeder(string $namespace, string $class): string
    {
        return <<<PHP
<?php

namespace {$namespace};

use Illuminate\Database\Seeder;

class {$class} extends Seeder
{
    public function run(): void
    {
        //
    }
}
PHP;
    }
}
