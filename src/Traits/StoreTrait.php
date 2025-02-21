<?php

namespace Habib\LaravelCrud\Traits;

trait StoreTrait
{
    /**
     * Store a newly created resource in storage.
     */
    public function store()
    {
        $repository = $this->getRepository();
        $request = $repository->getCreateRequest();
        $user = $request?->user();
        abort_if(boolean: $user?->cannot(abilities: $repository->getAuthorize(key: 'create')), code: 403);
        if (is_null($request)) {
            return back()->with('error', __('dashboard.request_is_not_valid'));
        }
        $validated = $request?->validated();
        $model = $repository->create($validated, $user);

        $message = __('dashboard.created', ['model' => $repository->getTranslate(key: 'singular'), 'user' => $user]);
        activity()
            ->by($user)
            ->on($model)
            ->event('created')
            ->useLog('created')
            ->withProperties($validated)
            ->log(
                description: $message
            );

        $this->socket(
            group: "{$repository->getKey()}-socket",
            data: [
                'message' => $message,
                'model' => $model,
            ]);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'data' => $model,
            ]);
        }

        return back()->with('success', $message);
    }
}
