<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Schedules</h2>
      <button
        type="button"
        class="tap-target-touch inline-flex items-center text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300"
        @click="addSchedule"
      >
        + Add Schedule
      </button>
    </div>

    <p v-if="schedules.length === 0" class="text-sm text-gray-500 dark:text-gray-400">
      No schedules yet. Add one for each distinct run of the market -- e.g. a summer market, a winter market, a one-off holiday fair.
    </p>

    <div
      v-for="(schedule, index) in schedules"
      :key="index"
      class="rounded-md border border-gray-200 dark:border-gray-700 p-4 space-y-4"
    >
      <div class="flex items-center justify-between gap-3">
        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Schedule {{ index + 1 }}</span>
        <button
          type="button"
          class="tap-target-touch text-sm text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300"
          @click="removeSchedule(index)"
        >
          Remove
        </button>
      </div>

      <ScheduleFieldset
        v-model="schedules[index]"
        :frequencies="frequencies"
        :liveness-labels="livenessLabels"
        :errors="errors"
        :error-prefix="`schedules.${index}.`"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import ScheduleFieldset, { type ScheduleForm } from './ScheduleFieldset.vue'

export type { ScheduleForm }

defineProps<{
  frequencies: Record<string, string>
  livenessLabels: Record<number, string>
  errors: Record<string, string>
}>()

const schedules = defineModel<ScheduleForm[]>({ required: true })

const addSchedule = () => {
  schedules.value = [
    ...schedules.value,
    {
      label: '',
      frequency: null,
      frequency_detail: '',
      start_date: '',
      end_date: '',
      address_line1: '',
      notes: '',
      liveness_score: '',
      liveness_checked_at: new Date().toISOString().slice(0, 10),
    },
  ]
}

const removeSchedule = (index: number) => {
  schedules.value = schedules.value.filter((_, i) => i !== index)
}
</script>
