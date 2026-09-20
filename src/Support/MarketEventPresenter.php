<?php

namespace Cultpantry\Market\Support;

use Illuminate\Support\Carbon;

/**
 * Turns a raw history row (see Contracts\MarketHistory) into what the market
 * page shows: readable field names, old -> new values, and a plain-English
 * line for each schedule that was added, removed or changed.
 */
class MarketEventPresenter
{
    /** Attribute => label, for the filter dropdown and the change lines. */
    public const FIELDS = [
        'name' => 'Name',
        'city' => 'City',
        'region' => 'Region',
        'market_type' => 'Type',
        'sponsor' => 'Sponsor',
        'address_line1' => 'Street address',
        'address_line2' => 'Unit / suite',
        'province' => 'Province',
        'postal_code' => 'Postal code',
        'vendor_fees' => 'Vendor fees',
        'phone' => 'Phone',
        'manager' => 'Manager',
        'manager_phone' => 'Manager phone',
        'manager_email' => 'Manager email',
        'facebook_page' => 'Facebook',
        'instagram_page' => 'Instagram',
        'website' => 'Website',
        'description' => 'Description',
        'notes' => 'Notes',
        'sources' => 'Sources',
        'liveness_score' => 'Liveness score',
        'liveness_checked_at' => 'Liveness checked on',
        'is_active' => 'Active',
        'schedules' => 'Schedules',
    ];

    private const SCHEDULE_FIELDS = [
        'label' => 'label',
        'frequency' => 'frequency',
        'frequency_detail' => 'days & hours',
        'start_date' => 'start date',
        'end_date' => 'end date',
        'address_line1' => 'location',
        'notes' => 'notes',
        'liveness_score' => 'liveness score',
        'liveness_checked_at' => 'checked on',
    ];

    /**
     * @param  array{id: int, kind: string, severity: string, actor: ?string, created_at: \DateTimeInterface, metadata: array<string, mixed>}  $row
     * @return array<string, mixed>
     */
    public static function present(array $row): array
    {
        $changes = $row['metadata']['changes'] ?? [];
        $when = Carbon::instance($row['created_at']);

        $fieldChanges = [];
        $scheduleNotes = [];

        if ($row['kind'] === 'updated') {
            foreach ($changes as $field => $change) {
                if ($field === 'schedules') {
                    $scheduleNotes = self::describeScheduleChange($change['old'] ?? [], $change['new'] ?? []);

                    continue;
                }
                $fieldChanges[] = [
                    'field' => self::FIELDS[$field] ?? $field,
                    'old' => self::value($field, $change['old'] ?? null),
                    'new' => self::value($field, $change['new'] ?? null),
                ];
            }
        } elseif (! empty($changes['schedules'])) {
            $count = count($changes['schedules']);
            $scheduleNotes[] = "Created with {$count} schedule".($count === 1 ? '' : 's').': '.collect($changes['schedules'])->map(fn ($s) => $s['label'] ?? 'Schedule')->implode(', ');
        }

        return [
            'id' => $row['id'],
            'kind' => $row['kind'],
            'severity' => $row['severity'],
            'actor' => $row['actor'],
            'source' => ($row['metadata']['source'] ?? null) === 'xml_import' ? 'XML import' : 'Admin form',
            'created_at' => $when->toIso8601String(),
            'created_at_human' => $when->diffForHumans(),
            'created_at_label' => $when->format('M j, Y g:i a'),
            'changes' => $fieldChanges,
            'schedule_notes' => $scheduleNotes,
        ];
    }

    private static function value(string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '(empty)';
        }
        if ($field === 'is_active') {
            return $value ? 'Active' : 'Inactive';
        }
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }
        $text = (string) $value;
        if (str_ends_with($text, ' 00:00:00')) {
            $text = substr($text, 0, 10);
        }

        return mb_strlen($text) > 110 ? mb_substr($text, 0, 110).'…' : $text;
    }

    /**
     * Schedules are matched by label; a label that appears on both sides but
     * differs is "changed" and says which attributes moved.
     *
     * @param  array<int, array<string, mixed>>  $old
     * @param  array<int, array<string, mixed>>  $new
     * @return array<int, string>
     */
    private static function describeScheduleChange(array $old, array $new): array
    {
        $key = fn (array $s, int $i) => ($s['label'] ?? null) !== null ? 'l:'.$s['label'] : "i:{$i}";
        $oldByKey = collect($old)->mapWithKeys(fn ($s, $i) => [$key($s, $i) => $s]);
        $newByKey = collect($new)->mapWithKeys(fn ($s, $i) => [$key($s, $i) => $s]);

        $notes = [];
        foreach ($newByKey->diffKeys($oldByKey) as $k => $s) {
            $notes[] = 'Added schedule: '.($s['label'] ?? 'Schedule');
        }
        foreach ($oldByKey->diffKeys($newByKey) as $k => $s) {
            $notes[] = 'Removed schedule: '.($s['label'] ?? 'Schedule');
        }
        foreach ($newByKey->intersectByKeys($oldByKey) as $k => $s) {
            $moved = [];
            foreach (self::SCHEDULE_FIELDS as $attr => $label) {
                if (($oldByKey[$k][$attr] ?? null) !== ($s[$attr] ?? null)) {
                    $moved[] = "{$label} ".self::value($attr, $oldByKey[$k][$attr] ?? null).' → '.self::value($attr, $s[$attr] ?? null);
                }
            }
            if ($moved !== []) {
                $notes[] = 'Changed schedule '.($s['label'] ?? '').': '.implode('; ', $moved);
            }
        }

        return $notes !== [] ? $notes : ['Schedules updated'];
    }
}
