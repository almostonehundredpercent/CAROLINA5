<?php

namespace App\Http\Controllers;

use App\Mail\RoomPriceChangeDecision;
use App\Models\ActivityLog;
use App\Models\RoomPriceChangeRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

class RoomPriceApprovalController extends Controller
{
    public function show(RoomPriceChangeRequest $change, string $token)
    {
        $change->load(['room', 'requester']);
        $this->assertValidApprovalLink($change, $token);

        if ($change->status === 'requested' && $change->approval_expires_at?->isPast()) {
            $change->update(['status' => 'expired', 'approval_token_hash' => null]);
        }

        $decisionUrl = URL::temporarySignedRoute('room-price-approval.decide', $change->approval_expires_at, ['change' => $change->id, 'token' => $token]);

        return response()->view('room-price-approval.review', compact('change', 'decisionUrl'))
            ->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function decide(Request $request, RoomPriceChangeRequest $change, string $token)
    {
        $data = $request->validate(['decision' => 'required|in:approve,reject']);

        $change = DB::transaction(function () use ($change, $token, $data) {
            $lockedChange = RoomPriceChangeRequest::query()->whereKey($change->id)->lockForUpdate()->firstOrFail();
            $this->assertValidApprovalLink($lockedChange, $token);
            if ($lockedChange->status !== 'requested') {
                throw ValidationException::withMessages(['decision' => 'This rate request has already been decided or has expired.']);
            }
            if (! $lockedChange->approval_expires_at || $lockedChange->approval_expires_at->isPast()) {
                $lockedChange->update(['status' => 'expired', 'approval_token_hash' => null]);
                throw ValidationException::withMessages(['decision' => 'This approval link has expired. Contact Carolina for a new request.']);
            }

            $approved = $data['decision'] === 'approve';
            $lockedChange->update([
                'status' => $approved ? 'approved' : 'rejected',
                'approved_at' => $approved ? now() : null,
                'rejected_at' => $approved ? null : now(),
            ]);

            ActivityLog::create([
                'subject_type' => $lockedChange::class,
                'subject_id' => $lockedChange->id,
                'event' => $approved ? 'room_rate_change_approved' : 'room_rate_change_rejected',
                'description' => $approved ? 'Owner approved the proposed room rate by signed email link.' : 'Owner rejected the proposed room rate by signed email link.',
                'before_values' => ['status' => 'requested', 'price_per_night' => $lockedChange->old_price],
                'after_values' => [
                    'status' => $lockedChange->status,
                    'reference' => $lockedChange->reference,
                    'old_price' => $lockedChange->old_price,
                    'proposed_price' => $lockedChange->proposed_price,
                    'owner_email' => $lockedChange->owner_email,
                    'decision_at' => ($lockedChange->approved_at ?? $lockedChange->rejected_at)->toIso8601String(),
                ],
            ]);

            return $lockedChange;
        });

        try {
            Mail::to($change->owner_email)->send(new RoomPriceChangeDecision($change->fresh()->load('room')));
        } catch (\Throwable $exception) {
            report($exception);
            ActivityLog::create([
                'subject_type' => $change::class,
                'subject_id' => $change->id,
                'event' => 'room_rate_decision_email_failed',
                'description' => 'Owner decision was saved, but the external confirmation email could not be delivered.',
                'after_values' => ['reference' => $change->reference, 'owner_email' => $change->owner_email, 'failed_at' => now()->toIso8601String()],
            ]);
        }

        $change->load(['room', 'requester']);
        $decisionUrl = '#';

        return response()->view('room-price-approval.review', compact('change', 'decisionUrl'))
            ->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    private function assertValidApprovalLink(RoomPriceChangeRequest $change, string $token): void
    {
        abort_unless($change->approval_token_hash
            && hash_equals($change->approval_token_hash, hash('sha256', $token)), 403);
    }
}
