<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>BC Farmer's Markets</title>
    <style>
        @page {
            margin: 28px 36px;
        }

        body {
            font-family: "Helvetica", "Arial", sans-serif;
            color: #1f2937;
            font-size: 10.5px;
            line-height: 1.4;
        }

        .report-header {
            border-bottom: 2px solid #1f2937;
            padding-bottom: 8px;
            margin-bottom: 14px;
        }

        .report-header h1 {
            font-size: 19px;
            margin: 0 0 2px 0;
        }

        .report-header .meta {
            font-size: 9.5px;
            color: #6b7280;
        }

        .region-heading {
            background-color: #1f2937;
            color: #ffffff;
            font-size: 11px;
            font-weight: bold;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            padding: 4px 8px;
            margin: 16px 0 8px 0;
        }

        .region-heading:first-child {
            margin-top: 0;
        }

        .market {
            page-break-inside: avoid;
            border-bottom: 1px solid #e5e7eb;
            padding: 8px 0;
        }

        .market-name-row {
            width: 100%;
        }

        .market-name {
            font-size: 12.5px;
            font-weight: bold;
            color: #111827;
        }

        .market-type {
            font-size: 9px;
            color: #ffffff;
            background-color: #4b5563;
            padding: 1px 6px;
            border-radius: 3px;
            float: right;
        }

        .market-subline {
            font-size: 9.5px;
            color: #6b7280;
            margin-top: 1px;
        }

        .market-sponsor {
            font-style: italic;
        }

        .market-description {
            margin-top: 5px;
            font-size: 9.5px;
            color: #374151;
        }

        .schedules {
            margin-top: 5px;
        }

        .schedule-line {
            font-size: 9.5px;
            color: #1f2937;
            padding-left: 10px;
            text-indent: -10px;
        }

        .schedule-line:before {
            content: "\2022";
            margin-right: 5px;
        }

        .contact-line {
            margin-top: 5px;
            font-size: 9px;
            color: #4b5563;
        }

        .contact-line span {
            margin-right: 14px;
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
    <div class="report-header">
        <h1>BC Farmer's Markets</h1>
        <div class="meta">
            Generated {{ $generatedAt->format('F j, Y') }}
            &middot; {{ $markets->count() }} {{ \Illuminate\Support\Str::plural('market', $markets->count()) }}
        </div>
    </div>

    @if ($markets->isEmpty())
        <div class="empty-state">No markets match the current filters.</div>
    @endif

    @foreach ($marketsByRegion as $region => $regionMarkets)
        <div class="region-heading">{{ $region }}</div>

        @foreach ($regionMarkets as $market)
            <div class="market">
                <div class="market-name-row">
                    @if ($market->market_type)
                        <span class="market-type">{{ $market->market_type }}</span>
                    @endif
                    <span class="market-name">{{ $market->name }}</span>
                </div>
                <div class="market-subline">
                    {{ collect([$market->city, $market->address_line1])->filter()->implode(', ') ?: '—' }}
                    @if ($market->sponsor)
                        &nbsp;&middot;&nbsp;<span class="market-sponsor">{{ $market->sponsor }}</span>
                    @endif
                </div>

                @if ($market->description)
                    <div class="market-description">{{ $market->description }}</div>
                @endif

                @if ($market->schedules->isNotEmpty())
                    <div class="schedules">
                        @foreach ($market->schedules as $schedule)
                            <div class="schedule-line">
                                @if ($schedule->label)<strong>{{ $schedule->label }}:</strong>@endif
                                {{ $schedule->frequency_detail ?: ($frequencyLabels[$schedule->frequency] ?? 'Schedule') }}
                                @if ($schedule->start_date)
                                    ({{ $schedule->start_date->format('M j') }}@if ($schedule->end_date && ! $schedule->end_date->equalTo($schedule->start_date)) &ndash; {{ $schedule->end_date->format('M j, Y') }}@else, {{ $schedule->start_date->format('Y') }}@endif)
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

                @php
                    $contactParts = collect([
                        $market->phone,
                        $market->website ? preg_replace('#^https?://(www\.)?#', '', rtrim($market->website, '/')) : null,
                        ! $market->website && $market->facebook_page ? 'Facebook' : null,
                    ])->filter();
                @endphp
                @if ($contactParts->isNotEmpty())
                    <div class="contact-line">
                        @foreach ($contactParts as $part)
                            <span>{{ $part }}</span>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    @endforeach
</body>
</html>
