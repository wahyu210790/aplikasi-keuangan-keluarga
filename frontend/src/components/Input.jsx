import React from 'react';

/**
 * Reusable Input component.
 *
 * Props (all passed through to the underlying <input> element):
 * - id
 * - name
 * - type (default: "text")
 * - value
 * - onChange
 * - placeholder
 * - disabled
 * - required
 * - className (additional Tailwind classes)
 * - aria-label
 */
export default function Input({
  id,
  name,
  type = 'text',
  value,
  onChange,
  placeholder = '',
  disabled = false,
  required = false,
  className = '',
  'aria-label': ariaLabel,
}) {
  const baseClasses =
    'w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:bg-gray-100 disabled:cursor-not-allowed';

  const combinedClasses = `${baseClasses} ${className}`.trim();

  return (
    <input
      id={id}
      name={name}
      type={type}
      value={value}
      onChange={onChange}
      placeholder={placeholder}
      disabled={disabled}
      required={required}
      aria-label={ariaLabel}
      className={combinedClasses}
    />
  );
}
