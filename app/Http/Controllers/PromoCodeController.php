<?php

namespace App\Http\Controllers;

use App\Models\PromoCode;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PromoCodeController extends Controller
{
    public function index(Request $request)
    {
        $section = $request->query('section');
        $section = in_array($section, ['drafts', 'offers', 'create'], true) ? $section : 'drafts';
        $drafts = PromoCode::where('review_status', 'pending');
        $offers = PromoCode::where('review_status', 'approved');
        $items = match ($section) {
            'drafts' => $drafts->with('rooms')->latest()->orderByDesc('id')->paginate(5)->appends(['section' => $section]),
            'offers' => $offers->with('rooms')->latest()->orderByDesc('id')->paginate(5)->appends(['section' => $section]),
            default => null,
        };
        if ($items && $items->currentPage() > $items->lastPage()) {
            return redirect($items->url($items->lastPage()));
        }

        return view('admin.promos', [
            'section' => $section,
            'items' => $items,
            'draftCount' => $section === 'drafts' ? $items->total() : $drafts->count(),
            'offerCount' => $section === 'offers' ? $items->total() : $offers->count(),
            'rooms' => Room::where('is_active', true)->orderBy('id')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $promo = PromoCode::create($this->validated($request));
        $promo->rooms()->sync($request->input('room_ids', []));

        return redirect()->route('admin.promos', ['section' => 'offers'])->with('success', 'Promo code '.$promo->code.' created.');
    }

    public function update(Request $request, PromoCode $promo)
    {
        $data = $this->validated($request, $promo);
        if ($promo->review_status === 'pending') {
            $data['is_active'] = false;
        }
        $promo->update($data);
        $promo->rooms()->sync($request->input('room_ids', []));

        return back()->with('success', 'Promo code '.$promo->code.' updated.');
    }

    public function destroy(PromoCode $promo)
    {
        $code = $promo->code;
        $message = 'Promo code '.$code.' deleted. Past booking records are unchanged.';
        if ($promo->carol_generation_key) {
            $promo->update(['review_status' => 'dismissed', 'is_active' => false]);
            $message = 'CAROL promo '.$code.' removed from offers and archived. Past booking records are unchanged.';
        } else {
            $promo->delete();
        }

        return back()->with('success', $message);
    }

    public function approveDraft(PromoCode $promo)
    {
        abort_unless($promo->review_status === 'pending', 422, 'This CAROL draft has already been reviewed.');

        DB::transaction(function () use ($promo) {
            $lockedPromo = PromoCode::whereKey($promo->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedPromo->review_status === 'pending', 422, 'This CAROL draft has already been reviewed.');
            abort_if(! $lockedPromo->rooms()->exists(), 422, 'Choose at least one room before approving this promo.');
            abort_if($lockedPromo->ends_at && $lockedPromo->ends_at->lte(now()), 422, 'This CAROL promo draft has expired and cannot be activated.');
            $lockedPromo->update(['review_status' => 'approved', 'is_active' => true]);
        });

        return back()->with('success', 'CAROL promo '.$promo->code.' approved and activated.');
    }

    public function dismissDraft(PromoCode $promo)
    {
        DB::transaction(function () use ($promo) {
            $lockedPromo = PromoCode::whereKey($promo->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedPromo->review_status === 'pending', 422, 'This CAROL draft has already been reviewed.');
            $lockedPromo->update(['review_status' => 'dismissed', 'is_active' => false]);
        });

        return back()->with('success', 'CAROL promo '.$promo->code.' dismissed.');
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
            'stay_start_date' => ['nullable', 'date_format:Y-m-d', 'required_with:stay_end_date'],
            'stay_end_date' => ['nullable', 'date_format:Y-m-d', 'required_with:stay_start_date', 'after_or_equal:stay_start_date'],
            'stay_day_type' => ['nullable', Rule::in(['weekday', 'weekend'])],
            'usage_limit' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'is_active' => ['required', 'boolean'],
            'room_ids' => ['required', 'array', 'min:1'],
            'room_ids.*' => ['integer', Rule::exists('rooms', 'id')->where('is_active', true)],
        ]);
    }
}
