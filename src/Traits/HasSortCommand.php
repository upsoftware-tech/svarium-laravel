<?php

namespace Upsoftware\Svarium\Traits;

use function Laravel\Prompts\select;

trait HasSortCommand
{
    public function sequentialSort(array $items): array
    {
        $sortedItems = [];
        $pool = $items;
        $total = count($items);

        for ($i = 1; $i <= $total; $i++) {
            $options = array_map(fn($v) => $v['native'], $pool);

            if (count($pool) === 1) {
                $key = array_key_first($pool);
                $this->info("Automatycznie przypisano pozycję #$i: " . $options[$key]);
                $sortedItems[$key] = $pool[$key];
            } else {
                $key = select("Wybierz pozycję #$i:", $options);
                $sortedItems[$key] = $pool[$key];
                unset($pool[$key]);
            }
        }

        return $sortedItems;
    }
}
