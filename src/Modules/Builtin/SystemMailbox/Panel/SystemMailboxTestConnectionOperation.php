<?php

namespace Upsoftware\Svarium\Modules\Builtin\SystemMailbox\Panel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\MailManager;
use Throwable;
use Upsoftware\Svarium\Enums\ExecutionMode;
use Upsoftware\Svarium\Http\OperationResult;
use Upsoftware\Svarium\Http\RedirectResult;
use Upsoftware\Svarium\Models\SystemMailbox;
use Upsoftware\Svarium\Panel\Operation;
use Upsoftware\Svarium\Panel\PanelContext;

class SystemMailboxTestConnectionOperation extends Operation
{
    public static string|array $panels = '*';

    public static function uri(): string
    {
        return 'system/mailboxes/{id}/test-connection';
    }

    public static function methods(): array
    {
        return ['GET'];
    }

    public function execution(): ExecutionMode
    {
        return ExecutionMode::ACTION;
    }

    public function authorize(PanelContext $context): bool
    {
        return app(SystemMailboxResource::class)->canEdit($context);
    }

    protected function handleAction(PanelContext $context, ...$args): OperationResult
    {
        if (! $context->isGet()) {
            abort(405);
        }

        $result = $this->call('run', $context, ...$args);

        if (! $result instanceof OperationResult) {
            throw new \RuntimeException(
                static::class.'::run() must return OperationResult.'
            );
        }

        return $result;
    }

    protected function run(PanelContext $context, string|int $id): RedirectResult
    {
        $redirect = panel_href('system/mailboxes', $context->panel()->name);
        $mailbox = $this->resolveMailbox($id);

        try {
            $config = $this->buildTransportConfig($mailbox);
            $this->validateRequiredConfig($config);

            $transport = app(MailManager::class)->createSymfonyTransport($config);

            if ($transport instanceof \Symfony\Component\Mailer\Transport\Smtp\SmtpTransport) {
                $transport->start();
                $transport->stop();
            }

            return RedirectResult::to($redirect)
                ->success(__('Connection test passed for mailbox ":name".', [
                    'name' => (string) ($mailbox->getAttribute('name') ?? ''),
                ]));
        } catch (Throwable $e) {
            $message = trim((string) $e->getMessage());
            if ($message === '') {
                $message = __('Unknown error');
            }

            return RedirectResult::to($redirect)
                ->error(__('Connection test failed for mailbox ":name": :message', [
                    'name' => (string) ($mailbox->getAttribute('name') ?? ''),
                    'message' => $message,
                ]));
        }
    }

    protected function resolveMailbox(string|int $id): Model
    {
        $modelClass = $this->modelClass();
        $mailbox = $modelClass::query()->find($id);

        if (! $mailbox instanceof Model) {
            abort(404);
        }

        return $mailbox;
    }

    protected function modelClass(): string
    {
        $configured = config('upsoftware.models.system_mailbox');

        if (is_string($configured) && $configured !== '' && class_exists($configured)) {
            return $configured;
        }

        return SystemMailbox::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildTransportConfig(Model $mailbox): array
    {
        $driver = strtolower(trim((string) ($mailbox->getAttribute('driver') ?? 'smtp')));
        if ($driver === '') {
            $driver = 'smtp';
        }

        $config = [
            'transport' => $driver,
        ];

        $host = trim((string) ($mailbox->getAttribute('host') ?? ''));
        if ($host !== '') {
            $config['host'] = $host;
        }

        $port = (int) ($mailbox->getAttribute('port') ?? 0);
        if ($port > 0) {
            $config['port'] = $port;
        }

        $encryption = strtolower(trim((string) ($mailbox->getAttribute('encryption') ?? '')));
        if ($encryption !== '') {
            $config['scheme'] = in_array($encryption, ['ssl', 'smtps'], true) ? 'smtps' : 'smtp';
        }

        $username = trim((string) ($mailbox->getAttribute('username') ?? ''));
        if ($username !== '') {
            $config['username'] = $username;
        }

        $password = (string) ($mailbox->getAttribute('password') ?? '');
        if (trim($password) !== '') {
            $config['password'] = $password;
        }

        $additional = $mailbox->getAttribute('config');
        if (is_array($additional) && $additional !== []) {
            $config = array_replace_recursive($config, $additional);
        }

        return $config;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function validateRequiredConfig(array $config): void
    {
        $driver = strtolower(trim((string) ($config['transport'] ?? 'smtp')));

        if ($driver !== 'smtp') {
            return;
        }

        $host = trim((string) ($config['host'] ?? ''));

        if ($host === '') {
            throw new \RuntimeException(__('SMTP host is required for connection test.'));
        }
    }
}

