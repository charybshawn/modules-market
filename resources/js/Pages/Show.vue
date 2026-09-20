<template>
  <div class="md:pt-6 pb-24 md:pb-6">
    <!-- Same outer wrapper as Edit.vue: no base px, the layout's own <main>
         already provides none on mobile by design. -->
    <!-- Wider than Create/Edit's max-w-3xl: those are forms, this is a
         read-only detail page whose History section wants the room. -->
    <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
      <AdminMobileHeader :title="market.name" :href="route('admin.market.index')" />

      <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
        <div v-if="$page.props.flash?.success" class="m-6 mb-0 rounded-md bg-green-50 dark:bg-green-900/20 p-4">
          <p class="text-sm font-medium text-green-800 dark:text-green-200">{{ $page.props.flash.success }}</p>
        </div>

        <div class="p-6 border-b border-gray-200 dark:border-gray-700 flex items-start justify-between gap-4">
          <div class="min-w-0">
            <h1 class="hidden md:block text-2xl font-semibold text-gray-900 dark:text-white">{{ market.name }}</h1>
            <p v-if="subtitle" class="md:mt-1 text-sm text-gray-600 dark:text-gray-400">{{ subtitle }}</p>
            <p v-if="market.sponsor" class="mt-0.5 text-sm text-gray-600 dark:text-gray-400">Sponsored by {{ market.sponsor }}</p>
            <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
              <span v-if="!market.is_active" class="font-medium text-gray-400 dark:text-gray-500">Inactive</span>
              <span v-if="market.liveness_score !== null" class="inline-flex items-center gap-1.5 text-gray-700 dark:text-gray-300">
                <span class="w-2 h-2 rounded-full flex-shrink-0" :class="livenessDotClass(market.liveness_score)"></span>
                {{ market.liveness_score }}/4 · {{ livenessLabel(market.liveness_score) }}
                <span v-if="market.liveness_checked_at" class="text-gray-400 dark:text-gray-500">· checked {{ formatDate(market.liveness_checked_at) }}</span>
              </span>
              <span v-else class="text-gray-400 dark:text-gray-500">Liveness not checked</span>
            </div>
          </div>
          <div class="flex items-center gap-2 shrink-0">
            <Link
              :href="route('admin.market.index')"
              class="tap-target-touch hidden md:inline-flex items-center text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white"
            >&larr; Back</Link>
            <Link
              :href="route('admin.market.edit', market.id)"
              class="tap-target-touch inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700"
            >Edit</Link>
          </div>
        </div>

        <div class="p-6 space-y-8">
          <section>
            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Schedules</h2>
            <p v-if="market.schedules.length === 0" class="mt-3 text-sm text-gray-500 dark:text-gray-400">No schedules yet.</p>
            <ul v-else class="mt-3 divide-y divide-gray-200 dark:divide-gray-700 rounded-md border border-gray-200 dark:border-gray-700">
              <li v-for="schedule in market.schedules" :key="schedule.id" class="flex items-start justify-between gap-4 px-4 py-3">
                <div class="min-w-0 space-y-0.5">
                  <div class="text-sm font-medium text-gray-900 dark:text-white">
                    {{ schedule.label ?? 'Schedule' }}
                    <span v-if="schedule.frequency" class="ml-1.5 text-xs font-normal text-gray-500 dark:text-gray-400">{{ frequencyLabel(schedule.frequency) }}</span>
                  </div>
                  <div v-if="schedule.frequency_detail" class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line">{{ schedule.frequency_detail }}</div>
                  <div v-if="dateRange(schedule)" class="text-sm text-gray-500 dark:text-gray-400">{{ dateRange(schedule) }}</div>
                  <div v-if="schedule.address_line1" class="text-sm text-gray-500 dark:text-gray-400">At {{ schedule.address_line1 }}</div>
                  <div v-if="schedule.notes" class="text-xs text-gray-400 dark:text-gray-500 whitespace-pre-line">{{ schedule.notes }}</div>
                </div>
                <span
                  class="shrink-0 inline-flex items-center gap-1.5 text-sm text-gray-700 dark:text-gray-300"
                  :title="`${livenessLabel(schedule.liveness_score)} · checked ${formatDate(schedule.liveness_checked_at)}`"
                >
                  <span class="w-2 h-2 rounded-full flex-shrink-0" :class="livenessDotClass(schedule.liveness_score)"></span>
                  {{ schedule.liveness_score }}/4
                </span>
              </li>
            </ul>
          </section>

          <section v-if="addressLines.length">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Location</h2>
            <div class="mt-3 text-sm text-gray-900 dark:text-white">
              <div v-for="line in addressLines" :key="line">{{ line }}</div>
            </div>
          </section>

          <section v-if="contactRows.length">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Contact</h2>
            <dl class="mt-3 divide-y divide-gray-200 dark:divide-gray-700">
              <div v-for="row in contactRows" :key="row.label" class="py-2 sm:grid sm:grid-cols-3 sm:gap-4">
                <dt class="text-sm text-gray-500 dark:text-gray-400">{{ row.label }}</dt>
                <dd class="mt-0.5 sm:mt-0 sm:col-span-2 text-sm text-gray-900 dark:text-white break-words">
                  <a
                    v-if="row.href"
                    :href="row.href"
                    :target="row.external ? '_blank' : undefined"
                    rel="noopener noreferrer"
                    class="text-indigo-600 dark:text-indigo-400 hover:underline"
                  >{{ row.value }}</a>
                  <template v-else>{{ row.value }}</template>
                </dd>
              </div>
            </dl>
          </section>

          <section v-if="market.description || market.vendor_fees">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">About</h2>
            <dl class="mt-3 divide-y divide-gray-200 dark:divide-gray-700">
              <div v-if="market.description" class="py-2 sm:grid sm:grid-cols-3 sm:gap-4">
                <dt class="text-sm text-gray-500 dark:text-gray-400">Description</dt>
                <dd class="mt-0.5 sm:mt-0 sm:col-span-2 text-sm text-gray-900 dark:text-white whitespace-pre-line">{{ market.description }}</dd>
              </div>
              <div v-if="market.vendor_fees" class="py-2 sm:grid sm:grid-cols-3 sm:gap-4">
                <dt class="text-sm text-gray-500 dark:text-gray-400">Vendor fees</dt>
                <dd class="mt-0.5 sm:mt-0 sm:col-span-2 text-sm text-gray-900 dark:text-white whitespace-pre-line">{{ market.vendor_fees }}</dd>
              </div>
            </dl>
          </section>

          <section v-if="market.notes || sourceLines.length">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Notes &amp; Sources</h2>
            <dl class="mt-3 divide-y divide-gray-200 dark:divide-gray-700">
              <div v-if="market.notes" class="py-2 sm:grid sm:grid-cols-3 sm:gap-4">
                <dt class="text-sm text-gray-500 dark:text-gray-400">Notes</dt>
                <dd class="mt-0.5 sm:mt-0 sm:col-span-2 text-sm text-gray-900 dark:text-white whitespace-pre-line">{{ market.notes }}</dd>
              </div>
              <div v-if="sourceLines.length" class="py-2 sm:grid sm:grid-cols-3 sm:gap-4">
                <dt class="text-sm text-gray-500 dark:text-gray-400">Sources</dt>
                <dd class="mt-0.5 sm:mt-0 sm:col-span-2 text-sm text-gray-900 dark:text-white break-words">
                  <div v-for="line in sourceLines" :key="line">
                    <a v-if="isHttpUrl(line)" :href="line" target="_blank" rel="noopener noreferrer" class="text-indigo-600 dark:text-indigo-400 hover:underline">{{ line }}</a>
                    <template v-else>{{ line }}</template>
                  </div>
                </dd>
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
      </div>
    </div>

    <MarketEventsDrawer v-if="feed.config.enabled" :feed="feed" />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AdminMobileHeader from '@/Components/Admin/AdminMobileHeader.vue'
import MarketEventFilters from './Partials/MarketEventFilters.vue'
import MarketEventList from './Partials/MarketEventList.vue'
import MarketEventsDrawer from './Partials/MarketEventsDrawer.vue'
import { useMarketEvents, type HistoryConfig } from './Partials/useMarketEvents'

defineOptions({ layout: (h, page) => h(AdminLayout, { hideBreadcrumbOnMobile: true }, () => page) })

interface ScheduleDetail {
  id: number
  label: string | null
  frequency: string | null
  frequency_detail: string | null
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
  schedules: ScheduleDetail[]
}

interface Props {
  market: MarketDetail
  frequencies: Record<string, string>
  livenessLabels: Record<number, string>
  history: HistoryConfig
}

const props = defineProps<Props>()

// One feed for both the desktop section and the mobile drawer.
const feed = useMarketEvents(props.history)

const frequencyLabel = (value: string) => props.frequencies[value] ?? value
const livenessLabel = (score: number) => props.livenessLabels[score] ?? 'Unknown'

// Same bands as Index.vue: 4 confidently active, 2-3 probably active, 0-1 likely defunct.
const livenessDotClass = (score: number) => {
  if (score >= 4) return 'bg-emerald-500 dark:bg-emerald-400'
  if (score >= 2) return 'bg-amber-500 dark:bg-amber-400'
  return 'bg-red-500 dark:bg-red-400'
}

// Eloquent's date cast serializes to a full ISO datetime; parse the
// YYYY-MM-DD prefix as a local date so a timezone offset can't shift the day.
const formatDate = (iso: string | null) => {
  if (!iso) return null
  const [y, m, d] = iso.slice(0, 10).split('-').map(Number)
  return new Date(y, m - 1, d).toLocaleDateString('en-CA', { month: 'short', day: 'numeric', year: 'numeric' })
}

const dateRange = (s: ScheduleDetail) => {
  const start = formatDate(s.start_date)
  const end = formatDate(s.end_date)
  if (start && end) return `${start} – ${end}`
  if (start) return `From ${start}`
  if (end) return `Until ${end}`
  return null
}

// Only http(s) values become links -- these fields are free text from
// admins/imports, and a "javascript:" URL must never end up in an href.
const isHttpUrl = (value: string) => /^https?:\/\//i.test(value)

const subtitle = computed(() =>
  [props.market.city, props.market.region, props.market.market_type].filter(Boolean).join(' · '),
)

const addressLines = computed(() => {
  const m = props.market
  const cityLine = [m.city, [m.province, m.postal_code].filter(Boolean).join(' ')].filter(Boolean).join(', ')
  return [m.address_line1, m.address_line2, cityLine].filter((line): line is string => !!line)
})

interface ContactRow {
  label: string
  value: string
  href?: string
  external?: boolean
}

const contactRows = computed<ContactRow[]>(() => {
  const m = props.market
  const rows: ContactRow[] = []
  if (m.phone) rows.push({ label: 'Phone', value: m.phone, href: `tel:${m.phone}` })
  if (m.manager) rows.push({ label: 'Manager', value: m.manager })
  if (m.manager_phone) rows.push({ label: 'Manager phone', value: m.manager_phone, href: `tel:${m.manager_phone}` })
  if (m.manager_email) rows.push({ label: 'Email', value: m.manager_email, href: `mailto:${m.manager_email}` })
  if (m.facebook_page) rows.push({ label: 'Facebook', value: m.facebook_page, ...(isHttpUrl(m.facebook_page) && { href: m.facebook_page, external: true }) })
  if (m.instagram_page) rows.push({ label: 'Instagram', value: m.instagram_page, ...(isHttpUrl(m.instagram_page) && { href: m.instagram_page, external: true }) })
  if (m.website) rows.push({ label: 'Website', value: m.website, ...(isHttpUrl(m.website) && { href: m.website, external: true }) })
  return rows
})

const sourceLines = computed(() =>
  (props.market.sources ?? '').split('\n').map((line) => line.trim()).filter(Boolean),
)
</script>
