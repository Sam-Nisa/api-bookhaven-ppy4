<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function index()
    {
        return response()->json(['message' => 'Coupons feature not implemented yet'], 501);
    }

    public function store(Request $request)
    {
        return response()->json(['message' => 'Coupons feature not implemented yet'], 501);
    }

    public function show($id)
    {
        return response()->json(['message' => 'Coupons feature not implemented yet'], 501);
    }

    public function destroy($id)
    {
        return response()->json(['message' => 'Coupons feature not implemented yet'], 501);
    }
}
