<?php

namespace Habib\LaravelCrud\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

trait DestroyTrait
{
    /**
     * Remove the specified resource from storage.
     *
     * @param Request $request The incoming HTTP request
     * @return JsonResponse|RedirectResponse
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function destroy(Request $request): JsonResponse|RedirectResponse
    {
        if (! method_exists($this, 'getRepository')) {
            throw new \RuntimeException('Controller must implement getRepository() method');
        }

        $repository = $this->getRepository();
        $user = $request->user();
        $routeParam = $request->route()->parameterNames()[0] ?? 0;
        $model = $repository->find($request->route()->parameter($routeParam));

        abort_if(
            ! $model,
            404,
            __('global.not_found')
        );

        abort_if(
            $user?->cannot($ability = $repository->getAuthorize('delete')),
            403,
            __('global.unauthorized').' '.$ability
        );

        $repository->delete($model);
        $message = __('dashboard.deleted', ['model' => $repository->getTranslate('singular')]);

        $this->socket(
            "{$repository->getKey()}-socket",
            [
                'message' => $message,
                'model' => $model,
            ]
        );

        activity()
            ->by($user)
            ->on($model)
            ->event('deleted')
            ->useLog('deleted')
            ->log($message);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'data' => $model,
            ]);
        }

        return back()->with('success', $message);
    }
}
