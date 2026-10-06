import React, { createContext, useContext, useState, useEffect } from 'react';
import { useAuth } from './AuthContext';

// Create the HouseholdContext
const HouseholdContext = createContext(null);

export const HouseholdProvider = ({ children }) => {
  const { households, loading: authLoading } = useAuth();

  // Derive initial active household id (first household if any)
  const [activeHouseholdId, setActiveHouseholdId] = useState(() => {
    return households && households.length > 0 ? households[0].id : null;
  });

  // Update activeHouseholdId when households list changes
  useEffect(() => {
    // If current active id not in new list, reset to first or null
    const ids = households.map((h) => h.id);
    if (!ids.includes(activeHouseholdId)) {
      setActiveHouseholdId(ids.length > 0 ? ids[0] : null);
    }
    // If there are no households, ensure null
    if (ids.length === 0) {
      setActiveHouseholdId(null);
    }
  }, [households]);

  // Guarded setter that only accepts IDs present in households
  const safeSetActiveHouseholdId = (id) => {
    if (households.some((h) => h.id === id)) {
      setActiveHouseholdId(id);
    }
    // otherwise ignore silently
  };

  const activeHousehold = households.find((h) => h.id === activeHouseholdId) || null;

  const value = {
    households,
    activeHousehold,
    activeHouseholdId,
    setActiveHouseholdId: safeSetActiveHouseholdId,
    loading: authLoading,
  };

  return (
    <HouseholdContext.Provider value={value}>
      {children}
    </HouseholdContext.Provider>
  );
};

export const useHousehold = () => {
  const context = useContext(HouseholdContext);
  if (!context) {
    throw new Error('useHousehold must be used within a HouseholdProvider');
  }
  return context;
};
