<section class="panel carol-report-insight" aria-labelledby="carol-report-title">
    <div class="panel-heading">
        <div>
            <p class="admin-kicker">CAROL · REPORT INSIGHT</p>
            <h2 id="carol-report-title">{{ $carolReportInsight['title'] }}</h2>
        </div>
    </div>
    <p>{{ $carolReportInsight['evidence'] }}</p>
    <small><strong>Rule:</strong> {{ $carolReportInsight['rule'] }}</small>
    <a class="dss-action" href="{{ $carolReportInsight['action_url'] }}">{{ $carolReportInsight['action_label'] }} <span aria-hidden="true">→</span></a>
</section>
