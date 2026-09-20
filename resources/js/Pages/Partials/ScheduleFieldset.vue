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

  <div>
    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Days &amp; Hours</label>
    <textarea v-model="schedule.frequency_detail" rows="2" placeholder="e.g. Saturdays 9am-1pm" :class="inputClass"></textarea>
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
export interface ScheduleForm {
  label: string
  frequency: string | null
  frequency_detail: string
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

const inputClass = 'mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base sm:text-sm'
</script>
