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
          <div class="min-w-0 flex-1 space-y-2">
            <!-- Name is only inline-editable on desktop: on mobile the name
                 lives in AdminMobileHeader above, a separate shared
                 component whose title is a static string set at page load,
                 not worth wiring up for one field here. -->
            <div class="hidden md:block">
              <InlineField
                label="Name"
                :model-value="market.name"
                value-class="text-2xl font-semibold text-gray-900 dark:text-white"
                :on-save="(v) => saveField('name', v)"
              />
            </div>

            <div class="flex flex-wrap items-center gap-x-1.5 gap-y-1 text-sm">
              <InlineField label="City" :model-value="market.city" :datalist-options="props.cities" :on-save="(v) => saveField('city', v)" />
              <span class="text-gray-300 dark:text-gray-600">·</span>
              <InlineField label="Region" type="select" :model-value="market.region" :options="regionOptions" :on-save="(v) => saveField('region', v)" />
              <span class="text-gray-300 dark:text-gray-600">·</span>
              <InlineField label="Market Type" :model-value="market.market_type" :datalist-options="props.marketTypes" :on-save="(v) => saveField('market_type', v)" />
            </div>

            <p class="flex items-center gap-1 text-sm text-gray-600 dark:text-gray-400">
              <span v-if="market.sponsor">Sponsored by</span>
              <InlineField label="Sponsor" :model-value="market.sponsor" placeholder="Add a sponsor" :on-save="(v) => saveField('sponsor', v)" />
            </p>

            <!-- flex-col on mobile: Active, the liveness score and the
                 checked-on date are three separate tap targets that don't
                 comfortably share one line at phone width -- wrapping mid-
                 line (the sm:flex-row default) put "· checked" on its own
                 half-empty row, which read as broken rather than just
                 wrapped. Stacked, each gets its own full-width line. -->
            <div class="flex flex-col sm:flex-row sm:flex-wrap items-start sm:items-center gap-x-3 gap-y-1 text-sm">
              <button
                type="button"
                class="tap-target-touch inline-flex items-center gap-1.5 font-medium disabled:opacity-50"
                :class="market.is_active ? 'text-gray-700 dark:text-gray-300' : 'text-gray-400 dark:text-gray-500'"
                :disabled="togglingActive"
                @click="toggleActive"
              >
                <span class="w-2 h-2 rounded-full flex-shrink-0" :class="market.is_active ? 'bg-emerald-500 dark:bg-emerald-400' : 'bg-gray-400 dark:bg-gray-500'"></span>
                {{ market.is_active ? 'Active' : 'Inactive' }}
              </button>
              <span class="inline-flex items-center gap-1.5 text-gray-700 dark:text-gray-300">
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
              </span>
              <span v-if="market.liveness_score !== null" class="inline-flex items-center gap-1 text-gray-400 dark:text-gray-500">
                · checked
                <InlineField
                  label="Checked On"
                  type="date"
                  :model-value="market.liveness_checked_at?.slice(0, 10) ?? null"
                  :display-value="formatDate(market.liveness_checked_at)"
                  :on-save="(v) => saveField('liveness_checked_at', v)"
                />
              </span>
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
      </div>
    </div>

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
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AdminMobileHeader from '@/Components/Admin/AdminMobileHeader.vue'
import InlineField from './Partials/InlineField.vue'
import MarketEventFilters from './Partials/MarketEventFilters.vue'
import MarketEventList from './Partials/MarketEventList.vue'
import MarketEventsDrawer from './Partials/MarketEventsDrawer.vue'
import ScheduleEditModal from './Partials/ScheduleEditModal.vue'
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

const togglingActive = ref(false)

const toggleActive = async () => {
  togglingActive.value = true
  try {
    // A real boolean, not '1'/'' -- Laravel's 'boolean' rule doesn't accept
    // an empty string (and the ConvertEmptyStringsToNull middleware turns it
    // into null first anyway), so that pairing silently failed validation
    // every time with no visible error.
    await saveField('is_active', !market.value.is_active)
  } catch {
    // The badge just stays as it was -- saveField's own errors aren't
    // surfaced anywhere for this control, so silently not-toggling is the
    // honest result rather than claiming a change that didn't happen.
  } finally {
    togglingActive.value = false
  }
}
</script>
