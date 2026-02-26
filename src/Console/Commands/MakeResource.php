<?php

namespace Upsoftware\Svarium\Console\Commands;

use Illuminate\Console\Command;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\text;
use Illuminate\Support\Str;
use File;

class MakeResource extends Command
{
    protected $signature = 'svarium:make.resource {resource?}';
    protected $description = 'Create Svarium Resource';
    protected string $resourceDir;
    protected string $resourceDirPages;
    protected string $resourceDirPagesPanel;
    protected string $resourceDirPagesWeb;
    protected string $resourceDirPagesApi;
    protected string $resourceDirSchemas;
    protected string $resourceDirHelpers;
    protected string $resourceDirComponents;

    protected string $resourceName;
    protected string $resourceNamePlural;

    public function replaceStub($content): string
    {
        $replace['{{ResourceName}}'] = Str::studly($this->resourceName);
        $replace['{{resource-name}}'] = Str::slug($this->resourceName);
        $replace['{{ResourceNamePlural}}'] = Str::plural($replace['{{ResourceName}}'] );

        return strtr($content, $replace);
    }

    protected function makeDirs(): void
    {
        File::makeDirectory($this->resourceDir);
        File::makeDirectory($this->resourceDirPages);
        File::makeDirectory($this->resourceDirPagesPanel);
        File::makeDirectory($this->resourceDirPagesWeb);
        File::makeDirectory($this->resourceDirPagesApi);
        File::makeDirectory($this->resourceDirSchemas);
        File::makeDirectory($this->resourceDirHelpers);
        File::makeDirectory($this->resourceDirComponents);
    }

    public function handle() {
        $resource = $this->argument('resource');
        while(!$resource || strlen($resource) < 3) {
            $resource = text(__('Set name resource (min. 3 characters)', __('E.g. Pages')));
        }

        if (!is_dir(svarium_resources())) {
            File::makeDirectory(svarium_resources(), 0755, true);
        }

        $this->resourceName = $resource;
        $this->resourceNamePlural = Str::plural($resource);

        $this->resourceDir = svarium_resources($this->resourceName);
        $this->resourceDirPages = $this->resourceDir . DIRECTORY_SEPARATOR . 'Pages';
        $this->resourceDirPagesPanel = $this->resourceDirPages . DIRECTORY_SEPARATOR . 'Panel';
        $this->resourceDirPagesWeb = $this->resourceDirPages . DIRECTORY_SEPARATOR . 'Web';
        $this->resourceDirPagesApi = $this->resourceDirPages . DIRECTORY_SEPARATOR . 'Api';
        $this->resourceDirSchemas = $this->resourceDir . DIRECTORY_SEPARATOR . 'Schemas';
        $this->resourceDirHelpers = $this->resourceDir . DIRECTORY_SEPARATOR . 'Helpers';
        $this->resourceDirComponents = $this->resourceDir . DIRECTORY_SEPARATOR . 'Components';
        $resourceFile = $this->resourceDir . DIRECTORY_SEPARATOR . $resource.'Resource.php';
        $resourcePagePanelCreate = $this->resourceDirPagesPanel . DIRECTORY_SEPARATOR . 'Create'.$this->resourceName.'.php';
        $resourcePagePanelList = $this->resourceDirPagesPanel . DIRECTORY_SEPARATOR . 'List'.$this->resourceNamePlural.'.php';
        $resourceHelperIndex = $this->resourceDirHelpers . DIRECTORY_SEPARATOR . 'index.php';
        $resourceSchemaTable = $this->resourceDirSchemas . DIRECTORY_SEPARATOR . $this->resourceName.'Table.php';
        $resourceSchemaForm = $this->resourceDirSchemas . DIRECTORY_SEPARATOR . $this->resourceName.'Form.php';
        $stubsDir = __DIR__ . '/../..'. DIRECTORY_SEPARATOR . 'stubs' . DIRECTORY_SEPARATOR;

        if (!File::isDirectory($this->resourceDir)) {
            $this->makeDirs();
        } else {
            $this->warn(__('Directory already exists!'));
            $delete = confirm(__('Are you sure you want to delete it and create new resource?'), false, __('Yes'), __('No'));
            if ($delete) {
                if (File::deleteDirectory($this->resourceDir)) {
                    $this->info(__('Old directory deleted successfully.'));
                    $this->makeDirs();
                } else {
                    $this->error(__('Could not delete the directory. Check permissions.'));
                    return;
                }
                $this->newLine();
            } else {
                return;
            }
        }

        // STUBS
        $resourceStubFile = File::get($stubsDir.'svarium.resource.php.stub');
        $resourcePagePanelCreateStubFile = File::get($stubsDir.'page.create.php.stub');
        $resourcePagePanelListStubFile = File::get($stubsDir.'page.list.php.stub');
        $resourceSchemaTableStubFile = File::get($stubsDir.'svarium.schema.table.php.stub');
        $resourceSchemaFormStubFile = File::get($stubsDir.'svarium.schema.form.php.stub');

        File::put($resourceFile, $this->replaceStub($resourceStubFile));
        File::put($resourcePagePanelCreate, $this->replaceStub($resourcePagePanelCreateStubFile));
        File::put($resourcePagePanelList, $this->replaceStub($resourcePagePanelListStubFile));
        File::put($resourceSchemaTable, $this->replaceStub($resourceSchemaTableStubFile));
        File::put($resourceSchemaForm, $this->replaceStub($resourceSchemaFormStubFile));
        File::put($resourceHelperIndex, "<?php\n\n");
        $this->info(__('Created new resource successfully.'));
        $this->newLine();
        $this->line(__('Resource Path: :file', ['file' => $resourceFile]));
        $this->line(__('Panel Page Create: :file', ['file' => $resourcePagePanelCreate]));
        $this->line(__('Panel List Create: :file', ['file' => $resourcePagePanelList]));
        $this->line(__('Schema table: :file', ['file' => $resourceSchemaTable]));
        $this->line(__('Schema form: :file', ['file' => $resourceSchemaForm]));
        $this->line(__('Helper index: :file', ['file' => $resourceHelperIndex]));
    }
}
