<template>
  <ResponsiveModal :show="show" max-width="md" @close="close">
    <template #desktop>
      <form @submit.prevent="submit" class="p-6">
        <h2 class="text-lg font-medium text-gray-900 dark:text-white">Ignore Market</h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ marketName }}</p>
        <p class="mt-4 text-sm text-gray-700 dark:text-gray-300">{{ explanation }}</p>

        <FormErrorSummary :errors="form.errors" class="mt-4" />

        <div class="mt-4">
          <label for="ignore-reason-desktop" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Reason <span class="font-normal text-gray-500 dark:text-gray-400">(optional)</span></label>
          <textarea id="ignore-reason-desktop" v-model="form.reason" rows="2" maxlength="255" :placeholder="placeholder" :class="inputClass"></textarea>
        </div>

        <div class="mt-6 flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
          <button type="button" @click="close" class="tap-target-touch bg-gray-200 dark:bg-gray-700 py-2 px-4 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600">
            Keep Showing It
          </button>
          <button type="submit" :disabled="form.processing" class="tap-target-touch bg-indigo-600 py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
            <span v-if="form.processing">Ignoring...</span>
            <span v-else>Ignore Market</span>
          </button>
        </div>
      </form>
    </template>

    <template #mobile>
      <form @submit.prevent="submit" class="flex-1 flex flex-col min-h-0">
        <div class="flex-1 overflow-y-auto px-4 -mt-2 space-y-4">
          <div>
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Ignore Market</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ marketName }}</p>
          </div>
          <p class="text-sm text-gray-700 dark:text-gray-300">{{ explanation }}</p>

          <FormErrorSummary :errors="form.errors" />

          <div>
            <label for="ignore-reason-mobile" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Reason <span class="font-normal text-gray-500 dark:text-gray-400">(optional)</span></label>
            <textarea id="ignore-reason-mobile" v-model="form.reason" rows="3" maxlength="255" :placeholder="placeholder" :class="inputClass"></textarea>
          </div>
        </div>

        <div class="shrink-0 p-4 pb-[calc(1rem+env(safe-area-inset-bottom))] space-y-3 border-t border-gray-200 dark:border-gray-700">
          <button type="submit" :disabled="form.processing" class="tap-target-touch w-full bg-indigo-600 py-3 px-4 border border-transparent rounded-md shadow-sm text-sm font-semibold text-white disabled:opacity-50">
            <span v-if="form.processing">Ignoring...</span>
            <span v-else>Ignore Market</span>
          </button>
          <button type="button" @click="close" class="tap-target-touch w-full bg-white dark:bg-gray-700 py-3 px-4 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-200">
            Keep Showing It
          </button>
        </div>
      </form>
    </template>
  </ResponsiveModal>
</template>

<script setup lang="ts">
/**
 * Ignore a market from its Show page, with an optional reason. Ignoring is
 * the admin's own "not relevant to us" -- separate from Active/Inactive,
 * which tracks whether the market still runs. Un-ignoring needs no dialog
 * (nothing is lost either way), so it's a plain button on the page.
 */
import { watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import ResponsiveModal from '@/Components/ResponsiveModal.vue'
import FormErrorSummary from '@/Components/Admin/FormErrorSummary.vue'

const props = defineProps<{
  show: boolean
  marketId: number
  marketName: string
}>()

const emit = defineEmits<{ close: [] }>()

const explanation = "It'll be hidden from the markets list and the calendar. Imports still keep its details up to date, and you can un-ignore it any time."
const placeholder = 'e.g. Not an artisan market, too far away'
const inputClass = 'mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base sm:text-sm'

const form = useForm({ ignored: true, reason: '' })

// A fresh, empty form each time it opens.
watch(
  () => props.show,
  (show) => {
    if (show) {
      form.clearErrors()
      form.reset()
    }
  },
)

const close = () => emit('close')

const submit = () => {
  form.patch(route('admin.market.ignore', props.marketId), {
    preserveScroll: true,
    onSuccess: () => emit('close'),
  })
}
</script>
