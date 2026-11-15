<?php

namespace App\Console\Commands;

use App\Models\DecisionSupportSeason;
use App\Models\DecisionSupportSetting;
use App\Models\Room;
use App\Services\OperationalDecisionSupport;
use Illuminate\Console\Command;

class GenerateCarolPromoDrafts extends Command
{
    protected $signature = 'carol:generate-promo-drafts';

    protected $description = 'Create inactive promo drafts from CAROL low-occupancy windows';

    public function handle(OperationalDecisionSupport $decisionSupport): int
    {
        $threshold = DecisionSupportSetting::find(1)?->low_occupancy_threshold ?? 40;
        $seasons = DecisionSupportSeason::whereDate('ends_on', '>=', today())->orderBy('starts_on')->get();
        $rooms = Room::where('is_active', true)->orderBy('id')->get();
        $opportunities = $decisionSupport->promoOpportunities($rooms, $threshold, $seasons);
        $created = $decisionSupport->generatePromoDrafts($opportunities, $seasons, $threshold);

        $this->info("Created {$created} new inactive CAROL promo draft(s).");

        return self::SUCCESS;
    }
}
