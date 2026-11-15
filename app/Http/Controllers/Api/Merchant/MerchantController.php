<?php

namespace App\Http\Controllers\Api\Merchant;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use Illuminate\Http\Request;

abstract class MerchantController extends Controller
{
    /** The authenticated merchant's shop; 404 if not registered yet. */
    protected function shop(Request $request): Shop
    {
        return $request->user()->shop ?? abort(response()->json(['message' => 'Register your shop first.', 'code' => 'shop_required'], 404));
    }
}
