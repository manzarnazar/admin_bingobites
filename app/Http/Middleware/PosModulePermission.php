<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class PosModulePermission
{
    public function handle(Request $request, Closure $next)
    {
        $admin = auth('admin_api')->user();

        if (!$admin) {
            return response()->json(['errors' => [['code' => 'auth-001', 'message' => translate('Unauthorized')]]], 401);
        }

        if ($admin->admin_role_id == 1) {
            return $next($request);
        }

        $permission = $admin->role->module_access ?? null;
        if (isset($permission) && in_array(MANAGEMENT_SECTION['pos_management'], (array) json_decode($permission), true)) {
            return $next($request);
        }

        return response()->json(['errors' => [['code' => 'pos-403', 'message' => translate('Access Denied !')]]], 403);
    }
}
