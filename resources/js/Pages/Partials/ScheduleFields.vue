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
            <option :value="null" disabled>Select…</option>
            <option v-for="(label, score) in livenessLabels" :key="score" :value="Number(score)">{{ score }}/4 &middot; {{ label }}</option>
          </select>
          <p v-if="errors[`schedules.${index}.liveness_score`]" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ errors[`schedules.${index}.liveness_score`] }}</p>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Checked On</label>
          <input v-model="schedule.liveness_checked_at" type="date" :class="inputClass" />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
export interface ScheduleForm {
  label: string
  frequency: string | null
  frequency_detail: string
  start_date: string
  end_date: string
  address_line1: string
  notes: string
  liveness_score: number | null
  liveness_checked_at: string
}

defineProps<{
  frequencies: Record<string, string>
  livenessLabels: Record<number, string>
  errors: Record<string, string>
}>()

const schedules = defineModel<ScheduleForm[]>({ required: true })

const inputClass = 'mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base sm:text-sm'

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
      liveness_score: null,
      liveness_checked_at: new Date().toISOString().slice(0, 10),
    },
  ]
}

const removeSchedule = (index: number) => {
  schedules.value = schedules.value.filter((_, i) => i !== index)
}
</script>
