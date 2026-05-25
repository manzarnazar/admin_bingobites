<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Model\Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PosLoginController extends Controller
{
    public function __construct(private Admin $admin)
    {
    }

    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $admin = $this->admin->with('role')
            ->where('email', $request->email)
            ->where('status', 1)
            ->first();

        if (!$admin) {
            return response()->json([
                'errors' => [['code' => 'auth-001', 'message' => translate('Invalid credential.')]],
            ], 401);
        }

        if ($admin->admin_role_id != 1) {
            $permission = $admin->role->module_access ?? null;
            $modules = (array) json_decode($permission ?? '[]', true);
            if (!in_array(MANAGEMENT_SECTION['pos_management'], $modules, true)) {
                return response()->json([
                    'errors' => [['code' => 'pos-403', 'message' => translate('Access Denied !')]],
                ], 403);
            }
        }

        $credentials = [
            'email' => $request->email,
            'password' => $request->password,
            'status' => 1,
        ];

        if (!auth('admin')->attempt($credentials)) {
            return response()->json([
                'errors' => [['code' => 'auth-001', 'message' => translate('Invalid credential.')]],
            ], 401);
        }

        $token = $admin->createToken('PosEmployeeAuth')->accessToken;

        return response()->json([
            'token' => $token,
            'admin' => [
                'id' => $admin->id,
                'name' => trim(($admin->f_name ?? '') . ' ' . ($admin->l_name ?? '')),
                'email' => $admin->email,
                'role' => $admin->role->name ?? null,
                'image' => $admin->imageFullPath,
            ],
            'message' => translate('Successfully login.'),
        ], 200);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = auth('admin_api')->user()->token();
        $token->revoke();

        return response()->json(['message' => translate('You have been successfully logged out!')], 200);
    }
}
