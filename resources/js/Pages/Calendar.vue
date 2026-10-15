<template>
  <div class="pb-36 md:pt-6 md:pb-6">
    <AdminMobileHeader title="Market Calendar" />

    <!-- The layout gives page content no side padding below sm; the sticky
         mobile header above stays full-bleed, everything else gets a gutter. -->
    <div class="px-4 sm:px-0">
      <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between mb-6">
        <div class="hidden md:block">
          <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Market Calendar</h1>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Market days for every active market, from each schedule's days and hours.</p>
        </div>
        <div class="flex items-end gap-2">
          <div class="min-w-0 flex-1 md:w-64 md:flex-none">
            <label for="calendar-search" class="block text-xs font-medium text-gray-500 dark:text-gray-400">Search</label>
            <input id="calendar-search" v-model="search" type="search" placeholder="Search markets..." :class="selectClass" />
          </div>
          <button
            type="button"
            :aria-expanded="showFilters"
            aria-controls="calendar-filters"
            :class="[navButtonClass, 'tap-target-touch h-9 shrink-0 gap-1.5 border border-gray-300 dark:border-gray-600 px-3 text-sm font-medium']"
            @click="showFilters = !showFilters"
          >
            Filters
            <span v-if="activeFilterCount" class="rounded-full bg-indigo-600 px-1.5 text-xs text-white">{{ activeFilterCount }}</span>
          </button>
        </div>
      </div>

      <p v-if="props.routingProblem" class="mb-3 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:bg-amber-500/10 dark:text-amber-200" role="status">{{ props.routingProblem }}</p>
      <p v-else-if="nearTown && props.unlocated" class="mb-3 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:bg-amber-500/10 dark:text-amber-200" role="status">
        {{ props.unlocated }} market{{ props.unlocated === 1 ? ' has' : 's have' }} no drive time, so {{ props.unlocated === 1 ? "it isn't" : "they aren't" }} included in this search.
      </p>

      <div v-show="showFilters" id="calendar-filters" class="mb-4 space-y-5 rounded-lg bg-white dark:bg-gray-800 p-4 shadow">
        <FilterSection v-for="group in chipGroups" :key="group.key" :title="group.title" :summary="group.selected.value.join(', ')">
          <div class="flex flex-wrap gap-2">
            <button
              v-for="option in group.options"
              :key="option"
              type="button"
              :aria-pressed="group.selected.value.includes(option)"
              :class="[
                'tap-target-touch rounded-full border px-3 py-1 text-sm',
                group.selected.value.includes(option)
                  ? 'border-indigo-600 bg-indigo-600 text-white'
                  : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300',
              ]"
              @click="group.toggle(option)"
            >{{ option }}</button>
          </div>
        </FilterSection>
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
        <button v-if="activeFilterCount" type="button" class="tap-target-touch text-sm font-medium text-indigo-600 dark:text-indigo-400" @click="clearAll">Clear all filters</button>
      </div>

      <div class="mb-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-600 dark:text-gray-400">
        <span v-for="swatch in legend" :key="swatch.label" class="inline-flex items-center gap-1.5">
          <span class="w-2.5 h-2.5 rounded-full" :style="{ backgroundColor: swatch.hex }"></span>{{ swatch.label }}
        </span>
        <span class="ml-auto" aria-live="polite">{{ monthCount }} market day{{ monthCount === 1 ? '' : 's' }} this month</span>
      </div>

      <div class="rounded-lg bg-white dark:bg-gray-800 shadow overflow-hidden">
        <div class="flex items-center justify-between gap-2 px-3 py-2 border-b border-gray-200 dark:border-gray-700">
          <div class="flex items-center gap-1">
            <IconButton label="Previous month" :class="navButtonClass" @click="shiftMonth(-1)">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
            </IconButton>
            <IconButton label="Next month" :class="navButtonClass" @click="shiftMonth(1)">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
            </IconButton>
            <h2 class="ml-2 text-base font-semibold text-gray-900 dark:text-white" aria-live="polite">{{ monthTitle }}</h2>
          </div>
          <button type="button" :class="[navButtonClass, 'tap-target-touch h-9 px-3 text-sm font-medium']" @click="goToday">Today</button>
        </div>

        <!-- Vuetify's own theme follows the admin's class-based dark mode. -->
        <v-theme-provider :theme="isDark ? 'dark' : 'light'" with-background>
          <v-calendar
            v-model="focus"
            type="month"
            :events="events"
            :event-color="eventColor"
            :event-name="eventName"
            :event-height="22"
            event-more
            event-more-text="+{0} more"
            :class="['market-calendar', { 'opacity-60 transition-opacity': loading }]"
            @click:day="openDay"
          />
        </v-theme-provider>
      </div>

      <section v-if="unscheduled.length" class="mt-6 rounded-lg bg-white dark:bg-gray-800 shadow">
        <button
          type="button"
          class="tap-target-touch flex w-full items-center justify-between px-4 py-3 text-left"
          :aria-expanded="showUnscheduled"
          @click="showUnscheduled = !showUnscheduled"
        >
          <span>
            <span class="text-sm font-semibold text-gray-900 dark:text-white">Not on the calendar yet ({{ unscheduled.length }})</span>
            <span class="block text-xs text-gray-500 dark:text-gray-400">These schedules have no market days set. Open one and pick its days to place it.</span>
          </span>
          <svg class="w-5 h-5 shrink-0 text-gray-400 transition-transform" :class="{ 'rotate-180': showUnscheduled }" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
          </svg>
        </button>
        <ul v-if="showUnscheduled" class="divide-y divide-gray-200 dark:divide-gray-700 border-t border-gray-200 dark:border-gray-700">
          <li v-for="row in unscheduled" :key="row.id">
            <Link :href="route('admin.market.show', row.market_id)" class="tap-target-touch block px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700/50">
              <div class="text-sm font-medium text-gray-900 dark:text-white">
                {{ row.market_name }}<span v-if="row.label" class="font-normal text-gray-500 dark:text-gray-400"> · {{ row.label }}</span>
              </div>
              <div class="text-xs text-gray-500 dark:text-gray-400">{{ [row.city, row.notes].filter(Boolean).join(' · ') || 'No details' }}</div>
            </Link>
          </li>
        </ul>
      </section>

      <MarketDayModal
        :date="selectedDay"
        :occurrences="occurrences"
        :liveness-labels="livenessLabels"
        :loading="loading"
        @close="selectedDay = null"
        @navigate="goToDay"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
/**
 * Month grid of market days. The server expands each schedule's structured
 * weekdays / week_of_month / times into dated occurrences for the visible
 * month (ScheduleOccurrences.php); this page only maps them onto Vuetify's
 * VCalendar and refetches when the month or a filter changes.
 *
 * Needs the host to install Vuetify 4 as a plugin (no global components):
 * see the host's resources/js/plugins/vuetify.js.
 */
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { VCalendar } from 'vuetify/components/VCalendar'
import { VThemeProvider } from 'vuetify/components/VThemeProvider'
import 'vuetify/styles/core'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AdminMobileHeader from '@/Components/Admin/AdminMobileHeader.vue'
import IconButton from '@/Components/IconButton.vue'
import FilterSection from '@/Components/Admin/FilterSection.vue'
import NearFilter from './Partials/NearFilter.vue'
import ScheduleLiveFilters from './Partials/ScheduleLiveFilters.vue'
import MarketDayModal, { type MarketDayOccurrence } from './Partials/MarketDayModal.vue'
import { marketTypeHex, UNTYPED_HEX } from './Partials/marketTypeColors'

defineOptions({ layout: (h, page) => h(AdminLayout, { wide: true, hideBreadcrumbOnMobile: true }, () => page) })

type Occurrence = MarketDayOccurrence

interface UnscheduledRow {
  id: number
  market_id: number
  market_name: string
  city: string | null
  label: string | null
  frequency: string | null
  notes: string | null
}

const props = defineProps<{
  month: string
  occurrences: Occurrence[]
  unscheduled: UnscheduledRow[]
  filters: {
    search?: string
    city?: string[]
    region?: string[]
    market_type?: string[]
    freq_include?: string[]
    freq_exclude?: string[]
    months?: (number | string)[]
    match?: 'all' | 'any'
    liveness_min?: number | string
    liveness_max?: number | string
    liveness_unchecked?: boolean | number
    near?: string
    radius?: number | string
  }
  places: string[]
  unlocated: number | null
  routingProblem: string | null
  regions: string[]
  cities: string[]
  marketTypes: string[]
  frequencies: Record<string, string>
  livenessLabels: Record<number, string>
}>()

const selectClass = 'mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base sm:text-sm'
const navButtonClass = 'inline-flex items-center justify-center rounded-md text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700'

// "YYYY-MM-DD" parsed as a local date, so a timezone offset can't shift the day.
const localDate = (ymd: string) => {
  const [y, m, d] = ymd.split('-').map(Number)
  return new Date(y, m - 1, d)
}
const toYmd = (date: Date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`

// Chips are coloured by market type; liveness shows in the day's modal.
const legend = computed(() => [
  ...props.marketTypes.map((t) => ({ label: t, hex: marketTypeHex(t) })),
  { label: 'No type set', hex: UNTYPED_HEX },
])

// VCalendar reads `start`/`end` as "YYYY-MM-DD" or "YYYY-MM-DD HH:mm";
// `timed` events show their time and sort before all-day ones.
const events = computed(() =>
  props.occurrences.map((o) => ({
    name: o.title,
    start: o.start_time ? `${o.date} ${o.start_time}` : o.date,
    end: o.end_time ? `${o.date} ${o.end_time}` : o.start_time ? `${o.date} ${o.start_time}` : o.date,
    timed: !!o.start_time,
    occurrence: o,
  })),
)
// VCalendar types its events as a loose record (and doesn't export the
// callback types), so the callbacks take that shape and read `occurrence`.
type CalendarEntry = Record<string, any>
const occurrenceOf = (e: CalendarEntry) => e.occurrence as Occurrence
const eventColor = (e: CalendarEntry) => marketTypeHex(occurrenceOf(e).market_type)
// Timed events already render their start time (in bold) ahead of the name.
const eventName = ({ input }: { input: CalendarEntry }) => occurrenceOf(input).title

const monthCount = computed(() => props.occurrences.filter((o) => o.date.startsWith(props.month)).length)

// The focused date drives which month VCalendar shows; the toolbar moves it,
// and a month change reloads just the data props.
// Same filters as the market list (FetchMarkets does the work on the server).
// The address bar is the source of truth for what is selected: every change is
// written to it (router.get with replace), so coming back from a market page
// lands on the same month and filters, whatever props the history restored.
// Server props only seed the controls where the URL says nothing.
const urlState = () => {
  const q = typeof window === 'undefined' ? new URLSearchParams() : new URLSearchParams(window.location.search)
  const list = (name: string) => [...q.entries()].filter(([k]) => k === name || k.startsWith(`${name}[`)).map(([, v]) => v)
  const one = (name: string) => q.get(name) ?? undefined
  return {
    month: one('month'),
    search: one('search'),
    city: list('city'),
    region: list('region'),
    market_type: list('market_type'),
    freq_include: list('freq_include'),
    freq_exclude: list('freq_exclude'),
    months: list('months').map(Number),
    match: one('match'),
    liveness_min: one('liveness_min'),
    liveness_max: one('liveness_max'),
    liveness_unchecked: one('liveness_unchecked'),
    near: one('near'),
    radius: one('radius'),
  }
}
const fromUrl = urlState()
const hasUrlState = typeof window !== 'undefined' && window.location.search !== ''
const echoed = hasUrlState ? fromUrl : Array.isArray(props.filters) ? {} : props.filters ?? {}
const focus = ref(`${(hasUrlState && fromUrl.month) || props.month}-01`)
const search = ref(echoed.search ?? '')
const cityPicked = ref<string[]>([...(echoed.city ?? [])])
const regionPicked = ref<string[]>([...(echoed.region ?? [])])
const typePicked = ref<string[]>([...(echoed.market_type ?? [])])
const freqStates = ref<Record<string, 'include' | 'exclude'>>({
  ...Object.fromEntries((echoed.freq_include ?? []).map((k) => [k, 'include'])),
  ...Object.fromEntries((echoed.freq_exclude ?? []).map((k) => [k, 'exclude'])),
})
const months = ref<number[]>((echoed.months ?? []).map(Number))
const matchMode = ref<'all' | 'any'>(echoed.match === 'any' ? 'any' : 'all')
const livenessMin = ref(Number(echoed.liveness_min ?? 0))
const livenessMax = ref(Number(echoed.liveness_max ?? 4))
const includeUnchecked = ref(!!Number(echoed.liveness_unchecked ?? 0))
const nearTown = ref(echoed.near ?? '')
const nearRadius = ref(Number(echoed.radius ?? 60))

// Does what the URL asked for match the filters the server applied?
const sameAsProps = () => {
  const applied = (Array.isArray(props.filters) ? {} : props.filters ?? {}) as Record<string, unknown>
  const asked = fromUrl as Record<string, unknown>
  const norm = (v: unknown) => (Array.isArray(v) ? v.map(String).sort().join(',') : v == null ? '' : String(v))
  const keys = ['search', 'city', 'region', 'market_type', 'freq_include', 'freq_exclude', 'months', 'match', 'liveness_min', 'liveness_max', 'near']
  return keys.every((k) => norm(applied[k]) === norm(asked[k]))
}
const toggleIn = (list: { value: string[] }, option: string) => {
  list.value = list.value.includes(option) ? list.value.filter((o) => o !== option) : [...list.value, option]
}
const chipGroups = [
  { key: 'market_type', title: 'Type', options: props.marketTypes, selected: typePicked, toggle: (o: string) => toggleIn(typePicked, o) },
  { key: 'region', title: 'Region', options: props.regions, selected: regionPicked, toggle: (o: string) => toggleIn(regionPicked, o) },
  { key: 'city', title: 'City', options: props.cities, selected: cityPicked, toggle: (o: string) => toggleIn(cityPicked, o) },
]

const includeFreqs = computed(() => Object.entries(freqStates.value).filter(([, v]) => v === 'include').map(([k]) => k))
const excludeFreqs = computed(() => Object.entries(freqStates.value).filter(([, v]) => v === 'exclude').map(([k]) => k))
const livenessFilterActive = computed(() => livenessMin.value > 0 || livenessMax.value < 4)
const customFilterCount = computed(
  () => (includeFreqs.value.length || excludeFreqs.value.length ? 1 : 0) + (months.value.length ? 1 : 0) + (livenessFilterActive.value ? 1 : 0) + (nearTown.value ? 1 : 0),
)
const activeFilterCount = computed(
  () => customFilterCount.value + [typePicked, regionPicked, cityPicked].filter((l) => l.value.length).length + (search.value.trim() ? 1 : 0),
)
const showFilters = ref(activeFilterCount.value > 0)

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
const clearAll = () => {
  clearCustomFilters()
  search.value = ''
  typePicked.value = []
  regionPicked.value = []
  cityPicked.value = []
}

const queryParams = () => ({
  month: focusMonth.value,
  search: search.value.trim() || undefined,
  market_type: typePicked.value.length ? typePicked.value : undefined,
  region: regionPicked.value.length ? regionPicked.value : undefined,
  city: cityPicked.value.length ? cityPicked.value : undefined,
  freq_include: includeFreqs.value.length ? includeFreqs.value : undefined,
  freq_exclude: excludeFreqs.value.length ? excludeFreqs.value : undefined,
  months: months.value.length ? months.value : undefined,
  match: matchMode.value === 'any' ? 'any' : undefined,
  liveness_min: livenessMin.value > 0 ? livenessMin.value : undefined,
  liveness_max: livenessMax.value < 4 ? livenessMax.value : undefined,
  liveness_unchecked: includeUnchecked.value && livenessFilterActive.value ? 1 : undefined,
  near: nearTown.value || undefined,
  radius: nearTown.value ? nearRadius.value : undefined,
})
const loading = ref(false)

const focusMonth = computed(() => focus.value.slice(0, 7))
const monthTitle = computed(() => localDate(`${focusMonth.value}-01`).toLocaleDateString(undefined, { month: 'long', year: 'numeric' }))

const shiftMonth = (by: number) => {
  const d = localDate(`${focusMonth.value}-01`)
  d.setMonth(d.getMonth() + by)
  focus.value = toYmd(d)
}
const goToday = () => {
  focus.value = toYmd(new Date())
}

const reload = () => {
  router.get(
    route('admin.market.calendar'),
    queryParams(),
    {
      preserveState: true,
      preserveScroll: true,
      replace: true,
      only: ['month', 'occurrences', 'unscheduled', 'filters', 'unlocated', 'routingProblem'],
      onStart: () => (loading.value = true),
      onFinish: () => (loading.value = false),
    },
  )
}

watch(focusMonth, (month) => {
  if (month !== props.month) reload()
})
// Debounced: typing in search and dragging the liveness slider fire per step.
let filterTimer: ReturnType<typeof setTimeout> | undefined
watch(
  [search, typePicked, regionPicked, cityPicked, freqStates, months, matchMode, livenessMin, livenessMax, includeUnchecked, nearTown, nearRadius],
  () => {
    clearTimeout(filterTimer)
    filterTimer = setTimeout(reload, 300)
  },
  { deep: true },
)

// Any click inside a day -- its date, a market chip, "+N more", or the empty
// cell -- bubbles up to VCalendar's click:day and opens that day's schedule;
// each market in it links to the market itself. (VCalendar calls every
// handler as (nativeEvent, data).)
const selectedDay = ref<string | null>(null)
const openDay = (_e: Event, day: { date: string }) => {
  selectedDay.value = day.date
}
// Stepping days in the modal: moving the calendar's focus to that day loads
// its month when the step crosses into another one.
const goToDay = (date: string) => {
  selectedDay.value = date
  focus.value = date
}

// Follow the admin's own class-based dark mode.
const isDark = ref(false)
let observer: MutationObserver | null = null
onMounted(() => {
  // Props restored from history (or a stale cache) may not be the URL's month or filters.
  if (hasUrlState && (focusMonth.value !== props.month || !sameAsProps())) reload()

  const root = document.documentElement
  isDark.value = root.classList.contains('dark')
  observer = new MutationObserver(() => (isDark.value = root.classList.contains('dark')))
  observer.observe(root, { attributes: true, attributeFilter: ['class'] })
})
onBeforeUnmount(() => observer?.disconnect())

const showUnscheduled = ref(false)
</script>

<style scoped>
/* Whole day cells open that day's schedule, so they should read as clickable. */
.market-calendar :deep(.v-calendar-weekly__day) {
  cursor: pointer;
}
.market-calendar :deep(.v-calendar-weekly__day:hover) {
  background-color: rgb(99 102 241 / 0.06);
}
</style>
