<?php

namespace Habib\LaravelCrud\Traits;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View as ViewFacade;

trait CreateTrait
{
    /**
     * Show the form for creating a new resource.
     *
     * @param Request $request The incoming HTTP request
     * @return View The view for creating a new resource
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function create(Request $request): View
    {
        if (! method_exists($this, 'getRepository')) {
            throw new \RuntimeException('Controller must implement getRepository() method');
        }

        $repository = $this->getRepository();
        $model = $repository->getModel();
        $fields = $repository->fields($model);
        $model->forceFill($fields);
        $view = $repository->getViewPrefix('create');

        abort_if(
            $request?->user()?->cannot($ability = $repository->getAuthorize('create')),
            403,
            __('global.unauthorized').' '.$ability
        );

        if (! ViewFacade::exists($view)) {
            throw new \RuntimeException("View [{$view}] not found.");
        }

        return view($view, [
            'prefixRoute' => $repository->getRoutePrefix(''),
            'prefixView' => $repository->getBaseViewPrefix(''),
            'title' => $repository->getTranslate('create'),
            'model' => $model,
            'fields' => array_merge($fields, [
                'successMsg' => null,
                'errorMsg' => null,
                '_method' => 'POST',
            ]),
            'errorBag' => "{$repository->getKey()}-create",
        ]);
    }
}
