<template>
  <div class="pb-36 md:pt-6 md:pb-6">
    <div>
      <AdminMobileHeader title="Farmer's Markets" />

      <!-- Desktop: title + actions band. Mobile: AdminMobileHeader above
           covers the title, actions collapse into the hero block below
           instead of a second fixed bottom bar -- matches the pattern
           established on cultpantry/costing's Ingredients page. -->
      <div class="hidden md:flex md:items-center md:justify-between mb-6">
        <div>
          <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Farmer's Markets</h1>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Artisan farmer's markets across British Columbia.
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <input ref="fileInput" type="file" accept=".xml,text/xml,application/xml" class="hidden" @change="handleFileChange" />
          <button
            type="button"
            :disabled="importForm.processing"
            title="Re-importing an updated file safely updates existing markets (matched on name + city) instead of duplicating them."
            class="tap-target-touch inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 disabled:opacity-50"
            @click="fileInput?.click()"
          >
            <span v-if="importForm.processing">Importing...</span>
            <span v-else>Import XML</span>
          </button>
          <!-- Plain <a>, not Inertia's <Link>: <Link> intercepts the click
               and treats the file response (no X-Inertia header) as a
               failed page visit -- confirmed by hand, it shows a blank
               in-page overlay instead of letting the browser download the
               file. A native anchor isn't intercepted at all. -->
          <a
            :href="route('admin.market.export')"
            title="Downloads every market (active and inactive) as an XML file shaped for this same Import XML form -- for moving the whole list to another server."
            class="tap-target-touch inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600"
          >
            Export XML
          </a>
          <a
            :href="pdfExportHref"
            :title="`Downloads a print-ready PDF of the ${props.markets.meta?.total ?? 0} market${props.markets.meta?.total === 1 ? '' : 's'} matching the search and filters below.`"
            class="tap-target-touch inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600"
          >
            Download PDF
          </a>
          <Link
            :href="route('admin.market.calendar')"
            class="tap-target-touch inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600"
          >
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            Calendar
          </Link>
          <Link
            :href="route('admin.market.create')"
            class="tap-target-touch inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700"
          >
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Add Market
          </Link>
        </div>
      </div>

      <!-- Mobile-only hero block, matching Admin/Dashboard.vue and
           Admin/Inventory/Dashboard.vue's own hero shell exactly (same
           colored card, w-16 h-16 rounded-2xl tile, Archivo Black label).
           Two tiles: Add Market, Import XML. -->
      <div class="md:hidden mb-6 rounded-lg bg-gray-200 dark:bg-amber-500 px-5 pt-[30px] pb-[20px]">
        <div class="text-center">
          <div class="text-sm font-bold text-gray-800">Active Markets</div>
          <div class="mt-1 text-4xl font-extrabold text-emerald-600">{{ props.counts.active }}</div>
        </div>
        <div class="mt-[30px] flex items-center justify-around">
          <div class="flex flex-col items-center gap-3">
            <Link
              :href="route('admin.market.create')"
              class="tap-target-touch w-16 h-16 rounded-2xl bg-white dark:bg-gray-900 shadow-md dark:shadow-[0_4px_10px_rgba(0,0,0,0.5)] flex items-center justify-center"
            >
              <svg class="w-11 h-11 text-amber-500 dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
              </svg>
            </Link>
            <span class="text-xs font-['Archivo_Black'] uppercase tracking-wide text-amber-500 dark:text-white leading-tight text-center">Add<br>Market</span>
          </div>

          <div class="flex flex-col items-center gap-3">
            <input ref="fileInputMobile" type="file" accept=".xml,text/xml,application/xml" class="hidden" @change="handleFileChange" />
            <button
              type="button"
              :disabled="importForm.processing"
              class="tap-target-touch w-16 h-16 rounded-2xl bg-white dark:bg-gray-900 shadow-md dark:shadow-[0_4px_10px_rgba(0,0,0,0.5)] flex items-center justify-center disabled:opacity-50"
              @click="fileInputMobile?.click()"
            >
              <svg class="w-11 h-11 text-amber-500 dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1M7 10l5 5 5-5M12 15V3" />
              </svg>
            </button>
            <span class="text-xs font-['Archivo_Black'] uppercase tracking-wide text-amber-500 dark:text-white leading-tight text-center">Import<br>XML</span>
          </div>

          <div class="flex flex-col items-center gap-3">
            <a
              :href="route('admin.market.export')"
              class="tap-target-touch w-16 h-16 rounded-2xl bg-white dark:bg-gray-900 shadow-md dark:shadow-[0_4px_10px_rgba(0,0,0,0.5)] flex items-center justify-center"
            >
              <svg class="w-11 h-11 text-amber-500 dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1M7 13l5-5 5 5M12 3v11" />
              </svg>
            </a>
            <span class="text-xs font-['Archivo_Black'] uppercase tracking-wide text-amber-500 dark:text-white leading-tight text-center">Export<br>XML</span>
          </div>

          <div class="flex flex-col items-center gap-3">
            <a
              :href="pdfExportHref"
              class="tap-target-touch w-16 h-16 rounded-2xl bg-white dark:bg-gray-900 shadow-md dark:shadow-[0_4px_10px_rgba(0,0,0,0.5)] flex items-center justify-center"
            >
              <svg class="w-11 h-11 text-amber-500 dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
            </a>
            <span class="text-xs font-['Archivo_Black'] uppercase tracking-wide text-amber-500 dark:text-white leading-tight text-center">Download<br>PDF</span>
          </div>
        </div>
        <Link
          :href="route('admin.market.calendar')"
          class="tap-target-touch mt-6 flex items-center justify-center gap-2 rounded-xl bg-white dark:bg-gray-900 py-3 shadow-md dark:shadow-[0_4px_10px_rgba(0,0,0,0.5)] text-xs font-['Archivo_Black'] uppercase tracking-wide text-amber-500 dark:text-white"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
          </svg>
          Market Calendar
        </Link>
      </div>

      <FormErrorSummary v-if="Object.keys(importForm.errors).length" :errors="importForm.errors" class="mb-6" />

      <p v-if="props.routingProblem" class="mb-3 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:bg-amber-500/10 dark:text-amber-200" role="status">
        {{ props.routingProblem }}
      </p>
      <p v-else-if="nearTown && props.unlocated" class="mb-3 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:bg-amber-500/10 dark:text-amber-200" role="status">
        {{ props.unlocated }} market{{ props.unlocated === 1 ? ' has' : 's have' }} no drive time, so {{ props.unlocated === 1 ? "it isn't" : "they aren't" }} included in this search
        (no city, a town that isn't in the list, or no road route). Look for the badge in the list.
      </p>

      <!-- No overflow-hidden here: it establishes a containing block for
           DataTable's sticky toolbar, pinning it at a fixed offset inside
           this box instead of sticking to the viewport. -->
      <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg">
        <DataTable
          :columns="columns"
          :items="accumulatedMarkets"
          :sort-field="localFilters.sort"
          :sort-direction="localFilters.direction"
          server-mode
          :initial-search="localFilters.search"
          :initial-filters="initialChips"
          infinite-scroll
          collapsible-filters
          :has-more="hasMore"
          :loading-more="loadingMore"
          :total-count="props.markets.meta?.total"
          filter-grid-class="grid-cols-1 sm:grid-cols-2 lg:grid-cols-4"
          :extra-filter-count="customFilterCount"
          table-id="market-markets"
          item-key="id"
          searchable
          search-placeholder="Search markets..."
          @sort="handleSort"
          @filters-change="handleFiltersChange"
          @load-more="handleLoadMore"
          @clear-filters="clearCustomFilters"
          :empty-message="customFilterCount ? 'No markets match these schedule or liveness filters.' : 'No markets yet.'"
          :empty-action-label="customFilterCount ? '' : 'Add your first market'"
          :empty-action-href="route('admin.market.create')"
          mobile-row-style="line"
          :row-href="(item) => route('admin.market.show', item.id)"
        >
          <!-- Filters DataTable's own chips can't express: schedule frequency
               with exclusion ("not weekly"), months a schedule falls in, and a
               liveness range. They render inside DataTable's Filters dropdown
               and are sent to the server with the rest of the filters. -->
          <template #filters-extra>
            <div class="border-t border-gray-200 pt-4 dark:border-gray-600">
              <NearFilter v-model:near="nearTown" v-model:radius="nearRadius" :towns="props.places" class="mb-5" />
              <ScheduleLiveFilters
                v-model:freq-states="freqStates"
                v-model:months="months"
                v-model:match-mode="matchMode"
                v-model:liveness-min="livenessMin"
                v-model:liveness-max="livenessMax"
                v-model:include-unchecked="includeUnchecked"
                :frequencies="props.frequencies"
                :custom-filter-count="customFilterCount"
                @clear="clearCustomFilters"
              />
            </div>
          </template>

          <template #mobile-card="{ item }">
            <div class="min-w-0">
              <div class="flex items-center gap-3 min-w-0">
                <span class="min-w-0 flex-1 truncate text-sm font-medium text-gray-900 dark:text-white">{{ item.name }}</span>
                <span v-if="item.status !== 'active'" class="shrink-0 text-xs font-medium text-gray-400 dark:text-gray-500">{{ statusLabel(item.status) }}</span>
                <span v-if="item.location_status !== 'ok'" :class="[badgeClass, 'shrink-0']" :title="locationHint(item.location_status)">{{ locationLabel(item.location_status) }}</span>
                <span class="shrink-0 truncate max-w-[40%] text-sm text-gray-500 dark:text-gray-400">{{ item.city ?? '—' }}</span>
                <span v-if="item.drive_minutes != null" class="shrink-0 text-sm font-medium tabular-nums text-gray-900 dark:text-white">{{ formatDrive(item.drive_minutes) }}</span>
              </div>
              <div v-if="item.sponsor" class="truncate text-xs text-gray-400 dark:text-gray-500">{{ item.sponsor }}</div>
            </div>
          </template>

          <template #cell-name="{ item }">
            <div class="text-sm font-medium text-gray-900 dark:text-white">
              {{ item.name }}
              <span v-if="item.status !== 'active'" class="ml-1.5 text-xs font-normal text-gray-400 dark:text-gray-500">({{ statusLabel(item.status) }})</span>
              <span v-if="item.location_status !== 'ok'" :class="[badgeClass, 'ml-1.5 align-middle']" :title="locationHint(item.location_status)">{{ locationLabel(item.location_status) }}</span>
            </div>
            <div v-if="item.sponsor" class="text-xs text-gray-400 dark:text-gray-500">{{ item.sponsor }}</div>
          </template>

          <template #cell-city="{ item }">
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ item.city ?? '—' }}</span>
          </template>

          <template #cell-drive_minutes="{ item }">
            <template v-if="item.drive_minutes === null"><span class="text-sm text-gray-400 dark:text-gray-500">—</span></template>
            <template v-else>
              <span class="text-sm font-medium tabular-nums text-gray-900 dark:text-white">{{ formatDrive(item.drive_minutes) }}</span>
              <span v-if="item.drive_minutes > 0 && item.drive_km !== null" class="block text-xs tabular-nums text-gray-400 dark:text-gray-500">{{ Math.round(item.drive_km) }} km</span>
            </template>
          </template>

          <template #cell-region="{ item }">
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ item.region ?? '—' }}</span>
          </template>

          <template #cell-market_type="{ item }">
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ item.market_type ?? '—' }}</span>
          </template>

          <template #cell-sponsor="{ item }">
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ item.sponsor ?? '—' }}</span>
          </template>

          <template #cell-schedules="{ item }">
            <div v-if="item.schedules.length" class="text-sm text-gray-500 dark:text-gray-400">
              <div
                v-for="schedule in visibleSchedules(item)"
                :key="schedule.id"
                :class="item.matched_schedule_ids.includes(schedule.id) ? 'font-medium text-gray-900 dark:text-white' : ''"
              >
                <div class="truncate">{{ scheduleSummary(schedule) }}</div>
                <div v-if="datesShown && scheduleDates(schedule)" class="truncate text-xs font-normal text-gray-500 dark:text-gray-400">{{ scheduleDates(schedule) }}</div>
              </div>
              <div v-if="item.schedules.length > visibleSchedules(item).length" class="text-xs text-gray-400 dark:text-gray-500">+{{ item.schedules.length - visibleSchedules(item).length }} more</div>
            </div>
            <span v-else class="text-sm text-gray-400 dark:text-gray-500">—</span>
          </template>

          <template #cell-phone="{ item }">
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ item.phone ?? '—' }}</span>
          </template>

          <template #cell-liveness_score="{ item }">
            <span v-if="item.liveness_score === null" class="text-sm text-gray-400 dark:text-gray-500">Not checked</span>
            <span v-else class="inline-flex items-center gap-1.5 text-sm text-gray-700 dark:text-gray-300">
              <span
                class="w-2 h-2 rounded-full flex-shrink-0"
                :class="livenessDotClass(item.liveness_score)"
                :title="`Checked ${item.liveness_checked_at ? item.liveness_checked_at.slice(0, 10) : 'at an unknown date'}`"
              ></span>
              {{ item.liveness_score }}/4 · {{ livenessLabel(item.liveness_score) }}
            </span>
          </template>
        </DataTable>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, inject, ref, watch } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AdminMobileHeader from '@/Components/Admin/AdminMobileHeader.vue'
import DataTable, { type Column } from '@/Components/Admin/DataTable.vue'
import FormErrorSummary from '@/Components/Admin/FormErrorSummary.vue'
import { useAdminIndexTable } from '@/composables/useAdminIndexTable'
import ScheduleLiveFilters from './Partials/ScheduleLiveFilters.vue'
import NearFilter from './Partials/NearFilter.vue'

defineOptions({ layout: (h, page) => h(AdminLayout, { wide: true, hideBreadcrumbOnMobile: true }, () => page) })

interface ScheduleRow {
  id: number
  label: string | null
  frequency: string | null
  start_date: string | null
  end_date: string | null
}

interface MarketRow {
  id: number
  name: string
  city: string | null
  region: string | null
  market_type: string | null
  sponsor: string | null
  schedules: ScheduleRow[]
  matched_schedule_ids: number[]
  phone: string | null
  liveness_score: number | null
  liveness_checked_at: string | null
  is_active: boolean
  // Ignored wins over is_active (set by MarketResource).
  status: 'active' | 'inactive' | 'ignored'
  location_status: 'ok' | 'no_city' | 'unknown_town'
  drive_minutes: number | null
  drive_km: number | null
}

interface MarketFilters {
  search?: string
  // 'all', or a comma list of 'active' | 'inactive' | 'ignored'.
  status?: string
  city?: string[]
  region?: string[]
  market_type?: string[]
  sort?: string
  direction?: 'asc' | 'desc'
  freq_include?: string[]
  freq_exclude?: string[]
  months?: (number | string)[]
  match?: 'all' | 'any'
  liveness_min?: number | string
  liveness_max?: number | string
  liveness_unchecked?: boolean
  near?: string
  radius?: number | string
}

interface Props {
  markets: { data: MarketRow[]; meta?: { current_page: number; last_page: number; total: number } }
  filters: MarketFilters
  counts: { total: number; active: number }
  places: string[]
  unlocated: number | null
  routingProblem: string | null
  cities: string[]
  regions: string[]
  marketTypes: string[]
  frequencies: Record<string, string>
  livenessLabels: Record<number, string>
}

const props = defineProps<Props>()

// Why a market can't be found by distance; shown as a badge next to its name.
const badgeClass = 'inline-flex items-center rounded-md bg-amber-50 px-1.5 py-0.5 text-xs font-medium text-amber-800 ring-1 ring-inset ring-amber-200 dark:bg-amber-500/15 dark:text-amber-200 dark:ring-amber-400/30'
// "45 min", "2 h 6 min"; the drive from the place being searched near.
const formatDrive = (minutes: number) => {
  if (minutes === 0) return 'In town'
  if (minutes < 60) return `${minutes} min`
  return minutes % 60 === 0 ? `${minutes / 60} h` : `${Math.floor(minutes / 60)} h ${minutes % 60} min`
}
const locationLabel = (status: MarketRow['location_status']) => (status === 'no_city' ? 'No city' : 'Town not found')
const locationHint = (status: MarketRow['location_status']) =>
  status === 'no_city'
    ? "No city is set, so this market can't be found by distance."
    : "This city isn't in the places list, so this market can't be found by distance."

const frequencyLabel = (value: string) => props.frequencies[value] ?? value
// Eloquent serializes date casts as ISO datetimes; parse the YYYY-MM-DD prefix
// as a local date so a timezone offset can't shift the day.
const shortDate = (iso: string) => {
  const [y, m, d] = iso.slice(0, 10).split('-').map(Number)
  return new Date(y, m - 1, d).toLocaleDateString('en-CA', { month: 'short', day: 'numeric', year: 'numeric' })
}
const scheduleDates = (schedule: ScheduleRow) => {
  const start = schedule.start_date?.slice(0, 10)
  const end = schedule.end_date?.slice(0, 10)
  if (start && end && start !== end) return `${shortDate(start)} – ${shortDate(end)}`
  const only = start ?? end
  return only ? shortDate(only) : null
}
// Dates appear once a frequency or month filter is on, since "which
// October?" is the whole point then; the plain list stays compact. They sit on
// their own line directly under the schedule's title, never inline with it.
const datesShown = computed(() => includeFreqs.value.length > 0 || excludeFreqs.value.length > 0 || months.value.length > 0)
const scheduleSummary = (schedule: ScheduleRow) =>
  [
    schedule.label,
    schedule.frequency ? frequencyLabel(schedule.frequency) : null,
  ].filter(Boolean).join(' · ') || 'Schedule'
const livenessLabel = (score: number) => props.livenessLabels[score] ?? 'Unknown'

// Matches the skill's own scoring bands (see .claude/skills/find-bc-markets/
// SKILL.md): 4 reads as confidently active, 2-3 as probably active with
// gaps, 0-1 as likely defunct -- the dot is just that band at a glance,
// the "Not checked" case (null) gets no dot at all rather than a 4th color,
// since "unknown" isn't a point on the same red-to-green scale.
const livenessDotClass = (score: number) => {
  if (score >= 4) return 'bg-emerald-500 dark:bg-emerald-400'
  if (score >= 2) return 'bg-amber-500 dark:bg-amber-400'
  return 'bg-red-500 dark:bg-red-400'
}

// ---- Filters ----
//
// Search, the chips, sort and the custom filters below are all applied on the
// server (FetchMarkets), and every page of the infinite scroll is fetched under
// them. `props.filters` is what the server applied, so the page starts from it.
// Inactive markets (including anything scored 1 or below, which the model
// deactivates on save) and ignored ones are hidden unless asked for: no
// `status` means "Active", and the Status chip is how to include the others.

// Guards the same trap as the controller's cast: an empty list has an array's
// own `sort`, `filter` and so on.
const echoed: MarketFilters = Array.isArray(props.filters) ? {} : (props.filters ?? {})

const localFilters = ref<Record<string, any>>({
  search: echoed.search ?? '',
  status: echoed.status ?? 'active',
  city: echoed.city ?? [],
  region: echoed.region ?? [],
  market_type: echoed.market_type ?? [],
  sort: echoed.sort ?? 'name',
  direction: echoed.direction ?? 'asc',
  freq_include: echoed.freq_include ?? [],
  freq_exclude: echoed.freq_exclude ?? [],
  months: echoed.months ?? [],
  match: echoed.match,
  liveness_min: echoed.liveness_min,
  liveness_max: echoed.liveness_max,
  liveness_unchecked: echoed.liveness_unchecked ? 1 : undefined,
  near: echoed.near || undefined,
  radius: echoed.near ? Number(echoed.radius ?? 50) : undefined,
})

// DataTable's own chips start from what the server applied.
const initialChips = {
  status: localFilters.value.status === 'all' ? [] : localFilters.value.status.split(','),
  city: localFilters.value.city,
  region: localFilters.value.region,
  market_type: localFilters.value.market_type,
}

// ---- Custom filters (schedule frequency, month, liveness range) ----

const freqStates = ref<Record<string, 'include' | 'exclude'>>({
  ...Object.fromEntries((echoed.freq_include ?? []).map((k) => [k, 'include'])),
  ...Object.fromEntries((echoed.freq_exclude ?? []).map((k) => [k, 'exclude'])),
})
const months = ref<number[]>((echoed.months ?? []).map(Number))
const matchMode = ref<'all' | 'any'>(echoed.match === 'any' ? 'any' : 'all')
const livenessMin = ref(Number(echoed.liveness_min ?? 0))
const livenessMax = ref(Number(echoed.liveness_max ?? 4))
const includeUnchecked = ref(!!echoed.liveness_unchecked)
const nearTown = ref(echoed.near ?? '')
const nearRadius = ref(Number(echoed.radius ?? 60))

const includeFreqs = computed(() => Object.entries(freqStates.value).filter(([, v]) => v === 'include').map(([k]) => k))
const excludeFreqs = computed(() => Object.entries(freqStates.value).filter(([, v]) => v === 'exclude').map(([k]) => k))
const livenessFilterActive = computed(() => livenessMin.value > 0 || livenessMax.value < 4)
// One badge count per active group, matching how DataTable counts its chips.
const customFilterCount = computed(() =>
  (includeFreqs.value.length || excludeFreqs.value.length ? 1 : 0) + (months.value.length ? 1 : 0) + (livenessFilterActive.value ? 1 : 0) + (nearTown.value ? 1 : 0),
)

const clearCustomFilters = () => {
  freqStates.value = {}
  months.value = []
  matchMode.value = 'all'
  livenessMin.value = 0
  livenessMax.value = 4
  includeUnchecked.value = false
  nearTown.value = ''
  nearRadius.value = 60
}

const {
  accumulatedItems: accumulatedMarkets,
  hasMore,
  loadingMore,
  applyFilters,
  handleSort,
  handleFiltersChange,
  handleLoadMore,
} = useAdminIndexTable({
  routeName: 'admin.market.index',
  dataKey: 'markets',
  getItems: () => props.markets.data,
  getMeta: () => props.markets.meta,
  filters: localFilters.value,
  mapDataTableFilters: ({ search, filters }, f) => {
    f.search = search
    const status = (filters.status as string[] | undefined) ?? []
    f.status = status.length ? status.join(',') : 'all'
    f.city = (filters.city as string[] | undefined) ?? []
    f.region = (filters.region as string[] | undefined) ?? []
    f.market_type = (filters.market_type as string[] | undefined) ?? []
  },
})

// Picking a town sorts by distance (nearest first); clearing it goes back to
// the name order. Only when the sort was still the automatic one, so a column
// the user chose themselves is left alone.
watch(nearTown, (town, before) => {
  const f = localFilters.value
  if (town && !before && f.sort === 'name') {
    f.sort = 'drive_minutes'
    f.direction = 'asc'
  } else if (!town && f.sort === 'drive_minutes') {
    f.sort = 'name'
    f.direction = 'asc'
  }
})

// The custom filters live in this page's own state, so a change is pushed into
// the shared filters and refetched here (DataTable only reports its own chips).
// Debounced: the liveness slider fires on every step of a drag.
let customTimer: ReturnType<typeof setTimeout> | undefined
watch([freqStates, months, matchMode, livenessMin, livenessMax, includeUnchecked, nearTown, nearRadius], () => {
  clearTimeout(customTimer)
  customTimer = setTimeout(() => {
    const f = localFilters.value
    f.freq_include = includeFreqs.value
    f.freq_exclude = excludeFreqs.value
    f.months = months.value
    f.match = matchMode.value === 'any' ? 'any' : undefined
    f.liveness_min = livenessMin.value > 0 ? livenessMin.value : undefined
    f.liveness_max = livenessMax.value < 4 ? livenessMax.value : undefined
    f.liveness_unchecked = includeUnchecked.value ? 1 : undefined
    f.near = nearTown.value || undefined
    f.radius = nearTown.value ? nearRadius.value : undefined
    applyFilters()
  }, 250)
}, { deep: true })

// With a schedule filter on, show every schedule that matched -- they're the
// reason the market is in the list, so none may hide behind "+N more". With
// none on, keep the compact first two.
const visibleSchedules = (item: { schedules: ScheduleRow[]; matched_schedule_ids: number[] }) =>
  item.matched_schedule_ids.length
    ? item.schedules.filter((s) => item.matched_schedule_ids.includes(s.id))
    : item.schedules.slice(0, 2)

const STATUS_OPTIONS = [
  { value: 'active', label: 'Active' },
  { value: 'inactive', label: 'Inactive' },
  { value: 'ignored', label: 'Ignored' },
]
const statusLabel = (status: string) => STATUS_OPTIONS.find((o) => o.value === status)?.label ?? status

const columns = computed<Column[]>(() => [
  {
    key: 'status', label: 'Status', filterable: true, filterType: 'multiselect', filterOnly: true,
    options: STATUS_OPTIONS,
  },
  { key: 'name', label: 'Market', sortable: true },
  // Only while searching near a place: the drive to each market, beside its name.
  ...(nearTown.value ? [{ key: 'drive_minutes', label: 'Drive', sortable: true }] : []),
  { key: 'city', label: 'City', sortable: true, filterable: true, filterType: 'multiselect', options: props.cities },
  { key: 'region', label: 'Region', sortable: true, filterable: true, filterType: 'multiselect', options: props.regions },
  { key: 'market_type', label: 'Type', hideable: true, filterable: true, filterType: 'multiselect', options: props.marketTypes },
  { key: 'sponsor', label: 'Sponsor', sortable: true, hideable: true },
  { key: 'schedules', label: 'Schedules', hideable: true },
  { key: 'phone', label: 'Phone', hideable: true },
  // Filtered with the range slider in the panel above instead of a chip: a
  // range suits a 0-4 scale, and it can include unchecked markets, which
  // DataTable's own empty-value handling couldn't.
  { key: 'liveness_score', label: 'Liveness', sortable: true, hideable: true },
])

// A plain useForm (not usePersistedForm) -- a File object can't be
// serialized to localStorage, and a draft file selection wouldn't survive
// a reload anyway. Same shape as cultpantry/costing's KitchenRentals import.
const importForm = useForm<{ file: File | null }>({ file: null })
const fileInput = ref<HTMLInputElement | null>(null)
const fileInputMobile = ref<HTMLInputElement | null>(null)

const handleFileChange = (event: Event) => {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0] ?? null
  if (!file) return

  importForm.file = file
  importForm.post(route('admin.market.import'), {
    forceFormData: true,
    // Remount the whole page on success (fresh list with the new markets);
    // keep it on a validation error so the form and its errors survive.
    preserveState: (page) => Object.keys(page.props.errors ?? {}).length > 0,
    onSuccess: () => { importForm.reset() },
    // Cleared either way -- picking the same file again wouldn't otherwise
    // fire a new 'change' event, since its value never changed from the
    // input's own perspective. Both inputs share one form, so both are
    // cleared regardless of which one triggered the upload.
    onFinish: () => {
      if (fileInput.value) fileInput.value.value = ''
      if (fileInputMobile.value) fileInputMobile.value.value = ''
    },
  })
}

// The injected helper, not the global route(): this computed runs during
// render, and the global only exists in the browser -- server-side
// rendering has just what ZiggyVue provides.
const ziggyRoute = inject<typeof route>('route')!
// The PDF is built from the same search and filters the list is under (every
// page of them, not just what has scrolled into view). Status is always sent
// so the link means "this view", never the bare "export everything" link.
const pdfExportHref = computed(() => {
  const params = Object.fromEntries(
    Object.entries(localFilters.value).filter(
      ([k, v]) => k !== 'sort' && k !== 'direction' && v !== '' && v !== null && v !== undefined && !(Array.isArray(v) && v.length === 0),
    ),
  )
  return ziggyRoute('admin.market.export-pdf', params)
})
</script>
