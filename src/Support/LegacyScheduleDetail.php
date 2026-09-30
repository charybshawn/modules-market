<?php

namespace Cultpantry\Market\Support;

/**
 * Turns the retired free-text "days & hours" note into the structured fields
 * that replaced it (opens, closes, days) plus whatever wording is left over,
 * which belongs in the schedule's notes.
 *
 * Used by the migration that dropped the column and by the XML import, so an
 * older file's <frequency_detail> still lands somewhere. Conservative by
 * design: anything it can't fully account for comes back whole as the note,
 * so no wording is ever thrown away.
 */
class LegacyScheduleDetail
{
    private const FULL_DAYS = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

    /**
     * @param  array<int, int>|null  $weekdays  0 = Sunday ... 6 = Saturday, as already stored
     * @return array{start_time: ?string, end_time: ?string, weekdays: ?array<int, int>, note: ?string}
     *   The values to store, and the text to append to the schedule's notes.
     */
    public static function apply(
        ?string $text,
        ?string $frequency,
        bool $hasDate,
        ?array $weekdays,
        ?string $startTime,
        ?string $endTime,
    ): array {
        $result = ['start_time' => $startTime, 'end_time' => $endTime, 'weekdays' => $weekdays ?: null, 'note' => null];

        $text = trim((string) $text);
        if ($text === '') {
            return $result;
        }

        $parsed = self::parse($text);
        if ($parsed === null) {
            $result['note'] = $text;

            return $result;
        }

        $understood = true;

        // The times are only trusted when the wording before them is nothing but
        // days ("Saturday", "Mon & Thu"), and no second day follows with hours
        // of its own ("Sat 10am-5pm, and Sun 10am-4pm" is really two schedules).
        // Anything else ("Saturday, free admission; a search summary gave
        // 10am-3pm") may be qualifying the hours, so it stays a note.
        $days = self::days($parsed['prefix']);
        $timesUsable = $days !== null && ! self::undermined((string) $parsed['rest'], $days);

        if (! $timesUsable) {
            $understood = false;
        } elseif ($startTime === null && $endTime === null) {
            $result['start_time'] = $parsed['start'];
            $result['end_time'] = $parsed['end'];
        } elseif ($startTime !== $parsed['start'] || $endTime !== $parsed['end']) {
            $understood = false;
        }

        // A date named in the wording is only safe to drop if the schedule has it.
        if ($parsed['hadDate'] && ! $hasDate) {
            $understood = false;
        }

        if ($days === null) {
            $understood = false;
        } elseif ($frequency === 'one_time') {
            // The date already says which day it is, unless there isn't one.
            $understood = $understood && ($hasDate || $days === []);
        } elseif ($days !== []) {
            if (empty($weekdays)) {
                $result['weekdays'] = $days;
            } elseif ($weekdays !== $days) {
                $understood = false;
            }
        }

        $result['note'] = $understood ? $parsed['rest'] : $text;

        return $result;
    }

    /**
     * Appends $note to the existing notes on its own line, unless the notes
     * already contain it, so running this twice changes nothing.
     */
    public static function mergeNotes(?string $existing, ?string $note): ?string
    {
        $existing = $existing === null || trim($existing) === '' ? null : $existing;
        if ($note === null || trim($note) === '') {
            return $existing;
        }
        if ($existing === null) {
            return $note;
        }

        return stripos($existing, $note) !== false ? $existing : $existing."\n".$note;
    }

    /**
     * @return array{prefix: string, hadDate: bool, start: string, end: string, rest: ?string}|null
     */
    private static function parse(string $text): ?array
    {
        $open = '(\d{1,2})(?::(\d{2}))?\s*(am|pm|a\.m\.|p\.m\.)?';
        $close = '(\d{1,2})(?::(\d{2}))?\s*(am|pm|a\.m\.|p\.m\.)';
        $pattern = "/^(?<prefix>.{0,60}?)\\s*(?:[,;:\\-]\\s*)?{$open}\\s*(?:-|\\x{2013}|\\x{2014}|to|until|till)\\s*{$close}(?=\\s*(?:[,.;]|\$))/iu";

        if (! preg_match($pattern, $text, $m)) {
            return null;
        }

        $end = self::minutes((int) $m[5], (int) ($m[6] ?: 0), $m[7]);
        $endMeridiem = self::meridiem($m[7]);

        // Groups: 1 = prefix; 2/3/4 = opening hour/minute/meridiem; 5/6/7 = closing.
        $startHour = (int) $m[2];
        $startMin = (int) ($m[3] ?: 0);
        $startMeridiem = ($m[4] ?? '') !== '' ? $m[4] : null;

        if ($startMeridiem !== null) {
            $start = self::minutes($startHour, $startMin, $startMeridiem);
        } else {
            $start = self::minutes($startHour, $startMin, $endMeridiem);
            if ($start > $end) {
                $start = self::minutes($startHour, $startMin, $endMeridiem === 'pm' ? 'am' : 'pm');
            }
        }

        if ($start >= $end || $start > 23 * 60 + 59 || $end > 23 * 60 + 59) {
            return null;
        }

        $rest = ltrim(substr($text, strlen($m[0])), " \t\n\r,.;");

        // "Sunday Dec 13" -> "Sunday": the date itself lives in start_date.
        $date = '/\b(?:jan|feb|mar|apr|may|jun|jul|aug|sep|sept|oct|nov|dec)[a-z]*\.?\s+\d{1,2}(?:\s*[-\x{2013}]\s*\d{1,2})?(?:,?\s*\d{4})?/iu';
        $prefix = trim(preg_replace($date, '', $m['prefix'], -1, $dates), " ,;:-");

        return [
            'prefix' => $prefix,
            'hadDate' => $dates > 0,
            'start' => sprintf('%02d:%02d', intdiv($start, 60), $start % 60),
            'end' => sprintf('%02d:%02d', intdiv($end, 60), $end % 60),
            'rest' => $rest === '' ? null : mb_strtoupper(mb_substr($rest, 0, 1)).mb_substr($rest, 1),
        ];
    }

    /**
     * True when what follows the times casts doubt on them: another day than
     * the one named (a two-day event, "and Sunday Oct 25"), or hedging such
     * as "unconfirmed" or "tentative". The hours then stay in the note.
     *
     * @param  array<int, int>  $days
     */
    private static function undermined(string $rest, array $days): bool
    {
        $day = '/\b(?:sun(?:day)?s?|mon(?:day)?s?|tue(?:s|sday)?s?|wed(?:nesday)?s?|thu(?:r|rs|rsday)?s?|fri(?:day)?s?|sat(?:urday)?s?)\b/i';
        if (preg_match_all($day, $rest, $found)) {
            foreach ($found[0] as $word) {
                $index = self::days($word)[0] ?? null;
                if ($index === null || ! in_array($index, $days, true)) {
                    return true;
                }
            }
        }

        return (bool) preg_match('/unconfirmed|not (?:yet )?confirmed|unverified|approx|estimat|tentative|\btbd\b|search[- ]result|summary/i', $rest);
    }

    private static function meridiem(?string $value): ?string
    {
        return $value === null || $value === '' ? null : strtolower(str_replace('.', '', $value));
    }

    private static function minutes(int $hour, int $minute, ?string $meridiem): int
    {
        $meridiem = self::meridiem($meridiem);
        if ($meridiem === 'pm' && $hour < 12) {
            $hour += 12;
        } elseif ($meridiem === 'am' && $hour === 12) {
            $hour = 0;
        }

        return $hour * 60 + $minute;
    }

    /**
     * The days named by the wording before the times ("Saturday", "Mondays
     * and Thursdays", "Sat & Sun"). An empty array when that wording is
     * empty, null when it contains anything that isn't a day, so it is never
     * guessed at.
     *
     * @return array<int, int>|null
     */
    private static function days(string $prefix): ?array
    {
        $prefix = strtolower(trim($prefix));
        $prefix = trim(preg_replace('/^(every|each|on)\s+/', '', $prefix), " ,;:-");
        if ($prefix === '') {
            return [];
        }

        $days = [];
        foreach (preg_split('/\s*(?:,|&|\/|\band\b)\s*|\s+/', $prefix, -1, PREG_SPLIT_NO_EMPTY) as $token) {
            $stem = preg_replace('/s$/', '', $token);
            $found = null;
            if (strlen($stem) >= 3) {
                foreach (self::FULL_DAYS as $index => $name) {
                    if (str_starts_with($name, $stem)) {
                        $found = $index;
                        break;
                    }
                }
            }
            if ($found === null) {
                return null;
            }
            $days[] = $found;
        }

        $days = array_values(array_unique($days));
        sort($days);

        return $days;
    }
}
