<template>
  <div class="pb-36 md:pt-6 md:pb-6">
    <AdminMobileHeader title="Farmer's Markets" />

    <div class="hidden md:flex md:items-center md:justify-between mb-6">
      <div>
        <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Farmer's Markets</h1>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
          Artisan farmer's markets across British Columbia.
        </p>
      </div>
      <div class="flex flex-wrap gap-2">
        <Link :href="route('admin.market.calendar')" :class="SECONDARY_BUTTON">Calendar</Link>
        <Link
          :href="route('admin.market.index')"
          class="tap-target-touch inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700"
        >
          Browse markets
        </Link>
      </div>
    </div>

    <!-- Headline figures. -->
    <dl class="grid grid-cols-2 gap-3 px-4 pt-4 md:px-0 md:pt-0 md:gap-4 md:grid-cols-3 mb-6">
      <div class="col-span-2 md:col-span-1 bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-5">
        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Markets on file</dt>
        <dd class="mt-1 text-3xl font-semibold text-gray-900 dark:text-white">{{ props.counts.total }}</dd>
        <dd class="text-sm text-gray-500 dark:text-gray-400">
          {{ props.counts.active }} active<span v-if="props.counts.ignored"> · {{ props.counts.ignored }} ignored, not counted</span>
        </dd>
      </div>
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-5">
        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Regions with markets</dt>
        <dd class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ coveredRegions }}</dd>
        <dd class="text-sm text-gray-500 dark:text-gray-400">of {{ props.regions.length }}</dd>
      </div>
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-5">
        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Last update</dt>
        <dd class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ latest ? formatDay(latest.date, false) : 'None yet' }}</dd>
        <dd v-if="latest" class="text-sm text-gray-500 dark:text-gray-400">{{ updateHeadline(latest) }}</dd>
      </div>
    </dl>

    <div class="grid gap-6 lg:grid-cols-2 lg:items-start">
      <!-- Markets per region. Each row opens the list filtered to it. -->
      <section class="bg-white dark:bg-gray-800 md:rounded-lg md:shadow-sm md:border md:border-gray-200 md:dark:border-gray-700">
        <div class="px-4 md:px-5 pt-5 pb-2">
          <h2 class="text-base font-semibold text-gray-900 dark:text-white">Markets by region</h2>
          <p class="text-sm text-gray-500 dark:text-gray-400">Active and inactive, busiest first.</p>
        </div>
        <ul class="divide-y divide-gray-100 dark:divide-gray-700">
          <li v-for="row in props.regions" :key="row.region">
            <Link
              :href="route('admin.market.index', { region: [row.region], status: 'active,inactive' })"
              class="tap-target-touch flex items-center gap-4 px-4 md:px-5 py-3 hover:bg-gray-50 dark:hover:bg-gray-700/50"
            >
              <span class="min-w-0 flex-1">
                <span
                  class="block text-sm font-medium"
                  :class="row.total ? 'text-gray-900 dark:text-white' : 'text-gray-400 dark:text-gray-500'"
                >
                  {{ row.region }}
                </span>
                <span class="mt-1.5 block h-1.5 rounded-full bg-gray-100 dark:bg-gray-700" aria-hidden="true">
                  <span class="block h-1.5 rounded-full bg-indigo-500" :style="{ width: barWidth(row.total) }" />
                </span>
              </span>
              <span class="flex-shrink-0 text-right">
                <span class="block text-sm font-semibold tabular-nums text-gray-900 dark:text-white">{{ row.total }}</span>
                <span v-if="row.total" class="block text-xs text-gray-500 dark:text-gray-400">{{ row.active }} active</span>
              </span>
            </Link>
          </li>
          <li v-if="props.unassigned.total" class="flex items-center gap-4 px-4 md:px-5 py-3">
            <span class="min-w-0 flex-1 text-sm italic text-gray-500 dark:text-gray-400">No region set</span>
            <span class="flex-shrink-0 text-right">
              <span class="block text-sm font-semibold tabular-nums text-gray-900 dark:text-white">{{ props.unassigned.total }}</span>
              <span class="block text-xs text-gray-500 dark:text-gray-400">{{ props.unassigned.active }} active</span>
            </span>
          </li>
        </ul>
      </section>

      <!-- What was added, a day at a time, newest first. -->
      <section class="bg-white dark:bg-gray-800 md:rounded-lg md:shadow-sm md:border md:border-gray-200 md:dark:border-gray-700">
        <div class="px-4 md:px-5 pt-5 pb-2">
          <h2 class="text-base font-semibold text-gray-900 dark:text-white">Recent updates</h2>
          <p class="text-sm text-gray-500 dark:text-gray-400">New markets and new market dates, newest first.</p>
        </div>
        <p v-if="!props.updates.length" class="px-4 md:px-5 pb-5 text-sm text-gray-500 dark:text-gray-400">
          Nothing has been added in the last three months.
        </p>
        <ol v-else class="divide-y divide-gray-100 dark:divide-gray-700">
          <li v-for="update in props.updates" :key="update.date" class="px-4 md:px-5 py-4">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
              <time :datetime="update.date">{{ formatDay(update.date, true) }}</time>
              <span class="font-normal text-gray-500 dark:text-gray-400"> · {{ updateHeadline(update) }}</span>
            </h3>

            <ul v-if="update.new.length" class="mt-2 space-y-1.5">
              <li v-for="market in update.new" :key="market.url" class="flex gap-2 text-sm">
                <span class="mt-0.5 flex-shrink-0 inline-flex items-center px-1.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300">New</span>
                <span class="min-w-0 text-gray-600 dark:text-gray-300">
                  <a :href="market.url" class="font-medium text-gray-900 dark:text-white hover:underline">{{ market.name }}</a><span v-if="market.city">, {{ market.city }}</span><span v-if="market.next" class="text-gray-500 dark:text-gray-400"> — {{ market.next }}</span>
                </span>
              </li>
              <li v-if="update.more_new" class="text-sm text-gray-500 dark:text-gray-400">and {{ update.more_new }} more new {{ update.more_new === 1 ? 'market' : 'markets' }}</li>
            </ul>

            <ul v-if="update.dates.length" class="mt-2 space-y-1.5">
              <li v-for="market in update.dates" :key="market.url" class="flex gap-2 text-sm">
                <span class="mt-0.5 flex-shrink-0 inline-flex items-center px-1.5 rounded text-xs font-medium bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-300">Dates</span>
                <span class="min-w-0 text-gray-600 dark:text-gray-300">
                  <a :href="market.url" class="font-medium text-gray-900 dark:text-white hover:underline">{{ market.name }}</a><span v-if="market.city">, {{ market.city }}</span>
                  <span class="text-gray-500 dark:text-gray-400"> — {{ market.items.join('; ') }}</span>
                </span>
              </li>
              <li v-if="update.more_dates" class="text-sm text-gray-500 dark:text-gray-400">and {{ update.more_dates }} more {{ update.more_dates === 1 ? 'market' : 'markets' }} with new dates</li>
            </ul>
          </li>
        </ol>
      </section>
    </div>

    <!-- Mobile: the desktop header's links. -->
    <div class="md:hidden grid grid-cols-2 gap-3 px-4 pt-6">
      <Link :href="route('admin.market.calendar')" :class="[SECONDARY_BUTTON, 'justify-center']">Calendar</Link>
      <Link
        :href="route('admin.market.index')"
        class="tap-target-touch inline-flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700"
      >
        Browse markets
      </Link>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AdminMobileHeader from '@/Components/Admin/AdminMobileHeader.vue'

defineOptions({ layout: (h, page) => h(AdminLayout, { wide: true, hideBreadcrumbOnMobile: true }, () => page) })

interface RegionRow {
  region: string
  total: number
  active: number
}

interface Update {
  date: string
  markets: number
  dated_markets: number
  total: number
  new: { name: string; city: string | null; type: string | null; url: string; next: string | null }[]
  more_new: number
  dates: { name: string; city: string | null; url: string; items: string[] }[]
  more_dates: number
}

const props = defineProps<{
  counts: { total: number; active: number; ignored: number }
  regions: RegionRow[]
  unassigned: { total: number; active: number }
  updates: Update[]
}>()

const SECONDARY_BUTTON = 'tap-target-touch inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600'

const coveredRegions = computed(() => props.regions.filter((r) => r.total > 0).length)

const busiest = computed(() => Math.max(1, ...props.regions.map((r) => r.total)))
const barWidth = (total: number) => `${Math.round((total / busiest.value) * 100)}%`

const latest = computed(() => props.updates[0] ?? null)

const plural = (n: number, word: string) => `${n} ${word}${n === 1 ? '' : 's'}`
const updateHeadline = (u: Update) =>
  [u.markets ? plural(u.markets, 'new market') : null, u.dated_markets ? `new dates for ${plural(u.dated_markets, 'market')}` : null]
    .filter(Boolean)
    .join(', ')

// A plain YYYY-MM-DD, read as a local date (not UTC midnight).
const formatDay = (date: string, withWeekday: boolean) =>
  new Date(`${date}T00:00:00`).toLocaleDateString('en-CA', {
    ...(withWeekday ? { weekday: 'short' } : {}),
    month: 'short',
    day: 'numeric',
  })
</script>
