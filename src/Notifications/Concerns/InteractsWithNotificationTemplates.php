<?php

namespace Upsoftware\Svarium\Notifications\Concerns;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\HtmlString;
use Throwable;
use Upsoftware\Svarium\Services\Notifications\NotificationTemplateRenderer;

trait InteractsWithNotificationTemplates
{
    /**
     * @param  array<int, string>|string  $defaultBody
     * @param  array<string, scalar|null>  $variables
     * @return array{subject:string,body:string}
     */
    protected function renderTemplateContent(
        string $defaultSubject,
        array|string $defaultBody,
        array $variables = []
    ): array {
        $defaultBodyText = is_array($defaultBody)
            ? implode("\n", array_values($defaultBody))
            : (string) $defaultBody;

        try {
            return app(NotificationTemplateRenderer::class)->render(
                static::class,
                $defaultSubject,
                $defaultBodyText,
                $variables
            );
        } catch (Throwable) {
            return [
                'subject' => $defaultSubject,
                'body' => $defaultBodyText,
            ];
        }
    }

    /**
     * @return array<int, string>
     */
    protected function splitTemplateBodyLines(string $body): array
    {
        $lines = preg_split('/\r\n|\r|\n/', (string) $body);

        if (! is_array($lines)) {
            return [];
        }

        return array_map(
            static fn (mixed $line): string => (string) $line,
            $lines
        );
    }

    protected function appendTemplateParagraphLines(MailMessage $message, string $body): void
    {
        $normalizedBody = trim($body);

        if ($normalizedBody === '') {
            return;
        }

        if ($this->looksLikeHtmlTemplate($normalizedBody)) {
            $message->line(new HtmlString($normalizedBody));

            return;
        }

        foreach ($this->splitTemplateBodyLines($normalizedBody) as $line) {
            $escaped = e($line);
            if ($escaped === '') {
                $escaped = '&nbsp;';
            }

            $message->line(new HtmlString('<p>'.$escaped.'</p>'));
        }
    }

    protected function looksLikeHtmlTemplate(string $body): bool
    {
        return preg_match('/<\/?[a-z][^>]*>/i', $body) === 1;
    }
}
