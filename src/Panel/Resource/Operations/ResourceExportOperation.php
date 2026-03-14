<?php

namespace Upsoftware\Svarium\Panel\Resource\Operations;

use Upsoftware\Svarium\Panel\PanelContext;
use Upsoftware\Svarium\Panel\Table\TableBuilder;

class ResourceExportOperation extends ResourceListOperation
{
    public function authorize(PanelContext $context): bool
    {
        if (! parent::authorize($context)) {
            return false;
        }

        $resource = $this->resource();
        if (method_exists($resource, 'canExport') && ! $resource->canExport($context)) {
            return false;
        }

        $table = $this->table($context);
        if (! $table instanceof TableBuilder) {
            return false;
        }

        return $table->isExportEnabled();
    }

    public function table(PanelContext $context): ?TableBuilder
    {
        $resource = $this->resource();

        if (method_exists($resource, 'exportTitle')) {
            $this->applyTitleIfEmpty($resource->exportTitle($context));
        }

        $table = parent::table($context);

        if ($table instanceof TableBuilder) {
            $table->imported(false);
            $table->exportUrl(null);
        }

        return $table;
    }
}
