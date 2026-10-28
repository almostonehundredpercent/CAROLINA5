<?php

namespace App\Http\Controllers;

use App\Models\DecisionSupportSeason;
use App\Models\DecisionSupportSetting;
use App\Models\Room;
use App\Services\OperationalDecisionSupport;
use Illuminate\Http\Request;

class DecisionSupportController extends Controller
{
    public function index(OperationalDecisionSupport $decisionSupport)
    {
        $setting = DecisionSupportSetting::find(1);
        $threshold = $setting?->low_occupancy_threshold ?? 40;
        $seasons = DecisionSupportSeason::whereDate('ends_on', '>=', today())
            ->orderBy('starts_on')->get();
        $rooms = Room::where('is_active', true)->orderBy('id')->get();

        return view('admin.decision-support', [
            'threshold' => $threshold,
            'seasons' => $seasons,
            'opportunities' => $decisionSupport->promoOpportunities($rooms, $threshold, $seasons),
            'promoOutcomes' => $decisionSupport->promoOutcomes(),
        ]);
    }

    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'low_occupancy_threshold' => ['required', 'integer', 'min:5', 'max:95'],
        ]);

        DecisionSupportSetting::updateOrCreate(['id' => 1], $data);

        return back()->with('success', 'Low-occupancy rule saved. Recommendations now use the updated threshold.');
    }

    public function storeSeason(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'starts_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
        ]);
        $data['created_by'] = $request->user()->id;

        DecisionSupportSeason::create($data);

        return back()->with('success', 'Seasonal window added. It will label matching low-utilization opportunities.');
    }

    public function destroySeason(DecisionSupportSeason $season)
    {
        $season->delete();

        return back()->with('success', 'Seasonal window removed.');
    }
}
