<?php

namespace Upsoftware\Svarium\UI\Components;

use Illuminate\Contracts\Support\Arrayable;
use Upsoftware\Svarium\UI\Component;

class BookingCalendar extends FieldComponent
{
    public function resources(array|Arrayable $resources): static
    {
        if ($resources instanceof Arrayable) {
            $resources = $resources->toArray();
        }

        return $this->prop('resources', array_values($resources));
    }

    public function dates(array|Arrayable $dates): static
    {
        if ($dates instanceof Arrayable) {
            $dates = $dates->toArray();
        }

        return $this->prop('dates', array_values($dates));
    }

    public function bookings(array|Arrayable $bookings): static
    {
        if ($bookings instanceof Arrayable) {
            $bookings = $bookings->toArray();
        }

        return $this->prop('bookings', array_values($bookings));
    }

    public function startDate(string $date): static
    {
        return $this->prop('startDate', trim($date));
    }

    public function days(int $days): static
    {
        return $this->prop('days', max(1, min($days, 730)));
    }

    public function leftWidth(int|string $width): static
    {
        return $this->prop('leftWidth', $width);
    }

    public function columnWidth(int|string $width): static
    {
        return $this->prop('columnWidth', $width);
    }

    public function rowHeight(int|string $height): static
    {
        return $this->prop('rowHeight', $height);
    }

    public function resourceHeaderLabel(string $label): static
    {
        return $this->prop('resourceHeaderLabel', trim($label));
    }

    public function emptyLabel(string $label): static
    {
        return $this->prop('emptyLabel', trim($label));
    }

    public function showWeekends(bool $enabled = true): static
    {
        return $this->prop('showWeekends', $enabled);
    }

    public function resourceKey(string $key): static
    {
        return $this->prop('resourceKey', trim($key));
    }

    public function resourceLabelKey(string $key): static
    {
        return $this->prop('resourceLabelKey', trim($key));
    }

    public function resourceChildrenKey(string $key): static
    {
        return $this->prop('resourceChildrenKey', trim($key));
    }

    public function resourceTypeKey(string $key): static
    {
        return $this->prop('resourceTypeKey', trim($key));
    }

    public function resourceIconKey(string $key): static
    {
        return $this->prop('resourceIconKey', trim($key));
    }

    public function bookingResourceKey(string $key): static
    {
        return $this->prop('bookingResourceKey', trim($key));
    }

    public function bookingStartKey(string $key): static
    {
        return $this->prop('bookingStartKey', trim($key));
    }

    public function bookingEndKey(string $key): static
    {
        return $this->prop('bookingEndKey', trim($key));
    }

    public function bookingLabelKey(string $key): static
    {
        return $this->prop('bookingLabelKey', trim($key));
    }

    public function bookingColorKey(string $key): static
    {
        return $this->prop('bookingColorKey', trim($key));
    }

    public function bookingUrlKey(string $key): static
    {
        return $this->prop('bookingUrlKey', trim($key));
    }

    public function draggable(bool $enabled = true): static
    {
        return $this->prop('draggable', $enabled);
    }

    public function resizable(bool $enabled = true): static
    {
        return $this->prop('resizable', $enabled);
    }

    public function moveUrl(string $url): static
    {
        return $this->prop('moveUrl', trim($url));
    }

    public function resizeUrl(string $url): static
    {
        return $this->prop('resizeUrl', trim($url));
    }

    public function monthHeader(bool $enabled = true): static
    {
        return $this->prop('monthHeader', $enabled);
    }

    public function todayLine(bool $enabled = true): static
    {
        return $this->prop('todayLine', $enabled);
    }

    public function collapsible(bool $enabled = true): static
    {
        return $this->prop('collapsible', $enabled);
    }

    public function confirmed(bool $enabled = true): static
    {
        return $this->prop('confirmed', $enabled);
    }

    public function selectable(bool $enabled = true): static
    {
        return $this->prop('selectable', $enabled);
    }

    public function selected(Component|array|string|\Closure|null $content): static
    {
        $this->prop('selectable', true);

        return $this->slot('selected', $content);
    }

    public function selectedTitle(string $title): static
    {
        return $this->prop('selectedTitle', trim($title));
    }

    public function selectedDescription(string $description): static
    {
        return $this->prop('selectedDescription', trim($description));
    }

    public function selectedHeader(Component|array|string|\Closure|null $content): static
    {
        return $this->slot('selectedHeader', $content);
    }

    public function selectedFooter(Component|array|string|\Closure|null $content): static
    {
        return $this->slot('selectedFooter', $content);
    }

    public function statuses(array $statuses): static
    {
        $normalized = [];

        foreach ($statuses as $status) {
            if (is_array($status) && count($status) >= 3) {
                $normalized[] = [
                    'label' => (string) $status[0],
                    'value' => (string) $status[1],
                    'color' => (string) $status[2],
                ];
            }
        }

        return $this->prop('statuses', $normalized);
    }

    public function model(string $model): static
    {
        return $this->prop('model', $model);
    }

    public function bookingStatusKey(string $key): static
    {
        return $this->prop('bookingStatusKey', trim($key));
    }

    public function toArray(): array
    {
        if (! is_array($this->getProp('resources'))) {
            $this->prop('resources', []);
        }

        if (! is_array($this->getProp('dates'))) {
            $this->prop('dates', []);
        }

        if (! is_array($this->getProp('bookings'))) {
            $this->prop('bookings', []);
        }

        $startDate = trim((string) ($this->getProp('startDate') ?? ''));
        if ($startDate === '') {
            $startDate = now()->toDateString();
        }

        $this->prop('startDate', $startDate);
        $this->prop('days', max(1, min((int) ($this->getProp('days') ?? 14), 730)));
        $this->prop('leftWidth', $this->getProp('leftWidth') ?? 280);
        $this->prop('columnWidth', $this->getProp('columnWidth') ?? 40);
        $this->prop('rowHeight', $this->getProp('rowHeight') ?? 36);
        $this->prop('resourceHeaderLabel', trim((string) ($this->getProp('resourceHeaderLabel') ?? __('Apartments'))));
        $this->prop('emptyLabel', trim((string) ($this->getProp('emptyLabel') ?? __('No resources'))));
        $this->prop('showWeekends', (bool) ($this->getProp('showWeekends') ?? true));

        $this->prop('resourceKey', trim((string) ($this->getProp('resourceKey') ?? 'value')) ?: 'value');
        $this->prop('resourceLabelKey', trim((string) ($this->getProp('resourceLabelKey') ?? 'label')) ?: 'label');
        $this->prop('resourceChildrenKey', trim((string) ($this->getProp('resourceChildrenKey') ?? 'children')) ?: 'children');
        $this->prop('resourceTypeKey', trim((string) ($this->getProp('resourceTypeKey') ?? 'type')) ?: 'type');
        $this->prop('resourceIconKey', trim((string) ($this->getProp('resourceIconKey') ?? 'icon')) ?: 'icon');
        $this->prop('bookingResourceKey', trim((string) ($this->getProp('bookingResourceKey') ?? 'resource')) ?: 'resource');
        $this->prop('bookingStartKey', trim((string) ($this->getProp('bookingStartKey') ?? 'start')) ?: 'start');
        $this->prop('bookingEndKey', trim((string) ($this->getProp('bookingEndKey') ?? 'end')) ?: 'end');
        $this->prop('bookingLabelKey', trim((string) ($this->getProp('bookingLabelKey') ?? 'label')) ?: 'label');
        $this->prop('bookingColorKey', trim((string) ($this->getProp('bookingColorKey') ?? 'color')) ?: 'color');
        $this->prop('bookingUrlKey', trim((string) ($this->getProp('bookingUrlKey') ?? 'url')) ?: 'url');

        $this->prop('draggable', (bool) ($this->getProp('draggable') ?? false));
        $this->prop('resizable', (bool) ($this->getProp('resizable') ?? false));
        $this->prop('moveUrl', trim((string) ($this->getProp('moveUrl') ?? '')));
        $this->prop('resizeUrl', trim((string) ($this->getProp('resizeUrl') ?? '')));
        $this->prop('monthHeader', (bool) ($this->getProp('monthHeader') ?? true));
        $this->prop('todayLine', (bool) ($this->getProp('todayLine') ?? true));
        $this->prop('collapsible', (bool) ($this->getProp('collapsible') ?? true));
        $this->prop('confirmed', (bool) ($this->getProp('confirmed') ?? false));
        $this->prop('selectable', (bool) ($this->getProp('selectable') ?? false));
        $this->prop('selectedTitle', trim((string) ($this->getProp('selectedTitle') ?? '')));
        $this->prop('selectedDescription', trim((string) ($this->getProp('selectedDescription') ?? '')));

        if (! is_array($this->getProp('statuses'))) {
            $this->prop('statuses', []);
        }

        $this->prop('bookingStatusKey', trim((string) ($this->getProp('bookingStatusKey') ?? 'status')) ?: 'status');

        return parent::toArray();
    }
}
