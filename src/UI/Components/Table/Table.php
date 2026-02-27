<?php

namespace Upsoftware\Svarium\UI\Components\Table;

use Illuminate\Database\Eloquent\Model;
use Upsoftware\Svarium\Enums\TableActionDisplay;
use Upsoftware\Svarium\UI\Component;
use Upsoftware\Svarium\UI\Components\Button;
use Upsoftware\Svarium\UI\Components\ButtonLink;
use Upsoftware\Svarium\UI\Components\Dropdown;
use Upsoftware\Svarium\UI\Components\DropdownItem;
use Upsoftware\Svarium\UI\Components\Icon;
use Upsoftware\Svarium\UI\Concerns\Props\HasChildren;

class Table extends Component
{
    use HasChildren;

    protected ?string $model = null;

    protected array $columns = [];

    protected array $actions = [];

    protected ?TableActionDisplay $actionDisplay = null;

    public static function make(?string $name = null): static
    {
        return new static;
    }

    /*
    |--------------------------------------------------------------------------
    | Model binding
    |--------------------------------------------------------------------------
    */

    public function model(string $modelClass): static
    {
        if (! is_subclass_of($modelClass, Model::class)) {
            throw new \InvalidArgumentException(
                "{$modelClass} must extend Eloquent Model."
            );
        }

        $this->model = $modelClass;

        $this->rows = $modelClass::query()
            ->get()
            ->map(function ($item) use ($modelClass) {
                $array = $item->toArray();
                $array['_model'] = $modelClass;

                return $array;
            })
            ->toArray();

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Action view type (inline, dropdown)
    |--------------------------------------------------------------------------
     */

    public function actionDisplay(TableActionDisplay|string $mode): static
    {
        if (is_string($mode)) {
            $mode = TableActionDisplay::tryFrom($mode);

            if (! $mode) {
                throw new \InvalidArgumentException(
                    'Invalid table action display mode.'
                );
            }
        }

        $this->actionDisplay = $mode;

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Columns
    |--------------------------------------------------------------------------
    */

    public function columns(array $columns): static
    {
        $this->columns = $columns;

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Actions
    |--------------------------------------------------------------------------
    */

    public function actions(array $actions): static
    {
        $this->actions = $actions;

        return $this;
    }

    public function sticky(array|string ...$sections): static
    {
        $normalized = [];

        foreach ($sections as $section) {
            if (is_array($section)) {
                foreach ($section as $nested) {
                    if (! is_string($nested)) {
                        continue;
                    }

                    $value = strtolower(trim($nested));
                    if (in_array($value, ['header', 'search', 'footer'], true) && ! in_array($value, $normalized, true)) {
                        $normalized[] = $value;
                    }
                }

                continue;
            }

            $value = strtolower(trim($section));
            if (in_array($value, ['header', 'search', 'footer'], true) && ! in_array($value, $normalized, true)) {
                $normalized[] = $value;
            }
        }

        return $this->prop('sticky', $normalized);
    }

    public function selected(bool $state = true): static
    {
        return $this->prop('columnSelection', $state);
    }

    protected function wrapActions(array $actions): array
    {
        if (empty($actions)) {
            return [];
        }

        if ($this->actionDisplay === TableActionDisplay::DROPDOWN) {
            $dropdown = Dropdown::make()
                ->trigger(
                    Button::make()
                        ->icon(Icon::make('lucide:ellipsis-vertical'))
                        ->variant('ghost')
                        ->size('icon-sm')
                )
                ->children(
                    array_map(function ($a) {
                        $item = DropdownItem::make();
                        foreach ($a['props'] as $key => $value) {
                            $item->prop($key, $value);
                        }
                        return $item;
                    }, $actions)
                );
            return [$dropdown];
        }

        return array_map(function ($a) {
            $button = ButtonLink::make();
            foreach ($a['props'] as $key => $value) {
                $button->prop($key, $value);
            }
            return $button;
        }, $actions);
    }

    /*
    |--------------------------------------------------------------------------
    | Serialization
    |--------------------------------------------------------------------------
    */

    public function toArray(): array
    {
        return parent::toArray();
    }
}
