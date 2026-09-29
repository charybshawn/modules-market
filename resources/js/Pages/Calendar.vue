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
        <div class="grid grid-cols-2 gap-3 md:flex md:items-end">
          <div>
            <label for="calendar-region" class="block text-xs font-medium text-gray-500 dark:text-gray-400">Region</label>
            <select id="calendar-region" v-model="filters.region" :class="selectClass">
              <option value="">All regions</option>
              <option v-for="region in regions" :key="region" :value="region">{{ region }}</option>
            </select>
          </div>
          <div>
            <label for="calendar-city" class="block text-xs font-medium text-gray-500 dark:text-gray-400">City</label>
            <select id="calendar-city" v-model="filters.city" :class="selectClass">
              <option value="">All cities</option>
              <option v-for="city in cities" :key="city" :value="city">{{ city }}</option>
            </select>
          </div>
        </div>
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
            <button type="button" :class="navButtonClass" aria-label="Previous month" @click="shiftMonth(-1)">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
            </button>
            <button type="button" :class="navButtonClass" aria-label="Next month" @click="shiftMonth(1)">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
            </button>
            <h2 class="ml-2 text-base font-semibold text-gray-900 dark:text-white" aria-live="polite">{{ monthTitle }}</h2>
          </div>
          <button type="button" :class="[navButtonClass, 'px-3 text-sm font-medium']" @click="goToday">Today</button>
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
            @click:event="openEvent"
            @click:date="openDay"
            @click:more="openDay"
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
              <div class="text-xs text-gray-500 dark:text-gray-400">{{ [row.city, row.frequency_detail].filter(Boolean).join(' · ') || 'No details' }}</div>
            </Link>
          </li>
        </ul>
      </section>

      <ResponsiveModal :show="selectedDay !== null" max-width="md" @close="selectedDay = null">
        <div v-if="selectedDay" class="p-6 max-md:px-4 max-md:pt-0">
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ dayHeading }}</h2>
          <p v-if="dayRows.length === 0" class="mt-3 text-sm text-gray-500 dark:text-gray-400">No markets on this day.</p>
          <ul v-else class="mt-3 divide-y divide-gray-200 dark:divide-gray-700">
            <li v-for="row in dayRows" :key="row.id">
              <Link :href="route('admin.market.show', row.market_id)" class="tap-target-touch flex items-start gap-3 py-3">
                <span class="mt-1.5 w-2.5 h-2.5 shrink-0 rounded-full" :style="{ backgroundColor: livenessHex(row.liveness_score) }" :title="livenessLabels[row.liveness_score]"></span>
                <span class="min-w-0">
                  <span class="block text-sm font-medium text-gray-900 dark:text-white">{{ row.market_name }}<span v-if="row.label" class="font-normal text-gray-500 dark:text-gray-400"> · {{ row.label }}</span></span>
                  <span class="block text-sm text-gray-700 dark:text-gray-300">{{ formatHours(row.start_time, row.end_time) || 'Hours not set' }}</span>
                  <span class="block text-xs text-gray-500 dark:text-gray-400">{{ [row.address, row.city].filter(Boolean).join(', ') }}</span>
                </span>
              </Link>
            </li>
          </ul>
        </div>
      </ResponsiveModal>
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
import ResponsiveModal from '@/Components/ResponsiveModal.vue'
import { formatHours, formatTime } from './Partials/scheduleSummary'

defineOptions({ layout: (h, page) => h(AdminLayout, { wide: true, hideBreadcrumbOnMobile: true }, () => page) })

interface Occurrence {
  id: string
  schedule_id: number
  market_id: number
  market_name: string
  city: string | null
  region: string | null
  label: string | null
  date: string
  start_time: string | null
  end_time: string | null
  frequency_detail: string | null
  address: string | null
  liveness_score: number
}

interface UnscheduledRow {
  id: number
  market_id: number
  market_name: string
  city: string | null
  label: string | null
  frequency: string | null
  frequency_detail: string | null
}

const props = defineProps<{
  month: string
  occurrences: Occurrence[]
  unscheduled: UnscheduledRow[]
  filters: { region: string; city: string }
  regions: string[]
  cities: string[]
  livenessLabels: Record<number, string>
}>()

const selectClass = 'mt-1 block w-full md:w-48 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base sm:text-sm'
const navButtonClass = 'tap-target-touch inline-flex items-center justify-center h-9 min-w-[2.25rem] rounded-md text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700'

// "YYYY-MM-DD" parsed as a local date, so a timezone offset can't shift the day.
const localDate = (ymd: string) => {
  const [y, m, d] = ymd.split('-').map(Number)
  return new Date(y, m - 1, d)
}
const toYmd = (date: Date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`

// Same thresholds as the liveness dots on the Show page.
const livenessHex = (score: number) => (score >= 4 ? '#10b981' : score >= 2 ? '#f59e0b' : '#ef4444')
const legend = [
  { label: 'Confirmed active', hex: livenessHex(4) },
  { label: 'Probably active', hex: livenessHex(2) },
  { label: 'Likely defunct', hex: livenessHex(0) },
]

// VCalendar reads `start`/`end` as "YYYY-MM-DD" or "YYYY-MM-DD HH:mm";
// `timed` events show their time and sort before all-day ones.
const events = computed(() =>
  props.occurrences.map((o) => ({
    name: o.market_name,
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
const eventColor = (e: CalendarEntry) => livenessHex(occurrenceOf(e).liveness_score)
const eventName = ({ input }: { input: CalendarEntry }) => {
  const o = occurrenceOf(input)
  return o.start_time ? `${formatTime(o.start_time)} ${o.market_name}` : o.market_name
}

const monthCount = computed(() => props.occurrences.filter((o) => o.date.startsWith(props.month)).length)

// The focused date drives which month VCalendar shows; the toolbar moves it,
// and a month change reloads just the data props.
const focus = ref(`${props.month}-01`)
const filters = ref({ ...props.filters })
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
    { month: focusMonth.value, region: filters.value.region || undefined, city: filters.value.city || undefined },
    {
      preserveState: true,
      preserveScroll: true,
      replace: true,
      only: ['month', 'occurrences', 'unscheduled', 'filters'],
      onStart: () => (loading.value = true),
      onFinish: () => (loading.value = false),
    },
  )
}

watch(focusMonth, (month) => {
  if (month !== props.month) reload()
})
watch(filters, reload, { deep: true })

// VCalendar calls every handler as (nativeEvent, data).
const openEvent = (_e: Event, { event }: { event: CalendarEntry }) => {
  router.visit(route('admin.market.show', occurrenceOf(event).market_id))
}

// Day list: the date number or "+N more" (and the main way to browse on a phone).
const selectedDay = ref<string | null>(null)
const openDay = (_e: Event, day: { date: string }) => {
  selectedDay.value = day.date
}
const dayRows = computed(() => props.occurrences.filter((o) => o.date === selectedDay.value))
const dayHeading = computed(() =>
  selectedDay.value ? localDate(selectedDay.value).toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric' }) : '',
)

// Follow the admin's own class-based dark mode.
const isDark = ref(false)
let observer: MutationObserver | null = null
onMounted(() => {
  const root = document.documentElement
  isDark.value = root.classList.contains('dark')
  observer = new MutationObserver(() => (isDark.value = root.classList.contains('dark')))
  observer.observe(root, { attributes: true, attributeFilter: ['class'] })
})
onBeforeUnmount(() => observer?.disconnect())

const showUnscheduled = ref(false)
</script>

