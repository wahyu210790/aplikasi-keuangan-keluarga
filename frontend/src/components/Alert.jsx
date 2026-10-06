import React from 'react';

/**
 * Reusable Alert component.
 *
 * Props:
 * - children: the message/content to display
 * - type: one of "success", "error", "warning", "info" (default "error")
 * - className: additional Tailwind classes to merge with the base styles
 */
export default function Alert({ children, type = 'error', className = '' }) {
  // Base classes shared by all alert types
  const baseClasses = 'rounded p-3 text-sm font-medium';

  // Tailwind classes per alert type
  const typeClasses = {
    success: 'bg-green-100 text-green-800 border border-green-200',
    error:   'bg-red-100 text-red-800 border border-red-200',
    warning: 'bg-yellow-100 text-yellow-800 border border-yellow-200',
    info:    'bg-blue-100 text-blue-800 border border-blue-200',
  };

  const role = type === 'info' ? 'status' : 'alert';

  const combined = `${baseClasses} ${typeClasses[type] || typeClasses.error} ${className}`.trim();

  return (
    <div role={role} className={combined}>
      {children}
    </div>
  );
}
