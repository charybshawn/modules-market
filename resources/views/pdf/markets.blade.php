@php
    use Illuminate\Support\Str;

    $today = $generatedAt->copy()->startOfDay();

    // "Aug 8, 2026" for a day; "Aug 8 – Sep 26, 2026" for a range, with both
    // years spelled out when the range crosses a year.
    $dateLabel = function ($schedule) {
        $start = $schedule->start_date;
        $end = $schedule->end_date;
        if (! $start && ! $end) {
            return null;
        }
        if (! $start || ! $end || $start->equalTo($end)) {
            return ($start ?? $end)->format('M j, Y');
        }

        return $start->year === $end->year
            ? $start->format('M j').' – '.$end->format('M j, Y')
            : $start->format('M j, Y').' – '.$end->format('M j, Y');
    };

    $isPast = function ($schedule) use ($today) {
        $last = $schedule->end_date ?? $schedule->start_date;

        return $last !== null && $last->lt($today);
    };

    // Ongoing schedules first, then what's coming (soonest first), then past
    // editions (most recent first), so the reader meets the useful ones first.
    $ordered = fn ($schedules) => $schedules->sortBy(fn ($s) => [
        $isPast($s) ? 2 : ($s->start_date ? 1 : 0),
        ($isPast($s) ? -1 : 1) * ($s->start_date?->timestamp ?? 0),
    ])->values();

    $bare = fn (?string $url) => Str::limit(preg_replace('#^https?://(www\.)?#i', '', rtrim((string) $url, '/')), 74, '…');
    $isUrl = fn (?string $value) => (bool) preg_match('#^https?://#i', (string) $value);
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>BC Farmer's Markets</title>
    <style>
        @page {
            margin: 30px 38px 54px 38px;
        }

        body {
            font-family: "Helvetica", "Arial", sans-serif;
            color: #1f2937;
            font-size: 10px;
            line-height: 1.4;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        /* ---- Page furniture ---- */

        .footer {
            position: fixed;
            bottom: -34px;
            left: 0;
            right: 0;
            border-top: 1px solid #d1d5db;
            padding-top: 5px;
            font-size: 8px;
            color: #6b7280;
        }

        .footer .page {
            float: right;
        }

        .footer .page:after {
            content: "Page " counter(page);
        }

        /* ---- Report header ---- */

        .report-header {
            border-bottom: 3px solid #1f2937;
            padding-bottom: 9px;
            margin-bottom: 4px;
        }

        .report-header h1 {
            font-size: 22px;
            margin: 0 0 3px 0;
            color: #111827;
        }

        .report-header .meta {
            font-size: 9.5px;
            color: #6b7280;
        }

        .report-header .regions {
            margin-top: 6px;
            font-size: 9.5px;
            color: #374151;
        }

        .report-header .regions span {
            margin-right: 14px;
            white-space: nowrap;
        }

        .report-header .regions strong {
            color: #111827;
        }

        /* ---- Region ---- */

        .region-heading {
            background-color: #1f2937;
            color: #ffffff;
            font-size: 10.5px;
            font-weight: bold;
            letter-spacing: 0.6px;
            text-transform: uppercase;
            padding: 5px 9px;
            margin: 20px 0 4px 0;
            page-break-after: avoid;
        }

        .region-heading .count {
            float: right;
            font-weight: normal;
            letter-spacing: 0;
            text-transform: none;
            color: #d1d5db;
        }

        /* ---- Market ---- */

        .market {
            page-break-inside: avoid;
            padding: 11px 0 11px 11px;
            margin-left: 2px;
            border-left: 3px solid #9ca3af;
            border-bottom: 1px solid #e5e7eb;
        }

        .market.inactive {
            border-left-color: #e5e7eb;
        }

        .market-head {
            width: 100%;
            border-collapse: collapse;
        }

        .market-head td {
            padding: 0;
            vertical-align: top;
        }

        .market-head .type-cell {
            text-align: right;
            white-space: nowrap;
            padding-left: 10px;
        }

        .market-name {
            font-size: 13.5px;
            font-weight: bold;
            color: #111827;
        }

        .badge {
            font-size: 8px;
            padding: 1.5px 6px;
            border-radius: 3px;
            white-space: nowrap;
        }

        .badge.type {
            background-color: #374151;
            color: #ffffff;
        }

        .badge.inactive {
            background-color: #f3f4f6;
            color: #6b7280;
            border: 1px solid #d1d5db;
            margin-left: 6px;
        }

        .where {
            margin-top: 2px;
            font-size: 9.5px;
            color: #4b5563;
        }

        .where strong {
            color: #111827;
        }

        .where .sponsor {
            font-style: italic;
            color: #6b7280;
        }

        .description {
            margin-top: 6px;
            font-size: 9.5px;
            color: #374151;
            line-height: 1.45;
        }

        /* ---- Schedules ---- */

        .schedules {
            margin-top: 8px;
        }

        .schedule {
            margin-top: 6px;
            padding-left: 9px;
            border-left: 2px solid #c7d2fe;
        }

        .schedule .title {
            font-size: 10px;
            font-weight: bold;
            color: #111827;
        }

        .schedule .when {
            margin-top: 1px;
            font-size: 10px;
            font-weight: bold;
            color: #312e81;
        }

        .schedule .when .hours {
            color: #111827;
        }

        .schedule .when .sep {
            color: #9ca3af;
            font-weight: normal;
        }

        .schedule .note {
            margin-top: 1px;
            font-size: 8.5px;
            color: #6b7280;
            line-height: 1.4;
        }

        .schedule.past {
            border-left-color: #e5e7eb;
        }

        .schedule.past .title,
        .schedule.past .when,
        .schedule.past .when .hours {
            color: #9ca3af;
        }

        .schedule .past-tag {
            font-size: 7.5px;
            font-weight: normal;
            color: #9ca3af;
            border: 1px solid #e5e7eb;
            border-radius: 3px;
            padding: 0 4px;
            margin-left: 5px;
        }

        /* ---- Contact and sources ---- */

        .contact {
            margin-top: 8px;
            font-size: 9px;
            color: #374151;
        }

        .contact .label,
        .sources .label {
            color: #9ca3af;
            font-size: 7.5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .sources {
            margin-top: 7px;
            padding-top: 5px;
            border-top: 1px dotted #d1d5db;
            font-size: 8px;
            color: #6b7280;
        }

        .sources .heading {
            margin-bottom: 1px;
        }

        .sources .checked {
            color: #9ca3af;
        }

        .sources .source {
            line-height: 1.35;
            color: #4b5563;
        }

        .empty-state {
            margin-top: 40px;
            text-align: center;
            color: #6b7280;
            font-size: 11px;
        }
    </style>
</head>
<body>
    <div class="footer">
        <span>BC Farmer's Markets &middot; {{ $generatedAt->format('F j, Y') }}</span>
        <span class="page"></span>
    </div>

    <div class="report-header">
        <h1>BC Farmer's Markets</h1>
        <div class="meta">
            Generated {{ $generatedAt->format('F j, Y') }}
            &middot; {{ $markets->count() }} {{ Str::plural('market', $markets->count()) }}
        </div>
        @if ($marketsByRegion->count() > 1)
            <div class="regions">
                @foreach ($marketsByRegion as $region => $regionMarkets)
                    <span><strong>{{ $regionMarkets->count() }}</strong> {{ $region }}</span>
                @endforeach
            </div>
        @endif
    </div>

    @if ($markets->isEmpty())
        <div class="empty-state">No markets match the current filters.</div>
    @endif

    @foreach ($marketsByRegion as $region => $regionMarkets)
        <div class="region-heading">
            {{ $region }}
            <span class="count">{{ $regionMarkets->count() }} {{ Str::plural('market', $regionMarkets->count()) }}</span>
        </div>

        @foreach ($regionMarkets as $market)
            @php
                $sources = collect(preg_split('/\R/', (string) $market->sources))->map(fn ($s) => trim($s))->filter()->unique()->values();
                $where = collect([$market->address_line1, $market->postal_code])->filter()->implode(', ');
                $contacts = collect([
                    ['Phone', $market->phone, null],
                    ['Email', $market->manager_email, $market->manager_email ? 'mailto:'.$market->manager_email : null],
                    ['Web', $market->website ? $bare($market->website) : null, $isUrl($market->website) ? $market->website : null],
                    ['Facebook', $market->facebook_page ? $bare($market->facebook_page) : null, $isUrl($market->facebook_page) ? $market->facebook_page : null],
                    ['Instagram', $market->instagram_page ? $bare($market->instagram_page) : null, $isUrl($market->instagram_page) ? $market->instagram_page : null],
                ])->filter(fn ($c) => filled($c[1]));
            @endphp
            <div class="market {{ $market->is_active ? '' : 'inactive' }}">
                <table class="market-head">
                    <tr>
                        <td>
                            <span class="market-name">{{ $market->name }}</span>
                            @unless ($market->is_active)
                                <span class="badge inactive">Inactive</span>
                            @endunless
                        </td>
                        @if ($market->market_type)
                            <td class="type-cell"><span class="badge type">{{ $market->market_type }}</span></td>
                        @endif
                    </tr>
                </table>

                <div class="where">
                    <strong>{{ $market->city ?: '—' }}</strong>@if ($where) &middot; {{ $where }}@endif
                    @if ($market->sponsor)
                        &nbsp;&middot;&nbsp;<span class="sponsor">Sponsored by {{ $market->sponsor }}</span>
                    @endif
                </div>

                @if ($market->description)
                    <div class="description">{{ $market->description }}</div>
                @endif

                @if ($market->schedules->isNotEmpty())
                    <div class="schedules">
                        @foreach ($ordered($market->schedules) as $schedule)
                            @php
                                $past = $isPast($schedule);
                                $when = $schedule->recurrenceSummary();
                                $date = $dateLabel($schedule);
                                $title = $schedule->label ?: ($when ?: ($frequencyLabels[$schedule->frequency] ?? 'Schedule'));
                                // With no label, the days and hours are the title already.
                                $line = $schedule->label ? $when : null;
                            @endphp
                            <div class="schedule {{ $past ? 'past' : '' }}">
                                <div class="title">{{ $title }}@if ($past)<span class="past-tag">Past</span>@endif</div>
                                @if ($date || $line)
                                    <div class="when">
                                        @if ($date){{ $date }}@endif
                                        @if ($date && $line)<span class="sep">&nbsp;&middot;&nbsp;</span>@endif
                                        @if ($line)<span class="hours">{{ $line }}</span>@endif
                                    </div>
                                @endif
                                @if ($schedule->address_line1)
                                    <div class="note">At {{ $schedule->address_line1 }}</div>
                                @endif
                                @if ($schedule->notes)
                                    <div class="note">{{ $schedule->notes }}</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

                @if ($contacts->isNotEmpty())
                    <div class="contact">
                        @foreach ($contacts as [$label, $text, $href])
                            <span class="label">{{ $label }}</span>&nbsp;@if ($href)<a href="{{ $href }}">{{ $text }}</a>@else{{ $text }}@endif{!! $loop->last ? '' : str_repeat('&nbsp;', 5) !!}
                        @endforeach
                    </div>
                @endif

                @if ($sources->isNotEmpty())
                    <div class="sources">
                        <div class="heading">
                            <span class="label">Sources</span>
                            @if ($market->liveness_checked_at)
                                <span class="checked">&middot; checked {{ $market->liveness_checked_at->format('M j, Y') }}</span>
                            @endif
                        </div>
                        @foreach ($sources as $source)
                            <div class="source">
                                @if ($isUrl($source))<a href="{{ $source }}">{{ $bare($source) }}</a>@else{{ Str::limit($source, 90) }}@endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    @endforeach
</body>
</html>
