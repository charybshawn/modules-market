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
          <div class="text-sm font-bold text-gray-800">Total Markets</div>
          <div class="mt-1 text-4xl font-extrabold text-emerald-600">{{ props.markets.length }}</div>
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
        <DataTable
          :columns="columns"
          :items="props.markets"
          table-id="market-markets"
          item-key="id"
          searchable
          search-placeholder="Search markets..."
          empty-message="No markets yet."
          empty-action-label="Add your first market"
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
              <div v-for="schedule in item.schedules.slice(0, 2)" :key="schedule.id" class="truncate">{{ scheduleSummary(schedule) }}</div>
              <div v-if="item.schedules.length > 2" class="text-xs text-gray-400 dark:text-gray-500">+{{ item.schedules.length - 2 }} more</div>
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
const scheduleSummary = (schedule: ScheduleRow) =>
  [schedule.label, schedule.frequency ? frequencyLabel(schedule.frequency) : null].filter(Boolean).join(' · ') || 'Schedule'
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

const columns = computed<Column[]>(() => [
  { key: 'name', label: 'Market', sortable: true },
  { key: 'city', label: 'City', sortable: true, filterable: true, options: props.cities },
  { key: 'region', label: 'Region', sortable: true, filterable: true, options: props.regions },
  { key: 'market_type', label: 'Type', hideable: true, filterable: true, options: props.marketTypes },
  { key: 'schedules', label: 'Schedules', hideable: true },
  { key: 'phone', label: 'Phone', hideable: true },
  {
    // No "Not checked" option here, deliberately -- DataTable's own
    // isFilterValueActive() treats an empty-string filter value as "no
    // filter selected" (confirmed live: selecting an option bound to ''
    // silently filters nothing, since it reads as identical to Any). That
    // collides with String(null ?? '') also being '', which is what a
    // genuinely unchecked market's score compares as -- there's no value
    // this column's own options can carry that both means "unchecked" to
    // the comparison AND registers as "a filter is active" to DataTable.
    // Sort ascending on this column instead to find unchecked/low-score
    // markets (they sort first).
    key: 'liveness_score', label: 'Liveness', sortable: true, hideable: true, filterable: true,
    options: Object.entries(props.livenessLabels).map(([value, label]) => ({ value, label: `${value}/4 · ${label}` })),
  },
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
