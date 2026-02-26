<?php

namespace Upsoftware\Svarium\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;
use Upsoftware\Svarium\Traits\HasHash;
use Upsoftware\Svarium\Traits\HasSetting;
use Upsoftware\Svarium\Traits\UsesConnection;

class Navigation extends Model
{
    use UsesConnection, HasTranslations, HasSetting, HasHash;

    protected $fillable = ['label', 'icon', 'route_name', 'parent_id', 'order', 'permission', 'status', 'position', 'type'];

    public array $translatable = ['label'];

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('order');
    }

    public static function getTree()
    {
        return self::whereNull('parent_id')
            ->with('children')
            ->where('is_active', true)
            ->orderBy('order')
            ->get();
    }

    public function getNavigationOptions()
    {
        $options = [];
        $options[NULL] = "BRAK NADRZEDNEGO";

        $allNavigations = Navigation::orderBy('order')->get();

        $buildTree = function ($items, $parentId = null, $depth = 0) use (&$options, &$buildTree) {
            foreach ($items->where('parent_id', $parentId) as $item) {
                // Pobieramy label (JSON lub String)
                $label = is_array($item->label) ? ($item->label['pl'] ?? '') : $item->label;

                // Tworzymy prefiks: poziom 0 = brak, poziom 1 = |-- , poziom 2 = |----
                $prefix = $depth > 0 ? '|' . str_repeat('--', $depth) . ' ' : '';

                // Zapisujemy: [ID] => "|-- Nazwa"
                $options[$item->id] = $prefix . mb_strtoupper($label);

                // Wywołujemy dla dzieci, zwiększając głębokość
                $buildTree($items, $item->id, $depth + 1);
            }
        };

        $buildTree($allNavigations);

        return $options;
    }
}
