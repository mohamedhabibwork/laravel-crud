<?php

namespace Habib\LaravelCrud\Traits;

use Illuminate\Http\Request;

trait ShowTrait
{
    /**
     * Display the specified resource.
     */
    public function show(Request $request)
    {
        $repository = $this->getRepository();
        $view = $repository->getViewPrefix('show');
        abort_if(
            boolean: ! $model = $repository->find($request->route()->parameter($request->route()->parameterNames()[0] ?? 0)),
            code: 404,
            message: __('global.not_found')
        );
        abort_if(
            boolean: $request?->user()?->cannot(abilities: $ability = $repository->getAuthorize(key: 'show')),
            code: 403,
            message: __('global.unauthorized').' '.$ability
        );

        return view($view, [
            'prefixRoute' => $repository->getRoutePrefix(''),
            'prefixView' => $repository->getBaseViewPrefix(''),
            'title' => $repository->getTranslate(key: 'show'),
            'model' => $model,
            'fields' => $repository->fields($model),
            'socketGroup' => "{$repository->getKey()}-socket",
        ]);
    }
}
