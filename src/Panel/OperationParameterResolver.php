<?php

namespace Upsoftware\Svarium\Panel;

use Illuminate\Database\Eloquent\Model;
use Upsoftware\Svarium\Enums\ExecutionMode;

class OperationParameterResolver
{
    protected function resolveTargetMethod(Operation $operation, PanelContext $context): string
    {
        return match ($operation->execution()) {

            ExecutionMode::ACTION => 'run',

            ExecutionMode::FORM => $context->isPost() ? 'save' : 'schema',

            ExecutionMode::DUPLICATE => $context->isPost() ? 'save' : 'schema',

            ExecutionMode::TABLE => 'table',

            ExecutionMode::VIEW => 'render',

            default => 'render',
        };
    }

    protected function resolveGenericModel($operation, $id)
    {
        if (method_exists($operation, 'getResourceClass')) {

            $resourceClass = $operation->getResourceClass();
            $resource = app($resourceClass);

            $modelClass = $resource::model();

            return $modelClass::findOrFail($id);
        }

        abort(500, 'Cannot resolve generic model.');
    }

    public function resolve(Operation $operation, PanelContext $context): array
    {
        $method = $this->resolveTargetMethod($operation, $context);

        $reflection = new \ReflectionMethod($operation, $method);
        $args = [];

        foreach ($reflection->getParameters() as $parameter) {

            $type = $parameter->getType()?->getName();

            if ($type === PanelContext::class) {
                $args[] = $context;

                continue;
            }

            if ($type === PanelInput::class) {
                $args[] = $context->input;

                continue;
            }

            if ($type && (
                $type === Model::class ||
                is_subclass_of($type, Model::class)
            )
            ) {
                if (! empty($context->params)) {

                    $value = reset($context->params);

                    $args[] = $type === Model::class
                        ? $this->resolveGenericModel($operation, $value)
                        : $type::findOrFail($value);

                    continue;
                }
            }

            $name = $parameter->getName();

            if (isset($context->params[$name])) {
                $args[] = $context->params[$name];

                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $args[] = $parameter->getDefaultValue();

                continue;
            }

            $args[] = null;
        }

        return $args;
    }
}
