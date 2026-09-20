<template>
  <ResponsiveModal :show="schedule !== null" max-width="lg" @close="close">
    <template #desktop>
      <form v-if="schedule" @submit.prevent="submit" class="p-6 max-h-[85dvh] overflow-y-auto">
        <h2 class="text-lg font-medium text-gray-900 dark:text-white">Edit Schedule</h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ marketName }}</p>

        <FormErrorSummary :errors="form.errors" class="mt-4" />

        <div class="mt-4 space-y-4">
          <ScheduleFieldset v-model="form" :frequencies="frequencies" :liveness-labels="livenessLabels" :errors="form.errors" />
        </div>

        <div class="mt-6 flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
          <button type="button" @click="close" class="tap-target-touch bg-gray-200 dark:bg-gray-700 py-2 px-4 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600">
            Cancel
          </button>
          <button type="submit" :disabled="form.processing" class="tap-target-touch bg-indigo-600 py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
            <span v-if="form.processing">Saving...</span>
            <span v-else>Save</span>
          </button>
        </div>
      </form>
    </template>

    <template #mobile>
      <form v-if="schedule" @submit.prevent="submit" class="flex-1 flex flex-col min-h-0">
        <div class="flex-1 overflow-y-auto px-4 -mt-2 space-y-4">
          <div>
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Edit Schedule</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ marketName }}</p>
          </div>

          <FormErrorSummary :errors="form.errors" />

          <ScheduleFieldset v-model="form" :frequencies="frequencies" :liveness-labels="livenessLabels" :errors="form.errors" />
        </div>

        <div class="shrink-0 p-4 pb-[calc(1rem+env(safe-area-inset-bottom))] space-y-3 border-t border-gray-200 dark:border-gray-700">
          <button type="submit" :disabled="form.processing" class="tap-target-touch w-full bg-indigo-600 py-3 px-4 border border-transparent rounded-md shadow-sm text-sm font-semibold text-white disabled:opacity-50">
            <span v-if="form.processing">Saving...</span>
            <span v-else>Save</span>
          </button>
          <button type="button" @click="close" class="tap-target-touch w-full bg-white dark:bg-gray-700 py-3 px-4 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-200">
            Cancel
          </button>
        </div>
      </form>
    </template>
  </ResponsiveModal>
</template>

<script setup lang="ts">
/**
 * Click-to-edit for a single schedule from the market Show page --
 * ResponsiveModal gives the desktop/mobile split for free (centered card vs.
 * full-screen takeover), so this only owns the form itself, matching
 * UpdatePriceModal.vue's shape in cultpantry/costing.
 */
import { watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import ResponsiveModal from '@/Components/ResponsiveModal.vue'
import FormErrorSummary from '@/Components/Admin/FormErrorSummary.vue'
import ScheduleFieldset, { type ScheduleForm } from './ScheduleFieldset.vue'

export interface ScheduleDetail {
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

interface Props {
  marketId: number
  marketName: string
  schedule: ScheduleDetail | null
  frequencies: Record<string, string>
  livenessLabels: Record<number, string>
}

const props = defineProps<Props>()

const emit = defineEmits<{ close: []; saved: [] }>()

const form = useForm<ScheduleForm>({
  label: '',
  frequency: null,
  frequency_detail: '',
  start_date: '',
  end_date: '',
  address_line1: '',
  notes: '',
  liveness_score: '',
  liveness_checked_at: '',
})

// Eloquent's date cast serializes to a full ISO datetime; an <input
// type="date"> wants just the YYYY-MM-DD prefix (same conversion Show.vue's
// own formatDate() does for the read-only display).
const toDateInput = (iso: string | null) => (iso ? iso.slice(0, 10) : '')

watch(
  () => props.schedule,
  (schedule) => {
    if (!schedule) return
    form.clearErrors()
    form.defaults({
      label: schedule.label ?? '',
      frequency: schedule.frequency,
      frequency_detail: schedule.frequency_detail ?? '',
      start_date: toDateInput(schedule.start_date),
      end_date: toDateInput(schedule.end_date),
      address_line1: schedule.address_line1 ?? '',
      notes: schedule.notes ?? '',
      liveness_score: schedule.liveness_score,
      liveness_checked_at: toDateInput(schedule.liveness_checked_at),
    })
    form.reset()
  },
  { immediate: true },
)

const close = () => emit('close')

const submit = () => {
  if (!props.schedule) return
  form.patch(route('admin.market.schedules.update', [props.marketId, props.schedule.id]), {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => emit('saved'),
  })
}
</script>
