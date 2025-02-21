<?php

namespace Habib\LaravelCrud\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

trait UpdateTrait
{
    /**
     * Update the specified resource in storage.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function update(): JsonResponse|RedirectResponse
    {
        if (! method_exists($this, 'getRepository')) {
            throw new \RuntimeException('Controller must implement getRepository() method');
        }

        $repository = $this->getRepository();
        $request = $repository->getUpdateRequest();
        $user = $request?->user();

        abort_if(
            $user?->cannot($ability = $repository->getAuthorize('update')),
            403,
            __('global.unauthorized').' '.$ability
        );

        $validated = $request->validated();
        $routeParam = $request->route()->parameterNames()[0] ?? 0;
        $model = $repository->update($request->route()->parameter($routeParam), $user, $validated);

        $message = __('dashboard.updated', [
            'model' => $repository->getTranslate('singular'),
            'user' => $user,
        ]);

        activity()
            ->by($user)
            ->on($model)
            ->event('updated')
            ->useLog('updated')
            ->withProperties($validated)
            ->log($message);

        $this->socket(
            "{$repository->getKey()}-socket",
            [
                'message' => $message,
                'model' => $model,
            ]
        );

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'data' => $model,
            ]);
        }

        return back()->with('success', $message);
    }
}
