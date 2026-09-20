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
          <div class="mt-1 text-4xl font-extrabold text-emerald-600">{{ props.markets.filter((m) => m.is_active).length }}</div>
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
        </div>
      </div>

      <div v-if="$page.props.flash?.success" class="mb-6 rounded-md bg-green-50 dark:bg-green-900/20 p-4">
        <p class="text-sm font-medium text-green-800 dark:text-green-200">{{ $page.props.flash.success }}</p>
      </div>

      <div v-if="$page.props.flash?.error" class="mb-6 rounded-md bg-red-50 dark:bg-red-900/20 p-4">
        <p class="text-sm font-medium text-red-800 dark:text-red-200">{{ $page.props.flash.error }}</p>
      </div>

      <FormErrorSummary v-if="Object.keys(importForm.errors).length" :errors="importForm.errors" class="mb-6" />

      <!-- No overflow-hidden here: it establishes a containing block for
           DataTable's sticky toolbar, pinning it at a fixed offset inside
           this box instead of sticking to the viewport. -->
      <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg">
        <!-- Filters DataTable's own chips can't express: schedule frequency
             with exclusion ("not weekly"), months a schedule falls in, and a
             liveness range. They narrow `rows` before DataTable sees them. -->
        <div class="border-b border-gray-200 dark:border-gray-700">
          <button
            type="button"
            class="tap-target-touch flex w-full items-center justify-between px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300"
            :aria-expanded="showMoreFilters"
            @click="showMoreFilters = !showMoreFilters"
          >
            <span class="inline-flex items-center gap-2">
              Schedule &amp; liveness filters
              <span
                v-if="customFilterCount"
                class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-indigo-600 px-1.5 text-xs font-semibold text-white"
              >{{ customFilterCount }}</span>
            </span>
            <svg class="w-4 h-4 transition-transform" :class="showMoreFilters ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </button>

          <div v-if="showMoreFilters" class="space-y-5 px-4 pb-5">
            <div>
              <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Frequency</h3>
              <div class="mt-2 flex flex-wrap gap-2">
                <button
                  v-for="(label, key) in props.frequencies"
                  :key="key"
                  type="button"
                  :aria-pressed="!!freqStates[key]"
                  :class="[chipBase, chipClass(freqStates[key])]"
                  @click="cycleFrequency(String(key))"
                >{{ freqStates[key] === 'exclude' ? 'Not ' : '' }}{{ label }}</button>
              </div>
              <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">Click once to include, again to exclude, a third time to clear.</p>
            </div>

            <div>
              <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Occurring in</h3>
              <div class="mt-2 flex flex-wrap gap-2">
                <button
                  v-for="(name, i) in MONTHS"
                  :key="name"
                  type="button"
                  :aria-pressed="months.includes(i + 1)"
                  :class="[chipBase, chipClass(months.includes(i + 1) ? 'include' : undefined)]"
                  @click="toggleMonth(i + 1)"
                >{{ name }}</button>
              </div>
              <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">Any year, so last year's edition counts for next year. Schedules without dates never match a month.</p>
            </div>

            <div v-if="includeFreqs.length && months.length" class="flex flex-wrap items-center gap-3 text-sm text-gray-700 dark:text-gray-300">
              <span>Show schedules that match</span>
              <span class="inline-flex overflow-hidden rounded-md border border-gray-300 dark:border-gray-600">
                <button
                  v-for="mode in ['all', 'any'] as const"
                  :key="mode"
                  type="button"
                  :aria-pressed="matchMode === mode"
                  class="px-3 py-1 text-sm"
                  :class="matchMode === mode ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300'"
                  @click="matchMode = mode"
                >{{ mode === 'all' ? 'both filters' : 'either filter' }}</button>
              </span>
            </div>

            <div>
              <div class="flex items-baseline justify-between">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Liveness</h3>
                <span class="text-sm text-gray-700 dark:text-gray-300">{{ livenessMin }}/4 &ndash; {{ livenessMax }}/4</span>
              </div>
              <div class="relative mt-2 h-6">
                <div class="absolute inset-x-[9px] top-1/2 h-1 -translate-y-1/2 rounded-full bg-gray-200 dark:bg-gray-600">
                  <div
                    class="absolute h-1 rounded-full bg-indigo-500"
                    :style="{ left: `${livenessMin * 25}%`, width: `${(livenessMax - livenessMin) * 25}%` }"
                  ></div>
                </div>
                <input
                  type="range" min="0" max="4" step="1"
                  :value="livenessMin"
                  aria-label="Minimum liveness score"
                  class="liveness-range absolute inset-0 w-full"
                  :style="{ zIndex: livenessMin === livenessMax && livenessMax >= 3 ? 5 : 3 }"
                  @input="setLivenessMin($event)"
                />
                <input
                  type="range" min="0" max="4" step="1"
                  :value="livenessMax"
                  aria-label="Maximum liveness score"
                  class="liveness-range absolute inset-0 w-full"
                  style="z-index: 4"
                  @input="setLivenessMax($event)"
                />
              </div>
              <div class="mt-0.5 flex justify-between px-1 text-xs text-gray-400 dark:text-gray-500">
                <span v-for="n in 5" :key="n">{{ n - 1 }}</span>
              </div>
              <label v-if="livenessFilterActive" class="tap-target-touch mt-2 flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <input v-model="includeUnchecked" type="checkbox" class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500 dark:bg-gray-700" />
                Also show markets that haven't been checked
              </label>
              <p v-if="livenessFilterActive && livenessMin <= 1" class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">
                Markets scoring 1 or below are marked inactive &mdash; also tick Inactive under Filters to see them.
              </p>
            </div>

            <button
              v-if="customFilterCount"
              type="button"
              class="tap-target-touch text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300"
              @click="clearCustomFilters"
            >Clear these filters</button>
          </div>
        </div>

        <DataTable
          :columns="columns"
          :items="rows"
          :initial-filters="{ status: ['active'] }"
          table-id="market-markets"
          item-key="id"
          searchable
          search-placeholder="Search markets..."
          :empty-message="customFilterCount ? 'No markets match these schedule or liveness filters.' : 'No markets yet.'"
          :empty-action-label="customFilterCount ? '' : 'Add your first market'"
          :empty-action-href="route('admin.market.create')"
          mobile-row-style="line"
          :row-href="(item) => route('admin.market.show', item.id)"
        >
          <template #mobile-card="{ item }">
            <div class="flex items-center gap-3 min-w-0">
              <span class="min-w-0 flex-1 truncate text-sm font-medium text-gray-900 dark:text-white">{{ item.name }}</span>
              <span v-if="!item.is_active" class="shrink-0 text-xs font-medium text-gray-400 dark:text-gray-500">Inactive</span>
              <span class="shrink-0 truncate max-w-[40%] text-sm text-gray-500 dark:text-gray-400">{{ item.city ?? '—' }}</span>
            </div>
          </template>

          <template #cell-name="{ item }">
            <div class="text-sm font-medium text-gray-900 dark:text-white">
              {{ item.name }}
              <span v-if="!item.is_active" class="ml-1.5 text-xs font-normal text-gray-400 dark:text-gray-500">(Inactive)</span>
            </div>
          </template>

          <template #cell-city="{ item }">
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ item.city ?? '—' }}</span>
          </template>

          <template #cell-region="{ item }">
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ item.region ?? '—' }}</span>
          </template>

          <template #cell-market_type="{ item }">
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ item.market_type ?? '—' }}</span>
          </template>

          <template #cell-schedules="{ item }">
            <div v-if="item.schedules.length" class="text-sm text-gray-500 dark:text-gray-400">
              <div
                v-for="schedule in visibleSchedules(item)"
                :key="schedule.id"
                class="truncate"
                :class="item.matched_schedule_ids.includes(schedule.id) ? 'font-medium text-gray-900 dark:text-white' : ''"
              >{{ scheduleSummary(schedule) }}</div>
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
import { computed, ref } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AdminMobileHeader from '@/Components/Admin/AdminMobileHeader.vue'
import DataTable, { type Column } from '@/Components/Admin/DataTable.vue'
import FormErrorSummary from '@/Components/Admin/FormErrorSummary.vue'

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
  schedules: ScheduleRow[]
  phone: string | null
  liveness_score: number | null
  liveness_checked_at: string | null
  is_active: boolean
}

interface Props {
  markets: MarketRow[]
  cities: string[]
  regions: string[]
  marketTypes: string[]
  frequencies: Record<string, string>
  livenessLabels: Record<number, string>
}

const props = defineProps<Props>()

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
// Dates join the summary once a frequency or month filter is on, since
// "which October?" is the whole point then; the plain list stays compact.
const scheduleSummary = (schedule: ScheduleRow) =>
  [
    schedule.label,
    schedule.frequency ? frequencyLabel(schedule.frequency) : null,
    includeFreqs.value.length || excludeFreqs.value.length || months.value.length ? scheduleDates(schedule) : null,
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

// Inactive markets (including anything scored 1 or below, which the model
// deactivates on save) are hidden by default: the Status filter starts on
// "Active" via :initial-filters, and stays reachable as a filter chip rather
// than a separate page -- tick Inactive as well, or clear it to see everything.
// ---- Custom filters (schedule frequency, month, liveness range) ----
const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']

const showMoreFilters = ref(false)
const freqStates = ref<Record<string, 'include' | 'exclude'>>({})
const months = ref<number[]>([])
const matchMode = ref<'all' | 'any'>('all')
const livenessMin = ref(0)
const livenessMax = ref(4)
const includeUnchecked = ref(false)

const includeFreqs = computed(() => Object.entries(freqStates.value).filter(([, v]) => v === 'include').map(([k]) => k))
const excludeFreqs = computed(() => Object.entries(freqStates.value).filter(([, v]) => v === 'exclude').map(([k]) => k))
const livenessFilterActive = computed(() => livenessMin.value > 0 || livenessMax.value < 4)
// One badge count per active group, matching how DataTable counts its chips.
const customFilterCount = computed(() =>
  (includeFreqs.value.length || excludeFreqs.value.length ? 1 : 0) + (months.value.length ? 1 : 0) + (livenessFilterActive.value ? 1 : 0),
)

const cycleFrequency = (key: string) => {
  const next = { ...freqStates.value }
  if (!next[key]) next[key] = 'include'
  else if (next[key] === 'include') next[key] = 'exclude'
  else delete next[key]
  freqStates.value = next
}
const toggleMonth = (month: number) => {
  months.value = months.value.includes(month) ? months.value.filter((m) => m !== month) : [...months.value, month]
}
// Thumbs can't cross; the DOM value is reset too, because when the clamp lands
// on the model's old value Vue sees no change and would leave the thumb
// wherever it was dragged.
const setLivenessMin = (event: Event) => {
  const input = event.target as HTMLInputElement
  livenessMin.value = Math.min(input.valueAsNumber, livenessMax.value)
  input.value = String(livenessMin.value)
}
const setLivenessMax = (event: Event) => {
  const input = event.target as HTMLInputElement
  livenessMax.value = Math.max(input.valueAsNumber, livenessMin.value)
  input.value = String(livenessMax.value)
}
const clearCustomFilters = () => {
  freqStates.value = {}
  months.value = []
  matchMode.value = 'all'
  livenessMin.value = 0
  livenessMax.value = 4
  includeUnchecked.value = false
}

const chipBase = 'tap-target-touch inline-flex items-center rounded-full border px-3 py-1 text-sm'
const chipClass = (state: 'include' | 'exclude' | undefined) => {
  if (state === 'include') return 'border-indigo-600 bg-indigo-600 text-white'
  if (state === 'exclude') return 'border-red-500 bg-red-50 text-red-700 line-through dark:bg-red-900/20 dark:text-red-300'
  return 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300'
}

// Every calendar month a schedule touches, ignoring the year: a 2023 edition
// in November still says "this runs in November". A schedule with no dates
// covers nothing (unknown, not "always"); one date covers just its month.
const monthsCovered = (schedule: ScheduleRow): Set<number> => {
  const covered = new Set<number>()
  const start = schedule.start_date?.slice(0, 10)
  const end = schedule.end_date?.slice(0, 10)
  if (!start && !end) return covered
  if (!start || !end) {
    covered.add(Number((start ?? end)!.slice(5, 7)))
    return covered
  }
  let year = Number(start.slice(0, 4))
  let month = Number(start.slice(5, 7))
  const last = Number(end.slice(0, 4)) * 12 + Number(end.slice(5, 7))
  for (let i = 0; i < 12 && year * 12 + month <= last; i++) {
    covered.add(month)
    if (++month > 12) { month = 1; year++ }
  }
  return covered
}

// An excluded frequency can never match; otherwise a schedule must satisfy
// whichever include groups are active, all of them or any of them.
const scheduleMatches = (schedule: ScheduleRow): boolean => {
  if (schedule.frequency && excludeFreqs.value.includes(schedule.frequency)) return false
  const checks: boolean[] = []
  if (includeFreqs.value.length) checks.push(!!schedule.frequency && includeFreqs.value.includes(schedule.frequency))
  if (months.value.length) {
    const covered = monthsCovered(schedule)
    checks.push(months.value.some((m) => covered.has(m)))
  }
  if (!checks.length) return true
  return matchMode.value === 'all' ? checks.every(Boolean) : checks.some(Boolean)
}

const rows = computed(() => {
  let list = props.markets.map((m) => ({
    ...m,
    status: m.is_active ? 'active' : 'inactive',
    matched_schedule_ids: [] as number[],
  }))

  if (livenessFilterActive.value) {
    list = list.filter((m) =>
      m.liveness_score === null
        ? includeUnchecked.value
        : m.liveness_score >= livenessMin.value && m.liveness_score <= livenessMax.value,
    )
  }

  if (!includeFreqs.value.length && !excludeFreqs.value.length && !months.value.length) return list

  const needsAMatch = includeFreqs.value.length > 0 || months.value.length > 0
  return list.flatMap((m) => {
    const matched = m.schedules.filter(scheduleMatches)
    if (needsAMatch) return matched.length ? [{ ...m, matched_schedule_ids: matched.map((s) => s.id) }] : []
    // Only exclusions: hide a market once *all* its schedules are excluded
    // ("not weekly" drops a weekly-only market), but keep one that has none.
    return m.schedules.length && !matched.length ? [] : [m]
  })
})

// With a schedule filter on, show every schedule that matched -- they're the
// reason the market is in the list, so none may hide behind "+N more". With
// none on, keep the compact first two.
const visibleSchedules = (item: { schedules: ScheduleRow[]; matched_schedule_ids: number[] }) =>
  item.matched_schedule_ids.length
    ? item.schedules.filter((s) => item.matched_schedule_ids.includes(s.id))
    : item.schedules.slice(0, 2)

const columns = computed<Column[]>(() => [
  {
    key: 'status', label: 'Status', filterable: true, filterType: 'multiselect', filterOnly: true,
    options: [{ value: 'active', label: 'Active' }, { value: 'inactive', label: 'Inactive' }],
  },
  { key: 'name', label: 'Market', sortable: true },
  { key: 'city', label: 'City', sortable: true, filterable: true, filterType: 'multiselect', options: props.cities },
  { key: 'region', label: 'Region', sortable: true, filterable: true, filterType: 'multiselect', options: props.regions },
  { key: 'market_type', label: 'Type', hideable: true, filterable: true, filterType: 'multiselect', options: props.marketTypes },
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
</script>

<style scoped>
/* Two overlaid range inputs make one two-thumb slider: the inputs themselves
   ignore the pointer so the top one doesn't block the other, and only the
   thumbs take clicks. */
.liveness-range {
  pointer-events: none;
  appearance: none;
  -webkit-appearance: none;
  background: transparent;
  height: 1.5rem;
  margin: 0;
}
.liveness-range::-webkit-slider-runnable-track {
  background: transparent;
}
.liveness-range::-moz-range-track {
  background: transparent;
}
.liveness-range::-webkit-slider-thumb {
  pointer-events: auto;
  -webkit-appearance: none;
  appearance: none;
  height: 18px;
  width: 18px;
  border-radius: 9999px;
  background: #4f46e5;
  border: 2px solid #fff;
  box-shadow: 0 0 0 1px #4f46e5;
  cursor: pointer;
}
.liveness-range::-moz-range-thumb {
  pointer-events: auto;
  height: 14px;
  width: 14px;
  border-radius: 9999px;
  background: #4f46e5;
  border: 2px solid #fff;
  box-shadow: 0 0 0 1px #4f46e5;
  cursor: pointer;
}
.liveness-range:focus-visible::-webkit-slider-thumb {
  outline: 2px solid #818cf8;
  outline-offset: 2px;
}
</style>
