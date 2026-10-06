import React from 'react';

/**
 * Reusable Button component.
 *
 * Props:
 * - children: button label/content
 * - type: HTML button type (button | submit | reset) – defaults to "button"
 * - disabled: boolean flag
 * - onClick: click handler
 * - className: additional Tailwind classes to merge with the base styles
 */
export default function Button({
  children,
  type = 'button',
  disabled = false,
  onClick,
  className = '',
}) {
  const baseClasses =
    'px-4 py-2 bg-indigo-600 text-white rounded transition-colors duration-200 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed';

  const combinedClasses = `${baseClasses} ${className}`.trim();

  return (
    <button
      type={type}
      disabled={disabled}
      onClick={onClick}
      className={combinedClasses}
    >
      {children}
    </button>
  );
}
