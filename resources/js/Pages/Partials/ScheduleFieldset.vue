<template>
  <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div>
      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Label</label>
      <input v-model="schedule.label" type="text" placeholder="e.g. Summer Market" :class="inputClass" />
    </div>
    <div>
      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Frequency</label>
      <select v-model="schedule.frequency" :class="inputClass">
        <option :value="null">—</option>
        <option v-for="(label, value) in frequencies" :key="value" :value="value">{{ label }}</option>
      </select>
    </div>
  </div>

  <div v-if="schedule.frequency !== 'one_time'">
    <span class="block text-sm font-medium text-gray-700 dark:text-gray-300">Market days</span>
    <div class="mt-1 flex flex-wrap gap-2" role="group" aria-label="Market days">
      <button
        v-for="(name, day) in WEEKDAY_NAMES"
        :key="day"
        type="button"
        :aria-pressed="schedule.weekdays.includes(day)"
        @click="toggleWeekday(day)"
        :class="[
          'tap-target-touch min-w-[3rem] px-3 py-1.5 rounded-md border text-sm font-medium',
          schedule.weekdays.includes(day)
            ? 'bg-indigo-600 border-indigo-600 text-white'
            : 'bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600',
        ]"
      >{{ name }}</button>
    </div>
    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Needed for the market to show on the calendar.</p>
  </div>

  <div v-if="schedule.frequency === 'monthly'">
    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Which week of the month</label>
    <select v-model="schedule.week_of_month" :class="inputClass">
      <option :value="null">—</option>
      <option v-for="(label, value) in WEEKS_OF_MONTH" :key="value" :value="Number(value)">{{ label }}</option>
    </select>
  </div>

  <div class="grid grid-cols-2 gap-4">
    <div>
      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Opens</label>
      <input v-model="schedule.start_time" type="time" :class="inputClass" />
    </div>
    <div>
      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Closes</label>
      <input v-model="schedule.end_time" type="time" :class="inputClass" />
    </div>
  </div>

  <div>
    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Days &amp; Hours note</label>
    <textarea v-model="schedule.frequency_detail" rows="2" placeholder="e.g. Saturdays 9am-1pm, closed long weekends" :class="inputClass"></textarea>
  </div>

  <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div>
      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Starts</label>
      <input v-model="schedule.start_date" type="date" :class="inputClass" />
    </div>
    <div>
      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Ends</label>
      <input v-model="schedule.end_date" type="date" :class="inputClass" />
    </div>
  </div>

  <div>
    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Location, if different from the market's address</label>
    <input v-model="schedule.address_line1" type="text" :class="inputClass" />
  </div>

  <div>
    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Notes</label>
    <textarea v-model="schedule.notes" rows="2" :class="inputClass"></textarea>
  </div>

  <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div>
      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Liveness Score *</label>
      <select v-model="schedule.liveness_score" required :class="inputClass">
        <option value="" disabled>Select…</option>
        <option v-for="(label, score) in livenessLabels" :key="score" :value="Number(score)">{{ score }}/4 &middot; {{ label }}</option>
      </select>
      <p v-if="errors[`${errorPrefix}liveness_score`]" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ errors[`${errorPrefix}liveness_score`] }}</p>
    </div>
    <div>
      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Checked On</label>
      <input v-model="schedule.liveness_checked_at" type="date" :class="inputClass" />
    </div>
  </div>
</template>

<script setup lang="ts">
/**
 * One schedule's fields -- no add/remove/wrapper chrome, just the inputs.
 * Shared by ScheduleFields.vue (the repeater on the full Create/Edit form,
 * one instance per row) and ScheduleEditModal.vue (a single schedule,
 * click-to-edit from the Show page), so the field list only lives in one
 * place.
 */
import { WEEKDAY_NAMES } from './scheduleSummary'

export interface ScheduleForm {
  label: string
  frequency: string | null
  frequency_detail: string
  // 0 = Sunday ... 6 = Saturday, same as MarketSchedule::WEEKDAYS.
  weekdays: number[]
  week_of_month: number | null
  start_time: string
  end_time: string
  start_date: string
  end_date: string
  address_line1: string
  notes: string
  // '' (not null) while unset: a required <select> only reports
  // valueMissing when its selected option's value is the empty string.
  liveness_score: number | ''
  liveness_checked_at: string
}

withDefaults(defineProps<{
  frequencies: Record<string, string>
  livenessLabels: Record<number, string>
  errors: Record<string, string>
  // ScheduleFields.vue's repeater keys its errors `schedules.${index}.field`
  // (Laravel's array-validation shape); a lone schedule being edited on its
  // own has no index, so its error keys are just `field`. This is the one
  // difference between the two call sites, passed in rather than guessed.
  errorPrefix?: string
}>(), {
  errorPrefix: '',
})

const schedule = defineModel<ScheduleForm>({ required: true })

const WEEKS_OF_MONTH: Record<number, string> = { 1: '1st', 2: '2nd', 3: '3rd', 4: '4th', [-1]: 'Last' }

const toggleWeekday = (day: number) => {
  const days = schedule.value.weekdays
  schedule.value.weekdays = days.includes(day) ? days.filter((d) => d !== day) : [...days, day].sort()
}

const inputClass = 'mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base sm:text-sm'
</script>
