<?php

namespace Upsoftware\Svarium\Routing;

use Symfony\Component\HttpFoundation\Response;

class ContentRenderer
{
    public function render(ContentMatch $match): Response
    {
        $request = request();

        if (!$match->methodAllowed($request)) {
            return response('Method Not Allowed', 405);
        }

        return $this->renderHtml($match);
    }

    protected function renderHtml(ContentMatch $match): Response
    {
        return response()->view(
            $match->view,
            array_merge([
                'record' => $match->record,
            ], $match->data)
        );
    }
}
