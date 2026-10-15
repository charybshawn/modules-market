// One colour per market type, shared by the calendar chips and the day modal.
// Mid-dark so white chip text stays readable.
const HEX: Record<string, string> = {
  "Farmers' market": '#16a34a',
  'Artisan market': '#7c3aed',
  'Craft fair': '#db2777',
  'Holiday market': '#dc2626',
  'Night market': '#1d4ed8',
  'Festival market': '#ea580c',
  'Vintage & flea market': '#0f766e',
  'Seed & plant sale': '#a16207',
}

export const UNTYPED_HEX = '#6b7280'

export const marketTypeHex = (type: string | null | undefined) => (type && HEX[type]) || UNTYPED_HEX
