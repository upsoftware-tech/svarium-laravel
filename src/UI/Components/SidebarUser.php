<?php

namespace Upsoftware\Svarium\UI\Components;

use Illuminate\Support\Facades\Route;
use Throwable;
use Upsoftware\Svarium\Menu\MenuRegistry;
use Upsoftware\Svarium\UI\Component;

class SidebarUser extends Component
{
    public function user(mixed $user): static
    {
        if ($user instanceof \Illuminate\Contracts\Support\Arrayable) {
            $user = $user->toArray();
        } elseif (is_object($user) && method_exists($user, 'toArray')) {
            $user = $user->toArray();
        }

        return $this->prop('user', is_array($user) ? $user : null);
    }

    public function name(string $name): static
    {
        return $this->prop('name', $name);
    }

    public function email(string $email): static
    {
        return $this->prop('email', $email);
    }

    public function avatar(string $avatar): static
    {
        return $this->prop('avatar', $avatar);
    }

    public function themeToggle(bool $enabled = true): static
    {
        return $this->prop('themeToggle', $enabled);
    }

    public function locale(bool $enabled = true): static
    {
        return $this->prop('locale', $enabled);
    }

    public function twoFactor(bool $enabled = true): static
    {
        return $this->prop('twoFactor', $enabled);
    }

    public function activityLog(bool $enabled = true): static
    {
        return $this->prop('activityLog', $enabled);
    }

    public function logout(bool $enabled = true): static
    {
        return $this->prop('logout', $enabled);
    }

    public function menu(bool $enabled = true): static
    {
        return $this->prop('menu', $enabled);
    }

    public function menuItems(array $items): static
    {
        return $this->prop('menuItems', $items);
    }

    public function menuNavigationId(string|int $navigationId): static
    {
        return $this->prop('menuNavigationId', $navigationId);
    }

    public function toArray(): array
    {
        $array = parent::toArray();
        $props = $array['props'] ?? [];

        if (! is_array($props)) {
            $props = [];
        }

        $authUser = $this->resolveAuthUserPayload();

        if ($authUser !== null && ! array_key_exists('user', $props)) {
            $props['user'] = $authUser;
        }

        if ($authUser !== null && ! array_key_exists('name', $props)) {
            $props['name'] = (string) ($authUser['name'] ?? '');
        }

        if ($authUser !== null && ! array_key_exists('email', $props)) {
            $props['email'] = (string) ($authUser['email'] ?? '');
        }

        if ($authUser !== null && ! array_key_exists('avatar', $props)) {
            $avatar = trim((string) ($authUser['avatar'] ?? ''));

            if ($avatar !== '') {
                $props['avatar'] = $avatar;
            }
        }

        if (! array_key_exists('menu', $props)) {
            $props['menu'] = (bool) config('upsoftware.ui.sidebar_user.menu_enabled', true);
        }

        if (($props['menu'] ?? false) === true && ! array_key_exists('menuItems', $props)) {
            $navigationId = $props['menuNavigationId']
                ?? config('upsoftware.ui.sidebar_user.menu_navigation_id', 'sidebar_user');

            $props['menuNavigationId'] = $navigationId;
            $props['menuItems'] = $this->resolveMenuItems(
                is_string($navigationId) || is_int($navigationId) ? $navigationId : null
            );
        }

        $array['props'] = $props;

        return $array;
    }

    protected function resolveAuthUserPayload(): ?array
    {
        try {
            if (! function_exists('auth') || ! auth()->check()) {
                return null;
            }

            $user = auth()->user();
            if (! $user) {
                return null;
            }

            $firstName = trim((string) $this->attributeValue($user, 'first_name'));
            $lastName = trim((string) $this->attributeValue($user, 'last_name'));

            $name = trim((string) $this->attributeValue($user, 'name'));
            if ($name === '') {
                $name = trim($firstName.' '.$lastName);
            }

            $email = trim((string) $this->attributeValue($user, 'email'));
            $avatar = trim((string) (
                $this->attributeValue($user, 'avatar_url')
                ?: $this->attributeValue($user, 'avatar')
                ?: $this->attributeValue($user, 'photo')
                ?: $this->attributeValue($user, 'profile_photo_url')
            ));

            return [
                'id' => $this->attributeValue($user, 'id'),
                'name' => $name,
                'email' => $email,
                'avatar' => $avatar,
                'first_name' => $firstName,
                'last_name' => $lastName,
            ];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function resolveMenuItems(string|int|null $navigationId): array
    {
        try {
            $items = app(MenuRegistry::class)->allForNavigation($navigationId);
        } catch (Throwable) {
            return [];
        }

        $resolved = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $type = strtolower(trim((string) ($item['type'] ?? 'item')));
            if ($type !== 'item') {
                continue;
            }

            $url = $this->resolveMenuItemUrl($item);
            if ($url === null) {
                continue;
            }

            $label = trim((string) ($item['label'] ?? ''));
            if ($label === '') {
                continue;
            }

            $path = is_array($item['path'] ?? null)
                ? array_values(array_filter(array_map(
                    static fn (mixed $segment): string => trim((string) $segment),
                    $item['path']
                ), static fn (string $segment): bool => $segment !== ''))
                : [];

            $resolved[] = [
                'key' => (string) ($item['key'] ?? sha1($label.'|'.$url)),
                'label' => $label,
                'path' => $path,
                'icon' => trim((string) ($item['icon'] ?? '')),
                'url' => $url,
                'order' => (int) ($item['order'] ?? 0),
            ];
        }

        usort($resolved, static function (array $left, array $right): int {
            $leftOrder = (int) ($left['order'] ?? 0);
            $rightOrder = (int) ($right['order'] ?? 0);

            if ($leftOrder !== $rightOrder) {
                return $leftOrder <=> $rightOrder;
            }

            return strcmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? ''));
        });

        return $resolved;
    }

    protected function resolveMenuItemUrl(array $item): ?string
    {
        $routeName = trim((string) ($item['route_name'] ?? ''));
        if ($routeName !== '' && Route::has($routeName)) {
            try {
                return route($routeName, [], false);
            } catch (Throwable) {
                // ignore invalid route parameters
            }
        }

        $url = trim((string) ($item['url'] ?? ''));
        if ($url !== '') {
            return $url;
        }

        return null;
    }

    protected function attributeValue(mixed $user, string $key): mixed
    {
        if (is_object($user)) {
            if (method_exists($user, 'getAttribute')) {
                return $user->getAttribute($key);
            }

            if (isset($user->{$key})) {
                return $user->{$key};
            }
        }

        if (is_array($user)) {
            return $user[$key] ?? null;
        }

        return null;
    }
}
