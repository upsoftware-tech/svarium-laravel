<?php

namespace Upsoftware\Svarium\Panel;

use Illuminate\Database\Eloquent\Model;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;
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
            $typeNames = $this->resolveTypeNames($parameter->getType());

            // PanelContext is already passed as the first argument by operation executors.
            if (in_array(PanelContext::class, $typeNames, true)) {
                continue;
            }

            if (in_array(PanelInput::class, $typeNames, true)) {
                $args[] = $context->input;

                continue;
            }

            if ($this->acceptsModel($typeNames)) {
                if (! empty($context->params)) {
                    $value = reset($context->params);
                    $modelType = $this->resolveModelType($typeNames);

                    $args[] = $modelType === Model::class
                        ? $this->resolveGenericModel($operation, $value)
                        : $modelType::findOrFail($value);

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

    /**
     * @return list<string>
     */
    protected function resolveTypeNames(?ReflectionType $type): array
    {
        if ($type instanceof ReflectionNamedType) {
            return [$type->getName()];
        }

        if ($type instanceof ReflectionUnionType) {
            return array_values(array_map(
                static fn (ReflectionNamedType $namedType): string => $namedType->getName(),
                array_filter(
                    $type->getTypes(),
                    static fn ($namedType): bool => $namedType instanceof ReflectionNamedType,
                ),
            ));
        }

        return [];
    }

    /**
     * @param  list<string>  $typeNames
     */
    protected function acceptsModel(array $typeNames): bool
    {
        foreach ($typeNames as $typeName) {
            if ($typeName === Model::class || (class_exists($typeName) && is_subclass_of($typeName, Model::class))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $typeNames
     */
    protected function resolveModelType(array $typeNames): string
    {
        foreach ($typeNames as $typeName) {
            if ($typeName === Model::class || (class_exists($typeName) && is_subclass_of($typeName, Model::class))) {
                return $typeName;
            }
        }

        return Model::class;
    }
}
