<?php

namespace Upsoftware\Svarium\Panel\Operations\Auth;

use Upsoftware\Svarium\Enums\ExecutionMode;
use Upsoftware\Svarium\Http\OperationResult;
use Upsoftware\Svarium\Http\RedirectResult;
use Upsoftware\Svarium\Panel\PanelContext;

class VerificationResendOperation extends VerificationOperation
{
    public static function uri(): string
    {
        return 'auth/{type}/verification/{userAuth}/resend';
    }

    public static function methods(): array
    {
        return ['GET'];
    }

    public function execution(): ExecutionMode
    {
        return ExecutionMode::ACTION;
    }

    protected function handleAction(PanelContext $context, ...$args): OperationResult
    {
        if (! $context->isGet()) {
            abort(405);
        }

        $result = $this->call('run', $context, ...$args);

        if (! $result instanceof OperationResult) {
            throw new \RuntimeException(
                static::class . '::run() must return OperationResult.'
            );
        }

        return $result;
    }

    protected function run(PanelContext $context): RedirectResult
    {
        $type = $this->resolveType($context);
        $userAuth = $this->resolveUserAuth($context);
        $cooldownSeconds = $this->resendCooldownSecondsFromLastCode($userAuth);

        if ($cooldownSeconds > 0) {
            return RedirectResult::to($this->verificationUrl($type, $userAuth))
                ->warning(__('Too many resend requests. Try again in :seconds seconds.', ['seconds' => $cooldownSeconds]));
        }

        $limitSeconds = $this->resendRateLimitSeconds($context, $userAuth);

        if ($limitSeconds > 0) {
            return RedirectResult::to($this->verificationUrl($type, $userAuth))
                ->warning(__('Too many resend requests. Try again in :seconds seconds.', ['seconds' => $limitSeconds]));
        }

        $this->hitResendRateLimit($context, $userAuth);

        if (! $this->resendVerificationCode($userAuth, $type)) {
            return RedirectResult::to($this->methodUrl($type, $userAuth))
                ->warning(__('Selected verification method is not available.'));
        }

        return RedirectResult::to($this->verificationUrl($type, $userAuth))
            ->success(__('A new verification code has been sent.'));
    }
}
