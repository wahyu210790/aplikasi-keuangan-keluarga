import React from 'react';

/**
 * Simple reusable Card component.
 *
 * Props:
 * - children: content to render inside the card
 * - className: optional additional Tailwind classes to merge with the base styles
 */
export default function Card({ children, className = '' }) {
  const baseClasses =
    'bg-white border border-gray-200 rounded p-4 shadow-sm w-full';

  const combinedClasses = `${baseClasses} ${className}`.trim();

  return <div className={combinedClasses}>{children}</div>;
}
