export function filterToggleClass(active: boolean): string {
  return `border-0 bg-transparent p-0 text-[14px] font-semibold leading-[18px] ${
    active
      ? 'text-ce-black underline decoration-[1.5px] underline-offset-[3px]'
      : 'text-ce-black'
  }`
}
