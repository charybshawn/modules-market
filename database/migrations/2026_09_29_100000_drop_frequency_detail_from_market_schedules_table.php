<?php

use Cultpantry\Market\Support\LegacyScheduleDetail;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The free-text "days & hours" note is retired: opens/closes and days are
     * the structured fields, and any other wording lives in notes. Each row's
     * text is folded into those first, so the column is only dropped once its
     * content has somewhere to live (anything not fully understood is copied
     * whole into notes).
     */
    public function up(): void
    {
        DB::table('market_schedules')->whereNotNull('frequency_detail')->orderBy('id')->each(function ($row) {
            $weekdays = $row->weekdays === null ? null : json_decode($row->weekdays, true);

            $result = LegacyScheduleDetail::apply(
                $row->frequency_detail,
                $row->frequency,
                $row->start_date !== null || $row->end_date !== null,
                is_array($weekdays) && $weekdays !== [] ? $weekdays : null,
                $row->start_time === null ? null : substr($row->start_time, 0, 5),
                $row->end_time === null ? null : substr($row->end_time, 0, 5),
            );

            DB::table('market_schedules')->where('id', $row->id)->update([
                'start_time' => $result['start_time'],
                'end_time' => $result['end_time'],
                'weekdays' => $result['weekdays'] === null ? null : json_encode($result['weekdays']),
                'notes' => LegacyScheduleDetail::mergeNotes($row->notes, $result['note']),
            ]);
        });

        Schema::table('market_schedules', function (Blueprint $table) {
            $table->dropColumn('frequency_detail');
        });
    }

    /**
     * Brings the column back empty. The text that was folded into notes and
     * the structured fields stays where it is.
     */
    public function down(): void
    {
        Schema::table('market_schedules', function (Blueprint $table) {
            $table->text('frequency_detail')->nullable()->after('frequency');
        });
    }
};
