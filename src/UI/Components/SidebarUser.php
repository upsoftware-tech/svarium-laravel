<?php

namespace Upsoftware\Svarium\UI\Components;

use Upsoftware\Svarium\UI\Component;

class SidebarUser extends Component
{
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
}
