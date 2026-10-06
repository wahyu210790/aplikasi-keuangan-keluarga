import React from 'react';
import { BrowserRouter } from 'react-router-dom';
import { AuthProvider } from './context/AuthContext';
import { HouseholdProvider } from './context/HouseholdContext';
import AppRoutes from './routes/AppRoutes';

export default function App() {
  return (
    <BrowserRouter>
      <AuthProvider>
        <HouseholdProvider>
          <AppRoutes />
        </HouseholdProvider>
      </AuthProvider>
    </BrowserRouter>
  );
}
