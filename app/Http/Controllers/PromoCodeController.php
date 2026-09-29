<?php

namespace App\Http\Controllers;

use App\Models\PromoCode;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PromoCodeController extends Controller
{
    public function index()
    {
        return view('admin.promos', [
            'promos' => PromoCode::with('rooms')->latest()->get(),
            'rooms' => Room::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $promo = PromoCode::create($this->validated($request));
        $promo->rooms()->sync($request->input('room_ids', []));

        return back()->with('success', 'Promo code '.$promo->code.' created.');
    }

    public function update(Request $request, PromoCode $promo)
    {
        $promo->update($this->validated($request, $promo));
        $promo->rooms()->sync($request->input('room_ids', []));

        return back()->with('success', 'Promo code '.$promo->code.' updated.');
    }

    private function validated(Request $request, ?PromoCode $promo = null): array
    {
        $request->merge([
            'code' => Str::upper(trim((string) $request->input('code'))),
            'is_active' => $request->boolean('is_active'),
        ]);

        return $request->validate([
            'code' => ['required', 'alpha_dash:ascii', 'max:40', Rule::unique('promo_codes')->ignore($promo)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'discount_type' => ['required', Rule::in(['percentage', 'fixed_amount', 'fixed_total'])],
            'discount_value' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'minimum_hours' => ['nullable', 'integer', 'min:1', 'max:8760'],
            'maximum_hours' => ['nullable', 'integer', 'min:1', 'max:8760', 'gte:minimum_hours'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'usage_limit' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'is_active' => ['required', 'boolean'],
            'room_ids' => ['required', 'array', 'min:1'],
            'room_ids.*' => ['integer', Rule::exists('rooms', 'id')->where('is_active', true)],
        ]);
    }
}
