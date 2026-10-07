<template>
  <!-- Wide: the History section wants the room. -->
  <AdminShowShell wide>
    <template #mobile-header>
      <AdminMobileHeader :title="market.name" :href="route('admin.market.index')" />
    </template>

    <!-- Edit is the only icon: Ignore needs words, so it's a labelled
         control in the Status section (FORM_DESIGN.md → Show pages). -->
    <template #actions>
      <IconButton
        :href="route('admin.market.edit', market.id)"
        label="Edit market"
        class="rounded-md text-gray-400 hover:text-gray-600 active:bg-gray-100 dark:text-gray-500 dark:hover:text-gray-300 dark:active:bg-gray-700"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
        </svg>
      </IconButton>
    </template>

    <template #header>
      <Link
        :href="route('admin.market.index')"
        class="hidden md:inline-flex tap-target-touch items-center text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white text-sm"
      >
        &larr; Back to Markets
      </Link>
    </template>

    <div class="space-y-8">
      <!-- State only; the command to undo it is in the Danger zone. -->
      <div v-if="market.ignored_at" class="rounded-md bg-gray-100 dark:bg-gray-700/60 p-4 text-sm">
        <p class="font-medium text-gray-900 dark:text-white">Ignored since {{ ignoredSince }}</p>
        <p v-if="market.ignored_reason" class="mt-0.5 text-gray-700 dark:text-gray-300">{{ market.ignored_reason }}</p>
        <p class="mt-0.5 text-gray-500 dark:text-gray-400">Hidden from the markets list and calendar. Imports still keep its details up to date. Un-ignore it in the Danger zone.</p>
      </div>

      <div>
        <!-- The name stays in the card on mobile too: AdminMobileHeader
             truncates it to one line, this wraps. md:pr-40 keeps it clear
             of the actions pill, which overlays this row on desktop. -->
        <div class="md:mt-2 md:pr-40">
          <InlineField
            label="Name"
            :model-value="market.name"
            value-class="text-xl md:text-2xl font-semibold leading-tight text-gray-900 dark:text-white"
            :on-save="(v) => saveField('name', v)"
          />
        </div>

        <!-- One wrapper for the score and its checked-on date, so a wrap at
             phone width never leaves "checked" on a row of its own. -->
        <div class="mt-1 flex flex-wrap items-center gap-x-1.5 text-sm text-gray-700 dark:text-gray-300">
          <span v-if="market.liveness_score !== null" class="w-2 h-2 rounded-full flex-shrink-0" :class="livenessDotClass(market.liveness_score)"></span>
          <InlineField
            label="Liveness Score"
            type="select"
            :model-value="market.liveness_score"
            :display-value="market.liveness_score !== null ? `${market.liveness_score}/4 · ${livenessLabel(market.liveness_score)}` : null"
            :options="livenessOptions"
            placeholder="Liveness not checked"
            :on-save="(v) => saveField('liveness_score', v)"
          />
          <span v-if="market.liveness_score !== null" class="inline-flex items-center gap-1 text-gray-400 dark:text-gray-500">
            · checked
            <InlineField
              label="Checked On"
              type="date"
              :model-value="market.liveness_checked_at?.slice(0, 10) ?? null"
              :display-value="formatDate(market.liveness_checked_at)"
              value-class="text-sm text-gray-400 dark:text-gray-500"
              :on-save="(v) => saveField('liveness_checked_at', v)"
            />
          </span>
        </div>

        <dl class="mt-6 grid grid-cols-2 gap-x-6 gap-y-4 sm:grid-cols-4">
          <div class="min-w-0">
            <dt class="text-sm text-gray-500 dark:text-gray-400">City</dt>
            <dd class="mt-0.5">
              <InlineField label="City" :model-value="market.city" :datalist-options="props.cities" :on-save="(v) => saveField('city', v)" />
              <span
                v-if="props.locationStatus !== 'ok'"
                class="mt-1 inline-flex items-center rounded-md bg-amber-50 px-1.5 py-0.5 text-xs font-medium text-amber-800 ring-1 ring-inset ring-amber-200 dark:bg-amber-500/15 dark:text-amber-200 dark:ring-amber-400/30"
                :title="props.locationStatus === 'no_city' ? 'No city is set, so this market can\'t be found by distance.' : 'This city isn\'t in the places list, so this market can\'t be found by distance.'"
              >{{ props.locationStatus === 'no_city' ? 'No city' : 'Town not found' }}</span>
            </dd>
          </div>
          <div class="min-w-0">
            <dt class="text-sm text-gray-500 dark:text-gray-400">Region</dt>
            <dd class="mt-0.5"><InlineField label="Region" type="select" :model-value="market.region" :options="regionOptions" :on-save="(v) => saveField('region', v)" /></dd>
          </div>
          <div class="min-w-0">
            <dt class="text-sm text-gray-500 dark:text-gray-400">Type</dt>
            <dd class="mt-0.5"><InlineField label="Market Type" :model-value="market.market_type" :datalist-options="props.marketTypes" :on-save="(v) => saveField('market_type', v)" /></dd>
          </div>
          <div class="min-w-0">
            <dt class="text-sm text-gray-500 dark:text-gray-400">Sponsor</dt>
            <dd class="mt-0.5"><InlineField label="Sponsor" :model-value="market.sponsor" :on-save="(v) => saveField('sponsor', v)" /></dd>
          </div>
        </dl>
      </div>

      <section>
        <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Schedules</h2>
        <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Tap a schedule to edit it. Adding or removing one still happens on the Edit page.</p>
        <p v-if="market.schedules.length === 0" class="mt-3 text-sm text-gray-500 dark:text-gray-400">No schedules yet.</p>
        <ul v-else class="mt-3 divide-y divide-gray-200 dark:divide-gray-700 rounded-md border border-gray-200 dark:border-gray-700">
          <li v-for="schedule in market.schedules" :key="schedule.id">
            <button
              type="button"
              class="tap-target-touch flex w-full items-start justify-between gap-4 px-4 py-3 text-left hover:bg-gray-50 dark:hover:bg-gray-700/50"
              @click="editingSchedule = schedule"
            >
              <div class="min-w-0 space-y-0.5">
                <div class="text-sm font-medium text-gray-900 dark:text-white">
                  {{ schedule.label ?? 'Schedule' }}
                  <span v-if="schedule.frequency" class="ml-1.5 text-xs font-normal text-gray-500 dark:text-gray-400">{{ frequencyLabel(schedule.frequency) }}</span>
                </div>
                <div v-if="dateRange(schedule) || recurrenceSummary(schedule)" class="flex flex-wrap items-center gap-2 py-1">
                  <span
                    v-if="dateRange(schedule)"
                    class="inline-flex items-center gap-1.5 rounded-md bg-indigo-50 px-2.5 py-1 text-sm font-semibold tabular-nums text-indigo-700 ring-1 ring-inset ring-indigo-200 dark:bg-indigo-500/15 dark:text-indigo-200 dark:ring-indigo-400/30"
                  >
                    <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3.75 8.25h16.5M5.25 5.25h13.5a1.5 1.5 0 0 1 1.5 1.5v12a1.5 1.5 0 0 1-1.5 1.5H5.25a1.5 1.5 0 0 1-1.5-1.5v-12a1.5 1.5 0 0 1 1.5-1.5Z" /></svg>
                    {{ dateRange(schedule) }}
                  </span>
                  <span
                    v-if="recurrenceSummary(schedule)"
                    class="inline-flex items-center gap-1.5 rounded-md bg-gray-100 px-2.5 py-1 text-sm font-semibold tabular-nums text-gray-900 ring-1 ring-inset ring-gray-200 dark:bg-gray-700/60 dark:text-white dark:ring-gray-600"
                  >
                    <svg class="h-4 w-4 shrink-0 text-gray-400 dark:text-gray-400" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75V12l3.25 1.9M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                    {{ recurrenceSummary(schedule) }}
                  </span>
                </div>
                <div v-if="schedule.notes" class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line">{{ schedule.notes }}</div>
                <div v-if="schedule.address_line1" class="text-sm text-gray-500 dark:text-gray-400">At {{ schedule.address_line1 }}</div>
              </div>
              <span
                class="shrink-0 inline-flex items-center gap-1.5 text-sm text-gray-700 dark:text-gray-300"
                :title="`${livenessLabel(schedule.liveness_score)} · checked ${formatDate(schedule.liveness_checked_at)}`"
              >
                <span class="w-2 h-2 rounded-full flex-shrink-0" :class="livenessDotClass(schedule.liveness_score)"></span>
                {{ schedule.liveness_score }}/4
              </span>
            </button>
          </li>
        </ul>
      </section>

      <section>
        <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Location</h2>
        <dl class="mt-3 divide-y divide-gray-200 dark:divide-gray-700">
          <div class="py-2 sm:grid sm:grid-cols-3 sm:gap-4">
            <dt class="text-sm text-gray-500 dark:text-gray-400">Street address</dt>
            <dd class="mt-0.5 sm:mt-0 sm:col-span-2"><InlineField label="Street address" :model-value="market.address_line1" placeholder="Add a street address" :on-save="(v) => saveField('address_line1', v)" /></dd>
          </div>
          <div class="py-2 sm:grid sm:grid-cols-3 sm:gap-4">
            <dt class="text-sm text-gray-500 dark:text-gray-400">Unit / suite</dt>
            <dd class="mt-0.5 sm:mt-0 sm:col-span-2"><InlineField label="Unit / suite" :model-value="market.address_line2" placeholder="Add a unit or suite" :on-save="(v) => saveField('address_line2', v)" /></dd>
          </div>
          <div class="py-2 sm:grid sm:grid-cols-3 sm:gap-4">
            <dt class="text-sm text-gray-500 dark:text-gray-400">Province</dt>
            <dd class="mt-0.5 sm:mt-0 sm:col-span-2"><InlineField label="Province" :model-value="market.province" placeholder="Add a province" :on-save="(v) => saveField('province', v)" /></dd>
          </div>
          <div class="py-2 sm:grid sm:grid-cols-3 sm:gap-4">
            <dt class="text-sm text-gray-500 dark:text-gray-400">Postal code</dt>
            <dd class="mt-0.5 sm:mt-0 sm:col-span-2"><InlineField label="Postal code" :model-value="market.postal_code" placeholder="Add a postal code" :on-save="(v) => saveField('postal_code', v)" /></dd>
          </div>
        </dl>
      </section>

      <section>
        <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Contact</h2>
        <dl class="mt-3 divide-y divide-gray-200 dark:divide-gray-700">
          <div class="py-2 sm:grid sm:grid-cols-3 sm:gap-4">
            <dt class="text-sm text-gray-500 dark:text-gray-400">Phone</dt>
            <dd class="mt-0.5 sm:mt-0 sm:col-span-2"><InlineField label="Phone" type="tel" :model-value="market.phone" :href="market.phone ? `tel:${market.phone}` : null" placeholder="Add a phone number" :on-save="(v) => saveField('phone', v)" /></dd>
          </div>
          <div class="py-2 sm:grid sm:grid-cols-3 sm:gap-4">
            <dt class="text-sm text-gray-500 dark:text-gray-400">Manager</dt>
            <dd class="mt-0.5 sm:mt-0 sm:col-span-2"><InlineField label="Manager" :model-value="market.manager" placeholder="Add a manager" :on-save="(v) => saveField('manager', v)" /></dd>
          </div>
          <div class="py-2 sm:grid sm:grid-cols-3 sm:gap-4">
            <dt class="text-sm text-gray-500 dark:text-gray-400">Manager phone</dt>
            <dd class="mt-0.5 sm:mt-0 sm:col-span-2"><InlineField label="Manager phone" type="tel" :model-value="market.manager_phone" :href="market.manager_phone ? `tel:${market.manager_phone}` : null" placeholder="Add a manager phone" :on-save="(v) => saveField('manager_phone', v)" /></dd>
          </div>
          <div class="py-2 sm:grid sm:grid-cols-3 sm:gap-4">
            <dt class="text-sm text-gray-500 dark:text-gray-400">Email</dt>
            <dd class="mt-0.5 sm:mt-0 sm:col-span-2"><InlineField label="Email" type="email" :model-value="market.manager_email" :href="market.manager_email ? `mailto:${market.manager_email}` : null" placeholder="Add an email" :on-save="(v) => saveField('manager_email', v)" /></dd>
          </div>
          <div class="py-2 sm:grid sm:grid-cols-3 sm:gap-4">
            <dt class="text-sm text-gray-500 dark:text-gray-400">Facebook</dt>
            <dd class="mt-0.5 sm:mt-0 sm:col-span-2"><InlineField label="Facebook" type="url" :model-value="market.facebook_page" :href="isHttpUrl(market.facebook_page) ? market.facebook_page : null" external placeholder="Add a Facebook page" :on-save="(v) => saveField('facebook_page', v)" /></dd>
          </div>
          <div class="py-2 sm:grid sm:grid-cols-3 sm:gap-4">
            <dt class="text-sm text-gray-500 dark:text-gray-400">Instagram</dt>
            <dd class="mt-0.5 sm:mt-0 sm:col-span-2"><InlineField label="Instagram" type="url" :model-value="market.instagram_page" :href="isHttpUrl(market.instagram_page) ? market.instagram_page : null" external placeholder="Add an Instagram page" :on-save="(v) => saveField('instagram_page', v)" /></dd>
          </div>
          <div class="py-2 sm:grid sm:grid-cols-3 sm:gap-4">
            <dt class="text-sm text-gray-500 dark:text-gray-400">Website</dt>
            <dd class="mt-0.5 sm:mt-0 sm:col-span-2"><InlineField label="Website" type="url" :model-value="market.website" :href="isHttpUrl(market.website) ? market.website : null" external placeholder="Add a website" :on-save="(v) => saveField('website', v)" /></dd>
          </div>
        </dl>
      </section>

      <section>
        <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">About</h2>
        <dl class="mt-3 divide-y divide-gray-200 dark:divide-gray-700">
          <div class="py-2 sm:grid sm:grid-cols-3 sm:gap-4">
            <dt class="text-sm text-gray-500 dark:text-gray-400">Description</dt>
            <dd class="mt-0.5 sm:mt-0 sm:col-span-2"><InlineField label="Description" type="textarea" multiline :model-value="market.description" placeholder="Add a description" :on-save="(v) => saveField('description', v)" /></dd>
          </div>
          <div class="py-2 sm:grid sm:grid-cols-3 sm:gap-4">
            <dt class="text-sm text-gray-500 dark:text-gray-400">Vendor fees</dt>
            <dd class="mt-0.5 sm:mt-0 sm:col-span-2"><InlineField label="Vendor fees" type="textarea" multiline :model-value="market.vendor_fees" placeholder="Add vendor fees" :on-save="(v) => saveField('vendor_fees', v)" /></dd>
          </div>
        </dl>
      </section>

      <section>
        <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Notes &amp; Sources</h2>
        <dl class="mt-3 divide-y divide-gray-200 dark:divide-gray-700">
          <div class="py-2 sm:grid sm:grid-cols-3 sm:gap-4">
            <dt class="text-sm text-gray-500 dark:text-gray-400">Notes</dt>
            <dd class="mt-0.5 sm:mt-0 sm:col-span-2"><InlineField label="Notes" type="textarea" multiline :model-value="market.notes" placeholder="Add notes" :on-save="(v) => saveField('notes', v)" /></dd>
          </div>
          <div class="py-2 sm:grid sm:grid-cols-3 sm:gap-4">
            <dt class="text-sm text-gray-500 dark:text-gray-400">Sources</dt>
            <dd class="mt-0.5 sm:mt-0 sm:col-span-2"><InlineField label="Sources" type="textarea" linkify-lines :model-value="market.sources" placeholder="Add sources" :on-save="(v) => saveField('sources', v)" /></dd>
          </div>
        </dl>
      </section>

      <section v-if="feed.config.enabled" class="hidden md:block">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">History</h2>
        <div class="mt-3">
          <MarketEventFilters :feed="feed" />
        </div>
        <div class="mt-3 max-h-[32rem] overflow-y-auto rounded-md border border-gray-200 dark:border-gray-700 p-4">
          <MarketEventList :feed="feed" />
        </div>
      </section>
    </div>

    <!-- Danger zone (FORM_DESIGN.md): the market's consequential commands,
         at the very bottom, each a labelled "…" button that only opens a
         confirmation -- no switches. -->
    <AccountSection id="danger-zone" title="Danger zone" tone="danger" class="mt-8">
      <DangerRow
        :title="market.is_active ? 'Mark inactive' : 'Mark active'"
        :description="market.is_active
          ? 'For a market that has stopped running. It\'s left out of the list and calendar by default; its details and history are kept.'
          : 'Puts it back in the list and calendar.'"
      >
        <button
          type="button"
          :disabled="togglingActive"
          :class="[dangerButtonClass, market.is_active ? dangerButtonRed : dangerButtonGray]"
          @click="toggleActive"
        >{{ market.is_active ? 'Mark inactive…' : 'Mark active…' }}</button>
      </DangerRow>

      <DangerRow
        :title="market.ignored_at ? 'Un-ignore this market' : 'Ignore this market'"
        :description="market.ignored_at
          ? 'Shows it in the list and calendar again.'
          : 'Not relevant to us. Hides it from the list and calendar; imports still keep its details up to date.'"
      >
        <button
          v-if="market.ignored_at"
          type="button"
          :disabled="unignoring"
          :class="[dangerButtonClass, dangerButtonGray]"
          @click="unignore"
        >Un-ignore…</button>
        <button
          v-else
          type="button"
          :class="[dangerButtonClass, dangerButtonRed]"
          @click="showIgnore = true"
        >Ignore…</button>
      </DangerRow>
    </AccountSection>

    <!-- Room so the phone History drawer's bar never covers the danger zone. -->
    <div v-if="feed.config.enabled" class="h-16 md:hidden" aria-hidden="true"></div>

    <MarketEventsDrawer v-if="feed.config.enabled" :feed="feed" />

    <ScheduleEditModal
      :market-id="market.id"
      :market-name="market.name"
      :schedule="editingSchedule"
      :frequencies="props.frequencies"
      :liveness-labels="props.livenessLabels"
      @close="editingSchedule = null"
      @saved="handleScheduleSaved"
    />

    <IgnoreMarketModal :show="showIgnore" :market-id="market.id" :market-name="market.name" @close="showIgnore = false" />
  </AdminShowShell>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AdminMobileHeader from '@/Components/Admin/AdminMobileHeader.vue'
import AdminShowShell from '@/Components/Admin/AdminShowShell.vue'
import IconButton from '@/Components/IconButton.vue'
import AccountSection from '@/Components/Admin/Accounts/AccountSection.vue'
import DangerRow from '@/Components/Admin/Accounts/DangerRow.vue'
import { useConfirmDialog } from '@/composables/useConfirmDialog'
import InlineField from './Partials/InlineField.vue'
import MarketEventFilters from './Partials/MarketEventFilters.vue'
import MarketEventList from './Partials/MarketEventList.vue'
import MarketEventsDrawer from './Partials/MarketEventsDrawer.vue'
import ScheduleEditModal from './Partials/ScheduleEditModal.vue'
import IgnoreMarketModal from './Partials/IgnoreMarketModal.vue'
import { recurrenceSummary } from './Partials/scheduleSummary'
import { useMarketEvents, type HistoryConfig } from './Partials/useMarketEvents'

defineOptions({ layout: (h, page) => h(AdminLayout, { hideBreadcrumbOnMobile: true }, () => page) })

interface ScheduleDetail {
  id: number
  label: string | null
  frequency: string | null
  weekdays: number[] | null
  week_of_month: number | null
  start_time: string | null
  end_time: string | null
  start_date: string | null
  end_date: string | null
  address_line1: string | null
  notes: string | null
  liveness_score: number
  liveness_checked_at: string
}

interface MarketDetail {
  id: number
  name: string
  city: string | null
  region: string | null
  market_type: string | null
  sponsor: string | null
  address_line1: string | null
  address_line2: string | null
  province: string | null
  postal_code: string | null
  vendor_fees: string | null
  phone: string | null
  manager: string | null
  manager_phone: string | null
  manager_email: string | null
  facebook_page: string | null
  instagram_page: string | null
  website: string | null
  description: string | null
  notes: string | null
  sources: string | null
  liveness_score: number | null
  liveness_checked_at: string | null
  is_active: boolean
  ignored_at: string | null
  ignored_reason: string | null
  schedules: ScheduleDetail[]
}

interface Props {
  market: MarketDetail
  locationStatus: 'ok' | 'no_city' | 'unknown_town'
  cities: string[]
  regions: string[]
  marketTypes: string[]
  frequencies: Record<string, string>
  livenessLabels: Record<number, string>
  history: HistoryConfig
}

const props = defineProps<Props>()

// A computed, not a plain destructure -- Inertia replaces the whole `market`
// prop object on each visit (it doesn't mutate the old one in place), so a
// bare `const market = props.market` would capture one snapshot and never
// see the fresh value after an inline save revisits the page. Template
// usages (`market.xxx`) auto-unwrap the computed the same way a ref would.
const market = computed(() => props.market)

// One feed for both the desktop section and the mobile drawer.
const feed = useMarketEvents(props.history)

const frequencyLabel = (value: string) => props.frequencies[value] ?? value
const livenessLabel = (score: number) => props.livenessLabels[score] ?? 'Unknown'

const regionOptions = props.regions.map((r) => ({ value: r, label: r }))
const livenessOptions = Object.entries(props.livenessLabels).map(([value, label]) => ({ value: Number(value), label: `${value}/4 · ${label}` }))

// Same bands as Index.vue: 4 confidently active, 2-3 probably active, 0-1 likely defunct.
const livenessDotClass = (score: number) => {
  if (score >= 4) return 'bg-emerald-500 dark:bg-emerald-400'
  if (score >= 2) return 'bg-amber-500 dark:bg-amber-400'
  return 'bg-red-500 dark:bg-red-400'
}

// Eloquent's date cast serializes to a full ISO datetime; parse the
// YYYY-MM-DD prefix as a local date so a timezone offset can't shift the day.
const formatDate = (iso: string | null | undefined) => {
  if (!iso) return null
  const [y, m, d] = iso.slice(0, 10).split('-').map(Number)
  return new Date(y, m - 1, d).toLocaleDateString('en-CA', { month: 'short', day: 'numeric', year: 'numeric' })
}

// A single day reads best with its weekday ("Sat, Aug 8, 2026").
const formatDay = (iso: string) => {
  const [y, m, d] = iso.slice(0, 10).split('-').map(Number)
  return new Date(y, m - 1, d).toLocaleDateString('en-CA', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' })
}

const dateRange = (s: ScheduleDetail) => {
  if (s.start_date && s.end_date && s.start_date.slice(0, 10) === s.end_date.slice(0, 10)) return formatDay(s.start_date)
  const start = formatDate(s.start_date)
  const end = formatDate(s.end_date)
  if (start && end) return `${start} – ${end}`
  if (start) return `From ${start}`
  if (end) return `Until ${end}`
  return null
}

// Only http(s) values become links -- these fields are free text from
// admins/imports, and a "javascript:" URL must never end up in an href.
const isHttpUrl = (value: string | null): value is string => !!value && /^https?:\/\//i.test(value)

/**
 * Saves one field via the inline editor's PATCH endpoint. Inertia's own
 * request/response cycle (not a plain fetch): the controller redirects back
 * to this page, so market updates the normal Inertia way -- preserveState
 * keeps every InlineField's own local state (which one is mid-edit, its
 * draft text) intact across the revisit instead of remounting the page.
 */
const saveField = (field: string, value: string | number | boolean | null): Promise<void> => {
  return new Promise((resolve, reject) => {
    router.patch(
      route('admin.market.update-field', market.value.id),
      { field, value },
      {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
          // preserveState keeps the page mounted, so nothing else re-triggers
          // the History feed's own fetch -- without this it would keep
          // showing whatever it last loaded, one save behind.
          feed.reload()
          resolve()
        },
        onError: (errors) => reject(new Error(String(Object.values(errors)[0] ?? 'Could not save.'))),
      },
    )
  })
}

// The schedule currently open in ScheduleEditModal -- null closes it. Set
// from the clicked row's own object rather than looking it up again, since
// market.schedules already has it.
const editingSchedule = ref<ScheduleDetail | null>(null)

const handleScheduleSaved = () => {
  editingSchedule.value = null
  // Same reason as saveField's own feed.reload(): preserveState keeps the
  // page mounted, so nothing else re-triggers the History feed's fetch.
  feed.reload()
}

const { confirmDialog } = useConfirmDialog()

// Danger zone buttons, same as Accounts/Show: red for the command that
// takes something away, grey for the one that undoes it.
const dangerButtonClass = 'tap-target-touch inline-flex items-center justify-center px-4 py-2 rounded-md border text-sm font-medium disabled:opacity-50'
const dangerButtonRed = 'border-red-300 text-red-700 hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20'
const dangerButtonGray = 'border-gray-300 text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700'

const togglingActive = ref(false)

// Ignore opens IgnoreMarketModal (for the optional reason), which is its
// confirmation; Un-ignore gets a plain confirm, since nothing in the danger
// zone is one click.
const showIgnore = ref(false)
const unignoring = ref(false)
const unignore = async () => {
  const confirmed = await confirmDialog({
    title: 'Un-ignore market',
    message: `Show "${market.value.name}" in the markets list and calendar again?`,
    confirmLabel: 'Un-ignore',
    variant: 'info',
  })
  if (!confirmed) return
  router.patch(route('admin.market.ignore', market.value.id), { ignored: false }, {
    preserveScroll: true,
    onStart: () => (unignoring.value = true),
    onFinish: () => (unignoring.value = false),
  })
}
// ignored_at is a full timestamp (UTC), so format it in local time rather
// than slicing its date part like the date-only fields.
const ignoredSince = computed(() =>
  market.value.ignored_at
    ? new Date(market.value.ignored_at).toLocaleDateString('en-CA', { month: 'short', day: 'numeric', year: 'numeric' })
    : '',
)

const toggleActive = async () => {
  const deactivating = market.value.is_active
  const confirmed = await confirmDialog({
    title: deactivating ? 'Mark market inactive' : 'Mark market active',
    message: deactivating
      ? `Mark "${market.value.name}" as no longer running? It's left out of the list and calendar by default.`
      : `Mark "${market.value.name}" as running again?`,
    confirmLabel: deactivating ? 'Mark inactive' : 'Mark active',
    variant: deactivating ? 'danger' : 'info',
  })
  if (!confirmed) return
  togglingActive.value = true
  try {
    // A real boolean, not '1'/'' -- Laravel's 'boolean' rule doesn't accept
    // an empty string (and the ConvertEmptyStringsToNull middleware turns it
    // into null first anyway), so that pairing silently failed validation
    // every time with no visible error.
    await saveField('is_active', !market.value.is_active)
  } catch {
    // The button just stays as it was -- saveField's own errors aren't
    // surfaced anywhere for this control, so silently not-toggling is the
    // honest result rather than claiming a change that didn't happen.
  } finally {
    togglingActive.value = false
  }
}
</script>
