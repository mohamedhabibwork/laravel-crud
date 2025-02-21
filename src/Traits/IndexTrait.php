<?php

namespace Habib\LaravelCrud\Traits;

use Illuminate\Http\Request;

trait IndexTrait
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $repository = $this->getRepository();

        $datatable = $repository->datatable($request);
        if ($request->expectsJson() && $request->has(key: 'search-index')) {
            $searchFields = explode(',', $request->get('searchField'));
            $searchValue = $request->str('search')->toString();

            //            dd(compact('searchFields','searchValue'));
            return $repository->setSearchQuery($datatable->query()
                ->select($repository->getSearchKeys()))
                ->when($request->filled('searchField'), function ($query) use ($searchFields, $searchValue) {
                    return $query->where(function ($query) use ($searchFields, $searchValue) {
                        foreach ($searchFields as $field) {
                            $query->orWhere($field, 'like', "%$searchValue%");
                        }
                    });
                })
                ->limit($request->integer('per_page', 10))
                ->get();
        }
        $model = $repository->getModel();
        $view = $repository->getViewPrefix(view: 'index');
        abort_if($request?->user()?->cannot(abilities: $repository->getAuthorize('index')), code: 403);

        return $datatable->render($view, [
            'prefixRoute' => $repository->getRoutePrefix(route: ''),
            'prefixView' => $repository->getBaseViewPrefix(''),
            'title' => __($repository->getTranslate(key: 'list')),
            'request' => $request,
            'fields' => $repository->fields($model),
            'model' => $model,
            'socketGroup' => "{$repository->getKey()}-socket",
            'createPermission' => $request->user()->can(abilities: $repository->getAuthorize('create')),
        ]);
    }
}
