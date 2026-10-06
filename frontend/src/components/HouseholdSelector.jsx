import React from 'react';
import { useHousehold } from '../context/HouseholdContext';

export default function HouseholdSelector() {
  const { households, activeHouseholdId, setActiveHouseholdId, loading } = useHousehold();

  // Loading – render nothing to keep header clean
  if (loading) {
    return null;
  }

  // No households – render nothing
  if (!households || households.length === 0) {
    return null;
  }

  // Single household – display its name without a select
  if (households.length === 1) {
    const household = households[0];
    return (
      <span className="text-sm font-medium text-white">
        {household.name}
      </span>
    );
  }

  // Multiple households – render a native select
  const handleChange = (e) => {
    const id = Number(e.target.value);
    setActiveHouseholdId(id);
  };

  return (
    <select
      value={activeHouseholdId ?? ''}
      onChange={handleChange}
      className="bg-white text-gray-800 rounded px-2 py-1"
      aria-label="Pilih household"
    >
      {households.map((h) => (
        <option key={h.id} value={h.id}>
          {h.name}
        </option>
      ))}
    </select>
  );
}
