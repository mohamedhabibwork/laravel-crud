<?php

namespace Habib\LaravelCrud\Traits;

use Illuminate\Http\Request;

trait EditTrait
{
    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request)
    {
        $repository = $this->getRepository();
        $view = $repository->getViewPrefix('edit');
        abort_if(
            boolean: ! $model = $repository->find($request->route()->parameter($request->route()->parameterNames()[0] ?? 0)),
            code: 404,
            message: __('global.not_found')
        );
        abort_if(
            boolean: $request?->user()?->cannot(abilities: $ability = $repository->getAuthorize('edit'), arguments: [$model]),
            code: 403,
            message: __('global.unauthorized').' '.$ability
        );

        $fields = $repository->fields($model);

        return view($view, [
            'prefixRoute' => $repository->getRoutePrefix(''),
            'prefixView' => $repository->getBaseViewPrefix(''),
            'title' => $repository->getTranslate(key: 'edit'),
            'model' => $model,
            'fields' => array_merge($fields, [
                //                'formData' => [],
                'successMsg' => null,
                'errorMsg' => null,
                '_method' => 'PUT',
            ]),
            'errorBag' => "{$repository->getKey()}-update",
        ]);
    }
}
