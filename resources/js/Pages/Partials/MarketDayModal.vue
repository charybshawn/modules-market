<template>
  <ResponsiveModal :show="date !== null" max-width="lg" @close="emit('close')">
    <!-- One layout for both shells: ResponsiveModal centers it in a card on
         desktop and gives it a full-screen takeover (with its own × row)
         on mobile, so only the × and the padding differ here. -->
    <div v-if="date" class="flex flex-col min-h-0 max-md:flex-1">
      <div class="shrink-0 flex items-center gap-2 px-4 md:px-6 max-md:-mt-2 md:pt-6 pb-4 border-b border-gray-200 dark:border-gray-700 md:sticky md:top-0 md:z-10 bg-white dark:bg-gray-800">
        <IconButton label="Previous day" :class="navClass" @click="step(-1)">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
        </IconButton>
        <div class="min-w-0 flex-1 text-center">
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white" aria-live="polite">{{ heading }}</h2>
          <p class="text-sm text-gray-500 dark:text-gray-400">
            <template v-if="loading">Loading…</template>
            <template v-else>{{ rows.length }} market{{ rows.length === 1 ? '' : 's' }}</template>
          </p>
        </div>
        <IconButton label="Next day" :class="navClass" @click="step(1)">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
        </IconButton>
        <!-- Desktop close; mobile uses the takeover's own × row above. -->
        <IconButton label="Close" :class="[navClass, 'max-md:hidden']" @click="emit('close')">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
        </IconButton>
      </div>

      <div class="flex-1 min-h-0 overflow-y-auto p-4 md:p-6" :class="{ 'opacity-60 transition-opacity': loading }">
        <p v-if="!loading && rows.length === 0" class="py-8 text-center text-sm text-gray-500 dark:text-gray-400">No markets on this day.</p>
        <ul v-else class="space-y-3">
          <li v-for="row in rows" :key="row.id">
            <Link
              :href="route('admin.market.show', row.market_id)"
              class="block rounded-lg border border-gray-200 dark:border-gray-700 p-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
            >
              <div class="flex items-start justify-between gap-3">
                <span class="text-sm font-semibold" :class="row.start_time ? 'text-gray-900 dark:text-white' : 'text-gray-500 dark:text-gray-400'">
                  {{ formatHours(row.start_time, row.end_time) || 'Hours not set' }}
                </span>
                <span class="shrink-0 inline-flex items-center gap-1.5 text-xs text-gray-600 dark:text-gray-300">
                  <span class="w-2 h-2 rounded-full" :class="livenessDotClass(row.liveness_score)" aria-hidden="true"></span>
                  {{ livenessLabels[row.liveness_score] }}
                </span>
              </div>
              <div class="mt-1 text-base font-medium text-gray-900 dark:text-white">{{ row.market_name }}</div>
              <div v-if="row.label || row.address || row.city" class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                {{ [row.label, [row.address, row.city].filter(Boolean).join(', ')].filter(Boolean).join(' · ') }}
              </div>
            </Link>
          </li>
        </ul>
      </div>
    </div>
  </ResponsiveModal>
</template>

<script setup lang="ts">
/**
 * One day's market schedule, opened by clicking a day on the calendar:
 * every market that day as a card (sorted by opening time, "hours not set"
 * last -- the server already orders them that way), each linking to its
 * market. Previous/next step through days; the page moves the calendar
 * (and loads that month) when a step crosses a month boundary.
 */
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import ResponsiveModal from '@/Components/ResponsiveModal.vue'
import IconButton from '@/Components/IconButton.vue'
import { formatHours } from './scheduleSummary'

export interface MarketDayOccurrence {
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

const props = defineProps<{
  /** "YYYY-MM-DD", or null when closed. */
  date: string | null
  occurrences: MarketDayOccurrence[]
  livenessLabels: Record<number, string>
  loading?: boolean
}>()

const emit = defineEmits<{ close: []; navigate: [date: string] }>()

const navClass = 'rounded-md text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'

// "YYYY-MM-DD" parsed as a local date, so a timezone offset can't shift the day.
const localDate = (ymd: string) => {
  const [y, m, d] = ymd.split('-').map(Number)
  return new Date(y, m - 1, d)
}
const toYmd = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`

const rows = computed(() => props.occurrences.filter((o) => o.date === props.date))
const heading = computed(() =>
  props.date ? localDate(props.date).toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' }) : '',
)

const step = (by: number) => {
  if (!props.date) return
  const d = localDate(props.date)
  d.setDate(d.getDate() + by)
  emit('navigate', toYmd(d))
}

// Same thresholds and colours as the liveness dots on the Show page.
const livenessDotClass = (score: number) => {
  if (score >= 4) return 'bg-emerald-500 dark:bg-emerald-400'
  if (score >= 2) return 'bg-amber-500 dark:bg-amber-400'
  return 'bg-red-500 dark:bg-red-400'
}
</script>
