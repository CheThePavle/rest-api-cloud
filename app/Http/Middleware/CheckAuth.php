<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;

class CheckAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\JsonResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $findUser = User::where('api_token', '=', $request->bearerToken())
            ->select('id')
            ->first();
        if (!$findUser) {
            return response()->json([
                'success'=>false,
                'message'=>'Not authorized'
            ], 401);
        }
        $request->merge(['idUser'=>$findUser->id]);

        return $next($request);
    }
}
