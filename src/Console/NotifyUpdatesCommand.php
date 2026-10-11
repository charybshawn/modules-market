<?php

namespace Cultpantry\Market\Console;

use App\Models\User;
use Cultpantry\Market\Actions\BuildUpdateDigest;
use Cultpantry\Market\Models\UpdateDigest;
use Cultpantry\Market\Notifications\MarketUpdateNotification;
use Cultpantry\Market\Support\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Emails everyone with access to the Farmer's Markets section one digest of
 * what was added since the last run (new markets, new dates for markets on
 * file), so a big import is one email, not one per market. Runs hourly after
 * the import; sends nothing when nothing is new. The first run only records a
 * baseline, so turning this on doesn't mail everything already on file.
 */
class NotifyUpdatesCommand extends Command
{
    /** The host's AdminNav key for this module's sidebar entry. */
    public const SECTION = 'farmers-markets';

    protected $signature = 'market:notify-updates
        {--dry-run : Report what would be sent and to whom, without sending or recording anything}';

    protected $description = "Email users with Farmer's Markets access a digest of new markets and dates";

    public function handle(BuildUpdateDigest $build): int
    {
        $until = now();
        $last = UpdateDigest::query()->latest('covers_through')->first();
        $dryRun = (bool) $this->option('dry-run');

        if ($last === null) {
            if (! $dryRun) {
                UpdateDigest::create(['covers_through' => $until, 'recipients' => 0]);
            }
            $this->line('First run: recorded a baseline; nothing sent.');

            return self::SUCCESS;
        }

        $digest = $build->handle($last->covers_through, $until);
        if ($digest['total'] === 0) {
            $this->line('Nothing new.');

            return self::SUCCESS;
        }

        $recipients = static::recipients();
        $this->line("{$digest['markets']} new market(s), new dates for {$digest['dated_markets']}; ".$recipients->count().' recipient(s).');

        if ($dryRun) {
            $recipients->each(fn (User $u) => $this->line("  would email {$u->email}"));

            return self::SUCCESS;
        }

        Notification::send($recipients, new MarketUpdateNotification($digest));
        Audit::record('market.update_digest_sent', "Farmer's Markets update email sent to {$recipients->count()} recipient(s)", [
            'markets' => $digest['markets'],
            'dated_markets' => $digest['dated_markets'],
            'recipients' => $recipients->pluck('email')->all(),
        ]);
        UpdateDigest::create([
            'covers_through' => $until,
            'counts' => ['markets' => $digest['markets'], 'dated_markets' => $digest['dated_markets']],
            'recipients' => $recipients->count(),
        ]);

        return self::SUCCESS;
    }

    /**
     * Active, verified users who can open the Farmer's Markets section: admins
     * with no section list, and anyone granted it.
     *
     * @return Collection<int, User>
     */
    public static function recipients(): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->whereNotNull('email_verified_at')
            ->where(fn ($q) => $q->whereIn('role', ['admin', 'super_admin'])->orWhereNotNull('admin_permissions'))
            ->get()
            ->filter(fn (User $u) => $u->canAccessAdminPanel() && $u->canAccessSection(self::SECTION))
            ->values();
    }
}
